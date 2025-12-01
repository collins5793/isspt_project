<?php
session_start();
require_once '../../includes/db.php';

// Vérifier si l'admin est connecté
if (!isset($_SESSION['admin_id'])) {
    header('Location: connexion_admin.php');
    exit;
}

// Vérifier l'ID de l'étudiant
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: etudiants.php?error=id");
    exit;
}

$id = intval($_GET['id']);
$message = "";

// ============================
//    RÉCUPÉRATION DES DONNÉES
// ============================
$stmt = $pdo->prepare("SELECT * FROM etudiants WHERE id_etudiant = ?");
$stmt->execute([$id]);
$etudiant = $stmt->fetch();

if (!$etudiant) {
    header("Location: etudiants.php?error=notfound");
    exit;
}


// ============================
//      TRAITEMENT UPDATE
// ============================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nom = trim($_POST['nom']);
    $prenom = trim($_POST['prenom']);
    $email = trim($_POST['email']);
    $promotion = trim($_POST['promotion']);
    $filiere = trim($_POST['filiere']);
    $telephone = trim($_POST['telephone']);

    // — Mot de passe : on ne change que si un nouveau est mis —
    $mot_de_passe = $etudiant['mot_de_passe'];
    if (!empty($_POST['mot_de_passe'])) {
        $mot_de_passe = password_hash($_POST['mot_de_passe'], PASSWORD_BCRYPT);
    }

    // — Photo —
    $photo = $etudiant['photo'];

    if (!empty($_FILES['photo']['name'])) {
        $uploadDir = "../uploads/photos_etudiants/";
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        // Supprimer l'ancienne photo (si elle existe)
        if (!empty($photo) && file_exists($uploadDir . $photo)) {
            unlink($uploadDir . $photo);
        }

        $fileName = uniqid() . "_" . basename($_FILES['photo']['name']);
        $uploadFile = $uploadDir . $fileName;

        if (move_uploaded_file($_FILES['photo']['tmp_name'], $uploadFile)) {
            $photo = $fileName;
        }
    }


    // — Mise à jour —
    $sql = "UPDATE etudiants 
            SET nom=?, prenom=?, email=?, mot_de_passe=?, promotion=?, filiere=?, telephone=?, photo=? 
            WHERE id_etudiant=?";

    $stmt = $pdo->prepare($sql);

    $result = $stmt->execute([
        $nom, $prenom, $email, $mot_de_passe, $promotion, $filiere, $telephone, $photo, $id
    ]);

    if ($result) {
        header("Location: etudiants.php?updated=1");
        exit;
    } else {
        $message = "<div class='alert alert-danger'>Erreur lors de la mise à jour.</div>";
    }
}


ob_start();
?>

<div class="page-header">
    <h1>Modifier un Étudiant</h1>
    <a href="etudiants.php" class="btn btn-secondary">⬅ Retour à la liste</a>
</div>

<?= $message ?>

<div class="card">
    <div class="card-header">
        <h3>Formulaire de modification</h3>
    </div>

    <div class="card-body">

        <form method="POST" enctype="multipart/form-data">

            <div class="form-group">
                <label>Nom :</label>
                <input type="text" name="nom" class="form-control" value="<?= htmlspecialchars($etudiant['nom']) ?>" required>
            </div>

            <div class="form-group">
                <label>Prénom :</label>
                <input type="text" name="prenom" class="form-control" value="<?= htmlspecialchars($etudiant['prenom']) ?>" required>
            </div>

            <div class="form-group">
                <label>Email :</label>
                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($etudiant['email']) ?>" required>
            </div>

            <div class="form-group">
                <label>Nouveau mot de passe (laisser vide pour ne rien changer) :</label>
                <input type="password" name="mot_de_passe" class="form-control">
            </div>

            <div class="form-group">
                <label>Promotion :</label>
                <input type="text" name="promotion" class="form-control" 
                       value="<?= htmlspecialchars($etudiant['promotion']) ?>" placeholder="Ex : 2024-2025">
            </div>

            <div class="form-group">
                <label>Filière :</label>
                <input type="text" name="filiere" class="form-control"
                       value="<?= htmlspecialchars($etudiant['filiere']) ?>" placeholder="Ex : SIL">
            </div>

            <div class="form-group">
                <label>Téléphone :</label>
                <input type="text" name="telephone" class="form-control" value="<?= htmlspecialchars($etudiant['telephone']) ?>">
            </div>

            <div class="form-group">
                <label>Photo actuelle :</label><br>
                <?php if ($etudiant['photo']) : ?>
                    <img src="../uploads/photos_etudiants/<?= $etudiant['photo'] ?>" width="120" class="mb-3 rounded">
                <?php else : ?>
                    <p class="text-muted">Aucune photo</p>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label>Nouvelle photo :</label>
                <input type="file" name="photo" class="form-control">
            </div>

            <button type="submit" class="btn btn-primary w-100">Modifier l’étudiant</button>

        </form>

    </div>
</div>

<?php
$content = ob_get_clean();
include "../layout.php";
