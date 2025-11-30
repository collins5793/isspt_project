<?php
require_once 'db.php'; // connexion PDO


// Initialisation des variables
$isLogged = false;
$isAdmin = false;
$userName = '';
$userAvatar = '../assets/images/default-avatar.png'; // avatar par défaut

// Vérification si un étudiant est connecté
if (isset($_SESSION['etudiant_id'])) {
    $stmt = $pdo->prepare("SELECT nom, prenom, photo FROM etudiants WHERE id_etudiant = ? AND statut = 'actif'");
    $stmt->execute([$_SESSION['etudiant_id']]);
    $etudiant = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($etudiant) {
        $isLogged = true;
        $userName = $etudiant['prenom'] . ' ' . $etudiant['nom'];
        if (!empty($etudiant['photo'])) $userAvatar = '../uploads/etudiants/' . $etudiant['photo'];
    } else {
        // Déconnecter si l'étudiant n'existe pas ou est inactif
        session_unset();
        session_destroy();
        header("Location: login.php");
        exit;
    }
}

// Vérification si un administrateur est connecté
if (isset($_SESSION['admin_id'])) {
    $stmt = $pdo->prepare("SELECT nom, prenom, role FROM administrateurs WHERE id_admin = ?");
    $stmt->execute([$_SESSION['admin_id']]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($admin) {
        $isLogged = true;
        $isAdmin = true;
        $userName = $admin['prenom'] . ' ' . $admin['nom'];
    } else {
        session_unset();
        session_destroy();
        header("Location: login.php");
        exit;
    }
}
?>

<style>
    /* ===== VARIABLES CSS ===== */
:root {
  --primary-red: rgb(186, 40, 30);
  --primary-dark: rgb(8, 0, 32);
  --primary-red-hover: rgba(186, 40, 30, 0.9);
  --primary-dark-hover: rgba(8, 0, 32, 0.95);
  --white: #ffffff;
  --light-gray: #f8f9fa;
  --text-light: #e0e0e0;
  --text-dark: #333333;
  --shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
  --shadow-hover: 0 8px 30px rgba(0, 0, 0, 0.15);
  --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  --border-radius: 8px;
}

/* ===== HEADER PRINCIPAL ===== */
.main-header {
  background: linear-gradient(135deg, var(--primary-dark) 0%, rgba(8, 0, 32, 0.98) 100%);
  backdrop-filter: blur(10px);
  padding: 0 2rem;
  height: 80px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  z-index: 1000;
  box-shadow: var(--shadow);
  border-bottom: 2px solid var(--primary-red);
  transition: var(--transition);
}

.main-header:hover {
  box-shadow: var(--shadow-hover);
  border-bottom-color: var(--primary-red-hover);
}

/* ===== PARTIE GAUCHE (LOGO + TITRE) ===== */
.header-left {
  flex: 0 1 auto;
}

.header-logo {
  display: flex;
  align-items: center;
  text-decoration: none;
  gap: 1rem;
  transition: var(--transition);
  padding: 0.5rem;
  border-radius: var(--border-radius);
}

.header-logo:hover {
  transform: translateY(-2px);
}

.header-logo img {
  width: 50px;
  height: 50px;
  object-fit: contain;
  filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.3));
  transition: var(--transition);
}

.header-logo:hover img {
  transform: scale(1.05);
  filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.4));
}

.header-title h1 {
  color: var(--white);
  font-size: 1.4rem;
  font-weight: 700;
  margin: 0;
  line-height: 1.2;
  text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
  transition: var(--transition);
}

.header-title span {
  color: var(--text-light);
  font-size: 0.85rem;
  font-weight: 400;
  display: block;
  opacity: 0.9;
  transition: var(--transition);
}

.header-logo:hover .header-title h1 {
  color: var(--primary-red);
  text-shadow: 0 2px 8px rgba(186, 40, 30, 0.3);
}

.header-logo:hover .header-title span {
  opacity: 1;
  transform: translateX(5px);
}

/* ===== NAVIGATION PRINCIPALE ===== */
.header-nav {
  flex: 1;
  display: flex;
  justify-content: center;
}

.header-nav ul {
  display: flex;
  list-style: none;
  margin: 0;
  padding: 0;
  gap: 2rem;
}

.header-nav li {
  position: relative;
}

.header-nav a {
  color: var(--text-light);
  text-decoration: none;
  font-weight: 500;
  padding: 0.5rem 1rem;
  border-radius: var(--border-radius);
  transition: var(--transition);
  position: relative;
  overflow: hidden;
}

.header-nav a::before {
  content: '';
  position: absolute;
  bottom: 0;
  left: 50%;
  width: 0;
  height: 2px;
  background: var(--primary-red);
  transition: var(--transition);
  transform: translateX(-50%);
}

