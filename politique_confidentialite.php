<?php
session_start();
require_once 'includes/db.php';
define('BASE_URL', '/isspt_projet/'); // chemin relatif depuis localhost
$base_url = BASE_URL;
// Initialisation des variables
$isLogged = false;
$isAdmin = false;
$userName = '';
$userAvatar = 'assets/images/default-avatar.png'; // avatar par défaut

// Vérifier la connexion
$isLogged = isset($_SESSION['etudiant_id']) || isset($_SESSION['admin_id']);
$isAdmin = isset($_SESSION['admin_id']);

// Récupérer la dernière date de mise à jour
$last_updated = date('d/m/Y', strtotime('-1 month'));
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📜 Politique & Conditions - Institut Supérieur Saint Paul Tarse</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
    /* ==========================================================================
       PAGE POLITIQUE & CONDITIONS PREMIUM - ISSPT
       Design élégant avec animations fluides
       ========================================================================== */
    
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
    @keyframes float {
        0%, 100% { transform: translateY(0) rotate(0deg); }
        33% { transform: translateY(-10px) rotate(5deg); }
        66% { transform: translateY(5px) rotate(-5deg); }
    }
    
    @keyframes slideInUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    
    @keyframes glow {
        0%, 100% { box-shadow: 0 0 20px rgba(32, 201, 151, 0.3); }
        50% { box-shadow: 0 0 40px rgba(32, 201, 151, 0.6); }
    }
    
    @keyframes pulse {
        0%, 100% { transform: scale(1); opacity: 1; }
        50% { transform: scale(1.05); opacity: 0.8; }
    }
    
    @keyframes highlight {
        0% { background: transparent; }
        50% { background: rgba(32, 201, 151, 0.2); }
        100% { background: transparent; }
    }
    
    /* ==================== HERO SECTION ==================== */
    .legal-hero {
        padding: 5rem 2rem;
        text-align: center;
        position: relative;
        overflow: hidden;
    }
    
    .legal-hero::before {
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
        animation: slideInUp 1s ease-out;
    }
    
    .hero-subtitle {
        font-size: 1.3rem;
        color: var(--text-light);
        max-width: 800px;
        margin: 0 auto 2rem;
        opacity: 0;
        animation: slideInUp 1s ease-out 0.3s forwards;
    }
    
    .hero-info {
        display: flex;
        justify-content: center;
        gap: 3rem;
        flex-wrap: wrap;
        opacity: 0;
        animation: slideInUp 1s ease-out 0.6s forwards;
    }
    
    .info-item {
        text-align: center;
    }
    
    .info-icon {
        width: 60px;
        height: 60px;
        background: rgba(32, 201, 151, 0.2);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1rem;
        font-size: 1.5rem;
        color: var(--accent-teal);
        animation: float 3s infinite ease-in-out;
    }
    
    .info-text {
        font-size: 0.9rem;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    /* ==================== MAIN CONTAINER ==================== */
    .legal-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 2rem 5rem;
    }
    
    /* Navigation rapide */
    .quick-nav-section {
        margin-bottom: 3rem;
        animation: slideInUp 1s ease-out 0.8s both;
    }
    
    .quick-nav {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1.5rem;
    }
    
    .nav-card {
        background: var(--glass-bg);
        backdrop-filter: blur(20px);
        border: 2px solid var(--glass-border);
        border-radius: 16px;
        padding: 2rem;
        text-align: center;
        transition: var(--transition-smooth);
        cursor: pointer;
        position: relative;
        overflow: hidden;
    }
    
    .nav-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, var(--accent-teal), var(--accent-red));
        opacity: 0;
        transition: var(--transition-smooth);
    }
    
    .nav-card:hover {
        transform: translateY(-10px);
        border-color: var(--accent-teal);
    }
    
    .nav-card:hover::before {
        opacity: 1;
    }
    
    .nav-icon {
        width: 70px;
        height: 70px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1.5rem;
        font-size: 2rem;
        transition: var(--transition-smooth);
    }
    
    .terms-icon {
        background: rgba(186, 40, 30, 0.2);
        color: var(--accent-red);
    }
    
    .privacy-icon {
        background: rgba(32, 201, 151, 0.2);
        color: var(--accent-teal);
    }
    
    .nav-card:hover .nav-icon {
        transform: scale(1.1) rotate(10deg);
        animation: float 2s infinite;
    }
    
    .nav-title {
        font-size: 1.5rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
        color: var(--text-white);
    }
    
    .nav-description {
        color: var(--text-muted);
        font-size: 0.95rem;
        line-height: 1.5;
    }
    
    /* Table des matières */
    .toc-section {
        margin-bottom: 4rem;
        animation: slideInUp 1s ease-out 1s both;
    }
    
    .toc-container {
        background: var(--glass-bg);
        backdrop-filter: blur(20px);
        border: 1px solid var(--glass-border);
        border-radius: 16px;
        padding: 2.5rem;
        box-shadow: var(--glass-shadow);
    }
    
    .toc-header {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 2rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }
    
    .toc-icon {
        width: 60px;
        height: 60px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.8rem;
        background: rgba(255, 215, 0, 0.2);
        color: var(--accent-gold);
    }
    
    .toc-title {
        font-size: 2rem;
        font-weight: 700;
        color: var(--text-white);
    }
    
    .toc-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 1.5rem;
    }
    
    .toc-column h3 {
        color: var(--accent-gold);
        font-size: 1.2rem;
        margin-bottom: 1.5rem;
        padding-bottom: 0.5rem;
        border-bottom: 2px solid rgba(255, 215, 0, 0.3);
    }
    
    .toc-list {
        list-style: none;
    }
    
    .toc-item {
        margin-bottom: 1rem;
        padding-left: 1.5rem;
        position: relative;
    }
    
    .toc-item::before {
        content: '•';
        position: absolute;
        left: 0;
        color: var(--accent-teal);
        font-size: 1.5rem;
    }
    
    .toc-link {
        color: var(--text-light);
        text-decoration: none;
        transition: var(--transition-smooth);
        display: block;
        padding: 0.5rem 0;
        border-radius: 6px;
    }
    
    .toc-link:hover {
        color: var(--accent-teal);
        background: rgba(255, 255, 255, 0.05);
        padding-left: 0.5rem;
    }
    
    .toc-link.active {
        color: var(--accent-gold);
        font-weight: 600;
        background: rgba(255, 215, 0, 0.1);
    }
    
    /* Content Sections */
    .content-section {
        animation: slideInUp 1s ease-out 1.2s both;
    }
    
    .content-container {
        background: var(--glass-bg);
        backdrop-filter: blur(20px);
        border: 1px solid var(--glass-border);
        border-radius: 16px;
        padding: 2.5rem;
        box-shadow: var(--glass-shadow);
        margin-bottom: 3rem;
    }
    
    .content-header {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 2rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }
    
    .content-icon {
        width: 60px;
        height: 60px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.8rem;
    }
    
    .terms-header .content-icon {
        background: rgba(186, 40, 30, 0.2);
        color: var(--accent-red);
    }
    
    .privacy-header .content-icon {
        background: rgba(32, 201, 151, 0.2);
        color: var(--accent-teal);
    }
    
    .content-title {
        font-size: 2rem;
        font-weight: 700;
        color: var(--text-white);
    }
    
    .content-date {
        color: var(--accent-gold);
        font-size: 0.9rem;
        font-weight: 600;
        margin-top: 0.5rem;
    }
    
    /* Articles */
    .legal-article {
        margin-bottom: 3rem;
        padding: 2rem;
        background: rgba(0, 0, 0, 0.2);
        border-radius: 12px;
        border-left: 4px solid var(--accent-teal);
        transition: var(--transition-smooth);
    }
    
    .legal-article:hover {
        background: rgba(0, 0, 0, 0.3);
        border-left-color: var(--accent-gold);
        transform: translateX(5px);
    }
    
    .article-header {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1.5rem;
    }
    
    .article-number {
        width: 40px;
        height: 40px;
        background: linear-gradient(135deg, var(--accent-red), #c53030);
        color: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 1.2rem;
        flex-shrink: 0;
    }
    
    .article-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--text-white);
    }
    
    .article-content {
        color: var(--text-light);
        line-height: 1.7;
        margin-bottom: 1.5rem;
    }
    
    .article-content p {
        margin-bottom: 1rem;
    }
    
    .article-content p:last-child {
        margin-bottom: 0;
    }
    
    .article-list {
        list-style: none;
        margin: 1rem 0 1rem 2rem;
    }
    
    .article-list li {
        margin-bottom: 0.8rem;
        padding-left: 1.5rem;
        position: relative;
        color: var(--text-light);
    }
    
    .article-list li::before {
        content: '▸';
        position: absolute;
        left: 0;
        color: var(--accent-teal);
    }
    
    .important-note {
        background: rgba(255, 215, 0, 0.1);
        border: 1px solid rgba(255, 215, 0, 0.3);
        border-radius: 8px;
        padding: 1.5rem;
        margin: 1.5rem 0;
        position: relative;
    }
    
    .important-note::before {
        content: '⚠️';
        position: absolute;
        left: 1rem;
        top: 1rem;
        font-size: 1.5rem;
    }
    
    .note-content {
        margin-left: 3rem;
    }
    
    .note-title {
        color: var(--accent-gold);
        font-weight: 700;
        margin-bottom: 0.5rem;
    }
    
    .warning-note {
        background: rgba(186, 40, 30, 0.1);
        border: 1px solid rgba(186, 40, 30, 0.3);
    }
    
    .warning-note::before {
        content: '🚨';
    }
    
    .warning-note .note-title {
        color: var(--accent-red);
    }
    
    .success-note {
        background: rgba(32, 201, 151, 0.1);
        border: 1px solid rgba(32, 201, 151, 0.3);
    }
    
    .success-note::before {
        content: '✅';
    }
    
    .success-note .note-title {
        color: var(--accent-teal);
    }
    
    /* Boutons d'action */
    .action-buttons {
        display: flex;
        justify-content: center;
        gap: 1rem;
        margin-top: 3rem;
        flex-wrap: wrap;
    }
    
    .btn {
        padding: 1rem 2rem;
        border: none;
        border-radius: 12px;
        font-family: 'Montserrat', sans-serif;
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        transition: var(--transition-smooth);
        display: flex;
        align-items: center;
        gap: 0.5rem;
        text-decoration: none;
    }
    
    .btn-primary {
        background: linear-gradient(135deg, var(--accent-red) 0%, #c53030 100%);
        color: white;
    }
    
    .btn-primary:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 30px rgba(186, 40, 30, 0.4);
        animation: glow 2s infinite;
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
    
    .btn-accept {
        background: linear-gradient(135deg, var(--accent-teal), #20b2aa);
        color: white;
    }
    
    .btn-accept:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 30px rgba(32, 201, 151, 0.4);
    }
    
    /* Footer Legal */
    .legal-footer {
        text-align: center;
        margin-top: 4rem;
        padding-top: 3rem;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
    }
    
    .version-info {
        color: var(--text-muted);
        font-size: 0.9rem;
        margin-bottom: 1rem;
    }
    
    .contact-legal {
        color: var(--text-light);
        margin-bottom: 2rem;
    }
    
    .contact-legal a {
        color: var(--accent-teal);
        text-decoration: none;
        transition: var(--transition-smooth);
    }
    
    .contact-legal a:hover {
        color: var(--accent-gold);
        text-decoration: underline;
    }
    
    /* ==================== RESPONSIVE ==================== */
    @media (max-width: 768px) {
        .legal-hero {
            padding: 3rem 1rem;
        }
        
        .legal-container {
            padding: 0 1rem 3rem;
        }
        
        .quick-nav {
            grid-template-columns: 1fr;
        }
        
        .toc-container, .content-container {
            padding: 1.5rem;
        }
        
        .legal-article {
            padding: 1.5rem;
        }
        
        .hero-info {
            gap: 1.5rem;
        }
        
        .action-buttons {
            flex-direction: column;
        }
        
        .btn {
            width: 100%;
            justify-content: center;
        }
    }
    
    @media (max-width: 480px) {
        .hero-title {
            font-size: 2rem;
        }
        
        .hero-subtitle {
            font-size: 1.1rem;
        }
        
        .content-title, .toc-title {
            font-size: 1.5rem;
        }
        
        .article-title {
            font-size: 1.3rem;
        }
        
        .toc-grid {
            grid-template-columns: 1fr;
        }
        
        .note-content {
            margin-left: 2rem;
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
    
    /* ==================== PRINT STYLES ==================== */
    @media print {
        body {
            background: white;
            color: black;
        }
        
        .legal-hero::before,
        .quick-nav-section,
        .action-buttons,
        .legal-footer {
            display: none;
        }
        
        .legal-container {
            padding: 0;
        }
        
        .content-container {
            border: 1px solid #ddd;
            box-shadow: none;
            background: white;
            color: black;
        }
        
        .legal-article {
            background: #f9f9f9;
            border-left: 4px solid #333;
        }
        
        .content-title, .article-title {
            color: #333;
        }
        
        .article-content {
            color: #555;
        }
    }
    </style>
</head>
<body>
    
    <!-- Hero Section -->
    <section class="legal-hero">
        <div class="container">
            <h1 class="hero-title">
                <i class="fas fa-scale-balanced"></i>
                Politique de Confidentialité & Conditions d'Utilisation
            </h1>
            <p class="hero-subtitle">
                Informations importantes concernant l'utilisation de nos services et la protection de vos données sur la plateforme des etudiants de l'Institut Supérieur Saint Paul Tarse
            </p>
            
            <div class="hero-info">
                <div class="info-item">
                    <div class="info-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <div class="info-text">Vos données sont protégées</div>
                </div>
                <div class="info-item">
                    <div class="info-icon">
                        <i class="fas fa-file-contract"></i>
                    </div>
                    <div class="info-text">Transparence totale</div>
                </div>
                <div class="info-item">
                    <div class="info-icon">
                        <i class="fas fa-user-lock"></i>
                    </div>
                    <div class="info-text">Respect de votre vie privée</div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Main Content -->
    <div class="legal-container">
        <!-- Navigation rapide -->
        <div class="quick-nav-section scroll-animate">
            <div class="quick-nav">
                <div class="nav-card" onclick="scrollToSection('terms')">
                    <div class="nav-icon terms-icon">
                        <i class="fas fa-file-signature"></i>
                    </div>
                    <h3 class="nav-title">Conditions d'Utilisation</h3>
                    <p class="nav-description">
                        Règles et obligations pour l'utilisation des modules Épreuves et JET de l'ISSPT
                    </p>
                </div>
                
                <div class="nav-card" onclick="scrollToSection('privacy')">
                    <div class="nav-icon privacy-icon">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <h3 class="nav-title">Politique de Confidentialité</h3>
                    <p class="nav-description">
                        Comment nous collectons, utilisons et protégeons vos données personnelles
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Table des matières -->
        <div class="toc-section scroll-animate">
            <div class="toc-container">
                <div class="toc-header">
                    <div class="toc-icon">
                        <i class="fas fa-list-ol"></i>
                    </div>
                    <h2 class="toc-title">Table des matières</h2>
                </div>
                
                <div class="toc-grid">
                    <div class="toc-column">
                        <h3>Conditions d'Utilisation</h3>
                        <ul class="toc-list">
                            <li class="toc-item">
                                <a href="#article1" class="toc-link">1. Acceptation des conditions</a>
                            </li>
                            <li class="toc-item">
                                <a href="#article2" class="toc-link">2. Description des services</a>
                            </li>
                            <li class="toc-item">
                                <a href="#article3" class="toc-link">3. Comptes utilisateurs</a>
                            </li>
                            <li class="toc-item">
                                <a href="#article4" class="toc-link">4. Droits d'auteur</a>
                            </li>
                            <li class="toc-item">
                                <a href="#article5" class="toc-link">5. Comportement acceptable</a>
                            </li>
                        </ul>
                    </div>
                    
                    <div class="toc-column">
                        <h3>Politique de Confidentialité</h3>
                        <ul class="toc-list">
                            <li class="toc-item">
                                <a href="#article6" class="toc-link">6. Données collectées</a>
                            </li>
                            <li class="toc-item">
                                <a href="#article7" class="toc-link">7. Utilisation des données</a>
                            </li>
                            <li class="toc-item">
                                <a href="#article8" class="toc-link">8. Protection des données</a>
                            </li>
                            <li class="toc-item">
                                <a href="#article9" class="toc-link">9. Vos droits</a>
                            </li>
                            <li class="toc-item">
                                <a href="#article10" class="toc-link">10. Cookies et tracking</a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Conditions d'Utilisation -->
        <div class="content-section">
            <div class="content-container" id="terms">
                <div class="content-header terms-header">
                    <div class="content-icon">
                        <i class="fas fa-file-contract"></i>
                    </div>
                    <div>
                        <h2 class="content-title">Conditions d'Utilisation</h2>
                        <div class="content-date">Dernière mise à jour : <?= $last_updated ?></div>
                    </div>
                </div>
                
                <!-- Article 1 -->
                <div class="legal-article" id="article1">
                    <div class="article-header">
                        <div class="article-number">1</div>
                        <h3 class="article-title">Acceptation des conditions</h3>
                    </div>
                    <div class="article-content">
                        <p>En accédant et en utilisant les services de la plateforme des etudiants de l'Institut Supérieur Saint Paul Tarse (ISSPT), vous acceptez d'être lié par les présentes conditions d'utilisation. Si vous n'acceptez pas ces conditions, veuillez ne pas utiliser nos services.</p>
                        
                        <p>Ces conditions s'appliquent à tous les utilisateurs du site, y compris les étudiants, les enseignants, les administrateurs et les visiteurs.</p>
                        
                        <div class="important-note">
                            <div class="note-content">
                                <div class="note-title">Important</div>
                                <p>L'utilisation des modules Épreuves et JET est strictement réservée aux membres de la communauté de l'ISSPT. Toute utilisation non autorisée est interdite.</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Article 2 -->
                <div class="legal-article" id="article2">
                    <div class="article-header">
                        <div class="article-number">2</div>
                        <h3 class="article-title">Description des services</h3>
                    </div>
                    <div class="article-content">
                        <p>L'ISSPT propose deux principaux services en ligne :</p>
                        
                        <ul class="article-list">
                            <li><strong>Module Épreuves :</strong> Recueil complet d'anciennes épreuves universitaires organisé par filière, niveau et année académique. Ce service permet aux étudiants de télécharger des documents pour leurs révisions.</li>
                            <li><strong>Module JET (Journée de l'Étudiant Tarsien) :</strong> Plateforme de gestion des activités et événements annuels incluant l'inscription aux activités, la réservation de tickets et la galerie des éditions précédentes.</li>
                        </ul>
                        
                        <p>Ces services sont fournis gratuitement à tous les étudiants régulièrement inscrits à l'ISSPT.</p>
                    </div>
                </div>
                
                <!-- Article 3 -->
                <div class="legal-article" id="article3">
                    <div class="article-header">
                        <div class="article-number">3</div>
                        <h3 class="article-title">Comptes utilisateurs</h3>
                    </div>
                    <div class="article-content">
                        <p>Pour accéder aux fonctionnalités complètes des services, vous devez créer un compte avec votre matricule étudiant valide.</p>
                        
                        <p>Vous êtes responsable de :</p>
                        
                        <ul class="article-list">
                            <li>La confidentialité de votre mot de passe</li>
                            <li>Toutes les activités effectuées depuis votre compte</li>
                            <li>La mise à jour de vos informations personnelles</li>
                            <li>La déclaration immédiate de toute utilisation non autorisée de votre compte</li>
                        </ul>
                        
                        <div class="important-note warning-note">
                            <div class="note-content">
                                <div class="note-title">Avertissement</div>
                                <p>Le partage de comptes est strictement interdit. Tout compte faisant l'objet d'un partage pourra être suspendu sans préavis.</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Article 4 -->
                <div class="legal-article" id="article4">
                    <div class="article-header">
                        <div class="article-number">4</div>
                        <h3 class="article-title">Droits d'auteur et propriété intellectuelle</h3>
                    </div>
                    <div class="article-content">
                        <p>Tous les contenus du module Épreuves (documents, corrigés, sujets) sont la propriété intellectuelle de l'ISSPT ou de leurs auteurs respectifs.</p>
                        
                        <p>Vous êtes autorisé à :</p>
                        
                        <ul class="article-list">
                            <li>Télécharger les documents pour un usage personnel</li>
                            <li>Utiliser les documents pour vos révisions académiques</li>
                            <li>Partager les références des documents avec d'autres étudiants de l'ISSPT</li>
                        </ul>
                        
                        <p>Vous n'êtes PAS autorisé à :</p>
                        
                        <ul class="article-list">
                            <li>Modifier, altérer ou falsifier les documents</li>
                            <li>Vendre, louer ou commercialiser les documents</li>
                            <li>Distribuer les documents en dehors de la communauté ISSPT</li>
                            <li>Utiliser les documents à des fins illégales ou immorales</li>
                        </ul>
                    </div>
                </div>
                
                <!-- Article 5 -->
                <div class="legal-article" id="article5">
                    <div class="article-header">
                        <div class="article-number">5</div>
                        <h3 class="article-title">Comportement acceptable</h3>
                    </div>
                    <div class="article-content">
                        <p>En utilisant nos services, vous vous engagez à :</p>
                        
                        <ul class="article-list">
                            <li>Respecter tous les membres de la communauté</li>
                            <li>Ne pas publier de contenus offensants, discriminatoires ou illégaux</li>
                            <li>Ne pas tenter de contourner les systèmes de sécurité</li>
                            <li>Ne pas surcharger les serveurs par des téléchargements abusifs</li>
                            <li>Signaler tout contenu inapproprié ou toute activité suspecte</li>
                        </ul>
                        
                        <div class="important-note">
                            <div class="note-content">
                                <div class="note-title">Sanctions</div>
                                <p>Tout manquement à ces règles peut entraîner la suspension temporaire ou permanente de votre compte, sans préavis, à la discrétion de l'administration.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Politique de Confidentialité -->
        <div class="content-section">
            <div class="content-container" id="privacy">
                <div class="content-header privacy-header">
                    <div class="content-icon">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <div>
                        <h2 class="content-title">Politique de Confidentialité</h2>
                        <div class="content-date">Dernière mise à jour : <?= $last_updated ?></div>
                    </div>
                </div>
                
                <!-- Article 6 -->
                <div class="legal-article" id="article6">
                    <div class="article-header">
                        <div class="article-number">6</div>
                        <h3 class="article-title">Données que nous collectons</h3>
                    </div>
                    <div class="article-content">
                        <p>Nous collectons les données suivantes pour fournir et améliorer nos services :</p>
                        
                        <ul class="article-list">
                            <li><strong>Données d'identification :</strong> Nom, prénom, matricule, email</li>
                            <li><strong>Données académiques :</strong> Filière, niveau, promotion</li>
                            <li><strong>Données de connexion :</strong> Adresse IP, type de navigateur, pages visitées</li>
                            <li><strong>Données d'activité :</strong> Épreuves téléchargées, activités JET suivies</li>
                            <li><strong>Données de paiement :</strong> Pour les événements payants du JET (traitées par des processeurs de paiement sécurisés)</li>
                        </ul>
                        
                        <div class="important-note success-note">
                            <div class="note-content">
                                <div class="note-title">Transparence</div>
                                <p>Nous ne collectons que les données nécessaires au fonctionnement de nos services. Vous pouvez à tout moment consulter et modifier vos données depuis votre profil.</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Article 7 -->
                <div class="legal-article" id="article7">
                    <div class="article-header">
                        <div class="article-number">7</div>
                        <h3 class="article-title">Comment nous utilisons vos données</h3>
                    </div>
                    <div class="article-content">
                        <p>Vos données sont utilisées dans les buts suivants :</p>
                        
                        <ul class="article-list">
                            <li><strong>Fournir les services :</strong> Accès aux épreuves, inscription aux activités JET</li>
                            <li><strong>Personnaliser l'expérience :</strong> Recommandations d'épreuves par filière</li>
                            <li><strong>Communication :</strong> Informations importantes, notifications d'événements</li>
                            <li><strong>Amélioration des services :</strong> Statistiques d'utilisation, feedback</li>
                            <li><strong>Sécurité :</strong> Prévention de la fraude, protection des comptes</li>
                            <li><strong>Conformité légale :</strong> Respect des obligations réglementaires</li>
                        </ul>
                        
                        <p>Nous ne vendons, ne louons ni ne partageons vos données personnelles avec des tiers à des fins commerciales.</p>
                    </div>
                </div>
                
                <!-- Article 8 -->
                <div class="legal-article" id="article8">
                    <div class="article-header">
                        <div class="article-number">8</div>
                        <h3 class="article-title">Protection de vos données</h3>
                    </div>
                    <div class="article-content">
                        <p>Nous mettons en œuvre des mesures de sécurité robustes pour protéger vos données :</p>
                        
                        <ul class="article-list">
                            <li><strong>Chiffrement :</strong> SSL/TLS pour toutes les communications</li>
                            <li><strong>Stockage sécurisé :</strong> Bases de données protégées et régulièrement sauvegardées</li>
                            <li><strong>Contrôle d'accès :</strong> Accès restreint aux données sensibles</li>
                            <li><strong>Authentification forte :</strong> Mots de passe chiffrés, possibilité d'authentification à deux facteurs</li>
                            <li><strong>Audits réguliers :</strong> Vérifications de sécurité périodiques</li>
                        </ul>
                        
                        <div class="important-note warning-note">
                            <div class="note-content">
                                <div class="note-title">Votre responsabilité</div>
                                <p>Vous devez également prendre des précautions pour protéger votre compte : choisissez un mot de passe robuste, ne le partagez pas et déconnectez-vous après utilisation sur les ordinateurs publics.</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Article 9 -->
                <div class="legal-article" id="article9">
                    <div class="article-header">
                        <div class="article-number">9</div>
                        <h3 class="article-title">Vos droits</h3>
                    </div>
                    <div class="article-content">
                        <p>Conformément à la législation sur la protection des données, vous disposez des droits suivants :</p>
                        
                        <ul class="article-list">
                            <li><strong>Droit d'accès :</strong> Consulter les données que nous détenons sur vous</li>
                            <li><strong>Droit de rectification :</strong> Corriger des données inexactes</li>
                            <li><strong>Droit à l'effacement :</strong> Demander la suppression de vos données</li>
                            <li><strong>Droit à la limitation :</strong> Restreindre le traitement de vos données</li>
                            <li><strong>Droit d'opposition :</strong> Vous opposer au traitement de vos données</li>
                            <li><strong>Droit à la portabilité :</strong> Recevoir vos données dans un format structuré</li>
                        </ul>
                        
                        <p>Pour exercer ces droits, contactez notre délégué à la protection des données à l'adresse : <strong>donnees@isspt.tg</strong></p>
                        
                        <div class="important-note success-note">
                            <div class="note-content">
                                <div class="note-title">Réponse garantie</div>
                                <p>Nous nous engageons à répondre à toute demande concernant vos données dans un délai maximum de 30 jours.</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Article 10 -->
                <div class="legal-article" id="article10">
                    <div class="article-header">
                        <div class="article-number">10</div>
                        <h3 class="article-title">Cookies et technologies de suivi</h3>
                    </div>
                    <div class="article-content">
                        <p>Nous utilisons des cookies et technologies similaires pour :</p>
                        
                        <ul class="article-list">
                            <li><strong>Fonctionnalité essentielle :</strong> Maintien de la session de connexion</li>
                            <li><strong>Préférences :</strong> Mémorisation de vos paramètres</li>
                            <li><strong>Analytique :</strong> Compréhension de l'utilisation des services</li>
                            <li><strong>Sécurité :</strong> Prévention des activités frauduleuses</li>
                        </ul>
                        
                        <p>Vous pouvez contrôler les cookies via les paramètres de votre navigateur. Cependant, désactiver certains cookies peut limiter certaines fonctionnalités.</p>
                        
                        <p>Nous n'utilisons pas de cookies tiers à des fins publicitaires.</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Boutons d'action -->
        <div class="action-buttons scroll-animate">
            <?php if ($isLogged): ?>
                <button class="btn btn-accept" onclick="acceptTerms()">
                    <i class="fas fa-check-circle"></i> J'accepte les conditions
                </button>
            <?php endif; ?>
            
            <a href="#top" class="btn btn-secondary">
                <i class="fas fa-arrow-up"></i> Retour en haut
            </a>
            
            <button class="btn btn-primary" onclick="window.print()">
                <i class="fas fa-print"></i> Imprimer cette page
            </button>
        </div>
        
        <!-- Footer Legal -->
        <div class="legal-footer scroll-animate">
            <div class="version-info">
                Version 2.1 | Entrée en vigueur : 01/01/2024
            </div>
            
            <div class="contact-legal">
                Pour toute question concernant ces conditions ou notre politique de confidentialité, contactez-nous à :<br>
                <strong>contact@isspt.tg</strong> ou <strong>donnees@isspt.tg</strong>
            </div>
            
            <p style="color: var(--text-muted); font-size: 0.9rem; margin-top: 2rem;">
                <i class="fas fa-info-circle"></i>
                Ces conditions et cette politique sont régulièrement mises à jour. Nous vous invitons à les consulter périodiquement.
            </p>
        </div>
    </div>
    
    
    <script>
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
    
    // Navigation dans la table des matières
    document.querySelectorAll('.toc-link').forEach((link) => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const targetId = this.getAttribute('href');
            const targetElement = document.querySelector(targetId);
            
            if (targetElement) {
                // Retirer la classe active de tous les liens
                document.querySelectorAll('.toc-link').forEach((l) => {
                    l.classList.remove('active');
                });
                
                // Ajouter la classe active au lien cliqué
                this.classList.add('active');
                
                // Scroll vers la section
                targetElement.scrollIntoView({ behavior: 'smooth' });
                
                // Animation de mise en évidence
                targetElement.style.animation = 'highlight 2s ease';
                setTimeout(() => {
                    targetElement.style.animation = '';
                }, 2000);
            }
        });
    });
    
    // Navigation rapide
    function scrollToSection(sectionId) {
        const section = document.getElementById(sectionId);
        if (section) {
            section.scrollIntoView({ behavior: 'smooth' });
            
            // Animation
            section.style.animation = 'none';
            setTimeout(() => {
                section.style.animation = 'slideInUp 0.5s ease-out';
            }, 10);
        }
    }
    
    // Accepter les conditions
    function acceptTerms() {
        if (confirm('En cliquant sur "OK", vous confirmez avoir lu, compris et accepté nos conditions d\'utilisation et notre politique de confidentialité.')) {
            // Envoyer l'acceptation au serveur (simulé ici)
            const notification = document.createElement('div');
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: var(--accent-teal);
                color: white;
                padding: 1rem 1.5rem;
                border-radius: 8px;
                box-shadow: 0 4px 15px rgba(0,0,0,0.2);
                z-index: 1000;
                animation: slideInUp 0.3s ease-out;
            `;
            notification.innerHTML = '<i class="fas fa-check-circle"></i> Votre acceptation a été enregistrée avec succès !';
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.animation = 'fadeOut 0.3s ease-out forwards';
                setTimeout(() => notification.remove(), 300);
            }, 3000);
            
            // Marquer comme accepté dans le localStorage
            localStorage.setItem('termsAccepted', 'true');
            localStorage.setItem('termsAcceptedDate', new Date().toISOString());
        }
    }
    
    // Vérifier si les conditions ont déjà été acceptées
    window.addEventListener('load', function() {
        document.body.style.opacity = '0';
        document.body.style.transition = 'opacity 0.5s ease-in';
        
        setTimeout(() => {
            document.body.style.opacity = '1';
        }, 100);
        
        // Ajouter une animation pour les icônes
        const icons = document.querySelectorAll('.info-icon, .nav-icon, .content-icon');
        icons.forEach((icon, index) => {
            setTimeout(() => {
                icon.style.transform = 'scale(0) rotate(-180deg)';
                icon.style.transition = 'all 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55)';
                
                setTimeout(() => {
                    icon.style.transform = 'scale(1) rotate(0)';
                }, 50);
            }, index * 100);
        });
        
        // Vérifier l'acceptation précédente
        const termsAccepted = localStorage.getItem('termsAccepted');
        if (!termsAccepted && <?= $isLogged ? 'true' : 'false' ?>) {
            // Afficher un rappel après 5 secondes
            setTimeout(() => {
                const reminder = document.createElement('div');
                reminder.style.cssText = `
                    position: fixed;
                    bottom: 20px;
                    left: 20px;
                    right: 20px;
                    max-width: 400px;
                    background: var(--glass-bg);
                    backdrop-filter: blur(10px);
                    border: 1px solid var(--glass-border);
                    border-radius: 12px;
                    padding: 1.5rem;
                    box-shadow: var(--glass-shadow);
                    z-index: 1000;
                    animation: slideInUp 0.3s ease-out;
                `;
                reminder.innerHTML = `
                    <h4 style="color: var(--accent-gold); margin-bottom: 0.5rem;">
                        <i class="fas fa-exclamation-circle"></i> Conditions importantes
                    </h4>
                    <p style="color: var(--text-light); margin-bottom: 1rem; font-size: 0.9rem;">
                        Vous n'avez pas encore accepté nos conditions d'utilisation et notre politique de confidentialité.
                    </p>
                    <div style="display: flex; gap: 0.5rem;">
                        <button onclick="acceptTerms()" style="flex: 1; padding: 0.5rem; background: var(--accent-teal); color: white; border: none; border-radius: 6px; cursor: pointer;">
                            Accepter maintenant
                        </button>
                        <button onclick="this.parentElement.parentElement.remove()" style="padding: 0.5rem 1rem; background: transparent; border: 1px solid var(--glass-border); color: var(--text-muted); border-radius: 6px; cursor: pointer;">
                            Plus tard
                        </button>
                    </div>
                `;
                document.body.appendChild(reminder);
            }, 5000);
        }
    });
    
    // Suivi des sections visibles pour la table des matières
    const sections = document.querySelectorAll('.legal-article');
    const tocLinks = document.querySelectorAll('.toc-link');
    
    const sectionObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const id = entry.target.getAttribute('id');
                tocLinks.forEach(link => {
                    link.classList.remove('active');
                    if (link.getAttribute('href') === `#${id}`) {
                        link.classList.add('active');
                    }
                });
            }
        });
    }, { threshold: 0.3 });
    
    sections.forEach(section => {
        sectionObserver.observe(section);
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
    
    // Fonction pour imprimer en PDF (simulation)
    function downloadAsPDF() {
        alert('La fonction d\'export PDF sera bientôt disponible. En attendant, vous pouvez utiliser la fonction d\'impression de votre navigateur.');
    }
    </script>
</body>
</html>