<?php
session_start();
require_once "../../includes/db.php";

// Gestion de la suppression si demandée
$deleteId = $_GET['delete'] ?? null;
if ($deleteId) {
    try {
        $stmtDel = $pdo->prepare("DELETE FROM concours WHERE id_concours = ?");
        $stmtDel->execute([$deleteId]);
        $_SESSION['flash_success'] = "Le concours a été supprimé avec succès.";
    } catch (Exception $e) {
        $_SESSION['flash_error'] = "Erreur lors de la suppression : " . $e->getMessage();
    }
    header("Location: concours.php");
    exit;
}

// 1. Récupération de toutes les années académiques pour remplir le menu déroulant
$yearsStmt = $pdo->query("SELECT id, label, is_current FROM academic_years ORDER BY label DESC");
$academicYears = $yearsStmt->fetchAll(PDO::FETCH_ASSOC);

// 2. Détermination de l'année académique par défaut (is_current = 1)
$defaultYearId = '';
foreach ($academicYears as $year) {
    if (isset($year['is_current']) && $year['is_current'] == 1) {
        $defaultYearId = $year['id'];
        break;
    }
}

// 3. Récupération des filtres depuis l'URL (GET)
$filter_year = $_GET['year'] ?? $defaultYearId;
$filter_statut = $_GET['statut'] ?? '';
$filter_type = $_GET['type'] ?? '';

// Construction dynamique de la requête de listing
$query = "
    SELECT c.*, ay.label AS academic_year, 
           (SELECT COUNT(*) FROM concours_phases WHERE id_concours = c.id_concours) AS total_phases
    FROM concours c
    LEFT JOIN academic_years ay ON c.academic_year_id = ay.id
    WHERE 1=1
";
$params = [];

// Application des filtres
if (!empty($filter_year)) {
    $query .= " AND c.academic_year_id = ? ";
    $params[] = $filter_year;
}

if (!empty($filter_statut)) {
    $query .= " AND c.statut = ? ";
    $params[] = $filter_statut;
}

if (!empty($filter_type)) {
    $query .= " AND c.type_concours = ? ";
    $params[] = $filter_type;
}

$query .= " ORDER BY c.created_at DESC ";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$concoursList = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistiques adaptées aux filtres
$statsQuery = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN statut = 'ouvert' THEN 1 ELSE 0 END) as ouvert,
        SUM(CASE WHEN statut = 'en_cours' THEN 1 ELSE 0 END) as en_cours,
        SUM(CASE WHEN statut = 'termine' THEN 1 ELSE 0 END) as termine
    FROM concours
    WHERE 1=1
";
$statsParams = [];
if (!empty($filter_year)) {
    $statsQuery .= " AND academic_year_id = ? ";
    $statsParams[] = $filter_year;
}
$statsStmt = $pdo->prepare($statsQuery);
$statsStmt->execute($statsParams);
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

ob_start();
?>

