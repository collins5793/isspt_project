<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once "../includes/db.php";

// -----------------------------------------------------------------------------
// 1. AUTHENTIFICATION & RÉCUPÉRATION DU NOM DE L'ADMIN
// -----------------------------------------------------------------------------
if ($_SESSION['admin_role'] === 'bureau' && !empty($_SESSION['admin_id_etudiant'])) {
    $stmt = $pdo->prepare("SELECT nom, prenom FROM etudiants WHERE id_etudiant = ?");
    $stmt->execute([$_SESSION['admin_id_etudiant']]);
    $etudiant = $stmt->fetch(PDO::FETCH_ASSOC);

    $prenom = $etudiant['prenom'] ?? 'Bureau';
    $nom = $etudiant['nom'] ?? 'Membre';
} else {
    $prenom = $_SESSION['admin_prenom'] ?? 'Admin';
    $nom = $_SESSION['admin_nom'] ?? 'JET';
}

// -----------------------------------------------------------------------------
// 2. GESTION DU FILTRE PAR ANNÉE ACADÉMIQUE
// -----------------------------------------------------------------------------
// Récupérer toutes les années académiques existantes
$years_query = $pdo->query("SELECT id, label, is_current, start_date, end_date FROM academic_years ORDER BY start_date DESC");
$academic_years = $years_query->fetchAll(PDO::FETCH_ASSOC);

// Déterminer l'année sélectionnée
$selected_year_id = isset($_GET['academic_year_id']) ? intval($_GET['academic_year_id']) : 0;

// Si aucune année n'est passée en paramètre, on cherche l'année en cours (is_current = 1)
if ($selected_year_id <= 0) {
    foreach ($academic_years as $ay) {
        if ($ay['is_current'] == 1) {
            $selected_year_id = $ay['id'];
            break;
        }
    }
    // Si aucune année n'est marquée "is_current", on prend la plus récente
    if ($selected_year_id <= 0 && !empty($academic_years)) {
        $selected_year_id = $academic_years[0]['id'];
    }
}

// Récupérer les détails de l'année sélectionnée (dates limites pour filtrer les étudiants)
$selected_year_info = null;
foreach ($academic_years as $ay) {
    if ($ay['id'] == $selected_year_id) {
        $selected_year_info = $ay;
        break;
    }
}

// Bornes temporelles pour filtrer les tables sans clé étrangère d'année directe (ex: etudiants)
$start_date = $selected_year_info ? $selected_year_info['start_date'] : '2000-01-01';
$end_date = $selected_year_info ? $selected_year_info['end_date'] : '2100-12-31';

// -----------------------------------------------------------------------------
// 3. REQUÊTES DE STATISTIQUES GLOBALISÉES (FILTRÉES PAR L'ANNÉE SÉLECTIONNÉE)
// -----------------------------------------------------------------------------

// A. ÉTUDIANTS : Inscrits durant cette année académique
$stmt_students = $pdo->prepare("SELECT COUNT(*) FROM etudiants WHERE date_inscription BETWEEN ? AND ?");
$stmt_students->execute([$start_date, $end_date]);
$totalEtudiants = $stmt_students->fetchColumn();

// B. ÉVÉNEMENTS : Créés pour cette année académique
$stmt_events = $pdo->prepare("SELECT COUNT(*) FROM evenements WHERE academic_year_id = ?");
$stmt_events->execute([$selected_year_id]);
$totalEvenements = $stmt_events->fetchColumn();

