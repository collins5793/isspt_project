<?php
session_start();
require_once "../../includes/db.php";

// 1. VÉRIFICATION ET RÉCUPÉRATION DU TEMPLATE EXISTANT
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: liste_templates.php");
    exit();
}

$id_template = intval($_GET['id']);

try {
    $stmt = $pdo->prepare("SELECT * FROM ticket_templates WHERE id_template = ?");
    $stmt->execute([$id_template]);
    $template = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$template) {
        header("Location: liste_templates.php");
        exit();
    }
} catch (PDOException $e) {
    die("Erreur de base de données : " . $e->getMessage());
}

// 2. INITIALISATION DES MESSAGES
$errors = [];
$success = null;

// Valeurs initiales (provenant de la BDD ou du POST en cas d'erreur)
$nom_template = $_POST['nom_template'] ?? $template['nom_template'];
$description = $_POST['description'] ?? $template['description'];
$border_radius = $_POST['border_radius'] ?? $template['border_radius'];
$overlay_color = $_POST['overlay_color'] ?? $template['overlay_color'];
$is_active = isset($_POST['submit']) ? (isset($_POST['is_active']) ? 1 : 0) : $template['is_active'];

// 3. TRAITEMENT DE LA MISE À JOUR (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    
    $nom_template = trim($nom_template);
    $description = trim($description);
    $border_radius = filter_var($border_radius, FILTER_VALIDATE_INT);
    $overlay_color = trim($overlay_color);

    if (empty($nom_template)) {
        $errors[] = "Le nom du template est obligatoire.";
    }

    // Sauvegarde du chemin actuel au cas où on ne change pas d'image
    $image_path = $template['image_path'];
    $canvas_width = $template['canvas_width'];
    $canvas_height = $template['canvas_height'];

    // Gestion de l'upload si une NOUVELLE image est fournie
    if (isset($_FILES['image_template']) && $_FILES['image_template']['error'] === UPLOAD_ERR_OK) {
        
        $fileTmpPath = $_FILES['image_template']['tmp_name'];
        $fileName = $_FILES['image_template']['name'];
        $fileSize = $_FILES['image_template']['size'];
        
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($fileTmpPath);
        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp'];

        if (!in_array($fileExtension, $allowedExtensions) || !in_array($mimeType, $allowedMimeTypes)) {
            $errors[] = "Format d'image non valide. Autorisés : JPG, PNG, WEBP.";
        }

        if ($fileSize > 5 * 1024 * 1024) {
            $errors[] = "L'image est trop lourde. Maximum 5 Mo.";
        }

        // Analyse des dimensions de la nouvelle image
        if (empty($errors)) {
            $imageSizes = getimagesize($fileTmpPath);
            if ($imageSizes !== false) {
                $canvas_width = $imageSizes[0];
                $canvas_height = $imageSizes[1];
            } else {
                $errors[] = "Impossible d'analyser les dimensions de la nouvelle image.";
            }
        }

        // Déplacement du fichier et nettoyage de l'ancien
        if (empty($errors)) {
            $uploadFolder = '../../uploads/templates/';
            if (!is_dir($uploadFolder)) {
                mkdir($uploadFolder, 0755, true);
            }
            
            $newFileName = bin2hex(random_bytes(8)) . '.' . $fileExtension;
            $dest_path = $uploadFolder . $newFileName;

            if (move_uploaded_file($fileTmpPath, $dest_path)) {
                // Supprimer l'ancienne image du serveur pour éviter les fichiers inutiles
                $oldImagePath = "../../" . $template['image_path'];
                if (!empty($template['image_path']) && file_exists($oldImagePath)) {
                    unlink($oldImagePath);
                }
                // Mettre à jour le chemin pour la BDD
                $image_path = 'uploads/templates/' . $newFileName;
            } else {
                $errors[] = "Erreur lors de l'enregistrement de la nouvelle image.";
            }
        }
    }

    // MISE À JOUR DANS LA BASE DE DONNÉES
    if (empty($errors)) {
        try {
            $sql = "UPDATE ticket_templates SET 
                        nom_template = :nom_template, 
                        description = :description, 
                        image_path = :image_path, 
                        canvas_width = :canvas_width, 
                        canvas_height = :canvas_height, 
                        border_radius = :border_radius, 
                        overlay_color = :overlay_color, 
                        is_active = :is_active 
                    WHERE id_template = :id_template";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':nom_template'  => $nom_template,
                ':description'   => !empty($description) ? $description : null,
                ':image_path'    => $image_path,
                ':canvas_width'  => $canvas_width,
                ':canvas_height' => $canvas_height,
                ':border_radius' => $border_radius,
                ':overlay_color' => $overlay_color,
                ':is_active'     => $is_active,
                ':id_template'   => $id_template
            ]);

            $success = "Le template a été mis à jour avec succès !";
            
            // Rafraîchir les données de l'arrière-plan pour l'affichage à jour
            $template['image_path'] = $image_path;
            $template['canvas_width'] = $canvas_width;
            $template['canvas_height'] = $canvas_height;

        } catch (PDOException $e) {
            $errors[] = "Erreur lors de la mise à jour : " . $e->getMessage();
        }
    }
}

