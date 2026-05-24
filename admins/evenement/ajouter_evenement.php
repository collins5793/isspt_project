<?php
session_start();
require_once '../../includes/db.php';

// Vérifier admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// 1. CHARGER LES ANNÉES ACADÉMIQUES
$years = $pdo->query("SELECT id, label FROM academic_years ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

// 2. CHARGER LES TEMPLATES DE TICKETS ACTIFS
$templates = $pdo->query("SELECT id_template, nom_template, image_path, canvas_width, canvas_height FROM ticket_templates WHERE is_active = 1 ORDER BY nom_template ASC")->fetchAll(PDO::FETCH_ASSOC);

// 3. CHARGER LES LAYOUTS EXISTANTS ET LEUR BACKDROP POUR LA PREVIEW DU CLONE
$existing_layouts = $pdo->query("
    SELECT tl.*, e.nom_evenement, t.image_path, t.canvas_width, t.canvas_height
    FROM ticket_layouts tl
    JOIN evenements e ON tl.event_id = e.id_evenement
    JOIN ticket_templates t ON tl.template_id = t.id_template
    ORDER BY tl.id_layout DESC
")->fetchAll(PDO::FETCH_ASSOC);

// 4. TRAITEMENT DE LA SOUMISSION
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nom_evenement = trim($_POST['nom_evenement']);
    $type_evenement = $_POST['type_evenement'];
    $description = trim($_POST['description']);
    $event_start = $_POST['event_start'];
    $lieu = trim($_POST['lieu']);
    $prix_ticket = !empty($_POST['prix_ticket']) ? floatval($_POST['prix_ticket']) : 0.00;
    $academic_year_id = $_POST['academic_year_id'];
    $cree_par = $_SESSION['admin_id'];
    
    // Gestion des tickets
    $has_ticket = isset($_POST['has_ticket']) ? intval($_POST['has_ticket']) : 0;
    $use_existing_layout = isset($_POST['use_existing_layout']) ? intval($_POST['use_existing_layout']) : 0;
    $selected_layout_id = !empty($_POST['existing_layout_id']) ? intval($_POST['existing_layout_id']) : null;
    
    // Détermination initiale du template_id
    $ticket_template_id = null;
    if ($has_ticket === 1) {
        if ($use_existing_layout === 1 && $selected_layout_id !== null) {
            // Si clone, on extrait le template ID du layout copié
            $stmtCheck = $pdo->prepare("SELECT template_id FROM ticket_layouts WHERE id_layout = ?");
            $stmtCheck->execute([$selected_layout_id]);
            $ticket_template_id = $stmtCheck->fetchColumn() ?: null;
        } else {
            $ticket_template_id = !empty($_POST['ticket_template_id']) ? intval($_POST['ticket_template_id']) : null;
        }
    }

    // Enregistrement de l'événement
    $stmt = $pdo->prepare("
        INSERT INTO evenements (nom_evenement, type_evenement, description, event_start, lieu, prix_ticket, academic_year_id, cree_par, has_ticket, ticket_template_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $nom_evenement, $type_evenement, $description, $event_start,
        $lieu, $prix_ticket, $academic_year_id, $cree_par, $has_ticket, $ticket_template_id
    ]);

    $event_id = $pdo->lastInsertId();

    /* -----------------------------------
        ARTISTES INVITÉS
    ----------------------------------- */
    if (isset($_POST['artist_name']) && is_array($_POST['artist_name'])) {
        $uploadDir = "../uploads/artistes/";
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        foreach ($_POST['artist_name'] as $i => $nom_artiste) {
            if (empty($nom_artiste)) continue;

            $pseudonyme = $_POST['artist_pseudo'][$i] ?? null;
            $description_artiste = $_POST['artist_desc'][$i] ?? null;
            $role = $_POST['artist_role'][$i] ?? null;

            $photoName = null;
            if (!empty($_FILES['artist_photo']['name'][$i])) {
                $fileTmp = $_FILES['artist_photo']['tmp_name'][$i];
                $fileName = time() . "_" . basename($_FILES['artist_photo']['name'][$i]);
                $targetPath = $uploadDir . $fileName;

                if (move_uploaded_file($fileTmp, $targetPath)) {
                    $photoName = $fileName;
                }
            }

            $insertArtist = $pdo->prepare("
                INSERT INTO evenement_artistes (id_evenement, nom_artiste, pseudonyme, description, photo, role)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $insertArtist->execute([
                $event_id, $nom_artiste, $pseudonyme, $description_artiste, $photoName, $role
            ]);
        }
    }

    /* -----------------------------------
        LOGIQUE DE DUPLICATION ET REDIRECTION
    ----------------------------------- */
    if ($has_ticket === 1) {
        if ($use_existing_layout === 1 && $selected_layout_id !== null) {
            $stmtLayout = $pdo->prepare("SELECT * FROM ticket_layouts WHERE id_layout = ?");
            $stmtLayout->execute([$selected_layout_id]);
            $oldLayout = $stmtLayout->fetch(PDO::FETCH_ASSOC);

            if ($oldLayout) {
                $sqlDuplicate = "INSERT INTO ticket_layouts (
                                    template_id, event_id, layout_name, 
                                    event_name_x, event_name_y, event_name_font_size, event_name_color, event_name_font_weight, event_name_font_family,
                                    event_date_x, event_date_y, event_date_font_size, event_date_color, event_date_font_weight, event_date_font_family,
                                    event_location_x, event_location_y, event_location_font_size, event_location_color,
                                    participant_name_x, participant_name_y, participant_name_font_size, participant_name_color,
                                    ticket_code_x, ticket_code_y, ticket_code_font_size, ticket_code_color,
                                    qr_x, qr_y, qr_width, qr_height, logo_x, logo_y, logo_width, logo_height
                                ) VALUES (
                                    :template_id, :event_id, :layout_name,
                                    :en_x, :en_y, :en_size, :en_color, :en_weight, :en_family,
                                    :ed_x, :ed_y, :ed_size, :ed_color, :ed_weight, :ed_family,
                                    :el_x, :el_y, :el_size, :el_color,
                                    :pn_x, :pn_y, :pn_size, :pn_color,
                                    :tc_x, :tc_y, :tc_size, :tc_color,
                                    :qr_x, :qr_y, :qr_w, :qr_h, :logo_x, :logo_y, :logo_w, :logo_h
                                )";
                
                $stmtDup = $pdo->prepare($sqlDuplicate);
                $stmtDup->execute([
                    ':template_id' => $ticket_template_id,
                    ':event_id'    => $event_id,
                    ':layout_name' => "Layout cloné pour - " . $nom_evenement,
                    ':en_x' => $oldLayout['event_name_x'], ':en_y' => $oldLayout['event_name_y'], ':en_size' => $oldLayout['event_name_font_size'], ':en_color' => $oldLayout['event_name_color'], ':en_weight' => $oldLayout['event_name_font_weight'], ':en_family' => $oldLayout['event_name_font_family'],
                    ':ed_x' => $oldLayout['event_date_x'], ':ed_y' => $oldLayout['event_date_y'], ':ed_size' => $oldLayout['event_date_font_size'], ':ed_color' => $oldLayout['event_date_color'], ':ed_weight' => $oldLayout['event_date_font_weight'], ':ed_family' => $oldLayout['event_date_font_family'],
                    ':el_x' => $oldLayout['event_location_x'], ':el_y' => $oldLayout['event_location_y'], ':el_size' => $oldLayout['event_location_font_size'], ':el_color' => $oldLayout['event_location_color'],
                    ':pn_x' => $oldLayout['participant_name_x'], ':pn_y' => $oldLayout['participant_name_y'], ':pn_size' => $oldLayout['participant_name_font_size'], ':pn_color' => $oldLayout['participant_name_color'],
                    ':tc_x' => $oldLayout['ticket_code_x'], ':tc_y' => $oldLayout['ticket_code_y'], ':tc_size' => $oldLayout['ticket_code_font_size'], ':tc_color' => $oldLayout['ticket_code_color'],
                    ':qr_x' => $oldLayout['qr_x'], ':qr_y' => $oldLayout['qr_y'], ':qr_w' => $oldLayout['qr_width'], ':qr_h' => $oldLayout['qr_height'],
                    ':logo_x' => $oldLayout['logo_x'], ':logo_y' => $oldLayout['logo_y'], ':logo_w' => $oldLayout['logo_width'], ':logo_h' => $oldLayout['logo_height']
                ]);

                $new_layout_id = $pdo->lastInsertId();

                $updateEvent = $pdo->prepare("UPDATE evenements SET layout_id = ? WHERE id_evenement = ?");
                $updateEvent->execute([$new_layout_id, $event_id]);

                header("Location: evenements.php?success=1");
                exit;
            }
        }
        
        // Mode manuel -> Studio d'édition
        header("Location: ajouter_layout.php?id=" . $event_id);
    } else {
        header("Location: evenements.php?success=1");
    }
    exit;
}

ob_start();
?>
<style>
    /* ==========================================================================
       1. CONTENEURS & HOUSING GENERAL
       ========================================================================== */
    form.card {
        background-color: var(--sidebar-bg);
        border: var(--sidebar-border);
        box-shadow: var(--shadow-xl);
        border-radius: var(--radius-xl);
        padding: var(--space-6) !important;
        position: relative;
        overflow: hidden;
    }

    form.card::before {
        content: "";
        position: absolute;
        top: 0; left: 0; width: 100%; height: 4px;
        background: linear-gradient(90deg, var(--accent-blue), var(--accent-green), var(--accent-red));
    }

    h2 {
        font-family: var(--font-primary);
        color: var(--white);
        font-weight: 700;
        letter-spacing: -0.5px;
    }

    h4 {
        font-family: var(--font-primary);
        color: var(--white);
        font-weight: 600;
        margin-top: var(--space-4);
        display: flex;
        align-items: center;
        gap: var(--space-2);
    }

    hr {
        border-color: rgba(255, 255, 255, 0.08);
        margin: var(--space-6) 0;
    }

    /* ==========================================================================
       2. FORMULAIRES & INPUTS PREMIUM DARK
       ========================================================================== */
    .form-label {
        color: var(--gray-300);
        font-size: var(--font-size-sm);
        font-weight: 600;
        margin-bottom: var(--space-2);
        display: inline-block;
    }

    .form-control, .form-select, select.form-control {
        background-color: var(--primary-700);
        border: 1px solid rgba(255, 255, 255, 0.1);
        color: var(--white) !important;
        padding: 0.75rem var(--space-4);
        border-radius: var(--radius-md);
        font-family: var(--font-primary);
        font-size: var(--font-size-md);
        transition: var(--transition-base);
    }

    /* Gestion du focus pour éviter les liserés par défaut du navigateur */
    .form-control:focus, .form-select:focus, select.form-control:focus {
        background-color: var(--primary-600);
        border-color: var(--accent-blue);
        box-shadow: 0 0 0 4px rgba(46, 134, 222, 0.2);
        outline: none;
    }

    /* Placeholder en gris stylisé */
    .form-control::placeholder {
        color: var(--gray-400);
        opacity: 0.7;
    }

    /* Fix pour l'icône de sélection webkit sur thème sombre */
    select.form-control {
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23ced6e0' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e");
        background-repeat: no-repeat;
        background-position: right 0.75rem center;
        background-size: 16px 12px;
        appearance: none;
    }

    /* ==========================================================================
       3. GRILLES FLUIDES (LAYOUT)
       ========================================================================== */
    .grid {
        display: grid;
        gap: var(--space-4);
    }
    .grid-cols-1 { grid-template-columns: repeat(1, minmax(0, 1fr)); }

    /* ==========================================================================
       4. CARTES DE SÉLECTION (TEMPLATES & LAYOUTS CLONABLES)
       ========================================================================== */
    .template-card, .layout-card {
        cursor: pointer;
        background: var(--primary-700) !important;
        border: 1px solid rgba(255, 255, 255, 0.08) !important;
        border-radius: var(--radius-lg);
        padding: var(--space-3);
        height: 100%;
        transition: var(--transition-base);
        display: flex;
        flex-direction: column;
    }

    .template-card:hover, .layout-card:hover {
        transform: translateY(-4px);
        border-color: rgba(255, 255, 255, 0.2) !important;
        box-shadow: var(--shadow-lg);
    }

    /* État coché (Liaison Radio CSS) */
    .template-radio:checked + .template-card,
    .layout-radio:checked + .layout-card {
        border-color: var(--accent-blue) !important;
        background-color: var(--primary-600) !important;
        box-shadow: 0 0 0 3px rgba(46, 134, 222, 0.4);
    }

    .template-card img {
        border-radius: var(--radius-md);
        transition: var(--transition-fast);
    }

    /* Zone de textes sous les vignettes */
    .template-card p, .layout-card p {
        margin: 0;
    }

    /* ==========================================================================
       5. MINI-PREVIEW DU STUDIO (TICKET COMPOSER MINIATURE)
       ========================================================================== */
    .mini-preview-container {
        position: relative;
        width: 100%;
        background-size: cover;
        background-repeat: no-repeat;
        background-position: center;
        background-color: var(--primary-900);
        border: 1px solid rgba(255, 255, 255, 0.1) !important;
        border-radius: var(--radius-md);
        overflow: hidden;
        box-shadow: var(--shadow-sm);
    }

    .mini-element {
        position: absolute;
        white-space: nowrap;
        line-height: 1;
        pointer-events: none;
        transform-origin: top left;
        font-family: var(--font-primary);
    }

    /* ==========================================================================
       6. BLOCS ARTISTES DYNAMIQUES
       ========================================================================== */
    .artist-box {
        border: 1px solid rgba(255, 255, 255, 0.08);
        padding: var(--space-5);
        border-radius: var(--radius-lg);
        background: rgba(255, 255, 255, 0.02);
        margin-bottom: var(--space-4);
        position: relative;
        transition: var(--transition-base);
    }

    .artist-box:hover {
        border-color: rgba(255, 255, 255, 0.15);
        background: rgba(255, 255, 255, 0.04);
    }

    .remove-btn {
        position: absolute;
        top: var(--space-3);
        right: var(--space-3);
        cursor: pointer;
        color: var(--accent-red);
        font-weight: 700;
        background: rgba(255, 71, 87, 0.1);
        width: 28px;
        height: 28px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: var(--radius-full);
        transition: var(--transition-fast);
    }

    .remove-btn:hover {
        background: var(--accent-red);
        color: var(--white);
    }

    /* ==========================================================================
       7. BOUTONS & INTERACTIONS ACCENTS
       ========================================================================== */
    .btn {
        font-weight: 600;
        font-family: var(--font-primary);
        border-radius: var(--radius-md);
        padding: 0.625rem var(--space-4);
        transition: var(--transition-base);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: var(--space-2);
        border: none;
    }

    .btn-primary {
        background: var(--accent-blue);
        color: var(--white) !important;
        box-shadow: 0 4px 12px rgba(46, 134, 222, 0.3);
    }

    .btn-primary:hover {
        background: #2475c9;
        transform: translateY(-2px);
        box-shadow: 0 6px 18px rgba(46, 134, 222, 0.4);
    }

    .btn-secondary {
        background: transparent;
        color: var(--gray-200) !important;
        border: 1px solid rgba(255, 255, 255, 0.2);
    }

    .btn-secondary:hover {
        background: rgba(255, 255, 255, 0.05);
        color: var(--white) !important;
        border-color: rgba(255, 255, 255, 0.4);
    }

    /* Alerte Bootstrap customisée */
    .alert-info, .alert-warning {
        background-color: var(--primary-600);
        border: 1px dashed rgba(255, 255, 255, 0.1);
        color: var(--gray-200);
        border-radius: var(--radius-md);
    }

    /* Wrapper de fond pour la méthode de configuration visuelle */
    .bg-light.p-3.rounded.border {
        background-color: rgba(255, 255, 255, 0.02) !important;
        border: 1px solid rgba(255, 255, 255, 0.05) !important;
        padding: var(--space-4) !important;
        border-radius: var(--radius-lg) !important;
    }

    /* ==========================================================================
       8. RESPONSIVITÉ SANS FAILLE (MEDIA QUERIES)
       ========================================================================== */
    /* Tablettes et écrans intermédiaires */
    @media (min-width: 768px) {
        .md\:grid-cols-2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .md\:grid-cols-3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    }

    /* Smartphones et Phablettes */
    @media (min-width: 640px) {
        .sm\:grid-cols-2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }

    /* Grands Écrans Desktops */
    @media (min-width: 1024px) {
        .lg\:grid-cols-3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    }

    /* Optimisations spécifiques pour écrans très étroits (< 576px) */
    @media (max-width: 576px) {
        form.card {
            padding: var(--space-4) !important;
        }
        .flex.justify-end {
            width: 100%;
        }
        .btn {
            width: 100%; /* Les boutons prennent toute la largeur sur mobile pour une meilleure UX d'empreinte */
        }
    }
</style>

<h2 class="mb-4">➕ Ajouter un Événement</h2>

<form method="POST" enctype="multipart/form-data" class="card p-4 text-start">

    <!-- Informations Principales -->
    <div class="mb-3">
        <label class="form-label">Nom de l'événement</label>
        <input type="text" name="nom_evenement" class="form-control" required>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-3">
        <div>
            <label class="form-label">Type</label>
            <select name="type_evenement" class="form-control" required>
                <option value="sortie">Sortie</option>
                <option value="soiree">Soirée</option>
                <option value="concert">Concert</option>
                <option value="competition">Compétition</option>
                <option value="autre">Autre</option>
            </select>
        </div>
        <div>
            <label class="form-label">Année Académique</label>
            <select name="academic_year_id" class="form-control" required>
                <?php foreach($years as $year): ?>
                    <option value="<?= $year['id'] ?>"><?= htmlspecialchars($year['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="mb-3">
        <label class="form-label">Description</label>
        <textarea name="description" class="form-control" rows="3"></textarea>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-3">
        <div>
            <label class="form-label">Date & Heure</label>
            <input type="datetime-local" name="event_start" class="form-control" required>
        </div>
        <div>
            <label class="form-label">Lieu</label>
            <input type="text" name="lieu" class="form-control">
        </div>
        <div>
            <label class="form-label">Prix du ticket (FCFA)</label>
            <input type="number" name="prix_ticket" class="form-control" step="0.01" min="0" value="0.00">
        </div>
    </div>

    <hr class="my-4">

    <!-- 🎫 SECTION GESTION DU TICKET & MODÈLES VISUELS -->
    <h4 class="mb-3">🎫 Billetterie & Accès</h4>
    
    <div class="mb-4">
        <label class="form-label">Cet événement nécessite-t-il un ticket ?</label>
        <select name="has_ticket" id="has_ticket" class="form-control" onchange="toggleTicketFields()" required>
            <option value="0">Non, entrée libre / Pas de ticket système</option>
            <option value="1">Oui, générer des tickets d'accès</option>
        </select>
    </div>
    
    <!-- Wrapper Global Billetterie -->
    <div id="global_ticket_wrapper" style="display: none;" class="space-y-6">
        
        <!-- CHOIX FLUX : MANUEL OU CLONE -->
        <div class="bg-light p-3 rounded border">
            <label class="form-label fw-bold block mb-2">Méthode de configuration visuelle</label>
            <select name="use_existing_layout" id="use_existing_layout" class="form-control" onchange="switchWorkflow()">
                <option value="0">Créer une nouvelle configuration manuelle (Redirection vers le Studio)</option>
                <option value="1">Copier et cloner la configuration d'un modèle déjà existant</option>
            </select>
        </div>

        <!-- FLUX A : SÉLECTION DU TEMPLATE VIERGE (S'affiche si Nouveau / Manuel) -->
        <div id="manual_template_wrapper" class="space-y-3">
            <label class="form-label fw-bold block text-gray-800">Sélectionnez le modèle graphique (Template) de fond *</label>
            <?php if (empty($templates)): ?>
                <div class="alert alert-warning">
                    Aucun template actif trouvé. <a href="ajouter_template.php" class="alert-link">Créez un modèle d'abord</a>.
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                    <?php foreach($templates as $tmpl): ?>
                        <div class="relative">
                            <input type="radio" name="ticket_template_id" id="tmpl_<?= $tmpl['id_template'] ?>" 
                                   value="<?= $tmpl['id_template'] ?>" class="template-radio sr-only">
                            <label for="tmpl_<?= $tmpl['id_template'] ?>" class="template-card block bg-white rounded-lg overflow-hidden border p-2 h-full">
                                <div class="w-full h-36 bg-gray-100 rounded mb-2 overflow-hidden flex items-center justify-center">
                                    <img src="../../<?= htmlspecialchars($tmpl['image_path']) ?>" class="w-full h-full object-cover" alt="Template">
                                </div>
                                <div class="px-1">
                                    <p class="text-sm font-semibold text-gray-800 mb-0 truncate"><?= htmlspecialchars($tmpl['nom_template']) ?></p>
                                    <p class="text-gray-400 text-[11px] mb-0">Dim : <?= $tmpl['canvas_width'] ?> × <?= $tmpl['canvas_height'] ?> px</p>
                                </div>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- FLUX B : CLONAGE ET PRÉVISUALISATION DU LAYOUT (S'affiche si Duplication) -->
        <div id="clone_layout_wrapper" style="display: none;" class="space-y-3">
            <label class="form-label fw-bold block text-blue-900">Sélectionnez le Layout complet à cloner (Aperçu réel ci-dessous) *</label>
            <?php if (empty($existing_layouts)): ?>
                <div class="alert alert-info">Aucun layout existant n'est disponible pour le clonage. Veuillez faire une configuration manuelle.</div>
            <?php else: ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach($existing_layouts as $lay): 
                        // Calcul d'échelle pour l'aperçu miniature (ex: base largeur 300px)
                        $preview_width = 300;
                        $scale = $preview_width / $lay['canvas_width'];
                        $preview_height = $lay['canvas_height'] * $scale;
                    ?>
                        <div class="relative">
                            <input type="radio" name="existing_layout_id" id="layout_<?= $lay['id_layout'] ?>" 
                                   value="<?= $lay['id_layout'] ?>" class="layout-radio sr-only">
                            <label for="layout_<?= $lay['id_layout'] ?>" class="layout-card block bg-white rounded-xl overflow-hidden border p-3 h-full">
                                
                                <!-- Mini Rendu Visuel Réel Dynamique -->
                                <div class="mini-preview-container rounded mb-3 border border-gray-300 shadow-inner" 
                                     style="height: <?= $preview_height ?>px; background-image: url('../../<?= htmlspecialchars($lay['image_path']) ?>');">
                                    
                                    <!-- Nom Événement -->
                                    <div class="mini-element font-bold" style="left:<?= $lay['event_name_x'] * $scale ?>px; top:<?= $lay['event_name_y'] * $scale ?>px; font-size:<?= $lay['event_name_font_size'] * $scale ?>px; color:<?= $lay['event_name_color'] ?>;">[Nom Event]</div>
                                    <!-- Participant -->
                                    <div class="mini-element" style="left:<?= $lay['participant_name_x'] * $scale ?>px; top:<?= $lay['participant_name_y'] * $scale ?>px; font-size:<?= $lay['participant_name_font_size'] * $scale ?>px; color:<?= $lay['participant_name_color'] ?>;">[Nom Participant]</div>
                                    <!-- Code Ticket -->
                                    <div class="mini-element font-mono" style="left:<?= $lay['ticket_code_x'] * $scale ?>px; top:<?= $lay['ticket_code_y'] * $scale ?>px; font-size:<?= $lay['ticket_code_font_size'] * $scale ?>px; color:<?= $lay['ticket_code_color'] ?>;">#T-XYZ</div>
                                    
                                    <!-- Zone QR Code miniature -->
                                    <div class="mini-element bg-white border border-black flex items-center justify-center font-bold text-[6px]" 
                                         style="left:<?= $lay['qr_x'] * $scale ?>px; top:<?= $lay['qr_y'] * $scale ?>px; width:<?= $lay['qr_width'] * $scale ?>px; height:<?= $lay['qr_height'] * $scale ?>px;">QR</div>
                                    
                                    <!-- Zone Logo miniature -->
                                    <div class="mini-element bg-gray-300/80 border border-gray-400 flex items-center justify-center font-bold text-[5px]" 
                                         style="left:<?= $lay['logo_x'] * $scale ?>px; top:<?= $lay['logo_y'] * $scale ?>px; width:<?= $lay['logo_width'] * $scale ?>px; height:<?= $lay['logo_height'] * $scale ?>px;">LOGO</div>
                                </div>

                                <div class="border-t pt-2">
                                    <p class="text-sm font-bold text-gray-900 mb-0 truncate"><?= htmlspecialchars($lay['layout_name']) ?></p>
                                    <p class="text-xs text-blue-600 mb-1 truncate">Source : <?= htmlspecialchars($lay['nom_evenement']) ?></p>
                                </div>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <hr class="my-4">

    <!-- Section Artistes -->
    <h4>🎤 Artistes Invités</h4>
    <div id="artists_container"></div>
    <button type="button" class="btn btn-secondary mb-3" onclick="addArtist()">+ Ajouter un artiste</button>

    <hr class="my-4">

    <div class="flex justify-end">
        <button type="submit" class="btn btn-primary px-5 py-2.5">Enregistrer l'événement</button>
    </div>

</form>

<script>
// Gestion de l'affichage global de la section Billetterie
function toggleTicketFields() {
    const hasTicket = document.getElementById('has_ticket').value;
    const globalWrapper = document.getElementById('global_ticket_wrapper');

    if (hasTicket === "1") {
        globalWrapper.style.display = "block";
        switchWorkflow(); // Applique les contraintes requises du workflow actif
    } else {
        globalWrapper.style.display = "none";
        clearRequirements();
    }
}

// Commutateur intelligent entre Création Manuelle et Clonage de Layout
function switchWorkflow() {
    const useExistingLayout = document.getElementById('use_existing_layout').value;
    const manualWrapper = document.getElementById('manual_template_wrapper');
    const cloneWrapper = document.getElementById('clone_layout_wrapper');
    
    const templateRadios = document.getElementsByName('ticket_template_id');
    const layoutRadios = document.getElementsByName('existing_layout_id');

    if (useExistingLayout === "0") {
        // Mode Manuel : On montre les templates vierges, on cache les clones
        manualWrapper.style.display = "block";
        cloneWrapper.style.display = "none";
        
        if(templateRadios.length > 0) templateRadios[0].setAttribute('required', 'required');
        if(layoutRadios.length > 0) layoutRadios[0].removeAttribute('required');
        
        // Reset choix opposé
        layoutRadios.forEach(r => r.checked = false);
    } else {
        // Mode Clone : On cache les templates vierges, on montre la mosaïque de clones
        manualWrapper.style.display = "none";
        cloneWrapper.style.display = "block";
        
        if(layoutRadios.length > 0) layoutRadios[0].setAttribute('required', 'required');
        if(templateRadios.length > 0) templateRadios[0].removeAttribute('required');
        
        // Reset choix opposé
        templateRadios.forEach(r => r.checked = false);
    }
}

function clearRequirements() {
    const templateRadios = document.getElementsByName('ticket_template_id');
    const layoutRadios = document.getElementsByName('existing_layout_id');
    
    if(templateRadios.length > 0) templateRadios[0].removeAttribute('required');
    if(layoutRadios.length > 0) layoutRadios[0].removeAttribute('required');
    
    templateRadios.forEach(r => r.checked = false);
    layoutRadios.forEach(r => r.checked = false);
}

function addArtist() {
    const container = document.getElementById('artists_container');
    const html = `
        <div class="artist-box text-start">
            <div class="text-end"><span class="remove-btn" onclick="this.parentElement.parentElement.remove()">X</span></div>
            <label class="form-label">Nom de l'artiste</label>
            <input type="text" name="artist_name[]" class="form-control mb-2" required>
            <label class="form-label">Pseudonyme</label>
            <input type="text" name="artist_pseudo[]" class="form-control mb-2">
            <label class="form-label">Rôle (Chanteur, DJ, Animateur…)</label>
            <input type="text" name="artist_role[]" class="form-control mb-2">
            <label class="form-label">Description</label>
            <textarea name="artist_desc[]" class="form-control mb-2"></textarea>
            <label class="form-label">Photo</label>
            <input type="file" name="artist_photo[]" accept="image/*" class="form-control">
        </div>
    `;
    container.insertAdjacentHTML('beforeend', html);
}
</script>

<?php
$content = ob_get_clean();
include "../layout.php";
?>