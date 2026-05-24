<?php
session_start();
require_once "../../includes/db.php";

/* ==========================================================================
   VÉRIFICATION DE L'ID ET DE L'EXISTENCE
   ========================================================================== */
$activiteId = $_GET['id'] ?? null;
if (!$activiteId) {
    header("Location: activites.php");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM activites WHERE id_activite = ?");
$stmt->execute([$activiteId]);
$activite = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$activite) {
    die("Activité introuvable.");
}

/* ==========================================================================
   CONFIGURATIONS & CONTEXTE
   ========================================================================== */
$errors = [];
$conditions_autorisees = [
    'etre_etudiant_isspt' => 'Être étudiant de l’ISSPT',
    'ouvert_a_tous'       => 'Ouvert à tous'
];

/* ==========================================================================
   TRAITEMENT DU FORMULAIRE (POST)
   ========================================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nom              = trim($_POST['nom_activite'] ?? '');
    $description      = trim($_POST['description'] ?? '');
    $conditions       = $_POST['conditions'] ?? '';
    $academic_year_id = $_POST['academic_year_id'] ?? null;

    // Validations rigoureuses
    if (!$nom) {
        $errors[] = "Le nom de l'activité est obligatoire.";
    }

    if (!array_key_exists($conditions, $conditions_autorisees)) {
        $errors[] = "La condition sélectionnée est invalide.";
    }

    if (!$academic_year_id) {
        $errors[] = "L'année académique est obligatoire.";
    }

    // Persistance si aucune erreur
    if (empty($errors)) {
        $stmt = $pdo->prepare("
            UPDATE activites
            SET 
                nom_activite = ?,
                description = ?,
                conditions = ?,
                academic_year_id = ?
            WHERE id_activite = ?
        ");

        $stmt->execute([
            $nom,
            $description,
            $conditions,
            $academic_year_id,
            $activiteId
        ]);

        header("Location: activites.php");
        exit;
    }
}

/* ==========================================================================
   CHARGEMENT DES DONNÉES SECONDAIRES
   ========================================================================== */
$years = $pdo
    ->query("SELECT id, label FROM academic_years ORDER BY label DESC")
    ->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>

<!-- 
  ==========================================================================
  🎨 SYSTÈME DESIGN EN CSS PUR (PROPRE, MODERNE & RESPONSIVE)
  ========================================================================== 