// Début du tampon d'affichage
ob_start();
?>

<style>
    /* ==========================================================================
       1. VARIABLES & RESET CONFIGURATION
       ========================================================================== */
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

        /* Sidebar */
        --sidebar-width: 280px;
        --sidebar-width-collapsed: 70px;
        --sidebar-bg: linear-gradient(180deg, var(--primary-900) 0%, var(--primary-800) 100%);
        --sidebar-border: 1px solid rgba(255, 255, 255, 0.05);
        --sidebar-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
        
        /* Typography */
        --font-primary: 'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif;
        --font-size-xs: 0.75rem;   /* 12px */
        --font-size-sm: 0.875rem;  /* 14px */
        --font-size-md: 1rem;      /* 16px */
        --font-size-lg: 1.125rem;  /* 18px */
        --font-size-xl: 1.25rem;   /* 20px */
        
        /* Spacing */
        --space-1: 0.25rem;   /* 4px */
        --space-2: 0.5rem;    /* 8px */
        --space-3: 0.75rem;   /* 12px */
        --space-4: 1rem;      /* 16px */
        --space-5: 1.5rem;    /* 24px */
        --space-6: 2rem;      /* 32px */
        
        /* Transitions */
        --transition-fast: 150ms cubic-bezier(0.4, 0, 0.2, 1);
        --transition-base: 300ms cubic-bezier(0.4, 0, 0.2, 1);
        --transition-slow: 500ms cubic-bezier(0.4, 0, 0.2, 1);
        
        /* Shadows */
        --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.12);
        --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.1);
        --shadow-lg: 0 10px 25px rgba(0, 0, 0, 0.2);
        --shadow-xl: 0 20px 50px rgba(0, 0, 0, 0.3);
        
        /* Border Radius */
        --radius-sm: 4px;
        --radius-md: 8px;
        --radius-lg: 12px;
        --radius-xl: 20px;
        --radius-full: 9999px;
        
        /* Z-index */
        --z-sidebar: 1000;
        --z-overlay: 999;
        --z-mobile-toggle: 1001;
    }

    /* Base Styling Wrapper */
    .studio-wrapper {
        font-family: var(--font-primary);
        color: var(--white);
        max-width: 1100px;
        margin: 0 auto;
        padding: var(--space-4);
        box-sizing: border-box;
    }

    .studio-wrapper *, .studio-wrapper *::before, .studio-wrapper *::after {
        box-sizing: border-box;
    }

    /* ==========================================================================
       2. HEADER STRIP
       ========================================================================== */
    .page-header-strip {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: var(--space-5);
        gap: var(--space-4);
    }

    .header-title-area h2 {
        font-size: var(--font-size-xl);
        font-weight: 700;
        margin: 0 0 var(--space-1) 0;
        letter-spacing: -0.5px;
        color: var(--white);
    }

    .header-title-area p {
        font-size: var(--font-size-sm);
        color: var(--gray-400);
        margin: 0;
    }

    .btn-back-action {
        display: inline-flex;
        align-items: center;
        background-color: var(--primary-700);
        color: var(--white);
        font-size: var(--font-size-sm);
        font-weight: 600;
        padding: var(--space-2) var(--space-4);
        border-radius: var(--radius-md);
        text-decoration: none;
        border: 1px solid rgba(255, 255, 255, 0.08);
        transition: all var(--transition-fast);
        box-shadow: var(--shadow-sm);
    }

    .btn-back-action:hover {
        background-color: var(--primary-600);
        transform: translateY(-1px);
        box-shadow: var(--shadow-md);
    }

    .studio-divider {
        border: 0;
        border-top: 1px solid rgba(255, 255, 255, 0.08);
        margin-bottom: var(--space-5);
    }

    /* ==========================================================================
       3. SYSTEM NOTIFICATIONS
       ========================================================================== */
    .alert-container {
        margin-bottom: var(--space-5);
    }

    .alert-box {
        padding: var(--space-4);
        border-radius: var(--radius-md);
        font-size: var(--font-size-sm);
        line-height: 1.5;
    }

    .alert-danger {
        background-color: rgba(255, 71, 87, 0.1);
        border-left: 4px solid var(--accent-red);
        color: #ff6b7b;
    }

    .alert-danger ul {
        margin: var(--space-2) 0 0 0;
        padding-left: var(--space-4);
    }

    .alert-success {
        background-color: rgba(16, 172, 132, 0.1);
        border-left: 4px solid var(--accent-green);
        color: #1dd1a1;
        font-weight: 500;
    }

    /* ==========================================================================
       4. COMPONENT EDITOR FORM
       ========================================================================== */
    .main-form-card {
        background: linear-gradient(145deg, var(--primary-800), var(--primary-900));
        border: var(--sidebar-border);
        border-radius: var(--radius-lg);
        box-shadow: var(--sidebar-shadow);
        padding: var(--space-5);
    }

    .form-stack {
        display: flex;
        flex-direction: column;
        gap: var(--space-5);
    }

    .form-section {
        background-color: rgba(255, 255, 255, 0.02);
        border: 1px solid rgba(255, 255, 255, 0.04);
        border-radius: var(--radius-md);
        padding: var(--space-4);
    }

    .section-title {
        font-size: var(--font-size-xs);
        font-weight: 700;
        color: var(--gray-400);
        text-transform: uppercase;
        letter-spacing: 0.75px;
        margin: 0 0 var(--space-4) 0;
    }

    .field-group {
        display: flex;
        flex-direction: column;
        gap: var(--space-2);
        margin-bottom: var(--space-4);
    }

    .field-group:last-child {
        margin-bottom: 0;
    }

    .field-group label {
        font-size: var(--font-size-sm);
        font-weight: 600;
        color: var(--gray-200);
    }

    .field-group label .required-mark {
        color: var(--accent-red);
    }

    .field-group label .optional-mark {
        font-size: var(--font-size-xs);
        color: var(--gray-400);
        font-weight: 400;
    }

    /* Controls inputs styling */
    .input-control, .textarea-control {
        width: 100%;
        background-color: var(--primary-700);
        border: 1px solid var(--primary-600);
        border-radius: var(--radius-md);
        padding: var(--space-3);
        color: var(--white);
        font-size: var(--font-size-sm);
        transition: all var(--transition-fast);
    }

    .input-control:focus, .textarea-control:focus {
        outline: none;
        border-color: var(--accent-blue);
        box-shadow: 0 0 0 3px rgba(46, 134, 222, 0.15);
    }

    .textarea-control {
        resize: vertical;
    }

    /* Row split grids */
    .field-row-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: var(--space-4);
    }

    @media (min-width: 768px) {
        .field-row-grid {
            grid-template-columns: 1fr 1fr;
        }
    }

    /* Current Asset Showcase Box */
    .asset-showcase-box {
        display: flex;
        align-items: center;
        gap: var(--space-4);
        background-color: rgba(0, 0, 0, 0.2);
        border: 1px solid rgba(255, 255, 255, 0.05);
        border-radius: var(--radius-md);
        padding: var(--space-3);
        margin-bottom: var(--space-4);
    }

    .asset-preview-frame {
        width: 80px;
        height: 55px;
        background-color: var(--primary-700);
        border-radius: var(--radius-sm);
        overflow: hidden;
        flex-shrink: 0;
        border: 1px solid rgba(255, 255, 255, 0.1);
    }

    .asset-preview-frame img {
        width: 100%;
        height: 100%;
        object-cover: cover;
    }

    .asset-meta-text {
        font-size: var(--font-size-xs);
        color: var(--gray-400);
    }

    .asset-meta-text p {
        margin: 0;
    }

    .asset-meta-text .asset-title {
        font-weight: 600;
        color: var(--white);
        margin-bottom: var(--space-1);
    }

    /* Custom File Input Decorator */
    .file-input-wrapper {
        position: relative;
    }

    .input-control[type="file"] {
        padding: var(--space-2);
        font-size: var(--font-size-xs);
        background-color: var(--primary-900);
    }

    .input-control[type="file"]::file-selector-button {
        background-color: var(--primary-600);
        color: var(--white);
        border: 0;
        padding: var(--space-2) var(--space-3);
        border-radius: var(--radius-sm);
        cursor: pointer;
        font-weight: 600;
        margin-right: var(--space-3);
        transition: background-color var(--transition-fast);
    }

    .input-control[type="file"]::file-selector-button:hover {
        background-color: var(--accent-blue);
    }

    .field-hint {
        font-size: var(--font-size-xs);
        color: var(--gray-400);
        margin: var(--space-1) 0 0 0;
    }

    /* Form Switch Toggles */
    .checkbox-switch-container {
        display: inline-flex;
        align-items: center;
        gap: var(--space-3);
        cursor: pointer;
        padding: var(--space-2) var(--space-1);
        user-select: none;
    }

    .checkbox-switch-container input[type="checkbox"] {
        appearance: none;
        -webkit-appearance: none;
        width: 40px;
        height: 22px;
        background-color: var(--primary-600);
        border-radius: var(--radius-full);
        position: relative;
        cursor: pointer;
        transition: background-color var(--transition-base);
        outline: none;
        border: 1px solid rgba(255, 255, 255, 0.1);
    }

    .checkbox-switch-container input[type="checkbox"]::before {
        content: "";
        position: absolute;
        width: 16px;
        height: 16px;
        border-radius: var(--radius-full);
        background-color: var(--white);
        top: 2px;
        left: 2px;
        transition: transform var(--transition-base);
        box-shadow: var(--shadow-sm);
    }

    .checkbox-switch-container input[type="checkbox"]:checked {
        background-color: var(--accent-green);
    }

    .checkbox-switch-container input[type="checkbox"]:checked::before {
        transform: translateX(18px);
    }

    .checkbox-switch-container span {
        font-size: var(--font-size-sm);
        font-weight: 600;
        color: var(--gray-200);
    }

    /* Footer Action Button Controls */
    .form-footer-actions {
        display: flex;
        justify-content: flex-end;
        padding-top: var(--space-4);
        border-top: 1px solid rgba(255, 255, 255, 0.06);
    }

    .btn-submit-action {
        background-color: var(--accent-blue);
        color: var(--white);
        font-size: var(--font-size-sm);
        font-weight: 700;
        padding: var(--space-3) var(--space-5);
        border: none;
        border-radius: var(--radius-md);
        cursor: pointer;
        box-shadow: var(--shadow-md);
        transition: all var(--transition-fast);
    }

    .btn-submit-action:hover {
        background-color: #2475c9;
        transform: translateY(-1px);
        box-shadow: var(--shadow-lg);
    }

    .btn-submit-action:active {
        transform: translateY(0);
    }
