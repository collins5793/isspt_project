<?php
session_start();
require_once '../../includes/db.php';

// Vérifier si l’admin est connecté
if (!isset($_SESSION['admin_id'])) {
    header('Location: connexion_admin.php');
    exit();
}

// Vérifier que l'id est fourni
if (!isset($_GET['id'])) {
    header('Location: bureau.php');
    exit();
}

$id_admin = intval($_GET['id']);
$message = '';

// Récupérer les informations du membre
$stmt = $pdo->prepare("
    SELECT a.*, e.nom, e.prenom 
    FROM administrateurs a
    LEFT JOIN etudiants e ON a.id_etudiant = e.id_etudiant
    WHERE a.id_admin = ? AND a.role = 'bureau'
");
$stmt->execute([$id_admin]);
$member = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$member) {
    $_SESSION['message'] = "<div class='alert alert-warning'>Membre du bureau introuvable.</div>";
    header('Location: bureau.php');
    exit();
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $poste_bureau = $_POST['poste_bureau'] ?? null;

    // Vérifier si le poste existe déjà pour un autre membre
    if (!empty($poste_bureau)) {
        $check = $pdo->prepare("
            SELECT * FROM administrateurs 
            WHERE role = 'bureau' AND poste_bureau = ? AND id_admin != ?
        ");
        $check->execute([$poste_bureau, $id_admin]);

        if ($check->rowCount() > 0) {
            $message = "<div class='alert alert-warning'>Ce rôle est déjà attribué à un autre membre.</div>";
        }
    }

    // Si pas de problème, mise à jour
    if (empty($message)) {
        $update = $pdo->prepare("
            UPDATE administrateurs 
            SET poste_bureau = ? 
            WHERE id_admin = ?
        ");
        if ($update->execute([$poste_bureau, $id_admin])) {
            $_SESSION['message'] = "<div class='alert alert-success'>Membre du bureau modifié avec succès.</div>";
            header('Location: bureau.php');
            exit();
        } else {
            $message = "<div class='alert alert-danger'>Erreur lors de la mise à jour du membre.</div>";
        }
    }
}

ob_start();
?>

<div class="page-header d-flex justify-content-between align-items-center mb-4">
    <h1>Modifier un membre du bureau</h1>
    <a href="bureau.php" class="btn btn-secondary">⬅ Retour à la liste</a>
</div>

<?= $message ?>

<div class="card">
    <div class="card-header">
        <h3>Étudiant : <?= htmlspecialchars($member['nom'] . ' ' . $member['prenom']) ?></h3>
    </div>
    <div class="card-body">
        <form method="POST">
            <div class="form-group mb-3">
                <label>Rôle dans le bureau :</label>
                <select name="poste_bureau" class="form-control">
                    <option value="" <?= empty($member['poste_bureau']) ? 'selected' : '' ?>>Membre</option>
                    <option value="président" <?= ($member['poste_bureau'] == 'président') ? 'selected' : '' ?>>Président</option>
                    <option value="vice-président" <?= ($member['poste_bureau'] == 'vice-président') ? 'selected' : '' ?>>Vice-président</option>
                    <option value="trésorier" <?= ($member['poste_bureau'] == 'trésorier') ? 'selected' : '' ?>>Trésorier</option>
                    <option value="secrétaire" <?= ($member['poste_bureau'] == 'secrétaire') ? 'selected' : '' ?>>Secrétaire</option>
                    <option value="organisateur" <?= ($member['poste_bureau'] == 'organisateur') ? 'selected' : '' ?>>Organisateur</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary w-100">Modifier le membre</button>
        </form>
    </div>
</div>

<?php
$content = ob_get_clean();
include '../layout.php';
?>
