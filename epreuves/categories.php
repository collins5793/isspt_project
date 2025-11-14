<?php
session_start();
require_once '../includes/db.php';

// Vérifier si admin
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header("Location: index.php");
    exit;
}

// Suppression d'une catégorie
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $pdo->prepare("DELETE FROM epreuves_categories WHERE id_category = ?");
    $stmt->execute([$id]);
    header("Location: categories.php");
    exit;
}

// Récupérer toutes les catégories
$categories = $pdo->query("SELECT c.*, a.nom AS created_by_name 
                           FROM epreuves_categories c
                           LEFT JOIN administrateurs a ON c.created_by = a.id_admin
                           ORDER BY c.nom_category")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>📁 Gestion des catégories</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container my-5">
    <h2 class="mb-4">📁 Catégories d'épreuves</h2>
    <a href="ajouter_categorie.php" class="btn btn-success mb-3">➕ Ajouter une catégorie</a>

    <table class="table table-striped table-hover align-middle">
        <thead class="table-light">
            <tr>
                <th>#</th>
                <th>Nom</th>
                <th>Description</th>
                <th>Créée par</th>
                <th>Date création</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php
            if ($categories) {
                $i = 1;
                foreach ($categories as $cat) {
                    echo "<tr>";
                    echo "<td>{$i}</td>";
                    echo "<td>".htmlspecialchars($cat['nom_category'])."</td>";
                    echo "<td>".htmlspecialchars($cat['description'])."</td>";
                    echo "<td>".htmlspecialchars($cat['created_by_name'] ?? 'N/A')."</td>";
                    echo "<td>".htmlspecialchars($cat['created_at'])."</td>";
                    echo "<td>
                            <a href='modifier_categorie.php?id={$cat['id_category']}' class='btn btn-sm btn-warning me-1'>✏️ Modifier</a>
                            <a href='categories.php?delete={$cat['id_category']}' class='btn btn-sm btn-danger' onclick='return confirm(\"Voulez-vous vraiment supprimer cette catégorie ?\")'>🗑️ Supprimer</a>
                          </td>";
                    echo "</tr>";
                    $i++;
                }
            } else {
                echo "<tr><td colspan='6' class='text-center text-muted'>Aucune catégorie trouvée.</td></tr>";
            }
            ?>
        </tbody>
    </table>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

