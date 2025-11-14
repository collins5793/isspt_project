<?php
session_start();
require_once '../includes/db.php';

// Vérifier si l'admin est connecté
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../admins/connexion_admin.php");
    exit;
}

// Inclure les infos du header
require_once 'include/header_dashboard.php';

// Compter les données pour les widgets
$nb_etudiants = $pdo->query("SELECT COUNT(*) FROM etudiants")->fetchColumn();
$nb_activites = $pdo->query("SELECT COUNT(*) FROM activites")->fetchColumn();
$nb_resultats = $pdo->query("SELECT COUNT(*) FROM resultats")->fetchColumn();
$nb_epreuves = $pdo->query("SELECT COUNT(*) FROM epreuves")->fetchColumn();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard Admin</title>
<link rel="stylesheet" href="assets/css/header_dashboard.css">
<link rel="stylesheet" href="assets/css/sidebar_dashboard.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
  body { margin:0; font-family: Arial, sans-serif; background:#f0f0f0; }
  main { margin-left:250px; padding:80px 20px 20px 20px; transition: margin-left 0.3s; }
  .widget { background:#fff; border-radius:10px; padding:20px; margin-bottom:20px; box-shadow:0 2px 8px rgba(0,0,0,0.1); display:flex; align-items:center; gap:15px; }
  .widget i { font-size:2rem; color:#007BFF; }
  .widget-info { font-size:1.2rem; }
  .widget-info span { font-weight:bold; font-size:1.5rem; display:block; }
</style>
</head>
<body>

<?php include 'include/sidebar_dashboard.php'; ?>

<main>
  <h2>👋 Bonjour, <?= htmlspecialchars($displayName) ?> !</h2>
  <p>Bienvenue sur votre tableau de bord.</p>

  <div class="widget">
    <i class="fas fa-user-graduate"></i>
    <div class="widget-info">
      Étudiants inscrits
      <span><?= $nb_etudiants ?></span>
    </div>
  </div>

  <div class="widget">
    <i class="fas fa-calendar-check"></i>
    <div class="widget-info">
      Activités & Événements
      <span><?= $nb_activites ?></span>
    </div>
  </div>

  <div class="widget">
    <i class="fas fa-file-alt"></i>
    <div class="widget-info">
      Résultats Académiques
      <span><?= $nb_resultats ?></span>
    </div>
  </div>

  <div class="widget">
    <i class="fas fa-book"></i>
    <div class="widget-info">
      Épreuves Uploadées
      <span><?= $nb_epreuves ?></span>
    </div>
  </div>
</main>

<script>
function toggleSidebar() {
  const sidebar = document.getElementById('sidebarAdmin');
  sidebar.classList.toggle('open');
  document.querySelector('main').style.marginLeft = sidebar.classList.contains('open') ? '250px' : '60px';
}
</script>

</body>
</html>
