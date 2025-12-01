<?php
session_start();
require_once '../../includes/db.php'; // Connexion PDO

// Vérifie si l'utilisateur est connecté
if (!isset($_SESSION['admin_id'])) {
    header("Location: connexion_admin.php");
    exit;
}

// Vérifie qu'un ID est passé
if (!isset($_GET['id'])) {
    header("Location: liste_admins.php");
    exit;
}

$id_admin = intval($_GET['id']);

// Empêche la suppression du super admin connecté
if ($id_admin === $_SESSION['admin_id']) {
    $_SESSION['flash_error'] = "❌ Vous ne pouvez pas vous supprimer vous-même.";
    header("Location: admins.php");
    exit;
}

// Vérifie que l'admin existe
$stmt = $pdo->prepare("SELECT * FROM administrateurs WHERE id_admin = ?");
$stmt->execute([$id_admin]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$admin) {
    $_SESSION['flash_error'] = "⚠️ Administrateur introuvable.";
    header("Location: admins.php");
    exit;
}

// Suppression
$stmt = $pdo->prepare("DELETE FROM administrateurs WHERE id_admin = ?");
$stmt->execute([$id_admin]);

$_SESSION['flash_success'] = "✅ Administrateur supprimé avec succès.";
header("Location: admins.php");
exit;
?>
