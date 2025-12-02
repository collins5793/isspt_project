<?php
session_start();
require_once "../../includes/db.php";

// Vérifier si l’admin est connecté
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login_admin.php");
    exit();
}

// =========================
//   GESTION FILTRES
// =========================
$search = $_GET['search'] ?? '';
$filiere = $_GET['filiere'] ?? '';
$promotion = $_GET['promotion'] ?? '';
$statut = $_GET['statut'] ?? '';

// Construction dynamique de la requête
$query = "SELECT * FROM etudiants WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (nom LIKE :s OR prenom LIKE :s OR matricule LIKE :s)";
    $params['s'] = "%$search%";
}

if (!empty($filiere)) {
    $query .= " AND filiere = :filiere";
    $params['filiere'] = $filiere;
}

if (!empty($promotion)) {
    $query .= " AND promotion = :promotion";
    $params['promotion'] = $promotion;
}

if (!empty($statut)) {
    $query .= " AND statut = :statut";
    $params['statut'] = $statut;
}

// Tri par nom
$query .= " ORDER BY nom ASC";

// Exécution
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$etudiants = $stmt->fetchAll();

// Récupérer les filières distinctes
$filieres = $pdo->query("SELECT DISTINCT filiere FROM etudiants WHERE filiere IS NOT NULL")->fetchAll();

// Promotions distinctes
$promotions = $pdo->query("SELECT DISTINCT promotion FROM etudiants WHERE promotion IS NOT NULL")->fetchAll();


// --- DÉBUT DU CONTENU ---
ob_start();
?>
<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success">Étudiant ajouté avec succès !</div>
<?php endif; ?>

<div class="top-actions">
    <h1>Gestion des Étudiants</h1>

    <div>
        <a href="bureau.php" class="btn">Voir le Bureau Étudiant</a>
        <a href="ajouter_etudiant.php" class="btn" style="background:#28a745;">+ Ajouter un Étudiant</a>
    </div>
</div>

<!-- FILTRES -->
<form method="GET" class="filters">

    <input type="text" 
           name="search" 
           placeholder="Rechercher (nom, matricule…)" 
           value="<?= htmlspecialchars($search) ?>">

    <select name="filiere">
        <option value="">Filtre : Filière</option>
        <?php foreach($filieres as $f): ?>
            <option value="<?= $f['filiere'] ?>" <?= ($f['filiere'] == $filiere) ? 'selected' : '' ?>>
                <?= $f['filiere'] ?>
            </option>
        <?php endforeach; ?>
    </select>

    <select name="promotion">
        <option value="">Filtre : Promotion</option>
        <?php foreach($promotions as $p): ?>
            <option value="<?= $p['promotion'] ?>" <?= ($p['promotion'] == $promotion) ? 'selected' : '' ?>>
                <?= $p['promotion'] ?>
            </option>
        <?php endforeach; ?>
    </select>

    <select name="statut">
        <option value="">Statut</option>
        <option value="actif" <?= ($statut == "actif") ? "selected" : "" ?>>Actif</option>
        <option value="inactif" <?= ($statut == "inactif") ? "selected" : "" ?>>Inactif</option>
    </select>

    <button class="btn" style="background:#333;">Filtrer</button>
</form>

<!-- TABLEAU -->
<table>
    <tr>
        <th>Photo</th>
        <th>Matricule</th>
        <th>Nom</th>
        <th>Prénom</th>
        <th>Filière</th>
        <th>Promotion</th>
        <th>Statut</th>
        <th>Actions</th>
    </tr>

    <?php foreach ($etudiants as $e): ?>
    <tr onclick="window.location='detail_etudiant.php?id=<?= $e['id_etudiant'] ?>'">

        <td>
            <?php if (!empty($e['photo']) && file_exists('../uploads/photos_etudiants/' . $e['photo'])): ?>
                <img src="../uploads/photos_etudiants/<?= htmlspecialchars($e['photo']) ?>" width="45" style="border-radius:50%;">
            <?php else: ?>
                <img src="../assets/default/avatar.png" width="45" style="border-radius:50%;">
            <?php endif; ?>
        </td>


        <td><?= $e['matricule'] ?></td>
        <td><?= $e['nom'] ?></td>
        <td><?= $e['prenom'] ?></td>
        <td><?= $e['filiere'] ?></td>
        <td><?= $e['promotion'] ?></td>
        <td><?= $e['statut'] ?></td>

        <td class="actions" onclick="event.stopPropagation();">
            <a href="detail_etudiant.php?id=<?= $e['id_etudiant'] ?>" class="view">Voir</a>
            <a href="modifier_etudiant.php?id=<?= $e['id_etudiant'] ?>" class="edit">Modifier</a>
            <a href="supprimer_etudiant.php?id=<?= $e['id_etudiant'] ?>" 
               class="delete" 
               onclick="return confirm('Supprimer cet étudiant ?')">Supprimer</a>
        </td>
    </tr>
    <?php endforeach; ?>

    <?php if (empty($etudiants)): ?>
    <tr>
        <td colspan="8" style="text-align:center;padding:20px;">Aucun étudiant trouvé.</td>
    </tr>
    <?php endif; ?>
</table>

<?php
$content = ob_get_clean();
include "../layout.php";
