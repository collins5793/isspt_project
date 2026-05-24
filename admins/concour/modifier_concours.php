<?php
session_start();
require_once "../../includes/db.php";

// Récupération de l'ID du concours à modifier
$id_concours = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id_concours <= 0) {
    header("Location: concours.php");
    exit;
}

// Simulation ou récupération de l'ID de l'admin connecté
$adminId = $_SESSION['user_id'] ?? 1; 

// 1. Récupération des données du concours existant
$stmtConcours = $pdo->prepare("SELECT * FROM concours WHERE id_concours = ?");
$stmtConcours->execute([$id_concours]);
$concours = $stmtConcours->fetch(PDO::FETCH_ASSOC);

if (!$concours) {
    die("Concours introuvable.");
}

// 2. Récupération des phases associées à ce concours
$stmtPhases = $pdo->prepare("SELECT * FROM concours_phases WHERE id_concours = ? ORDER BY ordre_phase ASC");
$stmtPhases->execute([$id_concours]);
$phases_existantes = $stmtPhases->fetchAll(PDO::FETCH_ASSOC);

// Récupération des années académiques pour le sélecteur
$yearsStmt = $pdo->query("SELECT id, label FROM academic_years ORDER BY id DESC");
$academic_years = $yearsStmt->fetchAll(PDO::FETCH_ASSOC);

$errors = [];
$success = false;

