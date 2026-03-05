<?php
session_start();
require_once "../includes/db.php";
define('BASE_URL', '/isspt_projet/'); // chemin relatif depuis localhost
$base_url = BASE_URL;
// Initialisation des variables
$isLogged = false;
$isAdmin = false;
$userName = '';
$userAvatar = '../assets/images/default-avatar.png'; // avatar par défaut

// =========================
//  RÉCUPÉRER LES ANNÉES ACADÉMIQUES
// =========================
$yearsStmt = $pdo->query("SELECT * FROM academic_years ORDER BY start_date DESC");
$academic_years = $yearsStmt->fetchAll(PDO::FETCH_ASSOC);

// =========================
//  ANNÉE SÉLECTIONNÉE
// =========================
if (isset($_GET['year'])) {
    $year_id = intval($_GET['year']);
} else {
    $stmt = $pdo->query("SELECT * FROM academic_years WHERE is_current = 1 LIMIT 1");
    $currentYear = $stmt->fetch(PDO::FETCH_ASSOC);
    $year_id = $currentYear ? $currentYear['id'] : null;
}

// =========================
//  RÉCUPÉRER ACTIVITÉS
// =========================
$activites = [];
if ($year_id) {
    $stmt = $pdo->prepare("SELECT * FROM activites WHERE academic_year_id = :id ORDER BY date_creation ASC");
    $stmt->execute(["id" => $year_id]);
    $activites = $stmt->fetchAll();
}

// =========================
//  RÉCUPÉRER ÉVÉNEMENTS
// =========================
$evenements = [];
if ($year_id) {
    $stmt = $pdo->prepare("SELECT * FROM evenements WHERE academic_year_id = :id AND is_public = 1 ORDER BY event_start ASC");
    $stmt->execute(["id" => $year_id]);
    $evenements = $stmt->fetchAll();
}

