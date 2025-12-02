<?php
session_start();
require_once '../../includes/db.php';

// Vérifier admin
// if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
//     header("Location: index.php");
//     exit;
// }

// Vérifier id
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: liste_matieres.php");
    exit;
}

$id_matiere = (int)$_GET['id'];
$errors = [];

// Récupérer matière
$stmt = $pdo->prepare("SELECT * FROM matiere_epreuves WHERE id_matiere = :id");
$stmt->execute([':id'=>$id_matiere]);
$matiere = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$matiere) die("Matière introuvable.");

// Variables
$nom_matiere = $matiere['nom_matiere'];
$id_filiere = $matiere['id_filiere'];
$description = $matiere['description'];

// Filieres
$filieres = $pdo->query("SELECT id_filiere, nom_filiere FROM filieres ORDER BY nom_filiere")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom_matiere = trim($_POST['nom_matiere']);
    $id_filiere = $_POST['id_filiere'];
    $description = trim($_POST['description']);

    // Validation
    if (empty($nom_matiere)) $errors[] = "Le nom de la matière est obligatoire.";
    if (empty($id_filiere)) $errors[] = "La filière est obligatoire.";

    if (empty($errors)) {
        $stmt = $pdo->prepare("UPDATE matiere_epreuves SET nom_matiere = :nom, id_filiere = :filiere, description = :desc WHERE id_matiere = :id");
        $stmt->execute([
            ':nom' => $nom_matiere,
            ':filiere' => $id_filiere,
            ':desc' => $description,
            ':id' => $id_matiere
        ]);

        header("Location: liste_matieres.php?updated=1");
        exit;
    }
}

// --- Contenu à injecter dans le layout ---
ob_start();
?>
    <h2 class="mb-4">✏️ Modifier la matière : <?= htmlspecialchars($nom_matiere) ?></h2>

    <?php if(!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul><?php foreach($errors as $err) echo "<li>".htmlspecialchars($err)."</li>"; ?></ul>
        </div>
    <?php endif; ?>

    <form method="POST" class="bg-white p-4 rounded shadow-sm">
        <div class="mb-3">
            <label class="form-label">Nom de la matière</label>
            <input type="text" name="nom_matiere" class="form-control" value="<?= htmlspecialchars($nom_matiere) ?>" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Filière</label>
            <select name="id_filiere" class="form-select" required>
                <option value="">-- Choisir la filière --</option>
                <?php foreach($filieres as $fil): ?>
                    <option value="<?= $fil['id_filiere'] ?>" <?= $id_filiere==$fil['id_filiere']?"selected":"" ?>><?= htmlspecialchars($fil['nom_filiere']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">Description (facultatif)</label>
            <textarea name="description" class="form-control"><?= htmlspecialchars($description) ?></textarea>
        </div>

        <button type="submit" class="btn btn-primary">💾 Mettre à jour</button>
        <a href="liste_matieres.php" class="btn btn-secondary">Annuler</a>
    </form>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php
// Récupération du contenu et injection dans le layout
$content = ob_get_clean();
include '../layout.php';
?>