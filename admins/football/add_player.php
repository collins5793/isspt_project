<?php
require_once '../../includes/db.php';
session_start();

$id_admin = $_SESSION['admin_id'] ?? null;
if (!$id_admin) die("Accès refusé");

// Charger équipes
$stmt = $pdo->query("
    SELECT t.team_id, t.name AS team_name, s.id_season
    FROM football_teams t
    JOIN football_seasons s ON s.id_season = t.season_id
");
$teams = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Charger étudiants
$students = $pdo->query("SELECT id_etudiant, CONCAT(nom,' ',prenom) AS full_name FROM etudiants ORDER BY nom ASC")->fetchAll(PDO::FETCH_ASSOC);

// Charger saisons
$seasons = $pdo->query("SELECT * FROM football_seasons ORDER BY is_active DESC, label ASC")->fetchAll(PDO::FETCH_ASSOC);

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

    $sql = "INSERT INTO team_players 
        (user_id, full_name, position, shirt_number, team_id, season_id, is_captain)
        VALUES (?,?,?,?,?,?,?)";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id, $full_name, $position, $shirt_number, $team_id, $season_id, $is_captain]);

    header("Location: players.php?success=1");
    exit;
}

ob_start();

?>


<h2>Ajouter un joueur</h2>

<form method="POST">

    <label>Équipe</label>
    <select name="team_id" required>
        <?php foreach ($teams as $t): ?>
            <option value="<?= $t['team_id'] ?>">
                <?= htmlspecialchars($t['name']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <label>Saison</label>
    <select name="season_id" required>
        <?php foreach ($seasons as $s): ?>
            <option value="<?= $s['id_season'] ?>">Saison #<?= $s['id_season'] ?></option>
        <?php endforeach; ?>
    </select>

    <label>Étudiant (optionnel)</label>
    <select name="user_id">
        <option value="">-- Joueur externe --</option>
        <?php foreach ($students as $s): ?>
            <option value="<?= $s['id_etudiant'] ?>">
                <?= htmlspecialchars($s['nom'] . " " . $s['prenom']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <label>Nom externe (si pas étudiant)</label>
    <input type="text" name="external_name">

    <label>Position</label>
    <input type="text" name="position">

    <label>Dossard</label>
    <input type="number" name="shirt_number">

    <button type="submit">Ajouter</button>
</form>
<?php
// Récupération du contenu et injection dans le layout
$content = ob_get_clean();
include '../layout.php';
?>