<?php
session_start();
require_once "../../includes/db.php";

// Vérification de l'ID événement
if (!isset($_GET['id_event']) || empty($_GET['id_event'])) {
    header("Location: dashboard.php");
    exit;
}

$eventId = intval($_GET['id_event']);
$error = "";

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom_artiste = trim($_POST['nom_artiste']);
    $pseudonyme = trim($_POST['pseudonyme']);
    $description = trim($_POST['description']);
    $role = trim($_POST['role']);
    $photoName = null;

    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed)) {
            $error = "Format d’image non accepté (jpg, jpeg, png, webp).";
        } else {
            $photoName = uniqid("artiste_") . "." . $ext;
            $path = "../../uploads/artistes/" . $photoName;
            if (!is_dir("../../uploads/artistes/")) {
                mkdir("../../uploads/artistes/", 0777, true);
            }
            move_uploaded_file($_FILES['photo']['tmp_name'], $path);
        }
    }

    if (empty($error)) {
        $stmt = $pdo->prepare("
            INSERT INTO evenement_artistes 
            (id_evenement, nom_artiste, pseudonyme, description, photo, role)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        if ($stmt->execute([$eventId, $nom_artiste, $pseudonyme, $description, $photoName, $role])) {
            header("Location: liste_artistes.php?id_event=" . $eventId . "&success=1");
            exit;
        } else {
            $error = "Erreur lors de l’ajout de l’artiste.";
        }
    }
}

ob_start();
?>

<style>
    /* Intégration du thème Dark avec la racine fournie */
    :root {
        --input-bg: var(--primary-700);
        --input-border: rgba(255, 255, 255, 0.1);
    }

    .form-container {
        max-width: 900px;
        margin: var(--space-6) auto;
        padding: 0 var(--space-4);
    }

    .glass-card {
        background: var(--sidebar-bg);
        border: var(--sidebar-border);
        box-shadow: var(--sidebar-shadow);
        border-radius: var(--radius-xl);
        padding: var(--space-6);
        position: relative;
        overflow: hidden;
    }

    .glass-card::before {
        content: "";
        position: absolute;
        top: 0; left: 0; width: 100%; height: 4px;
        background: linear-gradient(90deg, var(--accent-blue), var(--accent-green));
    }

    .page-title {
        font-family: var(--font-primary);
        font-size: var(--font-size-xl);
        font-weight: 700;
        color: var(--white);
        margin-bottom: var(--space-1);
    }

    .page-subtitle {
        color: var(--gray-400);
        font-size: var(--font-size-sm);
        margin-bottom: var(--space-5);
    }

    /* Form Styles */
    .form-label {
        color: var(--gray-200);
        font-size: var(--font-size-sm);
        font-weight: 600;
        margin-bottom: var(--space-2);
        display: block;
    }

    .form-control, .form-select {
        background-color: var(--input-bg);
        border: 1px solid var(--input-border);
        color: var(--white);
        padding: 0.75rem var(--space-4);
        border-radius: var(--radius-md);
        transition: var(--transition-base);
    }

    .form-control:focus, .form-select:focus {
        background-color: var(--primary-600);
        border-color: var(--accent-blue);
        box-shadow: 0 0 0 4px rgba(46, 134, 222, 0.15);
        color: var(--white);
        outline: none;
    }

    .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: var(--space-4);
    }

    /* Image Upload Zone */
    .upload-zone {
        border: 2px dashed var(--input-border);
        border-radius: var(--radius-lg);
        padding: var(--space-4);
        text-align: center;
        transition: var(--transition-fast);
        cursor: pointer;
        position: relative;
    }

    .upload-zone:hover {
        border-color: var(--accent-blue);
        background: rgba(46, 134, 222, 0.05);
    }

    /* Custom Button */
    .btn-submit {
        background: var(--accent-blue);
        color: var(--white);
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1px;
        padding: var(--space-3) var(--space-5);
        border: none;
        border-radius: var(--radius-md);
        transition: var(--transition-base);
        box-shadow: 0 4px 15px rgba(46, 134, 222, 0.3);
    }

    .btn-submit:hover {
        background: #1e70c1;
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(46, 134, 222, 0.4);
    }

    .btn-back {
        background: transparent;
        color: var(--gray-400);
        border: 1px solid var(--gray-400);
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: var(--transition-fast);
    }

    .btn-back:hover {
        color: var(--white);
        border-color: var(--white);
        background: rgba(255, 255, 255, 0.05);
    }

    .alert {
        border-radius: var(--radius-md);
        border: none;
        font-size: var(--font-size-sm);
        padding: var(--space-4);
    }

    /* Responsive */
    @media (max-width: 768px) {
        .form-grid {
            grid-template-columns: 1fr;
        }
        .glass-card {
            padding: var(--space-4);
        }
    }
