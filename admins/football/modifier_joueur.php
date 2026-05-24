<?php
session_start();
require_once '../../includes/db.php';

$id_admin = $_SESSION['admin_id'] ?? null;
if (!$id_admin) die("Accès refusé");

// Récupérer l'ID du joueur à modifier
$player_id = $_GET['id'] ?? null;
if (!$player_id) die("Joueur introuvable");

// Charger le joueur
$stmt = $pdo->prepare("SELECT * FROM team_players WHERE player_id = ?");
$stmt->execute([$player_id]);
$player = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$player) die("Joueur introuvable");

// Charger équipes
$teams = $pdo->query("
    SELECT t.team_id, t.name AS team_name, s.id_season
    FROM football_teams t
    JOIN football_seasons s ON s.id_season = t.season_id
")->fetchAll(PDO::FETCH_ASSOC);

// Charger étudiants
$students = $pdo->query("SELECT id_etudiant, nom, prenom FROM etudiants ORDER BY nom ASC")->fetchAll(PDO::FETCH_ASSOC);

// Charger saisons
$seasons = $pdo->query("SELECT * FROM football_seasons ORDER BY is_active DESC, label ASC")->fetchAll(PDO::FETCH_ASSOC);

// Traitement POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $team_id = $_POST['team_id'];
    $season_id = $_POST['season_id'];
    $user_id = $_POST['user_id'] !== "" ? $_POST['user_id'] : null;
    $external_name = $_POST['external_name'] !== "" ? trim($_POST['external_name']) : null;
    $position = $_POST['position'] ?? null;
    $shirt_number = $_POST['shirt_number'] ?? null;
    $is_captain = isset($_POST['is_captain']) ? 1 : 0;

    // Pour joueurs internes on remplit full_name avec NULL
    $full_name = $user_id ? null : $external_name;

    $sql = "UPDATE team_players 
            SET user_id = ?, full_name = ?, position = ?, shirt_number = ?, 
                team_id = ?, season_id = ?, is_captain = ?
            WHERE player_id = ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id, $full_name, $position, $shirt_number, $team_id, $season_id, $is_captain, $player_id]);

    header("Location: players.php?success=1");
    exit;
}

ob_start();
?>

