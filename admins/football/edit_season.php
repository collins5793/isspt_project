<?php
session_start();
require_once '../../includes/db.php';

if(!isset($_SESSION['admin_id'])){
    die("<div style='color: #ff4757; background: #080020; padding: var(--space-6); text-align: center; font-family: sans-serif; height: 100vh; display: flex; align-items: center; justify-content: center; flex-direction: column;'><h2>Accès refusé.</h2></div>");
}

$seasonId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if(!$seasonId) die("<div style='color: #ff4757; background: #080020; padding: var(--space-6); text-align: center; font-family: sans-serif; height: 100vh; display: flex; align-items: center; justify-content: center; flex-direction: column;'><h2>ID de saison invalide.</h2></div>");

// --- Récupérer saison ---
$stmt = $pdo->prepare("SELECT * FROM football_seasons WHERE id_season=?");
$stmt->execute([$seasonId]);
$season = $stmt->fetch(PDO::FETCH_ASSOC);
if(!$season) die("<div style='color: #ff4757; background: #080020; padding: var(--space-6); text-align: center; font-family: sans-serif; height: 100vh; display: flex; align-items: center; justify-content: center; flex-direction: column;'><h2>Saison introuvable.</h2></div>");

// --- Années académiques ---
$academicYears = $pdo->query("SELECT id,label FROM academic_years ORDER BY label DESC")->fetchAll(PDO::FETCH_ASSOC);

// --- Équipes ---
$teamsList = $pdo->prepare("SELECT * FROM football_teams WHERE season_id=? ORDER BY name ASC");
$teamsList->execute([$seasonId]);
$teams = $teamsList->fetchAll(PDO::FETCH_ASSOC);

// --- Poules ---
$poolsStmt = $pdo->prepare("SELECT * FROM pools WHERE season_id=? ORDER BY name ASC");
$poolsStmt->execute([$seasonId]);
$pools = $poolsStmt->fetchAll(PDO::FETCH_ASSOC);

