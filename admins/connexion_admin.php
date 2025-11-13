<?php
session_start();
require_once '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $mot_de_passe = $_POST['mot_de_passe'] ?? '';

    if ($email && $mot_de_passe) {
        $stmt = $pdo->prepare("SELECT * FROM administrateurs WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($admin && password_verify($mot_de_passe, $admin['mot_de_passe'])) {
            $_SESSION['admin_id'] = $admin['id_admin'];
            $_SESSION['admin_nom'] = $admin['nom'];
            $_SESSION['admin_role'] = $admin['role'];

            header("Location: dashboard_admin.php");
            exit;
        } else {
            $erreur = "❌ Email ou mot de passe incorrect.";
        }
    } else {
        $erreur = "⚠️ Tous les champs sont obligatoires.";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Connexion - Administrateur</title>
<link rel="stylesheet" href="../assets/style.css">
<style>
body {
  background: #f0f2f5;
  font-family: 'Poppins', sans-serif;
}
.form-container {
  width: 400px;
  margin: 100px auto;
  padding: 25px;
  background: white;
  border-radius: 12px;
  box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}
input {
  width: 100%;
  padding: 10px;
  margin-top: 10px;
  border: 1px solid #ccc;
  border-radius: 8px;
}
button {
  margin-top: 15px;
  width: 100%;
  background: #007BFF;
  color: white;
  border: none;
  padding: 10px;
  font-weight: bold;
  border-radius: 8px;
  cursor: pointer;
}
button:hover { background: #0056d2; }
.error { color: red; margin-top: 10px; }
</style>
</head>
<body>

<div class="form-container">
  <h2>Connexion admin</h2>
  <?php if(!empty($erreur)): ?><p class="error"><?= $erreur ?></p><?php endif; ?>
  <form method="POST">
    <input type="email" name="email" placeholder="Email" required>
    <input type="password" name="mot_de_passe" placeholder="Mot de passe" required>
    <button type="submit">Se connecter</button>
  </form>
  <p style="margin-top:15px;">Pas encore de compte ? <a href="inscription_admin.php">Créer un compte</a></p>
</div>

</body>
</html>
