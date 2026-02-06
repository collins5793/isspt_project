<?php
session_start();
require_once '../../includes/db.php';

// ----------- Recherche & Filtre -------------
$search = $_GET['search'] ?? '';
$year_filter = $_GET['year'] ?? '';

// Construire la requête
$sql = "
    SELECT e.id_epreuve, e.titre, e.file_path, e.niveau, e.date_ajout,
           c.nom_category,
           f.nom_filiere,
           m.nom_matiere,
           ay.label AS academic_year,
           a.nom AS admin_nom, a.prenom AS admin_prenom
    FROM epreuves e
    LEFT JOIN epreuves_categories c ON e.id_category = c.id_category
    LEFT JOIN filieres f ON e.id_filiere = f.id_filiere
    LEFT JOIN matiere_epreuves m ON e.id_matiere = m.id_matiere
    LEFT JOIN academic_years ay ON e.academic_year_id = ay.id
    LEFT JOIN administrateurs a ON e.ajoute_par = a.id_admin
    WHERE 1
";

// Recherche
if ($search !== "") {
    $sql .= " AND (e.titre LIKE :search OR m.nom_matiere LIKE :search)";
}

// Filtre année
if ($year_filter !== "") {
    $sql .= " AND ay.id = :year_filter";
}

$sql .= " ORDER BY e.date_ajout DESC";

$stmt = $pdo->prepare($sql);

if ($search !== "") $stmt->bindValue(':search', "%$search%");
if ($year_filter !== "") $stmt->bindValue(':year_filter', $year_filter);

$stmt->execute();
$epreuves = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les années pour filtre
$years = $pdo->query("SELECT id, label FROM academic_years ORDER BY id DESC")->fetchAll();

// ---- Layout content ----
ob_start();
?>

<div class="page-header d-flex justify-content-between align-items-center mb-4">
    <h2 class="page-title">📄 Liste des Épreuves</h2>
    <a href="ajouter_epreuve.php" class="btn btn-primary">➕ Ajouter une épreuve</a>
</div>

<!-- Barre de recherche -->
<form class="card p-3 mb-4" method="GET">
    <div class="row g-3">
        <div class="col-md-6">
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                   class="form-control" placeholder="Rechercher une épreuve...">
        </div>
        <div class="col-md-4">
            <select name="year" class="form-select">
                <option value="">Toutes les années</option>
                <?php foreach($years as $y): ?>
                    <option value="<?= $y['id'] ?>" <?= $year_filter == $y['id'] ? 'selected' : '' ?>>
                        <?= $y['label'] ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <button class="btn btn-dark w-100">Filtrer</button>
        </div>
    </div>
</form>

<div class="table-responsive">
    <table class="table table-hover align-middle">
        <thead class="table-dark">
            <tr>
                <th>#</th>
                <th>Titre</th>
                <th>Catégorie</th>
                <th>Matière</th>
                <th>Filière</th>
                <th>Année</th>
                <th>Niveau</th>
                <th>Ajouté par</th>
                <th>Date</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if(empty($epreuves)): ?>
            <tr>
                <td colspan="10" class="text-center text-muted">Aucune épreuve trouvée.</td>
            </tr>
        <?php else: ?>
            <?php foreach($epreuves as $i => $e): ?>
            <tr>
                <td><?= $i+1 ?></td>
                <td><?= htmlspecialchars($e['titre']) ?></td>
                <td><?= htmlspecialchars($e['nom_category']) ?></td>
                <td><?= htmlspecialchars($e['nom_matiere']) ?></td>
                <td><?= htmlspecialchars($e['nom_filiere']) ?></td>
                <td><?= htmlspecialchars($e['academic_year']) ?></td>
                <td><?= htmlspecialchars($e['niveau']) ?></td>
                <td><?= htmlspecialchars($e['admin_prenom'].' '.$e['admin_nom']) ?></td>
                <td><?= htmlspecialchars($e['date_ajout']) ?></td>
                <td>
                    <a href="modifier_epreuve.php?id=<?= $e['id_epreuve'] ?>" class="btn btn-sm btn-primary">✏️</a>
                    <a href="supprimer_epreuve.php?id=<?= $e['id_epreuve'] ?>" class="btn btn-sm btn-danger"
                       onclick="return confirm('Supprimer cette épreuve ?')">🗑️</a>
                    <a href="uploads/<?= $e['file_path'] ?>" class="btn btn-sm btn-success" download>⬇️</a>
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