<?php
session_start();
require_once '../includes/db.php';

// Sécurité : Vérification admin (Décommentez et ajustez selon vos besoins)
// if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
//     header("Location: index.php");
//     exit;
// }

// Vérification de la validité de l'identifiant
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: annees.php");
    exit;
}

$id = (int)$_GET['id'];
$errors = [];

// Récupération des données actuelles de l'année universitaire
$stmt = $pdo->prepare("SELECT * FROM academic_years WHERE id = :id");
$stmt->execute([':id' => $id]);
$annee = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$annee) {
    die("Année universitaire introuvable.");
}

$label = $annee['label'];
$start_date = $annee['start_date'];
$end_date = $annee['end_date'];
$is_current = $annee['is_current'];

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
            // Désactiver l'ancienne année universitaire active
            $pdo->exec("UPDATE academic_years SET is_current = 0");
        }

        $stmt = $pdo->prepare("UPDATE academic_years SET label = :label, start_date = :start, end_date = :end, is_current = :current WHERE id = :id");
        $stmt->execute([
            ':label'   => $label,
            ':start'   => $start_date,
            ':end'   => $end_date,
            ':current' => $is_current,
            ':id'      => $id
        ]);

        header("Location: annees.php?updated=1");
        exit;
    }
}

// --- Stockage du contenu dans la mémoire tampon pour injection ---
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

    /* Entête de page pro */
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

    /* Conteneur principal du formulaire */
    .form-card {
        background-color: var(--bg-card);
        border: 1px solid var(--border-glass);
        border-radius: var(--radius-lg);
        padding: var(--space-5);
        box-shadow: var(--shadow-xl);
        max-width: 650px;
        margin: 0 auto var(--space-6) auto;
    }

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

    /* Éléments de saisie de données uniformisés */
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

    /* Ajustement de l'icône de calendrier pour thème sombre */
    input[type="date"]::-webkit-calendar-picker-indicator {
        filter: invert(1);
        cursor: pointer;
        opacity: 0.6;
        transition: opacity var(--transition-fast);
    }
    input[type="date"]::-webkit-calendar-picker-indicator:hover {
        opacity: 1;
    }

    /* Alignement horizontal des dates */
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

    /* Structure du Toggle Switch personnalisé */
    .switch-container {
        display: flex;
        align-items: center;
        gap: var(--space-3);
        padding: var(--space-2) 0;
        margin-bottom: var(--space-5);
        cursor: pointer;
    }

    .switch-label {
        font-size: var(--font-size-sm);
        font-weight: 600;
        color: var(--gray-200);
        user-select: none;
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

    input:checked + .slider {
        background-color: var(--accent-green);
        border-color: var(--accent-green);
    }

    input:checked + .slider:before {
        transform: translateX(20px);
        background-color: var(--white);
    }

    /* Bloc d'actions (Boutons) */
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
        background-color: var(--accent-blue);
        color: var(--white);
        box-shadow: var(--shadow-md);
    }

    .btn-submit:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(46, 134, 222, 0.3);
    }

    /* Notification d'erreur épurée */
    .alert-container {
        background-color: rgba(255, 71, 87, 0.08);
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

    <!-- En-tête de la vue -->
    <div class="page-header">
        <h2>
            <svg width="22" height="22" fill="none" stroke="var(--accent-blue)" stroke-width="2.5" viewBox="0 0 24 24">
                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                <path d="M18.5 2.5a2.121 2.121 0 1 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
            </svg>
            Modifier l'année universitaire
        </h2>
        <a href="annees.php" class="btn-custom btn-secondary">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path d="M19 12H5M12 19l-7-7 7-7"/>
            </svg>
            Retour à la liste
        </a>
    </div>

    <!-- Affichage des erreurs de traitement -->
    <?php if (!empty($errors)): ?>
    <div class="alert-container">
        <ul>
            <?php foreach ($errors as $err): ?>
                <li><?= htmlspecialchars($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <!-- Formulaire d'édition -->
    <div class="form-card">
        <form method="POST">
            
            <div class="form-group">
                <label for="label">Libellé / Titre de l'année *</label>
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

            <!-- Commutateur d'activation graphique (Toggle Switch) -->
            <label class="switch-container" for="is_current">
                <span class="custom-switch">
                    <input type="checkbox" name="is_current" id="is_current" <?= $is_current ? 'checked' : '' ?>>
                    <span class="slider"></span>
                </span>
                <span class="switch-label">Définir comme l'année universitaire active</span>
            </label>

            <!-- Pied du formulaire / Boutons d'actions -->
            <div class="actions-group">
                <a href="annees.php" class="btn-custom btn-secondary">Annuler</a>
                <button type="submit" class="btn-custom btn-submit">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                        <polyline points="17 21 17 13 7 13 7 21"></polyline>
                        <polyline points="7 3 7 8 15 8"></polyline>
                    </svg>
                    Enregistrer les modifications
                </button>
            </div>

        </form>
    </div>
</div>

<?php
$content = ob_get_clean();
include 'layout.php';
?>