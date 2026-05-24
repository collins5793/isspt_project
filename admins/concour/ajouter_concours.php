<?php
session_start();
require_once "../../includes/db.php";

// Simulation ou récupération de l'ID de l'admin connecté
$adminId = $_SESSION['user_id'] ?? 1; 

// Récupération des années académiques pour le sélecteur
$yearsStmt = $pdo->query("SELECT id, label FROM academic_years ORDER BY id DESC");
$academic_years = $yearsStmt->fetchAll(PDO::FETCH_ASSOC);

$errors = [];
$success = false;

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Extraction et nettoyage des données du concours
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

    // Données des phases
    $phases_nom = $_POST['phase_nom'] ?? [];
    $phases_type = $_POST['phase_type'] ?? [];
    $phases_qualifies = $_POST['phase_qualifies'] ?? [];
    $phases_debut = $_POST['phase_debut'] ?? [];
    $phases_fin = $_POST['phase_fin'] ?? [];
    $phases_desc = $_POST['phase_desc'] ?? [];

    if (empty($phases_nom)) {
        $errors[] = "Vous devez ajouter au moins une étape (phase) pour ce concours.";
    }

    // Si aucune erreur, on procède à l'insertion
    if (empty($errors)) {
        try {
            // Début de la transaction pour sécuriser la double insertion
            $pdo->beginTransaction();

            // Gestion de l'image affiche (Upload basique)
            $image_affiche = null;
            if (isset($_FILES['image_affiche']) && $_FILES['image_affiche']['error'] === UPLOAD_ERR_OK) {
                $ext = pathinfo($_FILES['image_affiche']['name'], PATHINFO_EXTENSION);
                $filename = 'concours_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['image_affiche']['tmp_name'], '../uploads/' . $filename)) {
                    $image_affiche = $filename;
                }
            }

            // Insertion du concours
            $sqlConcours = "INSERT INTO concours (
                nom_concours, slug, type_concours, description, reglement, conditions_participation,
                date_debut, date_fin, date_limite_inscription, lieu, organisateur, max_participants,
                min_participants, inscription_obligatoire, participation_gratuite, frais_participation,
                mode_selection, nombre_qualifies, nombre_finalistes, nombre_gagnants, image_affiche,
                statut, is_public, academic_year_id, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $pdo->prepare($sqlConcours);
            $stmt->execute([
                $nom_concours, $slug, $type_concours, $description, $reglement, $conditions_participation,
                $date_debut, $date_fin, $date_limite_inscription, $lieu, $organisateur, $max_participants,
                $min_participants, $inscription_obligatoire, $participation_gratuite, $frais_participation,
                $mode_selection, $nombre_qualifies, $nombre_finalistes, $nombre_gagnants, $image_affiche,
                $statut, $is_public, $academic_year_id, $adminId
            ]);

            // Récupération de l'ID du concours tout juste créé
            $id_concours = $pdo->lastInsertId();

            // Insertion des phases
            $sqlPhase = "INSERT INTO concours_phases (
                id_concours, nom_phase, type_phase, ordre_phase, nombre_qualifies, date_debut, date_fin, description
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmtPhase = $pdo->prepare($sqlPhase);

            for ($i = 0; $i < count($phases_nom); $i++) {
                if (!empty(trim($phases_nom[$i]))) {
                    $ordre = $i + 1; // Ordre automatique basé sur la position dans le formulaire
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

            // Tout est bon, on valide la transaction
            $pdo->commit();
            $success = true;
            
            // Redirection après succès vers la liste des concours
            header("Refresh: 2; url=concours.php");

        } catch (Exception $e) {
            // En cas d'erreur, on annule tout
            $pdo->rollBack();
            $errors[] = "Erreur lors de l'enregistrement : " . $e->getMessage();
        }
    }
}

ob_start();
?>

<style>
    /* ==========================================================================
   RESET & SYSTEM BASE
   ========================================================================== */
* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
}

body {
    font-family: var(--font-primary);
    background-color: var(--primary-900);
    color: var(--gray-100);
    font-size: var(--font-size-md);
    line-height: 1.5;
    padding: var(--space-5);
}

/* ==========================================================================
   ENTÊTE DE PAGE (Page Header)
   ========================================================================== */
.page-header-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: var(--space-4);
    margin-bottom: var(--space-6);
    padding-bottom: var(--space-4);
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

.page-header-title h2 {
    font-size: 1.75rem;
    font-weight: 700;
    color: var(--white);
    margin-bottom: var(--space-2);
}

.text-muted {
    color: var(--gray-400) !important;
}

.text-muted.small {
    font-size: var(--font-size-sm);
}

/* ==========================================================================
   ALERTE & NOTIFICATIONS
   ========================================================================== */
.alert {
    padding: var(--space-4);
    border-radius: var(--radius-md);
    margin-bottom: var(--space-5);
    font-size: var(--font-size-sm);
    animation: fadeIn var(--transition-fast);
}

.alert-danger {
    background-color: rgba(255, 71, 87, 0.1);
    border: 1px solid var(--accent-red);
    color: #ff6b81;
}

.alert-danger ul {
    margin-top: var(--space-2);
    padding-left: var(--space-5);
}

.alert-success {
    background-color: rgba(16, 172, 132, 0.1);
    border: 1px solid var(--accent-green);
    color: #1dd1a1;
}

/* ==========================================================================
   LAYOUT GLOBO-RESPONSIVE (Grid Layout)
   ========================================================================== */
.form-grid-layout {
    display: grid;
    grid-template-columns: 1fr var(--sidebar-width);
    gap: var(--space-5);
    align-items: start;
}

.form-main-column,
.form-sidebar-column {
    display: flex;
    flex-direction: column;
    gap: var(--space-5);
}

/* Classes utilitaires d'espacement */
.mt-2 { margin-top: var(--space-2); }
.mt-3 { margin-top: var(--space-3); }
.mt-4 { margin-top: var(--space-5); }
.mb-2 { margin-bottom: var(--space-2); }
.mb-3 { margin-bottom: var(--space-3); }
.text-center { text-align: center; }

/* ==========================================================================
   COMPOSANT : CARTES (Cards)
   ========================================================================== */
.form-card {
    background: linear-gradient(145deg, var(--primary-800) 0%, var(--primary-700) 100%);
    border: 1px solid rgba(255, 255, 255, 0.05);
    border-radius: var(--radius-lg);
    padding: var(--space-5);
    box-shadow: var(--shadow-lg);
    transition: transform var(--transition-fast), box-shadow var(--transition-fast);
}

.form-card:hover {
    box-shadow: var(--shadow-xl);
    border-color: rgba(255, 255, 255, 0.08);
}

.form-card h3 {
    font-size: var(--font-size-lg);
    font-weight: 600;
    color: var(--white);
    display: flex;
    align-items: center;
    gap: var(--space-2);
}

.form-card hr {
    border: 0;
    height: 1px;
    background: rgba(255, 255, 255, 0.08);
    margin: var(--space-4) 0;
}

.section-header-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: var(--space-3);
}

/* ==========================================================================
   ÉLÉMENTS DE FORMULAIRE (Inputs, Selects, Textareas)
   ========================================================================== */
.form-group {
    display: flex;
    flex-direction: column;
    gap: var(--space-2);
    width: 100%;
}

.form-group label {
    font-size: var(--font-size-sm);
    font-weight: 500;
    color: var(--gray-300);
}

.form-group label .required {
    color: var(--accent-red);
    margin-left: var(--space-1);
}

