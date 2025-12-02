<?php
session_start();
require_once "../includes/db.php"; // Connexion PDO

// =========================
//  RÉCUPÉRER LES ANNÉES ACADÉMIQUES
// =========================
$yearsStmt = $pdo->query("SELECT * FROM academic_years ORDER BY start_date DESC");
$academic_years = $yearsStmt->fetchAll(PDO::FETCH_ASSOC);

// =========================
//  ANNÉE SÉLECTIONNÉE
// =========================
if (isset($_GET['year'])) {
    $year_id = intval($_GET['year']);
} else {
    // Si pas d'année dans GET, prendre l'année courante
    $stmt = $pdo->query("SELECT * FROM academic_years WHERE is_current = 1 LIMIT 1");
    $currentYear = $stmt->fetch(PDO::FETCH_ASSOC);
    $year_id = $currentYear ? $currentYear['id'] : null;
}

// =========================
//  RÉCUPÉRER ACTIVITÉS
// =========================
$activites = [];
if ($year_id) {
    $stmt = $pdo->prepare("SELECT * FROM activites WHERE academic_year_id = :id ORDER BY date_creation ASC");
    $stmt->execute(["id" => $year_id]);
    $activites = $stmt->fetchAll();
}

// =========================
//  RÉCUPÉRER ÉVÉNEMENTS
// =========================
$evenements = [];
if ($year_id) {
    $stmt = $pdo->prepare("SELECT * FROM evenements WHERE academic_year_id = :id AND is_public = 1 ORDER BY event_start ASC");
    $stmt->execute(["id" => $year_id]);
    $evenements = $stmt->fetchAll();
}

// =========================
//  PRÉSIDENT DU COMITÉ
// =========================

// Récupérer l'année académique courante
$stmtYear = $pdo->query("SELECT id FROM academic_years WHERE is_current = 1 LIMIT 1");
$currentYear = $stmtYear->fetch(PDO::FETCH_ASSOC);

// Vérifier qu'on a bien une année en cours
if ($currentYear) {
    $currentYearId = $currentYear['id'];
} else {
    $currentYearId = 0; // ou gérer l'erreur comme tu veux


}



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
    <title>JET – Journée de l’Étudiant Tarsien</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- AOS (animations) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css">
 <style>
    /* =============================================
   JET - JOURNÉE DE L'ÉTUDIANT TARSIEN
   STYLE PREMIUM & MODERNE
   ============================================= */

/* ==================== VARIABLES CSS ==================== */
:root {
  /* Couleurs principales */
  --color-primary: rgb(8, 0, 32);
  --color-accent: rgb(186, 40, 30);
  --color-text: #ffffff;
  --color-text-light: #dddddd;
  --color-dark: #111111;
  
  /* Couleurs supplémentaires */
  --color-glass: rgba(255, 255, 255, 0.08);
  --color-glass-dark: rgba(0, 0, 0, 0.3);
  --color-overlay: rgba(186, 40, 30, 0.3);
  
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
  --border-radius-sm: 8px;
  --border-radius-md: 14px;
  --border-radius-lg: 20px;
  --border-radius-xl: 30px;
  
  /* Ombres */
  --shadow-soft: 0 4px 20px rgba(0, 0, 0, 0.15);
  --shadow-medium: 0 8px 30px rgba(0, 0, 0, 0.25);
  --shadow-heavy: 0 15px 50px rgba(0, 0, 0, 0.35);
  --shadow-glow: 0 0 25px rgba(186, 40, 30, 0.4);
  
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
  background-image: 
    radial-gradient(circle at 10% 20%, rgba(186, 40, 30, 0.1) 0%, transparent 20%),
    radial-gradient(circle at 90% 80%, rgba(186, 40, 30, 0.05) 0%, transparent 20%);
}

/* ==================== ANIMATIONS AOS-LIKE ==================== */
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

@keyframes fadeLeft {
  from {
    opacity: 0;
    transform: translateX(40px);
  }
  to {
    opacity: 1;
    transform: translateX(0);
  }
}

@keyframes fadeRight {
  from {
    opacity: 0;
    transform: translateX(-40px);
  }
  to {
    opacity: 1;
    transform: translateX(0);
  }
}

@keyframes zoomIn {
  from {
    opacity: 0;
    transform: scale(0.9);
  }
  to {
    opacity: 1;
    transform: scale(1);
  }
}

