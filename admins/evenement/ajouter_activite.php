<?php
session_start();
require_once "../../includes/db.php";

// Vérifier si le formulaire est soumis
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom_activite'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $conditions = trim($_POST['conditions'] ?? '');
    $academic_year_id = $_POST['academic_year_id'] ?? null;
    $cree_par = $_SESSION['admin_id'] ?? null; // L'id de l'admin connecté

    // Validation
    if (!$nom) $errors[] = "Le nom de l'activité est obligatoire.";
    if (!$academic_year_id) $errors[] = "L'année académique est obligatoire.";

    if (empty($errors)) {
        $stmt = $pdo->prepare("
            INSERT INTO activites (nom_activite, description, conditions, academic_year_id, cree_par)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$nom, $description, $conditions, $academic_year_id, $cree_par]);

        // Redirection vers la liste des activités après succès
        header("Location: activites.php");
        exit;
    }

}

// Récupérer la liste des années académiques pour le select
$years = $pdo->query("SELECT id, label FROM academic_years ORDER BY label DESC")->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>



<div class="page-header">
    <h2>Ajouter une activité</h2>
    <a href="activites.php" class="btn btn-secondary">Retour à la liste</a>
</div>

<?php if ($success): ?>
    <div class="alert alert-success">Activité ajoutée avec succès !</div>
<?php endif; ?>

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
        <label for="nom_activite">Nom de l'activité <span class="text-danger">*</span></label>
        <input type="text" id="nom_activite" name="nom_activite" class="form-control" 
               value="<?= htmlspecialchars($_POST['nom_activite'] ?? '') ?>" required>
    </div>

    <div class="form-group">
        <label for="description">Description</label>
        <textarea id="description" name="description" class="form-control" rows="5"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
    </div>

    <div class="form-group">
        <label for="conditions">Conditions</label>
        <textarea id="conditions" name="conditions" class="form-control" rows="3"><?= htmlspecialchars($_POST['conditions'] ?? '') ?></textarea>
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

    <button type="submit" class="btn btn-primary">Ajouter l'activité</button>
</form>

<style>
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}
.form-container {
    background: #fff;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.05);
    max-width: 600px;
}
.form-group {
    margin-bottom: 15px;
}
.form-control {
    width: 100%;
    padding: 8px 12px;
    border-radius: 6px;
    border: 1px solid #ccc;
}
.btn {
    padding: 8px 16px;
    border-radius: 6px;
    border: none;
    cursor: pointer;
}
.btn-primary { background: rgb(8, 0, 32); color: #fff; }
.btn-secondary { background: #6c757d; color: #fff; }
.text-danger { color: red; }
.alert { padding: 10px 15px; border-radius: 6px; margin-bottom: 20px; }
.alert-success { background: #d4edda; color: #155724; }
.alert-danger { background: #f8d7da; color: #721c24; }
</style>

<?php
$content = ob_get_clean();
include "../layout.php";
