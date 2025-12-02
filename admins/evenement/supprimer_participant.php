<?php
session_start();
require_once "../../includes/db.php";

// Vérification des paramètres
if (!isset($_GET['id_event']) || !isset($_GET['id_participant'])) {
    die("Paramètres manquants.");
}

$eventId = intval($_GET['id_event']);
$participantId = intval($_GET['id_participant']);

// Vérifier que le participant existe
$stmt = $pdo->prepare("SELECT id FROM evenement_participants WHERE id = ? AND id_evenement = ?");
$stmt->execute([$participantId, $eventId]);

if (!$stmt->fetch()) {
    die("Participant introuvable.");
}

// Supprimer le participant
$delete = $pdo->prepare("DELETE FROM evenement_participants WHERE id = ? AND id_evenement = ?");
$delete->execute([$participantId, $eventId]);

// Redirection vers la page événement avec message succès
header("Location: details_evenement.php?id_event=" . $eventId . "&delp=success");
exit;
