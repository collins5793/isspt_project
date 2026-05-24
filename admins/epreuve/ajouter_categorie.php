<?php
session_start();
require_once '../../includes/db.php';

// Sécurité : Vérifier si l'admin est connecté
if (!isset($_SESSION['admin_id'])) {
    header('Location: connexion_admin.php');
    exit;
}

$errors = [];
$nom_category = '';
$description = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom_category = trim($_POST['nom_category']);
    $description = trim($_POST['description']);
    
    if (empty($nom_category)) {
        $errors[] = "Le nom de la catégorie est obligatoire.";
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("INSERT INTO epreuves_categories (nom_category, description, created_by) VALUES (:nom, :desc, :admin)");
        $stmt->execute([
            ':nom' => $nom_category,
            ':desc' => $description,
            ':admin' => $_SESSION['admin_id']
        ]);
        header("Location: categories.php?added=1"); 
        exit;
    }
}

ob_start();
?>

<style>
    /* Reset local pour intégration fluide */
    .dashboard-container {
        font-family: var(--font-primary);
        max-width: 720px;
        margin: 0 auto;
        padding: var(--space-4) var(--space-3);
    }

    /* En-tête de la page */
    .page-header-block {
        display: flex;
        flex-direction: column;
        gap: var(--space-2);
        margin-bottom: var(--space-5);
    }

    .back-nav-link {
        display: inline-flex;
        align-items: center;
        gap: var(--space-2);
        color: var(--gray-400);
        font-size: var(--font-size-sm);
        text-decoration: none;
        transition: color var(--transition-fast);
        width: fit-content;
    }

    .back-nav-link:hover {
        color: var(--accent-blue);
    }

    .page-title-main {
        color: var(--white);
        font-size: var(--font-size-xl);
        font-weight: 600;
        margin: 0;
        letter-spacing: -0.02em;
    }

    /* Messages d'erreur */
    .error-box {
        background: rgba(255, 71, 87, 0.06);
        border: 1px solid rgba(255, 71, 87, 0.3);
        border-radius: var(--radius-lg);
        padding: var(--space-4);
        margin-bottom: var(--space-5);
        box-shadow: var(--shadow-sm);
    }

    .error-list {
        margin: 0;
        padding-left: var(--space-4);
        color: var(--accent-red);
        font-size: var(--font-size-sm);
        display: flex;
        flex-direction: column;
        gap: var(--space-1);
    }

    /* Carte du Formulaire - Esthétique Premium Dashboard */
    .form-premium-card {
        background: var(--primary-800);
        border: var(--sidebar-border);
        border-radius: var(--radius-lg);
        padding: var(--space-5);
        box-shadow: var(--shadow-xl);
        position: relative;
        overflow: hidden;
    }

    /* Effet visuel discret sur le haut de la carte */
    .form-premium-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: linear-gradient(90deg, var(--accent-blue) 0%, var(--primary-600) 100%);
    }

    .form-layout {
        display: flex;
        flex-direction: column;
        gap: var(--space-5);
    }

    .field-wrapper {
        display: flex;
        flex-direction: column;
        gap: var(--space-2);
    }

    .field-label {
        color: var(--gray-300);
        font-size: var(--font-size-sm);
        font-weight: 500;
        letter-spacing: 0.01em;
    }

    .field-label span {
        color: var(--accent-red);
        margin-left: var(--space-1);
    }

    /* Éléments de saisie personnalisés */
    .input-text-custom, 
    .textarea-custom {
        width: 100%;
        box-sizing: border-box;
        font-family: var(--font-primary);
        font-size: var(--font-size-md);
        background-color: var(--primary-900);
        border: 1px solid var(--primary-700);
        border-radius: var(--radius-md);
        color: var(--white);
        padding: var(--space-3) var(--space-4);
        transition: border-color var(--transition-base), box-shadow var(--transition-base);
    }

    .textarea-custom {
        min-height: 140px;
        resize: vertical;
    }

    /* États Interactifs */
    .input-text-custom:hover, 
    .textarea-custom:hover {
        border-color: var(--primary-600);
    }

    .input-text-custom:focus, 
    .textarea-custom:focus {
        outline: none;
        border-color: var(--accent-blue);
        box-shadow: 0 0 0 4px rgba(46, 134, 222, 0.15);
        background-color: var(--primary-800);
    }

    /* Actions et Boutons */
    .form-actions-group {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: var(--space-4);
        margin-top: var(--space-2);
        border-top: 1px solid rgba(255, 255, 255, 0.03);
        padding-top: var(--space-4);
    }

    .btn-action-submit {
        font-family: var(--font-primary);
        background-color: var(--accent-blue);
        color: var(--white);
        font-size: var(--font-size-sm);
        font-weight: 600;
        border: none;
        border-radius: var(--radius-md);
        padding: var(--space-3) var(--space-5);
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: var(--space-2);
        box-shadow: 0 4px 12px rgba(46, 134, 222, 0.2);
        transition: background-color var(--transition-fast), transform var(--transition-fast), box-shadow var(--transition-fast);
    }

    .btn-action-submit:hover {
        background-color: #2475c4;
        box-shadow: 0 6px 16px rgba(46, 134, 222, 0.3);
        transform: translateY(-1px);
    }

    .btn-action-submit:active {
        transform: translateY(0);
    }

    .btn-action-cancel {
        color: var(--gray-400);
        font-size: var(--font-size-sm);
        font-weight: 500;
        text-decoration: none;
        padding: var(--space-3) var(--space-4);
        border-radius: var(--radius-md);
        transition: color var(--transition-fast), background-color var(--transition-fast);
    }

    .btn-action-cancel:hover {
        color: var(--white);
        background-color: rgba(255, 255, 255, 0.03);
    }

    /* Version Mobile / Écrans tactiles */
    @media (max-width: 576px) {
        .dashboard-container {
            padding: var(--space-3) var(--space-2);
        }
        
        .form-premium-card {
            padding: var(--space-4);
        }

        .form-actions-group {
            flex-direction: column-reverse;
            align-items: stretch;
            gap: var(--space-2);
        }

        .btn-action-submit,
        .btn-action-cancel {
            justify-content: center;
            text-align: center;
            width: 100%;
        }
    }
