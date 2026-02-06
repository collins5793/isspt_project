<?php
session_start();
require_once '../../includes/db.php';

$errors = [];
$nom_matiere = '';
$id_filiere = '';
$description = '';

$filieres = $pdo->query("SELECT id_filiere, nom_filiere FROM filieres ORDER BY nom_filiere")->fetchAll(PDO::FETCH_ASSOC);

if($_SERVER['REQUEST_METHOD']==='POST'){
    $nom_matiere = trim($_POST['nom_matiere']);
    $id_filiere = $_POST['id_filiere'];
    $description = trim($_POST['description']);

    if(empty($nom_matiere)) $errors[]="Le nom de la matière est obligatoire.";
    if(empty($id_filiere)) $errors[]="La filière est obligatoire.";

    if(empty($errors)){
        $stmt=$pdo->prepare("INSERT INTO matiere_epreuves (nom_matiere, id_filiere, description) VALUES (:nom,:filiere,:desc)");
        $stmt->execute([
            ':nom'=>$nom_matiere,
            ':filiere'=>$id_filiere,
            ':desc'=>$description
        ]);
        header("Location: matieres.php?added=1"); exit;
    }
}

ob_start();
?>

<div class="page-header mb-4">
    <h2 class="page-title">➕ Ajouter une matière</h2>
</div>

<?php if(!empty($errors)): ?>
<div class="alert alert-danger">
    <ul><?php foreach($errors as $e) echo "<li>".htmlspecialchars($e)."</li>"; ?></ul>
</div>
<?php endif; ?>

<form method="POST" class="form-card">
    <div class="form-group mb-3">
        <label class="form-label">Nom de la matière</label>
        <input type="text" name="nom_matiere" class="form-control" value="<?= htmlspecialchars($nom_matiere) ?>" required>
    </div>
    <div class="form-group mb-3">
        <label class="form-label">Filière</label>
        <select name="id_filiere" class="form-select" required>
            <option value="">-- Choisir la filière --</option>
            <?php foreach($filieres as $f): ?>
                <option value="<?= $f['id_filiere'] ?>" <?= $id_filiere==$f['id_filiere']?"selected":"" ?>><?= htmlspecialchars($f['nom_filiere']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group mb-3">
        <label class="form-label">Description (facultatif)</label>
        <textarea name="description" class="form-control"><?= htmlspecialchars($description) ?></textarea>
    </div>
    <button type="submit" class="btn btn-success">💾 Ajouter</button>
    <a href="liste_matieres.php" class="btn btn-secondary">Annuler</a>
</form>

<?php
$content = ob_get_clean();
include '../layout.php';
?>
