<?php
session_start();
require_once '../../includes/db.php';

// Vérifier si l’admin est connecté
if (!isset($_SESSION['admin_id'])) {
    header('Location: connexion_admin.php');
    exit();
}

// Vérifier que l'id est fourni
if (!isset($_GET['id'])) {
    header('Location: bureau.php');
    exit();
}

$id_admin = intval($_GET['id']);

// Vérifier que le membre existe et est bien un membre du bureau
$stmt = $pdo->prepare("SELECT * FROM administrateurs WHERE id_admin = ? AND role = 'bureau'");
$stmt->execute([$id_admin]);
$member = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$member) {
    $_SESSION['message'] = "<div class='alert alert-warning'>Membre du bureau introuvable.</div>";
    header('Location: bureau.php');
    exit();
}

// Suppression
$stmt = $pdo->prepare("DELETE FROM administrateurs WHERE id_admin = ?");
if ($stmt->execute([$id_admin])) {
    $_SESSION['message'] = "<div class='alert alert-success'>Membre du bureau supprimé avec succès.</div>";
} else {
    $_SESSION['message'] = "<div class='alert alert-danger'>Erreur lors de la suppression du membre.</div>";
}

// Redirection vers la liste
header('Location: bureau.php');
exit();
