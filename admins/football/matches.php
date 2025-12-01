<?php
session_start();
require_once '../../includes/db.php'; // connexion PDO

// 1. Récupérer toutes les saisons
$allSeasonsStmt = $pdo->query("SELECT * FROM football_seasons ORDER BY is_active DESC, label ASC");
$allSeasons = $allSeasonsStmt->fetchAll(PDO::FETCH_ASSOC);

// 2. Déterminer la saison choisie (GET ou saison active par défaut)
$season_id = isset($_GET['season_id']) ? intval($_GET['season_id']) : 0;
if ($season_id === 0) {
    $activeSeason = $pdo->query("SELECT * FROM football_seasons WHERE is_active = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $season_id = $activeSeason ? $activeSeason['id_season'] : 0;
}

// 3. Récupérer les pools de la saison
$poolsStmt = $pdo->prepare("SELECT * FROM pools WHERE season_id = ? ORDER BY name ASC");
$poolsStmt->execute([$season_id]);
$pools = $poolsStmt->fetchAll(PDO::FETCH_ASSOC);

// 4. Récupérer tous les matchs pour cette saison
$matchesStmt = $pdo->prepare("
    SELECT m.*, 
           t1.name AS team1_name, 
           t2.name AS team2_name,
           p.name AS pool_name
    FROM matches m
    LEFT JOIN football_teams t1 ON t1.team_id = m.team1_id
    LEFT JOIN football_teams t2 ON t2.team_id = m.team2_id
    LEFT JOIN pools p ON p.pool_id = m.pool_id
    WHERE m.season_id = ?
    ORDER BY p.name ASC, m.match_datetime ASC
");
$matchesStmt->execute([$season_id]);
$allMatches = $matchesStmt->fetchAll(PDO::FETCH_ASSOC);

// Organiser les matchs par pool
$matchesByPool = [];
foreach ($allMatches as $m) {
    $poolName = $m['pool_name'] ?? 'Non défini';
    $matchesByPool[$poolName][] = $m;
}

ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>⚽ Matches - Saison <?= htmlspecialchars($season_id) ?></h2>
    <a href="add_match.php" class="btn btn-success">➕ Ajouter un match</a>
</div>

<!-- Sélect Saison -->
<form method="GET" class="mb-4">
    <div class="row g-2 col-md-4">
        <select name="season_id" class="form-select" onchange="this.form.submit()">
            <?php foreach($allSeasons as $s): ?>
                <option value="<?= $s['id_season'] ?>" <?= $s['id_season'] == $season_id ? 'selected' : '' ?>>
                    <?= htmlspecialchars($s['label']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
</form>

<?php if($matchesByPool): ?>
    <?php foreach($matchesByPool as $poolName => $matches): ?>
        <h4 class="mt-4"><?= htmlspecialchars($poolName) ?></h4>
        <div class="table-responsive mb-4">
            <table class="table table-bordered table-striped">
                <thead class="table-dark">
                    <tr>
                        <th>#</th>
                        <th>Équipe 1</th>
                        <th>Équipe 2</th>
                        <th>Date & Heure</th>
                        <th>Lieu</th>
                        <th>Score</th>
                        <th>Joué</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($matches as $i => $m): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><?= htmlspecialchars($m['team1_name'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($m['team2_name'] ?? 'N/A') ?></td>
                            <td><?= $m['match_datetime'] ? date('d/m/Y H:i', strtotime($m['match_datetime'])) : 'Non défini' ?></td>
                            <td><?= htmlspecialchars($m['location'] ?? '') ?></td>
                            <td>
                                <?= $m['score_team1'] !== null && $m['score_team2'] !== null 
                                    ? htmlspecialchars($m['score_team1'] . ' - ' . $m['score_team2']) 
                                    : '-' ?>
                            </td>
                            <td><?= $m['is_played'] ? 'Oui' : 'Non' ?></td>
                            <td>
                                <a href="modifier_match.php?id=<?= $m['match_id'] ?>" class="btn btn-sm btn-primary">✏️ Modifier</a>
                                <a href="delete_match.php?id=<?= $m['match_id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Supprimer ce match ?')">🗑️ Supprimer</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endforeach; ?>
<?php else: ?>
    <p class="text-center mt-4">Aucun match trouvé pour cette saison.</p>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<?php
$content = ob_get_clean();
include '../layout.php';
?>
