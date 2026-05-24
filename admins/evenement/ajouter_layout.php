<?php
session_start();
require_once '../../includes/db.php';

// Vérifier l'accès admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// Vérifier la présence de l'ID de l'événement
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: evenements.php");
    exit;
}

$event_id = intval($_GET['id']);
$error = null;
$success = null;

// 1. CHARGER L'ÉVÉNEMENT ET SON TEMPLATE ASSOCIÉ
try {
    $stmt = $pdo->prepare("
        SELECT e.*, t.nom_template, t.image_path, t.canvas_width, t.canvas_height 
        FROM evenements e
        JOIN ticket_templates t ON e.ticket_template_id = t.id_template
        WHERE e.id_evenement = ?
    ");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$event) {
        header("Location: evenements.php");
        exit;
    }

    // Charger un layout existant pour cet événement s'il y en a un
    $stmtLayout = $pdo->prepare("SELECT * FROM ticket_layouts WHERE event_id = ? LIMIT 1");
    $stmtLayout->execute([$event_id]);
    $existing_layout = $stmtLayout->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erreur critique : " . $e->getMessage());
}

// 2. TRAITEMENT DU FORMULAIRE DE SAUVEGARDE (UPSERT)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_layout'])) {
    try {
        // Structurer les textes personnalisés libres en JSON
        $custom_texts_data = [];
        if (isset($_POST['custom_text_val'])) {
            foreach ($_POST['custom_text_val'] as $key => $val) {
                $custom_texts_data[] = [
                    'text' => trim($val),
                    'x' => intval($_POST['custom_x'][$key] ?? 0),
                    'y' => intval($_POST['custom_y'][$key] ?? 0),
                    'size' => intval($_POST['custom_size'][$key] ?? 16),
                    'color' => $_POST['custom_color'][$key] ?? '#FFFFFF'
                ];
            }
        }
        $custom_json = json_encode($custom_texts_data, JSON_UNESCAPED_UNICODE);

        if ($existing_layout) {
            // UPDATE
            $sql = "UPDATE ticket_layouts SET 
                    layout_name = :layout_name,
                    institut_name_x = :inst_x, institut_name_y = :inst_y, institut_name_font_size = :inst_size, institut_name_color = :inst_color,
                    event_name_x = :en_x, event_name_y = :en_y, event_name_font_size = :en_size, event_name_color = :en_color, event_name_font_weight = :en_weight, event_name_font_family = :en_family,
                    event_date_x = :ed_x, event_date_y = :ed_y, event_date_font_size = :ed_size, event_date_color = :ed_color, event_date_font_weight = :ed_weight, event_date_font_family = :ed_family,
                    event_location_x = :el_x, event_location_y = :el_y, event_location_font_size = :el_size, event_location_color = :el_color,
                    participant_name_x = :pn_x, participant_name_y = :pn_y, participant_name_font_size = :pn_size, participant_name_color = :pn_color,
                    ticket_code_x = :tc_x, ticket_code_y = :tc_y, ticket_code_font_size = :tc_size, ticket_code_color = :tc_color,
                    qr_x = :qr_x, qr_y = :qr_y, qr_width = :qr_w, qr_height = :qr_h,
                    logo_x = :logo_x, logo_y = :logo_y, logo_width = :logo_w, logo_height = :logo_h,
                    custom_texts = :custom_texts
                    WHERE event_id = :event_id";
        } else {
            // INSERT
            $sql = "INSERT INTO ticket_layouts (
                        template_id, event_id, layout_name, 
                        institut_name_x, institut_name_y, institut_name_font_size, institut_name_color,
                        event_name_x, event_name_y, event_name_font_size, event_name_color, event_name_font_weight, event_name_font_family,
                        event_date_x, event_date_y, event_date_font_size, event_date_color, event_date_font_weight, event_date_font_family,
                        event_location_x, event_location_y, event_location_font_size, event_location_color,
                        participant_name_x, participant_name_y, participant_name_font_size, participant_name_color,
                        ticket_code_x, ticket_code_y, ticket_code_font_size, ticket_code_color,
                        qr_x, qr_y, qr_width, qr_height,
                        logo_x, logo_y, logo_width, logo_height, custom_texts
                    ) VALUES (
                        :template_id, :event_id, :layout_name,
                        :inst_x, :inst_y, :inst_size, :inst_color,
                        :en_x, :en_y, :en_size, :en_color, :en_weight, :en_family,
                        :ed_x, :ed_y, :ed_size, :ed_color, :ed_weight, :ed_family,
                        :el_x, :el_y, :el_size, :el_color,
                        :pn_x, :pn_y, :pn_size, :pn_color,
                        :tc_x, :tc_y, :tc_size, :tc_color,
                        :qr_x, :qr_y, :qr_w, :qr_h,
                        :logo_x, :logo_y, :logo_w, :logo_h, :custom_texts
                    )";
        }

        $stmt = $pdo->prepare($sql);
        
        $binds = [
            ':event_id'   => $event_id,
            ':layout_name' => !empty($_POST['layout_name']) ? trim($_POST['layout_name']) : "Configuration - " . $event['nom_evenement'],
            ':inst_x'     => intval($_POST['institut_name_x']),
            ':inst_y'     => intval($_POST['institut_name_y']),
            ':inst_size'  => intval($_POST['institut_name_font_size']),
            ':inst_color' => $_POST['institut_name_color'],
            ':en_x'       => intval($_POST['event_name_x']),
            ':en_y'       => intval($_POST['event_name_y']),
            ':en_size'    => intval($_POST['event_name_font_size']),
            ':en_color'   => $_POST['event_name_color'],
            ':en_weight'  => $_POST['event_name_font_weight'],
            ':en_family'  => $_POST['event_name_font_family'],
            ':ed_x'       => intval($_POST['event_date_x']),
            ':ed_y'       => intval($_POST['event_date_y']),
            ':ed_size'    => intval($_POST['event_date_font_size']),
            ':ed_color'   => $_POST['event_date_color'],
            ':ed_weight'  => $_POST['event_date_font_weight'],
            ':ed_family'  => $_POST['event_date_font_family'],
            ':el_x'       => intval($_POST['event_location_x']),
            ':el_y'       => intval($_POST['event_location_y']),
            ':el_size'    => intval($_POST['event_location_font_size']),
            ':el_color'   => $_POST['event_location_color'],
            ':pn_x'       => intval($_POST['participant_name_x']),
            ':pn_y'       => intval($_POST['participant_name_y']),
            ':pn_size'    => intval($_POST['participant_name_font_size']),
            ':pn_color'   => $_POST['participant_name_color'],
            ':tc_x'       => intval($_POST['ticket_code_x']),
            ':tc_y'       => intval($_POST['ticket_code_y']),
            ':tc_size'    => intval($_POST['ticket_code_font_size']),
            ':tc_color'   => $_POST['ticket_code_color'],
            ':qr_x'       => intval($_POST['qr_x']),
            ':qr_y'       => intval($_POST['qr_y']),
            ':qr_w'       => intval($_POST['qr_width']),
            ':qr_h'       => intval($_POST['qr_height']),
            ':logo_x'     => intval($_POST['logo_x']),
            ':logo_y'     => intval($_POST['logo_y']),
            ':logo_w'     => intval($_POST['logo_width']),
            ':logo_h'     => intval($_POST['logo_height']),
            ':custom_texts' => $custom_json
        ];

        if (!$existing_layout) {
            $binds[':template_id'] = $event['ticket_template_id'];
        }

        $stmt->execute($binds);

        if (!$existing_layout) {
            $layout_id = $pdo->lastInsertId();
            $updateEvent = $pdo->prepare("UPDATE evenements SET layout_id = ? WHERE id_evenement = ?");
            $updateEvent->execute([$layout_id, $event_id]);
        }

        $success = "Le design du ticket a été sauvegardé avec succès !";
        // Recharger les données fraîches
        $stmtLayout->execute([$event_id]);
        $existing_layout = $stmtLayout->fetch(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        $error = "Erreur lors de la sauvegarde : " . $e->getMessage();
    }
}

