<?php
session_start();
require_once '../../includes/db.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: liste_categories.php"); exit;
}

$id_category = (int)$_GET['id'];
$errors = [];

$stmt = $pdo->prepare("SELECT * FROM epreuves_categories WHERE id_category = :id");
$stmt->execute([':id'=>$id_category]);
$categorie = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$categorie) die("Catégorie introuvable.");

$nom_category = $categorie['nom_category'];
$description = $categorie['description'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom_category = trim($_POST['nom_category']);
    $description = trim($_POST['description']);

    if (empty($nom_category)) $errors[] = "Le nom de la catégorie est obligatoire.";

    if (empty($errors)) {
        $stmt = $pdo->prepare("UPDATE epreuves_categories SET nom_category=:nom, description=:desc WHERE id_category=:id");
        $stmt->execute([
            ':nom'=>$nom_category, ':desc'=>$description, ':id'=>$id_category
        ]);
        header("Location: liste_categories.php?updated=1"); exit;
    }
}

ob_start();
?>

<h2 class="page-title mb-4">✏️ Modifier la catégorie : <?= htmlspecialchars($nom_category) ?></h2>

<?php if(!empty($errors)): ?>
<div class="alert alert-danger">
    <ul><?php foreach($errors as $err) echo "<li>".htmlspecialchars($err)."</li>"; ?></ul>
</div>
<?php endif; ?>

<form method="POST" class="form-card bg-white p-4 rounded shadow-sm">
    <div class="form-group mb-3">
        <label class="form-label">Nom de la catégorie</label>
        <input type="text" name="nom_category" class="form-control" value="<?= htmlspecialchars($nom_category) ?>" required>
    </div>

    <div class="form-group mb-3">
        <label class="form-label">Description (facultatif)</label>
        <textarea name="description" class="form-control"><?= htmlspecialchars($description) ?></textarea>
    </div>

    <button type="submit" class="btn btn-primary">💾 Mettre à jour</button>
    <a href="liste_categories.php" class="btn btn-secondary">Annuler</a>
</form>

<?php
$content = ob_get_clean();
include '../layout.php';
?>
