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
$user_id = null;
$user_type = null;
$user_data = null;
$is_admin = false;
$is_etudiant = false;
$admin_has_student = false;

if (isset($_SESSION['admin_id'])) {
    $user_id = $_SESSION['admin_id'];
    $user_type = 'admin';
    $is_admin = true;
} elseif (isset($_SESSION['etudiant_id'])) {
    $user_id = $_SESSION['etudiant_id'];
    $user_type = 'etudiant';
    $is_etudiant = true;
} else {
    header("Location: ../etudiant/login_etudiant.php");
    exit;
}

// Récupérer les informations de l'utilisateur
if ($user_type === 'admin') {
    $query = "SELECT a.*, e.* 
              FROM administrateurs a 
              LEFT JOIN etudiants e ON a.id_etudiant = e.id_etudiant 
              WHERE a.id_admin = :id";
    $stmt = $pdo->prepare($query);
    $stmt->execute([':id' => $user_id]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Si l'admin est lié à un étudiant, compléter les informations
    if ($user_data['id_etudiant']) {
        $is_etudiant = true;
        $admin_has_student = true;
        $user_data['matricule'] = $user_data['matricule'] ?? '';
        $user_data['promotion'] = $user_data['promotion'] ?? '';
        $user_data['filiere'] = $user_data['filiere'] ?? '';
        $user_data['photo'] = $user_data['photo'] ?? '';
    }
} else {
    $query = "SELECT * FROM etudiants WHERE id_etudiant = :id";
    $stmt = $pdo->prepare($query);
    $stmt->execute([':id' => $user_id]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Initialiser les messages
$success_message = '';
$error_message = '';
$password_error = '';

// Traitement de la mise à jour des informations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    
    // CAS 1: Utilisateur est un étudiant (connecté en tant qu'étudiant)
    if ($user_type === 'etudiant') {
        // Mise à jour étudiant
        $promotion = trim($_POST['promotion'] ?? '');
        $filiere = trim($_POST['filiere'] ?? '');
        
        $query = "UPDATE etudiants 
                  SET nom = :nom, prenom = :prenom, email = :email, 
                      telephone = :telephone, promotion = :promotion, filiere = :filiere 
                  WHERE id_etudiant = :id";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([
            ':nom' => $nom,
            ':prenom' => $prenom,
            ':email' => $email,
            ':telephone' => $telephone,
            ':promotion' => $promotion,
            ':filiere' => $filiere,
            ':id' => $user_id
        ]);
        
        $success_message = 'Profil étudiant mis à jour avec succès !';
        
    // CAS 2: Utilisateur est un administrateur
    } elseif ($user_type === 'admin') {
        // CAS 2A: Admin lié à un étudiant (membre du bureau étudiant)
        if ($admin_has_student) {
            // Mise à jour dans la table etudiants
            $promotion = trim($_POST['promotion'] ?? '');
            $filiere = trim($_POST['filiere'] ?? '');
            
            $query = "UPDATE etudiants 
                      SET nom = :nom, prenom = :prenom, email = :email, 
                          telephone = :telephone, promotion = :promotion, filiere = :filiere 
                      WHERE id_etudiant = :id_etudiant";
            
            $stmt = $pdo->prepare($query);
            $stmt->execute([
                ':nom' => $nom,
                ':prenom' => $prenom,
                ':email' => $email,
                ':telephone' => $telephone,
                ':promotion' => $promotion,
                ':filiere' => $filiere,
                ':id_etudiant' => $user_data['id_etudiant']
            ]);
            
            // Mettre à jour également l'email dans la table administrateurs pour la cohérence
            $query_admin = "UPDATE administrateurs 
                           SET email = :email 
                           WHERE id_admin = :id_admin";
            $stmt_admin = $pdo->prepare($query_admin);
            $stmt_admin->execute([
                ':email' => $email,
                ':id_admin' => $user_id
            ]);
            
            $success_message = 'Profil (étudiant et administrateur) mis à jour avec succès !';
            
        // CAS 2B: Admin non lié à un étudiant (admin classique)
        } else {
            // Mise à jour admin
            $query = "UPDATE administrateurs 
                      SET nom = :nom, prenom = :prenom, email = :email 
                      WHERE id_admin = :id";
            
            $stmt = $pdo->prepare($query);
            $stmt->execute([
                ':nom' => $nom,
                ':prenom' => $prenom,
                ':email' => $email,
                ':id' => $user_id
            ]);
            
            $success_message = 'Profil administrateur mis à jour avec succès !';
        }
    }
    
    // Rafraîchir les données après la mise à jour
    if ($user_type === 'admin') {
        $query = "SELECT a.*, e.* 
                  FROM administrateurs a 
                  LEFT JOIN etudiants e ON a.id_etudiant = e.id_etudiant 
                  WHERE a.id_admin = :id";
        $stmt = $pdo->prepare($query);
        $stmt->execute([':id' => $user_id]);
        $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user_data['id_etudiant']) {
            $is_etudiant = true;
            $admin_has_student = true;
        }
    } else {
        $stmt = $pdo->prepare("SELECT * FROM etudiants WHERE id_etudiant = :id");
        $stmt->execute([':id' => $user_id]);
        $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user_data) {
        $isLogged = true;
        $userName = $user_data['prenom'] . ' ' . $user_data['nom'];
        if (!empty($user_data['photo'])){

         $userAvatar = '../uploads/photos_etudiants/' . $user_data['photo'];
        } else {
         $userAvatar = 'assets/images/default-avatar.png';
        }
    } else {
        // Déconnecter si l'étudiant n'existe pas ou est inactif
        session_unset();
        session_destroy();
        header("Location: login.php");
        exit;
    }
    }
}