<style>
    :root {
        /* Intégration stricte de vos variables globales */
        --primary-900: #080020;
        --primary-800: #0a0127;
        --primary-700: #120c3a;
        --primary-600: #1a1849;
        --accent-red: #ff4757;
        --accent-blue: #2e86de;
        --accent-green: #10ac84;
        --accent-warning: #f1c40f;
        --white: #ffffff;
        --gray-50: #f8f9fa;
        --gray-100: #f1f2f6;
        --gray-200: #dfe4ea;
        --gray-300: #ced6e0;
        --gray-400: #a4b0be;

        --sidebar-width: 280px;
        --font-primary: 'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif;
        
        --font-size-xs: 0.75rem;
        --font-size-sm: 0.875rem;
        --font-size-md: 1rem;
        --font-size-lg: 1.125rem;
        --font-size-xl: 1.25rem;

        --space-1: 0.25rem;
        --space-2: 0.5rem;
        --space-3: 0.75rem;
        --space-4: 1rem;
        --space-5: 1.5rem;
        --space-6: 2rem;

        --transition-fast: 150ms cubic-bezier(0.4, 0, 0.2, 1);
        --transition-base: 300ms cubic-bezier(0.4, 0, 0.2, 1);

        --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.12);
        --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.1);
        --shadow-lg: 0 10px 25px rgba(0, 0, 0, 0.2);
        --shadow-xl: 0 20px 50px rgba(0, 0, 0, 0.3);

        --radius-sm: 4px;
        --radius-md: 8px;
        --radius-lg: 12px;
        --radius-xl: 20px;
        --radius-full: 9999px;
    }

    /* --- Réinitialisation locale et conteneur principal --- */
    .page-wrapper {
        font-family: var(--font-primary);
        color: var(--gray-100);
        background-color: transparent;
        animation: fadeIn var(--transition-base);
    }

    /* --- En-tête de page haut de gamme --- */
    .page-header-container {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: var(--space-4);
        margin-bottom: var(--space-5);
        padding-bottom: var(--space-4);
        border-bottom: 1px solid rgba(255, 255, 255, 0.06);
    }

    .page-header-title h2 {
        font-size: 1.65rem;
        font-weight: 700;
        color: var(--white);
        letter-spacing: -0.5px;
        display: flex;
        align-items: center;
        gap: var(--space-2);
    }

    .page-header-title p {
        color: var(--gray-400);
        font-size: var(--font-size-sm);
        margin-top: var(--space-1);
    }

    /* --- Boutons personnalisés --- */
    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: var(--space-2);
        font-family: inherit;
        font-size: var(--font-size-sm);
        font-weight: 600;
        padding: 0.65rem var(--space-4);
        border-radius: var(--radius-md);
        border: 1px solid transparent;
        cursor: pointer;
        transition: all var(--transition-fast);
        text-decoration: none;
        white-space: nowrap;
    }

    .btn:active {
        transform: scale(0.98);
    }

    .btn-primary {
        background: linear-gradient(135deg, var(--accent-blue) 0%, #1e52a4 100%);
        color: var(--white);
        box-shadow: 0 4px 15px rgba(46, 134, 222, 0.25);
    }

    .btn-primary:hover {
        background: linear-gradient(135deg, #48dbfb 0%, var(--accent-blue) 100%);
        box-shadow: 0 6px 20px rgba(46, 134, 222, 0.4);
    }

    .btn-secondary {
        background-color: var(--primary-600);
        color: var(--gray-200);
        border-color: rgba(255, 255, 255, 0.08);
    }

    .btn-secondary:hover {
        background-color: var(--primary-700);
        color: var(--white);
        border-color: rgba(255, 255, 255, 0.15);
    }

    .btn-sm {
        padding: 0.4rem var(--space-3);
        font-size: var(--font-size-xs);
        border-radius: var(--radius-sm);
    }

    /* Actions contextuelles d'icônes */
    .btn-action-icon {
        width: 32px;
        height: 32px;
        padding: 0;
        border-radius: var(--radius-md);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: all var(--transition-fast);
        border: 1px solid rgba(255, 255, 255, 0.05);
        background: var(--primary-600);
        color: var(--gray-200);
        text-decoration: none;
    }

    .btn-action-icon:hover {
        transform: translateY(-2px);
    }

    .btn-view:hover { background: rgba(46, 134, 222, 0.15); color: #48dbfb; border-color: var(--accent-blue); }
    .btn-edit:hover { background: rgba(241, 196, 15, 0.15); color: var(--accent-warning); border-color: var(--accent-warning); }
    .btn-delete:hover { background: rgba(255, 71, 87, 0.15); color: var(--accent-red); border-color: var(--accent-red); }

    /* --- Messages Flash modernisés --- */
    .alert {
        padding: var(--space-4);
        border-radius: var(--radius-lg);
        margin-bottom: var(--space-5);
        font-size: var(--font-size-sm);
        border-left: 4px solid transparent;
        backdrop-filter: blur(10px);
        animation: slideDown var(--transition-base);
    }

    .alert-success {
        background: rgba(16, 172, 132, 0.08);
        border-color: var(--accent-green);
        color: #1dd1a1;
    }

    .alert-danger {
        background: rgba(255, 71, 87, 0.08);
        border-color: var(--accent-red);
        color: #ff6b81;
    }

    /* --- Compteurs et Cartes de Statistiques --- */
    .stats-overview-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: var(--space-4);
        margin-bottom: var(--space-5);
    }

    .mini-stat-card {
        background: linear-gradient(145deg, var(--primary-800) 0%, var(--primary-700) 100%);
        border: 1px solid rgba(255, 255, 255, 0.04);
        border-radius: var(--radius-lg);
        padding: var(--space-4);
        display: flex;
        flex-direction: column;
        justify-content: center;
        box-shadow: var(--shadow-md);
        position: relative;
        overflow: hidden;
        transition: transform var(--transition-fast), border-color var(--transition-fast);
    }

    .mini-stat-card:hover {
        transform: translateY(-2px);
    }

    .mini-stat-card::before {
        content: '';
        position: absolute;
        top: 0; left: 0; width: 4px; height: 100%;
    }

    .mini-stat-card.border-primary::before { background-color: var(--accent-blue); }
    .mini-stat-card.border-success::before { background-color: var(--accent-green); }
    .mini-stat-card.border-warning::before { background-color: var(--accent-warning); }
    .mini-stat-card.border-dark::before { background-color: var(--gray-400); }

    .stat-value {
        font-size: 1.75rem;
        font-weight: 700;
        line-height: 1.2;
        color: var(--white);
    }
    
    .stat-value.text-success { color: #1dd1a1 !important; }
    .stat-value.text-warning { color: var(--accent-warning) !important; }
    .stat-value.text-dark { color: var(--gray-300) !important; }

    .stat-desc {
        font-size: var(--font-size-xs);
        color: var(--gray-400);
        margin-top: var(--space-1);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    /* --- Barre de Filtres Floue Épurée --- */
    .filter-card {
        background: rgba(18, 12, 58, 0.4);
        border: 1px solid rgba(255, 255, 255, 0.05);
        border-radius: var(--radius-lg);
        padding: var(--space-4);
        margin-bottom: var(--space-5);
        box-shadow: var(--shadow-sm);
    }

    .filter-form {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-end;
        gap: var(--space-4);
    }

    .filter-group {
        display: flex;
        flex-direction: column;
        gap: var(--space-2);
        flex: 1;
        min-width: 200px;
    }

    .filter-group label {
        font-size: var(--font-size-xs);
        font-weight: 600;
        color: var(--gray-300);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .filter-group select {
        width: 100%;
        background-color: var(--primary-600);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: var(--radius-md);
        padding: 0.6rem var(--space-3);
        color: var(--white);
        font-family: inherit;
        font-size: var(--font-size-sm);
        cursor: pointer;
        transition: all var(--transition-fast);
    }

    .filter-group select:focus {
        outline: none;
        border-color: var(--accent-blue);
        box-shadow: 0 0 0 3px rgba(46, 134, 222, 0.15);
    }

    .filter-group.align-end {
        flex: 0 0 auto;
        min-width: auto;
    }

    /* --- Structure Principale & Design du Tableau --- */
    .main-table-card {
        background: linear-gradient(180deg, var(--primary-800) 0%, var(--primary-900) 100%);
        border: 1px solid rgba(255, 255, 255, 0.05);
        border-radius: var(--radius-xl);
        box-shadow: var(--shadow-xl);
        overflow: hidden;
    }

    .table-responsive {
        width: 100%;
        overflow-x: auto;
    }

    .table-custom {
        width: 100%;
        border-collapse: collapse;
        text-align: left;
        font-size: var(--font-size-sm);
    }

    .table-custom th {
        background-color: rgba(255, 255, 255, 0.02);
        color: var(--gray-300);
        font-weight: 600;
        padding: var(--space-4);
        border-bottom: 1px solid rgba(255, 255, 255, 0.06);
        text-transform: uppercase;
        font-size: var(--font-size-xs);
        letter-spacing: 0.5px;
    }

    .table-custom td {
        padding: var(--space-4);
        border-bottom: 1px solid rgba(255, 255, 255, 0.04);
        color: var(--gray-100);
        vertical-align: middle;
    }

    .table-custom tbody tr {
        transition: background-color var(--transition-fast);
    }

    .table-custom tbody tr:hover {
        background-color: rgba(255, 255, 255, 0.02);
    }

    /* Images et Avatars d'affiches */
    .td-avatar {
        width: 70px;
    }

    .concours-mini-img {
        width: 46px;
        height: 46px;
        border-radius: var(--radius-md);
        overflow: hidden;
        background-color: var(--primary-600);
        display: flex;
        align-items: center;
        justify-content: center;
        border: 1px solid rgba(255, 255, 255, 0.1);
        box-shadow: var(--shadow-sm);
    }

    .concours-mini-img img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .img-placeholder {
        font-size: var(--font-size-lg);
    }

    /* Typographies internes aux cellules */
    .fw-bold { font-weight: 600; }
    .text-dark { color: var(--white) !important; }
    .d-block { display: block; }
    .text-center { text-align: center; }

    /* Tags Thématiques */
    .type-tag {
        display: inline-block;
        padding: 0.25rem var(--space-3);
        background-color: var(--primary-600);
        border: 1px solid rgba(255, 255, 255, 0.06);
        border-radius: var(--radius-full);
        font-size: var(--font-size-xs);
        color: var(--gray-200);
        font-weight: 500;
    }

    /* Badges de Statuts et Étapes */
    .badge {
        display: inline-flex;
        align-items: center;
        padding: 0.3rem var(--space-3);
        border-radius: var(--radius-full);
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .badge-info { background: rgba(46, 134, 222, 0.15); color: #48dbfb; }
    .badge-success { background: rgba(16, 172, 132, 0.15); color: #1dd1a1; }
    .badge-warning { background: rgba(241, 196, 15, 0.15); color: var(--accent-warning); }
    .badge-dark { background: rgba(164, 176, 190, 0.15); color: var(--gray-200); }
    .badge-danger { background: rgba(255, 71, 87, 0.15); color: var(--accent-red); }
    .badge-secondary { background: var(--primary-600); color: var(--gray-300); }

    /* Infos temporelles */
    .date-timeline-info {
        font-size: var(--font-size-xs);
        line-height: 1.5;
    }
    .date-timeline-info strong {
        color: var(--gray-300);
    }

    /* Prix */
    .text-success.fw-bold { color: #1dd1a1 !important; }
    .text-danger.fw-bold { color: #ff4757 !important; }

    /* Alignement Cellules d'Actions */
    .actions-cell {
        display: flex;
        justify-content: center;
        gap: var(--space-2);
        border-bottom: none !important; /* Évite des bugs d'alignement flex */
    }

    /* --- Empty State --- */
    .empty-state-container {
        padding: var(--space-6) var(--space-5);
        text-align: center;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: var(--space-2);
    }

    .empty-icon {
        font-size: 3rem;
        margin-bottom: var(--space-2);
        opacity: 0.7;
    }

    .empty-state-container h4 {
        font-size: var(--font-size-lg);
        color: var(--white);
        font-weight: 600;
    }

    .mt-2 { margin-top: var(--space-2); }

    /* --- Animations --- */
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }

    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* ==========================================================================
       RESPONSIVITÉ ABSOLUE SANS DÉFAUT
       ========================================================================== */

    /* Tablettes et Écrans Intermédiaires (max-width: 1024px) */
    @media (max-width: 1024px) {
        .stats-overview-grid {
            grid-template-columns: repeat(2, 1fr);
            gap: var(--space-3);
        }
    }

    /* Smartphones et Petits Écrans (max-width: 768px) */
    @media (max-width: 768px) {
        .page-header-container {
            flex-direction: column;
            align-items: flex-start;
            gap: var(--space-3);
        }

        .page-header-container .btn {
            width: 100%;
        }

        .stats-overview-grid {
            grid-template-columns: repeat(2, 1fr);
            gap: var(--space-2);
        }

        .stat-value {
            font-size: var(--font-size-xl);
        }

        .filter-form {
            flex-direction: column;
            align-items: stretch;
        }

        .filter-group {
            min-width: 100%;
        }

        .filter-group.align-end .btn {
            width: 100%;
            text-align: center;
        }

        /* --- Transformation Responsive du Tableau de données --- */
        /* On masque les en-têtes natifs du tableau */
        .table-custom thead {
            display: none;
        }

        .table-custom, .table-custom tbody, .table-custom tr, .table-custom td {
            display: block;
            width: 100%;
        }

        .table-custom tbody tr {
            margin-bottom: var(--space-4);
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: var(--radius-lg);
            background: rgba(255, 255, 255, 0.01);
            padding: var(--space-3);
        }

        .table-custom td {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: var(--space-2) 0;
            border-bottom: 1px dashed rgba(255, 255, 255, 0.04);
            text-align: right;
        }

        .table-custom td:last-child {
            border-bottom: none;
            padding-top: var(--space-3);
        }

        /* Injection dynamique des libellés de colonnes sur Mobile */
        .table-custom td::before {
            content: attr(data-label);
            font-weight: 600;
            color: var(--gray-400);
            font-size: var(--font-size-xs);
            text-transform: uppercase;
            text-align: left;
            letter-spacing: 0.5px;
        }

        /* Ajustements spécifiques aux contenus sous forme de carte */
        .table-custom .td-avatar {
            justify-content: center;
            padding-bottom: var(--space-3);
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
        }
        .table-custom .td-avatar::before {
            display: none;
        }
        .table-custom .concours-mini-img {
            width: 60px;
            height: 60px;
        }

        .table-custom .actions-cell {
            justify-content: flex-end;
            width: 100%;
        }
        
        .table-custom .actions-cell .btn {
            flex: 1;
        }
    }
</style>

<div class="page-wrapper">

    <!-- EN-TÊTE DE LA PAGE -->
    <div class="page-header-container">
        <div class="page-header-title">
            <h2>🏆 Gestion des Concours</h2>
            <p>Pilotez, planifiez et suivez le déroulement de vos compétitions et challenges.</p>
        </div>
        <a href="ajouter_concours.php" class="btn btn-primary">⚡ Créer un concours</a>
    </div>

    <!-- MESSAGES FLASH -->
    <?php if (isset($_SESSION['flash_success'])): ?>
        <div class="alert alert-success"><?= htmlspecialchars($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['flash_error'])): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div>
    <?php endif; ?>

    <!-- COMPTEURS STATISTIQUES RAPIDES -->
    <div class="stats-overview-grid">
        <div class="mini-stat-card border-primary">
            <span class="stat-value"><?= $stats['total'] ?? 0 ?></span>
            <span class="stat-desc">Concours Filtrés</span>
        </div>
        <div class="mini-stat-card border-success">
            <span class="stat-value text-success"><?= $stats['ouvert'] ?? 0 ?></span>
            <span class="stat-desc">Inscriptions Ouvertes</span>
        </div>
        <div class="mini-stat-card border-warning">
            <span class="stat-value text-warning"><?= $stats['en_cours'] ?? 0 ?></span>
            <span class="stat-desc">En cours</span>
        </div>
        <div class="mini-stat-card border-dark">
            <span class="stat-value text-dark"><?= $stats['termine'] ?? 0 ?></span>
            <span class="stat-desc">Clôturés</span>
        </div>
    </div>

    <!-- BARRE DE FILTRES -->
    <div class="filter-card">
        <form method="GET" action="" class="filter-form">
            <div class="filter-group">
                <label for="year">Année Académique</label>
                <select name="year" id="year" onchange="this.form.submit()">
                    <option value="">Toutes les années</option>
                    <?php foreach ($academicYears as $year): ?>
                        <option value="<?= $year['id'] ?>" <?= $filter_year == $year['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($year['label']) ?> <?= ($year['is_current'] == 1) ? '(En Cours)' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label for="statut">Filtrer par Statut</label>
                <select name="statut" id="statut" onchange="this.form.submit()">
                    <option value="">Tous les statuts</option>
                    <option value="brouillon" <?= $filter_statut === 'brouillon' ? 'selected' : '' ?>>Brouillon</option>
                    <option value="ouvert" <?= $filter_statut === 'ouvert' ? 'selected' : '' ?>>Ouvert (Inscriptions)</option>
                    <option value="en_cours" <?= $filter_statut === 'en_cours' ? 'selected' : '' ?>>En Cours</option>
                    <option value="termine" <?= $filter_statut === 'termine' ? 'selected' : '' ?>>Terminé</option>
                    <option value="annule" <?= $filter_statut === 'annule' ? 'selected' : '' ?>>Annulé</option>
                </select>
            </div>

            <div class="filter-group">
                <label for="type">Filtrer par Thématique</label>
                <select name="type" id="type" onchange="this.form.submit()">
                    <option value="">Toutes les catégories</option>
                    <option value="academique" <?= $filter_type === 'academique' ? 'selected' : '' ?>>Académique</option>
                    <option value="coding" <?= $filter_type === 'coding' ? 'selected' : '' ?>>Coding / Hackathon</option>
                    <option value="innovation" <?= $filter_type === 'innovation' ? 'selected' : '' ?>>Innovation</option>
                    <option value="football" <?= $filter_type === 'football' ? 'selected' : '' ?>>Football</option>
                    <option value="quiz" <?= $filter_type === 'quiz' ? 'selected' : '' ?>>Quiz</option>
                    <option value="debats" <?= $filter_type === 'debats' ? 'selected' : '' ?>>Débats</option>
                    <option value="miss_mister" <?= $filter_type === 'miss_mister' ? 'selected' : '' ?>>Miss & Mister</option>
                    <option value="autre" <?= $filter_type === 'autre' ? 'selected' : '' ?>>Autre</option>
                </select>
            </div>

            <?php if (!empty($filter_statut) || !empty($filter_type) || $filter_year != $defaultYearId): ?>
                <div class="filter-group align-end">
                    <a href="concours.php" class="btn btn-secondary btn-sm">❌ Réinitialiser</a>
                </div>
            <?php endif; ?>
        </form>
    </div>

    <!-- TABLEAU PRINCIPAL TRANSFORME -->
    <div class="main-table-card">
        <?php if (!empty($concoursList)): ?>
            <div class="table-responsive">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>Affiche</th>
                            <th>Nom du Concours</th>
                            <th>Type / Catégorie</th>
                            <th>Étapes</th>
                            <th>Dates Clés</th>
                            <th>Frais d'accès</th>
                            <th>Statut</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($concoursList as $c): ?>
                            <tr>
                                <td class="td-avatar">
                                    <div class="concours-mini-img">
                                        <?php if (!empty($c['image_affiche'])): ?>
                                            <img src="../uploads/<?= htmlspecialchars($c['image_affiche']) ?>" alt="Affiche">
                                        <?php else: ?>
                                            <span class="img-placeholder">🏆</span>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <td data-label="Nom du Concours">
                                    <span class="fw-bold text-dark d-block"><?= htmlspecialchars($c['nom_concours']) ?></span>
                                    <small class="text-muted">Année : <?= htmlspecialchars($c['academic_year'] ?? 'N/A') ?></small>
                                </td>

                                <td data-label="Type / Catégorie">
                                    <span class="type-tag"><?= htmlspecialchars(ucfirst($c['type_concours'])) ?></span>
                                </td>

                                <td data-label="Étapes">
                                    <span class="badge badge-info"><?= $c['total_phases'] ?> phase(s)</span>
                                </td>

                                <td data-label="Dates Clés">
                                    <div class="date-timeline-info">
                                        <span><strong>Début :</strong> <?= date('d/m/Y', strtotime($c['date_debut'])) ?></span>
                                        <?php if ($c['date_fin']): ?>
                                            <br><span class="text-muted"><strong>Fin :</strong> <?= date('d/m/Y', strtotime($c['date_fin'])) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <td data-label="Frais d'accès">
                                    <?php if ($c['participation_gratuite']): ?>
                                        <span class="text-success fw-bold">Gratuit</span>
                                    <?php else: ?>
                                        <span class="text-danger fw-bold"><?= number_format($c['frais_participation'], 0, ',', ' ') ?> FCFA</span>
                                    <?php endif; ?>
                                </td>

                                <td data-label="Statut">
                                    <?php 
                                        $statusClass = 'badge-secondary';
                                        $statusLabel = $c['statut'];
                                        if ($c['statut'] === 'ouvert') { $statusClass = 'badge-success'; $statusLabel = 'Ouvert'; }
                                        elseif ($c['statut'] === 'en_cours') { $statusClass = 'badge-warning'; $statusLabel = 'En Cours'; }
                                        elseif ($c['statut'] === 'termine') { $statusClass = 'badge-dark'; $statusLabel = 'Terminé'; }
                                        elseif ($c['statut'] === 'annule') { $statusClass = 'badge-danger'; $statusLabel = 'Annulé'; }
                                    ?>
                                    <span class="badge <?= $statusClass ?>"><?= $statusLabel ?></span>
                                </td>

                                <td class="actions-cell" data-label="Actions">
                                    <a href="detail_concours.php?id=<?= $c['id_concours'] ?>" class="btn-action-icon btn-view" title="Voir les détails">👁️</a>
                                    <a href="modifier_concours.php?id=<?= $c['id_concours'] ?>" class="btn-action-icon btn-edit" title="Modifier">✏️</a>
                                    <a href="concours.php?delete=<?= $c['id_concours'] ?>" 
                                       class="btn-action-icon btn-delete" 
                                       title="Supprimer"
                                       onclick="return confirm('Êtes-vous sûr de vouloir supprimer définitivement ce concours ainsi que toutes ses phases associées ?');">🗑️</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state-container">
                <span class="empty-icon">📂</span>
                <h4>Aucun concours trouvé</h4>
                <p class="text-muted">Il n'y a aucun concours correspondant à vos critères pour cette période.</p>
                <a href="ajouter_concours.php" class="btn btn-primary btn-sm mt-2">+ Lancer un concours</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
$content = ob_get_clean();
include "../layout.php";
?>