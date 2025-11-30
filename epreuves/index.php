<?php
session_start();
require_once '../includes/db.php'; // connexion PDO
use Spatie\PdfToImage\Pdf;

// --- Vérification de la connexion (admin OU étudiant) ---
$isAdmin = isset($_SESSION['admin_id']);
$isEtudiant = isset($_SESSION['etudiant_id']);

if (!$isAdmin && !$isEtudiant) {
    // Si ni admin ni étudiant n’est connecté → redirection
    header("Location: ../etudiant/login_etudiant.php");
    exit;
}

// --- Traitement des filtres et recherche ---
$search = $_GET['search'] ?? '';
$filiere = $_GET['filiere'] ?? '';
$annee = $_GET['annee'] ?? '';
$type = $_GET['type'] ?? '';
$niveau = $_GET['niveau'] ?? '';

// --- Requête SQL principale ---
$query = "
    SELECT e.id_epreuve, e.titre, e.description, e.file_path, 
           e.niveau, e.date_ajout, e.is_public,
           c.nom_category AS type_epreuve,
           y.label AS annee_univ,
           f.nom_filiere
    FROM epreuves e
    LEFT JOIN epreuves_categories c ON e.id_category = c.id_category
    LEFT JOIN academic_years y ON e.academic_year_id = y.id
    LEFT JOIN filieres f ON e.id_filiere = f.id_filiere
    WHERE 1=1
";

$params = [];

if (!empty($search)) { 
    $query .= " AND e.titre LIKE :search"; 
    $params[':search'] = "%$search%"; 
}
if (!empty($filiere)) { 
    $query .= " AND f.nom_filiere = :filiere"; 
    $params[':filiere'] = $filiere; 
}
if (!empty($annee)) { 
    $query .= " AND y.label = :annee"; 
    $params[':annee'] = $annee; 
}
if (!empty($type)) { 
    $query .= " AND c.nom_category = :type"; 
    $params[':type'] = $type; 
}
if (!empty($niveau)) { 
    $query .= " AND e.niveau = :niveau"; 
    $params[':niveau'] = $niveau; 
}

