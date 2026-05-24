<?php
session_start();
require_once '../../includes/db.php';

// Sécurité : Optionnelle mais fortement recommandée si vous avez un middleware admin
// if (!isset($_SESSION['admin_id'])) { header('Location: connexion_admin.php'); exit; }

$errors = [];
$nom_filiere = '';
$description = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom_filiere = trim($_POST['nom_filiere']);
    $description = trim($_POST['description']);
    
    if (empty($nom_filiere)) {
        $errors[] = "Le nom de la filière est obligatoire.";
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("INSERT INTO filieres (nom_filiere, description) VALUES (:nom, :desc)");
        $stmt->execute([
            ':nom'  => $nom_filiere,
            ':desc' => $description
        ]);
        // Redirection vers votre page de liste (ajustée selon votre logique de redirection)
        header("Location: filieres.php?added=1"); 
        exit;
    }
}

ob_start();
?>

<style>
    /* -------------------------------------------------------------------------- */
    /* STRUCTURE & WRAPPER                                                        */
    /* -------------------------------------------------------------------------- */
    .form-page-container {
        font-family: var(--font-primary);
        max-width: 720px;
        margin: 0 auto;
        padding: var(--space-5) var(--space-4);
        box-sizing: border-box;
    }

    /* En-tête de page */
    .page-header-nav {
        display: flex;
        flex-direction: column;
        gap: var(--space-2);
        margin-bottom: var(--space-5);
    }

    .btn-back-link {
        display: inline-flex;
        align-items: center;
        gap: var(--space-2);
        color: var(--gray-400);
        font-size: var(--font-size-sm);
        text-decoration: none;
        transition: color var(--transition-fast);
        width: fit-content;
    }

    .btn-back-link:hover {
        color: var(--accent-blue);
    }

    .page-main-title {
        color: var(--white);
        font-size: var(--font-size-xl);
        font-weight: 600;
        margin: 0;
        letter-spacing: -0.02em;
    }

    /* -------------------------------------------------------------------------- */
    /* ALERTES & ERREURS                                                          */
    /* -------------------------------------------------------------------------- */
    .error-alert-card {
        background: rgba(255, 71, 87, 0.04);
        border: 1px solid rgba(255, 71, 87, 0.2);
        border-radius: var(--radius-lg);
        padding: var(--space-4);
        margin-bottom: var(--space-5);
        box-shadow: var(--shadow-sm);
    }

    .error-alert-list {
        margin: 0;
        padding-left: var(--space-4);
        color: var(--accent-red);
        font-size: var(--font-size-sm);
        display: flex;
        flex-direction: column;
        gap: var(--space-1);
    }

    /* -------------------------------------------------------------------------- */
    /* CARTE DU FORMULAIRE COMPLÈTE                                              */
    /* -------------------------------------------------------------------------- */
    .premium-form-card {
        background: var(--primary-800);
        border: var(--sidebar-border);
        border-radius: var(--radius-lg);
        padding: var(--space-5);
        box-shadow: var(--shadow-xl);
        position: relative;
        overflow: hidden;
    }

    /* Ligne décorative subtile rappelant l'univers SaaS */
    .premium-form-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: linear-gradient(90deg, var(--accent-blue) 0%, var(--primary-600) 100%);
    }

    .form-flex-layout {
        display: flex;
        flex-direction: column;
        gap: var(--space-4);
    }

    /* Éléments de champs */
    .form-field-group {
        display: flex;
        flex-direction: column;
        gap: var(--space-2);
    }

    .custom-field-label {
        color: var(--gray-300);
        font-size: var(--font-size-sm);
        font-weight: 500;
        letter-spacing: 0.01em;
    }

    .custom-field-label span.required-mark {
        color: var(--accent-red);
        margin-left: var(--space-1);
    }

    /* Inputs & Textareas */
    .custom-input-text,
    .custom-textarea {
        width: 100%;
        box-sizing: border-box;
        font-family: var(--font-primary);
        font-size: var(--font-size-md);
        background-color: var(--primary-900);
        border: 1px solid var(--primary-700);
        border-radius: var(--radius-md);
        color: var(--white);
        padding: var(--space-3) var(--space-4);
        transition: border-color var(--transition-fast), box-shadow var(--transition-fast), background-color var(--transition-fast);
    }

    .custom-textarea {
        min-height: 140px;
        resize: vertical;
        line-height: 1.5;
    }

    .custom-input-text:hover,
    .custom-textarea:hover {
        border-color: var(--primary-600);
    }

    .custom-input-text:focus,
    .custom-textarea:focus {
        outline: none;
        border-color: var(--accent-blue);
        box-shadow: 0 0 0 4px rgba(46, 134, 222, 0.15);
        background-color: var(--primary-800);
    }

    /* Placeholder styling */
    .custom-input-text::placeholder,
    .custom-textarea::placeholder {
        color: var(--gray-400);
        opacity: 0.6;
    }

    /* -------------------------------------------------------------------------- */
    /* ZONE D'ACTIONS / BOUTONS                                                   */
    /* -------------------------------------------------------------------------- */
    .form-actions-wrapper {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: var(--space-3);
        margin-top: var(--space-3);
        border-top: 1px solid rgba(255, 255, 255, 0.05);
        padding-top: var(--space-4);
    }

    .btn-premium-submit {
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

    .btn-premium-submit:hover {
        background-color: #2475c4;
        box-shadow: 0 6px 16px rgba(46, 134, 222, 0.3);
        transform: translateY(-1px);
    }

    .btn-premium-submit:active {
        transform: translateY(0);
    }

    .btn-premium-cancel {
        color: var(--gray-400);
        font-size: var(--font-size-sm);
        font-weight: 500;
        text-decoration: none;
        padding: var(--space-3) var(--space-4);
        border-radius: var(--radius-md);
        transition: color var(--transition-fast), background-color var(--transition-fast);
    }

    .btn-premium-cancel:hover {
        color: var(--white);
        background-color: rgba(255, 255, 255, 0.03);
    }

    /* -------------------------------------------------------------------------- */
    /* RESPONSIVITÉ SANS FAILLE                                                   */
    /* -------------------------------------------------------------------------- */
    @media (max-width: 576px) {
        .form-page-container {
            padding: var(--space-3) var(--space-2);
        }

        .premium-form-card {
            padding: var(--space-4) var(--space-3);
        }

        .form-actions-wrapper {
            flex-direction: column-reverse; /* Met le bouton Annuler en dessous sur mobile */
            align-items: stretch;
            gap: var(--space-2);
        }

        .btn-premium-submit,
        .btn-premium-cancel {
            justify-content: center;
            text-align: center;
            width: 100%;
            box-sizing: border-box;
        }
    }
</style>

<div class="form-page-container">

    <!-- En-tête de navigation -->
    <div class="page-header-nav">
        <a href="liste_filieres.php" class="btn-back-link">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
            Retour à la liste
        </a>
        <h2 class="page-main-title">Ajouter une filière</h2>
    </div>

    <!-- Gestion des erreurs -->
    <?php if (!empty($errors)): ?>
        <div class="error-alert-card">
            <ul class="error-alert-list">
                <?php foreach ($errors as $e): ?>
                    <li><?= htmlspecialchars($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Conteneur Formulaire -->
    <div class="premium-form-card">
        <form method="POST" class="form-flex-layout">
            
            <!-- Champ : Nom -->
            <div class="form-field-group">
                <label class="custom-field-label">Nom de la filière<span class="required-mark">*</span></label>
                <input type="text" name="nom_filiere" class="custom-input-text" value="<?= htmlspecialchars($nom_filiere) ?>" placeholder="Ex: Génie Logiciel, Data Science..." required autocomplete="off">
            </div>

            <!-- Champ : Description -->
            <div class="form-field-group">
                <label class="custom-field-label">Description <span style="color: var(--gray-400); font-weight: 400;">(facultatif)</span></label>
                <textarea name="description" class="custom-textarea" placeholder="Présentation globale des objectifs de la filière et des compétences visées..."><?= htmlspecialchars($description) ?></textarea>
            </div>

            <!-- Actions -->
            <div class="form-actions-wrapper">
                <a href="liste_filieres.php" class="btn-premium-cancel">Annuler</a>
                <button type="submit" class="btn-premium-submit">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                    Enregistrer la filière
                </button>
            </div>

        </form>
    </div>

</div>

<?php
$content = ob_get_clean();
include '../layout.php';
?>