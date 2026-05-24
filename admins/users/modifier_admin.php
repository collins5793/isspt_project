<?php
session_start();
require_once '../../includes/db.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: connexion_admin.php");
    exit;
}

if (!isset($_GET['id'])) {
    header("Location: admins.php");
    exit;
}

$id_admin = intval($_GET['id']);
$stmt = $pdo->prepare("SELECT * FROM administrateurs WHERE id_admin = ?");
$stmt->execute([$id_admin]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$admin) {
    header("Location: admins.php");
    exit;
}

// Liste des étudiants pour le rôle bureau
$etudiants = $pdo->query("SELECT id_etudiant, matricule, nom, prenom FROM etudiants ORDER BY nom ASC")->fetchAll(PDO::FETCH_ASSOC);

// ... (Gardez votre logique de traitement POST inchangée) ...

ob_start();
?>

<style>
    /* Design System Personnalisé */
    .admin-form-container {
        max-width: 600px;
        margin: 2rem auto;
        background: var(--primary-800);
        padding: var(--space-5);
        border-radius: var(--radius-lg);
        border: var(--sidebar-border);
        box-shadow: var(--shadow-lg);
    }
    
    .form-group { margin-bottom: var(--space-4); }
    
    .form-label { display: block; margin-bottom: var(--space-2); color: var(--gray-300); font-size: var(--font-size-sm); }
    
    .form-control, .form-select {
        width: 100%;
        padding: 12px 16px;
        background: var(--primary-900);
        border: 1px solid var(--primary-700);
        border-radius: var(--radius-md);
        color: var(--white);
        transition: var(--transition-base);
    }
    
    .form-control:focus { border-color: var(--accent-blue); outline: none; }
    
    .btn-submit {
        background: var(--accent-blue);
        color: var(--white);
        border: none;
        padding: 12px;
        width: 100%;
        border-radius: var(--radius-md);
        font-weight: 600;
        cursor: pointer;
        transition: var(--transition-base);
    }
    
    .btn-submit:hover { background: var(--primary-600); }

    .d-none { display: none; }
    
    /* Responsivité */
    @media (max-width: 768px) {
        .admin-form-container { margin: 1rem; padding: var(--space-3); }
    }
</style>

<div class="admin-form-container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 style="color: var(--white); margin: 0;">Modification Admin</h2>
        <a href="liste_admins.php" style="color: var(--accent-red); text-decoration: none; font-size: 0.9rem;">Annuler</a>
    </div>

    <?php if(!empty($erreur)): ?>
        <div style="background: var(--accent-red); color: white; padding: 10px; border-radius: var(--radius-md); margin-bottom: 20px;">
            <?= $erreur ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label class="form-label">Rôle</label>
            <select name="role" id="roleSelect" class="form-select" required>
                <option value="administration" <?= $admin['role']==='administration' ? 'selected' : '' ?>>Administration</option>
                <option value="super_admin" <?= $admin['role']==='super_admin' ? 'selected' : '' ?>>Super Admin</option>
                <option value="bureau" <?= $admin['role']==='bureau' ? 'selected' : '' ?>>Bureau Étudiant</option>
            </select>
        </div>

        <!-- Champs Administration -->
        <div id="classiqueFields">
            <div class="form-group">
                <label class="form-label">Nom</label>
                <input type="text" name="nom" class="form-control" value="<?= htmlspecialchars($admin['nom'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Prénom</label>
                <input type="text" name="prenom" class="form-control" value="<?= htmlspecialchars($admin['prenom'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($admin['email'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Nouveau mot de passe</label>
                <input type="password" name="mot_de_passe" class="form-control" placeholder="Laisser vide pour conserver l'actuel">
            </div>
        </div>

        <!-- Champs Bureau (cachés par défaut si rôle admin) -->
        <div id="bureauFields" class="d-none">
            <div class="form-group">
                <label class="form-label">Sélectionner l'étudiant</label>
                <select name="id_etudiant" class="form-select">
                    <?php foreach($etudiants as $e): ?>
                        <option value="<?= $e['id_etudiant'] ?>" <?= $admin['id_etudiant'] == $e['id_etudiant'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($e['nom'].' '.$e['prenom'].' ('.$e['matricule'].')') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Poste au sein du bureau</label>
                <input type="text" name="poste_bureau" class="form-control" value="<?= htmlspecialchars($admin['poste_bureau'] ?? '') ?>">
            </div>
        </div>

        <button type="submit" class="btn-submit">Mettre à jour l'administrateur</button>
    </form>
</div>

<script>
    const roleSelect = document.getElementById('roleSelect');
    const classiqueFields = document.getElementById('classiqueFields');
    const bureauFields = document.getElementById('bureauFields');

    function toggleFields() {
        if (roleSelect.value === 'bureau') {
            classiqueFields.classList.add('d-none');
            bureauFields.classList.remove('d-none');
        } else {
            classiqueFields.classList.remove('d-none');
            bureauFields.classList.add('d-none');
        }
    }

    roleSelect.addEventListener('change', toggleFields);
    toggleFields(); 
</script>

<?php
$content = ob_get_clean();
include '../layout.php';
?>