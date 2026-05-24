<?php
session_start();
require_once '../../includes/db.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: liste_filieres.php"); 
    exit;
}

$id_filiere = (int)$_GET['id'];
$errors = [];

$stmt = $pdo->prepare("SELECT * FROM filieres WHERE id_filiere=:id");
$stmt->execute([':id'=>$id_filiere]);
$filiere = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$filiere) die("Filière introuvable.");

$nom_filiere = $filiere['nom_filiere'];
$description = $filiere['description'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom_filiere = trim($_POST['nom_filiere']);
    $description = trim($_POST['description']);

    if (empty($nom_filiere)) {
        $errors[] = "Le nom de la filière est obligatoire.";
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("UPDATE filieres SET nom_filiere=:nom, description=:desc WHERE id_filiere=:id");
        $stmt->execute([
            ':nom'=>$nom_filiere, ':desc'=>$description, ':id'=>$id_filiere
        ]);
        header("Location: liste_filieres.php?updated=1"); 
        exit;
    }
}

ob_start();
?>

<style>
    /* Scope de la page pour éviter les conflits globaux */
    .dashboard-page-container {
        font-family: var(--font-primary);
        color: var(--white);
        max-width: 800px;
        margin: 0 auto;
        padding: var(--space-4) var(--space-3);
        box-sizing: border-box;
    }

    /* Header Design */
    .page-hero-header {
        background: linear-gradient(135deg, var(--primary-800) 0%, var(--primary-700) 100%);
        border: var(--sidebar-border);
        border-radius: var(--radius-lg);
        padding: var(--space-5) var(--space-4);
        box-shadow: var(--shadow-md);
        display: flex;
        align-items: center;
        gap: var(--space-4);
        margin-bottom: var(--space-5);
        position: relative;
        overflow: hidden;
    }

    .page-hero-header::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 4px;
        height: 100%;
        background: var(--accent-blue);
    }

    .header-icon-wrapper {
        background: rgba(46, 134, 222, 0.1);
        border: 1px solid rgba(46, 134, 222, 0.2);
        padding: var(--space-3);
        border-radius: var(--radius-md);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--accent-blue);
        flex-shrink: 0;
    }

    .header-titles h1 {
        font-size: calc(var(--font-size-xl) * 1.15);
        font-weight: 700;
        margin: 0 0 var(--space-1) 0;
        letter-spacing: -0.01em;
        color: var(--white);
    }

    .header-titles p {
        color: var(--gray-400);
        font-size: var(--font-size-sm);
        margin: 0;
    }

    /* Card Formulaire */
    .premium-form-card {
        background-color: var(--primary-800);
        border: var(--sidebar-border);
        border-radius: var(--radius-lg);
        padding: var(--space-5);
        box-shadow: var(--shadow-xl);
    }

    /* Groupes de champs */
    .form-field-group {
        margin-bottom: var(--space-4);
        display: flex;
        flex-direction: column;
        gap: var(--space-2);
    }

    .premium-label {
        font-size: var(--font-size-sm);
        font-weight: 600;
        color: var(--gray-200);
        letter-spacing: 0.01em;
    }

    .premium-label .required-mark {
        color: var(--accent-red);
        margin-left: var(--space-1);
    }

    /* Inputs & Utilitaires de saisie */
    .premium-input, .premium-textarea {
        width: 100%;
        box-sizing: border-box;
        font-family: var(--font-primary);
        font-size: var(--font-size-md);
        background-color: var(--primary-900);
        border: 1px solid var(--primary-600);
        border-radius: var(--radius-md);
        color: var(--white);
        padding: 0 var(--space-3);
        transition: border-color var(--transition-fast), box-shadow var(--transition-fast);
    }

    .premium-input {
        height: 48px;
    }

    .premium-textarea {
        padding: var(--space-3);
        min-height: 140px;
        resize: vertical;
        line-height: 1.5;
    }

    .premium-input:focus, .premium-textarea:focus {
        outline: none;
        border-color: var(--accent-blue);
        box-shadow: 0 0 0 3px rgba(46, 134, 222, 0.15);
    }

    /* Boutons d'actions */
    .form-actions-wrapper {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: var(--space-3);
        margin-top: var(--space-5);
        padding-top: var(--space-4);
        border-top: 1px solid rgba(255, 255, 255, 0.05);
    }

    .btn-premium {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: var(--space-2);
        font-family: var(--font-primary);
        font-size: var(--font-size-sm);
        font-weight: 600;
        border-radius: var(--radius-md);
        text-decoration: none;
        padding: 0 var(--space-5);
        height: 46px;
        transition: all var(--transition-fast);
        cursor: pointer;
        border: none;
        white-space: nowrap;
    }

    .btn-primary-submit {
        background-color: var(--accent-blue);
        color: var(--white);
        box-shadow: 0 4px 12px rgba(46, 134, 222, 0.2);
    }

    .btn-primary-submit:hover {
        background-color: #2475c4;
        transform: translateY(-1px);
    }

    .btn-secondary-cancel {
        background-color: transparent;
        color: var(--gray-400);
        border: 1px solid var(--primary-600);
    }

    .btn-secondary-cancel:hover {
        background-color: rgba(255, 255, 255, 0.03);
        color: var(--white);
        border-color: var(--gray-300);
    }

    /* Notifications d'erreurs */
    .premium-error-container {
        background-color: rgba(255, 71, 87, 0.08);
        border: 1px solid rgba(255, 71, 87, 0.2);
        border-radius: var(--radius-md);
        padding: var(--space-4);
        margin-bottom: var(--space-4);
        display: flex;
        gap: var(--space-3);
        align-items: flex-start;
    }

    .premium-error-container svg {
        color: var(--accent-red);
        flex-shrink: 0;
        margin-top: 2px;
    }

    .premium-error-container ul {
        margin: 0;
        padding-left: var(--space-3);
        color: var(--white);
        font-size: var(--font-size-sm);
        line-height: 1.5;
    }

    /* Breakpoints Responsifs */
    @media (max-width: 576px) {
        .page-hero-header {
            flex-direction: column;
            align-items: flex-start;
            gap: var(--space-3);
            padding: var(--space-4);
        }

        .premium-form-card {
            padding: var(--space-4);
        }

        .form-actions-wrapper {
            flex-direction: column-reverse;
            align-items: stretch;
            gap: var(--space-2);
        }

        .form-actions-wrapper .btn-premium {
            width: 100%;
        }
    }