// =========================
//  PRÉSIDENT DU COMITÉ
// =========================
$president = null;
if ($year_id) {
    $stmt = $pdo->prepare("
        SELECT a.id_admin, e.nom, e.prenom, e.photo, e.filiere
        FROM administrateurs a
        JOIN etudiants e ON a.id_etudiant = e.id_etudiant
        WHERE a.poste_bureau = 'président'
        LIMIT 1
    ");
    $stmt->execute();
    $president = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Récupération des actualités du président
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

// Récupération des statistiques
$stats = [];
if ($year_id) {
    // Nombre d'événements
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM evenements WHERE academic_year_id = :id AND is_public = 1");
    $stmt->execute(["id" => $year_id]);
    $stats['events'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Nombre d'activités
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM activites WHERE academic_year_id = :id");
    $stmt->execute(["id" => $year_id]);
    $stats['activities'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Prochain événement
    $stmt = $pdo->prepare("SELECT nom_evenement, event_start FROM evenements WHERE academic_year_id = :id AND is_public = 1 AND event_start > NOW() ORDER BY event_start ASC LIMIT 1");
    $stmt->execute(["id" => $year_id]);
    $nextEvent = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['next_event'] = $nextEvent ? $nextEvent['nom_evenement'] : 'Aucun événement à venir';
    $stats['next_event_date'] = $nextEvent ? $nextEvent['event_start'] : null;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>JET – Journée de l'Étudiant Tarsien | ISSPT</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* =============================================
           VARIABLES & RESET
        ============================================= */
        :root {
            --primary: #080020;
            --primary-dark: #050014;
            --accent: #BA281E;
            --accent-light: #e63946;
            --gold: #FFD700;
            --gold-light: #FFED4E;
            --light: #ffffff;
            --light-gray: rgba(255, 255, 255, 0.85);
            --dark-gray: rgba(255, 255, 255, 0.6);
            --glass-bg: rgba(255, 255, 255, 0.08);
            --glass-bg-light: rgba(255, 255, 255, 0.12);
            --glass-border: rgba(255, 255, 255, 0.15);
            --shadow-sm: 0 4px 20px rgba(0, 0, 0, 0.15);
            --shadow-md: 0 8px 40px rgba(0, 0, 0, 0.25);
            --shadow-lg: 0 20px 60px rgba(0, 0, 0, 0.35);
            --transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.1);
            --transition-slow: all 0.6s cubic-bezier(0.16, 1, 0.3, 1);
            --radius-sm: 8px;
            --radius-md: 16px;
            --radius-lg: 24px;
            --radius-xl: 32px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
            scroll-padding-top: 100px;
        }

        body {
            font-family: 'Poppins', 'Segoe UI', system-ui, sans-serif;
            background: linear-gradient(135deg, var(--primary) 0%, #1a0b2e 70%, #2d1b4d 100%);
            color: var(--light);
            line-height: 1.7;
            min-height: 100vh;
            overflow-x: hidden;
            margin-top: 80px;
            position: relative;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                radial-gradient(circle at 10% 20%, rgba(186, 40, 30, 0.15) 0%, transparent 40%),
                radial-gradient(circle at 90% 30%, rgba(255, 215, 0, 0.1) 0%, transparent 40%),
                radial-gradient(circle at 50% 80%, rgba(105, 0, 255, 0.1) 0%, transparent 40%);
            z-index: -1;
            pointer-events: none;
        }

        .container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 24px;
        }

        section {
            padding: 100px 0;
            position: relative;
        }

        .section-header {
            text-align: center;
            margin-bottom: 70px;
            position: relative;
        }

        .section-title {
            font-size: 3rem;
            font-weight: 800;
            margin-bottom: 20px;
            background: linear-gradient(135deg, var(--light) 0%, var(--gold-light) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            position: relative;
            display: inline-block;
            font-family: 'Montserrat', sans-serif;
        }

        .section-title::after {
            content: '';
            position: absolute;
            bottom: -15px;
            left: 50%;
            transform: translateX(-50%);
            width: 120px;
            height: 5px;
            background: linear-gradient(90deg, var(--accent), var(--gold));
            border-radius: 5px;
            animation: widthPulse 3s infinite;
        }

        .section-subtitle {
            font-size: 1.2rem;
            color: var(--light-gray);
            max-width: 700px;
            margin: 0 auto;
            line-height: 1.8;
        }

        /* =============================================
           NAVIGATION
        ============================================= */
        .main-nav {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            background: rgba(8, 0, 32, 0.95);
            backdrop-filter: blur(15px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            z-index: 1000;
            padding: 15px 0;
            transition: var(--transition);
        }

        .nav-scrolled {
            background: rgba(8, 0, 32, 0.98);
            padding: 12px 0;
            box-shadow: var(--shadow-md);
        }

        .nav-container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .nav-logo {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--light);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .nav-logo i {
            color: var(--gold);
        }

        .nav-menu {
            display: flex;
            gap: 40px;
            list-style: none;
        }

        .nav-link {
            color: var(--light-gray);
            text-decoration: none;
            font-weight: 500;
            font-size: 1rem;
            position: relative;
            padding: 8px 0;
            transition: var(--transition);
        }

        .nav-link::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0;
            height: 2px;
            background: linear-gradient(90deg, var(--accent), var(--gold));
            transition: width 0.3s ease;
        }

        .nav-link:hover {
            color: var(--light);
        }

        .nav-link:hover::after {
            width: 100%;
        }

        .nav-cta {
            background: linear-gradient(135deg, var(--accent), var(--accent-light));
            color: var(--light);
            padding: 12px 28px;
            border-radius: var(--radius-md);
            font-weight: 600;
            text-decoration: none;
            transition: var(--transition);
            box-shadow: 0 4px 15px rgba(186, 40, 30, 0.3);
        }

        .nav-cta:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(186, 40, 30, 0.4);
        }

        .mobile-menu-btn {
            display: none;
            background: none;
            border: none;
            color: var(--light);
            font-size: 1.5rem;
            cursor: pointer;
        }

        /* =============================================
           HERO SECTION
        ============================================= */
        .hero-section {
            min-height: 90vh;
            display: flex;
            align-items: center;
            position: relative;
            overflow: hidden;
            padding-top: 60px;
        }

        .hero-bg {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                linear-gradient(rgba(8, 0, 32, 0.9), rgba(8, 0, 32, 0.7)),
                url('https://images.unsplash.com/photo-1523580494863-6f3031224c94?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80');
            background-size: cover;
            background-position: center;
            z-index: -2;
        }

        .hero-content {
            position: relative;
            z-index: 2;
            text-align: center;
            max-width: 900px;
            margin: 0 auto;
        }

        .hero-badge {
            display: inline-block;
            background: linear-gradient(135deg, var(--accent), var(--accent-light));
            color: var(--light);
            padding: 12px 28px;
            border-radius: 50px;
            font-weight: 600;
            margin-bottom: 30px;
            animation: float 3s ease-in-out infinite;
            box-shadow: var(--shadow-sm);
        }

        .hero-title {
            font-size: 4.5rem;
            font-weight: 800;
            margin-bottom: 25px;
            line-height: 1.1;
            font-family: 'Montserrat', sans-serif;
            background: linear-gradient(135deg, var(--light) 0%, var(--gold) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }

        .hero-subtitle {
            font-size: 1.4rem;
            color: var(--light-gray);
            margin-bottom: 40px;
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
            line-height: 1.8;
        }

        .hero-stats {
            display: flex;
            justify-content: center;
            gap: 40px;
            margin: 50px 0;
            flex-wrap: wrap;
        }

        .stat-item {
            text-align: center;
            animation: fadeInUp 0.8s ease-out;
        }

        .stat-number {
            font-size: 3rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--gold), var(--gold-light));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            line-height: 1;
            margin-bottom: 10px;
        }

        .stat-label {
            font-size: 1rem;
            color: var(--light-gray);
            font-weight: 500;
        }

        .year-selector {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-lg);
            padding: 25px;
            margin: 40px auto;
            max-width: 500px;
            display: flex;
            flex-direction: column;
            gap: 15px;
            box-shadow: var(--shadow-md);
            animation: slideUp 0.8s ease-out 0.3s both;
        }

        .year-selector label {
            font-weight: 600;
            margin-bottom: 10px;
            display: block;
            font-size: 1.1rem;
            color: var(--light);
        }

        .year-selector select {
            width: 100%;
            padding: 16px 20px;
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-md);
            color: var(--light);
            font-size: 1rem;
            cursor: pointer;
            transition: var(--transition);
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%23ffffff' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 20px center;
            background-size: 16px;
        }

        .year-selector select:hover {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(186, 40, 30, 0.2);
        }

        .hero-buttons {
            display: flex;
            gap: 20px;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 40px;
            animation: fadeInUp 0.8s ease-out 0.5s both;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 16px 32px;
            border-radius: var(--radius-md);
            font-weight: 600;
            text-decoration: none;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: var(--transition);
            border: none;
            cursor: pointer;
            position: relative;
            overflow: hidden;
            font-size: 0.95rem;
            gap: 10px;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.6s;
        }

        .btn:hover::before {
            left: 100%;
        }

        .btn:hover {
            transform: translateY(-5px);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--accent), var(--accent-light));
            color: var(--light);
            box-shadow: 0 8px 25px rgba(186, 40, 30, 0.3);
        }

        .btn-primary:hover {
            box-shadow: 0 15px 35px rgba(186, 40, 30, 0.4);
            transform: translateY(-5px);
        }

        .btn-secondary {
            background: var(--glass-bg-light);
            backdrop-filter: blur(10px);
            color: var(--light);
            border: 1px solid var(--glass-border);
        }

        .btn-secondary:hover {
            background: var(--glass-bg);
            border-color: var(--gold);
            box-shadow: 0 8px 25px rgba(255, 215, 0, 0.2);
        }

        .btn-gold {
            background: linear-gradient(135deg, var(--gold), var(--gold-light));
            color: var(--primary);
            font-weight: 700;
            box-shadow: 0 8px 25px rgba(255, 215, 0, 0.3);
        }

        .btn-gold:hover {
            box-shadow: 0 15px 35px rgba(255, 215, 0, 0.4);
        }

        /* =============================================
           FEATURES SECTION
        ============================================= */
        .features-section {
            background: linear-gradient(to bottom, transparent, rgba(8, 0, 32, 0.5));
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-top: 50px;
        }

        .feature-card {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-lg);
            padding: 40px 30px;
            text-align: center;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--accent), var(--gold));
            border-radius: var(--radius-lg) var(--radius-lg) 0 0;
        }

        .feature-card:hover {
            transform: translateY(-15px);
            box-shadow: var(--shadow-lg);
            border-color: var(--accent);
        }

        .feature-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, rgba(186, 40, 30, 0.1), rgba(255, 215, 0, 0.1));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 25px;
            font-size: 2.2rem;
            color: var(--gold);
            transition: var(--transition);
        }

        .feature-card:hover .feature-icon {
            transform: scale(1.1) rotate(5deg);
            background: linear-gradient(135deg, var(--accent), var(--gold));
            color: var(--light);
        }

        .feature-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 15px;
            color: var(--light);
        }

        .feature-description {
            color: var(--light-gray);
            font-size: 1rem;
            line-height: 1.7;
        }

        /* =============================================
           EVENTS SECTION
        ============================================= */
        .events-section {
            background: linear-gradient(to bottom, transparent, rgba(8, 0, 32, 0.7));
        }

        .events-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(380px, 1fr));
            gap: 35px;
            margin-top: 50px;
        }

        .event-card {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-lg);
            padding: 35px;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .event-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(186, 40, 30, 0.05), rgba(255, 215, 0, 0.05));
            opacity: 0;
            transition: var(--transition);
        }

        .event-card:hover::before {
            opacity: 1;
        }

        .event-card:hover {
            transform: translateY(-10px) scale(1.02);
            box-shadow: var(--shadow-lg);
            border-color: var(--gold);
        }

        .event-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 25px;
        }

        .event-type {
            display: inline-block;
            padding: 8px 20px;
            background: linear-gradient(135deg, var(--accent), var(--accent-light));
            color: var(--light);
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .event-icon {
            font-size: 2.5rem;
            color: var(--gold);
            opacity: 0.8;
        }

        .event-title {
            font-size: 1.6rem;
            font-weight: 700;
            margin-bottom: 20px;
            color: var(--light);
            line-height: 1.3;
        }

        .event-details {
            margin-bottom: 25px;
            flex-grow: 1;
        }

        .event-detail {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
            color: var(--light-gray);
        }

        .event-detail i {
            color: var(--accent);
            width: 20px;
            font-size: 1.1rem;
        }

        .event-description {
            color: var(--dark-gray);
            line-height: 1.7;
            margin-bottom: 25px;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .event-timer {
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(186, 40, 30, 0.3);
            border-radius: var(--radius-md);
            padding: 20px;
            text-align: center;
            font-family: 'Courier New', monospace;
            font-weight: 700;
            font-size: 1.3rem;
            color: var(--gold);
            margin-top: 20px;
            animation: pulse 2s infinite;
            letter-spacing: 2px;
        }

        /* =============================================
           ACTIVITIES SECTION
        ============================================= */
        .activities-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 30px;
            margin-top: 50px;
        }

        .activity-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.05) 0%, rgba(255, 255, 255, 0.02) 100%);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-lg);
            padding: 35px;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .activity-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.05), transparent);
            transition: left 0.7s;
        }

        .activity-card:hover::before {
            left: 100%;
        }

        .activity-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-lg);
            border-color: var(--gold);
        }

        .activity-header {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 25px;
        }

        .activity-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--accent), #d43f3f);
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            color: var(--light);
            flex-shrink: 0;
            transition: var(--transition);
        }

        .activity-card:hover .activity-icon {
            transform: rotate(15deg) scale(1.1);
        }

        .activity-name {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--light);
            line-height: 1.3;
        }

        .activity-description {
            color: var(--light-gray);
            margin-bottom: 20px;
            line-height: 1.7;
        }

        .activity-conditions {
            background: rgba(186, 40, 30, 0.1);
            border-left: 4px solid var(--accent);
            padding: 15px;
            margin: 20px 0;
            border-radius: 0 var(--radius-md) var(--radius-md) 0;
        }

        .activity-conditions strong {
            color: var(--light);
            font-weight: 600;
            display: block;
            margin-bottom: 8px;
        }

        .activity-link {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 12px 28px;
            background: transparent;
            border: 2px solid var(--gold);
            color: var(--gold);
            border-radius: var(--radius-md);
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition);
            margin-top: 10px;
        }

        .activity-link:hover {
            background: var(--gold);
            color: var(--primary);
            transform: translateX(8px);
            box-shadow: 0 5px 20px rgba(255, 215, 0, 0.3);
        }

        /* =============================================
           TIMELINE SECTION
        ============================================= */
        .timeline-section {
            background: linear-gradient(to bottom, rgba(8, 0, 32, 0.5), var(--primary));
        }

        .timeline-container {
            position: relative;
            max-width: 900px;
            margin: 60px auto 0;
        }

        .timeline-line {
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            top: 0;
            bottom: 0;
            width: 4px;
            background: linear-gradient(to bottom, 
                transparent, 
                var(--accent) 10%, 
                var(--gold) 50%,
                var(--accent) 90%,
                transparent);
            opacity: 0.6;
        }

        .timeline-items {
            position: relative;
        }

        .timeline-item {
            position: relative;
            width: calc(50% - 60px);
            margin-bottom: 60px;
            animation: fadeInRight 0.8s ease-out;
        }

        .timeline-item:nth-child(even) {
            margin-left: auto;
            animation: fadeInLeft 0.8s ease-out;
        }

        .timeline-item-content {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-lg);
            padding: 30px;
            position: relative;
            transition: var(--transition);
        }

        .timeline-item:hover .timeline-item-content {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
            border-color: var(--gold);
        }

        .timeline-dot {
            position: absolute;
            top: 30px;
            width: 24px;
            height: 24px;
            background: var(--gold);
            border: 4px solid var(--primary);
            border-radius: 50%;
            box-shadow: 0 0 0 6px rgba(186, 40, 30, 0.3);
            z-index: 1;
        }

        .timeline-item:nth-child(odd) .timeline-dot {
            right: -72px;
        }

        .timeline-item:nth-child(even) .timeline-dot {
            left: -72px;
        }

        .event-time {
            display: inline-block;
            padding: 8px 20px;
            background: linear-gradient(135deg, rgba(186, 40, 30, 0.2), rgba(255, 215, 0, 0.2));
            border-radius: 50px;
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--gold);
            margin-bottom: 15px;
        }

        .timeline-title {
            font-size: 1.4rem;
            font-weight: 700;
            margin-bottom: 15px;
            color: var(--light);
        }

        .timeline-location {
            color: var(--light-gray);
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1rem;
        }

        /* =============================================
           PRESIDENT SECTION
        ============================================= */
        .president-section {
            background: linear-gradient(135deg, rgba(8, 0, 32, 0.9), rgba(26, 11, 46, 0.9));
            position: relative;
            overflow: hidden;
        }

        .president-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 30% 40%, rgba(255, 215, 0, 0.1) 0%, transparent 50%);
            pointer-events: none;
        }

        .president-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.08) 0%, rgba(255, 255, 255, 0.04) 100%);
            border: 1px solid rgba(255, 215, 0, 0.2);
            border-radius: var(--radius-xl);
            padding: 50px;
            max-width: 1000px;
            margin: 0 auto;
            display: flex;
            gap: 50px;
            align-items: center;
            position: relative;
            overflow: hidden;
            backdrop-filter: blur(20px);
            box-shadow: var(--shadow-lg);
        }

        .president-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, var(--gold), var(--accent), transparent);
        }

        .president-avatar {
            flex-shrink: 0;
            width: 220px;
            height: 220px;
            border-radius: 50%;
            overflow: hidden;
            border: 4px solid var(--gold);
            box-shadow: var(--shadow-lg);
            position: relative;
            animation: float 6s ease-in-out infinite;
        }

        .president-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.8s;
        }

        .president-avatar:hover img {
            transform: scale(1.1);
        }

        .president-info {
            flex: 1;
        }

        .president-title {
            font-size: 2.2rem;
            font-weight: 800;
            margin-bottom: 25px;
            background: linear-gradient(135deg, var(--gold), var(--gold-light));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-family: 'Montserrat', sans-serif;
        }

        .president-message {
            color: var(--light-gray);
            font-size: 1.2rem;
            line-height: 1.9;
            margin-bottom: 30px;
            font-style: italic;
        }

        .president-signature {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            margin-top: 40px;
            padding-top: 25px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .president-name {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--gold);
            margin-bottom: 5px;
        }

        .president-details {
            color: var(--light-gray);
            font-size: 1rem;
        }

        /* =============================================
           CTA SECTION
        ============================================= */
        .cta-section {
            background: linear-gradient(135deg, var(--accent) 0%, #d43f3f 100%);
            position: relative;
            overflow: hidden;
            padding: 120px 0;
        }

        .cta-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                radial-gradient(circle at 20% 80%, rgba(255, 255, 255, 0.15) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(255, 255, 255, 0.1) 0%, transparent 50%);
            pointer-events: none;
        }

        .cta-content {
            position: relative;
            z-index: 2;
            text-align: center;
            max-width: 800px;
            margin: 0 auto;
        }

        .cta-title {
            font-size: 3.2rem;
            font-weight: 800;
            margin-bottom: 25px;
            color: var(--light);
            font-family: 'Montserrat', sans-serif;
            text-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        }

        .cta-text {
            font-size: 1.4rem;
            color: rgba(255, 255, 255, 0.9);
            margin-bottom: 50px;
            line-height: 1.8;
        }

        .cta-button {
            display: inline-flex;
            align-items: center;
            gap: 15px;
            padding: 20px 50px;
            background: var(--primary);
            color: var(--light);
            border-radius: var(--radius-lg);
            font-size: 1.2rem;
            font-weight: 700;
            text-decoration: none;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: var(--transition);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.3);
            position: relative;
            overflow: hidden;
        }

        .cta-button::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.6s;
        }

        .cta-button:hover::before {
            left: 100%;
        }

        .cta-button:hover {
            transform: translateY(-8px) scale(1.05);
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.4);
            background: var(--primary-dark);
        }

        /* =============================================
           ANIMATIONS
        ============================================= */
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

        @keyframes fadeInLeft {
            from {
                opacity: 0;
                transform: translateX(-50px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes fadeInRight {
            from {
                opacity: 0;
                transform: translateX(50px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes float {
            0%, 100% {
                transform: translateY(0);
            }
            50% {
                transform: translateY(-10px);
            }
        }

        @keyframes widthPulse {
            0%, 100% {
                width: 120px;
            }
            50% {
                width: 150px;
            }
        }

        @keyframes pulse {
            0%, 100% { 
                opacity: 1;
                box-shadow: 0 0 0 0 rgba(255, 215, 0, 0.4);
            }
            50% { 
                opacity: 0.9;
                box-shadow: 0 0 0 10px rgba(255, 215, 0, 0);
            }
        }

        /* =============================================
           RESPONSIVE DESIGN
        ============================================= */
        @media (max-width: 1200px) {
            .hero-title {
                font-size: 3.8rem;
            }
            
            .section-title {
                font-size: 2.5rem;
            }
        }

        @media (max-width: 992px) {
            .hero-title {
                font-size: 3.2rem;
            }
            
            .president-card {
                flex-direction: column;
                text-align: center;
                padding: 40px;
            }
            
            .president-avatar {
                width: 180px;
                height: 180px;
            }
            
            .timeline-line {
                left: 30px;
            }
            
            .timeline-item {
                width: calc(100% - 80px);
                margin-left: 80px !important;
                margin-right: 0 !important;
            }
            
            .timeline-item:nth-child(odd) .timeline-dot,
            .timeline-item:nth-child(even) .timeline-dot {
                left: -56px;
            }
            
            .mobile-menu-btn {
                display: block;
            }
            
            .nav-menu {
                position: fixed;
                top: 80px;
                left: -100%;
                width: 100%;
                height: calc(100vh - 80px);
                background: rgba(8, 0, 32, 0.98);
                backdrop-filter: blur(20px);
                flex-direction: column;
                align-items: center;
                justify-content: flex-start;
                padding-top: 60px;
                gap: 30px;
                transition: left 0.4s ease;
            }
            
            .nav-menu.active {
                left: 0;
            }
        }

        @media (max-width: 768px) {
            .container {
                padding: 0 20px;
            }
            
            section {
                padding: 80px 0;
            }
            
            .hero-title {
                font-size: 2.5rem;
            }
            
            .section-title {
                font-size: 2rem;
            }
            
            .hero-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .btn {
                width: 100%;
                max-width: 300px;
            }
            
            .events-grid,
            .activities-grid {
                grid-template-columns: 1fr;
            }
            
            .hero-stats {
                gap: 30px;
            }
            
            .stat-number {
                font-size: 2.5rem;
            }
            
            .cta-title {
                font-size: 2.5rem;
            }
        }

        @media (max-width: 576px) {
            .container {
                padding: 0 16px;
            }
            
            section {
                padding: 60px 0;
            }
            
            .hero-title {
                font-size: 2rem;
            }
            
            .section-title {
                font-size: 1.8rem;
            }
            
            .hero-subtitle,
            .section-subtitle {
                font-size: 1rem;
            }
            
            .president-card {
                padding: 30px 20px;
            }
            
            .event-card,
            .activity-card,
            .feature-card {
                padding: 25px;
            }
            
            .cta-button {
                padding: 18px 30px;
                font-size: 1.1rem;
            }
        }

        /* =============================================
           UTILITY CLASSES
        ============================================= */
        .text-center {
            text-align: center;
        }

        .mb-30 {
            margin-bottom: 30px;
        }

        .mb-40 {
            margin-bottom: 40px;
        }

        .mb-50 {
            margin-bottom: 50px;
        }

        .no-events {
            text-align: center;
            padding: 60px 40px;
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border-radius: var(--radius-lg);
            border: 2px dashed var(--glass-border);
            color: var(--light-gray);
            font-size: 1.2rem;
        }

        .no-events i {
            font-size: 3rem;
            color: var(--gold);
            margin-bottom: 20px;
            display: block;
            opacity: 0.7;
        }

        .fade-in {
            opacity: 0;
            transform: translateY(20px);
            transition: opacity 0.6s ease, transform 0.6s ease;
        }

        .fade-in.visible {
            opacity: 1;
            transform: translateY(0);
        }

        .parallax-bg {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-attachment: fixed;
            background-size: cover;
            background-position: center;
            z-index: -1;
        }
    </style>
</head>

<body>
        <?php include "../includes/header.php"; ?>

    <!-- ============================
         HERO SECTION
    ============================= -->
    <section class="hero-section" id="home">
        <div class="hero-bg parallax-bg"></div>
        
        <div class="container">
            <div class="hero-content">
                <div class="hero-badge fade-in">
                    <i class="fas fa-calendar-star"></i> Édition <?= date('Y') ?>
                </div>
                
                <h1 class="hero-title fade-in" style="animation-delay: 0.2s">
                    Journée de l'Étudiant<br>Tarsien
                </h1>
                
                <p class="hero-subtitle fade-in" style="animation-delay: 0.4s">
                    L'événement académique le plus attendu de l'année. Une célébration vibrante de l'excellence, 
                    de la culture et de l'esprit communautaire qui unit toute la famille tarsienne.
                </p>
                
                <?php if(!empty($stats)): ?>
                <div class="hero-stats">
                    <div class="stat-item" style="animation-delay: 0.6s">
                        <div class="stat-number"><?= $stats['events'] ?></div>
                        <div class="stat-label">Événements</div>
                    </div>
                    <div class="stat-item" style="animation-delay: 0.7s">
                        <div class="stat-number"><?= $stats['activities'] ?></div>
                        <div class="stat-label">Activités</div>
                    </div>
                    <div class="stat-item" style="animation-delay: 0.8s">
                        <div class="stat-number">Distraction</div>
                        <div class="stat-label">Non-stop</div>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="year-selector">
                    <form method="GET">
                        <label for="year">
                            <i class="fas fa-calendar-alt"></i> Sélectionnez l'année académique
                        </label>
                        <select name="year" id="year" onchange="this.form.submit()">
                            <?php foreach($academic_years as $y): ?>
                                <option value="<?= $y['id'] ?>" <?= ($y['id'] == $year_id) ? 'selected' : '' ?>>
                                    📅 <?= htmlspecialchars($y['label']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
                
                <div class="hero-buttons">
                    <a href="#events" class="btn btn-primary">
                        <i class="fas fa-calendar-check"></i> Voir le programme
                    </a>

                    <a href="football.php" class="btn btn-gold">
                        <i class="fas fa-futbol"></i> Tournoi de Football
                    </a>
                   
                    <a href="#president" class="btn btn-secondary">
                        <i class="fas fa-microphone-alt"></i> Mot du président
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- ============================
         FEATURES
    ============================= -->
    <section class="features-section">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">Pourquoi la JET ?</h2>
                <p class="section-subtitle">
                    Journée de l'Étudiant Tarsien. Une expérience unique qui marque la vie étudiante par son excellence, 
                    sa diversité et son impact durable sur la communauté universitaire.
                </p>
            </div>
            
            <div class="features-grid">
                <div class="feature-card fade-in">
                    <div class="feature-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3 class="feature-title">Communauté Unie</h3>
                    <p class="feature-description">
                        Rassemblement de toute la communauté étudiante autour de valeurs 
                        partagées et d'objectifs communs pour renforcer les liens inter-promotions.
                    </p>
                </div>
                
                <div class="feature-card fade-in" style="animation-delay: 0.2s">
                    <div class="feature-icon">
                        <i class="fas fa-trophy"></i>
                    </div>
                    <h3 class="feature-title">Excellence Académique</h3>
                    <p class="feature-description">
                        Reconnaissance et célébration des performances exceptionnelles, 
                        des parcours remarquables et des talents émergents de notre institution.
                    </p>
                </div>
                
                <div class="feature-card fade-in" style="animation-delay: 0.4s">
                    <div class="feature-icon">
                        <i class="fas fa-globe-africa"></i>
                    </div>
                    <h3 class="feature-title">Échanges Culturels</h3>
                    <p class="feature-description">
                        Découverte, partage et célébration des richesses culturelles à 
                        travers des activités diversifiées, inclusives et mémorables.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- ============================
         ÉVÉNEMENTS
    ============================= -->
    <section class="events-section" id="events">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">Événements Majeurs</h2>
                <p class="section-subtitle">
                    Découvrez les moments forts qui feront de cette édition une expérience inoubliable
                </p>
            </div>
            
            <?php if(count($evenements) == 0): ?>
                <div class="no-events">
                    <i class="fas fa-calendar-times"></i>
                    <h3 style="color: var(--light); margin-bottom: 10px;">Aucun événement programmé</h3>
                    <p>Les événements pour cette année académique seront bientôt annoncés.</p>
                </div>
            <?php else: ?>
                <div class="events-grid">
                    <?php foreach ($evenements as $index => $ev): 
                        $type_icon = '';
                        $type_class = '';
                        switch($ev["type_evenement"]) {
                            case 'Concert': 
                                $type_icon = 'fas fa-music';
                                $type_class = 'concert';
                                break;
                            case 'Conférence': 
                                $type_icon = 'fas fa-chalkboard-teacher';
                                $type_class = 'conference';
                                break;
                            case 'Compétition': 
                                $type_icon = 'fas fa-trophy';
                                $type_class = 'competition';
                                break;
                            case 'Soirée': 
                                $type_icon = 'fas fa-glass-cheers';
                                $type_class = 'party';
                                break;
                            default: 
                                $type_icon = 'fas fa-calendar';
                                $type_class = 'other';
                        }
                    ?>
                        <div class="event-card fade-in" style="animation-delay: <?= $index * 0.1 ?>s">
                            <div class="event-header">
                                <span class="event-type"><?= htmlspecialchars($ev["type_evenement"]) ?></span>
                                <div class="event-icon">
                                    <i class="<?= $type_icon ?>"></i>
                                </div>
                            </div>
                            
                            <h3 class="event-title"><?= htmlspecialchars($ev["nom_evenement"]) ?></h3>
                            
                            <div class="event-details">
                                <div class="event-detail">
                                    <i class="fas fa-clock"></i>
                                    <span>Début : <strong><?= date("d M Y à H:i", strtotime($ev["event_start"])) ?></strong></span>
                                </div>
                                
                                <?php if(!empty($ev["lieu"])): ?>
                                    <div class="event-detail">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <span>Lieu : <strong><?= htmlspecialchars($ev["lieu"]) ?></strong></span>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if($ev["prix_ticket"] > 0): ?>
                                    <div class="event-detail">
                                        <i class="fas fa-ticket-alt"></i>
                                        <span>Prix : <strong><?= number_format($ev["prix_ticket"], 0, ',', ' ') ?> FCFA</strong></span>
                                    </div>
                                <?php else: ?>
                                    <div class="event-detail">
                                        <i class="fas fa-gift"></i>
                                        <span><strong>Entrée gratuite</strong></span>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if(!empty($ev["description"])): ?>
                                    <p class="event-description">
                                        <?= nl2br(htmlspecialchars(mb_substr($ev["description"], 0, 150))) ?>...
                                    </p>
                                <?php endif; ?>
                            </div>
                            
                            <div class="event-timer" id="timer-<?= $ev['id_evenement'] ?>">
                                <i class="fas fa-hourglass-half"></i> 
                                <span id="timer-text-<?= $ev['id_evenement'] ?>">
                                    Chargement du compte à rebours...
                                </span>
                            </div>
                            
                            <a href="evenement.php?id=<?= $ev['id_evenement'] ?>" class="btn btn-secondary" style="margin-top: 20px;">
                                <i class="fas fa-info-circle"></i> Plus d'informations
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <?php if(count($evenements) > 0): ?>
            <div class="text-center mt-50">
                <a href="evenements.php" class="btn btn-primary">
                    <i class="fas fa-list"></i> Voir tous les événements
                </a>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- ============================
         ACTIVITÉS
    ============================= -->
    <section id="activities">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">Activités & Ateliers</h2>
                <p class="section-subtitle">
                    Programme complet des activités culturelles, sportives, éducatives et récréatives
                </p>
            </div>
            
            <?php if(count($activites) == 0): ?>
                <div class="no-events">
                    <i class="fas fa-clipboard-list"></i>
                    <h3 style="color: var(--light); margin-bottom: 10px;">Aucune activité programmée</h3>
                    <p>Le programme des activités sera bientôt disponible.</p>
                </div>
            <?php else: ?>
                <div class="activities-grid">
                    <?php foreach ($activites as $index => $act): ?>
                        <div class="activity-card fade-in" style="animation-delay: <?= $index * 0.1 ?>s">
                            <div class="activity-header">
                                <div class="activity-icon">
                                    <i class="fas fa-star"></i>
                                </div>
                                <div>
                                    <h3 class="activity-name"><?= htmlspecialchars($act["nom_activite"]) ?></h3>
                                    <div style="color: var(--gold); font-size: 0.9rem;">
                                        <i class="fas fa-calendar"></i> 
                                        <?= date("d M Y", strtotime($act["date_creation"])) ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="activity-description">
                                <?= nl2br(htmlspecialchars(mb_substr($act["description"], 0, 200))) ?>...
                            </div>
                            
                            <?php if(!empty($act["conditions"])): ?>
                                <div class="activity-conditions">
                                    <strong><i class="fas fa-clipboard-check"></i> Conditions :</strong><br>
                                    <?= htmlspecialchars($act["conditions"]) ?>
                                </div>
                            <?php endif; ?>
                            
                            <a href="details_activite.php?id=<?= $act['id_activite'] ?>" class="activity-link">
                                <i class="fas fa-arrow-right"></i> Découvrir cette activité
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
        </div>
    </section>

    <!-- ============================
         TIMELINE
    ============================= -->
    <section class="timeline-section" id="timeline">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">Calendrier des Événements</h2>
                <p class="section-subtitle">
                    Suivez le déroulement de la JET heure par heure
                </p>
            </div>
            
            <div class="timeline-container">
                <div class="timeline-line"></div>
                
                <div class="timeline-items">
                    <?php if(count($evenements) == 0): ?>
                        <div class="no-events" style="background: transparent; border: none;">
                            <i class="fas fa-timeline"></i>
                            <p>Le calendrier sera disponible prochainement</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($evenements as $index => $ev): ?>
                            <div class="timeline-item fade-in" style="animation-delay: <?= $index * 0.1 ?>s">
                                <div class="timeline-dot"></div>
                                <div class="timeline-item-content">
                                    <div class="event-time">
                                        <?= date("H:i", strtotime($ev["event_start"])) ?>
                                    </div>
                                    <h3 class="timeline-title"><?= htmlspecialchars($ev["nom_evenement"]) ?></h3>
                                    <?php if(!empty($ev["lieu"])): ?>
                                        <div class="timeline-location">
                                            <i class="fas fa-map-marker-alt"></i>
                                            <?= htmlspecialchars($ev["lieu"]) ?>
                                        </div>
                                    <?php endif; ?>
                                    <p style="color: var(--light-gray); margin-top: 15px; font-size: 0.95rem;">
                                        <?= nl2br(htmlspecialchars(mb_substr($ev["description"], 0, 100))) ?>...
                                    </p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- ============================
         MOT DU PRÉSIDENT
    ============================= -->
    <?php if ($president && $mot_du_president): ?>
    <section class="president-section" id="president">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">Message de la Présidence</h2>
                <p class="section-subtitle">
                    Un mot de notre président pour la communauté étudiante
                </p>
            </div>
            
            <div class="president-card fade-in">
                <div class="president-avatar">
                    <img src="<?= !empty($president['photo']) ? htmlspecialchars($president['photo']) : 'https://images.unsplash.com/photo-1560250097-0b93528c311a?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80' ?>" 
                         alt="<?= htmlspecialchars($president['prenom'] . ' ' . $president['nom']) ?>"
                         onerror="this.src='https://images.unsplash.com/photo-1560250097-0b93528c311a?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80'">
                </div>
                
                <div class="president-info">
                    <h3 class="president-title">Mot du Président</h3>
                    
                    <div class="president-message">
                        "<?= nl2br(htmlspecialchars($mot_du_president)) ?>"
                    </div>
                    
                    <div class="president-signature">
                        <div class="president-name">
                            <?= htmlspecialchars($president['prenom'] . ' ' . $president['nom']) ?>
                        </div>
                        <div class="president-details">
                            Président du Bureau des Étudiants
                            <?php if(!empty($president['filiere'])): ?>
                                • <?= htmlspecialchars($president['filiere']) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php if(count($actualites) > 0): ?>
            <div class="text-center mt-50">
                <a href="actualites.php" class="btn btn-secondary">
                    <i class="fas fa-newspaper"></i> Lire les dernières actualités
                </a>
            </div>
            <?php endif; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- ============================
         CTA FINAL
    ============================= -->
    <section class="cta-section" id="cta">
        <div class="container">
            <div class="cta-content fade-in">
                <h2 class="cta-title">Prêt pour l'aventure ?</h2>
                <p class="cta-text">
                    Rejoignez-nous pour célébrer l'excellence, la culture et l'esprit communautaire 
                    qui définissent la famille tarsienne. Inscrivez-vous dès maintenant pour ne rien 
                    manquer de cette édition exceptionnelle !
                </p>
                <!-- <div style="display: flex; gap: 20px; justify-content: center; flex-wrap: wrap;">
                    <a href="inscription.php" class="cta-button">
                        <i class="fas fa-user-plus"></i> S'inscrire maintenant
                    </a>
                    <a href="programme.php" class="btn btn-gold" style="padding: 20px 40px;">
                        <i class="fas fa-file-pdf"></i> Télécharger le programme
                    </a>
                </div> -->
            </div>
        </div>
    </section>

    <!-- ============================
         FOOTER
    ============================= -->
    <?php include "../includes/footer.php"; ?>

    <!-- ============================
         SCRIPTS
    ============================= -->
    <script>
        // Navigation scroll effect
        window.addEventListener('scroll', function() {
            const nav = document.getElementById('mainNav');
            if (window.scrollY > 50) {
                nav.classList.add('nav-scrolled');
            } else {
                nav.classList.remove('nav-scrolled');
            }
        });

        // Mobile menu toggle
        const mobileMenuBtn = document.getElementById('mobileMenuBtn');
        const navMenu = document.getElementById('navMenu');
        
        if (mobileMenuBtn) {
            mobileMenuBtn.addEventListener('click', function() {
                navMenu.classList.toggle('active');
                const icon = this.querySelector('i');
                icon.classList.toggle('fa-bars');
                icon.classList.toggle('fa-times');
            });
        }

        // Close mobile menu when clicking on a link
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', () => {
                navMenu.classList.remove('active');
                mobileMenuBtn.querySelector('i').classList.add('fa-bars');
                mobileMenuBtn.querySelector('i').classList.remove('fa-times');
            });
        });

        // Scroll animations
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -100px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                }
            });
        }, observerOptions);

        // Observe all fade-in elements
        document.querySelectorAll('.fade-in').forEach(element => {
            observer.observe(element);
        });

        // Smooth scroll for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                const targetId = this.getAttribute('href');
                if(targetId === '#') return;
                
                const targetElement = document.querySelector(targetId);
                if(targetElement) {
                    window.scrollTo({
                        top: targetElement.offsetTop - 100,
                        behavior: 'smooth'
                    });
                    
                    // Update active nav link
                    document.querySelectorAll('.nav-link').forEach(link => {
                        link.classList.remove('active');
                    });
                    this.classList.add('active');
                }
            });
        });

        // Event countdown timers
        <?php foreach ($evenements as $ev): ?>
        (function(){
            const eventDate = new Date("<?= $ev['event_start'] ?>").getTime();
            const timerEl = document.getElementById("timer-text-<?= $ev['id_evenement'] ?>");
            
            if (!timerEl) return;
            
            function updateTimer() {
                const now = new Date().getTime();
                const diff = eventDate - now;
                
                if (diff <= 0) {
                    timerEl.innerHTML = "🎉 L'événement a commencé !";
                    timerEl.parentElement.style.background = "rgba(0, 150, 0, 0.3)";
                    timerEl.parentElement.style.borderColor = "rgba(0, 200, 0, 0.5)";
                    return;
                } 
                
                const days = Math.floor(diff / (1000 * 60 * 60 * 24));
                const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((diff % (1000 * 60)) / 1000);
                
                let timerText = '';
                if (days > 0) {
                    timerText = `${days}j ${hours}h ${minutes}m`;
                } else if (hours > 0) {
                    timerText = `${hours}h ${minutes}m ${seconds}s`;
                } else {
                    timerText = `${minutes}m ${seconds}s`;
                }
                
                timerEl.innerHTML = timerText;
                
                // Add urgency style if less than 24 hours
                if (days === 0 && hours < 24) {
                    timerEl.parentElement.style.animationDuration = "1s";
                }
            }
            
            updateTimer();
            const timerInterval = setInterval(updateTimer, 1000);
        })();
        <?php endforeach; ?>

        // Parallax effect for hero background
        window.addEventListener('scroll', function() {
            const scrolled = window.pageYOffset;
            const parallax = document.querySelector('.parallax-bg');
            if (parallax) {
                parallax.style.transform = 'translateY(' + (scrolled * 0.5) + 'px)';
            }
        });

        // Active nav link based on scroll position
        function updateActiveNavLink() {
            const sections = document.querySelectorAll('section[id]');
            const scrollPos = window.scrollY + 150;
            
            sections.forEach(section => {
                const sectionTop = section.offsetTop;
                const sectionBottom = sectionTop + section.offsetHeight;
                const sectionId = section.getAttribute('id');
                
                if (scrollPos >= sectionTop && scrollPos < sectionBottom) {
                    document.querySelectorAll('.nav-link').forEach(link => {
                        link.classList.remove('active');
                        if (link.getAttribute('href') === `#${sectionId}`) {
                            link.classList.add('active');
                        }
                    });
                }
            });
        }
        
        window.addEventListener('scroll', updateActiveNavLink);

        // Year selector enhancement
        const yearSelect = document.getElementById('year');
        if (yearSelect) {
            yearSelect.addEventListener('change', function() {
                this.parentElement.classList.add('submitting');
                setTimeout(() => {
                    this.parentElement.submit();
                }, 300);
            });
        }

        // Add floating particles effect
        function createParticles() {
            const particlesContainer = document.createElement('div');
            particlesContainer.style.position = 'fixed';
            particlesContainer.style.top = '0';
            particlesContainer.style.left = '0';
            particlesContainer.style.width = '100%';
            particlesContainer.style.height = '100%';
            particlesContainer.style.pointerEvents = 'none';
            particlesContainer.style.zIndex = '-1';
            document.body.appendChild(particlesContainer);
            
            for (let i = 0; i < 30; i++) {
                const particle = document.createElement('div');
                particle.style.position = 'absolute';
                particle.style.width = Math.random() * 3 + 1 + 'px';
                particle.style.height = particle.style.width;
                particle.style.background = i % 2 === 0 ? 'rgba(186, 40, 30, 0.5)' : 'rgba(255, 215, 0, 0.5)';
                particle.style.borderRadius = '50%';
                particle.style.left = Math.random() * 100 + '%';
                particle.style.top = Math.random() * 100 + '%';
                
                particlesContainer.appendChild(particle);
                
                // Animate particle
                animateParticle(particle);
            }
        }
        
        function animateParticle(particle) {
            let x = parseFloat(particle.style.left);
            let y = parseFloat(particle.style.top);
            let xSpeed = (Math.random() - 0.5) * 0.2;
            let ySpeed = (Math.random() - 0.5) * 0.2;
            
            function move() {
                x += xSpeed;
                y += ySpeed;
                
                // Bounce off edges
                if (x <= 0 || x >= 100) xSpeed = -xSpeed;
                if (y <= 0 || y >= 100) ySpeed = -ySpeed;
                
                particle.style.left = x + '%';
                particle.style.top = y + '%';
                
                requestAnimationFrame(move);
            }
            
            move();
        }
        
        // Create particles after page load
        window.addEventListener('load', createParticles);

        // Add loading animation
        window.addEventListener('load', function() {
            document.body.classList.add('loaded');
        });
    </script>
    
    <style>
        /* Additional animations */
        .submitting {
            animation: pulse 0.5s ease-in-out;
        }
        
        body.loaded .hero-badge,
        body.loaded .hero-title,
        body.loaded .hero-subtitle,
        body.loaded .stat-item,
        body.loaded .year-selector,
        body.loaded .hero-buttons {
            animation-play-state: running !important;
        }
        
        /* Preloader style (optional) */
        .preloader {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: var(--primary);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            transition: opacity 0.5s, visibility 0.5s;
        }
        
        .preloader.hidden {
            opacity: 0;
            visibility: hidden;
        }
        
        .preloader-spinner {
            width: 50px;
            height: 50px;
            border: 5px solid rgba(255, 255, 255, 0.1);
            border-top-color: var(--gold);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }
    </style>
    
    <!-- Preloader (optional - uncomment if needed)
    <div class="preloader" id="preloader">
        <div class="preloader-spinner"></div>
    </div>
    -->
</body>
</html>