<?php
session_start();
require_once '../includes/db.php';

// Sécurité : Vérification admin (Décommentez et ajustez selon vos variables de session)
// if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
//     header("Location: index.php");
//     exit;
// }

$errors = [];
$label = '';
$start_date = '';
$end_date = '';
$is_current = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $label = trim($_POST['label'] ?? '');
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $is_current = isset($_POST['is_current']) ? 1 : 0;

    if (empty($label)) $errors[] = "Le libellé de l'année est obligatoire.";
    if (empty($start_date)) $errors[] = "La date de début est obligatoire.";
    if (empty($end_date)) $errors[] = "La date de fin est obligatoire.";
    if (!empty($start_date) && !empty($end_date) && $start_date > $end_date) {
        $errors[] = "La date de début doit être antérieure à la date de fin.";
    }

    if (empty($errors)) {
        if ($is_current) {
            // Désactiver l'année en cours précédente
            $pdo->exec("UPDATE academic_years SET is_current = 0");
        }

        $stmt = $pdo->prepare("INSERT INTO academic_years (label, start_date, end_date, is_current) VALUES (:label, :start, :end, :current)");
        $stmt->execute([
            ':label'   => $label,
            ':start'   => $start_date,
            ':end'   => $end_date,
            ':current' => $is_current
        ]);

        // Redirection vers votre page de liste révisée
        header("Location: annees.php?added=1");
        exit;
    }
}
ob_start();
?>

<!-- Bloc de styles dédiés exploitant vos variables :root -->
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

    --bg-page: var(--primary-900);
    --bg-card: var(--primary-800);
    --bg-input: var(--primary-700);
    --bg-input-focus: var(--primary-600);
    --border-glass: rgba(255, 255, 255, 0.06);
    --border-focus: var(--accent-blue);
    }

    body {
        background-color: var(--bg-page);
        color: var(--gray-100);
        font-family: var(--font-primary);
    }

    /* En-tête de la page */
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: var(--space-6);
        padding-bottom: var(--space-4);
        border-bottom: 1px solid var(--border-glass);
    }

    .page-header h2 {
        font-size: var(--font-size-xl);
        font-weight: 700;
        color: var(--white);
        margin: 0;
        display: flex;
        align-items: center;
        gap: var(--space-3);
        letter-spacing: -0.5px;
    }

    /* Conteneur du formulaire */
    .form-card {
        background-color: var(--bg-card);
        border: 1px solid var(--border-glass);
        border-radius: var(--radius-lg);
        padding: var(--space-5);
        box-shadow: var(--shadow-xl);
        max-width: 650px;
        margin: 0 auto var(--space-6) auto;
    }

    /* Groupes de champs */
    .form-group {
        margin-bottom: var(--space-4);
    }

    .form-group label {
        display: block;
        font-size: var(--font-size-sm);
        font-weight: 600;
        color: var(--gray-200);
        margin-bottom: var(--space-2);
    }

    /* Entrées de données uniformes */
    .form-control {
        width: 100%;
        background-color: var(--bg-input);
        border: 1px solid var(--border-glass);
        border-radius: var(--radius-md);
        color: var(--white);
        font-family: var(--font-primary);
        font-size: var(--font-size-sm);
        padding: var(--space-3) var(--space-4);
        box-sizing: border-box;
        transition: all var(--transition-fast);
    }

    .form-control:focus {
        outline: none;
        background-color: var(--bg-input-focus);
        border-color: var(--border-focus);
        box-shadow: 0 0 0 3px rgba(46, 134, 222, 0.15);
    }

    /* Style spécifique pour les inputs de type date (icône calendrier en mode sombre) */
    input[type="date"]::-webkit-calendar-picker-indicator {
        filter: invert(1);
        cursor: pointer;
        opacity: 0.6;
        transition: opacity var(--transition-fast);
    }
    input[type="date"]::-webkit-calendar-picker-indicator:hover {
        opacity: 1;
    }

    /* Grille de dates côte à côte */
    .date-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: var(--space-4);
    }

    @media (max-width: 576px) {
        .date-grid {
            grid-template-columns: 1fr;
        }
    }

    /* Alignement et design du commutateur personnalisé (Switch Checkbox) */
    .switch-container {
        display: flex;
        align-items: center;
        gap: var(--space-3);
        padding: var(--space-3) 0;
        margin-bottom: var(--space-5);
        cursor: pointer;
    }

    .switch-label {
        font-size: var(--font-size-sm);
        font-weight: 600;
        color: var(--gray-200);
        user-select: none;
        cursor: pointer;
    }

    .custom-switch {
        position: relative;
        display: inline-block;
        width: 44px;
        height: 24px;
    }

    .custom-switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }

    .slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: var(--bg-input);
        border: 1px solid var(--border-glass);
        transition: var(--transition-base);
        border-radius: var(--radius-full);
    }

    .slider:before {
        position: absolute;
        content: "";
        height: 16px;
        width: 16px;
        left: 3px;
        bottom: 3px;
        background-color: var(--gray-300);
        transition: var(--transition-base);
        border-radius: 50%;
    }

    /* Changement d'états du Switch au clic */
    ui-switch:focus-within .slider {
        box-shadow: 0 0 0 3px rgba(46, 134, 222, 0.15);
    }

    input:checked + .slider {
        background-color: var(--accent-green);
        border-color: var(--accent-green);
    }

    input:checked + .slider:before {
        transform: translateX(20px);
        background-color: var(--white);
    }

    /* Zone des boutons */
    .actions-group {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: var(--space-3);
        border-top: 1px solid var(--border-glass);
        padding-top: var(--space-5);
    }

    .btn-custom {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: var(--space-2);
        font-family: var(--font-primary);
        font-size: var(--font-size-sm);
        font-weight: 600;
        border-radius: var(--radius-md);
        text-decoration: none;
        padding: var(--space-2) var(--space-5);
        transition: all var(--transition-fast);
        cursor: pointer;
        border: none;
    }

    .btn-secondary {
        background-color: transparent;
        color: var(--gray-300);
        border: 1px solid var(--border-glass);
    }

    .btn-secondary:hover {
        background-color: var(--primary-700);
        color: var(--white);
    }

    .btn-submit {
        background-color: var(--accent-green);
        color: var(--white);
        box-shadow: var(--shadow-md);
    }

    .btn-submit:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(16, 172, 132, 0.3);
    }

    /* Alerte d'erreur moderne */
    .alert-container {
        background-color: rgba(255, 71, 87, 0.1);
        border: 1px solid rgba(255, 71, 87, 0.2);
        border-left: 4px solid var(--accent-red);
        border-radius: var(--radius-md);
        padding: var(--space-4);
        max-width: 650px;
        margin: 0 auto var(--space-5) auto;
    }

    .alert-container ul {
        margin: 0;
        padding-left: var(--space-4);
        color: #ff6b81;
        font-size: var(--font-size-sm);
    }
