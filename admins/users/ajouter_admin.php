<?php
session_start();
require_once '../../includes/db.php'; // Connexion PDO

// Vérifie si l'utilisateur est connecté
if (!isset($_SESSION['admin_id'])) {
    header("Location: connexion_admin.php");
    exit;
}

$erreur = "";
$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role = $_POST['role'] ?? 'administration';
    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $mot_de_passe = $_POST['mot_de_passe'] ?? '';
    $id_etudiant = $_POST['id_etudiant'] ?? null;
    $poste_bureau = trim($_POST['poste_bureau'] ?? '');

    // Validation
    if ($role === 'bureau' && empty($id_etudiant)) {
        $erreur = "Veuillez sélectionner un étudiant pour le rôle Bureau.";
    } elseif (($role === 'administration' || $role === 'super_admin') && (!$nom || !$prenom || !$email || !$mot_de_passe)) {
        $erreur = "Tous les champs marqués d'un astérisque sont obligatoires.";
    } else {
        if ($role !== 'bureau') {
            $check = $pdo->prepare("SELECT id_admin FROM administrateurs WHERE email = :email");
            $check->execute([':email' => $email]);
            if ($check->rowCount() > 0) {
                $erreur = "Cet email est déjà utilisé par un autre compte.";
            }
        }

        if (empty($erreur)) {
            if ($role === 'bureau') {
                $stmt = $pdo->prepare("INSERT INTO administrateurs (role, id_etudiant, poste_bureau) VALUES (:role, :id_etudiant, :poste_bureau)");
                $stmt->execute([':role' => $role, ':id_etudiant' => $id_etudiant, ':poste_bureau' => !empty($poste_bureau) ? $poste_bureau : 'Membre']);
            } else {
                $hash = password_hash($mot_de_passe, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO administrateurs (nom, prenom, email, mot_de_passe, role) VALUES (:nom, :prenom, :email, :mot_de_passe, :role)");
                $stmt->execute([':nom' => $nom, ':prenom' => $prenom, ':email' => $email, ':mot_de_passe' => $hash, ':role' => $role]);
            }
            header("Location: liste_admins.php?success=1");
            exit;
        }
    }
}

// Liste des étudiants pour le rôle Bureau
$etudiants = $pdo->query("SELECT id_etudiant, matricule, nom, prenom FROM etudiants ORDER BY nom ASC")->fetchAll(PDO::FETCH_ASSOC);

// Injection du contenu dans le layout
ob_start();
?>

