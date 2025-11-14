<?php
session_start();
require_once '../includes/db.php'; // Connexion PDO

// Vérifie si l'utilisateur est connecté
if (!isset($_SESSION['admin_id'])) {
    header("Location: connexion_admin.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role = $_POST['role'] ?? 'administration';
    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $mot_de_passe = $_POST['mot_de_passe'] ?? '';
    $id_etudiant = $_POST['id_etudiant'] ?? null;
    $poste_bureau = $_POST['poste_bureau'] ?? null;

    // Validation
    if ($role === 'bureau' && empty($id_etudiant)) {
        $erreur = "⚠️ Veuillez sélectionner un étudiant pour le bureau.";
    } elseif (($role === 'administration' || $role === 'super_admin') && (!$nom || !$prenom || !$email || !$mot_de_passe)) {
        $erreur = "⚠️ Tous les champs sont obligatoires.";
    } else {
        // Vérifier si email déjà utilisé pour admins classiques
        if ($role !== 'bureau') {
            $check = $pdo->prepare("SELECT id_admin FROM administrateurs WHERE email = :email");
            $check->execute([':email' => $email]);
            if ($check->rowCount() > 0) {
                $erreur = "❌ Cet email est déjà utilisé.";
            }
        }

        if (empty($erreur)) {
            if ($role === 'bureau') {
                // Insérer membre du bureau lié à un étudiant
                $stmt = $pdo->prepare("
                    INSERT INTO administrateurs (role, id_etudiant, poste_bureau)
                    VALUES (:role, :id_etudiant, :poste_bureau)
                ");
                $stmt->execute([
                    ':role' => $role,
                    ':id_etudiant' => $id_etudiant,
                    ':poste_bureau' => $poste_bureau
                ]);
                $success = "✅ Le membre du bureau a été ajouté avec succès.";
            } else {
                // Insérer admin classique
                $hash = password_hash($mot_de_passe, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    INSERT INTO administrateurs (nom, prenom, email, mot_de_passe, role)
                    VALUES (:nom, :prenom, :email, :mot_de_passe, :role)
                ");
                $stmt->execute([
                    ':nom' => $nom,
                    ':prenom' => $prenom,
                    ':email' => $email,
                    ':mot_de_passe' => $hash,
                    ':role' => $role
                ]);
                $success = "✅ L'administrateur {$prenom} {$nom} a été ajouté avec succès.";
            }
        }
    }
}

// Récupérer la liste des étudiants pour le bureau
$etudiants = $pdo->query("SELECT id_etudiant, matricule, nom, prenom FROM etudiants ORDER BY nom ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Ajouter un administrateur</title>
<link rel="stylesheet" href="../assets/style.css">
<style>
body { background: #f4f6f8; font-family: 'Poppins', sans-serif; }
.container { width: 500px; margin: 50px auto; background: #fff; border-radius: 12px; padding: 25px; box-shadow: 0 6px 20px rgba(0,0,0,0.1);}
h2 { text-align: center; color: #333; }
input, select, button { width: 100%; padding: 10px; margin-top: 10px; border: 1px solid #ccc; border-radius: 8px; }
button { background: #007BFF; color: #fff; font-weight: bold; cursor: pointer; }
button:hover { background: #0056d2; }
.error { color: #d9534f; background: #f8d7da; padding: 10px; border-radius: 8px; margin-bottom: 10px; }
.success { color: #155724; background: #d4edda; padding: 10px; border-radius: 8px; margin-bottom: 10px; }
a.retour { display: inline-block; margin-top: 10px; color: #007BFF; text-decoration: none; }
a.retour:hover { text-decoration: underline; }
.hidden { display: none; }
</style>
</head>
<body>

<div class="container">
        <a href="deconnexion.php" class="btn btn-danger mt-3">Se déconnecter</a>

<h2>👤 Ajouter un administrateur</h2>

<?php if(!empty($erreur)): ?>
    <p class="error"><?= $erreur ?></p>
<?php elseif(!empty($success)): ?>
    <p class="success"><?= $success ?></p>
<?php endif; ?>

<form method="POST" id="formAdmin">
    <label>Rôle</label>
    <select name="role" id="roleSelect" required>
        <option value="administration">Administration</option>
        <option value="bureau">Bureau</option>
        <option value="super_admin">Super Admin</option>
    </select>

    <div id="classiqueFields">
        <label>Nom</label>
        <input type="text" name="nom" placeholder="Nom">

        <label>Prénom</label>
        <input type="text" name="prenom" placeholder="Prénom">

        <label>Email</label>
        <input type="email" name="email" placeholder="Email">

        <label>Mot de passe</label>
        <input type="password" name="mot_de_passe" placeholder="Mot de passe">
    </div>

    <div id="bureauFields" class="hidden">
        <label>Étudiant</label>
        <input list="etudiantsList" name="id_etudiant" placeholder="Rechercher étudiant...">
        <datalist id="etudiantsList">
            <?php foreach($etudiants as $e): ?>
                <option value="<?= $e['id_etudiant'] ?>"><?= $e['matricule'] ?> - <?= $e['nom'] ?> <?= $e['prenom'] ?></option>
            <?php endforeach; ?>
        </datalist>

        <label>Poste du bureau</label>
        <select name="poste_bureau">
            <option value="président">Président</option>
            <option value="vice-président">Vice-président</option>
            <option value="trésorier">Trésorier</option>
            <option value="secrétaire">Secrétaire</option>
            <option value="organisateur">Organisateur</option>
        </select>
    </div>

    <button type="submit">Créer le compte</button>
</form>

<a href="dashboard_admin.php" class="retour">⬅ Retour au tableau de bord</a>
</div>

<script>
const roleSelect = document.getElementById('roleSelect');
const classiqueFields = document.getElementById('classiqueFields');
const bureauFields = document.getElementById('bureauFields');

roleSelect.addEventListener('change', function() {
    if (this.value === 'bureau') {
        classiqueFields.classList.add('hidden');
        bureauFields.classList.remove('hidden');
    } else {
        classiqueFields.classList.remove('hidden');
        bureauFields.classList.add('hidden');
    }
});

// Initial check
if(roleSelect.value === 'bureau'){
    classiqueFields.classList.add('hidden');
    bureauFields.classList.remove('hidden');
}
</script>

</body>
</html>
