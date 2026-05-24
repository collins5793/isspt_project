<?php
// On démarre la session au tout début
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/db.php'; // connexion PDO
define('BASE_URL', '/isspt_projet/'); // chemin relatif depuis localhost
$base_url = BASE_URL;

// -----------------------------------------------------------------------------
// 1. GESTION DES SESSIONS & AUTHENTIFICATION
// -----------------------------------------------------------------------------
$isLogged = false;
$isAdmin = false;
$userName = '';
$userAvatar = '../assets/images/default-avatar.png'; // avatar par défaut
$id_etudiant_connecte = null;
$id_admin_connecte = null;
$user_type = null;

// Vérification de la session Étudiant
if (isset($_SESSION['etudiant_id'])) {
    $stmt = $pdo->prepare("SELECT nom, prenom, photo FROM etudiants WHERE id_etudiant = ? AND statut = 'actif'");
    $stmt->execute([$_SESSION['etudiant_id']]);
    $etudiant = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($etudiant) {
        $isLogged = true;
        $user_type = 'etudiant';
        $id_etudiant_connecte = (int)$_SESSION['etudiant_id'];
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

// Vérification de la session Administrateur (si pas déjà connecté comme étudiant)
if (!$isLogged && isset($_SESSION['admin_id'])) {
    $stmt = $pdo->prepare("SELECT nom, prenom, role FROM administrateurs WHERE id_admin = ?");
    $stmt->execute([$_SESSION['admin_id']]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($admin) {
        $isLogged = true;
        $isAdmin = true;
        $user_type = 'admin';
        $id_admin_connecte = (int)$_SESSION['admin_id'];
        $userName = $admin['prenom'] . ' ' . $admin['nom'];
    } else {
        session_unset();
        session_destroy();
        header("Location: login.php");
        exit;
    }
}

// -----------------------------------------------------------------------------
// 2. RÉCUPÉRATION DU CONCOURS / ÉVÉNEMENT
// -----------------------------------------------------------------------------
$id_concours = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id_concours <= 0) {
    die("ID de concours invalide ou manquant.");
}

// Messages d'alerte pour l'utilisateur
$message_success = "";
$message_error = "";
$uploadMessage = "";

// -----------------------------------------------------------------------------
// 3. TRAITEMENTS DES FORMULAIRES (POST)
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // A. AJOUT D'UN COMMENTAIRE
    if (isset($_POST['submit_comment']) || isset($_POST['action_comment'])) {
        if (!$isLogged) {
            $message_error = "Vous devez être connecté pour publier un commentaire.";
        } else {
            $msg = trim($_POST['message'] ?? '');
            if (!empty($msg)) {
                $stmt = $pdo->prepare("
                    INSERT INTO commentaires (concours_id, event_id, id_etudiant, id_admin, user_type, message, date_commentaire) 
                    VALUES (:concours_id, :event_id, :id_etudiant, :id_admin, :user_type, :message, NOW())
                ");
                
                $execution = $stmt->execute([
                    'concours_id' => $id_concours,
                    'event_id'    => $id_concours, // S'assure de remplir les deux variantes structurelles au cas où
                    'id_etudiant' => $id_etudiant_connecte,
                    'id_admin'    => $id_admin_connecte,
                    'user_type'   => $user_type,
                    'message'     => $msg
                ]);

                if ($execution) {
                    header("Location: details_concours.php?id=" . $id_concours . "&success=1#comments-box");
                    exit;
                } else {
                    $message_error = "Impossible d'enregistrer le commentaire.";
                }
            } else {
                $message_error = "Le message ne peut pas être vide.";
            }
        }
    }

    // B. INSCRIPTION RAPIDE AU CONCOURS
    if (isset($_POST['action_register'])) {
        if (!$id_etudiant_connecte) {
            $message_error = "Vous devez être connecté en tant qu'étudiant pour vous inscrire.";
        } else {
            // Vérifier si déjà inscrit
            $check = $pdo->prepare("SELECT id_participant FROM concours_participants WHERE id_concours = ? AND id_etudiant = ?");
            $check->execute([$id_concours, $id_etudiant_connecte]);
            
            if ($check->rowCount() > 0) {
                $message_error = "Vous êtes déjà inscrit à ce concours !";
            } else {
                // Génération d'un numéro de participant unique
                $num_part = "PART-" . time() . "-" . $id_etudiant_connecte;
                
                $ins = $pdo->prepare("INSERT INTO concours_participants (id_concours, id_etudiant, numero_participant, statut, date_inscription) VALUES (?, ?, ?, 'en_attente', NOW())");
                if ($ins->execute([$id_concours, $id_etudiant_connecte, $num_part])) {
                    header("Location: details_concours.php?id=" . $id_concours . "&registered=1");
                    exit;
                } else {
                    $message_error = "Une erreur est survenue lors de l'inscription.";
                }
            }
        }
    }

    // C. UPLOAD DE MÉDIAS DANS LA GALERIE
    if (isset($_POST['submit_upload']) && isset($_FILES['file'])) {
        if (!$isLogged) {
            $uploadMessage = "Vous devez être connecté pour uploader des fichiers.";
        } else {
            $caption = trim($_POST['caption'] ?? '');
            $file = $_FILES['file'];
            $maxSize = 5 * 1024 * 1024; // 5 Mo

            if ($file['error'] !== 0) {
                $uploadMessage = "Erreur lors de l'upload du fichier.";
            } elseif ($file['size'] > $maxSize) {
                $uploadMessage = "Fichier trop volumineux (max 5 Mo).";
            } else {
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $allowedImages = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                $allowedVideos = ['mp4', 'webm', 'mov'];

                if (in_array($ext, $allowedImages)) {
                    $type = 'image';
                } elseif (in_array($ext, $allowedVideos)) {
                    $type = 'video';
                } else {
                    $type = null;
                }

                if ($type) {
                    $newName = uniqid('media_') . '.' . $ext;
                    $uploadDir = __DIR__ . '/uploads/evenements/';
                    
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    
                    $dest = $uploadDir . $newName;

                    if (move_uploaded_file($file['tmp_name'], $dest)) {
                        $stmt = $pdo->prepare("
                            INSERT INTO galerie (concours_id, file_path, file_type, caption, uploaded_at) 
                            VALUES (:concours_id, :file_path, :file_type, :caption, NOW())
                        ");
                        $stmt->execute([
                            'concours_id' => $id_concours,
                            'file_path'   => 'uploads/evenements/' . $newName,
                            'file_type'   => $type,
                            'caption'     => $caption
                        ]);
                        
                        header("Location: details_concours.php?id=" . $id_concours . "&upload=1#album-section");
                        exit;
                    } else {
                        $uploadMessage = "Erreur lors du déplacement du fichier vers le dossier final.";
                    }
                } else {
                    $uploadMessage = "Type de fichier non autorisé (Images et vidéos uniquement).";
                }
            }
        }
    }
}

// -----------------------------------------------------------------------------
// 4. CAPTURE DES MESSAGES DE SUCCÈS VIA URL (POST-REDIRECT-GET)
// -----------------------------------------------------------------------------
if (isset($_GET['success'])) $message_success = "Votre commentaire a été publié avec succès !";
if (isset($_GET['registered'])) $message_success = "Félicitations ! Votre demande d'inscription a été prise en compte avec succès.";
if (isset($_GET['upload'])) $message_success = "Média ajouté à l'album du concours avec succès !";

// -----------------------------------------------------------------------------
// 5. RÉCUPÉRATION DES DONNÉES DE LA PAGE (READ)
// -----------------------------------------------------------------------------

// Informations du Concours
$query = $pdo->prepare("SELECT c.*, y.label as annee_academique 
                        FROM concours c 
                        LEFT JOIN academic_years y ON c.academic_year_id = y.id 
                        WHERE c.id_concours = ?");
$query->execute([$id_concours]);
$concours = $query->fetch(PDO::FETCH_ASSOC);

if (!$concours) {
    die("Le concours demandé n'existe pas.");
}

// Détermination de l'état du concours
$deadline_ts = !empty($concours['date_limite_inscription']) ? strtotime($concours['date_limite_inscription']) : null;
$is_closed = ($deadline_ts && $deadline_ts < time()) || ($concours['statut'] === 'termine');

// Compter les inscriptions actives (en attente ou validées)
$count_part = $pdo->prepare("SELECT COUNT(*) FROM concours_participants WHERE id_concours = ? AND statut != 'refuse'");
$count_part->execute([$id_concours]);
$current_participants = $count_part->fetchColumn();

// Vérifier si l'étudiant connecté actuel est déjà inscrit
$is_already_registered = false;
if ($id_etudiant_connecte) {
    $check_reg = $pdo->prepare("SELECT id_participant FROM concours_participants WHERE id_concours = ? AND id_etudiant = ?");
    $check_reg->execute([$id_concours, $id_etudiant_connecte]);
    $is_already_registered = ($check_reg->rowCount() > 0);
}

// Icône FontAwesome en fonction du type de concours
$icon_type = 'fa-star';
switch ($concours['type_concours']) {
    case 'academique': $icon_type = 'fa-graduation-cap'; break;
    case 'football':   $icon_type = 'fa-futbol'; break;
    case 'danse':      $icon_type = 'fa-shoe-prints'; break;
    case 'musique':    $icon_type = 'fa-music'; break;
}

// Récupération des Commentaires avec jointures propres
$stmtComments = $pdo->prepare("
    SELECT c.*, 
           e.nom AS etudiant_nom, e.prenom AS etudiant_prenom, e.photo AS etudiant_photo,
           a.nom AS admin_nom, a.prenom AS admin_prenom, a.role AS admin_role,
           ae.nom AS admin_etudiant_nom, ae.prenom AS admin_etudiant_prenom
    FROM commentaires c
    LEFT JOIN etudiants e ON c.id_etudiant = e.id_etudiant
    LEFT JOIN administrateurs a ON c.id_admin = a.id_admin
    LEFT JOIN etudiants ae ON a.id_etudiant = ae.id_etudiant
    WHERE c.concours_id = :id_concours OR c.event_id = :id_concours
    ORDER BY c.date_commentaire DESC
");
$stmtComments->execute(['id_concours' => $id_concours]);
$comments = $stmtComments->fetchAll(PDO::FETCH_ASSOC);

// Récupération de la Galerie Média (Images et Vidéos de l'événement)
$galerie_q = $pdo->prepare("SELECT * FROM galerie WHERE (concours_id = ? OR event_id = ?) ORDER BY uploaded_at DESC");
$galerie_q->execute([$id_concours, $id_concours]);
$images_galerie = $galerie_q->fetchAll(PDO::FETCH_ASSOC);
?>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($concours['nom_concours']) ?> – Détails du Concours | JET ISSPT</title>
    
    <!-- Polices & FontAwesome -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            /* Couleurs principales */
            --color-primary: rgb(8, 0, 32);
            --color-primary-dark: #050014;
            --color-accent: rgb(186, 40, 30);
            --color-accent-hover: rgb(210, 45, 35);
            --color-text: #ffffff;
            --color-text-light: #dddddd;
            --color-dark: #141414;
            
            /* Couleurs supplémentaires */
            --color-gold: #FFD700;
            --color-gold-light: #FFED4E;
            --color-glass: rgba(255, 255, 255, 0.05);
            --color-glass-dark: rgba(0, 0, 0, 0.45);
            --color-glass-border: rgba(255, 255, 255, 0.08);
            
            /* Bordures */
            --border-radius-sm: 8px;
            --border-radius-md: 16px;
            --border-radius-lg: 24px;
            --border-radius-xl: 32px;
            
            /* Ombres */
            --shadow-soft: 0 4px 20px rgba(0, 0, 0, 0.2);
            --shadow-medium: 0 8px 30px rgba(0, 0, 0, 0.4);
            --shadow-heavy: 0 15px 50px rgba(0, 0, 0, 0.6);
            --shadow-glow: 0 0 25px rgba(186, 40, 30, 0.25);
            --shadow-glow-gold: 0 0 25px rgba(255, 215, 0, 0.2);
            
            /* Transitions */
            --transition-fast: 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            --transition-normal: 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --transition-slow: 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* Reset & Base */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            outline: none;
        }

        body {
            background-color: var(--color-primary-dark);
            color: var(--color-text);
            font-family: 'Poppins', sans-serif;
            overflow-x: hidden;
            line-height: 1.6;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        /* -------------------------------------------------------------
           BANNÈRE HERO PREMIUM
           ------------------------------------------------------------- */
        .hero-banner {
            position: relative;
            height: 420px;
            border-radius: var(--border-radius-lg);
            overflow: hidden;
            margin-bottom: 40px;
            box-shadow: var(--shadow-heavy);
            border: 1px solid var(--color-glass-border);
        }

        .hero-image-bg {
            width: 100%;
            height: 100%;
            object-fit: cover;
            position: absolute;
            inset: 0;
            transition: transform 1.5s ease;
        }

        .hero-banner:hover .hero-image-bg {
            transform: scale(1.03);
        }

        .hero-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(to top, rgba(5, 0, 20, 1) 0%, rgba(5, 0, 20, 0.6) 60%, rgba(0, 0, 0, 0.2) 100%);
            z-index: 1;
        }

        .hero-content {
            position: relative;
            z-index: 2;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            padding: 40px;
        }

        .category-badge {
            color: var(--color-gold);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 2px;
            font-size: 0.85rem;
            margin-bottom: 12px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .hero-title {
            font-family: 'Montserrat', sans-serif;
            font-weight: 800;
            font-size: 2.5rem;
            margin-bottom: 15px;
            line-height: 1.2;
            letter-spacing: -0.5px;
            text-shadow: 0 4px 8px rgba(0,0,0,0.5);
        }

        .hero-meta-strip {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.95rem;
        }

        .hero-meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .hero-meta-item i {
            color: var(--color-accent);
        }

        /* -------------------------------------------------------------
           LAYOUT DE GRILLE BI-COLONNE
           ------------------------------------------------------------- */
        .page-layout {
            display: grid;
            grid-template-columns: 2.2fr 1fr;
            gap: 30px;
            align-items: start;
        }

        @media (max-width: 992px) {
            .page-layout {
                grid-template-columns: 1fr;
            }
            .hero-banner {
                height: 320px;
            }
            .hero-title {
                font-size: 1.8rem;
            }
        }

        /* -------------------------------------------------------------
           BLOCS DE CONTENU CARD (GLASSMORPHIC)
           ------------------------------------------------------------- */
        .info-card {
            background-color: var(--color-glass-dark);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid var(--color-glass-border);
            border-radius: var(--border-radius-md);
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: var(--shadow-medium);
            transition: var(--transition-normal);
        }

        .info-card:hover {
            border-color: rgba(255, 255, 255, 0.15);
        }

        .card-heading {
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
            color: var(--color-text);
            font-size: 1.35rem;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 1px solid var(--color-glass-border);
            padding-bottom: 15px;
        }

        .card-heading i {
            color: var(--color-accent);
        }

        .text-content {
            font-size: 0.95rem;
            color: var(--color-text-light);
            line-height: 1.7;
            white-space: pre-line;
        }

        /* -------------------------------------------------------------
           BOUTONS ET COMPOSANTS INTERACTIFS
           ------------------------------------------------------------- */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            padding: 14px 20px;
            border-radius: var(--border-radius-sm);
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            font-size: 0.95rem;
            text-decoration: none;
            cursor: pointer;
            transition: var(--transition-normal);
            border: none;
        }

        .btn-register {
            background: linear-gradient(135deg, var(--color-accent) 0%, #9e140d 100%);
            color: var(--color-text);
            box-shadow: var(--shadow-glow);
            animation: pulse-button 2s infinite ease-in-out;
        }

        .btn-register:hover {
            background: linear-gradient(135deg, var(--color-accent-hover) 0%, var(--color-accent) 100%);
            transform: translateY(-2px);
            box-shadow: 0 0 30px rgba(186, 40, 30, 0.45);
        }

        @keyframes pulse-button {
            0% { box-shadow: 0 0 0 0 rgba(186, 40, 30, 0.5); }
            70% { box-shadow: 0 0 0 10px rgba(186, 40, 30, 0); }
            100% { box-shadow: 0 0 0 0 rgba(186, 40, 30, 0); }
        }

        .btn-disabled {
            background: #2a2a2a;
            color: rgba(255,255,255,0.3);
            cursor: not-allowed;
            box-shadow: none !important;
            animation: none !important;
        }

        .status-box {
            background: rgba(46, 204, 113, 0.1);
            border: 1px solid rgba(46, 204, 113, 0.25);
            color: #2ecc71;
            text-align: center;
            padding: 14px;
            border-radius: var(--border-radius-sm);
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-size: 0.95rem;
        }

        .input-textarea {
            width: 100%;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--color-glass-border);
            border-radius: var(--border-radius-sm);
            padding: 15px;
            color: var(--color-text);
            font-family: 'Poppins', sans-serif;
            font-size: 0.9rem;
            margin-bottom: 15px;
            resize: vertical;
            transition: var(--transition-fast);
        }

        .input-textarea:focus {
            border-color: var(--color-accent);
            background: rgba(255, 255, 255, 0.06);
            box-shadow: 0 0 10px rgba(186, 40, 30, 0.15);
        }

        /* -------------------------------------------------------------
           FICHE TECHNIQUE SIDEBAR
           ------------------------------------------------------------- */
        .specs-list {
            list-style: none;
        }

        .specs-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.04);
            font-size: 0.92rem;
        }

        .specs-item:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .specs-label {
            color: var(--color-text-light);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .specs-label i {
            color: var(--color-accent);
            font-size: 1rem;
            width: 16px;
        }

        .specs-value {
            font-weight: 600;
            color: var(--color-text);
        }

        /* -------------------------------------------------------------
           GALERIE & ALBUM PHOTO
           ------------------------------------------------------------- */
        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
            gap: 12px;
            margin-top: 15px;
        }

        .gallery-item {
            aspect-ratio: 1;
            border-radius: var(--border-radius-sm);
            overflow: hidden;
            border: 1px solid var(--color-glass-border);
            cursor: pointer;
            position: relative;
            transition: var(--transition-normal);
        }

        .gallery-item::before {
            content: '';
            position: absolute;
            inset: 0;
            background: rgba(0,0,0,0);
            z-index: 1;
            transition: var(--transition-fast);
        }

        .gallery-item:hover {
            transform: scale(1.05);
            border-color: var(--color-gold);
            box-shadow: var(--shadow-glow-gold);
        }

        .gallery-item:hover::before {
            background: rgba(0,0,0,0.3);
        }

        .gallery-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* -------------------------------------------------------------
           ESPACE DISCUSSION / COMMENTAIRES
           ------------------------------------------------------------- */
        .comment-bubble {
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid var(--color-glass-border);
            border-radius: var(--border-radius-sm);
            padding: 18px;
            margin-bottom: 15px;
            transition: var(--transition-normal);
        }

        .comment-bubble:hover {
            background: rgba(255, 255, 255, 0.03);
            border-color: rgba(255, 255, 255, 0.12);
        }

        .comment-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.8rem;
            margin-bottom: 8px;
            color: var(--color-text-light);
        }

        .comment-author-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .comment-avatar {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            object-fit: cover;
            border: 1px solid var(--color-accent);
        }

        .comment-author {
            font-weight: 600;
            color: var(--color-text);
        }

        .comment-badge {
            font-size: 0.7rem;
            padding: 2px 8px;
            border-radius: 10px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .comment-badge-admin {
            background-color: rgba(186, 40, 30, 0.15);
            color: var(--color-accent-light);
            border: 1px solid rgba(186, 40, 30, 0.3);
        }

        .comment-badge-etudiant {
            background-color: rgba(255, 215, 0, 0.08);
            color: var(--color-gold-light);
            border: 1px solid rgba(255, 215, 0, 0.2);
        }

        .comment-body {
            font-size: 0.9rem;
            color: var(--color-text-light);
            word-break: break-word;
        }

        /* -------------------------------------------------------------
           ALERTE ET NOTIFICATIONS
           ------------------------------------------------------------- */
        .notification {
            padding: 15px 20px;
            border-radius: var(--border-radius-sm);
            margin-bottom: 30px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slide-in 0.4s ease-out;
            font-size: 0.95rem;
        }

        @keyframes slide-in {
            from { transform: translateY(-15px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .notification-success {
            background-color: rgba(46, 204, 113, 0.15);
            color: #2ecc71;
            border: 1px solid rgba(46, 204, 113, 0.3);
        }

        .notification-error {
            background-color: rgba(230, 57, 70, 0.15);
            color: var(--color-accent-light);
            border: 1px solid rgba(230, 57, 70, 0.3);
        }

        /* -------------------------------------------------------------
           LIGHTBOX JAVASCRIPT IMAGE EN PLEIN ÉCRAN
           ------------------------------------------------------------- */
        .lightbox {
            position: fixed;
            inset: 0;
            background: rgba(5, 0, 15, 0.95);
            backdrop-filter: blur(15px);
            z-index: 9999;
            display: none;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .lightbox.active {
            display: flex;
            opacity: 1;
        }

        .lightbox-content {
            max-width: 90%;
            max-height: 80%;
            border-radius: var(--border-radius-sm);
            box-shadow: var(--shadow-heavy);
            transform: scale(0.95);
            transition: transform 0.3s ease;
        }

        .lightbox.active .lightbox-content {
            transform: scale(1);
        }

        .lightbox-close {
            position: absolute;
            top: 25px;
            right: 25px;
            background: rgba(255,255,255,0.05);
            color: var(--color-text);
            border: 1px solid rgba(255,255,255,0.1);
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 1.2rem;
            transition: var(--transition-fast);
        }

        .lightbox-close:hover {
            background: var(--color-accent);
            border-color: var(--color-accent-light);
        }

        .lightbox-caption {
            position: absolute;
            bottom: 30px;
            color: var(--color-text-light);
            font-size: 1rem;
            font-weight: 500;
            text-align: center;
            width: 80%;
        }

        /* Custom scrollbar pour les commentaires */
        .comments-scroll::-webkit-scrollbar {
            width: 6px;
        }
        .comments-scroll::-webkit-scrollbar-track {
            background: rgba(255,255,255,0.01);
            border-radius: 10px;
        }
        .comments-scroll::-webkit-scrollbar-thumb {
            background: var(--color-glass-border);
            border-radius: 10px;
        }
        .comments-scroll::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.2);
        }
    </style>

    <?php include "../includes/header.php"; ?>

<div class="container">

    <!-- -------------------------------------------------------------
       NOTIFICATIONS & RETOUR ACTIONS
       ------------------------------------------------------------- -->
    <?php if(!empty($message_success)): ?>
        <div class="notification notification-success">
            <i class="fas fa-check-circle"></i>
            <span><?= htmlspecialchars($message_success) ?></span>
        </div>
    <?php endif; ?>
    
    <?php if(!empty($message_error)): ?>
        <div class="notification notification-error">
            <i class="fas fa-exclamation-circle"></i>
            <span><?= htmlspecialchars($message_error) ?></span>
        </div>
    <?php endif; ?>
    
    <?php if(!empty($uploadMessage)): ?>
        <div class="notification notification-error">
            <i class="fas fa-exclamation-triangle"></i>
            <span><?= htmlspecialchars($uploadMessage) ?></span>
        </div>
    <?php endif; ?>

    <!-- -------------------------------------------------------------
       BANNIERE HERO (AFFICHE + COMPTE À REBOURS)
       ------------------------------------------------------------- -->
    <div class="hero-banner">
        <img class="hero-image-bg" src="<?= !empty($concours['image_affiche']) ? '../admins/uploads/' . $concours['image_affiche'] : '../assets/images/default-contest.jpg' ?>" alt="Affiche du concours">
        <div class="hero-overlay"></div>
        <div class="hero-content">
            <span class="category-badge">
                <i class="fas <?= $icon_type ?>"></i> Catégorie : <?= htmlspecialchars($concours['type_concours']) ?>
            </span>
            <h1 class="hero-title"><?= htmlspecialchars($concours['nom_concours']) ?></h1>
            <div class="hero-meta-strip">
                <div class="hero-meta-item">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Édition JET : <strong><?= htmlspecialchars($concours['annee_academique'] ?? 'JET Active') ?></strong></span>
                </div>
                <div class="hero-meta-item">
                    <i class="fas fa-clock"></i>
                    <span id="global-timer">Initialisation du compte à rebours...</span>
                </div>
            </div>
        </div>
    </div>

    <!-- -------------------------------------------------------------
       LAYOUT EN DEUX COLONNES
       ------------------------------------------------------------- -->
    <div class="page-layout">
        
        <!-- COLONNE PRINCIPALE (GAUCHE) -->
        <div class="main-column">
            
            <!-- Présentation -->
            <div class="info-card">
                <h2 class="card-heading"><i class="fas fa-align-left"></i> Présentation</h2>
                <div class="text-content">
                    <?= !empty($concours['description']) ? htmlspecialchars($concours['description']) : "Aucune description détaillée disponible pour le moment." ?>
                </div>
            </div>

            <!-- Conditions de Participation -->
            <?php if(!empty($concours['conditions_participation'])): ?>
                <div class="info-card">
                    <h2 class="card-heading"><i class="fas fa-clipboard-check"></i> Conditions de participation</h2>
                    <div class="text-content"><?= htmlspecialchars($concours['conditions_participation']) ?></div>
                </div>
            <?php endif; ?>

            <!-- Règlement Général -->
            <?php if(!empty($concours['reglement'])): ?>
                <div class="info-card">
                    <h2 class="card-heading"><i class="fas fa-gavel"></i> Règlement intérieur</h2>
                    <div class="text-content"><?= htmlspecialchars($concours['reglement']) ?></div>
                </div>
            <?php endif; ?>

            <!-- Espace Album Photo / Galerie -->
            <div class="info-card" id="album-section">
                <h2 class="card-heading"><i class="fas fa-images"></i> Album photos</h2>
                <?php if(count($images_galerie) === 0): ?>
                    <p style="color: rgba(255,255,255,0.4); font-size: 0.9rem; text-align: center; padding: 20px 0;">Aucune image n'a encore été ajoutée à cet album.</p>
                <?php else: ?>
                    <div class="gallery-grid">
                        <?php foreach($images_galerie as $img): ?>
                            <div class="gallery-item" onclick="openLightbox(this)" data-caption="<?= htmlspecialchars($img['caption'] ?? 'Album JET') ?>">
                                <img src="uploads/evenements/<?= basename($img['file_path']) ?>" alt="Média Galerie">
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Formulaire d'upload d'images de la galerie pour les utilisateurs connectés -->
                <?php if($isLogged): ?>
                    <div style="margin-top: 30px; border-top: 1px dashed var(--color-glass-border); padding-top: 25px;">
                        <h4 style="font-size: 0.95rem; margin-bottom: 15px; color: var(--color-text); font-weight: 600;"><i class="fas fa-upload" style="color: var(--color-gold);"></i> Ajouter vos photos à la galerie</h4>
                        <form action="" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="event_id" value="<?= $id_concours ?>">
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; align-items: center; margin-bottom: 15px;">
                                <input type="file" name="file" accept="image/*" required style="font-size: 0.85rem; color: var(--color-text-light);">
                                <input type="text" name="caption" placeholder="Légende courte de la photo..." style="background: rgba(255,255,255,0.03); border:1px solid var(--color-glass-border); padding: 8px 12px; border-radius: 4px; color:#fff; font-size:0.85rem;">
                            </div>
                            <button type="submit" name="submit_upload" class="btn" style="padding: 10px 15px; background: rgba(255,255,255,0.06); color: #fff; width: auto; font-size: 0.85rem; border: 1px solid var(--color-glass-border);">
                                Valider l'upload
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Espace Commentaires / Discussions -->
            <div class="info-card" id="comments-box">
                <h2 class="card-heading"><i class="fas fa-comments"></i> Discussions (<?= count($comments) ?>)</h2>
                
                <!-- Formulaire d'envoi du message -->
                <?php if($isLogged): ?>
                    <form action="" method="POST" style="margin-bottom: 30px;">
                        <textarea class="input-textarea" name="message" rows="3" placeholder="Posez une question ou partagez une impression..." required></textarea>
                        <button type="submit" name="submit_comment" class="btn" style="background: var(--color-glass); border: 1px solid var(--color-glass-border); color: var(--color-text); width: auto; padding: 10px 22px; font-size: 0.85rem;">
                            <i class="fas fa-paper-plane"></i> Publier mon message
                        </button>
                    </form>
                <?php else: ?>
                    <div style="background: rgba(255,255,255,0.02); border: 1px dashed var(--color-glass-border); text-align: center; padding: 20px; border-radius: var(--border-radius-sm); margin-bottom: 30px;">
                        <p style="font-size: 0.9rem; color: var(--color-text-light);">Vous devez vous <a href="login.php" style="color: var(--color-accent-light); font-weight: 600; text-decoration: none;">connecter</a> pour participer aux discussions.</p>
                    </div>
                <?php endif; ?>

                <!-- Flux de commentaires -->
                <div class="comments-scroll" style="max-height: 400px; overflow-y: auto; padding-right: 10px;">
                    <?php if(count($comments) === 0): ?>
                        <p style="color: rgba(255,255,255,0.3); text-align: center; padding: 30px 0; font-size: 0.95rem;">Aucun commentaire posté pour le moment. Soyez le premier !</p>
                    <?php else: ?>
                        <?php foreach($comments as $com): 
                            // Détermination du nom complet
                            if ($com['user_type'] === 'admin') {
                                $author_name = $com['admin_prenom'] . ' ' . $com['admin_nom'];
                                $badge_class = 'comment-badge-admin';
                                $badge_text = !empty($com['admin_role']) ? $com['admin_role'] : 'Organisateur';
                                $avatar_img = '../assets/images/default-avatar.png';
                            } else {
                                $author_name = $com['etudiant_prenom'] . ' ' . $com['etudiant_nom'];
                                $badge_class = 'comment-badge-etudiant';
                                $badge_text = 'Candidat';
                                $avatar_img = !empty($com['etudiant_photo']) ? '../admins/uploads/photos_etudiants/' . $com['etudiant_photo'] : '../assets/images/default-avatar.png';
                            }
                        ?>
                            <div class="comment-bubble">
                                <div class="comment-header">
                                    <div class="comment-author-info">
                                        <img src="<?= $avatar_img ?>" class="comment-avatar" alt="Avatar">
                                        <span class="comment-author"><?= htmlspecialchars($author_name) ?></span>
                                        <span class="comment-badge <?= $badge_class ?>"><?= $badge_text ?></span>
                                    </div>
                                    <span><?= date("d M Y à H:i", strtotime($com['date_commentaire'])) ?></span>
                                </div>
                                <div class="comment-body">
                                    <?= nl2br(htmlspecialchars($com['message'])) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

            </div>

        </div>

        <!-- SIDEBAR DE DROITE (LOGISTIQUE ET APPEL À L'ACTION) -->
        <div class="sidebar-column">
            
            <!-- Carte d'appel d'inscription rapide -->
            <div class="info-card" style="border: 1px solid var(--color-gold); background: linear-gradient(145deg, rgba(20,20,20,0.95) 0%, rgba(35,30,10,0.95) 100%);">
                <h3 class="card-heading" style="border: none; margin-bottom: 5px; font-size: 1.25rem;"><i class="fas fa-bolt" style="color: var(--color-gold);"></i> Inscription instantanée</h3>
                <p style="font-size: 0.85rem; color: var(--color-text-light); margin-bottom: 25px; line-height: 1.5;">
                    Rejoignez les candidats en un clic. Votre dossier sera immédiatement soumis pour examen au comité de jury.
                </p>

                <?php if($is_closed): ?>
                    <button class="btn btn-disabled" disabled><i class="fas fa-lock"></i> Les inscriptions sont closes</button>
                <?php elseif($is_already_registered): ?>
                    <div class="status-box">
                        <i class="fas fa-check-double"></i> Candidature enregistrée
                    </div>
                <?php else: ?>
                    <form action="" method="POST">
                        <button type="submit" name="action_register" class="btn btn-register">
                            <i class="fas fa-user-plus"></i> Postuler maintenant
                        </button>
                    </form>
                <?php endif; ?>
            </div>

            <!-- Fiche technique & Détails logistiques -->
            <div class="info-card">
                <h3 class="card-heading" style="font-size: 1.25rem;"><i class="fas fa-file-invoice"></i> Fiche technique</h3>
                <ul class="specs-list">
                    <li class="specs-item">
                        <span class="specs-label"><i class="fas fa-user-shield"></i> Organisateur</span>
                        <span class="specs-value"><?= htmlspecialchars($concours['organisateur'] ?? 'Comité JET') ?></span>
                    </li>
                    <li class="specs-item">
                        <span class="specs-label"><i class="fas fa-map-marker-alt"></i> Lieu</span>
                        <span class="specs-value"><?= htmlspecialchars($concours['lieu'] ?? 'Campus Principal') ?></span>
                    </li>
                    <li class="specs-item">
                        <span class="specs-label"><i class="fas fa-users"></i> Participants</span>
                        <span class="specs-value"><?= $current_participants ?> <?= $concours['max_participants'] ? '/ ' . $concours['max_participants'] : '(Illimité)' ?></span>
                    </li>
                    <li class="specs-item">
                        <span class="specs-label"><i class="fas fa-ticket-alt"></i> Frais d'accès</span>
                        <span class="specs-value" style="color: var(--color-gold);">
                            <?= ($concours['participation_gratuite'] == 1 || floatval($concours['frais_participation']) == 0) ? '<span style="color:#2ecc71;">Gratuit</span>' : number_format($concours['frais_participation'], 0, '.', ' ') . ' FCFA' ?>
                        </span>
                    </li>
                    <li class="specs-item">
                        <span class="specs-label"><i class="fas fa-trophy"></i> Mode Sélection</span>
                        <span class="specs-value" style="text-transform: capitalize;"><?= htmlspecialchars($concours['mode_selection']) ?></span>
                    </li>
                </ul>
            </div>

        </div>

    </div>

</div>

<!-- -------------------------------------------------------------
   LIGHTBOX VISIONNEUSE PHOTO DE LA GALERIE (JS NATIF)
   ------------------------------------------------------------- -->
<div class="lightbox" id="image-lightbox">
    <div class="lightbox-close" onclick="closeLightbox()"><i class="fas fa-times"></i></div>
    <img class="lightbox-content" id="lightbox-image" src="" alt="Zoom Image">
    <div class="lightbox-caption" id="lightbox-caption">Description de la photo</div>
</div>

<script>
    // --- 1. COMPTE À REBOURS INTERACTIF ---
    <?php if($deadline_ts): ?>
    const eventTime = new Date(<?= $deadline_ts * 1000 ?>).getTime();
    const globalTimerEl = document.getElementById("global-timer");

    function updateCountdown() {
        const now = new Date().getTime();
        const diff = eventTime - now;

        if (diff <= 0) {
            globalTimerEl.innerHTML = "⏳ Fin des inscriptions";
            globalTimerEl.style.color = "var(--color-accent-light)";
            return;
        }

        const days = Math.floor(diff / (1000 * 60 * 60 * 24));
        const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((diff % (1000 * 60)) / 1000);

        let countdownText = "Inscriptions closes dans : ";
        if (days > 0) {
            countdownText += `<strong>${days}j ${hours}h ${minutes}m</strong>`;
        } else {
            countdownText += `<strong>${hours}h ${minutes}m ${seconds}s</strong>`;
        }

        globalTimerEl.innerHTML = countdownText;
    }

    updateCountdown();
    setInterval(updateCountdown, 1000);
    <?php else: ?>
    document.getElementById("global-timer").innerHTML = "Date limite non spécifiée.";
    <?php endif; ?>

    // --- 2. VISIONNEUSE LIGHTBOX POUR LA GALERIE ---
    const lightbox = document.getElementById("image-lightbox");
    const lightboxImg = document.getElementById("lightbox-image");
    const lightboxCaption = document.getElementById("lightbox-caption");

    function openLightbox(element) {
        const imgSrc = element.querySelector("img").src;
        const imgCaption = element.getAttribute("data-caption");

        lightboxImg.src = imgSrc;
        lightboxCaption.innerHTML = imgCaption;
        
        lightbox.classList.add("active");
    }

    function closeLightbox() {
        lightbox.classList.remove("active");
    }

    // Fermeture avec la touche Échap
    document.addEventListener("keydown", function(e) {
        if (e.key === "Escape" && lightbox.classList.contains("active")) {
            closeLightbox();
        }
    });
</script>
<?php include "../includes/footer.php"; ?>

