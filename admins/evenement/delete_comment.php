<?php
session_start();
require_once "../../includes/db.php";

// Vérifier paramètres
if (!isset($_GET['id']) || !isset($_GET['event'])) {
    die("Paramètres manquants.");
}

$commentId = intval($_GET['id']);
$eventId = intval($_GET['event']);

// Vérifier que le commentaire existe
$stmt = $pdo->prepare("SELECT id_commentaire FROM evenement_commentaires WHERE id_commentaire = ?");
$stmt->execute([$commentId]);

if (!$stmt->fetch()) {
    die("Commentaire introuvable.");
}

// Supprimer le commentaire
$delete = $pdo->prepare("DELETE FROM evenement_commentaires WHERE id_commentaire = ?");
$delete->execute([$commentId]);

// Retour
header("Location: details_evenement.php?id_event=" . $eventId . "&delc=success");
exit;
