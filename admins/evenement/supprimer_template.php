<?php
session_start();
require_once "../../includes/db.php";

// Vérification de la présence de l'ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: liste_templates.php");
    exit();
}

$id_template = intval($_GET['id']);

try {
    // 1. Récupérer le chemin de l'image pour la supprimer du serveur
    $stmt = $pdo->prepare("SELECT image_path FROM ticket_templates WHERE id_template = ?");
    $stmt->execute([$id_template]);
    $template = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($template) {
        $realImagePath = "../../" . $template['image_path'];
        
        // Supprimer le fichier physique s'il existe
        if (!empty($template['image_path']) && file_exists($realImagePath)) {
            unlink($realImagePath);
        }

        // 2. Supprimer la ligne dans la base de données
        $deleteStmt = $pdo->prepare("DELETE FROM ticket_templates WHERE id_template = ?");
        $deleteStmt->execute([$id_template]);
        
        // Optionnel : Tu peux passer un message via la session si ton layout gère les flash messages
        $_SESSION['flash_success'] = "Le template a été supprimé avec succès.";
    }

} catch (PDOException $e) {
    // En cas d'erreur liée à une clé étrangère (ex: si le template est utilisé par un événement)
    $_SESSION['flash_error'] = "Impossible de supprimer ce template car il est actuellement lié à des tickets.";
}

// Redirection immédiate vers la liste
header("Location: liste_templates.php");
exit();