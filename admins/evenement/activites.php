<?php
session_start();
require_once "../../includes/db.php";

// -------------------------------
//  Récupération des années académiques
// -------------------------------
$years = $pdo->query("
    SELECT id, label, is_current 
    FROM academic_years 
    ORDER BY start_date DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Déterminer l'année active
$defaultYear = null;
foreach ($years as $y) {
    if ($y['is_current']) {
        $defaultYear = $y['id'];
        break;
    }
}
if (!$defaultYear && !empty($years)) {
    $defaultYear = $years[0]['id']; // fallback
}

// Année sélectionnée via GET
$selectedYear = isset($_GET['year']) ? intval($_GET['year']) : $defaultYear;

// -------------------------------
//  Récupération des activités filtrées
// -------------------------------
$stmt = $pdo->prepare("
    SELECT act.*, 
           a.nom AS admin_nom, 
           a.prenom AS admin_prenom, 
           ay.label AS academic_year
    FROM activites act
    LEFT JOIN administrateurs a ON act.cree_par = a.id_admin
    LEFT JOIN academic_years ay ON act.academic_year_id = ay.id
    WHERE act.academic_year_id = ?
    ORDER BY act.date_creation DESC
");

$stmt->execute([$selectedYear]);
$activites = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Contenu à injecter dans le layout
ob_start();
?>

<div class="page-header">
    <h2>Activités</h2>
    <div class="actions">
        <a href="ajouter_activite.php" class="btn btn-primary">Ajouter une activité</a>
    </div>
</div>

<!-- FILTRE PAR ANNÉE ACADÉMIQUE -->
<form method="GET" class="filter-form">
    <label for="year">Année académique :</label>
    <select name="year" id="year" onchange="this.form.submit()">
        <?php foreach ($years as $year): ?>
            <option value="<?= $year['id'] ?>" 
                <?= ($year['id'] == $selectedYear) ? 'selected' : '' ?>>
                <?= htmlspecialchars($year['label']) ?>
                <?= $year['is_current'] ? ' (en cours)' : '' ?>
            </option>
        <?php endforeach; ?>
    </select>
</form>

<hr>

<!-- Affichage des activités -->
<div class="events-grid">
    <?php if ($activites): ?>
        <?php foreach ($activites as $act): ?>
            <div class="event-card">
                <h3 class="event-title"><?= htmlspecialchars($act['nom_activite']) ?></h3>
                <p><strong>Description:</strong> <?= nl2br(htmlspecialchars($act['description'] ?? '')) ?></p>
                <p><strong>Conditions:</strong> <?= nl2br(htmlspecialchars($act['conditions'] ?? '')) ?></p>
                <p><strong>Année académique:</strong> <?= htmlspecialchars($act['academic_year']) ?></p>
                <p><strong>Créé par:</strong> <?= htmlspecialchars($act['admin_prenom'] . ' ' . $act['admin_nom']) ?></p>

                <div class="event-actions">
                    <a href="activite_detail.php?id=<?= $act['id_activite'] ?>" class="btn btn-info">Voir détails</a>
                    <a href="modifier_activite.php?id=<?= $act['id_activite'] ?>" class="btn btn-warning">Modifier</a>
                    <a href="activite_supprimer.php?id=<?= $act['id_activite'] ?>" class="btn btn-danger"
                       onclick="return confirm('Voulez-vous vraiment supprimer cette activité ?');">
                       Supprimer
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p style="text-align:center;margin-top:20px;">Aucune activité trouvée pour cette année académique.</p>
    <?php endif; ?>
</div>

<style>
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.filter-form {
    margin-bottom: 15px;
}

.filter-form select {
    padding: 6px 10px;
    font-size: 15px;
}

.events-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 20px;
}

.event-card {
    background: #fff;
    border-radius: 8px;
    padding: 18px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.12);
}

.event-title {
    margin-top: 0;
    margin-bottom: 10px;
    color: #333;
    font-size: 20px;
    font-weight: bold;
}

.event-actions {
    margin-top: 12px;
    display: flex;
    justify-content: space-between;
    flex-wrap: wrap;
}

.event-actions .btn {
    margin-top: 5px;
}
</style>

<?php
$content = ob_get_clean();
include "../layout.php";