// Traitement de la mise à jour du mot de passe
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Vérifier que le nouveau mot de passe correspond
    if ($new_password !== $confirm_password) {
        $password_error = 'Les nouveaux mots de passe ne correspondent pas.';
    } else {
        // Déterminer dans quelle table vérifier le mot de passe actuel
        if ($user_type === 'etudiant') {
            // CAS 1: Étudiant
            $query = "SELECT mot_de_passe FROM etudiants WHERE id_etudiant = :id";
            $update_query = "UPDATE etudiants SET mot_de_passe = :password WHERE id_etudiant = :id";
            $id = $user_id;
        } elseif ($user_type === 'admin') {
            if ($admin_has_student) {
                // CAS 2A: Admin lié à un étudiant - on modifie le mot de passe de l'étudiant
                $query = "SELECT mot_de_passe FROM etudiants WHERE id_etudiant = :id";
                $update_query = "UPDATE etudiants SET mot_de_passe = :password WHERE id_etudiant = :id";
                $id = $user_data['id_etudiant'];
            } else {
                // CAS 2B: Admin non lié à un étudiant
                $query = "SELECT mot_de_passe FROM administrateurs WHERE id_admin = :id";
                $update_query = "UPDATE administrateurs SET mot_de_passe = :password WHERE id_admin = :id";
                $id = $user_id;
            }
        }
        
        // Vérifier l'ancien mot de passe
        $stmt = $pdo->prepare($query);
        $stmt->execute([':id' => $id]);
        $db_password = $stmt->fetchColumn();
        
        if (password_verify($current_password, $db_password)) {
            // Mettre à jour le mot de passe
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare($update_query);
            $stmt->execute([
                ':password' => $hashed_password,
                ':id' => $id
            ]);
            
            // Si admin lié à étudiant, mettre à jour aussi le mot de passe admin pour cohérence
            if ($user_type === 'admin' && $admin_has_student) {
                $query_admin = "UPDATE administrateurs SET mot_de_passe = :password WHERE id_admin = :id";
                $stmt_admin = $pdo->prepare($query_admin);
                $stmt_admin->execute([
                    ':password' => $hashed_password,
                    ':id' => $user_id
                ]);
            }
            
            $success_message = 'Mot de passe mis à jour avec succès !';
        } else {
            $password_error = 'Mot de passe actuel incorrect.';
        }
    }
}

// Traitement de la photo de profil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_photo'])) {
    // Vérifier si l'utilisateur peut avoir une photo (étudiant ou admin lié à étudiant)
    if ($is_etudiant) {
        $target_dir = "../uploads/profiles/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $file_name = time() . '_' . basename($_FILES['profile_photo']['name']);
        $target_file = $target_dir . $file_name;
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
        
        // Vérifier si c'est une image
        $check = getimagesize($_FILES['profile_photo']['tmp_name']);
        if ($check !== false) {
            // Limiter la taille (2MB max)
            if ($_FILES['profile_photo']['size'] <= 2000000) {
                // Autoriser certains formats
                if (in_array($imageFileType, ['jpg', 'jpeg', 'png', 'gif'])) {
                    if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $target_file)) {
                        // Déterminer l'ID de l'étudiant à mettre à jour
                        if ($user_type === 'etudiant') {
                            $student_id = $user_id;
                        } elseif ($user_type === 'admin' && $admin_has_student) {
                            $student_id = $user_data['id_etudiant'];
                        }
                        
                        // Supprimer l'ancienne photo si elle existe
                        if (!empty($user_data['photo']) && file_exists("../" . $user_data['photo'])) {
                            unlink("../" . $user_data['photo']);
                        }
                        
                        // Mettre à jour la base de données
                        $query = "UPDATE etudiants SET photo = :photo WHERE id_etudiant = :id";
                        $stmt = $pdo->prepare($query);
                        $stmt->execute([
                            ':photo' => "uploads/profiles/" . $file_name,
                            ':id' => $student_id
                        ]);
                        
                        $user_data['photo'] = "uploads/profiles/" . $file_name;
                        $success_message = 'Photo de profil mise à jour avec succès !';
                    } else {
                        $error_message = 'Erreur lors du téléchargement de l\'image.';
                    }
                } else {
                    $error_message = 'Seuls les formats JPG, JPEG, PNG et GIF sont autorisés.';
                }
            } else {
                $error_message = 'L\'image est trop volumineuse (max 2MB).';
            }
        } else {
            $error_message = 'Le fichier n\'est pas une image valide.';
        }
    } else {
        $error_message = 'Seuls les étudiants peuvent avoir une photo de profil.';
    }
}
?>


<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>👤 Mon Profil - Recueil d'Épreuves Universitaires</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
/* ==========================================================================
   PAGE DE PROFIL PREMIUM
   Design élégant avec gestion complète des informations
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
    0%, 100% { transform: translateY(0) scale(1); }
    50% { transform: translateY(-10px) scale(1.05); }
}

@keyframes pulse {
    0%, 100% { box-shadow: 0 0 0 0 rgba(32, 201, 151, 0.7); }
    70% { box-shadow: 0 0 0 10px rgba(32, 201, 151, 0); }
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
.profile-hero {
    padding: 4rem 2rem;
    text-align: center;
    position: relative;
    overflow: hidden;
}

.profile-hero::before {
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
    margin-bottom: 1rem;
    background: linear-gradient(135deg, var(--text-white) 0%, var(--accent-gold) 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    animation: slideInUp 1s ease-out;
}

.hero-subtitle {
    font-size: 1.2rem;
    color: var(--text-light);
    opacity: 0;
    animation: slideInUp 1s ease-out 0.3s forwards;
}

/* ==================== MAIN CONTENT ==================== */
.profile-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 2rem 4rem;
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
    animation: pulse 2s infinite;
}

.success-message i {
    font-size: 1.5rem;
}

.error-message {
    background: rgba(186, 40, 30, 0.2);
    border: 1px solid rgba(186, 40, 30, 0.3);
    color: var(--accent-red);
    padding: 1.5rem;
    border-radius: 12px;
    display: flex;
    align-items: center;
    gap: 1rem;
}

/* Grid Layout */
.profile-grid {
    display: grid;
    grid-template-columns: 350px 1fr;
    gap: 2rem;
}

@media (max-width: 992px) {
    .profile-grid {
        grid-template-columns: 1fr;
    }
}

/* ==================== PROFILE SIDEBAR ==================== */
.profile-sidebar {
    opacity: 0;
    animation: slideInUp 0.8s ease-out 0.4s forwards;
}

.profile-card {
    background: var(--glass-bg);
    backdrop-filter: blur(20px);
    border: 1px solid var(--glass-border);
    border-radius: 20px;
    padding: 2rem;
    box-shadow: var(--glass-shadow);
    transition: var(--transition-smooth);
    text-align: center;
    position: sticky;
    top: 2rem;
}

