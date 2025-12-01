<?php
session_start();
require_once '../../includes/db.php'; // connexion PDO

// Vérifier que l'administrateur est connecté
$id_admin = $_SESSION['admin_id'] ?? null;
if (!$id_admin) {
    die("Accès refusé");
}

// Vérifier que l'ID du joueur est passé en GET
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("ID joueur manquant");
}

$player_id = intval($_GET['id']);

try {
    // Supprimer le joueur
    $stmt = $pdo->prepare("DELETE FROM team_players WHERE player_id = ?");
    $stmt->execute([$player_id]);

    // Redirection vers la page des joueurs avec un message de succès
    header("Location: players.php?success=1");
    exit;

} catch (PDOException $e) {
    die("Erreur lors de la suppression du joueur : " . $e->getMessage());
}
