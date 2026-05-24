<?php
session_start();
require_once "../../includes/db.php";

// 1. Gestion des actions rapides (Ex: Annuler/Expirer un code manuellement)
if (isset($_GET['expire_id'])) {
    try {
        $stmtExpire = $pdo->prepare("UPDATE codes_paiement SET statut = 'expire' WHERE id = ? AND statut = 'disponible'");
        $stmtExpire->execute([$_GET['expire_id']]);
        $_SESSION['flash_success'] = "Le code a été marqué comme expiré avec succès.";
    } catch (Exception $e) {
        $_SESSION['flash_error'] = "Erreur lors de la modification du statut : " . $e->getMessage();
    }
    header("Location: liste_codes.php");
    exit;
}

// 2. Récupération du filtre de statut
$filter_statut = $_GET['statut'] ?? '';

// 3. Construction de la requête principale avec jointure pour avoir le nom de l'événement
$query = "
    SELECT cp.*, c.nom_concours AS nom_evenement 
    FROM codes_paiement cp
    LEFT JOIN concours c ON cp.evenement_id = c.id_concours
    WHERE 1=1
";
$params = [];

if (!empty($filter_statut)) {
    $query .= " AND cp.statut = ? ";
    $params[] = $filter_statut;
}

$query .= " ORDER BY cp.date_creation DESC ";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$codesList = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 4. Statistiques globales rapides pour les compteurs
$statsStmt = $pdo->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN statut = 'disponible' THEN 1 ELSE 0 END) as disponible,
        SUM(CASE WHEN statut = 'utilise' THEN 1 ELSE 0 END) as utilise,
        SUM(CASE WHEN statut = 'expire' THEN 1 ELSE 0 END) as expire
    FROM codes_paiement
