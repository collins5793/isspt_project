<?php
session_start();
require_once '../../includes/db.php';

// Vérifier si l'admin est connecté
if (!isset($_SESSION['admin_id'])) {
    header('Location: connexion_admin.php');
    exit;
}

$message = '';

// =============================
//   TRAITEMENT DU FORMULAIRE
// =============================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom']);
    $prenom = trim($_POST['prenom']);
    $email = trim($_POST['email']);
    $mot_de_passe = password_hash($_POST['mot_de_passe'], PASSWORD_BCRYPT);
    $promotion = trim($_POST['promotion']);
    $filiere = trim($_POST['filiere']);
    $telephone = trim($_POST['telephone']);
    $photo = null;

    // Gestion de la photo
    if (!empty($_FILES['photo']['name'])) {
        $uploadDir = '../uploads/photos_etudiants/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $fileName = uniqid() . '_' . basename($_FILES['photo']['name']);
        $uploadFile = $uploadDir . $fileName;

        if (move_uploaded_file($_FILES['photo']['tmp_name'], $uploadFile)) {
            $photo = $fileName;
        }
    }

    // Matricule automatique
    $matricule = 'ETD-' . strtoupper(substr($nom, 0, 2)) . rand(1000, 9999);

    // Insertion DB
    $sql = "INSERT INTO etudiants (matricule, nom, prenom, email, mot_de_passe, photo, promotion, filiere, telephone)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([$matricule, $nom, $prenom, $email, $mot_de_passe, $photo, $promotion, $filiere, $telephone]);

    if ($result) {
        header("Location: etudiants.php?success=1");
        exit;
    } else {
        $message = "
        <div class='alert-custom error'>
            <svg xmlns='http://www.w3.org/2000/svg' width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><circle cx='12' cy='12' r='10'></circle><line x1='15' y1='9' x2='9' y2='15'></line><line x1='9' y1='9' x2='15' y2='15'></line></svg>
            <span>Une erreur est survenue lors de l’inscription de l'étudiant. Veuillez réessayer.</span>
        </div>";
    }
}

ob_start();
?>

