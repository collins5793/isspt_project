<?php
session_start();
require_once "../../includes/db.php";

// Vérifier si l'ID est passé en GET
$activiteId = $_GET['id'] ?? null;
if (!$activiteId) {
    header("Location: activites.php");
    exit;
}

// Vérifier si l'activité existe
$stmt = $pdo->prepare("SELECT * FROM activites WHERE id_activite = ?");
$stmt->execute([$activiteId]);
$activite = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$activite) {
    // Activité introuvable
    $_SESSION['error'] = "Activité introuvable.";
    header("Location: activites.php");
    exit;
}

// Supprimer l'activité
$stmt = $pdo->prepare("DELETE FROM activites WHERE id_activite = ?");
$stmt->execute([$activiteId]);

// Message de succès (optionnel via session)
$_SESSION['success'] = "L'activité '{$activite['nom_activite']}' a été supprimée avec succès.";

// Redirection vers la liste
header("Location: activites.php");
exit;
?>
