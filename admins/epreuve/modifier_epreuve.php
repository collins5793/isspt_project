<?php
session_start();
require_once '../../includes/db.php'; // connexion PDO

// Vérifier si admin
// if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
//     header("Location: index.php");
//     exit;
// }

// Vérifier si id_epreuve est fourni
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$id_epreuve = (int)$_GET['id'];
$errors = [];

// Récupérer l'épreuve
$stmt = $pdo->prepare("SELECT * FROM epreuves WHERE id_epreuve = :id");
$stmt->execute([':id' => $id_epreuve]);
$epreuve = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$epreuve) {
    die("Épreuve introuvable.");
}

// Initialiser les variables avec les valeurs actuelles
$titre = $epreuve['titre'];
$description = $epreuve['description'];
$id_category = $epreuve['id_category'];
$id_filiere = $epreuve['id_filiere'];
$id_matiere = $epreuve['id_matiere'];
$academic_year_id = $epreuve['academic_year_id'];
$niveau = $epreuve['niveau'];
$universite = $epreuve['universite'];
$pays = $epreuve['pays'];
$is_public = $epreuve['is_public'];

// Récupérer les catégories, filières, matières et années
$categories = $pdo->query("SELECT id_category, nom_category FROM epreuves_categories ORDER BY nom_category")->fetchAll(PDO::FETCH_ASSOC);
$filieres = $pdo->query("SELECT id_filiere, nom_filiere FROM filieres ORDER BY nom_filiere")->fetchAll(PDO::FETCH_ASSOC);
$matieres = $pdo->query("SELECT id_matiere, nom_matiere, id_filiere FROM matiere_epreuves ORDER BY nom_matiere")->fetchAll(PDO::FETCH_ASSOC);
$annees = $pdo->query("SELECT id, label FROM academic_years ORDER BY start_date DESC")->fetchAll(PDO::FETCH_ASSOC);

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titre = trim($_POST['titre']);
    $description = trim($_POST['description']);
    $id_category = $_POST['id_category'] ?? '';
    $id_filiere = $_POST['id_filiere'] ?? null;
    $id_matiere = $_POST['id_matiere'] ?? null;
    $academic_year_id = $_POST['academic_year_id'] ?? '';
    $niveau = $_POST['niveau'] ?? 'autre';
    $universite = trim($_POST['universite']);
    $pays = trim($_POST['pays']);
    $is_public = isset($_POST['is_public']) ? 1 : 0;

    // Validation simple
    if (empty($titre)) $errors[] = "Le titre est obligatoire.";
    if (empty($id_category)) $errors[] = "La catégorie est obligatoire.";
    if (empty($academic_year_id)) $errors[] = "L'année universitaire est obligatoire.";

    // Gestion du fichier PDF
    $file_name = $epreuve['file_path']; // conserver l'ancien fichier par défaut
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
        if ($ext !== 'pdf') {
            $errors[] = "Seul le format PDF est autorisé.";
        } else {
            $upload_dir = 'uploads/';
            $file_name = time() . '_' . basename($_FILES['file']['name']);
            $file_path = $upload_dir . $file_name;
            if (!move_uploaded_file($_FILES['file']['tmp_name'], $file_path)) {
                $errors[] = "Erreur lors de l'upload du fichier.";
            }
        }
    }

    // Si pas d'erreurs, mise à jour
    if (empty($errors)) {
        $stmt = $pdo->prepare("UPDATE epreuves SET
            titre = :titre,
            description = :description,
            file_path = :file_path,
            id_category = :id_category,
            id_filiere = :id_filiere,
            id_matiere = :id_matiere,
            academic_year_id = :academic_year_id,
            universite = :universite,
            pays = :pays,
            niveau = :niveau,
            is_public = :is_public
            WHERE id_epreuve = :id");

        $stmt->execute([
            ':titre' => $titre,
            ':description' => $description,
            ':file_path' => $file_name,
            ':id_category' => $id_category,
            ':id_filiere' => $id_filiere ?: null,
            ':id_matiere' => $id_matiere ?: null,
            ':academic_year_id' => $academic_year_id,
            ':universite' => $universite,
            ':pays' => $pays,
            ':niveau' => $niveau,
            ':is_public' => $is_public,
            ':id' => $id_epreuve
        ]);

        header("Location: index.php?updated=1");
        exit;
    }
}

