<?php
session_start();
require_once '../includes/db.php'; // connexion PDO

// Vérifier si admin
// if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
//     header("Location: index.php");
//     exit;
// }

$errors = [];
$nom_matiere = '';
$id_filiere = '';
$description = '';

// Récupérer les filières
$filieres = $pdo->query("SELECT id_filiere, nom_filiere FROM filieres ORDER BY nom_filiere")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom_matiere = trim($_POST['nom_matiere']);
    $id_filiere = $_POST['id_filiere'];
    $description = trim($_POST['description']);

    // Validation
    if (empty($nom_matiere)) $errors[] = "Le nom de la matière est obligatoire.";
    if (empty($id_filiere)) $errors[] = "La filière est obligatoire.";

    if (empty($errors)) {
        $stmt = $pdo->prepare("INSERT INTO matiere_epreuves (nom_matiere, id_filiere, description) VALUES (:nom_matiere, :id_filiere, :description)");
        $stmt->execute([
            ':nom_matiere' => $nom_matiere,
            ':id_filiere' => $id_filiere,
            ':description' => $description
        ]);

        header("Location: liste_matieres.php?added=1");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>➕ Ajouter une matière</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container my-5">
    <h2 class="mb-4">➕ Ajouter une matière</h2>

    <?php if(!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul><?php foreach($errors as $err) echo "<li>".htmlspecialchars($err)."</li>"; ?></ul>
        </div>
    <?php endif; ?>

    <form method="POST" class="bg-white p-4 rounded shadow-sm">
        <div class="mb-3">
            <label class="form-label">Nom de la matière</label>
            <input type="text" name="nom_matiere" class="form-control" value="<?= htmlspecialchars($nom_matiere) ?>" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Filière</label>
            <select name="id_filiere" class="form-select" required>
                <option value="">-- Choisir la filière --</option>
                <?php foreach($filieres as $fil): ?>
                    <option value="<?= $fil['id_filiere'] ?>" <?= $id_filiere==$fil['id_filiere']?"selected":"" ?>><?= htmlspecialchars($fil['nom_filiere']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">Description (facultatif)</label>
            <textarea name="description" class="form-control"><?= htmlspecialchars($description) ?></textarea>
        </div>

        <button type="submit" class="btn btn-success">💾 Ajouter</button>
        <a href="liste_matieres.php" class="btn btn-secondary">Annuler</a>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