// Traitement du formulaire de modification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Extraction et nettoyage des données du concours
    $nom_concours = trim($_POST['nom_concours'] ?? '');
    $type_concours = $_POST['type_concours'] ?? 'autre';
    $description = trim($_POST['description'] ?? null);
    $reglement = trim($_POST['reglement'] ?? null);
    $conditions_participation = trim($_POST['conditions_participation'] ?? null);
    $date_debut = $_POST['date_debut'] ?? null;
    $date_fin = $_POST['date_fin'] ?? null;
    $date_limite_inscription = $_POST['date_limite_inscription'] ?? null;
    $lieu = trim($_POST['lieu'] ?? null);
    $organisateur = trim($_POST['organisateur'] ?? null);
    $max_participants = !empty($_POST['max_participants']) ? intval($_POST['max_participants']) : null;
    $min_participants = !empty($_POST['min_participants']) ? intval($_POST['min_participants']) : 1;
    $inscription_obligatoire = isset($_POST['inscription_obligatoire']) ? 1 : 0;
    $participation_gratuite = isset($_POST['participation_gratuite']) ? 1 : 0;
    $frais_participation = !$participation_gratuite && !empty($_POST['frais_participation']) ? floatval($_POST['frais_participation']) : 0.00;
    $mode_selection = $_POST['mode_selection'] ?? 'manuel';
    $nombre_qualifies = !empty($_POST['nombre_qualifies']) ? intval($_POST['nombre_qualifies']) : 0;
    $nombre_finalistes = !empty($_POST['nombre_finalistes']) ? intval($_POST['nombre_finalistes']) : 0;
    $nombre_gagnants = !empty($_POST['nombre_gagnants']) ? intval($_POST['nombre_gagnants']) : 1;
    $statut = $_POST['statut'] ?? 'brouillon';
    $is_public = isset($_POST['is_public']) ? 1 : 0;
    $academic_year_id = !empty($_POST['academic_year_id']) ? intval($_POST['academic_year_id']) : null;

    // Génération automatique du slug
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $nom_concours), '-'));

    // Validation minimale
    if (empty($nom_concours)) $errors[] = "Le nom du concours est obligatoire.";
    if (empty($date_debut)) $errors[] = "La date de début est obligatoire.";

    // Données des phases soumises
    $phases_nom = $_POST['phase_nom'] ?? [];
    $phases_type = $_POST['phase_type'] ?? [];
    $phases_qualifies = $_POST['phase_qualifies'] ?? [];
    $phases_debut = $_POST['phase_debut'] ?? [];
    $phases_fin = $_POST['phase_fin'] ?? [];
    $phases_desc = $_POST['phase_desc'] ?? [];

    if (empty($phases_nom)) {
        $errors[] = "Vous devez laisser ou ajouter au moins une étape (phase) pour ce concours.";
    }

    // Si aucune erreur, on procède à la mise à jour
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Gestion de la nouvelle image affiche (si fournie)
            $image_affiche = $concours['image_affiche']; // Par défaut, on garde l'ancienne
            if (isset($_FILES['image_affiche']) && $_FILES['image_affiche']['error'] === UPLOAD_ERR_OK) {
                $ext = pathinfo($_FILES['image_affiche']['name'], PATHINFO_EXTENSION);
                $filename = 'concours_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['image_affiche']['tmp_name'], '../uploads/' . $filename)) {
                    // Optionnel : Vous pouvez supprimer l'ancienne image ici si nécessaire
                    $image_affiche = $filename;
                }
            }

            // Mise à jour du concours
            $sqlUpdateConcours = "UPDATE concours SET 
                nom_concours = ?, slug = ?, type_concours = ?, description = ?, reglement = ?, conditions_participation = ?,
                date_debut = ?, date_fin = ?, date_limite_inscription = ?, lieu = ?, organisateur = ?, max_participants = ?,
                min_participants = ?, inscription_obligatoire = ?, participation_gratuite = ?, frais_participation = ?,
                mode_selection = ?, nombre_qualifies = ?, nombre_finalistes = ?, nombre_gagnants = ?, image_affiche = ?,
                statut = ?, is_public = ?, academic_year_id = ?
                WHERE id_concours = ?";

            $stmt = $pdo->prepare($sqlUpdateConcours);
            $stmt->execute([
                $nom_concours, $slug, $type_concours, $description, $reglement, $conditions_participation,
                $date_debut, $date_fin, $date_limite_inscription, $lieu, $organisateur, $max_participants,
                $min_participants, $inscription_obligatoire, $participation_gratuite, $frais_participation,
                $mode_selection, $nombre_qualifies, $nombre_finalistes, $nombre_gagnants, $image_affiche,
                $statut, $is_public, $academic_year_id, $id_concours
            ]);

            // Nettoyage des anciennes phases pour réinsertion propre (stratégie la plus simple et robuste)
            $pdo->prepare("DELETE FROM concours_phases WHERE id_concours = ?")->execute([$id_concours]);

            // Insertion des nouvelles données de phases
            $sqlPhase = "INSERT INTO concours_phases (
                id_concours, nom_phase, type_phase, ordre_phase, nombre_qualifies, date_debut, date_fin, description
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmtPhase = $pdo->prepare($sqlPhase);

            for ($i = 0; $i < count($phases_nom); $i++) {
                if (!empty(trim($phases_nom[$i]))) {
                    $ordre = $i + 1;
                    $stmtPhase->execute([
                        $id_concours,
                        trim($phases_nom[$i]),
                        $phases_type[$i] ?? 'autre',
                        $ordre,
                        !empty($phases_qualifies[$i]) ? intval($phases_qualifies[$i]) : 0,
                        !empty($phases_debut[$i]) ? $phases_debut[$i] : null,
                        !empty($phases_fin[$i]) ? $phases_fin[$i] : null,
                        trim($phases_desc[$i] ?? null)
                    ]);
                }
            }

            $pdo->commit();
            $success = true;
            
            // Rafraîchir les données locales pour l'affichage post-succès
            header("Refresh: 2; url=concours.php");

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Erreur lors de la modification : " . $e->getMessage();
        }
    }
}

ob_start();
?>

<style>
    /* ==========================================================================
   DESIGN COMPLET ET RESPONSIVE - BACKOFFICE CONCOURS PREMIUM
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

    /* Sidebar Structure Context */
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

/* --- Base & Reset --- */
*, *::before, *::after {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}



