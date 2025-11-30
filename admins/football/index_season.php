<?php
session_start();
require_once '../../includes/db.php'; // connexion PDO

// Vérifier si l'utilisateur est admin
$isAdmin = isset($_SESSION['admin_id']);

// --- Supprimer une saison si action demandée ---
if ($isAdmin && isset($_GET['delete'])) {
    $id_to_delete = (int) $_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM football_seasons WHERE id_season = ?");
    $stmt->execute([$id_to_delete]);
    header("Location: football_seasons.php?message=deleted");
    exit;
}

// --- Récupérer toutes les saisons ---
$stmt = $pdo->query("
    SELECT fs.*, ay.label AS academic_year
    FROM football_seasons fs
    JOIN academic_years ay ON fs.academic_year_id = ay.id
    ORDER BY fs.created_at DESC
");
$seasons = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>📅 Gestion des saisons de football</title>
<link rel="stylesheet" href="../assets/css/index.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
.table-container { max-width: 1000px; margin: 30px auto; }
table { width: 100%; border-collapse: collapse; }
th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }
th { background: #f4f4f4; }
.actions a { margin-right: 5px; text-decoration: none; }
.btn-add { background: #28a745; color: #fff; padding: 8px 12px; border-radius: 5px; }
.btn-edit { color: #007bff; }
.btn-delete { color: #dc3545; }
.btn-view { color: #17a2b8; text-decoration: none; }
</style>
</head>
<body>

<div class="table-container">
    <h1>📅 Gestion des saisons de football</h1>

    <?php if (isset($_GET['message']) && $_GET['message'] === 'deleted'): ?>
        <p style="background:#d4edda; color:#155724; padding:10px; border-radius:5px;">✅ Saison supprimée avec succès.</p>
    <?php endif; ?>

    <?php if ($isAdmin): ?>
        <a href="add_season.php" class="btn-add">+ Ajouter une nouvelle saison</a>
    <?php endif; ?>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Label</th>
                <th>Année académique</th>
                <th>Active</th>
                <th>Créée le</th>
                <th>Détails</th>
                <?php if ($isAdmin) echo '<th>Actions</th>'; ?>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($seasons)): ?>
                <?php foreach ($seasons as $season): ?>
                    <tr>
                        <td><?= htmlspecialchars($season['id_season']) ?></td>
                        <td><?= htmlspecialchars($season['label']) ?></td>
                        <td><?= htmlspecialchars($season['academic_year']) ?></td>
                        <td><?= $season['is_active'] ? '✅' : '❌' ?></td>
                        <td><?= htmlspecialchars($season['created_at']) ?></td>
                        <td>
                            <a href="season_details.php?id=<?= $season['id_season'] ?>" class="btn-view" title="Voir détails">👁️</a>
                        </td>
                        <?php if ($isAdmin): ?>
                            <td class="actions">
                                <a href="edit_season.php?id=<?= $season['id_season'] ?>" class="btn-edit" title="Modifier">✏️</a>
                                <a href="?delete=<?= $season['id_season'] ?>" class="btn-delete" onclick="return confirm('Voulez-vous vraiment supprimer cette saison ?');" title="Supprimer">🗑️</a>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="<?= $isAdmin ? 7 : 6 ?>" style="text-align:center;">Aucune saison trouvée.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

</body>
</html>