<!-- Styles CSS intégrés utilisant vos variables globales :root -->
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

        /* Sidebar & Container */
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

    /* --- Base Layout --- */
    .form-dashboard-wrapper {
        font-family: var(--font-primary);
        color: var(--gray-100);
        max-width: 850px;
        margin: 0 auto;
        padding: var(--space-4) 0;
    }

    /* --- Custom Header --- */
    .form-header {
        display: flex;
        align-items: center;
        gap: var(--space-3);
        margin-bottom: var(--space-5);
        padding-bottom: var(--space-4);
        border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    }

    .form-header h2 {
        font-size: var(--font-size-xl);
        font-weight: 600;
        color: var(--white);
        margin: 0;
    }

    .form-header i {
        color: var(--accent-blue);
        font-size: 1.5rem;
    }

    /* --- Form Glass Card --- */
    .form-card {
        background: rgba(18, 12, 58, 0.35);
        border: 1px solid rgba(255, 255, 255, 0.06);
        border-radius: var(--radius-lg);
        padding: var(--space-5);
        box-shadow: var(--shadow-lg);
        backdrop-filter: blur(12px);
    }

    /* --- Form Grid layout --- */
    .form-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: var(--space-4);
    }

    .form-group {
        display: flex;
        flex-direction: column;
        gap: var(--space-2);
    }

    /* Full-width fields inside the grid layout */
    .form-group.full-width {
        grid-column: span 2;
    }

    /* --- Form Labels & Inputs Design --- */
    .form-group label {
        font-size: var(--font-size-sm);
        font-weight: 600;
        color: var(--gray-300);
        letter-spacing: 0.3px;
    }

    .form-control-custom {
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

    .form-control-custom:focus {
        border-color: var(--accent-blue);
        box-shadow: 0 0 0 3px rgba(46, 134, 222, 0.25);
    }

    /* Select element arrow clean setup */
    select.form-control-custom {
        appearance: none;
        background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='24' height='24' fill='%23a4b0be'><path d='M7 10l5 5 5-5z'/></svg>");
        background-repeat: no-repeat;
        background-position: right var(--space-2) center;
        background-size: 1.2rem;
        padding-right: var(--space-5);
    }

    select.form-control-custom option {
        background-color: var(--primary-800);
        color: var(--white);
    }

    /* --- Custom Switch Toggle (Captain) --- */
    .switch-container {
        display:flex;
        align-items: center;
        gap: var(--space-3);
        margin-top: var(--space-2);
        padding: var(--space-3);
        background: rgba(255, 255, 255, 0.02);
        border: 1px solid rgba(255, 255, 255, 0.04);
        border-radius: var(--radius-md);
    }

    .switch-label-text {
        font-size: var(--font-size-sm);
        font-weight: 600;
        color: var(--gray-200);
        cursor: pointer;
    }

    .switch {
        position: relative;
        display: inline-block;
        width: 44px;
        height: 24px;
        flex-shrink: 0;
    }

    .switch input { 
        opacity: 0;
        width: 0;
        height: 0;
    }

    .slider {
        position: absolute;
        cursor: pointer;
        top: 0; left: 0; right: 0; bottom: 0;
        background-color: var(--primary-600);
        transition: .3s;
        border-radius: var(--radius-full);
    }

    .slider:before {
        position: absolute;
        content: "";
        height: 16px;
        width: 16px;
        left: 4px;
        bottom: 4px;
        background-color: var(--white);
        transition: .3s;
        border-radius: 50%;
    }

    input:checked + .slider {
        background-color: var(--accent-blue);
    }

    input:checked + .slider:before {
        transform: translateX(20px);
    }

    /* --- Form Action Buttons Footer --- */
    .form-actions-footer {
        display: flex;
        justify-content: flex-end;
        align-items: center;
        gap: var(--space-3);
        margin-top: var(--space-5);
        padding-top: var(--space-4);
        border-top: 1px solid rgba(255, 255, 255, 0.06);
    }

    .btn-action-base {
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

    .btn-action-save {
        background-color: var(--accent-blue);
        color: var(--white);
        box-shadow: 0 4px 12px rgba(46, 134, 222, 0.25);
    }

    .btn-action-save:hover {
        background-color: #54a0ff;
        transform: translateY(-1px);
        box-shadow: 0 6px 16px rgba(46, 134, 222, 0.4);
    }

    .btn-action-cancel {
        background-color: transparent;
        color: var(--gray-400);
        border: 1px solid var(--primary-600);
    }

    .btn-action-cancel:hover {
        background-color: rgba(255, 255, 255, 0.03);
        color: var(--white);
        border-color: var(--gray-400);
    }

    /* --- Mobile & Responsive Breakpoints --- */
    @media (max-width: 768px) {
        .form-grid {
            grid-template-columns: 1fr;
            gap: var(--space-3);
        }

        .form-group.full-width {
            grid-column: span 1;
        }

        .form-card {
            padding: var(--space-4);
        }

        .form-actions-footer {
            flex-direction: column-reverse;
            width: 100%;
        }

        .btn-action-base {
            width: 100%;
            justify-content: center;
        }
    }
</style>

<div class="form-dashboard-wrapper">
    
    <!-- Entête de l'interface -->
    <header class="form-header">
        <i class="fa-solid fa-user-pen"></i>
        <h2>Modifier la fiche joueur</h2>
    </header>

    <!-- Formulaire Principal -->
    <div class="form-card">
        <form method="POST">
            <div class="form-grid">
                
                <!-- Sélection de l'Équipe -->
                <div class="form-group">
                    <label for="team_id">Équipe affectée</label>
                    <select name="team_id" id="team_id" class="form-control-custom" required>
                        <?php foreach ($teams as $t): ?>
                            <option value="<?= $t['team_id'] ?>" <?= $t['team_id'] == $player['team_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($t['team_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Sélection de la Saison -->
                <div class="form-group">
                    <label for="season_id">Saison sportive</label>
                    <select name="season_id" id="season_id" class="form-control-custom" required>
                        <?php foreach ($seasons as $s): ?>
                            <option value="<?= $s['id_season'] ?>" <?= $s['id_season'] == $player['season_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($s['label']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Sélection de l'Étudiant (Joueur Interne) -->
                <div class="form-group">
                    <label for="user_id">Étudiant inscrit (Optionnel)</label>
                    <select name="user_id" id="user_id" class="form-control-custom">
                        <option value="">-- Assigner un joueur externe --</option>
                        <?php foreach ($students as $s): ?>
                            <option value="<?= $s['id_etudiant'] ?>" <?= $s['id_etudiant'] == $player['user_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($s['nom'] . " " . $s['prenom']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Nom Complet Externe -->
                <div class="form-group">
                    <label for="external_name">Nom complet (Joueur Externe)</label>
                    <input type="text" name="external_name" id="external_name" class="form-control-custom" placeholder="Saisir si non étudiant" value="<?= htmlspecialchars($player['full_name'] ?? '') ?>">
                </div>

                <!-- Position sur le terrain -->
                <div class="form-group">
                    <label for="position">Poste / Position</label>
                    <input type="text" name="position" id="position" class="form-control-custom" placeholder="Ex: Attaquant, Gardien..." value="<?= htmlspecialchars($player['position'] ?? '') ?>">
                </div>

                <!-- Numéro de dossard -->
                <div class="form-group">
                    <label for="shirt_number">Numéro de dossard</label>
                    <input type="number" name="shirt_number" id="shirt_number" class="form-control-custom" placeholder="Ex: 10" min="1" max="99" value="<?= htmlspecialchars($player['shirt_number'] ?? '') ?>">
                </div>

                <!-- Statut de Capitaine (Plein écran sur la grille) -->
                <div class="form-group full-width">
                    <div class="switch-container">
                        <label class="switch">
                            <input type="checkbox" name="is_captain" id="is_captain" <?= !empty($player['is_captain']) ? 'checked' : '' ?>>
                            <span class="slider"></span>
                        </label>
                        <label for="is_captain" class="switch-label-text">Désigner ce joueur comme capitaine d'équipe</label>
                    </div>
                </div>

            </div>

            <!-- Boutons de validation d'action -->
            <footer class="form-actions-footer">
                <a href="players.php" class="btn-action-base btn-action-cancel">Annuler</a>
                <button type="submit" class="btn-action-base btn-action-save">
                    <i class="fa-solid fa-check"></i> Enregistrer les modifications
                </button>
            </footer>
        </form>
    </div>
</div>

<?php
$content = ob_get_clean();
include '../layout.php';
?>