</style>

<div class="container-fluid">

    <!-- En-tête -->
    <div class="page-header">
        <h2>
            <svg width="22" height="22" fill="none" stroke="var(--accent-green)" stroke-width="2.5" viewBox="0 0 24 24">
                <path d="M12 5v14M5 12h14"></path>
            </svg>
            Nouvelle année universitaire
        </h2>
        <a href="annees.php" class="btn-custom btn-secondary">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
            Retour à la liste
        </a>
    </div>

    <!-- Affichage des erreurs si existantes -->
    <?php if (!empty($errors)): ?>
    <div class="alert-container">
        <ul>
            <?php foreach ($errors as $err): ?>
                <li><?= htmlspecialchars($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <!-- Carte du formulaire -->
    <div class="form-card">
        <form method="POST">
            
            <div class="form-group">
                <label for="label">Libellé / Titre *</label>
                <input type="text" id="label" name="label" class="form-control" 
                       value="<?= htmlspecialchars($label) ?>" placeholder="Ex: 2026-2027" required autocomplete="off">
            </div>

            <div class="date-grid">
                <div class="form-group">
                    <label for="start_date">Date de début *</label>
                    <input type="date" id="start_date" name="start_date" class="form-control" 
                           value="<?= htmlspecialchars($start_date) ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="end_date">Date de fin *</label>
                    <input type="date" id="end_date" name="end_date" class="form-control" 
                           value="<?= htmlspecialchars($end_date) ?>" required>
                </div>
            </div>

            <!-- Bouton Switch Personnalisé pour l'état actif -->
            <label class="switch-container" for="is_current">
                <span class="custom-switch">
                    <input type="checkbox" name="is_current" id="is_current" <?= $is_current ? 'checked' : '' ?>>
                    <span class="slider"></span>
                </span>
                <span class="switch-label">Définir comme l'année universitaire active</span>
            </label>

            <!-- Actions de bas de carte -->
            <div class="actions-group">
                <a href="annees.php" class="btn-custom btn-secondary">Annuler</a>
                <button type="submit" class="btn-custom btn-submit">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                        <polyline points="17 21 17 13 7 13 7 21"></polyline>
                        <polyline points="7 3 7 8 15 8"></polyline>
                    </svg>
                    Enregistrer l'année
                </button>
            </div>

        </form>
    </div>
</div>

<?php
// Récupération du contenu et injection finale dans votre fichier de mise en page globale
$content = ob_get_clean();
include 'layout.php';
?>