input[type="text"],
input[type="number"],
input[type="datetime-local"],
select,
textarea {
    width: 100%;
    background-color: var(--primary-600);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: var(--radius-md);
    padding: calc(var(--space-3) - 2px) var(--space-4);
    color: var(--white);
    font-family: inherit;
    font-size: var(--font-size-sm);
    transition: border-color var(--transition-fast), box-shadow var(--transition-fast);
}

input:focus,
select:focus,
textarea:focus {
    outline: none;
    border-color: var(--accent-blue);
    box-shadow: 0 0 0 3px rgba(46, 134, 222, 0.2);
}

/* Style spécifique pour le type File */
input[type="file"] {
    padding: var(--space-2);
    background: rgba(255, 255, 255, 0.03);
    border: 1px dashed rgba(255, 255, 255, 0.15);
    cursor: pointer;
}

input[type="file"]:hover {
    border-color: var(--accent-blue);
    background: rgba(255, 255, 255, 0.05);
}

/* Placeholders */
::placeholder {
    color: var(--gray-400);
    opacity: 0.6;
}

/* Grilles internes adaptatives */
.form-row.duration-row {
    display: flex;
    gap: var(--space-4);
}

.form-row.duration-row > .form-group {
    flex: 1;
}

.form-grid-3 {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: var(--space-4);
}

.small-inputs input {
    text-align: center;
}

/* ==========================================================================
   GESTION DYNAMIQUE DES PHASES (Steps)
   ========================================================================== */
#phases-container {
    display: flex;
    flex-direction: column;
    gap: var(--space-4);
}

.phase-row-item {
    background: rgba(255, 255, 255, 0.02);
    border: 1px solid rgba(255, 255, 255, 0.06);
    border-radius: var(--radius-md);
    padding: var(--space-4);
    position: relative;
    animation: slideUp var(--transition-base);
}

.phase-row-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: var(--space-3);
    padding-bottom: var(--space-2);
    border-bottom: 1px dashed rgba(255, 255, 255, 0.06);
}

.phase-row-header h4 {
    font-size: var(--font-size-sm);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--accent-blue);
    font-weight: 600;
}

/* ==========================================================================
   BOUTONS (Buttons)
   ========================================================================== */
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-family: inherit;
    font-size: var(--font-size-sm);
    font-weight: 600;
    padding: var(--space-3) var(--space-5);
    border-radius: var(--radius-md);
    border: none;
    cursor: pointer;
    transition: background-color var(--transition-fast), transform var(--transition-fast);
    text-decoration: none;
    white-space: nowrap;
}

.btn:active {
    transform: scale(0.98);
}

