<?php
session_start();
require_once '../../includes/db.php';

// Vérifier si l'admin est connecté
if (!isset($_SESSION['admin_id'])) {
    header('Location: connexion_admin.php');
    exit;
}

$message = '';

// =============================
//   TRAITEMENT DU FORMULAIRE
// =============================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom']);
    $prenom = trim($_POST['prenom']);
    $email = trim($_POST['email']);
    $mot_de_passe = password_hash($_POST['mot_de_passe'], PASSWORD_BCRYPT);
    $promotion = trim($_POST['promotion']);
    $filiere = trim($_POST['filiere']);
    $telephone = trim($_POST['telephone']);
    $photo = null;

    // Gestion de la photo
    if (!empty($_FILES['photo']['name'])) {
        $uploadDir = '../uploads/photos_etudiants/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $fileName = uniqid() . '_' . basename($_FILES['photo']['name']);
        $uploadFile = $uploadDir . $fileName;

        if (move_uploaded_file($_FILES['photo']['tmp_name'], $uploadFile)) {
            $photo = $fileName;
        }
    }

    // Matricule automatique
    $matricule = 'ETD-' . strtoupper(substr($nom, 0, 2)) . rand(1000, 9999);

    // Insertion DB
    $sql = "INSERT INTO etudiants (matricule, nom, prenom, email, mot_de_passe, photo, promotion, filiere, telephone)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([$matricule, $nom, $prenom, $email, $mot_de_passe, $photo, $promotion, $filiere, $telephone]);

    if ($result) {
        // Redirection vers la liste des étudiants après succès
        header("Location: etudiants.php?success=1");
        exit;
    } else {
        $message = "<div class='alert alert-danger'>Erreur lors de l’inscription.</div>";
    }

}

ob_start();
?>

<div class="page-header">
    <h1>Inscrire un Étudiant</h1>
    <a href="etudiants.php" class="btn btn-secondary">⬅ Retour à la liste</a>
</div>

<?= $message; ?>

<div class="card">
    <div class="card-header">
        <h3>Formulaire d'inscription</h3>
    </div>

    <div class="card-body">

        <form method="POST" enctype="multipart/form-data">

            <div class="form-group">
                <label>Nom :</label>
                <input type="text" name="nom" class="form-control" required>
            </div>

            <div class="form-group">
                <label>Prénom :</label>
                <input type="text" name="prenom" class="form-control" required>
            </div>

            <div class="form-group">
                <label>Email :</label>
                <input type="email" name="email" class="form-control" required>
            </div>

            <div class="form-group">
                <label>Mot de passe :</label>
                <input type="password" name="mot_de_passe" class="form-control" required>
            </div>

            <div class="form-group">
                <label>Promotion :</label>
                <input type="text" name="promotion" class="form-control" placeholder="Ex : 2024-2025">
            </div>

            <div class="form-group">
                <label>Filière :</label>
                <input type="text" name="filiere" class="form-control" placeholder="Ex : Systèmes Informatiques et Logiciels">
            </div>

            <div class="form-group">
                <label>Téléphone :</label>
                <input type="text" name="telephone" class="form-control">
            </div>

            <div class="form-group">
                <label>Photo :</label>
                <input type="file" name="photo" class="form-control">
            </div>

            <button type="submit" class="btn btn-primary w-100">Inscrire l’étudiant</button>

        </form>

    </div>
</div>

<?php
$content = ob_get_clean();
include "../layout.php";
