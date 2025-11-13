<?php
session_start();
require_once '../includes/db.php'; // connexion PDO

// Vérifier si admin
// if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
//     header("Location: index.php");
//     exit;
// }

// Initialisation
$errors = [];
$titre = $description = $id_category = $id_filiere = $id_matiere = $academic_year_id = '';
$niveau = 'autre';
$universite = $pays = '';
$is_public = 1;

// Récupérer les catégories, filières, matières et années
$categories = $pdo->query("SELECT id_category, nom_category FROM epreuves_categories ORDER BY nom_category")->fetchAll(PDO::FETCH_ASSOC);
$filieres = $pdo->query("SELECT id_filiere, nom_filiere FROM filieres ORDER BY nom_filiere")->fetchAll(PDO::FETCH_ASSOC);
$matiere_epreuves = $pdo->query("SELECT id_matiere, nom_matiere, id_filiere FROM matiere_epreuves ORDER BY nom_matiere")->fetchAll(PDO::FETCH_ASSOC);
$annees = $pdo->query("SELECT id, label FROM academic_years ORDER BY start_date DESC")->fetchAll(PDO::FETCH_ASSOC);

// Définir les dossiers d’upload
$uploadDir = '../uploads/';
$thumbDir  = '../uploads/thumbs/';

if(!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
if(!is_dir($thumbDir)) mkdir($thumbDir, 0777, true);

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $titre = trim($_POST['titre']);
    $description = trim($_POST['description']);
    $id_category = $_POST['id_category'] ?? '';
    $id_filiere = $_POST['id_filiere'] ?? null;
    $id_matiere = $_POST['id_matiere'] ?? null;
    $academic_year_id = $_POST['academic_year_id'] ?? '';
    $niveau = $_POST['niveau'] ?? 'autre';
    $universite = trim($_POST['universite']);
    $pays = trim($_POST['pays']);
    $is_public = isset($_POST['is_public']) ? 1 : 0;

    // Validation
    if (empty($titre)) $errors[] = "Le titre est obligatoire.";
    if (empty($id_category)) $errors[] = "La catégorie est obligatoire.";
    if (empty($academic_year_id)) $errors[] = "L'année universitaire est obligatoire.";
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "Le fichier PDF est obligatoire.";
    } else {
        $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
        if ($ext !== 'pdf') $errors[] = "Seul le format PDF est autorisé.";
    }

    if (empty($errors)) {
        // Nom unique du fichier
        $file_name = time() . '_' . basename($_FILES['file']['name']);
        $file_path = $uploadDir . $file_name;

        if(move_uploaded_file($_FILES['file']['tmp_name'], $file_path)) {

            // --- Génération miniature avec Ghostscript ---
            $thumbPath = $thumbDir . pathinfo($file_name, PATHINFO_FILENAME) . '.jpg';

            // Chemin vers Ghostscript, adapte selon ton installation
            $gsExe = '"C:\\Program Files\\gs\\gs10.06.0\\bin\\gswin64c.exe"';

            $gsCommand = "$gsExe -dNOPAUSE -dBATCH -sDEVICE=jpeg -r150 -dFirstPage=1 -dLastPage=1 -sOutputFile="
                        . escapeshellarg($thumbPath) . ' '
                        . escapeshellarg($file_path);

            exec($gsCommand, $output, $returnVar);

            if($returnVar !== 0) {
                // Fallback si Ghostscript échoue
                $thumbPath = '../uploads/thumbs/pdf-icon.jpg';
            }

            // --- Insertion en base ---
            $stmt = $pdo->prepare("INSERT INTO epreuves 
                (titre, description, file_path, id_category, id_filiere, id_matiere, academic_year_id, universite, pays, niveau, ajoute_par, is_public)
                VALUES (:titre, :description, :file_path, :id_category, :id_filiere, :id_matiere, :academic_year_id, :universite, :pays, :niveau, :ajoute_par, :is_public)
            ");
            $stmt->execute([
                ':titre' => $titre,
                ':description' => $description,
                ':file_path' => $file_name,
                ':id_category' => $id_category,
                ':id_filiere' => $id_filiere ?: null,
                ':id_matiere' => $id_matiere ?: null,
                ':academic_year_id' => $academic_year_id,
                ':universite' => $universite,
                ':pays' => $pays,
                ':niveau' => $niveau,
                ':ajoute_par' => $_SESSION['admin_id'] ?? null,
                ':is_public' => $is_public
            ]);

            header("Location: index.php?success=1");
            exit;
        } else {
            $errors[] = "Erreur lors de l'upload du fichier.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>➕ Ajouter une épreuve</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container my-5">
    <h2 class="mb-4">➕ Ajouter une nouvelle épreuve</h2>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul><?php foreach($errors as $err) echo "<li>".htmlspecialchars($err)."</li>"; ?></ul>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="bg-white p-4 rounded shadow-sm">
        <div class="mb-3">
            <label class="form-label">Titre</label>
            <input type="text" name="titre" value="<?= htmlspecialchars($titre) ?>" class="form-control" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-control"><?= htmlspecialchars($description) ?></textarea>
        </div>

        <div class="mb-3">
            <label class="form-label">Catégorie</label>
            <select name="id_category" class="form-select" required>
                <option value="">-- Choisir une catégorie --</option>
                <?php foreach($categories as $cat): ?>
                    <option value="<?= $cat['id_category'] ?>" <?= $id_category==$cat['id_category']?"selected":"" ?>><?= htmlspecialchars($cat['nom_category']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">Filière</label>
            <select name="id_filiere" class="form-select">
                <option value="">-- Facultatif --</option>
                <?php foreach($filieres as $fil): ?>
                    <option value="<?= $fil['id_filiere'] ?>" <?= $id_filiere==$fil['id_filiere']?"selected":"" ?>><?= htmlspecialchars($fil['nom_filiere']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">Matière</label>
            <select name="id_matiere" class="form-select">
                <option value="">-- Facultatif --</option>
                <?php foreach($matiere_epreuves as $mat): ?>
                    <option value="<?= $mat['id_matiere'] ?>" <?= $id_matiere==$mat['id_matiere']?"selected":"" ?>><?= htmlspecialchars($mat['nom_matiere']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">Année universitaire</label>
            <select name="academic_year_id" class="form-select" required>
                <option value="">-- Choisir l'année --</option>
                <?php foreach($annees as $annee): ?>
                    <option value="<?= $annee['id'] ?>" <?= $academic_year_id==$annee['id']?"selected":"" ?>><?= htmlspecialchars($annee['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">Niveau</label>
            <select name="niveau" class="form-select">
                <option value="1ère année" <?= $niveau=="1ère année"?"selected":"" ?>>1ère année</option>
                <option value="2ème année" <?= $niveau=="2ème année"?"selected":"" ?>>2ème année</option>
                <option value="3ème année" <?= $niveau=="3ème année"?"selected":"" ?>>3ème année</option>
                <option value="autre" <?= $niveau=="autre"?"selected":"" ?>>Autre</option>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">Université</label>
            <input type="text" name="universite" value="<?= htmlspecialchars($universite) ?>" class="form-control">
        </div>

        <div class="mb-3">
            <label class="form-label">Pays</label>
            <input type="text" name="pays" value="<?= htmlspecialchars($pays) ?>" class="form-control">
        </div>

        <div class="mb-3">
            <label class="form-label">Fichier PDF</label>
            <input type="file" name="file" accept="application/pdf" class="form-control" required>
        </div>

        <div class="form-check mb-3">
            <input type="checkbox" name="is_public" value="1" class="form-check-input" <?= $is_public?"checked":"" ?>>
            <label class="form-check-label">Rendre public</label>
        </div>

        <button type="submit" class="btn btn-success">➕ Ajouter l'épreuve</button>
        <a href="index.php" class="btn btn-secondary">Annuler</a>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