<style>
    :root {
        --bg-page: var(--primary-900);
        --bg-card: var(--primary-800);
        --bg-input: var(--primary-700);
        --bg-input-focus: var(--primary-600);
        --border-glass: rgba(255, 255, 255, 0.06);
        --border-focus: var(--accent-blue);
    }

    body {
        background-color: var(--bg-page);
        color: var(--gray-100);
        font-family: var(--font-primary);
    }

    /* En-tête de page */
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: var(--space-6);
        padding-bottom: var(--space-4);
        border-bottom: 1px solid var(--border-glass);
        flex-wrap: wrap;
        gap: var(--space-4);
    }

    .page-header h2 {
        font-size: var(--font-size-xl);
        font-weight: 700;
        color: var(--white);
        margin: 0;
        display: flex;
        align-items: center;
        gap: var(--space-3);
        letter-spacing: -0.5px;
    }

    /* Boîtier de formulaire centralisé */
    .form-container-box {
        max-width: 680px;
        margin: 0 auto var(--space-6) auto;
    }

    .form-card {
        background-color: var(--bg-card);
        border: 1px solid var(--border-glass);
        border-radius: var(--radius-lg);
        padding: var(--space-5);
        box-shadow: var(--shadow-xl);
    }

    .form-group {
        margin-bottom: var(--space-4);
    }

    .form-group label {
        display: block;
        font-size: var(--font-size-sm);
        font-weight: 600;
        color: var(--gray-200);
        margin-bottom: var(--space-2);
    }

    /* Composants de saisie (Inputs, Selects) */
    .form-control, .form-select {
        width: 100%;
        background-color: var(--bg-input);
        border: 1px solid var(--border-glass);
        border-radius: var(--radius-md);
        color: var(--white);
        font-family: var(--font-primary);
        font-size: var(--font-size-sm);
        padding: var(--space-3) var(--space-4);
        box-sizing: border-box;
        transition: all var(--transition-fast);
    }

    .form-select {
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='none' stroke='%23a4b0be' stroke-width='2.5' viewBox='0 0 24 24'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right var(--space-4) center;
        background-size: 16px;
        padding-right: var(--space-6);
        cursor: pointer;
    }

    .form-control:focus, .form-select:focus {
        outline: none;
        background-color: var(--bg-input-focus);
        border-color: var(--border-focus);
        box-shadow: 0 0 0 3px rgba(46, 134, 222, 0.15);
    }

    /* Grille pour les champs alignés (Nom / Prénom) */
    .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: var(--space-4);
    }

    @media (max-width: 576px) {
        .form-grid {
            grid-template-columns: 1fr;
        }
    }

    /* Classes d'animation d'affichage JS */
    .d-none {
        display: none !important;
    }

    /* Boutons et Actions */
    .btn-custom {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: var(--space-2);
        font-family: var(--font-primary);
        font-size: var(--font-size-sm);
        font-weight: 600;
        border-radius: var(--radius-md);
        text-decoration: none;
        padding: var(--space-3) var(--space-5);
        transition: all var(--transition-fast);
        cursor: pointer;
        border: none;
    }

    .btn-submit {
        background-color: var(--accent-blue);
        color: var(--white);
        width: 100%;
        box-shadow: var(--shadow-md);
    }

    .btn-submit:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(46, 134, 222, 0.3);
    }

    .btn-logout {
        background-color: transparent;
        color: var(--gray-400);
        border: 1px solid var(--border-glass);
        padding: var(--space-2) var(--space-4);
    }

    .btn-logout:hover {
        background-color: rgba(255, 71, 87, 0.1);
        color: var(--accent-red);
        border-color: rgba(255, 71, 87, 0.2);
    }

    .navigation-link {
        display: inline-flex;
        align-items: center;
        gap: var(--space-2);
        color: var(--gray-400);
        text-decoration: none;
        font-size: var(--font-size-sm);
        font-weight: 600;
        margin-top: var(--space-4);
        transition: color var(--transition-fast);
    }

    .navigation-link:hover {
        color: var(--white);
    }

    /* Alertes système haut de gamme */
    .alert-custom {
        display: flex;
        align-items: center;
        gap: var(--space-3);
        border-radius: var(--radius-md);
        padding: var(--space-4);
        margin-bottom: var(--space-5);
        font-size: var(--font-size-sm);
    }

    .alert-danger {
        background-color: rgba(255, 71, 87, 0.08);
        border: 1px solid rgba(255, 71, 87, 0.2);
        border-left: 4px solid var(--accent-red);
        color: #ff6b81;
    }

    .alert-success {
        background-color: rgba(16, 172, 132, 0.08);
        border: 1px solid rgba(16, 172, 132, 0.2);
        border-left: 4px solid var(--accent-green);
        color: #1dd1a1;
    }
</style>