/* --- Page Header Layout --- */
.page-header-container {
    display: flex;
    flex-direction: column;
    gap: var(--space-4);
    margin-bottom: var(--space-6);
    padding-bottom: var(--space-4);
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

@media (min-width: 768px) {
    .page-header-container {
        flex-direction: row;
        justify-content: space-between;
        align-items: center;
    }
}

.page-header-title h2 {
    font-size: 1.65rem;
    font-weight: 700;
    color: var(--white);
    letter-spacing: -0.02em;
    margin-bottom: var(--space-1);
}

.text-muted {
    color: var(--gray-400) !important;
}

.small {
    font-size: var(--font-size-sm);
}

hr {
    border: 0;
    height: 1px;
    background: rgba(255, 255, 255, 0.08);
    margin: var(--space-4) 0;
}

/* --- Common UI Components (Alerts, Buttons) --- */
.alert {
    padding: var(--space-4);
    border-radius: var(--radius-lg);
    margin-bottom: var(--space-5);
    font-size: var(--font-size-sm);
    box-shadow: var(--shadow-md);
    animation: fadeIn var(--transition-fast) ease-out;
}

.alert-danger {
    background-color: rgba(255, 71, 87, 0.12);
    border: 1px solid rgba(255, 71, 87, 0.3);
    color: #ff6b81;
}

.alert-danger ul {
    margin-top: var(--space-2);
    margin-left: var(--space-4);
}

.alert-success {
    background-color: rgba(16, 172, 132, 0.12);
    border: 1px solid rgba(16, 172, 132, 0.3);
    color: #2ed573;
}

/* Buttons System */
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-family: inherit;
    font-size: var(--font-size-sm);
    font-weight: 600;
    padding: 0.65rem 1.25rem;
    border-radius: var(--radius-md);
    border: 1px solid transparent;
    cursor: pointer;
    text-decoration: none;
    transition: all var(--transition-fast);
    white-space: nowrap;
}

.btn-sm {
    padding: 0.4rem 0.85rem;
    font-size: var(--font-size-xs);
    border-radius: var(--radius-sm);
}

.btn-lg {
    padding: 0.9rem 1.5rem;
    font-size: var(--font-size-md);
    border-radius: var(--radius-lg);
}

.btn-block {
    display: flex;
    width: 100%;
}

.btn-secondary {
    background-color: var(--primary-700);
    color: var(--gray-100);
    border: 1px solid rgba(255, 255, 255, 0.08);
}

.btn-secondary:hover {
    background-color: var(--primary-600);
    color: var(--white);
    transform: translateY(-1px);
}

.btn-info {
    background-color: rgba(46, 134, 222, 0.15);
    color: var(--accent-blue);
    border: 1px solid rgba(46, 134, 222, 0.3);
}

.btn-info:hover {
    background-color: var(--accent-blue);
    color: var(--white);
}

.btn-success {
    background-color: var(--accent-green);
    color: var(--white);
    box-shadow: 0 4px 12px rgba(16, 172, 132, 0.2);
}

.btn-success:hover {
    background-color: #12c497;
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(16, 172, 132, 0.3);
}

/* --- Form Architecture & Grid Layout --- */
.modern-form {
    width: 100%;
}

.form-grid-layout {
    display: grid;
    grid-template-columns: 1fr;
    gap: var(--space-5);
    align-items: start;
}

@media (min-width: 1024px) {
    .form-grid-layout {
        grid-template-columns: minmax(0, 1fr) 340px;
    }
}

@media (min-width: 1200px) {
    .form-grid-layout {
        grid-template-columns: minmax(0, 1fr) 380px;
    }
}

/* Cards Structure */
.form-card {
    background-color: var(--primary-800);
    border: 1px solid rgba(255, 255, 255, 0.04);
    border-radius: var(--radius-lg);
    padding: var(--space-5);
    box-shadow: var(--shadow-lg);
    transition: border-color var(--transition-base);
}

.form-card:hover {
    border-color: rgba(255, 255, 255, 0.08);
}

.form-card h3 {
    font-size: var(--font-size-md);
    color: var(--white);
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: var(--space-2);
}

.form-card h3 .icon {
    font-style: normal;
}

.section-header-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: var(--space-2);
}

/* Helper Class Utilities */
.mt-2 { margin-top: var(--space-2); }
.mt-3 { margin-top: var(--space-3); }
.mt-4 { margin-top: var(--space-4); }
.text-center { text-align: center; }

/* Sub Grids for Form Control Items */
.form-row, .form-grid-3 {
    display: grid;
    grid-template-columns: 1fr;
    gap: var(--space-4);
}

@media (min-width: 640px) {
    .duration-row {
        grid-template-columns: repeat(2, 1fr);
    }
    .form-grid-3 {
        grid-template-columns: repeat(3, 1fr);
    }
}

