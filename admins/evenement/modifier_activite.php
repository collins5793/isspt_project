<?php
session_start();
require_once "../../includes/db.php";

/* ==========================
   Vérification de l'ID
========================== */
$activiteId = $_GET['id'] ?? null;
if (!$activiteId) {
    header("Location: activites.php");
    exit;
}

/* ==========================
   Récupération activité
========================== */
$stmt = $pdo->prepare("SELECT * FROM activites WHERE id_activite = ?");
$stmt->execute([$activiteId]);
$activite = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$activite) {
    die("Activité introuvable.");
}

/* ==========================
   Variables
========================== */
$errors = [];
$conditions_autorisees = [
    'etre_etudiant_isspt' => 'Être étudiant de l’ISSPT',
    'ouvert_a_tous'       => 'Ouvert à tous'
];

/* ==========================
   Traitement formulaire
========================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nom              = trim($_POST['nom_activite'] ?? '');
    $description      = trim($_POST['description'] ?? '');
    $conditions       = $_POST['conditions'] ?? '';
    $academic_year_id = $_POST['academic_year_id'] ?? null;

    // Validations
    if (!$nom) {
        $errors[] = "Le nom de l'activité est obligatoire.";
    }

    if (!array_key_exists($conditions, $conditions_autorisees)) {
        $errors[] = "Condition invalide.";
    }

    if (!$academic_year_id) {
        $errors[] = "L'année académique est obligatoire.";
    }

    // Mise à jour
    if (empty($errors)) {
        $stmt = $pdo->prepare("
            UPDATE activites
            SET 
                nom_activite = ?,
                description = ?,
                conditions = ?,
                academic_year_id = ?
            WHERE id_activite = ?
        ");

        $stmt->execute([
            $nom,
            $description,
            $conditions,
            $academic_year_id,
            $activiteId
        ]);

        header("Location: activites.php");
        exit;
    }
}

/* ==========================
   Années académiques
========================== */
$years = $pdo
    ->query("SELECT id, label FROM academic_years ORDER BY label DESC")
    ->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>

<div class="page-header">
    <h2>Modifier l'activité</h2>
    <a href="activites.php" class="btn btn-secondary">← Retour</a>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger">
        <ul>
            <?php foreach ($errors as $e): ?>
                <li><?= htmlspecialchars($e) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="POST" class="form-container">

    <div class="form-group">
        <label>Nom de l'activité <span class="text-danger">*</span></label>
        <input type="text" name="nom_activite" class="form-control"
               value="<?= htmlspecialchars($_POST['nom_activite'] ?? $activite['nom_activite']) ?>" required>
    </div>

    <div class="form-group">
        <label>Description</label>
        <textarea name="description" class="form-control" rows="4"><?= 
            htmlspecialchars($_POST['description'] ?? $activite['description']) 
        ?></textarea>
    </div>

    <div class="form-group">
        <label>Conditions <span class="text-danger">*</span></label>
        <select name="conditions" class="form-control" required>
            <option value="">-- Sélectionnez --</option>
            <?php foreach ($conditions_autorisees as $key => $label): ?>
                <option value="<?= $key ?>"
                    <?= (($activite['conditions'] === $key && !isset($_POST['conditions'])) 
                        || (isset($_POST['conditions']) && $_POST['conditions'] === $key)) 
                        ? 'selected' : '' ?>>
                    <?= $label ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-group">
        <label>Année académique <span class="text-danger">*</span></label>
        <select name="academic_year_id" class="form-control" required>
            <option value="">-- Sélectionnez --</option>
            <?php foreach ($years as $y): ?>
                <option value="<?= $y['id'] ?>"
                    <?= (($activite['academic_year_id'] == $y['id'] && !isset($_POST['academic_year_id'])) 
                        || (isset($_POST['academic_year_id']) && $_POST['academic_year_id'] == $y['id'])) 
                        ? 'selected' : '' ?>>
                    <?= htmlspecialchars($y['label']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <button class="btn btn-primary">Enregistrer les modifications</button>
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
    max-width: 600px;
    box-shadow: 0 2px 8px rgba(0,0,0,.05);
}
.form-group { margin-bottom: 15px; }
.form-control {
    width: 100%;
    padding: 9px 12px;
    border-radius: 6px;
    border: 1px solid #ccc;
}
.btn {
    padding: 9px 18px;
    border-radius: 6px;
    cursor: pointer;
    border: none;
}
.btn-primary {
    background: rgb(8, 0, 32);
    color: #fff;
}
.btn-secondary {
    background: #6c757d;
    color: #fff;
}
.alert-danger {
    background: #f8d7da;
    color: #721c24;
    padding: 10px;
    border-radius: 6px;
}
.text-danger { color: red; }
</style>

<?php
$content = ob_get_clean();
include "../layout.php";
?>
