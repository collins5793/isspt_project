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

// --- Traitement du formulaire de contact ---
$message_sent = false;
$errors = [];
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message_content = trim($_POST['message'] ?? '');
    
    // Validation
    if (empty($name)) $errors[] = 'Le nom est requis';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email invalide';
    if (empty($subject)) $errors[] = 'Le sujet est requis';
    if (empty($message_content)) $errors[] = 'Le message est requis';
    
    if (empty($errors)) {
        // Enregistrement en base de données
        $query = "INSERT INTO contact_messages (name, email, subject, message, ip_address, created_at) 
                  VALUES (:name, :email, :subject, :message, :ip, NOW())";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([
            ':name' => $name,
            ':email' => $email,
            ':subject' => $subject,
            ':message' => $message_content,
            ':ip' => $_SERVER['REMOTE_ADDR']
        ]);
        
        // Envoi d'email (optionnel)
        $to = "contact@votre-universite.com";
        $headers = "From: $email\r\n";
        $headers .= "Reply-To: $email\r\n";
        $headers .= "Content-Type: text/plain; charset=utf-8\r\n";
        
        $email_body = "Nouveau message de contact:\n\n";
        $email_body .= "Nom: $name\n";
        $email_body .= "Email: $email\n";
        $email_body .= "Sujet: $subject\n\n";
        $email_body .= "Message:\n$message_content\n\n";
        $email_body .= "IP: " . $_SERVER['REMOTE_ADDR'];
        
        mail($to, "Contact: $subject", $email_body, $headers);
        
        $message_sent = true;
        $success_message = 'Votre message a été envoyé avec succès ! Nous vous répondrons dans les plus brefs délais.';
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📧 Contactez-nous - Recueil d'Épreuves Universitaires</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
    /* ==========================================================================
       PAGE DE CONTACT PREMIUM
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
    
    @keyframes pulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.05); }
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
    
    @keyframes shimmer {
        0% { background-position: -1000px 0; }
        100% { background-position: 1000px 0; }
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
    .contact-hero {
        padding: 5rem 2rem;
        text-align: center;
        position: relative;
        overflow: hidden;
    }
    
    .contact-hero::before {
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
        animation: slideInLeft 1s ease-out;
    }
    
    .hero-subtitle {
        font-size: 1.3rem;
        color: var(--text-light);
        max-width: 600px;
        margin: 0 auto 2rem;
        opacity: 0;
        animation: fadeInUp 1s ease-out 0.3s forwards;
    }
    
    /* ==================== CONTENT GRID ==================== */
    .contact-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 2rem 5rem;
    }
    
    .contact-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 3rem;
        margin-top: 2rem;
    }
    
    @media (max-width: 992px) {
        .contact-grid {
            grid-template-columns: 1fr;
            gap: 4rem;
        }
    }
    
    /* ==================== INFO CARDS ==================== */
    .info-section {
        opacity: 0;
        animation: slideInLeft 0.8s ease-out 0.4s forwards;
    }
    
    .info-card {
        background: var(--glass-bg);
        backdrop-filter: blur(20px);
        border: 1px solid var(--glass-border);
        border-radius: 20px;
        padding: 2.5rem;
        margin-bottom: 2rem;
        box-shadow: var(--glass-shadow);
        transition: var(--transition-smooth);
    }
    
    .info-card:hover {
        transform: translateY(-10px);
        border-color: var(--accent-red);
    }
    
    .card-header {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 2rem;
    }
    
    .card-icon {
        width: 60px;
        height: 60px;
        background: rgba(186, 40, 30, 0.2);
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        color: var(--accent-red);
        transition: var(--transition-smooth);
    }
    
    .info-card:hover .card-icon {
        background: var(--accent-red);
        color: white;
        animation: float 2s infinite;
    }
    
    .card-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--text-white);
    }
    
    .info-list {
        list-style: none;
    }
    
    .info-item {
        display: flex;
        align-items: flex-start;
        gap: 1rem;
        margin-bottom: 1.5rem;
        padding-bottom: 1.5rem;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }
    
    .info-item:last-child {
        margin-bottom: 0;
        padding-bottom: 0;
        border-bottom: none;
    }
    
    .item-icon {
        color: var(--accent-teal);
        font-size: 1.2rem;
        margin-top: 0.2rem;
        min-width: 24px;
    }
    
    .item-content h4 {
        color: var(--accent-gold);
        font-size: 1rem;
        font-weight: 600;
        margin-bottom: 0.3rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .item-content p {
        color: var(--text-light);
        line-height: 1.6;
    }
    
    .item-content a {
        color: var(--accent-teal);
        text-decoration: none;
        transition: var(--transition-smooth);
        position: relative;
    }
    
    .item-content a:hover {
        color: var(--accent-red);
        text-decoration: underline;
    }
    
    /* ==================== FORMULAIRE ==================== */
    .form-section {
        opacity: 0;
        animation: slideInRight 0.8s ease-out 0.6s forwards;
    }
    
    .contact-form {
        background: var(--glass-bg);
        backdrop-filter: blur(20px);
        border: 1px solid var(--glass-border);
        border-radius: 20px;
        padding: 2.5rem;
        box-shadow: var(--glass-shadow);
        transition: var(--transition-smooth);
    }
    
    .contact-form:hover {
        border-color: var(--accent-teal);
    }
    
    .form-header {
        text-align: center;
        margin-bottom: 2rem;
    }
    
    .form-title {
        font-size: 2rem;
        font-weight: 700;
        color: var(--text-white);
        margin-bottom: 0.5rem;
    }
    
    .form-subtitle {
        color: var(--text-muted);
        font-size: 1rem;
    }
    
    /* Messages d'erreur/succès */
    .message-container {
        margin-bottom: 2rem;
        animation: fadeInUp 0.6s ease-out;
    }
    
    .success-message {
        background: rgba(32, 201, 151, 0.2);
        border: 1px solid rgba(32, 201, 151, 0.3);
        color: var(--accent-teal);
        padding: 1.5rem;
        border-radius: 12px;
        display: flex;
        align-items: center;
        gap: 1rem;
    }
    
    .success-message i {
        font-size: 1.5rem;
        animation: pulse 2s infinite;
    }
    
    .error-message {
        background: rgba(186, 40, 30, 0.2);
        border: 1px solid rgba(186, 40, 30, 0.3);
        color: var(--accent-red);
        padding: 1rem 1.5rem;
        border-radius: 12px;
        margin-bottom: 1rem;
    }
    
    .error-message ul {
        list-style: none;
        margin-left: 1rem;
    }
    
    .error-message li {
        margin-bottom: 0.5rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    /* Champs de formulaire */
    .form-group {
        margin-bottom: 1.5rem;
        position: relative;
    }
    
    .form-label {
        display: block;
        color: var(--accent-gold);
        font-size: 0.9rem;
        font-weight: 600;
        margin-bottom: 0.5rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .form-input {
        width: 100%;
        padding: 1rem;
        background: rgba(0, 0, 0, 0.3);
        border: 2px solid rgba(255, 255, 255, 0.1);
        border-radius: 12px;
        color: var(--text-white);
        font-size: 1rem;
        font-family: 'Montserrat', sans-serif;
        transition: var(--transition-smooth);
    }
    
    .form-input:focus {
        outline: none;
        border-color: var(--accent-teal);
        background: rgba(32, 201, 151, 0.1);
        box-shadow: 0 0 0 3px rgba(32, 201, 151, 0.1);
    }
    
    .form-input:hover {
        border-color: rgba(255, 255, 255, 0.2);
    }
    
    .form-input::placeholder {
        color: var(--text-muted);
    }
    
    textarea.form-input {
        min-height: 150px;
        resize: vertical;
    }
    
    /* Bouton d'envoi */
    .btn-submit {
        position: relative;
        overflow: hidden;
        width: 100%;
        padding: 1.2rem;
        background: linear-gradient(135deg, var(--accent-red) 0%, #c53030 100%);
        color: white;
        border: none;
        border-radius: 12px;
        font-size: 1.1rem;
        font-weight: 700;
        cursor: pointer;
        transition: var(--transition-smooth);
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.8rem;
    }
    
    .btn-submit:hover {
        transform: translateY(-3px);
        box-shadow: 0 15px 30px rgba(186, 40, 30, 0.4);
    }
    
    .btn-submit:active {
        transform: translateY(-1px);
    }
    
    .ripple {
        position: absolute;
        background: rgba(255, 255, 255, 0.7);
        border-radius: 50%;
        transform: scale(0);
        animation: ripple 0.6s linear;
        pointer-events: none;
    }
    
    .btn-submit i {
        transition: var(--transition-smooth);
    }
    
    .btn-submit:hover i {
        transform: translateX(5px);
    }
    
    /* ==================== FOOTER DE LA PAGE ==================== */
    .contact-footer {
        text-align: center;
        padding: 3rem 2rem;
        color: var(--text-muted);
        border-top: 1px solid rgba(255, 255, 255, 0.1);
        margin-top: 3rem;
    }
    
    .social-links {
        display: flex;
        justify-content: center;
        gap: 1rem;
        margin-top: 2rem;
    }
    
    .social-link {
        width: 50px;
        height: 50px;
        background: var(--glass-bg);
        border: 1px solid var(--glass-border);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--text-light);
        text-decoration: none;
        font-size: 1.2rem;
        transition: var(--transition-smooth);
    }
    
    .social-link:hover {
        background: var(--accent-red);
        color: white;
        transform: translateY(-5px);
    }
    
    .social-link:nth-child(2):hover {
        background: var(--accent-teal);
    }
    
    .social-link:nth-child(3):hover {
        background: #1DA1F2;
    }
    
    .social-link:nth-child(4):hover {
        background: #4267B2;
    }
    
    /* ==================== RESPONSIVE ==================== */
    @media (max-width: 768px) {
        .contact-hero {
            padding: 3rem 1rem;
        }
        
        .contact-container {
            padding: 0 1rem 3rem;
        }
        
        .info-card,
        .contact-form {
            padding: 1.5rem;
        }
        
        .card-icon {
            width: 50px;
            height: 50px;
            font-size: 1.2rem;
        }
        
        .card-title {
            font-size: 1.3rem;
        }
    }
    
    @media (max-width: 480px) {
        .hero-title {
            font-size: 2rem;
        }
        
        .hero-subtitle {
            font-size: 1.1rem;
        }
        
        .form-title {
            font-size: 1.5rem;
        }
        
        .btn-submit {
            padding: 1rem;
            font-size: 1rem;
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
    
    /* ==================== LOADING EFFECT ==================== */
    .loading {
        display: inline-block;
        width: 20px;
        height: 20px;
        border: 2px solid rgba(255, 255, 255, 0.3);
        border-radius: 50%;
        border-top-color: white;
        animation: spin 1s ease-in-out infinite;
    }
    
    @keyframes spin {
        to { transform: rotate(360deg); }
    }
    </style>
</head>
<body>
    <?php include "includes/header.php"; ?>
    
    <!-- Hero Section -->
    <section class="contact-hero">
        <div class="container">
            <h1 class="hero-title">
                <i class="fas fa-comments"></i>
                Contactez-nous
            </h1>
            <p class="hero-subtitle">
                Une question ? Une suggestion ? N'hésitez pas à nous contacter.
                Notre équipe est à votre écoute pour vous accompagner.
            </p>
        </div>
    </section>
    
    <!-- Main Content -->
    <div class="contact-container">
        <div class="contact-grid">
            <!-- Section Informations -->
            <div class="info-section scroll-animate">
                <div class="info-card">
                    <div class="card-header">
                        <div class="card-icon">
                            <i class="fas fa-university"></i>
                        </div>
                        <h2 class="card-title">Informations de contact</h2>
                    </div>
                    
                    <ul class="info-list">
                        <li class="info-item">
                            <div class="item-icon">
                                <i class="fas fa-map-marker-alt"></i>
                            </div>
                            <div class="item-content">
                                <h4>Adresse</h4>
                                <p>
                                    Université Excellence<br>
                                    123 Avenue du Savoir<br>
                                    75000 Paris, France
                                </p>
                            </div>
                        </li>
                        
                        <li class="info-item">
                            <div class="item-icon">
                                <i class="fas fa-phone"></i>
                            </div>
                            <div class="item-content">
                                <h4>Téléphone</h4>
                                <p>
                                    Standard: <a href="tel:+33123456789">+33 1 23 45 67 89</a><br>
                                    Support: <a href="tel:+33198765432">+33 1 98 76 54 32</a>
                                </p>
                            </div>
                        </li>
                        
                        <li class="info-item">
                            <div class="item-icon">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <div class="item-content">
                                <h4>Email</h4>
                                <p>
                                    Support: <a href="mailto:support@epreuves-univ.com">support@epreuves-univ.com</a><br>
                                    Administration: <a href="mailto:admin@epreuves-univ.com">admin@epreuves-univ.com</a>
                                </p>
                            </div>
                        </li>
                        
                        <li class="info-item">
                            <div class="item-icon">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="item-content">
                                <h4>Horaires d'ouverture</h4>
                                <p>
                                    Lundi - Vendredi: 9h00 - 18h00<br>
                                    Samedi: 10h00 - 16h00<br>
                                    Dimanche: Fermé
                                </p>
                            </div>
                        </li>
                    </ul>
                </div>
                
                <div class="info-card">
                    <div class="card-header">
                        <div class="card-icon">
                            <i class="fas fa-headset"></i>
                        </div>
                        <h2 class="card-title">Support rapide</h2>
                    </div>
                    <p style="color: var(--text-light); line-height: 1.6; margin-bottom: 1rem;">
                        Notre équipe de support répond généralement dans les 24 heures.
                        Pour les urgences, privilégiez l'appel téléphonique.
                    </p>
                    <p style="color: var(--accent-gold); font-weight: 600;">
                        <i class="fas fa-bolt"></i> Réponse garantie sous 48h
                    </p>
                </div>
            </div>
            
            <!-- Section Formulaire -->
            <div class="form-section scroll-animate">
                <div class="contact-form">
                    <div class="form-header">
                        <h2 class="form-title">Envoyez-nous un message</h2>
                        <p class="form-subtitle">Remplissez le formulaire ci-dessous</p>
                    </div>
                    
                    <?php if ($message_sent): ?>
                        <div class="message-container">
                            <div class="success-message">
                                <i class="fas fa-check-circle"></i>
                                <div>
                                    <h3>Message envoyé !</h3>
                                    <p><?= htmlspecialchars($success_message) ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($errors)): ?>
                        <div class="message-container">
                            <div class="error-message">
                                <h3><i class="fas fa-exclamation-triangle"></i> Veuillez corriger les erreurs suivantes :</h3>
                                <ul>
                                    <?php foreach ($errors as $error): ?>
                                        <li><?= htmlspecialchars($error) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="" id="contactForm">
                        <div class="form-group">
                            <label for="name" class="form-label">
                                <i class="fas fa-user"></i> Votre nom complet *
                            </label>
                            <input type="text" 
                                   id="name" 
                                   name="name" 
                                   class="form-input" 
                                   placeholder="John Doe"
                                   value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                                   required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email" class="form-label">
                                <i class="fas fa-envelope"></i> Adresse email *
                            </label>
                            <input type="email" 
                                   id="email" 
                                   name="email" 
                                   class="form-input" 
                                   placeholder="john@exemple.com"
                                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                   required>
                        </div>
                        
                        <div class="form-group">
                            <label for="subject" class="form-label">
                                <i class="fas fa-tag"></i> Sujet du message *
                            </label>
                            <input type="text" 
                                   id="subject" 
                                   name="subject" 
                                   class="form-input" 
                                   placeholder="Question sur les épreuves"
                                   value="<?= htmlspecialchars($_POST['subject'] ?? '') ?>"
                                   required>
                        </div>
                        
                        <div class="form-group">
                            <label for="message" class="form-label">
                                <i class="fas fa-comment-dots"></i> Votre message *
                            </label>
                            <textarea id="message" 
                                      name="message" 
                                      class="form-input" 
                                      placeholder="Décrivez-nous votre demande en détail..."
                                      required><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
                        </div>
                        
                        <button type="submit" class="btn-submit" id="submitBtn">
                            <i class="fas fa-paper-plane"></i>
                            <span id="btnText">Envoyer le message</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Footer Social -->
        <div class="contact-footer scroll-animate">
            <h3 style="color: var(--text-white); margin-bottom: 1rem;">
                Suivez-nous sur les réseaux sociaux
            </h3>
            <p style="color: var(--text-muted); max-width: 600px; margin: 0 auto 2rem;">
                Restez connecté avec notre communauté étudiante et recevez les dernières actualités
            </p>
            
            <div class="social-links">
                <a href="#" class="social-link" title="Twitter">
                    <i class="fab fa-twitter"></i>
                </a>
                <a href="#" class="social-link" title="Instagram">
                    <i class="fab fa-instagram"></i>
                </a>
                <a href="#" class="social-link" title="LinkedIn">
                    <i class="fab fa-linkedin-in"></i>
                </a>
                <a href="#" class="social-link" title="Facebook">
                    <i class="fab fa-facebook-f"></i>
                </a>
            </div>
            
            <p style="margin-top: 2rem; font-size: 0.9rem; color: var(--text-muted);">
                <i class="fas fa-shield-alt"></i>
                Vos données sont sécurisées et ne seront jamais partagées avec des tiers
            </p>
        </div>
    </div>
    
    <?php include "includes/footer.php"; ?>
    
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
    
    // Effet ripple sur le bouton
    document.querySelectorAll('.btn-submit').forEach(button => {
        button.addEventListener('click', function(e) {
            const rect = this.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            
            const ripple = document.createElement('span');
            ripple.classList.add('ripple');
            ripple.style.left = x + 'px';
            ripple.style.top = y + 'px';
            
            this.appendChild(ripple);
            
            setTimeout(() => {
                ripple.remove();
            }, 600);
        });
    });
    
    // Animation de saisie des champs
    document.querySelectorAll('.form-input').forEach(input => {
        input.addEventListener('focus', function() {
            this.parentElement.style.transform = 'translateY(-2px)';
        });
        
        input.addEventListener('blur', function() {
            this.parentElement.style.transform = 'translateY(0)';
        });
    });
    
    // Validation en temps réel
    const contactForm = document.getElementById('contactForm');
    if (contactForm) {
        contactForm.addEventListener('submit', function(e) {
            const submitBtn = document.getElementById('submitBtn');
            const btnText = document.getElementById('btnText');
            
            // Vérification basique
            const email = document.getElementById('email').value;
            const name = document.getElementById('name').value;
            const subject = document.getElementById('subject').value;
            const message = document.getElementById('message').value;
            
            let isValid = true;
            
            // Réinitialiser les erreurs
            document.querySelectorAll('.form-group').forEach(group => {
                group.classList.remove('error');
            });
            
            // Validation email
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                document.getElementById('email').parentElement.classList.add('error');
                isValid = false;
            }
            
            // Validation des champs requis
            if (!name.trim()) {
                document.getElementById('name').parentElement.classList.add('error');
                isValid = false;
            }
            
            if (!subject.trim()) {
                document.getElementById('subject').parentElement.classList.add('error');
                isValid = false;
            }
            
            if (!message.trim()) {
                document.getElementById('message').parentElement.classList.add('error');
                isValid = false;
            }
            
            if (!isValid) {
                e.preventDefault();
                
                // Animation d'erreur
                const errorGroups = document.querySelectorAll('.form-group.error');
                errorGroups.forEach(group => {
                    group.style.animation = 'none';
                    setTimeout(() => {
                        group.style.animation = 'shake 0.5s ease';
                    }, 10);
                });
                
                // Animation de secousse
                const style = document.createElement('style');
                style.textContent = `
                    @keyframes shake {
                        0%, 100% { transform: translateX(0); }
                        25% { transform: translateX(-10px); }
                        75% { transform: translateX(10px); }
                    }
                    
                    .form-group.error .form-input {
                        border-color: var(--accent-red) !important;
                        background: rgba(186, 40, 30, 0.1) !important;
                    }
                `;
                document.head.appendChild(style);
                
                setTimeout(() => {
                    document.head.removeChild(style);
                }, 500);
            } else {
                // Animation de chargement
                btnText.innerHTML = 'Envoi en cours...';
                submitBtn.disabled = true;
                submitBtn.style.opacity = '0.8';
                
                const loader = document.createElement('span');
                loader.className = 'loading';
                submitBtn.appendChild(loader);
            }
        });
    }
    
    // Effet de survol sur les cartes
    document.querySelectorAll('.info-card, .contact-form').forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-10px)';
            this.style.boxShadow = '0 20px 40px rgba(0, 0, 0, 0.4)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
            this.style.boxShadow = 'var(--glass-shadow)';
        });
    });
    
    // Animation de chargement de la page
    window.addEventListener('load', function() {
        document.body.style.opacity = '0';
        document.body.style.transition = 'opacity 0.5s ease-in';
        
        setTimeout(() => {
            document.body.style.opacity = '1';
        }, 100);
        
        // Animation des icônes
        const icons = document.querySelectorAll('.card-icon, .item-icon');
        icons.forEach((icon, index) => {
            setTimeout(() => {
                icon.style.opacity = '0';
                icon.style.transform = 'scale(0.8)';
                icon.style.transition = 'all 0.4s ease-out';
                
                setTimeout(() => {
                    icon.style.opacity = '1';
                    icon.style.transform = 'scale(1)';
                }, 50);
            }, index * 100);
        });
    });
    
    // Compteur de caractères pour le message
    const messageTextarea = document.getElementById('message');
    if (messageTextarea) {
        const counter = document.createElement('div');
        counter.style.cssText = `
            color: var(--text-muted);
            font-size: 0.8rem;
            text-align: right;
            margin-top: 0.5rem;
            font-weight: 500;
        `;
        counter.textContent = '0/2000 caractères';
        messageTextarea.parentElement.appendChild(counter);
        
        messageTextarea.addEventListener('input', function() {
            const length = this.value.length;
            counter.textContent = `${length}/2000 caractères`;
            
            if (length > 1800) {
                counter.style.color = 'var(--accent-red)';
            } else if (length > 1500) {
                counter.style.color = 'var(--accent-gold)';
            } else {
                counter.style.color = 'var(--accent-teal)';
            }
        });
    }
    </script>
</body>
</html>