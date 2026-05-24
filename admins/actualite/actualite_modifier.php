<?php
session_start();
require_once "../../includes/db.php";

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit;
}

$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: actualites.php");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM actualites WHERE id = ?");
$stmt->execute([$id]);
$actualite = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$actualite) {
    $_SESSION['error'] = "Actualité introuvable.";
    header("Location: actualites.php");
    exit;
}

$errors = [];

$years = $pdo->query("
    SELECT id, label 
    FROM academic_years 
    ORDER BY label DESC
")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $titre            = trim($_POST['titre'] ?? '');
    $contenu          = trim($_POST['contenu'] ?? '');
    $mot_president    = trim($_POST['mot_du_president'] ?? '');
    $academic_year_id = $_POST['academic_year_id'] ?? null;
    $statut           = $_POST['statut'] ?? 'brouillon';

    if (!$titre)            $errors[] = "Le titre est obligatoire.";
    if (!$contenu)          $errors[] = "Le contenu est obligatoire.";
    if (!$academic_year_id) $errors[] = "L'année académique est obligatoire.";

    if (empty($errors)) {
        $stmt = $pdo->prepare("
            UPDATE actualites
            SET titre = ?, 
                contenu = ?, 
                mot_du_president = ?, 
                id_academic_year = ?, 
                statut = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $titre,
            $contenu,
            $mot_president,
            $academic_year_id,
            $statut,
            $id
        ]);

        $_SESSION['success'] = "Actualité modifiée avec succès.";
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
        --bg-input-focus: var(--primary-600);
        --border-glass: rgba(255, 255, 255, 0.06);
        --border-focus: var(--accent-blue);
    }

    body {
        background-color: var(--bg-page);
        color: var(--gray-100);
        font-family: var(--font-primary);
    }

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
        letter-spacing: -0.5px;
    }

    .alert-container {
        background-color: rgba(255, 71, 87, 0.1);
        border: 1px solid rgba(255, 71, 87, 0.2);
        border-left: 4px solid var(--accent-red);
        border-radius: var(--radius-md);
        padding: var(--space-4);
        margin-bottom: var(--space-5);
    }

    .alert-container ul {
        margin: 0;
        padding-left: var(--space-4);
        color: #ff6b81;
        font-size: var(--font-size-sm);
    }

    .editor-grid {
        display: grid;
        grid-template-columns: 1fr 320px;
        gap: var(--space-5);
        align-items: start;
    }

    @media (max-width: 992px) {
        .editor-grid {
            grid-template-columns: 1fr;
        }
    }

    .card-panel {
        background-color: var(--bg-card);
        border: 1px solid var(--border-glass);
        border-radius: var(--radius-lg);
        padding: var(--space-5);
        box-shadow: var(--shadow-xl);
    }

    .panel-title {
        font-size: var(--font-size-sm);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: var(--gray-400);
        font-weight: 700;
        margin-top: 0;
        margin-bottom: var(--space-4);
        padding-bottom: var(--space-2);
        border-bottom: 1px solid var(--border-glass);
    }

    .form-group {
        margin-bottom: var(--space-4);
    }

    .form-group:last-child {
        margin-bottom: 0;
    }

    .form-group label {
        display: block;
        font-size: var(--font-size-sm);
        font-weight: 600;
        color: var(--gray-200);
        margin-bottom: var(--space-2);
    }

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

    textarea.form-control {
        resize: vertical;
        line-height: 1.6;
    }

    select.form-control {
        appearance: none;
        background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='24' height='24' fill='none' stroke='%23a4b0be' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><polyline points='6 9 12 15 18 9'></polyline></svg>");
        background-repeat: no-repeat;
        background-position: right var(--space-3) center;
        background-size: 16px;
        padding-right: var(--space-5);
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
        padding: var(--space-2) var(--space-4);
        transition: all var(--transition-fast);
        cursor: pointer;
        border: none;
        width: auto;
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

    .btn-primary {
        background-color: var(--accent-blue);
        color: var(--white);
        box-shadow: var(--shadow-md);
        font-weight: 600;
    }

    .btn-primary:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(46, 134, 222, 0.3);
    }
    
    .btn-submit-container {
        margin-top: var(--space-4);
    }
</style>

<div class="container-fluid">

    <div class="page-header">
        <h2>Modifier l’actualité</h2>
        <a href="actualites.php" class="btn-custom btn-secondary">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
            Annuler
        </a>
    </div>

    <?php if ($errors): ?>
    <div class="alert-container">
        <ul>
            <?php foreach ($errors as $e): ?>
                <li><?= htmlspecialchars($e) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <form method="POST">
        <div class="editor-grid">
            
            <div class="card-panel">
                <div class="form-group">
                    <label for="titre">Titre du communiqué *</label>
                    <input type="text" id="titre" name="titre" class="form-control"
                           value="<?= htmlspecialchars($_POST['titre'] ?? $actualite['titre']) ?>" required placeholder="Ex: Cérémonie de remise des diplômes...">
                </div>

                <div class="form-group">
                    <label for="contenu">Corps de l'actualité *</label>
                    <textarea id="contenu" name="contenu" class="form-control" rows="8" required placeholder="Saisissez le contenu principal ici..."><?= htmlspecialchars($_POST['contenu'] ?? $actualite['contenu']) ?></textarea>
                </div>

                <div class="form-group">
                    <label for="mot_du_president">Mot du Président (Optionnel)</label>
                    <textarea id="mot_du_president" name="mot_du_president" class="form-control" rows="4" placeholder="Citation ou message d'accompagnement de la présidence..."><?= htmlspecialchars($_POST['mot_du_president'] ?? $actualite['mot_du_president']) ?></textarea>
                </div>
            </div>

            <div class="editor-sidebar-layout" style="display: flex; flex-direction: column; gap: var(--space-4);">
                
                <div class="card-panel">
                    <h3 class="panel-title">Publication</h3>
                    
                    <div class="form-group">
                        <label for="statut">Statut de diffusion *</label>
                        <select id="statut" name="statut" class="form-control" required>
                            <option value="brouillon" <?= $actualite['statut'] === 'brouillon' ? 'selected' : '' ?>>Brouillon</option>
                            <option value="publie" <?= $actualite['statut'] === 'publie' ? 'selected' : '' ?>>Publié</option>
                            <option value="archive" <?= $actualite['statut'] === 'archive' ? 'selected' : '' ?>>Archivé</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="academic_year_id">Année académique *</label>
                        <select id="academic_year_id" name="academic_year_id" class="form-control" required>
                            <option value="" disabled>Sélectionner une année</option>
                            <?php foreach ($years as $y): ?>
                                <option value="<?= $y['id'] ?>" <?= $actualite['id_academic_year'] == $y['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($y['label']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="btn-submit-container">
                    <button type="submit" class="btn-custom btn-primary" style="width: 100%;">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
                        Enregistrer les modifications
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