<?php
session_start();
require_once '../../includes/db.php';

// Sécurité : Vérifier si l'admin est connecté
if (!isset($_SESSION['admin_id'])) {
    header('Location: connexion_admin.php');
    exit;
}

// Initialisation
$errors = [];
$titre = $description = $id_category = $id_filiere = $id_matiere = $academic_year_id = '';
$niveau = 'autre';
$universite = $pays = '';
$is_public = 1;

// Récupérer les catégories, filières, matières et années
$categories = $pdo->query("SELECT id_category, nom_category FROM epreuves_categories ORDER BY nom_category")->fetchAll(PDO::FETCH_ASSOC);
$filieres = $pdo->query("SELECT id_filiere, nom_filiere FROM filieres ORDER BY nom_filiere")->fetchAll(PDO::FETCH_ASSOC);
$matiere_epreuves = $pdo->query("SELECT id_matiere, nom_matiere FROM matiere_epreuves ORDER BY nom_matiere")->fetchAll(PDO::FETCH_ASSOC);
$annees = $pdo->query("SELECT id, label FROM academic_years ORDER BY start_date DESC")->fetchAll(PDO::FETCH_ASSOC);

$uploadDir = 'uploads/';
$thumbDir  = 'uploads/thumbs/';
if(!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
if(!is_dir($thumbDir)) mkdir($thumbDir, 0777, true);

// Traitement formulaire
if($_SERVER['REQUEST_METHOD']==='POST'){
    $titre = trim($_POST['titre']);
    $description = trim($_POST['description']);
    $id_category = $_POST['id_category'] ?? '';
    $id_filiere = $_POST['id_filiere'] ?? '';
    $id_matiere = $_POST['id_matiere'] ?? '';
    $academic_year_id = $_POST['academic_year_id'] ?? '';
    $niveau = $_POST['niveau'] ?? 'autre';
    $universite = trim($_POST['universite']);
    $pays = trim($_POST['pays']);
    $is_public = isset($_POST['is_public']) ? 1 : 0;

    if(empty($titre)) $errors[]="Le titre est obligatoire.";
    if(empty($id_category)) $errors[]="La catégorie est obligatoire.";
    if(empty($academic_year_id)) $errors[]="L'année universitaire est obligatoire.";
    if(!isset($_FILES['file']) || $_FILES['file']['error']!==UPLOAD_ERR_OK){
        $errors[]="Le fichier PDF est obligatoire.";
    }else{
        $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
        if($ext!=='pdf') $errors[]="Seul le format PDF est autorisé.";
    }

    if(empty($errors)){
    $file_name = time().'_'.basename($_FILES['file']['name']);
    $file_path = $uploadDir.$file_name;
    
    if(move_uploaded_file($_FILES['file']['tmp_name'], $file_path)){
        
        // --- DÉBUT DE LA GÉNÉRATION DE LA MINIATURE ---
        try {
            if (class_exists('Imagick')) {
                // 1. Définir le nom de l'image (même nom que le PDF mais avec l'extension .jpg)
                $thumb_name = pathinfo($file_name, PATHINFO_FILENAME) . '.jpg';
                $thumb_path = $thumbDir . $thumb_name;
                
                // 2. Charger uniquement la PREMIÈRE page du PDF [0]
                // Le paramètre [0] est crucial pour ne pas charger tout le document en mémoire
                $im = new Imagick();
                
                // Optionnel : Définir la résolution (DPI) avant la lecture pour une image plus nette
                $im->setResolution(150, 150); 
                
                $im->readImage($file_path . '[0]');
                
                // 3. Convertir le format et aplatir (au cas où le PDF a de la transparence)
                $im->setImageFormat('jpeg');
                $im = $im->flattenImages(); // Évite les fonds noirs sur les PDF transparents
                
                // 4. Redimensionner proprement (Ex: 300px de large, hauteur proportionnelle 0)
                $im->thumbnailImage(300, 0);
                
                // 5. Sauvegarder sur le disque dur
                $im->writeImage($thumb_path);
                
                // Délester la mémoire
                $im->clear();
                $im->destroy();
            } else {
                // Si Imagick n'est pas installé, on crée un fichier log ou on ignore silencieusement
                error_log("Imagick n'est pas installé sur ce serveur. Impossible de générer la miniature.");
            }
        } catch (Exception $e) {
            // Sécurité : si l'extraction échoue, on log l'erreur mais on ne bloque pas l'insertion en BDD
            error_log("Échec de la génération de la miniature : " . $e->getMessage());
        }
        // --- FIN DE LA GÉNÉRATION ---

        // Votre requête d'insertion SQL reste strictement la même
        $stmt=$pdo->prepare("INSERT INTO epreuves 
            (titre, description, file_path, id_category, id_filiere, id_matiere, academic_year_id, universite, pays, niveau, ajoute_par, is_public)
            VALUES (:titre,:description,:file_path,:id_category,:id_filiere,:id_matiere,:academic_year_id,:universite,:pays,:niveau,:ajoute_par,:is_public)");
        
        $stmt->execute([
            ':titre'=>$titre,
            ':description'=>$description,
            ':file_path'=>$file_name,
            ':id_category'=>$id_category,
            ':id_filiere'=>$id_filiere?:null,
            ':id_matiere'=>$id_matiere?:null,
            ':academic_year_id'=>$academic_year_id,
            ':universite'=>$universite,
            ':pays'=>$pays,
            ':niveau'=>$niveau,
            ':ajoute_par'=>$_SESSION['admin_id'],
            ':is_public'=>$is_public
        ]);
        
        header("Location: index.php?success=1"); exit;
    }else{
        $errors[]="Erreur lors de l'upload du fichier.";
    }
}
}

// Contenu layout
ob_start();
?>
<style>
    /* Conteneur principal */
    .dashboard-container {
        font-family: var(--font-primary);
        max-width: 960px;
        margin: 0 auto;
        padding: var(--space-4) var(--space-3);
    }

    /* En-tête */
    .page-header-block {
        display: flex;
        flex-direction: column;
        gap: var(--space-2);
        margin-bottom: var(--space-5);
    }

    .back-nav-link {
        display: inline-flex;
        align-items: center;
        gap: var(--space-2);
        color: var(--gray-400);
        font-size: var(--font-size-sm);
        text-decoration: none;
        transition: color var(--transition-fast);
        width: fit-content;
    }

    .back-nav-link:hover {
        color: var(--accent-blue);
    }

    .page-title-main {
        color: var(--white);
        font-size: var(--font-size-xl);
        font-weight: 600;
        margin: 0;
        letter-spacing: -0.02em;
    }

    /* Boîte d'erreurs */
    .error-box {
        background: rgba(255, 71, 87, 0.06);
        border: 1px solid rgba(255, 71, 87, 0.25);
        border-radius: var(--radius-lg);
        padding: var(--space-4);
        margin-bottom: var(--space-5);
        box-shadow: var(--shadow-sm);
    }

    .error-list {
        margin: 0;
        padding-left: var(--space-4);
        color: var(--accent-red);
        font-size: var(--font-size-sm);
        display: flex;
        flex-direction: column;
        gap: var(--space-1);
    }

    /* Formulaire Premium */
    .form-premium-card {
        background: var(--primary-800);
        border: var(--sidebar-border);
        border-radius: var(--radius-lg);
        padding: var(--space-5);
        box-shadow: var(--shadow-xl);
        position: relative;
        overflow: hidden;
    }

    .form-premium-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: linear-gradient(90deg, var(--accent-blue) 0%, var(--primary-600) 100%);
    }

    .form-layout {
        display: flex;
        flex-direction: column;
        gap: var(--space-5);
    }

    /* Grille de champs intelligente */
    .form-grid-2 {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: var(--space-4);
    }

    .field-wrapper {
        display: flex;
        flex-direction: column;
        gap: var(--space-2);
    }

    .field-wrapper.full-width {
        grid-column: span 2;
    }

    .field-label {
        color: var(--gray-300);
        font-size: var(--font-size-sm);
        font-weight: 500;
        letter-spacing: 0.01em;
    }

    .field-label span {
        color: var(--accent-red);
        margin-left: var(--space-1);
    }

    /* Inputs, Textareas & Selects */
    .input-custom, 
    .select-custom,
    .textarea-custom {
        width: 100%;
        box-sizing: border-box;
        font-family: var(--font-primary);
        font-size: var(--font-size-md);
        background-color: var(--primary-900);
        border: 1px solid var(--primary-700);
        border-radius: var(--radius-md);
        color: var(--white);
        padding: var(--space-3) var(--space-4);
        transition: border-color var(--transition-fast), box-shadow var(--transition-fast), background-color var(--transition-fast);
    }

    .select-custom {
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%23a4b0be' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right var(--space-4) center;
        padding-right: var(--space-5);
        cursor: pointer;
    }

    .textarea-custom {
        min-height: 100px;
        resize: vertical;
    }

    .input-custom:hover, 
    .select-custom:hover,
    .textarea-custom:hover {
        border-color: var(--primary-600);
    }

    .input-custom:focus, 
    .select-custom:focus,
    .textarea-custom:focus {
        outline: none;
        border-color: var(--accent-blue);
        box-shadow: 0 0 0 4px rgba(46, 134, 222, 0.12);
        background-color: var(--primary-800);
    }

    /* Zone d'Upload PDF Innovante */
    .upload-dropzone-area {
        position: relative;
        border: 2px dashed var(--primary-700);
        border-radius: var(--radius-md);
        padding: var(--space-5) var(--space-4);
        text-align: center;
        background: var(--primary-900);
        cursor: pointer;
        transition: border-color var(--transition-base), background-color var(--transition-base);
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: var(--space-2);
    }

    .upload-dropzone-area:hover {
        border-color: var(--accent-blue);
        background: rgba(46, 134, 222, 0.02);
    }

    .upload-dropzone-area svg {
        color: var(--gray-400);
        transition: color var(--transition-fast), transform var(--transition-fast);
    }

    .upload-dropzone-area:hover svg {
        color: var(--accent-blue);
        transform: translateY(-2px);
    }

    .upload-hidden-input {
        position: absolute;
        inset: 0;
        opacity: 0;
        cursor: pointer;
        width: 100%;
        height: 100%;
    }

    .upload-text-main {
        color: var(--white);
        font-size: var(--font-size-sm);
        font-weight: 500;
        margin: 0;
    }

    .upload-text-sub {
        color: var(--gray-400);
        font-size: var(--font-size-xs);
        margin: 0;
    }

    .file-selected-badge {
        display: none;
        margin-top: var(--space-1);
        font-size: var(--font-size-xs);
        color: var(--accent-green);
        background: rgba(16, 172, 132, 0.1);
        padding: 4px var(--space-3);
        border-radius: var(--radius-full);
        font-weight: 500;
        word-break: break-all;
    }

    /* Switch Option (Public / Privé) */
    .toggle-switch-wrapper {
        display: flex;
        align-items: center;
        gap: var(--space-3);
        background: var(--primary-900);
        border: 1px solid var(--primary-700);
        padding: var(--space-3) var(--space-4);
        border-radius: var(--radius-md);
        cursor: pointer;
        user-select: none;
        width: fit-content;
    }

    .switch-input-native {
        display: none;
    }

    .switch-control-track {
        width: 40px;
        height: 22px;
        background-color: var(--primary-600);
        border-radius: var(--radius-full);
        position: relative;
        transition: background-color var(--transition-fast);
    }

    .switch-control-track::after {
        content: '';
        position: absolute;
        top: 3px;
        left: 3px;
        width: 16px;
        height: 16px;
        background-color: var(--white);
        border-radius: 50%;
        transition: transform var(--transition-fast);
        box-shadow: var(--shadow-sm);
    }

    .switch-input-native:checked + .switch-control-track {
        background-color: var(--accent-green);
    }

    .switch-input-native:checked + .switch-control-track::after {
        transform: translateX(18px);
    }

    .switch-label-text {
        color: var(--gray-200);
        font-size: var(--font-size-sm);
        font-weight: 500;
    }

    /* Actions */
    .form-actions-group {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: var(--space-3);
        margin-top: var(--space-2);
        border-top: 1px solid rgba(255, 255, 255, 0.04);
        padding-top: var(--space-4);
    }

    .btn-action-submit {
        font-family: var(--font-primary);
        background-color: var(--accent-blue);
        color: var(--white);
        font-size: var(--font-size-sm);
        font-weight: 600;
        border: none;
        border-radius: var(--radius-md);
        padding: var(--space-3) var(--space-5);
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: var(--space-2);
        box-shadow: 0 4px 12px rgba(46, 134, 222, 0.15);
        transition: background-color var(--transition-fast), transform var(--transition-fast), box-shadow var(--transition-fast);
    }

    .btn-action-submit:hover {
        background-color: #2475c4;
        box-shadow: 0 6px 16px rgba(46, 134, 222, 0.25);
        transform: translateY(-1px);
    }

    .btn-action-cancel {
        color: var(--gray-400);
        font-size: var(--font-size-sm);
        font-weight: 500;
        text-decoration: none;
        padding: var(--space-3) var(--space-4);
        border-radius: var(--radius-md);
        transition: color var(--transition-fast), background-color var(--transition-fast);
    }

    .btn-action-cancel:hover {
        color: var(--white);
        background-color: rgba(255, 255, 255, 0.03);
    }

    /* --- RESPONSIVITÉ ASSURÉE SANS DÉFAUT --- */
    @media (max-width: 768px) {
        .form-grid-2 {
            grid-template-columns: 1fr;
            gap: var(--space-4);
        }
        
        .field-wrapper.full-width {
            grid-column: span 1;
        }
    }

    @media (max-width: 576px) {
        .dashboard-container {
            padding: var(--space-2) var(--space-2);
        }

        .form-premium-card {
            padding: var(--space-4) var(--space-3);
        }

        .form-actions-group {
            flex-direction: column-reverse;
            align-items: stretch;
            gap: var(--space-2);
        }

        .btn-action-submit,
        .btn-action-cancel {
            justify-content: center;
            text-align: center;
            width: 100%;
        }

        .toggle-switch-wrapper {
            width: 100%;
            box-sizing: border-box;
        }
    }
</style>


<div class="dashboard-container">
    
    <!-- En-tête -->
    <div class="page-header-block">
        <a href="index.php" class="back-nav-link">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
            Retour aux épreuves
        </a>
        <h2 class="page-title-main">Ajouter une nouvelle épreuve</h2>
    </div>

    <!-- Section Erreurs -->
    <?php if(!empty($errors)): ?>
        <div class="error-box">
            <ul class="error-list">
                <?php foreach($errors as $e): ?>
                    <li><?= htmlspecialchars($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Formulaire Premium Card -->
    <div class="form-premium-card">
        <form method="POST" enctype="multipart/form-data" class="form-layout">
            
            <div class="form-grid-2">
                
                <!-- Titre complet -->
                <div class="field-wrapper full-width">
                    <label class="field-label">Titre de l'épreuve <span>*</span></label>
                    <input type="text" name="titre" class="input-custom" value="<?= htmlspecialchars($titre) ?>" placeholder="Ex: Examen de Fin de Semestre - Algorithmique" required autocomplete="off">
                </div>

                <!-- Description complète -->
                <div class="field-wrapper full-width">
                    <label class="field-label">Description / Consignes <span style="color: var(--gray-400); font-weight: 400;">(facultatif)</span></label>
                    <textarea name="description" class="textarea-custom" placeholder="Détails supplémentaires concernant le sujet ou le barème de notation..."><?= htmlspecialchars($description) ?></textarea>
                </div>

                <!-- Catégorie -->
                <div class="field-wrapper">
                    <label class="field-label">Catégorie <span>*</span></label>
                    <select name="id_category" class="select-custom" required>
                        <option value="">-- Choisir une catégorie --</option>
                        <?php foreach($categories as $c): ?>
                            <option value="<?= $c['id_category'] ?>" <?= $id_category==$c['id_category']?"selected":"" ?>><?= htmlspecialchars($c['nom_category']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Année Universitaire -->
                <div class="field-wrapper">
                    <label class="field-label">Année universitaire <span>*</span></label>
                    <select name="academic_year_id" class="select-custom" required>
                        <option value="">-- Choisir l'année --</option>
                        <?php foreach($annees as $a): ?>
                            <option value="<?= $a['id'] ?>" <?= $academic_year_id==$a['id']?"selected":"" ?>><?= htmlspecialchars($a['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Filière -->
                <div class="field-wrapper">
                    <label class="field-label">Filière <span style="color: var(--gray-400); font-weight: 400;">(facultatif)</span></label>
                    <select name="id_filiere" class="select-custom">
                        <option value="">-- Non spécifiée --</option>
                        <?php foreach($filieres as $f): ?>
                            <option value="<?= $f['id_filiere'] ?>" <?= $id_filiere==$f['id_filiere']?"selected":"" ?>><?= htmlspecialchars($f['nom_filiere']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Matière -->
                <div class="field-wrapper">
                    <label class="field-label">Matière <span style="color: var(--gray-400); font-weight: 400;">(facultatif)</span></label>
                    <select name="id_matiere" class="select-custom">
                        <option value="">-- Non spécifiée --</option>
                        <?php foreach($matiere_epreuves as $m): ?>
                            <option value="<?= $m['id_matiere'] ?>" <?= $id_matiere==$m['id_matiere']?"selected":"" ?>><?= htmlspecialchars($m['nom_matiere']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Niveau -->
                <div class="field-wrapper">
                    <label class="field-label">Niveau d'études</label>
                    <select name="niveau" class="select-custom">
                        <option value="1ère année" <?= $niveau=="1ère année"?"selected":"" ?>>1ère année (Licence 1)</option>
                        <option value="2ème année" <?= $niveau=="2ème année"?"selected":"" ?>>2ème année (Licence 2)</option>
                        <option value="3ème année" <?= $niveau=="3ème année"?"selected":"" ?>>3ème année (Licence 3)</option>
                        <option value="autre" <?= $niveau=="autre"?"selected":"" ?>>Autre niveau</option>
                    </select>
                </div>

                <!-- Université -->
                <div class="field-wrapper">
                    <label class="field-label">Université / Centre</label>
                    <input type="text" name="universite" class="input-custom" value="<?= htmlspecialchars($universite) ?>" placeholder="Ex: UAC, ENEAM...">
                </div>

                <!-- Pays -->
                <div class="field-wrapper">
                    <label class="field-label">Pays</label>
                    <input type="text" name="pays" class="input-custom" value="<?= htmlspecialchars($pays) ?>" placeholder="Ex: Bénin, Togo...">
                </div>

                <!-- Upload de fichier interactif -->
                <div class="field-wrapper">
                    <label class="field-label">Document joint (PDF) <span>*</span></label>
                    <div class="upload-dropzone-area">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
                        <p class="upload-text-main">Parcourir ou glisser le fichier</p>
                        <p class="upload-text-sub">Format PDF uniquement</p>
                        <div id="fileBadge" class="file-selected-badge"></div>
                        <input type="file" name="file" accept="application/pdf" class="upload-hidden-input" id="pdfInput" required>
                    </div>
                </div>

            </div>

            <!-- Visibilité Switch -->
            <label class="toggle-switch-wrapper">
                <input type="checkbox" name="is_public" class="switch-input-native" <?= $is_public?"checked":"" ?>>
                <div class="switch-control-track"></div>
                <span class="switch-label-text">Rendre cette épreuve publique et accessible</span>
            </label>

            <!-- Actions de bas de page -->
            <div class="form-actions-group">
                <a href="index.php" class="btn-action-cancel">Annuler</a>
                <button type="submit" class="btn-action-submit">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                    Créer l'épreuve
                </button>
            </div>

        </form>
    </div>
</div>

<script>
    // Petit script d'UX pour afficher dynamiquement le nom du fichier PDF choisi
    document.getElementById('pdfInput').addEventListener('change', function(e) {
        const badge = document.getElementById('fileBadge');
        if(this.files && this.files.length > 0) {
            badge.textContent = "Sélectionné : " + this.files[0].name;
            badge.style.display = "inline-block";
        } else {
            badge.style.display = "none";
        }
    });
</script>

<?php
$content = ob_get_clean();
include '../layout.php';
?>