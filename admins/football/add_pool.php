<?php
require_once '../../includes/db.php';
session_start();
$id_admin = $_SESSION['admin_id'] ?? null;
if (!$id_admin) die("Accès refusé");

// Charger saisons
$seasons = $pdo->query("SELECT id_season FROM football_seasons ORDER BY id_season DESC")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $season_id = $_POST['season_id'];
    $name = trim($_POST['name']);

    $stmt = $pdo->prepare("INSERT INTO pools (season_id, name) VALUES (?,?)");
    $stmt->execute([$season_id, $name]);

    header("Location: pools_list.php?success=1");
    exit;
}
?>

<h2>Ajouter une poule</h2>

<form method="POST">
    <label>Saison</label>
    <select name="season_id" required>
        <?php foreach ($seasons as $s): ?>
            <option value="<?= $s['id_season'] ?>">Saison #<?= $s['id_season'] ?></option>
        <?php endforeach; ?>
    </select>

    <label>Nom de la poule</label>
    <input type="text" name="name" required>

    <button type="submit">Ajouter</button>
</form>