@keyframes glowRedHover {
  0%, 100% {
    box-shadow: var(--shadow-medium);
  }
  50% {
    box-shadow: var(--shadow-glow);
  }
}

@keyframes pulse {
  0%, 100% {
    transform: scale(1);
  }
  50% {
    transform: scale(1.05);
  }
}

/* Classes d'animation */
[data-aos="fade-up"] {
  animation: fadeUp 0.8s var(--transition-slow) both;
}

[data-aos="fade-down"] {
  animation: fadeDown 0.8s var(--transition-slow) both;
}

[data-aos="fade-left"] {
  animation: fadeLeft 0.8s var(--transition-slow) both;
}

[data-aos="fade-right"] {
  animation: fadeRight 0.8s var(--transition-slow) both;
}

[data-aos="zoom-in"] {
  animation: zoomIn 0.6s var(--transition-slow) both;
}

[data-aos="flip-up"] {
  animation: fadeUp 0.8s var(--transition-slow) both;
}

/* Délais d'animation */
[data-aos-delay="100"] { animation-delay: 0.1s; }
[data-aos-delay="200"] { animation-delay: 0.2s; }
[data-aos-delay="300"] { animation-delay: 0.3s; }

/* ==================== LAYOUT GÉNÉRAL ==================== */
section {
  padding: var(--spacing-xxl) var(--spacing-md);
  max-width: 1200px;
  margin: 0 auto;
}

h2 {
  font-family: var(--font-heading);
  font-size: clamp(2rem, 4vw, 2.8rem);
  font-weight: 700;
  text-align: center;
  margin-bottom: var(--spacing-lg);
  position: relative;
  display: inline-block;
  left: 50%;
  transform: translateX(-50%);
}

h2::after {
  content: '';
  position: absolute;
  bottom: -8px;
  left: 50%;
  transform: translateX(-50%);
  width: 80px;
  height: 4px;
  background: var(--color-accent);
  border-radius: 2px;
}

/* ==================== 1️⃣ HERO SECTION ==================== */
#hero {
  position: relative;
  min-height: 80vh;
  display: flex;
  align-items: center;
  justify-content: center;
  text-align: center;
  background: 
    linear-gradient(135deg, var(--color-primary) 0%, rgba(8, 0, 32, 0.9) 100%),
    var(--color-overlay);
  padding: var(--spacing-xxl) var(--spacing-md);
  overflow: hidden;
}

#hero::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: 
    radial-gradient(ellipse at 20% 50%, rgba(186, 40, 30, 0.2) 0%, transparent 50%),
    radial-gradient(ellipse at 80% 20%, rgba(186, 40, 30, 0.15) 0%, transparent 50%);
  pointer-events: none;
}

#hero > div {
  position: relative;
  z-index: 2;
  max-width: 800px;
}

#hero h1 {
  font-family: var(--font-heading);
  font-size: clamp(2.5rem, 6vw, 4rem);
  font-weight: 800;
  margin-bottom: var(--spacing-md);
  text-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
  line-height: 1.2;
}

#hero p {
  font-size: clamp(1.1rem, 2.5vw, 1.3rem);
  margin-bottom: var(--spacing-lg);
  color: var(--color-text-light);
  font-weight: 300;
}

.hero-buttons {
  display: flex;
  gap: var(--spacing-md);
  justify-content: center;
  flex-wrap: wrap;
  margin-top: var(--spacing-lg);
}


.year-selector {
  position: relative;
  z-index: 1;
  display: inline-block;
}

.year-selector select {
  background: var(--color-neutral-transparent-10);
  color: var(--color-text);
  padding: var(--spacing-xs) var(--spacing-sm);
  border-radius: var(--border-radius-md);
  border: 1px solid var(--color-neutral-transparent-30);
  cursor: pointer;
  transition: var(--transition-fast);
}

.year-selector select:hover {
  background: var(--color-neutral-transparent-30);
}

/* ==================== BOUTONS ==================== */
.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: var(--spacing-sm) var(--spacing-lg);
  background: var(--color-accent);
  color: var(--color-text);
  border-radius: var(--border-radius-lg);
  font-weight: 600;
  text-decoration: none;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  transition: var(--transition-normal);
  position: relative;
  overflow: hidden;
  font-size: 0.9rem;
  border: none;
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
  animation: glowRedHover 2s infinite;
}

.btn:hover::before {
  left: 100%;
}

/* ==================== 2️⃣ SECTION INTRO ==================== */
#intro {
  text-align: center;
  max-width: 700px;
  margin: 0 auto;
  padding: var(--spacing-xxl) var(--spacing-md);
  position: relative;
}

