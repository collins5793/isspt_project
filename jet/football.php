<?php
session_start(); // ⚠️ très important pour $_SESSION

require_once '../includes/db.php';


// ---------------------------
// Vérification si l'utilisateur est connecté
// ---------------------------
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
// Charger toutes les saisons disponibles
$all_seasons = $pdo->query("
    SELECT fs.id_season, fs.label, ay.label AS academic_year
    FROM football_seasons fs
    JOIN academic_years ay ON ay.id = fs.academic_year_id
    ORDER BY fs.id_season DESC
")->fetchAll(PDO::FETCH_ASSOC);

// ---------------------------------------
// Saison active
// ---------------------------------------
// Vérifier si l'utilisateur a choisi une saison
if (isset($_GET['season']) && ctype_digit($_GET['season'])) {
    $season_id = (int) $_GET['season'];

    // Charger la saison sélectionnée
    $season = $pdo->query("
        SELECT fs.*, ay.label AS academic_year
        FROM football_seasons fs
        JOIN academic_years ay ON ay.id = fs.academic_year_id
        WHERE fs.id_season = $season_id
        LIMIT 1
    ")->fetch();
}

// Si aucune saison choisie → prendre la saison active
if (empty($season)) {
    $season = $pdo->query("
        SELECT fs.*, ay.label AS academic_year
        FROM football_seasons fs
        JOIN academic_years ay ON ay.id = fs.academic_year_id
        WHERE fs.is_active = 1
        LIMIT 1
    ")->fetch();
}

if (!$season) die("<h2>Aucune saison disponible</h2>");

$season_id = $season['id_season'];


// ---------------------------------------
// KPIs
// ---------------------------------------
$kpi = [
    'equipes' => $pdo->query("SELECT COUNT(*) FROM football_teams WHERE season_id=$season_id")->fetchColumn(),
    'poules' => $pdo->query("SELECT COUNT(*) FROM pools WHERE season_id=$season_id")->fetchColumn(),
    'matchs_joues' => $pdo->query("SELECT COUNT(*) FROM matches WHERE season_id=$season_id AND is_played=1")->fetchColumn(),
    'matchs_total' => $pdo->query("SELECT COUNT(*) FROM matches WHERE season_id=$season_id")->fetchColumn(),
    'buts' => $pdo->query("SELECT COUNT(*) FROM match_goals WHERE season_id=$season_id")->fetchColumn(),
    'phase' => ($pdo->query("SELECT COUNT(*) FROM matches WHERE season_id=$season_id AND stage='group' AND is_played=0")->fetchColumn() == 0) ? 'Phases finales' : 'Phase de poules',
    'matchs_avenir' => $pdo->query("SELECT COUNT(*) FROM matches WHERE season_id=$season_id AND match_datetime >= NOW()")->fetchColumn()
];

// ---------------------------------------
// Meilleur buteur
// ---------------------------------------
$top_scorer = $pdo->query("
    SELECT tp.player_id, tp.full_name, tp.team_id, t.name AS team_name, e.photo, COUNT(mg.goal_id) AS total_buts
    FROM match_goals mg
    JOIN team_players tp ON tp.player_id = mg.player_id
    JOIN football_teams t ON t.team_id = tp.team_id
    LEFT JOIN etudiants e ON e.id_etudiant = tp.user_id
    WHERE mg.season_id = $season_id
    GROUP BY mg.player_id
    ORDER BY total_buts DESC
    LIMIT 1
")->fetch();

// ---------------------------------------
// Prochain match
// ---------------------------------------
$next_match = $pdo->query("
    SELECT m.*, t1.name AS team1, t1.short_code AS code1, t2.name AS team2, t2.short_code AS code2
    FROM matches m
    JOIN football_teams t1 ON t1.team_id = m.team1_id
    JOIN football_teams t2 ON t2.team_id = m.team2_id
    WHERE m.season_id = $season_id AND m.match_datetime >= NOW()
    ORDER BY m.match_datetime ASC
    LIMIT 1
")->fetch();

// ---------------------------------------
// Tous les matchs (carrousel & Match Center)
// ---------------------------------------
$matches = $pdo->query("
    SELECT m.*, t1.name AS team1, t2.name AS team2, t1.short_code AS code1, t2.short_code AS code2,
           p.name AS pool_name
    FROM matches m
    JOIN football_teams t1 ON t1.team_id = m.team1_id
    JOIN football_teams t2 ON t2.team_id = m.team2_id
    LEFT JOIN pools p ON m.pool_id = p.pool_id
    WHERE m.season_id = $season_id
    ORDER BY m.match_datetime ASC
")->fetchAll();

// ---------------------------------------
// Fan Zone
// ---------------------------------------
$comments = $pdo->query("
    SELECT c.*, e.nom, e.prenom
    FROM commentaire_football c
    LEFT JOIN etudiants e ON c.id_etudiant = e.id_etudiant
    ORDER BY c.date_commentaire_football DESC
    LIMIT 20
")->fetchAll();

// ---------------------------------------
// Phases finales
// ---------------------------------------
$finals = $pdo->query("
    SELECT m.*, t1.name AS team1, t2.name AS team2
    FROM matches m
    JOIN football_teams t1 ON m.team1_id = t1.team_id
    JOIN football_teams t2 ON m.team2_id = t2.team_id
    WHERE m.season_id=$season_id AND m.stage != 'group'
    ORDER BY FIELD(m.stage,'quarter','semi','third_place','final'), m.round_number ASC
")->fetchAll();

// ---------------------------------------
// Classements par poule
// ---------------------------------------
$stmt = $pdo->prepare("SELECT pool_id, name, advance_count FROM pools WHERE season_id=? ORDER BY name");
$stmt->execute([$season_id]);
$poules = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ---------------------------------------
// Enregistrement d'un commentaire
// ---------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['message']) && !empty($_POST['user_id'])) {
    $user_id = (int) $_POST['user_id'];
    $message = trim($_POST['message']);

    if ($message !== '') {
        $stmt = $pdo->prepare("
            INSERT INTO commentaire_football (id_etudiant, message, date_commentaire_football)
            VALUES (:user_id, :message, NOW())
        ");
        $stmt->execute([
            ':user_id' => $user_id,
            ':message' => $message
        ]);

        // Redirection pour éviter le double envoi si on rafraîchit la page
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($season['label']) ?> - Football</title>
<style>
    /* =============================================
   FOOTBALL UNIVERSITAIRE - STYLE MODERNE & RESPONSIVE
   ============================================= */

/* ==================== VARIABLES CSS ==================== */
:root {
  /* Couleurs principales */
  --color-primary: rgb(8, 0, 32);
  --color-accent: rgb(186, 40, 30);
  --color-text: #ffffff;
  --color-neutral-light: #f2f2f2;
  --color-neutral-transparent-10: rgba(255, 255, 255, 0.1);
  --color-neutral-transparent-30: rgba(255, 255, 255, 0.3);
  
  /* Couleurs supplémentaires */
  --color-qualified: rgba(76, 175, 80, 0.2);
  --color-running: rgba(255, 255, 255, 0.05);
  --color-live: #ffeb3b;
  --color-finished: rgba(76, 175, 80, 0.7);
  --color-upcoming: rgba(33, 150, 243, 0.7);
  
  /* Typographie */
  --font-primary: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
  --font-heading: 'Arial Black', 'Segoe UI', sans-serif;
  
  /* Espacements */
  --spacing-xs: 0.5rem;
  --spacing-sm: 1rem;
  --spacing-md: 1.5rem;
  --spacing-lg: 2rem;
  --spacing-xl: 3rem;
  
  /* Bordures */
  --border-radius-sm: 4px;
  --border-radius-md: 8px;
  --border-radius-lg: 12px;
  --border-radius-xl: 16px;
  
  /* Ombres */
  --shadow-light: 0 2px 8px rgba(0, 0, 0, 0.1);
  --shadow-medium: 0 4px 12px rgba(0, 0, 0, 0.15);
  --shadow-heavy: 0 8px 24px rgba(0, 0, 0, 0.2);
  
  /* Transitions */
  --transition-fast: 0.2s ease;
  --transition-normal: 0.3s ease;
  --transition-slow: 0.5s ease;
}

/* ==================== RESET MINIMALISTE ==================== */
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
  background-color: var(--color-primary);
  color: var(--color-text);
  line-height: 1.6;
  overflow-x: hidden;
}

img {
  max-width: 100%;
  height: auto;
}

ul, ol {
  list-style: none;
}

a {
  text-decoration: none;
  color: inherit;
}

button, input, select, textarea {
  font-family: inherit;
  font-size: inherit;
  border: none;
  outline: none;
}

/* ==================== LAYOUT GLOBAL ==================== */
.container {
  width: 100%;
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 var(--spacing-md);
}

.section {
  padding: var(--spacing-xl) 0;
}

.section-title {
  font-family: var(--font-heading);
  font-size: 2rem;
  margin-bottom: var(--spacing-lg);
  position: relative;
  display: inline-block;
}

.section-title::after {
  content: '';
  position: absolute;
  bottom: -8px;
  left: 0;
  width: 60px;
  height: 3px;
  background: var(--color-accent);
  border-radius: 2px;
}

.empty-msg {
  text-align: center;
  padding: var(--spacing-lg);
  background: var(--color-neutral-transparent-10);
  border-radius: var(--border-radius-md);
  font-style: italic;
  color: var(--color-neutral-transparent-30);
}

/* ==================== HERO SECTION ==================== */
.hero {
  position: relative;
  padding: var(--spacing-xl) 0;
  background: 
    linear-gradient(135deg, rgba(8, 0, 32, 0.9) 0%, rgba(8, 0, 32, 0.7) 100%),
    url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 800"><rect width="1200" height="800" fill="%23080020"/><circle cx="200" cy="200" r="100" fill="%23ba281e" opacity="0.1"/><circle cx="900" cy="500" r="150" fill="%23ba281e" opacity="0.05"/><circle cx="600" cy="100" r="80" fill="%23ba281e" opacity="0.08"/></svg>');
  background-size: cover;
  background-position: center;
  text-align: center;
  overflow: hidden;
  animation: fadeInUp 1s var(--transition-slow);
}

.hero::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: radial-gradient(circle at 30% 40%, rgba(186, 40, 30, 0.2) 0%, transparent 50%),
              radial-gradient(circle at 70% 60%, rgba(186, 40, 30, 0.15) 0%, transparent 50%);
  pointer-events: none;
}

.hero h1 {
  font-family: var(--font-heading);
  font-size: 3.5rem;
  margin-bottom: var(--spacing-sm);
  text-shadow: 0 2px 10px rgba(0, 0, 0, 0.5);
  position: relative;
  z-index: 1;
}

.hero p {
  font-size: 1.2rem;
  margin-bottom: var(--spacing-lg);
  max-width: 600px;
  margin-left: auto;
  margin-right: auto;
  position: relative;
  z-index: 1;
}

.hero > div {
  display: flex;
  justify-content: center;
  gap: var(--spacing-md);
  flex-wrap: wrap;
  margin-bottom: var(--spacing-lg);
  position: relative;
  z-index: 1;
}

.season-selector {
  position: relative;
  z-index: 1;
  display: inline-block;
}

.season-selector select {
  background: var(--color-neutral-transparent-10);
  color: var(--color-text);
  padding: var(--spacing-xs) var(--spacing-sm);
  border-radius: var(--border-radius-md);
  border: 1px solid var(--color-neutral-transparent-30);
  cursor: pointer;
  transition: var(--transition-fast);
}

.season-selector select:hover {
  background: var(--color-neutral-transparent-30);
}

/* ==================== BOUTONS ==================== */
.btn {
  display: inline-block;
  background: var(--color-accent);
  color: var(--color-text);
  padding: var(--spacing-sm) var(--spacing-lg);
  border-radius: var(--border-radius-md);
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  transition: var(--transition-normal);
  box-shadow: var(--shadow-medium);
  position: relative;
  overflow: hidden;
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
  box-shadow: 0 6px 20px rgba(186, 40, 30, 0.4);
}

.btn:hover::before {
  left: 100%;
}

/* ==================== KPI GRID ==================== */
.kpi-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: var(--spacing-md);
  margin-bottom: var(--spacing-xl);
}

.kpi {
  background: var(--color-neutral-transparent-10);
  border-radius: var(--border-radius-lg);
  padding: var(--spacing-md);
  text-align: center;
  box-shadow: var(--shadow-light);
  transition: var(--transition-normal);
  animation: zoomSoftly 0.5s ease-out;
  border: 1px solid transparent;
}

.kpi:hover {
  transform: translateY(-5px);
  box-shadow: var(--shadow-heavy);
  border-color: var(--color-accent);
  background: var(--color-neutral-transparent-30);
}

.kpi h2 {
  font-size: 2.5rem;
  color: var(--color-accent);
  margin-bottom: var(--spacing-xs);
  font-weight: 700;
}

.kpi p {
  font-size: 0.9rem;
  text-transform: uppercase;
  letter-spacing: 1px;
  color: var(--color-neutral-light);
}

/* ==================== TOP BUTEUR ==================== */
.top-scorer {
  display: flex;
  align-items: center;
  background: var(--color-neutral-transparent-10);
  border-radius: var(--border-radius-lg);
  padding: var(--spacing-md);
  margin-top: var(--spacing-md);
  transition: var(--transition-normal);
  position: relative;
  overflow: hidden;
  grid-column: 1 / -1;
}

.top-scorer::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: radial-gradient(circle at 80% 20%, rgba(186, 40, 30, 0.1) 0%, transparent 50%);
  pointer-events: none;
}

.top-scorer:hover {
  transform: translateY(-5px);
  box-shadow: var(--shadow-heavy);
  background: var(--color-neutral-transparent-30);
}

.top-scorer img {
  width: 80px;
  height: 80px;
  border-radius: 50%;
  object-fit: cover;
  margin-right: var(--spacing-md);
  border: 3px solid var(--color-accent);
  box-shadow: 0 0 15px rgba(186, 40, 30, 0.3);
}

.top-scorer h3 {
  font-size: 0.9rem;
  text-transform: uppercase;
  letter-spacing: 1px;
  color: var(--color-accent);
  margin-bottom: var(--spacing-xs);
}

.top-scorer h2 {
  font-size: 1.5rem;
  margin-bottom: var(--spacing-xs);
}

.top-scorer p {
  color: var(--color-neutral-light);
  font-size: 0.9rem;
}

/* ==================== PROCHAIN MATCH ==================== */
.featured {
  background: 
    linear-gradient(135deg, var(--color-accent) 0%, rgba(186, 40, 30, 0.8) 100%);
  border-radius: var(--border-radius-lg);
  padding: var(--spacing-lg);
  margin-bottom: var(--spacing-xl);
  text-align: center;
  position: relative;
  overflow: hidden;
  box-shadow: var(--shadow-medium);
  border: 1px solid rgba(255, 255, 255, 0.2);
  animation: glowingBorder 3s infinite alternate;
}

.featured::before {
  content: '';
  position: absolute;
  top: -50%;
  left: -50%;
  width: 200%;
  height: 200%;
  background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
  transform: rotate(30deg);
}

.featured h2 {
  font-size: 1.8rem;
  margin-bottom: var(--spacing-sm);
  position: relative;
  z-index: 1;
}

.featured p {
  margin-bottom: var(--spacing-xs);
  position: relative;
  z-index: 1;
  font-size: 1.1rem;
}

/* ==================== CARROUSEL ==================== */
.carousel {
  display: flex;
  gap: var(--spacing-md);
  overflow-x: auto;
  padding: var(--spacing-md) 0;
  scrollbar-width: thin;
  scrollbar-color: var(--color-accent) var(--color-primary);
  margin-bottom: var(--spacing-xl);
}

.carousel::-webkit-scrollbar {
  height: 8px;
}

.carousel::-webkit-scrollbar-track {
  background: var(--color-primary);
  border-radius: 4px;
}

.carousel::-webkit-scrollbar-thumb {
  background: var(--color-accent);
  border-radius: 4px;
}

.carousel-card {
  flex: 0 0 280px;
  background: var(--color-neutral-transparent-10);
  border-radius: var(--border-radius-lg);
  padding: var(--spacing-md);
  transition: var(--transition-normal);
  box-shadow: var(--shadow-light);
  border: 1px solid transparent;
}

.carousel-card:hover {
  transform: translateY(-8px);
  box-shadow: var(--shadow-heavy);
  border-color: var(--color-accent);
  background: var(--color-neutral-transparent-30);
}

.carousel-card h3 {
  font-size: 1.2rem;
  margin-bottom: var(--spacing-xs);
  color: var(--color-accent);
}

.carousel-card p {
  margin-bottom: var(--spacing-xs);
  font-size: 0.9rem;
}

.carousel-card b {
  color: var(--color-accent);
  font-size: 1.1rem;
}

/* ==================== CLASSEMENTS ==================== */
.standings-wrapper {
  margin-bottom: var(--spacing-xl);
}

/* Onglets */
.tabs {
  display: flex;
  gap: var(--spacing-sm);
  margin-bottom: var(--spacing-md);
  flex-wrap: wrap;
}

.tab-btn {
  background: var(--color-neutral-transparent-10);
  color: var(--color-text);
  padding: var(--spacing-sm) var(--spacing-md);
  border-radius: var(--border-radius-md);
  cursor: pointer;
  transition: var(--transition-fast);
  font-weight: 500;
}

.tab-btn:hover {
  background: var(--color-neutral-transparent-30);
}

.tab-btn.active {
  background: var(--color-accent);
  color: var(--color-text);
  box-shadow: var(--shadow-medium);
}

/* Tables */
.pool-table {
  transition: var(--transition-normal);
}

.pool-table.hidden {
  display: none;
}

.standings-table {
  width: 100%;
  border-collapse: collapse;
  margin-bottom: var(--spacing-lg);
  border-radius: var(--border-radius-md);
  overflow: hidden;
  box-shadow: var(--shadow-light);
}

.standings-table thead {
  background: var(--color-accent);
}

.standings-table th {
  padding: var(--spacing-sm);
  text-align: center;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  font-size: 0.85rem;
}

.standings-table tbody tr {
  background: var(--color-neutral-transparent-10);
  transition: var(--transition-fast);
}

.standings-table tbody tr:nth-child(even) {
  background: var(--color-neutral-transparent-30);
}

.standings-table tbody tr:hover {
  background: rgba(186, 40, 30, 0.2);
}

.standings-table td {
  padding: var(--spacing-sm);
  text-align: center;
  border-bottom: 1px solid var(--color-neutral-transparent-10);
}

.standings-table .qualified {
  background: var(--color-qualified);
  font-weight: 600;
}

.standings-table .running {
  background: var(--color-running);
}

/* ==================== BADGES DE STATUT ==================== */
.status-upcoming,
.status-live,
.status-finished {
  display: inline-block;
  padding: 4px 12px;
  border-radius: 20px;
  font-size: 0.8rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.status-upcoming {
  background: var(--color-upcoming);
  color: white;
}

.status-live {
  background: var(--color-live);
  color: var(--color-primary);
  animation: glowPulse 2s infinite;
}

.status-finished {
  background: var(--color-finished);
  color: white;
}

/* ==================== FAN ZONE ==================== */
form {
  background: var(--color-neutral-transparent-10);
  padding: var(--spacing-md);
  border-radius: var(--border-radius-lg);
  margin-bottom: var(--spacing-lg);
  box-shadow: var(--shadow-light);
}

form textarea {
  width: 100%;
  background: var(--color-primary);
  color: var(--color-text);
  border: 1px solid var(--color-neutral-transparent-30);
  border-radius: var(--border-radius-md);
  padding: var(--spacing-sm);
  margin-bottom: var(--spacing-sm);
  resize: vertical;
  min-height: 80px;
  transition: var(--transition-fast);
}

form textarea:focus {
  border-color: var(--color-accent);
  box-shadow: 0 0 0 2px rgba(186, 40, 30, 0.3);
}

form button {
  background: var(--color-accent);
  color: var(--color-text);
  padding: var(--spacing-sm) var(--spacing-lg);
  border-radius: var(--border-radius-md);
  cursor: pointer;
  transition: var(--transition-normal);
  font-weight: 600;
}

form button:hover {
  background: rgba(186, 40, 30, 0.9);
  transform: translateY(-2px);
  box-shadow: var(--shadow-medium);
}

.comment {
  background: var(--color-neutral-transparent-10);
  border-radius: var(--border-radius-md);
  padding: var(--spacing-md);
  margin-bottom: var(--spacing-sm);
  animation: fadeInUp 0.5s ease-out;
  border-left: 3px solid var(--color-accent);
  transition: var(--transition-fast);
}

.comment:hover {
  background: var(--color-neutral-transparent-30);
  transform: translateX(5px);
}

.comment strong {
  color: var(--color-accent);
  display: block;
  margin-bottom: var(--spacing-xs);
}

.comment small {
  color: var(--color-neutral-transparent-30);
  font-size: 0.8rem;
}

/* ==================== ANIMATIONS ==================== */
@keyframes fadeInUp {
  from {
    opacity: 0;
    transform: translateY(30px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes smoothSlide {
  from {
    opacity: 0;
    transform: translateX(-20px);
  }
  to {
    opacity: 1;
    transform: translateX(0);
  }
}

@keyframes glowPulse {
  0% {
    box-shadow: 0 0 5px var(--color-live);
  }
  50% {
    box-shadow: 0 0 15px var(--color-live);
  }
  100% {
    box-shadow: 0 0 5px var(--color-live);
  }
}

@keyframes hoverLift {
  from {
    transform: translateY(0);
  }
  to {
    transform: translateY(-5px);
  }
}

@keyframes glowingBorder {
  0% {
    box-shadow: 0 0 5px rgba(186, 40, 30, 0.5);
  }
  100% {
    box-shadow: 0 0 20px rgba(186, 40, 30, 0.8);
  }
}

@keyframes zoomSoftly {
  from {
    opacity: 0;
    transform: scale(0.9);
  }
  to {
    opacity: 1;
    transform: scale(1);
  }
}

/* ==================== RESPONSIVE DESIGN ==================== */
@media (max-width: 768px) {
  .hero h1 {
    font-size: 2.5rem;
  }
  
  .hero > div {
    flex-direction: column;
    align-items: center;
  }
  
  .btn {
    width: 100%;
    max-width: 250px;
    text-align: center;
  }
  
  .kpi-grid {
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
  }
  
  .top-scorer {
    flex-direction: column;
    text-align: center;
  }
  
  .top-scorer img {
    margin-right: 0;
    margin-bottom: var(--spacing-sm);
  }
  
  .tabs {
    flex-direction: column;
  }
  
  .tab-btn {
    text-align: center;
  }
  
  .standings-table {
    font-size: 0.8rem;
  }
  
  .standings-table th, 
  .standings-table td {
    padding: var(--spacing-xs);
  }
}

@media (max-width: 480px) {
  .hero h1 {
    font-size: 2rem;
  }
  
  .hero p {
    font-size: 1rem;
  }
  
  .kpi-grid {
    grid-template-columns: 1fr 1fr;
  }
  
  .kpi h2 {
    font-size: 2rem;
  }
  
  .section-title {
    font-size: 1.5rem;
  }
  
  .carousel-card {
    flex: 0 0 220px;
  }
}
</style>
</head>
<body>
<?php include '../includes/header.php'; ?>  
<!-- HERO -->
<div class="hero">
    <h1><?= htmlspecialchars($season['label']) ?></h1>
    <p>Année académique : <?= htmlspecialchars($season['academic_year']) ?></p>
    <div>
        <a class="btn" href="#">Voir la saison</a>
        <a class="btn" href="#">Classements</a>
        <a class="btn" href="#">Calendrier</a>
        <a class="btn" href="#">Équipes</a>
    </div>
    <form method="GET" class="season-selector">
    <select name="season" onchange="this.form.submit()">
        <?php foreach ($all_seasons as $s): ?>
            <option value="<?= $s['id_season'] ?>" 
                <?= $s['id_season'] == $season_id ? 'selected' : '' ?>>
                <?= htmlspecialchars($s['label']) ?> - 
                <?= htmlspecialchars($s['academic_year']) ?>
            </option>
        <?php endforeach; ?>
    </select>
</form>

</div>
<!-- KPIs -->
<div class="kpi-grid">

    <?php if (!empty($kpi)): ?>
        <?php foreach($kpi as $key => $val): ?>
            <div class="kpi">
                <h2><?= htmlspecialchars($val) ?></h2>
                <p><?= ucfirst(str_replace('_',' ',$key)) ?></p>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p class="empty-msg">Aucune statistique disponible pour cette saison.</p>
    <?php endif; ?>

    <?php if (!empty($top_scorer)): ?>
        <div class="top-scorer">
            <img src="<?= $top_scorer['photo'] ? '../storage/'.$top_scorer['photo'] : '/images/avatar.png' ?>" alt="Top Buteur">
            <div>
                <h3>🏆 TOP BUTEUR</h3>
                <h2><?= htmlspecialchars($top_scorer['full_name']) ?></h2>
                <p><?= htmlspecialchars($top_scorer['team_name']) ?> — <?= $top_scorer['total_buts'] ?> buts</p>
            </div>
        </div>
    <?php endif; ?>

</div>



<!-- Prochain match -->
<?php if (!empty($next_match)): ?>
<div class="featured">
    <h2>Prochain Match</h2>
    <p><?= htmlspecialchars($next_match['team1']) ?> VS <?= htmlspecialchars($next_match['team2']) ?></p>
    <p><?= date("d/m/Y H:i", strtotime($next_match['match_datetime'])) ?> — <?= htmlspecialchars($next_match['location']) ?></p>
</div>
<?php else: ?>
<p class="empty-msg">Aucun match prévu pour cette saison.</p>
<?php endif; ?>



<!-- Carrousel -->
<div class="carousel">
    <?php if (!empty($matches)): ?>
        <?php foreach($matches as $m): ?>
            <div class="carousel-card">
                <h3><?= htmlspecialchars($m['team1']) ?> vs <?= htmlspecialchars($m['team2']) ?></h3>
                <p><?= date("d/m/Y H:i", strtotime($m['match_datetime'])) ?></p>
                <p>
                    <?= $m['is_played'] ? "<b>{$m['score_team1']} - {$m['score_team2']}</b>" : "À venir" ?>
                </p>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p class="empty-msg">Aucun match enregistré pour cette saison.</p>
    <?php endif; ?>
</div>



<!-- Classements -->
<div class="standings-wrapper">
    <h2>Classements par poules</h2>

    <?php if (!empty($poules)): ?>

        <div class="tabs">
            <?php foreach($poules as $i=>$p): ?>
                <button class="tab-btn <?= $i===0?'active':'' ?>" data-target="#pool-<?= $p['pool_id'] ?>">
                    Poule <?= htmlspecialchars($p['name']) ?>
                </button>
            <?php endforeach; ?>
        </div>

        <?php foreach($poules as $i=>$p): ?>
            <?php
                $stmt2 = $pdo->prepare("
                    SELECT s.team_id, s.team_name, s.played, s.won, s.drawn, s.lost,
                           s.goals_for, s.goals_against, s.goal_diff, s.points
                    FROM vw_pool_standings s
                    WHERE s.pool_id = ?
                    ORDER BY s.points DESC, s.goal_diff DESC, s.goals_for DESC
                ");
                $stmt2->execute([$p['pool_id']]);
                $rows = $stmt2->fetchAll();
            ?>

            <div id="pool-<?= $p['pool_id'] ?>" class="pool-table <?= $i===0?'':'hidden' ?>">
                <?php if (!empty($rows)): ?>
                    <table class="standings-table">
                        <thead>
                        <tr>
                            <th>#</th><th>Équipe</th><th>MJ</th><th>V</th><th>N</th><th>D</th>
                            <th>BP</th><th>BC</th><th>Diff</th><th>Pts</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach($rows as $idx=>$r): ?>
                            <tr class="row-<?= $idx+1 <= $p['advance_count'] ? 'qualified':'running' ?>">
                                <td><?= $idx+1 ?></td>
                                <td><?= htmlspecialchars($r['team_name']) ?></td>
                                <td><?= $r['played'] ?></td>
                                <td><?= $r['won'] ?></td>
                                <td><?= $r['drawn'] ?></td>
                                <td><?= $r['lost'] ?></td>
                                <td><?= $r['goals_for'] ?></td>
                                <td><?= $r['goals_against'] ?></td>
                                <td><?= $r['goal_diff'] ?></td>
                                <td><?= $r['points'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="empty-msg">Aucun classement disponible pour cette poule.</p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

    <?php else: ?>
        <p class="empty-msg">Aucune poule créée pour cette saison.</p>
    <?php endif; ?>
</div>



<!-- Match Center Pro -->
<h1>Match Center Pro</h1>

<?php if (!empty($matches)): ?>
<table class="standings-table">
<tr>
    <th>Date</th><th>Poule</th><th>Équipe 1</th><th>Équipe 2</th><th>Score</th><th>Statut</th><th>Détails</th>
</tr>

<?php foreach($matches as $m): ?>
<tr>
    <td><?= date('d/m/Y H:i', strtotime($m['match_datetime'])) ?></td>
    <td><?= htmlspecialchars($m['pool_name'] ?? '-') ?></td>
    <td><?= htmlspecialchars($m['team1']) ?></td>
    <td><?= htmlspecialchars($m['team2']) ?></td>
    <td><?= ($m['score_team1'] ?? '-') . " - " . ($m['score_team2'] ?? '-') ?></td>

    <td>
        <?php
        if ($m['is_played'] == 0) echo '<span class="status-upcoming">À venir</span>';
        elseif ($m['is_played'] == 1 && $m['score_team1'] !== null) echo '<span class="status-finished">Terminé</span>';
        else echo '<span class="status-live">Live</span>';
        ?>
    </td>

    <td><a href="match_details.php?id=<?= $m['match_id'] ?>">Détails</a></td>
</tr>
<?php endforeach; ?>

</table>
<?php else: ?>
<p class="empty-msg">Aucun match n’a été joué pour cette saison.</p>
<?php endif; ?>



<!-- Phases finales -->
<h2>Phases finales</h2>

<?php if (!empty($finals)): ?>
<table class="standings-table">
<tr>
    <th>Stage</th><th>Équipe 1</th><th>Équipe 2</th><th>Score</th><th>Date</th>
</tr>

<?php foreach($finals as $f): ?>
<tr>
    <td><?= ucfirst($f['stage']) ?></td>
    <td><?= htmlspecialchars($f['team1']) ?></td>
    <td><?= htmlspecialchars($f['team2']) ?></td>
    <td><?= ($f['score_team1'] ?? '-') . " - " . ($f['score_team2'] ?? '-') ?></td>
    <td><?= date('d/m/Y H:i', strtotime($f['match_datetime'])) ?></td>
</tr>
<?php endforeach; ?>
</table>

<?php else: ?>
<p class="empty-msg">Aucune phase finale disponible pour cette saison.</p>
<?php endif; ?>



<!-- Fan Zone -->
<h2>Fan Zone</h2>

<form method="post">
    <input type="hidden" name="user_id" value="1">
    <textarea name="message" placeholder="Votre commentaire..." rows="3" required></textarea>
    <button type="submit">Envoyer</button>
</form>

<?php if (!empty($comments)): ?>
    <?php foreach($comments as $c): ?>
        <div class="comment">
            <strong>
                <?= htmlspecialchars($c['nom'] ?? 'Admin').' '.htmlspecialchars($c['prenom'] ?? '') ?>:
            </strong>
            <?= htmlspecialchars($c['message']) ?><br>
            <small>
                <?= !empty($c['date_commentaire_football'])
                    ? date('d/m/Y H:i', strtotime($c['date_commentaire_football']))
                    : "Date inconnue" ?>
            </small>
        </div>
    <?php endforeach; ?>
<?php else: ?>
    <p class="empty-msg">Aucun commentaire pour cette saison.</p>
<?php endif; ?>

<?php include "../includes/footer.php"; ?>


<script>
document.querySelectorAll('.tab-btn').forEach(btn=>{
    btn.addEventListener('click', ()=>{
        document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));
        btn.classList.add('active');
        const target = btn.dataset.target;
        document.querySelectorAll('.pool-table').forEach(div=>div.classList.add('hidden'));
        document.querySelector(target).classList.remove('hidden');
    });
});
</script>
</body>
</html>
