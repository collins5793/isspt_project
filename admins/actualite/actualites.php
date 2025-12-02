<?php
session_start();
require_once "../../includes/db.php";

// Récupérer toutes les actualités avec info admin et année académique
$stmt = $pdo->query("
    SELECT a.*, ad.nom AS admin_nom, ad.prenom AS admin_prenom, ay.label AS academic_year
    FROM actualites a
    LEFT JOIN administrateurs ad ON a.id_admin = ad.id_admin
    LEFT JOIN academic_years ay ON a.id_academic_year = ay.id
    ORDER BY a.date_publication DESC
");
$actualites = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Messages de succès ou erreur depuis la session
$success = $_SESSION['success'] ?? null;
$error = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);

ob_start();
?>

<div class="page-header">
    <h2>Liste des Actualités</h2>
    <a href="actualite_ajouter.php" class="btn btn-primary">Ajouter une actualité</a>
</div>

<?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if ($actualites): ?>
    <table class="table">
        <thead>
            <tr>
                <th>#</th>
                <th>Titre</th>
                <th>Année Académique</th>
                <th>Auteur</th>
                <th>Date de Publication</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($actualites as $i => $act): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= htmlspecialchars($act['titre']) ?></td>
                    <td><?= htmlspecialchars($act['academic_year']) ?></td>
                    <td><?= htmlspecialchars($act['admin_prenom'] . ' ' . $act['admin_nom']) ?></td>
                    <td><?= date('d/m/Y H:i', strtotime($act['date_publication'])) ?></td>
                    <td>
                        <a href="actualite_modifier.php?id=<?= $act['id'] ?>" class="btn btn-warning btn-sm">Modifier</a>
                        <a href="actualite_supprimer.php?id=<?= $act['id'] ?>" class="btn btn-danger btn-sm"
                           onclick="return confirm('Voulez-vous vraiment supprimer cette actualité ?');">
                            Supprimer
                        </a>
                        <a href="actualite_detail.php?id=<?= $act['id'] ?>" class="btn btn-info btn-sm">Voir</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php else: ?>
    <p>Aucune actualité publiée pour le moment.</p>
<?php endif; ?>

<style>
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}
.table {
    width: 100%;
    border-collapse: collapse;
}
.table th, .table td {
    padding: 10px 15px;
    border: 1px solid #ddd;
    text-align: left;
}
.btn {
    padding: 5px 10px;
    border-radius: 5px;
    text-decoration: none;
    color: #fff;
    font-size: 0.9rem;
}
.btn-primary { background: rgb(8, 0, 32); }
.btn-warning { background: #f0ad4e; }
.btn-danger { background: #d9534f; }
.btn-info { background: #5bc0de; }
.alert {
    padding: 10px 15px;
    border-radius: 6px;
    margin-bottom: 20px;
}
.alert-success { background: #d4edda; color: #155724; }
.alert-danger { background: #f8d7da; color: #721c24; }
</style>

<?php
$content = ob_get_clean();
include "../layout.php";
?>
