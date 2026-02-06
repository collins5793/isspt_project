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
//  Récupération des événements filtrés
// -------------------------------
$stmt = $pdo->prepare("
    SELECT e.*, 
        a.nom AS admin_nom, 
        a.prenom AS admin_prenom, 
        ay.label AS academic_year
    FROM evenements e
    LEFT JOIN administrateurs a ON e.cree_par = a.id_admin
    LEFT JOIN academic_years ay ON e.academic_year_id = ay.id
    WHERE e.academic_year_id = ?
    ORDER BY e.event_start DESC
");

$stmt->execute([$selectedYear]);
$evenements = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Contenu à injecter dans le layout
ob_start();
?>

<div class="page-header">
    <h2>Événements</h2>

    <div class="actions">
        <a href="ajouter_evenement.php" class="btn btn-primary">Ajouter un événement</a>
    </div>
</div>

<!-- ⬇️ FILTRE PAR ANNÉE ACADÉMIQUE -->
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

<!-- Affichage des événements -->
<div class="events-grid">
    <?php if ($evenements): ?>
        <?php foreach ($evenements as $event): ?>
            <div class="event-card">
                <h3 class="event-title"><?= htmlspecialchars($event['nom_evenement']) ?></h3>
                <p><strong>Type:</strong> <?= htmlspecialchars($event['type_evenement']) ?></p>
                <p><strong>Date:</strong> <?= date('d/m/Y H:i', strtotime($event['event_start'])) ?></p>
                <p><strong>Lieu:</strong> <?= htmlspecialchars($event['lieu'] ?? 'Non précisé') ?></p>
                <p><strong>Prix ticket:</strong> <?= number_format($event['prix_ticket'], 2) ?> FCFA</p>
                <p><strong>Année académique:</strong> <?= htmlspecialchars($event['academic_year']) ?></p>
                <p><strong>Créé par:</strong> <?= htmlspecialchars($event['admin_prenom'] . ' ' . $event['admin_nom']) ?></p>

                <div class="event-actions">
                    <a href="evenement_detail.php?id=<?= $event['id_evenement'] ?>" class="btn btn-info">Voir détails</a>
                    <a href="modifier_evenement.php?id=<?= $event['id_evenement'] ?>" class="btn btn-warning">Modifier</a>
                    <a href="evenement_supprimer.php?id=<?= $event['id_evenement'] ?>" class="btn btn-danger"
                       onclick="return confirm('Voulez-vous vraiment supprimer cet événement ?');">
                       Supprimer
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p style="text-align:center;margin-top:20px;">Aucun événement trouvé pour cette année académique.</p>
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
