<?php
session_start();
require_once '../includes/db.php';

// Vérifier admin
// if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
//     header("Location: index.php");
//     exit;
// }

// Vérifier id
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: liste_filieres.php");
    exit;
}

$id_filiere = (int)$_GET['id'];
$errors = [];

// Récupérer filière
$stmt = $pdo->prepare("SELECT * FROM filieres WHERE id_filiere = :id");
$stmt->execute([':id'=>$id_filiere]);
$filiere = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$filiere) die("Filière introuvable.");

$nom_filiere = $filiere['nom_filiere'];
$description = $filiere['description'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom_filiere = trim($_POST['nom_filiere']);
    $description = trim($_POST['description']);

    if (empty($nom_filiere)) $errors[] = "Le nom de la filière est obligatoire.";

    if (empty($errors)) {
        $stmt = $pdo->prepare("UPDATE filieres SET nom_filiere = :nom, description = :desc WHERE id_filiere = :id");
        $stmt->execute([
            ':nom' => $nom_filiere,
            ':desc' => $description,
            ':id' => $id_filiere
        ]);

        header("Location: liste_filieres.php?updated=1");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>✏️ Modifier la filière</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container my-5">
    <h2 class="mb-4">✏️ Modifier la filière : <?= htmlspecialchars($nom_filiere) ?></h2>

    <?php if(!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul><?php foreach($errors as $err) echo "<li>".htmlspecialchars($err)."</li>"; ?></ul>
        </div>
    <?php endif; ?>

    <form method="POST" class="bg-white p-4 rounded shadow-sm">
        <div class="mb-3">
            <label class="form-label">Nom de la filière</label>
            <input type="text" name="nom_filiere" class="form-control" value="<?= htmlspecialchars($nom_filiere) ?>" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Description (facultatif)</label>
            <textarea name="description" class="form-control"><?= htmlspecialchars($description) ?></textarea>
        </div>

        <button type="submit" class="btn btn-primary">💾 Mettre à jour</button>
        <a href="liste_filieres.php" class="btn btn-secondary">Annuler</a>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
