<?php
session_start();
require_once '../includes/db.php'; // connexion à la base

// --- Vérification que l'utilisateur est bien un administrateur ---
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../admin/login.php"); // redirection si pas connecté
    exit;
}

// --- Vérification que l'ID de l'épreuve est bien passé ---
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "<p style='color:red;'>❌ ID d’épreuve invalide.</p>";
    exit;
}

$id = (int) $_GET['id'];

// --- Récupération du fichier associé ---
$stmt = $pdo->prepare("SELECT file_path FROM epreuves WHERE id_epreuve = :id");
$stmt->execute(['id' => $id]);
$epreuve = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$epreuve) {
    echo "<p style='color:red;'>❌ Épreuve introuvable.</p>";
    exit;
}

// --- Suppression du fichier physique ---
$file = '../uploads/' . $epreuve['file_path'];
if (file_exists($file)) {
    unlink($file); // suppression du PDF
}

// --- Suppression de la miniature si elle existe ---
$thumb = '../uploads/thumbs/' . pathinfo($epreuve['file_path'], PATHINFO_FILENAME) . '.jpg';
if (file_exists($thumb)) {
    unlink($thumb);
}

// --- Suppression de l’épreuve dans la base de données ---
$delete = $pdo->prepare("DELETE FROM epreuves WHERE id_epreuve = :id");
$delete->execute(['id' => $id]);

// --- Redirection après suppression ---
header("Location: index.php?message=supprime");
exit;
?>
