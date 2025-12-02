<?php
session_start();
require_once '../../includes/db.php';

$id_admin = $_SESSION['admin_id'] ?? null;
if (!$id_admin) {
    die("Accès refusé.");
}

// Récupérer l'ID du match à supprimer
$match_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$match_id) {
    die("Match non spécifié.");
}

// Vérifier que le match existe
$stmt = $pdo->prepare("SELECT * FROM matches WHERE match_id = ?");
$stmt->execute([$match_id]);
$match = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$match) {
    die("Match introuvable.");
}

// Supprimer le match et toutes les données associées
try {
    $pdo->beginTransaction();

    // Supprimer les buts associés
    $pdo->prepare("DELETE FROM match_goals WHERE match_id = ?")->execute([$match_id]);

    // Supprimer l'historique des scores
    $pdo->prepare("DELETE FROM match_results_history WHERE match_id = ?")->execute([$match_id]);

    // Supprimer le match
    $pdo->prepare("DELETE FROM matches WHERE match_id = ?")->execute([$match_id]);

    $pdo->commit();

    // Rediriger vers la liste des matchs avec succès
    header("Location: matches.php?deleted=1");
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    die("Erreur lors de la suppression du match : " . $e->getMessage());
}
