<?php
session_start();
require_once '../../includes/db.php'; // Connexion PDO

$academicYears = $pdo->query("SELECT id, label FROM academic_years ORDER BY label DESC")->fetchAll(PDO::FETCH_ASSOC);

// Liste des équipes déjà créées
$teamsList = $pdo->query("SELECT team_id, name FROM football_teams ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_season'])) {
    try {
        $pdo->beginTransaction();

        // Créer la saison
        $stmt = $pdo->prepare("INSERT INTO football_seasons (label, academic_year_id, is_active, created_by) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $_POST['season_label'],
            $_POST['academic_year_id'],
            isset($_POST['is_active']) ? 1 : 0,
            $_SESSION['admin_id'] ?? null
        ]);
        $seasonId = $pdo->lastInsertId();

        // Ajouter les équipes
        $teamIds = [];
        if(!empty($_POST['teams'])){
            foreach($_POST['teams'] as $team){
                $stmt = $pdo->prepare("INSERT INTO football_teams (season_id, name, coach, created_by) VALUES (?, ?, ?, ?)");
                $stmt->execute([$seasonId, $team['name'], $team['coach'], $_SESSION['admin_id'] ?? null]);
                $teamIds[] = $pdo->lastInsertId();
            }
        }

        // Ajouter les poules et assigner les équipes
        $poolMap = [];
        if(!empty($_POST['pools'])){
            foreach($_POST['pools'] as $poolName => $advanceCount){
                $stmt = $pdo->prepare("INSERT INTO pools (season_id, name, advance_count) VALUES (?, ?, ?)");
                $stmt->execute([$seasonId, $poolName, $_POST['advance_count'][$poolName]]);
                $poolId = $pdo->lastInsertId();
                $poolMap[$poolName] = $poolId;

                if(!empty($_POST['pool_teams'][$poolName])){
                    foreach($_POST['pool_teams'][$poolName] as $teamIndex){
                        $teamId = $teamIds[$teamIndex] ?? null;
                        if($teamId){
                            $stmt = $pdo->prepare("INSERT INTO pool_teams (pool_id, team_id, season_id) VALUES (?, ?, ?)");
                            $stmt->execute([$poolId, $teamId, $seasonId]);
                        }
                    }
                }
            }
        }

        // Enregistrer le type de match
        $matchType = $_POST['match_type'];
        $stmt = $pdo->prepare("INSERT INTO season_settings (season_id, match_type) VALUES (?, ?)");
        $stmt->execute([$seasonId, $matchType]);

        // Ajouter les matchs
        foreach($_POST['pools'] as $poolName => $_){
            $poolId = $poolMap[$poolName];
            $teamsInPool = $_POST['pool_teams'][$poolName] ?? [];
            $matchesCount = 1;
            if($matchType === 'aller_retour' || ($matchType==='custom' && !empty($_POST['custom_pools'][$poolName]))){
                $matchesCount = 2;
            }
            for($i=0; $i<count($teamsInPool); $i++){
                for($j=$i+1; $j<count($teamsInPool); $j++){
                    for($k=0; $k<$matchesCount; $k++){
                        $team1 = $teamIds[$teamsInPool[$i]];
                        $team2 = $teamIds[$teamsInPool[$j]];
                        $date = $_POST['match_dates'][$poolName][$teamsInPool[$i]][$teamsInPool[$j]] ?? null;
                        $stmt = $pdo->prepare("INSERT INTO matches (season_id, pool_id, team1_id, team2_id, stage, match_datetime) VALUES (?, ?, ?, ?, 'group', ?)");
                        $stmt->execute([$seasonId, $poolId, $team1, $team2, $date]);
                    }
                }
            }
        }

        $pdo->commit();
        $success = "Saison créée avec succès !";

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Erreur : " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Nouvelle Saison Football</title>
    <style>
        .section { border: 1px solid #ccc; padding: 15px; margin-bottom: 15px; }
        .section h3 { margin-top: 0; }
        .pool { border: 1px solid #999; padding: 10px; margin-bottom: 10px; }
        .team-entry, .team-select { margin-bottom: 5px; display:block; }
        select, input[type="date"] { width: 200px; }
        table { border-collapse: collapse; width: 100%; margin-top: 15px; }
        th, td { border: 1px solid #999; padding: 5px; text-align:center; }
    </style>
</head>
<body>
<h1>Créer une nouvelle saison</h1>

<?php if(!empty($success)) echo "<p style='color:green'>$success</p>"; ?>
<?php if(!empty($error)) echo "<p style='color:red'>$error</p>"; ?>

<form method="post" id="seasonForm">

<!-- 1️⃣ Saison -->
<div class="section">
    <h3>1 — Nouvelle Saison</h3>
    <label>Label : <input type="text" name="season_label" required></label><br>
    <label>Année académique : 
        <select name="academic_year_id" required>
            <?php foreach($academicYears as $year): ?>
                <option value="<?= $year['id'] ?>"><?= $year['label'] ?></option>
            <?php endforeach; ?>
        </select>
    </label><br>
    <label>Activer : <input type="checkbox" name="is_active"></label>
</div>

<!-- 2️⃣ Équipes -->
<div class="section">
    <h3>2 — Ajouter les équipes</h3>
    <div id="teams-container">
        <div class="team-entry">
            Nom : <input type="text" class="team-name" name="teams[0][name]" required>
            Coach : <input type="text" name="teams[0][coach]" required>
            <button type="button" onclick="removeEntry(this)">Supprimer</button>
        </div>
    </div>
    <button type="button" onclick="addTeam()">+ Ajouter équipe</button>
</div>

<!-- 3️⃣ Poules -->
<div class="section">
    <h3>3 — Créer les poules et répartir les équipes</h3>
    <div id="pools-container"></div>
    <button type="button" onclick="addPool()">+ Ajouter poule</button>
</div>

<!-- 4️⃣ Type de match -->
<div class="section">
    <h3>4 — Type de match</h3>
    <label><input type="radio" name="match_type" value="simple" checked onchange="toggleCustom(false)"> Match simple</label><br>
    <label><input type="radio" name="match_type" value="aller_retour" onchange="toggleCustom(false)"> Aller-Retour</label><br>
    <label><input type="radio" name="match_type" value="custom" onchange="toggleCustom(true)"> Personnalisé</label>
    <div id="custom-options" style="display:none; margin-top:10px;"></div>
</div>

<!-- 5️⃣ Matchs générés -->
<div class="section">
    <h3>5 — Matchs générés</h3>
    <div id="matches-container"></div>
</div>

<button type="submit" name="save_season">Enregistrer toute la saison</button>
</form>

<script>
let teamIndex = 1;
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

// Génération dynamique des matchs
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
