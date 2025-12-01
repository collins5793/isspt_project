<?php
session_start();
require_once '../../includes/db.php'; // Connexion PDO

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
        if ($role !== 'bureau') {
            $check = $pdo->prepare("SELECT id_admin FROM administrateurs WHERE email = :email");
            $check->execute([':email' => $email]);
            if ($check->rowCount() > 0) {
                $erreur = "❌ Cet email est déjà utilisé.";
            }
        }

        if (empty($erreur)) {
            if ($role === 'bureau') {
                $stmt = $pdo->prepare("INSERT INTO administrateurs (role, id_etudiant, poste_bureau) VALUES (:role, :id_etudiant, :poste_bureau)");
                $stmt->execute([':role'=>$role, ':id_etudiant'=>$id_etudiant, ':poste_bureau'=>$poste_bureau]);
                $success = "✅ Le membre du bureau a été ajouté avec succès.";
            } else {
                $hash = password_hash($mot_de_passe, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO administrateurs (nom, prenom, email, mot_de_passe, role) VALUES (:nom, :prenom, :email, :mot_de_passe, :role)");
                $stmt->execute([':nom'=>$nom, ':prenom'=>$prenom, ':email'=>$email, ':mot_de_passe'=>$hash, ':role'=>$role]);
            }
            header("Location: liste_admins.php?added=1");
            exit;
        }
    }
}

// Liste des étudiants pour le bureau
$etudiants = $pdo->query("SELECT id_etudiant, matricule, nom, prenom FROM etudiants ORDER BY nom ASC")->fetchAll(PDO::FETCH_ASSOC);

// Injection du contenu dans le layout
ob_start();
?>
<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success">Admin ajouté avec succès !</div>
<?php endif; ?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="page-title">👤 Ajouter un administrateur</h2>
        <a href="deconnexion.php" class="btn btn-danger btn-sm">Se déconnecter</a>
    </div>

    <?php if(!empty($erreur)): ?>
        <div class="alert alert-danger"><?= $erreur ?></div>
    <?php elseif(!empty($success)): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>

    <form method="POST" id="formAdmin">
        <div class="mb-3">
            <label for="roleSelect" class="form-label">Rôle</label>
            <select name="role" id="roleSelect" class="form-select" required>
                <option value="administration">Administration</option>
                <option value="super_admin">Super Admin</option>
            </select>
        </div>

        <div id="classiqueFields">
            <div class="mb-3">
                <label class="form-label">Nom</label>
                <input type="text" name="nom" class="form-control" placeholder="Nom">
            </div>
            <div class="mb-3">
                <label class="form-label">Prénom</label>
                <input type="text" name="prenom" class="form-control" placeholder="Prénom">
            </div>
            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" placeholder="Email">
            </div>
            <div class="mb-3">
                <label class="form-label">Mot de passe</label>
                <input type="password" name="mot_de_passe" class="form-control" placeholder="Mot de passe">
            </div>
        </div>

        

        <button type="submit" class="btn btn-primary w-100">Créer le compte</button>
    </form>

    <a href="dashboard_admin.php" class="btn btn-link mt-3">⬅ Retour au tableau de bord</a>
</div>

<script>
const roleSelect = document.getElementById('roleSelect');
const classiqueFields = document.getElementById('classiqueFields');
const bureauFields = document.getElementById('bureauFields');

function toggleFields() {
    if (roleSelect.value === 'bureau') {
        classiqueFields.classList.add('d-none');
        bureauFields.classList.remove('d-none');
    } else {
        classiqueFields.classList.remove('d-none');
        bureauFields.classList.add('d-none');
    }
}

roleSelect.addEventListener('change', toggleFields);
toggleFields(); // Initial check
</script>

<?php
$content = ob_get_clean();
include '../layout.php';
