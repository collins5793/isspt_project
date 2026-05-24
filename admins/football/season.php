<?php
require_once "../../includes/db.php";

// 1. Récupérer toutes les saisons pour le select
$seasons = $pdo->query("
    SELECT id_season, label, is_active 
    FROM football_seasons 
    ORDER BY id_season DESC
")->fetchAll(PDO::FETCH_ASSOC);

// 2. Déterminer la saison à afficher
if (isset($_GET['season'])) {
    $season_id = intval($_GET['season']);
} else {
    // Saison active par défaut
    $season_id = $pdo->query("
        SELECT id_season FROM football_seasons WHERE is_active = 1 LIMIT 1
    ")->fetchColumn();
}

// 3. Charger la saison choisie
$season = $pdo->prepare("
    SELECT fs.*, ay.label AS year_label
    FROM football_seasons fs
    JOIN academic_years ay ON ay.id = fs.academic_year_id
    WHERE fs.id_season = ?
");
$season->execute([$season_id]);
$season = $season->fetch(PDO::FETCH_ASSOC);


// 4. Statistiques
$stats = [];

// Nombre d’équipes
$stats['teams'] = $pdo->prepare("
    SELECT COUNT(*) FROM football_teams WHERE season_id = ?
");
$stats['teams']->execute([$season_id]);
$stats['teams'] = $stats['teams']->fetchColumn();

// Nombre de joueurs
$stats['players'] = $pdo->prepare("
    SELECT COUNT(*) FROM team_players WHERE season_id = ?
");
$stats['players']->execute([$season_id]);
$stats['players'] = $stats['players']->fetchColumn();

// Nombre de poules
$stats['pools'] = $pdo->prepare("
    SELECT COUNT(*) FROM pools WHERE season_id = ?
");
$stats['pools']->execute([$season_id]);
$stats['pools'] = $stats['pools']->fetchColumn();

// Matchs joués / non joués
$stats['played'] = $pdo->prepare("
    SELECT COUNT(*) FROM matches WHERE season_id = ? AND is_played = 1
");
$stats['played']->execute([$season_id]);
$stats['played'] = $stats['played']->fetchColumn();

$stats['scheduled'] = $pdo->prepare("
    SELECT COUNT(*) FROM matches WHERE season_id = ?
");
$stats['scheduled']->execute([$season_id]);
$stats['scheduled'] = $stats['scheduled']->fetchColumn();

// Réglages de saison
$setting = $pdo->prepare("SELECT match_type FROM season_settings WHERE season_id = ?");
$setting->execute([$season_id]);
$setting = $setting->fetchColumn();

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

/* Page Base Overrides to fit into the Dashboard layout */
.season-dashboard-wrapper {
    font-family: var(--font-primary);
    background-color: var(--primary-900);
    color: var(--gray-100);
    padding: var(--space-4);
    box-sizing: border-box;
}

.season-dashboard-wrapper *, 
.season-dashboard-wrapper *::before, 
.season-dashboard-wrapper *::after {
    box-sizing: inherit;
}

/* Header Section */
.panel-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: var(--space-4);
    margin-bottom: var(--space-5);
}

.panel-title {
    font-size: 1.75rem;
    font-weight: 700;
    color: var(--white);
    margin: 0;
}

/* Core Buttons Style */
.custom-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: var(--space-2);
    padding: var(--space-3) var(--space-4);
    font-size: var(--font-size-sm);
    font-weight: 600;
    border-radius: var(--radius-md);
    border: 1px solid transparent;
    cursor: pointer;
    text-decoration: none;
    transition: all var(--transition-fast);
}

.btn-primary-custom {
    background-color: var(--accent-blue);
    color: var(--white);
}
.btn-primary-custom:hover {
    background-color: #1c72cd;
    transform: translateY(-1px);
}

.btn-success-custom {
    background-color: var(--accent-green);
    color: var(--white);
}
.btn-success-custom:hover {
    background-color: #0e9673;
    transform: translateY(-1px);
}

.btn-warning-custom {
    background-color: rgba(254, 202, 87, 0.1);
    color: #feca57;
    border-color: rgba(254, 202, 87, 0.2);
}
.btn-warning-custom:hover {
    background-color: #feca57;
    color: var(--primary-900);
}

.btn-danger-custom {
    background-color: rgba(255, 71, 87, 0.1);
    color: var(--accent-red);
    border-color: rgba(255, 71, 87, 0.2);
}
.btn-danger-custom:hover {
    background-color: var(--accent-red);
    color: var(--white);
}

/* Base Cards Structure */
.pro-card {
    background-color: var(--primary-800);
    border: 1px solid rgba(255, 255, 255, 0.05);
    border-radius: var(--radius-lg);
    padding: var(--space-5);
    margin-bottom: var(--space-4);
    box-shadow: var(--shadow-md);
}

.pro-card-title {
    font-size: var(--font-size-md);
    font-weight: 600;
    color: var(--white);
    margin-top: 0;
    margin-bottom: var(--space-3);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Form inputs & dropdowns architecture */
.select-container {
    position: relative;
    width: 100%;
}

.custom-select {
    width: 100%;
    padding: var(--space-3) var(--space-5) var(--space-3) var(--space-4);
    font-family: var(--font-primary);
    font-size: var(--font-size-sm);
    color: var(--white);
    background-color: var(--primary-700);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: var(--radius-md);
    appearance: none;
    cursor: pointer;
    transition: border-color var(--transition-fast);
}

.custom-select:focus {
    outline: none;
    border-color: var(--accent-blue);
    box-shadow: 0 0 0 3px rgba(46, 134, 222, 0.15);
}

.select-container::after {
    content: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%23a4b0be' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
    position: absolute;
    right: var(--space-4);
    top: 50%;
    transform: translateY(-50%);
    pointer-events: none;
}

/* Internal Rows & Grids Layouts */
.details-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: var(--space-5);
    margin-bottom: var(--space-5);
}

.details-column {
    display: flex;
    flex-direction: column;
    gap: var(--space-3);
}

.data-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-bottom: var(--space-2);
    border-bottom: 1px solid rgba(255, 255, 255, 0.03);
}

.data-label {
    color: var(--gray-400);
    font-size: var(--font-size-sm);
}

.data-value {
    color: var(--white);
    font-weight: 600;
    font-size: var(--font-size-sm);
}

/* Custom Badges Architecture */
.custom-badge {
    display: inline-flex;
    align-items: center;
    padding: var(--space-1) var(--space-3);
    border-radius: var(--radius-full);
    font-size: var(--font-size-xs);
    font-weight: 700;
    text-transform: uppercase;
}

.badge-active {
    background-color: rgba(16, 172, 132, 0.15);
    color: #1dd1a1;
    border: 1px solid rgba(16, 172, 132, 0.2);
}

.badge-inactive {
    background-color: rgba(164, 176, 190, 0.1);
    color: var(--gray-300);
    border: 1px solid rgba(164, 176, 190, 0.2);
}

.badge-type {
    background-color: rgba(46, 134, 222, 0.15);
    color: #54a0ff;
    border: 1px solid rgba(46, 134, 222, 0.2);
}

/* Section Divider */
.pro-divider {
    height: 1px;
    background: linear-gradient(90deg, rgba(255,255,255,0.05) 0%, rgba(255,255,255,0.01) 100%);
    border: none;
    margin: var(--space-5) 0;
}

/* Fluide Metrics Cards Display */
.metrics-row {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: var(--space-4);
}

.metric-box {
    background-color: var(--primary-700);
    border-radius: var(--radius-md);
    padding: var(--space-4);
    text-align: center;
    border: 1px solid rgba(255, 255, 255, 0.02);
    transition: transform var(--transition-base);
}

.metric-box:hover {
    transform: translateY(-2px);
}

.metric-number {
    font-size: 2rem;
    font-weight: 700;
    color: var(--white);
    line-height: 1;
    margin-bottom: var(--space-1);
    font-family: monospace;
}

.metric-label {
    font-size: var(--font-size-xs);
    color: var(--gray-400);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Action Trigger Row Layout */
.actions-footer {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    flex-wrap: wrap;
    gap: var(--space-3);
    margin-top: var(--space-5);
}

/* Media Queries for Bulletproof Responsiveness */
@media (max-width: 992px) {
    .metrics-row {
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    }
}

@media (max-width: 768px) {
    .season-dashboard-wrapper {
        padding: var(--space-2);
    }
    .panel-header {
        flex-direction: column;
        align-items: flex-start;
        gap: var(--space-3);
    }
    .panel-header .custom-btn {
        width: 100%;
    }
    .details-grid {
        grid-template-columns: 1fr;
        gap: var(--space-3);
    }
    .actions-footer {
        flex-direction: column;
        width: 100%;
    }
    .actions-footer .custom-btn {
        width: 100%;
    }
}
</style>

<div class="season-dashboard-wrapper">

    <!-- Top Navigation Section / Header -->
    <header class="panel-header">
        <h2 class="panel-title">📅 Gestion des Saisons</h2>
        <a href="add_season.php" class="custom-btn btn-success-custom">
            <span>➕</span> Ajouter une nouvelle saison
        </a>
    </header>

    <!-- Context Selector Card -->
    <section class="pro-card">
        <h3 class="pro-card-title">Sélectionner une saison de travail</h3>
        <form method="GET" action="">
            <div class="select-container">
                <select name="season" class="custom-select" onchange="this.form.submit()">
                    <?php foreach($seasons as $s): ?>
                        <option value="<?= $s['id_season'] ?>" <?= ($s['id_season'] == $season_id ? 'selected' : '') ?>>
                            <?= htmlspecialchars($s['label']) ?> <?= $s['is_active'] ? '— [ Active ]' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </section>

    <!-- Detailed Season Analytics & Specs Card -->
    <section class="pro-card" style="border-left: 4px solid var(--accent-blue);">
        <h3 class="pro-card-title" style="font-size: var(--font-size-lg); color: var(--white); margin-bottom: var(--space-4);">
            <?= htmlspecialchars($season['label']) ?> <span style="color: var(--gray-400); font-weight: 400;">— <?= htmlspecialchars($season['year_label']) ?></span>
        </h3>
        
        <div class="details-grid">
            <div class="details-column">
                <div class="data-row">
                    <span class="data-label">Année académique</span>
                    <span class="data-value"><?= htmlspecialchars($season['year_label']) ?></span>
                </div>
                <div class="data-row">
                    <span class="data-label">Date de création système</span>
                    <span class="data-value" style="font-family: monospace; color: var(--gray-300);"><?= $season['created_at'] ?></span>
                </div>
                <div class="data-row">
                    <span class="data-label">Statut opérationnel</span>
                    <span>
                        <?= $season['is_active'] ? '<span class="custom-badge badge-active">Active</span>' : '<span class="custom-badge badge-inactive">Inactive</span>' ?>
                    </span>
                </div>
            </div>

            <div class="details-column">
                <div class="data-row">
                    <span class="data-label">Type de confrontation</span>
                    <span>
                        <span class="custom-badge badge-type"><?= htmlspecialchars(strtoupper($setting ?? 'simple')) ?></span>
                    </span>
                </div>
                <div class="data-row">
                    <span class="data-label">Équipes engagées</span>
                    <span class="data-value"><?= (int)$stats['teams'] ?></span>
                </div>
                <div class="data-row">
                    <span class="data-label">Joueurs homologués</span>
                    <span class="data-value"><?= (int)$stats['players'] ?></span>
                </div>
            </div>
        </div>

        <hr class="pro-divider">

        <!-- Performance & Volume Statistics Rows -->
        <div class="metrics-row">
            <div class="metric-box">
                <div class="metric-number"><?= (int)$stats['pools'] ?></div>
                <div class="metric-label">Poules créées</div>
            </div>
            <div class="metric-box">
                <div class="metric-number" style="color: #feca57;"><?= (int)$stats['scheduled'] ?></div>
                <div class="metric-label">Matchs programmés</div>
            </div>
            <div class="metric-box">
                <div class="metric-number" style="color: var(--accent-green);"><?= (int)$stats['played'] ?></div>
                <div class="metric-label">Matchs clôturés</div>
            </div>
        </div>
    </section>

    <!-- Contextual Management Controls Bar -->
    <footer class="actions-footer">
        <a href="season_view.php?id=<?= $season_id ?>" class="custom-btn btn-primary-custom">Afficher les détails</a>
        <a href="season_edit.php?id=<?= $season_id ?>" class="custom-btn btn-warning-custom">Modifier la configuration</a>
        <a href="season_delete.php?id=<?= $season_id ?>" class="custom-btn btn-danger-custom" onclick="return confirm('Êtes-vous sûr de vouloir supprimer définitivement cette saison ainsi que l\'ensemble de ses données rattachées ?')">Supprimer la saison</a>
    </footer>

</div>

<?php
// Récupération du contenu et injection dans le layout de l'application
$content = ob_get_clean();
include '../layout.php';
?>