</style>

<div class="dashboard-container">
    
    <!-- En-tête -->
    <div class="page-header-block">
        <a href="categories.php" class="back-nav-link">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
            Retour aux catégories
        </a>
        <h2 class="page-title-main">Ajouter une catégorie d’épreuve</h2>
    </div>

    <!-- Gestion des erreurs -->
    <?php if (!empty($errors)): ?>
        <div class="error-box">
            <ul class="error-list">
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Corps de formulaire Pro -->
    <div class="form-premium-card">
        <form method="POST" class="form-layout">
            
            <div class="field-wrapper">
                <label class="field-label">Nom de la catégorie <span>*</span></label>
                <input 
                    type="text" 
                    name="nom_category" 
                    class="input-text-custom" 
                    placeholder="Ex: Épreuves Écrites, Tests Pratiques..." 
                    value="<?= htmlspecialchars($nom_category) ?>" 
                    required 
                    autocomplete="off"
                >
            </div>

            <div class="field-wrapper">
                <label class="field-label">Description <span style="color: var(--gray-400); font-weight: 400;">(facultatif)</span></label>
                <textarea 
                    name="description" 
                    class="textarea-custom" 
                    placeholder="Ajoutez des détails ou précisions sur l'usage de cette catégorie..."
                ><?= htmlspecialchars($description) ?></textarea>
            </div>

            <div class="form-actions-group">
                <a href="categories.php" class="btn-action-cancel">Annuler</a>
                <button type="submit" class="btn-action-submit">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                    Enregistrer la catégorie
                </button>
            </div>

        </form>
    </div>
</div>

<?php
$content = ob_get_clean();
include '../layout.php';
?>