#intro::after {
  content: '';
  position: absolute;
  bottom: 0;
  left: 50%;
  transform: translateX(-50%);
  width: 100px;
  height: 2px;
  background: var(--color-accent);
  border-radius: 1px;
}

#intro p {
  font-size: 1.1rem;
  line-height: 1.8;
  margin-bottom: var(--spacing-md);
  color: var(--color-text-light);
}

/* ==================== 3️⃣ ÉVÉNEMENTS ==================== */
#evenements {
  padding: var(--spacing-xxl) var(--spacing-md);
}

.cards {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
  gap: var(--spacing-lg);
  margin-top: var(--spacing-lg);
}

.card {
  background: var(--color-glass);
  backdrop-filter: blur(15px);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: var(--border-radius-md);
  padding: var(--spacing-lg);
  transition: var(--transition-normal);
  position: relative;
  overflow: hidden;
  box-shadow: var(--shadow-soft);
}

.card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 3px;
  background: linear-gradient(90deg, var(--color-accent), transparent);
}

.card:hover {
  transform: translateY(-5px);
  box-shadow: var(--shadow-heavy);
  border-color: var(--color-accent);
}

.card h3 {
  font-family: var(--font-heading);
  font-size: 1.4rem;
  font-weight: 700;
  margin-bottom: var(--spacing-sm);
  color: var(--color-text);
  position: relative;
  padding-bottom: var(--spacing-xs);
}

.card h3::after {
  content: '';
  position: absolute;
  bottom: 0;
  left: 0;
  width: 40px;
  height: 2px;
  background: var(--color-accent);
  border-radius: 1px;
}

.card p {
  color: var(--color-text-light);
  margin-bottom: var(--spacing-sm);
  line-height: 1.6;
}

/* Timer digital */
.card p[id^="timer-"] {
  font-family: 'Courier New', monospace;
  font-weight: 700;
  color: var(--color-accent);
  background: var(--color-dark);
  padding: var(--spacing-sm);
  border-radius: var(--border-radius-sm);
  text-align: center;
  margin-top: var(--spacing-md);
  border: 1px solid rgba(186, 40, 30, 0.3);
  animation: glowRedHover 3s infinite;
}

/* ==================== 4️⃣ ACTIVITÉS ==================== */
#activites {
  padding: var(--spacing-xxl) var(--spacing-md);
}

#activites .card {
  background: var(--color-glass);
  border: 1px solid rgba(255, 255, 255, 0.1);
  position: relative;
}

#activites .card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 4px;
  background: var(--color-accent);
  border-radius: var(--border-radius-md) var(--border-radius-md) 0 0;
}

#activites .card a {
  display: inline-block;
  padding: var(--spacing-xs) var(--spacing-md);
  background: transparent;
  color: var(--color-accent);
  border: 1px solid var(--color-accent);
  border-radius: var(--border-radius-sm);
  text-decoration: none;
  font-weight: 600;
  transition: var(--transition-normal);
  margin-top: var(--spacing-sm);
}

#activites .card a:hover {
  background: var(--color-accent);
  color: var(--color-text);
  transform: translateY(-2px);
}

/* ==================== 5️⃣ TIMELINE ==================== */
#programme {
  padding: var(--spacing-xxl) var(--spacing-md);
}

.timeline {
  position: relative;
  max-width: 800px;
  margin: var(--spacing-xl) auto;
  padding-left: var(--spacing-lg);
}

.timeline::before {
  content: '';
  position: absolute;
  left: 30px;
  top: 0;
  bottom: 0;
  width: 3px;
  background: linear-gradient(to bottom, 
    transparent 0%, 
    var(--color-accent) 10%, 
    var(--color-accent) 90%, 
    transparent 100%);
  opacity: 0.6;
}

.timeline-entry {
  position: relative;
  background: var(--color-glass);
  backdrop-filter: blur(10px);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: var(--border-radius-md);
  padding: var(--spacing-md);
  margin-bottom: var(--spacing-lg);
  transition: var(--transition-normal);
  box-shadow: var(--shadow-soft);
}

.timeline-entry::before {
  content: '';
  position: absolute;
  left: -44px;
  top: 50%;
  transform: translateY(-50%);
  width: 16px;
  height: 16px;
  background: var(--color-accent);
  border-radius: 50%;
  border: 3px solid var(--color-primary);
  box-shadow: 0 0 0 2px var(--color-accent);
  transition: var(--transition-normal);
  z-index: 2;
}

