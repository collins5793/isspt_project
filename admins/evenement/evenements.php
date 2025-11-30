<?php
session_start();
require_once "../../includes/db.php";

// Récupération des événements
$stmt = $pdo->query("
    SELECT e.*, a.nom AS admin_nom, a.prenom AS admin_prenom, ay.label AS academic_year
    FROM evenements e
    LEFT JOIN administrateurs a ON e.cree_par = a.id_admin
    LEFT JOIN academic_years ay ON e.academic_year_id = ay.id
    ORDER BY e.event_start DESC
");
$evenements = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Contenu à injecter dans le layout
ob_start();
?>

<div class="page-header">
    <h2>Événements</h2>
    <a href="evenement_ajouter.php" class="btn btn-primary">Ajouter un événement</a>
</div>

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
                    <a href="evenement_modifier.php?id=<?= $event['id_evenement'] ?>" class="btn btn-warning">Modifier</a>
                    <a href="evenement_supprimer.php?id=<?= $event['id_evenement'] ?>" class="btn btn-danger" onclick="return confirm('Voulez-vous vraiment supprimer cet événement ?');">Supprimer</a>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p>Aucun événement trouvé.</p>
    <?php endif; ?>
</div>

<style>
/* Styles simples pour la page */
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.events-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 20px;
}

.event-card {
    background: #fff;
    border-radius: 8px;
    padding: 15px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: transform 0.2s;
}

.event-card:hover {
    transform: translateY(-3px);
}

.event-actions {
    margin-top: 10px;
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