// Helper values pour assigner les valeurs par défaut de la BDD si elle existe
function val($field, $default, $db_array) {
    return isset($db_array[$field]) ? htmlspecialchars($db_array[$field]) : $default;
}

ob_start();
?>
<!-- À mettre dans le <head> de votre page pour que le navigateur connaisse ces polices -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Anton&family=Bebas+Neue&family=Caveat:wght@400..700&family=Cinzel:wght@400..900&family=Inter:wght@100..900&family=Lora:ital,wght@0,400..700;1,400..700&family=Montserrat:wght@100..900&family=Oswald:wght@200..700&family=Pacifico&family=Playfair+Display:ital,wght@0,400..900;1,400..900&family=Poppins:wght@100..900&family=Roboto:wght@100..900&display=swap" rel="stylesheet">


<!-- Framework UI & Icones -->
<script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<div class="max-w-[1600px] mx-auto p-4">
    <!-- Header fluide -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center border-b border-gray-200 pb-4 mb-6 gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
                <i class="fa-solid fa-palette text-indigo-600"></i> Studio Canvas — <?= htmlspecialchars($event['nom_evenement']) ?>
            </h2>
            <p class="text-sm text-gray-500 mt-1">Glissez-déposez les calques et ajustez les propriétés graphiques en direct.</p>
        </div>
        <a href="evenements.php" class="bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-semibold px-4 py-2 rounded-lg transition border">
            <i class="fa-solid fa-arrow-left mr-1"></i> Retour aux événements
        </a>
    </div>

    <!-- Alertes -->
    <?php if($error): ?>
        <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded-r-xl mb-4 text-sm shadow-sm"><?= $error ?></div>
    <?php endif; ?>
    <?php if($success): ?>
        <div class="bg-emerald-50 border-l-4 border-emerald-500 text-emerald-700 p-4 rounded-r-xl mb-4 text-sm shadow-sm"><?= $success ?></div>
    <?php endif; ?>

    <div class="flex flex-col lg:flex-row gap-6 items-start text-start">
        
        <!-- ZONE DE GAUCHE : WORKSPACE / CANVA INTERACTIF -->
        <div class="flex-1 w-full bg-slate-950 p-6 rounded-2xl border border-slate-800 shadow-2xl flex flex-col items-center overflow-auto min-h-[650px]">
            
            <!-- Actions rapides de l'espace de travail -->
            <div class="w-full mb-4 flex gap-2 justify-start border-b border-slate-800 pb-3">
                <button type="button" onclick="addCustomTextNode()" class="bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-medium px-4 py-2 rounded-lg flex items-center gap-2 shadow-lg shadow-indigo-600/20 transition-all active:scale-95">
                    <i class="fa-solid fa-plus-text"></i> <i class="fa-solid fa-font"></i> Ajouter un texte personnalisé
                </button>
            </div>

            <!-- Conteneur Ticket Imprimable -->
            <div id="ticket-canvas" class="relative bg-no-repeat bg-cover shadow-2xl rounded-sm border border-white/10 select-none"
                style="background-image: url('../../<?= $event['image_path'] ?>'); 
                        width: <?= $event['canvas_width'] ?>px; 
                        height: <?= $event['canvas_height'] ?>px;
                        max-width: 100%;">
                
                <!-- Calque : Nom de l'Institut -->
                <div id="drag-institut_name" class="draggable-element absolute cursor-move p-1 border-2 border-dashed border-transparent hover:border-indigo-500 rounded bg-black/30" 
                    data-target="institut_name"
                    style="color: <?= val('institut_name_color', '#FFFFFF', $existing_layout) ?>; 
                            font-size: <?= val('institut_name_font_size', 16, $existing_layout) ?>px; 
                            font-weight: <?= val('institut_name_font_weight', 'bold', $existing_layout) ?>; 
                            font-family: <?= val('institut_name_font_family', 'roboto', $existing_layout) ?>;
                            left: <?= val('institut_name_x', 40, $existing_layout) ?>px; top: <?= val('institut_name_y', 15, $existing_layout) ?>px;">
                    INSTITUT SUPÉRIEUR SAINT PAUL DE TARSE
                </div>

                <!-- Calque : Logo ISSPT -->
                <div id="drag-logo" class="draggable-element absolute cursor-move border-2 border-dashed border-transparent hover:border-indigo-500 rounded p-1 bg-white/5 flex items-center justify-center" 
                    data-target="logo"
                    style="left: <?= val('logo_x', 850, $existing_layout) ?>px; top: <?= val('logo_y', 30, $existing_layout) ?>px; z-index: 40;">
                    <img src="../../assets/images/logo.png" id="img-logo" 
                        style="width: <?= max(30, (int)val('logo_width', 100, $existing_layout)) ?>px; 
                                height: <?= max(30, (int)val('logo_height', 100, $existing_layout)) ?>px; 
                                object-fit: contain; 
                                display: block;" 
                        alt="Logo ISSPT">
                </div>
                
                <!-- Calque : Nom de l'Événement -->
                <div id="drag-event_name" class="draggable-element absolute cursor-move p-1 border-2 border-dashed border-transparent hover:border-indigo-500 rounded bg-black/30" 
                    data-target="event_name"
                    style="color: <?= val('event_name_color', '#FFFFFF', $existing_layout) ?>; 
                            font-size: <?= val('event_name_font_size', 32, $existing_layout) ?>px; 
                            font-weight: <?= val('event_name_font_weight', 'bold', $existing_layout) ?>; 
                            font-family: <?= val('event_name_font_family', 'roboto', $existing_layout) ?>;
                            left: <?= val('event_name_x', 50, $existing_layout) ?>px; top: <?= val('event_name_y', 100, $existing_layout) ?>px;">
                    <?= htmlspecialchars($event['nom_evenement']) ?>
                </div>

                <!-- Calque : Date -->
                <div id="drag-event_date" class="draggable-element absolute cursor-move p-1 border-2 border-dashed border-transparent hover:border-indigo-500 rounded bg-black/30" 
                    data-target="event_date"
                    style="color: <?= val('event_date_color', '#FFFFFF', $existing_layout) ?>; 
                            font-size: <?= val('event_date_date_font_size', 18, $existing_layout) ?>px; 
                            font-weight: <?= val('event_date_font_weight', 'normal', $existing_layout) ?>; 
                            font-family: <?= val('event_date_font_family', 'Arial', $existing_layout) ?>;
                            left: <?= val('event_date_x', 50, $existing_layout) ?>px; top: <?= val('event_date_y', 160, $existing_layout) ?>px;">
                    <?= date('d/m/Y H:i', strtotime($event['event_start'])) ?>
                </div>

                <!-- Calque : Lieu -->
                <div id="drag-event_location" class="draggable-element absolute cursor-move p-1 border-2 border-dashed border-transparent hover:border-indigo-500 rounded bg-black/30" 
                    data-target="event_location"
                    style="color: <?= val('event_location_color', '#FFFFFF', $existing_layout) ?>; 
                            font-size: <?= val('event_location_font_size', 18, $existing_layout) ?>px; 
                            font-weight: <?= val('event_location_font_weight', 'normal', $existing_layout) ?>; 
                            font-family: <?= val('event_location_font_family', 'roboto', $existing_layout) ?>;
                            left: <?= val('event_location_x', 50, $existing_layout) ?>px; top: <?= val('event_location_y', 200, $existing_layout) ?>px;">
                    <?= !empty($event['lieu']) ? htmlspecialchars($event['lieu']) : 'Lieu de l\'événement' ?>
                </div>

                <!-- Calque : Étudiant -->
                <div id="drag-participant_name" class="draggable-element absolute cursor-move p-1 border-2 border-dashed border-transparent hover:border-indigo-500 rounded bg-black/30" 
                    data-target="participant_name"
                    style="color: <?= val('participant_name_color', '#FFFFFF', $existing_layout) ?>; 
                            font-size: <?= val('participant_name_font_size', 28, $existing_layout) ?>px; 
                            font-weight: <?= val('participant_name_font_weight', 'bold', $existing_layout) ?>; 
                            font-family: <?= val('participant_name_font_family', 'roboto', $existing_layout) ?>;
                            left: <?= val('participant_name_x', 50, $existing_layout) ?>px; top: <?= val('participant_name_y', 300, $existing_layout) ?>px;">
                    [Nom Complet Étudiant]
                </div>

                <!-- Calque : Code Unique -->
                <div id="drag-ticket_code" class="draggable-element absolute cursor-move p-1 border-2 border-dashed border-transparent hover:border-indigo-500 rounded bg-black/30 font-mono tracking-wider" 
                    data-target="ticket_code"
                    style="color: <?= val('ticket_code_color', '#FFFFFF', $existing_layout) ?>; 
                            font-size: <?= val('ticket_code_font_size', 18, $existing_layout) ?>px; 
                            font-weight: <?= val('ticket_code_font_weight', 'normal', $existing_layout) ?>; 
                            font-family: <?= val('ticket_code_font_family', 'roboto', $existing_layout) ?>;
                            left: <?= val('ticket_code_x', 50, $existing_layout) ?>px; top: <?= val('ticket_code_y', 380, $existing_layout) ?>px;">
                    #ISSPT-2026-XYZ
                </div>

                <!-- Calque : QR Code de Sécurité -->
                <div id="drag-qr" class="draggable-element absolute cursor-move border-2 border-dashed border-transparent hover:border-indigo-500 bg-white p-2 flex items-center justify-center text-black font-bold border border-slate-300 shadow-lg" data-target="qr" style="width:<?= val('qr_width', 150, $existing_layout) ?>px; height:<?= val('qr_height', 150, $existing_layout) ?>px; left: <?= val('qr_x', 900, $existing_layout) ?>px; top: <?= val('qr_y', 250, $existing_layout) ?>px;">
                    <div class="text-center">
                        <i class="fa-solid fa-qrcode text-4xl block opacity-80"></i>
                        <span class="block font-mono text-[9px] mt-1 text-slate-500">SECURE QR</span>
                    </div>
                </div>

            </div>
        </div>

        <!-- ZONE DE DROITE : INSPECTEUR DE PROPRIÉTÉS (PANNEAU CONTRÔLE) -->
        <div class="w-full lg:w-100 bg-white border border-gray-200 rounded-2xl p-5 shadow-sm space-y-5 overflow-y-auto max-h-[85vh]">
            <div class="border-b border-gray-100 pb-3">
                <h3 class="text-sm font-bold text-gray-800 uppercase tracking-wider flex items-center gap-2">
                    <i class="fa-solid fa-sliders text-slate-500"></i> Inspecteur Graphique
                </h3>
            </div>
            
            <form method="POST" id="layoutForm" class="space-y-4 text-xs text-gray-600">
                
                <div>
                    <label class="block font-semibold text-gray-700 mb-1">Nom du Layout Général</label>
                    <input type="text" name="layout_name" value="<?= val('layout_name', 'Configuration Standard', $existing_layout) ?>" class="w-full border border-gray-300 p-2 rounded-lg text-sm text-gray-900 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                </div>

                <!-- SECTION : INSTITUT -->
                <div class="bg-gray-50/70 p-3 rounded-xl border border-gray-200 space-y-2">
                    <span class="font-bold text-indigo-700 flex items-center gap-1.5"><i class="fa-solid fa-graduation-cap"></i> 1. En-tête Institut</span>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block mb-0.5 text-slate-500">Taille (px)</label>
                            <input type="number" name="institut_name_font_size" id="size-institut_name" value="<?= val('institut_name_font_size', 16, $existing_layout) ?>" class="w-full border p-1.5 rounded-md bg-white style-trigger font-medium">
                        </div>
                        <div>
                            <label class="block mb-0.5 text-slate-500">Couleur</label>
                            <input type="color" name="institut_name_color" id="color-institut_name" value="<?= val('institut_name_color', '#FFFFFF', $existing_layout) ?>" class="w-full h-8 border p-0.5 bg-white rounded-md cursor-pointer style-trigger">
                        </div>
                        <div>
                            <label class="block mb-0.5 text-slate-500">Graisse</label>
                            <select name="institut_name_font_weight" id="weight-institut_name" class="w-full border p-1.5 rounded-md bg-white style-trigger font-medium">
                                <option value="bold" <?= val('institut_name_font_weight', 'bold', $existing_layout) === 'bold' ? 'selected' : '' ?>>Gras</option>
                                <option value="normal" <?= val('institut_name_font_weight', 'bold', $existing_layout) === 'normal' ? 'selected' : '' ?>>Normal</option>
                            </select>
                        </div>
                        <div>
                            <label class="block mb-0.5 text-slate-500">Typographie</label>
                            <select name="institut_name_font_family" id="family-institut_name" class="w-full border p-1.5 rounded-md bg-white style-trigger font-medium">
                                <option value="roboto" <?= val('institut_name_font_family', 'roboto', $existing_layout) === 'roboto' ? 'selected' : '' ?>>Roboto (Neutre)</option>
                                <option value="montserrat" <?= val('institut_name_font_family', 'roboto', $existing_layout) === 'montserrat' ? 'selected' : '' ?>>Montserrat</option>
                                <option value="inter" <?= val('institut_name_font_family', 'roboto', $existing_layout) === 'inter' ? 'selected' : '' ?>>Inter</option>
                                <option value="poppins" <?= val('institut_name_font_family', 'roboto', $existing_layout) === 'poppins' ? 'selected' : '' ?>>Poppins</option>
                                <option value="playfair" <?= val('institut_name_font_family', 'roboto', $existing_layout) === 'playfair' ? 'selected' : '' ?>>Playfair (Luxe)</option>
                                <option value="cinzel" <?= val('institut_name_font_family', 'roboto', $existing_layout) === 'cinzel' ? 'selected' : '' ?>>Cinzel (Prestige)</option>
                            </select>
                        </div>
                    </div>
                    <input type="hidden" name="institut_name_x" id="x-institut_name" value="<?= val('institut_name_x', 40, $existing_layout) ?>">
                    <input type="hidden" name="institut_name_y" id="y-institut_name" value="<?= val('institut_name_y', 15, $existing_layout) ?>">
                </div>

                <!-- SECTION : LOGO -->
                <div class="bg-gray-50/70 p-3 rounded-xl border border-gray-200 space-y-2">
                    <span class="font-bold text-indigo-700 flex items-center gap-1.5"><i class="fa-solid fa-image"></i> 2. Dimensions Logo</span>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block mb-0.5 text-slate-500">Largeur (px)</label>
                            <input type="number" name="logo_width" id="w-logo" value="<?= val('logo_width', 100, $existing_layout) ?>" class="w-full border p-1.5 rounded-md bg-white logo-size-trigger font-medium">
                        </div>
                        <div>
                            <label class="block mb-0.5 text-slate-500">Hauteur (px)</label>
                            <input type="number" name="logo_height" id="h-logo" value="<?= val('logo_height', 100, $existing_layout) ?>" class="w-full border p-1.5 rounded-md bg-white logo-size-trigger font-medium">
                        </div>
                    </div>
                    <input type="hidden" name="logo_x" id="x-logo" value="<?= val('logo_x', 850, $existing_layout) ?>">
                    <input type="hidden" name="logo_y" id="y-logo" value="<?= val('logo_y', 30, $existing_layout) ?>">
                </div>

                <!-- SECTION : NOM EVENEMENT -->
                <div class="bg-gray-50/70 p-3 rounded-xl border border-gray-200 space-y-2">
                    <span class="font-bold text-slate-800 flex items-center gap-1.5"><i class="fa-solid fa-tag"></i> 3. Titre Événement</span>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block mb-0.5 text-slate-500">Taille (px)</label>
                            <input type="number" name="event_name_font_size" id="size-event_name" value="<?= val('event_name_font_size', 32, $existing_layout) ?>" class="w-full border p-1.5 rounded-md bg-white style-trigger font-medium">
                        </div>
                        <div>
                            <label class="block mb-0.5 text-slate-500">Couleur</label>
                            <input type="color" name="event_name_color" id="color-event_name" value="<?= val('event_name_color', '#FFFFFF', $existing_layout) ?>" class="w-full h-8 border p-0.5 bg-white rounded-md cursor-pointer style-trigger">
                        </div>
                        <div>
                            <label class="block mb-0.5 text-slate-500">Graisse</label>
                            <select name="event_name_font_weight" id="weight-event_name" class="w-full border p-1.5 rounded-md bg-white style-trigger font-medium">
                                <option value="bold" <?= val('event_name_font_weight', 'bold', $existing_layout) === 'bold' ? 'selected' : '' ?>>Gras</option>
                                <option value="normal" <?= val('event_name_font_weight', 'bold', $existing_layout) === 'normal' ? 'selected' : '' ?>>Normal</option>
                            </select>
                        </div>
                        <div>
                            <label class="block mb-0.5 text-slate-500">Typographie</label>
                            <select name="event_name_font_family" id="family-event_name" class="w-full border p-1.5 rounded-md bg-white style-trigger font-medium">
                                <option value="roboto" <?= val('event_name_font_family', 'roboto', $existing_layout) === 'roboto' ? 'selected' : '' ?>>Roboto (Neutre)</option>
                                <option value="montserrat" <?= val('event_name_font_family', 'roboto', $existing_layout) === 'montserrat' ? 'selected' : '' ?>>Montserrat (Géométrique)</option>
                                <option value="inter" <?= val('event_name_font_family', 'roboto', $existing_layout) === 'inter' ? 'selected' : '' ?>>Inter (Ultra-lisible)</option>
                                <option value="poppins" <?= val('event_name_font_family', 'roboto', $existing_layout) === 'poppins' ? 'selected' : '' ?>>Poppins (Moderne rond)</option>
                                <option value="playfair" <?= val('event_name_font_family', 'roboto', $existing_layout) === 'playfair' ? 'selected' : '' ?>>Playfair (Luxe / Gala)</option>
                                <option value="cinzel" <?= val('event_name_font_family', 'roboto', $existing_layout) === 'cinzel' ? 'selected' : '' ?>>Cinzel (Prestige / Officiel)</option>
                                <option value="lora" <?= val('event_name_font_family', 'roboto', $existing_layout) === 'lora' ? 'selected' : '' ?>>Lora (Raffiné)</option>
                                <option value="bebas" <?= val('event_name_font_family', 'roboto', $existing_layout) === 'bebas' ? 'selected' : '' ?>>Bebas Neue (Sport)</option>
                                <option value="oswald" <?= val('event_name_font_family', 'roboto', $existing_layout) === 'oswald' ? 'selected' : '' ?>>Oswald (Condensé)</option>
                                <option value="anton" <?= val('event_name_font_family', 'roboto', $existing_layout) === 'anton' ? 'selected' : '' ?>>Anton (Impact Max)</option>
                                <option value="caveat" <?= val('event_name_font_family', 'roboto', $existing_layout) === 'caveat' ? 'selected' : '' ?>>Caveat (Manuscrit)</option>
                                <option value="pacifico" <?= val('event_name_font_family', 'roboto', $existing_layout) === 'pacifico' ? 'selected' : '' ?>>Pacifico (Rétro)</option>
                            </select>
                        </div>
                    </div>
                    <input type="hidden" name="event_name_x" id="x-event_name" value="<?= val('event_name_x', 50, $existing_layout) ?>">
                    <input type="hidden" name="event_name_y" id="y-event_name" value="<?= val('event_name_y', 100, $existing_layout) ?>">
                </div>

                <!-- SECTION : DATE -->
                <div class="bg-gray-50/70 p-3 rounded-xl border border-gray-200 space-y-2">
                    <span class="font-bold text-slate-800 flex items-center gap-1.5"><i class="fa-solid fa-calendar-day"></i> 4. Date & Heure</span>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block mb-0.5 text-slate-500">Taille (px)</label>
                            <input type="number" name="event_date_font_size" id="size-event_date" value="<?= val('event_date_font_size', 18, $existing_layout) ?>" class="w-full border p-1.5 rounded-md bg-white style-trigger font-medium">
                        </div>
                        <div>
                            <label class="block mb-0.5 text-slate-500">Couleur</label>
                            <input type="color" name="event_date_color" id="color-event_date" value="<?= val('event_date_color', '#FFFFFF', $existing_layout) ?>" class="w-full h-8 border p-0.5 bg-white rounded-md cursor-pointer style-trigger">
                        </div>
                        <div>
                            <label class="block mb-0.5 text-slate-500">Graisse</label>
                            <select name="event_date_font_weight" id="weight-event_date" class="w-full border p-1.5 rounded-md bg-white style-trigger font-medium">
                                <option value="normal" <?= val('event_date_font_weight', 'normal', $existing_layout) === 'normal' ? 'selected' : '' ?>>Normal</option>
                                <option value="bold" <?= val('event_date_font_weight', 'normal', $existing_layout) === 'bold' ? 'selected' : '' ?>>Gras</option>
                            </select>
                        </div>
                        <div>
                            <label class="block mb-0.5 text-slate-500">Typographie</label>
                            <select name="event_date_font_family" id="family-event_date" class="w-full border p-1.5 rounded-md bg-white style-trigger font-medium">
                                <option value="Arial" <?= val('event_date_font_family', 'Arial', $existing_layout) === 'Arial' ? 'selected' : '' ?>>Arial</option>
                                <option value="roboto" <?= val('event_date_font_family', 'Arial', $existing_layout) === 'roboto' ? 'selected' : '' ?>>Roboto</option>
                                <option value="inter" <?= val('event_date_font_family', 'Arial', $existing_layout) === 'inter' ? 'selected' : '' ?>>Inter</option>
                            </select>
                        </div>
                    </div>
                    <input type="hidden" name="event_date_x" id="x-event_date" value="<?= val('event_date_x', 50, $existing_layout) ?>">
                    <input type="hidden" name="event_date_y" id="y-event_date" value="<?= val('event_date_y', 160, $existing_layout) ?>">
                </div>

                <!-- SECTION : LIEU -->
                <div class="bg-gray-50/70 p-3 rounded-xl border border-gray-200 space-y-2">
                    <span class="font-bold text-slate-800 flex items-center gap-1.5"><i class="fa-solid fa-map-marker-alt"></i> 5. Lieu</span>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block mb-0.5 text-slate-500">Taille (px)</label>
                            <input type="number" name="event_location_font_size" id="size-event_location" value="<?= val('event_location_font_size', 18, $existing_layout) ?>" class="w-full border p-1.5 rounded-md bg-white style-trigger font-medium">
                        </div>
                        <div>
                            <label class="block mb-0.5 text-slate-500">Couleur</label>
                            <input type="color" name="event_location_color" id="color-event_location" value="<?= val('event_location_color', '#FFFFFF', $existing_layout) ?>" class="w-full h-8 border p-0.5 bg-white rounded-md cursor-pointer style-trigger">
                        </div>
                        <div>
                            <label class="block mb-0.5 text-slate-500">Graisse</label>
                            <select name="event_location_font_weight" id="weight-event_location" class="w-full border p-1.5 rounded-md bg-white style-trigger font-medium">
                                <option value="normal" <?= val('event_location_font_weight', 'normal', $existing_layout) === 'normal' ? 'selected' : '' ?>>Normal</option>
                                <option value="bold" <?= val('event_location_font_weight', 'normal', $existing_layout) === 'bold' ? 'selected' : '' ?>>Gras</option>
                            </select>
                        </div>
                        <div>
                            <label class="block mb-0.5 text-slate-500">Typographie</label>
                            <select name="event_location_font_family" id="family-event_location" class="w-full border p-1.5 rounded-md bg-white style-trigger font-medium">
                                <option value="roboto" <?= val('event_location_font_family', 'roboto', $existing_layout) === 'roboto' ? 'selected' : '' ?>>Roboto</option>
                                <option value="montserrat" <?= val('event_location_font_family', 'roboto', $existing_layout) === 'montserrat' ? 'selected' : '' ?>>Montserrat</option>
                                <option value="inter" <?= val('event_location_font_family', 'roboto', $existing_layout) === 'inter' ? 'selected' : '' ?>>Inter</option>
                            </select>
                        </div>
                    </div>
                    <input type="hidden" name="event_location_x" id="x-event_location" value="<?= val('event_location_x', 50, $existing_layout) ?>">
                    <input type="hidden" name="event_location_y" id="y-event_location" value="<?= val('event_location_y', 200, $existing_layout) ?>">
                </div>

                <!-- SECTION : PARTICIPANT -->
                <div class="bg-gray-50/70 p-3 rounded-xl border border-gray-200 space-y-2">
                    <span class="font-bold text-slate-800 flex items-center gap-1.5"><i class="fa-solid fa-user-graduate"></i> 6. Bloc Étudiant</span>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block mb-0.5 text-slate-500">Taille (px)</label>
                            <input type="number" name="participant_name_font_size" id="size-participant_name" value="<?= val('participant_name_font_size', 28, $existing_layout) ?>" class="w-full border p-1.5 rounded-md bg-white style-trigger font-medium">
                        </div>
                        <div>
                            <label class="block mb-0.5 text-slate-500">Couleur</label>
                            <input type="color" name="participant_name_color" id="color-participant_name" value="<?= val('participant_name_color', '#FFFFFF', $existing_layout) ?>" class="w-full h-8 border p-0.5 bg-white rounded-md cursor-pointer style-trigger">
                        </div>
                        <div>
                            <label class="block mb-0.5 text-slate-500">Graisse</label>
                            <select name="participant_name_font_weight" id="weight-participant_name" class="w-full border p-1.5 rounded-md bg-white style-trigger font-medium">
                                <option value="bold" <?= val('participant_name_font_weight', 'bold', $existing_layout) === 'bold' ? 'selected' : '' ?>>Gras</option>
                                <option value="normal" <?= val('participant_name_font_weight', 'bold', $existing_layout) === 'normal' ? 'selected' : '' ?>>Normal</option>
                            </select>
                        </div>
                        <div>
                            <label class="block mb-0.5 text-slate-500">Typographie</label>
                            <select name="participant_name_font_family" id="family-participant_name" class="w-full border p-1.5 rounded-md bg-white style-trigger font-medium">
                                <option value="roboto" <?= val('participant_name_font_family', 'roboto', $existing_layout) === 'roboto' ? 'selected' : '' ?>>Roboto</option>
                                <option value="montserrat" <?= val('participant_name_font_family', 'roboto', $existing_layout) === 'montserrat' ? 'selected' : '' ?>>Montserrat</option>
                                <option value="playfair" <?= val('participant_name_font_family', 'roboto', $existing_layout) === 'playfair' ? 'selected' : '' ?>>Playfair</option>
                                <option value="caveat" <?= val('participant_name_font_family', 'roboto', $existing_layout) === 'caveat' ? 'selected' : '' ?>>Caveat</option>
                            </select>
                        </div>
                    </div>
                    <input type="hidden" name="participant_name_x" id="x-participant_name" value="<?= val('participant_name_x', 50, $existing_layout) ?>">
                    <input type="hidden" name="participant_name_y" id="y-participant_name" value="<?= val('participant_name_y', 300, $existing_layout) ?>">
                </div>

                <!-- SECTION : TICKET CODE -->
                <div class="bg-gray-50/70 p-3 rounded-xl border border-gray-200 space-y-2">
                    <span class="font-bold text-slate-800 flex items-center gap-1.5"><i class="fa-solid fa-barcode"></i> 7. Code Unique Numérique</span>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block mb-0.5 text-slate-500">Taille (px)</label>
                            <input type="number" name="ticket_code_font_size" id="size-ticket_code" value="<?= val('ticket_code_font_size', 18, $existing_layout) ?>" class="w-full border p-1.5 rounded-md bg-white style-trigger font-medium">
                        </div>
                        <div>
                            <label class="block mb-0.5 text-slate-500">Couleur</label>
                            <input type="color" name="ticket_code_color" id="color-ticket_code" value="<?= val('ticket_code_color', '#FFFFFF', $existing_layout) ?>" class="w-full h-8 border p-0.5 bg-white rounded-md cursor-pointer style-trigger">
                        </div>
                        <div>
                            <label class="block mb-0.5 text-slate-500">Graisse</label>
                            <select name="ticket_code_font_weight" id="weight-ticket_code" class="w-full border p-1.5 rounded-md bg-white style-trigger font-medium">
                                <option value="normal" <?= val('ticket_code_font_weight', 'normal', $existing_layout) === 'normal' ? 'selected' : '' ?>>Normal</option>
                                <option value="bold" <?= val('ticket_code_font_weight', 'normal', $existing_layout) === 'bold' ? 'selected' : '' ?>>Gras</option>
                            </select>
                        </div>
                        <div>
                            <label class="block mb-0.5 text-slate-500">Typographie</label>
                            <select name="ticket_code_font_family" id="family-ticket_code" class="w-full border p-1.5 rounded-md bg-white style-trigger font-medium">
                                <option value="roboto" <?= val('ticket_code_font_family', 'roboto', $existing_layout) === 'roboto' ? 'selected' : '' ?>>Roboto (Mono)</option>
                                <option value="inter" <?= val('ticket_code_font_family', 'roboto', $existing_layout) === 'inter' ? 'selected' : '' ?>>Inter</option>
                            </select>
                        </div>
                    </div>
                    <input type="hidden" name="ticket_code_x" id="x-ticket_code" value="<?= val('ticket_code_x', 50, $existing_layout) ?>">
                    <input type="hidden" name="ticket_code_y" id="y-ticket_code" value="<?= val('ticket_code_y', 380, $existing_layout) ?>">
                </div>

                <!-- SECTION : QR CODE -->
                <div class="bg-gray-50/70 p-3 rounded-xl border border-gray-200 space-y-2">
                    <span class="font-bold text-slate-800 flex items-center gap-1.5"><i class="fa-solid fa-qrcode"></i> 8. Zone QR Code</span>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block mb-0.5 text-slate-500">Largeur (px)</label>
                            <input type="number" name="qr_width" id="w-qr" value="<?= val('qr_width', 150, $existing_layout) ?>" class="w-full border p-1.5 rounded-md bg-white qr-size-trigger font-medium">
                        </div>
                        <div>
                            <label class="block mb-0.5 text-slate-500">Hauteur (px)</label>
                            <input type="number" name="qr_height" id="h-qr" value="<?= val('qr_height', 150, $existing_layout) ?>" class="w-full border p-1.5 rounded-md bg-white qr-size-trigger font-medium">
                        </div>
                    </div>
                    <input type="hidden" name="qr_x" id="x-qr" value="<?= val('qr_x', 900, $existing_layout) ?>">
                    <input type="hidden" name="qr_y" id="y-qr" value="<?= val('qr_y', 250, $existing_layout) ?>">
                </div>

                <!-- ZONE SUBLIME DES TEXTES LIBRES GENERES EN JS -->
                <div id="custom-controls-container" class="space-y-3"></div>

                <button type="submit" name="save_layout" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 rounded-xl shadow-lg shadow-indigo-600/30 transition-all text-sm mt-4 active:scale-[0.99] cursor-pointer">
                    <i class="fa-solid fa-floppy-disk mr-1"></i> Enregistrer la Configuration Active
                </button>
            </form>
        </div>
    </div>
