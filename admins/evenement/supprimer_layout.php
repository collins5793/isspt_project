<?php
session_start();
require_once '../../includes/db.php';

// Vérifier admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

if (isset($_GET['id'])) {
    $id_layout = intval($_GET['id']);

    try {
        $pdo->beginTransaction();

        // 1. Rompre l'association dans la table evenements (Clé Étrangère à NULL)
        $updateEvents = $pdo->prepare("UPDATE evenements SET layout_id = NULL WHERE layout_id = ?");
        $updateEvents->execute([$id_layout]);

        // 2. Supprimer la configuration de la table ticket_layouts
        $deleteLayout = $pdo->prepare("DELETE FROM ticket_layouts WHERE id_layout = ?");
        $deleteLayout->execute([$id_layout]);

        $pdo->commit();
        header("Location: liste_layouts.php?delete_success=1");
        exit;

    } catch (PDOException $e) {
        $pdo->rollBack();
        die("Erreur lors de la suppression de la configuration : " . $e->getMessage());
    }
} else {
    header("Location: liste_layouts.php");
    exit;
}