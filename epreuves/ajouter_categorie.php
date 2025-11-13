<?php
session_start();
require_once '../includes/db.php'; // connexion PDO

// Vérifier admin
// if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
//     header("Location: index.php");
//     exit;
// }

$errors = [];
$nom_category = '';
$description = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom_category = trim($_POST['nom_category']);
    $description = trim($_POST['description']);

    if (empty($nom_category)) $errors[] = "Le nom de la catégorie est obligatoire.";

    if (empty($errors)) {
        $stmt = $pdo->prepare("INSERT INTO epreuves_categories (nom_category, description, created_by) VALUES (:nom, :desc, :admin)");
        $stmt->execute([
            ':nom' => $nom_category,
            ':desc' => $description,
            ':admin' => $_SESSION['id_admin']
        ]);

        header("Location: liste_categories.php?added=1");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>➕ Ajouter une catégorie</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container my-5">
    <h2 class="mb-4">➕ Ajouter une catégorie d’épreuve</h2>

    <?php if(!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul><?php foreach($errors as $err) echo "<li>".htmlspecialchars($err)."</li>"; ?></ul>
        </div>
    <?php endif; ?>

    <form method="POST" class="bg-white p-4 rounded shadow-sm">
        <div class="mb-3">
            <label class="form-label">Nom de la catégorie</label>
            <input type="text" name="nom_category" class="form-control" value="<?= htmlspecialchars($nom_category) ?>" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Description (facultatif)</label>
            <textarea name="description" class="form-control"><?= htmlspecialchars($description) ?></textarea>
        </div>

        <button type="submit" class="btn btn-success">💾 Ajouter</button>
        <a href="liste_categories.php" class="btn btn-secondary">Annuler</a>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
