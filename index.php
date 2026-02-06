<?php
session_start();
require_once 'includes/db.php';
define('BASE_URL', '/isspt_projet/');
$base_url = BASE_URL;

// Initialisation des variables
$isLogged = false;
$isAdmin = false;
$userName = '';
$userAvatar = 'assets/images/default-avatar.png';
$user_id = null;
$user_type = null;
$accepte_cgu = 0;

// Vérification si un étudiant est connecté
if (isset($_SESSION['etudiant_id'])) {
    $user_id = $_SESSION['etudiant_id'];
    $user_type = 'etudiant';
    $stmt = $pdo->prepare("SELECT nom, prenom, photo, accepte_cgu FROM etudiants WHERE id_etudiant = ? AND statut = 'actif'");
    $stmt->execute([$_SESSION['etudiant_id']]);
    $etudiant = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($etudiant) {
        $isLogged = true;
        $userName = $etudiant['prenom'] . ' ' . $etudiant['nom'];
        if (!empty($etudiant['photo'])) $userAvatar = '../uploads/etudiants/' . $etudiant['photo'];
        $accepte_cgu = $etudiant['accepte_cgu'] ?? 0;
    } else {
        session_unset();
        session_destroy();
        header("Location: login.php");
        exit;
    }
}

// Vérification si un administrateur est connecté
if (isset($_SESSION['admin_id'])) {
    $user_id = $_SESSION['admin_id'];
    $user_type = 'admin';
    $stmt = $pdo->prepare("SELECT nom, prenom, role, accepte_cgu FROM administrateurs WHERE id_admin = ?");
    $stmt->execute([$_SESSION['admin_id']]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($admin) {
        $isLogged = true;
        $isAdmin = true;
        $userName = $admin['prenom'] . ' ' . $admin['nom'];
        $accepte_cgu = $admin['accepte_cgu'] ?? 0;
    } else {
        session_unset();
        session_destroy();
        header("Location: login.php");
        exit;
    }
}

// --- Récupération de l'année académique en cours ---
$stmt = $pdo->query("SELECT * FROM academic_years WHERE is_current = 1 LIMIT 1");
$current_year = $stmt->fetch(PDO::FETCH_ASSOC);
$year_id = $current_year['id'] ?? null;

// --- Récupération du président actuel ---
$president = null;
if ($year_id) {
    $stmt = $pdo->prepare("
        SELECT a.id_admin, e.nom, e.prenom, e.photo
        FROM administrateurs a
        JOIN etudiants e ON a.id_etudiant = e.id_etudiant
        WHERE a.poste_bureau = 'président'
        LIMIT 1
    ");
    $stmt->execute();
    $president = $stmt->fetch(PDO::FETCH_ASSOC);
}

// --- Récupération des actualités du président pour l'année en cours ---
$actualites = [];
$mot_du_president = null;
if ($president && $year_id) {
    $stmt = $pdo->prepare("
        SELECT titre, contenu, mot_du_president, date_publication 
        FROM actualites
        WHERE id_admin = ? AND id_academic_year = ?
        ORDER BY date_publication DESC
        LIMIT 3
    ");
    $stmt->execute([$president['id_admin'], $year_id]);
    $actualites = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($actualites as $actu) {
        if (!empty($actu['mot_du_president'])) {
            $mot_du_president = $actu['mot_du_president'];
            break;
        }
    }
}

// Gestion de l'envoi du formulaire du popup
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accept_terms'])) {
    if($isLogged && $_POST['accept_terms'] == '1') {
        if($user_type === 'etudiant') {
            $stmt = $pdo->prepare("UPDATE etudiants SET accepte_cgu = 1, date_acceptation_cgu = NOW() WHERE id_etudiant = ?");
        } else {
            $stmt = $pdo->prepare("UPDATE administrateurs SET accepte_cgu = 1, date_acceptation_cgu = NOW() WHERE id_admin = ?");
        }
        $stmt->execute([$user_id]);
        $accepte_cgu = 1;
    }
    echo json_encode(['success' => true]);
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ISSPT - Portail Universitaire</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
    /* =============================================
       ISSPT - PORTAIL UNIVERSITAIRE PREMIUM
       Design élégant avec système CGU intégré
       ============================================= */

    :root {
        /* Palette de couleurs premium */
        --primary-dark: rgb(8, 0, 32);
        --primary-darker: rgb(5, 0, 20);
        --accent-red: rgb(186, 40, 30);
        --accent-red-light: rgba(186, 40, 30, 0.1);
        --accent-gold: #FFD700;
        --accent-teal: #20c997;
        
        /* Couleurs UI */
        --text-white: #ffffff;
        --text-light: rgba(255, 255, 255, 0.85);
        --text-muted: rgba(255, 255, 255, 0.6);
        
        /* Effets glassmorphism */
        --glass-bg: rgba(255, 255, 255, 0.08);
        --glass-border: rgba(255, 255, 255, 0.1);
        --glass-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        
        /* Animations */
        --transition-smooth: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        --transition-bounce: all 0.6s cubic-bezier(0.68, -0.55, 0.265, 1.55);
    }

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Montserrat', sans-serif;
        background: var(--primary-dark);
        color: var(--text-white);
        min-height: 100vh;
        overflow-x: hidden;
        background-image: 
            radial-gradient(circle at 10% 20%, rgba(186, 40, 30, 0.15) 0%, transparent 25%),
            radial-gradient(circle at 90% 80%, rgba(32, 201, 151, 0.1) 0%, transparent 25%),
            linear-gradient(135deg, var(--primary-dark) 0%, var(--primary-darker) 100%);
    }

    /* ==================== ANIMATIONS ==================== */
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
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

    @keyframes fadeDown {
        from {
            opacity: 0;
            transform: translateY(-40px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @keyframes scaleIn {
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
        0%, 100% { box-shadow: 0 0 20px rgba(186, 40, 30, 0.3); }
        50% { box-shadow: 0 0 40px rgba(186, 40, 30, 0.6); }
    }

    @keyframes modalSlide {
        from {
            opacity: 0;
            transform: translateY(-50px) scale(0.95);
        }
        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }

    /* ==================== POPUP CGU ==================== */
    .cgu-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.9);
        backdrop-filter: blur(10px);
        z-index: 9999;
        display: flex;
        align-items: center;
        justify-content: center;
        animation: fadeIn 0.3s ease-out;
    }

    .cgu-modal {
        background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary-darker) 100%);
        border: 1px solid var(--glass-border);
        border-radius: 24px;
        padding: 3rem;
        max-width: 600px;
        width: 90%;
        max-height: 80vh;
        overflow-y: auto;
        box-shadow: var(--glass-shadow), 0 25px 50px rgba(0, 0, 0, 0.5);
        animation: modalSlide 0.4s ease-out;
        position: relative;
    }

    .cgu-header {
        text-align: center;
        margin-bottom: 2rem;
    }

    .cgu-icon {
        width: 80px;
        height: 80px;
        background: linear-gradient(135deg, var(--accent-red), #c53030);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1.5rem;
        font-size: 2.5rem;
        animation: glowPulse 2s infinite;
    }

    .cgu-title {
        font-size: 2.2rem;
        font-weight: 800;
        margin-bottom: 1rem;
        background: linear-gradient(135deg, var(--text-white) 0%, var(--accent-gold) 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }

    .cgu-subtitle {
        color: var(--text-light);
        font-size: 1.1rem;
    }

    .cgu-content {
        background: var(--glass-bg);
        backdrop-filter: blur(20px);
        border: 1px solid var(--glass-border);
        border-radius: 16px;
        padding: 2rem;
        margin-bottom: 2rem;
        line-height: 1.7;
    }

    .cgu-content p {
        margin-bottom: 1rem;
        color: var(--text-light);
    }

    .cgu-content strong {
        color: var(--text-white);
    }

    .cgu-links {
        display: flex;
        gap: 1rem;
        margin-bottom: 1.5rem;
        flex-wrap: wrap;
    }

    .cgu-link {
        flex: 1;
        min-width: 200px;
        padding: 1rem;
        background: var(--glass-bg);
        border: 1px solid var(--glass-border);
        border-radius: 12px;
        text-decoration: none;
        color: var(--text-light);
        transition: var(--transition-smooth);
        text-align: center;
    }

    .cgu-link:hover {
        background: rgba(32, 201, 151, 0.1);
        border-color: var(--accent-teal);
        transform: translateY(-3px);
    }

    .cgu-link i {
        color: var(--accent-teal);
        font-size: 1.2rem;
        margin-right: 0.5rem;
    }

    .cgu-actions {
        display: flex;
        gap: 1rem;
        margin-top: 2rem;
    }

    .cgu-btn {
        flex: 1;
        padding: 1.2rem;
        border: none;
        border-radius: 12px;
        font-family: 'Montserrat', sans-serif;
        font-size: 1.1rem;
        font-weight: 700;
        cursor: pointer;
        transition: var(--transition-smooth);
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
    }

    .cgu-btn-accept {
        background: linear-gradient(135deg, var(--accent-teal), #20b2aa);
        color: white;
    }

    .cgu-btn-accept:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 30px rgba(32, 201, 151, 0.4);
    }

    .cgu-btn-decline {
        background: transparent;
        border: 2px solid var(--accent-red);
        color: var(--accent-red);
    }

    .cgu-btn-decline:hover {
        background: var(--accent-red);
        color: white;
        transform: translateY(-3px);
    }

    .cgu-note {
        text-align: center;
        color: var(--text-muted);
        font-size: 0.9rem;
        margin-top: 1.5rem;
        padding-top: 1.5rem;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
    }

    /* ==================== HEADER ==================== */
    header {
        background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary-darker) 50%, rgba(186, 40, 30, 0.1) 100%);
        backdrop-filter: blur(20px);
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        position: sticky;
        top: 0;
        z-index: 1000;
        transition: var(--transition-smooth);
    }

    .header-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 1rem 2rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .logo {
        display: flex;
        align-items: center;
        gap: 1rem;
        text-decoration: none;
    }

    .logo-icon {
        width: 50px;
        height: 50px;
        background: linear-gradient(135deg, var(--accent-red), #c53030);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        color: white;
    }

    .logo-text h1 {
        font-size: 1.5rem;
        font-weight: 800;
        background: linear-gradient(135deg, var(--text-white) 0%, var(--accent-gold) 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        line-height: 1.2;
    }

    .logo-text span {
        font-size: 0.8rem;
        color: var(--text-muted);
        font-weight: 400;
    }

    /* Navigation */
    nav ul {
        display: flex;
        list-style: none;
        gap: 2rem;
        align-items: center;
    }

    nav a {
        color: var(--text-white);
        text-decoration: none;
        font-weight: 500;
        position: relative;
        padding: 0.5rem 0;
        transition: var(--transition-smooth);
    }

    nav a::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        width: 0;
        height: 2px;
        background: var(--accent-teal);
        transition: var(--transition-smooth);
    }

    nav a:hover {
        color: var(--accent-teal);
    }

    nav a:hover::after {
        width: 100%;
    }

    /* Avatar utilisateur */
    .user-section {
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .avatar {
        width: 45px;
        height: 45px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid var(--accent-teal);
        box-shadow: 0 4px 15px rgba(32, 201, 151, 0.3);
        transition: var(--transition-smooth);
    }

    .avatar:hover {
        transform: scale(1.1);
        box-shadow: 0 0 25px rgba(32, 201, 151, 0.5);
    }

    .user-info {
        display: flex;
        flex-direction: column;
    }

    .user-name {
        font-weight: 600;
        font-size: 0.9rem;
        color: var(--text-white);
    }

    .user-role {
        font-size: 0.8rem;
        color: var(--text-muted);
    }

    /* ==================== MAIN CONTENT ==================== */
    .main-content {
        display: none; /* Caché par défaut, visible après acceptation CGU */
    }

    .hero-section {
        padding: 6rem 2rem;
        text-align: center;
        position: relative;
        overflow: hidden;
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
            radial-gradient(ellipse at 80% 20%, rgba(32, 201, 151, 0.15) 0%, transparent 50%);
        pointer-events: none;
    }

    .hero-title {
        font-size: clamp(2.5rem, 5vw, 4rem);
        font-weight: 800;
        margin-bottom: 1.5rem;
        background: linear-gradient(135deg, var(--text-white) 0%, var(--accent-gold) 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        animation: fadeDown 1s ease-out;
    }

    .hero-subtitle {
        font-size: 1.3rem;
        color: var(--text-light);
        max-width: 800px;
        margin: 0 auto 2rem;
        line-height: 1.7;
        opacity: 0;
        animation: fadeUp 1s ease-out 0.3s forwards;
    }

    /* Boutons d'authentification */
    .auth-buttons {
        display: flex;
        gap: 1.5rem;
        justify-content: center;
        flex-wrap: wrap;
        opacity: 0;
        animation: fadeUp 1s ease-out 0.6s forwards;
    }

    .btn {
        padding: 1rem 2.5rem;
        border: none;
        border-radius: 12px;
        font-family: 'Montserrat', sans-serif;
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        transition: var(--transition-smooth);
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        min-width: 180px;
        justify-content: center;
    }

    .btn-primary {
        background: linear-gradient(135deg, var(--accent-red) 0%, #c53030 100%);
        color: white;
    }

    .btn-primary:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 30px rgba(186, 40, 30, 0.4);
        animation: glowPulse 2s infinite;
    }

    .btn-secondary {
        background: transparent;
        border: 2px solid var(--accent-teal);
        color: var(--accent-teal);
    }

    .btn-secondary:hover {
        background: var(--accent-teal);
        color: var(--primary-dark);
        transform: translateY(-3px);
    }

    /* Modules Section */
    .modules-section {
        padding: 4rem 2rem;
        max-width: 1200px;
        margin: 0 auto;
    }

    .section-title {
        text-align: center;
        font-size: 2.5rem;
        font-weight: 800;
        margin-bottom: 3rem;
        color: var(--text-white);
        position: relative;
    }

    .section-title::after {
        content: '';
        position: absolute;
        bottom: -10px;
        left: 50%;
        transform: translateX(-50%);
        width: 100px;
        height: 4px;
        background: linear-gradient(90deg, var(--accent-red), var(--accent-teal));
        border-radius: 2px;
    }

    .modules-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
        gap: 2rem;
    }

    .module-card {
        background: var(--glass-bg);
        backdrop-filter: blur(20px);
        border: 1px solid var(--glass-border);
        border-radius: 20px;
        padding: 2.5rem;
        text-align: center;
        transition: var(--transition-smooth);
        position: relative;
        overflow: hidden;
    }

    .module-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: linear-gradient(90deg, var(--accent-teal), var(--accent-red));
        transform: scaleX(0);
        transition: var(--transition-smooth);
    }

    .module-card:hover {
        transform: translateY(-10px);
        border-color: var(--accent-teal);
        box-shadow: var(--glass-shadow), 0 20px 40px rgba(0, 0, 0, 0.4);
    }

    .module-card:hover::before {
        transform: scaleX(1);
    }

    .module-icon {
        width: 80px;
        height: 80px;
        background: rgba(32, 201, 151, 0.2);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1.5rem;
        font-size: 2rem;
        color: var(--accent-teal);
        transition: var(--transition-smooth);
    }

    .module-card:hover .module-icon {
        transform: scale(1.1) rotate(10deg);
    }

    .module-card h3 {
        font-size: 1.5rem;
        font-weight: 700;
        margin-bottom: 1rem;
        color: var(--text-white);
    }

    .module-card p {
        color: var(--text-light);
        line-height: 1.6;
        margin-bottom: 2rem;
    }

    /* Actualités Section */
    .actualites-section {
        padding: 4rem 2rem;
        max-width: 1200px;
        margin: 0 auto;
    }

    .actualites-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 2rem;
        margin-bottom: 3rem;
    }

    .actualite-card {
        background: var(--glass-bg);
        backdrop-filter: blur(20px);
        border: 1px solid var(--glass-border);
        border-radius: 16px;
        padding: 2rem;
        transition: var(--transition-smooth);
    }

    .actualite-card:hover {
        transform: translateY(-5px);
        border-color: var(--accent-teal);
        box-shadow: var(--glass-shadow);
    }

    .actualite-card h3 {
        font-size: 1.3rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
        color: var(--text-white);
    }

    .actualite-card small {
        color: var(--text-muted);
        font-size: 0.9rem;
        display: block;
        margin-bottom: 1rem;
    }

    .actualite-card p {
        color: var(--text-light);
        line-height: 1.6;
    }

    /* Mot du président */
    .mot-president-card {
        background: linear-gradient(135deg, var(--glass-bg), rgba(255, 255, 255, 0.05));
        backdrop-filter: blur(20px);
        border: 1px solid var(--glass-border);
        border-radius: 20px;
        padding: 3rem;
        display: flex;
        gap: 2rem;
        align-items: center;
        transition: var(--transition-smooth);
    }

    .mot-president-card:hover {
        transform: translateY(-5px);
        box-shadow: var(--glass-shadow);
    }

    .president-photo {
        flex-shrink: 0;
    }

    .president-photo img {
        width: 150px;
        height: 150px;
        border-radius: 50%;
        object-fit: cover;
        border: 3px solid var(--accent-teal);
        box-shadow: 0 8px 30px rgba(32, 201, 151, 0.3);
    }

    .mot-president-text {
        flex: 1;
    }

    .mot-president-text strong {
        display: block;
        font-size: 1.5rem;
        color: var(--accent-gold);
        margin-bottom: 1rem;
    }

    .mot-president-text p {
        color: var(--text-light);
        line-height: 1.7;
        margin-bottom: 1.5rem;
    }

    .president-nom {
        font-weight: 600;
        color: var(--accent-teal) !important;
        font-style: italic;
        margin-bottom: 0 !important;
    }

    /* ==================== RESPONSIVE ==================== */
    @media (max-width: 768px) {
        .header-container {
            flex-direction: column;
            gap: 1.5rem;
            text-align: center;
        }
        
        nav ul {
            flex-wrap: wrap;
            justify-content: center;
        }
        
        .auth-buttons {
            flex-direction: column;
            align-items: center;
        }
        
        .btn {
            width: 100%;
            max-width: 280px;
        }
        
        .mot-president-card {
            flex-direction: column;
            text-align: center;
        }
        
        .cgu-actions {
            flex-direction: column;
        }
        
        .modules-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 480px) {
        .hero-title {
            font-size: 2rem;
        }
        
        .hero-subtitle {
            font-size: 1.1rem;
        }
        
        .section-title {
            font-size: 2rem;
        }
        
        .cgu-modal {
            padding: 2rem 1.5rem;
        }
    }

    /* ==================== ANIMATIONS DE SCROLL ==================== */
    .scroll-animate {
        opacity: 0;
        transform: translateY(30px);
        transition: all 0.8s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .scroll-animate.visible {
        opacity: 1;
        transform: translateY(0);
    }
    </style>
</head>
<body>

    <!-- Popup CGU (visible seulement si non accepté) -->
    <?php if (!$accepte_cgu && (!isset($_COOKIE['cgu_accepted']) || $_COOKIE['cgu_accepted'] !== 'true')): ?>
    <div class="cgu-overlay" id="cguPopup">
        <div class="cgu-modal">
            <div class="cgu-header">
                <div class="cgu-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h1 class="cgu-title">Conditions d'Utilisation</h1>
                <p class="cgu-subtitle">Avant de continuer, veuillez lire et accepter nos conditions</p>
            </div>
            
            <div class="cgu-content">
                <p><strong>Bienvenue sur le portail de l'Institut Supérieur Saint Paul Tarse.</strong></p>
                <p>Pour utiliser nos services (modules Épreuves et JET), vous devez accepter nos Conditions Générales d'Utilisation et notre Politique de Confidentialité.</p>
                <p>Ces documents décrivent comment nous protégeons vos données, vos droits en tant qu'utilisateur, et les règles d'utilisation de notre plateforme.</p>
            </div>
            
            <div class="cgu-links">
                <a href="politique_condition.php" class="cgu-link" target="_blank">
                    <i class="fas fa-user-shield"></i> Politique de confidentialité et Conditions d'utilisation
                </a>
            </div>
            
            <div class="cgu-description">
                <p>En cliquant sur "J'accepte", vous acceptez les conditions d'utilisation et la politique de confidentialité de l'institut.</p>
            </div>
            
            <div class="cgu-actions">
                <button class="cgu-btn cgu-btn-accept" id="acceptCGU">
                    <i class="fas fa-check-circle"></i> J'accepte et continue
                </button>
                <button class="cgu-btn cgu-btn-decline" id="declineCGU">
                    <i class="fas fa-times-circle"></i> Je refuse
                </button>
            </div>
            
            <p class="cgu-note">
                <i class="fas fa-info-circle"></i> En acceptant, vous confirmez avoir lu et compris nos conditions.
            </p>
        </div>
    </div>
    <?php endif; ?>
    <?php include "includes/header.php"; ?>

    <!-- Contenu principal (visible seulement après acceptation) -->
    <div class="main-content" id="mainContent">

        <!-- Hero Section -->
        <section class="hero-section">
            <div class="container">
                <h1 class="hero-title">Portail Universitaire ISSPT</h1>
                <p class="hero-subtitle">
                    Accédez à tous les services de l'institut : banque d'épreuves, activités étudiantes, 
                    actualités et bien plus encore. Une plateforme unique pour votre réussite académique.
                </p>
                
                <div class="auth-buttons">
                    <?php if(!$isLogged): ?>
                        <a href="connexion.php" class="btn btn-primary">
                            <i class="fas fa-sign-in-alt"></i> Se connecter
                        </a>
                        <a href="inscription.php" class="btn btn-secondary">
                            <i class="fas fa-user-plus"></i> Créer un compte
                        </a>
                    <?php else: ?>
                        <a href="profil.php" class="btn btn-primary">
                            <i class="fas fa-user-circle"></i> Mon profil
                        </a>
                        <a href="#" class="btn btn-secondary" onclick="scrollToModules()">
                            <i class="fas fa-rocket"></i> Explorer les modules
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <!-- Modules Section -->
        <section class="modules-section" id="modules">
            <h2 class="section-title">Nos Modules</h2>
            <div class="modules-grid">
                <!-- Module Épreuves -->
                <div class="module-card scroll-animate">
                    <div class="module-icon">
                        <i class="fas fa-file-pdf"></i>
                    </div>
                    <h3>Banque d'Épreuves</h3>
                    <p>
                        Accédez à une collection complète d'anciennes épreuves classées par filière, 
                        niveau et année académique. Téléchargez les documents pour vos révisions.
                    </p>
                    <a href="<?= $base_url ?>epreuves/index.php" class="btn btn-primary">
                        <i class="fas fa-external-link-alt"></i> Accéder au module
                    </a>
                </div>

                <!-- Module JET -->
                <div class="module-card scroll-animate">
                    <div class="module-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <h3>Journée de l'Étudiant Tarsien</h3>
                    <p>
                        Participez aux activités et événements annuels. Inscrivez-vous aux activités, 
                        réservez vos tickets et consultez la galerie des éditions précédentes.
                    </p>
                    <a href="<?= $base_url ?>jet/index.php" class="btn btn-primary">
                        <i class="fas fa-external-link-alt"></i> Découvrir le JET
                    </a>
                </div>
            </div>
        </section>

        <!-- Actualités Section -->
        <?php if ($president): ?>
        <section class="actualites-section" id="actualites">
            <h2 class="section-title">Actualités du Bureau Étudiant</h2>
            
            <div class="actualites-grid">
                <?php if (!empty($actualites)): ?>
                    <?php foreach ($actualites as $actu): ?>
                    <div class="actualite-card scroll-animate">
                        <h3><?= htmlspecialchars($actu['titre']) ?></h3>
                        <small>Publié le <?= date("d/m/Y à H:i", strtotime($actu['date_publication'])) ?></small>
                        <p><?= nl2br(htmlspecialchars($actu['contenu'])) ?></p>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="actualite-card">
                        <h3>Aucune actualité pour le moment</h3>
                        <p>Les actualités du bureau étudiant seront publiées ici prochainement.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Mot du président -->
            <?php if ($mot_du_president): ?>
            <div class="mot-president-card scroll-animate">
                <div class="president-photo">
                    <img src="<?= htmlspecialchars($president['photo']) ?>" 
                         alt="Photo du président"
                         onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($president['prenom'] . '+' . $president['nom']) ?>&background=20c997&color=fff&size=150'">
                </div>
                <div class="mot-president-text">
                    <strong>Mot du Président</strong>
                    <p><?= nl2br(htmlspecialchars($mot_du_president)) ?></p>
                    <p class="president-nom">
                        <?= htmlspecialchars($president['prenom'] . ' ' . $president['nom']) ?><br>
                        Président du Bureau des Étudiants
                    </p>
                </div>
            </div>
            <?php endif; ?>
        </section>
        <?php endif; ?>

    </div>
    <?php include "includes/footer.php"; ?>

    <script>
    // Gestion du popup CGU
    const cguPopup = document.getElementById('cguPopup');
    const mainContent = document.getElementById('mainContent');
    const acceptCGU = document.getElementById('acceptCGU');
    const declineCGU = document.getElementById('declineCGU');
    
    // Vérifier l'état d'acceptation
    const cguAccepted = <?= $accepte_cgu ? 'true' : 'false' ?> || 
                       localStorage.getItem('cgu_accepted') === 'true' ||
                       document.cookie.includes('cgu_accepted=true');

    // Fonction pour afficher/masquer le contenu
    function toggleContent() {
        if (cguAccepted) {
            if (cguPopup) cguPopup.style.display = 'none';
            if (mainContent) mainContent.style.display = 'block';
            document.body.style.overflow = 'auto';
        } else {
            if (mainContent) mainContent.style.display = 'none';
            if (cguPopup) cguPopup.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
    }

    // Accepter les CGU
    if (acceptCGU) {
        acceptCGU.addEventListener('click', function() {
            const isLogged = <?= $isLogged ? 'true' : 'false' ?>;
            
            if (isLogged) {
                // Envoyer l'acceptation au serveur
                fetch('', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'accept_terms=1'
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        // Stocker dans le localStorage et cookie
                        localStorage.setItem('cgu_accepted', 'true');
                        document.cookie = 'cgu_accepted=true; path=/; max-age=31536000'; // 1 an
                        toggleContent();
                        window.location.reload();
                    }
                });
            } else {
                // Pour les utilisateurs non connectés
                localStorage.setItem('cgu_accepted', 'true');
                document.cookie = 'cgu_accepted=true; path=/; max-age=31536000';
                toggleContent();
            }
        });
    }

    // Refuser les CGU
    if (declineCGU) {
        declineCGU.addEventListener('click', function() {
            alert('Vous devez accepter les conditions pour utiliser notre plateforme.');
            // Rediriger vers la page de politique
            window.location.href = 'politique_confidentialite.php';
        });
    }

    // Animation au scroll
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
            }
        });
    }, {
        threshold: 0.1,
        rootMargin: '50px'
    });

    document.querySelectorAll('.scroll-animate').forEach((el) => {
        observer.observe(el);
    });

    // Fonction pour scroller vers les modules
    function scrollToModules() {
        const modulesSection = document.getElementById('modules');
        if (modulesSection) {
            modulesSection.scrollIntoView({ behavior: 'smooth' });
        }
    }

    // Initialiser la page
    window.addEventListener('load', function() {
        // Afficher le bon contenu selon l'acceptation CGU
        toggleContent();
        
        // Animation de chargement
        document.body.style.opacity = '0';
        document.body.style.transition = 'opacity 0.5s ease-in';
        
        setTimeout(() => {
            document.body.style.opacity = '1';
        }, 100);
        
        // Animation des cartes
        const cards = document.querySelectorAll('.module-card, .actualite-card');
        cards.forEach((card, index) => {
            setTimeout(() => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                card.style.transition = 'all 0.6s cubic-bezier(0.4, 0, 0.2, 1)';
                
                setTimeout(() => {
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, 50);
            }, 300 + (index * 100));
        });
        
        // Vérifier si l'utilisateur doit voir un rappel des CGU
        const lastCGUReminder = localStorage.getItem('last_cgu_reminder');
        const now = new Date().getTime();
        const oneMonth = 30 * 24 * 60 * 60 * 1000;
        
        if (cguAccepted && (!lastCGUReminder || now - lastCGUReminder > oneMonth)) {
            // Afficher un rappel doux après 5 secondes
            setTimeout(() => {
                const reminder = document.createElement('div');
                reminder.style.cssText = `
                    position: fixed;
                    bottom: 20px;
                    right: 20px;
                    background: var(--glass-bg);
                    backdrop-filter: blur(10px);
                    border: 1px solid var(--glass-border);
                    border-radius: 12px;
                    padding: 1rem 1.5rem;
                    max-width: 300px;
                    z-index: 1000;
                    animation: fadeIn 0.3s ease-out;
                    color: var(--text-light);
                    font-size: 0.9rem;
                `;
                reminder.innerHTML = `
                    <p><i class="fas fa-info-circle" style="color: var(--accent-gold);"></i> 
                    N'hésitez pas à revoir nos <a href="conditions_utilisation.php" style="color: var(--accent-teal);">conditions d'utilisation</a></p>
                `;
                document.body.appendChild(reminder);
                
                setTimeout(() => {
                    reminder.style.animation = 'fadeOut 0.3s ease-out forwards';
                    setTimeout(() => reminder.remove(), 300);
                }, 5000);
                
                localStorage.setItem('last_cgu_reminder', now);
            }, 5000);
        }
    });

    // Ajouter une animation de fadeOut
    const style = document.createElement('style');
    style.textContent = `
        @keyframes fadeOut {
            from { opacity: 1; transform: translateY(0); }
            to { opacity: 0; transform: translateY(20px); }
        }
    `;
    document.head.appendChild(style);

    // Gestion des erreurs d'images
    document.querySelectorAll('img').forEach((img) => {
        img.addEventListener('error', function() {
            if (this.classList.contains('avatar')) {
                this.src = 'https://ui-avatars.com/api/?name=Utilisateur&background=20c997&color=fff&size=150';
            }
        });
    });
    </script>
</body>
</html>