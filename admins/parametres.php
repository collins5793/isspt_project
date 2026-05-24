<?php
session_start();
require_once "../includes/db.php";

// Protection de la page : l'administrateur doit être connecté

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

$id_admin = intval($_SESSION['admin_id']);
$errors = [];
$success = null;

// ==========================================
// 1. CHARGEMENT DES DONNÉES DE L'ADMINISTRATEUR
// ==========================================
$stmt = $pdo->prepare("
    SELECT a.*, e.matricule, e.photo, e.promotion, e.filiere, e.telephone 
    FROM administrateurs a
    LEFT JOIN etudiants e ON a.id_etudiant = e.id_etudiant
    WHERE a.id_admin = ?
");
$stmt->execute([$id_admin]);
$adminData = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$adminData) {
    die("Erreur critique : Profil administrateur introuvable.");
}

// Détermination du contexte (Est-ce un étudiant membre du bureau ?)
$isStudentAdmin = !empty($adminData['id_etudiant']);

// ==========================================
// 2. TRAITEMENT DES FORMULAIRES (POST)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // --- FORMULAIRE : PROFIL GÉNÉRAL ---
    if ($action === 'update_profile') {
        $nom = trim($_POST['nom'] ?? '');
        $prenom = trim($_POST['prenom'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $telephone = trim($_POST['telephone'] ?? '');

        if (empty($nom) || empty($prenom) || empty($email)) {
            $errors[] = "Le nom, le prénom et l'adresse email sont obligatoires.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Le format de l'adresse email est invalide.";
        }

        // Vérification de l'unicité de l'email dans la table administrateurs
        $checkEmail = $pdo->prepare("SELECT COUNT(*) FROM administrateurs WHERE email = ? AND id_admin != ?");
        $checkEmail->execute([$email, $id_admin]);
        if ($checkEmail->fetchColumn() > 0) {
            $errors[] = "Cette adresse email est déjà utilisée par un autre compte.";
        }

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                // Mise à jour de la table administrateurs
                $updateAdmin = $pdo->prepare("UPDATE administrateurs SET nom = ?, prenom = ?, email = ? WHERE id_admin = ?");
                $updateAdmin->execute([$nom, $prenom, $email, $id_admin]);

                // Si c'est un étudiant, on met également à jour sa fiche étudiante
                if ($isStudentAdmin) {
                    $updateStudent = $pdo->prepare("UPDATE etudiants SET nom = ?, prenom = ?, email = ?, telephone = ? WHERE id_etudiant = ?");
                    $updateStudent->execute([$nom, $prenom, $email, $telephone, $adminData['id_etudiant']]);
                }

                $pdo->commit();
                $success = "Vos informations personnelles ont été mises à jour avec succès.";
                
                // Rechargement des données fraîches
                $stmt->execute([$id_admin]);
                $adminData = $stmt->fetch(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                $pdo->rollBack();
                $errors[] = "Une erreur système est survenue lors de la mise à jour.";
            }
        }
    }

    // --- FORMULAIRE : SÉCURITÉ / MOT DE PASSE ---
    if ($action === 'update_password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $errors[] = "Tous les champs de mot de passe sont obligatoires.";
        }

        if (!password_verify($current_password, $adminData['mot_de_passe'])) {
            $errors[] = "Le mot de passe actuel saisi est incorrect.";
        }

        if (strlen($new_password) < 8) {
            $errors[] = "Le nouveau mot de passe doit contenir au moins 8 caractères.";
        }

        if ($new_password !== $confirm_password) {
            $errors[] = "La confirmation du nouveau mot de passe ne correspond pas.";
        }

        if (empty($errors)) {
            $hashedPassword = password_hash($new_password, PASSWORD_DEFAULT);
            try {
                $pdo->beginTransaction();

                // Étape 1 : Mise à jour admin
                $updatePassAdmin = $pdo->prepare("UPDATE administrateurs SET mot_de_passe = ? WHERE id_admin = ?");
                $updatePassAdmin->execute([$hashedPassword, $id_admin]);

                // Étape 2 : Si étudiant, synchronisation du mot de passe
                if ($isStudentAdmin) {
                    $updatePassStudent = $pdo->prepare("UPDATE etudiants SET mot_de_passe = ? WHERE id_etudiant = ?");
                    $updatePassStudent->execute([$hashedPassword, $adminData['id_etudiant']]);
                }

                $pdo->commit();
                $success = "Votre mot de passe a été modifié avec succès.";
            } catch (Exception $e) {
                $pdo->rollBack();
                $errors[] = "Erreur lors du traitement de la mise à jour de sécurité.";
            }
        }
    }

    // --- FORMULAIRE : CHANGEMENT DE PHOTO (RÉSERVÉ COMPTE ÉTUDIANT/BUREAU) ---
    if ($action === 'update_avatar' && $isStudentAdmin) {
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath = $_FILES['avatar']['tmp_name'];
            $fileName = $_FILES['avatar']['name'];
            $fileSize = $_FILES['avatar']['size'];
            $fileType = $_FILES['avatar']['type'];
            
            $fileNameCmps = explode(".", $fileName);
            $fileExtension = strtolower(end($fileNameCmps));
            
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
            $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp'];

            if (in_array($fileExtension, $allowedExtensions) && $fileSize < 3000000) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $fileTmpPath);
                finfo_close($finfo);

                if (in_array($mime, $allowedMimeTypes)) {
                    $newFileName = md5(time() . $id_admin) . '.' . $fileExtension;
                    $uploadFileDir = 'uploads/';
                    
                    if (!is_dir($uploadFileDir)) {
                        mkdir($uploadFileDir, 0755, true);
                    }
                    
                    $dest_path = $uploadFileDir . $newFileName;
                    
                    if (move_uploaded_file($fileTmpPath, $dest_path)) {
                        // Supprimer l'ancienne photo si elle existe
                        if (!empty($adminData['photo']) && file_exists($uploadFileDir . $adminData['photo'])) {
                            unlink($uploadFileDir . $adminData['photo']);
                        }

                        // Sauvegarde en Base de données
                        $updatePhoto = $pdo->prepare("UPDATE etudiants SET photo = ? WHERE id_etudiant = ?");
                        $updatePhoto->execute([$newFileName, $adminData['id_etudiant']]);
                        
                        $success = "Votre photo de profil a été mise à jour.";
                        
                        // Rechargement des données fraîches
                        $stmt->execute([$id_admin]);
                        $adminData = $stmt->fetch(PDO::FETCH_ASSOC);
                    } else {
                        $errors[] = "Erreur lors du déplacement du fichier sur le serveur.";
                    }
                } else {
                    $errors[] = "Le contenu du fichier n'est pas une image valide.";
                }
            } else {
                $errors[] = "Extension non autorisée ou fichier trop volumineux (Max: 3 Mo).";
            }
        } else {
            $errors[] = "Veuillez sélectionner un fichier image valide.";
        }
    }
}