$query .= " ORDER BY e.date_ajout DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$epreuves = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>📚 Recueil d'Épreuves Universitaires</title>
<link rel="stylesheet" href="assets/css/index.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    /* =============================================
   RECUEIL D'ÉPREUVES UNIVERSITAIRES
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
  --border-radius-md: 10px;
  --border-radius-lg: 16px;
  --border-radius-xl: 24px;
  
  /* Ombres */
  --shadow-soft: 0 4px 20px rgba(0, 0, 0, 0.15);
  --shadow-medium: 0 8px 30px rgba(0, 0, 0, 0.25);
  --shadow-heavy: 0 15px 50px rgba(0, 0, 0, 0.35);
  --shadow-glow: 0 0 25px rgba(186, 40, 30, 0.3);
  
  /* Transitions */
  --transition-fast: 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  --transition-normal: 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  --transition-slow: 0.5s cubic-bezier(0.4, 0, 0.2, 1);
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
    transform: translateY(30px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes fadeDown {
  from {
    opacity: 0;
    transform: translateY(-30px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes scaleIn {
  from {
    opacity: 0;
    transform: scale(0.8);
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

@keyframes vibrate {
  0%, 100% {
    transform: translateX(0);
  }
  25% {
    transform: translateX(-2px);
  }
  75% {
    transform: translateX(2px);
  }
}

@keyframes rotate {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
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

.scale-in {
  animation: scaleIn 0.4s var(--transition-slow) both;
}

/* ==================== 1️⃣ HEADER ==================== */
header {
  text-align: center;
  padding: var(--spacing-xxl) var(--spacing-md);
  background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-dark) 100%);
  position: relative;
  overflow: hidden;
  animation: fadeIn 1s var(--transition-slow);
}

header::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: 
    radial-gradient(ellipse at 20% 50%, rgba(186, 40, 30, 0.15) 0%, transparent 50%),
    radial-gradient(ellipse at 80% 20%, rgba(186, 40, 30, 0.1) 0%, transparent 50%);
  pointer-events: none;
}

header h1 {
  font-family: var(--font-heading);
  font-size: clamp(2.5rem, 5vw, 3.5rem);
  font-weight: 800;
  margin-bottom: var(--spacing-md);
  text-shadow: 0 0 20px rgba(186, 40, 30, 0.3);
  position: relative;
  z-index: 2;
}

header p {
  font-size: 1.2rem;
  color: var(--color-text-light);
  max-width: 600px;
  margin: 0 auto;
  position: relative;
  z-index: 2;
  font-weight: 300;
}

/* ==================== 2️⃣ FORMULAIRE DE FILTRES ==================== */
.filters {
  display: flex;
  gap: var(--spacing-md);
  align-items: center;
  flex-wrap: wrap;
  background: var(--color-glass);
  backdrop-filter: blur(20px);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: var(--border-radius-lg);
  padding: var(--spacing-lg);
  margin: var(--spacing-xl) 0;
  animation: fadeUp 0.8s var(--transition-slow) both;
}

.filters > div {
  flex: 1;
  display: flex;
  gap: var(--spacing-sm);
  flex-wrap: wrap;
}

.filters input,
.filters select {
  flex: 1;
  min-width: 150px;
  padding: var(--spacing-sm);
  background: var(--color-glass-dark);
  border: 1px solid rgba(255, 255, 255, 0.2);
  border-radius: var(--border-radius-md);
  color: var(--color-text);
  font-size: 0.95rem;
  transition: var(--transition-normal);
}

.filters input::placeholder {
  color: var(--color-text-light);
}

.filters input:focus,
.filters select:focus {
  outline: none;
  border-color: var(--color-accent);
  box-shadow: 0 0 0 2px rgba(186, 40, 30, 0.2);
}

.filters input:hover,
.filters select:hover {
  border-color: rgba(255, 255, 255, 0.3);
}

.admin-buttons {
  display: flex;
  gap: var(--spacing-sm);
  align-items: center;
}

/* Boutons */
button,
.btn-add {
  padding: var(--spacing-sm) var(--spacing-lg);
  background: var(--color-accent);
  color: var(--color-text);
  border: none;
  border-radius: var(--border-radius-md);
  font-weight: 600;
  cursor: pointer;
  transition: var(--transition-normal);
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: var(--spacing-xs);
  font-size: 0.9rem;
}

button:hover,
.btn-add:hover {
  transform: translateY(-2px);
  box-shadow: var(--shadow-glow);
}

.btn-add {
  background: transparent;
  border: 2px solid var(--color-accent);
}

.btn-add:hover {
  background: var(--color-accent);
}

/* Message de confirmation */
[style*="background:#d4edda"] {
  background: rgba(76, 175, 80, 0.2) !important;
  color: #4caf50 !important;
  padding: var(--spacing-md) !important;
  border-radius: var(--border-radius-md) !important;
  border: 1px solid rgba(76, 175, 80, 0.3) !important;
  margin: var(--spacing-md) 0 !important;
  animation: fadeIn 0.5s ease !important;
}

/* ==================== 3️⃣ GRILLE DES CARTES PDF ==================== */
.epreuve-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
  gap: var(--spacing-lg);
  margin: var(--spacing-xl) 0;
}

.epreuve-card {
  background: var(--color-glass);
  backdrop-filter: blur(15px);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: var(--border-radius-lg);
  padding: var(--spacing-lg);
  transition: var(--transition-normal);
  position: relative;
  overflow: hidden;
  cursor: pointer;
  animation: fadeUp 0.6s var(--transition-slow) both;
}

.epreuve-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 3px;
  background: linear-gradient(90deg, var(--color-accent), transparent);
  transform: scaleX(0);
  transition: var(--transition-normal);
}

.epreuve-card:hover {
  transform: translateY(-8px) scale(1.02);
  box-shadow: var(--shadow-heavy);
  border-color: var(--color-accent);
}

.epreuve-card:hover::before {
  transform: scaleX(1);
}

/* Image de la carte */
.epreuve-card img {
  width: 100%;
  height: 200px;
  object-fit: cover;
  border-radius: var(--border-radius-md);
  margin-bottom: var(--spacing-md);
  transition: var(--transition-normal);
}

.epreuve-card:hover img {
  transform: scale(1.05);
}

/* En-tête de carte */
.epreuve-card-header {
  font-family: var(--font-heading);
  font-size: 1.3rem;
  font-weight: 700;
  margin-bottom: var(--spacing-sm);
  color: var(--color-text);
  line-height: 1.4;
}

/* Badges */
.badge {
  display: inline-block;
  padding: var(--spacing-xs) var(--spacing-sm);
  border-radius: 20px;
  font-size: 0.8rem;
  font-weight: 600;
  margin-right: var(--spacing-xs);
  margin-bottom: var(--spacing-xs);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.badge-type {
  background: rgba(186, 40, 30, 0.2);
  color: var(--color-accent);
  border: 1px solid rgba(186, 40, 30, 0.3);
}

.badge-niveau {
  background: rgba(255, 255, 255, 0.1);
  color: var(--color-text-light);
  border: 1px solid rgba(255, 255, 255, 0.2);
}

/* Pied de carte */
.epreuve-card-footer {
  color: var(--color-text-light);
  font-size: 0.9rem;
  margin: var(--spacing-md) 0;
  padding-top: var(--spacing-sm);
  border-top: 1px solid rgba(255, 255, 255, 0.1);
}

/* Bouton téléchargement */
.btn-download {
  display: inline-flex;
  align-items: center;
  gap: var(--spacing-xs);
  padding: var(--spacing-sm) var(--spacing-md);
  background: var(--color-accent);
  color: var(--color-text);
  text-decoration: none;
  border-radius: var(--border-radius-md);
  font-weight: 600;
  transition: var(--transition-normal);
  width: 100%;
  justify-content: center;
  margin-top: var(--spacing-sm);
}

.btn-download:hover {
  background: rgba(186, 40, 30, 0.9);
  transform: translateY(-2px);
  box-shadow: var(--shadow-medium);
}

/* ==================== 4️⃣ ACTIONS ADMIN ==================== */
.admin-actions {
  position: absolute;
  top: var(--spacing-md);
  right: var(--spacing-md);
  display: flex;
  gap: var(--spacing-xs);
  z-index: 10;
}

.btn-edit,
.btn-delete {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  text-decoration: none;
  transition: var(--transition-normal);
  backdrop-filter: blur(10px);
  border: 1px solid rgba(255, 255, 255, 0.1);
  font-size: 1rem;
}

.btn-edit {
  background: rgba(33, 150, 243, 0.2);
  color: #2196f3;
}

.btn-delete {
  background: rgba(244, 67, 54, 0.2);
  color: #f44336;
}

.btn-edit:hover {
  background: #2196f3;
  color: white;
  transform: scale(1.1);
  animation: vibrate 0.3s ease;
}

.btn-delete:hover {
  background: #f44336;
  color: white;
  transform: scale(1.1);
  animation: vibrate 0.3s ease;
}

/* ==================== 5️⃣ MODAL ==================== */
.modal-epreuve {
  display: none;
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.8);
  backdrop-filter: blur(10px);
  z-index: 1000;
  align-items: center;
  justify-content: center;
  animation: fadeIn 0.3s ease;
}

.modal-content {
  background: var(--color-glass);
  backdrop-filter: blur(30px);
  border: 1px solid rgba(255, 255, 255, 0.2);
  border-radius: var(--border-radius-lg);
  padding: var(--spacing-xl);
  max-width: 500px;
  width: 90%;
  max-height: 80vh;
  overflow-y: auto;
  position: relative;
  animation: scaleIn 0.3s var(--transition-slow);
  box-shadow: var(--shadow-heavy);
}

.modal-close {
  position: absolute;
  top: var(--spacing-md);
  right: var(--spacing-md);
  font-size: 2rem;
  cursor: pointer;
  color: var(--color-text-light);
  transition: var(--transition-normal);
  width: 40px;
  height: 40px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
}

.modal-close:hover {
  color: var(--color-accent);
  background: rgba(255, 255, 255, 0.1);
  transform: rotate(90deg);
}

.modal-content h2 {
  font-family: var(--font-heading);
  font-size: 1.8rem;
  margin-bottom: var(--spacing-md);
  color: var(--color-text);
}

.modal-content p {
  margin-bottom: var(--spacing-sm);
  color: var(--color-text-light);
  line-height: 1.6;
}

.modal-content strong {
  color: var(--color-text);
}

/* ==================== 6️⃣ FOOTER ==================== */
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

/* ==================== RESPONSIVE DESIGN ==================== */
@media (max-width: 900px) {
  .epreuve-grid {
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  }
  
  .filters {
    flex-direction: column;
    align-items: stretch;
  }
  
  .filters > div {
    flex-direction: column;
  }
  
  .admin-buttons {
    justify-content: center;
    margin-top: var(--spacing-md);
  }
  
  .footer-content {
    grid-template-columns: repeat(2, 1fr);
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
  
  .epreuve-grid {
    grid-template-columns: 1fr;
  }
  
  .filters input,
  .filters select {
    min-width: 100%;
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
  
  .modal-content {
    padding: var(--spacing-lg);
    margin: var(--spacing-md);
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
button:focus-visible,
.btn-add:focus-visible,
.btn-download:focus-visible,
.filters input:focus-visible,
.filters select:focus-visible,
.modal-close:focus-visible {
  outline: 2px solid var(--color-accent);
  outline-offset: 2px;
}

/* État de chargement (skeleton) */
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
    <?php include "../includes/header.php"; ?>

<header>
    <h1>📚 Recueil d’Épreuves Universitaires</h1>
    <p>Consultez, recherchez et téléchargez les anciennes épreuves par filière et année.</p>
</header>

<div class="container">

    <!-- Barre de recherche et filtres -->
    <form method="GET" class="filters">
        <div style="flex:1; display:flex; gap:10px; flex-wrap:wrap;">
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Rechercher titre ou matière">
            <select name="filiere">
                <option value="">Filière</option>
                <option value="SIL" <?= $filiere=="SIL"?"selected":"" ?>>SIL</option>
                <option value="RIT" <?= $filiere=="RIT"?"selected":"" ?>>RIT</option>
                <option value="GIT" <?= $filiere=="GIT"?"selected":"" ?>>GIT</option>
            </select>
            <select name="annee">
                <option value="">Année</option>
                <option value="2024-2025" <?= $annee=="2024-2025"?"selected":"" ?>>2024-2025</option>
                <option value="2023-2024" <?= $annee=="2023-2024"?"selected":"" ?>>2023-2024</option>
                <option value="2022-2023" <?= $annee=="2022-2023"?"selected":"" ?>>2022-2023</option>
            </select>
            <select name="type">
                <option value="">Type</option>
                <option value="Examen national" <?= $type=="Examen national"?"selected":"" ?>>Examen national</option>
                <option value="Partiel" <?= $type=="Partiel"?"selected":"" ?>>Partiel</option>
                <option value="TP" <?= $type=="TP"?"selected":"" ?>>TP</option>
                <option value="Devoir surveillé" <?= $type=="Devoir surveillé"?"selected":"" ?>>Devoir surveillé</option>
            </select>
            <select name="niveau">
                <option value="">Niveau</option>
                <option value="1ère année" <?= $niveau=="1ère année"?"selected":"" ?>>1ère année</option>
                <option value="2ème année" <?= $niveau=="2ème année"?"selected":"" ?>>2ème année</option>
                <option value="3ème année" <?= $niveau=="3ème année"?"selected":"" ?>>3ème année</option>
                <option value="autre" <?= $niveau=="autre"?"selected":"" ?>>Autre</option>
            </select>
        </div>
        <div class="admin-buttons">
            <button type="submit">Filtrer</button>
            <?php if ($isAdmin): ?>
                <a href="ajouter_epreuve.php" class="btn-add">+ Ajouter</a>
            <?php endif; ?>
        </div>
    </form>
    <?php if (isset($_GET['message']) && $_GET['message'] === 'supprime'): ?>
        <p style="background:#d4edda; color:#155724; padding:10px; border-radius:5px;">✅ Épreuve supprimée avec succès.</p>
    <?php endif; ?>

    <!-- Cards -->
    <div class="epreuve-grid">
        <?php if (!empty($epreuves)): ?>
            <?php foreach($epreuves as $row): 
                $thumbPath = '../uploads/thumbs/' . pathinfo($row['file_path'], PATHINFO_FILENAME) . '.jpg';
                if(!file_exists($thumbPath)) $thumbPath = '../uploads/thumbs/pdf-icon.jpg';
            ?>
            <div class="epreuve-card"
                data-titre="<?= htmlspecialchars($row['titre']) ?>"
                data-filiere="<?= htmlspecialchars($row['nom_filiere']) ?>"
                data-annee="<?= htmlspecialchars($row['annee_univ']) ?>"
                data-type="<?= htmlspecialchars($row['type_epreuve']) ?>"
                data-niveau="<?= htmlspecialchars($row['niveau']) ?>"
                data-description="<?= htmlspecialchars($row['description']) ?>"
                data-file="<?= htmlspecialchars($row['file_path']) ?>">
                
                <img src="<?= $thumbPath ?>" alt="PDF" style="width:100%; height:200px; object-fit:cover; border-radius:5px; margin-bottom:10px;">
                
                <?php if ($isAdmin): ?>
                <div class="admin-actions">
                    <a href="modifier_epreuve.php?id=<?= $row['id_epreuve'] ?>" class="btn-edit" title="Modifier">✏️</a>
                    <a href="supprimer_epreuve.php?id=<?= $row['id_epreuve'] ?>" class="btn-delete" onclick="return confirm('Supprimer cette épreuve ?');" title="Supprimer">🗑️</a>
                </div>
                <?php endif; ?>

                <div class="epreuve-card-header"><?= htmlspecialchars($row['titre']) ?></div>
                <div>
                    <span class="badge badge-type"><?= htmlspecialchars($row['type_epreuve']) ?></span>
                    <span class="badge badge-niveau"><?= htmlspecialchars($row['niveau']) ?></span>
                </div>
                <div class="epreuve-card-footer"><?= htmlspecialchars($row['nom_filiere']) ?> | <?= htmlspecialchars($row['annee_univ']) ?></div>
                <a id="modal-download" class="btn-download" href="#" download>🔽 Télécharger</a>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p>Aucune épreuve trouvée.</p>
        <?php endif; ?>
    </div>
</div>

<!-- Modal -->
<div class="modal-epreuve" id="modal-epreuve">
    <div class="modal-content">
        <span class="modal-close" id="modal-close">&times;</span>
        <h2 id="modal-titre"></h2>
        <p><strong>Filière:</strong> <span id="modal-filiere"></span></p>
        <p><strong>Année:</strong> <span id="modal-annee"></span></p>
        <p><strong>Type:</strong> <span id="modal-type"></span></p>
        <p><strong>Niveau:</strong> <span id="modal-niveau"></span></p>
        <p id="modal-description"></p>
        <a id="modal-download" class="btn-download" href="#" download>🔽 Télécharger</a>
    </div>
</div>

<?php include "../includes/footer.php"; ?>



<script>
const cards = document.querySelectorAll('.epreuve-card');
const modal = document.getElementById('modal-epreuve');
const modalClose = document.getElementById('modal-close');

cards.forEach(card => {
    card.addEventListener('click', (e) => {
        // empêcher que cliquer sur les boutons admin ouvre le modal
        if (e.target.closest('.btn-edit') || e.target.closest('.btn-delete')) return;
        document.getElementById('modal-titre').innerText = card.dataset.titre;
        document.getElementById('modal-filiere').innerText = card.dataset.filiere;
        document.getElementById('modal-annee').innerText = card.dataset.annee;
        document.getElementById('modal-type').innerText = card.dataset.type;
        document.getElementById('modal-niveau').innerText = card.dataset.niveau;
        document.getElementById('modal-description').innerText = card.dataset.description;
        document.getElementById('modal-download').href = '../uploads/' + card.dataset.file;
        modal.style.display = 'flex';
    });
});

modalClose.addEventListener('click', () => modal.style.display = 'none');
window.addEventListener('click', (e) => { if (e.target === modal) modal.style.display = 'none'; });
</script>

</body>
</html>
