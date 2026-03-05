<?php
// evenement.php

session_start(); // ⚠️ très important pour $_SESSION
require_once '../includes/db.php'; // connexion PDO
define('BASE_URL', '/isspt_projet/'); // chemin relatif depuis localhost
$base_url = BASE_URL;

// ---------------------------
// Vérification si l'utilisateur est connecté
// ---------------------------
$isLogged = false;
$isAdmin = false;
$userName = '';
$userAvatar = '../assets/images/default-avatar.png'; // avatar par défaut

// Vérifier étudiant
if (isset($_SESSION['etudiant_id'])) {
    $stmt = $pdo->prepare("SELECT nom, prenom, photo FROM etudiants WHERE id_etudiant = ? AND statut = 'actif'");
    $stmt->execute([$_SESSION['etudiant_id']]);
    $etudiant = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($etudiant) {
        $isLogged = true;
        $userName = $etudiant['prenom'] . ' ' . $etudiant['nom'];
        if (!empty($etudiant['photo'])) {
            $userAvatar = '../uploads/etudiants/' . $etudiant['photo'];
        }
    } else {
        session_unset();
        session_destroy();
        header("Location: login.php");
        exit;
    }
}

// Vérifier administrateur
if (isset($_SESSION['admin_id'])) {
    $stmt = $pdo->prepare("SELECT nom, prenom, role FROM administrateurs WHERE id_admin = ?");
    $stmt->execute([$_SESSION['admin_id']]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($admin) {
        $isLogged = true;
        $isAdmin = true;
        $userName = $admin['prenom'] . ' ' . $admin['nom'];
    } else {
        session_unset();
        session_destroy();
        header("Location: login.php");
        exit;
    }
}

// ---------------------------
// Vérifier que l'id de l'événement est présent
// ---------------------------
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die('Événement introuvable.');
}
$eventId = (int)$_GET['id'];

