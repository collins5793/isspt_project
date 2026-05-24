<?php
session_start();
// Inclusion de votre fichier de connexion centralisé
require_once "../../includes/db.php"; 

// INITIALISATION DES VARIABLES ET MESSAGES
$errors = [];
$success = null;

// TRAITEMENT DU FORMULAIRE AU POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Récupération et nettoyage des données textuelles/numériques
    $nom_template = trim($_POST['nom_template'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $border_radius = filter_var($_POST['border_radius'] ?? 20, FILTER_VALIDATE_INT);
    $overlay_color = trim($_POST['overlay_color'] ?? 'rgba(0,0,0,0.2)');
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    // Initialisation des dimensions automatiques
    $canvas_width = 0;
    $canvas_height = 0;

    // Validation de base
    if (empty($nom_template)) {
        $errors[] = "Le nom du template est obligatoire.";
    }

    // Gestion stricte et sécurisée de l'upload d'image
    $image_path = '';
    if (isset($_FILES['image_template']) && $_FILES['image_template']['error'] === UPLOAD_ERR_OK) {
        
        $fileTmpPath = $_FILES['image_template']['tmp_name'];
        $fileName = $_FILES['image_template']['name'];
        $fileSize = $_FILES['image_template']['size'];
        
        // Vérification de l'extension
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        // Vérification du type MIME réel de l'image
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($fileTmpPath);
        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp'];

        if (!in_array($fileExtension, $allowedExtensions) || !in_array($mimeType, $allowedMimeTypes)) {
            $errors[] = "Format d'image non valide. Autorisés : JPG, PNG, WEBP.";
        }

        // Limite de taille (5 Mo maximum)
        if ($fileSize > 5 * 1024 * 1024) {
            $errors[] = "L'image est trop lourde. Maximum 5 Mo.";
        }

        // Analyse automatique des dimensions de l'image
        if (empty($errors)) {
            $imageSizes = getimagesize($fileTmpPath);
            if ($imageSizes !== false) {
                $canvas_width = $imageSizes[0];  // Largeur détectée
                $canvas_height = $imageSizes[1]; // Hauteur détectée
            } else {
                $errors[] = "Impossible d'analyser les dimensions de l'image.";
            }
        }

        // Si aucune erreur, on déplace le fichier
        if (empty($errors)) {
            // Dossier de destination
            $uploadFolder = '../../uploads/templates/';
            if (!is_dir($uploadFolder)) {
                mkdir($uploadFolder, 0755, true);
            }
            
            // Renommer l'image de façon unique
            $newFileName = bin2hex(random_bytes(8)) . '.' . $fileExtension;
            $dest_path = $uploadFolder . $newFileName;

            if (move_uploaded_file($fileTmpPath, $dest_path)) {
                $image_path = 'uploads/templates/' . $newFileName;
            } else {
                $errors[] = "Une erreur est survenue lors de l'enregistrement de l'image.";
            }
        }
    } else {
        $errors[] = "Veuillez sélectionner une image de fond pour le ticket.";
    }

    // INSERTION DANS LA BASE DE DONNÉES
    if (empty($errors)) {
        try {
            $sql = "INSERT INTO ticket_templates (
                        nom_template, description, image_path, canvas_width, 
                        canvas_height, border_radius, overlay_color, is_active
                    ) VALUES (
                        :nom_template, :description, :image_path, :canvas_width, 
                        :canvas_height, :border_radius, :overlay_color, :is_active
                    )";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':nom_template'  => $nom_template,
                ':description'   => !empty($description) ? $description : null,
                ':image_path'    => $image_path,
                ':canvas_width'  => $canvas_width,
                ':canvas_height' => $canvas_height,
                ':border_radius' => $border_radius,
                ':overlay_color' => $overlay_color,
                ':is_active'     => $is_active
            ]);

            $success = "Le template a été ajouté avec succès ! (Dimensions : {$canvas_width}x{$canvas_height}px)";
            
            // Réinitialiser les champs
            $nom_template = $description = '';
            
        } catch (PDOException $e) {
            $errors[] = "Erreur d'insertion dans la base de données : " . $e->getMessage();
        }
    }
}

// Début de la capture pour le Layout
ob_start();
?>

<!-- Script Tailwind CSS -->
<script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>

