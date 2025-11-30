<?php
session_start();
require_once '../../includes/db.php';

if(!isset($_SESSION['admin_id'])){
    die("Accès refusé.");
}

$seasonId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if(!$seasonId) die("ID de saison invalide.");

// --- Récupérer saison ---
$stmt = $pdo->prepare("SELECT * FROM football_seasons WHERE id_season=?");
$stmt->execute([$seasonId]);
$season = $stmt->fetch(PDO::FETCH_ASSOC);
if(!$season) die("Saison introuvable.");

// --- Années académiques ---
$academicYears = $pdo->query("SELECT id,label FROM academic_years ORDER BY label DESC")->fetchAll(PDO::FETCH_ASSOC);

// --- Équipes ---
$teamsList = $pdo->prepare("SELECT * FROM football_teams WHERE season_id=? ORDER BY name ASC");
$teamsList->execute([$seasonId]);
$teams = $teamsList->fetchAll(PDO::FETCH_ASSOC);

// --- Poules ---
$poolsStmt = $pdo->prepare("SELECT * FROM pools WHERE season_id=? ORDER BY name ASC");
$poolsStmt->execute([$seasonId]);
$pools = $poolsStmt->fetchAll(PDO::FETCH_ASSOC);

// --- Pool teams ---
$poolTeams = [];
foreach($pools as $pool){
    $stmt = $pdo->prepare("SELECT pt.team_id, t.name FROM pool_teams pt JOIN football_teams t ON pt.team_id=t.team_id WHERE pt.pool_id=?");
    $stmt->execute([$pool['pool_id']]);
    $poolTeams[$pool['name']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// --- Saison settings ---
$stmt = $pdo->prepare("SELECT match_type FROM season_settings WHERE season_id=?");
$stmt->execute([$seasonId]);
$seasonSetting = $stmt->fetch(PDO::FETCH_ASSOC);
$matchType = $seasonSetting['match_type'] ?? 'simple';

// --- Matchs existants ---
$matchesStmt = $pdo->prepare("SELECT * FROM matches WHERE season_id=? ORDER BY pool_id, match_id ASC");
$matchesStmt->execute([$seasonId]);
$matches = $matchesStmt->fetchAll(PDO::FETCH_ASSOC);

// --- POST: Enregistrer modifications ---
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['save_season'])){
    try{
        $pdo->beginTransaction();

        // Mettre à jour saison
        $stmt = $pdo->prepare("UPDATE football_seasons SET label=?, academic_year_id=?, is_active=?, updated_at=NOW() WHERE id_season=?");
        $stmt->execute([$_POST['season_label'], $_POST['academic_year_id'], isset($_POST['is_active'])?1:0, $seasonId]);

        // Équipes: suppression, modification, ajout
        $existingTeamIds = array_column($teams,'team_id');
        $postedTeamIds = array_column($_POST['teams'],'id');
        $toDelete = array_diff($existingTeamIds,$postedTeamIds);
        if($toDelete) $pdo->exec("DELETE FROM football_teams WHERE team_id IN (".implode(',', $toDelete).")");

        $teamIdMap = [];
        foreach($_POST['teams'] as $idx=>$team){
            if(!empty($team['id'])){
                $stmt = $pdo->prepare("UPDATE football_teams SET name=?, coach=? WHERE team_id=?");
                $stmt->execute([$team['name'],$team['coach'],$team['id']]);
                $teamIdMap[$idx]=$team['id'];
            } else {
                $stmt = $pdo->prepare("INSERT INTO football_teams (season_id,name,coach,created_by) VALUES (?,?,?,?)");
                $stmt->execute([$seasonId,$team['name'],$team['coach'],$_SESSION['admin_id']]);
                $teamIdMap[$idx]=$pdo->lastInsertId();
            }
        }

        // Poules et pool_teams
        $existingPoolIds = array_column($pools,'pool_id');
        $postedPoolIds = array_column($_POST['pools'],'id');
        $toDeletePools = array_diff($existingPoolIds,$postedPoolIds);
        if($toDeletePools) $pdo->exec("DELETE FROM pools WHERE pool_id IN (".implode(',',$toDeletePools).")");

        $poolMap = [];
        foreach($_POST['pools'] as $poolName=>$poolData){
            if(!empty($poolData['id'])){
                $stmt = $pdo->prepare("UPDATE pools SET name=?, advance_count=? WHERE pool_id=?");
                $stmt->execute([$poolData['name'],$poolData['advance_count'],$poolData['id']]);
                $poolMap[$poolData['name']] = $poolData['id'];
            } else {
                $stmt = $pdo->prepare("INSERT INTO pools (season_id,name,advance_count) VALUES (?,?,?)");
                $stmt->execute([$seasonId,$poolData['name'],$poolData['advance_count']]);
                $poolMap[$poolData['name']] = $pdo->lastInsertId();
            }
            if(!empty($poolData['teams'])){
                $pdo->prepare("DELETE FROM pool_teams WHERE pool_id=?")->execute([$poolMap[$poolData['name']]]);
                foreach($poolData['teams'] as $teamIndex){
                    $stmt = $pdo->prepare("INSERT INTO pool_teams (pool_id,team_id,season_id) VALUES (?,?,?)");
                    $stmt->execute([$poolMap[$poolData['name']], $teamIdMap[$teamIndex], $seasonId]);
                }
            }
        }

        // Type de match
        $stmt = $pdo->prepare("UPDATE season_settings SET match_type=? WHERE season_id=?");
        $stmt->execute([$_POST['match_type'],$seasonId]);

        // Matchs existants: mettre à jour les dates
        if(!empty($_POST['match_dates'])){
            foreach($_POST['match_dates'] as $poolName=>$poolData){
                foreach($poolData as $team1Idx=>$team2Data){
                    foreach($team2Data as $team2Idx=>$date){
                        $team1Id = $teamIdMap[$team1Idx];
                        $team2Id = $teamIdMap[$team2Idx];
                        $stmt = $pdo->prepare("UPDATE matches SET match_datetime=? WHERE season_id=? AND team1_id=? AND team2_id=?");
                        $stmt->execute([$date,$seasonId,$team1Id,$team2Id]);
                    }
                }
            }
        }

        $pdo->commit();
        $success = "Saison mise à jour avec succès !";

    }catch(Exception $e){
        $pdo->rollBack();
        $error = "Erreur : ".$e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Modifier Saison</title>
    <style>
        .section { border:1px solid #ccc; padding:15px; margin-bottom:15px; }
        .team-entry,.team-select{margin-bottom:5px; display:block;}
        table{border-collapse:collapse;width:100%;}
        th,td{border:1px solid #999;padding:5px;text-align:center;}
    </style>
</head>
<body>
<h1>Modifier saison <?= htmlspecialchars($season['label']) ?></h1>
<?php if(!empty($success)) echo "<p style='color:green'>$success</p>"; ?>
<?php if(!empty($error)) echo "<p style='color:red'>$error</p>"; ?>

<form method="post" id="seasonForm">
<!-- 1️⃣ Saison -->
<div class="section">
<label>Label : <input type="text" name="season_label" value="<?= htmlspecialchars($season['label']) ?>" required></label><br>
<label>Année académique :
<select name="academic_year_id">
<?php foreach($academicYears as $year): ?>
<option value="<?= $year['id'] ?>" <?= $year['id']==$season['academic_year_id']?'selected':'' ?>><?= $year['label'] ?></option>
<?php endforeach; ?>
</select>
</label><br>
<label>Activer : <input type="checkbox" name="is_active" <?= $season['is_active']?'checked':'' ?>></label>
</div>

<!-- 2️⃣ Équipes -->
<div class="section" id="teams-container">
<?php foreach($teams as $idx=>$team): ?>
<div class="team-entry">
<input type="hidden" name="teams[<?= $idx ?>][id]" value="<?= $team['team_id'] ?>">
Nom: <input type="text" name="teams[<?= $idx ?>][name]" value="<?= htmlspecialchars($team['name']) ?>" required>
Coach: <input type="text" name="teams[<?= $idx ?>][coach]" value="<?= htmlspecialchars($team['coach']) ?>" required>
<button type="button" onclick="removeEntry(this)">Supprimer</button>
</div>
<?php endforeach; ?>
</div>
<button type="button" onclick="addTeam()">+ Ajouter équipe</button>

<!-- 3️⃣ Poules -->
<div class="section" id="pools-container">
<?php foreach($pools as $pool): ?>
<div class="pool" id="pool_<?= htmlspecialchars($pool['name']) ?>">
<input type="hidden" name="pools[<?= $pool['name'] ?>][id]" value="<?= $pool['pool_id'] ?>">
Nom: <input type="text" name="pools[<?= $pool['name'] ?>][name]" value="<?= htmlspecialchars($pool['name']) ?>" required>
Équipes qualifiées: <input type="number" name="pools[<?= $pool['name'] ?>][advance_count]" value="<?= $pool['advance_count'] ?>">
<button type="button" onclick="removeEntry(this)">Supprimer</button>
<h4>Répartir équipes :</h4>
<div class="pool-team-list">
<?php
$teamIndices = array_flip(array_column($teams,'team_id'));
foreach($poolTeams[$pool['name']] as $pt):
$idx = $teamIndices[$pt['team_id']] ?? -1;
if($idx>=0):
?>
<label><input type="checkbox" name="pools[<?= $pool['name'] ?>][teams][]" value="<?= $idx ?>" checked> <?= htmlspecialchars($pt['name']) ?></label>
<?php endif; endforeach; ?>
</div>
</div>
<?php endforeach; ?>
</div>
<button type="button" onclick="addPool()">+ Ajouter poule</button>

<!-- 4️⃣ Type de match -->
<div class="section">
<label><input type="radio" name="match_type" value="simple" <?= $matchType=='simple'?'checked':'' ?>> Match simple</label><br>
<label><input type="radio" name="match_type" value="aller_retour" <?= $matchType=='aller_retour'?'checked':'' ?>> Aller-Retour</label><br>
<label><input type="radio" name="match_type" value="custom" <?= $matchType=='custom'?'checked':'' ?>> Personnalisé</label>
</div>

<!-- 5️⃣ Matchs générés -->
<div class="section">
<h3>Matchs générés</h3>
<div id="matches-container"></div>
</div>

<button type="submit" name="save_season">Enregistrer toute la saison</button>
</form>

<script>
// JS simplifié pour gérer équipes/poules et matchs
let teamIndex = <?= count($teams) ?>;
let poolIndex = 65;

// Ajouter équipe
function addTeam() {
    const container = document.getElementById('teams-container');
    const div = document.createElement('div');
    div.className = 'team-entry';
    div.innerHTML = `
        Nom : <input type="text" class="team-name" name="teams[${teamIndex}][name]" required>
        Coach : <input type="text" name="teams[${teamIndex}][coach]" required>
        <button type="button" onclick="removeEntry(this)">Supprimer</button>
    `;
    container.appendChild(div);
    teamIndex++;
    updatePoolTeamLists();
}

// Supprimer équipe/poule
function removeEntry(btn) {
    btn.parentElement.remove();
    updatePoolTeamLists();
}

// Récupère équipes dynamiques
function getCurrentTeams() {
    const names = [];
    document.querySelectorAll('#teams-container .team-entry .team-name').forEach((input, idx) => {
        if(input.value.trim() !== '') names.push({name: input.value.trim(), index: idx});
    });
    return names;
}

// Ajouter poule
function addPool() {
    const poolName = String.fromCharCode(poolIndex);
    const container = document.getElementById('pools-container');
    const div = document.createElement('div');
    div.className = "pool";
    div.id = "pool_" + poolName;

    div.innerHTML = `
        Nom : <input type="text" name="pools[${poolName}]" value="${poolName}" required>
        Équipes qualifiées : <input type="number" name="advance_count[${poolName}]" value="2" min="1">
        <button type="button" onclick="removeEntry(this)">Supprimer</button>
        <h4>Répartir les équipes :</h4>
        <div class="pool-team-list"></div>
    `;
    container.appendChild(div);
    updatePoolTeamLists();
    poolIndex++;
}

// Mettre à jour équipes dans poules
function updatePoolTeamLists() {
    const teams = getCurrentTeams();
    document.querySelectorAll('.pool .pool-team-list').forEach(poolDiv => {
        poolDiv.innerHTML = '';
        const poolId = poolDiv.parentElement.id.replace('pool_', '');
        teams.forEach(team => {
            poolDiv.innerHTML += `
                <label class="team-select">
                    <input type="checkbox" name="pool_teams[${poolId}][]" value="${team.index}"> ${team.name}
                </label>
            `;
        });
    });
    updateExclusiveTeams();
    generateMatches();
}

// Exclusivité
function updateExclusiveTeams() {
    const allCheckboxes = document.querySelectorAll('.pool input[type="checkbox"]');
    allCheckboxes.forEach(cb => cb.disabled = false);
    const selectedTeams = new Set();
    allCheckboxes.forEach(cb => { if(cb.checked) selectedTeams.add(cb.value); });
    allCheckboxes.forEach(cb => { if(!cb.checked && selectedTeams.has(cb.value)) cb.disabled = true; });
}

// Met à jour les options personnalisées et régénère les matchs
function toggleCustom(show) {
    const container = document.getElementById('custom-options');
    container.style.display = show ? 'block' : 'none';
    container.innerHTML = '';

    if(show){
        container.innerHTML = "<h4>Choisir les poules pour Aller-Retour :</h4>";
        for(let i = 65; i < poolIndex; i++){
            const poolName = String.fromCharCode(i);
            container.innerHTML += `<label>
                <input type="checkbox" name="custom_pools[${poolName}]" value="1" onchange="generateMatches()"> Poule ${poolName}
            </label><br>`;
        }
    }
    generateMatches(); // Toujours générer les matchs après toggle
}

// Mise à jour quand on coche/décoche une équipe ou une poule custom
document.addEventListener('change', function(e){
    if(e.target.matches('.pool input[type="checkbox"]') || e.target.matches('#custom-options input[type="checkbox"]')){
        updateExclusiveTeams();
        generateMatches();
    }
});



// Afficher les matchs existants
const matchesData = <?= json_encode($matches) ?>;
const teamMap = {};
<?php foreach($teams as $idx=>$team): ?>
teamMap[<?= $team['team_id'] ?>] = {index: <?= $idx ?>, name: "<?= htmlspecialchars($team['name']) ?>"};
<?php endforeach; ?>

function renderMatches(){
    const container = document.getElementById('matches-container');
    container.innerHTML = '';
    matchesData.forEach(m=>{
        if(!teamMap[m.team1_id] || !teamMap[m.team2_id]) return;
        container.innerHTML += `<div>
            ${teamMap[m.team1_id].name} vs ${teamMap[m.team2_id].name} :
            <input type="date" name="match_dates[pool${m.pool_id}][${teamMap[m.team1_id].index}][${teamMap[m.team2_id].index}]" value="${m.match_datetime?m.match_datetime.split(' ')[0]:''}">
        </div>`;
    });
}
renderMatches();

function generateMatches() {
    const container = document.getElementById('matches-container');
    container.innerHTML = '';
    const matchType = document.querySelector('input[name="match_type"]:checked').value;
    const pools = document.querySelectorAll('.pool');
    if(pools.length === 0) return;

    pools.forEach(pool => {
        const poolName = pool.id.replace('pool_', '');
        const checkedTeams = Array.from(pool.querySelectorAll('input[type="checkbox"]:checked')).map(cb => ({
            name: cb.parentElement.textContent.trim(),
            index: cb.value
        }));

        if(checkedTeams.length < 2) return;

        // Vérifie si on est en type personnalisé et si la poule est cochée
        let matchesCount = 1;
        if(matchType === 'aller_retour') {
            matchesCount = 2;
        } else if(matchType === 'custom') {
            const isCustomChecked = document.querySelector(`input[name="custom_pools[${poolName}]"]`)?.checked;
            if(isCustomChecked) matchesCount = 2;
        }

        let html = `<h4>Pool ${poolName}</h4>`;
        html += `<table><tr><th>Équipe 1</th><th>Équipe 2</th><th>Date</th></tr>`;
        for(let i=0; i<checkedTeams.length; i++){
            for(let j=i+1; j<checkedTeams.length; j++){
                for(let k=0; k<matchesCount; k++){
                    html += `<tr>
                        <td>${checkedTeams[i].name}</td>
                        <td>${checkedTeams[j].name}</td>
                        <td><input type="date" name="match_dates[${poolName}][${checkedTeams[i].index}][${checkedTeams[j].index}]"></td>
                    </tr>`;
                }
            }
        }
        html += `</table>`;
        container.innerHTML += html;
    });
}
document.addEventListener('change', function(e){
    if(e.target.matches('.pool input[type="checkbox"]')){
        updateExclusiveTeams();
        generateMatches();
    }
});
</script>
</body>
</html>
