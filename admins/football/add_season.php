<?php
session_start();
require_once '../../includes/db.php'; // Connexion PDO

$academicYears = $pdo->query("SELECT id, label FROM academic_years ORDER BY label DESC")->fetchAll(PDO::FETCH_ASSOC);

// Liste des équipes déjà créées
$teamsList = $pdo->query("SELECT team_id, name FROM football_teams ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

$success = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_season'])) {
    try {
        $pdo->beginTransaction();

        // Créer la saison
        $stmt = $pdo->prepare("INSERT INTO football_seasons (label, academic_year_id, is_active, created_by) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $_POST['season_label'],
            $_POST['academic_year_id'],
            isset($_POST['is_active']) ? 1 : 0,
            $_SESSION['admin_id'] ?? null
        ]);
        $seasonId = $pdo->lastInsertId();

        // Ajouter les équipes
        $teamIds = [];
        if(!empty($_POST['teams'])){
            foreach($_POST['teams'] as $team){
                $stmt = $pdo->prepare("INSERT INTO football_teams (season_id, name, coach, created_by) VALUES (?, ?, ?, ?)");
                $stmt->execute([$seasonId, $team['name'], $team['coach'], $_SESSION['admin_id'] ?? null]);
                $teamIds[] = $pdo->lastInsertId();
            }
        }

        // Ajouter les poules et assigner les équipes
        $poolMap = [];
        if(!empty($_POST['pools'])){
            foreach($_POST['pools'] as $poolName => $advanceCount){
                $stmt = $pdo->prepare("INSERT INTO pools (season_id, name, advance_count) VALUES (?, ?, ?)");
                $stmt->execute([$seasonId, $poolName, $_POST['advance_count'][$poolName]]);
                $poolId = $pdo->lastInsertId();
                $poolMap[$poolName] = $poolId;

                if(!empty($_POST['pool_teams'][$poolName])){
                    foreach($_POST['pool_teams'][$poolName] as $teamIndex){
                        $teamId = $teamIds[$teamIndex] ?? null;
                        if($teamId){
                            $stmt = $pdo->prepare("INSERT INTO pool_teams (pool_id, team_id, season_id) VALUES (?, ?, ?)");
                            $stmt->execute([$poolId, $teamId, $seasonId]);
                        }
                    }
                }
            }
        }

        // Enregistrer le type de match
        $matchType = $_POST['match_type'];
        $stmt = $pdo->prepare("INSERT INTO season_settings (season_id, match_type) VALUES (?, ?)");
        $stmt->execute([$seasonId, $matchType]);

        // Ajouter les matchs
        foreach($_POST['pools'] as $poolName => $_){
            $poolId = $poolMap[$poolName];
            $teamsInPool = $_POST['pool_teams'][$poolName] ?? [];
            $matchesCount = 1;
            if($matchType === 'aller_retour' || ($matchType === 'custom' && !empty($_POST['custom_pools'][$poolName]))){
                $matchesCount = 2;
            }
            for($i=0; $i<count($teamsInPool); $i++){
                for($j=$i+1; $j<count($teamsInPool); $j++){
                    for($k=0; $k<$matchesCount; $k++){
                        $team1 = $teamIds[$teamsInPool[$i]];
                        $team2 = $teamIds[$teamsInPool[$j]];
                        $date = $_POST['match_dates'][$poolName][$teamsInPool[$i]][$teamsInPool[$j]] ?? null;
                        $stmt = $pdo->prepare("INSERT INTO matches (season_id, pool_id, team1_id, team2_id, stage, match_datetime) VALUES (?, ?, ?, ?, 'group', ?)");
                        $stmt->execute([$seasonId, $poolId, $team1, $team2, $date]);
                    }
                }
            }
        }

        $pdo->commit();
        $success = "Saison créée avec succès !";

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Erreur : " . $e->getMessage();
    }
}

ob_start();
?>

