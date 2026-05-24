<?php
session_start();
require_once '../../includes/db.php';

$errors = [];
$nom_matiere = '';
$id_filiere = '';
$description = '';

// Récupération des filières pour le menu déroulant
$filieres = $pdo->query("SELECT id_filiere, nom_filiere FROM filieres ORDER BY nom_filiere")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom_matiere = trim($_POST['nom_matiere']);
    $id_filiere = $_POST['id_filiere'];
    $description = trim($_POST['description']);

    if (empty($nom_matiere)) {
        $errors[] = "Le nom de la matière est obligatoire.";
    }
    if (empty($id_filiere)) {
        $errors[] = "La filière est obligatoire.";
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("INSERT INTO matiere_epreuves (nom_matiere, id_filiere, description) VALUES (:nom, :filiere, :desc)");
        $stmt->execute([
            ':nom'     => $nom_matiere,
            ':filiere' => $id_filiere,
            ':desc'    => $description
        ]);
        header("Location: matieres.php?added=1"); 
        exit;
    }
}

ob_start();
?>

<style>
    /* -------------------------------------------------------------------------- */
    /* STRUCTURE GLOBALE & CONTENEUR                                              */
    /* -------------------------------------------------------------------------- */
    .form-page-container {
        font-family: var(--font-primary);
        max-width: 720px;
        margin: 0 auto;
        padding: var(--space-5) var(--space-4);
        box-sizing: border-box;
    }

    /* En-tête de la page */
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
    /* CARTES DES ERREURS D'ENTRÉE                                                */
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
    /* BOÎTE DU FORMULAIRE PREMIUM                                                */
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

    /* Accentuation supérieure typique des interfaces SaaS */
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

    /* -------------------------------------------------------------------------- */
    /* INPUTS, SELECTS ET TEXTAREAS SUR MESURE                                    */
    /* -------------------------------------------------------------------------- */
    .custom-input-text,
    .custom-select,
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

    /* Spécificités du Select (Nettoyage de la flèche native pour icône épurée) */
    .custom-select {
        appearance: none;
        -webkit-appearance: none;
        -moz-appearance: none;
        background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='24' height='24' viewBox='0 0 24 24' fill='none' stroke='%23a4b0be' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><polyline points='6 9 12 15 18 9'></polyline></svg>");
        background-repeat: no-repeat;
        background-position: right var(--space-4) center;
        background-size: 16px;
        padding-right: var(--space-6);
        cursor: pointer;
    }

    .custom-textarea {
        min-height: 120px;
        resize: vertical;
        line-height: 1.5;
    }

    /* États Hover & Focus Uniformes */
    .custom-input-text:hover,
    .custom-select:hover,
    .custom-textarea:hover {
        border-color: var(--primary-600);
    }

    .custom-input-text:focus,
    .custom-select:focus,
    .custom-textarea:focus {
        outline: none;
        border-color: var(--accent-blue);
        box-shadow: 0 0 0 4px rgba(46, 134, 222, 0.15);
        background-color: var(--primary-800);
    }

    /* Styles des options à l'intérieur du select */
    .custom-select option {
        background-color: var(--primary-900);
        color: var(--white);
    }

    .custom-select option:disabled {
        color: var(--gray-400);
    }

    /* placeholders */
    .custom-input-text::placeholder,
    .custom-textarea::placeholder {
        color: var(--gray-400);
        opacity: 0.5;
    }

    /* -------------------------------------------------------------------------- */
    /* ACTIONS DE BAS DE PAGE (BOUTONS)                                           */
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
    /* ASSURANCE RESPONSIVE                                                       */
    /* -------------------------------------------------------------------------- */
    @media (max-width: 576px) {
        .form-page-container {
            padding: var(--space-3) var(--space-2);
        }

        .premium-form-card {
            padding: var(--space-4) var(--space-3);
        }

        .form-actions-wrapper {
            flex-direction: column-reverse;
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
        <a href="liste_matieres.php" class="btn-back-link">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
            Retour à la liste
        </a>
        <h2 class="page-main-title">Ajouter une matière</h2>
    </div>

    <!-- Affichage dynamique des erreurs -->
    <?php if (!empty($errors)): ?>
        <div class="error-alert-card">
            <ul class="error-alert-list">
                <?php foreach ($errors as $e): ?>
                    <li><?= htmlspecialchars($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Formulaire d'insertion -->
    <div class="premium-form-card">
        <form method="POST" class="form-flex-layout">
            
            <!-- Champ : Nom de la matière -->
            <div class="form-field-group">
                <label class="custom-field-label">Nom de la matière<span class="required-mark">*</span></label>
                <input type="text" name="nom_matiere" class="custom-input-text" value="<?= htmlspecialchars($nom_matiere) ?>" placeholder="Ex: Algorithmique avancée, Base de données..." required autocomplete="off">
            </div>

            <!-- Champ : Choix de la Filière -->
            <div class="form-field-group">
                <label class="custom-field-label">Filière associée<span class="required-mark">*</span></label>
                <select name="id_filiere" class="custom-select" required>
                    <option value="" disabled <?= empty($id_filiere) ? 'selected' : '' ?>>-- Sélectionner la filière cible --</option>
                    <?php foreach ($filieres as $f): ?>
                        <option value="<?= $f['id_filiere'] ?>" <?= $id_filiere == $f['id_filiere'] ? "selected" : "" ?>>
                            <?= htmlspecialchars($f['nom_filiere']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Champ : Description -->
            <div class="form-field-group">
                <label class="custom-field-label">Description <span style="color: var(--gray-400); font-weight: 400;">(facultatif)</span></label>
                <textarea name="description" class="custom-textarea" placeholder="Détails optionnels sur le programme, les coefficients ou les objectifs du cours..."><?= htmlspecialchars($description) ?></textarea>
            </div>

            <!-- Actions de validation/annulation -->
            <div class="form-actions-wrapper">
                <a href="liste_matieres.php" class="btn-premium-cancel">Annuler</a>
                <button type="submit" class="btn-premium-submit">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                    Enregistrer la matière
                </button>
            </div>

        </form>
    </div>

</div>

<?php
$content = ob_get_clean();
include '../layout.php';
?>