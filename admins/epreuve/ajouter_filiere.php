<?php
session_start();
require_once '../../includes/db.php';

$errors = [];
$nom_filiere = '';
$description = '';

if($_SERVER['REQUEST_METHOD']==='POST'){
    $nom_filiere = trim($_POST['nom_filiere']);
    $description = trim($_POST['description']);
    if(empty($nom_filiere)) $errors[]="Le nom de la filière est obligatoire.";

    if(empty($errors)){
        $stmt=$pdo->prepare("INSERT INTO filieres (nom_filiere, description) VALUES (:nom,:desc)");
        $stmt->execute([
            ':nom'=>$nom_filiere,
            ':desc'=>$description
        ]);
        header("Location: filieres.php?added=1"); exit;
    }
}

ob_start();
?>

<div class="page-header mb-4">
    <h2 class="page-title">➕ Ajouter une filière</h2>
</div>

<?php if(!empty($errors)): ?>
<div class="alert alert-danger">
    <ul><?php foreach($errors as $e) echo "<li>".htmlspecialchars($e)."</li>"; ?></ul>
</div>
<?php endif; ?>

<form method="POST" class="form-card">
    <div class="form-group mb-3">
        <label class="form-label">Nom de la filière</label>
        <input type="text" name="nom_filiere" class="form-control" value="<?= htmlspecialchars($nom_filiere) ?>" required>
    </div>
    <div class="form-group mb-3">
        <label class="form-label">Description (facultatif)</label>
        <textarea name="description" class="form-control"><?= htmlspecialchars($description) ?></textarea>
    </div>
    <button type="submit" class="btn btn-success">💾 Ajouter</button>
    <a href="liste_filieres.php" class="btn btn-secondary">Annuler</a>
</form>

<?php
$content = ob_get_clean();
include '../layout.php';
?>
