<?php
session_start();
require_once "../../includes/db.php";

// Vérifier que l'admin est connecté
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// Récupérer la saison en cours
$seasonId = $_GET['season_id'] ?? null;
if (!$seasonId) {
    die("Saison non définie !");
}

// Récupérer toutes les pools de la saison
$stmt = $pdo->prepare("SELECT * FROM pools WHERE season_id = ?");
$stmt->execute([$seasonId]);
$pools = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer toutes les équipes de la saison
$stmt2 = $pdo->prepare("SELECT * FROM football_teams WHERE season_id = ?");
$stmt2->execute([$seasonId]);
$teams = $stmt2->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Gestion des Pools - Football</title>
<style>
.pool-container { border:1px solid #ccc; padding:10px; margin-bottom:20px; }
.match-table { margin-top:10px; width:100%; border-collapse: collapse; }
.match-table th, .match-table td { border:1px solid #ccc; padding:5px; text-align:left; }
</style>
</head>
<body>
<h1>Gestion des Pools - Saison <?= htmlspecialchars($seasonId) ?></h1>

<form id="poolsForm" method="POST" action="save_pools.php">
<input type="hidden" name="season_id" value="<?= htmlspecialchars($seasonId) ?>">

<?php foreach ($pools as $pool): ?>
<div class="pool-container" data-pool-id="<?= $pool['pool_id'] ?>">
    <h3><?= htmlspecialchars($pool['name']) ?></h3>
    
    <label>Choisir les équipes :</label><br>
    <select multiple class="team-select" data-pool-id="<?= $pool['pool_id'] ?>" style="width:300px;">
        <?php foreach ($teams as $team): ?>
            <option value="<?= $team['team_id'] ?>"><?= htmlspecialchars($team['name']) ?></option>
        <?php endforeach; ?>
    </select>
    
    <h4>Matchs générés :</h4>
    <table class="match-table" id="matches-<?= $pool['pool_id'] ?>">
        <thead>
            <tr>
                <th>Équipe 1</th>
                <th>Équipe 2</th>
                <th>Date du match</th>
            </tr>
        </thead>
        <tbody>
            <!-- Matchs générés via JS -->
        </tbody>
    </table>
</div>
<?php endforeach; ?>

<button type="submit">Valider et enregistrer</button>
</form>

<script>
// Gestion dynamique des équipes
const allTeams = <?= json_encode($teams) ?>;

// Fonction pour générer les matchs round-robin
function generateMatches(selectedTeams) {
    let matches = [];
    for (let i = 0; i < selectedTeams.length; i++) {
        for (let j = i + 1; j < selectedTeams.length; j++) {
            matches.push({team1:selectedTeams[i], team2:selectedTeams[j]});
        }
    }
    return matches;
}

// Mettre à jour les matchs et désactiver les équipes déjà choisies
document.querySelectorAll('.team-select').forEach(select => {
    select.addEventListener('change', function() {
        // Récupérer toutes les équipes sélectionnées
        let selectedTeamIds = [];
        document.querySelectorAll('.team-select').forEach(s => {
            Array.from(s.selectedOptions).forEach(opt => {
                selectedTeamIds.push(opt.value);
            });
        });

        // Désactiver les options déjà prises dans les autres selects
        document.querySelectorAll('.team-select').forEach(s => {
            Array.from(s.options).forEach(opt => {
                if (!Array.from(s.selectedOptions).map(o=>o.value).includes(opt.value)) {
                    opt.disabled = selectedTeamIds.includes(opt.value);
                }
            });
        });

        // Générer les matchs pour ce pool
        const poolId = this.dataset.poolId;
        const selected = Array.from(this.selectedOptions).map(o=>({id:o.value, name:o.text}));
        const matches = generateMatches(selected);

        const tbody = document.querySelector(`#matches-${poolId} tbody`);
        tbody.innerHTML = '';
        matches.forEach(m=>{
            const row = document.createElement('tr');
            row.innerHTML = `<td>${m.team1.name}</td>
                             <td>${m.team2.name}</td>
                             <td><input type="datetime-local" name="match_datetime[${poolId}][]"></td>
                             <input type="hidden" name="team1[${poolId}][]" value="${m.team1.id}">
                             <input type="hidden" name="team2[${poolId}][]" value="${m.team2.id}">`;
            tbody.appendChild(row);
        });
    });
});
</script>

</body>
</html>