</div>

<!-- CANVAS ENGINE JS PRO -->
<script>
let customTextCount = 0;

document.addEventListener("DOMContentLoaded", function() {
    initAllDraggables();

    // Changements de style textes natifs
    document.querySelectorAll('.style-trigger').forEach(input => {
        input.addEventListener('input', function() {
            const target = this.id.substring(this.id.indexOf('-') + 1);
            applyStylesToElement(target);
        });
    });

    // Fonction utilitaire pour récupérer la bonne police CSS avec son fallback
function getCssFontFamily(fontKey) {
    const fonts = {
        'roboto': "'Roboto', sans-serif",
        'montserrat': "'Montserrat', sans-serif",
        'inter': "'Inter', sans-serif",
        'poppins': "'Poppins', sans-serif",
        'playfair': "'Playfair Display', serif",
        'cinzel': "'Cinzel', serif",
        'lora': "'Lora', serif",
        'bebas': "'Bebas Neue', sans-serif",
        'oswald': "'Oswald', sans-serif",
        'anton': "'Anton', sans-serif",
        'caveat': "'Caveat', cursive",
        'pacifico': "'Pacifico', cursive"
    };
    return fonts[fontKey.toLowerCase()] || "'Roboto', sans-serif";
}

// 1. Gestion globale des styles (Textes, Polices, Couleurs, Tailles)
document.querySelectorAll('.style-trigger').forEach(trigger => {
    trigger.addEventListener('input', function() {
        const idAttr = this.id; // Ex: "color-event_name", "family-institut_name"
        if (!idAttr.includes('-')) return;

        const parts = idAttr.split('-');
        const type = parts[0];       // "color", "size", "weight", "family"
        const target = parts[1];     // "event_name", "institut_name", "participant_name", etc.
        
        // On cible l'élément sur le ticket de l'espace de travail
        const element = document.getElementById(`drag-${target}`);
        
        if (element) {
            switch (type) {
                case 'color':
                    element.style.color = this.value;
                    break;
                case 'size':
                    element.style.fontSize = `${this.value}px`;
                    break;
                case 'weight':
                    element.style.fontWeight = this.value;
                    break;
                case 'family':
                    element.style.fontFamily = getCssFontFamily(this.value);
                    break;
            }
        }
    });
});

// 2. Gestion globale des dimensions (QR Code et Logo)
document.querySelectorAll('.qr-size-trigger').forEach(input => {
    input.addEventListener('input', function() {
        // Gestion du QR Code
        const qrBlock = document.getElementById('drag-qr');
        if (qrBlock) {
            const wQr = document.getElementById('w-qr');
            const hQr = document.getElementById('h-qr');
            if (wQr) qrBlock.style.width = `${wQr.value}px`;
            if (hQr) qrBlock.style.height = `${hQr.value}px`;
        }

        // Bonus/Sécurité : Si tu as aussi des inputs 'w-logo' ou 'h-logo' pour redimensionner le logo
        const logoImg = document.getElementById('img-logo');
        if (logoImg) {
            const wLogo = document.getElementById('w-logo');
            const hLogo = document.getElementById('h-logo');
            if (wLogo) logoImg.style.width = `${wLogo.value}px`;
            if (hLogo) logoImg.style.height = `${hLogo.value}px`;
        }
    });
});

    // Redimensionnement Logo
    document.querySelectorAll('.logo-size-trigger').forEach(input => {
        input.addEventListener('input', function() {
            const img = document.getElementById('img-logo');
            if (img) {
                img.style.width = `${document.getElementById('w-logo').value}px`;
                img.style.height = `${document.getElementById('h-logo').value}px`;
            }
        });
    });

    // Injection des calques personnalisés sauvegardés depuis la BDD (JSON)
    <?php 
    if(!empty($existing_layout['custom_texts'])) {
        $saved_texts = json_decode($existing_layout['custom_texts'], true);
        if(is_array($saved_texts)) {
            foreach($saved_texts as $txt) {
                echo "injectSavedCustomText('".addslashes($txt['text'])."', {$txt['x']}, {$txt['y']}, {$txt['size']}, '{$txt['color']}');\n";
            }
        }
    }
    ?>
});