.header-nav a:hover {
  color: var(--white);
  background: rgba(186, 40, 30, 0.1);
  transform: translateY(-2px);
}

.header-nav a:hover::before {
  width: 80%;
}

.header-nav a:active {
  transform: translateY(0);
}

/* ===== PARTIE DROITE ===== */
.header-right {
  flex: 0 1 auto;
  display: flex;
  align-items: center;
  gap: 1.5rem;
}

/* ===== RECHERCHE ===== */
.header-search {
  position: relative;
  display: flex;
  align-items: center;
}

.header-search input {
  background: rgba(255, 255, 255, 0.1);
  border: 1px solid rgba(255, 255, 255, 0.2);
  border-radius: 25px;
  padding: 0.6rem 1rem 0.6rem 2.5rem;
  color: var(--white);
  font-size: 0.9rem;
  width: 200px;
  transition: var(--transition);
  backdrop-filter: blur(10px);
}

.header-search input::placeholder {
  color: rgba(255, 255, 255, 0.6);
}

.header-search input:focus {
  outline: none;
  width: 250px;
  background: rgba(255, 255, 255, 0.15);
  border-color: var(--primary-red);
  box-shadow: 0 0 0 3px rgba(186, 40, 30, 0.2);
}

.header-search i {
  position: absolute;
  left: 1rem;
  color: rgba(255, 255, 255, 0.7);
  transition: var(--transition);
}

.header-search input:focus + i {
  color: var(--primary-red);
  transform: scale(1.1);
}

/* ===== BOUTON CONNEXION ===== */
.header-login-btn {
  background: linear-gradient(135deg, var(--primary-red) 0%, var(--primary-red-hover) 100%);
  color: var(--white);
  text-decoration: none;
  padding: 0.7rem 1.5rem;
  border-radius: 25px;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  transition: var(--transition);
  box-shadow: 0 4px 15px rgba(186, 40, 30, 0.3);
  border: none;
  cursor: pointer;
}

.header-login-btn:hover {
  transform: translateY(-3px);
  box-shadow: 0 8px 25px rgba(186, 40, 30, 0.4);
  background: linear-gradient(135deg, var(--primary-red-hover) 0%, var(--primary-red) 100%);
}

.header-login-btn:active {
  transform: translateY(-1px);
}

/* ===== UTILISATEUR CONNECTÉ ===== */
.header-user {
  position: relative;
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.5rem 1rem;
  border-radius: var(--border-radius);
  cursor: pointer;
  transition: var(--transition);
}

.header-user:hover {
  background: rgba(255, 255, 255, 0.1);
}

.user-avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  overflow: hidden;
  border: 2px solid var(--primary-red);
  transition: var(--transition);
}

.header-user:hover .user-avatar {
  transform: scale(1.1);
  border-color: var(--white);
}

