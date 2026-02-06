<?php
session_start();
require_once "../../includes/db.php";

$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: actualites.php");
    exit;
}

// Charger l’actualité
$stmt = $pdo->prepare("SELECT * FROM actualites WHERE id = ?");
$stmt->execute([$id]);
$actualite = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$actualite) {
    $_SESSION['error'] = "Actualité introuvable.";
    header("Location: actualites.php");
    exit;
}

$errors = [];

// Années académiques
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

<div class="page-header">
    <h2>Modifier l’actualité</h2>
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
               value="<?= htmlspecialchars($_POST['titre'] ?? $actualite['titre']) ?>" required>
    </div>

    <div class="form-group">
        <label>Contenu *</label>
        <textarea name="contenu" class="form-control" rows="6" required><?= htmlspecialchars($_POST['contenu'] ?? $actualite['contenu']) ?></textarea>
    </div>

    <div class="form-group">
        <label>Mot du Président</label>
        <textarea name="mot_du_president" class="form-control" rows="3"><?= htmlspecialchars($_POST['mot_du_president'] ?? $actualite['mot_du_president']) ?></textarea>
    </div>

    <div class="form-group">
        <label>Année académique *</label>
        <select name="academic_year_id" class="form-control" required>
            <?php foreach ($years as $y): ?>
                <option value="<?= $y['id'] ?>" <?= $actualite['id_academic_year'] == $y['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($y['label']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-group">
        <label>Statut *</label>
        <select name="statut" class="form-control" required>
            <option value="brouillon" <?= $actualite['statut']=='brouillon'?'selected':'' ?>>Brouillon</option>
            <option value="publie" <?= $actualite['statut']=='publie'?'selected':'' ?>>Publié</option>
            <option value="archive" <?= $actualite['statut']=='archive'?'selected':'' ?>>Archivé</option>
        </select>
    </div>

    <button class="btn btn-primary">Mettre à jour</button>
</form>

<?php
$content = ob_get_clean();
include "../layout.php";
?>
