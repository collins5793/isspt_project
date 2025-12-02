<?php
session_start();
require_once '../../includes/db.php'; // connexion PDO

// Vérifier admin
// if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
//     header("Location: index.php");
//     exit;
// }

$errors = [];
$nom_filiere = '';
$description = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom_filiere = trim($_POST['nom_filiere']);
    $description = trim($_POST['description']);

    if (empty($nom_filiere)) $errors[] = "Le nom de la filière est obligatoire.";

    if (empty($errors)) {
        $stmt = $pdo->prepare("INSERT INTO filieres (nom_filiere, description) VALUES (:nom, :desc)");
        $stmt->execute([
            ':nom' => $nom_filiere,
            ':desc' => $description
        ]);

        header("Location: filieres.php?added=1");
        exit;
    }
}
// --- Contenu à injecter dans le layout ---
ob_start();
?>

    <h2 class="mb-4">➕ Ajouter une filière</h2>

    <?php if(!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul><?php foreach($errors as $err) echo "<li>".htmlspecialchars($err)."</li>"; ?></ul>
        </div>
    <?php endif; ?>

    <form method="POST" class="bg-white p-4 rounded shadow-sm">
        <div class="mb-3">
            <label class="form-label">Nom de la filière</label>
            <input type="text" name="nom_filiere" class="form-control" value="<?= htmlspecialchars($nom_filiere) ?>" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Description (facultatif)</label>
            <textarea name="description" class="form-control"><?= htmlspecialchars($description) ?></textarea>
        </div>

        <button type="submit" class="btn btn-success">💾 Ajouter</button>
        <a href="liste_filieres.php" class="btn btn-secondary">Annuler</a>
    </form>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<?php
// Récupération du contenu et injection dans le layout
$content = ob_get_clean();
include '../layout.php';
?>