<?php
session_start();
require_once '../../includes/db.php';

// Vérifier l'accès admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// Vérifier la présence de l'ID de l'événement à modifier
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: evenements.php?error=missing_id");
    exit;
}

$id_evenement = intval($_GET['id']);

// 1. CHARGER LES DONNÉES DE L'ÉVÉNEMENT ACTUEL
$stmtEvent = $pdo->prepare("SELECT * FROM evenements WHERE id_evenement = ?");
$stmtEvent->execute([$id_evenement]);
$event = $stmtEvent->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    header("Location: evenements.php?error=not_found");
    exit;
}

// 2. CHARGER LES ARTISTES ACTUELS DE L'ÉVÉNEMENT
$stmtArtists = $pdo->prepare("SELECT * FROM evenement_artistes WHERE id_evenement = ? ");
$stmtArtists->execute([$id_evenement]);
$current_artists = $stmtArtists->fetchAll(PDO::FETCH_ASSOC);

// 3. CHARGER LES ANNÉES ACADÉMIQUES
$years = $pdo->query("SELECT id, label FROM academic_years ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

// 4. CHARGER LES TEMPLATES DE TICKETS ACTIFS
$templates = $pdo->query("SELECT id_template, nom_template, image_path, canvas_width, canvas_height FROM ticket_templates WHERE is_active = 1 ORDER BY nom_template ASC")->fetchAll(PDO::FETCH_ASSOC);

// 5. CHARGER LES LAYOUTS EXISTANTS (Exclure le layout actuel de l'événement pour éviter l'auto-clonage)
$existing_layouts = $pdo->query("
    SELECT tl.*, e.nom_evenement, t.image_path, t.canvas_width, t.canvas_height
    FROM ticket_layouts tl
    JOIN evenements e ON tl.event_id = e.id_evenement
    JOIN ticket_templates t ON tl.template_id = t.id_template
    WHERE e.id_evenement != $id_evenement
    ORDER BY tl.id_layout DESC
")->fetchAll(PDO::FETCH_ASSOC);

// 6. TRAITEMENT DE LA SOUMISSION DU FORMULAIRE (MISE À JOUR)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nom_evenement = trim($_POST['nom_evenement']);
    $type_evenement = $_POST['type_evenement'];
    $description = trim($_POST['description']);
    $event_start = $_POST['event_start'];
    $lieu = trim($_POST['lieu']);
    $prix_ticket = !empty($_POST['prix_ticket']) ? floatval($_POST['prix_ticket']) : 0.00;
    $academic_year_id = $_POST['academic_year_id'];
    
    // Gestion des tickets
    $has_ticket = isset($_POST['has_ticket']) ? intval($_POST['has_ticket']) : 0;
    $use_existing_layout = isset($_POST['use_existing_layout']) ? intval($_POST['use_existing_layout']) : 0;
    $selected_layout_id = !empty($_POST['existing_layout_id']) ? intval($_POST['existing_layout_id']) : null;
    
    // Détermination dynamique du template_id
    $ticket_template_id = $event['ticket_template_id']; // Par défaut, on garde l'ancien
    $layout_id = $event['layout_id']; // Par défaut, on garde l'ancien layout

    if ($has_ticket === 1) {
        if ($use_existing_layout === 1 && $selected_layout_id !== null) {
            // Si l'utilisateur choisit de cloner un autre layout
            $stmtCheck = $pdo->prepare("SELECT template_id FROM ticket_layouts WHERE id_layout = ?");
            $stmtCheck->execute([$selected_layout_id]);
            $ticket_template_id = $stmtCheck->fetchColumn() ?: null;
        } else {
            // Mode manuel : Si le template change, on récupère le nouveau choix
            $ticket_template_id = !empty($_POST['ticket_template_id']) ? intval($_POST['ticket_template_id']) : $event['ticket_template_id'];
        }
    } else {
        $ticket_template_id = null;
        $layout_id = null;
    }

    // Mise à jour de la table evenements
    $stmtUpdate = $pdo->prepare("
        UPDATE evenements 
        SET nom_evenement = ?, type_evenement = ?, description = ?, event_start = ?, 
            lieu = ?, prix_ticket = ?, academic_year_id = ?, has_ticket = ?, ticket_template_id = ?
        WHERE id_evenement = ?
    ");
    $stmtUpdate->execute([
        $nom_evenement, $type_evenement, $description, $event_start,
        $lieu, $prix_ticket, $academic_year_id, $has_ticket, $ticket_template_id, $id_evenement
    ]);

    /* -----------------------------------
        TRAITEMENT DES ARTISTES (RESET & RE-INSERT)
    ----------------------------------- */
    // Option propre : Supprimer les anciens enregistrements pour réinsérer proprement (conserve les anciennes images si non modifiées)
    $uploadDir = "../uploads/artistes/";
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    // Récupérer la liste des anciennes photos pour ne pas les perdre
    $old_photos = [];
    foreach ($current_artists as $ca) {
        $old_photos[$ca['id_artiste']] = $ca['photo'];
    }

    // Vider la table des artistes liés à cet événement
    $pdo->prepare("DELETE FROM evenement_artistes WHERE id_evenement = ?")->execute([$id_evenement]);

    if (isset($_POST['artist_name']) && is_array($_POST['artist_name'])) {
        foreach ($_POST['artist_name'] as $i => $nom_artiste) {
            if (empty($nom_artiste)) continue;

            $pseudonyme = $_POST['artist_pseudo'][$i] ?? null;
            $description_artiste = $_POST['artist_desc'][$i] ?? null;
            $role = $_POST['artist_role'][$i] ?? null;
            $artist_id = $_POST['artist_id'][$i] ?? null;

            // Récupération par défaut de l'ancienne photo si elle existe
            $photoName = (!empty($artist_id) && isset($old_photos[$artist_id])) ? $old_photos[$artist_id] : null;

            // Remplacement si un nouveau fichier est fourni
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
                $id_evenement, $nom_artiste, $pseudonyme, $description_artiste, $photoName, $role
            ]);
        }
    }

    /* -----------------------------------
        LOGIQUE DE RE-DUPLICATION ET REDIRECTION
    ----------------------------------- */
    if ($has_ticket === 1) {
        if ($use_existing_layout === 1 && $selected_layout_id !== null) {
            // L'utilisateur demande explicitement le clonage d'un modèle structurel
            $stmtLayout = $pdo->prepare("SELECT * FROM ticket_layouts WHERE id_layout = ?");
            $stmtLayout->execute([$selected_layout_id]);
            $oldLayout = $stmtLayout->fetch(PDO::FETCH_ASSOC);

            if ($oldLayout) {
                // Insérer le nouveau layout cloné
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
                    ':event_id'    => $id_evenement,
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
                $pdo->prepare("UPDATE evenements SET layout_id = ? WHERE id_evenement = ?")->execute([$new_layout_id, $id_evenement]);
                
                header("Location: evenements.php?success=2");
                exit;
            }
        }
        
        // Si manuel et qu'aucun layout n'existait, on l'envoie vers l'éditeur de layout
        if (empty($event['layout_id']) && $use_existing_layout === 0) {
            header("Location: ajouter_layout.php?id=" . $id_evenement);
            exit;
        }
    }

    header("Location: evenements.php?success=2");
    exit;
}