ob_start();
?>
<style>
:root {
    /* Colors - Dark Theme (default) */
    --primary-900: #080020;
    --primary-800: #0a0127;
    --primary-700: #120c3a;
    --primary-600: #1a1849;
    --accent-red: #ff4757;
    --accent-blue: #2e86de;
    --accent-green: #10ac84;
    --white: #ffffff;
    --gray-50: #f8f9fa;
    --gray-100: #f1f2f6;
    --gray-200: #dfe4ea;
    --gray-300: #ced6e0;
    --gray-400: #a4b0be;

    /* Typography */
    --font-primary: 'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif;
    --font-size-xs: 0.75rem;
    --font-size-sm: 0.875rem;
    --font-size-md: 1rem;
    --font-size-lg: 1.125rem;
    --font-size-xl: 1.25rem;
    
    /* Spacing */
    --space-1: 0.25rem;
    --space-2: 0.5rem;
    --space-3: 0.75rem;
    --space-4: 1rem;
    --space-5: 1.5rem;
    --space-6: 2rem;
    
    /* Variables de design */
    --radius-md: 8px;
    --radius-lg: 12px;
    --radius-full: 9999px;
    --transition-base: 250ms cubic-bezier(0.4, 0, 0.2, 1);
}

.settings-wrapper {
    font-family: var(--font-primary);
    color: var(--gray-100);
    max-width: 1200px;
    margin: 0 auto;
    padding: var(--space-4);
}

