<?php
session_start();
require_once "../../includes/db.php";

if (!isset($_SESSION['admin_id'])) {
    die("Non autorisé");
}

$seasonId = $_POST['season_id'] ?? null;
if (!$seasonId) die("Saison non définie !");

// Récupérer toutes les données
$team1Data = $_POST['team1'] ?? [];
$team2Data = $_POST['team2'] ?? [];
$matchDates = $_POST['match_datetime'] ?? [];

try {
    $pdo->beginTransaction();

    // Supprimer les anciennes équipes et matchs de cette saison
    $stmtDelMatches = $pdo->prepare("DELETE FROM matches WHERE season_id = ?");
    $stmtDelMatches->execute([$seasonId]);

    $stmtDelPools = $pdo->prepare("DELETE FROM pool_teams WHERE season_id = ?");
    $stmtDelPools->execute([$seasonId]);

    // Insertion des équipes dans les pools
    foreach ($team1Data as $poolId => $teamIds) {
        foreach ($teamIds as $index => $teamId) {
            $stmtInsert = $pdo->prepare("INSERT INTO pool_teams (pool_id, team_id, season_id) VALUES (?, ?, ?)");
            $stmtInsert->execute([$poolId, $teamId, $seasonId]);
        }
    }

    // Insertion des matchs
    foreach ($team1Data as $poolId => $teamIds) {
        foreach ($teamIds as $index => $team1Id) {
            $team2Id = $team2Data[$poolId][$index];
            $matchDate = $matchDates[$poolId][$index] ?? null;
            if (!$matchDate) throw new Exception("Date manquante pour un match");

            $stmtMatch = $pdo->prepare("INSERT INTO matches (season_id, pool_id, team1_id, team2_id, match_datetime, stage) VALUES (?, ?, ?, ?, ?, 'group')");
            $stmtMatch->execute([$seasonId, $poolId, $team1Id, $team2Id, $matchDate]);
        }
    }

    $pdo->commit();
    echo "Pools et matchs enregistrés avec succès !";

} catch (Exception $e) {
    $pdo->rollBack();
    die("Erreur : ".$e->getMessage());
}
