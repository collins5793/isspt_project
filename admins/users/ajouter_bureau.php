<?php
session_start();
require_once '../../includes/db.php';

// Vérifier si l’admin est connecté
if (!isset($_SESSION['admin_id'])) {
    header('Location: connexion_admin.php');
    exit();
}

$message = '';

// =====================
// Traitement du formulaire
// =====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_etudiant = intval($_POST['id_etudiant']);
    $poste_bureau = $_POST['poste_bureau'] ?? null;

    // Vérifier si l'étudiant est déjà membre du bureau
    $check = $pdo->prepare("SELECT * FROM administrateurs WHERE role = 'bureau' AND id_etudiant = ?");
    $check->execute([$id_etudiant]);

    if ($check->rowCount() > 0) {
        $message = "<div class='alert alert-warning'>Cet étudiant est déjà membre du bureau.</div>";
    } else {
        $stmt = $pdo->prepare("INSERT INTO administrateurs (role, id_etudiant, poste_bureau) VALUES ('bureau', ?, ?)");
        $result = $stmt->execute([$id_etudiant, $poste_bureau]);

        if ($result) {
            header("Location: bureau.php?success=1");
            exit();
        } else {
            $message = "<div class='alert alert-danger'>Erreur lors de l'ajout du membre.</div>";
        }
    }
}

// =====================
// Recherche rapide
// =====================
$search = $_GET['search'] ?? '';
$query = "SELECT * FROM etudiants WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (nom LIKE :s OR prenom LIKE :s)";
    $params['s'] = "%$search%";
}

$query .= " ORDER BY nom ASC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$etudiants = $stmt->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>

<div class="page-header d-flex justify-content-between align-items-center mb-4">
    <h1>Ajouter un membre du bureau</h1>
    <a href="bureau.php" class="btn btn-secondary">⬅ Retour à la liste</a>
</div>

<?= $message ?>

<!-- Formulaire de recherche -->
<form method="GET" class="mb-4">
    <input type="text" name="search" class="form-control" placeholder="Rechercher un étudiant par nom ou prénom" value="<?= htmlspecialchars($search) ?>">
</form>

<div class="row">
    <?php if (!empty($etudiants)): ?>
        <?php foreach ($etudiants as $e): ?>
            <div class="col-md-4 mb-4">
                <div class="card h-100">
                    <?php if (!empty($e['photo']) && file_exists('../uploads/photos_etudiants/' . $e['photo'])): ?>
                        <img src="../uploads/photos_etudiants/<?= htmlspecialchars($e['photo']) ?>" class="card-img-top" style="height:200px; object-fit:cover;">
                    <?php else: ?>
                        <img src="../assets/default/avatar.png" class="card-img-top" style="height:200px; object-fit:cover;">
                    <?php endif; ?>
                    <div class="card-body text-center">
                        <h5 class="card-title"><?= htmlspecialchars($e['nom'] . ' ' . $e['prenom']) ?></h5>
                        <p class="card-text"><?= htmlspecialchars($e['filiere'] ?? '') ?> - <?= htmlspecialchars($e['promotion'] ?? '') ?></p>

                        <!-- Formulaire d'ajout rapide -->
                        <form method="POST">
                            <input type="hidden" name="id_etudiant" value="<?= $e['id_etudiant'] ?>">
                            <select name="poste_bureau" class="form-control mb-2">
                                <option value="">Membre</option>
                                <option value="président">Président</option>
                                <option value="vice-président">Vice-président</option>
                                <option value="trésorier">Trésorier</option>
                                <option value="secrétaire">Secrétaire</option>
                                <option value="organisateur">Organisateur</option>
                            </select>
                            <button type="submit" class="btn btn-success w-100">Ajouter au bureau</button>
                        </form>

                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="col-12">
            <div class="alert alert-info text-center">Aucun étudiant trouvé.</div>
        </div>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
include '../layout.php';
?>
