<?php
session_start();
require_once '../../includes/db.php';

$id_admin = $_SESSION['admin_id'] ?? null;
if (!$id_admin) die("Accès refusé");

// Récupérer l'ID du match
$match_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$match_id) die("Match non spécifié.");

// Charger le match
$stmt = $pdo->prepare("SELECT * FROM matches WHERE match_id = ?");
$stmt->execute([$match_id]);
$match = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$match) die("Match introuvable.");

// Charger saisons, pools et équipes
$seasons = $pdo->query("SELECT * FROM football_seasons ORDER BY is_active DESC, label ASC")->fetchAll(PDO::FETCH_ASSOC);
$pools = $pdo->prepare("SELECT * FROM pools WHERE season_id = ? ORDER BY name ASC");
$pools->execute([$match['season_id']]);
$pools = $pools->fetchAll(PDO::FETCH_ASSOC);

$teams = $pdo->prepare("SELECT * FROM football_teams WHERE season_id = ? ORDER BY name ASC");
$teams->execute([$match['season_id']]);
$teams = $teams->fetchAll(PDO::FETCH_ASSOC);
// Charger joueurs pour chaque équipe avec nom depuis etudiants
// Charger joueurs pour chaque équipe avec nom depuis etudiants
$team1_players_stmt = $pdo->prepare("
    SELECT tp.player_id, 
           COALESCE(CONCAT(e.nom, ' ', e.prenom), tp.full_name) AS full_name
    FROM team_players tp
    LEFT JOIN etudiants e ON tp.user_id = e.id_etudiant
    WHERE tp.team_id = ? AND tp.season_id = ?
    ORDER BY full_name ASC
");
$team1_players_stmt->execute([$match['team1_id'], $match['season_id']]);
$team1_players = $team1_players_stmt->fetchAll(PDO::FETCH_ASSOC);

$team2_players_stmt = $pdo->prepare("
    SELECT tp.player_id, 
           COALESCE(CONCAT(e.nom, ' ', e.prenom), tp.full_name) AS full_name
    FROM team_players tp
    LEFT JOIN etudiants e ON tp.user_id = e.id_etudiant
    WHERE tp.team_id = ? AND tp.season_id = ?
    ORDER BY full_name ASC
");
$team2_players_stmt->execute([$match['team2_id'], $match['season_id']]);
$team2_players = $team2_players_stmt->fetchAll(PDO::FETCH_ASSOC);

// Charger les buts existants
$goals_stmt = $pdo->prepare("SELECT * FROM match_goals WHERE match_id = ?");
$goals_stmt->execute([$match_id]);
$existing_goals = $goals_stmt->fetchAll(PDO::FETCH_ASSOC);

// Organiser les buts par joueur
$team1_goals = [];
$team2_goals = [];
foreach ($existing_goals as $g) {
    if ($g['team_id'] == $match['team1_id']) {
        $team1_goals[$g['player_id']] = ($team1_goals[$g['player_id']] ?? 0) + 1;
    } else {
        $team2_goals[$g['player_id']] = ($team2_goals[$g['player_id']] ?? 0) + 1;
    }
}

// Traitement POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $team1_id = $_POST['team1_id'];
    $team2_id = $_POST['team2_id'];
    $pool_id = $_POST['pool_id'] ?: null;
    $match_datetime = $_POST['match_datetime'] ?: null;
    $location = $_POST['location'] ?: null;
    $score_team1 = $_POST['score_team1'] !== "" ? intval($_POST['score_team1']) : null;
    $score_team2 = $_POST['score_team2'] !== "" ? intval($_POST['score_team2']) : null;
    $is_played = isset($_POST['is_played']) ? 1 : 0;

    // Déterminer le gagnant
    $winner_team_id = null;
    if ($score_team1 !== null && $score_team2 !== null) {
        if ($score_team1 > $score_team2) $winner_team_id = $team1_id;
        elseif ($score_team2 > $score_team1) $winner_team_id = $team2_id;
    }

    // Historique des scores
    $pdo->prepare("INSERT INTO match_results_history 
        (match_id, old_score_team1, old_score_team2, new_score_team1, new_score_team2, changed_by)
        VALUES (?, ?, ?, ?, ?, ?)")
        ->execute([$match_id, $match['score_team1'], $match['score_team2'], $score_team1, $score_team2, $id_admin]);

    // Mise à jour du match
    $pdo->prepare("UPDATE matches SET team1_id=?, team2_id=?, pool_id=?, match_datetime=?, location=?, score_team1=?, score_team2=?, winner_team_id=?, is_played=?, updated_at=NOW() WHERE match_id=?")
        ->execute([$team1_id, $team2_id, $pool_id, $match_datetime, $location, $score_team1, $score_team2, $winner_team_id, $is_played, $match_id]);

    // Supprimer anciens buts
    $pdo->prepare("DELETE FROM match_goals WHERE match_id=?")->execute([$match_id]);

    // Ajouter nouveaux buts
    foreach ($_POST['team1_goal'] ?? [] as $player_id => $nb) {
        $nb = intval($nb);
        for ($i=0; $i<$nb; $i++) {
            $pdo->prepare("INSERT INTO match_goals (match_id, season_id, team_id, player_id) VALUES (?, ?, ?, ?)")
                ->execute([$match_id, $match['season_id'], $team1_id, $player_id]);
        }
    }
    foreach ($_POST['team2_goal'] ?? [] as $player_id => $nb) {
        $nb = intval($nb);
        for ($i=0; $i<$nb; $i++) {
            $pdo->prepare("INSERT INTO match_goals (match_id, season_id, team_id, player_id) VALUES (?, ?, ?, ?)")
                ->execute([$match_id, $match['season_id'], $team2_id, $player_id]);
        }
    }

    header("Location: matches.php?success=1");
    exit;
}

ob_start();
?>

<h2>✏️ Modifier Match</h2>

<form method="POST">
    <div class="row g-3">
        <div class="col-md-4">
            <label>Équipe 1</label>
            <select name="team1_id" class="form-select" required>
                <?php foreach($teams as $t): ?>
                    <option value="<?= $t['team_id'] ?>" <?= $t['team_id']==$match['team1_id']?'selected':'' ?>>
                        <?= htmlspecialchars($t['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-4">
            <label>Équipe 2</label>
            <select name="team2_id" class="form-select" required>
                <?php foreach($teams as $t): ?>
                    <option value="<?= $t['team_id'] ?>" <?= $t['team_id']==$match['team2_id']?'selected':'' ?>>
                        <?= htmlspecialchars($t['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-4">
            <label>Pool</label>
            <select name="pool_id" class="form-select">
                <option value="">-- Aucun --</option>
                <?php foreach($pools as $p): ?>
                    <option value="<?= $p['pool_id'] ?>" <?= $p['pool_id']==$match['pool_id']?'selected':'' ?>>
                        <?= htmlspecialchars($p['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-4">
            <label>Date & Heure</label>
            <input type="datetime-local" name="match_datetime" class="form-control" 
                value="<?= $match['match_datetime'] ? date('Y-m-d\TH:i', strtotime($match['match_datetime'])) : '' ?>">
        </div>

        <div class="col-md-4">
            <label>Lieu</label>
            <input type="text" name="location" class="form-control" value="<?= htmlspecialchars($match['location'] ?? '') ?>">
        </div>

        <div class="col-md-2">
            <label>Match joué</label>
            <input type="checkbox" name="is_played" <?= $match['is_played']?'checked':'' ?>>
        </div>

        <div class="col-md-6">
            <label>Score Équipe 1</label>
            <input type="number" name="score_team1" class="form-control" value="<?= $match['score_team1'] ?>">
        </div>

        <div class="col-md-6">
            <label>Score Équipe 2</label>
            <input type="number" name="score_team2" class="form-control" value="<?= $match['score_team2'] ?>">
        </div>
    </div>

    <hr>
    <h5>Buteurs Équipe 1</h5>
<?php foreach($team1_players as $player): 
    $player_id = $player['player_id'];
    // Récupérer le nombre de buts pour ce joueur, si existant
    $goals = 0;
    foreach($team1_goals as $g){
        if($g['player_id'] == $player_id) $goals++;
    }
?>
    <div class="mb-2">
        <label><?= htmlspecialchars($player['full_name'] ?? 'Joueur') ?></label>
        <input type="number" min="0" name="team1_goal[<?= $player_id ?>]" class="form-control" value="<?= $goals ?>">
    </div>
<?php endforeach; ?>

<hr>
<h5>Buteurs Équipe 2</h5>
<?php foreach($team2_players as $player): 
    $player_id = $player['player_id'];
    // Récupérer le nombre de buts pour ce joueur, si existant
    $goals = 0;
    foreach($team2_goals as $g){
        if($g['player_id'] == $player_id) $goals++;
    }
?>
    <div class="mb-2">
        <label><?= htmlspecialchars($player['full_name'] ?? 'Joueur') ?></label>
        <input type="number" min="0" name="team2_goal[<?= $player_id ?>]" class="form-control" value="<?= $goals ?>">
    </div>
<?php endforeach; ?>

    <button type="submit" class="btn btn-primary mt-3">💾 Enregistrer</button>
</form>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<?php
$content = ob_get_clean();
include '../layout.php';
?>
