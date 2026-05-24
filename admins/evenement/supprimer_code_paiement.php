<?php
session_start();
require_once "../../includes/db.php";

// 1. Vérification de la présence de l'ID à supprimer
$id = $_GET['id'] ?? null;

if (!$id) {
    $_SESSION['flash_error'] = "Identifiant introuvable ou manquant pour effectuer la suppression.";
    header("Location: liste_codes.php");
    exit;
}

try {
    // 2. Récupération préalable pour valider le statut du code
    $stmtCheck = $pdo->prepare("SELECT code, statut FROM codes_paiement WHERE id = ?");
    $stmtCheck->execute([$id]);
    $code_to_delete = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    // Si le code n'existe pas en BDD
    if (!$code_to_delete) {
        $_SESSION['flash_error'] = "Ce code de paiement n'existe pas ou a déjà été supprimé.";
        header("Location: liste_codes.php");
        exit;
    }

    // 3. Sécurité d'intégrité financière
    // On bloque la suppression si le jeton d'accès a déjà servi à prendre un ticket réel
    if ($code_to_delete['statut'] === 'utilise') {
        $_SESSION['flash_error'] = "Sécurité : Impossible de supprimer le code <strong>{$code_to_delete['code']}</strong> car il a déjà été utilisé pour générer un ticket.";
        header("Location: liste_codes.php");
        exit;
    }

    // 4. Exécution de la suppression définitive
    $stmtDelete = $pdo->prepare("DELETE FROM codes_paiement WHERE id = ?");
    $stmtDelete->execute([$id]);

    // Notification de succès à l'administrateur
    $_SESSION['flash_success'] = "Le code de paiement <strong>{$code_to_delete['code']}</strong> a été supprimé définitivement de la base de données.";

} catch (Exception $e) {
    // Capture des exceptions PDO en cas de contrainte de clé étrangère active
    $_SESSION['flash_error'] = "Erreur système lors de la suppression : " . $e->getMessage();
}

// 5. Redirection automatique vers la liste mise à jour
header("Location: liste_codes.php");
exit;