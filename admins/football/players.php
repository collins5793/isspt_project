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

// 3. Récupérer les équipes pour la saison
$teamsStmt = $pdo->prepare("SELECT * FROM football_teams WHERE season_id = ? ORDER BY name ASC");
$teamsStmt->execute([$season_id]);
$teams = $teamsStmt->fetchAll(PDO::FETCH_ASSOC);

// 4. Filtrage par équipe et recherche
$team_filter = isset($_GET['team_id']) ? intval($_GET['team_id']) : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// 5. Construire la requête dynamique
$sql = "
    SELECT tp.player_id, tp.team_id, tp.season_id, tp.position, tp.shirt_number, tp.is_captain,
           tp.full_name, 
           t.name AS team_name,
           CONCAT(e.nom, ' ', e.prenom) AS student_name,
           COUNT(mg.goal_id) AS goals
    FROM team_players tp
    JOIN football_teams t ON t.team_id = tp.team_id
    LEFT JOIN etudiants e ON e.id_etudiant = tp.user_id
    LEFT JOIN match_goals mg ON mg.player_id = tp.player_id AND mg.season_id = tp.season_id
    WHERE tp.season_id = ?
";

$params = [$season_id];

if ($team_filter) {
    $sql .= " AND tp.team_id = ?";
    $params[] = $team_filter;
}

if ($search) {
    $sql .= " AND (tp.full_name LIKE ? OR CONCAT(e.nom,' ',e.prenom) LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " GROUP BY tp.player_id ORDER BY t.name ASC, goals DESC, tp.full_name ASC";

$playersStmt = $pdo->prepare($sql);
$playersStmt->execute($params);
$players = $playersStmt->fetchAll(PDO::FETCH_ASSOC);

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

/* Page Container Wrapper */
.dashboard-container {
    font-family: var(--font-primary);
    background-color: var(--primary-900);
    color: var(--white);
    padding: var(--space-5);
    min-height: 100vh;
    box-sizing: border-box;
}

/* Header Section */
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: var(--space-6);
    flex-wrap: wrap;
    gap: var(--space-4);
}

.page-title {
    font-size: 1.75rem;
    font-weight: 700;
    color: var(--white);
    margin: 0;
    display: flex;
    align-items: center;
    gap: var(--space-2);
}

/* Buttons */
.btn-custom {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: var(--space-2);
    padding: var(--space-3) var(--space-5);
    font-size: var(--font-size-sm);
    font-weight: 600;
    border-radius: var(--radius-md);
    border: none;
    cursor: pointer;
    text-decoration: none;
    transition: all var(--transition-fast);
}

.btn-add {
    background-color: var(--accent-green);
    color: var(--white);
    box-shadow: 0 4px 12px rgba(16, 172, 132, 0.2);
}

.btn-add:hover {
    background-color: #0d9471;
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(16, 172, 132, 0.3);
}

.btn-filter {
    background-color: var(--accent-blue);
    color: var(--white);
}

.btn-filter:hover {
    background-color: #2475c4;
}

.btn-action {
    padding: var(--space-2) var(--space-3);
    font-size: var(--font-size-xs);
    border-radius: var(--radius-sm);
}

.btn-edit {
    background-color: rgba(46, 134, 222, 0.15);
    color: #54a0ff;
    border: 1px solid rgba(46, 134, 222, 0.3);
}

.btn-edit:hover {
    background-color: var(--accent-blue);
    color: var(--white);
}

.btn-delete {
    background-color: rgba(255, 71, 87, 0.15);
    color: #ff6b81;
    border: 1px solid rgba(255, 71, 87, 0.3);
}

.btn-delete:hover {
    background-color: var(--accent-red);
    color: var(--white);
}

/* Filters Panel Bar */
.filters-panel {
    background: var(--primary-800);
    border: 1px solid rgba(255, 255, 255, 0.05);
    padding: var(--space-4);
    border-radius: var(--radius-lg);
    margin-bottom: var(--space-5);
    box-shadow: var(--shadow-md);
}

.filters-form {
    display: grid;
    grid-template-columns: repeat(12, 1fr);
    gap: var(--space-4);
    align-items: center;
}

.filter-group-3 { grid-column: span 3; }
.filter-group-4 { grid-column: span 4; }
.filter-group-2 { grid-column: span 2; }

/* Form Controls */
.form-input-custom {
    width: 100%;
    padding: var(--space-3) var(--space-4);
    background-color: var(--primary-700);
    border: 1px solid rgba(255, 255, 255, 0.1);
    color: var(--white);
    font-size: var(--font-size-sm);
    border-radius: var(--radius-md);
    outline: none;
    transition: all var(--transition-fast);
    box-sizing: border-box;
}

.form-input-custom:focus {
    border-color: var(--accent-blue);
    box-shadow: 0 0 0 3px rgba(46, 134, 222, 0.25);
}

select.form-input-custom {
    cursor: pointer;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%23ffffff' viewBox='0 0 16 16'%3E%3Cpath fill-rule='evenodd' d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right var(--space-4) center;
    padding-right: var(--space-5);
}

/* Modern Data Table Layer */
.table-responsive-wrapper {
    width: 100%;
    overflow-x: auto;
    background: var(--primary-800);
    border: 1px solid rgba(255, 255, 255, 0.05);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-lg);
}

.table-custom {
    width: 100%;
    border-collapse: collapse;
    text-align: left;
    font-size: var(--font-size-sm);
}

.table-custom th {
    background-color: var(--primary-600);
    color: var(--gray-300);
    font-weight: 600;
    padding: var(--space-4);
    text-transform: uppercase;
    font-size: var(--font-size-xs);
    letter-spacing: 0.5px;
    border-bottom: 2px solid rgba(255, 255, 255, 0.05);
}

