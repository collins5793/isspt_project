<?php
require_once '../../includes/db.php';
session_start();

$id_admin = $_SESSION['admin_id'] ?? null;
if (!$id_admin) {
    die("Accès refusé");
}

// Charger équipes avec leurs alias corrigés
$stmt = $pdo->query("
    SELECT t.team_id, t.name AS team_name, s.id_season
    FROM football_teams t
    JOIN football_seasons s ON s.id_season = t.season_id
");
$teams = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Charger étudiants
$students = $pdo->query("SELECT id_etudiant, nom, prenom FROM etudiants ORDER BY nom ASC")->fetchAll(PDO::FETCH_ASSOC);

// Charger saisons
$seasons = $pdo->query("SELECT * FROM football_seasons ORDER BY is_active DESC, label ASC")->fetchAll(PDO::FETCH_ASSOC);

$error_msg = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $team_id = $_POST['team_id'] ?? null;
    $season_id = $_POST['season_id'] ?? null;
    $user_id = (isset($_POST['user_id']) && $_POST['user_id'] !== "") ? $_POST['user_id'] : null;
    $external_name = (isset($_POST['external_name']) && $_POST['external_name'] !== "") ? trim($_POST['external_name']) : null;
    $position = (isset($_POST['position']) && $_POST['position'] !== "") ? trim($_POST['position']) : null;
    $shirt_number = (isset($_POST['shirt_number']) && $_POST['shirt_number'] !== "") ? intval($_POST['shirt_number']) : null;
    $is_captain = isset($_POST['is_captain']) ? 1 : 0;

    // Validation minimale
    if (!$user_id && !$external_name) {
        $error_msg = "Veuillez sélectionner un étudiant ou renseigner un nom pour un joueur externe.";
    } else {
        $full_name = $user_id ? null : $external_name;

        $sql = "INSERT INTO team_players 
            (user_id, full_name, position, shirt_number, team_id, season_id, is_captain)
            VALUES (?, ?, ?, ?, ?, ?, ?)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id, $full_name, $position, $shirt_number, $team_id, $season_id, $is_captain]);

        header("Location: players.php?success=1");
        exit;
    }
}

ob_start();
?>

