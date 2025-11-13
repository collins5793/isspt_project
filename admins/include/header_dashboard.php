<?php
require_once '../includes/db.php';

// Vérifier si un admin est connecté
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../admins/connexion_admin.php");
    exit;
}

// Récupérer les informations de l'admin
$adminId = $_SESSION['admin_id'];

$sql = "SELECT a.*, e.nom AS etu_nom, e.prenom AS etu_prenom 
        FROM administrateurs a
        LEFT JOIN etudiants e ON a.id_etudiant = e.id_etudiant
        WHERE a.id_admin = :id LIMIT 1";
$stmt = $pdo->prepare($sql);
$stmt->execute(['id' => $adminId]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

// Préparer les variables pour le header
if ($admin['role'] === 'bureau') {
    $displayName = $admin['etu_prenom'] . ' ' . $admin['etu_nom'];
    $displayEmail = $admin['poste_bureau'] ? ucfirst($admin['poste_bureau']) : 'Membre du bureau';
} else {
    $displayName = $admin['prenom'] . ' ' . $admin['nom'];
    $displayEmail = $admin['email'];
}

// Photo (optionnelle)
$avatar = isset($admin['id_etudiant']) && !empty($admin['photo']) ? '../uploads/' . $admin['photo'] : null;
?>



<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Tableau de bord Admin</title>

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<header>
  <button class="sidebar-toggle" onclick="toggleSidebar()">&#9776;</button>
  <h1>Tableau de bord Admin</h1>

  <div class="user-menu" id="userMenu">
    <div class="user-info" onclick="toggleUserMenu()">
        <i class="fas fa-user-circle user-avatar"></i>
        <div class="user-details">
            <div class="user-name"><?= htmlspecialchars($displayName) ?></div>
            <div class="user-email"><?= htmlspecialchars($displayEmail) ?></div>
        </div>
        <i class="fas fa-chevron-down chevron" id="chevron"></i>
    </div>


    <div class="user-actions">
      <button onclick="viewProfile()"><i class="fas fa-user"></i> Voir mon profil</button>
      <button onclick="logout()"><i class="fas fa-sign-out-alt"></i> Déconnexion</button>
    </div>
  </div>
</header>

<script>
  function toggleSidebar() {
    alert("Toggle sidebar ici");
  }

  function toggleUserMenu() {
    const menu = document.getElementById('userMenu');
    const chevron = document.getElementById('chevron');
    menu.classList.toggle('expanded');
    chevron.classList.toggle('rotate');
  }

  function viewProfile() {
    alert("Voir mon profil ici");
  }

  function logout() {
    alert("Déconnexion ici");
  }
</script>

</body>
</html>
