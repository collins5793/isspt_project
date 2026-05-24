<?php
session_start();
require_once '../../includes/db.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: liste_categories.php"); 
    exit;
}

$id_category = (int)$_GET['id'];
$errors = [];

$stmt = $pdo->prepare("SELECT * FROM epreuves_categories WHERE id_category = :id");
$stmt->execute([':id' => $id_category]);
$categorie = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$categorie) {
    die("Catégorie introuvable.");
}

$nom_category = $categorie['nom_category'];
$description = $categorie['description'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom_category = trim($_POST['nom_category']);
    $description = trim($_POST['description']);

    if (empty($nom_category)) {
        $errors[] = "Le nom de la catégorie est obligatoire.";
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("UPDATE epreuves_categories SET nom_category = :nom, description = :desc WHERE id_category = :id");
        $stmt->execute([
            ':nom'  => $nom_category, 
            ':desc' => $description, 
            ':id'   => $id_category
        ]);
        header("Location: liste_categories.php?updated=1"); 
        exit;
    }
}

// ---- Début de la capture du contenu pour le layout ----
ob_start();
?>

<style>
    /* Container principal */
    .form-dashboard-wrapper {
        font-family: var(--font-primary);
        color: var(--white);
        max-width: 800px;
        margin: 0 auto;
        padding: var(--space-4) var(--space-3);
    }

    /* En-tête de page moderne */
    .form-hero-header {
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

    .form-hero-header::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 4px;
        height: 100%;
        background: var(--accent-blue);
    }

    .form-header-icon {
        background-color: rgba(46, 134, 222, 0.1);
        border: 1px solid rgba(46, 134, 222, 0.2);
        padding: var(--space-3);
        border-radius: var(--radius-md);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--accent-blue);
    }

    .form-header-text h1 {
        font-size: calc(var(--font-size-xl) * 1.2);
        font-weight: 700;
        margin: 0 0 var(--space-1) 0;
        letter-spacing: -0.01em;
    }

    .form-header-text p {
        color: var(--gray-400);
        font-size: var(--font-size-sm);
        margin: 0;
    }

    /* Panneau du Formulaire */
    .premium-form-card {
        background-color: var(--primary-800);
        border: var(--sidebar-border);
        border-radius: var(--radius-lg);
        padding: var(--space-5);
        box-shadow: var(--shadow-xl);
    }

    .form-structural-group {
        margin-bottom: var(--space-4);
    }

    .custom-form-label {
        display: block;
        font-size: var(--font-size-sm);
        font-weight: 600;
        color: var(--gray-200);
        margin-bottom: var(--space-2);
        letter-spacing: 0.02em;
    }

    .custom-form-label span {
        color: var(--accent-red);
        margin-left: var(--space-1);
    }

    /* Éléments de champs saisis */
    .premium-input-field, .premium-textarea-field {
        width: 100%;
        box-sizing: border-box;
        font-family: var(--font-primary);
        font-size: var(--font-size-md);
        background-color: var(--primary-900);
        border: 1px solid var(--primary-600);
        border-radius: var(--radius-md);
        color: var(--white);
        padding: var(--space-3);
        transition: border-color var(--transition-fast), box-shadow var(--transition-fast);
    }

    .premium-input-field {
        height: 46px;
    }

    .premium-textarea-field {
        min-height: 120px;
        resize: vertical;
    }

    .premium-input-field:focus, .premium-textarea-field:focus {
        outline: none;
        border-color: var(--accent-blue);
        box-shadow: 0 0 0 3px rgba(46, 134, 222, 0.15);
    }

    /* Boutons de contrôle */
    .form-action-cluster {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: var(--space-3);
        margin-top: var(--space-5);
        padding-top: var(--space-4);
        border-top: 1px solid rgba(255, 255, 255, 0.05);
    }

    .btn-action-base {
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

    .btn-submit-save {
        background-color: var(--accent-green);
        color: var(--white);
        box-shadow: 0 4px 12px rgba(16, 172, 132, 0.2);
    }
    .btn-submit-save:hover {
        background-color: #0d9270;
        transform: translateY(-1px);
    }

    .btn-cancel-return {
        background-color: transparent;
        color: var(--gray-400);
        border: 1px solid var(--primary-600);
    }
    .btn-cancel-return:hover {
        background-color: rgba(255, 255, 255, 0.03);
        color: var(--white);
        border-color: var(--gray-400);
    }

    /* Traitement des Alertes d'Erreurs */
    .premium-alert-box {
        background-color: rgba(255, 71, 87, 0.1);
        border: 1px solid rgba(255, 71, 87, 0.2);
        border-radius: var(--radius-md);
        padding: var(--space-4);
        margin-bottom: var(--space-4);
        display: flex;
        gap: var(--space-3);
        align-items: flex-start;
    }

    .premium-alert-box svg {
        color: var(--accent-red);
        flex-shrink: 0;
        margin-top: 2px;
    }

    .premium-alert-box ul {
        margin: 0;
        padding-left: var(--space-4);
        color: var(--white);
        font-size: var(--font-size-sm);
        line-height: 1.5;
    }

    /* Responsivité fluide */
    @media (max-width: 576px) {
        .form-hero-header {
            flex-direction: column;
            align-items: flex-start;
            padding: var(--space-4);
        }
        
        .premium-form-card {
            padding: var(--space-4);
        }

        .form-action-cluster {
            flex-direction: column-reverse;
            align-items: stretch;
        }

        .form-action-cluster .btn-action-base {
            width: 100%;
        }
    }
</style>

<div class="form-dashboard-wrapper">

    <!-- En-tête contextuel -->
    <header class="form-hero-header">
        <div class="form-header-icon">
            <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                <path d="M18.5 2.5a2.121 2.121 0 1 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
            </svg>
        </div>
        <div class="form-header-text">
            <h1>Modifier la catégorie</h1>
            <p>Mettez à jour les informations relatives à la catégorie : <span style="color: var(--accent-blue); font-weight: 600;"><?= htmlspecialchars($nom_category) ?></span></p>
        </div>
    </header>

    <!-- Affichage des erreurs de validation -->
    <?php if(!empty($errors)): ?>
        <div class="premium-alert-box">
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

    <!-- Corps du Formulaire -->
    <div class="premium-form-card">
        <form method="POST">
            
            <div class="form-structural-group">
                <label class="custom-form-label" for="nom_category">
                    Nom de la catégorie <span>*</span>
                </label>
                <input type="text" id="nom_category" name="nom_category" 
                       class="premium-input-field" 
                       value="<?= htmlspecialchars($nom_category) ?>" required autocomplete="off">
            </div>

            <div class="form-structural-group">
                <label class="custom-form-label" for="description">
                    Description / Note contextuelle <span style="color: var(--gray-400); font-weight: normal;">(facultatif)</span>
                </label>
                <textarea id="description" name="description" 
                          class="premium-textarea-field" 
                          placeholder="Saisissez une brève description pour expliciter l'usage de cette catégorie..."><?= htmlspecialchars($description) ?></textarea>
            </div>

            <!-- Boutons de validation d'action -->
            <div class="form-action-cluster">
                <a href="liste_categories.php" class="btn-action-base btn-cancel-return">
                    Annuler
                </a>
                <button type="submit" class="btn-action-base btn-submit-save">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
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
include '../layout.php';
?>