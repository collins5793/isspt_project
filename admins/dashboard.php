<?php
session_start();
require_once "../includes/db.php";

// Récupération des stats
$totalEtudiants = $pdo->query("SELECT COUNT(*) FROM etudiants")->fetchColumn();
$totalEvenements = $pdo->query("SELECT COUNT(*) FROM evenements")->fetchColumn();
$totalCommentaires = $pdo->query("SELECT COUNT(*) FROM commentaires")->fetchColumn();

// Détermination du nom à afficher selon le rôle
if ($_SESSION['admin_role'] === 'bureau' && !empty($_SESSION['admin_id_etudiant'])) {
    $stmt = $pdo->prepare("SELECT nom, prenom FROM etudiants WHERE id_etudiant = ?");
    $stmt->execute([$_SESSION['admin_id_etudiant']]);
    $etudiant = $stmt->fetch(PDO::FETCH_ASSOC);

    $prenom = $etudiant['prenom'] ?? 'Bureau';
    $nom = $etudiant['nom'] ?? 'Membre';
} else {
    $prenom = $_SESSION['admin_prenom'] ?? '';
    $nom = $_SESSION['admin_nom'] ?? '';
}

// Contenu à injecter dans le layout
ob_start();
?>

<h2 class="page-title">Bienvenue, <?= htmlspecialchars($prenom . " " . $nom) ?></h2>

<div class="stats-grid">

    <div class="stat-card">
        <h3>Étudiants</h3>
        <p><?= $totalEtudiants ?></p>
    </div>

    <div class="stat-card">
        <h3>Événements</h3>
        <p><?= $totalEvenements ?></p>
    </div>

    <div class="stat-card">
        <h3>Commentaires</h3>
        <p><?= $totalCommentaires ?></p>
    </div>

</div>

<?php
$content = ob_get_clean();
include "layout.php";
