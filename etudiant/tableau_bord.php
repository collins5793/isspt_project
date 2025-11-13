<?php
session_start();
if (!isset($_SESSION['etudiant_id'])) {
    header('Location: login_etudiant.php');
    exit;
}

$nom = $_SESSION['etudiant_nom'];
$prenom = $_SESSION['etudiant_prenom'];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Tableau de bord - Étudiant</title>
    <link rel="stylesheet" href="../assets/bootstrap.min.css">
    <style>
        body {
            background-color: #f4f6f9;
        }
        .container {
            margin-top: 80px;
        }
    </style>
</head>
<body>

<div class="container text-center">
    <h2>Bienvenue, <?= htmlspecialchars($prenom . ' ' . $nom); ?> 🎓</h2>
    <p>Vous êtes connecté à votre espace étudiant.</p>

    <a href="deconnexion.php" class="btn btn-danger mt-3">Se déconnecter</a>
</div>

</body>
</html>