/* Specific styling for miniature packed numeric inputs inside sidebar */
@media (min-width: 640px) {
    .small-inputs {
        grid-template-columns: repeat(3, 1fr);
        gap: var(--space-2);
    }
}

/* --- Form Fields & Inputs Styles --- */
.form-group {
    display: flex;
    flex-direction: column;
    gap: var(--space-2);
    margin-bottom: var(--space-4);
}

.form-group:last-child {
    margin-bottom: 0;
}

label {
    font-size: var(--font-size-sm);
    color: var(--gray-300);
    font-weight: 500;
}

label .required {
    color: var(--accent-red);
    margin-left: 2px;
}

/* Core Inputs Fields Reset & Skinning */
input[type="text"],
input[type="number"],
input[type="datetime-local"],
select,
textarea {
    width: 100%;
    font-family: inherit;
    font-size: var(--font-size-sm);
    color: var(--white);
    background-color: var(--primary-700);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: var(--radius-md);
    padding: 0.65rem 0.85rem;
    outline: none;
    transition: all var(--transition-fast);
}

/* Focus States */
input[type="text"]:focus,
input[type="number"]:focus,
input[type="datetime-local"]:focus,
select:focus,
textarea:focus {
    border-color: var(--accent-blue);
    background-color: var(--primary-600);
    box-shadow: 0 0 0 3px rgba(46, 134, 222, 0.2);
}

/* Element adjustments */
textarea {
    resize: vertical;
    min-height: 80px;
}

select {
    cursor: pointer;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%23ced6e0' viewBox='0 0 16 16'%3E%3Cpath fill-rule='evenodd' d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'--%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 0.85rem center;
    background-size: 10px;
    padding-right: 2.5rem;
}

/* Input Type File Styling styling trick */
input[type="file"] {
    background: transparent;
    border: 2px dashed rgba(255, 255, 255, 0.1);
    padding: var(--space-4);
    border-radius: var(--radius-md);
    cursor: pointer;
    text-align: center;
}

input[type="file"]:hover {
    border-color: var(--accent-blue);
    background: rgba(46, 134, 222, 0.02);
}

/* --- Dynamic Phases & Repeater Component --- */
.phase-row-item {
    background-color: rgba(255, 255, 255, 0.02);
    border: 1px solid rgba(255, 255, 255, 0.06);
    border-radius: var(--radius-md);
    padding: var(--space-4);
    position: relative;
    animation: slideDown var(--transition-base) ease;
}

.phase-row-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: var(--space-3);
}

.phase-row-header h4 {
    font-size: var(--font-size-sm);
    font-weight: 600;
    color: var(--accent-blue);
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.btn-remove-phase {
    background: transparent;
    border: none;
    color: var(--gray-400);
    font-size: var(--font-size-xs);
    cursor: pointer;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 4px;
    transition: color var(--transition-fast);
}

.btn-remove-phase:hover {
    color: var(--accent-red);
}

/* --- Toggle Switch System (Custom Checkboxes) --- */
.toggle-group {
    display: flex;
    align-items: center;
    gap: var(--space-3);
    user-select: none;
}

.switch-container {
    position: relative;
    display: inline-block;
    width: 42px;
    height: 24px;
    flex-shrink: 0;
}

.switch-container input {
    opacity: 0;
    width: 0;
    height: 0;
}

.slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: var(--primary-600);
    border: 1px solid rgba(255, 255, 255, 0.08);
    transition: .3s;
    border-radius: var(--radius-full);
}

.slider:before {
    position: absolute;
    content: "";
    height: 16px;
    width: 16px;
    left: 3px;
    bottom: 3px;
    background-color: var(--gray-300);
    transition: .3s;
    border-radius: 50%;
}

/* Checked Actions */
.switch-container input:checked + .slider {
    background-color: var(--accent-green);
    border-color: transparent;
}

.switch-container input:checked + .slider:before {
    transform: translateX(18px);
    background-color: var(--white);
}

.switch-container input:focus + .slider {
    box-shadow: 0 0 0 3px rgba(16, 172, 132, 0.2);
}

.toggle-label {
    font-size: var(--font-size-sm);
    color: var(--gray-200);
    font-weight: 400;
}

/* --- Sticky Submit Sidebar Fix --- */
.submit-container {
    position: static;
}