.table-custom td {
    padding: var(--space-4);
    border-bottom: 1px solid rgba(255, 255, 255, 0.03);
    color: var(--gray-100);
    vertical-align: middle;
}

.table-custom tbody tr {
    transition: background-color var(--transition-fast);
}

.table-custom tbody tr:hover {
    background-color: rgba(255, 255, 255, 0.02);
}

/* Table Badges & Elements */
.badge {
    display: inline-flex;
    align-items: center;
    padding: var(--space-1) var(--space-3);
    font-size: var(--font-size-xs);
    font-weight: 600;
    border-radius: var(--radius-full);
}

.badge-captain {
    background-color: rgba(16, 172, 132, 0.15);
    color: #1dd1a1;
    border: 1px solid rgba(16, 172, 132, 0.3);
}

.badge-normal {
    background-color: rgba(164, 176, 190, 0.1);
    color: var(--gray-400);
}

.goals-count {
    font-weight: 700;
    color: var(--white);
    background: var(--primary-700);
    padding: var(--space-1) var(--space-3);
    border-radius: var(--radius-md);
    display: inline-block;
}

.actions-cell {
    display: flex;
    gap: var(--space-2);
    flex-wrap: wrap;
}

.empty-row-text {
    text-align: center;
    color: var(--gray-400);
    padding: var(--space-6) !important;
    font-style: italic;
}

/* Responsiveness Rules Grid Breakpoints */
@media (max-width: 992px) {
    .filters-form {
        grid-template-columns: repeat(2, 1fr);
    }
    .filter-group-3, .filter-group-4, .filter-group-2 {
        grid-column: span 1;
    }
    .filter-group-btn {
        grid-column: span 2;
    }
}

@media (max-width: 576px) {
    .dashboard-container {
        padding: var(--space-3);
    }
    .page-header {
        flex-direction: column;
        align-items: flex-start;
    }
    .btn-add {
        width: 100%;
    }
    .filters-form {
        grid-template-columns: 1fr;
    }
    .filter-group-3, .filter-group-4, .filter-group-2, .filter-group-btn {
        grid-column: span 1;
    }
}
</style>

<div class="dashboard-container">
    
    <!-- Top Bar Title & CTA -->
    <div class="page-header">
        <h2 class="page-title">
            <span>📋</span> Joueurs - Saison <?= htmlspecialchars($season_id) ?>
        </h2>
        <a href="add_player.php" class="btn-custom btn-add">
            <span>➕</span> Ajouter un joueur
        </a>
    </div>

    <!-- Filters Modern Panel -->
    <div class="filters-panel">
        <form method="GET" class="filters-form">
            
            <div class="filter-group-3">
                <select name="season_id" class="form-input-custom" onchange="this.form.submit()">
                    <?php foreach($allSeasons as $s): ?>
                        <option value="<?= $s['id_season'] ?>" <?= $s['id_season'] == $season_id ? 'selected' : '' ?>>
                            Saison : <?= htmlspecialchars($s['label']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group-3">
                <select name="team_id" class="form-input-custom" onchange="this.form.submit()">
                    <option value="0">-- Toutes les équipes --</option>
                    <?php foreach($teams as $t): ?>
                        <option value="<?= $t['team_id'] ?>" <?= $t['team_id'] == $team_filter ? 'selected' : '' ?>>
                            <?= htmlspecialchars($t['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group-4">
                <input type="text" name="search" class="form-input-custom" placeholder="Rechercher un joueur par nom..." value="<?= htmlspecialchars($search) ?>">
            </div>
            
            <div class="filter-group-2 filter-group-btn">
                <button type="submit" class="btn-custom btn-filter w-100">Filtrer</button>
            </div>
            
        </form>
    </div>

    <!-- Clean Scannable Data Table Layer -->
    <div class="table-responsive-wrapper">
        <table class="table-custom">
            <thead>
                <tr>
                    <th style="width: 60px;">#</th>
                    <th>Nom complet</th>
                    <th>Équipe</th>
                    <th>Position</th>
                    <th style="text-align: center;">Numéro</th>
                    <th>Statut</th>
                    <th>Buts</th>
                    <th style="width: 200px; text-align: center;">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if($players): ?>
                <?php foreach($players as $i => $p): ?>
                    <tr>
                        <td><strong><?= $i + 1 ?></strong></td>
                        <td><?= htmlspecialchars($p['full_name'] ?? $p['student_name'] ?? 'N/A') ?></td>
                        <td><?= htmlspecialchars($p['team_name'] ?? 'N/A') ?></td>
                        <td><?= htmlspecialchars($p['position'] ?? '—') ?></td>
                        <td style="text-align: center;">
                            <?= !empty($p['shirt_number']) ? htmlspecialchars($p['shirt_number']) : '—' ?>
                        </td>
                        <td>
                            <?php if(!empty($p['is_captain'])): ?>
                                <span class="badge badge-captain">Capitaine</span>
                            <?php else: ?>
                                <span class="badge badge-normal">Joueur</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="goals-count"><?= $p['goals'] ?? 0 ?></span>
                        </td>
                        <td>
                            <div class="actions-cell">
                                <a href="modifier_joueur.php?id=<?= $p['player_id'] ?>" class="btn-custom btn-action btn-edit">✏️ Modifier</a>
                                <a href="delete_joueur.php?id=<?= $p['player_id'] ?>" class="btn-custom btn-action btn-delete" onclick="return confirm('Supprimer ce joueur ?')">🗑️ Supprimer</a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="8" class="empty-row-text">Aucun joueur trouvé pour cette sélection ou cette recherche.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

<?php
$content = ob_get_clean();
include '../layout.php';
?>