<style>
    /* ==========================================================================
       STYLE DESIGN PREMIUM - FORMULAIRE AJOUT JOUEUR
       ========================================================================== */

    :root {
        /* Intégration stricte de votre configuration racine */
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

        --radius-md: 8px;
        --radius-lg: 12px;
        --radius-full: 9999px;
    }

    /* Conteneur principal & typographie globale */
    .view-wrapper {
        font-family: var(--font-primary);
        color: var(--gray-100);
        background-color: transparent;
        max-width: 1300px;
        margin: 0 auto;
        padding: var(--space-4);
    }

    /* En-tête de page épuré */
    .page-header {
        margin-bottom: var(--space-6);
        border-bottom: 1px solid rgba(255, 255, 255, 0.06);
        padding-bottom: var(--space-4);
    }

    .page-header h2 {
        font-size: 1.75rem;
        font-weight: 700;
        color: var(--white);
        letter-spacing: -0.02em;
        margin-bottom: var(--space-1);
    }

    .page-header p {
        color: var(--gray-400);
        font-size: var(--font-size-sm);
    }

    /* Alertes d'erreurs ou notifications */
    .alert {
        background-color: rgba(255, 71, 87, 0.1);
        border: 1px solid rgba(255, 71, 87, 0.2);
        color: var(--accent-red);
        padding: var(--space-4);
        border-radius: var(--radius-md);
        margin-bottom: var(--space-5);
        font-size: var(--font-size-sm);
        display: flex;
        align-items: center;
        gap: var(--space-3);
    }

    /* Structure de la Grille Responsive */
    .form-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: var(--space-5);
        align-items: start;
    }

    @media (min-width: 1024px) {
        .form-grid {
            grid-template-columns: minmax(0, 1fr) 380px;
        }
    }

    /* Cartes d'encapsulation (Cards) */
    .panel-card {
        background-color: var(--primary-800);
        border: 1px solid rgba(255, 255, 255, 0.04);
        border-radius: var(--radius-lg);
        padding: var(--space-5);
        box-shadow: var(--shadow-lg);
        margin-bottom: var(--space-4);
    }

    .panel-card-title {
        font-size: var(--font-size-md);
        font-weight: 600;
        color: var(--white);
        margin-bottom: var(--space-4);
        padding-bottom: var(--space-2);
        border-bottom: 1px solid rgba(255, 255, 255, 0.04);
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }

    /* Groupes de formulaires et champs natifs */
    .form-group {
        display: flex;
        flex-direction: column;
        gap: var(--space-2);
        margin-bottom: var(--space-4);
    }

    .form-group:last-child {
        margin-bottom: 0;
    }

    .form-row {
        display: grid;
        grid-template-columns: 1fr;
        gap: var(--space-4);
    }

    @media (min-width: 640px) {
        .form-row-2col {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    label {
        font-size: var(--font-size-sm);
        color: var(--gray-300);
        font-weight: 500;
    }

    /* Éléments de champs unifiés */
    input[type="text"],
    input[type="number"],
    select {
        width: 100%;
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
        cursor: pointer;
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%23a4b0be' viewBox='0 0 16 16'%3E%3Cpath fill-rule='evenodd' d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 1rem center;
        background-size: 11px;
        padding-right: 2.5rem;
    }

    /* Colonne latérale fixe au défilement (Sticky Desktop) */
    @media (min-width: 1024px) {
        .sticky-sidebar {
            position: sticky;
            top: var(--space-4);
        }
    }

    /* Interrupteur personnalisé (Toggle Switch Capitaine) */
    .switch-wrapper {
        display: flex;
        align-items: center;
        gap: var(--space-3);
        padding: var(--space-3) 0;
        cursor: pointer;
    }

    .switch-control {
        position: relative;
        display: inline-block;
        width: 44px;
        height: 24px;
        flex-shrink: 0;
    }

    .switch-control input {
        opacity: 0;
        width: 0;
        height: 0;
    }

    .switch-slider {
        position: absolute;
        top: 0; left: 0; right: 0; bottom: 0;
        background-color: var(--primary-600);
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: var(--radius-full);
        transition: background-color var(--transition-base);
    }

    .switch-slider:before {
        position: absolute;
        content: "";
        height: 16px;
        width: 16px;
        left: 3px;
        bottom: 3px;
        background-color: var(--gray-300);
        border-radius: 50%;
        transition: transform var(--transition-base), background-color var(--transition-base);
    }

    .switch-control input:checked + .switch-slider {
        background-color: var(--accent-green);
        border-color: transparent;
    }

    .switch-control input:checked + .switch-slider:before {
        transform: translateX(20px);
        background-color: var(--white);
    }

    .switch-label-text {
        font-size: var(--font-size-sm);
        color: var(--gray-200);
    }

    /* Zone d'action (Bouton d'enregistrement) */
    .action-panel {
        display: flex;
        flex-direction: column;
        gap: var(--space-3);
    }

    .btn-submit {
        width: 100%;
        font-family: inherit;
        background-color: var(--accent-blue);
        color: var(--white);
        font-size: var(--font-size-sm);
        font-weight: 600;
        padding: 0.85rem 1.5rem;
        border: none;
        border-radius: var(--radius-md);
        cursor: pointer;
        box-shadow: 0 4px 12px rgba(46, 134, 222, 0.2);
        transition: all var(--transition-fast) ease;
        text-align: center;
    }

    .btn-submit:hover {
        background-color: #4834d4;
        transform: translateY(-1px);
        box-shadow: 0 6px 16px rgba(46, 134, 222, 0.3);
    }

    .btn-submit:active {
        transform: translateY(0);
    }

    .btn-cancel {
        display: block;
        text-align: center;
        color: var(--gray-400);
        font-size: var(--font-size-sm);
        text-decoration: none;
        padding: var(--space-2);
        transition: color var(--transition-fast);
    }

    .btn-cancel:hover {
        color: var(--white);
    }
</style>

<div class="view-wrapper">
    
    <!-- En-tête de page -->
    <header class="page-header">
        <h2>Ajouter un nouveau joueur</h2>
        <p>Enregistrez un athlète au sein d'une équipe et d'une saison spécifiques.</p>
    </header>

    <!-- Affichage des erreurs éventuelles -->
    <?php if ($error_msg): ?>
        <div class="alert">
            <svg width="18" height="18" viewBox="0 0 16 16" fill="currentColor"><path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zM8 4a.905.905 0 0 0-.9.995l.35 3.507a.552.552 0 0 0 1.1 0l.35-3.507A.905.905 0 0 0 8 4zm.002 6a1 1 0 1 0 0 2 1 1 0 0 0 0-2z"/></svg>
            <span><?= htmlspecialchars($error_msg) ?></span>
        </div>
    <?php endif; ?>

    <!-- Formulaire principal -->
    <form method="POST" class="modern-form">
        <div class="form-grid">
            
            <!-- Colonne Principale (Gauche) : Données d'identité du Joueur -->
            <div class="form-main-column">
                
                <div class="panel-card">
                    <div class="panel-card-title">Sélection du profil</div>
                    
                    <!-- Sélection de l'étudiant officiel (Interne) -->
                    <div class="form-group">
                        <label for="user_id">Étudiant inscrit (Optionnel)</label>
                        <select name="user_id" id="user_id">
                            <option value="">-- Sélectionner un étudiant (Joueur Interne) --</option>
                            <?php foreach ($students as $s): ?>
                                <option value="<?= $s['id_etudiant'] ?>">
                                    <?= htmlspecialchars(strtoupper($s['nom']) . " " . $s['prenom']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Séparateur visuel textuel alternatif -->
                    <div style="text-align: center; margin: var(--space-4) 0; color: rgba(255,255,255,0.15); font-size: var(--font-size-xs); font-weight: 600; text-transform: uppercase; letter-spacing: 0.1em;">
                        OU (Si joueur externe)
                    </div>

                    <!-- Saisie manuelle (Joueur Externe) -->
                    <div class="form-group">
                        <label for="external_name">Nom complet du joueur externe</label>
                        <input type="text" name="external_name" id="external_name" placeholder="Ex: Jean Dupont">
                    </div>
                </div>

                <div class="panel-card">
                    <div class="panel-card-title">Attributs de jeu</div>
                    
                    <div class="form-row form-row-2col">
                        <!-- Position sur le terrain -->
                        <div class="form-group">
                            <label for="position">Position occupée</label>
                            <input type="text" name="position" id="position" placeholder="Ex: Milieu offensif, Gardien">
                        </div>

                        <!-- Numéro de dossard -->
                        <div class="form-group">
                            <label for="shirt_number">Numéro de maillot (Dossard)</label>
                            <input type="number" name="shirt_number" id="shirt_number" min="1" max="99" placeholder="Ex: 10">
                        </div>
                    </div>
                </div>

            </div>

            <!-- Colonne Latérale (Droite) : Logistique d'équipe & Actions d'enregistrement -->
            <div class="form-sidebar-column sticky-sidebar">
                
                <div class="panel-card">
                    <div class="panel-card-title">Affectation</div>

                    <!-- Choix de l'équipe -->
                    <div class="form-group">
                        <label for="team_id">Équipe de destination</label>
                        <select name="team_id" id="team_id" required>
                            <option value="" disabled selected>Choisir une équipe</option>
                            <?php foreach ($teams as $t): ?>
                                <option value="<?= $t['team_id'] ?>">
                                    <?= htmlspecialchars($t['team_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Choix de la saison -->
                    <div class="form-group" style="margin-top: var(--space-4);">
                        <label for="season_id">Saison sportive</label>
                        <select name="season_id" id="season_id" required>
                            <?php foreach ($seasons as $s): ?>
                                <option value="<?= $s['id_season'] ?>" <?= $s['is_active'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($s['label'] ?? "Saison #" . $s['id_season']) ?> <?= $s['is_active'] ? '(Active)' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Case à cocher stylisée pour le statut de capitaine -->
                    <div class="form-group" style="margin-top: var(--space-5);">
                        <label class="switch-wrapper">
                            <span class="switch-control">
                                <input type="checkbox" name="is_captain" value="1">
                                <span class="switch-slider"></span>
                            </span>
                            <span class="switch-label-text">Nommer capitaine de l'équipe</span>
                        </label>
                    </div>
                </div>

                <!-- Boutons d'envoi et d'annulation -->
                <div class="action-panel">
                    <button type="submit" class="btn-submit">Confirmer l'ajout</button>
                    <a href="players.php" class="btn-cancel">Annuler et retourner</a>
                </div>

            </div>

        </div>
    </form>
</div>

<?php
$content = ob_get_clean();
include '../layout.php';
?>