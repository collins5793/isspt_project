<?php
session_start();
require_once '../../includes/db.php';

// Vérifier si l’admin est connecté
if (!isset($_SESSION['admin_id'])) {
    header('Location: connexion_admin.php');
    exit();
}

// =====================
// Récupération de la liste des admins
// =====================
$query = "
    SELECT a.*, 
           e.nom AS etu_nom, e.prenom AS etu_prenom, e.photo AS etu_photo, e.filiere AS etu_filiere, e.promotion AS etu_promotion
    FROM administrateurs a
    LEFT JOIN etudiants e ON a.id_etudiant = e.id_etudiant
    ORDER BY a.date_creation DESC
";
$stmt = $pdo->query($query);
$admins = $stmt->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>
<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success">admin ajouté avec succès !</div>
<?php endif; ?>
<div class="page-header d-flex justify-content-between align-items-center mb-4">
    <h1>Liste des administrateurs</h1>
    <a href="ajouter_admin.php" class="btn btn-success">+ Ajouter un administrateur</a>
</div>

<div class="row">
    <?php if (!empty($admins)): ?>
        <?php foreach ($admins as $a): ?>
            <div class="col-md-4 mb-4">
                <div class="card h-100">
                    <?php
                    // Image selon type
                    if ($a['role'] === 'bureau' && !empty($a['etu_photo']) && file_exists('../uploads/photos_etudiants/' . $a['etu_photo'])) {
                        $photo = '../uploads/photos_etudiants/' . $a['etu_photo'];
                        $nom = $a['etu_nom'];
                        $prenom = $a['etu_prenom'];
                        $info_sup = ($a['etu_filiere'] ?? '') . ' - ' . ($a['etu_promotion'] ?? '');
                    } else {
                        $photo = '../assets/default/avatar.png';
                        $nom = $a['nom'] ?? '';
                        $prenom = $a['prenom'] ?? '';
                        $info_sup = $a['role'];
                    }
                    ?>
                    <img src="<?= htmlspecialchars($photo) ?>" class="card-img-top" style="height:200px; object-fit:cover;">
                    <div class="card-body text-center">
                        <h5 class="card-title"><?= htmlspecialchars($nom . ' ' . $prenom) ?></h5>
                        <p class="card-text"><?= htmlspecialchars($info_sup) ?></p>
                        <?php if ($a['role'] === 'bureau'): ?>
                            <p class="text-muted">Poste: <?= htmlspecialchars($a['poste_bureau'] ?? 'Membre') ?></p>
                        <?php endif; ?>

                        <div class="d-flex justify-content-between">
                            <a href="modifier_admin.php?id=<?= $a['id_admin'] ?>" class="btn btn-sm btn-primary">Modifier</a>
                            <a href="supprimer_admin.php?id=<?= $a['id_admin'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Supprimer cet administrateur ?')">Supprimer</a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="col-12">
            <div class="alert alert-info text-center">Aucun administrateur trouvé.</div>
        </div>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
include '../layout.php';
?>
