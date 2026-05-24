<?php
session_start();
require_once '../includes/db.php';

// Protection : Il faut être connecté
$isAdmin = isset($_SESSION['admin_id']);
$isEtudiant = isset($_SESSION['etudiant_id']);

if (!$isAdmin && !$isEtudiant) {
    header("Location: ../etudiant/login_etudiant.php");
    exit;
}

// Récupération et validation de l'ID de l'épreuve
$id_epreuve = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id_epreuve <= 0) {
    die("ID d'épreuve invalide.");
}

// 1. Récupération des informations sur l'épreuve
$stmt = $pdo->prepare("SELECT file_path FROM epreuves WHERE id_epreuve = ?");
$stmt->execute([$id_epreuve]);
$epreuve = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$epreuve) {
    die("Épreuve introuvable.");
}

$relative_path = '../admins/epreuve/uploads/' . $epreuve['file_path'];

if (!file_exists($relative_path)) {
    die("Le fichier physique n'existe pas sur le serveur.");
}

// 2. Identification de l'étudiant qui télécharge
$id_etudiant = 0;

if ($isEtudiant) {
    $id_etudiant = intval($_SESSION['etudiant_id']);
} elseif ($isAdmin) {
    // Si c'est un admin, on vérifie s'il est rattaché à un profil étudiant (bureau des étudiants)
    $stmtAdmin = $pdo->prepare("SELECT id_etudiant FROM administrateurs WHERE id_admin = ?");
    $stmtAdmin->execute([intval($_SESSION['admin_id'])]);
    $admin = $stmtAdmin->fetch(PDO::FETCH_ASSOC);
    if ($admin && !empty($admin['id_etudiant'])) {
        $id_etudiant = intval($admin['id_etudiant']);
    }
}

// 3. Collecte des métadonnées environnementales
$ip_adresse = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
// En cas de proxy / Cloudflare, on essaie de récupérer la vraie IP
if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $ip_adresse = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
}

$device = substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown Device', 0, 100);

// 4. Insertion propre dans la base de données (si l'ID étudiant a pu être mappé)
if ($id_etudiant > 0) {
    try {
        $logQuery = $pdo->prepare("
            INSERT INTO epreuves_downloads (id_epreuve, id_etudiant, date_download, ip_adresse, device) 
            VALUES (?, ?, NOW(), ?, ?)
        ");
        $logQuery->execute([$id_epreuve, $id_etudiant, $ip_adresse, $device]);
    } catch (PDOException $e) {
        // Optionnel : Loggez l'erreur en arrière-plan sans bloquer le téléchargement de l'étudiant
    }
}

// 5. Envoi sécurisé du fichier PDF (Force le téléchargement)
// On nettoie le tampon de sortie pour éviter de corrompre le PDF
if (ob_get_level()) {
    ob_end_clean();
}

header('Content-Description: File Transfer');
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . basename($relative_path) . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' * filesize($relative_path));

readfile($relative_path);
exit;