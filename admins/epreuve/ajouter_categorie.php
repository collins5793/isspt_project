<?php
session_start();
require_once '../../includes/db.php'; // connexion PDO



$errors = [];
$nom_category = '';
$description = '';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom_category = trim($_POST['nom_category']);
    $description = trim($_POST['description']);

    if (empty($nom_category)) $errors[] = "Le nom de la catégorie est obligatoire.";

    if (empty($errors)) {
        $stmt = $pdo->prepare("INSERT INTO epreuves_categories (nom_category, description, created_by) VALUES (:nom, :desc, :admin)");
        $stmt->execute([
            ':nom' => $nom_category,
            ':desc' => $description,
            ':admin' => $_SESSION['admin_id']
        ]);

        header("Location: categories.php?added=1");
        exit;
    }
}

// Récupérer le nom à afficher selon le rôle
if ($_SESSION['admin_role'] === 'bureau' && !empty($_SESSION['admin_id_etudiant'])) {
    $stmt = $pdo->prepare("SELECT nom, prenom FROM etudiants WHERE id_etudiant = ?");
    $stmt->execute([$_SESSION['admin_id_etudiant']]);
    $etudiant = $stmt->fetch(PDO::FETCH_ASSOC);
    $prenom = $etudiant['prenom'] ?? 'Bureau';
    $nom = $etudiant['nom'] ?? 'Membre';
} else {
    $prenom = $_SESSION['admin_prenom'] ?? '';
    $nom = $_SESSION['admin_nom'] ?? '';
}

// --- Contenu à injecter dans le layout ---
ob_start();
?>

<h2 class="page-title">➕ Ajouter une catégorie d’épreuve</h2>

<?php if(!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul><?php foreach($errors as $err) echo "<li>".htmlspecialchars($err)."</li>"; ?></ul>
    </div>
<?php endif; ?>

<form method="POST" class="bg-white p-4 rounded shadow-sm">
    <div class="mb-3">
        <label class="form-label">Nom de la catégorie</label>
        <input type="text" name="nom_category" class="form-control" value="<?= htmlspecialchars($nom_category) ?>" required>
    </div>

    <div class="mb-3">
        <label class="form-label">Description (facultatif)</label>
        <textarea name="description" class="form-control"><?= htmlspecialchars($description) ?></textarea>
    </div>

    <button type="submit" class="btn btn-success">💾 Ajouter</button>
    <a href="liste_categories.php" class="btn btn-secondary">Annuler</a>
</form>

<?php
// Récupération du contenu et injection dans le layout
$content = ob_get_clean();
include '../layout.php';
?>