.profile-card:hover {
    border-color: var(--accent-red);
    transform: translateY(-5px);
}

/* Photo de profil */
.profile-photo-container {
    position: relative;
    width: 150px;
    height: 150px;
    margin: 0 auto 1.5rem;
}

.profile-photo {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
    border: 4px solid var(--accent-teal);
    transition: var(--transition-smooth);
}

.profile-photo:hover {
    border-color: var(--accent-red);
    transform: scale(1.05);
}

.photo-overlay {
    position: absolute;
    bottom: 0;
    right: 0;
    background: var(--accent-teal);
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: var(--transition-smooth);
}

.photo-overlay:hover {
    background: var(--accent-red);
    transform: scale(1.1);
}

/* Info utilisateur */
.user-name {
    font-size: 1.8rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
    color: var(--text-white);
}

.user-email {
    color: var(--accent-gold);
    font-size: 0.95rem;
    margin-bottom: 1.5rem;
    word-break: break-word;
}

.user-role {
    display: inline-block;
    padding: 0.3rem 1rem;
    background: linear-gradient(135deg, var(--accent-red) 0%, #c53030 100%);
    color: white;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 600;
    margin-bottom: 2rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Stats */
.user-stats {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
    margin-top: 2rem;
    padding-top: 2rem;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.stat-item {
    text-align: center;
}

.stat-value {
    display: block;
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--accent-teal);
}

.stat-label {
    font-size: 0.85rem;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* ==================== PROFILE CONTENT ==================== */
.profile-content {
    opacity: 0;
    animation: slideInUp 0.8s ease-out 0.6s forwards;
}

/* Tabs Navigation */
.profile-tabs {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 2rem;
    background: var(--glass-bg);
    backdrop-filter: blur(10px);
    border: 1px solid var(--glass-border);
    border-radius: 12px;
    padding: 0.5rem;
}

.tab-btn {
    flex: 1;
    padding: 1rem;
    background: transparent;
    border: none;
    color: var(--text-muted);
    font-family: 'Montserrat', sans-serif;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    border-radius: 8px;
    transition: var(--transition-smooth);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.tab-btn.active {
    background: rgba(32, 201, 151, 0.2);
    color: var(--accent-teal);
    border: 1px solid rgba(32, 201, 151, 0.3);
}

.tab-btn:hover:not(.active) {
    background: rgba(255, 255, 255, 0.05);
    color: var(--text-light);
}

.tab-btn i {
    font-size: 1.2rem;
}

/* Tab Content */
.tab-content {
    display: none;
    animation: fadeIn 0.5s ease-out;
}

.tab-content.active {
    display: block;
}

/* Forms */
.profile-form {
    background: var(--glass-bg);
    backdrop-filter: blur(20px);
    border: 1px solid var(--glass-border);
    border-radius: 20px;
    padding: 2rem;
    box-shadow: var(--glass-shadow);
    transition: var(--transition-smooth);
}

.profile-form:hover {
    border-color: var(--accent-teal);
}

.form-section {
    margin-bottom: 2.5rem;
}

.form-section:last-child {
    margin-bottom: 0;
}

.section-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.section-icon {
    width: 50px;
    height: 50px;
    background: rgba(186, 40, 30, 0.2);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: var(--accent-red);
}

.section-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--text-white);
}

.section-subtitle {
    color: var(--text-muted);
    font-size: 0.95rem;
}

/* Form Grid */
.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
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

.form-label i {
    margin-right: 0.5rem;
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

.form-input:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.input-with-icon {
    position: relative;
}

.input-with-icon i {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--accent-teal);
    font-size: 1.2rem;
}

.input-with-icon input {
    padding-left: 3rem;
}

/* Password Strength Indicator */
.password-strength {
    margin-top: 0.5rem;
    height: 4px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 2px;
    overflow: hidden;
}

.strength-bar {
    height: 100%;
    width: 0%;
    background: var(--accent-red);
    border-radius: 2px;
    transition: var(--transition-smooth);
}

.strength-bar.weak { width: 33%; background: var(--accent-red); }
.strength-bar.medium { width: 66%; background: #FFA500; }
.strength-bar.strong { width: 100%; background: var(--accent-teal); }

.strength-text {
    font-size: 0.8rem;
    color: var(--text-muted);
    margin-top: 0.3rem;
    text-align: right;
}

/* Buttons */
.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
    margin-top: 2rem;
    padding-top: 2rem;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
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
}

.btn-primary {
    background: linear-gradient(135deg, var(--accent-red) 0%, #c53030 100%);
    color: white;
}

.btn-primary:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 30px rgba(186, 40, 30, 0.4);
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

.btn-delete {
    background: transparent;
    border: 2px solid var(--accent-red);
    color: var(--accent-red);
}

.btn-delete:hover {
    background: var(--accent-red);
    color: white;
    transform: translateY(-3px);
}

/* Modal Photo */
.photo-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.9);
    backdrop-filter: blur(10px);
    z-index: 1000;
    align-items: center;
    justify-content: center;
    animation: fadeIn 0.3s ease-out;
}

.photo-modal-content {
    background: var(--glass-bg);
    backdrop-filter: blur(30px);
    border: 1px solid var(--glass-border);
    border-radius: 20px;
    padding: 2rem;
    max-width: 400px;
    width: 90%;
    animation: slideInUp 0.4s ease-out;
}

.photo-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}

.photo-modal-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--text-white);
}

.photo-modal-close {
    background: none;
    border: none;
    color: var(--text-muted);
    font-size: 1.5rem;
    cursor: pointer;
    transition: var(--transition-smooth);
}

.photo-modal-close:hover {
    color: var(--accent-red);
    transform: rotate(90deg);
}

/* Badges pour les rôles */
.role-badges {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-top: 1rem;
}

