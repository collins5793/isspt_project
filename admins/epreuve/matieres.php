<?php
session_start();
require_once '../../includes/db.php';

// DELETE
if (isset($_GET['delete'])) {
    $pdo->prepare("DELETE FROM matiere_epreuves WHERE id_matiere = ?")->execute([intval($_GET['delete'])]);
    header("Location: matieres.php");
    exit;
}

// Recherche
$search = $_GET['search'] ?? '';

$sql = "SELECT m.*, f.nom_filiere
        FROM matiere_epreuves m
        LEFT JOIN filieres f ON m.id_filiere = f.id_filiere
        WHERE 1 ";

if ($search !== "") {
    $sql .= " AND (m.nom_matiere LIKE :s OR f.nom_filiere LIKE :s)";
}

$sql .= " ORDER BY m.nom_matiere ASC";

$stmt = $pdo->prepare($sql);
if ($search !== "") $stmt->bindValue(":s", "%$search%");
$stmt->execute();
$matieres = $stmt->fetchAll();

// ---- Layout ----
ob_start();
?>

<div class="page-header d-flex justify-content-between align-items-center mb-4">
    <h2 class="page-title">📚 Matières</h2>
    <a href="ajouter_matiere.php" class="btn btn-primary">➕ Ajouter</a>
</div>

<form class="card p-3 mb-4" method="GET">
    <div class="row g-3">
        <div class="col-md-10">
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                   class="form-control" placeholder="Rechercher une matière...">
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
    <th>Matière</th>
    <th>Filière</th>
    <th>Description</th>
    <th>Date</th>
    <th>Actions</th>
</tr>
</thead>
<tbody>

<?php if(empty($matieres)): ?>
<tr><td colspan="6" class="text-center text-muted">Aucune matière trouvée</td></tr>
<?php else: ?>
<?php foreach($matieres as $i => $m): ?>
<tr>
    <td><?= $i+1 ?></td>
    <td><?= htmlspecialchars($m['nom_matiere']) ?></td>
    <td><?= htmlspecialchars($m['nom_filiere']) ?></td>
    <td><?= htmlspecialchars($m['description']) ?></td>
    <td><?= htmlspecialchars($m['created_at']) ?></td>
    <td>
        <a href="modifier_matiere.php?id=<?= $m['id_matiere'] ?>" class="btn btn-warning btn-sm">✏️</a>
        <a href="matieres.php?delete=<?= $m['id_matiere'] ?>" class="btn btn-danger btn-sm"
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
