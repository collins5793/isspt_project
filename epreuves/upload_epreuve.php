<?php
session_start();
require_once '../includes/db.php';

use Spatie\PdfToImage\Pdf;

if(isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] === 0) {

    $titre = $_POST['titre'] ?? '';
    $filiere = $_POST['filiere'] ?? '';
    $type = $_POST['type'] ?? '';
    $niveau = $_POST['niveau'] ?? 'autre';
    $description = $_POST['description'] ?? '';

    $uploadDir = '../uploads/';
    $thumbDir = '../uploads/thumbs/';

    if(!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
    if(!is_dir($thumbDir)) mkdir($thumbDir, 0777, true);

    $filename = time() . '_' . basename($_FILES['pdf_file']['name']);
    $targetFile = $uploadDir . $filename;

    if(move_uploaded_file($_FILES['pdf_file']['tmp_name'], $targetFile)) {

        // --- Génération miniature PDF ---
        $thumbPath = $thumbDir . pathinfo($filename, PATHINFO_FILENAME) . '.jpg';
        try {
            $pdf = new Pdf($targetFile);
            $pdf->setPage(1)
                ->saveImage($thumbPath);
        } catch(Exception $e) {
            // Fallback: icône PDF si la miniature échoue
            $thumbPath = 'uploads/thumbs/pdf-icon.jpg';
        }

        // --- Insertion en base ---
        

        echo "✅ Upload et miniature PDF OK !";
    } else {
        echo "❌ Erreur lors de l'upload du fichier.";
    }
}
?>
