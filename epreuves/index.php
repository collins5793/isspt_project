<?php
session_start();
require_once '../includes/db.php'; // connexion PDO

define('BASE_URL', '/isspt_projet/'); // chemin relatif depuis localhost
$base_url = BASE_URL;

// Initialisation des variables
$isLogged = false;
$isAdmin = false;
$userName = '';
$userAvatar = '../assets/images/default-avatar.png'; // avatar par défaut

// --- Vérification de la connexion (admin OU étudiant) ---
$isAdmin = isset($_SESSION['admin_id']);
$isEtudiant = isset($_SESSION['etudiant_id']);

if (!$isAdmin && !$isEtudiant) {
    // Si ni admin ni étudiant n'est connecté → redirection
    header("Location: ../etudiant/login_etudiant.php");
    exit;
}

// --- Traitement des filtres et recherche ---
$search = $_GET['search'] ?? '';
$filiere = $_GET['filiere'] ?? '';
$annee = $_GET['annee'] ?? '';
$type = $_GET['type'] ?? '';
$niveau = $_GET['niveau'] ?? '';

// --- Requête SQL principale ---
$query = "
    SELECT e.id_epreuve, e.titre, e.description, e.file_path, 
           e.niveau, e.date_ajout, e.is_public,
           c.nom_category AS type_epreuve,
           y.label AS annee_univ,
           f.nom_filiere
    FROM epreuves e
    LEFT JOIN epreuves_categories c ON e.id_category = c.id_category
    LEFT JOIN academic_years y ON e.academic_year_id = y.id
    LEFT JOIN filieres f ON e.id_filiere = f.id_filiere
    WHERE 1=1
";

$params = [];

if (!empty($search)) { 
    $query .= " AND e.titre LIKE :search"; 
    $params[':search'] = "%$search%"; 
}
if (!empty($filiere)) { 
    $query .= " AND f.nom_filiere = :filiere"; 
    $params[':filiere'] = $filiere; 
}
if (!empty($annee)) { 
    $query .= " AND y.label = :annee"; 
    $params[':annee'] = $annee; 
}
if (!empty($type)) { 
    $query .= " AND c.nom_category = :type"; 
    $params[':type'] = $type; 
}
if (!empty($niveau)) { 
    $query .= " AND e.niveau = :niveau"; 
    $params[':niveau'] = $niveau; 
}

$query .= " ORDER BY e.date_ajout DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$epreuves = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📚 Recueil d'Épreuves Universitaires</title>
    <link rel="stylesheet" href="assets/css/index.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
/* ==========================================================================
   THEME PREMIUM - RECUEIL D'ÉPREUVES UNIVERSITAIRES
   Design moderne avec animations élégantes
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
    font-family: 'Montserrat', 'Segoe UI', sans-serif;
    background: var(--primary-dark);
    color: var(--text-white);
    min-height: 100vh;
    overflow-x: hidden;
    background-image: 
        radial-gradient(circle at 20% 80%, rgba(186, 40, 30, 0.15) 0%, transparent 40%),
        radial-gradient(circle at 80% 20%, rgba(8, 0, 32, 0.8) 0%, transparent 40%),
        linear-gradient(135deg, var(--primary-dark) 0%, var(--primary-darker) 100%);
}

/* ==================== ANIMATIONS ==================== */
@keyframes float {
    0%, 100% { transform: translateY(0px); }
    50% { transform: translateY(-10px); }
}