@media (min-width: 1024px) {
    .form-sidebar-column {
        position: sticky;
        top: var(--space-4);
    }
}

/* --- Keyframe Animations --- */
@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-8px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>


<div class="page-header-container">
    <div class="page-header-title">
        <h2>📝 Modifier le Concours : <?= htmlspecialchars($concours['nom_concours']) ?></h2>
        <p class="text-muted">Modifiez les configurations générales ainsi que les étapes du concours.</p>
    </div>
    <a href="concours.php" class="btn btn-secondary">← Retour à la liste</a>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <strong>Oups ! Les erreurs suivantes sont survenues :</strong>
        <ul>
            <?php foreach ($errors as $error): ?>
                <li><?= htmlspecialchars($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success">
        🎉 Le concours a été modifié avec succès ! Redirection en cours...
    </div>
<?php endif; ?>

<form action="" method="POST" enctype="multipart/form-data" class="modern-form">
    
    <!-- GRID PRINCIPALE -->
    <div class="form-grid-layout">
        
        <!-- COLONNE GAUCHE : INFOS DU CONCOURS -->
        <div class="form-main-column">
            
            <div class="form-card">
                <h3><i class="icon">ℹ️</i> Informations Générales</h3>
                <hr>
                
                <div class="form-group">
                    <label for="nom_concours">Nom du Concours <span class="required">*</span></label>
                    <input type="text" id="nom_concours" name="nom_concours" required value="<?= htmlspecialchars($concours['nom_concours']) ?>">
                </div>

                <div class="form-row duration-row">
                    <div class="form-group">
                        <label for="type_concours">Type de Concours</label>
                        <select id="type_concours" name="type_concours">
                            <?php
                            $types = [
                                'autre' => 'Autre', 'academique' => 'Académique', 'coding' => 'Coding / Hackathon',
                                'innovation' => 'Innovation', 'football' => 'Football', 'quiz' => 'Quiz / Génie en herbe',
                                'debats' => 'Débats', 'miss_mister' => 'Miss & Mister', 'musique' => 'Musique',
                                'danse' => 'Danse', 'e-sport' => 'E-Sport'
                            ];
                            foreach ($types as $value => $label): ?>
                                <option value="<?= $value ?>" <?= $concours['type_concours'] === $value ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="academic_year_id">Année Académique</label>
                        <select id="academic_year_id" name="academic_year_id">
                            <option value="">Sélectionner l'année</option>
                            <?php foreach ($academic_years as $year): ?>
                                <option value="<?= $year['id'] ?>" <?= intval($concours['academic_year_id']) === intval($year['id']) ? 'selected' : '' ?>><?= htmlspecialchars($year['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="description">Description globale</label>
                    <textarea id="description" name="description" rows="4"><?= htmlspecialchars($concours['description'] ?? '') ?></textarea>
                </div>

                <div class="form-row duration-row">
                    <div class="form-group">
                        <label for="reglement">Règlement du concours</label>
                        <textarea id="reglement" name="reglement" rows="3"><?= htmlspecialchars($concours['reglement'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="conditions_participation">Conditions d'éligibilité</label>
                        <textarea id="conditions_participation" name="conditions_participation" rows="3"><?= htmlspecialchars($concours['conditions_participation'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <!-- STRUCTURE DYNAMIQUE DES ÉTAPES -->
            <div class="form-card mt-4">
                <div class="section-header-actions">
                    <h3><i class="icon">⛓️</i> Étapes & Phases du Concours</h3>
                    <button type="button" id="btn-add-phase" class="btn btn-info btn-sm">+ Ajouter une étape</button>
                </div>
                <p class="text-muted small">Modifiez ou réorganisez la chronologie du concours.</p>
                <hr>

                <!-- Conteneur JavaScript des étapes -->
                <div id="phases-container">
                    <?php if (!empty($phases_existantes)): ?>
                        <?php foreach ($phases_existantes as $index => $phase): ?>
                            <div class="phase-row-item <?= $index > 0 ? 'mt-3' : '' ?>" data-index="<?= $index ?>">
                                <div class="phase-row-header">
                                    <h4>Étape N°<?= $index + 1 ?></h4>
                                    <button type="button" class="btn-remove-phase" onclick="removePhase(this)">🗑️ Retirer</button>
                                </div>
                                <div class="form-grid-3">
                                    <div class="form-group">
                                        <label>Nom de l'étape <span class="required">*</span></label>
                                        <input type="text" name="phase_nom[]" required value="<?= htmlspecialchars($phase['nom_phase']) ?>">
                                    </div>
                                    <div class="form-group">
                                        <label>Type de phase</label>
                                        <select name="phase_type[]">
                                            <?php
                                            $p_types = [
                                                'inscription' => 'Inscription', 'qualification' => 'Qualification',
                                                'quart_finale' => 'Quart de finale', 'demi_finale' => 'Demi-finale',
                                                'finale' => 'Finale', 'vote' => 'Phase de vote', 'autre' => 'Autre'
                                            ];
                                            foreach ($p_types as $v => $l): ?>
                                                <option value="<?= $v ?>" <?= $phase['type_phase'] === $v ? 'selected' : '' ?>><?= $l ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Nombre qualifiés après l'étape</label>
                                        <input type="number" name="phase_qualifies[]" value="<?= intval($phase['nombre_qualifies']) ?>" min="0">
                                    </div>
                                </div>
                                <div class="form-grid-3 mt-2">
                                    <div class="form-group">
                                        <label>Date Début</label>
                                        <input type="datetime-local" name="phase_debut[]" value="<?= $phase['date_debut'] ? date('Y-m-d\TH:i', strtotime($phase['date_debut'])) : '' ?>">
                                    </div>
                                    <div class="form-group">
                                        <label>Date Fin</label>
                                        <input type="datetime-local" name="phase_fin[]" value="<?= $phase['date_fin'] ? date('Y-m-d\TH:i', strtotime($phase['date_fin'])) : '' ?>">
                                    </div>
                                    <div class="form-group">
                                        <label>Objectif court / Description</label>
                                        <input type="text" name="phase_desc[]" value="<?= htmlspecialchars($phase['description'] ?? '') ?>">
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <!-- Fallback si aucune phase n'est étrangement trouvée -->
                        <div class="phase-row-item" data-index="0">
                            <div class="phase-row-header">
                                <h4>Étape N°1</h4>
                                <button type="button" class="btn-remove-phase" onclick="removePhase(this)">🗑️ Retirer</button>
                            </div>
                            <div class="form-grid-3">
                                <div class="form-group">
                                    <label>Nom de l'étape <span class="required">*</span></label>
                                    <input type="text" name="phase_nom[]" required placeholder="Ex: Phase d'inscriptions">
                                </div>
                                <div class="form-group">
                                    <label>Type de phase</label>
                                    <select name="phase_type[]">
                                        <option value="inscription">Inscription</option>
                                        <option value="qualification">Qualification</option>
                                        <option value="finale">Finale</option>
                                        <option value="autre">Autre</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Nombre qualifiés après l'étape</label>
                                    <input type="number" name="phase_qualifies[]" value="0" min="0">
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <!-- COLONNE DROITE : LOGISTIQUE ET PARAMÈTRES -->
        <div class="form-sidebar-column">
            
            <!-- LOGISTIQUE & PLACES -->
            <div class="form-card">
                <h3><i class="icon">📍</i> Logistique & Places</h3>
                <hr>
                <div class="form-group">
                    <label for="lieu">Lieu de l'événement</label>
                    <input type="text" id="lieu" name="lieu" value="<?= htmlspecialchars($concours['lieu'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="organisateur">Organisateur / Comité</label>
                    <input type="text" id="organisateur" name="organisateur" value="<?= htmlspecialchars($concours['organisateur'] ?? '') ?>">
                </div>
                <div class="form-row duration-row">
                    <div class="form-group">
                        <label for="min_participants">Min Participants</label>
                        <input type="number" id="min_participants" name="min_participants" value="<?= intval($concours['min_participants']) ?>" min="1">
                    </div>
                    <div class="form-group">
                        <label for="max_participants">Max Participants</label>
                        <input type="number" id="max_participants" name="max_participants" value="<?= $concours['max_participants'] ? intval($concours['max_participants']) : '' ?>" placeholder="Illimité">
                    </div>
                </div>
            </div>

            <!-- CALENDRIER GLOBAL -->
            <div class="form-card mt-4">
                <h3><i class="icon">📅</i> Dates Globales</h3>
                <hr>
                <div class="form-group">
                    <label for="date_debut">Date Début Concours <span class="required">*</span></label>
                    <input type="datetime-local" id="date_debut" name="date_debut" required value="<?= $concours['date_debut'] ? date('Y-m-d\TH:i', strtotime($concours['date_debut'])) : '' ?>">
                </div>
                <div class="form-group">
                    <label for="date_fin">Date Clôture / Finale</label>
                    <input type="datetime-local" id="date_fin" name="date_fin" value="<?= $concours['date_fin'] ? date('Y-m-d\TH:i', strtotime($concours['date_fin'])) : '' ?>">
                </div>
                <div class="form-group">
                    <label for="date_limite_inscription">Limite Inscription</label>
                    <input type="datetime-local" id="date_limite_inscription" name="date_limite_inscription" value="<?= $concours['date_limite_inscription'] ? date('Y-m-d\TH:i', strtotime($concours['date_limite_inscription'])) : '' ?>">
                </div>
            </div>

            <!-- FINANCES & MODES -->
            <div class="form-card mt-4">
                <h3><i class="icon">💰</i> Frais & Sélection</h3>
                <hr>
                <div class="toggle-group mb-3">
                    <label class="switch-container">
                        <input type="checkbox" id="participation_gratuite" name="participation_gratuite" <?= $concours['participation_gratuite'] ? 'checked' : '' ?> onchange="toggleFrais(this)">
                        <span class="slider"></span>
                    </label>
                    <span class="toggle-label">Participation Gratuite</span>
                </div>

                <div class="form-group" id="frais-container" style="display: <?= $concours['participation_gratuite'] ? 'none' : 'block' ?>;">
                    <label for="frais_participation">Montant des Frais (FCFA)</label>
                    <input type="number" id="frais_participation" name="frais_participation" value="<?= floatval($concours['frais_participation']) ?>" step="50">
                </div>

                <div class="form-group mt-3">
                    <label for="mode_selection">Mode de Sélection</label>
                    <select id="mode_selection" name="mode_selection">
                        <?php
                        $modes = [
                            'manuel' => 'Manuel (Comité de jury)', 'automatique' => 'Automatique',
                            'vote' => 'Votes du Public', 'points' => 'Cumul de Points', 'elimination' => 'Élimination directe'
                        ];
                        foreach ($modes as $key => $lbl): ?>
                            <option value="<?= $key ?>" <?= $concours['mode_selection'] === $key ? 'selected' : '' ?>><?= $lbl ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-grid-3 text-center small-inputs">
                    <div class="form-group">
                        <label>Qualifiés</label>
                        <input type="number" name="nombre_qualifies" value="<?= intval($concours['nombre_qualifies']) ?>">
                    </div>
                    <div class="form-group">
                        <label>Finalistes</label>
                        <input type="number" name="nombre_finalistes" value="<?= intval($concours['nombre_finalistes']) ?>">
                    </div>
                    <div class="form-group">
                        <label>Gagnants</label>
                        <input type="number" name="nombre_gagnants" value="<?= intval($concours['nombre_gagnants']) ?>">
                    </div>
                </div>
            </div>

            <!-- OPTIONS DE PUBLICATION -->
            <div class="form-card mt-4">
                <h3><i class="icon">⚙️</i> Publication</h3>
                <hr>
                <div class="form-group">
                    <label for="statut">Statut Initial</label>
                    <select id="statut" name="statut">
                        <option value="brouillon" <?= $concours['statut'] === 'brouillon' ? 'selected' : '' ?>>Brouillon</option>
                        <option value="ouvert" <?= $concours['statut'] === 'ouvert' ? 'selected' : '' ?>>Ouvert (Inscriptions lancées)</option>
                        <option value="en_cours" <?= $concours['statut'] === 'en_cours' ? 'selected' : '' ?>>En cours</option>
                        <option value="termine" <?= $concours['statut'] === 'termine' ? 'selected' : '' ?>>Terminé</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="image_affiche">Affiche publicitaire</label>
                    <input type="file" id="image_affiche" name="image_affiche" accept="image/*">
                    <?php if (!empty($concours['image_affiche'])): ?>
                        <div class="mt-2 text-muted small">
                            Affiche actuelle : <strong><?= htmlspecialchars($concours['image_affiche']) ?></strong>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="toggle-group mb-2">
                    <label class="switch-container">
                        <input type="checkbox" id="inscription_obligatoire" name="inscription_obligatoire" <?= $concours['inscription_obligatoire'] ? 'checked' : '' ?>>
                        <span class="slider"></span>
                    </label>
                    <span class="toggle-label">Inscription en ligne obligatoire</span>
                </div>

                <div class="toggle-group">
                    <label class="switch-container">
                        <input type="checkbox" id="is_public" name="is_public" <?= $concours['is_public'] ? 'checked' : '' ?>>
                        <span class="slider"></span>
                    </label>
                    <span class="toggle-label">Rendre visible au public</span>
                </div>
            </div>

            <!-- BOUTON DE SOUMISSION -->
            <div class="submit-container mt-4">
                <button type="submit" class="btn btn-success btn-block btn-lg">💾 Enregistrer les modifications</button>
            </div>

        </div>

    </div>
</form>

<script>
    // Initialiser l'index JavaScript sur le nombre réel de phases existantes
    let phaseIndex = <?= count($phases_existantes) > 0 ? count($phases_existantes) : 1 ?>;

    document.getElementById('btn-add-phase').addEventListener('click', function() {
        const container = document.getElementById('phases-container');
        phaseIndex++;

        const phaseHTML = `
            <div class="phase-row-item mt-3" data-index="${phaseIndex - 1}">
                <div class="phase-row-header">
                    <h4>Étape N°${phaseIndex}</h4>
                    <button type="button" class="btn-remove-phase" onclick="removePhase(this)">🗑️ Retirer</button>
                </div>
                <div class="form-grid-3">
                    <div class="form-group">
                        <label>Nom de l'étape <span class="required">*</span></label>
                        <input type="text" name="phase_nom[]" required placeholder="Ex: Nouvelle Phase">
                    </div>
                    <div class="form-group">
                        <label>Type de phase</label>
                        <select name="phase_type[]">
                            <option value="qualification">Qualification</option>
                            <option value="inscription">Inscription</option>
                            <option value="quart_finale">Quart de finale</option>
                            <option value="demi_finale">Demi-finale</option>
                            <option value="finale">Finale</option>
                            <option value="vote">Phase de vote</option>
                            <option value="autre" selected>Autre</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Nombre qualifiés après l'étape</label>
                        <input type="number" name="phase_qualifies[]" value="0" min="0">
                    </div>
                </div>
                <div class="form-grid-3 mt-2">
                    <div class="form-group">
                        <label>Date Début</label>
                        <input type="datetime-local" name="phase_debut[]">
                    </div>
                    <div class="form-group">
                        <label>Date Fin</label>
                        <input type="datetime-local" name="phase_fin[]">
                    </div>
                    <div class="form-group">
                        <label>Objectif court / Description</label>
                        <input type="text" name="phase_desc[]" placeholder="Critères...">
                    </div>
                </div>
            </div>
        `;
        container.insertAdjacentHTML('beforeend', phaseHTML);
        updatePhaseTitles();
    });

    function removePhase(button) {
        const rows = document.querySelectorAll('.phase-row-item');
        if(rows.length > 1) {
            button.closest('.phase-row-item').remove();
            updatePhaseTitles();
        } else {
            alert('Votre concours doit contenir au moins une étape fondamentale.');
        }
    }

    function updatePhaseTitles() {
        const titles = document.querySelectorAll('.phase-row-header h4');
        titles.forEach((title, i) => {
            title.textContent = `Étape N°${i + 1}`;
        });
        phaseIndex = titles.length;
    }

    function toggleFrais(checkbox) {
        const fraisContainer = document.getElementById('frais-container');
        if (checkbox.checked) {
            fraisContainer.style.display = 'none';
            document.getElementById('frais_participation').value = '0.00';
        } else {
            fraisContainer.style.display = 'block';
        }
    }
</script>
<?php
// Correction de votre faute de frappe d'origine (endstyle au lieu de endforeach)
$content = ob_get_clean();
include "../layout.php";
?>