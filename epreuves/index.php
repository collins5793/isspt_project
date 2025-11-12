<?php
session_start();
require_once '../includes/db.php'; // connexion à la BD

// --- Traitement des filtres et recherche ---
$search = $_GET['search'] ?? '';
$filiere = $_GET['filiere'] ?? '';
$annee = $_GET['annee'] ?? '';
$type = $_GET['type'] ?? '';
$semestre = $_GET['semestre'] ?? '';

// Requête SQL dynamique
$query = "
    SELECT e.id_epreuve, e.Titre, e.description, e.file_path, 
           e.Filiere, e.Niveau, e.date_ajout, e.is_public,
           c.nom_category AS type_epreuve,
           y.nom_annee AS annee_univ
    FROM epreuves e
    LEFT JOIN epreuves_categories c ON e.id_category = c.id_category
    LEFT JOIN academic_years y ON e.academic_year_id = y.id
    WHERE 1
";

if (!empty($search)) {
    $query .= " AND e.Titre LIKE '%$search%'";
}
if (!empty($filiere)) {
    $query .= " AND e.Filiere = '$filiere'";
}
if (!empty($annee)) {
    $query .= " AND y.nom_annee = '$annee'";
}
if (!empty($type)) {
    $query .= " AND c.nom_category = '$type'";
}

$query .= " ORDER BY e.date_ajout DESC";
$result = mysqli_query($conn, $query);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>📚 Recueil d'Épreuves Universitaires</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<!-- 🟦 En-tête -->
<header class="bg-primary text-white text-center py-4 shadow-sm mb-4">
    <h1>📚 Recueil d’Épreuves Universitaires</h1>
    <p class="lead mb-0">Consultez, recherchez et téléchargez les anciennes épreuves par filière et année.</p>
</header>

<div class="container">

    <!-- 🟨 Barre de recherche et filtres -->
    <form method="GET" class="row g-3 mb-4 bg-white p-3 rounded shadow-sm">
        <div class="col-md-4">
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" class="form-control" placeholder="🔍 Rechercher une matière ou un titre">
        </div>
        <div class="col-md-2">
            <select name="filiere" class="form-select">
                <option value="">Filière</option>
                <option value="SIL" <?= $filiere=="SIL"?"selected":"" ?>>SIL</option>
                <option value="RIT" <?= $filiere=="RIT"?"selected":"" ?>>RIT</option>
                <option value="GIT" <?= $filiere=="GIT"?"selected":"" ?>>GIT</option>
            </select>
        </div>
        <div class="col-md-2">
            <select name="annee" class="form-select">
                <option value="">Année</option>
                <option value="2024-2025" <?= $annee=="2024-2025"?"selected":"" ?>>2024-2025</option>
                <option value="2023-2024" <?= $annee=="2023-2024"?"selected":"" ?>>2023-2024</option>
                <option value="2022-2023" <?= $annee=="2022-2023"?"selected":"" ?>>2022-2023</option>
            </select>
        </div>
        <div class="col-md-2">
            <select name="type" class="form-select">
                <option value="">Type</option>
                <option value="Examen national" <?= $type=="Examen national"?"selected":"" ?>>Examen national</option>
                <option value="Partiel" <?= $type=="Partiel"?"selected":"" ?>>Partiel</option>
                <option value="TP" <?= $type=="TP"?"selected":"" ?>>TP</option>
                <option value="Devoir surveillé" <?= $type=="Devoir surveillé"?"selected":"" ?>>Devoir surveillé</option>
            </select>
        </div>
        <div class="col-md-2 text-end">
            <button type="submit" class="btn btn-primary w-100">Filtrer</button>
        </div>
    </form>

    <!-- 🟩 Section Admin -->
    <?php if (isset($_SESSION['admin']) && $_SESSION['admin'] === true): ?>
        <div class="mb-3 text-end">
            <a href="ajouter_epreuve.php" class="btn btn-success me-2">➕ Ajouter une épreuve</a>
            <a href="gerer_epreuves.php" class="btn btn-secondary">⚙️ Gérer les épreuves</a>
        </div>
    <?php endif; ?>

    <!-- 🟪 Liste des épreuves -->
    <div class="card shadow-sm">
        <div class="card-header bg-secondary text-white">
            <h5 class="mb-0">Liste des épreuves disponibles</h5>
        </div>
        <div class="card-body p-0">
            <table class="table table-striped table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Titre</th>
                        <th>Filière</th>
                        <th>Année</th>
                        <th>Type</th>
                        <th>Niveau</th>
                        <th>Télécharger</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if (mysqli_num_rows($result) > 0) {
                        $i = 1;
                        while ($row = mysqli_fetch_assoc($result)) {
                            echo "<tr>";
                            echo "<td>{$i}</td>";
                            echo "<td>".htmlspecialchars($row['Titre'])."</td>";
                            echo "<td>".htmlspecialchars($row['Filiere'])."</td>";
                            echo "<td>".htmlspecialchars($row['annee_univ'])."</td>";
                            echo "<td>".htmlspecialchars($row['type_epreuve'])."</td>";
                            echo "<td>".htmlspecialchars($row['Niveau'])."</td>";
                            echo "<td><a href='../uploads/".htmlspecialchars($row['file_path'])."' class='btn btn-sm btn-primary' download>🔽 Télécharger</a></td>";
                            echo "</tr>";
                            $i++;
                        }
                    } else {
                        echo "<tr><td colspan='7' class='text-center text-muted py-3'>Aucune épreuve trouvée.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 🟧 Pagination -->
    <nav class="mt-4" aria-label="Pagination">
        <ul class="pagination justify-content-center">
            <li class="page-item disabled"><a class="page-link">Précédent</a></li>
            <li class="page-item active"><a class="page-link" href="#">1</a></li>
            <li class="page-item"><a class="page-link" href="#">2</a></li>
            <li class="page-item"><a class="page-link" href="#">Suivant</a></li>
        </ul>
    </nav>

</div>

<!-- 🟫 Pied de page -->
<footer class="text-center mt-5 mb-3 text-muted">
    <p>&copy; 2025 ISSPT - Tous droits réservés | <a href="../index.php">Retour à l'accueil</a></p>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
