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
    $stmt = $pdo->prepare("DELETE FROM filieres WHERE id_filiere = ?");
    $stmt->execute([$id]);
    header("Location: filieres.php");
    exit;
}

// Récupérer toutes les filières
$filieres = $pdo->query("SELECT * FROM filieres ORDER BY nom_filiere")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>🎓 Gestion des filières</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container my-5">
    <h2>🎓 Filieres</h2>
    <a href="ajouter_filiere.php" class="btn btn-success mb-3">➕ Ajouter une filière</a>

    <table class="table table-striped table-hover align-middle">
        <thead class="table-light">
            <tr>
                <th>#</th>
                <th>Nom</th>
                <th>Description</th>
                <th>Date création</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php
            if ($filieres) {
                $i = 1;
                foreach ($filieres as $f) {
                    echo "<tr>";
                    echo "<td>{$i}</td>";
                    echo "<td>".htmlspecialchars($f['nom_filiere'])."</td>";
                    echo "<td>".htmlspecialchars($f['description'])."</td>";
                    echo "<td>".htmlspecialchars($f['created_at'])."</td>";
                    echo "<td>
                            <a href='modifier_filiere.php?id={$f['id_filiere']}' class='btn btn-sm btn-warning me-1'>✏️ Modifier</a>
                            <a href='filieres.php?delete={$f['id_filiere']}' class='btn btn-sm btn-danger' onclick='return confirm(\"Voulez-vous vraiment supprimer cette filière ?\")'>🗑️ Supprimer</a>
                          </td>";
                    echo "</tr>";
                    $i++;
                }
            } else {
                echo "<tr><td colspan='5' class='text-center text-muted'>Aucune filière trouvée.</td></tr>";
            }
            ?>
        </tbody>
    </table>
</div>
</body>
</html>