<style>
    :root {
        /* Intégration de vos variables */
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

        --font-primary: 'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif;
        --font-size-xs: 0.75rem;
        --font-size-sm: 0.875rem;
        --font-size-md: 1rem;
        --font-size-lg: 1.125rem;
        --font-size-xl: 1.25rem;

        --space-1: 0.25rem;
        --space-2: 0.5rem;
        --space-3: 0.75rem;
        --space-4: 1rem;
        --space-5: 1.5rem;
        --space-6: 2rem;

        --transition-fast: 150ms cubic-bezier(0.4, 0, 0.2, 1);
        --transition-base: 300ms cubic-bezier(0.4, 0, 0.2, 1);

        --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.1);
        --shadow-lg: 0 10px 25px rgba(0, 0, 0, 0.2);

        --radius-md: 8px;
        --radius-lg: 12px;
        --radius-xl: 20px;
        --radius-full: 9999px;
    }

    /* Styles globaux de la zone de contenu (si non gérés par le layout) */
    .register-container {
        font-family: var(--font-primary);
        color: var(--white);
        max-width: 1100px;
        margin: 0 auto;
        padding: var(--space-4);
    }

    /* En-tête de la page */
    .page-header-custom {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: var(--space-6);
    }

    .page-header-custom h1 {
        font-size: 1.75rem;
        font-weight: 700;
        color: var(--white);
        margin: 0;
        letter-spacing: -0.02em;
    }

    /* Bouton Retour élégant */
    .btn-back {
        display: inline-flex;
        align-items: center;
        gap: var(--space-2);
        background: var(--primary-700);
        color: var(--gray-200);
        padding: 0.625rem var(--space-4);
        border-radius: var(--radius-md);
        text-decoration: none;
        font-size: var(--font-size-sm);
        font-weight: 500;
        border: 1px solid rgba(255, 255, 255, 0.05);
        transition: var(--transition-fast);
    }

    .btn-back:hover {
        background: var(--primary-600);
        color: var(--white);
        transform: translateX(-2px);
    }

    /* Alertes personnalisées */
    .alert-custom {
        display: flex;
        align-items: center;
        gap: var(--space-3);
        padding: var(--space-4);
        border-radius: var(--radius-md);
        margin-bottom: var(--space-5);
        font-size: var(--font-size-sm);
        line-height: 1.5;
    }

    .alert-custom.error {
        background: rgba(255, 71, 87, 0.1);
        border: 1px solid var(--accent-red);
        color: #ff6b81;
    }

    /* Structure de la carte */
    .card-custom {
        background: linear-gradient(145deg, var(--primary-800) 0%, var(--primary-900) 100%);
        border: 1px solid rgba(255, 255, 255, 0.05);
        border-radius: var(--radius-xl);
        box-shadow: var(--shadow-lg);
        overflow: hidden;
    }

    .card-header-custom {
        padding: var(--space-5) var(--space-6);
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        background: rgba(0, 0, 0, 0.1);
    }

    .card-header-custom h3 {
        font-size: var(--font-size-lg);
        font-weight: 600;
        color: var(--gray-100);
        margin: 0;
    }

    .card-body-custom {
        padding: var(--space-6);
    }

    /* Grille de formulaire intelligente */
    .form-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: var(--space-5) var(--space-6);
        margin-bottom: var(--space-6);
    }

    @media (max-width: 768px) {
        .form-grid {
            grid-template-columns: 1fr;
            gap: var(--space-4);
        }
    }

    /* Section thématique dans le formulaire */
    .form-section-title {
        grid-column: 1 / -1;
        font-size: var(--font-size-sm);
        text-transform: uppercase;
        letter-spacing: 0.1em;
        color: var(--accent-blue);
        margin-bottom: -0.5rem;
        margin-top: var(--space-2);
        font-weight: 700;
    }

    /* Groupes de champs de formulaire */
    .form-group-custom {
        display: flex;
        flex-direction: column;
        gap: var(--space-2);
    }

    .form-group-custom label {
        font-size: var(--font-size-sm);
        font-weight: 500;
        color: var(--gray-300);
    }

    /* Input style pro */
    .form-control-custom {
        background: var(--primary-700);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: var(--radius-md);
        padding: 0.75rem var(--space-4);
        color: var(--white);
        font-family: var(--font-primary);
        font-size: var(--font-size-sm);
        transition: var(--transition-fast);
    }

    .form-control-custom:focus {
        outline: none;
        border-color: var(--accent-blue);
        box-shadow: 0 0 0 3px rgba(46, 134, 222, 0.15);
        background: var(--primary-600);
    }

    .form-control-custom::placeholder {
        color: var(--gray-400);
        opacity: 0.5;
    }

    /* Upload de fichier stylisé (Drag and drop visual look) */
    .file-upload-wrapper {
        position: relative;
        border: 2px dashed rgba(255, 255, 255, 0.15);
        border-radius: var(--radius-md);
        padding: var(--space-4);
        text-align: center;
        background: rgba(0, 0, 0, 0.1);
        cursor: pointer;
        transition: var(--transition-fast);
    }

    .file-upload-wrapper:hover {
        border-color: var(--accent-blue);
        background: rgba(46, 134, 222, 0.02);
    }

    .file-upload-wrapper input[type="file"] {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        opacity: 0;
        cursor: pointer;
    }

    .file-upload-icon {
        color: var(--gray-400);
        margin-bottom: var(--space-2);
    }

    .file-upload-text {
        font-size: var(--font-size-sm);
        color: var(--gray-300);
    }

    .file-upload-hint {
        font-size: var(--font-size-xs);
        color: var(--gray-400);
        margin-top: var(--space-1);
    }

    /* Bouton d'action principal */
    .btn-submit-custom {
        background: var(--accent-blue);
        color: var(--white);
        border: none;
        border-radius: var(--radius-md);
        padding: 0.875rem var(--space-5);
        font-size: var(--font-size-md);
        font-weight: 600;
        cursor: pointer;
        width: 100%;
        display: flex;
        justify-content: center;
        align-items: center;
        gap: var(--space-2);
        box-shadow: var(--shadow-md);
        transition: var(--transition-base);
    }

    .btn-submit-custom:hover {
        background: #48dbfb; /* Légère variation lumineuse au survol */
        transform: translateY(-1px);
        box-shadow: 0 6px 20px rgba(46, 134, 222, 0.3);
    }

    .btn-submit-custom:active {
        transform: translateY(1px);
    }