</style>

<div class="dashboard-page-container">

    <!-- En-tête de la page -->
    <header class="page-hero-header">
        <div class="header-icon-wrapper">
            <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                <path d="M18.5 2.5a2.121 2.121 0 1 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
            </svg>
        </div>
        <div class="header-titles">
            <h1>Modifier la filière</h1>
            <p>Édition des informations de : <span style="color: var(--accent-blue); font-weight: 600;"><?= htmlspecialchars($nom_filiere) ?></span></p>
        </div>
    </header>

    <!-- Gestion propre des erreurs -->
    <?php if(!empty($errors)): ?>
        <div class="premium-error-container">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="8" x2="12" y2="12"></line>
                <line x1="12" y1="16" x2="12.01" y2="16"></line>
            </svg>
            <ul>
                <?php foreach($errors as $err): ?>
                    <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Carte du Formulaire -->
    <div class="premium-form-card">
        <form method="POST">
            
            <!-- Champ : Nom -->
            <div class="form-field-group">
                <label class="premium-label" for="nom_filiere">
                    Nom de la filière <span class="required-mark">*</span>
                </label>
                <input type="text" id="nom_filiere" name="nom_filiere" class="premium-input" value="<?= htmlspecialchars($nom_filiere) ?>" required placeholder="Ex: Informatique de Gestion" autocomplete="off">
            </div>

            <!-- Champ : Description -->
            <div class="form-field-group">
                <label class="premium-label" for="description">
                    Description <span style="color: var(--gray-400); font-weight: normal;">(facultatif)</span>
                </label>
                <textarea id="description" name="description" class="premium-textarea" placeholder="Décrivez brièvement les objectifs, les débouchés ou les spécificités de cette filière..."><?= htmlspecialchars($description) ?></textarea>
            </div>

            <!-- Actions -->
            <div class="form-actions-wrapper">
                <a href="liste_filieres.php" class="btn-premium btn-secondary-cancel">
                    Annuler
                </a>
                <button type="submit" class="btn-premium btn-primary-submit">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                        <polyline points="17 21 17 13 7 13 7 21"></polyline>
                        <polyline points="7 3 7 8 15 8"></polyline>
                    </svg>
                    Mettre à jour
                </button>
            </div>

        </form>
    </div>

</div>

<?php
$content = ob_get_clean();
include '../layout.php';
?>