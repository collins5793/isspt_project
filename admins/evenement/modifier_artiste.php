<?php
session_start();
require_once "../../includes/db.php";

// Vérification admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit;
}

$id_event = $_GET['id_event'] ?? null;
$nom_artiste = $_GET['nom'] ?? null;

if (!$id_event || !$nom_artiste) {
    die("Paramètres invalides.");
}

// Charger artiste
$stmt = $pdo->prepare("
    SELECT * FROM evenement_artistes
    WHERE id_evenement = ? AND nom_artiste = ?
");
$stmt->execute([$id_event, $nom_artiste]);
$artiste = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$artiste) {
    die("Artiste introuvable.");
}

// Traitement formulaire
$message = "";
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $new_nom = trim($_POST['nom_artiste']);
    $pseudonyme = trim($_POST['pseudonyme']);
    $description = trim($_POST['description']);
    $role = trim($_POST['role']);

    // Gestion photo
    $photoName = $artiste['photo'];

    if (!empty($_FILES['photo']['name'])) {

        $uploadDir = "../uploads/artistes/";
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $fileTmp = $_FILES['photo']['tmp_name'];
        $fileName = time() . "_" . basename($_FILES['photo']['name']);
        $targetPath = $uploadDir . $fileName;

        if (move_uploaded_file($fileTmp, $targetPath)) {

            // Supprimer l'ancienne image si existe
            if ($photoName && file_exists($uploadDir . $photoName)) {
                unlink($uploadDir . $photoName);
            }
            $photoName = $fileName;
        }
    }

    // Mise à jour artiste
    $update = $pdo->prepare("
        UPDATE evenement_artistes
        SET nom_artiste = ?, pseudonyme = ?, description = ?, photo = ?, role = ?
        WHERE id_evenement = ? AND nom_artiste = ?
    ");

    $update->execute([
        $new_nom, $pseudonyme, $description, $photoName, $role,
        $id_event, $nom_artiste
    ]);

    $message = "✔ Artiste modifié avec succès !";

    // Pour éviter bug si nom_artiste a changé :
    $nom_artiste = $new_nom;

    // Recharger artiste
    $stmt->execute([$id_event, $nom_artiste]);
    $artiste = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Contenu à injecter dans layout
ob_start();
?>

<div class="page-header">
    <h2>Modifier un artiste invité</h2>
    <a href="evenement_detail.php?id=<?= $id_event ?>" class="btn btn-secondary">⬅ Retour</a>
</div>

<?php if ($message): ?>
    <div class="alert alert-success"><?= $message ?></div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data" class="form-artiste">

    <div class="form-group">
        <label>Nom de l'artiste *</label>
        <input type="text" name="nom_artiste" required class="form-control"
               value="<?= htmlspecialchars($artiste['nom_artiste']) ?>">
    </div>

    <div class="form-group">
        <label>Pseudonyme (optionnel)</label>
        <input type="text" name="pseudonyme" class="form-control"
               value="<?= htmlspecialchars($artiste['pseudonyme']) ?>">
    </div>

    <div class="form-group">
        <label>Rôle *</label>
        <input type="text" name="role" required class="form-control"
               value="<?= htmlspecialchars($artiste['role']) ?>">
    </div>

    <div class="form-group">
        <label>Description</label>
        <textarea name="description" class="form-control" rows="4"><?= htmlspecialchars($artiste['description']) ?></textarea>
    </div>

    <div class="form-group">
        <label>Photo actuelle</label><br>
        <?php 
            $photoPath = "../uploads/artistes/" . ($artiste['photo'] ?: "default.png");
        ?>
        <img src="<?= $photoPath ?>" class="artiste-edit-photo" alt="Photo artiste">
    </div>

    <div class="form-group">
        <label>Changer la photo</label>
        <input type="file" name="photo" class="form-control">
    </div>

    <button type="submit" class="btn btn-primary btn-lg">💾 Enregistrer les modifications</button>

</form>

<style>
.page-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 25px;
}

.form-artiste {
    max-width: 600px;
    margin: auto;
}

.form-group {
    margin-bottom: 18px;
}

.artiste-edit-photo {
    width: 180px;
    height: 180px;
    border-radius: 10px;
    object-fit: cover;
    border: 2px solid #ddd;
    margin-bottom: 10px;
}
</style>

<?php
$content = ob_get_clean();
include "../layout.php";
