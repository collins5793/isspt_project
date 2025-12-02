<?php
session_start();
require_once "../../includes/db.php";

// Vérification des paramètres
if (!isset($_GET['id_event']) || !isset($_GET['nom'])) {
    die("Paramètres invalides.");
}

$id_event = intval($_GET['id_event']);
$nom_artiste = $_GET['nom']; // primary key avec id_event

// Récupérer l'artiste pour supprimer la photo
$stmt = $pdo->prepare("
    SELECT photo 
    FROM evenement_artistes 
    WHERE id_evenement = ? AND nom_artiste = ?
");
$stmt->execute([$id_event, $nom_artiste]);
$artiste = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$artiste) {
    die("Artiste introuvable.");
}

// Supprimer la photo si elle existe
if (!empty($artiste['photo'])) {
    $photoPath = "../../uploads/artistes/" . $artiste['photo'];
    if (file_exists($photoPath)) {
        unlink($photoPath);
    }
}

// Supprimer l'artiste dans la base
$delete = $pdo->prepare("
    DELETE FROM evenement_artistes 
    WHERE id_evenement = ? AND nom_artiste = ?
");

if ($delete->execute([$id_event, $nom_artiste])) {
    header("Location: liste_artistes.php?id_event=" . $id_event . "&deleted=1");
    exit;
} else {
    die("Erreur lors de la suppression.");
}
