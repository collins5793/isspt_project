<?php
session_start();
require_once '../../includes/db.php';

$id_admin = $_SESSION['admin_id'] ?? null;
if (!$id_admin) die("Accès refusé");

// Récupérer l'ID du match
$match_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$match_id) die("Match non spécifié.");

// Charger le match
$stmt = $pdo->prepare("SELECT * FROM matches WHERE match_id = ?");
$stmt->execute([$match_id]);
$match = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$match) die("Match introuvable.");

// Charger saisons, pools et équipes
$seasons = $pdo->query("SELECT * FROM football_seasons ORDER BY is_active DESC, label ASC")->fetchAll(PDO::FETCH_ASSOC);
$pools = $pdo->prepare("SELECT * FROM pools WHERE season_id = ? ORDER BY name ASC");
$pools->execute([$match['season_id']]);
$pools = $pools->fetchAll(PDO::FETCH_ASSOC);

$teams = $pdo->prepare("SELECT * FROM football_teams WHERE season_id = ? ORDER BY name ASC");
$teams->execute([$match['season_id']]);
$teams = $teams->fetchAll(PDO::FETCH_ASSOC);

// Charger joueurs pour chaque équipe avec nom depuis etudiants
$team1_players_stmt = $pdo->prepare("
    SELECT tp.player_id, 
           COALESCE(CONCAT(e.nom, ' ', e.prenom), tp.full_name) AS full_name
    FROM team_players tp
    LEFT JOIN etudiants e ON tp.user_id = e.id_etudiant
    WHERE tp.team_id = ? AND tp.season_id = ?
    ORDER BY full_name ASC
");
$team1_players_stmt->execute([$match['team1_id'], $match['season_id']]);
$team1_players = $team1_players_stmt->fetchAll(PDO::FETCH_ASSOC);

$team2_players_stmt = $pdo->prepare("
    SELECT tp.player_id, 
           COALESCE(CONCAT(e.nom, ' ', e.prenom), tp.full_name) AS full_name
    FROM team_players tp
    LEFT JOIN etudiants e ON tp.user_id = e.id_etudiant
    WHERE tp.team_id = ? AND tp.season_id = ?
    ORDER BY full_name ASC
");
$team2_players_stmt->execute([$match['team2_id'], $match['season_id']]);
$team2_players = $team2_players_stmt->fetchAll(PDO::FETCH_ASSOC);

// Charger les buts existants
$goals_stmt = $pdo->prepare("SELECT * FROM match_goals WHERE match_id = ?");
$goals_stmt->execute([$match_id]);
$existing_goals = $goals_stmt->fetchAll(PDO::FETCH_ASSOC);

// Organiser le total des buts par ID de joueur
$team1_goals = [];
$team2_goals = [];
foreach ($existing_goals as $g) {
    if ($g['team_id'] == $match['team1_id']) {
        $team1_goals[$g['player_id']] = ($team1_goals[$g['player_id']] ?? 0) + 1;
    } else {
        $team2_goals[$g['player_id']] = ($team2_goals[$g['player_id']] ?? 0) + 1;
    }
}

// Traitement POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $team1_id = $_POST['team1_id'];
    $team2_id = $_POST['team2_id'];
    $pool_id = $_POST['pool_id'] ?: null;
    $match_datetime = $_POST['match_datetime'] ?: null;
    $location = $_POST['location'] ?: null;
    $score_team1 = $_POST['score_team1'] !== "" ? intval($_POST['score_team1']) : null;
    $score_team2 = $_POST['score_team2'] !== "" ? intval($_POST['score_team2']) : null;
    $is_played = isset($_POST['is_played']) ? 1 : 0;

    // Déterminer le gagnant
    $winner_team_id = null;
    if ($score_team1 !== null && $score_team2 !== null) {
        if ($score_team1 > $score_team2) $winner_team_id = $team1_id;
        elseif ($score_team2 > $score_team1) $winner_team_id = $team2_id;
    }

    // Historique des scores
    $pdo->prepare("INSERT INTO match_results_history 
        (match_id, old_score_team1, old_score_team2, new_score_team1, new_score_team2, changed_by)
        VALUES (?, ?, ?, ?, ?, ?)")
        ->execute([$match_id, $match['score_team1'], $match['score_team2'], $score_team1, $score_team2, $id_admin]);

    // Mise à jour du match
    $pdo->prepare("UPDATE matches SET team1_id=?, team2_id=?, pool_id=?, match_datetime=?, location=?, score_team1=?, score_team2=?, winner_team_id=?, is_played=?, updated_at=NOW() WHERE match_id=?")
        ->execute([$team1_id, $team2_id, $pool_id, $match_datetime, $location, $score_team1, $score_team2, $winner_team_id, $is_played, $match_id]);

    // Supprimer anciens buts
    $pdo->prepare("DELETE FROM match_goals WHERE match_id=?")->execute([$match_id]);

    // Ajouter nouveaux buts pour l'équipe 1
    foreach ($_POST['team1_goal'] ?? [] as $player_id => $nb) {
        $nb = intval($nb);
        for ($i=0; $i<$nb; $i++) {
            $pdo->prepare("INSERT INTO match_goals (match_id, season_id, team_id, player_id) VALUES (?, ?, ?, ?)")
                ->execute([$match_id, $match['season_id'], $team1_id, $player_id]);
        }
    }
    // Ajouter nouveaux buts pour l'équipe 2
    foreach ($_POST['team2_goal'] ?? [] as $player_id => $nb) {
        $nb = intval($nb);
        for ($i=0; $i<$nb; $i++) {
            $pdo->prepare("INSERT INTO match_goals (match_id, season_id, team_id, player_id) VALUES (?, ?, ?, ?)")
                ->execute([$match_id, $match['season_id'], $team2_id, $player_id]);
        }
    }

    header("Location: matches.php?success=1");
    exit;
}

ob_start();
?>

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

        /* Sidebar & Layout */
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
        
        /* Shadows & Corners */
        --shadow-lg: 0 10px 25px rgba(0, 0, 0, 0.2);
        --radius-md: 8px;
        --radius-lg: 12px;
        --radius-full: 9999px;

        --z-sidebar: 1000;
        --z-overlay: 999;
        --z-mobile-toggle: 1001;
    }

    /* --- Base Wrapper --- */
    .dashboard-match-container {
        font-family: var(--font-primary);
        color: var(--gray-100);
        max-width: 1100px;
        margin: 0 auto;
        padding: var(--space-4) var(--space-3);
    }

    /* --- Custom Headings & Labels --- */
    .dashboard-main-title {
        display: flex;
        align-items: center;
        gap: var(--space-3);
        font-size: var(--font-size-xl);
        font-weight: 600;
        color: var(--white);
        margin-bottom: var(--space-5);
        padding-bottom: var(--space-3);
        border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    }

    .dashboard-main-title i {
        color: var(--accent-blue);
    }

    .section-subtitle {
        display: flex;
        align-items: center;
        gap: var(--space-2);
        font-size: var(--font-size-md);
        font-weight: 600;
        color: var(--white);
        margin: var(--space-4) 0 var(--space-3) 0;
    }

    /* --- Glassmorphism Form Card --- */
    .glass-form-card {
        background: rgba(18, 12, 58, 0.3);
        border: 1px solid rgba(255, 255, 255, 0.06);
        border-radius: var(--radius-lg);
        padding: var(--space-5);
        box-shadow: var(--shadow-lg);
        backdrop-filter: blur(10px);
    }

    /* --- Responsive Grids --- */
    .match-details-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: var(--space-4);
        margin-bottom: var(--space-5);
    }

    .score-status-grid {
        display: grid;
        grid-template-columns: 2fr 2fr 1.5fr;
        gap: var(--space-4);
        align-items: flex-end;
        background: rgba(255, 255, 255, 0.02);
        padding: var(--space-4);
        border-radius: var(--radius-md);
        border: 1px solid rgba(255, 255, 255, 0.04);
        margin-bottom: var(--space-5);
    }

    .scorers-section-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: var(--space-5);
        margin-top: var(--space-4);
    }

    .scorer-team-column {
        background: rgba(8, 0, 32, 0.4);
        border: 1px solid rgba(255, 255, 255, 0.04);
        border-radius: var(--radius-md);
        padding: var(--space-4);
    }

    /* --- Form Controls Custom UX --- */
    .input-field-group {
        display: flex;
        flex-direction: column;
        gap: var(--space-2);
    }

    .input-field-group label {
        font-size: var(--font-size-sm);
        font-weight: 600;
        color: var(--gray-300);
    }

    .custom-form-control {
        width: 100%;
        background-color: var(--primary-700);
        color: var(--white);
        border: 1px solid var(--primary-600);
        border-radius: var(--radius-md);
        padding: var(--space-3);
        font-family: var(--font-primary);
        font-size: var(--font-size-sm);
        outline: none;
        box-sizing: border-box;
        transition: border-color var(--transition-fast), box-shadow var(--transition-fast);
    }

    .custom-form-control:focus {
        border-color: var(--accent-blue);
        box-shadow: 0 0 0 3px rgba(46, 134, 222, 0.25);
    }

    /* Select design arrow cleanup */
    select.custom-form-control {
        appearance: none;
        background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='24' height='24' fill='%23a4b0be'><path d='M7 10l5 5 5-5z'/></svg>");
        background-repeat: no-repeat;
        background-position: right var(--space-2) center;
        background-size: 1.2rem;
        padding-right: var(--space-5);
    }

    select.custom-form-control option {
        background-color: var(--primary-800);
        color: var(--white);
    }

    /* --- Scorer Row Elements --- */
    .scorer-input-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: var(--space-3);
        padding: var(--space-2) 0;
        border-bottom: 1px solid rgba(255, 255, 255, 0.04);
    }

    .scorer-input-row:last-child {
        border-bottom: none;
    }

    .scorer-name {
        font-size: var(--font-size-sm);
        color: var(--gray-200);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 70%;
    }

    .scorer-count-input {
        width: 75px;
        text-align: center;
        padding: var(--space-2);
    }

    /* --- Premium Switch Toggle --- */
    .custom-toggle-box {
        display: flex;
        align-items: center;
        gap: var(--space-3);
        padding: var(--space-3) var(--space-4);
        background: rgba(255, 255, 255, 0.02);
        border: 1px solid rgba(255, 255, 255, 0.04);
        border-radius: var(--radius-md);
        height: 45px;
        box-sizing: border-box;
    }

    .toggle-switch {
        position: relative;
        display: inline-block;
        width: 42px;
        height: 22px;
        flex-shrink: 0;
    }

    .toggle-switch input {
        opacity: 0; width: 0; height: 0;
    }

    .toggle-slider {
        position: absolute;
        cursor: pointer;
        top: 0; left: 0; right: 0; bottom: 0;
        background-color: var(--primary-600);
        transition: 250ms;
        border-radius: var(--radius-full);
    }

    .toggle-slider:before {
        position: absolute;
        content: "";
        height: 14px;
        width: 14px;
        left: 4px;
        bottom: 4px;
        background-color: var(--white);
        transition: 250ms;
        border-radius: 50%;
    }

    input:checked + .toggle-slider {
        background-color: var(--accent-green);
    }

    input:checked + .toggle-slider:before {
        transform: translateX(20px);
    }

    .toggle-label-text {
        font-size: var(--font-size-sm);
        font-weight: 600;
        color: var(--gray-200);
        cursor: pointer;
        user-select: none;
    }

    /* --- Form Footer Actions --- */
    .form-footer-actions {
        display: flex;
        justify-content: flex-end;
        align-items: center;
        gap: var(--space-3);
        margin-top: var(--space-5);
        padding-top: var(--space-4);
        border-top: 1px solid rgba(255, 255, 255, 0.06);
    }

    .btn-dashboard {
        display: inline-flex;
        align-items: center;
        gap: var(--space-2);
        font-family: var(--font-primary);
        font-size: var(--font-size-sm);
        font-weight: 600;
        padding: var(--space-3) var(--space-5);
        border-radius: var(--radius-md);
        text-decoration: none;
        cursor: pointer;
        border: none;
        transition: all var(--transition-fast);
    }

    .btn-dashboard-save {
        background-color: var(--accent-blue);
        color: var(--white);
    }

    .btn-dashboard-save:hover {
        background-color: #54a0ff;
        transform: translateY(-1px);
    }

    .btn-dashboard-cancel {
        background-color: transparent;
        color: var(--gray-400);
        border: 1px solid var(--primary-600);
    }

    .btn-dashboard-cancel:hover {
        background-color: rgba(255, 255, 255, 0.02);
        color: var(--white);
        border-color: var(--gray-300);
    }

    /* Divider */
    .form-section-divider {
        border: 0;
        height: 1px;
        background: linear-gradient(90deg, rgba(255,255,255,0.08) 0%, rgba(255,255,255,0.02) 100%);
        margin: var(--space-5) 0;
    }

    /* --- Screen Responsiveness Media Queries --- */
    @media (max-width: 992px) {
        .scorers-section-grid {
            grid-template-columns: 1fr;
            gap: var(--space-4);
        }
    }

    @media (max-width: 768px) {
        .match-details-grid {
            grid-template-columns: 1fr;
            gap: var(--space-3);
        }

        .score-status-grid {
            grid-template-columns: 1fr;
            gap: var(--space-3);
        }

        .custom-toggle-box {
            height: auto;
            justify-content: flex-start;
        }

        .form-footer-actions {
            flex-direction: column-reverse;
            width: 100%;
        }

        .btn-dashboard {
            width: 100%;
            justify-content: center;
        }
    }
