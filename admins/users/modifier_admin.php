<?php
session_start();
require_once '../../includes/db.php'; // Connexion PDO

// Vérifie si l'utilisateur est connecté
if (!isset($_SESSION['admin_id'])) {
    header("Location: connexion_admin.php");
    exit;
}

// Vérifie qu'on a bien l'ID de l'admin à modifier
if (!isset($_GET['id'])) {
    header("Location: admins.php");
    exit;
}

$id_admin = intval($_GET['id']);

// Récupère les données de l'admin
$stmt = $pdo->prepare("SELECT * FROM administrateurs WHERE id_admin = ?");
$stmt->execute([$id_admin]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$admin) {
    header("Location: admins.php");
    exit;
}

// Liste des étudiants pour le bureau
$etudiants = $pdo->query("SELECT id_etudiant, matricule, nom, prenom FROM etudiants ORDER BY nom ASC")->fetchAll(PDO::FETCH_ASSOC);

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
    } elseif (($role === 'administration' || $role === 'super_admin') && (!$nom || !$prenom || !$email)) {
        $erreur = "⚠️ Tous les champs obligatoires doivent être remplis.";
    } else {
        // Vérifie si l'email est déjà utilisé par un autre admin
        if ($role !== 'bureau') {
            $check = $pdo->prepare("SELECT id_admin FROM administrateurs WHERE email = :email AND id_admin != :id_admin");
            $check->execute([':email' => $email, ':id_admin' => $id_admin]);
            if ($check->rowCount() > 0) {
                $erreur = "❌ Cet email est déjà utilisé.";
            }
        }

        if (empty($erreur)) {
            if ($role === 'bureau') {
                $stmt = $pdo->prepare("UPDATE administrateurs SET role=:role, id_etudiant=:id_etudiant, poste_bureau=:poste_bureau, nom=NULL, prenom=NULL, email=NULL, mot_de_passe=NULL WHERE id_admin=:id_admin");
                $stmt->execute([
                    ':role' => $role,
                    ':id_etudiant' => $id_etudiant,
                    ':poste_bureau' => $poste_bureau,
                    ':id_admin' => $id_admin
                ]);
            } else {
                $params = [
                    ':nom' => $nom,
                    ':prenom' => $prenom,
                    ':email' => $email,
                    ':role' => $role,
                    ':id_admin' => $id_admin
                ];

                $sql = "UPDATE administrateurs SET nom=:nom, prenom=:prenom, email=:email, role=:role";

                if (!empty($mot_de_passe)) {
                    $hash = password_hash($mot_de_passe, PASSWORD_DEFAULT);
                    $sql .= ", mot_de_passe=:mot_de_passe";
                    $params[':mot_de_passe'] = $hash;
                }

                $sql .= " WHERE id_admin=:id_admin";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
            }

            // Redirection après succès
            header("Location: admins.php?updated=1");
            exit;
        }
    }
}

// Pré-remplissage des champs
$role = $admin['role'];
$nom = $admin['nom'];
$prenom = $admin['prenom'];
$email = $admin['email'];
$id_etudiant = $admin['id_etudiant'];
$poste_bureau = $admin['poste_bureau'];

// Injection du contenu dans le layout
ob_start();
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="page-title">✏️ Modifier l'administrateur</h2>
        <a href="deconnexion.php" class="btn btn-danger btn-sm">Se déconnecter</a>
    </div>

    <?php if(!empty($erreur)): ?>
        <div class="alert alert-danger"><?= $erreur ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="mb-3">
            <label for="roleSelect" class="form-label">Rôle</label>
            <select name="role" id="roleSelect" class="form-select" required>
                <option value="administration" <?= $role==='administration' ? 'selected' : '' ?>>Administration</option>
                <option value="super_admin" <?= $role==='super_admin' ? 'selected' : '' ?>>Super Admin</option>
            </select>
        </div>

        <div id="classiqueFields">
            <div class="mb-3">
                <label class="form-label">Nom</label>
                <input type="text" name="nom" class="form-control" placeholder="Nom" value="<?= htmlspecialchars($nom) ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">Prénom</label>
                <input type="text" name="prenom" class="form-control" placeholder="Prénom" value="<?= htmlspecialchars($prenom) ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" placeholder="Email" value="<?= htmlspecialchars($email) ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">Mot de passe (laisser vide pour ne pas changer)</label>
                <input type="password" name="mot_de_passe" class="form-control">
            </div>
        </div>

        

        <button type="submit" class="btn btn-primary w-100">Mettre à jour</button>
    </form>

    <a href="liste_admins.php" class="btn btn-link mt-3">⬅ Retour à la liste des admins</a>
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
?>