ob_start();
?>
<style>
    /* ==========================================================================
       CONGRUENCE DES VARIABLES COMPLÈTES (DARK THEME)
       ========================================================================== */
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
        --font-size-xs: 0.75rem;   /* 12px */
        --font-size-sm: 0.875rem;  /* 14px */
        --font-size-md: 1rem;      /* 16px */
        --font-size-lg: 1.125rem;  /* 18px */
        --font-size-xl: 1.25rem;   /* 20px */
        
        --space-1: 0.25rem;   /* 4px */
        --space-2: 0.5rem;    /* 8px */
        --space-3: 0.75rem;   /* 12px */
        --space-4: 1rem;      /* 16px */
        --space-5: 1.5rem;    /* 24px */
        --space-6: 2rem;      /* 32px */
        
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

    /* ==========================================================================
       RESET APPLICATIF & CONFIGURATION GLOBALE
       ========================================================================== */
    *, *::before, *::after {
        box-sizing: border-box;
    }

    /* Conteneur principal de la page */
    .page-wrapper {
        font-family: var(--font-primary);
        color: var(--gray-100);
        max-width: 1300px;
        margin: 0 auto;
        padding: var(--space-4) var(--space-4) var(--space-6) var(--space-4);
    }

    /* Titre principal de la page */
    h2.mb-4 {
        font-size: 1.6rem;
        font-weight: 700;
        color: var(--white);
        margin: 0 0 var(--space-5) 0;
        letter-spacing: -0.5px;
        display: flex;
        align-items: center;
        gap: var(--space-2);
    }

    h4 {
        color: var(--white);
        font-size: var(--font-size-lg);
        font-weight: 600;
        margin-top: 0;
        margin-bottom: var(--space-4);
        display: flex;
        align-items: center;
        gap: var(--space-2);
    }

    /* Séparateurs horizontaux harmonieux */
    hr.my-4 {
        border: 0;
        border-top: 1px solid rgba(255, 255, 255, 0.08);
        margin: var(--space-6) 0;
    }

    /* ==========================================================================
       BLOC CONTENEUR PRINCIPAL (CARD FORMULAIRE)
       ========================================================================== */
    .card {
        background-color: var(--primary-800);
        border: var(--sidebar-border);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-xl);
        padding: var(--space-5) !important;
        transition: var(--transition-base);
    }

    /* ==========================================================================
       CONTRÔLES DE FORMULAIRE (INPUTS, SELECTS, TEXTAREAS)
       ========================================================================== */
    .form-label {
        display: block;
        color: var(--gray-300);
        font-size: var(--font-size-sm);
        font-weight: 500;
        margin-bottom: var(--space-2);
    }

    .form-control {
        display: block;
        width: 100%;
        background-color: var(--primary-900);
        border: 1px solid var(--primary-600);
        color: var(--white);
        padding: 0.65rem var(--space-4);
        border-radius: var(--radius-md);
        font-size: var(--font-size-md);
        font-family: var(--font-primary);
        transition: var(--transition-fast);
    }

    .form-control:focus {
        outline: none;
        border-color: var(--accent-blue);
        background-color: var(--primary-700);
        box-shadow: 0 0 0 3px rgba(46, 134, 222, 0.15);
    }

    select.form-control {
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%23a4b0be'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right var(--space-4) center;
        background-size: 16px;
        padding-right: var(--space-6);
    }

    textarea.form-control {
        resize: vertical;
        min-height: 90px;
    }

    /* Input type file maquillé proprement */
    input[type="file"].form-control {
        padding: 0.5rem;
        cursor: pointer;
    }
    input[type="file"].form-control::file-selector-button {
        background: var(--primary-600);
        border: none;
        color: var(--white);
        padding: 0.35rem var(--space-3);
        border-radius: var(--radius-sm);
        margin-right: var(--space-3);
        cursor: pointer;
        font-size: var(--font-size-xs);
        transition: var(--transition-fast);
    }
    input[type="file"].form-control::file-selector-button:hover {
        background: var(--primary-700);
    }

    /* ==========================================================================
       SYSTÈME DE GRILLE SANS TAILWIND (REMPLACE GRID-COLS-X / GAP-X)
       ========================================================================== */
    .grid {
        display: grid;
        gap: var(--space-4);
    }

    .grid-cols-1 { grid-template-columns: repeat(1, minmax(0, 1fr)); }
    
    @media (min-width: 768px) {
        .md\:grid-cols-2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .md\:grid-cols-3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    }
    
    @media (min-width: 1024px) {
        .lg\:grid-cols-3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    }

    /* Espacements utilitaires natifs */
    .mb-2 { margin-bottom: var(--space-2); }
    .mb-3 { margin-bottom: var(--space-4); }
    .mb-4 { margin-bottom: var(--space-5); }
    .space-y-3 > * + * { margin-top: var(--space-4); }
    .space-y-6 > * + * { margin-top: var(--space-6); }

    /* Wrappers de flux fluides */
    .bg-light.p-3 {
        background-color: var(--primary-700) !important;
        border: 1px solid rgba(255, 255, 255, 0.05) !important;
        border-radius: var(--radius-md);
        padding: var(--space-4) !important;
    }

    .fw-bold { font-weight: 600; }
    .block { display: block; }
    .text-gray-800, .text-blue-900 { color: var(--gray-100) !important; }

    /* ==========================================================================
       CONCEPTION VISUELLE : TEMPLATES & LAYOUT CARDS (RADIO CUSTOM)
       ========================================================================== */
    .sr-only {
        position: absolute;
        width: 1px;
        height: 1px;
        padding: 0;
        margin: -1px;
        overflow: hidden;
        clip: rect(0, 0, 0, 0);
        white-space: nowrap;
        border-width: 0;
    }

    .template-card, .layout-card {
        cursor: pointer;
        background-color: var(--primary-900) !important;
        border: 1px solid var(--primary-600) !important;
        border-radius: var(--radius-lg);
        padding: var(--space-3) !important;
        height: 100%;
        display: flex;
        flex-direction: column;
        transition: var(--transition-base);
    }

    .template-card:hover, .layout-card:hover {
        transform: translateY(-4px);
        border-color: var(--gray-400) !important;
        box-shadow: var(--shadow-lg);
    }

    /* État coché (Checked) */
    .template-radio:checked + .template-card,
    .layout-radio:checked + .layout-card {
        border-color: var(--accent-blue) !important;
        background-color: var(--primary-700) !important;
        box-shadow: 0 0 0 3px rgba(46, 134, 222, 0.3);
    }

    .template-card img {
        width: 100%;
        height: 130px;
        object-fit: cover;
        border-radius: var(--radius-md);
        background-color: var(--primary-800);
    }

    .template-card p {
        margin: 0;
    }
    .template-card .text-sm {
        color: var(--white);
        font-weight: 600;
        margin-top: var(--space-2);
    }
    .template-card .text-gray-400 {
        color: var(--gray-400);
        font-size: var(--font-size-xs);
        margin-top: var(--space-1);
    }

    /* Prévisualisations miniatures des Layouts dynamiques */
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
        box-shadow: inset 0 2px 8px rgba(0,0,0,0.5);
    }

    .mini-element {
        position: absolute;
        white-space: nowrap;
        line-height: 1;
        pointer-events: none;
        transform-origin: top left;
        padding: 1px 3px;
        border-radius: 2px;
        background: rgba(0, 0, 0, 0.4);
    }

    .layout-card .border-t {
        border-top: 1px solid rgba(255, 255, 255, 0.08) !important;
        margin-top: var(--space-3);
        padding-top: var(--space-2);
    }
    .layout-card p { margin: 0; }
    .layout-card .text-gray-900 { color: var(--white) !important; font-weight: 600; font-size: var(--font-size-sm); }
    .layout-card .text-blue-600 { color: var(--accent-blue) !important; font-size: var(--font-size-xs); margin-top: 2px; }

    /* ==========================================================================
       COMPOSANTS : ARTISTES (ARTIST BOX)
       ========================================================================== */
    #artists_container {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: var(--space-4);
        margin-bottom: var(--space-4);
    }

    .artist-box {
        position: relative;
        border: 1px solid var(--primary-600) !important;
        padding: var(--space-4) !important;
        border-radius: var(--radius-lg);
        background: var(--primary-700) !important;
        box-shadow: var(--shadow-sm);
        animation: boxFadeIn 300ms ease-out;
        display: flex;
        flex-direction: column;
        gap: 0.35rem;
    }

    /* Bouton de suppression flottant */
    .artist-box .text-end {
        position: absolute;
        top: var(--space-3);
        right: var(--space-3);
        z-index: 2;
    }

    .remove-btn {
        cursor: pointer;
        color: var(--white);
        background-color: var(--accent-red);
        width: 24px;
        height: 24px;
        border-radius: var(--radius-full);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: var(--font-size-xs);
        font-weight: 700;
        transition: var(--transition-fast);
        box-shadow: var(--shadow-md);
    }

    .remove-btn:hover {
        transform: scale(1.15) rotate(90deg);
        background-color: #ff6b81;
    }

    /* Textes de métadonnées pour photos actuelles */
    .text-muted { color: var(--gray-400) !important; }
    .text-xs { font-size: var(--font-size-xs); }
    code {
        background-color: var(--primary-900);
        padding: 2px 6px;
        border-radius: var(--radius-sm);
        color: var(--accent-blue);
        font-family: monospace;
    }

    /* ==========================================================================
       BOUTONS ET ACTIONS DE FORMULAIRE
       ========================================================================== */
    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: var(--space-2);
        font-family: var(--font-primary);
        font-size: var(--font-size-sm);
        font-weight: 600;
        padding: 0.65rem var(--space-5);
        border-radius: var(--radius-md);
        border: none;
        cursor: pointer;
        text-decoration: none;
        transition: var(--transition-base);
    }

    .btn-primary {
        background-color: var(--accent-blue);
        color: var(--white);
        box-shadow: var(--shadow-md);
    }
    .btn-primary:hover {
        background-color: #48dbfb;
        transform: translateY(-2px);
        box-shadow: var(--shadow-lg);
    }

    .btn-secondary {
        background-color: transparent;
        border: 1px dashed var(--primary-600);
        color: var(--gray-300);
        width: 100%;
        padding: var(--space-4);
        border-radius: var(--radius-lg);
    }
    .btn-secondary:hover {
        background-color: var(--primary-600);
        color: var(--white);
        border-color: var(--accent-blue);
    }

    .btn-light {
        background-color: var(--primary-600);
        color: var(--gray-200);
        border: 1px solid rgba(255, 255, 255, 0.05);
    }
    .btn-light:hover {
        background-color: var(--primary-700);
        color: var(--white);
    }

    /* Barre d'action finale */
    .flex.justify-end {
        display: flex;
        justify-content: flex-end;
        align-items: center;
        gap: var(--space-3);
    }

    /* ==========================================================================
       ANIMATIONS ET RESPONSIVITÉ SANS FAILLE
       ========================================================================== */
    @keyframes boxFadeIn {
        from { opacity: 0; transform: scale(0.96) translateY(10px); }
        to { opacity: 1; transform: scale(1) translateY(0); }
    }

    /* Ajustements Media Queries pour petits écrans */
    @media (max-width: 640px) {
        .page-wrapper {
            padding: var(--space-3) var(--space-2) var(--space-5) var(--space-2);
        }
        .card {
            padding: var(--space-4) !important;
        }
        #artists_container {
            grid-template-columns: 1fr;
        }
        .flex.justify-end {
            flex-direction: column-reverse;
            width: 100%;
        }
        .flex.justify-end .btn {
            width: 100%;
            padding: var(--space-3) !important;
        }
    }
