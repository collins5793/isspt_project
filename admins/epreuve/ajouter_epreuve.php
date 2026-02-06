<?php
session_start();
require_once '../../includes/db.php';

// Initialisation
$errors = [];
$titre = $description = $id_category = $id_filiere = $id_matiere = $academic_year_id = '';
$niveau = 'autre';
$universite = $pays = '';
$is_public = 1;

// Récupérer les catégories, filières, matières et années
$categories = $pdo->query("SELECT id_category, nom_category FROM epreuves_categories ORDER BY nom_category")->fetchAll(PDO::FETCH_ASSOC);
$filieres = $pdo->query("SELECT id_filiere, nom_filiere FROM filieres ORDER BY nom_filiere")->fetchAll(PDO::FETCH_ASSOC);
$matiere_epreuves = $pdo->query("SELECT id_matiere, nom_matiere FROM matiere_epreuves ORDER BY nom_matiere")->fetchAll(PDO::FETCH_ASSOC);
$annees = $pdo->query("SELECT id, label FROM academic_years ORDER BY start_date DESC")->fetchAll(PDO::FETCH_ASSOC);

$uploadDir = 'uploads/';
$thumbDir  = 'uploads/thumbs/';
if(!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
if(!is_dir($thumbDir)) mkdir($thumbDir, 0777, true);

// Traitement formulaire
if($_SERVER['REQUEST_METHOD']==='POST'){
    $titre = trim($_POST['titre']);
    $description = trim($_POST['description']);
    $id_category = $_POST['id_category'] ?? '';
    $id_filiere = $_POST['id_filiere'] ?? '';
    $id_matiere = $_POST['id_matiere'] ?? '';
    $academic_year_id = $_POST['academic_year_id'] ?? '';
    $niveau = $_POST['niveau'] ?? 'autre';
    $universite = trim($_POST['universite']);
    $pays = trim($_POST['pays']);
    $is_public = isset($_POST['is_public']) ? 1 : 0;

    if(empty($titre)) $errors[]="Le titre est obligatoire.";
    if(empty($id_category)) $errors[]="La catégorie est obligatoire.";
    if(empty($academic_year_id)) $errors[]="L'année universitaire est obligatoire.";
    if(!isset($_FILES['file']) || $_FILES['file']['error']!==UPLOAD_ERR_OK){
        $errors[]="Le fichier PDF est obligatoire.";
    }else{
        $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
        if($ext!=='pdf') $errors[]="Seul le format PDF est autorisé.";
    }

    if(empty($errors)){
        $file_name = time().'_'.basename($_FILES['file']['name']);
        $file_path = $uploadDir.$file_name;
        if(move_uploaded_file($_FILES['file']['tmp_name'],$file_path)){
            $thumbPath = $thumbDir.pathinfo($file_name,PATHINFO_FILENAME).'.jpg';
            $stmt=$pdo->prepare("INSERT INTO epreuves 
                (titre, description, file_path, id_category, id_filiere, id_matiere, academic_year_id, universite, pays, niveau, ajoute_par, is_public)
                VALUES (:titre,:description,:file_path,:id_category,:id_filiere,:id_matiere,:academic_year_id,:universite,:pays,:niveau,:ajoute_par,:is_public)");
            $stmt->execute([
                ':titre'=>$titre,
                ':description'=>$description,
                ':file_path'=>$file_name,
                ':id_category'=>$id_category,
                ':id_filiere'=>$id_filiere?:null,
                ':id_matiere'=>$id_matiere?:null,
                ':academic_year_id'=>$academic_year_id,
                ':universite'=>$universite,
                ':pays'=>$pays,
                ':niveau'=>$niveau,
                ':ajoute_par'=>$_SESSION['admin_id'] ?? null,
                ':is_public'=>$is_public
            ]);
            header("Location: index.php?success=1"); exit;
        }else{
            $errors[]="Erreur lors de l'upload du fichier.";
        }
    }
}

// Contenu layout
ob_start();
?>

<div class="page-header mb-4">
    <h2 class="page-title">➕ Ajouter une nouvelle épreuve</h2>
</div>

<?php if(!empty($errors)): ?>
<div class="alert alert-danger">
    <ul><?php foreach($errors as $e) echo "<li>".htmlspecialchars($e)."</li>"; ?></ul>
</div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data" class="form-card">
    <div class="form-group mb-3">
        <label class="form-label">Titre</label>
        <input type="text" name="titre" class="form-control" value="<?= htmlspecialchars($titre) ?>" required>
    </div>
    <div class="form-group mb-3">
        <label class="form-label">Description</label>
        <textarea name="description" class="form-control"><?= htmlspecialchars($description) ?></textarea>
    </div>
    <div class="form-group mb-3">
        <label class="form-label">Catégorie</label>
        <select name="id_category" class="form-select" required>
            <option value="">-- Choisir --</option>
            <?php foreach($categories as $c): ?>
                <option value="<?= $c['id_category'] ?>" <?= $id_category==$c['id_category']?"selected":"" ?>><?= htmlspecialchars($c['nom_category']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group mb-3">
        <label class="form-label">Filière</label>
        <select name="id_filiere" class="form-select">
            <option value="">-- Facultatif --</option>
            <?php foreach($filieres as $f): ?>
                <option value="<?= $f['id_filiere'] ?>" <?= $id_filiere==$f['id_filiere']?"selected":"" ?>><?= htmlspecialchars($f['nom_filiere']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group mb-3">
        <label class="form-label">Matière</label>
        <select name="id_matiere" class="form-select">
            <option value="">-- Facultatif --</option>
            <?php foreach($matiere_epreuves as $m): ?>
                <option value="<?= $m['id_matiere'] ?>" <?= $id_matiere==$m['id_matiere']?"selected":"" ?>><?= htmlspecialchars($m['nom_matiere']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group mb-3">
        <label class="form-label">Année universitaire</label>
        <select name="academic_year_id" class="form-select" required>
            <option value="">-- Choisir --</option>
            <?php foreach($annees as $a): ?>
                <option value="<?= $a['id'] ?>" <?= $academic_year_id==$a['id']?"selected":"" ?>><?= htmlspecialchars($a['label']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group mb-3">
        <label class="form-label">Niveau</label>
        <select name="niveau" class="form-select">
            <option value="1ère année" <?= $niveau=="1ère année"?"selected":"" ?>>1ère année</option>
            <option value="2ème année" <?= $niveau=="2ème année"?"selected":"" ?>>2ème année</option>
            <option value="3ème année" <?= $niveau=="3ème année"?"selected":"" ?>>3ème année</option>
            <option value="autre" <?= $niveau=="autre"?"selected":"" ?>>Autre</option>
        </select>
    </div>
    <div class="form-group mb-3">
        <label class="form-label">Université</label>
        <input type="text" name="universite" class="form-control" value="<?= htmlspecialchars($universite) ?>">
    </div>
    <div class="form-group mb-3">
        <label class="form-label">Pays</label>
        <input type="text" name="pays" class="form-control" value="<?= htmlspecialchars($pays) ?>">
    </div>
    <div class="form-group mb-3">
        <label class="form-label">Fichier PDF</label>
        <input type="file" name="file" accept="application/pdf" class="form-control" required>
    </div>
    <div class="form-check mb-3">
        <input type="checkbox" name="is_public" class="form-check-input" <?= $is_public?"checked":"" ?>>
        <label class="form-check-label">Rendre public</label>
    </div>
    <button type="submit" class="btn btn-success">➕ Ajouter</button>
    <a href="index.php" class="btn btn-secondary">Annuler</a>
</form>

<?php
$content = ob_get_clean();
include '../layout.php';
?>