// C. PARTICIPANTS ÉVÉNEMENTS : Nombre total d'inscriptions aux événements
$stmt_event_part = $pdo->prepare("SELECT COUNT(*) FROM participants_evenements pe 
                                  JOIN evenements e ON pe.event_id = e.id_evenement 
                                  WHERE e.academic_year_id = ?");
$stmt_event_part->execute([$selected_year_id]);
$totalParticipationEvenements = $stmt_event_part->fetchColumn();

// D. CONCOURS : Créés pour cette année académique
$stmt_contests = $pdo->prepare("SELECT COUNT(*) FROM concours WHERE academic_year_id = ?");
$stmt_contests->execute([$selected_year_id]);
$totalConcours = $stmt_contests->fetchColumn();

// E. CANDIDATS AUX CONCOURS : Inscriptions totales aux concours
$stmt_contest_part = $pdo->prepare("SELECT COUNT(*) FROM concours_participants cp 
                                    JOIN concours c ON cp.id_concours = c.id_concours 
                                    WHERE c.academic_year_id = ?");
$stmt_contest_part->execute([$selected_year_id]);
$totalParticipationConcours = $stmt_contest_part->fetchColumn();

// F. ACTIVITÉS : Activités de clubs créées pour cette année académique
$stmt_activities = $pdo->prepare("SELECT COUNT(*) FROM activites WHERE academic_year_id = ?");
$stmt_activities->execute([$selected_year_id]);
$totalActivites = $stmt_activities->fetchColumn();

// G. ÉPREUVES & TELECHARGEMENTS
$stmt_exams = $pdo->prepare("SELECT COUNT(*) FROM epreuves WHERE academic_year_id = ?");
$stmt_exams->execute([$selected_year_id]);
$totalEpreuves = $stmt_exams->fetchColumn();

$stmt_downloads = $pdo->prepare("SELECT COUNT(*) FROM epreuves_downloads ed 
                                 JOIN epreuves e ON ed.id_epreuve = e.id_epreuve 
                                 WHERE e.academic_year_id = ?");
$stmt_downloads->execute([$selected_year_id]);
$totalDownloads = $stmt_downloads->fetchColumn();

// H. COMMENTAIRES (Filtre basé sur les concours et événements de cette année)
$stmt_comments = $pdo->prepare("SELECT COUNT(*) FROM commentaires c 
                                LEFT JOIN evenements e ON c.event_id = e.id_evenement 
                                LEFT JOIN concours co ON c.concours_id = co.id_concours
                                WHERE e.academic_year_id = ? OR co.academic_year_id = ?");
$stmt_comments->execute([$selected_year_id, $selected_year_id]);
$totalCommentaires = $stmt_comments->fetchColumn();

// -----------------------------------------------------------------------------
// 4. RÉCUPÉRATION DES DONNÉES DE GRAPHES (CONVERTIES EN JSON POUR JS)
// -----------------------------------------------------------------------------

// Graphique 1 : Répartition des étudiants inscrits par filière
$filiere_stmt = $pdo->prepare("SELECT filiere, COUNT(*) as effectif 
                               FROM etudiants 
                               WHERE date_inscription BETWEEN ? AND ? AND filiere IS NOT NULL AND filiere != ''
                               GROUP BY filiere");
$filiere_stmt->execute([$start_date, $end_date]);
$filiere_data = $filiere_stmt->fetchAll(PDO::FETCH_ASSOC);

// Graphique 2 : Catégories de concours populaires (nombre d'inscrits par type de concours)
$contest_types_stmt = $pdo->prepare("SELECT c.type_concours, COUNT(cp.id_participant) as inscrits 
                                     FROM concours_participants cp
                                     JOIN concours c ON cp.id_concours = c.id_concours
                                     WHERE c.academic_year_id = ?
                                     GROUP BY c.type_concours");
$contest_types_stmt->execute([$selected_year_id]);
$contest_chart_data = $contest_types_stmt->fetchAll(PDO::FETCH_ASSOC);

// Graphique 3 : Évolution des inscriptions étudiantes par mois
$months_stmt = $pdo->prepare("SELECT DATE_FORMAT(date_inscription, '%M') as mois, COUNT(*) as nb 
                              FROM etudiants 
                              WHERE date_inscription BETWEEN ? AND ? 
                              GROUP BY DATE_FORMAT(date_inscription, '%m-%M') 
                              ORDER BY DATE_FORMAT(date_inscription, '%m') ASC");
$months_stmt->execute([$start_date, $end_date]);
$monthly_registrations = $months_stmt->fetchAll(PDO::FETCH_ASSOC);

// -----------------------------------------------------------------------------
// 5. COMPOSANTS SECONDAIRES : INSCRIPTIONS RÉCENTES À EN VUE
// -----------------------------------------------------------------------------
$recent_registrations_stmt = $pdo->prepare("SELECT cp.*, c.nom_concours 
                                            FROM concours_participants cp 
                                            JOIN concours c ON cp.id_concours = c.id_concours 
                                            WHERE c.academic_year_id = ? 
                                            ORDER BY cp.date_inscription DESC LIMIT 5");
$recent_registrations_stmt->execute([$selected_year_id]);
$recent_registrations = $recent_registrations_stmt->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>
<style>
    :root {
    /* Colors - Dark Theme (default) */
    --primary-900: #080020;
    --primary-800: #0a0127;
    --primary-700: #120c3a;
    --primary-600: #1a1849;
    --accent-red: #ff4757;
    --accent-blue: #2e86de;
    --accent-green: #10ac84;
    --white: #ffffff;
    --gray-50: #f8f9fa;
    --gray-100: #f1f2f6;
    --gray-200: #dfe4ea;
    --gray-300: #ced6e0;
    --gray-400: #a4b0be;
  
    /* Sidebar */
    --sidebar-width: 280px;
    --sidebar-width-collapsed: 70px;
    --sidebar-bg: linear-gradient(180deg, var(--primary-900) 0%, var(--primary-800) 100%);
    --sidebar-border: 1px solid rgba(255, 255, 255, 0.05);
    --sidebar-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
    
    /* Typography */
    --font-primary: 'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif;
    --font-size-xs: 0.75rem;   /* 12px */
    --font-size-sm: 0.875rem;  /* 14px */
    --font-size-md: 1rem;      /* 16px */
    --font-size-lg: 1.125rem;  /* 18px */
    --font-size-xl: 1.25rem;   /* 20px */
    
    /* Spacing */
    --space-1: 0.25rem;   /* 4px */
    --space-2: 0.5rem;    /* 8px */
    --space-3: 0.75rem;   /* 12px */
    --space-4: 1rem;      /* 16px */
    --space-5: 1.5rem;    /* 24px */
    --space-6: 2rem;      /* 32px */
    
    /* Transitions */
    --transition-fast: 150ms cubic-bezier(0.4, 0, 0.2, 1);
    --transition-base: 300ms cubic-bezier(0.4, 0, 0.2, 1);
    --transition-slow: 500ms cubic-bezier(0.4, 0, 0.2, 1);
    
    /* Shadows */
    --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.12);
    --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.1);
    --shadow-lg: 0 10px 25px rgba(0, 0, 0, 0.2);
    --shadow-xl: 0 20px 50px rgba(0, 0, 0, 0.3);
    
    /* Border Radius */
    --radius-sm: 4px;
    --radius-md: 8px;
    --radius-lg: 12px;
    --radius-xl: 20px;
    --radius-full: 9999px;
    
    /* Z-index */
    --z-sidebar: 1000;
    --z-overlay: 999;
    --z-mobile-toggle: 1001;
}

    /* ==========================================================================
   Tableau de Bord JET - Feuille de Style Professionnelle (Dark Theme)
   ========================================================================== */

/* 1. Global Reset & Base Layout */
*, *::before, *::after {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    font-family: var(--font-primary);
    background-color: var(--primary-900);
    color: var(--gray-100);
    overflow-x: hidden;
    -webkit-font-smoothing: antialiased;
}

.dashboard-wrapper {
    width: 100%;
    max-width: 1400px;
    margin: 0 auto;
    padding: var(--space-5);
    animation: fadeIn var(--transition-base);
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* 2. Header Section */
.dashboard-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: var(--space-4);
    margin-bottom: var(--space-6);
    padding-bottom: var(--space-4);
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}

.dashboard-title {
    font-size: var(--font-size-xl);
    font-weight: 700;
    color: var(--white);
    letter-spacing: -0.5px;
    margin-bottom: var(--space-1);
}

.dashboard-subtitle {
    font-size: var(--font-size-sm);
    color: var(--gray-400);
}

.user-highlight {
    color: var(--accent-blue);
    font-weight: 600;
}

/* 3. Filter Box Styling */
.filter-box {
    background: var(--primary-800);
    padding: var(--space-2) var(--space-4);
    border-radius: var(--radius-md);
    border: 1px solid rgba(255, 255, 255, 0.05);
    box-shadow: var(--shadow-sm);
}

#yearFilterForm {
    display: flex;
    align-items: center;
    gap: var(--space-3);
}

.filter-label {
    font-size: var(--font-size-xs);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--gray-400);
    font-weight: 600;
}

.select-wrapper {
    position: relative;
}

.filter-select {
    appearance: none;
    background-color: var(--primary-700);
    color: var(--white);
    font-family: var(--font-primary);
    font-size: var(--font-size-sm);
    font-weight: 500;
    padding: var(--space-2) calc(var(--space-5) + var(--space-2)) var(--space-2) var(--space-3);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: var(--radius-sm);
    cursor: pointer;
    transition: all var(--transition-fast);
}

.filter-select:focus {
    outline: none;
    border-color: var(--accent-blue);
    box-shadow: 0 0 0 3px rgba(46, 134, 222, 0.2);
}

.select-wrapper::after {
    content: '\f078';
    font-family: 'Font Awesome 5 Free';
    font-weight: 900;
    font-size: 10px;
    color: var(--gray-400);
    position: absolute;
    right: var(--space-3);
    top: 50%;
    transform: translateY(-50%);
    pointer-events: none;
}

/* 4. KPI Cards Grid */
.kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: var(--space-4);
    margin-bottom: var(--space-6);
}

.kpi-card {
    background: linear-gradient(145deg, var(--primary-800) 0%, var(--primary-700) 100%);
    border-radius: var(--radius-lg);
    border: 1px solid rgba(255, 255, 255, 0.03);
    padding: var(--space-4) var(--space-5);
    display: flex;
    align-items: center;
    gap: var(--space-4);
    box-shadow: var(--shadow-md);
    transition: transform var(--transition-fast), box-shadow var(--transition-fast);
}

.kpi-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-lg), 0 4px 20px rgba(0, 0, 0, 0.2);
    border-color: rgba(255, 255, 255, 0.08);
}

.kpi-icon-wrapper {
    width: 52px;
    height: 52px;
    border-radius: var(--radius-md);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: var(--font-size-xl);
    flex-shrink: 0;
}

/* Custom KPI Icon Accent Colors using gradients for a slick modern look */
.kpi-icon-blue   { background: rgba(46, 134, 222, 0.15); color: #54a0ff; }
.kpi-icon-red    { background: rgba(255, 71, 87, 0.15);  color: #ff6b81; }
.kpi-icon-gold   { background: rgba(255, 215, 0, 0.12);  color: #ffeaa7; }
.kpi-icon-purple { background: rgba(155, 89, 182, 0.15); color: #a55eea; }
.kpi-icon-cyan   { background: rgba(0, 188, 212, 0.15);  color: #00d2d3; }
.kpi-icon-green  { background: rgba(16, 172, 132, 0.15); color: #1dd1a1; }

.kpi-details {
    display: flex;
    flex-direction: column;
}

.kpi-label {
    font-size: var(--font-size-xs);
    color: var(--gray-400);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 600;
}

.kpi-number {
    font-size: 1.625rem;
    font-weight: 700;
    color: var(--white);
    margin: var(--space-1) 0;
}

.kpi-trend {
    font-size: var(--font-size-xs);
    color: var(--gray-300);
    opacity: 0.7;
}

/* 5. Charts Grid Section */
.charts-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: var(--space-4);
    margin-bottom: var(--space-6);
}

.chart-card {
    background-color: var(--primary-800);
    border-radius: var(--radius-lg);
    border: 1px solid rgba(255, 255, 255, 0.04);
    padding: var(--space-5);
    box-shadow: var(--shadow-md);
}

.chart-card.full-width {
    grid-column: span 2;
}

.chart-header {
    margin-bottom: var(--space-5);
}

.chart-card-title {
    font-size: var(--font-size-md);
    color: var(--white);
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: var(--space-2);
}

.chart-card-title i {
    color: var(--gray-400);
}

.chart-card-subtitle {
    font-size: var(--font-size-xs);
    color: var(--gray-400);
    margin-top: var(--space-1);
}

.chart-canvas-wrapper {
    position: relative;
    height: 260px; /* Taille fixée pour l'harmonie */
    width: 100%;
}

.chart-canvas-wrapper.wide {
    height: 300px;
}

/* 6. Data Tables Section */
.data-tables-section {
    margin-bottom: var(--space-4);
}

.table-card {
    background-color: var(--primary-800);
    border-radius: var(--radius-lg);
    border: 1px solid rgba(255, 255, 255, 0.04);
    box-shadow: var(--shadow-lg);
    overflow: hidden;
}

.table-card-header {
    padding: var(--space-5);
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}

.table-card-title {
    font-size: var(--font-size-md);
    color: var(--white);
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: var(--space-2);
}

.table-card-subtitle {
    font-size: var(--font-size-xs);
    color: var(--gray-400);
    margin-top: var(--space-1);
}

.table-responsive {
    overflow-x: auto;
    width: 100%;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
    text-align: left;
    font-size: var(--font-size-sm);
}

.data-table th {
    background-color: var(--primary-700);
    color: var(--gray-300);
    font-weight: 600;
    padding: var(--space-4) var(--space-5);
    text-transform: uppercase;
    font-size: var(--font-size-xs);
    letter-spacing: 0.5px;
}

.data-table td {
    padding: var(--space-4) var(--space-5);
    border-bottom: 1px solid rgba(255, 255, 255, 0.03);
    color: var(--gray-200);
    vertical-align: middle;
}

.data-table tbody tr {
    transition: background-color var(--transition-fast);
}

.data-table tbody tr:hover {
    background-color: rgba(255, 255, 255, 0.02);
}

.data-table tbody tr:last-child td {
    border-bottom: none;
}

/* Table Special Cells Components */
.table-user-cell {
    display: flex;
    align-items: center;
    gap: var(--space-3);
}

.user-info {
    display: flex;
    flex-direction: column;
}

.user-fullname {
    font-weight: 600;
    color: var(--white);
}

.user-email {
    font-size: var(--font-size-xs);
    color: var(--gray-400);
}

.item-bold {
    color: var(--white);
    font-weight: 500;
}

.numeric-code {
    font-family: monospace;
    background-color: var(--primary-600);
    padding: var(--space-1) var(--space-2);
    border-radius: var(--radius-sm);
    color: var(--gray-100);
    font-size: var(--font-size-xs);
}

/* Status Badges Components */
.status-badge {
    display: inline-block;
    padding: var(--space-1) var(--space-3);
    border-radius: var(--radius-full);
    font-size: var(--font-size-xs);
    font-weight: 600;
    text-align: center;
}

.status-badge-approved {
    background-color: rgba(16, 172, 132, 0.15);
    color: #1dd1a1;
}

.status-badge-pending {
    background-color: rgba(46, 134, 222, 0.15);
    color: #54a0ff;
}

.status-badge-rejected {
    background-color: rgba(255, 71, 87, 0.15);
    color: #ff6b81;
}

.table-empty-state {
    text-align: center !important;
    padding: var(--space-6) !important;
    color: var(--gray-400) !important;
    font-style: italic;
}

/* 7. Responsive Breakpoints */
@media (max-width: 1024px) {
    .charts-grid {
        grid-template-columns: 1fr;
    }
    .chart-card.full-width {
        grid-column: auto;
    }
}

@media (max-width: 768px) {
    .dashboard-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .filter-box {
        width: 100%;
    }
    
    #yearFilterForm {
        justify-content: space-between;
        width: 100%;
    }
    
    .filter-select {
        width: 100%;
    }
    
    .kpi-grid {
        grid-template-columns: 1fr;
    }
}
</style>
<!-- Conteneur Général du Tableau de Bord -->
<div class="dashboard-wrapper">
    
    <!-- En-tête : Message d'accueil & Filtre -->
    <header class="dashboard-header">
        <div class="header-welcome">
            <h2 class="dashboard-title">Tableau de bord principal</h2>
            <p class="dashboard-subtitle">Bienvenue, <strong class="user-highlight"><?= htmlspecialchars($prenom . " " . $nom) ?></strong>. Voici l'état d'avancement des activités de la JET.</p>
        </div>
        
        <!-- Formulaire de filtrage d'année académique -->
        <div class="filter-box">
            <form id="yearFilterForm" method="GET" action="">
                <label for="academic_year_id" class="filter-label">Année Académique :</label>
                <div class="select-wrapper">
                    <select name="academic_year_id" id="academic_year_id" class="filter-select">
                        <?php foreach ($academic_years as $ay): ?>
                            <option value="<?= $ay['id'] ?>" <?= $ay['id'] == $selected_year_id ? 'selected' : '' ?>>
                                <?= htmlspecialchars($ay['label']) ?> <?= $ay['is_current'] == 1 ? '(En cours)' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>
    </header>

    <!-- Section 1 : Cartes de statistiques clés (KPIs) -->
    <section class="kpi-grid">
        
        <!-- Étudiants -->
        <div class="kpi-card">
            <div class="kpi-icon-wrapper kpi-icon-blue">
                <i class="fas fa-user-graduate"></i>
            </div>
            <div class="kpi-details">
                <span class="kpi-label">Nouveaux Candidats</span>
                <h3 class="kpi-number"><?= number_format($totalEtudiants, 0, '.', ' ') ?></h3>
                <span class="kpi-trend">Inscrits cette année</span>
            </div>
        </div>

        <!-- Événements -->
        <div class="kpi-card">
            <div class="kpi-icon-wrapper kpi-icon-red">
                <i class="fas fa-calendar-check"></i>
            </div>
            <div class="kpi-details">
                <span class="kpi-label">Événements</span>
                <h3 class="kpi-number"><?= $totalEvenements ?></h3>
                <span class="kpi-trend"><?= $totalParticipationEvenements ?> réservation(s)</span>
            </div>
        </div>

        <!-- Concours -->
        <div class="kpi-card">
            <div class="kpi-icon-wrapper kpi-icon-gold">
                <i class="fas fa-trophy"></i>
            </div>
            <div class="kpi-details">
                <span class="kpi-label">Défis & Concours</span>
                <h3 class="kpi-number"><?= $totalConcours ?></h3>
                <span class="kpi-trend"><?= $totalParticipationConcours ?> candidature(s)</span>
            </div>
        </div>

        <!-- Activités Clubs -->
        <div class="kpi-card">
            <div class="kpi-icon-wrapper kpi-icon-purple">
                <i class="fas fa-cubes"></i>
            </div>
            <div class="kpi-details">
                <span class="kpi-label">Activités de Clubs</span>
                <h3 class="kpi-number"><?= $totalActivites ?></h3>
                <span class="kpi-trend">Clubs actifs</span>
            </div>
        </div>

        <!-- Épreuves d'examen -->
        <div class="kpi-card">
            <div class="kpi-icon-wrapper kpi-icon-cyan">
                <i class="fas fa-file-pdf"></i>
            </div>
            <div class="kpi-details">
                <span class="kpi-label">Annales & Épreuves</span>
                <h3 class="kpi-number"><?= $totalEpreuves ?></h3>
                <span class="kpi-trend"><?= $totalDownloads ?> téléchargement(s)</span>
            </div>
        </div>

        <!-- Commentaires & Feedback -->
        <div class="kpi-card">
            <div class="kpi-icon-wrapper kpi-icon-green">
                <i class="fas fa-comments"></i>
            </div>
            <div class="kpi-details">
                <span class="kpi-label">Commentaires</span>
                <h3 class="kpi-number"><?= $totalCommentaires ?></h3>
                <span class="kpi-trend">Retours d'étudiants</span>
            </div>
        </div>

    </section>

    <!-- Section 2 : Graphiques Interactifs (Charts Section) -->
    <section class="charts-grid">
        
        <!-- Graphique 1 : Répartition Filières -->
        <div class="chart-card">
            <div class="chart-header">
                <h4 class="chart-card-title"><i class="fas fa-chart-pie"></i> Étudiants par Filière</h4>
                <p class="chart-card-subtitle">Répartition relative globale pour l'année</p>
            </div>
            <div class="chart-canvas-wrapper">
                <canvas id="filiereChart"></canvas>
            </div>
        </div>

        <!-- Graphique 2 : Engouement Concours -->
        <div class="chart-card">
            <div class="chart-header">
                <h4 class="chart-card-title"><i class="fas fa-chart-bar"></i> Inscriptions par type de Défi</h4>
                <p class="chart-card-subtitle">Analyse d'engagement des filières</p>
            </div>
            <div class="chart-canvas-wrapper">
                <canvas id="contestTypesChart"></canvas>
            </div>
        </div>

        <!-- Graphique 3 : Flux Mensuel -->
        <div class="chart-card full-width">
            <div class="chart-header">
                <h4 class="chart-card-title"><i class="fas fa-chart-line"></i> Évolution des Inscriptions Étudiantes</h4>
                <p class="chart-card-subtitle">Flux d'enregistrement mois par mois</p>
            </div>
            <div class="chart-canvas-wrapper wide">
                <canvas id="monthlyRegistrationsChart"></canvas>
            </div>
        </div>

    </section>

    <!-- Section 3 : Tableaux de Détails Directs -->
    <section class="data-tables-section">
        
        <div class="table-card">
            <div class="table-card-header">
                <h4 class="table-card-title"><i class="fas fa-user-plus"></i> Dernières candidatures aux concours</h4>
                <p class="table-card-subtitle">Dossiers récemment soumis par les étudiants</p>
            </div>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Candidat</th>
                            <th>Concours</th>
                            <th>N° Candidat</th>
                            <th>Date inscription</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recent_registrations)): ?>
                            <tr>
                                <td colspan="5" class="table-empty-state">Aucune inscription récente sur cette année.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recent_registrations as $reg): 
                                $badge_class = 'status-badge-pending';
                                if ($reg['statut'] === 'valide' || $reg['statut'] === 'qualifie') {
                                    $badge_class = 'status-badge-approved';
                                } elseif ($reg['statut'] === 'refuse') {
                                    $badge_class = 'status-badge-rejected';
                                }
                            ?>
                                <tr>
                                    <td>
                                        <div class="table-user-cell">
                                            <div class="user-info">
                                                <span class="user-fullname"><?= htmlspecialchars($reg['nom_complet'] ?? 'Étudiant anonyme') ?></span>
                                                <span class="user-email"><?= htmlspecialchars($reg['email'] ?? 'Non fourni') ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td><strong class="item-bold"><?= htmlspecialchars($reg['nom_concours']) ?></strong></td>
                                    <td><span class="numeric-code"><?= htmlspecialchars($reg['numero_participant'] ?? 'N/A') ?></span></td>
                                    <td><?= date("d/m/Y à H:i", strtotime($reg['date_inscription'])) ?></td>
                                    <td><span class="status-badge <?= $badge_class ?>"><?= ucfirst(htmlspecialchars($reg['statut'])) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </section>

</div>

<!-- Chargement propre de Chart.js via CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    // -------------------------------------------------------------------------
    // 1. GESTION DU RECHARGEMENT AU CHANGEMENT D'ANNÉE ACADÉMIQUE
    // -------------------------------------------------------------------------
    document.getElementById('academic_year_id').addEventListener('change', function() {
        document.getElementById('yearFilterForm').submit();
    });

    // -------------------------------------------------------------------------
    // 2. PARSE ET PRÉPARATION DES DONNÉES PHP POUR CHART.JS
    // -------------------------------------------------------------------------
    
    // Graphe 1 : Filières
    const rawFiliereData = <?= json_encode($filiere_data) ?>;
    const filiereLabels = rawFiliereData.map(item => item.filiere);
    const filiereValues = rawFiliereData.map(item => item.effectif);

    // Graphe 2 : Catégories de Concours
    const rawContestData = <?= json_encode($contest_chart_data) ?>;
    const contestLabels = rawContestData.map(item => {
        // Formater proprement les types d'énumération
        return item.type_concours.charAt(0).toUpperCase() + item.type_concours.slice(1);
    });
    const contestValues = rawContestData.map(item => item.inscrits);

    // Graphe 3 : Inscriptions Mensuelles
    const rawMonthlyData = <?= json_encode($monthly_registrations) ?>;
    const monthlyLabels = rawMonthlyData.map(item => item.mois);
    const monthlyValues = rawMonthlyData.map(item => item.nb);

    // Palettes de couleurs harmonieuses pour les graphes
    const colorPalette = [
        '#BA281E', // Crimson
        '#080020', // Midnight Blue
        '#FFD700', // Gold
        '#00bcd4', // Cyan
        '#2ecc71', // Green
        '#9b59b6', // Amethyst
        '#e67e22'  // Orange
    ];

    // -------------------------------------------------------------------------
    // 3. INITIALISATION DES GRAPHIQUES CHART.JS
    // -------------------------------------------------------------------------
    
    // Graphique : Filières (Doughnut)
    const ctxFiliere = document.getElementById('filiereChart').getContext('2d');
    new Chart(ctxFiliere, {
        type: 'doughnut',
        data: {
            labels: filiereLabels.length > 0 ? filiereLabels : ['Aucun étudiant'],
            datasets: [{
                data: filiereValues.length > 0 ? filiereValues : [0],
                backgroundColor: colorPalette,
                borderWidth: 2,
                borderColor: 'rgba(255, 255, 255, 0.05)'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        color: 'rgba(255,255,255,0.7)',
                        font: { family: 'Poppins', size: 11 }
                    }
                }
            }
        }
    });

    // Graphique : Concours (Barres)
    const ctxContests = document.getElementById('contestTypesChart').getContext('2d');
    new Chart(ctxContests, {
        type: 'bar',
        data: {
            labels: contestLabels.length > 0 ? contestLabels : ['Aucun défi'],
            datasets: [{
                label: 'Candidats inscrits',
                data: contestValues.length > 0 ? contestValues : [0],
                backgroundColor: 'rgba(186, 40, 30, 0.85)',
                hoverBackgroundColor: 'rgba(186, 40, 30, 1)',
                borderWidth: 0,
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    grid: { color: 'rgba(255, 255, 255, 0.05)' },
                    ticks: { color: 'rgba(255,255,255,0.6)', stepSize: 1 }
                },
                x: {
                    grid: { display: false },
                    ticks: { color: 'rgba(255,255,255,0.6)' }
                }
            }
        }
    });

    // Graphique : Évolution Mensuelle (Line Chart)
    const ctxMonthly = document.getElementById('monthlyRegistrationsChart').getContext('2d');
    new Chart(ctxMonthly, {
        type: 'line',
        data: {
            labels: monthlyLabels.length > 0 ? monthlyLabels : ['Aucun enregistrement'],
            datasets: [{
                label: 'Inscriptions d\'étudiants',
                data: monthlyValues.length > 0 ? monthlyValues : [0],
                borderColor: '#FFD700',
                backgroundColor: 'rgba(255, 215, 0, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.3,
                pointBackgroundColor: '#FFD700',
                pointHoverRadius: 7
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    grid: { color: 'rgba(255, 255, 255, 0.05)' },
                    ticks: { color: 'rgba(255,255,255,0.6)' }
                },
                x: {
                    grid: { color: 'rgba(255, 255, 255, 0.05)' },
                    ticks: { color: 'rgba(255,255,255,0.6)' }
                }
            }
        }
    });
</script>

<?php
$content = ob_get_clean();
include "layout.php";
?>