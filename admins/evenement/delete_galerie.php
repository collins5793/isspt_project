<?php
session_start();
require_once "../../includes/db.php";

// Vérification des paramètres
if (!isset($_GET['id']) || !isset($_GET['event'])) {
    die("Paramètres manquants.");
}

$id = intval($_GET['id']);
$eventId = intval($_GET['event']);

// Récupérer l'élément avant suppression (pour supprimer aussi le fichier)
$stmt = $pdo->prepare("SELECT file_path FROM evenement_galerie WHERE id = ?");
$stmt->execute([$id]);
$file = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$file) {
    die("Élément introuvable.");
}

// Supprimer le fichier physique s'il existe
$filePath = "../uploads/" . $file['file_path'];

if (file_exists($filePath)) {
    unlink($filePath);
}

// Supprimer de la base
$delete = $pdo->prepare("DELETE FROM evenement_galerie WHERE id = ?");
$delete->execute([$id]);

// Redirection
header("Location: details_evenement.php?id_event=" . $eventId . "&del=success");
exit;
