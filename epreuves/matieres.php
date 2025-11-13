<?php
session_start();
require_once '../includes/db.php';

// Vérification admin
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header("Location: index.php");
    exit;
}

// Suppression
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $pdo->prepare("DELETE FROM matiere_epreuves WHERE id_matiere = ?");
    $stmt->execute([$id]);
    header("Location: matieres.php");
    exit;
}

// Récupérer toutes les matières avec filière
$matieres = $pdo->query("SELECT m.*, f.nom_filiere 
                         FROM matiere_epreuves m
                         LEFT JOIN filieres f ON m.id_filiere = f.id_filiere
                         ORDER BY m.nom_matiere")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>📚 Gestion des matières</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container my-5">
    <h2>📚 Matières</h2>
    <a href="ajouter_matiere.php" class="btn btn-success mb-3">➕ Ajouter une matière</a>

    <table class="table table-striped table-hover align-middle">
        <thead class="table-light">
            <tr>
                <th>#</th>
                <th>Nom matière</th>
                <th>Filière</th>
                <th>Description</th>
                <th>Date création</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php
            if ($matieres) {
                $i = 1;
                foreach ($matieres as $m) {
                    echo "<tr>";
                    echo "<td>{$i}</td>";
                    echo "<td>".htmlspecialchars($m['nom_matiere'])."</td>";
                    echo "<td>".htmlspecialchars($m['nom_filiere'])."</td>";
                    echo "<td>".htmlspecialchars($m['description'])."</td>";
                    echo "<td>".htmlspecialchars($m['created_at'])."</td>";
                    echo "<td>
                            <a href='modifier_matiere.php?id={$m['id_matiere']}' class='btn btn-sm btn-warning me-1'>✏️ Modifier</a>
                            <a href='matieres.php?delete={$m['id_matiere']}' class='btn btn-sm btn-danger' onclick='return confirm(\"Voulez-vous vraiment supprimer cette matière ?\")'>🗑️ Supprimer</a>
                          </td>";
                    echo "</tr>";
                    $i++;
                }
            } else {
                echo "<tr><td colspan='6' class='text-center text-muted'>Aucune matière trouvée.</td></tr>";
            }
            ?>
        </tbody>
    </table>
</div>
</body>
</html>