// --- Pool teams ---
$poolTeams = [];
foreach($pools as $pool){
    $stmt = $pdo->prepare("SELECT pt.team_id, t.name FROM pool_teams pt JOIN football_teams t ON pt.team_id=t.team_id WHERE pt.pool_id=?");
    $stmt->execute([$pool['pool_id']]);
    $poolTeams[$pool['name']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// --- Saison settings ---
$stmt = $pdo->prepare("SELECT match_type FROM season_settings WHERE season_id=?");
$stmt->execute([$seasonId]);
$seasonSetting = $stmt->fetch(PDO::FETCH_ASSOC);
$matchType = $seasonSetting['match_type'] ?? 'simple';

// --- Matchs existants ---
$matchesStmt = $pdo->prepare("SELECT * FROM matches WHERE season_id=? ORDER BY pool_id, match_id ASC");
$matchesStmt->execute([$seasonId]);
$matches = $matchesStmt->fetchAll(PDO::FETCH_ASSOC);

$success = "";
$error = "";

// --- POST: Enregistrer modifications ---
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['save_season'])){
    try{
        $pdo->beginTransaction();

        // Mettre à jour saison
        $stmt = $pdo->prepare("UPDATE football_seasons SET label=?, academic_year_id=?, is_active=?, updated_at=NOW() WHERE id_season=?");
        $stmt->execute([$_POST['season_label'], $_POST['academic_year_id'], isset($_POST['is_active'])?1:0, $seasonId]);

        // Équipes: suppression, modification, ajout
        $existingTeamIds = array_column($teams,'team_id');
        $postedTeams = isset($_POST['teams']) ? $_POST['teams'] : [];
        $postedTeamIds = array_filter(array_column($postedTeams,'id'));
        $toDelete = array_diff($existingTeamIds,$postedTeamIds);
        if($toDelete) $pdo->exec("DELETE FROM football_teams WHERE team_id IN (".implode(',', $toDelete).")");

        $teamIdMap = [];
        foreach($postedTeams as $idx=>$team){
            if(!empty($team['id'])){
                $stmt = $pdo->prepare("UPDATE football_teams SET name=?, coach=? WHERE team_id=?");
                $stmt->execute([$team['name'],$team['coach'],$team['id']]);
                $teamIdMap[$idx]=$team['id'];
            } else {
                $stmt = $pdo->prepare("INSERT INTO football_teams (season_id,name,coach,created_by) VALUES (?,?,?,?)");
                $stmt->execute([$seasonId,$team['name'],$team['coach'],$_SESSION['admin_id']]);
                $teamIdMap[$idx]=$pdo->lastInsertId();
            }
        }

        // Poules et pool_teams
        $existingPoolIds = array_column($pools,'pool_id');
        $postedPools = isset($_POST['pools']) ? $_POST['pools'] : [];
        $postedPoolIds = array_filter(array_column($postedPools,'id'));
        $toDeletePools = array_diff($existingPoolIds,$postedPoolIds);
        if($toDeletePools) $pdo->exec("DELETE FROM pools WHERE pool_id IN (".implode(',',$toDeletePools).")");

        $poolMap = [];
        foreach($postedPools as $poolName=>$poolData){
            if(!empty($poolData['id'])){
                $stmt = $pdo->prepare("UPDATE pools SET name=?, advance_count=? WHERE pool_id=?");
                $stmt->execute([$poolData['name'],$poolData['advance_count'],$poolData['id']]);
                $poolMap[$poolData['name']] = $poolData['id'];
            } else {
                $stmt = $pdo->prepare("INSERT INTO pools (season_id,name,advance_count) VALUES (?,?,?)");
                $stmt->execute([$seasonId,$poolData['name'],$poolData['advance_count']]);
                $poolMap[$poolData['name']] = $pdo->lastInsertId();
            }
            if(!empty($poolData['teams'])){
                $pdo->prepare("DELETE FROM pool_teams WHERE pool_id=?")->execute([$poolMap[$poolData['name']]]);
                foreach($poolData['teams'] as $teamIndex){
                    $stmt = $pdo->prepare("INSERT INTO pool_teams (pool_id,team_id,season_id) VALUES (?,?,?)");
                    $stmt->execute([$poolMap[$poolData['name']], $teamIdMap[$teamIndex], $seasonId]);
                }
            }
        }

        // Type de match
        $stmt = $pdo->prepare("UPDATE season_settings SET match_type=? WHERE season_id=?");
        $stmt->execute([$_POST['match_type'],$seasonId]);

        // Matchs existants: mettre à jour les dates
        if(!empty($_POST['match_dates'])){
            foreach($_POST['match_dates'] as $pName=>$poolData){
                foreach($poolData as $team1Idx=>$team2Data){
                    foreach($team2Data as $team2Idx=>$date){
                        if(isset($teamIdMap[$team1Idx]) && isset($teamIdMap[$team2Idx])){
                            $team1Id = $teamIdMap[$team1Idx];
                            $team2Id = $teamIdMap[$team2Idx];
                            $stmt = $pdo->prepare("UPDATE matches SET match_datetime=? WHERE season_id=? AND team1_id=? AND team2_id=?");
                            $stmt->execute([$date,$seasonId,$team1Id,$team2Id]);
                        }
                    }
                }
            }
        }

        $pdo->commit();
        header("Location: modifier_saison.php?id=".$seasonId."&success=1");
        exit;

    }catch(Exception $e){
        $pdo->rollBack();
        $error = "Erreur de traitement : ".$e->getMessage();
    }
}

