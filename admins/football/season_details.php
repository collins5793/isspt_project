<?php
session_start();
require_once '../../includes/db.php';

$seasonId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if(!$seasonId) die("ID de saison invalide.");

// --- Saison ---
$stmt = $pdo->prepare("
    SELECT fs.*, ay.label AS academic_year, a.nom AS creator_nom, a.prenom AS creator_prenom
    FROM football_seasons fs
    JOIN academic_years ay ON fs.academic_year_id=ay.id
    LEFT JOIN administrateurs a ON fs.created_by=a.id_admin
    WHERE fs.id_season=?
");
$stmt->execute([$seasonId]);
$season = $stmt->fetch(PDO::FETCH_ASSOC);
if(!$season) die("Saison introuvable.");

// --- Paramètres ---
$stmt = $pdo->prepare("SELECT match_type FROM season_settings WHERE season_id=?");
$stmt->execute([$seasonId]);
$settings = $stmt->fetch(PDO::FETCH_ASSOC);

// --- Équipes et joueurs ---
$teamsStmt = $pdo->prepare("SELECT * FROM football_teams WHERE season_id=? ORDER BY name ASC");
$teamsStmt->execute([$seasonId]);
$teams = $teamsStmt->fetchAll(PDO::FETCH_ASSOC);

// Joueurs
$players = [];
foreach($teams as $team){
    $stmt = $pdo->prepare("SELECT * FROM team_players WHERE team_id=? ORDER BY shirt_number ASC");
    $stmt->execute([$team['team_id']]);
    $players[$team['team_id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// --- Poules et pool_teams ---
$poolsStmt = $pdo->prepare("SELECT * FROM pools WHERE season_id=? ORDER BY name ASC");
$poolsStmt->execute([$seasonId]);
$pools = $poolsStmt->fetchAll(PDO::FETCH_ASSOC);

$poolTeams = [];
foreach($pools as $pool){
    $stmt = $pdo->prepare("
        SELECT pt.*, t.name AS team_name
        FROM pool_teams pt
        JOIN football_teams t ON pt.team_id=t.team_id
        WHERE pt.pool_id=? ORDER BY pt.points DESC, pt.goals_for DESC
    ");
    $stmt->execute([$pool['pool_id']]);
    $poolTeams[$pool['name']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// --- Matchs ---
$matchesStmt = $pdo->prepare("
    SELECT m.*, t1.name AS team1_name, t2.name AS team2_name, p.name AS pool_name
    FROM matches m
    JOIN football_teams t1 ON m.team1_id=t1.team_id
    JOIN football_teams t2 ON m.team2_id=t2.team_id
    LEFT JOIN pools p ON m.pool_id=p.pool_id
    WHERE m.season_id=? ORDER BY m.stage ASC, m.round_number ASC
");
$matchesStmt->execute([$seasonId]);
$matches = $matchesStmt->fetchAll(PDO::FETCH_ASSOC);

// --- Historique des modifications ---
$historyStmt = $pdo->prepare("
    SELECT h.*, a.nom AS admin_nom, a.prenom AS admin_prenom, m.team1_id, m.team2_id, t1.name AS team1_name, t2.name AS team2_name
    FROM match_results_history h
    JOIN administrateurs a ON h.changed_by=a.id_admin
    JOIN matches m ON h.match_id=m.match_id
    JOIN football_teams t1 ON m.team1_id=t1.team_id
    JOIN football_teams t2 ON m.team2_id=t2.team_id
    WHERE m.season_id=?
    ORDER BY h.changed_at DESC
");
$historyStmt->execute([$seasonId]);
$history = $historyStmt->fetchAll(PDO::FETCH_ASSOC);

// --- Résultats finaux ---
$finalStmt = $pdo->prepare("
    SELECT f.*, t1.name AS champion_name, t2.name AS runner_up_name, m.match_id
    FROM match_finale f
    JOIN football_teams t1 ON f.champion_team_id=t1.team_id
    JOIN football_teams t2 ON f.runner_up_team_id=t2.team_id
    JOIN matches m ON f.match_id=m.match_id
    WHERE f.season_id=?
");
$finalStmt->execute([$seasonId]);
$finalResult = $finalStmt->fetch(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Détails de la saison <?= htmlspecialchars($season['label']) ?></title>
<style>
body{font-family:Arial,sans-serif; margin:20px;}
h2,h3{margin-top:30px;}
table{border-collapse:collapse;width:100%; margin-bottom:20px;}
th,td{border:1px solid #ccc;padding:8px;text-align:left;}
th{background:#f4f4f4;}
.section{margin-bottom:30px;}
</style>
</head>
<body>
<h1>📅 Détails de la saison : <?= htmlspecialchars($season['label']) ?></h1>

<div class="section">
<h2>Informations générales</h2>
<p><strong>Année académique :</strong> <?= htmlspecialchars($season['academic_year']) ?></p>
<p><strong>Active :</strong> <?= $season['is_active']?'✅ Oui':'❌ Non' ?></p>
<p><strong>Créée par :</strong> <?= $season['creator_nom'].' '.$season['creator_prenom'] ?></p>
<p><strong>Créée le :</strong> <?= $season['created_at'] ?></p>
<p><strong>Dernière modification :</strong> <?= $season['updated_at'] ?: '-' ?></p>
<p><strong>Type de match :</strong> <?= $settings['match_type'] ?? '-' ?></p>
</div>

<div class="section">
<h2>Équipes et joueurs</h2>
<?php foreach($teams as $team): ?>
<h3><?= htmlspecialchars($team['name']) ?> (Coach: <?= htmlspecialchars($team['coach']) ?>, Status: <?= $team['status'] ?>)</h3>
<?php if(!empty($players[$team['team_id']])): ?>
<table>
<tr><th>Nom</th><th>Numéro</th><th>Poste</th><th>Capitaine</th><th>Rejoint</th><th>Quitte</th></tr>
<?php foreach($players[$team['team_id']] as $p): ?>
<tr>
<td><?= htmlspecialchars($p['full_name']) ?></td>
<td><?= $p['shirt_number'] ?></td>
<td><?= htmlspecialchars($p['position']) ?></td>
<td><?= $p['is_captain']?'✅':'' ?></td>
<td><?= $p['joined_at'] ?></td>
<td><?= $p['left_at'] ?: '-' ?></td>
</tr>
<?php endforeach; ?>
</table>
<?php else: ?>
<p>Aucun joueur.</p>
<?php endif; ?>
<?php endforeach; ?>
</div>

<div class="section">
<h2>Poules</h2>
<?php foreach($pools as $pool): ?>
<h3>Poule <?= htmlspecialchars($pool['name']) ?> (Qualif: <?= $pool['advance_count'] ?>)</h3>
<table>
<tr><th>Équipe</th><th>Points</th><th>J</th><th>G</th><th>N</th><th>P</th><th>BP</th><th>BC</th></tr>
<?php foreach($poolTeams[$pool['name']] as $pt): ?>
<tr>
<td><?= htmlspecialchars($pt['team_name']) ?></td>
<td><?= $pt['points'] ?></td>
<td><?= $pt['played'] ?></td>
<td><?= $pt['won'] ?></td>
<td><?= $pt['drawn'] ?></td>
<td><?= $pt['lost'] ?></td>
<td><?= $pt['goals_for'] ?></td>
<td><?= $pt['goals_against'] ?></td>
</tr>
<?php endforeach; ?>
</table>
<?php endforeach; ?>
</div>

<div class="section">
<h2>Matchs</h2>
<table>
<tr><th>Poule</th><th>Équipe 1</th><th>Équipe 2</th><th>Stage</th><th>Round</th><th>Date</th><th>Lieu</th><th>Score</th><th>Gagnant</th><th>Joué</th><th>Publié</th></tr>
<?php foreach($matches as $m): ?>
<tr>
<td><?= htmlspecialchars($m['pool_name'] ?: '-') ?></td>
<td><?= htmlspecialchars($m['team1_name']) ?></td>
<td><?= htmlspecialchars($m['team2_name']) ?></td>
<td><?= $m['stage'] ?></td>
<td><?= $m['round_number'] ?></td>
<td><?= $m['match_datetime'] ?: '-' ?></td>
<td><?= htmlspecialchars($m['location'] ?: '-') ?></td>
<td><?= ($m['score_team1'] !== null ? $m['score_team1'].' - '.$m['score_team2'] : '-') ?></td>
<td><?= $m['winner_team_id'] ? ($m['winner_team_id']==$m['team1_id']?$m['team1_name']:$m['team2_name']) : '-' ?></td>
<td><?= $m['is_played']?'✅':'' ?></td>
<td><?= $m['is_published']?'✅':'' ?></td>
</tr>
<?php endforeach; ?>
</table>
</div>

<div class="section">
<h2>Historique des résultats</h2>
<?php if($history): ?>
<table>
<tr><th>Match</th><th>Ancien score</th><th>Nouveau score</th><th>Modifié par</th><th>Raison</th><th>Date</th></tr>
<?php foreach($history as $h): ?>
<tr>
<td><?= $h['team1_name'].' vs '.$h['team2_name'] ?></td>
<td><?= $h['old_score_team1'].' - '.$h['old_score_team2'] ?></td>
<td><?= $h['new_score_team1'].' - '.$h['new_score_team2'] ?></td>
<td><?= $h['admin_nom'].' '.$h['admin_prenom'] ?></td>
<td><?= htmlspecialchars($h['reason']) ?></td>
<td><?= $h['changed_at'] ?></td>
</tr>
<?php endforeach; ?>
</table>
<?php else: ?>
<p>Aucun historique.</p>
<?php endif; ?>
</div>

<div class="section">
<h2>Résultats finaux</h2>
<?php if($finalResult): ?>
<p>Champion : <?= htmlspecialchars($finalResult['champion_name']) ?></p>
<p>Runner-up : <?= htmlspecialchars($finalResult['runner_up_name']) ?></p>
<p>Match final ID : <?= $finalResult['match_id'] ?></p>
<?php else: ?>
<p>Pas de résultats finaux.</p>
<?php endif; ?>
</div>

</body>
</html>
