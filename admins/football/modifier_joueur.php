<?php
session_start();
require_once '../../includes/db.php';

$id_admin = $_SESSION['admin_id'] ?? null;
if (!$id_admin) die("Accès refusé");

// Récupérer l'ID du joueur à modifier
$player_id = $_GET['id'] ?? null;
if (!$player_id) die("Joueur introuvable");

// Charger le joueur
$stmt = $pdo->prepare("SELECT * FROM team_players WHERE player_id = ?");
$stmt->execute([$player_id]);
$player = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$player) die("Joueur introuvable");

// Charger équipes
$teams = $pdo->query("
    SELECT t.team_id, t.name AS team_name, s.id_season
    FROM football_teams t
    JOIN football_seasons s ON s.id_season = t.season_id
")->fetchAll(PDO::FETCH_ASSOC);

// Charger étudiants
$students = $pdo->query("SELECT id_etudiant, nom, prenom FROM etudiants ORDER BY nom ASC")->fetchAll(PDO::FETCH_ASSOC);

// Charger saisons
$seasons = $pdo->query("SELECT * FROM football_seasons ORDER BY is_active DESC, label ASC")->fetchAll(PDO::FETCH_ASSOC);

// Traitement POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $team_id = $_POST['team_id'];
    $season_id = $_POST['season_id'];
    $user_id = $_POST['user_id'] !== "" ? $_POST['user_id'] : null;
    $external_name = $_POST['external_name'] !== "" ? trim($_POST['external_name']) : null;
    $position = $_POST['position'] ?? null;
    $shirt_number = $_POST['shirt_number'] ?? null;
    $is_captain = isset($_POST['is_captain']) ? 1 : 0;

    // Pour joueurs internes on remplit full_name avec NULL
    $full_name = $user_id ? null : $external_name;

    $sql = "UPDATE team_players 
            SET user_id = ?, full_name = ?, position = ?, shirt_number = ?, 
                team_id = ?, season_id = ?, is_captain = ?
            WHERE player_id = ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id, $full_name, $position, $shirt_number, $team_id, $season_id, $is_captain, $player_id]);

    header("Location: players.php?success=1");
    exit;
}

ob_start();
?>

<h2>Modifier le joueur</h2>

<form method="POST">

    <label>Équipe</label>
    <select name="team_id" required>
        <?php foreach ($teams as $t): ?>
            <option value="<?= $t['team_id'] ?>" <?= $t['team_id'] == $player['team_id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($t['team_name']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <label>Saison</label>
    <select name="season_id" required>
        <?php foreach ($seasons as $s): ?>
            <option value="<?= $s['id_season'] ?>" <?= $s['id_season'] == $player['season_id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($s['label']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <label>Étudiant (optionnel)</label>
    <select name="user_id">
        <option value="">-- Joueur externe --</option>
        <?php foreach ($students as $s): ?>
            <option value="<?= $s['id_etudiant'] ?>" <?= $s['id_etudiant'] == $player['user_id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($s['nom'] . " " . $s['prenom']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <label>Nom externe (si pas étudiant)</label>
    <input type="text" name="external_name" value="<?= htmlspecialchars($player['full_name'] ?? '') ?>">

    <label>Position</label>
    <input type="text" name="position" value="<?= htmlspecialchars($player['position'] ?? '') ?>">

    <label>Dossard</label>
    <input type="number" name="shirt_number" value="<?= htmlspecialchars($player['shirt_number'] ?? '') ?>">

    <label>
        <input type="checkbox" name="is_captain" <?= !empty($player['is_captain']) ? 'checked' : '' ?>>
        Capitaine
    </label>

    <button type="submit" class="btn btn-primary">Enregistrer</button>
    <a href="players.php" class="btn btn-secondary">Annuler</a>
</form>

<?php
$content = ob_get_clean();
include '../layout.php';
?>