function initAllDraggables() {
    const canvas = document.getElementById("ticket-canvas");
    const draggables = document.querySelectorAll(".draggable-element");

    draggables.forEach(el => {
        const target = el.getAttribute("data-target");
        
        // Placement absolu d'origine
        const inputX = document.getElementById(`x-${target}`);
        const inputY = document.getElementById(`y-${target}`);
        if(inputX && inputY) {
            el.style.left = `${inputX.value}px`;
            el.style.top = `${inputY.value}px`;
        }
        
        applyStylesToElement(target);

        // Nettoyage événementiel pour éviter l'effet "glitch stack"
        el.onmousedown = null;

        el.addEventListener("mousedown", function(e) {
            e.preventDefault();
            el.classList.add('border-indigo-500', 'z-50');
            
            let shiftX = e.clientX - el.getBoundingClientRect().left;
            let shiftY = e.clientY - el.getBoundingClientRect().top;

            function moveAt(clientX, clientY) {
                const canvasRect = canvas.getBoundingClientRect();
                let newX = clientX - canvasRect.left - shiftX;
                let newY = clientY - canvasRect.top - shiftY;

                // Contraintes limites du Canvas principal
                if (newX < 0) newX = 0;
                if (newY < 0) newY = 0;
                if (newX + el.offsetWidth > canvasRect.width) newX = canvasRect.width - el.offsetWidth;
                if (newY + el.offsetHeight > canvasRect.height) newY = canvasRect.height - el.offsetHeight;

                el.style.left = `${newX}px`;
                el.style.top = `${newY}px`;

                const inputXField = document.getElementById(`x-${target}`);
                const inputYField = document.getElementById(`y-${target}`);
                if(inputXField && inputYField) {
                    inputXField.value = Math.round(newX);
                    inputYField.value = Math.round(newY);
                }
            }

            function onMouseMove(event) { moveAt(event.clientX, event.clientY); }
            document.addEventListener("mousemove", onMouseMove);

            document.addEventListener("mouseup", function() {
                document.removeEventListener("mousemove", onMouseMove);
                el.classList.remove('border-indigo-500', 'z-50');
            }, { once: true });
        });
    });
}

