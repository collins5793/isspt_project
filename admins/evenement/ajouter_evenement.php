<?php
session_start();
require_once '../../includes/db.php';

// Vérifier admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// Charger années académiques
$years = $pdo->query("SELECT id, label FROM academic_years ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nom_evenement = trim($_POST['nom_evenement']);
    $type_evenement = $_POST['type_evenement'];
    $description = trim($_POST['description']);
    $event_start = $_POST['event_start'];
    $lieu = trim($_POST['lieu']);
    $prix_ticket = !empty($_POST['prix_ticket']) ? floatval($_POST['prix_ticket']) : 0.00;
    $academic_year_id = $_POST['academic_year_id'];
    $cree_par = $_SESSION['admin_id'];

    // Enregistrement événement
    $stmt = $pdo->prepare("
        INSERT INTO evenements (nom_evenement, type_evenement, description, event_start, lieu, prix_ticket, academic_year_id, cree_par)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $nom_evenement, $type_evenement, $description, $event_start,
        $lieu, $prix_ticket, $academic_year_id, $cree_par
    ]);

    $event_id = $pdo->lastInsertId();


    /* -----------------------------------
        ARTISTES INVITÉS
    ----------------------------------- */
    if (isset($_POST['artist_name']) && is_array($_POST['artist_name'])) {

        $uploadDir = "../uploads/artistes/";

        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        foreach ($_POST['artist_name'] as $i => $nom_artiste) {

            if (empty($nom_artiste)) continue;

            $pseudonyme = $_POST['artist_pseudo'][$i] ?? null;
            $description_artiste = $_POST['artist_desc'][$i] ?? null;
            $role = $_POST['artist_role'][$i] ?? null;

            // Upload photo
            $photoName = null;
            if (!empty($_FILES['artist_photo']['name'][$i])) {
                $fileTmp = $_FILES['artist_photo']['tmp_name'][$i];
                $fileName = time() . "_" . basename($_FILES['artist_photo']['name'][$i]);
                $targetPath = $uploadDir . $fileName;

                if (move_uploaded_file($fileTmp, $targetPath)) {
                    $photoName = $fileName;
                }
            }

            $insertArtist = $pdo->prepare("
                INSERT INTO evenement_artistes (id_evenement, nom_artiste, pseudonyme, description, photo, role)
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            $insertArtist->execute([
                $event_id, $nom_artiste, $pseudonyme, $description_artiste, $photoName, $role
            ]);
        }
    }

    // 🔥 REDIRECTION APRÈS INSERTION
    header("Location: evenements.php?success=1");
    exit;
}


// Contenu à injecter dans le layout
ob_start();
?>



    <style>
        .artist-box {
            border: 1px solid #ddd;
            padding: 15px;
            border-radius: 10px;
            background: #f9f9f9;
            margin-bottom: 15px;
        }
        .remove-btn {
            cursor: pointer;
            color: red;
            font-weight: bold;
        }
    </style>


    <h2 class="mb-4">➕ Ajouter un Événement</h2>

    

    <form method="POST" enctype="multipart/form-data" class="card p-4">

        <div class="mb-3">
            <label class="form-label">Nom de l'événement</label>
            <input type="text" name="nom_evenement" class="form-control" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Type</label>
            <select name="type_evenement" class="form-control" required>
                <option value="sortie">Sortie</option>
                <option value="soiree">Soirée</option>
                <option value="concert">Concert</option>
                <option value="competition">Compétition</option>
                <option value="autre">Autre</option>
            </select>
        </div>

        <div class="mb-3">
            <label>Description</label>
            <textarea name="description" class="form-control" rows="3"></textarea>
        </div>

        <div class="mb-3">
            <label>Date & Heure</label>
            <input type="datetime-local" name="event_start" class="form-control" required>
        </div>

        <div class="mb-3">
            <label>Lieu</label>
            <input type="text" name="lieu" class="form-control">
        </div>

        <div class="mb-3">
            <label>Prix du ticket (FCFA)</label>
            <input type="number" name="prix_ticket" class="form-control" step="0.01" min="0">
        </div>

        <div class="mb-3">
            <label>Année Académique</label>
            <select name="academic_year_id" class="form-control" required>
                <?php foreach($years as $year): ?>
                    <option value="<?= $year['id'] ?>"><?= htmlspecialchars($year['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <hr>

        <h4>🎤 Artistes Invités</h4>

        <div id="artists_container"></div>

        <button type="button" class="btn btn-secondary mb-3" onclick="addArtist()">+ Ajouter un artiste</button>

        <hr>

        <button type="submit" class="btn btn-primary">Enregistrer l'événement</button>

    </form>



<!-- Script dynamique artistes -->
<script>
function addArtist() {
    const container = document.getElementById('artists_container');

    const html = `
        <div class="artist-box">
            <div class="text-end"><span class="remove-btn" onclick="this.parentElement.parentElement.remove()">X</span></div>

            <label>Nom de l'artiste</label>
            <input type="text" name="artist_name[]" class="form-control mb-2" required>

            <label>Pseudonyme</label>
            <input type="text" name="artist_pseudo[]" class="form-control mb-2">

            <label>Rôle (Chanteur, DJ, Animateur…)</label>
            <input type="text" name="artist_role[]" class="form-control mb-2">

            <label>Description</label>
            <textarea name="artist_desc[]" class="form-control mb-2"></textarea>

            <label>Photo</label>
            <input type="file" name="artist_photo[]" accept="image/*" class="form-control">
        </div>
    `;

    container.insertAdjacentHTML('beforeend', html);
}
</script>

<?php
$content = ob_get_clean();
include "../layout.php";