// ---------------------------
// Récupérer l'événement
// ---------------------------
$stmt = $pdo->prepare("
    SELECT e.*, ay.label AS annee_scolaire, a.nom AS admin_nom, a.prenom AS admin_prenom
    FROM evenements e
    LEFT JOIN academic_years ay ON e.academic_year_id = ay.id
    LEFT JOIN administrateurs a ON e.cree_par = a.id_admin
    WHERE e.id_evenement = :id
");
$stmt->execute(['id' => $eventId]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$event) die('Événement introuvable.');

// ---------------------------
// Récupérer artistes
// ---------------------------
$stmtArtistes = $pdo->prepare("SELECT * FROM evenement_artistes WHERE id_evenement = :id");
$stmtArtistes->execute(['id' => $eventId]);
$artistes = $stmtArtistes->fetchAll(PDO::FETCH_ASSOC);

// ---------------------------
// Récupérer galerie
// ---------------------------
$stmtGalerie = $pdo->prepare("SELECT * FROM galerie WHERE event_id = :id ORDER BY uploaded_at DESC");
$stmtGalerie->execute(['id' => $eventId]);
$medias = $stmtGalerie->fetchAll(PDO::FETCH_ASSOC);


// ---------------------------
// Ajouter un commentaire si formulaire soumis
// ---------------------------
// ---------------------------
// Ajouter un commentaire si formulaire soumis
// ---------------------------
if ($isLogged && isset($_POST['submit_comment'])) {
    $message = trim($_POST['message'] ?? '');

    if (!empty($message)) {
        // Déterminer l'ID et le type de l'utilisateur connecté
        $idEtudiant = null;
        $idAdmin = null;
        $userType = null;

        if (isset($_SESSION['etudiant_id'])) {
            $idEtudiant = $_SESSION['etudiant_id'];
            $userType = 'etudiant';
        } elseif (isset($_SESSION['admin_id'])) {
            $idAdmin = $_SESSION['admin_id'];
            $userType = 'admin';
        }

        if ($userType) {
            $stmt = $pdo->prepare("
                INSERT INTO commentaires (event_id, id_etudiant, id_admin, user_type, message)
                VALUES (:event_id, :id_etudiant, :id_admin, :user_type, :message)
            ");

            $stmt->execute([
                'event_id'    => $eventId,
                'id_etudiant' => $idEtudiant,
                'id_admin'    => $idAdmin,
                'user_type'   => $userType,
                'message'     => $message
            ]);

            // Redirection pour éviter le double envoi
            header("Location: evenement.php?id=$eventId");
            exit;
        }
    } else {
        $error = "Le commentaire ne peut pas être vide.";
    }
}


// ===============================
// CHARGEMENT DES COMMENTAIRES
// ===============================
$stmtComments = $pdo->prepare("
    SELECT c.*, 
           e.nom AS etudiant_nom, e.prenom AS etudiant_prenom,
           a.nom AS admin_nom, a.prenom AS admin_prenom, a.role AS admin_role,
           ae.nom AS admin_etudiant_nom, ae.prenom AS admin_etudiant_prenom
    FROM commentaires c
    LEFT JOIN etudiants e ON c.id_etudiant = e.id_etudiant
    LEFT JOIN administrateurs a ON c.id_admin = a.id_admin
    LEFT JOIN etudiants ae ON a.id_etudiant = ae.id_etudiant
    WHERE c.event_id = :event_id
    ORDER BY c.date_commentaire DESC
");
$stmtComments->execute(['event_id' => $eventId]);
$comments = $stmtComments->fetchAll(PDO::FETCH_ASSOC);





// ---------------------------
// Upload fichiers média
// ---------------------------
$uploadMessage = '';
if($isLogged && isset($_POST['submit_upload']) && isset($_FILES['file'])) {
    $event_id = (int)$_POST['event_id'];
    $caption = trim($_POST['caption'] ?? '');
    $file = $_FILES['file'];
    $maxSize = 5 * 1024 * 1024;

    if($file['error'] !== 0) {
        $uploadMessage = "Erreur lors de l'upload.";
    } elseif($file['size'] > $maxSize) {
        $uploadMessage = "Fichier trop volumineux (max 5 Mo).";
    } else {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedImages = ['jpg','jpeg','png','gif','webp'];
        $allowedVideos = ['mp4','webm','mov'];

        if(in_array($ext, $allowedImages)) $type = 'image';
        elseif(in_array($ext, $allowedVideos)) $type = 'video';
        else $type = null;

        if($type) {
            $newName = uniqid('media_') . '.' . $ext;
            $uploadDir = __DIR__ . '/uploads/evenements/';
            if(!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $dest = $uploadDir . $newName;

            if(move_uploaded_file($file['tmp_name'], $dest)) {
                $stmt = $pdo->prepare("
                    INSERT INTO galerie (event_id, file_path, file_type, caption) 
                    VALUES (:event_id, :file_path, :file_type, :caption)
                ");
                $stmt->execute([
                    'event_id' => $event_id,
                    'file_path' => 'uploads/evenements/' . $newName,
                    'file_type' => $type,
                    'caption' => $caption
                ]);
                header("Location: evenement.php?id=$event_id");
                exit;
            } else $uploadMessage = "Erreur lors du déplacement du fichier.";
        } else $uploadMessage = "Type de fichier non autorisé.";
    }
}

// ---------------------------
// Ajouter commentaire
// ---------------------------
if($isLogged && isset($_POST['submit_comment']) && isset($_SESSION['etudiant_id'])) {
    $event_id = (int)$_POST['event_id'];
    $etudiant_id = (int)$_SESSION['etudiant_id'];
    $message = trim($_POST['message']);
    if(!empty($message)) {
        $stmt = $pdo->prepare("
            INSERT INTO commentaires (event_id, id_etudiant, message)
            VALUES (:event_id, :id_etudiant, :message)
        ");
        $stmt->execute([
            'event_id' => $event_id,
            'id_etudiant' => $etudiant_id,
            'message' => $message
        ]);
        header("Location: evenement.php?id=$event_id");
        exit;
    }
}

?>


<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($event['nom_evenement']) ?> - JET</title>
<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/moment@2.29.4/moment.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/moment/locale/fr.js"></script>
<style>
  /* =============================================
   STYLE ÉVÉNEMENT - PREMIUM & MODERNE
   ============================================= */

/* ==================== VARIABLES CSS ==================== */
:root {
  /* Couleurs principales */
  --color-primary: rgb(8, 0, 32);
  --color-accent: rgb(186, 40, 30);
  --color-text: #ffffff;
  --color-text-light: #dddddd;
  --color-dark: #1a1a1a;
  
  /* Couleurs supplémentaires */
  --color-glass: rgba(255, 255, 255, 0.1);
  --color-glass-dark: rgba(0, 0, 0, 0.3);
  --color-overlay: rgba(186, 40, 30, 0.1);
  
  /* Typographie */
  --font-primary: 'Segoe UI', system-ui, -apple-system, sans-serif;
  --font-heading: 'Montserrat', 'Arial Black', sans-serif;
  
  /* Espacements */
  --spacing-xs: 0.5rem;
  --spacing-sm: 1rem;
  --spacing-md: 1.5rem;
  --spacing-lg: 2rem;
  --spacing-xl: 3rem;
  --spacing-xxl: 5rem;
  
  /* Bordures */
  --border-radius-sm: 6px;
  --border-radius-md: 12px;
  --border-radius-lg: 20px;
  --border-radius-xl: 30px;
  
  /* Ombres */
  --shadow-soft: 0 4px 20px rgba(0, 0, 0, 0.1);
  --shadow-medium: 0 8px 30px rgba(0, 0, 0, 0.2);
  --shadow-heavy: 0 15px 50px rgba(0, 0, 0, 0.3);
  --shadow-glow: 0 0 20px rgba(186, 40, 30, 0.4);
  
  /* Transitions */
  --transition-fast: 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  --transition-normal: 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  --transition-slow: 0.5s cubic-bezier(0.4, 0, 0.2, 1);
}

/* ==================== RESET & BASE ==================== */
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

html {
  scroll-behavior: smooth;
}

body {
  font-family: var(--font-primary);
  background: var(--color-primary);
  color: var(--color-text);
  line-height: 1.7;
  overflow-x: hidden;
  background-image: 
    radial-gradient(circle at 10% 20%, rgba(186, 40, 30, 0.1) 0%, transparent 20%),
    radial-gradient(circle at 90% 80%, rgba(186, 40, 30, 0.05) 0%, transparent 20%);
}

/* ==================== ANIMATIONS AOS-LIKE ==================== */
@keyframes fadeIn {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}

@keyframes fadeUp {
  from {
    opacity: 0;
    transform: translateY(40px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes fadeInUp {
  from {
    opacity: 0;
    transform: translateY(30px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes zoomIn {
  from {
    opacity: 0;
    transform: scale(0.9);
  }
  to {
    opacity: 1;
    transform: scale(1);
  }
}

@keyframes glowPulse {
  0%, 100% {
    box-shadow: 0 0 10px rgba(186, 40, 30, 0.4);
  }
  50% {
    box-shadow: 0 0 20px rgba(186, 40, 30, 0.8);
  }
}

@keyframes levitate {
  0%, 100% {
    transform: translateY(0);
  }
  50% {
    transform: translateY(-10px);
  }
}

@keyframes slideInLeft {
  from {
    opacity: 0;
    transform: translateX(-50px);
  }
  to {
    opacity: 1;
    transform: translateX(0);
  }
}

@keyframes slideInRight {
  from {
    opacity: 0;
    transform: translateX(50px);
  }
  to {
    opacity: 1;
    transform: translateX(0);
  }
}

/* Classes d'animation */
[data-aos="fade-in"] {
  animation: fadeIn 1s var(--transition-slow) both;
}

[data-aos="fade-up"] {
  animation: fadeUp 0.8s var(--transition-slow) both;
}

[data-aos="zoom-in"] {
  animation: zoomIn 0.6s var(--transition-slow) both;
}

[data-aos="slide-left"] {
  animation: slideInLeft 0.8s var(--transition-slow) both;
}

[data-aos="slide-right"] {
  animation: slideInRight 0.8s var(--transition-slow) both;
}

/* Délais d'animation */
[data-aos-delay="100"] { animation-delay: 0.1s; }
[data-aos-delay="200"] { animation-delay: 0.2s; }
[data-aos-delay="300"] { animation-delay: 0.3s; }
[data-aos-delay="400"] { animation-delay: 0.4s; }
[data-aos-delay="500"] { animation-delay: 0.5s; }

/* ==================== LAYOUT GÉNÉRAL ==================== */
.section {
  padding: var(--spacing-xxl) 0;
  position: relative;
}

.section-title {
  font-family: var(--font-heading);
  font-size: clamp(1.8rem, 4vw, 2.5rem);
  font-weight: 700;
  margin-bottom: var(--spacing-lg);
  text-align: center;
  position: relative;
  display: inline-block;
  left: 50%;
  transform: translateX(-50%);
}

.section-title::after {
  content: '';
  position: absolute;
  bottom: -8px;
  left: 50%;
  transform: translateX(-50%);
  width: 80px;
  height: 4px;
  background: linear-gradient(90deg, transparent, var(--color-accent), transparent);
  border-radius: 2px;
}

/* ==================== 1️⃣ HERO SECTION ==================== */
.hero-section {
  position: relative;
  min-height: 80vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: 
    linear-gradient(135deg, var(--color-primary) 0%, rgba(8, 0, 32, 0.9) 100%),
    url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 800"><rect width="1200" height="800" fill="%23080020"/><circle cx="200" cy="200" r="100" fill="%23ba281e" opacity="0.1"/><circle cx="900" cy="500" r="150" fill="%23ba281e" opacity="0.05"/></svg>');
  background-size: cover;
  background-attachment: fixed;
  background-position: center;
  overflow: hidden;
  text-align: center;
  padding: var(--spacing-xxl) var(--spacing-md);
}

.hero-section::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: 
    radial-gradient(ellipse at 20% 50%, rgba(186, 40, 30, 0.2) 0%, transparent 50%),
    radial-gradient(ellipse at 80% 20%, rgba(186, 40, 30, 0.15) 0%, transparent 50%);
  pointer-events: none;
}

.hero-content {
  position: relative;
  z-index: 2;
  max-width: 800px;
  margin: 0 auto;
}

.hero-title {
  font-family: var(--font-heading);
  font-size: clamp(2.5rem, 6vw, 4rem);
  font-weight: 800;
  margin-bottom: var(--spacing-md);
  text-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
  line-height: 1.2;
  background: linear-gradient(135deg, #fff 0%, #ddd 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.hero-subtitle {
  font-size: clamp(1.1rem, 2.5vw, 1.3rem);
  margin-bottom: var(--spacing-lg);
  color: var(--color-text-light);
  font-weight: 300;
  letter-spacing: 0.5px;
}

.hero-buttons {
  display: flex;
  gap: var(--spacing-md);
  justify-content: center;
  flex-wrap: wrap;
  margin-top: var(--spacing-lg);
}

/* ==================== BOUTONS ==================== */
.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: var(--spacing-sm) var(--spacing-lg);
  border-radius: var(--border-radius-lg);
  font-weight: 600;
  text-decoration: none;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  transition: var(--transition-normal);
  position: relative;
  overflow: hidden;
  font-size: 0.9rem;
  border: none;
  cursor: pointer;
  min-width: 160px;
}

.btn::before {
  content: '';
  position: absolute;
  top: 0;
  left: -100%;
  width: 100%;
  height: 100%;
  background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
  transition: var(--transition-normal);
}

.btn:hover::before {
  left: 100%;
}

.primary-btn {
  background: var(--color-accent);
  color: var(--color-text);
  box-shadow: var(--shadow-medium);
}

.primary-btn:hover {
  transform: translateY(-3px);
  box-shadow: var(--shadow-glow);
  animation: glowPulse 2s infinite;
}

.secondary-btn {
  background: transparent;
  color: var(--color-text);
  border: 2px solid var(--color-accent);
  backdrop-filter: blur(10px);
}

.secondary-btn:hover {
  background: var(--color-accent);
  transform: translateY(-3px);
  box-shadow: var(--shadow-medium);
}

/* ==================== 2️⃣ DESCRIPTION ==================== */
.description-section {
  background: var(--color-glass);
  backdrop-filter: blur(20px);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: var(--border-radius-lg);
  margin: 0 var(--spacing-md);
  padding: var(--spacing-xl);
  position: relative;
  overflow: hidden;
}

.description-section::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 1px;
  background: linear-gradient(90deg, transparent, var(--color-accent), transparent);
}

.event-description {
  font-size: 1.1rem;
  line-height: 1.8;
  color: var(--color-text-light);
  margin-bottom: var(--spacing-md);
}

.event-price {
  font-size: 1.3rem;
  font-weight: 700;
  color: var(--color-accent);
  text-align: center;
  padding: var(--spacing-sm);
  background: var(--color-glass-dark);
  border-radius: var(--border-radius-md);
  display: inline-block;
}

/* ==================== 3️⃣ INFOS PRATIQUES ==================== */
.infos-section {
  padding: var(--spacing-xl) var(--spacing-md);
}

.info-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: var(--spacing-md);
  max-width: 1000px;
  margin: 0 auto;
}

.info-item {
  background: var(--color-glass);
  backdrop-filter: blur(10px);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: var(--border-radius-md);
  padding: var(--spacing-lg);
  text-align: center;
  transition: var(--transition-normal);
  position: relative;
  overflow: hidden;
}

.info-item::before {
  content: '';
  position: absolute;
  top: 0;
  left: -100%;
  width: 100%;
  height: 100%;
  background: linear-gradient(90deg, transparent, rgba(186, 40, 30, 0.1), transparent);
  transition: var(--transition-normal);
}

.info-item:hover {
  transform: translateY(-5px);
  border-color: var(--color-accent);
  box-shadow: var(--shadow-glow);
}

.info-item:hover::before {
  left: 100%;
}

.info-item span {
  display: block;
  font-size: 1.5rem;
  margin-bottom: var(--spacing-xs);
}

/* ==================== 4️⃣ TIMER ==================== */
.timer-section {
  text-align: center;
  padding: var(--spacing-xl) var(--spacing-md);
}

.timer-box {
  font-family: 'Courier New', monospace;
  font-size: clamp(1.5rem, 4vw, 2.5rem);
  font-weight: 700;
  background: var(--color-dark);
  color: var(--color-accent);
  padding: var(--spacing-lg);
  border-radius: var(--border-radius-lg);
  display: inline-block;
  box-shadow: var(--shadow-glow);
  border: 2px solid rgba(186, 40, 30, 0.3);
  animation: glowPulse 3s infinite;
  letter-spacing: 2px;
}

/* ==================== 5️⃣ ARTISTES ==================== */
.artists-section {
  padding: var(--spacing-xl) var(--spacing-md);
}

.artist-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: var(--spacing-lg);
  max-width: 1200px;
  margin: 0 auto;
}

.artist-card {
  background: var(--color-glass);
  backdrop-filter: blur(15px);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: var(--border-radius-lg);
  padding: var(--spacing-lg);
  text-align: center;
  transition: var(--transition-normal);
  position: relative;
  overflow: hidden;
}

.artist-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 3px;
  background: linear-gradient(90deg, var(--color-accent), transparent);
}

.artist-card:hover {
  transform: translateY(-8px) scale(1.02);
  box-shadow: var(--shadow-heavy);
  border-color: var(--color-accent);
}

.artist-photo {
  width: 120px;
  height: 120px;
  border-radius: 50%;
  object-fit: cover;
  border: 3px solid var(--color-accent);
  box-shadow: var(--shadow-medium);
  margin: 0 auto var(--spacing-md);
  transition: var(--transition-normal);
}

.artist-card:hover .artist-photo {
  transform: scale(1.1);
  box-shadow: var(--shadow-glow);
}

.artist-name {
  font-family: var(--font-heading);
  font-size: 1.3rem;
  font-weight: 700;
  margin-bottom: var(--spacing-xs);
  color: var(--color-text);
}

.artist-role {
  color: var(--color-accent);
  font-weight: 600;
  margin-bottom: var(--spacing-sm);
  font-size: 0.9rem;
  text-transform: uppercase;
  letter-spacing: 1px;
}

.artist-description {
  color: var(--color-text-light);
  font-size: 0.95rem;
  line-height: 1.6;
}

/* ==================== 6️⃣ UPLOAD SECTION ==================== */
.upload-section {
  padding: var(--spacing-xl) var(--spacing-md);
}

.upload-form {
  background: var(--color-glass);
  backdrop-filter: blur(15px);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: var(--border-radius-lg);
  padding: var(--spacing-xl);
  max-width: 600px;
  margin: 0 auto;
}

.upload-form label {
  display: block;
  margin-bottom: var(--spacing-xs);
  font-weight: 600;
  color: var(--color-text);
}

.input-note {
  display: block;
  font-size: 0.8rem;
  color: var(--color-text-light);
  margin-bottom: var(--spacing-sm);
}

.input-file {
  width: 100%;
  padding: var(--spacing-sm);
  background: var(--color-glass-dark);
  border: 2px dashed rgba(255, 255, 255, 0.2);
  border-radius: var(--border-radius-md);
  color: var(--color-text);
  margin-bottom: var(--spacing-md);
  transition: var(--transition-normal);
  cursor: pointer;
}

.input-file:hover {
  border-color: var(--color-accent);
  background: rgba(186, 40, 30, 0.1);
}

.input-textarea {
  width: 100%;
  padding: var(--spacing-sm);
  background: var(--color-glass-dark);
  border: 1px solid rgba(255, 255, 255, 0.2);
  border-radius: var(--border-radius-md);
  color: var(--color-text);
  margin-bottom: var(--spacing-md);
  resize: vertical;
  min-height: 100px;
  transition: var(--transition-normal);
}

.input-textarea:focus {
  border-color: var(--color-accent);
  box-shadow: 0 0 0 2px rgba(186, 40, 30, 0.3);
}

.upload-status {
  text-align: center;
  margin-top: var(--spacing-md);
  padding: var(--spacing-sm);
  border-radius: var(--border-radius-md);
}

.upload-status p {
  color: var(--color-accent);
  font-weight: 600;
}

/* ==================== 7️⃣ GALERIE ==================== */
.gallery-section {
  padding: var(--spacing-xl) var(--spacing-md);
}

.gallery {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: var(--spacing-md);
  max-width: 1400px;
  margin: 0 auto;
}

.media-card {
  background: var(--color-glass);
  backdrop-filter: blur(10px);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: var(--border-radius-lg);
  overflow: hidden;
  transition: var(--transition-normal);
  position: relative;
}

.media-card:hover {
  transform: scale(1.05);
  box-shadow: var(--shadow-heavy);
  border-color: var(--color-accent);
}

.media-image,
.media-video {
  width: 100%;
  height: 250px;
  object-fit: cover;
  display: block;
  transition: var(--transition-normal);
}

.media-card:hover .media-image,
.media-card:hover .media-video {
  transform: scale(1.1);
}

.media-caption {
  padding: var(--spacing-sm);
  background: var(--color-glass-dark);
  color: var(--color-text-light);
  font-size: 0.9rem;
  text-align: center;
}

/* ==================== 8️⃣ COMMENTAIRES ==================== */
.comments-section {
  padding: var(--spacing-xl) var(--spacing-md);
}

.comment-form {
  background: var(--color-glass);
  backdrop-filter: blur(15px);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: var(--border-radius-lg);
  padding: var(--spacing-lg);
  max-width: 600px;
  margin: 0 auto var(--spacing-xl);
}

.comment-textarea {
  width: 100%;
  padding: var(--spacing-sm);
  background: var(--color-glass-dark);
  border: 1px solid rgba(255, 255, 255, 0.2);
  border-radius: var(--border-radius-md);
  color: var(--color-text);
  margin-bottom: var(--spacing-md);
  resize: vertical;
  min-height: 100px;
  transition: var(--transition-normal);
}

.comment-textarea:focus {
  border-color: var(--color-accent);
  box-shadow: 0 0 0 2px rgba(186, 40, 30, 0.3);
}

.comment-info {
  text-align: center;
  color: var(--color-text-light);
  font-style: italic;
  margin-bottom: var(--spacing-lg);
}

.comments-list {
  max-width: 800px;
  margin: 0 auto;
}

.comment-card {
  background: var(--color-glass);
  backdrop-filter: blur(10px);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: var(--border-radius-lg);
  padding: var(--spacing-lg);
  margin-bottom: var(--spacing-md);
  transition: var(--transition-normal);
  position: relative;
}

.comment-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  width: 4px;
  height: 100%;
  background: var(--color-accent);
  border-radius: var(--border-radius-sm) 0 0 var(--border-radius-sm);
}

.comment-card:hover {
  transform: translateX(5px);
  border-color: var(--color-accent);
}

.comment-header {
  display: flex;
  justify-content: between;
  align-items: center;
  margin-bottom: var(--spacing-sm);
  flex-wrap: wrap;
  gap: var(--spacing-sm);
}

.comment-author {
  font-weight: 700;
  color: var(--color-accent);
  font-size: 1rem;
}

.comment-date {
  color: var(--color-text-light);
  font-size: 0.8rem;
}

.comment-text {
  color: var(--color-text-light);
  line-height: 1.6;
}

/* ==================== 9️⃣ CTA FINAL ==================== */
.cta-section {
  text-align: center;
  padding: var(--spacing-xxl) var(--spacing-md);
  background: linear-gradient(135deg, var(--color-primary) 0%, rgba(8, 0, 32, 0.9) 100%);
  position: relative;
  overflow: hidden;
}

.cta-section::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: 
    radial-gradient(circle at 30% 70%, rgba(186, 40, 30, 0.2) 0%, transparent 50%),
    radial-gradient(circle at 70% 30%, rgba(186, 40, 30, 0.15) 0%, transparent 50%);
  pointer-events: none;
}

.cta-title {
  font-family: var(--font-heading);
  font-size: clamp(2rem, 5vw, 3rem);
  font-weight: 800;
  margin-bottom: var(--spacing-lg);
  position: relative;
  z-index: 2;
}

.cta-section .primary-btn {
  font-size: 1.1rem;
  padding: var(--spacing-md) var(--spacing-xl);
  animation: levitate 3s ease-in-out infinite;
  position: relative;
  z-index: 2;
}

/* ==================== RESPONSIVE DESIGN ==================== */
@media (max-width: 768px) {
  .section {
    padding: var(--spacing-xl) 0;
  }
  
  .hero-section {
    min-height: 60vh;
    background-attachment: scroll;
  }
  
  .hero-buttons {
    flex-direction: column;
    align-items: center;
  }
  
  .btn {
    width: 100%;
    max-width: 280px;
  }
  
  .info-grid {
    grid-template-columns: 1fr;
  }
  
  .artist-grid {
    grid-template-columns: 1fr;
  }
  
  .gallery {
    grid-template-columns: 1fr;
  }
  
  .description-section,
  .upload-form,
  .comment-form {
    margin: 0 var(--spacing-sm);
    padding: var(--spacing-lg);
  }
  
  .comment-header {
    flex-direction: column;
    align-items: flex-start;
  }
}

@media (max-width: 480px) {
  :root {
    --spacing-xs: 0.25rem;
    --spacing-sm: 0.75rem;
    --spacing-md: 1rem;
    --spacing-lg: 1.5rem;
    --spacing-xl: 2rem;
    --spacing-xxl: 3rem;
  }
  
  .hero-title {
    font-size: 2rem;
  }
  
  .hero-subtitle {
    font-size: 1rem;
  }
  
  .section-title {
    font-size: 1.5rem;
  }
  
  .timer-box {
    font-size: 1.2rem;
    padding: var(--spacing-md);
  }
}

/* ==================== ACCESSIBILITÉ ==================== */
@media (prefers-reduced-motion: reduce) {
  * {
    animation-duration: 0.01ms !important;
    animation-iteration-count: 1 !important;
    transition-duration: 0.01ms !important;
  }
}

/* Focus visible pour accessibilité */
.btn:focus-visible,
.input-file:focus-visible,
.input-textarea:focus-visible,
.comment-textarea:focus-visible {
  outline: 2px solid var(--color-accent);
  outline-offset: 2px;
}

/* ==================== CHARGEMENT PROGRESSIF ==================== */
.fade-in {
  opacity: 0;
  transition: opacity 0.6s ease;
}

.fade-in.visible {
  opacity: 1;
}
</style>
</head>
<body>
    <?php include "../includes/header.php"; ?>

<!-- =============================== -->
<!--         1️⃣ HERO HEADER         -->
<!-- =============================== -->
<header id="hero" class="hero-section" data-aos="fade-in">
    <div class="hero-content">
        <h1 class="hero-title"><?= htmlspecialchars($event['nom_evenement']) ?></h1>
        <p class="hero-subtitle">
            <?= htmlspecialchars($event['type_evenement']) ?> |
            <?= date('d M Y', strtotime($event['event_start'])) ?> |
            <?= htmlspecialchars($event['lieu']) ?>
        </p>

        <div class="hero-buttons">
            <a class="btn primary-btn" href="inscription.php?id=<?= $eventId ?>">S’inscrire / Réserver</a>
            <a class="btn secondary-btn" href="index.php">Retour à l’accueil</a>
        </div>
    </div>
</header>

<!-- =============================== -->
<!--      2️⃣ DESCRIPTION EVENT       -->
<!-- =============================== -->
<section class="section description-section" data-aos="fade-up">
    <h2 class="section-title">Description</h2>

    <p class="event-description"><?= nl2br(htmlspecialchars($event['description'])) ?></p>

    <?php if ($event['prix_ticket'] > 0): ?>
        <p class="event-price">Prix du ticket : <?= number_format($event['prix_ticket'], 2) ?> F</p>
    <?php endif; ?>
</section>

<!-- =============================== -->
<!--      3️⃣ INFORMATIONS PRATIQUES  -->
<!-- =============================== -->
<section class="section infos-section" data-aos="fade-up">
    <h2 class="section-title">📌 Infos pratiques</h2>

    <div class="info-grid">
        <div class="info-item"><span>🗓️ Date :</span> <?= date('d M Y', strtotime($event['event_start'])) ?></div>
        <div class="info-item"><span>🕒 Heure :</span> <?= date('H:i', strtotime($event['event_start'])) ?></div>
        <div class="info-item"><span>📍 Lieu :</span> <?= htmlspecialchars($event['lieu']) ?></div>
        <div class="info-item"><span>🎟️ Participation :</span> <?= $event['prix_ticket'] > 0 ? number_format($event['prix_ticket'], 2) . " F" : "Gratuit" ?></div>
    </div>
</section>

<!-- =============================== -->
<!--         4️⃣ COMPTE À REBOURS     -->
<!-- =============================== -->
<section class="section timer-section" data-aos="fade-up">
    <h2 class="section-title">⏰ Compte à rebours</h2>
    <div id="timer" class="timer-box"></div>
</section>

<script>
const eventDate = moment("<?= $event['event_start'] ?>");
function updateTimer() {
    const now = moment();
    const diff = eventDate.diff(now);
    if(diff <= 0){
        document.getElementById('timer').innerText = "L'événement a commencé !";
        clearInterval(timerInterval);
        return;
    }
    const duration = moment.duration(diff);
    document.getElementById('timer').innerText = 
        `${duration.days()}j ${duration.hours()}h ${duration.minutes()}m ${duration.seconds()}s`;
}
const timerInterval = setInterval(updateTimer, 1000);
updateTimer();
</script>

<!-- =============================== -->
<!--       5️⃣ ARTISTES / INVITÉS     -->
<!-- =============================== -->
<?php if(count($artistes) > 0): ?>
<section class="section artists-section" data-aos="fade-up">
    <h2 class="section-title">🎤 Artistes invités</h2>

    <div class="artist-grid">
        <?php foreach($artistes as $art): ?>
        <div class="artist-card">
            <?php if($art['photo']): ?>
            <img class="artist-photo" src="<?= htmlspecialchars($art['photo']) ?>" alt="<?= htmlspecialchars($art['nom_artiste']) ?>">
            <?php endif; ?>

            <h3 class="artist-name">
                <?= htmlspecialchars($art['nom_artiste']) ?>
                <?= $art['pseudonyme'] ? "(" . htmlspecialchars($art['pseudonyme']) . ")" : "" ?>
            </h3>

            <?php if($art['role']): ?>
            <p class="artist-role">Rôle : <?= htmlspecialchars($art['role']) ?></p>
            <?php endif; ?>

            <?php if($art['description']): ?>
            <p class="artist-description"><?= nl2br(htmlspecialchars($art['description'])) ?></p>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<!-- =============================== -->
<!--       6️⃣ UPLOAD MEDIA           -->
<!-- =============================== -->
<section class="section upload-section" data-aos="fade-up">
    <h2 class="section-title">📤 Partagez vos moments</h2>

    <form id="uploadForm" class="upload-form" enctype="multipart/form-data" method="post" action="">
        <input type="hidden" name="event_id" value="<?= $eventId ?>">

        <label>Fichier :</label>
        <small class="input-note">Image ou vidéo (max 5 Mo)</small>
        <input type="file" class="input-file" name="file" accept="image/*,video/*" required>

        <label>Commentaire / Légende :</label>
        <textarea class="input-textarea" name="caption" rows="3" placeholder="Votre commentaire..."></textarea>

        <button class="btn primary-btn" type="submit" name="submit_upload">Envoyer</button>
    </form>

    <div id="uploadStatus" class="upload-status">
        <?php if(isset($uploadMessage)) echo '<p>' . htmlspecialchars($uploadMessage) . '</p>'; ?>
    </div>
</section>

<!-- =============================== -->
<!--           7️⃣ GALERIE           -->
<!-- =============================== -->
<?php if(count($medias) > 0): ?>
<section class="section gallery-section" data-aos="fade-up">
    <h2 class="section-title">🖼️ Galerie des moments</h2>

    <div class="gallery">
        <?php
        $stmtGalerie = $pdo->prepare("SELECT * FROM galerie WHERE event_id = :id ORDER BY uploaded_at DESC");
        $stmtGalerie->execute(['id' => $eventId]);
        $medias = $stmtGalerie->fetchAll(PDO::FETCH_ASSOC);

        foreach($medias as $media): ?>
            <div class="media-card" data-aos="zoom-in">
                <?php if($media['file_type'] === 'image'): ?>
                    <img class="media-image" src="<?= htmlspecialchars($media['file_path']) ?>" alt="<?= htmlspecialchars($media['caption']) ?>">
                <?php else: ?>
                    <video class="media-video" src="<?= htmlspecialchars($media['file_path']) ?>" controls></video>
                <?php endif; ?>

                <?php if(!empty($media['caption'])): ?>
                    <p class="media-caption"><?= nl2br(htmlspecialchars($media['caption'])) ?></p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<!-- =============================== -->
<!--         8️⃣ COMMENTAIRES         -->
<!-- =============================== -->
<section class="section comments-section" data-aos="fade-up">
    <h2 class="section-title">💬 Commentaires</h2>

    <?php if($isLogged): ?>
    <form class="comment-form" method="post" action="evenement.php?id=<?= $eventId ?>">
        <textarea class="comment-textarea" name="message" rows="3" placeholder="Votre commentaire..." required></textarea>
        <button class="btn primary-btn" type="submit" name="submit_comment">Envoyer</button>
    </form>
    <?php else: ?>
        <p class="comment-info">Veuillez vous connecter pour laisser un commentaire.</p>
    <?php endif; ?>

    <div class="comments-list">
        <?php foreach($comments as $c):
            $authorName = "Utilisateur inconnu";

            if ($c['user_type'] === 'etudiant' && !empty($c['etudiant_nom'])) {
                $authorName = $c['etudiant_prenom'] . ' ' . $c['etudiant_nom'];
            } elseif ($c['user_type'] === 'admin') {
                if ($c['admin_role'] === 'bureau' && !empty($c['admin_etudiant_nom'])) {
                    $authorName = $c['admin_etudiant_prenom'] . ' ' . $c['admin_etudiant_nom'];
                } elseif (!empty($c['admin_nom'])) {
                    $authorName = $c['admin_prenom'] . ' ' . $c['admin_nom'] . ' (Admin)';
                }
            }
        ?>
        <div class="comment-card" data-aos="fade-up">
            <div class="comment-header">
                <strong class="comment-author"><?= htmlspecialchars($authorName) ?></strong>
                <span class="comment-date"><?= date('d M Y H:i', strtotime($c['date_commentaire'])) ?></span>
            </div>

            <p class="comment-text"><?= nl2br(htmlspecialchars($c['message'])) ?></p>
        </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- =============================== -->
<!--         9️⃣ CALL TO ACTION       -->
<!-- =============================== -->
<section class="section cta-section" data-aos="fade-up">
    <h2 class="cta-title">🎉 Participez maintenant !</h2>
    <a class="btn primary-btn" href="inscription.php?id=<?= $eventId ?>">S’inscrire / Réserver</a>
</section>

<?php include "../includes/footer.php"; ?>
</body>

</html>