.timeline-entry:hover {
  transform: translateX(10px);
  border-color: var(--color-accent);
  box-shadow: var(--shadow-medium);
}

.timeline-entry:hover::before {
  transform: translateY(-50%) scale(1.3);
  box-shadow: 0 0 0 3px var(--color-accent), 0 0 20px rgba(186, 40, 30, 0.5);
}

.timeline-entry strong {
  display: block;
  font-size: 1.2rem;
  font-weight: 700;
  margin-bottom: var(--spacing-xs);
  color: var(--color-text);
}

.timeline-entry br {
  margin-bottom: var(--spacing-xs);
  display: block;
  content: '';
}

/* ==================== 6️⃣ MOT DU PRÉSIDENT ==================== */
#mot-president {
  padding: var(--spacing-xxl) var(--spacing-md);
}

.president-block {
  display: flex;
  gap: var(--spacing-xl);
  align-items: center;
  max-width: 1000px;
  margin: 0 auto;
  background: var(--color-glass);
  backdrop-filter: blur(15px);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: var(--border-radius-lg);
  padding: var(--spacing-xl);
  position: relative;
  overflow: hidden;
}

.president-block::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 1px;
  background: linear-gradient(90deg, transparent, var(--color-accent), transparent);
}

.president-block > div:first-child {
  flex-shrink: 0;
}

.president-block img {
  width: 200px;
  height: 200px;
  border-radius: 50%;
  object-fit: cover;
  border: 4px solid var(--color-accent);
  box-shadow: var(--shadow-medium);
  transition: var(--transition-normal);
}

.president-block:hover img {
  transform: scale(1.05);
  box-shadow: var(--shadow-glow);
}

.president-block > div:last-child {
  flex: 1;
}

.president-block p {
  font-size: 1.1rem;
  line-height: 1.8;
  margin-bottom: var(--spacing-md);
  color: var(--color-text-light);
}

.president-block p:last-of-type {
  font-weight: 700;
  color: var(--color-accent);
  font-size: 1.2rem;
  margin-top: var(--spacing-md);
}

.president-block em {
  color: var(--color-text-light);
  font-style: italic;
}

/* ==================== 7️⃣ CTA FINAL ==================== */
#cta {
  text-align: center;
  padding: var(--spacing-xxl) var(--spacing-md);
  background: linear-gradient(135deg, var(--color-accent) 0%, rgba(186, 40, 30, 0.8) 100%);
  border-radius: var(--border-radius-lg);
  margin: var(--spacing-xxl) auto;
  max-width: 1000px;
  position: relative;
  overflow: hidden;
}

#cta::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: 
    radial-gradient(circle at 30% 70%, rgba(255, 255, 255, 0.1) 0%, transparent 50%),
    radial-gradient(circle at 70% 30%, rgba(255, 255, 255, 0.05) 0%, transparent 50%);
  pointer-events: none;
}

#cta h2 {
  color: var(--color-text);
  margin-bottom: var(--spacing-md);
  position: relative;
  z-index: 2;
}

#cta p {
  font-size: 1.2rem;
  margin-bottom: var(--spacing-lg);
  color: var(--color-text);
  position: relative;
  z-index: 2;
}

#cta a {
  display: inline-block;
  padding: var(--spacing-md) var(--spacing-xl);
  background: var(--color-text);
  color: var(--color-accent);
  border-radius: var(--border-radius-lg);
  font-weight: 700;
  text-decoration: none;
  text-transform: uppercase;
  letter-spacing: 1px;
  transition: var(--transition-normal);
  position: relative;
  z-index: 2;
  box-shadow: var(--shadow-medium);
  animation: pulse 2s ease-in-out infinite;
}

#cta a:hover {
  background: var(--color-primary);
  color: var(--color-text);
  transform: translateY(-3px);
  box-shadow: var(--shadow-heavy);
  animation: none;
}

