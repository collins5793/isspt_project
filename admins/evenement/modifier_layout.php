<?php
session_start();
require_once '../../includes/db.php';

// Vérifier l'accès admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

if (!isset($_GET['id'])) {
    header("Location: liste_layouts.php");
    exit;
}

$id_layout = intval($_GET['id']);

// 1. RÉCUPÉRER LE LAYOUT ET LES INFOS DU TEMPLATE ASSOCIÉ
$stmt = $pdo->prepare("
    SELECT tl.*, t.image_path, t.canvas_width, t.canvas_height, e.nom_evenement 
    FROM ticket_layouts tl
    JOIN ticket_templates t ON tl.template_id = t.id_template
    JOIN evenements e ON tl.event_id = e.id_evenement
    WHERE tl.id_layout = ?
");
$stmt->execute([$id_layout]);
$layout = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$layout) {
    die("Configuration de layout introuvable.");
}

// 2. MISE À JOUR DES COORDONNÉES
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $layout_name = trim($_POST['layout_name']);
    
    $sql = "UPDATE ticket_layouts SET 
                layout_name = :layout_name,
                event_name_x = :en_x, event_name_y = :en_y, event_name_font_size = :en_size, event_name_color = :en_color,
                event_date_x = :ed_x, event_date_y = :ed_y, event_date_font_size = :ed_size, event_date_color = :ed_color,
                event_location_x = :el_x, event_location_y = :el_y, event_location_font_size = :el_size, event_location_color = :el_color,
                participant_name_x = :pn_x, participant_name_y = :pn_y, participant_name_font_size = :pn_size, participant_name_color = :pn_color,
                ticket_code_x = :tc_x, ticket_code_y = :tc_y, ticket_code_font_size = :tc_size, ticket_code_color = :tc_color,
                qr_x = :qr_x, qr_y = :qr_y, qr_width = :qr_w, qr_height = :qr_h,
                logo_x = :logo_x, logo_y = :logo_y, logo_width = :logo_w, logo_height = :logo_h
            WHERE id_layout = :id_layout";
            
    $stmtUpdate = $pdo->prepare($sql);
    $stmtUpdate->execute([
        ':layout_name' => $layout_name,
        ':en_x' => intval($_POST['event_name_x']), ':en_y' => intval($_POST['event_name_y']), ':en_size' => intval($_POST['event_name_font_size']), ':en_color' => $_POST['event_name_color'],
        ':ed_x' => intval($_POST['event_date_x']), ':ed_y' => intval($_POST['event_date_y']), ':ed_size' => intval($_POST['event_date_font_size']), ':ed_color' => $_POST['event_date_color'],
        ':el_x' => intval($_POST['event_location_x']), ':el_y' => intval($_POST['event_location_y']), ':el_size' => intval($_POST['event_location_font_size']), ':el_color' => $_POST['event_location_color'],
        ':pn_x' => intval($_POST['participant_name_x']), ':pn_y' => intval($_POST['participant_name_y']), ':pn_size' => intval($_POST['participant_name_font_size']), ':pn_color' => $_POST['participant_name_color'],
        ':tc_x' => intval($_POST['ticket_code_x']), ':tc_y' => intval($_POST['ticket_code_y']), ':tc_size' => intval($_POST['ticket_code_font_size']), ':tc_color' => $_POST['ticket_code_color'],
        ':qr_x' => intval($_POST['qr_x']), ':qr_y' => intval($_POST['qr_y']), ':qr_w' => intval($_POST['qr_width']), ':qr_h' => intval($_POST['qr_height']),
        ':logo_x' => intval($_POST['logo_x']), ':logo_y' => intval($_POST['logo_y']), ':logo_w' => intval($_POST['logo_width']), ':logo_h' => intval($_POST['logo_height']),
        ':id_layout' => $id_layout
    ]);

    header("Location: liste_layouts.php?update_success=1");
    exit;
}

ob_start();
?>

