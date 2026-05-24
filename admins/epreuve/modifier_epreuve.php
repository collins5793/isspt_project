<?php
session_start();
require_once '../../includes/db.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php"); 
    exit;
}

$id_epreuve = (int)$_GET['id'];
$errors = [];

$stmt = $pdo->prepare("SELECT * FROM epreuves WHERE id_epreuve = :id");
$stmt->execute([':id' => $id_epreuve]);
$epreuve = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$epreuve) {
    die("Épreuve introuvable.");
}

$titre = $epreuve['titre'];
$description = $epreuve['description'];
$id_category = $epreuve['id_category'];
$id_filiere = $epreuve['id_filiere'];
$id_matiere = $epreuve['id_matiere'];
$academic_year_id = $epreuve['academic_year_id'];
$niveau = $epreuve['niveau'];
$universite = $epreuve['universite'];
$pays = $epreuve['pays'];
$is_public = $epreuve['is_public'];

$categories = $pdo->query("SELECT id_category, nom_category FROM epreuves_categories ORDER BY nom_category")->fetchAll(PDO::FETCH_ASSOC);
$filieres = $pdo->query("SELECT id_filiere, nom_filiere FROM filieres ORDER BY nom_filiere")->fetchAll(PDO::FETCH_ASSOC);
$matieres = $pdo->query("SELECT id_matiere, nom_matiere FROM matiere_epreuves ORDER BY nom_matiere")->fetchAll(PDO::FETCH_ASSOC);
$annees = $pdo->query("SELECT id, label FROM academic_years ORDER BY start_date DESC")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titre = trim($_POST['titre']);
    $description = trim($_POST['description']);
    $id_category = $_POST['id_category'] ?? '';
    $id_filiere = $_POST['id_filiere'] ?? null;
    $id_matiere = $_POST['id_matiere'] ?? null;
    $academic_year_id = $_POST['academic_year_id'] ?? '';
    $niveau = $_POST['niveau'] ?? 'autre';
    $universite = trim($_POST['universite']);
    $pays = trim($_POST['pays']);
    $is_public = isset($_POST['is_public']) ? 1 : 0;

    if (empty($titre)) $errors[] = "Le titre est obligatoire.";
    if (empty($id_category)) $errors[] = "La catégorie est obligatoire.";
    if (empty($academic_year_id)) $errors[] = "L'année universitaire est obligatoire.";

    $file_name = $epreuve['file_path'];
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
        if ($ext !== 'pdf') {
            $errors[] = "Seul le format PDF est autorisé.";
        } else {
            $upload_dir = 'uploads/';
            $file_name = time().'_'.basename($_FILES['file']['name']);
            $file_path = $upload_dir.$file_name;
            if (!move_uploaded_file($_FILES['file']['tmp_name'], $file_path)) {
                $errors[] = "Erreur lors de l'upload du fichier.";
            }
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("UPDATE epreuves SET titre=:titre, description=:description, file_path=:file_path,
            id_category=:id_category, id_filiere=:id_filiere, id_matiere=:id_matiere,
            academic_year_id=:academic_year_id, universite=:universite, pays=:pays, niveau=:niveau, is_public=:is_public
            WHERE id_epreuve=:id");
        $stmt->execute([
            ':titre' => $titre, ':description' => $description, ':file_path' => $file_name,
            ':id_category' => $id_category, ':id_filiere' => $id_filiere ?: null, ':id_matiere' => $id_matiere ?: null,
            ':academic_year_id' => $academic_year_id, ':universite' => $universite, ':pays' => $pays,
            ':niveau' => $niveau, ':is_public' => $is_public, ':id' => $id_epreuve
        ]);
        header("Location: index.php?updated=1"); 
        exit;
    }
}

ob_start();
?>