");
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

    .page-wrapper {
        font-family: var(--font-primary);
        color: var(--gray-100);
        animation: fadeIn var(--transition-base);
    }

    /* En-tête */
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
    }

    .page-header-title p {
        color: var(--gray-400);
        font-size: var(--font-size-sm);
        margin-top: var(--space-1);
    }

    /* Boutons */
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

    .btn-primary {
        background: linear-gradient(135deg, var(--accent-blue) 0%, #1e52a4 100%);
        color: var(--white);
        box-shadow: 0 4px 15px rgba(46, 134, 222, 0.25);
    }

    .btn-primary:hover {
        background: linear-gradient(135deg, #48dbfb) 0%, var(--accent-blue) 100%;
        box-shadow: 0 6px 20px rgba(46, 134, 222, 0.4);
    }

    .btn-secondary {
        background-color: var(--primary-600);
        color: var(--gray-200);
        border-color: rgba(255, 255, 255, 0.08);
    }

    .btn-secondary:hover { background-color: var(--primary-700); color: var(--white); }

    /* Compteurs Statistiques */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: var(--space-4);
        margin-bottom: var(--space-5);
    }

    .stat-card {
        background: linear-gradient(145deg, var(--primary-800) 0%, var(--primary-700) 100%);
        border: 1px solid rgba(255, 255, 255, 0.04);
        border-radius: var(--radius-lg);
        padding: var(--space-4);
        display: flex;
        flex-direction: column;
        box-shadow: var(--shadow-md);
        position: relative;
    }

    .stat-card::before {
        content: ''; position: absolute; top: 0; left: 0; width: 4px; height: 100%; border-radius: var(--radius-full) 0 0 var(--radius-full);
    }
    .stat-card.blue::before { background-color: var(--accent-blue); }
    .stat-card.green::before { background-color: var(--accent-green); }
    .stat-card.warning::before { background-color: var(--accent-warning); }
    .stat-card.red::before { background-color: var(--accent-red); }

    .stat-num { font-size: 1.75rem; font-weight: 700; color: var(--white); }
    .stat-num.text-green { color: #1dd1a1; }
    .stat-num.text-warning { color: var(--accent-warning); }
    .stat-num.text-red { color: #ff6b81; }
    .stat-label { font-size: var(--font-size-xs); color: var(--gray-400); text-transform: uppercase; margin-top: var(--space-1); }

    /* Filtres */
    .filter-box {
        background: rgba(18, 12, 58, 0.4);
        border: 1px solid rgba(255, 255, 255, 0.05);
        border-radius: var(--radius-lg);
        padding: var(--space-4);
        margin-bottom: var(--space-5);
    }

    .filter-form { display: flex; align-items: center; gap: var(--space-4); }
    .filter-group { display: flex; flex-direction: column; gap: var(--space-2); min-width: 220px; }
    .filter-group label { font-size: var(--font-size-xs); font-weight: 600; color: var(--gray-300); text-transform: uppercase; }
    .filter-select {
        background-color: var(--primary-600); border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: var(--radius-md); padding: 0.6rem var(--space-3); color: var(--white); font-family: inherit; font-size: var(--font-size-sm); cursor: pointer;
    }

    /* Tableau Principal */
    .table-container-card {
        background: linear-gradient(180deg, var(--primary-800) 0%, var(--primary-900) 100%);
        border: 1px solid rgba(255, 255, 255, 0.05);
        border-radius: var(--radius-xl);
        box-shadow: var(--shadow-xl);
        overflow: hidden;
    }

    .table-responsive { width: 100%; overflow-x: auto; }
    .table-custom { width: 100%; border-collapse: collapse; text-align: left; font-size: var(--font-size-sm); }
    .table-custom th {
        background-color: rgba(255, 255, 255, 0.02); color: var(--gray-300); font-weight: 600; padding: var(--space-4);
        border-bottom: 1px solid rgba(255, 255, 255, 0.06); text-transform: uppercase; font-size: var(--font-size-xs); letter-spacing: 0.5px;
    }
    .table-custom td { padding: var(--space-4); border-bottom: 1px solid rgba(255, 255, 255, 0.04); color: var(--gray-100); vertical-align: middle; }
    .table-custom tbody tr:hover { background-color: rgba(255, 255, 255, 0.02); }

    /* Code Badge Spécifique */
    .code-display {
        font-family: 'Courier New', Courier, monospace;
        font-weight: 700;
        font-size: var(--font-size-md);
        color: var(--white);
        background: rgba(46, 134, 222, 0.15);
        padding: var(--space-1) var(--space-3);
        border-radius: var(--radius-sm);
        border: 1px dashed rgba(46, 134, 222, 0.4);
        letter-spacing: 1px;
    }

    /* Badges de statuts */
    .badge { display: inline-flex; align-items: center; padding: 0.3rem var(--space-3); border-radius: var(--radius-full); font-size: 11px; font-weight: 700; text-transform: uppercase; }
    .badge-success { background: rgba(16, 172, 132, 0.15); color: #1dd1a1; }
    .badge-warning { background: rgba(241, 196, 15, 0.15); color: var(--accent-warning); }
    .badge-danger { background: rgba(255, 71, 87, 0.15); color: #ff6b81; }

    /* Action rapide */
    .btn-action {
        width: 30px; height: 30px; display: inline-flex; align-items: center; justify-content: center;
        border-radius: var(--radius-md); background: rgba(255, 71, 87, 0.1); color: var(--accent-red);
        border: 1px solid rgba(255, 71, 87, 0.2); text-decoration: none; font-size: var(--font-size-xs); transition: all var(--transition-fast);
    }
    .btn-action:hover { background: var(--accent-red); color: var(--white); transform: translateY(-1px); }

    /* Alerts */
    .alert { padding: var(--space-4); border-radius: var(--radius-lg); margin-bottom: var(--space-5); font-size: var(--font-size-sm); border-left: 4px solid transparent; }
    .alert-success { background: rgba(16, 172, 132, 0.08); border-color: var(--accent-green); color: #1dd1a1; }
    .alert-danger { background: rgba(255, 71, 87, 0.08); border-color: var(--accent-red); color: #ff6b81; }

    .empty-state { padding: var(--space-6); text-align: center; color: var(--gray-400); }

    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

    /* ==========================================================================
       RESPONSIVITÉ CRUCIALE
       ========================================================================== */
    @media (max-width: 1024px) {
        .stats-grid { grid-template-columns: repeat(2, 1fr); gap: var(--space-3); }
    }

    @media (max-width: 768px) {
        .page-header-container { flex-direction: column; align-items: flex-start; gap: var(--space-3); }
        .page-header-container .btn { width: 100%; }
        .filter-form { flex-direction: column; align-items: stretch; }
        .filter-group { min-width: 100%; }
        .stats-grid { grid-template-columns: repeat(2, 1fr); gap: var(--space-2); }

        /* Transformation Mobile du Tableau en Cards */
        .table-custom thead { display: none; }
        .table-custom, .table-custom tbody, .table-custom tr, .table-custom td { display: block; width: 100%; }
        .table-custom tbody tr { margin-bottom: var(--space-4); border: 1px solid rgba(255, 255, 255, 0.06); border-radius: var(--radius-lg); background: rgba(255, 255, 255, 0.01); padding: var(--space-3); }
        .table-custom td { display: flex; justify-content: space-between; align-items: center; padding: var(--space-2) 0; border-bottom: 1px dashed rgba(255, 255, 255, 0.04); text-align: right; }
        .table-custom td:last-child { border-bottom: none; padding-top: var(--space-2); }
        .table-custom td::before { content: attr(data-label); font-weight: 600; color: var(--gray-400); font-size: var(--font-size-xs); text-transform: uppercase; text-align: left; }
    }
</style>

<div class="page-wrapper">

    <!-- EN-TÊTE -->
    <div class="page-header-container">
        <div class="page-header-title">
            <h2>📋 Codes de Paiement Générés</h2>
            <p>Suivi complet des accès et des statuts des tickets externes de l'établissement.</p>
        </div>
        <a href="ajouter_code_paiement.php" class="btn btn-primary">➕ Générer un nouveau code</a>
    </div>

    <!-- FLASH MESSAGES -->
    <?php if (isset($_SESSION['flash_success'])): ?>
        <div class="alert alert-success"><?= $_SESSION['flash_success']; unset($_SESSION['flash_success']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['flash_error'])): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div>
    <?php endif; ?>

    <!-- BLOCKS STATS -->
    <div class="stats-grid">
        <div class="stat-card blue">
            <span class="stat-num"><?= $stats['total'] ?? 0 ?></span>
            <span class="stat-desc">Total Codes</span>
        </div>
        <div class="stat-card green">
            <span class="stat-num text-green"><?= $stats['disponible'] ?? 0 ?></span>
            <span class="stat-desc">Disponibles</span>
        </div>
        <div class="stat-card warning">
            <span class="stat-num text-warning"><?= $stats['utilise'] ?? 0 ?></span>
            <span class="stat-desc">Utilisés</span>
        </div>
        <div class="stat-card red">
            <span class="stat-num text-red"><?= $stats['expire'] ?? 0 ?></span>
            <span class="stat-desc">Expirés / Bloqués</span>
        </div>
    </div>

    <!-- ZONE DE FILTRE -->
    <div class="filter-box">
        <form method="GET" action="" class="filter-form">
            <div class="filter-group">
                <label for="statut">Filtrer par Statut</label>
                <select name="statut" id="statut" class="filter-select" onchange="this.form.submit()">
                    <option value="">Tous les codes</option>
                    <option value="disponible" <?= $filter_statut === 'disponible' ? 'selected' : '' ?>>Disponibles</option>
                    <option value="utilise" <?= $filter_statut === 'utilise' ? 'selected' : '' ?>>Utilisés</option>
                    <option value="expire" <?= $filter_statut === 'expire' ? 'selected' : '' ?>>Expirés / Bloqués</option>
                </select>
            </div>
        </form>
    </div>

    <!-- TABLEAU DE RÉSULTATS -->
    <div class="table-container-card">
        <?php if (!empty($codesList)): ?>
            <div class="table-responsive">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>Code unique</th>
                            <th>Bénéficiaire</th>
                            <th>Événement Cible</th>
                            <th>Montant Associé</th>
                            <th>Date de Création</th>
                            <th>Date d'Utilisation</th>
                            <th>Statut</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($codesList as $row): ?>
                            <tr>
                                <td data-label="Code unique">
                                    <span class="code-display"><?= htmlspecialchars($row['code']) ?></span>
                                </td>
                                
                                <td data-label="Bénéficiaire">
                                    <strong style="color: var(--white);"><?= htmlspecialchars($row['nom_complet']) ?></strong>
                                </td>

                                <td data-label="Événement Cible">
                                    <span><?= htmlspecialchars($row['nom_evenement'] ?? 'Non spécifié') ?></span>
                                </td>

                                <td data-label="Montant Associé">
                                    <span style="font-weight: 600; color: #1dd1a1;"><?= number_format($row['montant_associe'], 0, ',', ' ') ?> FCFA</span>
                                </td>

                                <td data-label="Date de Création">
                                    <small><?= date('d/m/Y H:i', strtotime($row['date_creation'])) ?></small>
                                </td>

                                <td data-label="Date d'Utilisation">
                                    <small>
                                        <?= $row['date_utilisation'] ? date('d/m/Y H:i', strtotime($row['date_utilisation'])) : '<span style="opacity:0.4;">—</span>' ?>
                                    </small>
                                </td>

                                <td data-label="Statut">
                                    <?php 
                                        $stClass = 'badge-danger'; $stLabel = 'Expiré';
                                        if ($row['statut'] === 'disponible') { $stClass = 'badge-success'; $stLabel = 'Disponible'; }
                                        elseif ($row['statut'] === 'utilise') { $stClass = 'badge-warning'; $stLabel = 'Utilisé'; }
                                    ?>
                                    <span class="badge <?= $stClass ?>"><?= $stLabel ?></span>
                                </td>

                                <td data-label="Actions" class="text-center">
                                    <?php if ($row['statut'] === 'disponible'): ?>
                                        <a href="liste_codes.php?expire_id=<?= $row['id'] ?>" 
                                           class="btn-action" 
                                           title="Forcer l'expiration / Révoquer"
                                           onclick="return confirm('Voulez-vous vraiment expirer ce code ? Il ne pourra plus être utilisé pour prendre un ticket.');">
                                            ❌
                                        </a>
                                    <?php else: ?>
                                        <span style="opacity: 0.3; font-size: var(--font-size-xs);">Aucune action</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <p>📂 Aucun code de paiement enregistré ou correspondant à vos critères actuels.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
$content = ob_get_clean();
include "../layout.php";
?>