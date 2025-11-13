<?php
session_start();
require_once '../includes/db.php';

// Vérifier si l'admin est connecté
if (!isset($_SESSION['admin_id'])) {
    header('Location: connexion_admin.php');
    exit;
}

// Traitement du formulaire
$message = '';

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

    // Génération du matricule automatique
    $matricule = 'ETD-' . strtoupper(substr($nom, 0, 2)) . rand(1000, 9999);

    // Insertion dans la base
    $sql = "INSERT INTO etudiants (matricule, nom, prenom, email, mot_de_passe, photo, promotion, filiere, telephone)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([$matricule, $nom, $prenom, $email, $mot_de_passe, $photo, $promotion, $filiere, $telephone]);

    if ($result) {
        $message = "<div class='alert alert-success'>✅ Étudiant inscrit avec succès ! Matricule : <b>$matricule</b></div>";
    } else {
        $message = "<div class='alert alert-danger'>❌ Erreur lors de l’inscription de l’étudiant.</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Inscrire un étudiant</title>
    <link rel="stylesheet" href="../assets/bootstrap.min.css">
    <style>
        body {
            background-color: #f7f9fc;
        }
        .container {
            margin-top: 50px;
            max-width: 700px;
        }
        .card {
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            border-radius: 10px;
        }
        .btn-primary {
            background-color: #0055a5;
            border: none;
        }
        .btn-primary:hover {
            background-color: #004080;
        }
    </style>
</head>
<body>


<div class="container">
    <div class="card">
        <div class="card-header bg-primary text-white text-center">
            <h4>Inscription d’un Étudiant</h4>
        </div>
        <div class="card-body">
            <?= $message; ?>

            <form method="POST" enctype="multipart/form-data">
                <div class="mb-3">
                    <label>Nom :</label>
                    <input type="text" name="nom" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label>Prénom :</label>
                    <input type="text" name="prenom" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label>Email :</label>
                    <input type="email" name="email" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label>Mot de passe :</label>
                    <input type="password" name="mot_de_passe" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label>Promotion :</label>
                    <input type="text" name="promotion" class="form-control" placeholder="Ex: 2024-2025">
                </div>

                <div class="mb-3">
                    <label>Filière :</label>
                    <input type="text" name="filiere" class="form-control" placeholder="Ex: Systèmes Informatiques et Logiciels">
                </div>

                <div class="mb-3">
                    <label>Téléphone :</label>
                    <input type="text" name="telephone" class="form-control">
                </div>

                <div class="mb-3">
                    <label>Photo :</label>
                    <input type="file" name="photo" class="form-control">
                </div>

                <button type="submit" class="btn btn-primary w-100">Inscrire l’étudiant</button>
            </form>
        </div>
    </div>
</div>

</body>
</html>