<style>
    /* Reset & Wrappers principaux */
    .premium-page-container {
        font-family: var(--font-primary);
        color: var(--white);
        max-width: 1100px;
        margin: 0 auto;
        padding: var(--space-4) var(--space-3);
    }

    /* En-tête de l'interface */
    .premium-hero-header {
        background: linear-gradient(135deg, var(--primary-800) 0%, var(--primary-700) 100%);
        border: var(--sidebar-border);
        border-radius: var(--radius-lg);
        padding: var(--space-5) var(--space-4);
        box-shadow: var(--shadow-md);
        display: flex;
        align-items: center;
        gap: var(--space-4);
        margin-bottom: var(--space-5);
        position: relative;
    }

    .premium-hero-header::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 4px;
        height: 100%;
        background: var(--accent-blue);
    }

    .premium-header-icon {
        background-color: rgba(46, 134, 222, 0.1);
        border: 1px solid rgba(46, 134, 222, 0.2);
        padding: var(--space-3);
        border-radius: var(--radius-md);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--accent-blue);
    }

    .premium-header-text h1 {
        font-size: calc(var(--font-size-xl) * 1.15);
        font-weight: 700;
        margin: 0 0 var(--space-1) 0;
        letter-spacing: -0.01em;
    }

    .premium-header-text p {
        color: var(--gray-400);
        font-size: var(--font-size-sm);
        margin: 0;
    }

    /* Architecture globale du formulaire */
    .premium-form-card {
        background-color: var(--primary-800);
        border: var(--sidebar-border);
        border-radius: var(--radius-lg);
        padding: var(--space-5);
        box-shadow: var(--shadow-xl);
    }

    /* Grille adaptative */
    .form-grid-layout {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: var(--space-4);
    }

    .grid-col-full {
        grid-column: span 2;
    }

    .form-structural-group {
        margin-bottom: var(--space-2);
    }

    .custom-form-label {
        display: block;
        font-size: var(--font-size-sm);
        font-weight: 600;
        color: var(--gray-200);
        margin-bottom: var(--space-2);
        letter-spacing: 0.01em;
    }

    .custom-form-label span.required-star {
        color: var(--accent-red);
        margin-left: var(--space-1);
    }

    /* Éléments de saisie et listes déroulantes */
    .premium-input-field, .premium-select-field, .premium-textarea-field {
        width: 100%;
        box-sizing: border-box;
        font-family: var(--font-primary);
        font-size: var(--font-size-md);
        background-color: var(--primary-900);
        border: 1px solid var(--primary-600);
        border-radius: var(--radius-md);
        color: var(--white);
        padding: 0 var(--space-3);
        transition: border-color var(--transition-fast), box-shadow var(--transition-fast);
    }

    .premium-input-field, .premium-select-field {
        height: 46px;
    }

    .premium-select-field {
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='24' height='24' viewBox='0 0 24 24' fill='none' stroke='%23a4b0be' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right var(--space-3) center;
        background-size: 16px;
        padding-right: var(--space-5);
    }

    .premium-textarea-field {
        padding: var(--space-3);
        min-height: 100px;
        resize: vertical;
    }

    .premium-input-field:focus, .premium-select-field:focus, .premium-textarea-field:focus {
        outline: none;
        border-color: var(--accent-blue);
        box-shadow: 0 0 0 3px rgba(46, 134, 222, 0.15);
    }

    /* Zone Custom d'upload de fichier PDF */
    .file-upload-wrapper {
        position: relative;
        border: 2px dashed var(--primary-600);
        border-radius: var(--radius-md);
        background-color: var(--primary-900);
        padding: var(--space-4);
        text-align: center;
        transition: border-color var(--transition-base), background-color var(--transition-base);
    }

    .file-upload-wrapper:hover {
        border-color: var(--accent-blue);
        background-color: rgba(46, 134, 222, 0.02);
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

    .file-upload-content {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: var(--space-2);
        color: var(--gray-400);
        font-size: var(--font-size-sm);
    }

    .file-upload-icon {
        color: var(--accent-blue);
        margin-bottom: var(--space-1);
    }

    .current-file-badge {
        display: inline-flex;
        align-items: center;
        gap: var(--space-2);
        background-color: rgba(255, 255, 255, 0.04);
        border: 1px solid var(--primary-600);
        padding: var(--space-2) var(--space-3);
        border-radius: var(--radius-sm);
        margin-top: var(--space-3);
        max-width: 100%;
        box-sizing: border-box;
    }

    .current-file-badge span {
        color: var(--gray-300);
        font-size: var(--font-size-xs);
        text-overflow: ellipsis;
        overflow: hidden;
        white-space: nowrap;
    }

    /* Interrupteur public/privé (Switch UI) */
    .custom-switch-container {
        display: inline-flex;
        align-items: center;
        gap: var(--space-3);
        cursor: pointer;
        user-select: none;
        margin-top: var(--space-3);
    }

    .custom-switch-input {
        display: none;
    }

    .custom-switch-track {
        width: 44px;
        height: 24px;
        background-color: var(--primary-600);
        border-radius: var(--radius-full);
        position: relative;
        transition: background-color var(--transition-base);
    }

    .custom-switch-thumb {
        width: 18px;
        height: 18px;
        background-color: var(--white);
        border-radius: var(--radius-full);
        position: absolute;
        top: 3px;
        left: 3px;
        transition: transform var(--transition-base);
        box-shadow: var(--shadow-sm);
    }

    .custom-switch-input:checked + .custom-switch-track {
        background-color: var(--accent-green);
    }

    .custom-switch-input:checked + .custom-switch-track .custom-switch-thumb {
        transform: translateX(20px);
    }

    .custom-switch-label {
        font-size: var(--font-size-sm);
        font-weight: 600;
        color: var(--gray-200);
    }

    /* Boutons de contrôle d'actions */
    .form-action-cluster {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: var(--space-3);
        margin-top: var(--space-5);
        padding-top: var(--space-4);
        border-top: 1px solid rgba(255, 255, 255, 0.05);
    }

    .btn-action-base {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: var(--space-2);
        font-family: var(--font-primary);
        font-size: var(--font-size-sm);
        font-weight: 600;
        border-radius: var(--radius-md);
        text-decoration: none;
        padding: 0 var(--space-5);
        height: 46px;
        transition: all var(--transition-fast);
        cursor: pointer;
        border: none;
        white-space: nowrap;
    }

    .btn-submit-save {
        background-color: var(--accent-blue);
        color: var(--white);
        box-shadow: 0 4px 12px rgba(46, 134, 222, 0.2);
    }

    .btn-submit-save:hover {
        background-color: #2475c4;
        transform: translateY(-1px);
    }

    .btn-cancel-return {
        background-color: transparent;
        color: var(--gray-400);
        border: 1px solid var(--primary-600);
    }

    .btn-cancel-return:hover {
        background-color: rgba(255, 255, 255, 0.03);
        color: var(--white);
        border-color: var(--gray-300);
    }

    /* Alertes d'erreurs */
    .premium-alert-box {
        background-color: rgba(255, 71, 87, 0.08);
        border: 1px solid rgba(255, 71, 87, 0.2);
        border-radius: var(--radius-md);
        padding: var(--space-4);
        margin-bottom: var(--space-4);
        display: flex;
        gap: var(--space-3);
        align-items: flex-start;
    }

    .premium-alert-box svg {
        color: var(--accent-red);
        flex-shrink: 0;
        margin-top: 2px;
    }

    .premium-alert-box ul {
        margin: 0;
        padding-left: var(--space-4);
        color: var(--white);
        font-size: var(--font-size-sm);
        line-height: 1.6;
    }

    /* Responsivité totale sans défauts */
    @media (max-width: 768px) {
        .form-grid-layout {
            grid-template-columns: 1fr;
            gap: var(--space-4);
        }
        
        .grid-col-full {
            grid-column: span 1;
        }
    }

    @media (max-width: 576px) {
        .premium-hero-header {
            flex-direction: column;
            align-items: flex-start;
            gap: var(--space-3);
            padding: var(--space-4);
        }

        .premium-form-card {
            padding: var(--space-4);
        }

        .form-action-cluster {
            flex-direction: column-reverse;
            align-items: stretch;
        }

        .form-action-cluster .btn-action-base {
            width: 100%;
        }
    }
</style>

<div class="premium-page-container">

    <!-- En-tête contextuel de l'interface -->
    <header class="premium-hero-header">
        <div class="premium-header-icon">
            <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                <path d="M18.5 2.5a2.121 2.121 0 1 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
            </svg>
        </div>
        <div class="premium-header-text">
            <h1>Modifier l'épreuve</h1>
            <p>Mise à jour des métadonnées et fichiers de l'épreuve : <span style="color: var(--accent-blue); font-weight: 600;"><?= htmlspecialchars($titre) ?></span></p>
        </div>
    </header>

    <!-- Affichage sécurisé des erreurs de traitement -->
    <?php if(!empty($errors)): ?>
        <div class="premium-alert-box">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="8" x2="12" y2="12"></line>
                <line x1="12" y1="16" x2="12.01" y2="16"></line>
            </svg>
            <ul>
                <?php foreach($errors as $err): ?>
                    <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Corps du Formulaire Premium -->
    <div class="premium-form-card">
        <form method="POST" enctype="multipart/form-data">
            
            <div class="form-grid-layout">
                
                <!-- Titre de l'épreuve (Pleine Largeur) -->
                <div class="form-structural-group grid-col-full">
                    <label class="custom-form-label" for="titre">
                        Titre de l'épreuve <span class="required-star">*</span>
                    </label>
                    <input type="text" id="titre" name="titre" class="premium-input-field" value="<?= htmlspecialchars($titre) ?>" required autocomplete="off">
                </div>

                <!-- Description (Pleine Largeur) -->
                <div class="form-structural-group grid-col-full">
                    <label class="custom-form-label" for="description">
                        Description / Thématiques abordées <span style="color: var(--gray-400); font-weight: normal;">(facultatif)</span>
                    </label>
                    <textarea id="description" name="description" class="premium-textarea-field" placeholder="Détaillez le contenu ou spécificités de l'épreuve..."><?= htmlspecialchars($description) ?></textarea>
                </div>

                <!-- Catégorie -->
                <div class="form-structural-group">
                    <label class="custom-form-label" for="id_category">
                        Catégorie <span class="required-star">*</span>
                    </label>
                    <select id="id_category" name="id_category" class="premium-select-field" required>
                        <option value="">-- Choisir une catégorie --</option>
                        <?php foreach($categories as $cat): ?>
                            <option value="<?= $cat['id_category'] ?>" <?= $id_category == $cat['id_category'] ? "selected" : "" ?>>
                                <?= htmlspecialchars($cat['nom_category']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Filière -->
                <div class="form-structural-group">
                    <label class="custom-form-label" for="id_filiere">Filière d'étude</label>
                    <select id="id_filiere" name="id_filiere" class="premium-select-field">
                        <option value="">-- Facultatif / Toutes --</option>
                        <?php foreach($filieres as $fil): ?>
                            <option value="<?= $fil['id_filiere'] ?>" <?= $id_filiere == $fil['id_filiere'] ? "selected" : "" ?>>
                                <?= htmlspecialchars($fil['nom_filiere']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Matière -->
                <div class="form-structural-group">
                    <label class="custom-form-label" for="id_matiere">Matière / Module</label>
                    <select id="id_matiere" name="id_matiere" class="premium-select-field">
                        <option value="">-- Facultatif --</option>
                        <?php foreach($matieres as $mat): ?>
                            <option value="<?= $mat['id_matiere'] ?>" <?= $id_matiere == $mat['id_matiere'] ? "selected" : "" ?>>
                                <?= htmlspecialchars($mat['nom_matiere']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Année universitaire -->
                <div class="form-structural-group">
                    <label class="custom-form-label" for="academic_year_id">
                        Année universitaire <span class="required-star">*</span>
                    </label>
                    <select id="academic_year_id" name="academic_year_id" class="premium-select-field" required>
                        <option value="">-- Sélectionner l'année --</option>
                        <?php foreach($annees as $annee): ?>
                            <option value="<?= $annee['id'] ?>" <?= $academic_year_id == $annee['id'] ? "selected" : "" ?>>
                                <?= htmlspecialchars($annee['label']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Niveau d'étude -->
                <div class="form-structural-group">
                    <label class="custom-form-label" for="niveau">Niveau d'étude</label>
                    <select id="niveau" name="niveau" class="premium-select-field">
                        <option value="1ère année" <?= $niveau == "1ère année" ? "selected" : "" ?>>1ère année</option>
                        <option value="2ème année" <?= $niveau == "2ème année" ? "selected" : "" ?>>2ème année</option>
                        <option value="3ème année" <?= $niveau == "3ème année" ? "selected" : "" ?>>3ème année</option>
                        <option value="autre" <?= $niveau == "autre" ? "selected" : "" ?>>Autre (Master/Doctorat)</option>
                    </select>
                </div>

                <!-- Pays -->
                <div class="form-structural-group">
                    <label class="custom-form-label" for="pays">Pays d'origine</label>
                    <input type="text" id="pays" name="pays" class="premium-input-field" value="<?= htmlspecialchars($pays) ?>" placeholder="Ex: Bénin">
                </div>

                <!-- Université (Pleine Largeur sur mobile) -->
                <div class="form-structural-group grid-col-full">
                    <label class="custom-form-label" for="universite">Université / Établissement de rattachement</label>
                    <input type="text" id="universite" name="universite" class="premium-input-field" value="<?= htmlspecialchars($universite) ?>" placeholder="Ex: Université d'Abomey-Calavi (UAC)">
                </div>

                <!-- Zone d'importation de fichier PDF (Pleine Largeur) -->
                <div class="form-structural-group grid-col-full" style="margin-top: var(--space-2);">
                    <label class="custom-form-label">Document joint de l'épreuve</label>
                    <div class="file-upload-wrapper">
                        <input type="file" name="file" id="file_input" accept="application/pdf">
                        <div class="file-upload-content">
                            <div class="file-upload-icon">
                                <svg width="28" height="28" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                    <polyline points="17 8 12 3 7 8"></polyline>
                                    <line x1="12" y1="3" x2="12" y2="15"></line>
                                </svg>
                            </div>
                            <span style="font-weight: 600; color: var(--white);" id="file_status_text">Glissez ou cliquez pour téléverser un nouveau PDF</span>
                            <span>Format accepté : PDF uniquement (Max 10Mo)</span>
                        </div>
                    </div>
                    
                    <?php if(!empty($epreuve['file_path'])): ?>
                        <div class="current-file-badge">
                            <svg width="14" height="14" fill="none" stroke="var(--accent-blue)" stroke-width="2" viewBox="0 0 24 24">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                <polyline points="14 2 14 8 20 8"></polyline>
                            </svg>
                            <span>Fichier actuellement en ligne : <strong><?= htmlspecialchars($epreuve['file_path']) ?></strong></span>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Paramètre de Visibilité (Pleine Largeur) -->
                <div class="form-structural-group grid-col-full">
                    <label class="custom-switch-container">
                        <input type="checkbox" name="is_public" value="1" class="custom-switch-input" <?= $is_public ? "checked" : "" ?>>
                        <div class="custom-switch-track">
                            <div class="custom-switch-thumb"></div>
                        </div>
                        <span class="custom-switch-label">Rendre cette épreuve publique et accessible à la communauté</span>
                    </label>
                </div>

            </div>

            <!-- Boutons de validation et contrôles d'exécution -->
            <div class="form-action-cluster">
                <a href="index.php" class="btn-action-base btn-cancel-return">
                    Annuler
                </a>
                <button type="submit" class="btn-action-base btn-submit-save">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                        <polyline points="17 21 17 13 7 13 7 21"></polyline>
                        <polyline points="7 3 7 8 15 8"></polyline>
                    </svg>
                    Mettre à jour l'épreuve
                </button>
            </div>

        </form>
    </div>

</div>

<script>
    // Petit ajout UI pour rendre dynamique l'affichage du nom du fichier sélectionné
    document.getElementById('file_input').addEventListener('change', function(e) {
        const fileName = e.target.files[0] ? e.target.files[0].name : "Glissez ou cliquez pour téléverser un nouveau PDF";
        const statusText = document.getElementById('file_status_text');
        statusText.innerText = fileName;
        if(e.target.files[0]) {
            statusText.style.color = "var(--accent-blue)";
        }
    });
</script>

<?php
$content = ob_get_clean();
include '../layout.php';
?>