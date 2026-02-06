<?php
session_start();
require_once "../../includes/db.php";

$errors = [];

// Sécurité admin
$id_admin = $_SESSION['admin_id'] ?? null;
if (!$id_admin) {
    header("Location: ../login.php");
    exit;
}

// Années académiques
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

    // Validation
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

<div class="page-header">
    <h2>Ajouter une actualité</h2>
    <a href="actualites.php" class="btn btn-secondary">← Retour</a>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger">
    <ul>
        <?php foreach ($errors as $e): ?>
            <li><?= htmlspecialchars($e) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<form method="POST" class="form-container">

    <div class="form-group">
        <label>Titre *</label>
        <input type="text" name="titre" class="form-control"
               value="<?= htmlspecialchars($_POST['titre'] ?? '') ?>" required>
    </div>

    <div class="form-group">
        <label>Contenu *</label>
        <textarea name="contenu" class="form-control" rows="6" required><?= htmlspecialchars($_POST['contenu'] ?? '') ?></textarea>
    </div>

    <div class="form-group">
        <label>Mot du Président</label>
        <textarea name="mot_du_president" class="form-control" rows="3"><?= htmlspecialchars($_POST['mot_du_president'] ?? '') ?></textarea>
    </div>

    <div class="form-group">
        <label>Année académique *</label>
        <select name="academic_year_id" class="form-control" required>
            <option value="">-- Sélectionner --</option>
            <?php foreach ($years as $y): ?>
                <option value="<?= $y['id'] ?>" <?= (($_POST['academic_year_id'] ?? '') == $y['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($y['label']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-group">
        <label>Statut *</label>
        <select name="statut" class="form-control" required>
            <option value="brouillon">Brouillon</option>
            <option value="publie">Publié</option>
            <option value="archive">Archivé</option>
        </select>
    </div>

    <button class="btn btn-primary">Enregistrer</button>
</form>

<?php
$content = ob_get_clean();
include "../layout.php";
?>
