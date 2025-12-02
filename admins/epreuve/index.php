<?php
session_start();
require_once '../../includes/db.php'; // connexion PDO

// Récupération des épreuves avec jointures pour infos
$query = "
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
    ORDER BY e.date_ajout DESC
";
$epreuves = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);

// Gestion messages
$success = $_GET['success'] ?? '';


// --- Contenu à injecter dans le layout ---
ob_start();
?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>📄 Liste des Épreuves</h2>
        <a href="ajouter_epreuve.php" class="btn btn-success">➕ Ajouter une épreuve</a>
    </div>

    <?php if($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <div class="table-responsive">
        <table class="table table-striped table-bordered align-middle">
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
                    <th>Date d'ajout</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($epreuves)): ?>
                    <tr><td colspan="10" class="text-center">Aucune épreuve disponible.</td></tr>
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
                                <a href="modifier_epreuve.php?id=<?= $e['id_epreuve'] ?>" class="btn btn-sm btn-primary">✏️ Modifier</a>
                                <a href="supprimer_epreuve.php?id=<?= $e['id_epreuve'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Supprimer cette épreuve ?')">🗑️ Supprimer</a>
                                <a href="uploads/<?= $e['file_path'] ?>" class="btn btn-sm btn-success" download>⬇️ Télécharger</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php
// Récupération du contenu et injection dans le layout
$content = ob_get_clean();
include '../layout.php';
?>