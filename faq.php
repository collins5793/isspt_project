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

// Récupérer les catégories et questions de la FAQ depuis la base de données
$faq_categories = [];
$faq_items = [];

try {
    // Récupérer les catégories
    $query = "SELECT * FROM faq_categories WHERE status = 'active' ORDER BY display_order ASC";
    $stmt = $pdo->query($query);
    $faq_categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Récupérer les questions/réponses
    $query = "SELECT f.*, c.name as category_name, c.color as category_color 
              FROM faq_items f 
              JOIN faq_categories c ON f.category_id = c.id 
              WHERE f.status = 'active' 
              ORDER BY c.display_order ASC, f.display_order ASC";
    $stmt = $pdo->query($query);
    $faq_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Grouper par catégorie
    $faq_by_category = [];
    foreach ($faq_items as $item) {
        $faq_by_category[$item['category_id']][] = $item;
    }
    
} catch (PDOException $e) {
    // En cas d'erreur, utiliser des données par défaut
    $faq_categories = [
        ['id' => 1, 'name' => 'Module Épreuves', 'color' => '#20c997'],
        ['id' => 2, 'name' => 'Module JET', 'color' => '#BA281E'],
        ['id' => 3, 'name' => 'Inscription & Compte', 'color' => '#FFD700'],
        ['id' => 4, 'name' => 'Général', 'color' => '#8A2BE2']
    ];
    
    $faq_by_category = [
        1 => [
            ['id' => 1, 'question' => 'Comment télécharger une épreuve ?', 'answer' => 'Connectez-vous à votre compte, naviguez dans le recueil d\'épreuves, utilisez les filtres pour trouver l\'épreuve souhaitée et cliquez sur le bouton "Télécharger".'],
            ['id' => 2, 'question' => 'Comment proposer une épreuve manquante ?', 'answer' => 'Si vous avez une épreuve qui n\'est pas dans notre base, contactez l\'administration ou envoyez-la à l\'adresse epreuves@isspt.tg'],
            ['id' => 3, 'question' => 'Les épreuves sont-elles gratuites ?', 'answer' => 'Oui, toutes les épreuves sont entièrement gratuites pour les étudiants de l\'ISS-PT.'],
            ['id' => 4, 'question' => 'Comment filtrer les épreuves par filière ?', 'answer' => 'Utilisez le menu déroulant "Filière" dans la barre de filtres pour sélectionner votre filière (SIL, RIT, GIT, etc.).']
        ],
        2 => [
            ['id' => 5, 'question' => 'Qu\'est-ce que la Journée de l\'Étudiant Tarsien ?', 'answer' => 'La JET est un événement annuel organisé par l\'ISS-PT comprenant des activités sportives, culturelles, académiques et sociales.'],
            ['id' => 6, 'question' => 'Comment s\'inscrire aux activités de la JET ?', 'answer' => 'Connectez-vous, accédez à la section JET, choisissez les activités et suivez le processus d\'inscription.'],
            ['id' => 7, 'question' => 'Comment obtenir des tickets pour les événements ?', 'answer' => 'Les tickets sont disponibles en ligne dans la section JET. Les étudiants peuvent réserver leurs places pour les événements payants.'],
            ['id' => 8, 'question' => 'Puis-je proposer une activité pour la JET ?', 'answer' => 'Oui, contactez le comité d\'organisation via le formulaire de contact ou en personne.'],
            ['id' => 9, 'question' => 'Où puis-je voir les photos des éditions précédentes ?', 'answer' => 'Accédez à la galerie dans la section JET pour voir les photos et vidéos des éditions passées.']
        ],
        3 => [
            ['id' => 10, 'question' => 'Comment créer un compte étudiant ?', 'answer' => 'Rendez-vous sur la page d\'inscription, remplissez le formulaire avec votre matricule et informations personnelles.'],
            ['id' => 11, 'question' => 'J\'ai oublié mon mot de passe, que faire ?', 'answer' => 'Cliquez sur "Mot de passe oublié" sur la page de connexion et suivez les instructions pour le réinitialiser.'],
            ['id' => 12, 'question' => 'Comment modifier mes informations personnelles ?', 'answer' => 'Connectez-vous et accédez à votre profil dans le menu utilisateur pour modifier vos informations.'],
            ['id' => 13, 'question' => 'Mon compte a été désactivé, pourquoi ?', 'answer' => 'Cela peut être dû à une inactivité prolongée ou à une violation des conditions d\'utilisation. Contactez l\'administration.']
        ],
        4 => [
            ['id' => 14, 'question' => 'Le site est-il compatible mobile ?', 'answer' => 'Oui, le site est entièrement responsive et fonctionne sur tous les appareils (smartphones, tablettes, ordinateurs).'],
            ['id' => 15, 'question' => 'Comment contacter l\'administration ?', 'answer' => 'Utilisez le formulaire de contact dans le menu ou envoyez un email à contact@isspt.tg'],
            ['id' => 16, 'question' => 'Le site est-il sécurisé ?', 'answer' => 'Oui, nous utilisons des protocoles de sécurité avancés pour protéger vos données.'],
            ['id' => 17, 'question' => 'Comment signaler un problème technique ?', 'answer' => 'Utilisez le formulaire de contact en sélectionnant "Problème technique" comme sujet.']
        ]
    ];
}