/* Alert Boxes */
.alert-container {
    margin-bottom: var(--space-4);
}
.custom-alert {
    padding: var(--space-4);
    border-radius: var(--radius-md);
    font-size: var(--font-size-sm);
    font-weight: 500;
    margin-bottom: var(--space-2);
}
.alert-danger {
    background-color: rgba(255, 71, 87, 0.12);
    color: var(--accent-red);
    border: 1px solid rgba(255, 71, 87, 0.2);
}
.alert-success {
    background-color: rgba(16, 172, 132, 0.12);
    color: var(--accent-green);
    border: 1px solid rgba(16, 172, 132, 0.2);
}

/* Layout Split Horizontal */
.settings-layout {
    display: grid;
    grid-template-columns: 280px 1fr;
    gap: var(--space-5);
}

/* Navigation par Onglets (Sidebar gauche) */
.settings-nav {
    display: flex;
    flex-direction: column;
    gap: var(--space-2);
}

.nav-tab-btn {
    background: transparent;
    border: 1px solid transparent;
    color: var(--gray-400);
    padding: var(--space-3) var(--space-4);
    border-radius: var(--radius-md);
    text-align: left;
    font-size: var(--font-size-sm);
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: var(--space-3);
    transition: all var(--transition-base);
}

.nav-tab-btn:hover {
    color: var(--white);
    background-color: var(--primary-700);
}

.nav-tab-btn.active {
    color: var(--white);
    background-color: var(--primary-600);
    border-color: rgba(255, 255, 255, 0.08);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

/* Zone de contenu de droite */
.settings-content-panel {
    background-color: var(--primary-800);
    border: 1px solid rgba(255, 255, 255, 0.05);
    border-radius: var(--radius-lg);
    padding: var(--space-5);
    box-shadow: 0 10px 25px rgba(0,0,0,0.15);
}

.tab-pane {
    display: none;
}
.tab-pane.active {
    display: block;
}

.pane-title {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--white);
    margin-top: 0;
    margin-bottom: var(--space-4);
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    padding-bottom: var(--space-3);
}

/* Sub-layout Avatar Upload Section */
.avatar-section {
    display: flex;
    align-items: center;
    gap: var(--space-5);
    background-color: var(--primary-700);
    padding: var(--space-4);
    border-radius: var(--radius-md);
    margin-bottom: var(--space-5);
    border: 1px solid rgba(255, 255, 255, 0.02);
}

.avatar-preview-box {
    width: 96px;
    height: 96px;
    border-radius: var(--radius-full);
    object-fit: cover;
    border: 3px solid var(--primary-600);
    background-color: var(--primary-900);
}

.upload-btn-wrapper {
    position: relative;
    overflow: hidden;
    display: inline-block;
}

.file-input-custom {
    font-size: 100px;
    position: absolute;
    left: 0;
    top: 0;
    opacity: 0;
    cursor: pointer;
}

/* Formulaires & Inputs Pro */
.form-grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: var(--space-4);
    margin-bottom: var(--space-4);
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: var(--space-2);
    margin-bottom: var(--space-4);
}

.form-group.fullwidth {
    grid-column: span 2;
}

