<?php
session_start();
require_once 'includes/db.php'; // connexion PDO

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
// --- Récupération de l'année académique en cours ---
$stmt = $pdo->query("SELECT * FROM academic_years WHERE is_current = 1 LIMIT 1");
$current_year = $stmt->fetch(PDO::FETCH_ASSOC);
$year_id = $current_year['id'] ?? null;

// --- Récupération du président actuel ---
$president = null;
if ($year_id) {
    $stmt = $pdo->prepare("
        SELECT a.id_admin, e.nom, e.prenom, e.photo
        FROM administrateurs a
        JOIN etudiants e ON a.id_etudiant = e.id_etudiant
        WHERE a.poste_bureau = 'président'
        LIMIT 1
    ");
    $stmt->execute();
    $president = $stmt->fetch(PDO::FETCH_ASSOC);
}

// --- Récupération des actualités du président pour l'année en cours ---
$actualites = [];
$mot_du_president = null;
if ($president && $year_id) {
    $stmt = $pdo->prepare("
        SELECT titre, contenu, mot_du_president, date_publication 
        FROM actualites
        WHERE id_admin = ? AND id_academic_year = ?
        ORDER BY date_publication DESC
        LIMIT 3
    ");
    $stmt->execute([$president['id_admin'], $year_id]);
    $actualites = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Si le mot du président existe, prendre le dernier
    foreach ($actualites as $actu) {
        if (!empty($actu['mot_du_president'])) {
            $mot_du_president = $actu['mot_du_president'];
            break; // prend le premier trouvé
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>ISSPT - Portail Universitaire</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* =============================================
   ISSPT - PORTAIL UNIVERSITAIRE
   STYLE PREMIUM & MODERNE
   ============================================= */

/* ==================== VARIABLES CSS ==================== */
:root {
  /* Couleurs principales */
  --color-primary: rgb(8, 0, 32);
  --color-accent: rgb(186, 40, 30);
  --color-text: #ffffff;
  --color-text-light: #cccccc;
  --color-dark: #0f0a2c;
  
  /* Couleurs supplémentaires */
  --color-glass: rgba(255, 255, 255, 0.08);
  --color-glass-light: rgba(255, 255, 255, 0.12);
  --color-glass-dark: rgba(0, 0, 0, 0.3);
  --color-overlay: rgba(186, 40, 30, 0.1);
  
  /* Typographie */
  --font-primary: 'Segoe UI', system-ui, -apple-system, sans-serif;
  --font-heading: 'Montserrat', 'Arial Black', sans-serif;
  
  /* Espacements */
  --spacing-xs: 0.5rem;
  --spacing-sm: 1rem;
  --spacing-md: 1.5rem;
  --spacing-lg: 2rem;
  --spacing-xl: 3rem;
  --spacing-xxl: 5rem;
  
  /* Bordures */
  --border-radius-sm: 6px;
  --border-radius-md: 12px;
  --border-radius-lg: 20px;
  --border-radius-xl: 30px;
  
  /* Ombres */
  --shadow-soft: 0 4px 20px rgba(0, 0, 0, 0.15);
  --shadow-medium: 0 8px 30px rgba(0, 0, 0, 0.25);
  --shadow-heavy: 0 15px 50px rgba(0, 0, 0, 0.35);
  --shadow-glow: 0 0 25px rgba(186, 40, 30, 0.3);
  
  /* Transitions */
  --transition-fast: 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  --transition-normal: 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  --transition-slow: 0.45s cubic-bezier(0.4, 0, 0.2, 1);
}

/* ==================== RESET & BASE ==================== */
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

html {
  scroll-behavior: smooth;
}

body {
  font-family: var(--font-primary);
  background: var(--color-primary);
  color: var(--color-text);
  line-height: 1.7;
  overflow-x: hidden;
  min-height: 100vh;
  background-image: 
    radial-gradient(circle at 10% 20%, rgba(186, 40, 30, 0.1) 0%, transparent 20%),
    radial-gradient(circle at 90% 80%, rgba(186, 40, 30, 0.05) 0%, transparent 20%);
}

.container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 var(--spacing-md);
}

/* ==================== ANIMATIONS ==================== */
@keyframes fadeIn {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}

@keyframes fadeUp {
  from {
    opacity: 0;
    transform: translateY(40px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes fadeDown {
  from {
    opacity: 0;
    transform: translateY(-40px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes slideLeft {
  from {
    opacity: 0;
    transform: translateX(40px);
  }
  to {
    opacity: 1;
    transform: translateX(0);
  }
}

@keyframes slideRight {
  from {
    opacity: 0;
    transform: translateX(-40px);
  }
  to {
    opacity: 1;
    transform: translateX(0);
  }
}

@keyframes scaleIn {
  from {
    opacity: 0;
    transform: scale(0.9);
  }
  to {
    opacity: 1;
    transform: scale(1);
  }
}

@keyframes glowPulse {
  0%, 100% {
    box-shadow: var(--shadow-medium);
  }
  50% {
    box-shadow: var(--shadow-glow);
  }
}

@keyframes iconPulse {
  0%, 100% {
    transform: scale(1);
  }
  50% {
    transform: scale(1.1);
  }
}

@keyframes lineSlide {
  from {
    transform: scaleX(0);
  }
  to {
    transform: scaleX(1);
  }
}

@keyframes stagger {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* Classes d'animation */
.fade-in {
  animation: fadeIn 0.8s var(--transition-slow) both;
}

.fade-up {
  animation: fadeUp 0.6s var(--transition-slow) both;
}

.fade-down {
  animation: fadeDown 0.6s var(--transition-slow) both;
}

.slide-left {
  animation: slideLeft 0.6s var(--transition-slow) both;
}

.slide-right {
  animation: slideRight 0.6s var(--transition-slow) both;
}

.scale-in {
  animation: scaleIn 0.4s var(--transition-slow) both;
}

/* ==================== 1️⃣ HEADER PRINCIPAL ==================== */
header {
  background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-dark) 50%, rgba(186, 40, 30, 0.1) 100%);
  backdrop-filter: blur(20px);
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
  position: sticky;
  top: 0;
  z-index: 1000;
  transition: var(--transition-normal);
}

.header-content {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: var(--spacing-md) 0;
  max-width: 1200px;
  margin: 0 auto;
  padding: var(--spacing-md) var(--spacing-md);
}

.logo {
  display: flex;
  align-items: center;
  gap: var(--spacing-sm);
  font-family: var(--font-heading);
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--color-text);
  text-decoration: none;
}

.logo i {
  color: var(--color-accent);
  font-size: 1.8rem;
}

/* Navigation */
nav ul {
  display: flex;
  list-style: none;
  gap: var(--spacing-lg);
  align-items: center;
}

nav a {
  color: var(--color-text);
  text-decoration: none;
  font-weight: 500;
  position: relative;
  padding: var(--spacing-xs) 0;
  transition: var(--transition-normal);
}

nav a::after {
  content: '';
  position: absolute;
  bottom: 0;
  left: 0;
  width: 0;
  height: 2px;
  background: var(--color-accent);
  transition: var(--transition-normal);
  transform-origin: left;
}

nav a:hover {
  color: var(--color-text-light);
}

nav a:hover::after {
  width: 100%;
  animation: lineSlide 0.3s ease;
}

/* Avatar utilisateur */
.user-avatar {
  display: flex;
  align-items: center;
  gap: var(--spacing-sm);
}

.avatar {
  width: 45px;
  height: 45px;
  border-radius: 50%;
  object-fit: cover;
  border: 2px solid var(--color-accent);
  box-shadow: var(--shadow-soft);
  transition: var(--transition-normal);
}

.avatar:hover {
  transform: scale(1.1);
  box-shadow: var(--shadow-glow);
}

.user-info {
  display: flex;
  flex-direction: column;
}

.user-name {
  font-weight: 600;
  font-size: 0.9rem;
}

.user-role {
  font-size: 0.8rem;
  color: var(--color-text-light);
}

/* ==================== 2️⃣ SECTION D'INTRODUCTION ==================== */
.intro-section {
  text-align: center;
  padding: var(--spacing-xxl) var(--spacing-md);
  max-width: 800px;
  margin: 0 auto;
  animation: fadeIn 1s var(--transition-slow);
}

.intro-section h2 {
  font-family: var(--font-heading);
  font-size: clamp(2.5rem, 5vw, 3.5rem);
  font-weight: 800;
  margin-bottom: var(--spacing-lg);
  text-shadow: 0 0 20px rgba(186, 40, 30, 0.2);
  animation: fadeDown 0.8s var(--transition-slow) 0.2s both;
}

.intro-section p {
  font-size: 1.2rem;
  color: var(--color-text-light);
  margin-bottom: var(--spacing-xl);
  line-height: 1.8;
  animation: fadeUp 0.8s var(--transition-slow) 0.4s both;
}

.auth-buttons {
  display: flex;
  gap: var(--spacing-md);
  justify-content: center;
  flex-wrap: wrap;
  animation: fadeUp 0.8s var(--transition-slow) 0.6s both;
}

/* Boutons */
.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: var(--spacing-sm) var(--spacing-lg);
  background: var(--color-accent);
  color: var(--color-text);
  border: none;
  border-radius: var(--border-radius-lg);
  font-weight: 600;
  text-decoration: none;
  transition: var(--transition-normal);
  position: relative;
  overflow: hidden;
  font-size: 1rem;
  cursor: pointer;
  min-width: 160px;
  box-shadow: var(--shadow-medium);
}

.btn::before {
  content: '';
  position: absolute;
  top: 0;
  left: -100%;
  width: 100%;
  height: 100%;
  background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
  transition: var(--transition-normal);
}

.btn:hover {
  transform: translateY(-3px);
  box-shadow: var(--shadow-glow);
}

.btn:hover::before {
  left: 100%;
}

.btn-secondary {
  background: transparent;
  border: 2px solid var(--color-text);
  color: var(--color-text);
}

.btn-secondary:hover {
  background: var(--color-text);
  color: var(--color-primary);
}

/* ==================== 3️⃣ SECTION MODULES ==================== */
.modules-section {
  padding: var(--spacing-xxl) var(--spacing-md);
}

.modules-section h2 {
  text-align: center;
  margin-bottom: var(--spacing-xl);
  animation: fadeIn 0.8s var(--transition-slow);
}

.modules-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
  gap: var(--spacing-lg);
  animation: fadeUp 0.8s var(--transition-slow) 0.2s both;
}

.module-card {
  background: var(--color-glass);
  backdrop-filter: blur(20px);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: var(--border-radius-lg);
  padding: var(--spacing-xl);
  text-align: center;
  transition: var(--transition-normal);
  position: relative;
  overflow: hidden;
  animation: stagger 0.6s var(--transition-slow) both;
}

.module-card:nth-child(1) { animation-delay: 0.1s; }
.module-card:nth-child(2) { animation-delay: 0.2s; }
.module-card:nth-child(3) { animation-delay: 0.3s; }

.module-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 3px;
  background: linear-gradient(90deg, transparent, var(--color-accent), transparent);
  transform: scaleX(0);
  transition: var(--transition-normal);
}

.module-card:hover {
  transform: translateY(-8px) scale(1.05);
  box-shadow: var(--shadow-heavy);
  border-color: var(--color-accent);
}

.module-card:hover::before {
  transform: scaleX(1);
}

.module-icon {
  font-size: 3.5rem;
  color: var(--color-accent);
  margin-bottom: var(--spacing-md);
  transition: var(--transition-normal);
}

.module-card:hover .module-icon {
  animation: iconPulse 1s ease-in-out;
}

.module-card h3 {
  font-family: var(--font-heading);
  font-size: 1.5rem;
  font-weight: 700;
  margin-bottom: var(--spacing-md);
  color: var(--color-text);
}

.module-card p {
  color: var(--color-text-light);
  margin-bottom: var(--spacing-lg);
  line-height: 1.6;
}

.btn-module {
  background: transparent;
  border: 2px solid var(--color-accent);
  color: var(--color-accent);
  font-size: 0.9rem;
  padding: var(--spacing-sm) var(--spacing-md);
}

.btn-module:hover {
  background: var(--color-accent);
  color: var(--color-text);
}

/* ==================== 4️⃣ SECTION ACTUALITÉS ==================== */
.actualites-section {
  padding: var(--spacing-xxl) var(--spacing-md);
}

.actualites-section h2 {
  text-align: center;
  margin-bottom: var(--spacing-xl);
  animation: fadeIn 0.8s var(--transition-slow);
}

.actualites-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: var(--spacing-lg);
  margin-bottom: var(--spacing-xl);
  animation: fadeUp 0.8s var(--transition-slow) 0.2s both;
}

.actualite-card {
  background: var(--color-glass);
  backdrop-filter: blur(15px);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: var(--border-radius-lg);
  padding: var(--spacing-lg);
  transition: var(--transition-normal);
  position: relative;
  overflow: hidden;
  animation: stagger 0.6s var(--transition-slow) both;
}

.actualite-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  width: 4px;
  height: 100%;
  background: var(--color-accent);
  transform: scaleY(0);
  transition: var(--transition-normal);
  transform-origin: top;
}

.actualite-card:hover {
  transform: translateY(-5px);
  box-shadow: var(--shadow-heavy);
}

.actualite-card:hover::before {
  transform: scaleY(1);
}

.actualite-card h3 {
  font-family: var(--font-heading);
  font-size: 1.3rem;
  font-weight: 700;
  margin-bottom: var(--spacing-xs);
  color: var(--color-text);
  padding-left: var(--spacing-sm);
}

.actualite-card small {
  color: var(--color-text-light);
  font-size: 0.8rem;
  margin-bottom: var(--spacing-sm);
  display: block;
  padding-left: var(--spacing-sm);
}

.actualite-card p {
  color: var(--color-text-light);
  line-height: 1.6;
  padding-left: var(--spacing-sm);
}

/* Mot du président */
.mot-president-card {
  background: var(--color-glass-light);
  backdrop-filter: blur(25px);
  border: 1px solid rgba(255, 255, 255, 0.15);
  border-radius: var(--border-radius-lg);
  padding: var(--spacing-xl);
  display: flex;
  gap: var(--spacing-xl);
  align-items: center;
  transition: var(--transition-normal);
  animation: scaleIn 0.8s var(--transition-slow) 0.4s both;
  box-shadow: var(--shadow-medium);
}

.mot-president-card:hover {
  transform: translateY(-5px);
  box-shadow: var(--shadow-heavy);
}

.president-photo {
  flex-shrink: 0;
}

.president-photo img {
  width: 120px;
  height: 120px;
  border-radius: 50%;
  object-fit: cover;
  border: 3px solid var(--color-accent);
  box-shadow: var(--shadow-medium);
  transition: var(--transition-normal);
}

.mot-president-card:hover .president-photo img {
  transform: scale(1.1);
  box-shadow: var(--shadow-glow);
}

.mot-president-text {
  flex: 1;
}

.mot-president-text strong {
  display: block;
  font-family: var(--font-heading);
  font-size: 1.3rem;
  color: var(--color-accent);
  margin-bottom: var(--spacing-md);
}

.mot-president-text p {
  color: var(--color-text-light);
  line-height: 1.7;
  margin-bottom: var(--spacing-md);
}

.president-nom {
  font-weight: 600;
  color: var(--color-text) !important;
  font-style: italic;
  margin-bottom: 0 !important;
}

/* ==================== 5️⃣ FOOTER UNIVERSITAIRE ==================== */
footer {
  background: var(--color-primary);
  color: var(--color-text-light);
  padding: var(--spacing-xxl) 0 var(--spacing-xl);
  margin-top: var(--spacing-xxl);
  border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.footer-content {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: var(--spacing-xl);
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 var(--spacing-md);
}

.footer-column h3 {
  color: var(--color-accent);
  font-family: var(--font-heading);
  font-size: 1.2rem;
  margin-bottom: var(--spacing-md);
  position: relative;
}

.footer-column h3::after {
  content: '';
  position: absolute;
  bottom: -8px;
  left: 0;
  width: 30px;
  height: 2px;
  background: var(--color-accent);
  border-radius: 1px;
}

.footer-column ul {
  list-style: none;
}

.footer-column ul li {
  margin-bottom: var(--spacing-sm);
}

.footer-column a {
  color: var(--color-text-light);
  text-decoration: none;
  transition: var(--transition-normal);
  position: relative;
  padding-bottom: 2px;
}

.footer-column a::after {
  content: '';
  position: absolute;
  bottom: 0;
  left: 0;
  width: 0;
  height: 1px;
  background: var(--color-accent);
  transition: var(--transition-normal);
}

.footer-column a:hover {
  color: var(--color-text);
}

.footer-column a:hover::after {
  width: 100%;
}

.social-icons {
  display: flex;
  gap: var(--spacing-sm);
  margin-top: var(--spacing-md);
}

.social-icons a {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: var(--color-glass);
  display: flex;
  align-items: center;
  justify-content: center;
  transition: var(--transition-normal);
  border: 1px solid rgba(255, 255, 255, 0.1);
}

.social-icons a:hover {
  background: var(--color-accent);
  transform: translateY(-3px);
  box-shadow: var(--shadow-glow);
}

.footer-bottom {
  text-align: center;
  margin-top: var(--spacing-xl);
  padding-top: var(--spacing-md);
  border-top: 1px solid rgba(255, 255, 255, 0.1);
  color: var(--color-text-light);
  font-size: 0.9rem;
}

/* ==================== RESPONSIVE DESIGN ==================== */
@media (max-width: 900px) {
  .modules-grid {
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  }
  
  .mot-president-card {
    flex-direction: column;
    text-align: center;
    gap: var(--spacing-lg);
  }
  
  .footer-content {
    grid-template-columns: repeat(2, 1fr);
  }
  
  nav ul {
    gap: var(--spacing-md);
  }
}

@media (max-width: 768px) {
  .header-content {
    flex-direction: column;
    gap: var(--spacing-md);
    text-align: center;
  }
  
  nav ul {
    flex-wrap: wrap;
    justify-content: center;
  }
  
  .user-avatar {
    flex-direction: column;
    text-align: center;
  }
}

@media (max-width: 600px) {
  :root {
    --spacing-xs: 0.25rem;
    --spacing-sm: 0.75rem;
    --spacing-md: 1rem;
    --spacing-lg: 1.5rem;
    --spacing-xl: 2rem;
    --spacing-xxl: 3rem;
  }
  
  .modules-grid,
  .actualites-grid {
    grid-template-columns: 1fr;
  }
  
  .auth-buttons {
    flex-direction: column;
    align-items: center;
  }
  
  .btn {
    width: 100%;
    max-width: 280px;
  }
  
  .footer-content {
    grid-template-columns: 1fr;
    text-align: center;
  }
  
  .footer-column h3::after {
    left: 50%;
    transform: translateX(-50%);
  }
  
  .social-icons {
    justify-content: center;
  }
}

/* ==================== ACCESSIBILITÉ ==================== */
@media (prefers-reduced-motion: reduce) {
  * {
    animation-duration: 0.01ms !important;
    animation-iteration-count: 1 !important;
    transition-duration: 0.01ms !important;
  }
}

/* Focus visible pour accessibilité */
.btn:focus-visible,
nav a:focus-visible,
.social-icons a:focus-visible {
  outline: 2px solid var(--color-accent);
  outline-offset: 2px;
}

/* États de chargement */
.skeleton {
  background: linear-gradient(90deg, var(--color-glass) 25%, rgba(255,255,255,0.1) 50%, var(--color-glass) 75%);
  background-size: 200% 100%;
  animation: loading 1.5s infinite;
}

@keyframes loading {
  0% {
    background-position: 200% 0;
  }
  100% {
    background-position: -200% 0;
  }
}
    </style>
</head>
<body>

<!-- Inclure le header -->
<?php include 'includes/header.php'; ?>

<main class="container">

    <!-- Section d'introduction -->
    <section class="intro-section">
        <h2>Bienvenue sur le portail étudiant</h2>
        <p>
            Accédez facilement aux modules : activités étudiantes, résultats académiques et banque d’épreuves.
            Ce portail centralise toutes les informations utiles pour votre parcours universitaire.
        </p>
        <div class="auth-buttons">
            <?php if(!$isLogged): ?>
                <a href="connexion.php" class="btn">Se connecter</a>
                <a href="inscription.php" class="btn btn-secondary">Créer un compte</a>
            <?php else: ?>
                <a href="profil.php" class="btn">Mon profil</a>
            <?php endif; ?>
        </div>
    </section>

    <!-- Section : Choisissez un Module -->
    <section class="modules-section">
        <h2>Choisissez un module</h2>
        <div class="modules-grid">

            <!-- Carte 1 : Activités & Fêtes -->
            <div class="module-card">
                <i class="fas fa-calendar-alt module-icon"></i>
                <h3>Activités & Événements</h3>
                <p>Participez aux activités, clubs et événements de l’année. Consultez aussi les archives des éditions précédentes.</p>
                <a href="jet/index.php" class="btn btn-module">Accéder au module</a>
            </div>

            <!-- Carte 2 : Résultats Académiques -->
            <div class="module-card">
                <i class="fas fa-chart-line module-icon"></i>
                <h3>Résultats & Relevés</h3>
                <p>Consultez vos résultats par semestre, téléchargez vos relevés et suivez votre progression académique.</p>
                <a href="resultats.php" class="btn btn-module">Voir mes résultats</a>
            </div>

            <!-- Carte 3 : Épreuves -->
            <div class="module-card">
                <i class="fas fa-book module-icon"></i>
                <h3>Banque d’Épreuves</h3>
                <p>Téléchargez les anciens sujets d’examens par filière, module et année académique.</p>
                <a href="epreuves.php" class="btn btn-module">Accéder à la banque</a>
            </div>

        </div>
    </section>

    <!-- Section Actualités & Mot du Président -->
    <?php if ($president): ?>
    <section class="actualites-section">
        <h2>Actualités du Bureau Étudiant - <?= htmlspecialchars($current_year['label'] ?? '') ?></h2>

        <div class="actualites-grid">
            <!-- Actualités -->
            <?php if (!empty($actualites)): ?>
                <?php foreach ($actualites as $actu): ?>
                    <div class="actualite-card">
                        <h3><?= htmlspecialchars($actu['titre']) ?></h3>
                        <small>Publié le <?= date("d/m/Y à H:i", strtotime($actu['date_publication'])) ?></small>
                        <p><?= nl2br(htmlspecialchars($actu['contenu'])) ?></p>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>Aucune actualité publiée pour le moment.</p>
            <?php endif; ?>
        </div>

        <!-- Mot du président -->
        <?php if ($mot_du_president): ?>
        <div class="mot-president-card">
            <div class="president-photo">
                <img src="<?= htmlspecialchars($president['photo']) ?>" alt="Photo du président">
            </div>
            <div class="mot-president-text">
                <strong>Mot du Président :</strong>
                <p><?= nl2br(htmlspecialchars($mot_du_president)) ?></p>
                <p class="president-nom"><?= htmlspecialchars($president['prenom'] . ' ' . $president['nom']) ?> - Président du Bureau des Étudiants</p>
            </div>
        </div>
        <?php endif; ?>

    </section>
    <?php endif; ?>

</main>

<!-- Inclure le footer -->
<?php include 'includes/footer.php'; ?>

</body>
</html>
