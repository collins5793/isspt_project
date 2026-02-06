<?php
session_start();
require_once '../../includes/db.php';

// DELETE
if (isset($_GET['delete'])) {
    $pdo->prepare("DELETE FROM filieres WHERE id_filiere = ?")->execute([intval($_GET['delete'])]);
    header("Location: filieres.php");
    exit;
}

// Recherche
$search = $_GET['search'] ?? '';

$sql = "SELECT * FROM filieres WHERE 1 ";
if ($search !== "") {
    $sql .= " AND nom_filiere LIKE :s ";
}
$sql .= " ORDER BY nom_filiere ASC";

$stmt = $pdo->prepare($sql);
if ($search !== "") $stmt->bindValue(":s", "%$search%");
$stmt->execute();
$filieres = $stmt->fetchAll();

// ---- Layout ----
ob_start();
?>

<div class="page-header d-flex justify-content-between align-items-center mb-4">
    <h2 class="page-title">🎓 Filières</h2>
    <a href="ajouter_filiere.php" class="btn btn-primary">➕ Ajouter</a>
</div>

<form class="card p-3 mb-4" method="GET">
    <div class="row g-3">
        <div class="col-md-10">
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                   class="form-control" placeholder="Rechercher une filière...">
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
    <th>Actions</th>
</tr>
</thead>
<tbody>

<?php if(empty($filieres)): ?>
<tr><td colspan="4" class="text-center text-muted">Aucune filière trouvée</td></tr>
<?php else: ?>
<?php foreach($filieres as $i => $f): ?>
<tr>
    <td><?= $i+1 ?></td>
    <td><?= htmlspecialchars($f['nom_filiere']) ?></td>
    <td><?= htmlspecialchars($f['description']) ?></td>
    <td>
        <a href="modifier_filiere.php?id=<?= $f['id_filiere'] ?>" class="btn btn-warning btn-sm">✏️</a>
        <a href="filieres.php?delete=<?= $f['id_filiere'] ?>" class="btn btn-danger btn-sm"
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