<!-- Styles Custom injectés pour mapper parfaitement votre :root -->
<style>
    :root {
        /* Intégration exacte de vos variables */
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

        --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.12);
        --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.1);
        --shadow-lg: 0 10px 25px rgba(0, 0, 0, 0.2);
        --shadow-xl: 0 20px 50px rgba(0, 0, 0, 0.3);

        --radius-sm: 4px;
        --radius-md: 8px;
        --radius-lg: 12px;
        --radius-xl: 20px;
        --radius-full: 9999px;
    }

    /* Override global pour correspondre au design haut de gamme demandé */
    body {
        font-family: var(--font-primary);
        background-color: var(--primary-900);
        color: var(--gray-100);
    }

    /* Style personnalisé pour les inputs et textarea */
    .custom-input {
        background-color: var(--primary-900) !important;
        border: 1px solid var(--primary-600) !important;
        color: var(--white) !important;
        transition: all var(--transition-fast);
    }
    .custom-input:focus {
        border-color: var(--accent-blue) !important;
        box-shadow: 0 0 0 3px rgba(46, 134, 222, 0.25) !important;
        outline: none;
    }
    .custom-input::placeholder {
        color: var(--gray-400);
        opacity: 0.5;
    }

    /* Zone de téléversement Drag & Drop stylisée */
    .file-drop-area {
        border: 2px dashed var(--primary-600);
        transition: all var(--transition-base);
        background-color: rgba(8, 0, 32, 0.4);
    }
    .file-drop-area:hover {
        border-color: var(--accent-blue);
        background-color: rgba(46, 134, 222, 0.05);
    }

    /* Custom Checkbox */
    .custom-checkbox {
        appearance: none;
        background-color: var(--primary-900);
        border: 2px solid var(--primary-600);
        width: 1.25rem;
        height: 1.25rem;
        border-radius: var(--radius-sm);
        display: inline-grid;
        place-content: center;
        cursor: pointer;
        transition: all var(--transition-fast);
    }
    .custom-checkbox:checked {
        background-color: var(--accent-green);
        border-color: var(--accent-green);
    }
    .custom-checkbox:checked::before {
        content: "";
        width: 0.65rem;
        height: 0.65rem;
        transform: scale(1);
        background-color: var(--white);
        clip-path: polygon(14% 44%, 0 65%, 50% 100%, 100% 16%, 80% 0%, 43% 62%);
    }

    /* Bouton Pro */
    .btn-gradient {
        background: linear-gradient(135deg, var(--accent-blue) 0%, #1e3c72 100%);
        box-shadow: 0 4px 15px rgba(46, 134, 222, 0.3);
        transition: all var(--transition-base);
    }
    .btn-gradient:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(46, 134, 222, 0.5);
        opacity: 0.95;
    }
</style>