@keyframes glow {
    0%, 100% { box-shadow: 0 0 20px rgba(186, 40, 30, 0.3); }
    50% { box-shadow: 0 0 40px rgba(186, 40, 30, 0.6); }
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateX(-30px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(40px);
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

/* ==================== HEADER HERO ==================== */
.hero-section {
    position: relative;
    padding: 5rem 2rem;
    text-align: center;
    background: linear-gradient(135deg, 
        rgba(8, 0, 32, 0.9) 0%, 
        rgba(186, 40, 30, 0.15) 100%);
    overflow: hidden;
    animation: fadeInUp 1s ease-out;
}

.hero-section::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: 
        url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="50" cy="50" r="1" fill="rgba(255,255,255,0.05)"/></svg>'),
        url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="20" cy="20" r="1" fill="rgba(186,40,30,0.1)"/></svg>');
    background-size: 50px 50px, 30px 30px;
    animation: float 20s infinite linear;
}

.hero-content {
    position: relative;
    z-index: 2;
    max-width: 800px;
    margin: 0 auto;
}

.hero-title {
    font-size: clamp(2.5rem, 5vw, 4rem);
    font-weight: 800;
    margin-bottom: 1rem;
    background: linear-gradient(135deg, #fff 0%, var(--accent-gold) 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    animation: slideIn 1s ease-out;
}

.hero-subtitle {
    font-size: 1.2rem;
    color: var(--text-light);
    margin-bottom: 2rem;
    opacity: 0;
    animation: fadeInUp 1s ease-out 0.3s forwards;
}

/* ==================== FILTRES AVANCÉS ==================== */
.filters-container {
    max-width: 1200px;
    margin: -2rem auto 3rem;
    padding: 0 2rem;
}

.filters-card {
    background: var(--glass-bg);
    backdrop-filter: blur(20px);
    border: 1px solid var(--glass-border);
    border-radius: 20px;
    padding: 2rem;
    box-shadow: var(--glass-shadow);
    animation: fadeInUp 1s ease-out 0.5s both;
}

.filters-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}

.filter-group {
    position: relative;
}

.filter-group label {
    display: block;
    color: var(--accent-gold);
    font-size: 0.9rem;
    margin-bottom: 0.5rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.filter-input {
    width: 100%;
    padding: 0.8rem 1rem;
    background: rgba(0, 0, 0, 0.3);
    border: 2px solid rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    color: var(--text-white);
    font-size: 1rem;
    transition: var(--transition-smooth);
}

.filter-input:focus {
    outline: none;
    border-color: var(--accent-red);
    background: rgba(186, 40, 30, 0.1);
    transform: translateY(-2px);
}

.filter-input::placeholder {
    color: var(--text-muted);
}

.filters-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 1.5rem;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.btn-filter {
    padding: 0.8rem 2rem;
    background: linear-gradient(135deg, var(--accent-red) 0%, #c53030 100%);
    color: white;
    border: none;
    border-radius: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: var(--transition-smooth);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-filter:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 30px rgba(186, 40, 30, 0.4);
    animation: glow 2s infinite;
}

.btn-add {
    padding: 0.8rem 1.5rem;
    background: transparent;
    border: 2px solid var(--accent-teal);
    color: var(--accent-teal);
    border-radius: 12px;
    font-weight: 600;
    text-decoration: none;
    transition: var(--transition-smooth);
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-add:hover {
    background: var(--accent-teal);
    color: var(--primary-dark);
    transform: translateY(-3px);
}

/* ==================== GRILLE DE CARTES ==================== */
.main-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 2rem 4rem;
}

.epreuve-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 2rem;
    margin-top: 2rem;
}

.epreuve-card {
    background: linear-gradient(135deg, 
        rgba(255, 255, 255, 0.05) 0%,
        rgba(255, 255, 255, 0.02) 100%);
    backdrop-filter: blur(10px);
    border: 1px solid var(--glass-border);
    border-radius: 20px;
    overflow: hidden;
    transition: var(--transition-bounce);
    position: relative;
    opacity: 0;
    animation: fadeInUp 0.6s ease-out forwards;
}

.epreuve-card:nth-child(2) { animation-delay: 0.1s; }
.epreuve-card:nth-child(3) { animation-delay: 0.2s; }
.epreuve-card:nth-child(4) { animation-delay: 0.3s; }
.epreuve-card:nth-child(5) { animation-delay: 0.4s; }

.epreuve-card:hover {
    transform: translateY(-15px) scale(1.02);
    border-color: var(--accent-red);
    box-shadow: 
        0 20px 40px rgba(0, 0, 0, 0.4),
        0 0 0 1px rgba(186, 40, 30, 0.3);
}

.card-image {
    width: 100%;
    height: 200px;
    object-fit: cover;
    transition: var(--transition-smooth);
}

.epreuve-card:hover .card-image {
    transform: scale(1.05);
}

.card-badge {
    position: absolute;
    top: 1rem;
    right: 1rem;
    background: var(--accent-red);
    color: white;
    padding: 0.3rem 0.8rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    animation: float 3s infinite ease-in-out;
}

.card-content {
    padding: 1.5rem;
}

.card-title {
    font-size: 1.3rem;
    font-weight: 700;
    margin-bottom: 1rem;
    line-height: 1.4;
    color: var(--text-white);
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.card-meta {
    display: flex;
    gap: 1rem;
    margin-bottom: 1rem;
    flex-wrap: wrap;
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 0.3rem;
    color: var(--text-light);
    font-size: 0.9rem;
}

.meta-item i {
    color: var(--accent-teal);
}

.card-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 1rem;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    gap: 10px;
}

.filiere-tag {
    background: rgba(186, 40, 30, 0.2);
    color: var(--accent-red);
    padding: 0.3rem 0.8rem;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 600;
}

.btn-view {
    background: transparent;
    border: 2px solid var(--accent-teal);
    color: var(--accent-teal);
    padding: 0.5rem 1rem;
    border-radius: 10px;
    text-decoration: none;
    font-weight: 600;
    transition: var(--transition-smooth);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-view:hover {
    background: var(--accent-teal);
    color: var(--primary-dark);
    transform: translateX(5px);
}

/* ==================== ADMIN ACTIONS ==================== */
.admin-actions {
    position: absolute;
    top: 1rem;
    left: 1rem;
    display: flex;
    gap: 0.5rem;
    z-index: 10;
}

.admin-btn {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(0, 0, 0, 0.7);
    color: white;
    text-decoration: none;
    transition: var(--transition-smooth);
    backdrop-filter: blur(10px);
}

.admin-btn:hover {
    transform: scale(1.1) rotate(5deg);
}

.btn-edit:hover {
    background: var(--accent-teal);
    color: var(--primary-dark);
}

.btn-delete:hover {
    background: var(--accent-red);
    color: white;
}

/* ==================== MODAL ==================== */
.modal-overlay {
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
    animation: fadeInUp 0.3s ease-out;
}

.modal-content {
    background: linear-gradient(135deg, 
        rgba(255, 255, 255, 0.1) 0%,
        rgba(255, 255, 255, 0.05) 100%);
    backdrop-filter: blur(20px);
    border: 1px solid var(--glass-border);
    border-radius: 24px;
    padding: 2.5rem;
    max-width: 500px;
    width: 90%;
    max-height: 80vh;
    overflow-y: auto;
    position: relative;
    animation: fadeInUp 0.4s ease-out 0.1s both;
}

.modal-close {
    position: absolute;
    top: 1rem;
    right: 1rem;
    background: none;
    border: none;
    color: var(--text-muted);
    font-size: 1.5rem;
    cursor: pointer;
    transition: var(--transition-smooth);
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-close:hover {
    color: var(--accent-red);
    background: rgba(255, 255, 255, 0.1);
    transform: rotate(90deg);
}

.modal-header {
    margin-bottom: 2rem;
}

.modal-title {
    font-size: 1.8rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
    color: var(--text-white);
}

.modal-subtitle {
    color: var(--text-light);
    font-size: 1rem;
}

.modal-details {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.detail-item {
    background: rgba(255, 255, 255, 0.05);
    padding: 1rem;
    border-radius: 12px;
}

.detail-label {
    display: block;
    color: var(--accent-gold);
    font-size: 0.8rem;
    margin-bottom: 0.3rem;
    font-weight: 600;
    text-transform: uppercase;
}

.detail-value {
    color: var(--text-white);
    font-weight: 500;
}

.modal-description {
    background: rgba(255, 255, 255, 0.05);
    padding: 1.5rem;
    border-radius: 12px;
    margin-bottom: 2rem;
    line-height: 1.6;
    color: var(--text-light);
}

.btn-modal-download {
    display: block;
    width: 100%;
    padding: 1rem;
    background: linear-gradient(135deg, var(--accent-red) 0%, #c53030 100%);
    color: white;
    text-align: center;
    text-decoration: none;
    border-radius: 12px;
    font-weight: 600;
    transition: var(--transition-smooth);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.btn-modal-download:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 30px rgba(186, 40, 30, 0.4);
    animation: glow 2s infinite;
}

/* ==================== MESSAGES D'ALERTE ==================== */
.alert-success {
    background: rgba(72, 187, 120, 0.2);
    border: 1px solid rgba(72, 187, 120, 0.3);
    color: #48bb78;
    padding: 1rem 1.5rem;
    border-radius: 12px;
    margin: 2rem auto;
    max-width: 1200px;
    animation: fadeInUp 0.5s ease-out;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.no-results {
    grid-column: 1 / -1;
    text-align: center;
    padding: 4rem 2rem;
    color: var(--text-muted);
    font-size: 1.1rem;
}

.no-results i {
    font-size: 3rem;
    margin-bottom: 1rem;
    color: var(--accent-red);
}

/* ==================== RESPONSIVE ==================== */
@media (max-width: 768px) {
    .hero-section {
        padding: 3rem 1rem;
    }
    
    .filters-container {
        padding: 0 1rem;
        margin-top: -1rem;
    }
    
    .filters-card {
        padding: 1.5rem;
    }
    
    .filters-grid {
        grid-template-columns: 1fr;
    }
    
    .epreuve-grid {
        grid-template-columns: 1fr;
        gap: 1.5rem;
    }
    
    .main-container {
        padding: 0 1rem 2rem;
    }
    
    .filters-actions {
        flex-direction: column;
        gap: 1rem;
    }
    
    .btn-filter, .btn-add {
        width: 100%;
        justify-content: center;
    }
}

@media (max-width: 480px) {
    .hero-title {
        font-size: 2rem;
    }
    
    .modal-content {
        padding: 1.5rem;
        width: 95%;
    }
    
    .modal-details {
        grid-template-columns: 1fr;
    }
}

/* ==================== ANIMATIONS DE SCROLL ==================== */
.scroll-animate {
    opacity: 0;
    transform: translateY(30px);
    transition: all 0.6s cubic-bezier(0.4, 0, 0.2, 1);
}

.scroll-animate.visible {
    opacity: 1;
    transform: translateY(0);
}
</style>

    <?php include "../includes/header.php"; ?>

<!-- Hero Section -->
<section class="hero-section">
    <div class="hero-content">
        <h1 class="hero-title">
            <i class="fas fa-graduation-cap"></i> 
            Recueil d'Épreuves Universitaires
        </h1>
        <p class="hero-subtitle">
            Explorez notre collection premium d'épreuves académiques. 
            Recherchez, filtrez et téléchargez en un clic.
        </p>
        <div class="hero-stats">
            <span class="stat">
                <i class="fas fa-book-open"></i>
                <?= count($epreuves) ?> Épreuves disponibles
            </span>
        </div>
    </div>
</section>

<!-- Filtres -->
<div class="filters-container">
    <form method="GET" class="filters-card scroll-animate">
        <div class="filters-grid">
            <div class="filter-group">
                <label><i class="fas fa-search"></i> Recherche</label>
                <input type="text" 
                       name="search" 
                       value="<?= htmlspecialchars($search) ?>" 
                       placeholder="Titre, matière, professeur..."
                       class="filter-input">
            </div>
            
            <div class="filter-group">
                <label><i class="fas fa-graduation-cap"></i> Filière</label>
                <select name="filiere" class="filter-input">
                    <option value="">Toutes les filières</option>
                    <option value="SIL" <?= $filiere=="SIL"?"selected":"" ?>>SIL</option>
                    <option value="RIT" <?= $filiere=="RIT"?"selected":"" ?>>RIT</option>
                    <option value="GIT" <?= $filiere=="GIT"?"selected":"" ?>>GIT</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label><i class="fas fa-calendar-alt"></i> Année</label>
                <select name="annee" class="filter-input">
                    <option value="">Toutes les années</option>
                    <option value="2024-2025" <?= $annee=="2024-2025"?"selected":"" ?>>2024-2025</option>
                    <option value="2023-2024" <?= $annee=="2023-2024"?"selected":"" ?>>2023-2024</option>
                    <option value="2022-2023" <?= $annee=="2022-2023"?"selected":"" ?>>2022-2023</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label><i class="fas fa-file-alt"></i> Type</label>
                <select name="type" class="filter-input">
                    <option value="">Tous les types</option>
                    <option value="Examen national" <?= $type=="Examen national"?"selected":"" ?>>Examen national</option>
                    <option value="Partiel" <?= $type=="Partiel"?"selected":"" ?>>Partiel</option>
                    <option value="TP" <?= $type=="TP"?"selected":"" ?>>TP</option>
                    <option value="Devoir surveillé" <?= $type=="Devoir surveillé"?"selected":"" ?>>Devoir surveillé</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label><i class="fas fa-layer-group"></i> Niveau</label>
                <select name="niveau" class="filter-input">
                    <option value="">Tous les niveaux</option>
                    <option value="1ère année" <?= $niveau=="1ère année"?"selected":"" ?>>1ère année</option>
                    <option value="2ème année" <?= $niveau=="2ème année"?"selected":"" ?>>2ème année</option>
                    <option value="3ème année" <?= $niveau=="3ème année"?"selected":"" ?>>3ème année</option>
                    <option value="autre" <?= $niveau=="autre"?"selected":"" ?>>Autre</option>
                </select>
            </div>
        </div>
        
        <div class="filters-actions">
            <button type="submit" class="btn-filter">
                <i class="fas fa-filter"></i> Appliquer les filtres
            </button>
            
            <?php if ($isAdmin): ?>
                <a href="ajouter_epreuve.php" class="btn-add">
                    <i class="fas fa-plus-circle"></i> Ajouter une épreuve
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Messages -->
<?php if (isset($_GET['message']) && $_GET['message'] === 'supprime'): ?>
    <div class="alert-success scroll-animate">
        <i class="fas fa-check-circle"></i>
        Épreuve supprimée avec succès
    </div>
<?php endif; ?>

<!-- Grille des épreuves -->
<div class="main-container">
    <?php if (!empty($epreuves)): ?>
        <div class="epreuve-grid">
            <?php foreach($epreuves as $row): 
                $thumbPath = '../admins/epreuve/uploads/thumbs/' . pathinfo($row['file_path'], PATHINFO_FILENAME) . '.jpg';
                if(!file_exists($thumbPath)) $thumbPath = '../admins/epreuve/uploads/thumbs/pdf-icon.jpg';
            ?>
            <div class="epreuve-card scroll-animate"
                data-id="<?= intval($row['id_epreuve']) ?>"
                data-titre="<?= htmlspecialchars($row['titre']) ?>"
                data-filiere="<?= htmlspecialchars($row['nom_filiere']) ?>"
                data-annee="<?= htmlspecialchars($row['annee_univ']) ?>"
                data-type="<?= htmlspecialchars($row['type_epreuve']) ?>"
                data-niveau="<?= htmlspecialchars($row['niveau']) ?>"
                data-description="<?= htmlspecialchars($row['description']) ?>"
                data-file="<?= htmlspecialchars($row['file_path']) ?>">
                
                <span class="card-badge">
                    <i class="fas fa-file-pdf"></i> PDF
                </span>
                
                <?php if ($isAdmin): ?>
                <div class="admin-actions">
                    <a href="../admins/epreuve/modifier_epreuve.php?id=<?= $row['id_epreuve'] ?>" 
                       class="admin-btn btn-edit" 
                       title="Modifier">
                        <i class="fas fa-edit"></i>
                    </a>
                    <a href="../admins/epreuve/supprimer_epreuve.php?id=<?= $row['id_epreuve'] ?>" 
                       class="admin-btn btn-delete" 
                       onclick="return confirm('Supprimer cette épreuve ?');" 
                       title="Supprimer">
                        <i class="fas fa-trash-alt"></i>
                    </a>
                </div>
                <?php endif; ?>
                
                <img src="<?= $thumbPath ?>" 
                     alt="<?= htmlspecialchars($row['titre']) ?>" 
                     class="card-image">
                
                <div class="card-content">
                    <h3 class="card-title"><?= htmlspecialchars($row['titre']) ?></h3>
                    
                    <div class="card-meta">
                        <span class="meta-item">
                            <i class="fas fa-university"></i>
                            <?= htmlspecialchars($row['type_epreuve']) ?>
                        </span>
                        <span class="meta-item">
                            <i class="fas fa-user-graduate"></i>
                            <?= htmlspecialchars($row['niveau']) ?>
                        </span>
                        <span class="meta-item">
                            <i class="fas fa-calendar"></i>
                            <?= htmlspecialchars($row['annee_univ']) ?>
                        </span>
                    </div>
                    
                    <div class="card-footer">
                        <span class="filiere-tag">
                            <?= htmlspecialchars($row['nom_filiere']) ?>
                        </span>
                        <a href="#" class="btn-view open-modal">
                            <i class="fas fa-eye"></i> Voir détails
                        </a>
                        <!-- Routage propre vers download.php avec l'identifiant unique -->
                        <a class="btn-download" href="download.php?id=<?= $row['id_epreuve'] ?>">
                            <i class="fas fa-download"></i> Télécharger
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="no-results scroll-animate">
            <i class="fas fa-search"></i>
            <h3>Aucune épreuve trouvée</h3>
            <p>Essayez de modifier vos critères de recherche</p>
        </div>
    <?php endif; ?>
</div>

<!-- Modal -->
<div class="modal-overlay" id="modal-overlay">
    <div class="modal-content">
        <button class="modal-close" id="modal-close">&times;</button>
        
        <div class="modal-header">
            <h2 class="modal-title" id="modal-titre"></h2>
            <p class="modal-subtitle" id="modal-subtitle"></p>
        </div>
        
        <div class="modal-details">
            <div class="detail-item">
                <span class="detail-label">Filière</span>
                <span class="detail-value" id="modal-filiere"></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Année universitaire</span>
                <span class="detail-value" id="modal-annee"></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Type d'épreuve</span>
                <span class="detail-value" id="modal-type"></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Niveau</span>
                <span class="detail-value" id="modal-niveau"></span>
            </div>
        </div>
        
        <div class="modal-description">
            <h4>Description</h4>
            <p id="modal-description"></p>
        </div>
        
        <!-- Le bouton de la modal est lui aussi synchronisé en JS vers download.php -->
        <a id="modal-download-btn" class="btn-modal-download" href="#">
            <i class="fas fa-download"></i> Télécharger le PDF
        </a>
    </div>
</div>

<?php include "../includes/footer.php"; ?>

<script>
// Animation au scroll via IntersectionObserver
const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.classList.add('visible');
        }
    });
}, { threshold: 0.1 });

document.querySelectorAll('.scroll-animate').forEach((el) => {
    observer.observe(el);
});

// Gestion fine de la fenêtre Modale
document.querySelectorAll('.open-modal').forEach(button => {
    button.addEventListener('click', (e) => {
        e.preventDefault();
        const card = button.closest('.epreuve-card');
        
        document.getElementById('modal-titre').textContent = card.dataset.titre;
        document.getElementById('modal-filiere').textContent = card.dataset.filiere;
        document.getElementById('modal-annee').textContent = card.dataset.annee;
        document.getElementById('modal-type').textContent = card.dataset.type;
        document.getElementById('modal-niveau').textContent = card.dataset.niveau;
        document.getElementById('modal-description').textContent = card.dataset.description || 'Aucune description disponible';
        
        // Routage dynamique du bouton de téléchargement de la modal vers le script de log
        document.getElementById('modal-download-btn').href = 'download.php?id=' + card.dataset.id;
        
        document.getElementById('modal-subtitle').textContent = `${card.dataset.filiere} • ${card.dataset.annee}`;
        document.getElementById('modal-overlay').style.display = 'flex';
    });
});

// Fermeture de la modal
document.getElementById('modal-close').addEventListener('click', () => {
    document.getElementById('modal-overlay').style.display = 'none';
});

document.getElementById('modal-overlay').addEventListener('click', (e) => {
    if (e.target === document.getElementById('modal-overlay')) {
        document.getElementById('modal-overlay').style.display = 'none';
    }
});

// Recherche dynamique temps réel (Filtre visuel côté client)
const searchInput = document.querySelector('input[name="search"]');
const cards = document.querySelectorAll('.epreuve-card');

if (searchInput) {
    searchInput.addEventListener('input', (e) => {
        const searchTerm = e.target.value.toLowerCase();
        
        cards.forEach(card => {
            const title = card.querySelector('.card-title').textContent.toLowerCase();
            const filiere = card.querySelector('.filiere-tag').textContent.toLowerCase();
            const type = card.dataset.type.toLowerCase();
            
            const matches = title.includes(searchTerm) || 
                            filiere.includes(searchTerm) || 
                            type.includes(searchTerm);
            
            card.style.display = matches ? 'block' : 'none';
        });
    });
}

// Fade-in fluide au chargement global du DOM
window.addEventListener('load', () => {
    document.body.style.opacity = 0;
    document.body.style.transition = 'opacity 0.4s ease-in';
    setTimeout(() => { document.body.style.opacity = 1; }, 50);
});
</script>
