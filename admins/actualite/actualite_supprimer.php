<?php
session_start();
require_once "../../includes/db.php";

$id = $_GET['id'] ?? null;
if (!$id) {
    $_SESSION['error'] = "Aucune actualité sélectionnée.";
    header("Location: actualites.php");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM actualites WHERE id = ?");
$stmt->execute([$id]);
$actualite = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$actualite) {
    $_SESSION['error'] = "Actualité introuvable.";
    header("Location: actualites.php");
    exit;
}

$stmt = $pdo->prepare("DELETE FROM actualites WHERE id = ?");
if ($stmt->execute([$id])) {
    $_SESSION['success'] = "Actualité supprimée avec succès !";
} else {
    $_SESSION['error'] = "Une erreur est survenue lors de la suppression.";
}

header("Location: actualites.php");
exit;
?>