function applyStylesToElement(target) {
    const el = document.getElementById(`drag-${target}`);
    if (!el || target === 'qr' || target === 'logo') return;

    const fontSize = document.getElementById(`size-${target}`)?.value;
    const color = document.getElementById(`color-${target}`)?.value;
    const weight = document.getElementById(`weight-${target}`)?.value;
    const family = document.getElementById(`family-${target}`)?.value;

    if (fontSize) el.style.fontSize = `${fontSize}px`;
    if (color) el.style.color = color;
    if (weight) el.style.fontWeight = weight;
    if (family) el.style.fontFamily = family;
}

// AJOUT DE TEXTES PERSOS LIBRES (CALQUE DYNAMIQUE)
function addCustomTextNode() {
    customTextCount++;
    const targetName = `custom_node_${customTextCount}`;
    buildTextDOM(targetName, `Texte libre #${customTextCount}`, 100, 150, 18, '#FFFFFF');
    initAllDraggables();
}

function injectSavedCustomText(text, x, y, size, color) {
    customTextCount++;
    const targetName = `custom_node_${customTextCount}`;
    buildTextDOM(targetName, text, x, y, size, color);
    initAllDraggables();
}

function buildTextDOM(targetName, textValue, x, y, size, color) {
    const canvas = document.getElementById("ticket-canvas");
    const controlsContainer = document.getElementById("custom-controls-container");

    const textHtml = `
        <div id="drag-${targetName}" class="draggable-element absolute cursor-move p-1 border-2 border-dashed border-transparent hover:border-amber-500 rounded bg-black/40 text-white font-medium" data-target="${targetName}">
            ${textValue}
        </div>
    `;
    canvas.insertAdjacentHTML('beforeend', textHtml);

    const controlHtml = `
        <div class="bg-amber-50/50 p-3 rounded-xl border border-amber-200/70 space-y-2" id="control-box-${targetName}">
            <div class="flex justify-between items-center">
                <span class="font-bold text-amber-800 flex items-center gap-1"><i class="fa-solid fa-font"></i> Texte Libre</span>
                <button type="button" class="text-red-500 hover:text-red-700 font-semibold" onclick="removeCustomNode('${targetName}')"><i class="fa-solid fa-trash-can"></i></button>
            </div>
            <input type="text" name="custom_text_val[]" value="${textValue}" class="w-full border border-gray-300 p-1.5 rounded-md bg-white text-gray-900" oninput="updateCustomTextString('${targetName}', this.value)">
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="text-amber-900/60 block">Taille (px)</label>
                    <input type="number" name="custom_size[]" id="size-${targetName}" value="${size}" class="w-full border p-1 rounded bg-white text-gray-900" oninput="applyStylesToElement('${targetName}')">
                </div>
                <div>
                    <label class="text-amber-900/60 block">Couleur</label>
                    <input type="color" name="custom_color[]" id="color-${targetName}" value="${color}" class="w-full h-7 border p-0.5 bg-white rounded cursor-pointer" oninput="applyStylesToElement('${targetName}')">
                </div>
            </div>
            <input type="hidden" name="custom_x[]" id="x-${targetName}" value="${x}">
            <input type="hidden" name="custom_y[]" id="y-${targetName}" value="${y}">
        </div>
    `;
    controlsContainer.insertAdjacentHTML('beforeend', controlHtml);
}

function updateCustomTextString(target, val) {
    const el = document.getElementById(`drag-${target}`);
    if(el) el.innerText = val;
}

function removeCustomNode(target) {
    document.getElementById(`drag-${target}`)?.remove();
    document.getElementById(`control-box-${target}`)?.remove();
}
</script>

<?php
$content = ob_get_clean();
include "../layout.php";
?>