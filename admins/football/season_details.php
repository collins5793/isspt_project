<?php
session_start();
require_once '../../includes/db.php';

$seasonId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if(!$seasonId) die("ID de saison invalide.");

// --- Saison ---
$stmt = $pdo->prepare("
    SELECT fs.*, ay.label AS academic_year, a.nom AS creator_nom, a.prenom AS creator_prenom
    FROM football_seasons fs
    JOIN academic_years ay ON fs.academic_year_id=ay.id
    LEFT JOIN administrateurs a ON fs.created_by=a.id_admin
    WHERE fs.id_season=?
");
$stmt->execute([$seasonId]);
$season = $stmt->fetch(PDO::FETCH_ASSOC);
if(!$season) die("Saison introuvable.");

// --- Paramètres ---
$stmt = $pdo->prepare("SELECT match_type FROM season_settings WHERE season_id=?");
$stmt->execute([$seasonId]);
$settings = $stmt->fetch(PDO::FETCH_ASSOC);

// --- Équipes et joueurs ---
$teamsStmt = $pdo->prepare("SELECT * FROM football_teams WHERE season_id=? ORDER BY name ASC");
$teamsStmt->execute([$seasonId]);
$teams = $teamsStmt->fetchAll(PDO::FETCH_ASSOC);

// Joueurs
$players = [];
foreach($teams as $team){
    $stmt = $pdo->prepare("SELECT * FROM team_players WHERE team_id=? ORDER BY shirt_number ASC");
    $stmt->execute([$team['team_id']]);
    $players[$team['team_id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// --- Poules et pool_teams ---
$poolsStmt = $pdo->prepare("SELECT * FROM pools WHERE season_id=? ORDER BY name ASC");
$poolsStmt->execute([$seasonId]);
$pools = $poolsStmt->fetchAll(PDO::FETCH_ASSOC);

$poolTeams = [];
foreach($pools as $pool){
    $stmt = $pdo->prepare("
        SELECT pt.*, t.name AS team_name
        FROM pool_teams pt
        JOIN football_teams t ON pt.team_id=t.team_id
        WHERE pt.pool_id=? ORDER BY pt.points DESC, pt.goals_for DESC
    ");
    $stmt->execute([$pool['pool_id']]);
    $poolTeams[$pool['name']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// --- Matchs ---
$matchesStmt = $pdo->prepare("
    SELECT m.*, t1.name AS team1_name, t2.name AS team2_name, p.name AS pool_name
    FROM matches m
    JOIN football_teams t1 ON m.team1_id=t1.team_id
    JOIN football_teams t2 ON m.team2_id=t2.team_id
    LEFT JOIN pools p ON m.pool_id=p.pool_id
    WHERE m.season_id=? ORDER BY m.stage ASC, m.round_number ASC
");
$matchesStmt->execute([$seasonId]);
$matches = $matchesStmt->fetchAll(PDO::FETCH_ASSOC);

// --- Historique des modifications ---
$historyStmt = $pdo->prepare("
    SELECT h.*, a.nom AS admin_nom, a.prenom AS admin_prenom, m.team1_id, m.team2_id, t1.name AS team1_name, t2.name AS team2_name
    FROM match_results_history h
    JOIN administrateurs a ON h.changed_by=a.id_admin
    JOIN matches m ON h.match_id=m.match_id
    JOIN football_teams t1 ON m.team1_id=t1.team_id
    JOIN football_teams t2 ON m.team2_id=t2.team_id
    WHERE m.season_id=?
    ORDER BY h.changed_at DESC
");
$historyStmt->execute([$seasonId]);
$history = $historyStmt->fetchAll(PDO::FETCH_ASSOC);

// --- Résultats finaux ---
$finalStmt = $pdo->prepare("
    SELECT f.*, t1.name AS champion_name, t2.name AS runner_up_name, m.match_id
    FROM match_finale f
    JOIN football_teams t1 ON f.champion_team_id=t1.team_id
    JOIN football_teams t2 ON f.runner_up_team_id=t2.team_id
    JOIN matches m ON f.match_id=m.match_id
    WHERE f.season_id=?
");
$finalStmt->execute([$seasonId]);
$finalResult = $finalStmt->fetch(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Détails de la saison <?= htmlspecialchars($season['label']) ?></title>
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

/* Base Body Styles */
body {
    font-family: var(--font-primary);
    background-color: var(--primary-900);
    color: var(--gray-100);
    margin: 0;
    padding: var(--space-5);
    line-height: 1.5;
    box-sizing: border-box;
}

*, *::before, *::after {
    box-sizing: inherit;
}

/* Container Structure */
.container {
    max-width: 1400px;
    margin: 0 auto;
}

/* Header & Banner styling */
.main-header {
    margin-bottom: var(--space-6);
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    padding-bottom: var(--space-4);
}

.main-title {
    font-size: 2rem;
    font-weight: 700;
    margin: 0 0 var(--space-2) 0;
    color: var(--white);
    display: flex;
    align-items: center;
    gap: var(--space-3);
}

/* Podiums & Final Results Cards */
.podium-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: var(--space-4);
    margin-bottom: var(--space-6);
}

.podium-card {
    background: linear-gradient(135deg, var(--primary-800) 0%, var(--primary-700) 100%);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: var(--radius-lg);
    padding: var(--space-5);
    display: flex;
    align-items: center;
    gap: var(--space-4);
    box-shadow: var(--shadow-md);
}

.podium-icon {
    font-size: 2.5rem;
    padding: var(--space-3);
    border-radius: var(--radius-md);
    background: rgba(255, 255, 255, 0.03);
}

.podium-card.champion .podium-icon {
    color: #ffd700;
    background: rgba(255, 215, 0, 0.1);
}

.podium-info h3 {
    margin: 0;
    font-size: var(--font-size-sm);
    color: var(--gray-400);
    text-transform: uppercase;
    letter-spacing: 1px;
}

.podium-info p {
    margin: var(--space-1) 0 0 0;
    font-size: var(--font-size-xl);
    font-weight: 700;
    color: var(--white);
}

/* Dashboard Structural Blocks */
.dashboard-block {
    background-color: var(--primary-800);
    border: 1px solid rgba(255, 255, 255, 0.05);
    border-radius: var(--radius-xl);
    padding: var(--space-5);
    margin-bottom: var(--space-6);
    box-shadow: var(--shadow-lg);
}

.block-title {
    font-size: var(--font-size-xl);
    font-weight: 600;
    margin-top: 0;
    margin-bottom: var(--space-5);
    color: var(--white);
    display: flex;
    align-items: center;
    gap: var(--space-2);
    border-left: 4px solid var(--accent-blue);
    padding-left: var(--space-3);
}

/* Information Grid List */
.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: var(--space-4);
}

.info-tile {
    background-color: var(--primary-700);
    padding: var(--space-4);
    border-radius: var(--radius-lg);
    border: 1px solid rgba(255, 255, 255, 0.03);
}

.info-label {
    font-size: var(--font-size-xs);
    color: var(--gray-400);
    text-transform: uppercase;
    margin-bottom: var(--space-1);
    letter-spacing: 0.5px;
}

.info-value {
    font-size: var(--font-size-md);
    font-weight: 600;
    color: var(--white);
}

/* Entities Internal Subsections (Teams/Pools) */
.sub-entity-box {
    background-color: var(--primary-700);
    border-radius: var(--radius-lg);
    padding: var(--space-4);
    margin-bottom: var(--space-4);
    border: 1px solid rgba(255, 255, 255, 0.02);
}

.sub-entity-title {
    font-size: var(--font-size-lg);
    font-weight: 600;
    color: var(--white);
    margin-top: 0;
    margin-bottom: var(--space-3);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: var(--space-2);
}

.sub-entity-meta {
    font-size: var(--font-size-sm);
    color: var(--gray-300);
    font-weight: 400;
}

/* Professional Tables Styling */
.table-scroll-wrapper {
    width: 100%;
    overflow-x: auto;
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-sm);
    background-color: var(--primary-600);
}

.custom-table {
    width: 100%;
    border-collapse: collapse;
    text-align: left;
    font-size: var(--font-size-sm);
    min-width: 600px;
}

.custom-table th {
    background-color: var(--primary-600);
    color: var(--gray-300);
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

/* Custom Component Badges & States */
.status-badge {
    display: inline-flex;
    align-items: center;
    padding: var(--space-1) var(--space-3);
    border-radius: var(--radius-full);
    font-size: var(--font-size-xs);
    font-weight: 600;
}

.status-active {
    background-color: rgba(16, 172, 132, 0.15);
    color: #1dd1a1;
    border: 1px solid rgba(16, 172, 132, 0.2);
}

.status-inactive {
    background-color: rgba(255, 71, 87, 0.15);
    color: #ff6b81;
    border: 1px solid rgba(255, 71, 87, 0.2);
}

.captain-dot {
    display: inline-flex;
    padding: var(--space-1) var(--space-2);
    background-color: rgba(46, 134, 222, 0.15);
    color: #54a0ff;
    font-size: var(--font-size-xs);
    font-weight: 600;
    border-radius: var(--radius-sm);
}

.score-display {
    font-family: monospace;
    font-size: var(--font-size-md);
    font-weight: 700;
    background-color: var(--primary-900);
    padding: var(--space-1) var(--space-3);
    border-radius: var(--radius-sm);
    color: var(--white);
    display: inline-block;
    letter-spacing: 1px;
}

.empty-state {
    color: var(--gray-400);
    font-style: italic;
    padding: var(--space-3) 0;
}

/* Responsive Structural Overrides */
@media (max-width: 768px) {
    body {
        padding: var(--space-3);
    }
    
    .main-title {
        font-size: 1.5rem;
    }
    
    .dashboard-block {
        padding: var(--space-4);
    }
    
    .info-grid {
        grid-template-columns: 1fr;
    }
}
</style>
</head>
<body>

<div class="container">

    <!-- Header Principal -->
    <header class="main-header">
        <h1 class="main-title">📅 Détails de la saison : <?= htmlspecialchars($season['label']) ?></h1>
    </header>

    <!-- Section Podium & Résultats Finaux (si disponible) -->
    <?php if($finalResult): ?>
    <section class="podium-grid">
        <div class="podium-card champion">
            <div class="podium-icon">🏆</div>
            <div class="podium-info">
                <h3>Champion</h3>
                <p><?= htmlspecialchars($finalResult['champion_name']) ?></p>
            </div>
        </div>
        <div class="podium-card">
            <div class="podium-icon" style="color: var(--gray-300);">🥈</div>
            <div class="podium-info">
                <h3>Vice-Champion</h3>
                <p><?= htmlspecialchars($finalResult['runner_up_name']) ?></p>
            </div>
        </div>
        <div class="podium-card">
            <div class="podium-icon" style="color: var(--accent-blue); font-size: 1.75rem;">⚽</div>
            <div class="podium-info">
                <h3>Match de Finale</h3>
                <p>ID ##<?= $finalResult['match_id'] ?></p>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Section Informations Générales -->
    <section class="dashboard-block">
        <h2 class="block-title">Informations générales</h2>
        <div class="info-grid">
            <div class="info-tile">
                <div class="info-label">Année académique</div>
                <div class="info-value"><?= htmlspecialchars($season['academic_year']) ?></div>
            </div>
            <div class="info-tile">
                <div class="info-label">Statut</div>
                <div class="info-value">
                    <?= $season['is_active'] ? '<span class="status-badge status-active">Active</span>' : '<span class="status-badge status-inactive">Inactive</span>' ?>
                </div>
            </div>
            <div class="info-tile">
                <div class="info-label">Créateur</div>
                <div class="info-value"><?= htmlspecialchars($season['creator_nom'].' '.$season['creator_prenom']) ?></div>
            </div>
            <div class="info-tile">
                <div class="info-label">Type de match</div>
                <div class="info-value"><?= htmlspecialchars($settings['match_type'] ?? 'Non spécifié') ?></div>
            </div>
            <div class="info-tile">
                <div class="info-label">Date de création</div>
                <div class="info-value" style="font-size: var(--font-size-sm); color: var(--gray-300);"><?= $season['created_at'] ?></div>
            </div>
            <div class="info-tile">
                <div class="info-label">Mise à jour</div>
                <div class="info-value" style="font-size: var(--font-size-sm); color: var(--gray-300);"><?= $season['updated_at'] ?: '—' ?></div>
            </div>
        </div>
    </section>

    <!-- Section Poules -->
    <section class="dashboard-block">
        <h2 class="block-title">Classement des Poules</h2>
        <?php if(!empty($pools)): ?>
            <?php foreach($pools as $pool): ?>
                <div class="sub-entity-box">
                    <h3 class="sub-entity-title">
                        <span>Poule : <?= htmlspecialchars($pool['name']) ?></span>
                        <span class="sub-entity-meta">Nombre de qualifiés : <strong><?= $pool['advance_count'] ?></strong></span>
                    </h3>
                    
                    <div class="table-scroll-wrapper">
                        <table class="custom-table">
                            <thead>
                                <tr>
                                    <th>Équipe</th>
                                    <th style="text-align: center;">Points</th>
                                    <th style="text-align: center;">MJ</th>
                                    <th style="text-align: center;">G</th>
                                    <th style="text-align: center;">N</th>
                                    <th style="text-align: center;">P</th>
                                    <th style="text-align: center;">BP</th>
                                    <th style="text-align: center;">BC</th>
                                    <th style="text-align: center;">Diff</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($poolTeams[$pool['name']] as $pt): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($pt['team_name']) ?></strong></td>
                                    <td style="text-align: center; color: var(--accent-green); font-weight:700;"><?= $pt['points'] ?></td>
                                    <td style="text-align: center;"><?= $pt['played'] ?></td>
                                    <td style="text-align: center;"><?= $pt['won'] ?></td>
                                    <td style="text-align: center;"><?= $pt['drawn'] ?></td>
                                    <td style="text-align: center;"><?= $pt['lost'] ?></td>
                                    <td style="text-align: center;"><?= $pt['goals_for'] ?></td>
                                    <td style="text-align: center;"><?= $pt['goals_against'] ?></td>
                                    <td style="text-align: center; font-weight:600; color: <?= ($pt['goals_for'] - $pt['goals_against'] >= 0) ? 'var(--gray-100)' : 'var(--accent-red)' ?>;">
                                        <?= $pt['goals_for'] - $pt['goals_against'] ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="empty-state">Aucune poule n'est paramétrée pour cette saison.</p>
        <?php endif; ?>
    </section>

    <!-- Section Équipes et Joueurs -->
    <section class="dashboard-block">
        <h2 class="block-title">Équipes & Effectifs</h2>
        <?php if(!empty($teams)): ?>
            <?php foreach($teams as $team): ?>
                <div class="sub-entity-box">
                    <h3 class="sub-entity-title">
                        <span>🏃‍♂️ <?= htmlspecialchars($team['name']) ?></span>
                        <span class="sub-entity-meta">Coach : <strong><?= htmlspecialchars($team['coach'] ?: 'Aucun') ?></strong> | Statut : <em><?= htmlspecialchars($team['status'] ?? '—') ?></em></span>
                    </h3>
                    
                    <?php if(!empty($players[$team['team_id']])): ?>
                        <div class="table-scroll-wrapper">
                            <table class="custom-table">
                                <thead>
                                    <tr>
                                        <th>Nom du Joueur</th>
                                        <th style="text-align: center;">Numéro</th>
                                        <th>Poste</th>
                                        <th>Rôle</th>
                                        <th>Date d'arrivée</th>
                                        <th>Date de départ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($players[$team['team_id']] as $p): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($p['full_name']) ?></strong></td>
                                        <td style="text-align: center;"><span class="score-display" style="font-size:var(--font-size-sm);"><?= $p['shirt_number'] ?: '—' ?></span></td>
                                        <td><?= htmlspecialchars($p['position'] ?: 'Non défini') ?></td>
                                        <td><?= $p['is_captain'] ? '<span class="captain-dot">★ Capitaine</span>' : '<span style="color:var(--gray-400)">Joueur</span>' ?></td>
                                        <td style="color: var(--gray-300); font-size:var(--font-size-sm);"><?= $p['joined_at'] ?></td>
                                        <td style="color: var(--gray-400); font-size:var(--font-size-sm);"><?= $p['left_at'] ?: '—' ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="empty-state">Aucun joueur enregistré dans cette équipe pour le moment.</p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="empty-state">Aucune équipe n'est enregistrée pour cette saison.</p>
        <?php endif; ?>
    </section>

    <!-- Section Calendrier des Matchs -->
    <section class="dashboard-block">
        <h2 class="block-title">Calendrier & Résultats des Matchs</h2>
        <?php if(!empty($matches)): ?>
            <div class="table-scroll-wrapper">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>Poule</th>
                            <th>Équipe Domicile</th>
                            <th style="text-align: center;">Score</th>
                            <th>Équipe Extérieur</th>
                            <th>Étape / Round</th>
                            <th>Date & Heure</th>
                            <th>Lieu</th>
                            <th style="text-align: center;">Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($matches as $m): ?>
                        <tr>
                            <td><span style="color:var(--gray-300); font-weight:600;"><?= htmlspecialchars($m['pool_name'] ?: 'Phase finale') ?></span></td>
                            <td><strong><?= htmlspecialchars($m['team1_name']) ?></strong></td>
                            <td style="text-align: center;">
                                <span class="score-display">
                                    <?= ($m['score_team1'] !== null ? $m['score_team1'].' - '.$m['score_team2'] : 'vs') ?>
                                </span>
                            </td>
                            <td><strong><?= htmlspecialchars($m['team2_name']) ?></strong></td>
                            <td><span class="status-badge" style="background: var(--primary-700); color: var(--white);"><?= htmlspecialchars($m['stage']) ?> (T.<?= $m['round_number'] ?>)</span></td>
                            <td style="font-size: var(--font-size-sm); color: var(--gray-300);"><?= $m['match_datetime'] ?: 'À déterminer' ?></td>
                            <td><?= htmlspecialchars($m['location'] ?: '—') ?></td>
                            <td style="text-align: center;">
                                <?= $m['is_played'] ? '<span class="status-badge status-active" title="Match joué">✓ Joué</span>' : '<span class="status-badge status-inactive" style="background:rgba(46,134,222,0.15); color:#54a0ff; border-color:transparent;">Planifié</span>' ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="empty-state">Aucun match planifié pour le moment.</p>
        <?php endif; ?>
    </section>

    <!-- Section Historique des modifications -->
    <section class="dashboard-block">
        <h2 class="block-title">Historique des modifications de scores</h2>
        <?php if($history): ?>
            <div class="table-scroll-wrapper">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>Match concerné</th>
                            <th style="text-align: center;">Ancien Score</th>
                            <th style="text-align: center;">Nouveau Score</th>
                            <th>Modifié par</th>
                            <th>Raison du changement</th>
                            <th>Date de l'action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($history as $h): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($h['team1_name'].' vs '.$h['team2_name']) ?></strong></td>
                            <td style="text-align: center;"><span style="color: var(--accent-red); font-family: monospace;"><?= $h['old_score_team1'].' - '.$h['old_score_team2'] ?></span></td>
                            <td style="text-align: center;"><span style="color: var(--accent-green); font-family: monospace; font-weight:700;"><?= $h['new_score_team1'].' - '.$h['new_score_team2'] ?></span></td>
                            <td><span style="color: var(--white); font-weight: 500;"><?= htmlspecialchars($h['admin_nom'].' '.$h['admin_prenom']) ?></span></td>
                            <td><span style="color: var(--gray-300); font-style: italic;"><?= htmlspecialchars($h['reason']) ?></span></td>
                            <td style="font-size: var(--font-size-sm); color: var(--gray-400);"><?= $h['changed_at'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="empty-state">Aucune modification de score n'a été enregistrée pour cette saison.</p>
        <?php endif; ?>
    </section>

</div>

</body>
</html>
<?php
// En conformité avec votre fin de fichier initiale
$content = ob_get_clean();
include '../layout.php';
?>