// --- Contenu à injecter dans le layout ---
ob_start();
?>

    <h2 class="mb-4">✏️ Modifier l'épreuve : <?= htmlspecialchars($titre) ?></h2>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul><?php foreach($errors as $err) echo "<li>".htmlspecialchars($err)."</li>"; ?></ul>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="bg-white p-4 rounded shadow-sm">
        <div class="mb-3">
            <label class="form-label">Titre</label>
            <input type="text" name="titre" value="<?= htmlspecialchars($titre) ?>" class="form-control" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-control"><?= htmlspecialchars($description) ?></textarea>
        </div>

        <div class="mb-3">
            <label class="form-label">Catégorie</label>
            <select name="id_category" class="form-select" required>
                <option value="">-- Choisir une catégorie --</option>
                <?php foreach($categories as $cat): ?>
                    <option value="<?= $cat['id_category'] ?>" <?= $id_category==$cat['id_category']?"selected":"" ?>><?= htmlspecialchars($cat['nom_category']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">Filière</label>
            <select name="id_filiere" class="form-select">
                <option value="">-- Facultatif --</option>
                <?php foreach($filieres as $fil): ?>
                    <option value="<?= $fil['id_filiere'] ?>" <?= $id_filiere==$fil['id_filiere']?"selected":"" ?>><?= htmlspecialchars($fil['nom_filiere']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">Matière</label>
            <select name="id_matiere" class="form-select">
                <option value="">-- Facultatif --</option>
                <?php foreach($matieres as $mat): ?>
                    <option value="<?= $mat['id_matiere'] ?>" <?= $id_matiere==$mat['id_matiere']?"selected":"" ?>><?= htmlspecialchars($mat['nom_matiere']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">Année universitaire</label>
            <select name="academic_year_id" class="form-select" required>
                <option value="">-- Choisir l'année --</option>
                <?php foreach($annees as $annee): ?>
                    <option value="<?= $annee['id'] ?>" <?= $academic_year_id==$annee['id']?"selected":"" ?>><?= htmlspecialchars($annee['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">Niveau</label>
            <select name="niveau" class="form-select">
                <option value="1ère année" <?= $niveau=="1ère année"?"selected":"" ?>>1ère année</option>
                <option value="2ème année" <?= $niveau=="2ème année"?"selected":"" ?>>2ème année</option>
                <option value="3ème année" <?= $niveau=="3ème année"?"selected":"" ?>>3ème année</option>
                <option value="autre" <?= $niveau=="autre"?"selected":"" ?>>Autre</option>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">Université</label>
            <input type="text" name="universite" value="<?= htmlspecialchars($universite) ?>" class="form-control">
        </div>

        <div class="mb-3">
            <label class="form-label">Pays</label>
            <input type="text" name="pays" value="<?= htmlspecialchars($pays) ?>" class="form-control">
        </div>

        <div class="mb-3">
            <label class="form-label">Fichier PDF (laisser vide pour garder l'ancien)</label>
            <input type="file" name="file" accept="application/pdf" class="form-control">
            <?php if(!empty($epreuve['file_path'])): ?>
                <small>Fichier actuel : <?= htmlspecialchars($epreuve['file_path']) ?></small>
            <?php endif; ?>
        </div>

        <div class="form-check mb-3">
            <input type="checkbox" name="is_public" value="1" class="form-check-input" <?= $is_public?"checked":"" ?>>
            <label class="form-check-label">Rendre public</label>
        </div>

        <button type="submit" class="btn btn-primary">💾 Mettre à jour</button>
        <a href="index.php" class="btn btn-secondary">Annuler</a>
    </form>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php
// Récupération du contenu et injection dans le layout
$content = ob_get_clean();
include '../layout.php';
?>
