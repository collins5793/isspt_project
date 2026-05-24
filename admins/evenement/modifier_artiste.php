<?php
session_start();
require_once "../../includes/db.php";

// Vérification admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit;
}

$id_event = $_GET['id_event'] ?? null;
$nom_artiste = $_GET['nom'] ?? null;

if (!$id_event || !$nom_artiste) {
    die("Paramètres invalides.");
}

// Charger artiste
$stmt = $pdo->prepare("
    SELECT * FROM evenement_artistes
    WHERE id_evenement = ? AND nom_artiste = ?
");
$stmt->execute([$id_event, $nom_artiste]);
$artiste = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$artiste) {
    die("Artiste introuvable.");
}

// Traitement formulaire
$message = "";
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $new_nom = trim($_POST['nom_artiste']);
    $pseudonyme = trim($_POST['pseudonyme']);
    $description = trim($_POST['description']);
    $role = trim($_POST['role']);

    // Gestion photo
    $photoName = $artiste['photo'];

    if (!empty($_FILES['photo']['name'])) {

        $uploadDir = "../uploads/artistes/";
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $fileTmp = $_FILES['photo']['tmp_name'];
        $fileName = time() . "_" . basename($_FILES['photo']['name']);
        $targetPath = $uploadDir . $fileName;

        if (move_uploaded_file($fileTmp, $targetPath)) {

            // Supprimer l'ancienne image si existe
            if ($photoName && file_exists($uploadDir . $photoName)) {
                unlink($uploadDir . $photoName);
            }
            $photoName = $fileName;
        }
    }

    // Mise à jour artiste
    $update = $pdo->prepare("
        UPDATE evenement_artistes
        SET nom_artiste = ?, pseudonyme = ?, description = ?, photo = ?, role = ?
        WHERE id_evenement = ? AND nom_artiste = ?
    ");

    $update->execute([
        $new_nom, $pseudonyme, $description, $photoName, $role,
        $id_event, $nom_artiste
    ]);

    $message = "✔ Artiste modifié avec succès !";

    // Pour éviter bug si nom_artiste a changé :
    $nom_artiste = $new_nom;

    // Recharger artiste
    $stmt->execute([$id_event, $nom_artiste]);
    $artiste = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Contenu à injecter dans layout
ob_start();
?>

<style>
    /* Intégration de la configuration racine */
    :root {
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

        --sidebar-width: 280px;
        --sidebar-width-collapsed: 70px;
        --sidebar-bg: linear-gradient(180deg, var(--primary-900) 0%, var(--primary-800) 100%);
        --sidebar-border: 1px solid rgba(255, 255, 255, 0.05);
        --sidebar-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
        
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
        --transition-slow: 500ms cubic-bezier(0.4, 0, 0.2, 1);
        
        --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.12);
        --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.1);
        --shadow-lg: 0 10px 25px rgba(0, 0, 0, 0.2);
        --shadow-xl: 0 20px 50px rgba(0, 0, 0, 0.3);
        
        --radius-sm: 4px;
        --radius-md: 8px;
        --radius-lg: 12px;
        --radius-xl: 20px;
        --radius-full: 9999px;
        
        --z-sidebar: 1000;
        --z-overlay: 999;
        --z-mobile-toggle: 1001;
    }

    /* Styles Généraux de Conteneur */
    .page-container {
        font-family: var(--font-primary);
        max-width: 1200px;
        margin: 0 auto;
        padding: var(--space-4);
    }

    /* Entête de Page */
    .page-header {
        display: flex;
        justify-content: flex-between;
        align-items: center;
        margin-bottom: var(--space-6);
        gap: var(--space-4);
        flex-wrap: wrap;
    }

    .page-header h2 {
        font-size: var(--font-size-xl);
        color: var(--white);
        margin: 0;
        font-weight: 600;
        letter-spacing: -0.5px;
    }

    /* Boutons personnalisés */
    .btn-custom {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: var(--space-2);
        font-size: var(--font-size-sm);
        font-weight: 500;
        padding: var(--space-3) var(--space-5);
        border-radius: var(--radius-md);
        border: none;
        cursor: pointer;
        transition: var(--transition-base);
        text-decoration: none;
    }

    .btn-back {
        background-color: var(--primary-600);
        color: var(--gray-100);
        border: 1px solid rgba(255, 255, 255, 0.1);
    }

    .btn-back:hover {
        background-color: var(--primary-700);
        color: var(--white);
        transform: translateX(-3px);
    }

    .btn-submit {
        background-color: var(--accent-blue);
        color: var(--white);
        box-shadow: var(--shadow-md);
        width: 100%;
    }

    .btn-submit:hover {
        background-color: #2475c7;
        transform: translateY(-2px);
        box-shadow: var(--shadow-lg);
    }

    /* Notification d'alerte */
    .custom-alert {
        background-color: rgba(16, 172, 132, 0.15);
        border: 1px solid var(--accent-green);
        color: #1dd1a1;
        padding: var(--space-4);
        border-radius: var(--radius-lg);
        margin-bottom: var(--space-5);
        font-size: var(--font-size-sm);
        display: flex;
        align-items: center;
        animation: fadeIn 400ms ease;
    }

    /* Structure Layout Formulaire */
    .form-card {
        background-color: var(--primary-800);
        border: var(--sidebar-border);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-xl);
        padding: var(--space-5);
    }

    .form-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: var(--space-6);
    }

    .form-main-inputs {
        display: flex;
        flex-direction: column;
        gap: var(--space-4);
    }

    .form-photo-section {
        display: flex;
        flex-direction: column;
        gap: var(--space-4);
        align-items: center;
        background-color: var(--primary-700);
        padding: var(--space-5);
        border-radius: var(--radius-lg);
        height: fit-content;
        border: 1px solid rgba(255, 255, 255, 0.03);
    }

    /* Éléments de formulaire */
    .form-group-custom {
        display: flex;
        flex-direction: column;
        gap: var(--space-2);
    }

    .form-group-custom label {
        color: var(--gray-300);
        font-size: var(--font-size-sm);
        font-weight: 500;
    }

    .input-custom {
        background-color: var(--primary-900);
        border: 1px solid var(--primary-600);
        color: var(--white);
        padding: var(--space-3) var(--space-4);
        border-radius: var(--radius-md);
        font-size: var(--font-size-md);
        transition: var(--transition-fast);
        font-family: var(--font-primary);
    }

    .input-custom:focus {
        outline: none;
        border-color: var(--accent-blue);
        box-shadow: 0 0 0 3px rgba(46, 134, 222, 0.2);
    }

    .input-custom::placeholder {
        color: var(--gray-400);
        opacity: 0.5;
    }

    /* Zone visuelle Photo */
    .photo-preview-container {
        position: relative;
        width: 160px;
        height: 160px;
        border-radius: var(--radius-xl);
        overflow: hidden;
        border: 3px solid var(--primary-600);
        box-shadow: var(--shadow-lg);
        background-color: var(--primary-900);
    }

    .artiste-edit-photo {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    /* Personnalisation moderne de l'input File */
    .file-upload-wrapper {
        position: relative;
        width: 100%;
        text-align: center;
    }

    .file-upload-label {
        display: block;
        background-color: var(--primary-900);
        border: 1px dashed var(--primary-600);
        color: var(--gray-300);
        padding: var(--space-3);
        border-radius: var(--radius-md);
        cursor: pointer;
        transition: var(--transition-base);
        font-size: var(--font-size-sm);
    }

    .file-upload-label:hover {
        border-color: var(--accent-blue);
        background-color: var(--primary-600);
        color: var(--white);
    }

    .file-input-hidden {
        position: absolute;
        left: 0;
        top: 0;
        opacity: 0;
        width: 100%;
        height: 100%;
        cursor: pointer;
    }

    .action-container {
        grid-column: span 2;
        margin-top: var(--space-4);
        border-top: 1px solid rgba(255, 255, 255, 0.05);
        padding-top: var(--space-5);
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* Responsivité totale */
    @media (max-width: 992px) {
        .form-grid {
            grid-template-columns: 1fr;
            gap: var(--space-5);
        }
        
        .form-photo-section {
            order: -1; /* Place la photo en haut sur tablette/mobile */
            width: 100%;
            box-sizing: border-box;
        }

        .action-container {
            grid-column: span 1;
        }
    }

    @media (max-width: 576px) {
        .page-header {
            flex-direction: column;
            align-items: stretch;
        }

        .btn-back {
            order: 2;
        }

        .form-card {
            padding: var(--space-4);
        }
    }
</style>

<div class="page-container">
    
    <div class="page-header">
        <h2>Modifier un artiste invité</h2>
        <a href="evenement_detail.php?id=<?= $id_event ?>" class="btn-custom btn-back">
            <span>⬅</span> Retour au détail
        </a>
    </div>

    <?php if ($message): ?>
        <div class="custom-alert"><?= $message ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="form-card">
        <div class="form-grid">
            
            <!-- Colonne Principale : Inputs -->
            <div class="form-main-inputs">
                <div class="form-group-custom">
                    <label for="nom_artiste">Nom de l'artiste *</label>
                    <input type="text" id="nom_artiste" name="nom_artiste" required class="input-custom"
                           value="<?= htmlspecialchars($artiste['nom_artiste']) ?>" placeholder="Ex: John Doe">
                </div>

                <div class="form-group-custom">
                    <label for="pseudonyme">Pseudonyme <span style="opacity: 0.5; font-size: var(--font-size-xs);">(optionnel)</span></label>
                    <input type="text" id="pseudonyme" name="pseudonyme" class="input-custom"
                           value="<?= htmlspecialchars($artiste['pseudonyme']) ?>" placeholder="Ex: The Rock">
                </div>

                <div class="form-group-custom">
                    <label for="role">Rôle ou Performance *</label>
                    <input type="text" id="role" name="role" required class="input-custom"
                           value="<?= htmlspecialchars($artiste['role']) ?>" placeholder="Ex: Chanteur Principal, DJ...">
                </div>

                <div class="form-group-custom">
                    <label for="description">Biographie / Description</label>
                    <textarea id="description" name="description" class="input-custom" rows="5" 
                              placeholder="Ajoutez une courte description ou bio de l'artiste..."><?= htmlspecialchars($artiste['description']) ?></textarea>
                </div>
            </div>

            <!-- Colonne Latérale : Gestion Média -->
            <div class="form-photo-section">
                <label style="color: var(--gray-300); font-size: var(--font-size-sm); font-weight: 500;">Visuel Artiste</label>
                
                <div class="photo-preview-container">
                    <?php 
                        $photoPath = "../uploads/artistes/" . ($artiste['photo'] ?: "default.png");
                    ?>
                    <img src="<?= $photoPath ?>" class="artiste-edit-photo" alt="Photo artiste">
                </div>
                
                <div class="file-upload-wrapper">
                    <div class="file-upload-label">🖼 Remplacer la photo</div>
                    <input type="file" name="photo" class="file-input-hidden" onchange="updateFileName(this)">
                    <div id="file-name-display" style="color: var(--gray-400); font-size: var(--font-size-xs); margin-top: var(--space-2); max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"></div>
                </div>
            </div>

            <!-- Bouton de Soumission global -->
            <div class="action-container">
                <button type="submit" class="btn-custom btn-submit">
                    💾 Enregistrer les modifications
                </button>
            </div>

        </div>
    </form>
</div>

<script>
    // Petit script d'aide UI pour afficher le nom du fichier sélectionné
    function updateFileName(input) {
        const display = document.getElementById('file-name-display');
        if (input.files && input.files[0]) {
            display.textContent = input.files[0].name;
        } else {
            display.textContent = "";
        }
    }
</script>

<?php
$content = ob_get_clean();
include "../layout.php";
?>