<?php
session_start();
require_once "../../includes/db.php";

$errors = [];
$success = false;

// Liste des années académiques pour le select
$years = $pdo->query("SELECT id, label FROM academic_years ORDER BY label DESC")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titre = trim($_POST['titre'] ?? '');
    $contenu = trim($_POST['contenu'] ?? '');
    $mot_president = trim($_POST['mot_du_president'] ?? '');
    $academic_year_id = $_POST['academic_year_id'] ?? null;
    $id_admin = $_SESSION['admin_id'] ?? null;

    // Validation
    if (!$titre) $errors[] = "Le titre est obligatoire.";
    if (!$contenu) $errors[] = "Le contenu est obligatoire.";
    if (!$academic_year_id) $errors[] = "L'année académique est obligatoire.";

    if (empty($errors)) {
        $stmt = $pdo->prepare("
            INSERT INTO actualites (id_admin, id_academic_year, titre, contenu, mot_du_president)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$id_admin, $academic_year_id, $titre, $contenu, $mot_president]);

        // Redirection vers la liste des actualités
        $_SESSION['success'] = "Actualité ajoutée avec succès !";
        header("Location: actualites.php");
        exit;
    }
}

ob_start();
?>

<div class="page-header">
    <h2>Ajouter une Actualité</h2>
    <a href="actualites.php" class="btn btn-secondary">Retour à la liste</a>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger">
        <ul>
            <?php foreach($errors as $e): ?>
                <li><?= htmlspecialchars($e) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form action="" method="POST" class="form-container">
    <div class="form-group">
        <label for="titre">Titre <span class="text-danger">*</span></label>
        <input type="text" id="titre" name="titre" class="form-control" 
               value="<?= htmlspecialchars($_POST['titre'] ?? '') ?>" required>
    </div>

    <div class="form-group">
        <label for="contenu">Contenu <span class="text-danger">*</span></label>
        <textarea id="contenu" name="contenu" class="form-control" rows="6" required><?= htmlspecialchars($_POST['contenu'] ?? '') ?></textarea>
    </div>

    <div class="form-group">
        <label for="mot_du_president">Mot du Président</label>
        <textarea id="mot_du_president" name="mot_du_president" class="form-control" rows="3"><?= htmlspecialchars($_POST['mot_du_president'] ?? '') ?></textarea>
    </div>

    <div class="form-group">
        <label for="academic_year_id">Année académique <span class="text-danger">*</span></label>
        <select name="academic_year_id" id="academic_year_id" class="form-control" required>
            <option value="">-- Sélectionnez --</option>
            <?php foreach($years as $y): ?>
                <option value="<?= $y['id'] ?>" <?= (isset($_POST['academic_year_id']) && $_POST['academic_year_id'] == $y['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($y['label']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <button type="submit" class="btn btn-primary">Ajouter l'actualité</button>
</form>

<style>
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.form-container { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.05); max-width: 700px; }
.form-group { margin-bottom: 15px; }
.form-control { width: 100%; padding: 8px 12px; border-radius: 6px; border: 1px solid #ccc; }
.btn { padding: 8px 16px; border-radius: 6px; border: none; cursor: pointer; }
.btn-primary { background: rgb(8,0,32); color: #fff; }
.btn-secondary { background: #6c757d; color: #fff; }
.text-danger { color: red; }
.alert { padding: 10px 15px; border-radius: 6px; margin-bottom: 20px; }
.alert-danger { background: #f8d7da; color: #721c24; }
</style>

<?php
$content = ob_get_clean();
include "../layout.php";
?>
