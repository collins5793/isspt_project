<?php
session_start();
require_once "../../includes/db.php";

// Vérifier si l'ID est fourni
$id = $_GET['id'] ?? null;
if (!$id) {
    $_SESSION['error'] = "Aucune actualité sélectionnée.";
    header("Location: actualites.php");
    exit;
}

// Vérifier si l'actualité existe
$stmt = $pdo->prepare("SELECT * FROM actualites WHERE id = ?");
$stmt->execute([$id]);
$actualite = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$actualite) {
    $_SESSION['error'] = "Actualité introuvable.";
    header("Location: actualites.php");
    exit;
}

// Supprimer l'actualité
$stmt = $pdo->prepare("DELETE FROM actualites WHERE id = ?");
if ($stmt->execute([$id])) {
    $_SESSION['success'] = "Actualité supprimée avec succès !";
} else {
    $_SESSION['error'] = "Une erreur est survenue lors de la suppression.";
}

// Redirection vers la liste des actualités
header("Location: actualites.php");
exit;
?>
