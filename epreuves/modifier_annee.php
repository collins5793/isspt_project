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
    header("Location: liste_annees.php");
    exit;
}

$id = (int)$_GET['id'];
$errors = [];

// Récupérer année
$stmt = $pdo->prepare("SELECT * FROM academic_years WHERE id = :id");
$stmt->execute([':id'=>$id]);
$annee = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$annee) die("Année introuvable.");

$label = $annee['label'];
$start_date = $annee['start_date'];
$end_date = $annee['end_date'];
$is_current = $annee['is_current'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $label = trim($_POST['label']);
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $is_current = isset($_POST['is_current']) ? 1 : 0;

    if (empty($label)) $errors[] = "Le libellé de l'année est obligatoire.";
    if (empty($start_date)) $errors[] = "La date de début est obligatoire.";
    if (empty($end_date)) $errors[] = "La date de fin est obligatoire.";
    if (!empty($start_date) && !empty($end_date) && $start_date > $end_date) $errors[] = "La date de début doit être avant la date de fin.";

    if (empty($errors)) {
        if ($is_current) {
            $pdo->exec("UPDATE academic_years SET is_current = 0");
        }

        $stmt = $pdo->prepare("UPDATE academic_years SET label=:label, start_date=:start, end_date=:end, is_current=:current WHERE id=:id");
        $stmt->execute([
            ':label' => $label,
            ':start' => $start_date,
            ':end' => $end_date,
            ':current' => $is_current,
            ':id' => $id
        ]);

        header("Location: liste_annees.php?updated=1");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>✏️ Modifier l'année universitaire</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container my-5">
    <h2 class="mb-4">✏️ Modifier l'année universitaire : <?= htmlspecialchars($label) ?></h2>

    <?php if(!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul><?php foreach($errors as $err) echo "<li>".htmlspecialchars($err)."</li>"; ?></ul>
        </div>
    <?php endif; ?>

    <form method="POST" class="bg-white p-4 rounded shadow-sm">
        <div class="mb-3">
            <label class="form-label">Libellé</label>
            <input type="text" name="label" class="form-control" value="<?= htmlspecialchars($label) ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Date de début</label>
            <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($start_date) ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Date de fin</label>
            <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($end_date) ?>" required>
        </div>
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="is_current" id="is_current" <?= $is_current ? 'checked' : '' ?>>
            <label class="form-check-label" for="is_current">Année en cours</label>
        </div>
        <button type="submit" class="btn btn-primary">💾 Mettre à jour</button>
        <a href="liste_annees.php" class="btn btn-secondary">Annuler</a>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
