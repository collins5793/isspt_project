<?php
require_once '../../includes/db.php';
session_start();

$id_admin = $_SESSION['admin_id'] ?? null;
if (!$id_admin) die("Accès refusé");

// Charger saisons
$stmt = $pdo->query("SELECT id_season FROM football_seasons ORDER BY id_season DESC");
$seasons = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $season_id = $_POST['season_id'];
    $name = trim($_POST['name']);
    $coach = trim($_POST['coach']);

    $sql = "INSERT INTO football_teams (season_id, name, coach, created_by) VALUES (?,?,?,?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$season_id, $name, $coach, $id_admin]);

    header("Location: teams_list.php?success=1");
    exit;
}
?>

<h2>Ajouter une équipe</h2>

<form method="POST">
    <label>Saison</label>
    <select name="season_id" required>
        <?php foreach ($seasons as $s): ?>
            <option value="<?= $s['id_season'] ?>">Saison #<?= $s['id_season'] ?></option>
        <?php endforeach; ?>
    </select>

    <label>Nom de l'équipe</label>
    <input type="text" name="name" required>

    <label>Entraîneur</label>
    <input type="text" name="coach">

    <button type="submit">Ajouter</button>
</form>
