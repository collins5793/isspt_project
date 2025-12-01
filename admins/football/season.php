<?php
require_once "../../includes/db.php";

// 1. Récupérer toutes les saisons pour le select
$seasons = $pdo->query("
    SELECT id_season, label, is_active 
    FROM football_seasons 
    ORDER BY id_season DESC
")->fetchAll(PDO::FETCH_ASSOC);

// 2. Déterminer la saison à afficher
if (isset($_GET['season'])) {
    $season_id = intval($_GET['season']);
} else {
    // Saison active par défaut
    $season_id = $pdo->query("
        SELECT id_season FROM football_seasons WHERE is_active = 1 LIMIT 1
    ")->fetchColumn();
}

// 3. Charger la saison choisie
$season = $pdo->prepare("
    SELECT fs.*, ay.label AS year_label
    FROM football_seasons fs
    JOIN academic_years ay ON ay.id = fs.academic_year_id
    WHERE fs.id_season = ?
");
$season->execute([$season_id]);
$season = $season->fetch(PDO::FETCH_ASSOC);


// 4. Statistiques
$stats = [];

// Nombre d’équipes
$stats['teams'] = $pdo->prepare("
    SELECT COUNT(*) FROM football_teams WHERE season_id = ?
");
$stats['teams']->execute([$season_id]);
$stats['teams'] = $stats['teams']->fetchColumn();

// Nombre de joueurs
$stats['players'] = $pdo->prepare("
    SELECT COUNT(*) FROM team_players WHERE season_id = ?
");
$stats['players']->execute([$season_id]);
$stats['players'] = $stats['players']->fetchColumn();

// Nombre de poules
$stats['pools'] = $pdo->prepare("
    SELECT COUNT(*) FROM pools WHERE season_id = ?
");
$stats['pools']->execute([$season_id]);
$stats['pools'] = $stats['pools']->fetchColumn();

// Matchs joués / non joués
$stats['played'] = $pdo->prepare("
    SELECT COUNT(*) FROM matches WHERE season_id = ? AND is_played = 1
");
$stats['played']->execute([$season_id]);
$stats['played'] = $stats['played']->fetchColumn();

$stats['scheduled'] = $pdo->prepare("
    SELECT COUNT(*) FROM matches WHERE season_id = ?
");
$stats['scheduled']->execute([$season_id]);
$stats['scheduled'] = $stats['scheduled']->fetchColumn();

// Réglages de saison
$setting = $pdo->prepare("SELECT match_type FROM season_settings WHERE season_id = ?");
$setting->execute([$season_id]);
$setting = $setting->fetchColumn();

ob_start();

?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0">📅 Gestion des Saisons</h2>
    <a href="add_season.php" class="btn btn-success">
        ➕ Ajouter une nouvelle saison
    </a>
</div>



    <!-- Sélecteur de Saison -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h5 class="mb-3">Sélectionner une saison</h5>
            <form method="GET">
                <select name="season" class="form-select" onchange="this.form.submit()">
                    <?php foreach($seasons as $s): ?>
                        <option value="<?= $s['id_season'] ?>" 
                            <?= ($s['id_season'] == $season_id ? 'selected' : '') ?>>
                            <?= $s['label'] ?> 
                            <?= $s['is_active'] ? '(Active)' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
    </div>

    <!-- Informations de la saison -->
    <div class="card shadow-lg border-0 mb-4">
        <div class="card-header bg-dark text-white">
            <h4 class="mb-0"><?= $season['label'] ?> — <?= $season['year_label'] ?></h4>
        </div>
        <div class="card-body">

            <div class="row">
                <div class="col-md-6">
                    <p><strong>Année académique :</strong> <?= $season['year_label'] ?></p>
                    <p><strong>Date de création :</strong> <?= $season['created_at'] ?></p>
                    <p><strong>Statut :</strong> 
                        <?= $season['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>' ?>
                    </p>
                </div>

                <div class="col-md-6">
                    <p><strong>Type de match :</strong> 
                        <span class="badge bg-primary">
                            <?= strtoupper($setting ?? 'simple') ?>
                        </span>
                    </p>
                    <p><strong>Total équipes :</strong> <?= $stats['teams'] ?></p>
                    <p><strong>Total joueurs :</strong> <?= $stats['players'] ?></p>
                </div>
            </div>

            <hr>

            <div class="row text-center">
                <div class="col">
                    <h3><?= $stats['pools'] ?></h3>
                    <p>Poules</p>
                </div>
                <div class="col">
                    <h3><?= $stats['scheduled'] ?></h3>
                    <p>Matchs programmés</p>
                </div>
                <div class="col">
                    <h3><?= $stats['played'] ?></h3>
                    <p>Matchs joués</p>
                </div>
            </div>

        </div>
    </div>

    <!-- Actions -->
    <div class="text-end">
        <a href="season_view.php?id=<?= $season_id ?>" class="btn btn-primary">Afficher la saison</a>
        <a href="season_edit.php?id=<?= $season_id ?>" class="btn btn-warning">Modifier</a>
        <a href="season_delete.php?id=<?= $season_id ?>" class="btn btn-danger"
           onclick="return confirm('Supprimer cette saison ?')">Supprimer</a>
    </div>


<?php
// Récupération du contenu et injection dans le layout
$content = ob_get_clean();
include '../layout.php';
?>