</style>

<h2 class="mb-4">✏️ Modifier l'Événement</h2>

<form method="POST" enctype="multipart/form-data" class="card p-4 text-start">

    <!-- Informations Principales -->
    <div class="mb-3">
        <label class="form-label">Nom de l'événement</label>
        <input type="text" name="nom_evenement" class="form-control" value="<?= htmlspecialchars($event['nom_evenement']) ?>" required>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-3">
        <div>
            <label class="form-label">Type</label>
            <select name="type_evenement" class="form-control" required>
                <option value="sortie" <?= $event['type_evenement'] === 'sortie' ? 'selected' : '' ?>>Sortie</option>
                <option value="soiree" <?= $event['type_evenement'] === 'soiree' ? 'selected' : '' ?>>Soirée</option>
                <option value="concert" <?= $event['type_evenement'] === 'concert' ? 'selected' : '' ?>>Concert</option>
                <option value="competition" <?= $event['type_evenement'] === 'competition' ? 'selected' : '' ?>>Compétition</option>
                <option value="autre" <?= $event['type_evenement'] === 'autre' ? 'selected' : '' ?>>Autre</option>
            </select>
        </div>
        <div>
            <label class="form-label">Année Académique</label>
            <select name="academic_year_id" class="form-control" required>
                <?php foreach($years as $year): ?>
                    <option value="<?= $year['id'] ?>" <?= $event['academic_year_id'] == $year['id'] ? 'selected' : '' ?>><?= htmlspecialchars($year['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="mb-3">
        <label class="form-label">Description</label>
        <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($event['description']) ?></textarea>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-3">
        <div>
            <label class="form-label">Date & Heure</label>
            <input type="datetime-local" name="event_start" class="form-control" value="<?= date('Y-m-d\TH:i', strtotime($event['event_start'])) ?>" required>
        </div>
        <div>
            <label class="form-label">Lieu</label>
            <input type="text" name="lieu" class="form-control" value="<?= htmlspecialchars($event['lieu']) ?>">
        </div>
        <div>
            <label class="form-label">Prix du ticket (FCFA)</label>
            <input type="number" name="prix_ticket" class="form-control" step="0.01" min="0" value="<?= htmlspecialchars($event['prix_ticket']) ?>">
        </div>
    </div>

    <hr class="my-4">

    <!-- 🎫 SECTION GESTION DU TICKET & MODÈLES VISUELS -->
    <h4 class="mb-3">🎫 Billetterie & Accès</h4>
    
    <div class="mb-4">
        <label class="form-label">Cet événement nécessite-t-il un ticket ?</label>
        <select name="has_ticket" id="has_ticket" class="form-control" onchange="toggleTicketFields()" required>
            <option value="0" <?= $event['has_ticket'] === 0 ? 'selected' : '' ?>>Non, entrée libre / Pas de ticket système</option>
            <option value="1" <?= $event['has_ticket'] === 1 ? 'selected' : '' ?>>Oui, générer des tickets d'accès</option>
        </select>
    </div>
    
    <!-- Wrapper Global Billetterie -->
    <div id="global_ticket_wrapper" style="display: <?= $event['has_ticket'] === 1 ? 'block' : 'none' ?>;" class="space-y-6">
        
        <!-- CHOIX FLUX : CONSERVER, NOUVEAU OU CLONE -->
        <div class="bg-light p-3 rounded border">
            <label class="form-label fw-bold block mb-2">Méthode de configuration visuelle</label>
            <select name="use_existing_layout" id="use_existing_layout" class="form-control" onchange="switchWorkflow()">
                <option value="0">Conserver la configuration actuelle (Ou créer une configuration manuelle si vide)</option>
                <option value="1">Remplacer et Cloner la configuration complète d'un autre modèle existant</option>
            </select>
        </div>

        <!-- FLUX A : SÉLECTION DU TEMPLATE (Masqué par défaut en modification, sauf si manuel choisi) -->
        <div id="manual_template_wrapper" class="space-y-3" style="display: block;">
            <label class="form-label fw-bold block text-gray-800">Modèle graphique (Template) de fond assigné</label>
            <?php if (empty($templates)): ?>
                <div class="alert alert-warning">Aucun template actif trouvé.</div>
            <?php else: ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                    <?php foreach($templates as $tmpl): ?>
                        <div class="relative">
                            <input type="radio" name="ticket_template_id" id="tmpl_<?= $tmpl['id_template'] ?>" 
                                   value="<?= $tmpl['id_template'] ?>" class="template-radio sr-only"
                                   <?= $event['ticket_template_id'] == $tmpl['id_template'] ? 'checked' : '' ?>>
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

        <!-- FLUX B : RETRO-CLONAGE (Masqué initialement) -->
        <div id="clone_layout_wrapper" style="display: none;" class="space-y-3">
            <label class="form-label fw-bold block text-blue-900">Sélectionnez le nouveau Layout complet à cloner *</label>
            <?php if (empty($existing_layouts)): ?>
                <div class="alert alert-info">Aucun autre layout disponible pour le clonage.</div>
            <?php else: ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach($existing_layouts as $lay): 
                        $preview_width = 300;
                        $scale = $preview_width / $lay['canvas_width'];
                        $preview_height = $lay['canvas_height'] * $scale;
                    ?>
                        <div class="relative">
                            <input type="radio" name="existing_layout_id" id="layout_<?= $lay['id_layout'] ?>" 
                                   value="<?= $lay['id_layout'] ?>" class="layout-radio sr-only">
                            <label for="layout_<?= $lay['id_layout'] ?>" class="layout-card block bg-white rounded-xl overflow-hidden border p-3 h-full">
                                
                                <div class="mini-preview-container rounded mb-3 border border-gray-300 shadow-inner" 
                                     style="height: <?= $preview_height ?>px; background-image: url('../../<?= htmlspecialchars($lay['image_path']) ?>');">
                                    <div class="mini-element font-bold" style="left:<?= $lay['event_name_x'] * $scale ?>px; top:<?= $lay['event_name_y'] * $scale ?>px; font-size:<?= $lay['event_name_font_size'] * $scale ?>px; color:<?= $lay['event_name_color'] ?>;">[Nom Event]</div>
                                    <div class="mini-element" style="left:<?= $lay['participant_name_x'] * $scale ?>px; top:<?= $lay['participant_name_y'] * $scale ?>px; font-size:<?= $lay['participant_name_font_size'] * $scale ?>px; color:<?= $lay['participant_name_color'] ?>;">[Nom Participant]</div>
                                    <div class="mini-element font-mono" style="left:<?= $lay['ticket_code_x'] * $scale ?>px; top:<?= $lay['ticket_code_y'] * $scale ?>px; font-size:<?= $lay['ticket_code_font_size'] * $scale ?>px; color:<?= $lay['ticket_code_color'] ?>;">#T-XYZ</div>
                                    <div class="mini-element bg-white border border-black flex items-center justify-center font-bold text-[6px]" 
                                         style="left:<?= $lay['qr_x'] * $scale ?>px; top:<?= $lay['qr_y'] * $scale ?>px; width:<?= $lay['qr_width'] * $scale ?>px; height:<?= $lay['qr_height'] * $scale ?>px;">QR</div>
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
    <div id="artists_container">
        <!-- Remplissage dynamique des artistes déjà enregistrés -->
        <?php foreach($current_artists as $artist): ?>
            <div class="artist-box text-start">
                <div class="text-end"><span class="remove-btn" onclick="this.parentElement.parentElement.remove()">X</span></div>
                <input type="hidden" name="artist_id[]" value="<?= $artist['id_artiste'] ?>">
                
                <label class="form-label">Nom de l'artiste</label>
                <input type="text" name="artist_name[]" class="form-control mb-2" value="<?= htmlspecialchars($artist['nom_artiste']) ?>" required>
                
                <label class="form-label">Pseudonyme</label>
                <input type="text" name="artist_pseudo[]" class="form-control mb-2" value="<?= htmlspecialchars($artist['pseudonyme']) ?>">
                
                <label class="form-label">Rôle (Chanteur, DJ, Animateur…)</label>
                <input type="text" name="artist_role[]" class="form-control mb-2" value="<?= htmlspecialchars($artist['role']) ?>">
                
                <label class="form-label">Description</label>
                <textarea name="artist_desc[]" class="form-control mb-2"><?= htmlspecialchars($artist['description']) ?></textarea>
                
                <label class="form-label">Photo (Laisser vide pour conserver l'actuelle)</label>
                <input type="file" name="artist_photo[]" accept="image/*" class="form-control mb-1">
                <?php if(!empty($artist['photo'])): ?>
                    <p class="text-muted text-xs">Photo actuelle : <code><?= htmlspecialchars($artist['photo']) ?></code></p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <button type="button" class="btn btn-secondary mb-3" onclick="addArtist()">+ Ajouter un artiste</button>

    <hr class="my-4">

    <div class="flex justify-end gap-2">
        <a href="evenements.php" class="btn btn-light px-4 py-2.5">Annuler</a>
        <button type="submit" class="btn btn-primary px-5 py-2.5">Enregistrer les modifications</button>
    </div>

</form>

<script>
function toggleTicketFields() {
    const hasTicket = document.getElementById('has_ticket').value;
    const globalWrapper = document.getElementById('global_ticket_wrapper');

    if (hasTicket === "1") {
        globalWrapper.style.display = "block";
        switchWorkflow();
    } else {
        globalWrapper.style.display = "none";
        clearRequirements();
    }
}

function switchWorkflow() {
    const useExistingLayout = document.getElementById('use_existing_layout').value;
    const manualWrapper = document.getElementById('manual_template_wrapper');
    const cloneWrapper = document.getElementById('clone_layout_wrapper');
    
    const templateRadios = document.getElementsByName('ticket_template_id');
    const layoutRadios = document.getElementsByName('existing_layout_id');

    if (useExistingLayout === "0") {
        manualWrapper.style.display = "block";
        cloneWrapper.style.display = "none";
        
        if(layoutRadios.length > 0) layoutRadios[0].removeAttribute('required');
        layoutRadios.forEach(r => r.checked = false);
    } else {
        manualWrapper.style.display = "none";
        cloneWrapper.style.display = "block";
        
        if(layoutRadios.length > 0) layoutRadios[0].setAttribute('required', 'required');
    }
}

function clearRequirements() {
    const templateRadios = document.getElementsByName('ticket_template_id');
    const layoutRadios = document.getElementsByName('existing_layout_id');
    
    if(templateRadios.length > 0) templateRadios[0].removeAttribute('required');
    if(layoutRadios.length > 0) layoutRadios[0].removeAttribute('required');
    
    layoutRadios.forEach(r => r.checked = false);
}

function addArtist() {
    const container = document.getElementById('artists_container');
    const html = `
        <div class="artist-box text-start">
            <div class="text-end"><span class="remove-btn" onclick="this.parentElement.parentElement.remove()">X</span></div>
            <input type="hidden" name="artist_id[]" value="">
            <label class="form-label">Nom de l'artiste</label>
            <input type="text" name="artist_name[]" class="form-control mb-2" required>
            <label class="form-label">Pseudonyme</label>
            <input type="text" name="artist_pseudo[]" class="form-control mb-2">
            <label class="form-label">Rôle</label>
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