-->
<style>
    :root {
        /* Colors - Dark Theme (Forcé localement pour conformité) */
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

        /* Sidebar config */
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

    /* Conteneur principal harmonisé */
    .studio-page-wrapper {
        font-family: var(--font-primary);
        background-color: var(--primary-900);
        color: var(--white);
        padding: var(--space-5);
        min-height: 100vh;
        box-sizing: border-box;
    }

    /* Header de la zone de travail */
    .studio-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: var(--space-5);
        gap: var(--space-4);
    }

    .header-text-group h2 {
        font-size: var(--font-size-xl);
        font-weight: 700;
        color: var(--white);
        margin: 0 0 var(--space-1) 0;
        letter-spacing: -0.02em;
    }

    .header-text-group p {
        font-size: var(--font-size-sm);
        color: var(--gray-400);
        margin: 0;
    }

    /* Boutons de navigation d'action */
    .btn-action-back {
        display: inline-flex;
        align-items: center;
        gap: var(--space-2);
        background-color: var(--primary-700);
        color: var(--gray-100);
        font-size: var(--font-size-sm);
        font-weight: 600;
        padding: 0.6rem 1.15rem;
        border-radius: var(--radius-md);
        text-decoration: none;
        border: 1px solid rgba(255, 255, 255, 0.05);
        transition: background-color var(--transition-fast), color var(--transition-fast);
        cursor: pointer;
        white-space: nowrap;
    }

    .btn-action-back:hover {
        background-color: var(--primary-600);
        color: var(--white);
    }

    /* Séparateur de section */
    .studio-divider {
        border: 0;
        height: 1px;
        background: rgba(255, 255, 255, 0.08);
        margin: var(--space-4) 0 var(--space-6) 0;
    }

    /* Bloc d'affichage des alertes d'erreurs */
    .studio-alert-card {
        background-color: rgba(255, 71, 87, 0.1);
        border: 1px solid rgba(255, 71, 87, 0.25);
        border-radius: var(--radius-lg);
        padding: var(--space-4);
        margin-bottom: var(--space-5);
        display: flex;
        gap: var(--space-3);
        align-items: flex-start;
    }

    .studio-alert-card .alert-icon {
        font-size: var(--font-size-lg);
        color: var(--accent-red);
        line-height: 1;
    }

    .studio-alert-card ul {
        margin: 0;
        padding-left: var(--space-4);
        color: var(--gray-200);
        font-size: var(--font-size-sm);
    }

    .studio-alert-card li {
        margin-bottom: var(--space-1);
    }

    .studio-alert-card li:last-child {
        margin-bottom: 0;
    }

    /* Conteneur Formulaire Pro - Style Verre & Ombre */
    .studio-form-card {
        background-color: var(--primary-800);
        border: 1px solid rgba(255, 255, 255, 0.04);
        border-radius: var(--radius-lg);
        padding: var(--space-6);
        max-width: 800px;
        margin: 0 auto;
        box-shadow: var(--shadow-lg);
    }

    /* Grille interne pour le positionnement côte à côte */
    .form-grid-row {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: var(--space-4);
        margin-bottom: var(--space-4);
    }

    .form-grid-full-width {
        grid-column: 1 / -1;
    }

    /* Groupes de champs individuels */
    .studio-form-group {
        display: flex;
        flex-direction: column;
        gap: var(--space-2);
    }

    .studio-form-group label {
        font-size: var(--font-size-sm);
        font-weight: 600;
        color: var(--gray-200);
        letter-spacing: 0.01em;
    }

    .studio-form-group label .required-mark {
        color: var(--accent-red);
        margin-left: 2px;
    }

    /* Inputs, Selects & Textareas unifiés */
    .studio-form-control {
        background-color: var(--primary-700);
        color: var(--white);
        font-family: var(--font-primary);
        font-size: var(--font-size-sm);
        padding: 0.75rem var(--space-4);
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: var(--radius-md);
        outline: none;
        box-sizing: border-box;
        transition: border-color var(--transition-fast), box-shadow var(--transition-fast);
        width: 100%;
    }

    .studio-form-control:focus {
        border-color: var(--accent-blue);
        box-shadow: 0 0 0 3px rgba(46, 134, 222, 0.2);
    }

    /* Ajustement spécifique pour les select */
    select.studio-form-control {
        appearance: none;
        background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='24' height='24' viewBox='0 0 24 24' fill='none' stroke='%23a4b0be' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><polyline points='6 9 12 15 18 9'></polyline></svg>");
        background-repeat: no-repeat;
        background-position: right var(--space-3) center;
        background-size: 16px;
        padding-right: var(--space-5);
        cursor: pointer;
    }

    /* Ajustement spécifique pour les zones de texte */
    textarea.studio-form-control {
        resize: vertical;
        min-height: 100px;
        line-height: 1.5;
    }

    /* Zone de validation basse */
    .form-footer-actions {
        display: flex;
        justify-content: flex-end;
        border-top: 1px solid rgba(255, 255, 255, 0.05);
        padding-top: var(--space-5);
        margin-top: var(--space-5);
    }

    .btn-submit-primary {
        background-color: var(--accent-blue);
        color: var(--white);
        font-family: var(--font-primary);
        font-size: var(--font-size-sm);
        font-weight: 600;
        padding: 0.75rem 1.75rem;
        border: none;
        border-radius: var(--radius-md);
        box-shadow: var(--shadow-md);
        cursor: pointer;
        transition: background-color var(--transition-fast), transform var(--transition-fast), box-shadow var(--transition-fast);
    }

    .btn-submit-primary:hover {
        background-color: #2475c9;
        transform: translateY(-1px);
        box-shadow: var(--shadow-lg);
    }

    .btn-submit-primary:active {
        transform: translateY(0);
    }

    /* ==========================================================================
       📱 CONFIGURATION ET BREAKPOINTS RESPONSIVES (SANS BRISURE DE FLUX)
       ========================================================================== */
    @media (max-width: 768px) {
        .studio-page-wrapper {
            padding: var(--space-4);
        }

        .studio-form-card {
            padding: var(--space-4);
        }

        .form-grid-row {
            grid-template-columns: 1fr; /* Passage automatique en colonne unique */
            gap: var(--space-4);
        }
    }

    @media (max-width: 480px) {
        .studio-header {
            flex-direction: column;
            align-items: flex-start;
            gap: var(--space-3);
        }

        .btn-action-back {
            width: 100%;
            justify-content: center;
        }

        .form-footer-actions {
            justify-content: center;
        }

        .btn-submit-primary {
            width: 100%;
            text-align: center;
        }
    }
