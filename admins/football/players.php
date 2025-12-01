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

// 3. Récupérer les équipes pour la saison
$teamsStmt = $pdo->prepare("SELECT * FROM football_teams WHERE season_id = ? ORDER BY name ASC");
$teamsStmt->execute([$season_id]);
$teams = $teamsStmt->fetchAll(PDO::FETCH_ASSOC);

// 4. Filtrage par équipe et recherche
$team_filter = isset($_GET['team_id']) ? intval($_GET['team_id']) : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// 5. Construire la requête dynamique
$sql = "
    SELECT tp.player_id, tp.team_id, tp.season_id, tp.position, tp.shirt_number, tp.is_captain,
           tp.full_name, 
           t.name AS team_name,
           CONCAT(e.nom, ' ', e.prenom) AS student_name,
           COUNT(mg.goal_id) AS goals
    FROM team_players tp
    JOIN football_teams t ON t.team_id = tp.team_id
    LEFT JOIN etudiants e ON e.id_etudiant = tp.user_id
    LEFT JOIN match_goals mg ON mg.player_id = tp.player_id AND mg.season_id = tp.season_id
    WHERE tp.season_id = ?
";

$params = [$season_id];

if ($team_filter) {
    $sql .= " AND tp.team_id = ?";
    $params[] = $team_filter;
}

if ($search) {
    $sql .= " AND (tp.full_name LIKE ? OR CONCAT(e.nom,' ',e.prenom) LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " GROUP BY tp.player_id ORDER BY t.name ASC, goals DESC, tp.full_name ASC";

$playersStmt = $pdo->prepare($sql);
$playersStmt->execute($params);
$players = $playersStmt->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>📋 Joueurs - Saison <?= htmlspecialchars($season_id) ?></h2>
    <a href="add_player.php" class="btn btn-success">➕ Ajouter un joueur</a>
</div>

<!-- Filtres -->
<form method="GET" class="mb-4 row g-2">
    <div class="col-md-3">
        <select name="season_id" class="form-select" onchange="this.form.submit()">
            <?php foreach($allSeasons as $s): ?>
                <option value="<?= $s['id_season'] ?>" <?= $s['id_season'] == $season_id ? 'selected' : '' ?>>
                    <?= htmlspecialchars($s['label']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-3">
        <select name="team_id" class="form-select" onchange="this.form.submit()">
            <option value="0">-- Toutes les équipes --</option>
            <?php foreach($teams as $t): ?>
                <option value="<?= $t['team_id'] ?>" <?= $t['team_id'] == $team_filter ? 'selected' : '' ?>>
                    <?= htmlspecialchars($t['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-4">
        <input type="text" name="search" class="form-control" placeholder="Rechercher un joueur..." value="<?= htmlspecialchars($search) ?>">
    </div>
    <div class="col-md-2">
        <button type="submit" class="btn btn-primary w-100">Filtrer</button>
    </div>
</form>

<!-- Tableau des joueurs -->
<div class="table-responsive">
    <table class="table table-bordered table-striped">
        <thead class="table-dark">
            <tr>
                <th>#</th>
                <th>Nom complet</th>
                <th>Équipe</th>
                <th>Position</th>
                <th>Numéro</th>
                <th>Capitaine</th>
                <th>Buts</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if($players): ?>
            <?php foreach($players as $i => $p): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= htmlspecialchars($p['full_name'] ?? $p['student_name'] ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($p['team_name'] ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($p['position'] ?? '') ?></td>
                    <td><?= htmlspecialchars($p['shirt_number'] ?? '') ?></td>
                    <td><?= !empty($p['is_captain']) ? 'Oui' : 'Non' ?></td>
                    <td><?= $p['goals'] ?? 0 ?></td>
                    <td>
                        <a href="modifier_joueur.php?id=<?= $p['player_id'] ?>" class="btn btn-sm btn-primary">✏️ Modifier</a>
                        <a href="delete_joueur.php?id=<?= $p['player_id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Supprimer ce joueur ?')">🗑️ Supprimer</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr><td colspan="8" class="text-center">Aucun joueur trouvé pour cette saison / équipe.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<?php
$content = ob_get_clean();
include '../layout.php';
?>
