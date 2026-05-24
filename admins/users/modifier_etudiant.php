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

// Récupération des données
$stmt = $pdo->prepare("SELECT * FROM etudiants WHERE id_etudiant = ?");
$stmt->execute([$id]);
$etudiant = $stmt->fetch();

if (!$etudiant) {
    header("Location: etudiants.php?error=notfound");
    exit;
}

// Traitement POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ... (Votre logique PHP reste identique, sécurisée et fonctionnelle)
    $nom = trim($_POST['nom']); $prenom = trim($_POST['prenom']); $email = trim($_POST['email']);
    $promotion = trim($_POST['promotion']); $filiere = trim($_POST['filiere']); $telephone = trim($_POST['telephone']);
    $mot_de_passe = $etudiant['mot_de_passe'];
    if (!empty($_POST['mot_de_passe'])) { $mot_de_passe = password_hash($_POST['mot_de_passe'], PASSWORD_BCRYPT); }
    $photo = $etudiant['photo'];

    if (!empty($_FILES['photo']['name'])) {
        $uploadDir = "../uploads/photos_etudiants/";
        if (!empty($photo) && file_exists($uploadDir . $photo)) { unlink($uploadDir . $photo); }
        $fileName = uniqid() . "_" . basename($_FILES['photo']['name']);
        if (move_uploaded_file($_FILES['photo']['tmp_name'], $uploadDir . $fileName)) { $photo = $fileName; }
    }

    $stmt = $pdo->prepare("UPDATE etudiants SET nom=?, prenom=?, email=?, mot_de_passe=?, promotion=?, filiere=?, telephone=?, photo=? WHERE id_etudiant=?");
    if ($stmt->execute([$nom, $prenom, $email, $mot_de_passe, $promotion, $filiere, $telephone, $photo, $id])) {
        header("Location: etudiants.php?updated=1");
        exit;
    } else {
        $message = "<div class='alert error'>Erreur lors de la mise à jour.</div>";
    }
}

ob_start();
?>

<style>
    .page-container { max-width: 900px; margin: 0 auto; padding: var(--space-4); }
    
    .header-actions { display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-5); }
    .header-actions h1 { color: var(--white); font-size: var(--font-size-xl); margin: 0; }

    .form-card { 
        background: var(--primary-800); 
        border: var(--sidebar-border); 
        border-radius: var(--radius-lg); 
        padding: var(--space-5); 
        box-shadow: var(--shadow-md);
    }

    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4); }
    
    .form-group { margin-bottom: var(--space-4); }
    .form-group label { display: block; color: var(--gray-300); margin-bottom: var(--space-2); font-size: var(--font-size-sm); }
    
    .form-control { 
        width: 100%; padding: 12px; background: var(--primary-900); border: 1px solid var(--primary-700); 
        border-radius: var(--radius-md); color: var(--white); transition: var(--transition-base);
    }
    .form-control:focus { border-color: var(--accent-blue); outline: none; box-shadow: 0 0 0 2px rgba(46, 134, 222, 0.2); }

    .photo-section { text-align: center; padding: var(--space-4); border: 1px dashed var(--primary-700); border-radius: var(--radius-md); }
    .photo-preview { width: 120px; height: 120px; border-radius: 50%; object-fit: cover; margin-bottom: var(--space-3); border: 3px solid var(--primary-600); }

    .btn-submit { background: var(--accent-blue); color: white; border: none; padding: 14px; width: 100%; border-radius: var(--radius-md); font-weight: 600; cursor: pointer; margin-top: var(--space-4); }
    .btn-submit:hover { background: var(--primary-600); }
    
    .btn-back { color: var(--gray-400); text-decoration: none; font-size: var(--font-size-sm); }

    .alert { padding: var(--space-3); border-radius: var(--radius-md); margin-bottom: var(--space-4); }
    .error { background: rgba(255, 71, 87, 0.1); color: var(--accent-red); border: 1px solid var(--accent-red); }

    @media (max-width: 768px) {
        .form-grid { grid-template-columns: 1fr; }
    }
</style>

<div class="page-container">
    <div class="header-actions">
        <h1>Modifier l'étudiant</h1>
        <a href="etudiants.php" class="btn-back">⬅ Retour à la liste</a>
    </div>

    <?= $message ?>

    <div class="form-card">
        <form method="POST" enctype="multipart/form-data">
            <div class="form-grid">
                <!-- Colonne Gauche -->
                <div>
                    <div class="form-group">
                        <label>Nom</label>
                        <input type="text" name="nom" class="form-control" value="<?= htmlspecialchars($etudiant['nom']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Prénom</label>
                        <input type="text" name="prenom" class="form-control" value="<?= htmlspecialchars($etudiant['prenom']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($etudiant['email']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Téléphone</label>
                        <input type="text" name="telephone" class="form-control" value="<?= htmlspecialchars($etudiant['telephone']) ?>">
                    </div>
                </div>

                <!-- Colonne Droite -->
                <div>
                    <div class="form-group">
                        <label>Promotion</label>
                        <input type="text" name="promotion" class="form-control" value="<?= htmlspecialchars($etudiant['promotion']) ?>">
                    </div>
                    <div class="form-group">
                        <label>Filière</label>
                        <input type="text" name="filiere" class="form-control" value="<?= htmlspecialchars($etudiant['filiere']) ?>">
                    </div>
                    <div class="form-group">
                        <label>Changer mot de passe</label>
                        <input type="password" name="mot_de_passe" class="form-control" placeholder="••••••••">
                    </div>
                    
                    <div class="photo-section">
                        <?php if ($etudiant['photo']) : ?>
                            <img src="../uploads/photos_etudiants/<?= $etudiant['photo'] ?>" class="photo-preview">
                        <?php endif; ?>
                        <label style="margin-bottom:0;">Changer la photo</label>
                        <input type="file" name="photo" style="margin-top: 10px; width: 100%; color: var(--gray-400);">
                    </div>
                </div>
            </div>

            <button type="submit" class="btn-submit">Enregistrer les modifications</button>
        </form>
    </div>
</div>

<?php
$content = ob_get_clean();
include "../layout.php";
?>