// Traitement du formulaire de question
$question_sent = false;
$question_errors = [];
$question_success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_question'])) {
    $question_name = trim($_POST['name'] ?? '');
    $question_email = trim($_POST['email'] ?? '');
    $question_category = trim($_POST['category'] ?? '');
    $question_content = trim($_POST['question'] ?? '');
    
    // Validation
    if (empty($question_name)) $question_errors[] = 'Le nom est requis';
    if (empty($question_email) || !filter_var($question_email, FILTER_VALIDATE_EMAIL)) $question_errors[] = 'Email invalide';
    if (empty($question_category)) $question_errors[] = 'Veuillez sélectionner une catégorie';
    if (empty($question_content)) $question_errors[] = 'La question est requise';
    
    if (empty($question_errors)) {
        try {
            // Enregistrer en base de données
            $query = "INSERT INTO faq_questions (name, email, category, question, status, created_at) 
                      VALUES (:name, :email, :category, :question, 'pending', NOW())";
            
            $stmt = $pdo->prepare($query);
            $stmt->execute([
                ':name' => $question_name,
                ':email' => $question_email,
                ':category' => $question_category,
                ':question' => $question_content
            ]);
            
            $question_sent = true;
            $question_success = 'Votre question a été soumise avec succès ! Nous y répondrons dans les plus brefs délais.';
            
        } catch (PDOException $e) {
            $question_errors[] = 'Erreur lors de l\'enregistrement. Veuillez réessayer.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>❓ FAQ - Institut Supérieur Saint Paul Tarse</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
    /* ==========================================================================
       PAGE FAQ PREMIUM - ISS-PT
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
    
    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        25% { transform: translateX(-5px); }
        75% { transform: translateX(5px); }
    }
    
    @keyframes rotate {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
    
    @keyframes glow {
        0%, 100% { box-shadow: 0 0 20px rgba(32, 201, 151, 0.3); }
        50% { box-shadow: 0 0 40px rgba(32, 201, 151, 0.6); }
    }
    
    @keyframes shimmer {
        0% { background-position: -1000px 0; }
        100% { background-position: 1000px 0; }
    }
    
    /* ==================== HERO SECTION ==================== */
    .faq-hero {
        padding: 5rem 2rem;
        text-align: center;
        position: relative;
        overflow: hidden;
    }
    
    .faq-hero::before {
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
    
    .hero-stats {
        display: flex;
        justify-content: center;
        gap: 2rem;
        flex-wrap: wrap;
        opacity: 0;
        animation: slideInUp 1s ease-out 0.6s forwards;
    }
    
    .stat-item {
        text-align: center;
    }
    
    .stat-value {
        display: block;
        font-size: 2rem;
        font-weight: 700;
        color: var(--accent-teal);
    }
    
    .stat-label {
        font-size: 0.9rem;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    /* ==================== MAIN CONTENT ==================== */
    .faq-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 2rem 5rem;
    }
    
    /* Recherche */
    .search-section {
        margin-bottom: 3rem;
        animation: slideInUp 1s ease-out 0.8s both;
    }
    
    .search-container {
        position: relative;
        max-width: 600px;
        margin: 0 auto;
    }
    
    .search-input {
        width: 100%;
        padding: 1.2rem 1.5rem 1.2rem 4rem;
        background: var(--glass-bg);
        backdrop-filter: blur(20px);
        border: 2px solid var(--glass-border);
        border-radius: 16px;
        color: var(--text-white);
        font-size: 1.1rem;
        transition: var(--transition-smooth);
    }
    
    .search-input:focus {
        outline: none;
        border-color: var(--accent-teal);
        box-shadow: 0 0 0 3px rgba(32, 201, 151, 0.1);
    }
    
    .search-input::placeholder {
        color: var(--text-muted);
    }
    
    .search-icon {
        position: absolute;
        left: 1.5rem;
        top: 50%;
        transform: translateY(-50%);
        color: var(--accent-teal);
        font-size: 1.3rem;
    }
    
    /* Catégories */
    .categories-section {
        margin-bottom: 4rem;
        animation: slideInUp 1s ease-out 1s both;
    }
    
    .categories-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1.5rem;
        margin-top: 2rem;
    }
    
    .category-card {
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
    
    .category-card::before {
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
    
    .category-card:hover {
        transform: translateY(-10px);
        border-color: var(--accent-teal);
    }
    
    .category-card:hover::before {
        opacity: 1;
    }
    
    .category-icon {
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
    
    .category-card:hover .category-icon {
        transform: scale(1.1) rotate(10deg);
        animation: float 2s infinite;
    }
    
    .category-title {
        font-size: 1.3rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
        color: var(--text-white);
    }
    
    .category-count {
        color: var(--accent-gold);
        font-weight: 600;
        font-size: 0.9rem;
    }
    
    /* FAQ Accordéon */
    .faq-section {
        animation: slideInUp 1s ease-out 1.2s both;
    }
    
    .section-header {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 2rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }
    
    .section-icon {
        width: 60px;
        height: 60px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.8rem;
        background: rgba(32, 201, 151, 0.2);
        color: var(--accent-teal);
    }
    
    .section-title {
        font-size: 2rem;
        font-weight: 700;
        color: var(--text-white);
    }
    
    .faq-accordion {
        background: var(--glass-bg);
        backdrop-filter: blur(20px);
        border: 1px solid var(--glass-border);
        border-radius: 16px;
        overflow: hidden;
        box-shadow: var(--glass-shadow);
    }
    
    .faq-item {
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        transition: var(--transition-smooth);
    }
    
    .faq-item:last-child {
        border-bottom: none;
    }
    
    .faq-item:hover {
        background: rgba(255, 255, 255, 0.03);
    }
    
    .faq-question {
        padding: 1.5rem 2rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        cursor: pointer;
        transition: var(--transition-smooth);
    }
    
    .faq-question:hover {
        background: rgba(255, 255, 255, 0.05);
    }
    
    .question-text {
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--text-white);
        flex: 1;
        margin-right: 2rem;
    }
    
    .question-number {
        width: 30px;
        height: 30px;
        background: var(--accent-red);
        color: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.9rem;
        font-weight: 700;
        margin-right: 1rem;
        flex-shrink: 0;
    }
    
    .toggle-icon {
        color: var(--accent-teal);
        font-size: 1.2rem;
        transition: var(--transition-smooth);
        flex-shrink: 0;
    }
    
    .faq-item.active .toggle-icon {
        transform: rotate(180deg);
    }
    
    .faq-answer {
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.5s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    .faq-item.active .faq-answer {
        max-height: 500px;
    }
    
    .answer-content {
        padding: 0 2rem 1.5rem 5.5rem;
        color: var(--text-light);
        line-height: 1.7;
    }
    
    .answer-content p {
        margin-bottom: 1rem;
    }
    
    .answer-content p:last-child {
        margin-bottom: 0;
    }
    
    .useful-buttons {
        display: flex;
        gap: 1rem;
        margin-top: 1rem;
    }
    
    .btn-useful {
        background: transparent;
        border: 1px solid var(--glass-border);
        color: var(--text-muted);
        padding: 0.5rem 1rem;
        border-radius: 8px;
        font-size: 0.9rem;
        cursor: pointer;
        transition: var(--transition-smooth);
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .btn-useful:hover {
        background: rgba(32, 201, 151, 0.1);
        color: var(--accent-teal);
        border-color: var(--accent-teal);
    }
    
    /* Form Question */
    .question-form-section {
        margin-top: 6rem;
        animation: slideInUp 1s ease-out 1.4s both;
    }
    
    .question-form {
        background: var(--glass-bg);
        backdrop-filter: blur(20px);
        border: 1px solid var(--glass-border);
        border-radius: 20px;
        padding: 2.5rem;
        box-shadow: var(--glass-shadow);
        transition: var(--transition-smooth);
    }
    
    .question-form:hover {
        border-color: var(--accent-teal);
    }
    
    .form-header {
        text-align: center;
        margin-bottom: 2rem;
    }
    
    .form-icon {
        width: 80px;
        height: 80px;
        background: linear-gradient(135deg, var(--accent-red), #c53030);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1.5rem;
        font-size: 2rem;
        animation: pulse 2s infinite;
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
    
    /* Messages */
    .message-container {
        margin-bottom: 2rem;
        animation: slideInUp 0.6s ease-out;
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
        padding: 1.5rem;
        border-radius: 12px;
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
    
    /* Form */
    .form-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }
    
    .form-group {
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
    
    .form-input, .form-select, .form-textarea {
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
    
    .form-input:focus, .form-select:focus, .form-textarea:focus {
        outline: none;
        border-color: var(--accent-teal);
        background: rgba(32, 201, 151, 0.1);
        box-shadow: 0 0 0 3px rgba(32, 201, 151, 0.1);
    }
    
    .form-textarea {
        min-height: 150px;
        resize: vertical;
    }
    
    .form-full {
        grid-column: 1 / -1;
    }
    
    .btn-submit {
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
        animation: glow 2s infinite;
    }
    
    /* Contact Info */
    .contact-info {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 2rem;
        margin-top: 4rem;
        padding-top: 4rem;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
    }
    
    .contact-card {
        text-align: center;
        padding: 2rem;
        background: var(--glass-bg);
        backdrop-filter: blur(10px);
        border: 1px solid var(--glass-border);
        border-radius: 16px;
        transition: var(--transition-smooth);
    }
    
    .contact-card:hover {
        border-color: var(--accent-teal);
        transform: translateY(-5px);
    }
    
    .contact-icon {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--accent-teal), #20b2aa);
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1.5rem;
        font-size: 1.5rem;
        color: white;
    }
    
    .contact-title {
        font-size: 1.2rem;
        font-weight: 700;
        margin-bottom: 1rem;
        color: var(--text-white);
    }
    
    .contact-text {
        color: var(--text-light);
        line-height: 1.6;
    }
    
    /* ==================== RESPONSIVE ==================== */
    @media (max-width: 768px) {
        .faq-hero {
            padding: 3rem 1rem;
        }
        
        .faq-container {
            padding: 0 1rem 3rem;
        }
        
        .faq-question {
            padding: 1.2rem 1.5rem;
        }
        
        .question-text {
            font-size: 1rem;
        }
        
        .answer-content {
            padding: 0 1.5rem 1.2rem 1.5rem;
        }
        
        .question-form {
            padding: 1.5rem;
        }
        
        .form-grid {
            grid-template-columns: 1fr;
        }
        
        .hero-stats {
            gap: 1rem;
        }
        
        .stat-value {
            font-size: 1.5rem;
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
            font-size: 1.5rem;
        }
        
        .categories-grid {
            grid-template-columns: 1fr;
        }
        
        .question-number {
            display: none;
        }
        
        .useful-buttons {
            flex-direction: column;
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
        border-top-color: var(--accent-teal);
        animation: rotate 1s ease-in-out infinite;
    }
    </style>
</head>
<body>
    <?php include "includes/header.php"; ?>
    
    <!-- Hero Section -->
    <section class="faq-hero">
        <div class="container">
            <h1 class="hero-title">
                <i class="fas fa-question-circle"></i>
                Foire Aux Questions
            </h1>
            <p class="hero-subtitle">
                Trouvez rapidement des réponses à vos questions sur les modules Épreuves et JET de l'Institut Supérieur Saint Paul Tarse
            </p>
            
            <div class="hero-stats">
                <div class="stat-item">
                    <span class="stat-value">17</span>
                    <span class="stat-label">Questions</span>
                </div>
                <div class="stat-item">
                    <span class="stat-value"><?= count($faq_categories) ?></span>
                    <span class="stat-label">Catégories</span>
                </div>
                <div class="stat-item">
                    <span class="stat-value">24/7</span>
                    <span class="stat-label">Support</span>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Main Content -->
    <div class="faq-container">
        <!-- Recherche -->
        <div class="search-section scroll-animate">
            <div class="search-container">
                <i class="fas fa-search search-icon"></i>
                <input type="text" 
                       id="faqSearch" 
                       class="search-input" 
                       placeholder="Rechercher une question...">
            </div>
        </div>
        
        <!-- Catégories -->
        <div class="categories-section scroll-animate">
            <h2 style="text-align: center; margin-bottom: 1rem; color: var(--text-white);">
                Catégories principales
            </h2>
            <p style="text-align: center; color: var(--text-muted); margin-bottom: 2rem;">
                Sélectionnez une catégorie pour explorer les questions
            </p>
            
            <div class="categories-grid">
                <?php foreach ($faq_categories as $category): ?>
                <div class="category-card" data-category="<?= $category['id'] ?>">
                    <div class="category-icon" style="background: <?= $category['color'] ?>20; color: <?= $category['color'] ?>;">
                        <?php if ($category['id'] == 1): ?>
                            <i class="fas fa-file-pdf"></i>
                        <?php elseif ($category['id'] == 2): ?>
                            <i class="fas fa-calendar-alt"></i>
                        <?php elseif ($category['id'] == 3): ?>
                            <i class="fas fa-user-circle"></i>
                        <?php else: ?>
                            <i class="fas fa-info-circle"></i>
                        <?php endif; ?>
                    </div>
                    <h3 class="category-title"><?= htmlspecialchars($category['name']) ?></h3>
                    <span class="category-count">
                        <?= count($faq_by_category[$category['id']] ?? []) ?> questions
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- FAQ Accordéon -->
        <div class="faq-section">
            <?php foreach ($faq_categories as $category): 
                if (!empty($faq_by_category[$category['id']])): ?>
            <div class="faq-category" id="category-<?= $category['id'] ?>">
                <div class="section-header scroll-animate">
                    <div class="section-icon" style="background: <?= $category['color'] ?>20; color: <?= $category['color'] ?>;">
                        <?php if ($category['id'] == 1): ?>
                            <i class="fas fa-file-pdf"></i>
                        <?php elseif ($category['id'] == 2): ?>
                            <i class="fas fa-calendar-alt"></i>
                        <?php elseif ($category['id'] == 3): ?>
                            <i class="fas fa-user-circle"></i>
                        <?php else: ?>
                            <i class="fas fa-info-circle"></i>
                        <?php endif; ?>
                    </div>
                    <h2 class="section-title"><?= htmlspecialchars($category['name']) ?></h2>
                </div>
                
                <div class="faq-accordion scroll-animate">
                    <?php foreach ($faq_by_category[$category['id']] as $index => $item): ?>
                    <div class="faq-item" data-id="<?= $item['id'] ?>">
                        <div class="faq-question">
                            <div class="question-number"><?= $index + 1 ?></div>
                            <div class="question-text"><?= htmlspecialchars($item['question']) ?></div>
                            <div class="toggle-icon">
                                <i class="fas fa-chevron-down"></i>
                            </div>
                        </div>
                        <div class="faq-answer">
                            <div class="answer-content">
                                <?= nl2br(htmlspecialchars($item['answer'])) ?>
                                <div class="useful-buttons">
                                    <button class="btn-useful" data-action="helpful">
                                        <i class="fas fa-thumbs-up"></i> Utile
                                    </button>
                                    <button class="btn-useful" data-action="unhelpful">
                                        <i class="fas fa-thumbs-down"></i> Pas utile
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; endforeach; ?>
        </div>
        
        <!-- Formulaire de question -->
        <div class="question-form-section scroll-animate">
            <div class="question-form">
                <div class="form-header">
                    <div class="form-icon">
                        <i class="fas fa-question"></i>
                    </div>
                    <h2 class="form-title">Vous ne trouvez pas votre réponse ?</h2>
                    <p class="form-subtitle">Contacter nous et nous vous répondrons dans les plus brefs délais</p>
                </div>
                
                <?php if ($question_sent): ?>
                    <div class="message-container">
                        <div class="success-message">
                            <i class="fas fa-check-circle"></i>
                            <div>
                                <h3>Question envoyée !</h3>
                                <p><?= htmlspecialchars($question_success) ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($question_errors)): ?>
                    <div class="message-container">
                        <div class="error-message">
                            <h3><i class="fas fa-exclamation-triangle"></i> Veuillez corriger les erreurs suivantes :</h3>
                            <ul>
                                <?php foreach ($question_errors as $error): ?>
                                    <li><?= htmlspecialchars($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" id="questionForm">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="name" class="form-label">
                                <i class="fas fa-user"></i> Votre nom *
                            </label>
                            <input type="text" 
                                   id="name" 
                                   name="name" 
                                   class="form-input" 
                                   placeholder="Votre nom complet"
                                   value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                                   required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email" class="form-label">
                                <i class="fas fa-envelope"></i> Email *
                            </label>
                            <input type="email" 
                                   id="email" 
                                   name="email" 
                                   class="form-input" 
                                   placeholder="votre@email.com"
                                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                   required>
                        </div>
                        
                        <div class="form-group form-full">
                            <label for="category" class="form-label">
                                <i class="fas fa-tag"></i> Catégorie *
                            </label>
                            <select id="category" name="category" class="form-select" required>
                                <option value="">Sélectionnez une catégorie</option>
                                <?php foreach ($faq_categories as $category): ?>
                                    <option value="<?= $category['id'] ?>" 
                                            <?= ($_POST['category'] ?? '') == $category['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($category['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group form-full">
                            <label for="question" class="form-label">
                                <i class="fas fa-comment-dots"></i> Votre question *
                            </label>
                            <textarea id="question" 
                                      name="question" 
                                      class="form-textarea" 
                                      placeholder="Décrivez votre question en détail..."
                                      required><?= htmlspecialchars($_POST['question'] ?? '') ?></textarea>
                        </div>
                    </div>
                    
                    <button type="submit" name="submit_question" class="btn-submit" id="submitQuestionBtn">
                        <i class="fas fa-paper-plane"></i>
                        <span id="btnText">Envoyer ma question</span>
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Contact Info -->
        <div class="contact-info scroll-animate">
            <div class="contact-card">
                <div class="contact-icon">
                    <i class="fas fa-phone"></i>
                </div>
                <h3 class="contact-title">Support téléphonique</h3>
                <p class="contact-text">
                    Lundi - Vendredi: 8h - 17h<br>
                    Samedi: 9h - 13h<br>
                    <strong>+228 XX XX XX XX</strong>
                </p>
            </div>
            
            <div class="contact-card">
                <div class="contact-icon">
                    <i class="fas fa-envelope"></i>
                </div>
                <h3 class="contact-title">Email de support</h3>
                <p class="contact-text">
                    <strong>Épreuves:</strong> epreuves@isspt.tg<br>
                    <strong>JET:</strong> jet@isspt.tg<br>
                    <strong>Général:</strong> support@isspt.tg
                </p>
            </div>
            
            <div class="contact-card">
                <div class="contact-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <h3 class="contact-title">Temps de réponse</h3>
                <p class="contact-text">
                    Nous nous engageons à répondre dans les <strong>24-48 heures</strong>.<br>
                    Pour les urgences, privilégiez l'appel téléphonique.
                </p>
            </div>
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
    
    // Accordéon FAQ
    document.querySelectorAll('.faq-question').forEach((question) => {
        question.addEventListener('click', function() {
            const item = this.closest('.faq-item');
            const isActive = item.classList.contains('active');
            
            // Fermer tous les autres items
            document.querySelectorAll('.faq-item').forEach((otherItem) => {
                if (otherItem !== item) {
                    otherItem.classList.remove('active');
                }
            });
            
            // Ouvrir/fermer l'item actuel
            item.classList.toggle('active', !isActive);
            
            // Animation de l'icône
            const icon = this.querySelector('.toggle-icon i');
            icon.style.transform = isActive ? 'rotate(0deg)' : 'rotate(180deg)';
        });
    });
    
    // Recherche dans la FAQ
    const searchInput = document.getElementById('faqSearch');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase().trim();
            const items = document.querySelectorAll('.faq-item');
            
            items.forEach((item) => {
                const question = item.querySelector('.question-text').textContent.toLowerCase();
                const answer = item.querySelector('.answer-content').textContent.toLowerCase();
                
                const matches = question.includes(searchTerm) || answer.includes(searchTerm);
                
                if (matches) {
                    item.style.display = '';
                    // Afficher aussi la catégorie parente
                    const category = item.closest('.faq-category');
                    if (category) {
                        category.style.display = '';
                    }
                } else {
                    item.style.display = 'none';
                }
                
                // Cacher les catégories vides
                document.querySelectorAll('.faq-category').forEach((category) => {
                    const visibleItems = category.querySelectorAll('.faq-item[style=""]');
                    if (visibleItems.length === 0) {
                        category.style.display = 'none';
                    } else {
                        category.style.display = '';
                    }
                });
            });
        });
    }
    
    // Navigation par catégorie
    document.querySelectorAll('.category-card').forEach((card) => {
        card.addEventListener('click', function() {
            const categoryId = this.getAttribute('data-category');
            const targetSection = document.getElementById(`category-${categoryId}`);
            
            if (targetSection) {
                // Fermer tous les accordéons
                document.querySelectorAll('.faq-item').forEach((item) => {
                    item.classList.remove('active');
                });
                
                // Scroll vers la section
                targetSection.scrollIntoView({ behavior: 'smooth' });
                
                // Animation de la section
                targetSection.style.animation = 'none';
                setTimeout(() => {
                    targetSection.style.animation = 'slideInUp 0.5s ease-out';
                }, 10);
            }
        });
    });
    
    // Boutons Utile/Pas utile
    document.querySelectorAll('.btn-useful').forEach((button) => {
        button.addEventListener('click', function() {
            const action = this.getAttribute('data-action');
            const questionId = this.closest('.faq-item').getAttribute('data-id');
            const isHelpful = action === 'helpful';
            
            // Animation du bouton
            this.style.transform = 'scale(0.95)';
            this.style.background = isHelpful 
                ? 'rgba(32, 201, 151, 0.2)' 
                : 'rgba(186, 40, 30, 0.2)';
            this.style.borderColor = isHelpful 
                ? 'var(--accent-teal)' 
                : 'var(--accent-red)';
            this.style.color = isHelpful 
                ? 'var(--accent-teal)' 
                : 'var(--accent-red)';
            
            // Réinitialiser après animation
            setTimeout(() => {
                this.style.transform = '';
            }, 200);
            
            // Envoyer les données au serveur (simulé ici)
            console.log(`Question ${questionId} marquée comme ${action}`);
            
            // Afficher un message de remerciement
            const message = isHelpful 
                ? 'Merci pour votre retour ! Cette réponse vous a été utile.' 
                : 'Merci pour votre retour. Nous améliorerons cette réponse.';
            
            // Petite notification
            const notification = document.createElement('div');
            notification.style.cssText = `
                position: fixed;
                bottom: 20px;
                right: 20px;
                background: ${isHelpful ? 'var(--accent-teal)' : 'var(--accent-red)'};
                color: white;
                padding: 1rem 1.5rem;
                border-radius: 8px;
                box-shadow: 0 4px 15px rgba(0,0,0,0.2);
                z-index: 1000;
                animation: slideInUp 0.3s ease-out;
            `;
            notification.innerHTML = `<i class="fas fa-${isHelpful ? 'thumbs-up' : 'thumbs-down'}"></i> ${message}`;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.animation = 'fadeOut 0.3s ease-out forwards';
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        });
    });
    
    // Validation du formulaire de question
    const questionForm = document.getElementById('questionForm');
    if (questionForm) {
        questionForm.addEventListener('submit', function(e) {
            const email = this.querySelector('input[name="email"]').value;
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            
            if (!emailRegex.test(email)) {
                e.preventDefault();
                alert('Veuillez entrer une adresse email valide.');
                return;
            }
            
            const submitBtn = document.getElementById('submitQuestionBtn');
            const btnText = document.getElementById('btnText');
            
            btnText.innerHTML = '<span class="loading"></span> Envoi en cours...';
            submitBtn.disabled = true;
        });
    }
    
    // Animation des catégories
    document.querySelectorAll('.category-card').forEach((card, index) => {
        card.style.animationDelay = `${index * 0.1}s`;
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        card.style.transition = 'all 0.6s ease-out';
        
        setTimeout(() => {
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, 100 + (index * 100));
    });
    
    // Animation de chargement de la page
    window.addEventListener('load', function() {
        document.body.style.opacity = '0';
        document.body.style.transition = 'opacity 0.5s ease-in';
        
        setTimeout(() => {
            document.body.style.opacity = '1';
        }, 100);
        
        // Ajouter une animation pour les icônes
        const icons = document.querySelectorAll('.section-icon, .category-icon');
        icons.forEach((icon, index) => {
            setTimeout(() => {
                icon.style.transform = 'scale(0) rotate(-180deg)';
                icon.style.transition = 'all 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55)';
                
                setTimeout(() => {
                    icon.style.transform = 'scale(1) rotate(0)';
                }, 50);
            }, index * 100);
        });
    });
    
    // Ajouter une animation de fadeOut pour les notifications
    const style = document.createElement('style');
    style.textContent = `
        @keyframes fadeOut {
            from { opacity: 1; transform: translateY(0); }
            to { opacity: 0; transform: translateY(20px); }
        }
    `;
    document.head.appendChild(style);
    
    // Fonction pour ouvrir une question spécifique
    function openQuestion(questionId) {
        const questionItem = document.querySelector(`.faq-item[data-id="${questionId}"]`);
        if (questionItem) {
            // Fermer tous les autres
            document.querySelectorAll('.faq-item').forEach((item) => {
                item.classList.remove('active');
            });
            
            // Ouvrir la question cible
            questionItem.classList.add('active');
            
            // Scroll vers la question
            questionItem.scrollIntoView({ behavior: 'smooth', block: 'center' });
            
            // Animation
            questionItem.style.background = 'rgba(32, 201, 151, 0.1)';
            setTimeout(() => {
                questionItem.style.background = '';
            }, 1000);
        }
    }
    
    // Si URL contient un hash, ouvrir la question correspondante
    if (window.location.hash) {
        const questionId = window.location.hash.substring(1);
        setTimeout(() => openQuestion(questionId), 500);
    }
    </script>
</body>
</html>