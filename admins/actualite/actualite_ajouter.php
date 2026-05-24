<?php
session_start();
require_once "../../includes/db.php";

$errors = [];

$id_admin = $_SESSION['admin_id'] ?? null;
if (!$id_admin) {
    header("Location: ../login.php");
    exit;
}

$years = $pdo->query("
    SELECT id, label 
    FROM academic_years 
    ORDER BY label DESC
")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $titre              = trim($_POST['titre'] ?? '');
    $contenu            = trim($_POST['contenu'] ?? '');
    $mot_president      = trim($_POST['mot_du_president'] ?? '');
    $academic_year_id   = $_POST['academic_year_id'] ?? null;
    $statut             = $_POST['statut'] ?? 'brouillon';

    if (!$titre)            $errors[] = "Le titre est obligatoire.";
    if (!$contenu)          $errors[] = "Le contenu est obligatoire.";
    if (!$academic_year_id) $errors[] = "L'année académique est obligatoire.";

    if (empty($errors)) {
        $stmt = $pdo->prepare("
            INSERT INTO actualites 
            (id_admin, id_academic_year, titre, contenu, mot_du_president, statut)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $id_admin,
            $academic_year_id,
            $titre,
            $contenu,
            $mot_president,
            $statut
        ]);

        $_SESSION['success'] = "Actualité ajoutée avec succès.";
        header("Location: actualites.php");
        exit;
    }
}

ob_start();
?>

<style>
    :root {
        --bg-page: var(--primary-900);
        --bg-card: var(--primary-800);
        --bg-input: var(--primary-700);
        --border-color: rgba(255, 255, 255, 0.08);
    }

    body {
        background-color: var(--bg-page);
        color: var(--white);
        font-family: var(--font-primary);
    }

    .page-header-custom {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: var(--space-6);
        padding-bottom: var(--space-4);
        border-bottom: 1px solid var(--border-color);
    }

    .page-title-area h2 {
        font-size: var(--font-size-xl);
        font-weight: 600;
        color: var(--white);
        margin: 0 0 var(--space-1) 0;
    }

    .page-title-area p {
        font-size: var(--font-size-sm);
        color: var(--gray-400);
        margin: 0;
    }

    .btn-custom {
        display: inline-flex;
        align-items: center;
        gap: var(--space-2);
        padding: var(--space-3) var(--space-5);
        font-size: var(--font-size-sm);
        font-weight: 500;
        border-radius: var(--radius-md);
        transition: all var(--transition-fast);
        cursor: pointer;
        border: none;
        text-decoration: none;
    }

    .btn-back {
        background-color: transparent;
        color: var(--gray-300);
        border: 1px solid var(--border-color);
    }

    .btn-back:hover {
        background-color: var(--primary-700);
        color: var(--white);
    }

    .btn-save {
        background-color: var(--accent-blue);
        color: var(--white);
        box-shadow: 0 4px 12px rgba(46, 134, 222, 0.3);
    }

    .btn-save:hover {
        transform: translateY(-1px);
        box-shadow: 0 6px 16px rgba(46, 134, 222, 0.4);
        filter: brightness(1.1);
    }

    .dashboard-grid {
        display: grid;
        grid-template-columns: 1fr 320px;
        gap: var(--space-5);
        align-items: start;
    }

    @media (max-width: 992px) {
        .dashboard-grid {
            grid-template-columns: 1fr;
        }
    }

    .form-card {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: var(--radius-lg);
        padding: var(--space-5);
        box-shadow: var(--shadow-lg);
    }

    .card-title {
        font-size: var(--font-size-md);
        font-weight: 600;
        margin-top: 0;
        margin-bottom: var(--space-4);
        color: var(--gray-100);
        border-bottom: 1px solid var(--border-color);
        padding-bottom: var(--space-2);
    }

    .form-group-custom {
        margin-bottom: var(--space-4);
    }

    .form-group-custom label {
        display: block;
        font-size: var(--font-size-sm);
        font-weight: 500;
        color: var(--gray-300);
        margin-bottom: var(--space-2);
    }

    .form-group-custom label span {
        color: var(--accent-red);
    }

    .input-control {
        width: 100%;
        box-sizing: border-box;
        background-color: var(--bg-input);
        border: 1px solid var(--border-color);
        border-radius: var(--radius-md);
        color: var(--white);
        padding: var(--space-3) var(--space-4);
        font-size: var(--font-size-sm);
        font-family: var(--font-primary);
        transition: border-color var(--transition-fast), box-shadow var(--transition-fast);
    }

    .input-control:focus {
        outline: none;
        border-color: var(--accent-blue);
        box-shadow: 0 0 0 3px rgba(46, 134, 222, 0.15);
    }

    .alert-custom {
        background-color: rgba(255, 71, 87, 0.1);
        border: 1px solid var(--accent-red);
        border-radius: var(--radius-md);
        padding: var(--space-4);
        margin-bottom: var(--space-5);
        color: #ff6b81;
    }

    .alert-custom ul {
        margin: 0;
        padding-left: var(--space-4);
    }

    .alert-custom li {
        font-size: var(--font-size-sm);
    }
