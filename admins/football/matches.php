<?php
session_start();
require_once '../../includes/db.php'; // connexion PDO

// 1. Récupérer toutes les saisons
$allSeasonsStmt = $pdo->query("SELECT * FROM football_seasons ORDER BY is_active DESC, label ASC");
$allSeasons = $allSeasonsStmt->fetchAll(PDO::FETCH_ASSOC);

// 2. Déterminer la saison choisie (GET ou saison active par défaut)
$season_id = isset($_GET['season_id']) ? intval($_GET['season_id']) : 0;
if ($season_id === 0) {
    $activeSeason = $pdo->query("SELECT * FROM football_seasons WHERE is_active = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $season_id = $activeSeason ? $activeSeason['id_season'] : 0;
}

// 3. Récupérer les pools de la saison
$poolsStmt = $pdo->prepare("SELECT * FROM pools WHERE season_id = ? ORDER BY name ASC");
$poolsStmt->execute([$season_id]);
$pools = $poolsStmt->fetchAll(PDO::FETCH_ASSOC);

// 4. Récupérer tous les matchs pour cette saison
$matchesStmt = $pdo->prepare("
    SELECT m.*, 
           t1.name AS team1_name, 
           t2.name AS team2_name,
           p.name AS pool_name
    FROM matches m
    LEFT JOIN football_teams t1 ON t1.team_id = m.team1_id
    LEFT JOIN football_teams t2 ON t2.team_id = m.team2_id
    LEFT JOIN pools p ON p.pool_id = m.pool_id
    WHERE m.season_id = ?
    ORDER BY p.name ASC, m.match_datetime ASC
");
$matchesStmt->execute([$season_id]);
$allMatches = $matchesStmt->fetchAll(PDO::FETCH_ASSOC);

// Organiser les matchs par pool
$matchesByPool = [];
foreach ($allMatches as $m) {
    $poolName = $m['pool_name'] ?? 'Non défini';
    $matchesByPool[$poolName][] = $m;
}

ob_start();
?>

<!-- Bloc de styles CSS personnalisés injectés dans le layout -->
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

        /* Sidebar & Layout */
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
    }

    /* --- Page Base Configuration --- */
    .dashboard-container {
        font-family: var(--font-primary);
        color: var(--gray-100);
        max-width: 1200px;
        margin: 0 auto;
        padding: var(--space-4) 0;
    }

    /* --- Action Header Bar --- */
    .action-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: var(--space-4);
        margin-bottom: var(--space-6);
        padding-bottom: var(--space-4);
        border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    }

    .action-bar h2 {
        font-size: var(--font-size-xl);
        font-weight: 600;
        color: var(--white);
        display: flex;
        align-items: center;
        gap: var(--space-2);
    }

    /* --- Typography & Buttons --- */
    .btn-custom {
        display: inline-flex;
        align-items: center;
        gap: var(--space-2);
        font-family: var(--font-primary);
        font-size: var(--font-size-sm);
        font-weight: 600;
        padding: var(--space-3) var(--space-4);
        border-radius: var(--radius-md);
        text-decoration: none;
        cursor: pointer;
        transition: all var(--transition-fast);
        border: none;
    }

    .btn-add {
        background-color: var(--accent-green);
        color: var(--white);
        box-shadow: 0 4px 12px rgba(16, 172, 132, 0.25);
    }

    .btn-add:hover {
        background-color: #1dd1a1;
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(16, 172, 132, 0.4);
    }

    /* --- Controls Area --- */
    .filter-section {
        margin-bottom: var(--space-5);
    }

    .select-wrapper {
        position: relative;
        max-width: 300px;
    }

    .custom-select {
        width: 100%;
        appearance: none;
        background-color: var(--primary-700);
        color: var(--white);
        border: 1px solid var(--primary-600);
        border-radius: var(--radius-md);
        padding: var(--space-3) var(--space-5) var(--space-3) var(--space-3);
        font-family: var(--font-primary);
        font-size: var(--font-size-sm);
        cursor: pointer;
        outline: none;
        transition: border-color var(--transition-fast), box-shadow var(--transition-fast);
    }

    .custom-select:focus {
        border-color: var(--accent-blue);
        box-shadow: 0 0 0 3px rgba(46, 134, 222, 0.2);
    }

    .select-wrapper::after {
        content: '▼';
        font-size: 0.65rem;
        color: var(--gray-400);
        position: absolute;
        right: var(--space-3);
        top: 50%;
        transform: translateY(-50%);
        pointer-events: none;
    }

    /* --- Pool Sections & Cards --- */
    .pool-section {
        background: rgba(18, 12, 58, 0.4);
        border: 1px solid rgba(255, 255, 255, 0.05);
        border-radius: var(--radius-lg);
        padding: var(--space-5);
        margin-bottom: var(--space-5);
        backdrop-filter: blur(10px);
        box-shadow: var(--shadow-md);
    }

    .pool-header-title {
        font-size: var(--font-size-lg);
        color: var(--white);
        margin-bottom: var(--space-4);
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: var(--space-2);
    }

    .pool-header-title::before {
        content: '';
        display: inline-block;
        width: 4px;
        height: 18px;
        background: var(--accent-blue);
        border-radius: var(--radius-full);
    }

    /* --- Tables Design --- */
    .table-container {
        width: 100%;
        overflow-x: auto;
        border-radius: var(--radius-md);
        border: 1px solid rgba(255, 255, 255, 0.05);
        background: rgba(10, 1, 39, 0.3);
    }

    .custom-table {
        width: 100%;
        border-collapse: collapse;
        font-size: var(--font-size-sm);
        text-align: left;
    }

    .custom-table th {
        background-color: var(--primary-600);
        color: var(--gray-200);
        font-weight: 600;
        padding: var(--space-3) var(--space-4);
        border-bottom: 2px solid rgba(255, 255, 255, 0.05);
        text-transform: uppercase;
        font-size: var(--font-size-xs);
        letter-spacing: 0.5px;
    }

    .custom-table td {
        padding: var(--space-3) var(--space-4);
        border-bottom: 1px solid rgba(255, 255, 255, 0.03);
        color: var(--gray-100);
        vertical-align: middle;
    }

    .custom-table tbody tr:hover {
        background-color: rgba(255, 255, 255, 0.02);
    }

    /* --- Badges & States --- */
    .badge-score {
        background-color: var(--primary-700);
        color: var(--white);
        font-weight: 700;
        padding: var(--space-2) var(--space-3);
        border-radius: var(--radius-sm);
        border: 1px solid var(--primary-600);
        font-variant-numeric: tabular-nums;
        display: inline-block;
    }

    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: var(--space-1);
        padding: var(--space-1) var(--space-2);
        border-radius: var(--radius-full);
        font-size: var(--font-size-xs);
        font-weight: 600;
    }

    .status-played {
        background-color: rgba(16, 172, 132, 0.15);
        color: #1dd1a1;
    }

    .status-pending {
        background-color: rgba(255, 71, 87, 0.15);
        color: #ff6b81;
    }

    /* --- Action Controls --- */
    .action-links {
        display: flex;
        gap: var(--space-2);
    }

    .btn-action {
        padding: var(--space-2) var(--space-3);
        border-radius: var(--radius-sm);
        text-decoration: none;
        font-size: var(--font-size-xs);
        font-weight: 600;
        transition: all var(--transition-fast);
    }

    .btn-edit {
        background-color: rgba(46, 134, 222, 0.15);
        color: #54a0ff;
    }

    .btn-edit:hover {
        background-color: var(--accent-blue);
        color: var(--white);
    }

    .btn-delete {
        background-color: rgba(255, 71, 87, 0.15);
        color: #ff6b81;
    }

    .btn-delete:hover {
        background-color: var(--accent-red);
        color: var(--white);
    }

    .empty-state-card {
        text-align: center;
        padding: var(--space-6) var(--space-4);
        color: var(--gray-400);
        font-style: italic;
    }

    /* --- Strict Responsive Breakdown --- */
    @media (max-width: 992px) {
        .custom-table, .custom-table thead, .custom-table tbody, .custom-table th, .custom-table td, .custom-table tr {
            display: block;
        }

        .custom-table thead {
            display: none;
        }

        .custom-table tr {
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            padding: var(--space-3) var(--space-2);
        }

        .custom-table tr:last-child {
            border-bottom: none;
        }

        .custom-table td {
            border: none;
            padding: var(--space-2) var(--space-3);
            display: flex;
            justify-content: space-between;
            align-items: center;
            text-align: right;
        }

        .custom-table td::before {
            content: attr(data-label);
            float: left;
            font-weight: 600;
            color: var(--gray-400);
            font-size: var(--font-size-xs);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .action-links {
            justify-content: flex-end;
            width: 100%;
        }
    }

    @media (max-width: 576px) {
        .action-bar {
            flex-direction: column;
            align-items: flex-start;
        }
        .btn-add, .select-wrapper {
            width: 100%;
            max-width: 100%;
        }
        .btn-add {
            justify-content: center;
        }
    }
</style>

<div class="dashboard-container">
    
    <!-- Top Action Hub -->
    <div class="action-bar">
        <h2><i class="fa-solid fa-trophy" style="color: #ffd200;"></i> Matches — Saison <?= htmlspecialchars($season_id) ?></h2>
        <a href="add_match.php" class="btn-custom btn-add">
            <i class="fa-solid fa-plus"></i> Ajouter un match
        </a>
    </div>

    <!-- Filtering Pipeline -->
    <div class="filter-section">
        <form method="GET">
            <div class="select-wrapper">
                <select name="season_id" class="custom-select" onchange="this.form.submit()">
                    <?php foreach($allSeasons as $s): ?>
                        <option value="<?= $s['id_season'] ?>" <?= $s['id_season'] == $season_id ? 'selected' : '' ?>>
                            <?= htmlspecialchars($s['label']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>

    <!-- Pools Processing -->
    <?php if($matchesByPool): ?>
        <?php foreach($matchesByPool as $poolName => $matches): ?>
            <section class="pool-section">
                <h4 class="pool-header-title"><?= htmlspecialchars($poolName) ?></h4>
                <div class="table-container">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Équipe Locale</th>
                                <th>Équipe Visiteuse</th>
                                <th>Date & Heure</th>
                                <th>Stade / Lieu</th>
                                <th>Score</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($matches as $i => $m): ?>
                                <tr>
                                    <td data-label="#">#<?= $i + 1 ?></td>
                                    <td data-label="Équipe Locale">
                                        <strong style="color: var(--white);"><?= htmlspecialchars($m['team1_name'] ?? 'N/A') ?></strong>
                                    </td>
                                    <td data-label="Équipe Visiteuse">
                                        <strong style="color: var(--white);"><?= htmlspecialchars($m['team2_name'] ?? 'N/A') ?></strong>
                                    </td>
                                    <td data-label="Date & Heure">
                                        <i class="fa-regular fa-clock" style="margin-right: 4px; color: var(--accent-blue);"></i> 
                                        <?= $m['match_datetime'] ? date('d/m/Y H:i', strtotime($m['match_datetime'])) : 'Non défini' ?>
                                    </td>
                                    <td data-label="Stade / Lieu">
                                        <?= $m['location'] ? '<i class="fa-solid fa-location-dot" style="margin-right: 4px; color: var(--gray-400);"></i> ' . htmlspecialchars($m['location']) : '—' ?>
                                    </td>
                                    <td data-label="Score">
                                        <?php if ($m['score_team1'] !== null && $m['score_team2'] !== null): ?>
                                            <span class="badge-score"><?= htmlspecialchars($m['score_team1'] . ' - ' . $m['score_team2']) ?></span>
                                        <?php else: ?>
                                            <span style="color: var(--gray-400)">Pas de score</span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Statut">
                                        <?php if ($m['is_played']): ?>
                                            <span class="status-badge status-played"><i class="fa-solid fa-circle-check"></i> Joué</span>
                                        <?php else: ?>
                                            <span class="status-badge status-pending"><i class="fa-solid fa-spinner"></i> À venir</span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Actions">
                                        <div class="action-links">
                                            <a href="modifier_match.php?id=<?= $m['match_id'] ?>" class="btn-action btn-edit" title="Modifier le match">
                                                <i class="fa-solid fa-pen-to-square"></i>
                                            </a>
                                            <a href="delete_match.php?id=<?= $m['match_id'] ?>" class="btn-action btn-delete" onclick="return confirm('Supprimer ce match ?')" title="Supprimer le match">
                                                <i class="fa-solid fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="pool-section empty-state-card">
            <i class="fa-solid fa-folder-open" style="font-size: 2rem; margin-bottom: var(--space-3); color: var(--gray-400);"></i>
            <p>Aucun match répertorié pour cette saison actuellement.</p>
        </div>
    <?php endif; ?>

</div>

<?php
$content = ob_get_clean();
include '../layout.php';
?>