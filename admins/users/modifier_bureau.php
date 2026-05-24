<?php
session_start();
require_once '../../includes/db.php';

// Vérification de sécurité
if (!isset($_SESSION['admin_id'])) {
    header('Location: connexion_admin.php');
    exit();
}

if (!isset($_GET['id'])) {
    header('Location: bureau.php');
    exit();
}

$id_admin = intval($_GET['id']);
$message = '';

// Récupérer les informations du membre
$stmt = $pdo->prepare("
    SELECT a.*, e.nom, e.prenom 
    FROM administrateurs a
    LEFT JOIN etudiants e ON a.id_etudiant = e.id_etudiant
    WHERE a.id_admin = ? AND a.role = 'bureau'
");
$stmt->execute([$id_admin]);
$member = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$member) {
    $_SESSION['message'] = "<div class='alert alert-warning'>Membre du bureau introuvable.</div>";
    header('Location: bureau.php');
    exit();
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $poste_bureau = $_POST['poste_bureau'] ?? null;

    if (!empty($poste_bureau)) {
        $check = $pdo->prepare("SELECT * FROM administrateurs WHERE role = 'bureau' AND poste_bureau = ? AND id_admin != ?");
        $check->execute([$poste_bureau, $id_admin]);
        if ($check->rowCount() > 0) {
            $message = "<div class='alert-msg error'>⚠️ Ce rôle est déjà attribué à un autre membre.</div>";
        }
    }

    if (empty($message)) {
        $update = $pdo->prepare("UPDATE administrateurs SET poste_bureau = ? WHERE id_admin = ?");
        if ($update->execute([$poste_bureau, $id_admin])) {
            $_SESSION['message'] = "<div class='alert-msg success'>✅ Membre modifié avec succès.</div>";
            header('Location: bureau.php');
            exit();
        } else {
            $message = "<div class='alert-msg error'>❌ Erreur lors de la mise à jour.</div>";
        }
    }
}

ob_start();
?>

<style>
    /* Conteneur principal */
    .admin-container {
        max-width: 500px;
        margin: var(--space-6) auto;
        padding: 0 var(--space-4);
    }

    .header-section {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: var(--space-5);
    }

    .header-section h1 {
        font-size: var(--font-size-lg);
        color: var(--white);
    }

    /* Style de la Carte */
    .form-card {
        background: var(--primary-800);
        padding: var(--space-5);
        border-radius: var(--radius-lg);
        border: var(--sidebar-border);
        box-shadow: var(--shadow-lg);
    }

    .card-title {
        color: var(--white);
        margin-bottom: var(--space-4);
        font-size: var(--font-size-md);
        border-bottom: 1px solid var(--primary-700);
        padding-bottom: var(--space-3);
    }

    /* Inputs & Select */
    .form-group { margin-bottom: var(--space-4); }
    label { display: block; margin-bottom: var(--space-2); color: var(--gray-300); font-size: var(--font-size-sm); }
    
    select {
        width: 100%;
        padding: 12px;
        background: var(--primary-900);
        border: 1px solid var(--primary-700);
        border-radius: var(--radius-md);
        color: var(--white);
        font-size: var(--font-size-md);
        cursor: pointer;
        transition: var(--transition-base);
    }

    select:focus { border-color: var(--accent-blue); outline: none; }

    /* Boutons */
    .btn-submit {
        width: 100%;
        padding: 12px;
        background: var(--accent-blue);
        color: white;
        border: none;
        border-radius: var(--radius-md);
        font-weight: 600;
        cursor: pointer;
        transition: var(--transition-base);
        margin-top: var(--space-2);
    }

    .btn-submit:hover { background: var(--primary-600); }
    .btn-back { color: var(--gray-400); text-decoration: none; font-size: var(--font-size-sm); transition: var(--transition-fast); }
    .btn-back:hover { color: var(--white); }

    /* Alertes */
    .alert-msg { padding: 12px; border-radius: var(--radius-md); margin-bottom: var(--space-4); font-size: var(--font-size-sm); }
    .error { background: rgba(255, 71, 87, 0.1); color: var(--accent-red); border: 1px solid var(--accent-red); }
    .success { background: rgba(16, 172, 132, 0.1); color: var(--accent-green); border: 1px solid var(--accent-green); }

    /* Responsivité */
    @media (max-width: 480px) {
        .admin-container { margin: var(--space-3) auto; }
        .form-card { padding: var(--space-4); }
    }
</style>

<div class="admin-container">
    <div class="header-section">
        <h1>Modifier le membre</h1>
        <a href="bureau.php" class="btn-back">⬅ Retour</a>
    </div>

    <?= $message ?>

    <div class="form-card">
        <h3 class="card-title"><?= htmlspecialchars($member['nom'] . ' ' . $member['prenom']) ?></h3>
        
        <form method="POST">
            <div class="form-group">
                <label>Rôle dans le bureau :</label>
                <select name="poste_bureau">
                    <option value="" <?= empty($member['poste_bureau']) ? 'selected' : '' ?>>Membre</option>
                    <option value="président" <?= ($member['poste_bureau'] == 'président') ? 'selected' : '' ?>>Président</option>
                    <option value="vice-président" <?= ($member['poste_bureau'] == 'vice-président') ? 'selected' : '' ?>>Vice-président</option>
                    <option value="trésorier" <?= ($member['poste_bureau'] == 'trésorier') ? 'selected' : '' ?>>Trésorier</option>
                    <option value="secrétaire" <?= ($member['poste_bureau'] == 'secrétaire') ? 'selected' : '' ?>>Secrétaire</option>
                    <option value="organisateur" <?= ($member['poste_bureau'] == 'organisateur') ? 'selected' : '' ?>>Organisateur</option>
                </select>
            </div>

            <button type="submit" class="btn-submit">Mettre à jour le membre</button>
        </form>
    </div>
</div>

<?php
$content = ob_get_clean();
include '../layout.php';
?>