.role-badge {
    padding: 0.3rem 0.8rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.badge-admin {
    background: rgba(186, 40, 30, 0.2);
    color: var(--accent-red);
    border: 1px solid rgba(186, 40, 30, 0.3);
}

.badge-etudiant {
    background: rgba(32, 201, 151, 0.2);
    color: var(--accent-teal);
    border: 1px solid rgba(32, 201, 151, 0.3);
}

.badge-super-admin {
    background: rgba(255, 215, 0, 0.2);
    color: var(--accent-gold);
    border: 1px solid rgba(255, 215, 0, 0.3);
}

/* ==================== RESPONSIVE ==================== */
@media (max-width: 768px) {
    .profile-hero {
        padding: 2rem 1rem;
    }
    
    .profile-container {
        padding: 0 1rem 2rem;
    }
    
    .profile-tabs {
        flex-direction: column;
    }
    
    .form-grid {
        grid-template-columns: 1fr;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .btn {
        width: 100%;
        justify-content: center;
    }
    
    .profile-card {
        position: static;
    }
}

@media (max-width: 480px) {
    .hero-title {
        font-size: 2rem;
    }
    
    .hero-subtitle {
        font-size: 1rem;
    }
    
    .profile-photo-container {
        width: 120px;
        height: 120px;
    }
    
    .user-stats {
        grid-template-columns: 1fr;
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

/* ==================== LOADING STATES ==================== */
.loading {
    display: inline-block;
    width: 20px;
    height: 20px;
    border: 2px solid rgba(255, 255, 255, 0.3);
    border-radius: 50%;
    border-top-color: var(--accent-teal);
    animation: rotate 1s ease-in-out infinite;
}

/* Ripple effect */
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
    <section class="profile-hero">
        <div class="container">
            <h1 class="hero-title">
                <i class="fas fa-user-circle"></i>
                Mon Profil
            </h1>
            <p class="hero-subtitle">
                Gérez vos informations personnelles et votre mot de passe
            </p>
        </div>
    </section>
    
    <!-- Messages -->
    <div class="profile-container">
        <?php if ($success_message): ?>
            <div class="message-container">
                <div class="success-message">
                    <i class="fas fa-check-circle"></i>
                    <span><?= htmlspecialchars($success_message) ?></span>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="message-container">
                <div class="error-message">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span><?= htmlspecialchars($error_message) ?></span>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="profile-grid">
            <!-- Sidebar avec informations -->
            <div class="profile-sidebar scroll-animate">
                <div class="profile-card">
                    <!-- Photo de profil -->
                    <div class="profile-photo-container">
                        <?php if ($is_etudiant && !empty($user_data['photo'])): ?>
                            <img src="<?= $userAvatar ?>" 
                                 alt="Photo de profil" 
                                 class="profile-photo"
                                 id="profilePhoto">
                        <?php else: ?>
                            <img src="https://ui-avatars.com/api/?name=<?= urlencode($user_data['prenom'] . '+' . $user_data['nom']) ?>&background=BA281E&color=fff&size=150" 
                                 alt="Photo de profil" 
                                 class="profile-photo"
                                 id="profilePhoto">
                        <?php endif; ?>
                        
                        <?php if ($is_etudiant): ?>
                            <div class="photo-overlay" id="changePhotoBtn">
                                <i class="fas fa-camera"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Nom et email -->
                    <h2 class="user-name">
                        <?= htmlspecialchars($user_data['prenom'] . ' ' . $user_data['nom']) ?>
                    </h2>
                    <p class="user-email"><?= htmlspecialchars($user_data['email']) ?></p>
                    
                    <!-- Badges de rôles -->
                    <div class="role-badges">
                        <?php if ($is_admin): ?>
                            <span class="role-badge badge-admin">
                                <i class="fas fa-user-shield"></i> Admin
                            </span>
                            <?php if ($user_data['role'] === 'super_admin'): ?>
                                <span class="role-badge badge-super-admin">
                                    <i class="fas fa-crown"></i> Super Admin
                                </span>
                            <?php endif; ?>
                            <?php if ($user_data['poste_bureau']): ?>
                                <span class="role-badge badge-admin">
                                    <i class="fas fa-users"></i> <?= htmlspecialchars($user_data['poste_bureau']) ?>
                                </span>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php if ($is_etudiant): ?>
                            <span class="role-badge badge-etudiant">
                                <i class="fas fa-graduation-cap"></i> Étudiant
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Informations supplémentaires pour les étudiants -->
                    <?php if ($is_etudiant): ?>
                        <div class="user-info">
                            <?php if (!empty($user_data['matricule'])): ?>
                                <p style="margin: 0.5rem 0; color: var(--text-light);">
                                    <i class="fas fa-id-card"></i> 
                                    <strong>Matricule:</strong> <?= htmlspecialchars($user_data['matricule']) ?>
                                </p>
                            <?php endif; ?>
                            
                            <?php if (!empty($user_data['filiere'])): ?>
                                <p style="margin: 0.5rem 0; color: var(--text-light);">
                                    <i class="fas fa-book"></i> 
                                    <strong>Filière:</strong> <?= htmlspecialchars($user_data['filiere']) ?>
                                </p>
                            <?php endif; ?>
                            
                            <?php if (!empty($user_data['promotion'])): ?>
                                <p style="margin: 0.5rem 0; color: var(--text-light);">
                                    <i class="fas fa-calendar-alt"></i> 
                                    <strong>Promotion:</strong> <?= htmlspecialchars($user_data['promotion']) ?>
                                </p>
                            <?php endif; ?>
                            
                            <?php if (!empty($user_data['telephone'])): ?>
                                <p style="margin: 0.5rem 0; color: var(--text-light);">
                                    <i class="fas fa-phone"></i> 
                                    <strong>Téléphone:</strong> <?= htmlspecialchars($user_data['telephone']) ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Stats -->
                    <div class="user-stats">
                        <!-- <div class="stat-item">
                            <span class="stat-value" id="statDays"><?= $user_data['statut'] === 'actif' ? 'Actif' : 'Inactif' ?></span>
                            <span class="stat-label">Statut</span>
                        </div> -->
                        <div class="stat-item">
                            <span class="stat-value" id="statActivity">
                                <?= date('d/m/Y', strtotime($user_data['date_inscription'] ?? $user_data['date_creation'])) ?>
                            </span>
                            <span class="stat-label">Inscription</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Contenu principal -->
            <div class="profile-content scroll-animate">
                <!-- Onglets -->
                <div class="profile-tabs">
                    <button class="tab-btn active" data-tab="info">
                        <i class="fas fa-user-edit"></i> Informations
                    </button>
                    <button class="tab-btn" data-tab="password">
                        <i class="fas fa-key"></i> Mot de passe
                    </button>
                    <button class="tab-btn" data-tab="security">
                        <i class="fas fa-shield-alt"></i> Sécurité
                    </button>
                </div>
                
                <!-- Onglet Informations -->
                <div class="tab-content active" id="info-tab">
                    <form method="POST" class="profile-form" id="infoForm">
                        <input type="hidden" name="update_profile" value="1">
                        
                        <!-- Informations de base -->
                        <div class="form-section">
                            <div class="section-header">
                                <div class="section-icon">
                                    <i class="fas fa-id-card"></i>
                                </div>
                                <div>
                                    <h3 class="section-title">Informations personnelles</h3>
                                    <p class="section-subtitle">Mettez à jour vos informations de base</p>
                                </div>
                            </div>
                            
                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label">
                                        <i class="fas fa-user"></i> Prénom *
                                    </label>
                                    <input type="text" 
                                           name="prenom" 
                                           class="form-input" 
                                           value="<?= htmlspecialchars($user_data['prenom'] ?? '') ?>" 
                                           required>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">
                                        <i class="fas fa-user"></i> Nom *
                                    </label>
                                    <input type="text" 
                                           name="nom" 
                                           class="form-input" 
                                           value="<?= htmlspecialchars($user_data['nom'] ?? '') ?>" 
                                           required>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">
                                        <i class="fas fa-envelope"></i> Email *
                                    </label>
                                    <div class="input-with-icon">
                                        <i class="fas fa-at"></i>
                                        <input type="email" 
                                               name="email" 
                                               class="form-input" 
                                               value="<?= htmlspecialchars($user_data['email'] ?? '') ?>" 
                                               required>
                                    </div>
                                </div>
                                
                                <?php if ($is_etudiant): ?>
                                    <div class="form-group">
                                        <label class="form-label">
                                            <i class="fas fa-phone"></i> Téléphone
                                        </label>
                                        <div class="input-with-icon">
                                            <i class="fas fa-phone"></i>
                                            <input type="tel" 
                                                   name="telephone" 
                                                   class="form-input" 
                                                   value="<?= htmlspecialchars($user_data['telephone'] ?? '') ?>">
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Dans la section Informations du formulaire HTML, modifiez cette partie : -->

<!-- Informations académiques (étudiants seulement) -->
<?php if ($is_etudiant): ?>
    <div class="form-section">
        <div class="section-header">
            <div class="section-icon">
                <i class="fas fa-graduation-cap"></i>
            </div>
            <div>
                <h3 class="section-title">Informations académiques</h3>
                <p class="section-subtitle">Vos informations d'étudiant</p>
            </div>
        </div>
        
        <div class="form-grid">
            <?php if ($user_type === 'etudiant' || ($user_type === 'admin' && $admin_has_student)): ?>
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-id-card"></i> Matricule
                    </label>
                    <input type="text" 
                           name="matricule" 
                           class="form-input" 
                           value="<?= htmlspecialchars($user_data['matricule'] ?? '') ?>" 
                           <?= ($user_type === 'admin' && $admin_has_student) ? 'disabled' : '' ?>>
                    <?php if ($user_type === 'admin' && $admin_has_student): ?>
                        <small style="color: var(--text-muted); font-size: 0.8rem;">
                            Contactez l'administration pour modifier votre matricule
                        </small>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($is_etudiant): ?>
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-book"></i> Filière
                    </label>
                    <select name="filiere" class="form-input">
                        <option value="">Sélectionnez une filière</option>
                        <option value="SIL" <?= ($user_data['filiere'] ?? '') == 'SIL' ? 'selected' : '' ?>>SIL</option>
                        <option value="RIT" <?= ($user_data['filiere'] ?? '') == 'RIT' ? 'selected' : '' ?>>RIT</option>
                        <option value="GIT" <?= ($user_data['filiere'] ?? '') == 'GIT' ? 'selected' : '' ?>>GIT</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-calendar-alt"></i> Promotion
                    </label>
                    <input type="text" 
                           name="promotion" 
                           class="form-input" 
                           value="<?= htmlspecialchars($user_data['promotion'] ?? '') ?>"
                           placeholder="Ex: 2024-2025">
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
                        
                        <!-- Actions -->
                        <div class="form-actions">
                            <button type="reset" class="btn btn-secondary">
                                <i class="fas fa-undo"></i> Réinitialiser
                            </button>
                            <button type="submit" class="btn btn-primary" id="saveInfoBtn">
                                <i class="fas fa-save"></i> Enregistrer les modifications
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Onglet Mot de passe -->
                <div class="tab-content" id="password-tab">
                    <form method="POST" class="profile-form" id="passwordForm">
                        <input type="hidden" name="update_password" value="1">
                        
                        <div class="form-section">
                            <div class="section-header">
                                <div class="section-icon">
                                    <i class="fas fa-key"></i>
                                </div>
                                <div>
                                    <h3 class="section-title">Modifier le mot de passe</h3>
                                    <p class="section-subtitle">Pour des raisons de sécurité, veuillez confirmer votre mot de passe actuel</p>
                                </div>
                            </div>
                            
                            <?php if ($password_error): ?>
                                <div class="error-message" style="margin-bottom: 2rem;">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <?= htmlspecialchars($password_error) ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label">
                                        <i class="fas fa-lock"></i> Mot de passe actuel *
                                    </label>
                                    <div class="input-with-icon">
                                        <i class="fas fa-key"></i>
                                        <input type="password" 
                                               name="current_password" 
                                               class="form-input" 
                                               id="currentPassword"
                                               required>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">
                                        <i class="fas fa-lock"></i> Nouveau mot de passe *
                                    </label>
                                    <div class="input-with-icon">
                                        <i class="fas fa-key"></i>
                                        <input type="password" 
                                               name="new_password" 
                                               class="form-input" 
                                               id="newPassword"
                                               required
                                               minlength="8">
                                    </div>
                                    <div class="password-strength">
                                        <div class="strength-bar" id="strengthBar"></div>
                                    </div>
                                    <div class="strength-text" id="strengthText"></div>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">
                                        <i class="fas fa-lock"></i> Confirmer le mot de passe *
                                    </label>
                                    <div class="input-with-icon">
                                        <i class="fas fa-key"></i>
                                        <input type="password" 
                                               name="confirm_password" 
                                               class="form-input" 
                                               id="confirmPassword"
                                               required>
                                    </div>
                                    <div id="passwordMatch" class="strength-text"></div>
                                </div>
                            </div>
                            
                            <!-- Indicateurs de sécurité -->
                            <div style="background: rgba(0, 0, 0, 0.2); padding: 1rem; border-radius: 8px; margin-top: 1.5rem;">
                                <h4 style="color: var(--accent-gold); margin-bottom: 0.5rem;">
                                    <i class="fas fa-shield-alt"></i> Conseils de sécurité
                                </h4>
                                <ul style="color: var(--text-muted); padding-left: 1.5rem;">
                                    <li>Utilisez au moins 8 caractères</li>
                                    <li>Combinez lettres majuscules et minuscules</li>
                                    <li>Ajoutez des chiffres et des caractères spéciaux</li>
                                    <li>Évitez les mots courants ou personnels</li>
                                </ul>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary" id="savePasswordBtn">
                                <i class="fas fa-key"></i> Mettre à jour le mot de passe
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Onglet Sécurité -->
                <div class="tab-content" id="security-tab">
                    <div class="profile-form">
                        <div class="form-section">
                            <div class="section-header">
                                <div class="section-icon">
                                    <i class="fas fa-shield-alt"></i>
                                </div>
                                <div>
                                    <h3 class="section-title">Sécurité du compte</h3>
                                    <p class="section-subtitle">Gérez la sécurité de votre compte</p>
                                </div>
                            </div>
                            
                            <div class="form-grid">
                                <div class="form-group">
                                    <div style="background: rgba(0, 0, 0, 0.2); padding: 1.5rem; border-radius: 12px;">
                                        <h4 style="color: var(--accent-teal); margin-bottom: 1rem;">
                                            <i class="fas fa-sign-in-alt"></i> Sessions actives
                                        </h4>
                                        <p style="color: var(--text-light); margin-bottom: 0.5rem;">
                                            <i class="fas fa-check-circle" style="color: var(--accent-teal);"></i>
                                            Session actuelle: 
                                            <strong><?= $_SERVER['REMOTE_ADDR'] ?></strong>
                                        </p>
                                        <p style="color: var(--text-muted); font-size: 0.9rem;">
                                            Dernière connexion: <?= date('d/m/Y à H:i') ?>
                                        </p>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <div style="background: rgba(0, 0, 0, 0.2); padding: 1.5rem; border-radius: 12px;">
                                        <h4 style="color: var(--accent-gold); margin-bottom: 1rem;">
                                            <i class="fas fa-bell"></i> Notifications
                                        </h4>
                                        <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
                                            <div style="flex: 1;">
                                                <p style="color: var(--text-light); margin: 0;">Notifications par email</p>
                                                <small style="color: var(--text-muted);">Recevez des notifications importantes</small>
                                            </div>
                                            <label class="switch">
                                                <input type="checkbox" checked disabled>
                                                <span class="slider"></span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <div class="section-header">
                                <div class="section-icon">
                                    <i class="fas fa-exclamation-triangle"></i>
                                </div>
                                <div>
                                    <h3 class="section-title">Zone de danger</h3>
                                    <p class="section-subtitle">Actions irréversibles</p>
                                </div>
                            </div>
                            
                            <div style="background: rgba(186, 40, 30, 0.1); border: 1px solid rgba(186, 40, 30, 0.3); border-radius: 12px; padding: 1.5rem;">
                                <h4 style="color: var(--accent-red); margin-bottom: 1rem;">
                                    <i class="fas fa-trash-alt"></i> Supprimer le compte
                                </h4>
                                <p style="color: var(--text-light); margin-bottom: 1rem;">
                                    Cette action supprimera définitivement votre compte et toutes vos données associées.
                                    <strong>Cette action est irréversible.</strong>
                                </p>
                                <button type="button" class="btn btn-delete" id="deleteAccountBtn">
                                    <i class="fas fa-trash-alt"></i> Supprimer mon compte
                                </button>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="button" class="btn btn-secondary" id="exportDataBtn">
                                <i class="fas fa-download"></i> Exporter mes données
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal pour changer la photo -->
    <?php if ($is_etudiant): ?>
        <div class="photo-modal" id="photoModal">
            <div class="photo-modal-content">
                <div class="photo-modal-header">
                    <h3 class="photo-modal-title">Changer la photo de profil</h3>
                    <button class="photo-modal-close" id="closePhotoModal">&times;</button>
                </div>
                
                <form method="POST" enctype="multipart/form-data" id="photoForm">
                    <div style="text-align: center; margin-bottom: 2rem;">
                        <div style="width: 150px; height: 150px; border-radius: 50%; background: rgba(255, 255, 255, 0.05); margin: 0 auto 1.5rem; overflow: hidden; border: 2px dashed var(--accent-teal); display: flex; align-items: center; justify-content: center; cursor: pointer;" id="dropZone">
                            <div id="photoPreview" style="display: none; width: 100%; height: 100%;"></div>
                            <div id="uploadText" style="color: var(--text-muted);">
                                <i class="fas fa-cloud-upload-alt" style="font-size: 3rem; margin-bottom: 0.5rem;"></i>
                                <p>Glissez-déposez ou cliquez</p>
                            </div>
                        </div>
                        <input type="file" name="profile_photo" id="photoInput" accept="image/*" style="display: none;">
                        <small style="color: var(--text-muted); display: block; margin-top: 0.5rem;">
                            Formats acceptés: JPG, PNG, GIF (max 2MB)
                        </small>
                    </div>
                    
                    <div style="display: flex; gap: 1rem;">
                        <button type="button" class="btn btn-secondary" id="cancelPhotoBtn" style="flex: 1;">
                            Annuler
                        </button>
                        <button type="submit" class="btn btn-primary" id="savePhotoBtn" style="flex: 1;" disabled>
                            <i class="fas fa-save"></i> Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
    
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
    
    // Gestion des onglets
    const tabButtons = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');
    
    tabButtons.forEach(button => {
        button.addEventListener('click', () => {
            const tabId = button.getAttribute('data-tab');
            
            // Retirer la classe active de tous les boutons et contenus
            tabButtons.forEach(btn => btn.classList.remove('active'));
            tabContents.forEach(content => content.classList.remove('active'));
            
            // Ajouter la classe active au bouton cliqué
            button.classList.add('active');
            
            // Afficher le contenu correspondant
            document.getElementById(`${tabId}-tab`).classList.add('active');
            
            // Animation
            document.getElementById(`${tabId}-tab`).style.animation = 'none';
            setTimeout(() => {
                document.getElementById(`${tabId}-tab`).style.animation = 'fadeIn 0.5s ease-out';
            }, 10);
        });
    });
    
    // Validation du mot de passe
    const newPasswordInput = document.getElementById('newPassword');
    const confirmPasswordInput = document.getElementById('confirmPassword');
    const strengthBar = document.getElementById('strengthBar');
    const strengthText = document.getElementById('strengthText');
    const passwordMatch = document.getElementById('passwordMatch');
    
    function checkPasswordStrength(password) {
        let strength = 0;
        
        if (password.length >= 8) strength++;
        if (/[A-Z]/.test(password)) strength++;
        if (/[a-z]/.test(password)) strength++;
        if (/[0-9]/.test(password)) strength++;
        if (/[^A-Za-z0-9]/.test(password)) strength++;
        
        return strength;
    }
    
    function updatePasswordStrength() {
        const password = newPasswordInput.value;
        const strength = checkPasswordStrength(password);
        
        strengthBar.className = 'strength-bar';
        
        if (password.length === 0) {
            strengthBar.style.width = '0%';
            strengthText.textContent = '';
        } else if (strength <= 2) {
            strengthBar.classList.add('weak');
            strengthText.textContent = 'Faible';
            strengthText.style.color = 'var(--accent-red)';
        } else if (strength <= 4) {
            strengthBar.classList.add('medium');
            strengthText.textContent = 'Moyen';
            strengthText.style.color = '#FFA500';
        } else {
            strengthBar.classList.add('strong');
            strengthText.textContent = 'Fort';
            strengthText.style.color = 'var(--accent-teal)';
        }
        
        // Vérifier la correspondance des mots de passe
        if (confirmPasswordInput.value) {
            if (newPasswordInput.value === confirmPasswordInput.value) {
                passwordMatch.textContent = '✓ Les mots de passe correspondent';
                passwordMatch.style.color = 'var(--accent-teal)';
            } else {
                passwordMatch.textContent = '✗ Les mots de passe ne correspondent pas';
                passwordMatch.style.color = 'var(--accent-red)';
            }
        } else {
            passwordMatch.textContent = '';
        }
    }
    
    newPasswordInput.addEventListener('input', updatePasswordStrength);
    confirmPasswordInput.addEventListener('input', updatePasswordStrength);
    
    // Validation du formulaire d'informations
    const infoForm = document.getElementById('infoForm');
    if (infoForm) {
        infoForm.addEventListener('submit', function(e) {
            const email = this.querySelector('input[name="email"]').value;
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            
            if (!emailRegex.test(email)) {
                e.preventDefault();
                alert('Veuillez entrer une adresse email valide.');
                return;
            }
            
            const saveBtn = document.getElementById('saveInfoBtn');
            saveBtn.innerHTML = '<span class="loading"></span> Enregistrement...';
            saveBtn.disabled = true;
        });
    }
    
    // Validation du formulaire de mot de passe
    const passwordForm = document.getElementById('passwordForm');
    if (passwordForm) {
        passwordForm.addEventListener('submit', function(e) {
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            
            if (newPassword !== confirmPassword) {
                e.preventDefault();
                document.getElementById('passwordMatch').textContent = '✗ Les mots de passe ne correspondent pas';
                document.getElementById('passwordMatch').style.color = 'var(--accent-red)';
                return;
            }
            
            if (newPassword.length < 8) {
                e.preventDefault();
                alert('Le mot de passe doit contenir au moins 8 caractères.');
                return;
            }
            
            const saveBtn = document.getElementById('savePasswordBtn');
            saveBtn.innerHTML = '<span class="loading"></span> Mise à jour...';
            saveBtn.disabled = true;
        });
    }
    
    // Gestion de la photo de profil
    <?php if ($is_etudiant): ?>
    const changePhotoBtn = document.getElementById('changePhotoBtn');
    const photoModal = document.getElementById('photoModal');
    const closePhotoModal = document.getElementById('closePhotoModal');
    const cancelPhotoBtn = document.getElementById('cancelPhotoBtn');
    const photoInput = document.getElementById('photoInput');
    const dropZone = document.getElementById('dropZone');
    const photoPreview = document.getElementById('photoPreview');
    const uploadText = document.getElementById('uploadText');
    const savePhotoBtn = document.getElementById('savePhotoBtn');
    const photoForm = document.getElementById('photoForm');
    
    changePhotoBtn.addEventListener('click', () => {
        photoModal.style.display = 'flex';
    });
    
    closePhotoModal.addEventListener('click', () => {
        photoModal.style.display = 'none';
        resetPhotoForm();
    });
    
    cancelPhotoBtn.addEventListener('click', () => {
        photoModal.style.display = 'none';
        resetPhotoForm();
    });
    
    // Click sur la zone de dépôt
    dropZone.addEventListener('click', () => {
        photoInput.click();
    });
    
    // Drag and drop
    dropZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropZone.style.borderColor = 'var(--accent-teal)';
        dropZone.style.background = 'rgba(32, 201, 151, 0.1)';
    });
    
    dropZone.addEventListener('dragleave', () => {
        dropZone.style.borderColor = 'var(--accent-teal)';
        dropZone.style.background = 'rgba(255, 255, 255, 0.05)';
    });
    
    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.style.borderColor = 'var(--accent-teal)';
        dropZone.style.background = 'rgba(255, 255, 255, 0.05)';
        
        if (e.dataTransfer.files.length) {
            handleFileSelect(e.dataTransfer.files[0]);
        }
    });
    
    // Sélection de fichier
    photoInput.addEventListener('change', (e) => {
        if (e.target.files.length) {
            handleFileSelect(e.target.files[0]);
        }
    });
    
    function handleFileSelect(file) {
        // Vérifier le type de fichier
        if (!file.type.match('image.*')) {
            alert('Veuillez sélectionner une image valide.');
            return;
        }
        
        // Vérifier la taille (2MB max)
        if (file.size > 2000000) {
            alert('L\'image est trop volumineuse (max 2MB).');
            return;
        }
        
        // Aperçu de l'image
        const reader = new FileReader();
        reader.onload = function(e) {
            photoPreview.innerHTML = `<img src="${e.target.result}" style="width:100%;height:100%;object-fit:cover;">`;
            photoPreview.style.display = 'block';
            uploadText.style.display = 'none';
            savePhotoBtn.disabled = false;
        };
        reader.readAsDataURL(file);
    }
    
    function resetPhotoForm() {
        photoInput.value = '';
        photoPreview.innerHTML = '';
        photoPreview.style.display = 'none';
        uploadText.style.display = 'block';
        savePhotoBtn.disabled = true;
    }
    
    // Soumission du formulaire photo
    photoForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        if (!photoInput.files.length) {
            alert('Veuillez sélectionner une image.');
            return;
        }
        
        const formData = new FormData(this);
        savePhotoBtn.innerHTML = '<span class="loading"></span> Téléchargement...';
        savePhotoBtn.disabled = true;
        
        fetch('', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(html => {
            // Recharger la page pour voir les changements
            window.location.reload();
        })
        .catch(error => {
            console.error('Erreur:', error);
            alert('Une erreur est survenue lors du téléchargement.');
            savePhotoBtn.innerHTML = '<i class="fas fa-save"></i> Enregistrer';
            savePhotoBtn.disabled = false;
        });
    });
    <?php endif; ?>
    
    // Effet ripple sur les boutons
    document.querySelectorAll('.btn').forEach(button => {
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
    
    // Animation des champs de formulaire
    document.querySelectorAll('.form-input').forEach(input => {
        input.addEventListener('focus', function() {
            this.parentElement.style.transform = 'translateY(-2px)';
        });
        
        input.addEventListener('blur', function() {
            this.parentElement.style.transform = 'translateY(0)';
        });
    });
    
    // Export de données
    document.getElementById('exportDataBtn')?.addEventListener('click', function() {
        const btn = this;
        btn.innerHTML = '<span class="loading"></span> Préparation...';
        btn.disabled = true;
        
        setTimeout(() => {
            // Simuler l'export
            alert('Vos données sont en cours de préparation. Vous recevrez un email avec le lien de téléchargement.');
            btn.innerHTML = '<i class="fas fa-download"></i> Exporter mes données';
            btn.disabled = false;
        }, 2000);
    });
    
    // Suppression de compte
    document.getElementById('deleteAccountBtn')?.addEventListener('click', function() {
        if (confirm('Êtes-vous sûr de vouloir supprimer votre compte ? Cette action est irréversible.')) {
            if (confirm('Toutes vos données seront définitivement perdues. Confirmez la suppression.')) {
                alert('Pour des raisons de sécurité, la suppression de compte doit être effectuée via l\'administration. Contactez le support.');
            }
        }
    });
    
    // Animation de chargement de la page
    window.addEventListener('load', function() {
        document.body.style.opacity = '0';
        document.body.style.transition = 'opacity 0.5s ease-in';
        
        setTimeout(() => {
            document.body.style.opacity = '1';
        }, 100);
        
        // Animation des icônes
        const icons = document.querySelectorAll('.section-icon, .card-icon');
        icons.forEach((icon, index) => {
            setTimeout(() => {
                icon.style.opacity = '0';
                icon.style.transform = 'scale(0.8) rotate(-10deg)';
                icon.style.transition = 'all 0.4s ease-out';
                
                setTimeout(() => {
                    icon.style.opacity = '1';
                    icon.style.transform = 'scale(1) rotate(0deg)';
                }, 50);
            }, index * 100);
        });
        
        // Afficher les stats avec animation
        const statValues = document.querySelectorAll('.stat-value');
        statValues.forEach((stat, index) => {
            const originalText = stat.textContent;
            if (originalText && !isNaN(originalText.replace(/[^0-9]/g, ''))) {
                const target = parseInt(originalText.replace(/[^0-9]/g, ''));
                let current = 0;
                const increment = target / 20;
                
                const timer = setInterval(() => {
                    current += increment;
                    if (current >= target) {
                        clearInterval(timer);
                        stat.textContent = originalText;
                    } else {
                        stat.textContent = Math.floor(current);
                    }
                }, 50);
            }
        });
    });
    
    // Toggle pour afficher/masquer les mots de passe
    const togglePassword = document.createElement('span');
    togglePassword.innerHTML = '<i class="fas fa-eye"></i>';
    togglePassword.style.cssText = `
        position: absolute;
        right: 1rem;
        top: 50%;
        transform: translateY(-50%);
        color: var(--accent-teal);
        cursor: pointer;
        font-size: 1.2rem;
    `;
    
    document.querySelectorAll('input[type="password"]').forEach(input => {
        const toggle = togglePassword.cloneNode(true);
        input.parentElement.style.position = 'relative';
        input.parentElement.appendChild(toggle);
        
        toggle.addEventListener('click', function() {
            const icon = this.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'fas fa-eye-slash';
            } else {
                input.type = 'password';
                icon.className = 'fas fa-eye';
            }
        });
    });
    </script>
    
    <style>
    /* Style pour le toggle switch */
    .switch {
        position: relative;
        display: inline-block;
        width: 60px;
        height: 34px;
    }
    
    .switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }
    
    .slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: rgba(255, 255, 255, 0.1);
        transition: var(--transition-smooth);
        border-radius: 34px;
    }
    
    .slider:before {
        position: absolute;
        content: "";
        height: 26px;
        width: 26px;
        left: 4px;
        bottom: 4px;
        background-color: var(--text-white);
        transition: var(--transition-smooth);
        border-radius: 50%;
    }
    
    input:checked + .slider {
        background-color: var(--accent-teal);
    }
    
    input:checked + .slider:before {
        transform: translateX(26px);
    }
    </style>