<style>
    /* ==========================================================================
       VARIABLES ET RESET D'INTEGRATION PREMIUM
       ========================================================================== */
    :root {
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

        --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.1);
        --shadow-lg: 0 10px 25px rgba(0, 0, 0, 0.2);

        --radius-sm: 4px;
        --radius-md: 8px;
        --radius-lg: 12px;
        --radius-full: 9999px;
    }

    .season-builder {
        font-family: var(--font-primary);
        color: var(--gray-100);
        max-width: 1100px;
        margin: 0 auto;
        padding: var(--space-4) var(--space-3);
        box-sizing: border-box;
    }

    /* En-tête de page */
    .header-block {
        margin-bottom: var(--space-6);
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        padding-bottom: var(--space-4);
    }

    .header-block h1 {
        font-size: 1.85rem;
        font-weight: 700;
        color: var(--white);
        margin: 0 0 var(--space-2) 0;
        letter-spacing: -0.02em;
    }

    .header-block p {
        color: var(--gray-400);
        font-size: var(--font-size-sm);
        margin: 0;
    }

    /* Bannières de notifications */
    .toast-msg {
        padding: var(--space-4);
        border-radius: var(--radius-md);
        margin-bottom: var(--space-5);
        font-size: var(--font-size-sm);
        display: flex;
        align-items: center;
        gap: var(--space-3);
        font-weight: 500;
    }
    .toast-success {
        background-color: rgba(16, 172, 132, 0.12);
        border: 1px solid rgba(16, 172, 132, 0.25);
        color: var(--accent-green);
    }
    .toast-error {
        background-color: rgba(255, 71, 87, 0.12);
        border: 1px solid rgba(255, 71, 87, 0.25);
        color: var(--accent-red);
    }

    /* Sections Etape par Etape Card Design */
    .step-section {
        background-color: var(--primary-800);
        border: 1px solid rgba(255, 255, 255, 0.04);
        border-radius: var(--radius-lg);
        padding: var(--space-5);
        margin-bottom: var(--space-5);
        box-shadow: var(--shadow-md);
        transition: transform var(--transition-fast);
    }

    .step-section:hover {
        border-color: rgba(255, 255, 255, 0.08);
    }

    .step-header {
        display: flex;
        align-items: center;
        gap: var(--space-3);
        margin-bottom: var(--space-5);
        padding-bottom: var(--space-3);
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    }

    .step-badge {
        background-color: var(--primary-600);
        color: var(--accent-blue);
        width: 28px;
        height: 28px;
        border-radius: var(--radius-full);
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: var(--font-size-sm);
        border: 1px solid rgba(46, 134, 222, 0.3);
    }

    .step-header h3 {
        font-size: var(--font-size-lg);
        font-weight: 600;
        color: var(--white);
        margin: 0;
    }

    /* Formulaire Layout Elements */
    .inputs-row {
        display: grid;
        grid-template-columns: 1fr;
        gap: var(--space-4);
        margin-bottom: var(--space-3);
    }

    @media(min-width: 768px) {
        .inputs-row-3col { grid-template-columns: 2fr 2fr 1fr; }
        .inputs-row-2col { grid-template-columns: 1fr 1fr; }
    }

    .field-group {
        display: flex;
        flex-direction: column;
        gap: var(--space-2);
    }

    .field-group label {
        font-size: var(--font-size-sm);
        color: var(--gray-300);
        font-weight: 500;
    }

    /* Form Controls Unifiés */
    input[type="text"],
    input[type="number"],
    input[type="date"],
    select {
        width: 100%;
        box-sizing: border-box;
        font-family: inherit;
        font-size: var(--font-size-sm);
        color: var(--white);
        background-color: var(--primary-700);
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: var(--radius-md);
        padding: 0.75rem var(--space-4);
        outline: none;
        transition: all var(--transition-fast) ease;
    }

    input:focus,
    select:focus {
        border-color: var(--accent-blue);
        background-color: var(--primary-600);
        box-shadow: 0 0 0 3px rgba(46, 134, 222, 0.15);
    }

    select {
        appearance: none;
        cursor: pointer;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%23a4b0be' viewBox='0 0 16 16'%3E%3Cpath fill-rule='evenodd' d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 1rem center;
        background-size: 11px;
        padding-right: 2.5rem;
    }

    /* Switch Option (Checkbox stylisé) */
    .toggle-control {
        display: inline-flex;
        align-items: center;
        gap: var(--space-3);
        cursor: pointer;
        margin-top: var(--space-2);
    }

    .toggle-box {
        position: relative;
        width: 44px;
        height: 24px;
        background-color: var(--primary-700);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: var(--radius-full);
        transition: background-color var(--transition-base);
    }

    .toggle-box::before {
        content: "";
        position: absolute;
        width: 16px;
        height: 16px;
        border-radius: 50%;
        background-color: var(--gray-300);
        left: 3px;
        bottom: 3px;
        transition: transform var(--transition-base), background-color var(--transition-base);
    }

    .toggle-control input { opacity: 0; width: 0; height: 0; }

    .toggle-control input:checked + .toggle-box {
        background-color: var(--accent-green);
        border-color: transparent;
    }

    .toggle-control input:checked + .toggle-box::before {
        transform: translateX(20px);
        background-color: var(--white);
    }

    /* Boutons et Triggers */
    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-family: inherit;
        font-size: var(--font-size-sm);
        font-weight: 600;
        padding: 0.7rem 1.25rem;
        border-radius: var(--radius-md);
        border: none;
        cursor: pointer;
        transition: all var(--transition-fast) ease;
        gap: var(--space-2);
    }

    .btn-add {
        background-color: rgba(46, 134, 222, 0.1);
        color: var(--accent-blue);
        border: 1px dashed rgba(46, 134, 222, 0.4);
        margin-top: var(--space-2);
    }

    .btn-add:hover {
        background-color: var(--accent-blue);
        color: var(--white);
        border-color: transparent;
    }

    .btn-danger {
        background-color: rgba(255, 71, 87, 0.1);
        color: var(--accent-red);
        border: 1px solid rgba(255, 71, 87, 0.2);
    }

    .btn-danger:hover {
        background-color: var(--accent-red);
        color: var(--white);
        border-color: transparent;
    }

    .btn-submit-all {
        background-color: var(--accent-blue);
        color: var(--white);
        font-size: var(--font-size-md);
        padding: 0.9rem var(--space-6);
        box-shadow: var(--shadow-md);
        width: 100%;
    }

    .btn-submit-all:hover {
        background-color: #4834d4;
        transform: translateY(-1px);
        box-shadow: var(--shadow-lg);
    }

    /* Listes d'entrées et Cartes dynamiques */
    .dynamic-list {
        display: flex;
        flex-direction: column;
        gap: var(--space-3);
        margin-bottom: var(--space-4);
    }

    .pool-card {
        background-color: var(--primary-700);
        border: 1px solid rgba(255, 255, 255, 0.05);
        border-radius: var(--radius-md);
        padding: var(--space-4);
        margin-bottom: var(--space-3);
    }

    .pool-card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: var(--space-3);
        margin-bottom: var(--space-4);
        padding-bottom: var(--space-2);
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    }

    /* Grille de répartition des équipes */
    .checkbox-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: var(--space-3);
        background-color: var(--primary-800);
        padding: var(--space-3);
        border-radius: var(--radius-md);
        border: 1px solid rgba(255, 255, 255, 0.03);
    }

    .checkbox-item {
        display: flex;
        align-items: center;
        gap: var(--space-2);
        padding: var(--space-2) var(--space-3);
        background-color: var(--primary-700);
        border-radius: var(--radius-sm);
        cursor: pointer;
        font-size: var(--font-size-sm);
        border: 1px solid transparent;
        transition: all var(--transition-fast);
    }

    .checkbox-item:hover:not(.disabled) {
        border-color: rgba(255,255,255,0.1);
    }

    .checkbox-item.disabled {
        opacity: 0.3;
        cursor: not-allowed;
    }

    /* Radio types de Match */
    .radio-group {
        display: flex;
        flex-direction: column;
        gap: var(--space-3);
    }

    .radio-item {
        display: flex;
        align-items: center;
        gap: var(--space-3);
        padding: var(--space-3) var(--space-4);
        background-color: var(--primary-700);
        border-radius: var(--radius-md);
        cursor: pointer;
        border: 1px solid rgba(255, 255, 255, 0.04);
        transition: border var(--transition-fast);
    }

    .radio-item:hover { border-color: rgba(46, 134, 222, 0.3); }

    /* Tables de Matchs générées */
    .pool-match-title {
        font-size: var(--font-size-md);
        color: var(--white);
        font-weight: 600;
        margin: var(--space-5) 0 var(--space-3) 0;
        display: flex;
        align-items: center;
        gap: var(--space-2);
    }

    .table-responsive-box {
        width: 100%;
        overflow-x: auto;
        border-radius: var(--radius-md);
        border: 1px solid rgba(255,255,255,0.05);
        background-color: var(--primary-700);
    }

    table {
        width: 100%;
        border-collapse: collapse;
        text-align: left;
        font-size: var(--font-size-sm);
    }

    th {
        background-color: var(--primary-600);
        color: var(--white);
        font-weight: 600;
        padding: var(--space-3) var(--space-4);
    }

    td {
        padding: var(--space-3) var(--space-4);
        border-bottom: 1px solid rgba(255,255,255,0.04);
        color: var(--gray-200);
    }

    tr:last-child td { border-bottom: none; }