</style>

<div class="studio-page-wrapper">

    <!-- 🌐 EN-TÊTE DE LA PAGE -->
    <div class="studio-header">
        <div class="header-text-group">
            <h2>Modifier l'activité</h2>
            <p>Mettez à jour les informations, restrictions de scolarité et configurations structurelles de l'activité.</p>
        </div>
        <a href="activites.php" class="btn-action-back">
            ← Retour aux activités
        </a>
    </div>

    <hr class="studio-divider">

    <!-- 🛑 GESTION DES NOTIFICATIONS D'ERREURS -->
    <?php if ($errors): ?>
        <div class="studio-alert-card">
            <div class="alert-icon">⚠️</div>
            <div>
                <ul>
                    <?php foreach ($errors as $e): ?>
                        <li><?= htmlspecialchars($e) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    <?php endif; ?>

    <!-- 📝 FORMULAIRE CENTRALISÉ ET CONFIGURÉ -->
    <form method="POST" class="studio-form-card">
        <div class="form-grid-row">
            
            <!-- Champ : Nom de l'activité -->
            <div class="studio-form-group自动 form-grid-full-width">
                <label for="nom_activite">Nom de l'activité <span class="required-mark">*</span></label>
                <input type="text" 
                       id="nom_activite"
                       name="nom_activite" 
                       class="studio-form-control"
                       placeholder="Ex: Tournoi de Football Inter-fac"
                       value="<?= htmlspecialchars($_POST['nom_activite'] ?? $activite['nom_activite']) ?>" 
                       required>
            </div>

            <!-- Champ : Description -->
            <div class="studio-form-group form-grid-full-width">
                <label for="description">Description détaillée</label>
                <textarea id="description"
                          name="description" 
                          class="studio-form-control" 
                          rows="5"
                          placeholder="Décrivez les objectifs, le déroulement ou toute information pertinente relative à cette activité..."><?= htmlspecialchars($_POST['description'] ?? $activite['description']) ?></textarea>
            </div>

            <!-- Champ : Conditions d'accès -->
            <div class="studio-form-group">
                <label for="conditions">Conditions d'accès <span class="required-mark">*</span></label>
                <select id="conditions" name="conditions" class="studio-form-control" required>
                    <option value="">-- Sélectionnez une condition --</option>
                    <?php foreach ($conditions_autorisees as $key => $label): ?>
                        <option value="<?= $key ?>"
                            <?= (($activite['conditions'] === $key && !isset($_POST['conditions'])) 
                                || (isset($_POST['conditions']) && $_POST['conditions'] === $key)) 
                                ? 'selected' : '' ?>>
                            <?= $label ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Champ : Année Académique -->
            <div class="studio-form-group">
                <label for="academic_year_id">Année académique <span class="required-mark">*</span></label>
                <select id="academic_year_id" name="academic_year_id" class="studio-form-control" required>
                    <option value="">-- Sélectionnez l'année --</option>
                    <?php foreach ($years as $y): ?>
                        <option value="<?= $y['id'] ?>"
                            <?= (($activite['academic_year_id'] == $y['id'] && !isset($_POST['academic_year_id'])) 
                                || (isset($_POST['academic_year_id']) && $_POST['academic_year_id'] == $y['id'])) 
                                ? 'selected' : '' ?>>
                            <?= htmlspecialchars($y['label']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

        </div>

        <!-- Zone de soumission -->
        <div class="form-footer-actions">
            <button type="submit" class="btn-submit-primary">
                Enregistrer les modifications
            </button>
        </div>
    </form>
</div>

<?php
$content = ob_get_clean();
include "../layout.php";
?>