if(isset($_GET['success'])) {
    $success = "Saison configurée et mise à jour avec succès !";
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier Saison — Tableau de bord</title>
    <style>
        :root {
            /* Colors - Dark Theme */
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

            /* Sidebar contextual dimensions */
            --sidebar-width: 280px;
            --sidebar-width-collapsed: 70px;
            --sidebar-bg: linear-gradient(180deg, var(--primary-900) 0%, var(--primary-800) 100%);
            --sidebar-border: 1px solid rgba(255, 255, 255, 0.05);
            --sidebar-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            
            /* Typography */
            --font-primary: 'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif;
            --font-size-xs: 0.75rem;
            --font-size-sm: 0.875rem;
            --font-size-md: 1rem;
            --font-size-lg: 1.125rem;
            --font-size-xl: 1.25rem;
            
            /* Spacing */
            --space-1: 0.25rem;
            --space-2: 0.5rem;
            --space-3: 0.75rem;
            --space-4: 1rem;
            --space-5: 1.5rem;
            --space-6: 2rem;
            
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

        /* Base Resets */
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: var(--font-primary);
            background-color: var(--primary-900);
            color: var(--white);
            min-height: 100vh;
            line-height: 1.5;
            padding: var(--space-5);
        }

        /* Layout Container */
        .admin-wrapper {
            max-width: 1200px;
            margin: 0 auto;
            padding-bottom: var(--space-6);
        }

        /* Page Title Header */
        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: var(--space-4);
            margin-bottom: var(--space-6);
            padding-bottom: var(--space-4);
            border-bottom: 1px solid var(--primary-600);
        }

        .page-title h1 {
            font-size: var(--font-size-xl);
            font-weight: 700;
            letter-spacing: -0.02em;
        }

        .page-title p {
            font-size: var(--font-size-sm);
            color: var(--gray-400);
        }

        /* Notifications */
        .alert {
            padding: var(--space-4);
            border-radius: var(--radius-md);
            font-size: var(--font-size-sm);
            font-weight: 500;
            margin-bottom: var(--space-5);
            display: flex;
            align-items: center;
            gap: var(--space-2);
            box-shadow: var(--shadow-sm);
        }

        .alert-success {
            background-color: rgba(16, 172, 132, 0.1);
            border: 1px solid var(--accent-green);
            color: var(--white);
        }

        .alert-error {
            background-color: rgba(255, 71, 87, 0.1);
            border: 1px solid var(--accent-red);
            color: var(--white);
        }

        /* Structure des Sections / Cards */
        .card-section {
            background-color: var(--primary-800);
            border: 1px solid rgba(255, 255, 255, 0.04);
            border-radius: var(--radius-lg);
            padding: var(--space-5);
            margin-bottom: var(--space-5);
            box-shadow: var(--shadow-md);
            transition: border-color var(--transition-base);
        }

        .card-section:hover {
            border-color: rgba(46, 134, 222, 0.2);
        }

        .section-title {
            font-size: var(--font-size-lg);
            font-weight: 600;
            margin-bottom: var(--space-4);
            color: var(--gray-100);
            display: flex;
            align-items: center;
            gap: var(--space-2);
            border-left: 3px solid var(--accent-blue);
            padding-left: var(--space-2);
        }

        /* Form Controls standardisés */
        .form-grid-3 {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: var(--space-4);
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: var(--space-2);
        }

        .form-group label {
            font-size: var(--font-size-sm);
            color: var(--gray-300);
            font-weight: 500;
        }

        .input-text, .input-select, .input-number {
            width: 100%;
            background-color: var(--primary-700);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: var(--white);
            padding: var(--space-3) var(--space-4);
            font-size: var(--font-size-sm);
            border-radius: var(--radius-md);
            outline: none;
            transition: all var(--transition-fast);
            font-family: inherit;
        }

        .input-text:focus, .input-select:focus, .input-number:focus {
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 3px rgba(46, 134, 222, 0.2);
            background-color: var(--primary-600);
        }

        /* Custom Checkbox Toggle styled minimal dark */
        .toggle-container {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            cursor: pointer;
            user-select: none;
            margin-top: var(--space-2);
        }

        .toggle-input {
            appearance: none;
            width: 42px;
            height: 22px;
            background-color: var(--primary-600);
            border-radius: var(--radius-full);
            position: relative;
            cursor: pointer;
            transition: background-color var(--transition-base);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .toggle-input::before {
            content: '';
            position: absolute;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background-color: var(--gray-300);
            top: 2px;
            left: 2px;
            transition: transform var(--transition-base), background-color var(--transition-base);
        }

        .toggle-input:checked {
            background-color: var(--accent-green);
        }

        .toggle-input:checked::before {
            transform: translateX(20px);
            background-color: var(--white);
        }

        /* Boutons d'actions */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: var(--space-2);
            font-family: inherit;
            font-size: var(--font-size-sm);
            font-weight: 600;
            padding: var(--space-3) var(--space-4);
            border-radius: var(--radius-md);
            border: none;
            cursor: pointer;
            transition: all var(--transition-base);
        }

        .btn-primary {
            background-color: var(--accent-blue);
            color: var(--white);
            box-shadow: var(--shadow-sm);
        }

        .btn-primary:hover {
            background-color: #2475c7;
            transform: translateY(-1px);
        }

        .btn-secondary {
            background-color: var(--primary-600);
            color: var(--gray-100);
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        .btn-secondary:hover {
            background-color: var(--primary-700);
            color: var(--white);
        }

        .btn-danger {
            background-color: rgba(255, 71, 87, 0.1);
            color: var(--accent-red);
            border: 1px solid rgba(255, 71, 87, 0.2);
        }

        .btn-danger:hover {
            background-color: var(--accent-red);
            color: var(--white);
        }

        .btn-submit-main {
            background-color: var(--accent-green);
            color: var(--white);
            font-size: var(--font-size-md);
            padding: var(--space-4) var(--space-6);
            width: 100%;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
        }

        .btn-submit-main:hover {
            background-color: #0e9673;
            box-shadow: 0 4px 15px rgba(16, 172, 132, 0.3);
            transform: translateY(-2px);
        }

        /* Entrées dynamiques (Équipes et Poules) */
        .dynamic-list {
            display: flex;
            flex-direction: column;
            gap: var(--space-3);
            margin-bottom: var(--space-4);
        }

        .entry-row {
            display: grid;
            grid-template-columns: 1fr 1fr auto;
            gap: var(--space-3);
            background-color: var(--primary-700);
            padding: var(--space-3);
            border-radius: var(--radius-md);
            align-items: center;
            border: 1px solid rgba(255, 255, 255, 0.02);
            animation: fadeIn 200ms ease-out;
        }

        /* Poules Structure multi-colonnes */
        .pool-card {
            background-color: var(--primary-700);
            border-radius: var(--radius-md);
            padding: var(--space-4);
            margin-bottom: var(--space-4);
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        .pool-card-header {
            display: grid;
            grid-template-columns: 2fr 1fr auto;
            gap: var(--space-3);
            align-items: center;
            margin-bottom: var(--space-4);
            padding-bottom: var(--space-3);
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }

        .pool-team-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: var(--space-2);
            background: var(--primary-800);
            padding: var(--space-3);
            border-radius: var(--radius-md);
            max-height: 200px;
            overflow-y: auto;
        }

        .checkbox-label {
            display: flex;
            align-items: center;
            gap: var(--space-2);
            font-size: var(--font-size-sm);
            color: var(--gray-200);
            cursor: pointer;
            padding: var(--space-1) var(--space-2);
            border-radius: var(--radius-sm);
            background: var(--primary-700);
            transition: background var(--transition-fast);
        }

        .checkbox-label:hover {
            background: var(--primary-600);
            color: var(--white);
        }

        /* Radio group pour types de matchs */
        .radio-channels {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: var(--space-3);
        }

        .radio-tile {
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: var(--space-4);
            background-color: var(--primary-700);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-md);
            cursor: pointer;
            transition: all var(--transition-base);
            text-align: center;
        }

        .radio-tile input[type="radio"] {
            position: absolute;
            opacity: 0;
        }

        .radio-tile .tile-label {
            font-size: var(--font-size-sm);
            font-weight: 600;
            color: var(--gray-300);
        }

        .radio-tile:hover {
            background-color: var(--primary-600);
            border-color: rgba(46, 134, 222, 0.4);
        }

        .radio-tile input[type="radio"]:checked + .tile-label {
            color: var(--accent-blue);
        }

        .radio-tile:has(input[type="radio"]:checked) {
            border-color: var(--accent-blue);
            background-color: rgba(46, 134, 222, 0.05);
            box-shadow: 0 0 10px rgba(46, 134, 222, 0.1);
        }

        /* Tables & Matrix Matchs */
        .table-responsive-container {
            width: 100%;
            overflow-x: auto;
            border-radius: var(--radius-md);
            border: 1px solid rgba(255, 255, 255, 0.08);
            background: var(--primary-700);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: var(--font-size-sm);
            color: var(--gray-200);
        }

        th, td {
            padding: var(--space-3);
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            border-right: 1px solid rgba(255, 255, 255, 0.05);
        }

        th {
            background-color: var(--primary-600);
            color: var(--white);
            font-weight: 600;
        }

        tr:last-child td {
            border-bottom: none;
        }

        .input-table-date {
            background: var(--primary-800);
            border: 1px solid rgba(255,255,255,0.1);
            color: var(--white);
            padding: var(--space-1) var(--space-2);
            border-radius: var(--radius-sm);
            font-family: inherit;
            font-size: var(--font-size-xs);
            outline: none;
        }

        .input-table-date:focus {
            border-color: var(--accent-blue);
        }

        .nested-match-info {
            font-size: var(--font-size-xs);
            color: var(--gray-400);
            margin-top: var(--space-1);
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(4px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Écrans Mobiles et Tablettes verticales */
        @media (max-width: 768px) {
            body { padding: var(--space-3); }
            .entry-row {
                grid-template-columns: 1fr;
                gap: var(--space-2);
            }
            .pool-card-header {
                grid-template-columns: 1fr;
                gap: var(--space-2);
            }
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>

<div class="admin-wrapper">

    <!-- En-tête de page -->
    <header class="page-header">
        <div class="page-title">
            <h1>Modifier la saison : <?= htmlspecialchars($season['label']) ?></h1>
            <p>Gestion globale de la configuration des ligues, poules et calendriers correspondants</p>
        </div>
        <div>
            <a href="seasons_list.php" class="btn btn-secondary">Retour à la liste</a>
        </div>
    </header>

    <!-- Notifications Système -->
    <?php if(!empty($success)): ?>
        <div class="alert alert-success">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
            <div><?= $success ?></div>
        </div>
    <?php endif; ?>

    <?php if(!empty($error)): ?>
        <div class="alert alert-error">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
            <div><?= $error ?></div>
        </div>
    <?php endif; ?>

    <!-- Formulaire d'administration global -->
    <form method="post" id="seasonForm">

        <!-- 1️⃣ Config Générale -->
        <section class="card-section">
            <h2 class="section-title">Paramètres Généraux</h2>
            <div class="form-grid-3">
                <div class="form-group">
                    <label for="season_label">Nom / Label de la saison</label>
                    <input type="text" class="input-text" id="season_label" name="season_label" value="<?= htmlspecialchars($season['label']) ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="academic_year_id">Année académique rattachée</label>
                    <select class="input-select" id="academic_year_id" name="academic_year_id">
                        <?php foreach($academicYears as $year): ?>
                            <option value="<?= $year['id'] ?>" <?= $year['id']==$season['academic_year_id']?'selected':'' ?>><?= htmlspecialchars($year['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Statut de la Saison</label>
                    <label class="toggle-container">
                        <input type="checkbox" class="toggle-input" name="is_active" <?= $season['is_active']?'checked':'' ?>>
                        <span>Définir comme saison active</span>
                    </label>
                </div>
            </div>
        </section>

        <!-- 2️⃣ Les Équipes Engageés -->
        <section class="card-section">
            <h2 class="section-title">Équipes Engagées</h2>
            <div class="dynamic-list" id="teams-container">
                <?php foreach($teams as $idx=>$team): ?>
                    <div class="entry-row">
                        <input type="hidden" name="teams[<?= $idx ?>][id]" value="<?= $team['team_id'] ?>">
                        <div class="form-group">
                            <input type="text" class="input-text" name="teams[<?= $idx ?>][name]" value="<?= htmlspecialchars($team['name']) ?>" placeholder="Nom du club" required>
                        </div>
                        <div class="form-group">
                            <input type="text" class="input-text" name="teams[<?= $idx ?>][coach]" value="<?= htmlspecialchars($team['coach']) ?>" placeholder="Nom du Manager / Coach" required>
                        </div>
                        <button type="button" class="btn btn-danger" onclick="removeEntry(this)">Supprimer</button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="btn btn-secondary" onclick="addTeam()">+ Engager une équipe</button>
        </section>

        <!-- 3️⃣ Gestion des Poules de Championnat -->
        <section class="card-section">
            <h2 class="section-title">Poules et Qualification</h2>
            <div id="pools-container">
                <?php foreach($pools as $pool): ?>
                    <div class="pool-card" id="pool_<?= htmlspecialchars($pool['name']) ?>">
                        <input type="hidden" name="pools[<?= $pool['name'] ?>][id]" value="<?= $pool['pool_id'] ?>">
                        
                        <div class="pool-card-header">
                            <div class="form-group">
                                <input type="text" class="input-text" name="pools[<?= $pool['name'] ?>][name]" value="<?= htmlspecialchars($pool['name']) ?>" placeholder="Nom de la poule (Ex: Poule A)" required>
                            </div>
                            <div class="form-group">
                                <input type="number" class="input-number" name="pools[<?= $pool['name'] ?>][advance_count]" value="<?= $pool['advance_count'] ?>" placeholder="Nbr qualifiés">
                            </div>
                            <button type="button" class="btn btn-danger" onclick="removeEntry(this)">Supprimer Poule</button>
                        </div>

                        <div class="form-group">
                            <label style="margin-bottom: var(--space-1);">Distribuer les équipes engagées dans cette poule :</label>
                            <div class="pool-team-grid">
                                <?php
                                $teamIndices = array_flip(array_column($teams,'team_id'));
                                $currentPoolTeams = $poolTeams[$pool['name']] ?? [];
                                foreach($currentPoolTeams as $pt):
                                    $idx = $teamIndices[$pt['team_id']] ?? -1;
                                    if($idx>=0):
                                ?>
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="pools[<?= $pool['name'] ?>][teams][]" value="<?= $idx ?>" checked> 
                                        <span><?= htmlspecialchars($pt['name']) ?></span>
                                    </label>
                                <?php endif; endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="btn btn-secondary" onclick="addPool()">+ Ajouter une poule de calcul</button>
        </section>

        <!-- 4️⃣ Configuration des Règles de Match -->
        <section class="card-section">
            <h2 class="section-title">Format des Rencontres</h2>
            <div class="radio-channels">
                <label class="radio-tile">
                    <input type="radio" name="match_type" value="simple" <?= $matchType=='simple'?'checked':'' ?>>
                    <span class="tile-label">Match simple unique</span>
                </label>
                <label class="radio-tile">
                    <input type="radio" name="match_type" value="aller_retour" <?= $matchType=='aller_retour'?'checked':'' ?>>
                    <span class="tile-label">Confrontation Aller-Retour</span>
                </label>
                <label class="radio-tile">
                    <input type="radio" name="match_type" value="custom" <?= $matchType=='custom'?'checked':'' ?>>
                    <span class="tile-label">Tournoi personnalisé</span>
                </label>
            </div>
        </section>

        <!-- 5️⃣ Calendrier dynamique des Matchs Générés -->
        <section class="card-section">
            <h2 class="section-title">Calendrier & Planification des Matchs</h2>
            <div class="table-responsive-container">
                <div id="matches-container" style="padding: var(--space-2);">
                    <!-- Rempli en JS ou via la matrice de l'application -->
                    <table id="matchesTable">
                        <thead>
                            <tr>
                                <th>Poule</th>
                                <th>Équipe Recevante (1)</th>
                                <th>Équipe Visiteuse (2)</th>
                                <th>Date & Heure de la rencontre</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($matches)): ?>
                                <tr>
                                    <td colspan="4" style="color: var(--gray-400); padding: var(--space-5);">Aucun match généré pour cette configuration actuellement.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach($matches as $m): ?>
                                    <tr>
                                        <td><strong>Poule #<?= $m['pool_id'] ?></strong></td>
                                        <td><?= $m['team1_id'] ?></td>
                                        <td><?= $m['team2_id'] ?></td>
                                        <td>
                                            <input type="datetime-local" class="input-table-date" value="<?= $m['match_datetime'] ? date('Y-m-d\TH:i', strtotime($m['match_datetime'])) : '' ?>">
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- Action finale soumission complète de la vue -->
        <div style="margin-top: var(--space-5);">
            <button type="submit" name="save_season" class="btn btn-submit-main">Enregistrer toute la configuration de la saison</button>
        </div>

    </form>
</div>

<script>
// JS simplifié pour gérer équipes/poules et matchs
let teamIndex = <?= count($teams) ?>;
let poolIndex = 65;

// Ajouter équipe
function addTeam() {
    const container = document.getElementById('teams-container');
    const div = document.createElement('div');
    div.className = 'team-entry';
    div.innerHTML = `
        Nom : <input type="text" class="team-name" name="teams[${teamIndex}][name]" required>
        Coach : <input type="text" name="teams[${teamIndex}][coach]" required>
        <button type="button" onclick="removeEntry(this)">Supprimer</button>
    `;
    container.appendChild(div);
    teamIndex++;
    updatePoolTeamLists();
}

// Supprimer équipe/poule
function removeEntry(btn) {
    btn.parentElement.remove();
    updatePoolTeamLists();
}

// Récupère équipes dynamiques
function getCurrentTeams() {
    const names = [];
    document.querySelectorAll('#teams-container .team-entry .team-name').forEach((input, idx) => {
        if(input.value.trim() !== '') names.push({name: input.value.trim(), index: idx});
    });
    return names;
}

// Ajouter poule
function addPool() {
    const poolName = String.fromCharCode(poolIndex);
    const container = document.getElementById('pools-container');
    const div = document.createElement('div');
    div.className = "pool";
    div.id = "pool_" + poolName;

    div.innerHTML = `
        Nom : <input type="text" name="pools[${poolName}]" value="${poolName}" required>
        Équipes qualifiées : <input type="number" name="advance_count[${poolName}]" value="2" min="1">
        <button type="button" onclick="removeEntry(this)">Supprimer</button>
        <h4>Répartir les équipes :</h4>
        <div class="pool-team-list"></div>
    `;
    container.appendChild(div);
    updatePoolTeamLists();
    poolIndex++;
}

// Mettre à jour équipes dans poules
function updatePoolTeamLists() {
    const teams = getCurrentTeams();
    document.querySelectorAll('.pool .pool-team-list').forEach(poolDiv => {
        poolDiv.innerHTML = '';
        const poolId = poolDiv.parentElement.id.replace('pool_', '');
        teams.forEach(team => {
            poolDiv.innerHTML += `
                <label class="team-select">
                    <input type="checkbox" name="pool_teams[${poolId}][]" value="${team.index}"> ${team.name}
                </label>
            `;
        });
    });
    updateExclusiveTeams();
    generateMatches();
}

// Exclusivité
function updateExclusiveTeams() {
    const allCheckboxes = document.querySelectorAll('.pool input[type="checkbox"]');
    allCheckboxes.forEach(cb => cb.disabled = false);
    const selectedTeams = new Set();
    allCheckboxes.forEach(cb => { if(cb.checked) selectedTeams.add(cb.value); });
    allCheckboxes.forEach(cb => { if(!cb.checked && selectedTeams.has(cb.value)) cb.disabled = true; });
}

// Met à jour les options personnalisées et régénère les matchs
function toggleCustom(show) {
    const container = document.getElementById('custom-options');
    container.style.display = show ? 'block' : 'none';
    container.innerHTML = '';

    if(show){
        container.innerHTML = "<h4>Choisir les poules pour Aller-Retour :</h4>";
        for(let i = 65; i < poolIndex; i++){
            const poolName = String.fromCharCode(i);
            container.innerHTML += `<label>
                <input type="checkbox" name="custom_pools[${poolName}]" value="1" onchange="generateMatches()"> Poule ${poolName}
            </label><br>`;
        }
    }
    generateMatches(); // Toujours générer les matchs après toggle
}

// Mise à jour quand on coche/décoche une équipe ou une poule custom
document.addEventListener('change', function(e){
    if(e.target.matches('.pool input[type="checkbox"]') || e.target.matches('#custom-options input[type="checkbox"]')){
        updateExclusiveTeams();
        generateMatches();
    }
});



// Afficher les matchs existants
const matchesData = <?= json_encode($matches) ?>;
const teamMap = {};
<?php foreach($teams as $idx=>$team): ?>
teamMap[<?= $team['team_id'] ?>] = {index: <?= $idx ?>, name: "<?= htmlspecialchars($team['name']) ?>"};
<?php endforeach; ?>

function renderMatches(){
    const container = document.getElementById('matches-container');
    container.innerHTML = '';
    matchesData.forEach(m=>{
        if(!teamMap[m.team1_id] || !teamMap[m.team2_id]) return;
        container.innerHTML += `<div>
            ${teamMap[m.team1_id].name} vs ${teamMap[m.team2_id].name} :
            <input type="date" name="match_dates[pool${m.pool_id}][${teamMap[m.team1_id].index}][${teamMap[m.team2_id].index}]" value="${m.match_datetime?m.match_datetime.split(' ')[0]:''}">
        </div>`;
    });
}
renderMatches();

function generateMatches() {
    const container = document.getElementById('matches-container');
    container.innerHTML = '';
    const matchType = document.querySelector('input[name="match_type"]:checked').value;
    const pools = document.querySelectorAll('.pool');
    if(pools.length === 0) return;

    pools.forEach(pool => {
        const poolName = pool.id.replace('pool_', '');
        const checkedTeams = Array.from(pool.querySelectorAll('input[type="checkbox"]:checked')).map(cb => ({
            name: cb.parentElement.textContent.trim(),
            index: cb.value
        }));

        if(checkedTeams.length < 2) return;

        // Vérifie si on est en type personnalisé et si la poule est cochée
        let matchesCount = 1;
        if(matchType === 'aller_retour') {
            matchesCount = 2;
        } else if(matchType === 'custom') {
            const isCustomChecked = document.querySelector(`input[name="custom_pools[${poolName}]"]`)?.checked;
            if(isCustomChecked) matchesCount = 2;
        }

        let html = `<h4>Pool ${poolName}</h4>`;
        html += `<table><tr><th>Équipe 1</th><th>Équipe 2</th><th>Date</th></tr>`;
        for(let i=0; i<checkedTeams.length; i++){
            for(let j=i+1; j<checkedTeams.length; j++){
                for(let k=0; k<matchesCount; k++){
                    html += `<tr>
                        <td>${checkedTeams[i].name}</td>
                        <td>${checkedTeams[j].name}</td>
                        <td><input type="date" name="match_dates[${poolName}][${checkedTeams[i].index}][${checkedTeams[j].index}]"></td>
                    </tr>`;
                }
            }
        }
        html += `</table>`;
        container.innerHTML += html;
    });
}
document.addEventListener('change', function(e){
    if(e.target.matches('.pool input[type="checkbox"]')){
        updateExclusiveTeams();
        generateMatches();
    }
});
</script>
</body>
</html>