</style>

<div class="form-container">
    <div class="glass-card">
        <div class="mb-4">
            <h1 class="page-title">Ajouter un Artiste</h1>
            <p class="page-subtitle">Complétez les informations pour inclure un nouveau talent à votre événement.</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger d-flex align-items-center mb-4">
                <i class="fas fa-exclamation-circle me-2"></i> <?= $error ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <div class="form-grid mb-4">
                <div class="form-group">
                    <label class="form-label" for="nom_artiste">Nom complet de l'artiste *</label>
                    <input type="text" name="nom_artiste" id="nom_artiste" class="form-control w-100" required placeholder="Ex : Koffi Agbéssi">
                </div>

                <div class="form-group">
                    <label class="form-label" for="pseudonyme">Pseudonyme</label>
                    <input type="text" name="pseudonyme" id="pseudonyme" class="form-control w-100" placeholder="Ex : King Agbéssi">
                </div>
            </div>

            <div class="form-grid mb-4">
                <div class="form-group">
                    <label class="form-label" for="role">Rôle dans l'événement *</label>
                    <select name="role" id="role" class="form-select w-100" required>
                        <option value="" disabled selected>Choisir un rôle</option>
                        <option value="Invité spécial">Invité spécial</option>
                        <option value="Artiste chanteur">Artiste chanteur</option>
                        <option value="Danseur">Danseur</option>
                        <option value="DJ">DJ</option>
                        <option value="Intervenant">Intervenant</option>
                        <option value="Comédien">Comédien</option>
                        <option value="Autre">Autre</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Photo de profil *</label>
                    <div class="upload-zone">
                        <input type="file" name="photo" class="position-absolute opacity-0 w-100 h-100 top-0 start-0" accept="image/*" required id="photoInput">
                        <div id="upload-preview">
                            <i class="fas fa-cloud-upload-alt mb-2 text-primary" style="font-size: 1.5rem;"></i>
                            <p class="mb-0 small text-gray-400" id="fileName">Cliquez pour choisir un fichier</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mb-5">
                <label class="form-label" for="description">Biographie ou Description</label>
                <textarea name="description" id="description" class="form-control w-100" rows="4" placeholder="Présentez l'artiste et son parcours en quelques lignes..."></textarea>
            </div>

            <div class="d-flex flex-column gap-3">
                <button type="submit" class="btn-submit w-100">
                    <i class="fas fa-plus-circle me-2"></i> Confirmer l'ajout
                </button>
                <a href="liste_artistes.php?id_event=<?= $eventId ?>" class="btn btn-back w-100">
                    <i class="fas fa-arrow-left me-2"></i> Annuler et retourner à la liste
                </a>
            </div>
        </form>
    </div>
</div>

<script>
    // Petit script pour afficher le nom du fichier sélectionné
    document.getElementById('photoInput').addEventListener('change', function(e) {
        let fileName = e.target.files[0].name;
        document.getElementById('fileName').innerHTML = '<span class="text-success">' + fileName + '</span>';
    });
</script>

<?php
$content = ob_get_clean();
include "../layout.php";
?>