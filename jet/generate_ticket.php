<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
// generate_ticket.php
// Inclus séquentiellement dans evenement.php - Hérite de $pdo, $id_ticket_genere et $eventId
require_once __DIR__ . '/../vendor/autoload.php';
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
if (!isset($id_ticket_genere) || empty($id_ticket_genere)) {
    throw new Exception("Paramètres de génération du ticket manquants ou accès direct interdit.");
}

try {
    // ---------------------------------------------------------
    // 0. Système de Téléchargement Automatique des Polices (Roboto)
    // ---------------------------------------------------------
    $fontDir = '../assets/fonts/';
    if (!is_dir($fontDir)) {
        mkdir($fontDir, 0775, true);
    }

    $fonts = [
        'bold'    => $fontDir . 'Roboto-Bold.ttf',
        'regular' => $fontDir . 'Roboto-Regular.ttf'
    ];

    // URLs officielles des polices Google Fonts à télécharger si manquantes
    $fontUrls = [
        'bold'    => 'https://github.com/google/fonts/raw/main/apache/roboto/static/Roboto-Bold.ttf',
        'regular' => 'https://github.com/google/fonts/raw/main/apache/roboto/static/Roboto-Regular.ttf'
    ];

    $contextDownload = stream_context_create(['http' => ['timeout' => 5], 'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
    
    foreach ($fonts as $key => $fontPath) {
        if (!file_exists($fontPath)) {
            $fontData = @file_get_contents($fontUrls[$key], false, $contextDownload);
            if ($fontData) {
                file_put_contents($fontPath, $fontData);
            }
        }
    }

    $useTTF = file_exists($fonts['bold']) && file_exists($fonts['regular']);

    // ---------------------------------------------------------
    // 1. Extraction exhaustive des spécifications (Base de données)
    // ---------------------------------------------------------
    $stmtTicketData = $pdo->prepare("
        SELECT t.*, e.nom_evenement, e.event_start, e.lieu,
               et.nom AS etudiant_nom, et.prenom AS etudiant_prenom,
               cp.nom_complet AS externe_nom,
               tmpl.image_path AS template_image, tmpl.canvas_width, tmpl.canvas_height,
               lay.layout_name,
               lay.event_name_x, lay.event_name_y, lay.event_name_font_size, lay.event_name_color, lay.event_name_font_weight, lay.event_name_font_family,
               lay.event_date_x, lay.event_date_y, lay.event_date_font_size, lay.event_date_color, lay.event_date_font_weight, lay.event_date_font_family,
               lay.event_location_x, lay.event_location_y, lay.event_location_font_size, lay.event_location_color,
               lay.participant_name_x, lay.participant_name_y, lay.participant_name_font_size, lay.participant_name_color,
               lay.ticket_code_x, lay.ticket_code_y, lay.ticket_code_font_size, lay.ticket_code_color,
               lay.qr_x, lay.qr_y, lay.qr_width, lay.qr_height,
               lay.logo_x, lay.logo_y, lay.logo_width, lay.logo_height,
               lay.institut_name_x, lay.institut_name_y, lay.institut_name_font_size, lay.institut_name_color,
               lay.custom_texts
        FROM tickets t
        INNER JOIN evenements e ON t.event_id = e.id_evenement
        LEFT JOIN etudiants et ON t.user_id = et.id_etudiant
        LEFT JOIN codes_paiement cp ON t.external_participant_id = cp.id
        LEFT JOIN ticket_templates tmpl ON t.template_id = tmpl.id_template
        LEFT JOIN ticket_layouts lay ON t.layout_id = lay.id_layout
        WHERE t.id_ticket = ?
    ");
    $stmtTicketData->execute([$id_ticket_genere]);
    $ticketInfo = $stmtTicketData->fetch(PDO::FETCH_ASSOC);

    if (!$ticketInfo) {
        throw new Exception("Spécifications du ticket introuvables.");
    }

    // ---------------------------------------------------------
    // 2. Préparation et normalisation des variables textuelles
    // ---------------------------------------------------------
    $nomParticipant = "PARTICIPANT";
    if (!empty($ticketInfo['etudiant_nom'])) {
        $nomParticipant = mb_strtoupper($ticketInfo['etudiant_prenom'] . ' ' . $ticketInfo['etudiant_nom'], 'UTF-8');
    } elseif (!empty($ticketInfo['externe_nom'])) {
        $nomParticipant = mb_strtoupper($ticketInfo['externe_nom'], 'UTF-8');
    }

    $codeTicket     = $ticketInfo['code_ticket'];
    $nomEvenement   = mb_strtoupper($ticketInfo['nom_evenement'], 'UTF-8');
    $dateEvenement  = date('d/m/Y H:i', strtotime($ticketInfo['event_start']));
    $lieuEvenement  = mb_strtoupper($ticketInfo['lieu'], 'UTF-8');
    $nomInstitut    = "Institut Superieur Saint Paul Tarse";

    // ---------------------------------------------------------
    // 3. Initialisation de la surface graphique (Canvas)
    // ---------------------------------------------------------
    $templateRawPath = !empty($ticketInfo['template_image']) ? $ticketInfo['template_image'] : 'assets/images/default_template.png';
    $templateCleanPath = ltrim($templateRawPath, './');
    $templateImagePath = $_SERVER['DOCUMENT_ROOT'] . '/' . explode('/', $_SERVER['SCRIPT_NAME'])[1] . '/' . $templateCleanPath;

    $canvasWidth  = !empty($ticketInfo['canvas_width']) ? (int)$ticketInfo['canvas_width'] : 1200;
    $canvasHeight = !empty($ticketInfo['canvas_height']) ? (int)$ticketInfo['canvas_height'] : 500;

    if (!file_exists($templateImagePath)) {
        $image = imagecreatetruecolor($canvasWidth, $canvasHeight);
        $bgColor = imagecolorallocate($image, 20, 20, 20);
        imagefill($image, 0, 0, $bgColor);
    } else {
        $imageInfo = getimagesize($templateImagePath);
        switch ($imageInfo['mime'] ?? '') {
            case 'image/jpeg': case 'image/jpg': $image = imagecreatefromjpeg($templateImagePath); break;
            case 'image/png': $image = imagecreatefrompng($templateImagePath); break;
            default:
                $image = imagecreatetruecolor($canvasWidth, $canvasHeight);
                $bgColor = imagecolorallocate($image, 20, 20, 20);
                imagefill($image, 0, 0, $bgColor);
                break;
        }
    }

    imagealphablending($image, true);
    imagesavealpha($image, true);

    // ---------------------------------------------------------
    // 4. Déclaration des fonctions anonymes locales utilitaires
    // ---------------------------------------------------------
    $allocateColorHex = function($img, $hexColor) {
        $hexColor = ltrim($hexColor, '#');
        if (strlen($hexColor) === 3) {
            $r = hexdec(substr($hexColor, 0, 1) . substr($hexColor, 0, 1));
            $g = hexdec(substr($hexColor, 1, 1) . substr($hexColor, 1, 1));
            $b = hexdec(substr($hexColor, 2, 1) . substr($hexColor, 2, 1));
        } else {
            $r = hexdec(substr($hexColor, 0, 2));
            $g = hexdec(substr($hexColor, 2, 2));
            $b = hexdec(substr($hexColor, 4, 2));
        }
        return imagecolorallocate($img, $r, $g, $b);
    };

    $getBaselineY = function($y, $fontSize) {
        return ((int)$y < (int)$fontSize) ? (int)$fontSize + (int)$y + 5 : (int)$y;
    };

    // Fonction de rendu universelle (Bascule automatique propre si le téléchargement réseau échouait)
    $renderText = function($img, $text, $x, $y, $fontSize, $hexColor, $fontWeight, $fonts, $useTTF) use ($allocateColorHex, $getBaselineY) {
        $color = $allocateColorHex($img, $hexColor);
        
        if ($useTTF) {
            $font = ($fontWeight === 'bold') ? $fonts['bold'] : $fonts['regular'];
            $targetY = $getBaselineY($y, $fontSize);
            imagettftext($img, $fontSize, 0, $x, $targetY, $color, $font, $text);
        } else {
            $sysFont = 3;
            if ($fontSize > 24) $sysFont = 5;
            elseif ($fontSize < 14) $sysFont = 2;
            imagestring($img, $sysFont, $x, $y, $text, $color);
        }
    };

    // ---------------------------------------------------------
    // 5. Rendu dynamique de toutes les informations du Layout
    // ---------------------------------------------------------
    
    // 1. Nom de l'institut
    $renderText($image, $nomInstitut, 
        !empty($ticketInfo['institut_name_x']) ? (int)$ticketInfo['institut_name_x'] : 40,
        !empty($ticketInfo['institut_name_y']) ? (int)$ticketInfo['institut_name_y'] : 20,
        !empty($ticketInfo['institut_name_font_size']) ? (int)$ticketInfo['institut_name_font_size'] : 16,
        $ticketInfo['institut_name_color'] ?? '#FFFFFF', 'bold', $fonts, $useTTF
    );

    // 2. Nom de l'événement
    $renderText($image, $nomEvenement, 
        !empty($ticketInfo['event_name_x']) ? (int)$ticketInfo['event_name_x'] : 50,
        !empty($ticketInfo['event_name_y']) ? (int)$ticketInfo['event_name_y'] : 100,
        !empty($ticketInfo['event_name_font_size']) ? (int)$ticketInfo['event_name_font_size'] : 32,
        $ticketInfo['event_name_color'] ?? '#FFFFFF', $ticketInfo['event_name_font_weight'] ?? 'bold', $fonts, $useTTF
    );

    // 3. Date de l'événement
    $renderText($image, "DATE : " . $dateEvenement, 
        !empty($ticketInfo['event_date_x']) ? (int)$ticketInfo['event_date_x'] : 50,
        !empty($ticketInfo['event_date_y']) ? (int)$ticketInfo['event_date_y'] : 160,
        !empty($ticketInfo['event_date_font_size']) ? (int)$ticketInfo['event_date_font_size'] : 18,
        $ticketInfo['event_date_color'] ?? '#FFFFFF', $ticketInfo['event_date_font_weight'] ?? 'normal', $fonts, $useTTF
    );
http://localhost/isspt_projet/admins/evenement/ajouter_layout.php?id=8
    // 4. Lieu de l'événement
    $renderText($image, "LIEU : " . $lieuEvenement, 
        !empty($ticketInfo['event_location_x']) ? (int)$ticketInfo['event_location_x'] : 50,
        !empty($ticketInfo['event_location_y']) ? (int)$ticketInfo['event_location_y'] : 200,
        !empty($ticketInfo['event_location_font_size']) ? (int)$ticketInfo['event_location_font_size'] : 18,
        $ticketInfo['event_location_color'] ?? '#FFFFFF', 'normal', $fonts, $useTTF
    );

    // 5. Identité du participant
    $renderText($image, "PARTICIPANT : " . $nomParticipant, 
        !empty($ticketInfo['participant_name_x']) ? (int)$ticketInfo['participant_name_x'] : 50,
        !empty($ticketInfo['participant_name_y']) ? (int)$ticketInfo['participant_name_y'] : 300,
        !empty($ticketInfo['participant_name_font_size']) ? (int)$ticketInfo['participant_name_font_size'] : 28,
        $ticketInfo['participant_name_color'] ?? '#FFFFFF', 'bold', $fonts, $useTTF
    );

    // 6. Code de validation alphanumérique
    $renderText($image, "CODE : " . $codeTicket, 
        !empty($ticketInfo['ticket_code_x']) ? (int)$ticketInfo['ticket_code_x'] : 50,
        !empty($ticketInfo['ticket_code_y']) ? (int)$ticketInfo['ticket_code_y'] : 380,
        !empty($ticketInfo['ticket_code_font_size']) ? (int)$ticketInfo['ticket_code_font_size'] : 18,
        $ticketInfo['ticket_code_color'] ?? '#FFFFFF', 'normal', $fonts, $useTTF
    );

    // ---------------------------------------------------------
    // 6. Rendu des Textes Volontaires / Libres (custom_texts JSON)
    // ---------------------------------------------------------
    if (!empty($ticketInfo['custom_texts'])) {
        $customTexts = json_decode($ticketInfo['custom_texts'], true);
        if (is_array($customTexts)) {
            foreach ($customTexts as $ct) {
                $txtContent = $ct['text'] ?? '';
                if (trim($txtContent) === '') continue;

                $txtX     = isset($ct['x']) ? (int)$ct['x'] : 50;
                $txtY     = isset($ct['y']) ? (int)$ct['y'] : 420;
                $txtSize  = isset($ct['font_size']) ? (int)$ct['font_size'] : 14;
                $txtColor = $ct['color'] ?? '#FFFFFF';
                $txtWeight= $ct['font_weight'] ?? 'normal';
                
                $renderText($image, $txtContent, $txtX, $txtY, $txtSize, $txtColor, $txtWeight, $fonts, $useTTF);
            }
        }
    }

    // ---------------------------------------------------------
    // 7. Rendu des assets graphiques annexes (Logo & QR Code)
    // ---------------------------------------------------------
    // A. Incrustation du Logo de l'établissement
    $logoPath = $_SERVER['DOCUMENT_ROOT'] . '/' . explode('/', $_SERVER['SCRIPT_NAME'])[1] . '/assets/images/logo.png';
    if (file_exists($logoPath) && !empty($ticketInfo['logo_x'])) {
        $logoSrc = @imagecreatefrompng($logoPath);
        if ($logoSrc) {
            imagecopyresampled(
                $image, $logoSrc,
                (int)$ticketInfo['logo_x'], (int)$ticketInfo['logo_y'],
                0, 0,
                (int)$ticketInfo['logo_width'], (int)$ticketInfo['logo_height'],
                imagesx($logoSrc), imagesy($logoSrc)
            );
            imagedestroy($logoSrc);
        }
    }

    // B. Incrustation dynamique du QR Code via l'API Google Charts
    // B. Incrustation du QR Code via Endroid/QrCode (Ta méthode validée avec succès)
    if (!empty($ticketInfo['qr_x'])) {
        $qrWidth  = !empty($ticketInfo['qr_width']) ? (int)$ticketInfo['qr_width'] : 150;
        $qrHeight = !empty($ticketInfo['qr_height']) ? (int)$ticketInfo['qr_height'] : 150;
        
        try {
            // 1. Génération du QR Code en utilisant exactement ton architecture fonctionnelle
            $result = new \Endroid\QrCode\Builder\Builder(
                writer: new \Endroid\QrCode\Writer\PngWriter(),
                data: (string)$codeTicket,
                size: (int)$qrWidth,
                margin: 10
            );

            // 2. Build du QR Code
            $qr = $result->build();
            
            // 3. Gestion sécurisée du dossier temporaire pour l'incrustation GD
            $tempDir = __DIR__ . '/temp/';
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0775, true);
            }
            $qrPath = $tempDir . 'qr_' . $codeTicket . '.png';
            
            // 4. Sauvegarde de l'image propre générée à la volée
            $qr->saveToFile($qrPath);
            
            // 5. Fusion et positionnement du QR Code sur le canevas GD de ton ticket
            if (file_exists($qrPath)) {
                $qrSrc = @imagecreatefrompng($qrPath);
                if ($qrSrc) {
                    imagecopyresampled(
                        $image, $qrSrc,
                        (int)$ticketInfo['qr_x'], (int)$ticketInfo['qr_y'],
                        0, 0,
                        $qrWidth, $qrHeight,
                        imagesx($qrSrc), imagesy($qrSrc)
                    );
                    imagedestroy($qrSrc);
                }
                unlink($qrPath); // Nettoyage immédiat du fichier temporaire
            }
            
        } catch (\Throwable $e) {
            // Enregistrement discret de l'erreur dans les logs au cas où un problème de droits d'écriture survient sur /temp
            error_log("Erreur d'incrustation du QR Code : " . $e->getMessage());
        }
    }


    // ---------------------------------------------------------
    // 8. Sauvegarde physique du rendu final (.PNG)
    // ---------------------------------------------------------
    $outputFolder = '../uploads/tickets_generes/';
    if (!is_dir($outputFolder)) {
        mkdir($outputFolder, 0775, true);
    }

    $fileName = 'ticket_' . $codeTicket . '.png';
    $finalImagePath = $outputFolder . $fileName;

    imagepng($image, $finalImagePath);
    imagedestroy($image);

    // Synchronisation du chemin de l'image générée en base de données
    $stmtUpdateTicket = $pdo->prepare("UPDATE tickets SET ticket_image_path = ? WHERE id_ticket = ?");
    $stmtUpdateTicket->execute([$finalImagePath, $id_ticket_genere]);


    // ---------------------------------------------------------
    // 9. GÉNÉRATION PROPRE ET ENREGISTREMENT DU PDF (Sur Mesure)
    // ---------------------------------------------------------
    if (!file_exists($finalImagePath)) {
        throw new Exception("L'image de base du ticket n'existe pas pour l'injection PDF.");
    }

    // On crée le chemin du PDF en remplaçant l'extension .png par .pdf
    $finalPdfPath = str_replace('.png', '.pdf', $finalImagePath);

    // Récupération des dimensions réelles de l'image générée (en pixels)
    $imageSize = getimagesize($finalImagePath);
    if (!$imageSize) {
        throw new Exception("Impossible de lire les dimensions de l'image du ticket.");
    }
    $imgWidthPx  = $imageSize[0];
    $imgHeightPx = $imageSize[1];

    // Conversion dynamique des pixels en millimètres pour FPDF (Standard 72 DPI)
    // Formule : (pixels * 25.4) / 72
    $pdfWidthMm  = ($imgWidthPx * 25.4) / 72;
    $pdfHeightMm = ($imgHeightPx * 25.4) / 72;

    // Instanciation de FPDF avec un format de page personnalisé UNIQUE au ticket
    $pdf = new \FPDF('L', 'mm', [$pdfWidthMm, $pdfHeightMm]);
    
    // On désactive totalement les marges par défaut pour coller aux bords de l'image
    $pdf->SetMargins(0, 0, 0);
    $pdf->SetAutoPageBreak(false);
    
    $pdf->AddPage();
    $pdf->SetTitle("Ticket_" . $codeTicket);

    // Incrustation de l'image à l'origine exacte (0,0) prenant 100% de la surface personnalisée
    $pdf->Image($finalImagePath, 0, 0, $pdfWidthMm, $pdfHeightMm);

    // Sauvegarde PHYSIQUE du fichier .pdf sur ton serveur dans le dossier uploads
    $pdf->Output('F', $finalPdfPath); 

    // Mise à jour EXCLUSIVE de la colonne ticket_pdf_path pour ton ticket
    $stmtUpdatePdf = $pdo->prepare("UPDATE tickets SET ticket_pdf_path = ? WHERE id_ticket = ?");
    $stmtUpdatePdf->execute([$finalPdfPath, $id_ticket_genere]);

    

} catch (Exception $e) {
    error_log("Échec de la génération graphique du ticket : " . $e->getMessage());
    throw $e;
}