.btn-secondary {
    background-color: var(--primary-600);
    color: var(--gray-100);
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.btn-secondary:hover {
    background-color: var(--primary-700);
    color: var(--white);
}

.btn-info {
    background-color: rgba(46, 134, 222, 0.15);
    color: #54a0ff;
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
    background-color: #1dd1a1;
    box-shadow: 0 6px 16px rgba(16, 172, 132, 0.3);
}

.btn-sm {
    padding: var(--space-2) var(--space-3);
    font-size: var(--font-size-xs);
    border-radius: var(--radius-sm);
}

.btn-lg {
    padding: var(--space-4) var(--space-6);
    font-size: var(--font-size-md);
}

.btn-block {
    width: 100%;
}

.btn-remove-phase {
    background: transparent;
    border: none;
    color: var(--accent-red);
    font-size: var(--font-size-xs);
    font-weight: 500;
    cursor: pointer;
    padding: var(--space-1) var(--space-2);
    border-radius: var(--radius-sm);
    transition: background var(--transition-fast);
}

.btn-remove-phase:hover {
    background: rgba(255, 71, 87, 0.1);
}

/* ==========================================================================
   INTERRUPTEURS MODERNES (IOS Custom Switches)
   ========================================================================== */
.toggle-group {
    display: flex;
    align-items: center;
    gap: var(--space-3);
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
    top: 0; left: 0; right: 0; bottom: 0;
    background-color: var(--primary-600);
    border: 1px solid rgba(255, 255, 255, 0.1);
    transition: var(--transition-fast);
    border-radius: var(--radius-full);
}

.slider:before {
    position: absolute;
    content: "";
    height: 16px;
    width: 16px;
    left: 3px;
    bottom: 3px;
    background-color: var(--white);
    transition: var(--transition-fast);
    border-radius: 50%;
    box-shadow: var(--shadow-sm);
}

input:checked + .slider {
    background-color: var(--accent-green);
    border-color: transparent;
}

input:focus + .slider {
    box-shadow: 0 0 0 2px rgba(16, 172, 132, 0.2);
}

input:checked + .slider:before {
    transform: translateX(18px);
}

.toggle-label {
    font-size: var(--font-size-sm);
    color: var(--gray-200);
    user-select: none;
}

/* ==========================================================================
   ANIMATIONS SYSTEM
   ========================================================================== */
@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes slideUp {
    from { opacity: 0; transform: translateY(8px); }
    to { opacity: 1; transform: translateY(0); }
}

/* ==========================================================================
   RESPONSIVITÉ ABSOLUE (Breakpoints Media Queries)
   ========================================================================== */

/* Écrans intermédiaires ou Tablettes (max-width: 1024px) */
@media (max-width: 1024px) {
    .form-grid-layout {
        grid-template-columns: 1fr; /* Passage sur une seule colonne principale */
    }

    .form-sidebar-column {
        display: grid;
        grid-template-columns: repeat(2, 1fr); /* Les options passent côte à côte */
        gap: var(--space-4);
    }

    .submit-container {
        grid-column: span 2; /* Le bouton prend toute la largeur sous la grille */
    }
}

/* Smartphones et Petits Écrans (max-width: 768px) */
@media (max-width: 768px) {
    body {
        padding: var(--space-3);
    }

    .page-header-container {
        flex-direction: column;
        align-items: flex-start;
        gap: var(--space-3);
    }

    .btn {
        width: 100%; /* Les actions d'entêtes s'étendent sur mobile */
    }

    .form-sidebar-column {
        grid-template-columns: 1fr; /* Rebascule en vertical strict */
    }

    .submit-container {
        grid-column: span 1;
    }

    .form-row.duration-row {
        flex-direction: column; /* Les inputs côte à côte passent les uns sous les autres */
        gap: var(--space-4);
    }

    .form-grid-3 {
        grid-template-columns: 1fr; /* Les sous-grilles passent à une seule colonne */
        gap: var(--space-4);
    }
    
    .form-card {
        padding: var(--space-4); /* Réduction du padding interne pour maximiser l'espace */
    }
}
</style>

<div class="page-header-container">
    <div class="page-header-title">
        <h2>🏆 Créer un Nouveau Concours</h2>
        <p class="text-muted">Configurez le concours ainsi que ses différentes phases de déroulement.</p>
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
        🎉 Le concours et ses étapes ont été créés avec succès ! Redirection en cours...
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
                    <input type="text" id="nom_concours" name="nom_concours" required placeholder="Ex: Tournoi d'Innovation Fintech 2026">
                </div>

                <div class="form-row duration-row">
                    <div class="form-group">
                        <label for="type_concours">Type de Concours</label>
                        <select id="type_concours" name="type_concours">
                            <option value="autre">Autre</option>
                            <option value="academique">Académique</option>
                            <option value="coding">Coding / Hackathon</option>
                            <option value="innovation">Innovation</option>
                            <option value="football">Football</option>
                            <option value="quiz">Quiz / Génie en herbe</option>
                            <option value="debats">Débats</option>
                            <option value="miss_mister">Miss & Mister</option>
                            <option value="musique">Musique</option>
                            <option value="danse">Danse</option>
                            <option value="e-sport">E-Sport</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="academic_year_id">Année Académique</label>
                        <select id="academic_year_id" name="academic_year_id">
                            <option value="">Sélectionner l'année</option>
                            <?php foreach ($academic_years as $year): ?>
                                <option value="<?= $year['id'] ?>"><?= htmlspecialchars($year['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="description">Description globale</label>
                    <textarea id="description" name="description" rows="4" placeholder="Présentation, contexte du concours..."></textarea>
                </div>

                <div class="form-row duration-row">
                    <div class="form-group">
                        <label for="reglement">Règlement du concours</label>
                        <textarea id="reglement" name="reglement" rows="3" placeholder="Règles à respecter par les candidats..."></textarea>
                    </div>
                    <div class="form-group">
                        <label for="conditions_participation">Conditions d'éligibilité</label>
                        <textarea id="conditions_participation" name="conditions_participation" rows="3" placeholder="Qui peut participer ? (Âge, niveau...)"></textarea>
                    </div>
                </div>
            </div>

            <!-- STRUCTURE DYNAMIQUE DES ÉTAPES -->
            <div class="form-card mt-4">
                <div class="section-header-actions">
                    <h3><i class="icon">⛓️</i> Étapes & Phases du Concours</h3>
                    <button type="button" id="btn-add-phase" class="btn btn-info btn-sm">+ Ajouter une étape</button>
                </div>
                <p class="text-muted small">Définissez la chronologie du concours (ex: Inscription -> Qualification -> Finale).</p>
                <hr>

                <!-- Conteneur JavaScript des étapes -->
                <div id="phases-container">
                    <!-- Étape 1 par défaut -->
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
                                    <option value="quart_finale">Quart de finale</option>
                                    <option value="demi_finale">Demi-finale</option>
                                    <option value="finale">Finale</option>
                                    <option value="vote">Phase de vote</option>
                                    <option value="autre">Autre</option>
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
                                <input type="text" name="phase_desc[]" placeholder="Critères légers de validation...">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- COLONNE DROITE : LOGISTIQUE ET PARAMÈTRES -->
        <div class="form-sidebar-column">
            
            <!-- LOGISTIQUE & LOGES -->
            <div class="form-card">
                <h3><i class="icon">📍</i> Logistique & Places</h3>
                <hr>
                <div class="form-group">
                    <label for="lieu">Lieu de l'événement</label>
                    <input type="text" id="lieu" name="lieu" placeholder="Ex: Amphi C, En ligne...">
                </div>
                <div class="form-group">
                    <label for="organisateur">Organisateur / Comité</label>
                    <input type="text" id="organisateur" name="organisateur" placeholder="Ex: BDE, Commission Innovation">
                </div>
                <div class="form-row duration-row">
                    <div class="form-group">
                        <label for="min_participants">Min Participants</label>
                        <input type="number" id="min_participants" name="min_participants" value="1" min="1">
                    </div>
                    <div class="form-group">
                        <label for="max_participants">Max Participants</label>
                        <input type="number" id="max_participants" name="max_participants" placeholder="Illimité">
                    </div>
                </div>
            </div>

            <!-- CALENDRIER GLOBAL -->
            <div class="form-card mt-4">
                <h3><i class="icon">📅</i> Dates Globales</h3>
                <hr>
                <div class="form-group">
                    <label for="date_debut">Date Début Concours <span class="required">*</span></label>
                    <input type="datetime-local" id="date_debut" name="date_debut" required>
                </div>
                <div class="form-group">
                    <label for="date_fin">Date Clôture / Finale</label>
                    <input type="datetime-local" id="date_fin" name="date_fin">
                </div>
                <div class="form-group">
                    <label for="date_limite_inscription">Limite Inscription</label>
                    <input type="datetime-local" id="date_limite_inscription" name="date_limite_inscription">
                </div>
            </div>

            <!-- FINANCES & MODES -->
            <div class="form-card mt-4">
                <h3><i class="icon">💰</i> Frais & Sélection</h3>
                <hr>
                <div class="toggle-group mb-3">
                    <label class="switch-container">
                        <input type="checkbox" id="participation_gratuite" name="participation_gratuite" checked onchange="toggleFrais(this)">
                        <span class="slider"></span>
                    </label>
                    <span class="toggle-label">Participation Gratuite</span>
                </div>

                <div class="form-group" id="frais-container" style="display: none;">
                    <label for="frais_participation">Montant des Frais (FCFA)</label>
                    <input type="number" id="frais_participation" name="frais_participation" value="0.00" step="50">
                </div>

                <div class="form-group mt-3">
                    <label for="mode_selection">Mode de Sélection</label>
                    <select id="mode_selection" name="mode_selection">
                        <option value="manuel">Manuel (Comité de jury)</option>
                        <option value="automatique">Automatique</option>
                        <option value="vote">Votes du Public</option>
                        <option value="points">Cumul de Points</option>
                        <option value="elimination">Élimination directe</option>
                    </select>
                </div>

                <div class="form-grid-3 text-center small-inputs">
                    <div class="form-group">
                        <label>Qualifiés</label>
                        <input type="number" name="nombre_qualifies" value="0">
                    </div>
                    <div class="form-group">
                        <label>Finalistes</label>
                        <input type="number" name="nombre_finalistes" value="0">
                    </div>
                    <div class="form-group">
                        <label>Gagnants</label>
                        <input type="number" name="nombre_gagnants" value="1">
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
                        <option value="brouillon">Brouillon</option>
                        <option value="ouvert">Ouvert (Inscriptions lancées)</option>
                        <option value="en_cours">En cours</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="image_affiche">Affiche publicitaire</label>
                    <input type="file" id="image_affiche" name="image_affiche" accept="image/*">
                </div>

                <div class="toggle-group mb-2">
                    <label class="switch-container">
                        <input type="checkbox" id="inscription_obligatoire" name="inscription_obligatoire" checked>
                        <span class="slider"></span>
                    </label>
                    <span class="toggle-label">Inscription en ligne obligatoire</span>
                </div>

                <div class="toggle-group">
                    <label class="switch-container">
                        <input type="checkbox" id="is_public" name="is_public" checked>
                        <span class="slider"></span>
                    </label>
                    <span class="toggle-label">Rendre visible au public</span>
                </div>
            </div>

            <!-- BOUTON DE SOUMISSION -->
            <div class="submit-container mt-4">
                <button type="submit" class="btn btn-success btn-block btn-lg">🚀 Créer le concours complet</button>
            </div>

        </div>

    </div>
</form>

<!-- SCRIPT LOGIQUE ET CLIENT-SIDE POUR LES PHASES -->
<script>
    let phaseIndex = 1;

    // Ajouter dynamiquement une étape
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
                        <input type="text" name="phase_nom[]" required placeholder="Ex: Demi-Finale">
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
                        <input type="text" name="phase_desc[]" placeholder="Critères de validation...">
                    </div>
                </div>
            </div>
        `;
        container.insertAdjacentHTML('beforeend', phaseHTML);
        updatePhaseTitles();
    });

    // Supprimer une étape
    function removePhase(button) {
        const rows = document.querySelectorAll('.phase-row-item');
        if(rows.length > 1) {
            button.closest('.phase-row-item').remove();
            updatePhaseTitles();
        } else {
            alert('Votre concours doit contenir au moins une étape fondamentale.');
        }
    }

    // Réindexer les numéros d'étapes visuels après suppression
    function updatePhaseTitles() {
        const titles = document.querySelectorAll('.phase-row-header h4');
        titles.forEach((title, i) => {
            title.textContent = `Étape N°${i + 1}`;
        });
        phaseIndex = titles.length;
    }

    // Afficher/masquer les frais financiers
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
$content = ob_get_clean();
include "../layout.php";
?>