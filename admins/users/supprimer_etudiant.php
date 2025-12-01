<?php
session_start();
require_once '../../includes/db.php';

// Vérifier si l'admin est connecté
if (!isset($_SESSION['admin_id'])) {
    header('Location: connexion_admin.php');
    exit;
}

// Vérifier si un ID est fourni
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: etudiants.php?error=id");
    exit;
}

$id = intval($_GET['id']);

// ============================
//   RÉCUPÉRATION DE L'ÉTUDIANT
// ============================
$stmt = $pdo->prepare("SELECT * FROM etudiants WHERE id_etudiant = ?");
$stmt->execute([$id]);
$etudiant = $stmt->fetch();

if (!$etudiant) {
    header("Location: etudiants.php?error=notfound");
    exit;
}

// ============================
//   SUPPRESSION PHOTO SI EXISTE
// ============================
$photoPath = "../uploads/photos_etudiants/" . $etudiant['photo'];

if (!empty($etudiant['photo']) && file_exists($photoPath)) {
    unlink($photoPath);
}

// ============================
//      SUPPRESSION DB
// ============================
$stmt = $pdo->prepare("DELETE FROM etudiants WHERE id_etudiant = ?");
$delete = $stmt->execute([$id]);

if ($delete) {
    header("Location: etudiants.php?deleted=1");
    exit;
} else {
    header("Location: etudiants.php?error=delete");
    exit;
}