.custom-label {
    font-size: var(--font-size-xs);
    font-weight: 600;
    color: var(--gray-400);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.custom-input {
    width: 100%;
    padding: var(--space-3) var(--space-4);
    background-color: var(--primary-700);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: var(--radius-md);
    color: var(--white);
    font-family: var(--font-primary);
    font-size: var(--font-size-sm);
    transition: all var(--transition-base);
}

.custom-input:focus {
    outline: none;
    border-color: var(--accent-blue);
    background-color: var(--primary-600);
}

.custom-input:disabled {
    background-color: var(--primary-900);
    color: var(--gray-400);
    cursor: not-allowed;
    border-color: transparent;
}

/* Actions Triggers */
.action-row-footer {
    display: flex;
    justify-content: flex-end;
    margin-top: var(--space-4);
}

.btn-submit-pro {
    background-color: var(--accent-blue);
    color: var(--white);
    border: none;
    padding: var(--space-3) var(--space-5);
    border-radius: var(--radius-md);
    font-size: var(--font-size-sm);
    font-weight: 600;
    cursor: pointer;
    transition: transform var(--transition-base), background-color var(--transition-base);
}

.btn-submit-pro:hover {
    background-color: #1a70c2;
    transform: translateY(-1px);
}

.badge-role-pill {
    display: inline-block;
    padding: var(--space-1) var(--space-3);
    border-radius: var(--radius-full);
    font-size: var(--font-size-xs);
    font-weight: 700;
    text-transform: uppercase;
    background-color: rgba(46, 134, 222, 0.15);
    color: #54a0ff;
    width: fit-content;
}

/* Responsivité complète */
@media (max-width: 912px) {
    .settings-layout {
        grid-template-columns: 1fr;
    }
    .settings-nav {
        flex-direction: row;
        overflow-x: auto;
        padding-bottom: var(--space-2);
    }
    .nav-tab-btn {
        white-space: nowrap;
    }
}

@media (max-width: 640px) {
    .form-grid-2 {
        grid-template-columns: 1fr;
    }
    .form-group.fullwidth {
        grid-column: span 1;
    }
    .avatar-section {
        flex-direction: column;
        text-align: center;
        align-items: center;
    }
}
</style>

<div class="settings-wrapper">

    <!-- Alert Processing Logs -->
    <div class="alert-container">
        <?php if (!empty($errors)): ?>
            <?php foreach($errors as $error): ?>
                <div class="custom-alert alert-danger">⚠️ <?= htmlspecialchars($error) ?></div>
            <?php endforeach; ?>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="custom-alert alert-success">✨ <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
    </div>

    <!-- Layout Container Structure -->
    <div class="settings-layout">
        
        <!-- Navigation Menu Segment -->
        <nav class="settings-nav">
            <button class="nav-tab-btn active" onclick="switchTab(event, 'account-pane')">
                👤 Mon Profil Général
            </button>
            <button class="nav-tab-btn" onclick="switchTab(event, 'security-pane')">
                🔒 Sécurité & Mot de passe
            </button>
            <?php if ($isStudentAdmin): ?>
                <button class="nav-tab-btn" onclick="switchTab(event, 'student-pane')">
                    🎓 Informations Scolaires
                </button>
            <?php endif; ?>
        </nav>

        <!-- View Content Interchanging Panels Section -->
        <main class="settings-content-panel">

            <!-- PANE 1: PROFIL COMPTE -->
            <div id="account-pane" class="tab-pane active">
                <h3 class="pane-title">Mon Profil Personnel</h3>
                
                <?php if ($isStudentAdmin): ?>
                    <!-- Gestionnaire Avatar (Réservé si lié à un profil étudiant existant) -->
                    <form method="POST" enctype="multipart/form-data" action="">
                        <input type="hidden" name="action" value="update_avatar">
                        <div class="avatar-section">
                            <img src="<?= !empty($adminData['photo']) ? 'uploads/'.htmlspecialchars($adminData['photo']) : 'assets/images/default-avatar.png' ?>" alt="Avatar" class="avatar-preview-box">
                            <div>
                                <div class="upload-btn-wrapper">
                                    <button type="button" class="btn-submit-pro" style="background-color: var(--primary-600); border: 1px solid rgba(255,255,255,0.1);">Choisir une nouvelle photo</button>
                                    <input type="file" name="avatar" class="file-input-custom" onchange="this.form.submit()">
                                </div>
                                <p style="font-size: var(--font-size-xs); color: var(--gray-400); margin: var(--space-2) 0 0 0;">Formats acceptés : PNG, JPG, WEBP. Max 3Mo.</p>
                            </div>
                        </div>
                    </form>
                <?php endif; ?>

                <form method="POST" action="">
                    <input type="hidden" name="action" value="update_profile">
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label class="custom-label">Nom de famille</label>
                            <input type="text" name="nom" class="custom-input" value="<?= htmlspecialchars($adminData['nom'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="custom-label">Prénom</label>
                            <input type="text" name="prenom" class="custom-input" value="<?= htmlspecialchars($adminData['prenom'] ?? '') ?>" required>
                        </div>
                        <div class="form-group <?= !$isStudentAdmin ? 'fullwidth' : '' ?>">
                            <label class="custom-label">Adresse E-mail</label>
                            <input type="email" name="email" class="custom-input" value="<?= htmlspecialchars($adminData['email'] ?? '') ?>" required>
                        </div>
                        <?php if ($isStudentAdmin): ?>
                            <div class="form-group">
                                <label class="custom-label">Numéro de Téléphone</label>
                                <input type="text" name="telephone" class="custom-input" value="<?= htmlspecialchars($adminData['telephone'] ?? '') ?>">
                            </div>
                        <?php endif; ?>
                        
                        <div class="form-group">
                            <label class="custom-label">Rôle d'administration</label>
                            <span class="badge-role-pill"><?= htmlspecialchars($adminData['role']) ?></span>
                        </div>
                        <?php if (!empty($adminData['poste_bureau'])): ?>
                            <div class="form-group">
                                <label class="custom-label">Poste occupé au Bureau</label>
                                <span class="badge-role-pill" style="background-color:rgba(16, 172, 132, 0.15); color: #1dd1a1;"><?= htmlspecialchars($adminData['poste_bureau']) ?></span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="action-row-footer">
                        <button type="submit" class="btn-submit-pro">Enregistrer les modifications</button>
                    </div>
                </form>
            </div>

            <!-- PANE 2: SÉCURITÉ -->
            <div id="security-pane" class="tab-pane">
                <h3 class="pane-title">Sécurité & Authentification</h3>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="update_password">
                    
                    <div class="form-group">
                        <label class="custom-label">Mot de passe actuel</label>
                        <input type="password" name="current_password" class="custom-input" placeholder="••••••••••••" required>
                    </div>
                    
                    <div class="form-grid-2" style="margin-bottom: 0;">
                        <div class="form-group">
                            <label class="custom-label">Nouveau mot de passe</label>
                            <input type="password" name="new_password" class="custom-input" placeholder="Minimum 8 caractères" required>
                        </div>
                        <div class="form-group">
                            <label class="custom-label">Confirmer le nouveau mot de passe</label>
                            <input type="password" name="confirm_password" class="custom-input" placeholder="Ressaisir à l'identique" required>
                        </div>
                    </div>

                    <div class="action-row-footer">
                        <button type="submit" class="btn-submit-pro">Mettre à jour le mot de passe</button>
                    </div>
                </form>
            </div>

            <!-- PANE 3: EN TANT QU'ÉTUDIANT (CONTEXTUEL) -->
            <?php if ($isStudentAdmin): ?>
                <div id="student-pane" class="tab-pane">
                    <h3 class="pane-title">Informations de Scolarité rattachées</h3>
                    <p style="font-size: var(--font-size-sm); color: var(--gray-400); margin-bottom: var(--space-4);">
                        Ces données proviennent de votre fiche d'inscription officielle d'étudiant à l'ISSPT. Elles sont en lecture seule.
                    </p>
                    
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label class="custom-label">Numéro Matricule</label>
                            <input type="text" class="custom-input" value="<?= htmlspecialchars($adminData['matricule'] ?? 'Non renseigné') ?>" disabled>
                        </div>
                        <div class="form-group">
                            <label class="custom-label">Promotion de rattachement</label>
                            <input type="text" class="custom-input" value="<?= htmlspecialchars($adminData['promotion'] ?? 'Non renseigné') ?>" disabled>
                        </div>
                        <div class="form-group fullwidth">
                            <label class="custom-label">Filière / Spécialité d'Étude</label>
                            <input type="text" class="custom-input" value="<?= htmlspecialchars($adminData['filiere'] ?? 'Non renseigné') ?>" disabled>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

        </main>
    </div>
</div>

<script>
/**
 * Logique d'interchangeabilité des panneaux (Tabs)
 */
function switchTab(event, targetPaneId) {
    // Retirer la classe active de tous les boutons de navigation
    const tabButtons = document.querySelectorAll('.nav-tab-btn');
    tabButtons.forEach(btn => btn.classList.remove('active'));

    // Retirer la classe active de tous les volets de contenu
    const tabPanes = document.querySelectorAll('.tab-pane');
    tabPanes.forEach(pane => pane.classList.remove('active'));

    // Ajouter la classe active sur les cibles courantes
    event.currentTarget.classList.add('active');
    document.getElementById(targetPaneId).classList.add('active');
    
    // Conserver l'état de l'onglet actif lors du rechargement de formulaire
    localStorage.setItem('activeSettingsTab', targetPaneId);
}

// Restaurer l'onglet précédemment sélectionné si applicable
document.addEventListener("DOMContentLoaded", () => {
    const savedTab = localStorage.getItem('activeSettingsTab');
    if (savedTab && document.getElementById(savedTab)) {
        const correspondingBtn = Array.from(document.querySelectorAll('.nav-tab-btn')).find(btn => 
            btn.getAttribute('onclick').includes(savedTab)
        );
        if (correspondingBtn) {
            correspondingBtn.click();
        }
    }
});
</script>
<?php
$content = ob_get_clean();
include 'layout.php';
?>