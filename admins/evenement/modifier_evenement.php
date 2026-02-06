<?php
session_start();
require_once '../../includes/db.php';

/* =========================
   SÉCURITÉ ADMIN
========================= */
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

/* =========================
   VÉRIF ID ÉVÉNEMENT
========================= */
$event_id = $_GET['id'] ?? null;
if (!$event_id) {
    header("Location: evenements.php");
    exit;
}

/* =========================
   ANNÉES ACADÉMIQUES
========================= */
$years = $pdo->query("
    SELECT id, label FROM academic_years ORDER BY id DESC
")->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   RÉCUP ÉVÉNEMENT
========================= */
$stmt = $pdo->prepare("SELECT * FROM evenements WHERE id_evenement = ?");
$stmt->execute([$event_id]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    die("Événement introuvable.");
}

/* =========================
   ARTISTES EXISTANTS
========================= */
$artists = $pdo->prepare("
    SELECT * FROM evenement_artistes
    WHERE id_evenement = ?
");
$artists->execute([$event_id]);
$artists = $artists->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   SUPPRESSION ARTISTE
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_artist'])) {

    $artistId = (int) $_POST['artist_id'];

    $stmt = $pdo->prepare("
        SELECT photo FROM evenement_artistes
        WHERE id_artiste = ? AND id_evenement = ?
    ");
    $stmt->execute([$artistId, $event_id]);
    $artist = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($artist) {
        if ($artist['photo'] && file_exists("../uploads/artistes/".$artist['photo'])) {
            unlink("../uploads/artistes/".$artist['photo']);
        }

        $pdo->prepare("
            DELETE FROM evenement_artistes WHERE id_artiste = ?
        ")->execute([$artistId]);
    }

    header("Location: modifier_evenement.php?id=".$event_id);
    exit;
}

/* =========================
   MISE À JOUR ÉVÉNEMENT
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_event'])) {

    $stmt = $pdo->prepare("
        UPDATE evenements SET
            nom_evenement = ?,
            type_evenement = ?,
            description = ?,
            event_start = ?,
            lieu = ?,
            prix_ticket = ?,
            academic_year_id = ?
        WHERE id_evenement = ?
    ");

    $stmt->execute([
        trim($_POST['nom_evenement']),
        $_POST['type_evenement'],
        trim($_POST['description']),
        $_POST['event_start'],
        trim($_POST['lieu']),
        floatval($_POST['prix_ticket']),
        $_POST['academic_year_id'],
        $event_id
    ]);

    /* ===== AJOUT NOUVEAUX ARTISTES ===== */
    if (!empty($_POST['artist_name'])) {

        $uploadDir = "../uploads/artistes/";
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        foreach ($_POST['artist_name'] as $i => $nom) {

            if (empty($nom)) continue;

            $photoName = null;
            if (!empty($_FILES['artist_photo']['name'][$i])) {
                $photoName = time().'_'.$_FILES['artist_photo']['name'][$i];
                move_uploaded_file(
                    $_FILES['artist_photo']['tmp_name'][$i],
                    $uploadDir.$photoName
                );
            }

            $pdo->prepare("
                INSERT INTO evenement_artistes
                (id_evenement, nom_artiste, pseudonyme, description, photo, role)
                VALUES (?, ?, ?, ?, ?, ?)
            ")->execute([
                $event_id,
                $nom,
                $_POST['artist_pseudo'][$i] ?? null,
                $_POST['artist_desc'][$i] ?? null,
                $photoName,
                $_POST['artist_role'][$i] ?? null
            ]);
        }
    }

    header("Location: evenements.php?updated=1");
    exit;
}

ob_start();
?>
<h2>✏️ Modifier l’Événement</h2>

<form method="POST" enctype="multipart/form-data" class="card p-4">

    <input type="hidden" name="update_event">

    <label>Nom</label>
    <input type="text" name="nom_evenement" class="form-control mb-2"
           value="<?= htmlspecialchars($event['nom_evenement']) ?>" required>

    <label>Type</label>
    <select name="type_evenement" class="form-control mb-2">
        <?php foreach(['sortie','soiree','concert','competition','autre'] as $t): ?>
            <option value="<?= $t ?>" <?= $event['type_evenement']===$t?'selected':'' ?>>
                <?= ucfirst($t) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <label>Description</label>
    <textarea name="description" class="form-control mb-2"><?= htmlspecialchars($event['description']) ?></textarea>

    <label>Date & Heure</label>
    <input type="datetime-local" name="event_start" class="form-control mb-2"
           value="<?= date('Y-m-d\TH:i', strtotime($event['event_start'])) ?>">

    <label>Lieu</label>
    <input type="text" name="lieu" class="form-control mb-2"
           value="<?= htmlspecialchars($event['lieu']) ?>">

    <label>Prix ticket</label>
    <input type="number" name="prix_ticket" class="form-control mb-2"
           value="<?= $event['prix_ticket'] ?>" step="0.01">

    <label>Année académique</label>
    <select name="academic_year_id" class="form-control mb-3">
        <?php foreach($years as $y): ?>
            <option value="<?= $y['id'] ?>" <?= $y['id']==$event['academic_year_id']?'selected':'' ?>>
                <?= htmlspecialchars($y['label']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <hr>
<h4>🎤 Artistes invités</h4>

<?php foreach ($artists as $a): ?>
    <div class="artist-box">
        <strong><?= htmlspecialchars($a['nom_artiste']) ?></strong>
        <small><?= htmlspecialchars($a['role']) ?></small>

        <?php if ($a['photo']): ?>
            <img src="../uploads/artistes/<?= $a['photo'] ?>" width="80">
        <?php endif; ?>

        <form method="POST" class="text-end">
            <input type="hidden" name="artist_id" value="<?= $a['id_artiste'] ?>">
            <button name="delete_artist" class="btn btn-danger btn-sm">
                Supprimer
            </button>
        </form>
    </div>
<?php endforeach; ?>

<?php
$content = ob_get_clean();
include "../layout.php";