/* ==================== RESPONSIVE DESIGN ==================== */
@media (max-width: 900px) {
  .president-block {
    flex-direction: column;
    text-align: center;
    gap: var(--spacing-lg);
  }
  
  .president-block img {
    width: 150px;
    height: 150px;
  }
  
  .cards {
    grid-template-columns: 1fr;
  }
  
  .timeline {
    padding-left: var(--spacing-md);
  }
  
  .timeline::before {
    left: 20px;
  }
  
  .timeline-entry::before {
    left: -34px;
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
  
  section {
    padding: var(--spacing-xl) var(--spacing-sm);
  }
  
  #hero {
    min-height: 60vh;
    padding: var(--spacing-xl) var(--spacing-sm);
  }
  
  #hero h1 {
    font-size: 2rem;
  }
  
  .hero-buttons {
    flex-direction: column;
    align-items: center;
  }
  
  .btn {
    width: 100%;
    max-width: 280px;
  }
  
  .timeline::before {
    left: 15px;
  }
  
  .timeline-entry::before {
    left: -29px;
    width: 14px;
    height: 14px;
  }
  
  .president-block {
    padding: var(--spacing-lg);
  }
  
  .president-block img {
    width: 120px;
    height: 120px;
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
#cta a:focus-visible,
#activites .card a:focus-visible {
  outline: 2px solid var(--color-accent);
  outline-offset: 2px;
}

/* États de chargement */
.card p[id^="timer-"]:empty::before {
  content: '⏳ Chargement...';
  color: var(--color-text-light);
  font-style: italic;
}
 </style>
</head>

<body>
    <?php include "../includes/header.php"; ?>


<!-- ============================
     HERO / BANNIÈRE 
============================= -->
<!-- ============================
     HERO / BANNIÈRE 