<!-- CONTENEUR PRINCIPAL DE LA PAGE -->
<div class="w-full mx-auto px-4 sm:px-6 lg:px-8 py-6 max-w-7xl">
    
    <!-- EN-TÊTE NET ET DESIGN -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between pb-6 mb-8 border-b border-solid" style="border-color: var(--primary-600);">
        <div class="mb-4 sm:mb-0">
            <h2 class="text-2xl font-bold tracking-tight text-white flex items-center gap-2">
                <svg class="w-7 h-7 text-[var(--accent-blue)]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                Ajouter un Template de Ticket
            </h2>
            <p class="text-sm mt-1" style="color: var(--gray-400);">Configurez et automatisez le canevas d'arrière-plan de vos futurs tickets d'événements.</p>
        </div>
        <div>
            <a href="liste_templates.php" class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium rounded-lg text-white transition-all border border-solid hover:bg-[var(--primary-600)]" style="background-color: var(--primary-700); border-color: var(--primary-600);">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Retour à la liste
            </a>
        </div>
    </div>

    <!-- RETOURS UTILISATEURS (ALERTES STYLISÉES) -->
    <?php if (!empty($errors)): ?>
        <div class="mb-6 p-4 rounded-xl border border-solid flex gap-3 animate-fade-in" style="background-color: rgba(255, 71, 87, 0.1); border-color: var(--accent-red);">
            <svg class="w-5 h-5 flex-shrink-0 mt-0.5" style="color: var(--accent-red);" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
            </svg>
            <div>
                <span class="font-bold text-white block text-sm mb-1">Erreur de validation</span>
                <ul class="list-disc pl-4 text-xs space-y-1" style="color: var(--gray-200);">
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="mb-6 p-4 rounded-xl border border-solid flex items-center gap-3" style="background-color: rgba(16, 172, 132, 0.1); border-color: var(--accent-green);">
            <svg class="w-5 h-5 flex-shrink-0" style="color: var(--accent-green);" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
            </svg>
            <p class="text-sm font-medium text-white"><?= htmlspecialchars($success) ?></p>
        </div>
    <?php endif; ?>

    <!-- FORMULAIRE PRINCIPAL (LAYOUT EN GRILLE TYPE STUDIO SAAS) -->
    <form action="" method="POST" enctype="multipart/form-data" class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">
        
        <!-- COLONNE DE GAUCHE & CENTRE : CONFIGURATION DES DONNÉES -->
        <div class="lg:col-span-2 space-y-6">
            
            <!-- BLOC 1 : INFORMATIONS -->
            <div class="rounded-xl p-6 border border-solid" style="background-color: var(--primary-800); border-color: var(--primary-600); box-shadow: var(--shadow-lg);">
                <div class="flex items-center gap-2 mb-5 pb-3 border-b border-solid" style="border-color: rgba(255,255,255,0.05);">
                    <span class="flex items-center justify-center w-6 h-6 rounded-full text-xs font-bold text-white bg-[var(--primary-600)]">1</span>
                    <h3 class="text-sm font-semibold tracking-wider uppercase text-white">Identité du Template</h3>
                </div>
                
                <div class="space-y-4">
                    <div>
                        <label for="nom_template" class="block text-xs font-semibold tracking-wide uppercase mb-2" style="color: var(--gray-300);">Nom du Template *</label>
                        <input type="text" id="nom_template" name="nom_template" required
                               value="<?= htmlspecialchars($nom_template ?? '') ?>"
                               class="w-full px-4 py-3 rounded-lg custom-input text-sm" 
                               placeholder="Ex: Gala Triunity Tech 2026 - VIP">
                    </div>

                    <div>
                        <label for="description" class="block text-xs font-semibold tracking-wide uppercase mb-2" style="color: var(--gray-300);">Description / Notes internes</label>
                        <textarea id="description" name="description" rows="4"
                                  class="w-full px-4 py-3 rounded-lg custom-input text-sm resize-none" 
                                  placeholder="Précisez ici les spécificités de ce visuel pour vos collaborateurs..."><?= htmlspecialchars($description ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <!-- BLOC 2 : UPLOAD DESIGN -->
            <div class="rounded-xl p-6 border border-solid" style="background-color: var(--primary-800); border-color: var(--primary-600); box-shadow: var(--shadow-lg);">
                <div class="flex items-center gap-2 mb-5 pb-3 border-b border-solid" style="border-color: rgba(255,255,255,0.05);">
                    <span class="flex items-center justify-center w-6 h-6 rounded-full text-xs font-bold text-white bg-[var(--primary-600)]">2</span>
                    <h3 class="text-sm font-semibold tracking-wider uppercase text-white">Fichier Source Arrière-Plan</h3>
                </div>

                <div>
                    <label class="block text-xs font-semibold tracking-wide uppercase mb-3" style="color: var(--gray-300);">Fichier Graphique du Ticket *</label>
                    <div class="file-drop-area relative rounded-xl p-8 text-center cursor-pointer flex flex-col items-center justify-center">
                        <input type="file" id="image_template" name="image_template" accept="image/*" required
                               class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10"
                               onchange="updateFileName(this)">
                        
                        <div id="upload-icon-container" class="p-4 rounded-full bg-[var(--primary-700)] mb-3 transition-transform">
                            <svg class="w-8 h-8 text-[var(--accent-blue)]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                        </div>
                        
                        <span id="file-placeholder-text" class="text-sm font-medium text-white block">Cliquez ou glissez-déposez l'image ici</span>
                        <p class="text-xs mt-2 max-w-xs mx-auto" style="color: var(--gray-400);">Extensions acceptées : <span class="text-white font-medium">PNG, JPG, WEBP</span>. Poids max : 5 Mo. Les dimensions PHP (Largeur/Hauteur) s'extraient à la volée.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- COLONNE DE DROITE : PROPRIÉTÉS DU COMPOSANT & CONFIGURATION TECHNIQUE -->
        <div class="space-y-6">
            <div class="rounded-xl p-6 border border-solid sticky top-6" style="background-color: var(--primary-800); border-color: var(--primary-600); box-shadow: var(--shadow-lg);">
                <div class="flex items-center gap-2 mb-5 pb-3 border-b border-solid" style="border-color: rgba(255,255,255,0.05);">
                    <span class="flex items-center justify-center w-6 h-6 rounded-full text-xs font-bold text-white bg-[var(--primary-600)]">3</span>
                    <h3 class="text-sm font-semibold tracking-wider uppercase text-white">Inspecteur Graphique</h3>
                </div>

                <div class="space-y-5">
                    <!-- Couleur Overlay -->
                    <div>
                        <label for="overlay_color" class="block text-xs font-semibold tracking-wide uppercase mb-2" style="color: var(--gray-300);">Superposition (Overlay CSS)</label>
                        <div class="relative flex items-center">
                            <input type="text" id="overlay_color" name="overlay_color" 
                                   value="<?= htmlspecialchars($overlay_color ?? 'rgba(0,0,0,0.2)') ?>"
                                   class="w-full pl-4 pr-12 py-2.5 rounded-lg custom-input text-sm font-mono">
                            <div class="absolute right-2 w-7 h-7 rounded border border-solid border-white/10" style="background-color: rgba(0,0,0,0.2);"></div>
                        </div>
                        <p class="text-[11px] mt-1.5" style="color: var(--gray-400);">Teinte de fonçage optionnelle appliquée sur l'arrière-plan.</p>
                    </div>

                    <!-- Border Radius -->
                    <div>
                        <label for="border_radius" class="block text-xs font-semibold tracking-wide uppercase mb-2" style="color: var(--gray-300);">Arrondi des angles (px)</label>
                        <div class="flex items-center gap-2">
                            <input type="number" id="border_radius" name="border_radius" 
                                   value="<?= htmlspecialchars($border_radius ?? 20) ?>"
                                   class="w-full px-4 py-2.5 rounded-lg custom-input text-sm">
                            <span class="text-xs px-3 py-2.5 rounded-lg bg-[var(--primary-900)] border border-solid border-[var(--primary-600)] text-white font-mono">px</span>
                        </div>
                    </div>

                    <!-- Statut d'activation -->
                    <div class="pt-2">
                        <label class="flex items-center gap-3 cursor-pointer group">
                            <input type="checkbox" id="is_active" name="is_active" value="1" checked class="custom-checkbox">
                            <div class="flex flex-col">
                                <span class="text-sm font-medium text-white group-hover:text-[var(--accent-blue)] transition-colors">Activer immédiatement</span>
                                <span class="text-[11px]" style="color: var(--gray-400);">Rendre disponible pour la création immédiate de tickets.</span>
                            </div>
                        </label>
                    </div>

                    <!-- BOUTON D'ACTION PRINCIPAL -->
                    <div class="pt-4 border-t border-solid" style="border-color: rgba(255,255,255,0.05);">
                        <button type="submit" class="w-full btn-gradient text-white text-sm font-semibold py-3.5 px-4 rounded-lg flex items-center justify-center gap-2 cursor-pointer">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Enregistrer le Template
                        </button>
                    </div>
                </div>
            </div>
        </div>

    </form>
</div>

<!-- Script interactif discret pour améliorer le retour visuel de l'upload d'image -->
<script>
    function updateFileName(input) {
        const placeholderText = document.getElementById('file-placeholder-text');
        const iconContainer = document.getElementById('upload-icon-container');
        
        if (input.files && input.files[0]) {
            const fileName = input.files[0].name;
            placeholderText.textContent = fileName;
            placeholderText.style.color = 'var(--accent-green)';
            iconContainer.style.backgroundColor = 'rgba(16, 172, 132, 0.15)';
        } else {
            placeholderText.textContent = "Cliquez ou glissez-déposez l'image ici";
            placeholderText.style.color = 'var(--white)';
            iconContainer.style.backgroundColor = 'var(--primary-700)';
        }
    }
</script>

<?php
$content = ob_get_clean();
include "../layout.php";
?>