</style>

<div class="season-builder">

    <header class="header-block">
        <h1>Configuration de Saison</h1>
        <p>Générez pas à pas une nouvelle saison sportive, incluant les équipes, la structure des poules et le calendrier.</p>
    </header>

    <?php if($success): ?>
        <div class="toast-msg toast-success">
            <svg width="18" height="18" viewBox="0 0 16 16" fill="currentColor"><path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zm-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z"/></svg>
            <span><?= htmlspecialchars($success) ?></span>
        </div>
    <?php endif; ?>

    <?php if($error): ?>
        <div class="toast-msg toast-error">
            <svg width="18" height="18" viewBox="0 0 16 16" fill="currentColor"><path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zM8 4a.905.905 0 0 0-.9.995l.35 3.507a.552.552 0 0 0 1.1 0l.35-3.507A.905.905 0 0 0 8 4zm.002 6a1 1 0 1 0 0 2 1 1 0 0 0 0-2z"/></svg>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>

    <form method="post" id="seasonForm">

        <!-- ÉTAPE 1: Logistique Saison -->
        <section class="step-section">
            <div class="step-header">
                <div class="step-badge">1</div>
                <h3>Nouvelle Saison</h3>
            </div>
            <div class="inputs-row inputs-row-2col">
                <div class="field-group">
                    <label for="season_label">Nom / Label de la Saison</label>
                    <input type="text" id="season_label" name="season_label" placeholder="Ex: Championnat d'Automne 2026" required>
                </div>
                <div class="field-group">
                    <label for="academic_year_id">Année Académique</label>
                    <select id="academic_year_id" name="academic_year_id" required>
                        <?php foreach($academicYears as $year): ?>
                            <option value="<?= $year['id'] ?>"><?= htmlspecialchars($year['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="field-group">
                <label class="toggle-control">
                    <input type="checkbox" name="is_active" checked>
                    <div class="toggle-box"></div>
                    <span class="switch-label-text">Définir comme saison active par défaut</span>
                </label>
            </div>
        </section>

        <!-- ÉTAPE 2: Équipes -->
        <section class="step-section">
            <div class="step-header">
                <div class="step-badge">2</div>
                <h3>Enregistrement des Équipes</h3>
            </div>
            <div id="teams-container" class="dynamic-list">
                <div class="inputs-row inputs-row-3col" style="align-items: flex-end;">
                    <div class="field-group">
                        <label>Nom de l'équipe</label>
                        <input type="text" class="team-name" name="teams[0][name]" placeholder="Ex: FC Dragons" required oninput="updatePoolTeamLists()">
                    </div>
                    <div class="field-group">
                        <label>Nom du Coach</label>
                        <input type="text" name="teams[0][coach]" placeholder="Ex: Prof. Traoré" required>
                    </div>
                    <button type="button" class="btn btn-danger" style="height: 42px;" onclick="removeEntry(this)">Supprimer</button>
                </div>
            </div>
            <button type="button" class="btn btn-add" onclick="addTeam()">+ Ajouter une équipe</button>
        </section>

        <!-- ÉTAPE 3: Poules -->
        <section class="step-section">
            <div class="step-header">
                <div class="step-badge">3</div>
                <h3>Création des Poules & Répartition</h3>
            </div>
            <div id="pools-container"></div>
            <button type="button" class="btn btn-add" onclick="addPool()">+ Configurer une poule</button>
        </section>

        <!-- ÉTAPE 4: Mode de Match -->
        <section class="step-section">
            <div class="step-header">
                <div class="step-badge">4</div>
                <h3>Format des Confrontations</h3>
            </div>
            <div class="radio-group">
                <label class="radio-item">
                    <input type="radio" name="match_type" value="simple" checked onchange="toggleCustom(false)">
                    <span>Match simple (Une seule rencontre sur terrain neutre ou défini)</span>
                </label>
                <label class="radio-item">
                    <input type="radio" name="match_type" value="aller_retour" onchange="toggleCustom(false)">
                    <span>Aller - Retour (Double confrontation systématique)</span>
                </label>
                <label class="radio-item">
                    <input type="radio" name="match_type" value="custom" onchange="toggleCustom(true)">
                    <span>Format personnalisé (Aller-Retour sélectif selon la poule)</span>
                </label>
            </div>
            <div id="custom-options" style="display:none; margin-top: var(--space-4); background: var(--primary-700); padding: var(--space-4); border-radius: var(--radius-md);"></div>
        </section>

        <!-- ÉTAPE 5: Calendrier Dynamique -->
        <section class="step-section">
            <div class="step-header">
                <div class="step-badge">5</div>
                <h3>Calendrier Prévisionnel des Matchs</h3>
            </div>
            <div id="matches-container">
                <p style="color: var(--gray-400); font-size: var(--font-size-sm); margin: 0; text-align: center; padding: var(--space-4) 0;">
                    Assignez des équipes à vos poules pour générer automatiquement les rencontres.
                </p>
            </div>
        </section>

        <!-- Validation Générale -->
        <div style="margin-top: var(--space-5);">
            <button type="submit" name="save_season" class="btn btn-submit-all">Créer et initialiser toute la saison</button>
        </div>
    </form>
</div>

<script>
let teamIndex = 1;
let poolIndex = 65; // Lettre 'A'

function addTeam() {
    const container = document.getElementById('teams-container');
    const div = document.createElement('div');
    div.className = 'inputs-row inputs-row-3col';
    div.style.alignItems = 'flex-end';
    div.innerHTML = `
        <div class="field-group">
            <input type="text" class="team-name" name="teams[${teamIndex}][name]" placeholder="Ex: FC Dragons" required oninput="updatePoolTeamLists()">
        </div>
        <div class="field-group">
            <input type="text" name="teams[${teamIndex}][coach]" placeholder="Ex: Prof. Traoré" required>
        </div>
        <button type="button" class="btn btn-danger" style="height: 42px;" onclick="removeEntry(this)">Supprimer</button>
    `;
    container.appendChild(div);
    teamIndex++;
    updatePoolTeamLists();
}

function removeEntry(btn) {
    btn.parentElement.remove();
    updatePoolTeamLists();
}

function getCurrentTeams() {
    const names = [];
    document.querySelectorAll('#teams-container .team-name').forEach((input, idx) => {
        if(input.value.trim() !== '') {
            names.push({name: input.value.trim(), index: idx});
        }
    });
    return names;
}

function addPool() {
    const poolName = String.fromCharCode(poolIndex);
    const container = document.getElementById('pools-container');
    const div = document.createElement('div');
    div.className = "pool-card";
    div.id = "pool_" + poolName;

    div.innerHTML = `
        <div class="pool-card-header">
            <div class="inputs-row" style="grid-template-columns: 120px 180px; margin-bottom: 0; align-items: center;">
                <div class="field-group">
                    <input type="text" name="pools[${poolName}]" value="${poolName}" required style="font-weight: bold; text-align: center;">
                </div>
                <div class="field-group" style="flex-direction: row; align-items: center; gap: 8px;">
                    <label style="white-space:nowrap; font-size:12px;">Qualifiés :</label>
                    <input type="number" name="advance_count[${poolName}]" value="2" min="1" style="padding: 0.5rem;">
                </div>
            </div>
            <button type="button" class="btn btn-danger" style="padding: 0.5rem 1rem;" onclick="removeEntry(this)">Retirer Poule</button>
        </div>
        <div style="margin-bottom: 8px; font-size: 13px; color: var(--gray-300); font-weight: 500;">Sélectionner les équipes de la Poule :</div>
        <div class="checkbox-grid pool-team-list"></div>
    `;
    container.appendChild(div);
    updatePoolTeamLists();
    poolIndex++;
    
    // Déclencher la mise à jour des options personnalisées si requis
    const isCustom = document.querySelector('input[name="match_type"]:checked').value === 'custom';
    if(isCustom) toggleCustom(true);
}

function updatePoolTeamLists() {
    const teams = getCurrentTeams();
    document.querySelectorAll('.pool-card .pool-team-list').forEach(poolDiv => {
        const poolId = poolDiv.parentElement.id.replace('pool_', '');
        
        // Sauvegarder l'état coché avant reconstruction
        const checkedIndexes = new Set();
        poolDiv.querySelectorAll('input[type="checkbox"]:checked').forEach(cb => checkedIndexes.add(cb.value));

        poolDiv.innerHTML = '';
        teams.forEach(team => {
            const isChecked = checkedIndexes.has(team.index.toString()) ? 'checked' : '';
            poolDiv.innerHTML += `
                <label class="checkbox-item">
                    <input type="checkbox" name="pool_teams[${poolId}][]" value="${team.index}" ${isChecked}> 
                    <span>${team.name}</span>
                </label>
            `;
        });
    });
    updateExclusiveTeams();
    generateMatches();
}

function updateExclusiveTeams() {
    const allCheckboxes = document.querySelectorAll('.pool-card input[type="checkbox"]');
    const selectedTeams = new Set();
    
    allCheckboxes.forEach(cb => { 
        if(cb.checked) selectedTeams.add(cb.value); 
        cb.closest('.checkbox-item').classList.remove('disabled');
        cb.disabled = false;
    });
    
    allCheckboxes.forEach(cb => { 
        if(!cb.checked && selectedTeams.has(cb.value)) {
            cb.disabled = true;
            cb.closest('.checkbox-item').classList.add('disabled');
        } 
    });
}

function toggleCustom(show) {
    const container = document.getElementById('custom-options');
    container.style.display = show ? 'block' : 'none';
    
    if(show) {
        let html = '<h4 style="margin: 0 0 12px 0; font-size:14px; color: var(--white);">Poules appliquant un format Aller-Retour :</h4>';
        document.querySelectorAll('.pool-card').forEach(pool => {
            const poolName = pool.id.replace('pool_', '');
            const checkedAttr = document.querySelector(`input[name="custom_pools[${poolName}]"]`)?.checked ? 'checked' : '';
            html += `
                <label class="toggle-control" style="margin-right: 20px;">
                    <input type="checkbox" name="custom_pools[${poolName}]" value="1" ${checkedAttr} onchange="generateMatches()">
                    <div class="toggle-box"></div>
                    <span class="switch-label-text">Poule ${poolName}</span>
                </label>
            `;
        });
        container.innerHTML = html;
    }
    generateMatches();
}

function generateMatches() {
    const container = document.getElementById('matches-container');
    const matchType = document.querySelector('input[name="match_type"]:checked').value;
    const pools = document.querySelectorAll('.pool-card');
    
    if(pools.length === 0) {
        container.innerHTML = '<p style="color: var(--gray-400); text-align: center; margin:0; padding:15px; font-size:14px;">Aucune poule créée pour le moment.</p>';
        return;
    }

    let hasMatches = false;
    let fullHtml = '';

    pools.forEach(pool => {
        const poolName = pool.id.replace('pool_', '');
        const checkedTeams = Array.from(pool.querySelectorAll('input[type="checkbox"]:checked')).map(cb => ({
            name: cb.closest('.checkbox-item').querySelector('span').textContent.trim(),
            index: cb.value
        }));

        if(checkedTeams.length < 2) return;
        hasMatches = true;

        let matchesCount = 1;
        if(matchType === 'aller_retour') {
            matchesCount = 2;
        } else if(matchType === 'custom') {
            const isCustomChecked = document.querySelector(`input[name="custom_pools[${poolName}]"]`)?.checked;
            if(isCustomChecked) matchesCount = 2;
        }

        let html = `<div class="pool-match-title">
            <svg width="6" height="12" viewBox="0 0 6 12" fill="var(--accent-blue)"><circle cx="3" cy="6" r="3"/></svg>
            Rencontres Poule ${poolName}
        </div>`;
        html += `<div class="table-responsive-box"><table><tr><th>Équipe Dom.</th><th>Équipe Ext.</th><th style="width: 240px;">Date & Heure du Match</th></tr>`;
        
        for(let i=0; i<checkedTeams.length; i++){
            for(let j=i+1; j<checkedTeams.length; j++){
                for(let k=0; k<matchesCount; k++){
                    html += `<tr>
                        <td style="font-weight:600; color:var(--white);">${checkedTeams[i].name}</td>
                        <td style="font-weight:600; color:var(--white);">${checkedTeams[j].name}</td>
                        <td><input type="date" name="match_dates[${poolName}][${checkedTeams[i].index}][${checkedTeams[j].index}]"></td>
                    </tr>`;
                }
            }
        }
        html += `</table></div>`;
        fullHtml += html;
    });

    if(!hasMatches) {
        container.innerHTML = '<p style="color: var(--gray-400); text-align: center; margin:0; padding:15px; font-size:14px;">Cochez au moins 2 équipes dans une poule pour éditer le calendrier.</p>';
    } else {
        container.innerHTML = fullHtml;
    }
}

// Gestion globale et unique des écouteurs événementiels délégués
document.addEventListener('change', function(e){
    if(e.target.closest('.pool-card input[type="checkbox"]')){
        updateExclusiveTeams();
        generateMatches();
    }
});

// Initialisation au chargement
updatePoolTeamLists();
</script>

<?php
$content = ob_get_clean();
include '../layout.php';
?>