============================= -->
<header id="hero" data-aos="fade-down">
    <div>
        <h1>JET – Journée de l’Étudiant Tarsien</h1>
        <p>Célébration annuelle, échanges, culture, performances et distinctions.</p>

        <!-- SELECT ANNEE ACADÉMIQUE -->
        <form method="GET" class="year-selector">
            <label for="year" >Choisir l'année académique :</label>
            <select name="year" id="year" onchange="this.form.submit()">
                <?php foreach($academic_years as $y): ?>
                    <option value="<?= $y['id'] ?>" <?= ($y['id'] == $year_id) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($y['label']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>

        <div class="hero-buttons">
            <?php
            // Récupération de tous les événements publics pour l'année sélectionnée
            $stmt = $pdo->prepare("SELECT * FROM evenements WHERE is_public = 1 AND academic_year_id = :year ORDER BY event_start ASC");
            $stmt->execute(['year' => $year_id]); // <-- utilisation de $year_id et non $currentYearId
            $evenements_hero = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($evenements_hero as $event) {
                echo '<a href="evenement.php?id=' . $event['id_evenement'] . '" class="btn">' 
                    . htmlspecialchars($event['nom_evenement']) . '</a> ';
            }
            ?>
        </div>

        <div class="hero-buttons">
            <a href="football.php" class="btn">Football</a>
        </div>
    </div>
</header>


<!-- ============================
     INTRODUCTION 
============================= -->
<section id="intro" data-aos="fade-up">
    <h2>Présentation</h2>
    <p>La Journée de l’Étudiant Tarsien (JET) rassemble chaque année toute la communauté.</p>
    <p>⭐ Un moment unique pour fédérer la communauté</p>
    <p>⭐ Activités, compétitions, ateliers et rencontres</p>
</section>

<!-- ÉVÉNEMENTS -->
<section id="evenements" data-aos="fade-up">
    <h2>🎉 Événements</h2>
    <p>Découvrez toutes les sorties, soirées, concerts et compétitions de la JET.</p>
    <div class="cards">
        <?php if(count($evenements) == 0) echo "<p>Aucun événement pour cette année.</p>"; ?>
        <?php foreach ($evenements as $ev): ?>
            <div class="card" data-aos="zoom-in">
                <h3><?= htmlspecialchars($ev["nom_evenement"]) ?> (<?= $ev["type_evenement"] ?>)</h3>
                <p><?= nl2br(htmlspecialchars($ev["description"])) ?></p>
                <?php if(!empty($ev["lieu"])): ?>
                    <p>📍 Lieu : <?= htmlspecialchars($ev["lieu"]) ?></p>
                <?php endif; ?>
                <?php if($ev["prix_ticket"] > 0): ?>
                    <p>💰 Prix du ticket : <?= number_format($ev["prix_ticket"],2) ?> FCFA</p>
                <?php else: ?>
                    <p>💰 Gratuit</p>
                <?php endif; ?>
                <p>🕒 Début : <?= date("d M Y H:i", strtotime($ev["event_start"])) ?></p>
                <p id="timer-<?= $ev['id_evenement'] ?>">⏳ Chargement...</p>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- ============================
     ACTIVITÉS 
============================= -->
<section id="activites" data-aos="fade-up">
    <h2>🌟 Nos Activités</h2>
    <p>Présentation du programme officiel.</p>

    <div class="cards">

        <?php if (count($activites) == 0): ?>
            <p>Aucune activité enregistrée pour cette année académique.</p>
        <?php endif; ?>

        <?php foreach ($activites as $act): ?>
            <div class="card" data-aos="zoom-in">
                <h3><?= htmlspecialchars($act["nom_activite"]) ?></h3>
                <p><?= nl2br(htmlspecialchars($act["description"])) ?></p>

                <?php if (!empty($act["conditions"])): ?>
                    <p><strong>Conditions :</strong> <?= htmlspecialchars($act["conditions"]) ?></p>
                <?php endif; ?>

                <a href="details_activite.php?id=<?= $act['id_activite'] ?>">Voir détails</a>
            </div>
        <?php endforeach; ?>
    </div>
</section>





<!-- ============================
     TIMELINE / PROGRAMME 
============================= -->
<section id="programme" data-aos="fade-left">
    <h2>📅 Programme</h2>

    <div class="timeline">

        <?php if (count($evenements) == 0): ?>
            <p>Aucun événement enregistré pour cette année.</p>
        <?php endif; ?>

        <?php foreach ($evenements as $ev): ?>
            <div class="timeline-entry" data-aos="fade-right">
                <strong><?= htmlspecialchars($ev["nom_evenement"]) ?></strong><br>
                <?= date("d M Y à H:i", strtotime($ev["event_start"])) ?><br>
                <?php if ($ev["lieu"]): ?>
                    📍 <?= htmlspecialchars($ev["lieu"]) ?>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

    </div>
</section>


<!-- ============================
     MOT DU PRÉSIDENT 
============================= -->
<section id="mot-president" data-aos="fade-up">
    <h2>👨‍💼 Mot du Président</h2>

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


<!-- ============================
     CTA FINAL 
============================= -->
<section id="cta" data-aos="flip-up">
    <h2>🎉 Prêt pour la JET ?</h2>
    <p>Rejoignez-nous pour célébrer l’excellence et la communauté tarsienne !</p>

    <a href="#programme">Accéder au programme complet</a>
</section>


<!-- ============================
     FOOTER
============================= -->
<?php include "../includes/footer.php"; ?>


<!-- ============================
     SCRIPTS
============================= -->
<script src="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js"></script>
<script>
    AOS.init();

    // TIMERS POUR LES ÉVÉNEMENTS
    <?php foreach ($evenements as $ev): 
        $id = $ev["id_evenement"]; 
        $date = $ev["event_start"];
    ?>
    (function(){
        const eventDate = new Date("<?= $date ?>").getTime();
        setInterval(() => {
            const now = Date.now();
            const diff = eventDate - now;

            const el = document.getElementById("timer-<?= $id ?>");
            if (!el) return;

            if (diff <= 0) {
                el.innerHTML = "En cours ou terminé";
                return;
            }

            const d = Math.floor(diff / (1000*60*60*24));
            const h = Math.floor((diff % (1000*60*60*24))/(1000*60*60));
            const m = Math.floor((diff % (1000*60*60))/(1000*60));

            el.innerHTML = d+"j "+h+"h "+m+"min";
        }, 60000);
    })();
    <?php endforeach; ?>
</script>

<script src="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js"></script>
<script>
    AOS.init();

    // Countdown pour chaque événement
    <?php foreach ($evenements as $ev): ?>
    (function(){
        const eventDate = new Date("<?= $ev['event_start'] ?>").getTime();
        const timerEl = document.getElementById("timer-<?= $ev['id_evenement'] ?>");
        function updateTimer() {
            const now = new Date().getTime();
            const diff = eventDate - now;
            if(diff <= 0){
                timerEl.innerHTML = "🎉 Événement en cours ou terminé";
                return;
            }
            const d = Math.floor(diff / (1000*60*60*24));
            const h = Math.floor((diff % (1000*60*60*24))/(1000*60*60));
            const m = Math.floor((diff % (1000*60*60))/(1000*60));
            const s = Math.floor((diff % (1000*60))/1000);
            timerEl.innerHTML = d+"j "+h+"h "+m+"m "+s+"s";
        }
        updateTimer();
        setInterval(updateTimer,1000);
    })();
    <?php endforeach; ?>
</script>


</body>
</html>