</style>

<div class="dashboard-match-container">

    <!-- En-tête de la page -->
    <h2 class="dashboard-main-title">
        <i class="fa-solid fa-pen-to-square"></i> Modifier la feuille de match
    </h2>

    <div class="glass-form-card">
        <form method="POST">
            
            <!-- Informations de base du match -->
            <div class="match-details-grid">
                <div class="input-field-group">
                    <label for="team1_id">Équipe à domicile (1)</label>
                    <select name="team1_id" id="team1_id" class="custom-form-control" required>
                        <?php foreach($teams as $t): ?>
                            <option value="<?= $t['team_id'] ?>" <?= $t['team_id'] == $match['team1_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($t['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="input-field-group">
                    <label for="team2_id">Équipe extérieure (2)</label>
                    <select name="team2_id" id="team2_id" class="custom-form-control" required>
                        <?php foreach($teams as $t): ?>
                            <option value="<?= $t['team_id'] ?>" <?= $t['team_id'] == $match['team2_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($t['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="input-field-group">
                    <label for="pool_id">Pool / Groupe</label>
                    <select name="pool_id" id="pool_id" class="custom-form-control">
                        <option value="">-- Aucun groupe défini --</option>
                        <?php foreach($pools as $p): ?>
                            <option value="<?= $p['pool_id'] ?>" <?= $p['pool_id'] == $match['pool_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($p['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="input-field-group">
                    <label for="match_datetime">Date & Heure du coup d'envoi</label>
                    <input type="datetime-local" name="match_datetime" id="match_datetime" class="custom-form-control" 
                           value="<?= $match['match_datetime'] ? date('Y-m-d\TH:i', strtotime($match['match_datetime'])) : '' ?>">
                </div>

                <div class="input-field-group">
                    <label for="location">Stade / Lieu de rencontre</label>
                    <input type="text" name="location" id="location" class="custom-form-control" placeholder="Ex: Terrain central" value="<?= htmlspecialchars($match['location'] ?? '') ?>">
                </div>
            </div>

            <!-- Block Scores & Clôture du match -->
            <div class="score-status-grid">
                <div class="input-field-group">
                    <label for="score_team1">Score final Équipe 1</label>
                    <input type="number" min="0" name="score_team1" id="score_team1" class="custom-form-control" placeholder="0" value="<?= $match['score_team1'] ?>">
                </div>

                <div class="input-field-group">
                    <label for="score_team2">Score final Équipe 2</label>
                    <input type="number" min="0" name="score_team2" id="score_team2" class="custom-form-control" placeholder="0" value="<?= $match['score_team2'] ?>">
                </div>

                <div class="input-field-group">
                    <div class="custom-toggle-box">
                        <label class="toggle-switch">
                            <input type="checkbox" name="is_played" id="is_played" <?= $match['is_played'] ? 'checked' : '' ?>>
                            <span class="toggle-slider"></span>
                        </label>
                        <label for="is_played" class="toggle-label-text">Match terminé</label>
                    </div>
                </div>
            </div>

            <hr class="form-section-divider">

            <!-- Sélection et attribution des Buteurs -->
            <h3 class="section-subtitle"><i class="fa-solid fa-soccer-ball"></i> Attribution des buts individuels</h3>
            
            <div class="scorers-section-grid">
                
                <!-- Buteurs Équipe 1 -->
                <div class="scorer-team-column">
                    <h4 class="section-subtitle" style="margin-top:0;"><i class="fa-solid fa-shirt" style="color: var(--accent-blue);"></i> Buteurs Équipe 1</h4>
                    <?php if (empty($team1_players)): ?>
                        <p style="font-size: var(--font-size-sm); color: var(--gray-400); font-style: italic;">Aucun joueur trouvé pour cette équipe.</p>
                    <?php else: ?>
                        <?php foreach($team1_players as $player): 
                            $player_id = $player['player_id'];
                            $goals = $team1_goals[$player_id] ?? 0;
                        ?>
                            <div class="scorer-input-row">
                                <span class="scorer-name" title="<?= htmlspecialchars($player['full_name']) ?>">
                                    <?= htmlspecialchars($player['full_name']) ?>
                                </span>
                                <input type="number" min="0" max="20" name="team1_goal[<?= $player_id ?>]" class="custom-form-control scorer-count-input" value="<?= $goals ?>">
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Buteurs Équipe 2 -->
                <div class="scorer-team-column">
                    <h4 class="section-subtitle" style="margin-top:0;"><i class="fa-solid fa-shirt" style="color: var(--accent-red);"></i> Buteurs Équipe 2</h4>
                    <?php if (empty($team2_players)): ?>
                        <p style="font-size: var(--font-size-sm); color: var(--gray-400); font-style: italic;">Aucun joueur trouvé pour cette équipe.</p>
                    <?php else: ?>
                        <?php foreach($team2_players as $player): 
                            $player_id = $player['player_id'];
                            $goals = $team2_goals[$player_id] ?? 0;
                        ?>
                            <div class="scorer-input-row">
                                <span class="scorer-name" title="<?= htmlspecialchars($player['full_name']) ?>">
                                    <?= htmlspecialchars($player['full_name']) ?>
                                </span>
                                <input type="number" min="0" max="20" name="team2_goal[<?= $player_id ?>]" class="custom-form-control scorer-count-input" value="<?= $goals ?>">
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

            </div>

            <!-- Zone de validation -->
            <footer class="form-footer-actions">
                <a href="matches.php" class="btn-dashboard btn-dashboard-cancel">Retour aux matchs</a>
                <button type="submit" class="btn-dashboard btn-dashboard-save">
                    <i class="fa-solid fa-floppy-disk"></i> Enregistrer les modifications
                </button>
            </footer>

        </form>
    </div>
</div>

<?php
$content = ob_get_clean();
include '../layout.php';
?>