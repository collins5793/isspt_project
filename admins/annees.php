<?php
session_start();
require_once '../includes/db.php';



// Suppression
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $pdo->prepare("DELETE FROM academic_years WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: annees.php");
    exit;
}

// Récupérer les années
$annees = $pdo->query("SELECT * FROM academic_years ORDER BY start_date DESC")->fetchAll(PDO::FETCH_ASSOC);
// --- Contenu à injecter dans le layout ---
ob_start();
?>

    <h2>📅 Années universitaires</h2>
    <a href="ajouter_annee.php" class="btn btn-success mb-3">➕ Ajouter une année</a>

    <table class="table table-striped table-hover align-middle">
        <thead class="table-light">
            <tr>
                <th>#</th>
                <th>Label</th>
                <th>Date début</th>
                <th>Date fin</th>
                <th>Actuelle</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php
            if ($annees) {
                $i = 1;
                foreach ($annees as $a) {
                    echo "<tr>";
                    echo "<td>{$i}</td>";
                    echo "<td>".htmlspecialchars($a['label'])."</td>";
                    echo "<td>".htmlspecialchars($a['start_date'])."</td>";
                    echo "<td>".htmlspecialchars($a['end_date'])."</td>";
                    echo "<td>".($a['is_current']?"✅":"")."</td>";
                    echo "<td>
                            <a href='modifier_annee.php?id={$a['id']}' class='btn btn-sm btn-warning me-1'>✏️ Modifier</a>
                            <a href='annees.php?delete={$a['id']}' class='btn btn-sm btn-danger' onclick='return confirm(\"Voulez-vous vraiment supprimer cette année ?\")'>🗑️ Supprimer</a>
                          </td>";
                    echo "</tr>";
                    $i++;
                }
            } else {
                echo "<tr><td colspan='6' class='text-center text-muted'>Aucune année trouvée.</td></tr>";
            }
            ?>
        </tbody>
    </table>
<?php
// Récupération du contenu et injection dans le layout
$content = ob_get_clean();
include 'layout.php';
?>
