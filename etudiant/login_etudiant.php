<?php
session_start();
require_once '../includes/db.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifiant = trim($_POST['identifiant']);
    $mot_de_passe = $_POST['mot_de_passe'];

    // Vérifier si l'identifiant correspond à un email ou un téléphone
    $sql = "SELECT * FROM etudiants WHERE (email = :identifiant OR telephone = :identifiant) AND statut = 'actif' LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['identifiant' => $identifiant]);
    $etudiant = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($etudiant && password_verify($mot_de_passe, $etudiant['mot_de_passe'])) {
        // Connexion réussie
        

        // Vérifier si l'étudiant est membre du bureau (admin)
        $sqlAdmin = "SELECT * FROM administrateurs WHERE id_etudiant = :id_etudiant AND role = 'bureau' LIMIT 1";
        $stmtAdmin = $pdo->prepare($sqlAdmin);
        $stmtAdmin->execute(['id_etudiant' => $etudiant['id_etudiant']]);
        $admin = $stmtAdmin->fetch(PDO::FETCH_ASSOC);

        if ($admin) {
            // L'étudiant est membre du bureau, on définit les sessions admin
            $_SESSION['admin_id'] = $admin['id_admin'];
            $_SESSION['admin_role'] = $admin['role'];
            $_SESSION['admin_poste'] = $admin['poste_bureau'];
            header('Location: dashboard_admin.php'); // Tableau de bord admin
            exit;
        } else {
            $_SESSION['etudiant_id'] = $etudiant['id_etudiant'];
            $_SESSION['etudiant_nom'] = $etudiant['nom'];
            $_SESSION['etudiant_prenom'] = $etudiant['prenom'];
            // Connexion simple étudiant
            header('Location: tableau_bord.php'); // Tableau de bord étudiant
            exit;
        }

    } else {
        $message = "<div class='alert alert-danger text-center'>❌ Identifiants incorrects ou compte inactif.</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Connexion Étudiant</title>
    <link rel="stylesheet" href="../assets/bootstrap.min.css">
    <style>
        body {
            background: linear-gradient(120deg, #007bff, #0056b3);
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .login-card {
            background: #fff;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            padding: 40px 30px;
            width: 400px;
            text-align: center;
        }
        .login-card img { width: 90px; margin-bottom: 15px; }
        .login-card h3 { color: #0055a5; margin-bottom: 25px; }
        .form-control { border-radius: 10px; }
        .btn-primary { border-radius: 10px; background-color: #0055a5; border: none; }
        .btn-primary:hover { background-color: #004080; }
    </style>
</head>
<body>

<div class="login-card">
    <img src="../assets/logo.png" alt="Logo" onerror="this.style.display='none'">
    <h3>Connexion Étudiant</h3>

    <?= $message; ?>

    <form method="POST">
        <div class="mb-3">
            <label class="form-label">Email ou Téléphone</label>
            <input type="text" name="identifiant" class="form-control" placeholder="Ex : exemple@gmail.com ou 97000000" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Mot de passe</label>
            <input type="password" name="mot_de_passe" class="form-control" required>
        </div>

        <button type="submit" class="btn btn-primary w-100">Se connecter</button>

        <p class="mt-3">
            <a href="#" class="text-decoration-none">Mot de passe oublié ?</a>
        </p>
    </form>
</div>

</body>
</html>
