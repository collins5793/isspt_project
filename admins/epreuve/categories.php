<?php
session_start();
require_once '../../includes/db.php';

// DELETE
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $pdo->prepare("DELETE FROM epreuves_categories WHERE id_category = ?")->execute([$id]);
    header("Location: categories.php");
    exit;
}

// Recherche
$search = $_GET['search'] ?? '';

$sql = "SELECT c.*, a.nom AS admin_nom, a.prenom AS admin_prenom
        FROM epreuves_categories c
        LEFT JOIN administrateurs a ON c.created_by = a.id_admin
        WHERE 1 ";

if ($search !== "") {
    $sql .= " AND c.nom_category LIKE :s ";
}

$sql .= " ORDER BY c.nom_category ASC";

$stmt = $pdo->prepare($sql);
if ($search !== "") $stmt->bindValue(":s", "%$search%");
$stmt->execute();
$categories = $stmt->fetchAll();

// ---- Layout ----
ob_start();
?>

<div class="page-header d-flex justify-content-between align-items-center mb-4">
    <h2 class="page-title">📁 Catégories d’épreuves</h2>
    <a href="ajouter_categorie.php" class="btn btn-primary">➕ Ajouter</a>
</div>

<form class="card p-3 mb-4" method="GET">
    <div class="row g-3">
        <div class="col-md-10">
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                   class="form-control" placeholder="Rechercher une catégorie...">
        </div>
        <div class="col-md-2">
            <button class="btn btn-dark w-100">Rechercher</button>
        </div>
    </div>
</form>

<div class="table-responsive">
<table class="table table-hover align-middle">
<thead class="table-dark">
    <tr>
        <th>#</th>
        <th>Nom</th>
        <th>Description</th>
        <th>Créée par</th>
        <th>Date</th>
        <th>Actions</th>
    </tr>
</thead>
<tbody>

<?php if(empty($categories)): ?>
    <tr><td colspan="6" class="text-center text-muted">Aucune catégorie trouvée</td></tr>
<?php else: ?>
    <?php foreach($categories as $i => $cat): ?>
    <tr>
        <td><?= $i+1 ?></td>
        <td><?= htmlspecialchars($cat['nom_category']) ?></td>
        <td><?= htmlspecialchars($cat['description']) ?></td>
        <td><?= htmlspecialchars($cat['admin_prenom']." ".$cat['admin_nom']) ?></td>
        <td><?= htmlspecialchars($cat['created_at']) ?></td>
        <td>
            <a href="modifier_categorie.php?id=<?= $cat['id_category'] ?>" class="btn btn-warning btn-sm">✏️</a>
            <a href="categories.php?delete=<?= $cat['id_category'] ?>" class="btn btn-danger btn-sm"
               onclick="return confirm('Supprimer ?')">🗑️</a>
        </td>
    </tr>
    <?php endforeach; ?>
<?php endif; ?>

</tbody>
</table>
</div>

<?php
$content = ob_get_clean();
include '../layout.php';
?>