<!-- Styles requis pour le fonctionnement du studio de design -->
<style>
    /* ==========================================================================
       1. VARIABLES & RESET CONFIGURATION
       ========================================================================== */
    :root {
        /* Colors - Dark Theme */
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

        /* Sidebar & Layout Content Layout */
        --sidebar-width: 280px;
        --sidebar-bg: linear-gradient(180deg, var(--primary-900) 0%, var(--primary-800) 100%);
        --sidebar-border: 1px solid rgba(255, 255, 255, 0.05);
        --sidebar-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);

        /* Typography */
        --font-primary: 'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif;
        --font-size-xs: 0.75rem;
        --font-size-sm: 0.875rem;
        --font-size-md: 1rem;
        --font-size-lg: 1.125rem;
        --font-size-xl: 1.25rem;

        /* Spacing */
        --space-1: 0.25rem;
        --space-2: 0.5rem;
        --space-3: 0.75rem;
        --space-4: 1rem;
        --space-5: 1.5rem;
        --space-6: 2rem;

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
    }

    /* Reset global appliqué au conteneur du studio pour éviter les conflits */
    #layout_form, .mb-6 {
        font-family: var(--font-primary);
        color: var(--white);
        box-sizing: border-box;
    }

    /* ==========================================================================
       2. HEADER STRIP (Titre & Bouton Retour)
       ========================================================================== */
    .mb-6 {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: var(--space-5);
        gap: var(--space-4);
        flex-wrap: wrap;
    }

    .mb-6 h2 {
        font-size: var(--font-size-xl);
        font-weight: 700;
        color: var(--primary-900);
        margin: 0 0 var(--space-1) 0;
    }

    /* Version mode sombre si inclus dans une page à fond noir */
    .dark-theme-title {
        color: var(--white) !important;
    }

    .mb-6 p {
        font-size: var(--font-size-sm);
        color: var(--gray-400);
        margin: 0;
    }

    .mb-6 p strong {
        color: var(--accent-blue);
        font-weight: 600;
    }

    .btn-back {
        display: inline-flex;
        align-items: center;
        background-color: var(--primary-700);
        color: var(--white);
        font-size: var(--font-size-xs);
        font-weight: 600;
        padding: var(--space-2) var(--space-4);
        border-radius: var(--radius-md);
        text-decoration: none;
        border: 1px solid rgba(255, 255, 255, 0.1);
        transition: all var(--transition-fast);
        box-shadow: var(--shadow-sm);
    }

    .btn-back:hover {
        background-color: var(--primary-600);
        transform: translateY(-1px);
        box-shadow: var(--shadow-md);
    }

    /* ==========================================================================
       3. ARCHITECTURE DE LA PAGE (Grid Responsif)
       ========================================================================== */
    .grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: var(--space-5);
        align-items: start;
    }

    @media (min-width: 1200px) {
        .grid {
            grid-template-columns: 320px 1fr; /* Largeur fixe optimale pour le panneau de contrôle */
        }
    }

    /* ==========================================================================
       4. CONTROL PANEL (Panneau latéral gauche)
       ========================================================================== */
    .control-panel {
        background: linear-gradient(145deg, var(--primary-800), var(--primary-900));
        padding: var(--space-5);
        border-radius: var(--radius-lg);
        border: var(--sidebar-border);
        box-shadow: var(--sidebar-shadow);
        display: flex;
        flex-direction: column;
        gap: var(--space-4);
    }

    .control-panel label {
        display: block;
        text-transform: uppercase;
        font-size: var(--font-size-xs);
        font-weight: 700;
        color: var(--gray-300);
        letter-spacing: 0.5px;
        margin-bottom: var(--space-2);
    }

    .control-panel input[type="text"] {
        width: 100%;
        background-color: var(--primary-700);
        border: 1px solid var(--primary-600);
        border-radius: var(--radius-md);
        padding: var(--space-3);
        color: var(--white);
        font-size: var(--font-size-sm);
        transition: border-color var(--transition-fast);
        box-sizing: border-box;
    }

    .control-panel input[type="text"]:focus {
        outline: none;
        border-color: var(--accent-blue);
        box-shadow: 0 0 0 3px rgba(46, 134, 222, 0.2);
    }

    .control-panel hr {
        border: 0;
        border-top: 1px solid rgba(255, 255, 255, 0.08);
        margin: var(--space-2) 0;
    }

    .control-panel h4 {
        font-size: var(--font-size-xs);
        font-weight: 700;
        color: var(--gray-400);
        text-transform: uppercase;
        margin: 0 0 var(--space-3) 0;
        letter-spacing: 0.5px;
    }

    /* Messages & États actifs */
    #no_selection_msg {
        font-size: var(--font-size-xs);
        color: var(--gray-400);
        background-color: rgba(255, 255, 255, 0.03);
        padding: var(--space-3);
        border-radius: var(--radius-md);
        border: 1px dashed rgba(255, 255, 255, 0.1);
        text-align: center;
    }

    #active_element_title {
        font-size: var(--font-size-xs);
        font-weight: 700;
        color: var(--white);
        background-color: var(--accent-blue);
        padding: var(--space-2) var(--space-3);
        border-radius: var(--radius-sm);
        margin-bottom: var(--space-3);
        text-transform: uppercase;
        box-shadow: var(--shadow-sm);
    }

    /* Éditeur de propriétés */
    .editor-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: var(--space-3);
    }

    .editor-grid input[type="number"] {
        width: 100%;
        background-color: var(--primary-700);
        border: 1px solid var(--primary-600);
        border-radius: var(--radius-md);
        padding: var(--space-2);
        color: var(--white);
        font-size: var(--font-size-sm);
        font-family: monospace;
        box-sizing: border-box;
    }

    .editor-grid input[type="color"] {
        -webkit-appearance: none;
        -moz-appearance: none;
        appearance: none;
        width: 100%;
        height: 38px;
        background-color: transparent;
        border: none;
        cursor: pointer;
    }

    .editor-grid input[type="color"]::-webkit-color-swatch {
        border: 1px solid var(--primary-600);
        border-radius: var(--radius-md);
    }

    /* Bouton principal de sauvegarde */
    .btn-submit {
        width: 100%;
        background-color: var(--accent-blue);
        color: var(--white);
        font-size: var(--font-size-sm);
        font-weight: 700;
        padding: var(--space-3) var(--space-4);
        border: none;
        border-radius: var(--radius-md);
        cursor: pointer;
        box-shadow: var(--shadow-md);
        transition: all var(--transition-fast);
        display: flex;
        align-items: center;
        justify-content: center;
        gap: var(--space-2);
    }

    .btn-submit:hover {
        background-color: #2475c9;
        transform: translateY(-1px);
        box-shadow: var(--shadow-lg);
    }

    .btn-submit:active {
        transform: translateY(0);
    }

    /* ==========================================================================
       5. CANVAS INTERACTIVE STUDIO (Zone de travail droite)
       ========================================================================== */
    .canvas-container {
        display: flex;
        justify-content: center;
        align-items: center;
        background-color: var(--primary-900);
        background-image: radial-gradient(rgba(255, 255, 255, 0.05) 1px, transparent 0);
        background-size: 24px 24px; /* Effet de grille de design technique moderne */
        padding: var(--space-6);
        border-radius: var(--radius-lg);
        border: 2px dashed var(--primary-600);
        min-height: 500px;
        overflow: auto; /* Défilement propre si le ticket dépasse le conteneur */
        position: relative;
    }

    /* Version sur-mesure de la zone de rendu du ticket */
    .studio-canvas {
        position: relative;
        width: <?= $layout['canvas_width'] ?>px;
        height: <?= $layout['canvas_height'] ?>px;
        background-image: url('../../<?= htmlspecialchars($layout['image_path']) ?>');
        background-size: cover;
        background-position: center;
        background-repeat: no-repeat;
        border: 1px solid rgba(255, 255, 255, 0.15);
        box-shadow: var(--shadow-xl);
        border-radius: var(--radius-sm);
        overflow: hidden;
        flex-shrink: 0; /* Empêche l'écrasement de l'aspect ratio */
    }

    /* Éléments déplaçables */
    .draggable-element {
        position: absolute;
        cursor: move;
        user-select: none;
        white-space: nowrap;
        padding: var(--space-1) var(--space-2);
        border: 1px dashed transparent;
        transition: border-color var(--transition-fast), background-color var(--transition-fast);
        border-radius: var(--radius-sm);
        box-sizing: border-box;
    }

    .draggable-element:hover {
        border-color: var(--accent-blue);
        background-color: rgba(46, 134, 222, 0.15);
    }

    /* État actif sélectionné */
    .active-element {
        border: 2px solid var(--accent-blue) !important;
        background-color: rgba(46, 134, 222, 0.25) !important;
        box-shadow: var(--shadow-md);
        z-index: 10;
    }

    /* Zones spécifiques pour QR et Logo */
    #drag_qr, #drag_logo {
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-size: 10px;
        color: #000000;
        box-shadow: var(--shadow-sm);
    }

    #drag_qr {
        background-color: rgba(255, 255, 255, 0.95);
        border: 2px solid #000000;
    }

    #drag_logo {
        background-color: rgba(220, 225, 235, 0.9);
        border: 1px solid var(--gray-400);
        color: var(--primary-900);
    }

    /* Note d'information mobile */
    .responsive-hint {
        display: none;
        background-color: rgba(46, 134, 222, 0.1);
        border: 1px solid rgba(46, 134, 222, 0.2);
        color: var(--gray-300);
        padding: var(--space-3);
        border-radius: var(--radius-md);
        font-size: var(--font-size-xs);
        margin-bottom: var(--space-4);
        width: 100%;
        box-sizing: border-box;
    }

    /* ==========================================================================
       6. RESPONSIVITÉ ET COMPORTEMENT MOBILES
       ========================================================================== */
    @media (max-width: 991px) {
        .canvas-container {
            padding: var(--space-3);
            justify-content: flex-start; /* Permet le défilement horizontal fluide */
        }
        
        .responsive-hint {
            display: flex;
            align-items: center;
            gap: var(--space-2);
        }
    }
