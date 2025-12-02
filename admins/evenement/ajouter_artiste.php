<?php
session_start();
require_once "../../includes/db.php";

// Vérifier si l'id_event existe
if (!isset($_GET['id_event']) || empty($_GET['id_event'])) {
    die("ID événement manquant.");
}

$eventId = intval($_GET['id_event']);
$success = "";
$error = "";

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nom_artiste = trim($_POST['nom_artiste']);
    $pseudonyme = trim($_POST['pseudonyme']);
    $description = trim($_POST['description']);
    $role = trim($_POST['role']);

    // Gestion de l'image
    $photoName = null;

    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {

        $allowed = ['jpg','jpeg','png','webp'];
        $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed)) {
            $error = "Format d’image non accepté (jpg, jpeg, png, webp).";
        } else {
            $photoName = uniqid("artiste_") . "." . $ext;
            $path = "../../uploads/artistes/" . $photoName;
            move_uploaded_file($_FILES['photo']['tmp_name'], $path);
        }
    }

    // Insert dans evenement_artistes
    if (empty($error)) {
        $stmt = $pdo->prepare("
            INSERT INTO evenement_artistes 
            (id_evenement, nom_artiste, pseudonyme, description, photo, role)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        if ($stmt->execute([$eventId, $nom_artiste, $pseudonyme, $description, $photoName, $role])) {
            $success = "Artiste ajouté avec succès !";
            header("Location: liste_artistes.php?id_event=" . $eventId);
            exit;
        } else {
            $error = "Erreur lors de l’ajout de l’artiste.";
        }
    }
}
ob_start();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Ajouter un Artiste</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            background: #f4f4f8;
        }
        .card {
            border-radius: 12px;
        }
    </style>
</head>

<>

<di class="container py-5">
    <div class="card shadow-lg p-4">
        <h3 class="text-center mb-4 text-primary">Ajouter un Artiste</h3>

        <!-- Message succès -->
        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>

        <!-- Message erreur -->
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>

        <!-- Formulaire -->
        <form method="POST" enctype="multipart/form-data">

            <div class="mb-3">
                <label class="form-label fw-bold">Nom complet de l'artiste *</label>
                <input type="text" name="nom_artiste" class="form-control" required placeholder="Ex : Koffi Agbéssi">
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold">Pseudonyme (facultatif)</label>
                <input type="text" name="pseudonyme" class="form-control" placeholder="Ex : King Agbéssi">
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold">Rôle dans l'événement *</label>
                <select name="role" class="form-select" required>
                    <option value="">-- Choisir un rôle --</option>
                    <option value="Invité spécial">Invité spécial</option>
                    <option value="Artiste chanteur">Artiste chanteur</option>
                    <option value="Danseur">Danseur</option>
                    <option value="DJ">DJ</option>
                    <option value="Intervenant">Intervenant</option>
                    <option value="Comédien">Comédien</option>
                    <option value="Autre">Autre</option>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold">Photo *</label>
                <input type="file" name="photo" class="form-control" accept="image/*" required>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold">Description (facultatif)</label>
                <textarea name="description" class="form-control" rows="4" placeholder="Courte biographie ou présentation..."></textarea>
            </div>

            <button class="btn btn-primary w-100">Ajouter</button>

            <a href="liste_artistes.php?id_event=<?= $eventId ?>" class="btn btn-secondary w-100 mt-3">
                Retour
            </a>

        </form>

    </div>

<?php
$content = ob_get_clean();
include "../layout.php";