</style>

<div class="register-container">
    
    <!-- Header de la page -->
    <div class="page-header-custom">
        <h1>Inscrire un Étudiant</h1>
        <a href="etudiants.php" class="btn-back">
            <svg xmlns="http://www.w3.org/2000/svg" width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><line x1='19' y1='12' x2='5' y2='12'></line><polyline points='12 19 5 12 12 5'></polyline></svg>
            Retour à la liste
        </a>
    </div>

    <!-- Zone des Messages d'erreur/succès -->
    <?= $message; ?>

    <!-- Formulaire d'inscription -->
    <div class="card-custom">
        <div class="card-header-custom">
            <h3>Formulaire d'inscription de l'étudiant</h3>
        </div>

        <div class="card-body-custom">
            <form method="POST" enctype="multipart/form-data">
                
                <div class="form-grid">
                    
                    <!-- Section : Informations Personnelles -->
                    <div class="form-section-title">Informations Personnelles</div>

                    <div class="form-group-custom">
                        <label for="nom">Nom</label>
                        <input type="text" id="nom" name="nom" class="form-control-custom" placeholder="Ex : DOSSOUR" required>
                    </div>

                    <div class="form-group-custom">
                        <label for="prenom">Prénom</label>
                        <input type="text" id="prenom" name="prenom" class="form-control-custom" placeholder="Ex : Koffi" required>
                    </div>

                    <div class="form-group-custom">
                        <label for="email">Adresse Email Pro / Universitaire</label>
                        <input type="email" id="email" name="email" class="form-control-custom" placeholder="Ex : k.dossour@institution.bj" required>
                    </div>

                    <div class="form-group-custom">
                        <label for="telephone">Téléphone</label>
                        <input type="tel" id="telephone" name="telephone" class="form-control-custom" placeholder="Ex : +229 01 00 00 00 00">
                    </div>

                    <!-- Section : Sécurité & Compte -->
                    <div class="form-section-title">Sécurité du compte</div>

                    <div class="form-group-custom">
                        <label for="mot_de_passe">Mot de passe initial</label>
                        <input type="password" id="mot_de_passe" name="mot_de_passe" class="form-control-custom" placeholder="••••••••" required>
                    </div>

                    <div class="form-group-custom">
                        <label>Photo de profil</label>
                        <div class="file-upload-wrapper">
                            <div class="file-upload-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap='round' stroke-linejoin='round'><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
                            </div>
                            <div class="file-upload-text">Parcourir ou glisser l'image ici</div>
                            <div class="file-upload-hint">Format acceptés : JPG, PNG (Max. 2MB)</div>
                            <input type="file" name="photo" id="photo-input">
                        </div>
                    </div>

                    <!-- Section : Informations Académiques -->
                    <div class="form-section-title">Parcours Académique</div>

                    <div class="form-group-custom">
                        <label for="filiere">Filière / Spécialité</label>
                        <input type="text" id="filiere" name="filiere" class="form-control-custom" placeholder="Ex : Systèmes Informatiques et Logiciels" required>
                    </div>

                    <div class="form-group-custom">
                        <label for="promotion">Promotion / Année</label>
                        <input type="text" id="promotion" name="promotion" class="form-control-custom" placeholder="Ex : 2024-2025" required>
                    </div>

                </div>

                <!-- Bouton de Soumission -->
                <button type="submit" class="btn-submit-custom">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx='8.5' cy='7' r='4'></circle><line x1='20' y1='8' x2='20' y2='14'></line><line x1='23' y1='11' x2='17' y2='11'></line></svg>
                    Inscrire et générer le matricule
                </button>

            </form>
        </div>
    </div>
</div>

<script>
    // Petit ajout d'UI dynamique : Changer le texte de l'upload lorsqu'un fichier est sélectionné
    const fileInput = document.getElementById('photo-input');
    const uploadText = document.querySelector('.file-upload-text');
    
    if(fileInput) {
        fileInput.addEventListener('change', function(e) {
            if(this.files && this.files.length > 0) {
                uploadText.textContent = "Fichier sélectionné : " + this.files[0].name;
                uploadText.style.color = "var(--accent-green)";
            }
        });
    }
</script>

<?php
$content = ob_get_clean();
include "../layout.php";
?>