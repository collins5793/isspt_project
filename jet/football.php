<?php
session_start();
require_once '../includes/db.php';
define('BASE_URL', '/isspt_projet/'); // chemin relatif depuis localhost
$base_url = BASE_URL;

// Vérification de l'authentification
$isLogged = false;
$isAdmin = false;
$userName = '';
$userAvatar = '../assets/images/default-avatar.png';

if (isset($_SESSION['etudiant_id'])) {
    $stmt = $pdo->prepare("SELECT nom, prenom, photo FROM etudiants WHERE id_etudiant = ? AND statut = 'actif'");
    $stmt->execute([$_SESSION['etudiant_id']]);
    $etudiant = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($etudiant) {
        $isLogged = true;
        $userName = $etudiant['prenom'] . ' ' . $etudiant['nom'];
        if (!empty($etudiant['photo'])) $userAvatar = '../uploads/etudiants/' . $etudiant['photo'];
    }
}

if (isset($_SESSION['admin_id'])) {
    $stmt = $pdo->prepare("SELECT nom, prenom, role FROM administrateurs WHERE id_admin = ?");
    $stmt->execute([$_SESSION['admin_id']]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($admin) {
        $isLogged = true;
        $isAdmin = true;
        $userName = $admin['prenom'] . ' ' . $admin['nom'];
    }
}

// Charger toutes les saisons disponibles
$all_seasons = $pdo->query("
    SELECT fs.id_season, fs.label, ay.label AS academic_year
    FROM football_seasons fs
    JOIN academic_years ay ON ay.id = fs.academic_year_id
    ORDER BY fs.id_season DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Saison active
if (isset($_GET['season']) && ctype_digit($_GET['season'])) {
    $season_id = (int) $_GET['season'];
    $season = $pdo->query("
        SELECT fs.*, ay.label AS academic_year
        FROM football_seasons fs
        JOIN academic_years ay ON ay.id = fs.academic_year_id
        WHERE fs.id_season = $season_id
        LIMIT 1
    ")->fetch();
}

if (empty($season)) {
    $season = $pdo->query("
        SELECT fs.*, ay.label AS academic_year
        FROM football_seasons fs
        JOIN academic_years ay ON ay.id = fs.academic_year_id
        WHERE fs.is_active = 1
        LIMIT 1
    ")->fetch();
}

if (!$season) die("<h2>Aucune saison disponible</h2>");

$season_id = $season['id_season'];

// KPIs
$kpi = [
    'equipes' => $pdo->query("SELECT COUNT(*) FROM football_teams WHERE season_id=$season_id")->fetchColumn(),
    'poules' => $pdo->query("SELECT COUNT(*) FROM pools WHERE season_id=$season_id")->fetchColumn(),
    'matchs_joues' => $pdo->query("SELECT COUNT(*) FROM matches WHERE season_id=$season_id AND is_played=1")->fetchColumn(),
    'matchs_total' => $pdo->query("SELECT COUNT(*) FROM matches WHERE season_id=$season_id")->fetchColumn(),
    'buts' => $pdo->query("SELECT COUNT(*) FROM match_goals WHERE season_id=$season_id")->fetchColumn(),
    'phase' => ($pdo->query("SELECT COUNT(*) FROM matches WHERE season_id=$season_id AND stage='group' AND is_played=0")->fetchColumn() == 0) ? 'Phases finales' : 'Phase de poules',
    'matchs_avenir' => $pdo->query("SELECT COUNT(*) FROM matches WHERE season_id=$season_id AND match_datetime >= NOW()")->fetchColumn()
];

// Meilleur buteur
$top_scorer = $pdo->query("
    SELECT 
        tp.player_id,
        COALESCE(CONCAT(e.prenom, ' ', e.nom), tp.full_name) AS full_name,
        tp.team_id,
        t.name AS team_name,
        e.photo,
        COUNT(mg.goal_id) AS total_buts
    FROM match_goals mg
    JOIN team_players tp ON tp.player_id = mg.player_id
    JOIN football_teams t ON t.team_id = tp.team_id
    LEFT JOIN etudiants e ON e.id_etudiant = tp.user_id
    WHERE mg.season_id = $season_id
    GROUP BY tp.player_id, tp.team_id, t.name, e.prenom, e.nom, e.photo, tp.full_name
    ORDER BY total_buts DESC
    LIMIT 1
")->fetch();

// Prochain match
$next_match = $pdo->query("
    SELECT m.*, t1.name AS team1, t1.short_code AS code1, t2.name AS team2, t2.short_code AS code2,
           m.location, m.match_datetime
    FROM matches m
    JOIN football_teams t1 ON t1.team_id = m.team1_id
    JOIN football_teams t2 ON t2.team_id = m.team2_id
    WHERE m.season_id = $season_id AND m.match_datetime >= NOW()
    ORDER BY m.match_datetime ASC
    LIMIT 1
")->fetch();

// Tous les matchs
$matches = $pdo->query("
    SELECT m.*, t1.name AS team1, t2.name AS team2, t1.short_code AS code1, t2.short_code AS code2,
           p.name AS pool_name, m.location, m.match_datetime, m.score_team1, m.score_team2, m.is_played
    FROM matches m
    JOIN football_teams t1 ON t1.team_id = m.team1_id
    JOIN football_teams t2 ON t2.team_id = m.team2_id
    LEFT JOIN pools p ON m.pool_id = p.pool_id
    WHERE m.season_id = $season_id
    ORDER BY m.match_datetime ASC
")->fetchAll();

// Fan Zone
$comments = $pdo->query("
    SELECT c.*, e.nom, e.prenom, e.photo
    FROM commentaire_football c
    LEFT JOIN etudiants e ON c.id_etudiant = e.id_etudiant
    ORDER BY c.date_commentaire_football DESC
    LIMIT 20
")->fetchAll();

// Phases finales
$finals = $pdo->query("
    SELECT m.*, t1.name AS team1, t2.name AS team2, m.match_datetime, m.score_team1, m.score_team2
    FROM matches m
    JOIN football_teams t1 ON m.team1_id = t1.team_id
    JOIN football_teams t2 ON m.team2_id = t2.team_id
    WHERE m.season_id=$season_id AND m.stage != 'group'
    ORDER BY FIELD(m.stage,'quarter','semi','third_place','final'), m.round_number ASC
")->fetchAll();

// Classements par poule
$stmt = $pdo->prepare("SELECT pool_id, name, advance_count FROM pools WHERE season_id=? ORDER BY name");
$stmt->execute([$season_id]);
$poules = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Pour chaque poule, récupérer le classement
$classements = [];
foreach ($poules as $poule) {
    $stmt2 = $pdo->prepare("
        SELECT s.team_id, s.team_name, s.played, s.won, s.drawn, s.lost,
               s.goals_for, s.goals_against, s.goal_diff, s.points
        FROM vw_pool_standings s
        WHERE s.pool_id = ?
        ORDER BY s.points DESC, s.goal_diff DESC, s.goals_for DESC
    ");
    $stmt2->execute([$poule['pool_id']]);
    $classements[$poule['pool_id']] = $stmt2->fetchAll();
}

// Enregistrement d'un commentaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['message']) && !empty($_POST['user_id'])) {
    $user_id = (int) $_POST['user_id'];
    $message = trim($_POST['message']);

    if ($message !== '') {
        $stmt = $pdo->prepare("
            INSERT INTO commentaire_football (id_etudiant, message, date_commentaire_football)
            VALUES (:user_id, :message, NOW())
        ");
        $stmt->execute([
            ':user_id' => $user_id,
            ':message' => $message
        ]);
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }
}
?>

<meta charset="UTF-8">
<title>Football Universitaire - <?= htmlspecialchars($season['label']) ?> | ISSPT</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    /* =============================================
       VARIABLES & RESET OPTIMISÉES
    ============================================= */
    :root {
        --primary: #080020;
        --accent: #BA281E;
        --gold: #FFD700;
        --silver: #C0C0C0;
        --bronze: #CD7F32;
        --light: #ffffff;
        --light-gray: rgba(255, 255, 255, 0.8);
        --dark-gray: rgba(255, 255, 255, 0.6);
        --grass-green: #2E8B57;
        --field-green: #228B22;
        --glass-bg: rgba(255, 255, 255, 0.1);
        --glass-border: rgba(255, 255, 255, 0.2);
        --shadow-sm: 0 4px 6px rgba(0, 0, 0, 0.1);
        --shadow-md: 0 8px 30px rgba(0, 0, 0, 0.2);
        --shadow-lg: 0 15px 50px rgba(0, 0, 0, 0.3);
        --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        --radius-md: 12px;
        --radius-lg: 20px;
        --radius-xl: 30px;
    }

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        background: linear-gradient(135deg, var(--primary) 0%, #0a001f 100%);
        color: var(--light);
        line-height: 1.6;
        overflow-x: hidden;
        min-height: 100vh;
    }

    .container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 0 20px;
    }

    section {
        padding: 80px 0;
    }

    .section-title {
        text-align: center;
        font-size: 2.5rem;
        font-weight: 700;
        margin-bottom: 20px;
        position: relative;
        display: inline-block;
        left: 50%;
        transform: translateX(-50%);
    }

    .section-title::after {
        content: '';
        position: absolute;
        bottom: -10px;
        left: 50%;
        transform: translateX(-50%);
        width: 80px;
        height: 4px;
        background: var(--accent);
        border-radius: 2px;
    }

    .section-subtitle {
        text-align: center;
        color: var(--light-gray);
        font-size: 1.1rem;
        margin-bottom: 50px;
        max-width: 600px;
        margin-left: auto;
        margin-right: auto;
    }

    /* =============================================
       HERO SECTION - OPTIMISÉE
    ============================================= */
    .hero-section {
        position: relative;
        min-height: 70vh;
        display: flex;
        align-items: center;
        background: 
            linear-gradient(rgba(8, 0, 32, 0.9), rgba(8, 0, 32, 0.7)),
            url('https://images.unsplash.com/photo-1575361204480-aadea25e6e68?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80');
        background-size: cover;
        background-position: center;
        background-attachment: fixed;
        padding: 60px 0;
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
            radial-gradient(circle at 20% 30%, rgba(186, 40, 30, 0.3) 0%, transparent 50%),
            radial-gradient(circle at 80% 70%, rgba(34, 139, 34, 0.2) 0%, transparent 50%);
        z-index: 1;
    }

    .hero-content {
        position: relative;
        z-index: 2;
        text-align: center;
        max-width: 1000px;
        margin: 0 auto;
        animation: fadeInUp 1s ease-out;
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

    .hero-title {
        font-size: 3.5rem;
        font-weight: 800;
        margin-bottom: 15px;
        text-shadow: 0 2px 10px rgba(0, 0, 0, 0.5);
        line-height: 1.2;
        background: linear-gradient(to right, var(--light), var(--gold));
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
    }

    .hero-subtitle {
        font-size: 1.3rem;
        color: var(--light-gray);
        margin-bottom: 30px;
        max-width: 600px;
        margin-left: auto;
        margin-right: auto;
    }

    .season-selector-container {
        background: var(--glass-bg);
        backdrop-filter: blur(10px);
        border: 1px solid var(--glass-border);
        border-radius: var(--radius-md);
        padding: 20px;
        margin: 30px auto;
        max-width: 400px;
        position: relative;
        transition: var(--transition);
    }

    .season-selector-container:hover {
        transform: translateY(-5px);
        box-shadow: var(--shadow-md);
        border-color: var(--accent);
    }

    .season-selector-container label {
        display: flex;
        align-items: center;
        gap: 10px;
        font-weight: 600;
        margin-bottom: 12px;
        color: var(--gold);
    }

    .season-selector {
        width: 100%;
        padding: 12px 15px;
        background: rgba(0, 0, 0, 0.3);
        border: 1px solid var(--glass-border);
        border-radius: 8px;
        color: var(--light);
        font-size: 1rem;
        cursor: pointer;
        transition: var(--transition);
        appearance: none;
        background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='white'%3e%3cpath d='M7 10l5 5 5-5z'/%3e%3c/svg%3e");
        background-repeat: no-repeat;
        background-position: right 10px center;
        background-size: 20px;
    }

    .season-selector:hover {
        border-color: var(--accent);
        box-shadow: 0 0 0 2px rgba(186, 40, 30, 0.3);
    }

    .season-selector:focus {
        outline: none;
        border-color: var(--gold);
    }

    .hero-stats {
        display: flex;
        gap: 20px;
        justify-content: center;
        flex-wrap: wrap;
        margin-top: 40px;
    }

    .stat-item {
        background: var(--glass-bg);
        backdrop-filter: blur(10px);
        border: 1px solid var(--glass-border);
        border-radius: var(--radius-md);
        padding: 15px 25px;
        min-width: 150px;
        text-align: center;
        transition: var(--transition);
        animation: fadeInUp 0.8s ease-out;
        animation-fill-mode: both;
    }

    .stat-item:nth-child(1) { animation-delay: 0.1s; }
    .stat-item:nth-child(2) { animation-delay: 0.2s; }
    .stat-item:nth-child(3) { animation-delay: 0.3s; }
    .stat-item:nth-child(4) { animation-delay: 0.4s; }

    .stat-item:hover {
        transform: translateY(-5px);
        border-color: var(--accent);
        box-shadow: var(--shadow-md);
    }

    .stat-value {
        display: block;
        font-size: 2.2rem;
        font-weight: 700;
        color: var(--gold);
        line-height: 1;
        margin-bottom: 5px;
    }

    .stat-label {
        font-size: 0.9rem;
        color: var(--light-gray);
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    /* =============================================
       KPI SECTION - OPTIMISÉE
    ============================================= */
    .kpi-section {
        background: linear-gradient(to bottom, transparent, rgba(8, 0, 32, 0.3));
    }

    .kpi-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 25px;
        margin-bottom: 40px;
    }

    .kpi-card {
        background: linear-gradient(135deg, rgba(255, 255, 255, 0.08) 0%, rgba(255, 255, 255, 0.04) 100%);
        border: 1px solid var(--glass-border);
        border-radius: var(--radius-lg);
        padding: 25px;
        text-align: center;
        transition: var(--transition);
        position: relative;
        overflow: hidden;
        cursor: pointer;
    }

    .kpi-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: linear-gradient(90deg, var(--accent), var(--gold));
        border-radius: var(--radius-lg) var(--radius-lg) 0 0;
    }

    .kpi-card::after {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
        transition: left 0.6s;
    }

    .kpi-card:hover::after {
        left: 100%;
    }

    .kpi-card:hover {
        transform: translateY(-10px) scale(1.02);
        box-shadow: var(--shadow-lg);
        border-color: var(--accent);
    }

    .kpi-icon {
        font-size: 2.5rem;
        color: var(--gold);
        margin-bottom: 15px;
        transition: transform 0.3s;
    }

    .kpi-card:hover .kpi-icon {
        transform: scale(1.1) rotate(5deg);
    }

    .kpi-value {
        font-size: 2.8rem;
        font-weight: 800;
        color: var(--light);
        margin-bottom: 10px;
        line-height: 1;
        text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
    }

    .kpi-label {
        font-size: 0.9rem;
        color: var(--light-gray);
        text-transform: uppercase;
        letter-spacing: 1.5px;
        font-weight: 500;
    }

    /* =============================================
       TOP SCORER SECTION - OPTIMISÉE
    ============================================= */
    .top-scorer-section {
        background: linear-gradient(135deg, rgba(186, 40, 30, 0.1) 0%, rgba(34, 139, 34, 0.1) 100%);
        border-radius: var(--radius-xl);
        padding: 40px;
        margin: 40px 0;
        border: 1px solid rgba(255, 215, 0, 0.2);
        box-shadow: var(--shadow-md);
    }

    .top-scorer-card {
        display: flex;
        align-items: center;
        gap: 40px;
        background: linear-gradient(135deg, rgba(255, 255, 255, 0.1) 0%, rgba(255, 255, 255, 0.05) 100%);
        border: 1px solid rgba(255, 215, 0, 0.3);
        border-radius: var(--radius-lg);
        padding: 30px;
        position: relative;
        overflow: hidden;
        transition: var(--transition);
    }

    .top-scorer-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        border-color: var(--gold);
    }

    .top-scorer-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: radial-gradient(circle at 80% 20%, rgba(255, 215, 0, 0.1) 0%, transparent 50%);
        pointer-events: none;
    }

    .player-avatar {
        flex-shrink: 0;
        width: 150px;
        height: 150px;
        border-radius: 50%;
        overflow: hidden;
        border: 4px solid var(--gold);
        box-shadow: 0 0 30px rgba(255, 215, 0, 0.4);
        position: relative;
        transition: var(--transition);
    }

    .top-scorer-card:hover .player-avatar {
        transform: scale(1.05);
        box-shadow: 0 0 40px rgba(255, 215, 0, 0.6);
    }

    .player-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.5s;
    }

    .player-avatar:hover img {
        transform: scale(1.1);
    }

    .player-badge {
        position: absolute;
        top: 10px;
        right: 10px;
        background: var(--gold);
        color: var(--primary);
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 1.2rem;
        box-shadow: 0 0 15px rgba(255, 215, 0, 0.5);
        animation: pulseGold 2s infinite;
    }

    @keyframes pulseGold {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.1); }
    }

    .player-info {
        flex: 1;
    }

    .player-title {
        font-size: 0.9rem;
        text-transform: uppercase;
        letter-spacing: 2px;
        color: var(--gold);
        margin-bottom: 10px;
        font-weight: 600;
    }

    .player-name {
        font-size: 2.2rem;
        font-weight: 700;
        margin-bottom: 10px;
        color: var(--light);
        text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
    }

    .player-team {
        font-size: 1.2rem;
        color: var(--light-gray);
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .player-stats {
        display: flex;
        gap: 30px;
        margin-top: 20px;
    }

    .stat-box {
        text-align: center;
        padding: 15px;
        background: rgba(0, 0, 0, 0.2);
        border-radius: var(--radius-md);
        transition: var(--transition);
        min-width: 120px;
    }

    .stat-box:hover {
        background: rgba(186, 40, 30, 0.2);
        transform: translateY(-3px);
    }

    .stat-number {
        display: block;
        font-size: 2.5rem;
        font-weight: 700;
        color: var(--accent);
        line-height: 1;
        margin-bottom: 5px;
    }

    .stat-text {
        font-size: 0.9rem;
        color: var(--light-gray);
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    /* =============================================
       NEXT MATCH SECTION - OPTIMISÉE
    ============================================= */
    .next-match-section {
        background: linear-gradient(135deg, var(--field-green) 0%, #1a7a1a 100%);
        border-radius: var(--radius-xl);
        padding: 50px;
        margin: 60px 0;
        position: relative;
        overflow: hidden;
        box-shadow: var(--shadow-lg);
        border: 1px solid rgba(255, 255, 255, 0.2);
        animation: pulseBorder 3s infinite;
    }

    @keyframes pulseBorder {
        0%, 100% { box-shadow: var(--shadow-lg); }
        50% { box-shadow: 0 0 40px rgba(34, 139, 34, 0.6); }
    }

    .next-match-section::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: 
            url("data:image/svg+xml,%3Csvg width='100' height='100' viewBox='0 0 100 100' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M11 18c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm48 25c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm-43-7c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm63 31c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM34 90c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm56-76c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM12 86c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm28-65c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm23-11c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-6 60c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm29 22c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zM32 63c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm57-13c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-9-21c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM60 91c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM35 41c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM12 60c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2z' fill='%23ffffff' fill-opacity='0.1' fill-rule='evenodd'/%3E%3C/svg%3E");
        opacity: 0.3;
        pointer-events: none;
    }

    .match-header {
        text-align: center;
        margin-bottom: 40px;
        position: relative;
        z-index: 1;
    }

    .match-header h2 {
        font-size: 2.2rem;
        font-weight: 700;
        margin-bottom: 10px;
        color: var(--light);
        text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
    }

    .match-timer {
        font-size: 1.1rem;
        color: rgba(255, 255, 255, 0.9);
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
    }

    .match-teams {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 60px;
        margin-bottom: 40px;
        position: relative;
        z-index: 1;
    }

    .team {
        text-align: center;
        flex: 1;
        max-width: 300px;
        transition: var(--transition);
    }

    .team:hover {
        transform: translateY(-5px);
    }

    .team-logo {
        width: 120px;
        height: 120px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 50%;
        margin: 0 auto 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2.5rem;
        font-weight: 700;
        color: var(--light);
        border: 3px solid rgba(255, 255, 255, 0.3);
        transition: var(--transition);
    }

    .team:hover .team-logo {
        transform: scale(1.1);
        border-color: var(--gold);
        background: rgba(255, 255, 255, 0.2);
    }

    .team-name {
        font-size: 1.8rem;
        font-weight: 600;
        margin-bottom: 10px;
        color: var(--light);
        text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
    }

    .team-code {
        font-size: 1.2rem;
        color: rgba(255, 255, 255, 0.8);
    }

    .vs-separator {
        font-size: 3rem;
        font-weight: 700;
        color: var(--gold);
        text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
        animation: pulse 2s infinite;
    }

    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.7; }
    }

    .match-info {
        text-align: center;
        background: rgba(0, 0, 0, 0.2);
        border-radius: var(--radius-md);
        padding: 20px;
        max-width: 500px;
        margin: 0 auto;
        position: relative;
        z-index: 1;
        border: 1px solid rgba(255, 255, 255, 0.1);
    }

    .match-datetime {
        font-size: 1.3rem;
        font-weight: 600;
        color: var(--gold);
        margin-bottom: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
    }

    .match-location {
        font-size: 1.1rem;
        color: rgba(255, 255, 255, 0.9);
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
    }

    /* =============================================
       MATCHES CAROUSEL - OPTIMISÉ
    ============================================= */
    .matches-carousel-section {
        position: relative;
        overflow: hidden;
        background: linear-gradient(to bottom, rgba(8, 0, 32, 0.5), transparent);
    }

    .carousel-container {
        position: relative;
        max-width: 1200px;
        margin: 0 auto;
    }

    .carousel-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
        flex-wrap: wrap;
        gap: 20px;
    }

    .carousel-nav {
        display: flex;
        gap: 10px;
    }

    .carousel-btn {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: var(--glass-bg);
        border: 1px solid var(--glass-border);
        color: var(--light);
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: var(--transition);
    }

    .carousel-btn:hover {
        background: var(--accent);
        transform: scale(1.1);
    }

    .carousel-track {
        display: flex;
        gap: 25px;
        overflow-x: auto;
        padding: 20px 10px;
        scroll-behavior: smooth;
        scrollbar-width: thin;
        scrollbar-color: var(--accent) transparent;
    }
    .carousel-tracke {
        gap: 25px;
        overflow-x: auto;
        padding: 20px 10px;
        scroll-behavior: smooth;
        scrollbar-width: thin;
        scrollbar-color: var(--accent) transparent;
    }

    .carousel-track::-webkit-scrollbar {
        height: 6px;
    }

    .carousel-track::-webkit-scrollbar-track {
        background: rgba(255, 255, 255, 0.1);
        border-radius: 3px;
    }

    .carousel-track::-webkit-scrollbar-thumb {
        background: var(--accent);
        border-radius: 3px;
    }

    .match-card {
        flex: 0 0 300px;
        background: linear-gradient(135deg, rgba(255, 255, 255, 0.08) 0%, rgba(255, 255, 255, 0.04) 100%);
        border: 1px solid var(--glass-border);
        border-radius: var(--radius-lg);
        padding: 25px;
        transition: var(--transition);
        position: relative;
        overflow: hidden;
        cursor: pointer;
    }

    .match-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: linear-gradient(90deg, var(--accent), transparent);
        border-radius: var(--radius-lg) var(--radius-lg) 0 0;
    }

    .match-card:hover {
        transform: translateY(-10px) scale(1.02);
        box-shadow: var(--shadow-lg);
        border-color: var(--accent);
    }

    .match-status {
        position: absolute;
        top: 15px;
        right: 15px;
        padding: 5px 15px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 1px;
        z-index: 2;
    }

    .status-upcoming {
        background: rgba(33, 150, 243, 0.3);
        color: #2196F3;
        border: 1px solid #2196F3;
    }

    .status-live {
        background: rgba(255, 235, 59, 0.3);
        color: var(--gold);
        border: 1px solid var(--gold);
        animation: pulse 2s infinite;
    }

    .status-finished {
        background: rgba(76, 175, 80, 0.3);
        color: #4CAF50;
        border: 1px solid #4CAF50;
    }

    .match-teams-small {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }

    .team-small {
        text-align: center;
        flex: 1;
    }

    .team-logo-small {
        width: 60px;
        height: 60px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 50%;
        margin: 0 auto 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        font-weight: 700;
        color: var(--light);
        border: 2px solid rgba(255, 255, 255, 0.2);
        transition: var(--transition);
    }

    .match-card:hover .team-logo-small {
        transform: scale(1.1);
        border-color: var(--accent);
    }

    .team-name-small {
        font-size: 1rem;
        font-weight: 600;
        color: var(--light);
        margin-bottom: 5px;
        transition: var(--transition);
    }

    .match-card:hover .team-name-small {
        color: var(--gold);
    }

    .team-code-small {
        font-size: 0.9rem;
        color: var(--light-gray);
    }

    .match-score {
        font-size: 2.5rem;
        font-weight: 700;
        color: var(--gold);
        text-align: center;
        margin: 20px 0;
        text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
    }

    .match-details {
        text-align: center;
        color: var(--light-gray);
        font-size: 0.9rem;
        margin-top: 15px;
        display: flex;
        flex-direction: column;
        gap: 5px;
    }

    .match-details i {
        margin-right: 5px;
        color: var(--accent);
    }

    /* =============================================
       STANDINGS SECTION - OPTIMISÉE
    ============================================= */
    .standings-section {
        background: linear-gradient(to bottom, rgba(8, 0, 32, 0.5), var(--primary));
        border-radius: var(--radius-xl);
        padding: 50px;
        margin: 40px 0;
        border: 1px solid var(--glass-border);
        box-shadow: var(--shadow-md);
    }

    .tabs-container {
        max-width: 1000px;
        margin: 0 auto;
    }

    .tabs-header {
        display: flex;
        gap: 10px;
        margin-bottom: 30px;
        flex-wrap: wrap;
        justify-content: center;
    }

    .tab-btn {
        background: var(--glass-bg);
        color: var(--light);
        border: 1px solid var(--glass-border);
        border-radius: var(--radius-md);
        padding: 12px 24px;
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        transition: var(--transition);
        position: relative;
        overflow: hidden;
    }

    .tab-btn::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 50%;
        width: 0;
        height: 3px;
        background: var(--accent);
        transition: all 0.3s;
        transform: translateX(-50%);
    }

    .tab-btn:hover::after {
        width: 80%;
    }

    .tab-btn:hover {
        background: rgba(186, 40, 30, 0.2);
        border-color: var(--accent);
    }

    .tab-btn.active {
        background: var(--accent);
        color: var(--light);
        border-color: var(--accent);
        box-shadow: 0 4px 15px rgba(186, 40, 30, 0.3);
    }

    .tab-btn.active::after {
        width: 80%;
        background: var(--gold);
    }

    .tab-content {
        display: none;
        animation: fadeIn 0.5s ease;
    }

    .tab-content.active {
        display: block;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .standings-table {
        width: 100%;
        border-collapse: collapse;
        background: rgba(255, 255, 255, 0.05);
        border-radius: var(--radius-md);
        overflow: hidden;
        box-shadow: var(--shadow-md);
    }

    .standings-table thead {
        background: linear-gradient(135deg, var(--accent) 0%, #d43f3f 100%);
    }

    .standings-table th {
        padding: 15px;
        text-align: center;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 1px;
        font-size: 0.9rem;
        color: var(--light);
        border-bottom: 2px solid rgba(255, 255, 255, 0.1);
    }

    .standings-table tbody tr {
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        transition: var(--transition);
    }

    .standings-table tbody tr:nth-child(even) {
        background: rgba(255, 255, 255, 0.02);
    }

    .standings-table tbody tr:hover {
        background: rgba(186, 40, 30, 0.1);
        transform: translateX(5px);
    }

    .standings-table tbody tr.qualified {
        background: rgba(76, 175, 80, 0.1);
        border-left: 4px solid #4CAF50;
        position: relative;
    }

    .standings-table tbody tr.qualified::before {
        content: '✓';
        position: absolute;
        left: 5px;
        top: 50%;
        transform: translateY(-50%);
        color: #4CAF50;
        font-weight: bold;
    }

    .standings-table tbody tr.noqualified {
        background: rgba(76, 175, 80, 0.1);
        border-left: 4px solid #d12d2d;
        position: relative;
    }

    .standings-table tbody tr.noqualified::before {
        content: '*';
        position: absolute;
        left: 5px;
        top: 50%;
        transform: translateY(-50%);
        color: #d12d2d;
        font-weight: bold;
    }

    .standings-table td {
        padding: 12px;
        text-align: center;
        font-size: 0.95rem;
    }

    .team-position {
        font-weight: 700;
        color: var(--gold);
        width: 40px;
    }

    .team-name-cell {
        text-align: left;
        font-weight: 600;
        color: var(--light);
        padding-left: 20px;
    }

    /* =============================================
       MATCH CENTER SECTION - OPTIMISÉE
    ============================================= */
    .match-center-section {
        background: linear-gradient(135deg, rgba(8, 0, 32, 0.8) 0%, rgba(26, 11, 46, 0.8) 100%);
        border-radius: var(--radius-xl);
        padding: 50px;
        border: 1px solid var(--glass-border);
        box-shadow: var(--shadow-md);
    }

    .match-center-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
        flex-wrap: wrap;
        gap: 20px;
    }

    .match-center-filters {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }

    .filter-btn {
        background: var(--glass-bg);
        color: var(--light);
        border: 1px solid var(--glass-border);
        border-radius: var(--radius-md);
        padding: 8px 16px;
        font-size: 0.9rem;
        cursor: pointer;
        transition: var(--transition);
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .filter-btn:hover {
        background: rgba(186, 40, 30, 0.2);
        transform: translateY(-2px);
    }

    .filter-btn.active {
        background: var(--accent);
        border-color: var(--accent);
        box-shadow: 0 4px 10px rgba(186, 40, 30, 0.3);
    }

    .match-center-table {
        width: 100%;
        border-collapse: collapse;
        background: rgba(255, 255, 255, 0.05);
        border-radius: var(--radius-md);
        overflow: hidden;
        box-shadow: var(--shadow-sm);
    }

    .match-center-table thead {
        background: linear-gradient(135deg, rgba(186, 40, 30, 0.3) 0%, rgba(255, 215, 0, 0.2) 100%);
    }

    .match-center-table th {
        padding: 15px;
        text-align: center;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 1px;
        font-size: 0.85rem;
        color: var(--light);
        border-bottom: 2px solid rgba(255, 255, 255, 0.1);
    }

    .match-center-table tbody tr {
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        transition: var(--transition);
    }

    .match-center-table tbody tr:hover {
        background: rgba(186, 40, 30, 0.1);
    }

    .match-center-table td {
        padding: 12px;
        text-align: center;
        font-size: 0.9rem;
    }

    .match-center-table .match-score-cell {
        font-weight: 700;
        color: var(--gold);
        font-size: 1.1rem;
    }

    /* =============================================
       FAN ZONE SECTION - OPTIMISÉE
    ============================================= */
    .fan-zone-section {
        background: linear-gradient(135deg, rgba(186, 40, 30, 0.1) 0%, rgba(34, 139, 34, 0.1) 100%);
        border-radius: var(--radius-xl);
        padding: 50px;
        margin: 40px 0;
        border: 1px solid var(--glass-border);
        box-shadow: var(--shadow-md);
    }

    .comment-form {
        background: var(--glass-bg);
        backdrop-filter: blur(10px);
        border: 1px solid var(--glass-border);
        border-radius: var(--radius-lg);
        padding: 30px;
        margin-bottom: 40px;
        box-shadow: var(--shadow-md);
        transition: var(--transition);
    }

    .comment-form:hover {
        border-color: var(--accent);
        box-shadow: 0 10px 30px rgba(186, 40, 30, 0.2);
    }

    .comment-form h3 {
        font-size: 1.5rem;
        margin-bottom: 20px;
        color: var(--light);
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .comment-form textarea {
        width: 100%;
        background: rgba(0, 0, 0, 0.3);
        border: 1px solid var(--glass-border);
        border-radius: var(--radius-md);
        padding: 15px;
        color: var(--light);
        font-size: 1rem;
        resize: vertical;
        min-height: 100px;
        transition: var(--transition);
        margin-bottom: 15px;
        font-family: inherit;
    }

    .comment-form textarea:focus {
        outline: none;
        border-color: var(--accent);
        box-shadow: 0 0 0 2px rgba(186, 40, 30, 0.3);
        background: rgba(0, 0, 0, 0.4);
    }

    .form-actions {
        display: flex;
        justify-content: flex-end;
        gap: 15px;
    }

    .submit-btn {
        background: var(--accent);
        color: var(--light);
        border: none;
        border-radius: var(--radius-md);
        padding: 12px 30px;
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        transition: var(--transition);
        display: flex;
        align-items: center;
        gap: 8px;
        position: relative;
        overflow: hidden;
    }

    .submit-btn::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
        transition: left 0.6s;
    }

    .submit-btn:hover::before {
        left: 100%;
    }

    .submit-btn:hover {
        background: #d43f3f;
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(186, 40, 30, 0.4);
    }

    .comments-container {
        max-height: 600px;
        overflow-y: auto;
        padding-right: 10px;
    }

    .comments-container::-webkit-scrollbar {
        width: 6px;
    }

    .comments-container::-webkit-scrollbar-track {
        background: rgba(255, 255, 255, 0.1);
        border-radius: 3px;
    }

    .comments-container::-webkit-scrollbar-thumb {
        background: var(--accent);
        border-radius: 3px;
    }

    .comment-item {
        background: var(--glass-bg);
        border: 1px solid var(--glass-border);
        border-radius: var(--radius-md);
        padding: 20px;
        margin-bottom: 15px;
        transition: var(--transition);
        position: relative;
        animation: slideIn 0.5s ease;
        cursor: pointer;
    }

    .comment-item:hover {
        transform: translateX(5px);
        border-color: var(--accent);
        background: rgba(255, 255, 255, 0.05);
    }

    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateX(-20px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }

    .comment-header {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 12px;
    }

    .comment-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid var(--accent);
        transition: var(--transition);
    }

    .comment-item:hover .comment-avatar {
        transform: scale(1.1);
        border-color: var(--gold);
    }

    .comment-author {
        flex: 1;
    }

    .comment-name {
        font-weight: 600;
        color: var(--light);
        margin-bottom: 2px;
        transition: var(--transition);
    }

    .comment-item:hover .comment-name {
        color: var(--gold);
    }

    .comment-time {
        font-size: 0.85rem;
        color: var(--light-gray);
    }

    .comment-content {
        color: var(--light-gray);
        line-height: 1.5;
        margin-left: 52px;
        transition: var(--transition);
    }

    .comment-item:hover .comment-content {
        color: var(--light);
    }

    /* =============================================
       FINALS SECTION - OPTIMISÉE
    ============================================= */
    .finals-section {
        background: linear-gradient(135deg, rgba(255, 215, 0, 0.1) 0%, rgba(192, 192, 192, 0.1) 100%);
        border-radius: var(--radius-xl);
        padding: 50px;
        margin: 40px 0;
        border: 1px solid rgba(255, 215, 0, 0.2);
        box-shadow: var(--shadow-md);
    }

    .finals-bracket {
        display: flex;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 30px;
        max-width: 1200px;
        margin: 0 auto;
    }

    .finals-stage {
        flex: 1;
        min-width: 250px;
        background: rgba(255, 255, 255, 0.05);
        border-radius: var(--radius-lg);
        padding: 20px;
        border: 1px solid rgba(255, 255, 255, 0.1);
        transition: var(--transition);
    }

    .finals-stage:hover {
        transform: translateY(-5px);
        border-color: var(--gold);
        box-shadow: var(--shadow-md);
    }

    .stage-title {
        font-size: 1.3rem;
        font-weight: 700;
        color: var(--gold);
        margin-bottom: 20px;
        text-align: center;
        padding-bottom: 10px;
        border-bottom: 2px solid rgba(255, 215, 0, 0.3);
        text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
    }

    .match-item {
        background: var(--glass-bg);
        border: 1px solid var(--glass-border);
        border-radius: var(--radius-md);
        padding: 15px;
        margin-bottom: 15px;
        transition: var(--transition);
        cursor: pointer;
    }

    .match-item:hover {
        transform: translateY(-3px);
        border-color: var(--gold);
        box-shadow: var(--shadow-md);
        background: rgba(255, 255, 255, 0.1);
    }

    .match-teams-compact {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
    }

    .team-compact {
        flex: 1;
        text-align: center;
        font-weight: 600;
        color: var(--light);
        transition: var(--transition);
    }

    .match-item:hover .team-compact {
        color: var(--gold);
    }

    .score-compact {
        font-weight: 700;
        color: var(--gold);
        margin: 0 15px;
        font-size: 1.2rem;
        text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
    }

    .match-info-compact {
        text-align: center;
        font-size: 0.85rem;
        color: var(--light-gray);
        margin-top: 5px;
    }

    /* =============================================
       RESPONSIVE DESIGN - OPTIMISÉE
    ============================================= */
    @media (max-width: 1200px) {
        .hero-title {
            font-size: 3rem;
        }
        
        .kpi-grid {
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        }
        
        .match-teams {
            gap: 30px;
        }
        
        .finals-bracket {
            justify-content: center;
        }
    }

    @media (max-width: 992px) {
        .hero-title {
            font-size: 2.5rem;
        }
        
        .top-scorer-card {
            flex-direction: column;
            text-align: center;
            gap: 25px;
        }
        
        .player-stats {
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .match-teams {
            flex-direction: column;
            gap: 40px;
        }
        
        .team {
            max-width: none;
        }
        
        .tabs-header {
            flex-direction: column;
            align-items: center;
        }
        
        .tab-btn {
            width: 100%;
            max-width: 300px;
        }
        
        .match-center-header {
            flex-direction: column;
            text-align: center;
        }
        
        .match-center-filters {
            justify-content: center;
        }
        
        .carousel-header {
            flex-direction: column;
            text-align: center;
        }
        
        .carousel-nav {
            justify-content: center;
        }
    }

    @media (max-width: 768px) {
        section {
            padding: 60px 0;
        }
        
        .section-title {
            font-size: 2rem;
        }
        
        .hero-title {
            font-size: 2rem;
        }
        
        .hero-subtitle {
            font-size: 1.1rem;
        }
        
        .kpi-card {
            padding: 20px;
        }
        
        .kpi-value {
            font-size: 2.2rem;
        }
        
        .match-card {
            flex: 0 0 280px;
        }
        
        .standings-section,
        .match-center-section,
        .fan-zone-section,
        .finals-section {
            padding: 30px 20px;
        }
        
        .comment-form {
            padding: 20px;
        }
        
        .player-name {
            font-size: 1.8rem;
        }
        
        .player-stats {
            flex-direction: column;
            gap: 15px;
        }
        
        .stat-box {
            min-width: auto;
            width: 100%;
        }
    }

    @media (max-width: 480px) {
        .container {
            padding: 0 15px;
        }
        
        .section-title {
            font-size: 1.6rem;
        }
        
        .hero-title {
            font-size: 1.8rem;
        }
        
        .hero-stats {
            flex-direction: column;
            align-items: center;
        }
        
        .stat-item {
            width: 100%;
            max-width: 200px;
        }
        
        .kpi-grid {
            grid-template-columns: 1fr;
        }
        
        .next-match-section {
            padding: 30px 20px;
        }
        
        .team-logo {
            width: 80px;
            height: 80px;
            font-size: 1.8rem;
        }
        
        .team-name {
            font-size: 1.4rem;
        }
        
        .vs-separator {
            font-size: 2rem;
        }
        
        .match-status {
            font-size: 0.7rem;
            padding: 3px 10px;
        }
        
        .match-score {
            font-size: 2rem;
        }
        
        .form-actions {
            flex-direction: column;
        }
        
        .submit-btn {
            width: 100%;
            justify-content: center;
        }
    }
</style>

    
<?php include '../includes/header.php'; ?>

<!-- ============================
     HERO SECTION
============================= -->
<section class="hero-section">
    <div class="container">
        <div class="hero-content">
            <h1 class="hero-title">Football Universitaire</h1>
            <p class="hero-subtitle">Tournoi inter-classes - Saison <?= htmlspecialchars($season['label']) ?></p>
            
            <div class="season-selector-container">
                <label for="season-select">
                    <i class="fas fa-futbol"></i> Sélectionner une saison
                </label>
                <form method="GET" class="season-selector-form">
                    <select name="season" id="season-select" class="season-selector" onchange="this.form.submit()">
                        <?php foreach ($all_seasons as $s): ?>
                            <option value="<?= $s['id_season'] ?>" 
                                <?= $s['id_season'] == $season_id ? 'selected' : '' ?>>
                                <?= htmlspecialchars($s['label']) ?> - 
                                <?= htmlspecialchars($s['academic_year']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>

            <div class="hero-stats">
                <div class="stat-item">
                    <span class="stat-value"><?= $kpi['equipes'] ?></span>
                    <span class="stat-label">Équipes</span>
                </div>
                <div class="stat-item">
                    <span class="stat-value"><?= $kpi['matchs_joues'] ?>/<?= $kpi['matchs_total'] ?></span>
                    <span class="stat-label">Matchs</span>
                </div>
                <div class="stat-item">
                    <span class="stat-value"><?= $kpi['buts'] ?></span>
                    <span class="stat-label">Buts</span>
                </div>
                <div class="stat-item">
                    <span class="stat-value"><?= $kpi['phase'] ?></span>
                    <span class="stat-label">Phase</span>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============================
     KPI SECTION
============================= -->
<section class="kpi-section">
    <div class="container">
        <h2 class="section-title">Statistiques de la Saison</h2>
        <p class="section-subtitle">Vue d'ensemble des performances et indicateurs clés</p>
        
        <div class="kpi-grid">
            <div class="kpi-card">
                <div class="kpi-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="kpi-value"><?= $kpi['equipes'] ?></div>
                <div class="kpi-label">Équipes</div>
            </div>
            
            <div class="kpi-card">
                <div class="kpi-icon">
                    <i class="fas fa-trophy"></i>
                </div>
                <div class="kpi-value"><?= $kpi['poules'] ?></div>
                <div class="kpi-label">Poules</div>
            </div>
            
            <div class="kpi-card">
                <div class="kpi-icon">
                    <i class="fas fa-futbol"></i>
                </div>
                <div class="kpi-value"><?= $kpi['matchs_joues'] ?>/<?= $kpi['matchs_total'] ?></div>
                <div class="kpi-label">Matchs Joués</div>
            </div>
            
            <div class="kpi-card">
                <div class="kpi-icon">
                    <i class="fas fa-futbol"></i>
                </div>
                <div class="kpi-value"><?= $kpi['buts'] ?></div>
                <div class="kpi-label">Buts Marqués</div>
            </div>
            
            <div class="kpi-card">
                <div class="kpi-icon">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div class="kpi-value"><?= $kpi['matchs_avenir'] ?></div>
                <div class="kpi-label">Matchs à Venir</div>
            </div>
        </div>
    </div>
</section>

<!-- ============================
     TOP SCORER SECTION
============================= -->
<?php if ($top_scorer): ?>
<section class="top-scorer-section">
    <div class="container">
        <div class="top-scorer-card">
            <div class="player-avatar">
                <img src="<?= htmlspecialchars($top_scorer['photo'] ? '../admins/uploads/photos_etudiants/' . $top_scorer['photo'] : '../assets/images/default-avatar.png') ?>" 
                     alt="<?= htmlspecialchars($top_scorer['full_name']) ?>"
                     onerror="this.src='../assets/images/default-avatar.png'">
                <div class="player-badge">1</div>
            </div>
            <div class="player-info">
                <div class="player-title">🏆 Meilleur Buteur</div>
                <h3 class="player-name"><?= htmlspecialchars($top_scorer['full_name']) ?></h3>
                <div class="player-team">
                    <i class="fas fa-shield-alt"></i>
                    <?= htmlspecialchars($top_scorer['team_name']) ?>
                </div>
                <div class="player-stats">
                    <div class="stat-box">
                        <span class="stat-number"><?= (int)$top_scorer['total_buts'] ?></span>
                        <span class="stat-text">Buts</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ============================
     NEXT MATCH SECTION
============================= -->
<?php if ($next_match): ?>
<section class="next-match-section">
    <div class="container">
        <div class="match-header">
            <h2>Prochain Match</h2>
            <div class="match-timer">
                <i class="far fa-clock"></i>
                <?= date("d F Y - H:i", strtotime($next_match['match_datetime'])) ?>
            </div>
        </div>
        
        <div class="match-teams">
            <div class="team">
                <div class="team-logo"><?= htmlspecialchars($next_match['code1']) ?></div>
                <h3 class="team-name"><?= htmlspecialchars($next_match['team1']) ?></h3>
                <div class="team-code"><?= htmlspecialchars($next_match['code1']) ?></div>
            </div>
            
            <div class="vs-separator">VS</div>
            
            <div class="team">
                <div class="team-logo"><?= htmlspecialchars($next_match['code2']) ?></div>
                <h3 class="team-name"><?= htmlspecialchars($next_match['team2']) ?></h3>
                <div class="team-code"><?= htmlspecialchars($next_match['code2']) ?></div>
            </div>
        </div>
        
        <div class="match-info">
            <div class="match-datetime">
                <i class="far fa-calendar-alt"></i>
                <?= date("l d F Y", strtotime($next_match['match_datetime'])) ?>
            </div>
            <div class="match-location">
                <i class="fas fa-map-marker-alt"></i>
                <?= htmlspecialchars($next_match['location'] ?? 'Stade Universitaire') ?>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ============================
     MATCHES CAROUSEL
============================= -->
<section class="matches-carousel-section">
    <div class="container">
        <div class="carousel-header">
            <h2 class="section-title">Calendrier des Matchs</h2>
            <div class="carousel-nav">
                <button class="carousel-btn prev-btn">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <button class="carousel-btn next-btn">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </div>
        
        <div class="carousel-container">
            <div class="carousel-track" id="matches-carousel">
                <?php if (!empty($matches)): ?>
                    <?php foreach($matches as $m): 
                        $status_class = '';
                        $status_text = '';
                        if ($m['is_played'] == 0) {
                            $status_class = 'status-upcoming';
                            $status_text = 'À venir';
                        } elseif ($m['is_played'] == 1 && $m['score_team1'] !== null) {
                            $status_class = 'status-finished';
                            $status_text = 'Terminé';
                        } else {
                            $status_class = 'status-live';
                            $status_text = 'En direct';
                        }
                    ?>
                        <div class="match-card">
                            <span class="match-status <?= $status_class ?>"><?= $status_text ?></span>
                            
                            <div class="match-teams-small">
                                <div class="team-small">
                                    <div class="team-logo-small"><?= htmlspecialchars($m['code1'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                                    <div class="team-name-small"><?= htmlspecialchars($m['team1'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                                    <div class="team-code-small"><?= htmlspecialchars($m['code1'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                                </div>
                                
                                <div class="match-score">
                                    <?= ($m['score_team1'] !== null ? $m['score_team1'] : '?') . ' - ' . ($m['score_team2'] !== null ? $m['score_team2'] : '?') ?>
                                </div>
                                
                                <div class="team-small">
                                    <div class="team-logo-small"><?= htmlspecialchars($m['code2'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                                    <div class="team-name-small"><?= htmlspecialchars($m['team2'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                                    <div class="team-code-small"><?= htmlspecialchars($m['code2'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                                </div>
                            </div>
                            
                            <div class="match-details">
                                <div><i class="far fa-calendar"></i> <?= date("d/m/Y H:i", strtotime($m['match_datetime'])) ?></div>
                                <?php if ($m['pool_name']): ?>
                                    <div><i class="fas fa-layer-group"></i> Poule <?= htmlspecialchars($m['pool_name']) ?></div>
                                <?php endif; ?>
                                <?php if ($m['location']): ?>
                                    <div><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($m['location']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="text-align: center; width: 100%; padding: 40px;">
                        <p class="section-subtitle">Aucun match programmé pour cette saison</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- ============================
     STANDINGS SECTION
============================= -->
<section class="standings-section">
    <div class="container">
        <h2 class="section-title">Classement des Poules</h2>
        <p class="section-subtitle">Suivez les performances de chaque équipe</p>
        
        <?php if (!empty($poules)): ?>
            <div class="tabs-container">
                <div class="tabs-header">
                    <?php foreach($poules as $i=>$p): ?>
                        <button class="tab-btn <?= $i===0?'active':'' ?>" data-target="pool-<?= $p['pool_id'] ?>">
                            Poule <?= htmlspecialchars($p['name']) ?>
                        </button>
                    <?php endforeach; ?>
                </div>
                <div class="carousel-tracke" id="matches-carousel">
                
                <?php foreach($poules as $i=>$p): ?>
                    <div id="pool-<?= $p['pool_id'] ?>" class="tab-content <?= $i===0?'active':'' ?>">
                        <?php if (!empty($classements[$p['pool_id']])): ?>
                            <table class="standings-table">
                                <thead>
                                    <tr>
                                        <th>Q</th>
                                        <th>#</th>
                                        <th>Équipe</th>
                                        <th>MJ</th>
                                        <th>V</th>
                                        <th>N</th>
                                        <th>D</th>
                                        <th>BP</th>
                                        <th>BC</th>
                                        <th>+/-</th>
                                        <th>Pts</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($classements[$p['pool_id']] as $idx=>$r): ?>
                                        <tr class="<?= $idx+1 <= $p['advance_count'] ? 'qualified' : 'noqualified' ?>">
                                            <td class="team-position"><?= $idx+1 ?></td>
                                            <td class="team-name-cell"><?= htmlspecialchars($r['team_name']) ?></td>
                                            <td><?= $r['played'] ?></td>
                                            <td><?= $r['won'] ?></td>
                                            <td><?= $r['drawn'] ?></td>
                                            <td><?= $r['lost'] ?></td>
                                            <td><?= $r['goals_for'] ?></td>
                                            <td><?= $r['goals_against'] ?></td>
                                            <td><?= $r['goal_diff'] ?></td>
                                            <td><strong><?= $r['points'] ?></strong></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p class="section-subtitle">Aucune donnée disponible pour cette poule</p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                </div>
            </div>
        <?php else: ?>
            <p class="section-subtitle">Aucune poule créée pour cette saison</p>
        <?php endif; ?>
    </div>
</section>

<!-- ============================
     MATCH CENTER SECTION
============================= -->
<section class="match-center-section">
    <div class="container">
        <div class="match-center-header">
            <h2 class="section-title">Match Center</h2>
            <div class="match-center-filters">
                <button class="filter-btn active" data-filter="all">
                    <i class="fas fa-list"></i> Tous
                </button>
                <button class="filter-btn" data-filter="upcoming">
                    <i class="far fa-clock"></i> À venir
                </button>
                <button class="filter-btn" data-filter="live">
                    <i class="fas fa-play-circle"></i> En direct
                </button>
                <button class="filter-btn" data-filter="finished">
                    <i class="fas fa-flag-checkered"></i> Terminés
                </button>
            </div>
        </div>
        
        <div class="carousel-tracke" id="matches-carousel">
        <?php if (!empty($matches)): ?>
            <table class="match-center-table">
                <thead>
                    <tr>
                        <th>Date & Heure</th>
                        <th>Poule</th>
                        <th>Équipe 1</th>
                        <th>Score</th>
                        <th>Équipe 2</th>
                        <th>Statut</th>
                        <th>Détails</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($matches as $m): 
                        $status_class = '';
                        $status_text = '';
                        if ($m['is_played'] == 0) {
                            $status_class = 'status-upcoming';
                            $status_text = 'À venir';
                        } elseif ($m['is_played'] == 1 && $m['score_team1'] !== null) {
                            $status_class = 'status-finished';
                            $status_text = 'Terminé';
                        } else {
                            $status_class = 'status-live';
                            $status_text = 'En direct';
                        }
                    ?>
                        <tr class="match-row" data-status="<?= strtolower($status_text) ?>">
                            <td><?= date('d/m/Y H:i', strtotime($m['match_datetime'])) ?></td>
                            <td><?= htmlspecialchars($m['pool_name'] ?? '-') ?></td>
                            <td><strong><?= htmlspecialchars($m['team1']) ?></strong></td>
                            <td class="match-score-cell">
                                <?= ($m['score_team1'] !== null ? $m['score_team1'] : '?') . " - " . ($m['score_team2'] !== null ? $m['score_team2'] : '?') ?>
                            </td>
                            <td><strong><?= htmlspecialchars($m['team2']) ?></strong></td>
                            <td>
                                <span class="match-status <?= $status_class ?>"><?= $status_text ?></span>
                            </td>
                            <td>
                                <a href="match_details.php?id=<?= $m['match_id'] ?>" class="filter-btn" style="padding: 5px 10px;">
                                    <i class="fas fa-external-link-alt"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="section-subtitle">Aucun match disponible pour cette saison</p>
        <?php endif; ?>
        </div>
    </div>
</section>

<!-- ============================
     FAN ZONE SECTION
============================= -->
<section class="fan-zone-section">
    <div class="container">
        <h2 class="section-title">Fan Zone</h2>
        <p class="section-subtitle">Partagez votre passion pour le football universitaire</p>
        
        <?php if ($isLogged): ?>
            <div class="comment-form">
                <h3><i class="fas fa-comment-alt"></i> Ajouter un commentaire</h3>
                <form method="post">
                    <input type="hidden" name="user_id" value="<?= $_SESSION['etudiant_id'] ?? $_SESSION['admin_id'] ?>">
                    <textarea name="message" placeholder="Votre commentaire..." required maxlength="500"></textarea>
                    <div class="form-actions">
                        <button type="submit" class="submit-btn">
                            <i class="fas fa-paper-plane"></i> Publier
                        </button>
                    </div>
                </form>
            </div>
        <?php else: ?>
            <div class="comment-form">
                <h3><i class="fas fa-sign-in-alt"></i> Connectez-vous pour commenter</h3>
                <p style="color: var(--light-gray); margin-bottom: 20px;">Vous devez être connecté pour participer à la discussion.</p>
                <a href="../login.php" class="submit-btn" style="text-decoration: none; display: inline-flex;">
                    <i class="fas fa-sign-in-alt"></i> Se connecter
                </a>
            </div>
        <?php endif; ?>
        
        <div class="comments-container">
            <?php if (!empty($comments)): ?>
                <?php foreach($comments as $c): ?>
                    <div class="comment-item">
                        <div class="comment-header">
                            <img src="<?= !empty($c['photo']) ? '../admins/uploads/photos_etudiants/' . htmlspecialchars($c['photo']) : '../assets/images/default-avatar.png' ?>" 
                                 alt="<?= htmlspecialchars($c['nom'] . ' ' . $c['prenom']) ?>" 
                                 class="comment-avatar"
                                 onerror="this.src='../assets/images/default-avatar.png'">
                            <div class="comment-author">
                                <div class="comment-name">
                                    <?= htmlspecialchars($c['nom'] ?? 'Admin') . ' ' . htmlspecialchars($c['prenom'] ?? '') ?>
                                </div>
                                <div class="comment-time">
                                    <i class="far fa-clock"></i>
                                    <?= !empty($c['date_commentaire_football']) 
                                        ? date('d/m/Y H:i', strtotime($c['date_commentaire_football'])) 
                                        : "Date inconnue" ?>
                                </div>
                            </div>
                        </div>
                        <div class="comment-content">
                            <?= nl2br(htmlspecialchars($c['message'])) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="section-subtitle" style="text-align: center; padding: 40px;">Soyez le premier à commenter !</p>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- ============================
     FINALS SECTION
============================= -->
<?php if (!empty($finals)): ?>
<section class="finals-section">
    <div class="container">
        <h2 class="section-title">Phases Finales</h2>
        <p class="section-subtitle">Le chemin vers la victoire</p>
        
        <div class="finals-bracket">
            <div class="finals-stage">
                <h3 class="stage-title">Quarts de finale</h3>
                <?php foreach($finals as $f): 
                    if ($f['stage'] == 'quarter'): ?>
                        <div class="match-item">
                            <div class="match-teams-compact">
                                <div class="team-compact"><?= htmlspecialchars($f['team1']) ?></div>
                                <div class="score-compact">
                                    <?= ($f['score_team1'] !== null ? $f['score_team1'] : '?') . ' - ' . ($f['score_team2'] !== null ? $f['score_team2'] : '?') ?>
                                </div>
                                <div class="team-compact"><?= htmlspecialchars($f['team2']) ?></div>
                            </div>
                            <div class="match-info-compact">
                                <i class="far fa-calendar"></i> <?= date('d/m/Y H:i', strtotime($f['match_datetime'])) ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            
            <div class="finals-stage">
                <h3 class="stage-title">Demi-finales</h3>
                <?php foreach($finals as $f): 
                    if ($f['stage'] == 'semi'): ?>
                        <div class="match-item">
                            <div class="match-teams-compact">
                                <div class="team-compact"><?= htmlspecialchars($f['team1']) ?></div>
                                <div class="score-compact">
                                    <?= ($f['score_team1'] !== null ? $f['score_team1'] : '?') . ' - ' . ($f['score_team2'] !== null ? $f['score_team2'] : '?') ?>
                                </div>
                                <div class="team-compact"><?= htmlspecialchars($f['team2']) ?></div>
                            </div>
                            <div class="match-info-compact">
                                <i class="far fa-calendar"></i> <?= date('d/m/Y H:i', strtotime($f['match_datetime'])) ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            
            <div class="finals-stage">
                <h3 class="stage-title">Finale</h3>
                <?php foreach($finals as $f): 
                    if ($f['stage'] == 'final'): ?>
                        <div class="match-item">
                            <div class="match-teams-compact">
                                <div class="team-compact"><?= htmlspecialchars($f['team1']) ?></div>
                                <div class="score-compact">
                                    <?= ($f['score_team1'] !== null ? $f['score_team1'] : '?') . ' - ' . ($f['score_team2'] !== null ? $f['score_team2'] : '?') ?>
                                </div>
                                <div class="team-compact"><?= htmlspecialchars($f['team2']) ?></div>
                            </div>
                            <div class="match-info-compact">
                                <i class="far fa-calendar"></i> <?= date('d/m/Y H:i', strtotime($f['match_datetime'])) ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<?php include "../includes/footer.php"; ?>

<script>

document.addEventListener('DOMContentLoaded', function() {
    // Gestion des onglets des poules
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            document.getElementById(btn.dataset.target).classList.add('active');
        });
    });

    // Filtres du match center
    document.querySelectorAll('.filter-btn[data-filter]').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            
            const filter = btn.dataset.filter;
            document.querySelectorAll('.match-row').forEach(row => {
                if (filter === 'all') {
                    row.style.display = 'table-row';
                } else {
                    const status = row.dataset.status;
                    if (status && status.includes(filter)) {
                        row.style.display = 'table-row';
                    } else {
                        row.style.display = 'none';
                    }
                }
            });
        });
    });

    // Navigation du carrousel
    const carousel = document.getElementById('matches-carousel');
    const prevBtn = document.querySelector('.prev-btn');
    const nextBtn = document.querySelector('.next-btn');
    
    if (carousel && prevBtn && nextBtn) {
        const scrollAmount = 320;
        
        prevBtn.addEventListener('click', () => {
            carousel.scrollBy({ left: -scrollAmount, behavior: 'smooth' });
        });
        
        nextBtn.addEventListener('click', () => {
            carousel.scrollBy({ left: scrollAmount, behavior: 'smooth' });
        });
    }

    // Animation au scroll
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
                entry.target.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            }
        });
    }, observerOptions);

    // Animer les cartes au scroll
    document.querySelectorAll('.kpi-card, .match-card, .comment-item, .match-item, .stat-item').forEach(card => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        observer.observe(card);
    });

    // Compteur pour les matchs à venir
    function updateMatchTimers() {
        document.querySelectorAll('.match-timer').forEach(timer => {
            // Logique de compteur à implémenter selon vos besoins
        });
    }

    // Initialiser les compteurs
    updateMatchTimers();
    setInterval(updateMatchTimers, 60000); // Mettre à jour toutes les minutes

    // Gestion des erreurs d'images
    document.querySelectorAll('img').forEach(img => {
        img.addEventListener('error', function() {
            this.src = '../assets/images/default-avatar.png';
        });
    });

    // Smooth scroll pour les ancres
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            e.preventDefault();
            const targetId = this.getAttribute('href');
            if (targetId === '#') return;
            
            const targetElement = document.querySelector(targetId);
            if (targetElement) {
                window.scrollTo({
                    top: targetElement.offsetTop - 80,
                    behavior: 'smooth'
                });
            }
        });
    });

    // Validation du formulaire de commentaire
    const commentForm = document.querySelector('.comment-form form');
    if (commentForm) {
        commentForm.addEventListener('submit', function(e) {
            const textarea = this.querySelector('textarea');
            if (textarea.value.trim().length < 5) {
                e.preventDefault();
                alert('Le commentaire doit contenir au moins 5 caractères.');
                textarea.focus();
            }
        });
    }
});
</script>
