<?php
session_start();
require_once 'includes/db.php';

define('BASE_URL', '/isspt_projet/'); // Chemin relatif depuis localhost
$base_url = BASE_URL;

// Initialisation des variables de session et connexion
$isLogged = false;
$isAdmin = false;
$userName = '';
$userAvatar = 'assets/images/default-avatar.png'; // Avatar par défaut

// Vérifier l'état de la connexion
$isLogged = isset($_SESSION['etudiant_id']) || isset($_SESSION['admin_id']);
$isAdmin = isset($_SESSION['admin_id']);

// Statistiques générales du projet de la promotion SIL
$project_stats = [
    ['value' => '6', 'label' => 'Mois de développement', 'icon' => 'calendar-alt'],
    ['value' => '56K+', 'label' => 'Lignes de code cumulées', 'icon' => 'code'],
    ['value' => '2', 'label' => 'Modules principaux intégrés', 'icon' => 'cubes'],
    ['value' => '24/7', 'label' => 'Disponibilité plateforme', 'icon' => 'server']
];
?>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>👨‍💻 Équipe de Développement - Institut Supérieur Saint Paul Tarse</title>
    <!-- Font Awesome & Google Fonts -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
        <style>
    /* ==========================================================================
       PAGE ÉQUIPE DE DÉVELOPPEMENT PREMIUM - ISSPT
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
        33% { transform: translateY(-15px) rotate(5deg); }
        66% { transform: translateY(10px) rotate(-5deg); }
    }
    
    @keyframes slideInUp {
        from {
            opacity: 0;
            transform: translateY(40px);
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
    
    @keyframes pulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.05); }
    }
    
    @keyframes glow {
        0%, 100% { box-shadow: 0 0 20px rgba(32, 201, 151, 0.3); }
        50% { box-shadow: 0 0 40px rgba(32, 201, 151, 0.6); }
    }
    
    @keyframes rotate {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
    
    @keyframes typewriter {
        from { width: 0; }
        to { width: 100%; }
    }
    
    @keyframes blink {
        0%, 100% { opacity: 1; }
        50% { opacity: 0; }
    }
    
    @keyframes ripple {
        0% {
            transform: scale(0);
            opacity: 1;
        }
        100% {
            transform: scale(4);
            opacity: 0;
        }
    }
    
    /* ==================== HERO SECTION ==================== */
    .team-hero {
        padding: 5rem 2rem;
        text-align: center;
        position: relative;
        overflow: hidden;
    }
    
    .team-hero::before {
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
    
    .typewriter-container {
        display: inline-block;
        position: relative;
        margin: 1rem 0;
    }
    
    .typewriter-text {
        display: inline-block;
        overflow: hidden;
        white-space: nowrap;
        border-right: 3px solid var(--accent-teal);
        animation: typewriter 3s steps(30, end), blink 0.75s step-end infinite;
        font-size: 1.2rem;
        color: var(--accent-gold);
        font-weight: 600;
    }
    
    /* ==================== STATS SECTION ==================== */
    .stats-section {
        max-width: 1200px;
        margin: 0 auto 4rem;
        padding: 0 2rem;
        animation: slideInUp 1s ease-out 0.6s both;
    }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1.5rem;
    }
    
    .stat-card {
        background: var(--glass-bg);
        backdrop-filter: blur(20px);
        border: 1px solid var(--glass-border);
        border-radius: 16px;
        padding: 2rem;
        text-align: center;
        transition: var(--transition-smooth);
        position: relative;
        overflow: hidden;
    }
    
    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, var(--accent-teal), var(--accent-red));
        transform: scaleX(0);
        transition: var(--transition-smooth);
    }
    
    .stat-card:hover {
        transform: translateY(-10px);
        border-color: var(--accent-teal);
    }
    
    .stat-card:hover::before {
        transform: scaleX(1);
    }
    
    .stat-icon {
        width: 60px;
        height: 60px;
        background: rgba(32, 201, 151, 0.2);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1.5rem;
        font-size: 1.5rem;
        color: var(--accent-teal);
        transition: var(--transition-smooth);
    }
    
    .stat-card:hover .stat-icon {
        transform: scale(1.1) rotate(10deg);
        animation: float 2s infinite;
    }
    
    .stat-value {
        display: block;
        font-size: 2.5rem;
        font-weight: 800;
        color: var(--accent-gold);
        margin-bottom: 0.5rem;
    }
    
    .stat-label {
        color: var(--text-muted);
        font-size: 0.9rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    /* ==================== MAIN CONTENT ==================== */
    .team-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 2rem 5rem;
    }
    
    /* Section Titre */
    .section-header {
        text-align: center;
        margin-bottom: 3rem;
        position: relative;
    }
    
    .section-title {
        font-size: 2.5rem;
        font-weight: 800;
        color: var(--text-white);
        margin-bottom: 1rem;
        position: relative;
        display: inline-block;
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
    
    .section-subtitle {
        color: var(--text-muted);
        font-size: 1.1rem;
        max-width: 600px;
        margin: 0 auto;
    }
    
    /* Grille des développeurs */
    .developers-section {
        margin-bottom: 6rem;
    }
    
    .team-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
        gap: 2rem;
    }
    
    .team-card {
        background: var(--glass-bg);
        backdrop-filter: blur(20px);
        border: 1px solid var(--glass-border);
        border-radius: 20px;
        overflow: hidden;
        transition: var(--transition-smooth);
        position: relative;
        height: 100%;
    }
    
    .team-card:hover {
        transform: translateY(-15px) scale(1.02);
        border-color: var(--accent-teal);
        box-shadow: var(--glass-shadow), 0 20px 40px rgba(0, 0, 0, 0.4);
    }
    
    /* Badge de rôle */
    .role-badge {
        position: absolute;
        top: 18.5rem;
        right: 1.5rem;
        background: linear-gradient(135deg, var(--accent-red), #c53030);
        color: white;
        padding: 0.5rem 1.2rem;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        z-index: 10;
        animation: pulse 2s infinite;
    }
    
    /* Photo de profil */
    .team-photo {
        width: 100%;
        height: 400px;
        object-fit: cover;
        transition: var(--transition-smooth);
        margin-bottom: 0;
        padding-bottom: 0;
        padding-top: 60px;
    }
    
    .team-card:hover .team-photo {
        transform: scale(1.05);
    }
    
    /* Contenu de la carte */
    .team-content {
        padding: 2rem;
        padding-top: 0;
        margin-top: 0;
    }
    
    .team-name {
        font-size: 1.8rem;
        font-weight: 700;
        color: var(--text-white);
        margin-bottom: 0.5rem;
    }
    
    .team-role {
        color: var(--accent-teal);
        font-size: 1rem;
        font-weight: 600;
        margin-bottom: 1.5rem;
    }
    
    .team-description {
        color: var(--text-light);
        line-height: 1.6;
        margin-bottom: 1.5rem;
    }
    
    /* Compétences */
    .skills-container {
        margin: 1.5rem 0;
    }
    
    .skills-title {
        color: var(--accent-gold);
        font-size: 0.9rem;
        font-weight: 600;
        margin-bottom: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .skills-list {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
    }
    
    .skill-tag {
        background: rgba(32, 201, 151, 0.1);
        color: var(--accent-teal);
        padding: 0.4rem 0.8rem;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
        border: 1px solid rgba(32, 201, 151, 0.2);
        transition: var(--transition-smooth);
    }
    
    .skill-tag:hover {
        background: var(--accent-teal);
        color: var(--primary-dark);
        transform: translateY(-2px);
    }
    
    /* Contributions */
    .contributions-list {
        list-style: none;
        margin: 1rem 0;
    }
    
    .contributions-list li {
        color: var(--text-light);
        margin-bottom: 0.5rem;
        padding-left: 1.5rem;
        position: relative;
    }
    
    .contributions-list li::before {
        content: '✓';
        position: absolute;
        left: 0;
        color: var(--accent-teal);
        font-weight: bold;
    }
    
    /* Fun Fact */
    .fun-fact {
        background: rgba(255, 215, 0, 0.1);
        border: 1px solid rgba(255, 215, 0, 0.2);
        border-radius: 12px;
        padding: 1rem;
        margin-top: 1.5rem;
    }
    
    .fun-fact-title {
        color: var(--accent-gold);
        font-size: 0.9rem;
        font-weight: 600;
        margin-bottom: 0.5rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .fun-fact-text {
        color: var(--text-light);
        font-size: 0.9rem;
        font-style: italic;
    }
    
    /* Réseaux sociaux */
    .social-links {
        display: flex;
        gap: 1rem;
        margin-top: 1.5rem;
        padding-top: 1.5rem;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
    }
    
    .social-link {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.05);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--text-muted);
        text-decoration: none;
        transition: var(--transition-smooth);
        border: 1px solid rgba(255, 255, 255, 0.1);
    }
    
    .social-link:hover {
        transform: translateY(-3px);
        color: white;
    }
    
    .social-link.github:hover { background: #333; }
    .social-link.linkedin:hover { background: #0077b5; }
    .social-link.twitter:hover { background: #1da1f2; }
    .social-link.dribbble:hover { background: #ea4c89; }
    .social-link.gitlab:hover { background: #fc6d26; }
    
    
    
    /* Timeline du projet */
    .timeline-section {
        margin: 4rem 0;
    }
    
    .timeline {
        position: relative;
        max-width: 800px;
        margin: 3rem auto 0;
    }
    
    .timeline::before {
        content: '';
        position: absolute;
        left: 50%;
        transform: translateX(-50%);
        width: 2px;
        height: 100%;
        background: linear-gradient(to bottom, var(--accent-teal), var(--accent-red));
    }
    .contacting {
        content: '';
        position: absolute;
        left: 50%;
        transform: translateX(-50%);
        background: linear-gradient(to right, var(--accent-teal), var(--accent-red));
    }
    
    .timeline-item {
        margin-bottom: 3rem;
        position: relative;
        width: calc(50% - 40px);
    }
    
    .timeline-item:nth-child(odd) {
        left: 0;
        text-align: right;
        padding-right: 60px;
    }
    
    .timeline-item:nth-child(even) {
        left: 55%;
        text-align: left;
        padding-left: 60px;
    }
    
    .timeline-dot {
        position: absolute;
        width: 20px;
        height: 20px;
        background: var(--accent-teal);
        border-radius: 50%;
        top: 10px;
    }
    
    .timeline-item:nth-child(odd) .timeline-dot {
        right: -50px;
    }
    
    .timeline-item:nth-child(even) .timeline-dot {
        left: -50px;
    }
    
    .timeline-content {
        background: var(--glass-bg);
        backdrop-filter: blur(20px);
        border: 1px solid var(--glass-border);
        border-radius: 12px;
        padding: 1.5rem;
        transition: var(--transition-smooth);
    }
    
    .timeline-content:hover {
        border-color: var(--accent-teal);
        transform: translateY(-5px);
    }
    
    .timeline-date {
        color: var(--accent-gold);
        font-weight: 600;
        margin-bottom: 0.5rem;
        font-size: 0.9rem;
    }
    
    .timeline-title {
        color: var(--text-white);
        font-size: 1.2rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
    }
    
    .timeline-description {
        color: var(--text-light);
        font-size: 0.95rem;
    }
    
    /* CTA Section */
    .cta-section {
        text-align: center;
        margin-top: 4rem;
        padding: 3rem;
        background: var(--glass-bg);
        backdrop-filter: blur(20px);
        border: 1px solid var(--glass-border);
        border-radius: 20px;
    }
    
    .cta-title {
        font-size: 2rem;
        font-weight: 700;
        color: var(--text-white);
        margin-bottom: 1rem;
    }
    
    .cta-description {
        color: var(--text-light);
        max-width: 600px;
        margin: 0 auto 2rem;
        line-height: 1.6;
    }
    
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 1rem 2.5rem;
        background: linear-gradient(135deg, var(--accent-red) 0%, #c53030 100%);
        color: white;
        border: none;
        border-radius: 12px;
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        transition: var(--transition-smooth);
        text-decoration: none;
    }
    
    .btn:hover {
        transform: translateY(-3px);
        box-shadow: 0 15px 30px rgba(186, 40, 30, 0.4);
        animation: glow 2s infinite;
    }
    
    /* ==================== RESPONSIVE ==================== */
    @media (max-width: 768px) {
        .team-hero {
            padding: 3rem 1rem;
        }
        
        .team-container {
            padding: 0 1rem 3rem;
        }
        
        .team-grid {
            grid-template-columns: 1fr;
        }
        
        .timeline::before {
            left: 30px;
        }
        
        .timeline-item {
            width: calc(100% - 80px);
            left: 80px !important;
            text-align: left !important;
            padding-left: 60px !important;
            padding-right: 0 !important;
        }
        
        .timeline-item:nth-child(odd) .timeline-dot,
        .timeline-item:nth-child(even) .timeline-dot {
            left: -40px;
        }
        
        .section-title {
            font-size: 2rem;
        }
        
        .supervisors-grid {
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
        
        .stat-value {
            font-size: 2rem;
        }
        
        .cta-section {
            padding: 2rem 1rem;
        }
        
        .cta-title {
            font-size: 1.5rem;
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
    
    /* Effet ripple */
    .ripple {
        position: absolute;
        background: rgba(255, 255, 255, 0.7);
        border-radius: 50%;
        transform: scale(0);
        animation: ripple 0.6s linear;
        pointer-events: none;
    }
    </style>


    <?php include "includes/header.php"; ?>
    
    <!-- Hero Section -->
    <section class="team-hero">
        <div class="container">
            <h1 class="hero-title">
                <i class="fas fa-code"></i>
                L'Équipe de Développement
            </h1>
            <p class="hero-subtitle">
                Découvrez la promotion qui a conçu et réalisé la plateforme de l'ISSPT, 
                alliant rigueur technique et esprit d'innovation.
            </p>
            <div class="typewriter-container">
                <span class="typewriter-text">Conception d'outils modernes pour l'éducation...</span>
            </div>
        </div>
    </section>
    
    <!-- Statistiques du Projet -->
    <div class="stats-section scroll-animate">
        <div class="stats-grid">
            <?php foreach ($project_stats as $stat): ?>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-<?= htmlspecialchars($stat['icon']) ?>"></i>
                </div>
                <div class="stat-value"><?= htmlspecialchars($stat['value']) ?></div>
                <div class="stat-label"><?= htmlspecialchars($stat['label']) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="team-container">
        
        <!-- Présentation de la Promotion -->
        <section class="developers-section">
            <div class="section-header scroll-animate">
                <h2 class="section-title">Les Concepteurs</h2>
                <div class="promo-presentation-card">
                    <h3>Étudiants en Système Informatique et Logiciel (SIL)</h3>
                    <h4>Institut Supérieur Saint Paul Tarse</h4>
                    <p class="section-subtitle">
                        Cette plateforme est l'œuvre collective des étudiants de la filière Système Informatique et Logiciel. 
                        À travers ce projet d'envergure, la promotion a mis en synergie ses compétences en ingénierie logicielle, 
                        gestion de bases de données et conception d'interfaces pour offrir un outil adapté aux besoins de notre communauté académique.
                    </p>
                </div>
            </div>
        </section>
        
        <!-- Timeline du projet -->
        <section class="timeline-section">
            <div class="section-header scroll-animate">
                <h2 class="section-title">Timeline du Projet</h2>
                <p class="section-subtitle">
                    Le parcours de développement de la plateforme ISSPT par la promotion.
                </p>
            </div>
            
            <div class="timeline scroll-animate">
                <div class="timeline-item">
                    <div class="timeline-dot"></div>
                    <div class="timeline-content">
                        <div class="timeline-date">Novembre - Décembre 2025</div>
                        <h3 class="timeline-title">Conception & Planification</h3>
                        <p class="timeline-description">
                            Analyse globale des besoins de l'institut, rédaction du cahier des charges et modélisation de l'architecture.
                        </p>
                    </div>
                </div>
                
                <div class="timeline-item">
                    <div class="timeline-dot"></div>
                    <div class="timeline-content">
                        <div class="timeline-date">Janvier 2026</div>
                        <h3 class="timeline-title">Développement du Module Épreuves</h3>
                        <p class="timeline-description">
                            Mise en place de la base de données relationnelle et implémentation du système de gestion des épreuves.
                        </p>
                    </div>
                </div>
                
                <div class="timeline-item">
                    <div class="timeline-dot"></div>
                    <div class="timeline-content">
                        <div class="timeline-date">Février 2026 - Avril 2026</div>
                        <h3 class="timeline-title">Développement du Module JET</h3>
                        <p class="timeline-description">
                            Spécification et implémentation complète du système d'inscription aux activités et de suivi.
                        </p>
                    </div>
                </div>
                
                <div class="timeline-item">
                    <div class="timeline-dot"></div>
                    <div class="timeline-content">
                        <div class="timeline-date">Mai 2026</div>
                        <h3 class="timeline-title">Intégration d'Interface & Ergonomie</h3>
                        <p class="timeline-description">
                            Travail sur l'expérience utilisateur (UI/UX) pour garantir une navigation fluide, moderne et accessible.
                        </p>
                    </div>
                </div>
                
                <div class="timeline-item">
                    <div class="timeline-dot"></div>
                    <div class="timeline-content">
                        <div class="timeline-date">Mai 2026</div>
                        <h3 class="timeline-title">Tests & Optimisations</h3>
                        <p class="timeline-description">
                            Campagne de tests unitaires, audits de sécurité, correction des bugs et optimisation des requêtes de la base de données.
                        </p>
                    </div>
                </div>
                
                <div class="timeline-item">
                    <div class="timeline-dot"></div>
                    <div class="timeline-content">
                        <div class="timeline-date">Juin 2026</div>
                        <h3 class="timeline-title">Lancement Officiel</h3>
                        <p class="timeline-description">
                            Déploiement final sur les serveurs de l'établissement et ouverture officielle des accès de la plateforme.
                        </p>
                    </div>
                </div>
            </div>
        </section>
        
        <!-- CTA Section -->
        <section class="cta-section scroll-animate">
            <h2 class="cta-title">Une question ou une suggestion concernant notre travail ?</h2>
            <p class="cta-description">
                L'équipe reste à l'écoute de la communauté pour toute suggestion d'amélioration, 
                remontée de bug ou échange technique autour du projet.
            </p>
            <p class="contacting">N'hésitez pas à vous rapprocher des délégués de la promotion ou de l'administration de l'ISSPT.</p>
        </section>
    </div>
    
    <?php include "includes/footer.php"; ?>
    
    <!-- Scripts d'animation & Compteurs -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Observer pour les animations au scroll
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
        
        // Animation dynamique des compteurs de statistiques
        document.querySelectorAll('.stat-value').forEach((stat) => {
            const originalText = stat.textContent;
            const numericPart = originalText.replace(/[^0-9]/g, '');
            
            if (numericPart) {
                const target = parseInt(numericPart);
                let current = 0;
                const increment = target / 20;
                
                const timer = setInterval(() => {
                    current += increment;
                    if (current >= target) {
                        clearInterval(timer);
                        stat.textContent = originalText;
                    } else {
                        stat.textContent = Math.floor(current) + (originalText.includes('+') ? '+' : '');
                    }
                }, 50);
            }
        });

        // Animation Typewriter séquentielle
        const typewriterText = document.querySelector('.typewriter-text');
        if (typewriterText) {
            const texts = [
                "Conception d'outils modernes pour l'éducation...",
                "Code. Design. Innovation.",
                "Une promotion engagée, une vision collective.",
                "L'excellence au cœur de chaque ligne de code."
            ];
            let currentIndex = 0;
            
            setInterval(() => {
                currentIndex = (currentIndex + 1) % texts.length;
                typewriterText.style.animation = 'none';
                typewriterText.textContent = texts[currentIndex];
                
                setTimeout(() => {
                    typewriterText.style.animation = 'typewriter 3s steps(30, end), blink 0.75s step-end infinite';
                }, 10);
            }, 7000);
        }
    });
    </script>