</style>

<!-- En-tête -->
<div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 text-start">
    <div>
        <h2 class="text-2xl font-bold text-gray-900 tracking-tight">⚙️ Studio : Modifier la Configuration</h2>
        <p class="text-sm text-gray-500 mt-0.5">Ajustez le visuel pour l'événement : <span class="font-semibold text-gray-700"><?= htmlspecialchars($layout['nom_evenement']) ?></span></p>
    </div>
    <a href="liste_layouts.php" class="inline-flex items-center justify-center self-start bg-white hover:bg-gray-50 text-gray-700 text-xs font-semibold px-3.5 py-2 rounded-xl border border-gray-200 shadow-xs transition-colors">
        <svg class="w-4 h-4 mr-1.5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        Retour à la liste
    </a>
</div>

<!-- Formulaire principal -->
<form method="POST" id="layout_form" class="grid grid-cols-1 xl:grid-cols-4 gap-6 text-start">
    
    <!-- 🛠️ PANNEAU LATÉRAL DE CONTRÔLE -->
    <div class="xl:col-span-1 bg-white p-5 rounded-2xl border border-gray-200 shadow-xs space-y-5 h-fit">
        <div>
            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Nom de la configuration</label>
            <input type="text" name="layout_name" value="<?= htmlspecialchars($layout['layout_name']) ?>" class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:outline-hidden focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all font-medium" required placeholder="Ex: Layout Standard VIP">
        </div>

        <hr class="border-gray-100">
        
        <!-- Éditeur dynamique de l'élément sélectionné -->
        <div class="bg-gray-50 p-4 rounded-xl border border-gray-100">
            <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">Propriétés de l'élément actif</h4>
            
            <div id="no_selection_msg" class="text-xs text-gray-400 italic py-2 text-center bg-white rounded-lg border border-dashed border-gray-200">
                Cliquez sur un élément du ticket pour ajuster sa taille ou sa couleur.
            </div>
            
            <div id="editor_controls" style="display: none;" class="space-y-4">
                <p id="active_element_title" class="text-xs font-semibold text-blue-700 bg-blue-50/70 border border-blue-100 px-2.5 py-1.5 rounded-lg inline-block w-full text-center tracking-wide"></p>
                
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-[11px] font-medium text-gray-500 block mb-1">Taille / Échelle (px)</label>
                        <input type="number" id="input_size" class="w-full border border-gray-300 bg-white rounded-lg p-1.5 text-xs font-mono font-semibold focus:outline-hidden focus:border-blue-500">
                    </div>
                    <div id="color_wrapper">
                        <label class="text-[11px] font-medium text-gray-500 block mb-1">Couleur</label>
                        <input type="color" id="input_color" class="w-full h-8 border border-gray-300 bg-white rounded-lg p-0.5 cursor-pointer focus:outline-hidden">
                    </div>
                </div>
            </div>
        </div>

        <!-- CHAMPS CACHÉS (Mise à jour en temps réel via JS) -->
        <input type="hidden" name="event_name_x" id="event_name_x" value="<?= $layout['event_name_x'] ?>">
        <input type="hidden" name="event_name_y" id="event_name_y" value="<?= $layout['event_name_y'] ?>">
        <input type="hidden" name="event_name_font_size" id="event_name_font_size" value="<?= $layout['event_name_font_size'] ?>">
        <input type="hidden" name="event_name_color" id="event_name_color" value="<?= $layout['event_name_color'] ?>">
        
        <input type="hidden" name="event_date_x" id="event_date_x" value="<?= $layout['event_date_x'] ?>">
        <input type="hidden" name="event_date_y" id="event_date_y" value="<?= $layout['event_date_y'] ?>">
        <input type="hidden" name="event_date_font_size" id="event_date_font_size" value="<?= $layout['event_date_font_size'] ?>">
        <input type="hidden" name="event_date_color" id="event_date_color" value="<?= $layout['event_date_color'] ?>">
        
        <input type="hidden" name="event_location_x" id="event_location_x" value="<?= $layout['event_location_x'] ?>">
        <input type="hidden" name="event_location_y" id="event_location_y" value="<?= $layout['event_location_y'] ?>">
        <input type="hidden" name="event_location_font_size" id="event_location_font_size" value="<?= $layout['event_location_font_size'] ?>">
        <input type="hidden" name="event_location_color" id="event_location_color" value="<?= $layout['event_location_color'] ?>">
        
        <input type="hidden" name="participant_name_x" id="participant_name_x" value="<?= $layout['participant_name_x'] ?>">
        <input type="hidden" name="participant_name_y" id="participant_name_y" value="<?= $layout['participant_name_y'] ?>">
        <input type="hidden" name="participant_name_font_size" id="participant_name_font_size" value="<?= $layout['participant_name_font_size'] ?>">
        <input type="hidden" name="participant_name_color" id="participant_name_color" value="<?= $layout['participant_name_color'] ?>">
        
        <input type="hidden" name="ticket_code_x" id="ticket_code_x" value="<?= $layout['ticket_code_x'] ?>">
        <input type="hidden" name="ticket_code_y" id="ticket_code_y" value="<?= $layout['ticket_code_y'] ?>">
        <input type="hidden" name="ticket_code_font_size" id="ticket_code_font_size" value="<?= $layout['ticket_code_font_size'] ?>">
        <input type="hidden" name="ticket_code_color" id="ticket_code_color" value="<?= $layout['ticket_code_color'] ?>">
        
        <input type="hidden" name="qr_x" id="qr_x" value="<?= $layout['qr_x'] ?>">
        <input type="hidden" name="qr_y" id="qr_y" value="<?= $layout['qr_y'] ?>">
        <input type="hidden" name="qr_width" id="qr_width" value="<?= $layout['qr_width'] ?>">
        <input type="hidden" name="qr_height" id="qr_height" value="<?= $layout['qr_height'] ?>">
        
        <input type="hidden" name="logo_x" id="logo_x" value="<?= $layout['logo_x'] ?>">
        <input type="hidden" name="logo_y" id="logo_y" value="<?= $layout['logo_y'] ?>">
        <input type="hidden" name="logo_width" id="logo_width" value="<?= $layout['logo_width'] ?>">
        <input type="hidden" name="logo_height" id="logo_height" value="<?= $layout['logo_height'] ?>">

        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white text-sm font-bold py-3 rounded-xl shadow-md shadow-blue-500/10 transition-all active:scale-[0.98] cursor-pointer">
            💾 Mettre à jour le Layout
        </button>
    </div>

    <!-- 🎨 ESPACE CANVAS -->
    <div class="xl:col-span-3 flex justify-center items-start bg-gray-50 p-6 rounded-2xl border border-dashed border-gray-300 overflow-auto min-h-[500px]">
        
        <div class="studio-canvas" id="canvas">
            
            <!-- NOM ÉVÉNEMENT -->
            <div id="drag_event_name" class="draggable-element font-bold" 
                 style="left:<?= $layout['event_name_x'] ?>px; top:<?= $layout['event_name_y'] ?>px; font-size:<?= $layout['event_name_font_size'] ?>px; color:<?= $layout['event_name_color'] ?>;">
                [Nom de l'événement]
            </div>

            <!-- DATE ÉVÉNEMENT -->
            <div id="drag_event_date" class="draggable-element" 
                 style="left:<?= $layout['event_date_x'] ?>px; top:<?= $layout['event_date_y'] ?>px; font-size:<?= $layout['event_date_font_size'] ?>px; color:<?= $layout['event_date_color'] ?>;">
                [Date & Heure]
            </div>

            <!-- LIEU ÉVÉNEMENT -->
            <div id="drag_event_location" class="draggable-element" 
                 style="left:<?= $layout['event_location_x'] ?>px; top:<?= $layout['event_location_y'] ?>px; font-size:<?= $layout['event_location_font_size'] ?>px; color:<?= $layout['event_location_color'] ?>;">
                [Lieu de l'évènement]
            </div>

            <!-- NOM DU PARTICIPANT -->
            <div id="drag_participant_name" class="draggable-element" 
                 style="left:<?= $layout['participant_name_x'] ?>px; top:<?= $layout['participant_name_y'] ?>px; font-size:<?= $layout['participant_name_font_size'] ?>px; color:<?= $layout['participant_name_color'] ?>;">
                [Nom Prénom du Participant]
            </div>

            <!-- CODE UNIQUE DU TICKET -->
            <div id="drag_ticket_code" class="draggable-element font-mono" 
                 style="left:<?= $layout['ticket_code_x'] ?>px; top:<?= $layout['ticket_code_y'] ?>px; font-size:<?= $layout['ticket_code_font_size'] ?>px; color:<?= $layout['ticket_code_color'] ?>;">
                #T-0000-XYZ
            </div>

            <!-- BLOC QR CODE -->
            <div id="drag_qr" class="draggable-element bg-white border-2 border-black flex items-center justify-center font-bold text-[10px] text-center shadow-xs" 
                 style="left:<?= $layout['qr_x'] ?>px; top:<?= $layout['qr_y'] ?>px; width:<?= $layout['qr_width'] ?>px; height:<?= $layout['qr_height'] ?>px; padding:0;">
                [ Zone QR Code ]
            </div>

            <!-- BLOC LOGO -->
            <div id="drag_logo" class="draggable-element bg-gray-100/90 backdrop-blur-xs border border-gray-400 flex items-center justify-center font-bold text-[10px] text-center shadow-xs" 
                 style="left:<?= $layout['logo_x'] ?>px; top:<?= $layout['logo_y'] ?>px; width:<?= $layout['logo_width'] ?>px; height:<?= $layout['logo_height'] ?>px; padding:0;">
                [ Zone Logo ]
            </div>

        </div>

    </div>
</form>

<!-- MOTEUR INTERACTIF DU DRAG AND DROP -->
<script>
let currentActiveElement = null;
let currentKeyString = ""; 

const canvas = document.getElementById('canvas');
const elements = document.querySelectorAll('.draggable-element');

const noSelectionMsg = document.getElementById('no_selection_msg');
const editorControls = document.getElementById('editor_controls');
const activeElementTitle = document.getElementById('active_element_title');
const inputSize = document.getElementById('input_size');
const inputColor = document.getElementById('input_color');
const colorWrapper = document.getElementById('color_wrapper');

// 1. GESTION DU DRAG & DROP
elements.forEach(el => {
    el.addEventListener('mousedown', function(e) {
        e.preventDefault();
        
        elements.forEach(item => item.classList.remove('active-element'));
        el.classList.add('active-element');
        currentActiveElement = el;
        
        currentKeyString = el.id.replace('drag_', ''); 
        setupEditorPanel(el, currentKeyString);

        let shiftX = e.clientX - el.getBoundingClientRect().left;
        let shiftY = e.clientY - el.getBoundingClientRect().top;

        function moveAt(pageX, pageY) {
            let canvasRect = canvas.getBoundingClientRect();
            let newX = pageX - canvasRect.left - shiftX;
            let newY = pageY - canvasRect.top - shiftY;

            // Limiter les déplacements au Canvas
            if (newX < 0) newX = 0;
            if (newY < 0) newY = 0;
            if (newX + el.offsetWidth > canvas.offsetWidth) newX = canvas.offsetWidth - el.offsetWidth;
            if (newY + el.offsetHeight > canvas.offsetHeight) newY = canvas.offsetHeight - el.offsetHeight;

            el.style.left = newX + 'px';
            el.style.top = newY + 'px';

            // Sauvegarde temps réel dans les inputs cachés
            document.getElementById(currentKeyString + '_x').value = Math.round(newX);
            document.getElementById(currentKeyString + '_y').value = Math.round(newY);
        }

        function onMouseMove(e) {
            moveAt(e.clientX, e.clientY);
        }

        document.addEventListener('mousemove', onMouseMove);

        document.onmouseup = function() {
            document.removeEventListener('mousemove', onMouseMove);
            document.onmouseup = null;
        };
    });
});

// 2. CONFIGURATION DE L'INTERFACE DE CONTRÔLE
function setupEditorPanel(el, key) {
    noSelectionMsg.style.display = 'none';
    editorControls.style.display = 'block';
    
    activeElementTitle.innerText = key.toUpperCase().replace('_', ' ');

    if (key === 'qr' || key === 'logo') {
        colorWrapper.style.display = 'none';
        inputSize.value = el.offsetWidth;
    } else {
        colorWrapper.style.display = 'block';
        inputSize.value = parseInt(window.getComputedStyle(el).fontSize);
        
        let rgb = window.getComputedStyle(el).color;
        inputColor.value = rgbToHex(rgb);
    }
}

// Changements de taille / échelle
inputSize.addEventListener('input', function() {
    if (!currentActiveElement) return;
    let val = parseInt(this.value) || 12;

    if (currentKeyString === 'qr' || currentKeyString === 'logo') {
        currentActiveElement.style.width = val + 'px';
        currentActiveElement.style.height = val + 'px';
        document.getElementById(currentKeyString + '_width').value = val;
        document.getElementById(currentKeyString + '_height').value = val;
    } else {
        currentActiveElement.style.fontSize = val + 'px';
        document.getElementById(currentKeyString + '_font_size').value = val;
    }
});

// Changements de couleur
inputColor.addEventListener('input', function() {
    if (!currentActiveElement || currentKeyString === 'qr' || currentKeyString === 'logo') return;
    let color = this.value;
    currentActiveElement.style.color = color;
    document.getElementById(currentKeyString + '_color').value = color;
});

function rgbToHex(rgb) {
    let result = /^rgb\((\d+),\s*(\d+),\s*(\d+)\)$/.exec(rgb);
    return result ? "#" + 
        ("0" + parseInt(result[1],10).toString(16)).slice(-2) +
        ("0" + parseInt(result[2],10).toString(16)).slice(-2) +
        ("0" + parseInt(result[3],10).toString(16)).slice(-2) : rgb;
}
</script>

<?php
$content = ob_get_clean();
include "../layout.php";
?>