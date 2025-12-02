<?php
session_start();
require_once "../../includes/db.php";

$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: actualites.php");
    exit;
}

// Récupération de l'actualité
$stmt = $pdo->prepare("SELECT * FROM actualites WHERE id = ?");
$stmt->execute([$id]);
$actualite = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$actualite) {
    $_SESSION['error'] = "Actualité introuvable.";
    header("Location: actualites.php");
    exit;
}

$errors = [];

// Liste des années académiques pour le select
$years = $pdo->query("SELECT id, label FROM academic_years ORDER BY label DESC")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titre = trim($_POST['titre'] ?? '');
    $contenu = trim($_POST['contenu'] ?? '');
    $mot_president = trim($_POST['mot_du_president'] ?? '');
    $academic_year_id = $_POST['academic_year_id'] ?? null;

    // Validation
    if (!$titre) $errors[] = "Le titre est obligatoire.";
    if (!$contenu) $errors[] = "Le contenu est obligatoire.";
    if (!$academic_year_id) $errors[] = "L'année académique est obligatoire.";

    if (empty($errors)) {
        $stmt = $pdo->prepare("
            UPDATE actualites
            SET titre = ?, contenu = ?, mot_du_president = ?, id_academic_year = ?
            WHERE id = ?
        ");
        $stmt->execute([$titre, $contenu, $mot_president, $academic_year_id, $id]);

        $_SESSION['success'] = "Actualité modifiée avec succès !";
        header("Location: actualites.php");
        exit;
    }
}

ob_start();
?>

<div class="page-header">
    <h2>Modifier l'Actualité</h2>
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
               value="<?= htmlspecialchars($_POST['titre'] ?? $actualite['titre']) ?>" required>
    </div>

    <div class="form-group">
        <label for="contenu">Contenu <span class="text-danger">*</span></label>
        <textarea id="contenu" name="contenu" class="form-control" rows="6" required><?= htmlspecialchars($_POST['contenu'] ?? $actualite['contenu']) ?></textarea>
    </div>

    <div class="form-group">
        <label for="mot_du_president">Mot du Président</label>
        <textarea id="mot_du_president" name="mot_du_president" class="form-control" rows="3"><?= htmlspecialchars($_POST['mot_du_president'] ?? $actualite['mot_du_president']) ?></textarea>
    </div>

    <div class="form-group">
        <label for="academic_year_id">Année académique <span class="text-danger">*</span></label>
        <select name="academic_year_id" id="academic_year_id" class="form-control" required>
            <option value="">-- Sélectionnez --</option>
            <?php foreach($years as $y): ?>
                <option value="<?= $y['id'] ?>" <?= ((isset($_POST['academic_year_id']) && $_POST['academic_year_id']==$y['id']) || (!isset($_POST['academic_year_id']) && $actualite['id_academic_year']==$y['id'])) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($y['label']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <button type="submit" class="btn btn-primary">Modifier l'actualité</button>
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
