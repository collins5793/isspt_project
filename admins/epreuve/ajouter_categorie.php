<?php
session_start();
require_once '../../includes/db.php';

$errors = [];
$nom_category = '';
$description = '';

if($_SERVER['REQUEST_METHOD']==='POST'){
    $nom_category = trim($_POST['nom_category']);
    $description = trim($_POST['description']);
    if(empty($nom_category)) $errors[]="Le nom de la catégorie est obligatoire.";

    if(empty($errors)){
        $stmt=$pdo->prepare("INSERT INTO epreuves_categories (nom_category, description, created_by) VALUES (:nom,:desc,:admin)");
        $stmt->execute([
            ':nom'=>$nom_category,
            ':desc'=>$description,
            ':admin'=>$_SESSION['admin_id']
        ]);
        header("Location: categories.php?added=1"); exit;
    }
}

ob_start();
?>

<div class="page-header mb-4">
    <h2 class="page-title">➕ Ajouter une catégorie d’épreuve</h2>
</div>

<?php if(!empty($errors)): ?>
<div class="alert alert-danger">
    <ul><?php foreach($errors as $e) echo "<li>".htmlspecialchars($e)."</li>"; ?></ul>
</div>
<?php endif; ?>

<form method="POST" class="form-card">
    <div class="form-group mb-3">
        <label class="form-label">Nom de la catégorie</label>
        <input type="text" name="nom_category" class="form-control" value="<?= htmlspecialchars($nom_category) ?>" required>
    </div>
    <div class="form-group mb-3">
        <label class="form-label">Description (facultatif)</label>
        <textarea name="description" class="form-control"><?= htmlspecialchars($description) ?></textarea>
    </div>
    <button type="submit" class="btn btn-success">💾 Ajouter</button>
    <a href="liste_categories.php" class="btn btn-secondary">Annuler</a>
</form>

<?php
$content = ob_get_clean();
include '../layout.php';
?>
