<?php
session_start();
require_once '../../includes/db.php';

// Vérifier si l’admin est connecté
if (!isset($_SESSION['admin_id'])) {
    header('Location: connexion_admin.php');
    exit();
}

// =====================
//   RÉCUPÉRER LES MEMBRES DU BUREAU
// =====================
$stmt = $pdo->query("
    SELECT a.id_admin, a.poste_bureau, e.*
    FROM administrateurs a
    LEFT JOIN etudiants e ON e.id_etudiant = a.id_etudiant
    WHERE a.role = 'bureau'
    ORDER BY a.poste_bureau ASC, e.nom ASC
");
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>

<div class="page-header d-flex justify-content-between align-items-center mb-4">
    <h1>Membres du Bureau Étudiant</h1>
    <a href="ajouter_bureau.php" class="btn btn-success">+ Ajouter un membre</a>
</div>

<div class="row">
    <?php if (!empty($members)): ?>
        <?php foreach ($members as $m): ?>
            <div class="col-md-4 mb-4">
                <div class="card h-100" style="cursor:pointer;" onclick="window.location='detail_bureau.php?id=<?= $m['id_admin'] ?>'">
                    <?php if (!empty($m['photo']) && file_exists('../uploads/photos_etudiants/' . $m['photo'])): ?>
                        <img src="../uploads/photos_etudiants/<?= htmlspecialchars($m['photo']) ?>" class="card-img-top" style="height:250px; object-fit:cover;">
                    <?php else: ?>
                        <img src="../assets/default/avatar.png" class="card-img-top" style="height:250px; object-fit:cover;">
                    <?php endif; ?>
                    <div class="card-body text-center">
                        <h5 class="card-title"><?= htmlspecialchars($m['nom'] . ' ' . $m['prenom']) ?></h5>
                        <p class="card-text"><?= htmlspecialchars($m['poste_bureau'] ?? 'Membre') ?></p>
                        <p class="card-text"><?= htmlspecialchars($m['filiere'] ?? '') ?> - <?= htmlspecialchars($m['promotion'] ?? '') ?></p>
                        <div class="d-flex justify-content-center gap-2">
                            <a href="modifier_bureau.php?id=<?= $m['id_admin'] ?>" class="btn btn-sm btn-primary">Modifier</a>
                            <a href="supprimer_bureau.php?id=<?= $m['id_admin'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Supprimer ce membre du bureau ?')">Supprimer</a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="col-12">
            <div class="alert alert-info text-center">Aucun membre du bureau trouvé.</div>
        </div>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
include '../layout.php';
?>