</style>

<div class="container-fluid">

    <div class="page-header-custom">
        <div class="page-title-area">
            <h2>Ajouter une actualité</h2>
            <p>Publiez un communiqué ou une info clé pour l'année académique</p>
        </div>
        <a href="actualites.php" class="btn-custom btn-back">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
            Retour à la liste
        </a>
    </div>

    <?php if ($errors): ?>
    <div class="alert-custom">
        <ul>
            <?php foreach ($errors as $e): ?>
                <li><?= htmlspecialchars($e) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <form method="POST">
        <div class="dashboard-grid">
            
            <div class="form-card">
                <div class="form-group-custom">
                    <label for="titre">Titre de l'actualité <span>*</span></label>
                    <input type="text" id="titre" name="titre" class="input-control" 
                           placeholder="Ex: Lancement des inscriptions pour l'année..."
                           value="<?= htmlspecialchars($_POST['titre'] ?? '') ?>" required>
                </div>

                <div class="form-group-custom">
                    <label for="contenu">Contenu principal <span>*</span></label>
                    <textarea id="contenu" name="contenu" class="input-control" rows="8" 
                              placeholder="Rédigez le corps de votre actualité ici..." required><?= htmlspecialchars($_POST['contenu'] ?? '') ?></textarea>
                </div>

                <div class="form-group-custom" style="margin-bottom: 0;">
                    <label for="mot_du_president">Le mot du Président</label>
                    <textarea id="mot_du_president" name="mot_du_president" class="input-control" rows="4" 
                              placeholder="Note ou message additionnel de la présidence..."><?= htmlspecialchars($_POST['mot_du_president'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="space-sidebar">
                <div class="form-card">
                    <h3 class="card-title">Configurations</h3>

                    <div class="form-group-custom">
                        <label for="academic_year_id">Année académique <span>*</span></label>
                        <select id="academic_year_id" name="academic_year_id" class="input-control" required>
                            <option value="">-- Choisir l'année --</option>
                            <?php foreach ($years as $y): ?>
                                <option value="<?= $y['id'] ?>" <?= (($_POST['academic_year_id'] ?? '') == $y['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($y['label']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group-custom" style="margin-bottom: var(--space-5);">
                        <label for="statut">Statut de publication <span>*</span></label>
                        <select id="statut" name="statut" class="input-control" required>
                            <option value="brouillon" <?= (($_POST['statut'] ?? '') === 'brouillon') ? 'selected' : '' ?>>📁 Brouillon</option>
                            <option value="publie" <?= (($_POST['statut'] ?? '') === 'publie') ? 'selected' : '' ?>>🚀 Publié</option>
                            <option value="archive" <?= (($_POST['statut'] ?? '') === 'archive') ? 'selected' : '' ?>>📦 Archivé</option>
                        </select>
                    </div>

                    <button type="submit" class="btn-custom btn-save" style="width: 100%; justify-content: center;">
                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
                        Enregistrer l'actualité
                    </button>
                </div>
            </div>

        </div>
    </form>
</div>

<?php
$content = ob_get_clean();
include "../layout.php";
?>