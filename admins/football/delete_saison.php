<?php
session_start();
require_once '../../includes/db.php'; // ajuster le chemin selon ton projet

// Vérifier si l'admin est connecté
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header("Location: ../index.php");
    exit;
}

// Vérifier si l'ID de la saison est fourni
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: season.php?error=missing_id");
    exit;
}

$season_id = (int)$_GET['id'];

try {
    // Supprimer la saison
    $stmt = $pdo->prepare("DELETE FROM football_seasons WHERE id_season = :id");
    $stmt->execute([':id' => $season_id]);

    // Vérifier si la suppression a affecté une ligne
    if ($stmt->rowCount() > 0) {
        header("Location: season.php?deleted=1");
        exit;
    } else {
        header("Location: season.php?error=not_found");
        exit;
    }
} catch (PDOException $e) {
    // Gestion des erreurs, notamment les contraintes FK
    header("Location: season.php?error=" . urlencode($e->getMessage()));
    exit;
}
