<?php
session_start();
require_once "../../includes/db.php";

$errors = [];

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nom = trim($_POST['nom_activite'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $conditions = $_POST['conditions'] ?? null;
    $academic_year_id = $_POST['academic_year_id'] ?? null;
    $accessibilite = $_POST['accessibilite'] ?? 'isspt';
    $type_participation = $_POST['type_participation'] ?? 'individuel';
    $equipe_min = $_POST['equipe_min'] ?: null;
    $equipe_max = $_POST['equipe_max'] ?: null;
    $max_participants = $_POST['max_participants'] ?: null;
    $date_limite = $_POST['date_limite_inscription'] ?: null;
    $statut = $_POST['statut'] ?? 'ouverte';

    $cree_par = $_SESSION['admin_id'] ?? null;

    // Validations
    if (!$nom) $errors[] = "Le nom de l’activité est obligatoire.";
    if (!$academic_year_id) $errors[] = "L’année académique est obligatoire.";

    if ($type_participation === 'equipe') {
        if (!$equipe_min || !$equipe_max) {
            $errors[] = "Les tailles d’équipe sont obligatoires pour une activité en équipe.";
        }
        if ($equipe_min > $equipe_max) {
            $errors[] = "Le nombre minimum ne peut pas dépasser le maximum.";
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("
            INSERT INTO activites (
                nom_activite, description, conditions,
                academic_year_id, cree_par,
                accessibilite, type_participation,
                equipe_min, equipe_max,
                max_participants, date_limite_inscription, statut
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $nom, $description, $conditions,
            $academic_year_id, $cree_par,
            $accessibilite, $type_participation,
            $equipe_min, $equipe_max,
            $max_participants, $date_limite, $statut
        ]);

        header("Location: activites.php");
        exit;
    }
}

// Années académiques
$years = $pdo->query("
    SELECT id, label FROM academic_years ORDER BY is_current DESC, label DESC
")->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>

<div class="page-header">
    <h2>Nouvelle activité</h2>
    <a href="activites.php" class="btn btn-secondary">Retour</a>
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
        <label>Nom de l’activité *</label>
        <input type="text" name="nom_activite" class="form-control" required>
    </div>

    <div class="form-group">
        <label>Description</label>
        <textarea name="description" class="form-control"></textarea>
    </div>

    <div class="form-group">
        <label>Conditions</label>
        <select name="conditions" class="form-control">
            <option value="">-- Aucune --</option>
            <option value="etre etudiant de l'isspt">Être étudiant ISSPT</option>
            <option value="ouvert à tous">Ouvert à tous</option>
        </select>
    </div>

    <div class="form-group">
        <label>Année académique *</label>
        <select name="academic_year_id" class="form-control" required>
            <option value="">-- Sélectionner --</option>
            <?php foreach ($years as $y): ?>
                <option value="<?= $y['id'] ?>"><?= htmlspecialchars($y['label']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-group">
        <label>Accessibilité</label>
        <select name="accessibilite" class="form-control">
            <option value="isspt">ISSPT</option>
            <option value="public">Public</option>
        </select>
    </div>

    <div class="form-group">
        <label>Type de participation</label>
        <select name="type_participation" id="typeParticipation" class="form-control">
            <option value="individuel">Individuelle</option>
            <option value="equipe">Par équipe</option>
        </select>
    </div>

    <div id="equipeFields" style="display:none">
        <div class="form-group">
            <label>Équipe minimum</label>
            <input type="number" name="equipe_min" class="form-control">
        </div>
        <div class="form-group">
            <label>Équipe maximum</label>
            <input type="number" name="equipe_max" class="form-control">
        </div>
    </div>

    <div class="form-group">
        <label>Nombre max de participants</label>
        <input type="number" name="max_participants" class="form-control">
    </div>

    <div class="form-group">
        <label>Date limite d’inscription</label>
        <input type="datetime-local" name="date_limite_inscription" class="form-control">
    </div>

    <div class="form-group">
        <label>Statut</label>
        <select name="statut" class="form-control">
            <option value="brouillon">Brouillon</option>
            <option value="ouverte" selected>Ouverte</option>
            <option value="fermee">Fermée</option>
            <option value="terminee">Terminée</option>
        </select>
    </div>

    <button class="btn btn-primary">Créer l’activité</button>
</form>

<script>
document.getElementById('typeParticipation').addEventListener('change', function () {
    document.getElementById('equipeFields').style.display =
        this.value === 'equipe' ? 'block' : 'none';
});
</script>

<?php
$content = ob_get_clean();
include "../layout.php";