.user-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.user-name {
  color: var(--white);
  font-weight: 500;
  max-width: 150px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.header-user i.fa-chevron-down {
  color: var(--text-light);
  font-size: 0.8rem;
  transition: var(--transition);
}

.header-user:hover i.fa-chevron-down {
  transform: rotate(180deg);
  color: var(--primary-red);
}

/* ===== MENU DÉROULANT UTILISATEUR ===== */
.user-dropdown {
  position: absolute;
  top: 100%;
  right: 0;
  background: var(--white);
  min-width: 220px;
  border-radius: var(--border-radius);
  box-shadow: var(--shadow-hover);
  list-style: none;
  margin: 0.5rem 0 0 0;
  padding: 0.5rem 0;
  opacity: 0;
  visibility: hidden;
  transform: translateY(-10px);
  transition: var(--transition);
  z-index: 1001;
}

.header-user:hover .user-dropdown {
  opacity: 1;
  visibility: visible;
  transform: translateY(0);
}

.user-dropdown li {
  margin: 0;
}

.user-dropdown a {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.75rem 1.5rem;
  color: var(--text-dark);
  text-decoration: none;
  transition: var(--transition);
  border-left: 3px solid transparent;
}

.user-dropdown a:hover {
  background: var(--light-gray);
  color: var(--primary-red);
  border-left-color: var(--primary-red);
  padding-left: 1.75rem;
}

.user-dropdown i {
  width: 16px;
  color: var(--primary-red);
}

.user-dropdown a:hover i {
  transform: scale(1.1);
}

.user-dropdown a:last-child {
  border-top: 1px solid rgba(0, 0, 0, 0.1);
  margin-top: 0.25rem;
  padding-top: 1rem;
  color: #dc3545;
}

.user-dropdown a:last-child:hover {
  color: #c82333;
  background: #fff5f5;
}

/* ===== BURGER MENU MOBILE ===== */
.burger-menu {
  display: none;
  color: var(--white);
  font-size: 1.5rem;
  cursor: pointer;
  padding: 0.5rem;
  transition: var(--transition);
  border-radius: var(--border-radius);
}

.burger-menu:hover {
  background: rgba(255, 255, 255, 0.1);
  color: var(--primary-red);
  transform: scale(1.1);
}

/* ===== ANIMATIONS ===== */
@keyframes fadeInDown {
  from {
    opacity: 0;
    transform: translateY(-20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes slideInRight {
  from {
    opacity: 0;
    transform: translateX(20px);
  }
  to {
    opacity: 1;
    transform: translateX(0);
  }
}

.main-header {
  animation: fadeInDown 0.6s ease-out;
}

.header-right > * {
  animation: slideInRight 0.6s ease-out 0.2s both;
}

/* ===== RESPONSIVE ===== */
@media (max-width: 1200px) {
  .header-nav ul {
    gap: 1rem;
  }
  
  .header-search input {
    width: 180px;
  }
  
  .header-search input:focus {
    width: 220px;
  }
}

@media (max-width: 992px) {
  .main-header {
    padding: 0 1rem;
  }
  
  .header-nav {
    display: none;
  }
  
  .header-search {
    display: none;
  }
  
  .burger-menu {
    display: block;
  }
  
  .header-title h1 {
    font-size: 1.2rem;
  }
  
  .header-title span {
    font-size: 0.8rem;
  }
}

@media (max-width: 768px) {
  .main-header {
    height: 70px;
  }
  
  .header-logo img {
    width: 40px;
    height: 40px;
  }
  
  .user-name {
    display: none;
  }
  
  .header-login-btn span {
    display: none;
  }
  
  .header-login-btn {
    padding: 0.7rem;
    border-radius: 50%;
    width: 40px;
    height: 40px;
    justify-content: center;
  }
}

@media (max-width: 480px) {
  .header-title h1 {
    font-size: 1rem;
  }
  
  .header-title span {
    font-size: 0.7rem;
  }
  
  .header-logo {
    gap: 0.5rem;
  }
}

/* ===== SCROLL ANIMATION ===== */
.main-header.scrolled {
  height: 70px;
  background: var(--primary-dark-hover);
  backdrop-filter: blur(20px);
  border-bottom-color: transparent;
}

/* ===== ACCESSIBILITÉ ===== */
@media (prefers-reduced-motion: reduce) {
  * {
    animation-duration: 0.01ms !important;
    animation-iteration-count: 1 !important;
    transition-duration: 0.01ms !important;
  }
}

/* Focus visible pour l'accessibilité */
.header-nav a:focus-visible,
.header-login-btn:focus-visible,
.header-search input:focus-visible {
  outline: 2px solid var(--primary-red);
  outline-offset: 2px;
}
</style>


<header class="main-header">

  <!-- Logo + Nom université -->
  <div class="header-left">
    <a href="index.php" class="header-logo">
      <img src="../assets/images/logo.png" alt="Logo Université">
      <div class="header-title">
        <h1>Université Superieur Saint Paul Tarse</h1>
        <span>Portail Étudiant</span>
      </div>
    </a>
  </div>

  <!-- Menu principal -->
  <nav class="header-nav">
    <ul>
      <li><a href="index.php">Accueil</a></li>
      <li><a href="activites.php">Activités & Événements</a></li>
      <li><a href="resultats.php">Résultats Académiques</a></li>
      <li><a href="epreuves.php">Épreuves</a></li>
      <li><a href="contact.php">Contact</a></li>
    </ul>
  </nav>

  <!-- Boutons utilisateur -->
  <div class="header-right">

    <!-- Recherche -->
    <div class="header-search">
      <input type="text" placeholder="Rechercher...">
      <i class="fas fa-search"></i>
    </div>

    <!-- Si l'utilisateur n'est pas connecté -->
    <?php if (!$isLogged): ?>
      <a href="login.php" class="header-login-btn">
        <i class="fas fa-user"></i> Connexion
      </a>
    <?php else: ?>
      <!-- Si l'utilisateur est connecté -->
      <div class="header-user">
        <span class="user-avatar">
          <img src="<?= $userAvatar ?>" alt="Avatar" />
        </span>
        <span class="user-name"><?= htmlspecialchars($userName) ?></span>
        <i class="fas fa-chevron-down"></i>

        <!-- Menu déroulant -->
        <ul class="user-dropdown">
          <li><a href="profil.php"><i class="fas fa-id-badge"></i> Mon profil</a></li>
          <?php if ($isAdmin): ?>
            <li><a href="dashboard.php"><i class="fas fa-cog"></i> Espace admin</a></li>
          <?php endif; ?>
          <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Déconnexion</a></li>
        </ul>
      </div>
    <?php endif; ?>

  </div>

  <!-- Burger menu pour mobile -->
  <div class="burger-menu">
    <i class="fas fa-bars"></i>
  </div>

</header>