<div class="container-fluid">

    <!-- En-tête de page -->
    <div class="page-header">
        <h2>
            <svg width="22" height="22" fill="none" stroke="var(--accent-blue)" stroke-width="2.5" viewBox="0 0 24 24">
                <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                <circle cx="8.5" cy="7" r="4"></circle>
                <line x1="20" y1="8" x2="20" y2="14"></line>
                <line x1="23" y1="11" x2="17" y2="11"></line>
            </svg>
            Ajouter un administrateur
        </h2>
        <a href="deconnexion.php" class="btn-custom btn-logout" onclick="return confirm('Se déconnecter de la session ?')">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>
            </svg>
            Se déconnecter
        </a>
    </div>

    <div class="form-container-box">
        <!-- Notifications dynamiques -->
        <?php if (!empty($erreur)): ?>
            <div class="alert-custom alert-danger">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <span><?= htmlspecialchars($erreur) ?></span>
            </div>
        <?php elseif (!empty($success)): ?>
            <div class="alert-custom alert-success">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span><?= htmlspecialchars($success) ?></span>
            </div>
        <?php endif; ?>

        <!-- Formulaire principal -->
        <div class="form-card">
            <form method="POST" id="formAdmin">
                
                <div class="form-group">
                    <label for="roleSelect">Type de profil / Rôle</label>
                    <select name="role" id="roleSelect" class="form-select" required>
                        <option value="administration">Administration</option>
                        <option value="super_admin">Super Admin</option>
                        <option value="bureau">Membre du Bureau (Étudiant)</option>
                    </select>
                </div>

                <!-- Section des champs pour Administration & Super Admin -->
                <div id="classiqueFields">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="nom">Nom *</label>
                            <input type="text" id="nom" name="nom" class="form-control" placeholder="Nom de l'agent">
                        </div>
                        <div class="form-group">
                            <label for="prenom">Prénom *</label>
                            <input type="text" id="prenom" name="prenom" class="form-control" placeholder="Prénom de l'agent">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="email">Adresse Email *</label>
                        <input type="email" id="email" name="email" class="form-control" placeholder="exemple@domaine.com" autocomplete="email">
                    </div>
                    <div class="form-group">
                        <label for="mot_de_passe">Mot de passe de connexion *</label>
                        <input type="password" id="mot_de_passe" name="mot_de_passe" class="form-control" placeholder="••••••••" autocomplete="new-password">
                    </div>
                </div>

                <!-- Section spécifique pour les membres du bureau (Masquée par défaut) -->
                <div id="bureauFields" class="d-none">
                    <div class="form-group">
                        <label for="id_etudiant">Associer à l'étudiant *</label>
                        <select name="id_etudiant" id="id_etudiant" class="form-select">
                            <option value="" disabled selected>-- Choisir un étudiant --</option>
                            <?php foreach ($etudiants as $e): ?>
                                <option value="<?= $e['id_etudiant'] ?>">
                                    <?= htmlspecialchars($e['nom'] . ' ' . $e['prenom'] . ' (' . $e['matricule'] . ')') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="poste_bureau">Poste au sein du bureau</label>
                        <input type="text" id="poste_bureau" name="poste_bureau" class="form-control" placeholder="Ex: Président, Secrétaire Général, Trésorier...">
                    </div>
                </div>

                <!-- Bouton d'action principal -->
                <div class="form-group" style="margin-top: var(--space-5); margin-bottom: 0;">
                    <button type="submit" class="btn-custom btn-submit">
                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path d="M5 13l4 4L19 7"/>
                        </svg>
                        Créer le compte administrateur
                    </button>
                </div>
            </form>
        </div>

        <!-- Lien de retour -->
        <a href="dashboard_admin.php" class="navigation-link">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path d="M19 12H5M12 19l-7-7 7-7"/>
            </svg>
            Retour au tableau de bord
        </a>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const roleSelect = document.getElementById('roleSelect');
    const classiqueFields = document.getElementById('classiqueFields');
    const bureauFields = document.getElementById('bureauFields');

    // Éléments requis à activer/désactiver dynamiquement pour la validation html5 de soumission
    const inputsClassique = classiqueFields.querySelectorAll('input');
    const selectBureau = document.getElementById('id_etudiant');

    function toggleFields() {
        if (roleSelect.value === 'bureau') {
            classiqueFields.classList.add('d-none');
            bureauFields.classList.remove('d-none');
            
            // Retirer les contraintes requis sur le formulaire caché
            inputsClassique.forEach(input => input.removeAttribute('required'));
            selectBureau.setAttribute('required', 'required');
        } else {
            classiqueFields.classList.remove('d-none');
            bureauFields.classList.add('d-none');
            
            // Appliquer les contraintes requis sur les champs affichés
            inputsClassique.forEach(input => input.setAttribute('required', 'required'));
            selectBureau.removeAttribute('required');
        }
    }

    roleSelect.addEventListener('change', toggleFields);
    toggleFields(); // Exécution initiale au chargement du DOM
});
</script>

<?php
$content = ob_get_clean();
include '../layout.php';
?>