</style>

<div class="studio-wrapper">
    
    <!-- HEADER STRIP -->
    <div class="page-header-strip">
        <div class="header-title-area">
            <h2>Modifier le Template : <?= htmlspecialchars($template['nom_template']) ?></h2>
            <p>Identifiant de configuration unique : <strong>#<?= $id_template ?></strong></p>
        </div>
        <a href="liste_templates.php" class="btn-back-action">Retour à la liste</a>
    </div>

    <hr class="studio-divider">

    <!-- SYSTEM NOTIFICATIONS -->
    <div class="alert-container">
        <?php if (!empty($errors)): ?>
            <div class="alert-box alert-danger">
                <strong>Erreur(s) détectée(s) :</strong>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert-box alert-success">
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- MAIN EDITING CORE CARD -->
    <div class="main-form-card">
        <form action="" method="POST" enctype="multipart/form-data" class="form-stack">
            
            <!-- SECTION 1 : Généralités -->
            <div class="form-section">
                <h3 class="section-title">1. Informations Générales</h3>
                
                <div class="field-group">
                    <label for="nom_template">Nom du Template <span class="required-mark">*</span></label>
                    <input type="text" id="nom_template" name="nom_template" required
                           value="<?= htmlspecialchars($nom_template) ?>"
                           class="input-control" placeholder="Ex: Ticket d'accès standard">
                </div>

                <div class="field-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="3"
                              class="textarea-control" placeholder="Ajoutez des détails sur l'usage de ce template..."><?= htmlspecialchars($description ?? '') ?></textarea>
                </div>
            </div>

            <!-- SECTION 2 : Image et Styles -->
            <div class="form-section">
                <h3 class="section-title">2. Visuel & Styles de l'interface</h3>
                
                <!-- Asset Showcase Area -->
                <div class="asset-showcase-box">
                    <div class="asset-preview-frame">
                        <img src="../../<?= htmlspecialchars($template['image_path']) ?>" alt="Aperçu actuel">
                    </div>
                    <div class="asset-meta-text">
                        <p class="asset-title">Arrière-plan actuellement configuré</p>
                        <p>Dimensions à l'échelle : <?= $template['canvas_width'] ?> × <?= $template['canvas_height'] ?> pixels</p>
                    </div>
                </div>

                <div class="field-group">
                    <label for="image_template">Remplacer l'image de fond <span class="optional-mark">(Optionnel)</span></label>
                    <div class="file-input-wrapper">
                        <input type="file" id="image_template" name="image_template" accept="image/*" class="input-control">
                    </div>
                    <p class="field-hint">Laissez ce champ vide si vous désirez conserver l'image d'arrière-plan actuelle.</p>
                </div>

                <div class="field-row-grid">
                    <div class="field-group">
                        <label for="overlay_color">Couleur de superposition (Overlay)</label>
                        <input type="text" id="overlay_color" name="overlay_color" value="<?= htmlspecialchars($overlay_color) ?>"
                               class="input-control" placeholder="Ex: rgba(0,0,0,0.5) ou #000000">
                    </div>

                    <div class="field-group">
                        <label for="border_radius">Arrondi des angles (Bords en px)</label>
                        <input type="number" id="border_radius" name="border_radius" value="<?= htmlspecialchars($border_radius) ?>"
                               class="input-control" placeholder="0">
                    </div>
                </div>
            </div>

            <!-- SECTION 3 : Statut Système -->
            <div class="checkbox-switch-container">
                <input type="checkbox" id="is_active" name="is_active" value="1" <?= $is_active ? 'checked' : '' ?>>
                <label for="is_active"><span>Rendre ce template actif immédiatement</span></label>
            </div>

            <!-- ACTIONS FOOTER -->
            <div class="form-footer-actions">
                <button type="submit" name="submit" class="btn-submit-action">
                    Enregistrer les modifications
                </button>
            </div>
        </form>
    </div>
</div>

<?php
$content = ob_get_clean();
include "../layout.php";
?>