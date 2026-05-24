<?php
session_start();
require_once "../includes/db.php";
define('BASE_URL', '/isspt_projet/'); // chemin relatif depuis localhost
$base_url = BASE_URL;

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
    } else {
        session_unset();
        session_destroy();
        header("Location: login.php");
        exit;
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
    } else {
        session_unset();
        session_destroy();
        header("Location: login.php");
        exit;
    }
}

if (!isset($_GET['id'])) {
    die("Activité introuvable.");
}

$id = intval($_GET['id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $message = trim($_POST['message'] ?? '');

    if (!empty($message)) {
        $id_etudiant = $_SESSION['etudiant_id'] ?? null;

        if ($isLogged) {
            $stmt = $pdo->prepare("
                INSERT INTO commentaires (activity_id, id_etudiant, message, user_type)
                VALUES (:activity_id, :id_etudiant, :message, 'etudiant')
            ");
            $stmt->execute([
                'activity_id' => $id,
                'id_etudiant' => $id_etudiant,
                'message' => $message
            ]);

            header("Location: details_activite.php?id=$id");
            exit();
        } else {
            $error_commentaire = "Vous devez être connecté pour commenter.";
        }
    } else {
        $error_commentaire = "Le message ne peut pas être vide.";
    }
}

$req = $pdo->prepare("
    SELECT a.*, ay.label AS annee_academique, ad.nom AS admin_nom, ad.prenom AS admin_prenom
    FROM activites a
    JOIN academic_years ay ON ay.id = a.academic_year_id
    LEFT JOIN administrateurs ad ON ad.id_admin = a.cree_par
    WHERE a.id_activite = ?
");
$req->execute([$id]);
$activite = $req->fetch();

if (!$activite) {
    die("Activité non trouvée.");
}

$accessibilite_label = $activite['accessibilite'] === 'isspt'
    ? 'Réservé aux étudiants ISSPT'
    : 'Ouvert au public';

$type_participation_label = $activite['type_participation'] === 'equipe'
    ? 'Participation en équipe'
    : 'Participation individuelle';

$inscription_ouverte = true;

if ($activite['statut'] !== 'ouverte') {
    $inscription_ouverte = false;
}

if (!empty($activite['date_limite_inscription']) 
    && strtotime($activite['date_limite_inscription']) < time()) {
    $inscription_ouverte = false;
}

$galerie = $pdo->prepare("SELECT * FROM galerie WHERE activity_id = ?");
$galerie->execute([$id]);

$participants = $pdo->prepare("
    SELECT p.*, e.nom, e.prenom, e.matricule
    FROM participants_activites p
    JOIN etudiants e ON e.id_etudiant = p.id_etudiant
    WHERE p.id_activite = ?
");
$participants->execute([$id]);

$commentaires = $pdo->prepare("
    SELECT c.*, e.nom AS etu_nom, e.prenom AS etu_prenom, ad.nom AS admin_nom, ad.prenom AS admin_prenom
    FROM commentaires c
    LEFT JOIN etudiants e ON e.id_etudiant = c.id_etudiant
    LEFT JOIN administrateurs ad ON ad.id_admin = c.id_admin
    WHERE c.activity_id = ?
    ORDER BY c.date_commentaire DESC
");
$commentaires->execute([$id]);
?>


    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails Activité - <?= htmlspecialchars($activite['nom_activite'] ?? '') ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-blue: #080020;
            --secondary-blue: #0a0127;
            --accent-red: #d32f2f;
            --light-blue: #e8eaf6;
            --dark-blue: #0d1b3e;
            --white: #ffffff;
            --gray-light: #f5f5f5;
            --gray-medium: #e0e0e0;
            --gray-dark: #757575;
            --success: #4caf50;
            --warning: #ff9800;
            --danger: #f44336;
            --shadow-sm: 0 2px 8px rgba(0,0,0,0.1);
            --shadow-md: 0 4px 16px rgba(0,0,0,0.15);
            --shadow-lg: 0 8px 30px rgba(0,0,0,0.2);
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        }

        body {
            background: linear-gradient(135deg, var(--light-blue) 0%, #ffffff 100%);
            color: #333;
            line-height: 1.6;
            min-height: 100vh;
            margin-top: 70px;
        }

        .page-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        /* Header avec bannière */
        .activity-header {
            background: linear-gradient(rgba(1, 5, 49, 0.9), rgba(11, 15, 46, 0.9)), 
                        url('https://images.unsplash.com/photo-1546519638-68e109498ffc?ixlib=rb-4.0.3&auto=format&fit=crop&w=1400&q=80');
            background-size: cover;
            background-position: center;
            border-radius: var(--radius-lg);
            padding: 50px 40px;
            color: white;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow-lg);
        }

        .activity-header::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--accent-red), #ff7043);
        }

        .activity-title {
            font-size: 2.8rem;
            font-weight: 800;
            margin-bottom: 10px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }

        .academic-year {
            font-size: 1.2rem;
            opacity: 0.9;
            margin-bottom: 25px;
        }

        /* Grid principal */
        .main-grid {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 30px;
        }

        @media (max-width: 1024px) {
            .main-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Cartes principales */
        .card {
            background: var(--white);
            border-radius: var(--radius-md);
            padding: 30px;
            margin-bottom: 25px;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
            border: 1px solid var(--gray-medium);
        }

        .card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-3px);
        }

        .card-title {
            font-size: 1.4rem;
            color: var(--primary-blue);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 3px solid var(--accent-red);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-title i {
            color: var(--accent-red);
        }

        /* Barre d'infos rapides */
        .quick-info-bar {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            color: black;
            margin-bottom: 30px;
        }

        .info-chip {
            background: var(--white);
            padding: 15px;
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: var(--shadow-sm);
            border-left: 4px solid var(--primary-blue);
        }

        .info-chip i {
            color: var(--primary-blue);
            font-size: 1.2rem;
        }

        /* Boutons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 14px 28px;
            border-radius: var(--radius-sm);
            font-weight: 600;
            text-decoration: none;
            transition: var(--transition);
            border: none;
            cursor: pointer;
            font-size: 1rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
            color: white;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--secondary-blue), var(--primary-blue));
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .btn-secondary {
            background: var(--gray-light);
            color: var(--dark-blue);
            border: 2px solid var(--gray-medium);
        }

        .btn-secondary:hover {
            background: var(--gray-medium);
        }

        .btn-danger {
            background: var(--accent-red);
            color: white;
        }

        .btn-danger:hover {
            background: #b71c1c;
        }

        /* Galerie */
        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .gallery-item {
            border-radius: var(--radius-sm);
            overflow: hidden;
            height: 200px;
            position: relative;
            cursor: pointer;
            transition: var(--transition);
        }

        .gallery-item:hover {
            transform: scale(1.05);
            box-shadow: var(--shadow-lg);
        }

        .gallery-item img,
        .gallery-item video {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* Table participants */
        .table-container {
            overflow-x: auto;
            margin-top: 20px;
        }

        .participants-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: var(--radius-sm);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }

        .participants-table th {
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
            color: white;
            padding: 16px;
            text-align: left;
            font-weight: 600;
        }

        .participants-table td {
            padding: 14px 16px;
            border-bottom: 1px solid var(--gray-light);
        }

        .participants-table tr:hover {
            background: var(--light-blue);
        }

        /* Commentaires */
        .comment-section {
            max-height: 500px;
            overflow-y: auto;
            padding-right: 10px;
        }

        .comment {
            background: var(--gray-light);
            padding: 20px;
            border-radius: var(--radius-sm);
            margin-bottom: 15px;
            border-left: 4px solid var(--primary-blue);
        }

        .comment-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .comment-author {
            font-weight: 600;
            color: var(--primary-blue);
        }

        .comment-date {
            color: var(--gray-dark);
            font-size: 0.9rem;
        }

        /* Formulaire commentaire */
        .comment-form textarea {
            width: 100%;
            padding: 16px;
            border: 2px solid var(--gray-medium);
            border-radius: var(--radius-sm);
            resize: vertical;
            min-height: 120px;
            font-size: 1rem;
            transition: var(--transition);
            margin-bottom: 15px;
        }

        .comment-form textarea:focus {
            outline: none;
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgba(26, 35, 126, 0.1);
        }

        /* Sidebar */
        .sidebar {
            position: sticky;
            top: 30px;
            height: fit-content;
        }

        .sidebar-card {
            background: var(--white);
            border-radius: var(--radius-md);
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: var(--shadow-md);
            border-top: 5px solid var(--accent-red);
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            margin-bottom: 20px;
        }

        .status-open {
            background: #e8f5e9;
            color: var(--success);
        }

        .status-closed {
            background: #ffebee;
            color: var(--danger);
        }

        .info-grid {
            display: grid;
            gap: 15px;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px dashed var(--gray-medium);
        }

        .info-item:last-child {
            border-bottom: none;
        }

        .info-label {
            color: var(--gray-dark);
        }

        .info-value {
            font-weight: 600;
            color: var(--primary-blue);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .page-container {
                padding: 15px;
            }

            .activity-header {
                padding: 30px 20px;
            }

            .activity-title {
                font-size: 2rem;
            }

            .quick-info-bar {
                grid-template-columns: 1fr;
            }

            .card {
                padding: 20px;
            }

            .gallery-grid {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            }
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .fade-in {
            animation: fadeIn 0.5s ease-out;
        }

        /* Badges */
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .badge-team {
            background: #e3f2fd;
            color: #1565c0;
        }

        .badge-individual {
            background: #f3e5f5;
            color: #7b1fa2;
        }

        /* Loading state */
        .skeleton {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: loading 1.5s infinite;
        }

        @keyframes loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }
    </style>

    <?php include "../includes/header.php"; ?>

    <div class="page-container">
        <!-- En-tête de l'activité -->
        <div class="activity-header fade-in">
            <h1 class="activity-title"><?= htmlspecialchars($activite['nom_activite'] ?? '') ?></h1>
            <p class="academic-year">
                <i class="fas fa-calendar-alt"></i> 
                Année académique : <?= htmlspecialchars($activite['annee_academique'] ?? '') ?>
            </p>
            <div class="quick-info-bar">
                <div class="info-chip">
                    <i class="fas fa-users"></i>
                    <div>
                        <div style="font-weight: 600; font-size: 1.1rem;"><?= $accessibilite_label ?></div>
                        <div style="font-size: 0.9rem; opacity: 0.8;">Accès</div>
                    </div>
                </div>
                <div class="info-chip">
                    <i class="fas fa-trophy"></i>
                    <div>
                        <div style="font-weight: 600; font-size: 1.1rem;"><?= $type_participation_label ?></div>
                        <div style="font-size: 0.9rem; opacity: 0.8;">Type</div>
                    </div>
                </div>
                <?php if ($activite['type_participation'] === 'equipe'): ?>
                <div class="info-chip">
                    <i class="fas fa-user-friends"></i>
                    <div>
                        <div style="font-weight: 600; font-size: 1.1rem;">
                            <?= ($activite['equipe_min'] ?? '').' - '.($activite['equipe_max'] ?? '') ?> membres
                        </div>
                        <div style="font-size: 0.9rem; opacity: 0.8;">Taille équipe</div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="main-grid">
            <!-- Contenu principal -->
            <div class="main-content">
                <!-- Description -->
                <div class="card fade-in" style="animation-delay: 0.1s;">
                    <h2 class="card-title">
                        <i class="fas fa-align-left"></i> Description
                    </h2>
                    <p style="white-space: pre-line; font-size: 1.05rem; line-height: 1.8;">
                        <?= nl2br(htmlspecialchars($activite['description'] ?? '')) ?>
                    </p>
                </div>

                <!-- Conditions -->
                <div class="card fade-in" style="animation-delay: 0.2s;">
                    <h2 class="card-title">
                        <i class="fas fa-clipboard-check"></i> Conditions de Participation
                    </h2>
                    <p style="white-space: pre-line; font-size: 1.05rem; line-height: 1.8;">
                        <?= nl2br(htmlspecialchars($activite['conditions'] ?? '')) ?>
                    </p>
                </div>

                <!-- Galerie -->
                <?php $galerieData = $galerie->fetchAll(); ?>
                <?php if (!empty($galerieData)): ?>
                <div class="card fade-in" style="animation-delay: 0.3s;">
                    <h2 class="card-title">
                        <i class="fas fa-images"></i> Galerie
                    </h2>
                    <div class="gallery-grid">
                        <?php foreach ($galerieData as $media): ?>
                            <div class="gallery-item">
                                <?php if ($media['file_type'] === 'image'): ?>
                                    <img src="<?= htmlspecialchars($media['file_path'] ?? '') ?>" 
                                         alt="Média activité">
                                <?php else: ?>
                                    <video src="<?= htmlspecialchars($media['file_path'] ?? '') ?>" 
                                           controls 
                                           poster="https://images.unsplash.com/photo-1558618666-fcd25c85cd64?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80">
                                    </video>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Participants -->
                <div class="card fade-in" style="animation-delay: 0.4s;">
                    <h2 class="card-title">
                        <i class="fas fa-user-check"></i> Participants
                        <span style="margin-left: auto; font-size: 0.9rem; color: var(--gray-dark);">
                            <?= $participants->rowCount() ?> participants
                        </span>
                    </h2>
                    <div class="table-container">
                        <table class="participants-table">
                            <thead>
                                <tr>
                                    <th>Nom</th>
                                    <th>Matricule</th>
                                    <th>Rôle</th>
                                    <th>Statut</th>
                                    <th>Performance</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($participants as $p): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($p['prenom'] ?? '') ?> <?= htmlspecialchars($p['nom'] ?? '') ?></strong>
                                    </td>
                                    <td><?= htmlspecialchars($p['matricule'] ?? '') ?></td>
                                    <td>
                                        <span class="badge badge-<?= ($p['role'] ?? '') === 'leader' ? 'team' : 'individual' ?>">
                                            <?= htmlspecialchars($p['role'] ?? '—') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span style="color: <?= ($p['statut'] ?? '') === 'confirmé' ? 'var(--success)' : 'var(--warning)' ?>;">
                                            <?= htmlspecialchars($p['statut'] ?? '') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!empty($p['performance'])): ?>
                                            <div style="display: flex; align-items: center; gap: 5px;">
                                                <i class="fas fa-star" style="color: #ff9800;"></i>
                                                <?= htmlspecialchars($p['performance'] ?? '') ?>
                                            </div>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Commentaires -->
                <div class="card fade-in" style="animation-delay: 0.5s;">
                    <h2 class="card-title">
                        <i class="fas fa-comments"></i> Commentaires
                    </h2>
                    
                    <div class="comment-section">
                        <?php foreach ($commentaires as $c): ?>
                            <div class="comment">
                                <div class="comment-header">
                                    <div class="comment-author">
                                        <?php if ($c['user_type'] === 'admin'): ?>
                                            <i class="fas fa-crown" style="color: var(--accent-red); margin-right: 5px;"></i>
                                            Admin : <?= htmlspecialchars($c['admin_prenom'] ?? '') ?> <?= htmlspecialchars($c['admin_nom'] ?? '') ?>
                                        <?php else: ?>
                                            <i class="fas fa-user-graduate" style="color: var(--primary-blue); margin-right: 5px;"></i>
                                            <?= htmlspecialchars($c['etu_prenom'] ?? '') ?> <?= htmlspecialchars($c['etu_nom'] ?? '') ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="comment-date">
                                        <i class="far fa-clock"></i> 
                                        <?= date('d/m/Y H:i', strtotime($c['date_commentaire'] ?? '')) ?>
                                    </div>
                                </div>
                                <p style="margin-top: 10px; line-height: 1.6;">
                                    <?= nl2br(htmlspecialchars($c['message'] ?? '')) ?>
                                </p>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Formulaire commentaire -->
                    <div style="margin-top: 30px; padding-top: 25px; border-top: 2px solid var(--gray-light);">
                        <h3 style="margin-bottom: 20px; color: var(--primary-blue);">
                            <i class="fas fa-edit"></i> Laisser un commentaire
                        </h3>
                        
                        <?php if (!empty($error_commentaire)): ?>
                            <div style="background: #ffebee; color: var(--danger); padding: 15px; 
                                        border-radius: var(--radius-sm); margin-bottom: 20px; 
                                        border-left: 4px solid var(--danger);">
                                <i class="fas fa-exclamation-circle"></i> 
                                <?= htmlspecialchars($error_commentaire) ?>
                            </div>
                        <?php endif; ?>

                        <?php if($isLogged): ?>
                            <form class="comment-form" method="POST">
                                <input type="hidden" name="id_activite" value="<?= $id ?>">
                                <textarea name="message" 
                                          placeholder="Partagez vos impressions sur cette activité..." 
                                          required></textarea>
                                <div style="display: flex; justify-content: flex-end; gap: 15px;">
                                    <button type="reset" class="btn btn-secondary">
                                        <i class="fas fa-times"></i> Annuler
                                    </button>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-paper-plane"></i> Publier
                                    </button>
                                </div>
                            </form>
                        <?php else: ?>
                            <div style="background: var(--gray-light); padding: 25px; 
                                        border-radius: var(--radius-sm); text-align: center;">
                                <i class="fas fa-lock" style="font-size: 2rem; color: var(--gray-dark); 
                                                               margin-bottom: 15px;"></i>
                                <h4 style="color: var(--dark-blue); margin-bottom: 10px;">
                                    Connectez-vous pour commenter
                                </h4>
                                <p style="color: var(--gray-dark); margin-bottom: 20px;">
                                    Vous devez être connecté pour participer à la discussion.
                                </p>
                                <a href="login.php" class="btn btn-primary">
                                    <i class="fas fa-sign-in-alt"></i> Se connecter
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="sidebar">
                <!-- Statut inscription -->
                <div class="sidebar-card fade-in" style="animation-delay: 0.6s;">
                    <div class="status-badge <?= $inscription_ouverte ? 'status-open' : 'status-closed' ?>">
                        <i class="fas fa-<?= $inscription_ouverte ? 'lock-open' : 'lock' ?>"></i>
                        <?= $inscription_ouverte ? 'Inscriptions ouvertes' : 'Inscriptions fermées' ?>
                    </div>
                    
                    <div style="text-align: center; margin: 25px 0;">
                        <?php if ($inscription_ouverte): ?>
                            <?php if ($isLogged): ?>
                                <a href="inscription_activite.php?id=<?= $id ?>" 
                                   class="btn btn-primary" style="width: 100%; padding: 16px;">
                                    <i class="fas fa-user-plus"></i> S'inscrire
                                </a>
                            <?php else: ?>
                                <a href="login.php" 
                                   class="btn btn-primary" style="width: 100%; padding: 16px;">
                                    <i class="fas fa-sign-in-alt"></i> Se connecter pour s'inscrire
                                </a>
                            <?php endif; ?>
                        <?php else: ?>
                            <button class="btn btn-secondary" style="width: 100%; padding: 16px;" disabled>
                                <i class="fas fa-ban"></i> Inscriptions terminées
                            </button>
                        <?php endif; ?>
                    </div>

                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Créateur :</span>
                            <span class="info-value">
                                <?= htmlspecialchars($activite['admin_prenom'] ?? '') ?> 
                                <?= htmlspecialchars($activite['admin_nom'] ?? '') ?>
                            </span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Statut :</span>
                            <span class="info-value" style="text-transform: capitalize;">
                                <?= htmlspecialchars($activite['statut'] ?? '') ?>
                            </span>
                        </div>
                        <?php if (!empty($activite['date_limite_inscription'])): ?>
                        <div class="info-item">
                            <span class="info-label">Date limite :</span>
                            <span class="info-value">
                                <i class="far fa-clock"></i> 
                                <?= date('d/m/Y H:i', strtotime($activite['date_limite_inscription'])) ?>
                            </span>
                        </div>
                        <?php endif; ?>
                        <div class="info-item">
                            <span class="info-label">Participants :</span>
                            <span class="info-value">
                                <?= $participants->rowCount() ?> inscrits
                            </span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Commentaires :</span>
                            <span class="info-value">
                                <?= $commentaires->rowCount() ?> messages
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Actions rapides -->
                <div class="sidebar-card fade-in" style="animation-delay: 0.7s;">
                    <h3 style="color: var(--primary-blue); margin-bottom: 20px; 
                               border-bottom: 2px solid var(--light-blue); padding-bottom: 10px;">
                        <i class="fas fa-bolt"></i> Actions rapides
                    </h3>
                    <div style="display: flex; flex-direction: column; gap: 12px;">
                        <a href="index.php#activities" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Retour aux activités
                        </a>
                        <button class="btn btn-secondary" onclick="window.print()">
                            <i class="fas fa-print"></i> Imprimer cette page
                        </button>
                        <button class="btn btn-secondary" onclick="shareActivity()">
                            <i class="fas fa-share-alt"></i> Partager
                        </button>
                        <?php if ($isAdmin): ?>
                            <a href="edit_activite.php?id=<?= $id ?>" class="btn btn-danger">
                                <i class="fas fa-edit"></i> Modifier l'activité
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include "../includes/footer.php"; ?>

    <script>
        // Fonction de partage
        function shareActivity() {
            if (navigator.share) {
                navigator.share({
                    title: document.title,
                    text: 'Découvrez cette activité sur ISSPT',
                    url: window.location.href
                });
            } else {
                // Fallback pour anciens navigateurs
                navigator.clipboard.writeText(window.location.href);
                alert('Lien copié dans le presse-papier !');
            }
        }

        // Animation au scroll
        document.addEventListener('DOMContentLoaded', function() {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = 1;
                        entry.target.style.transform = 'translateY(0)';
                    }
                });
            }, { threshold: 0.1 });

            document.querySelectorAll('.card').forEach(card => {
                card.style.opacity = 0;
                card.style.transform = 'translateY(20px)';
                card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                observer.observe(card);
            });
        });

        // Gestion de la galerie (lightbox simplifiée)
        document.querySelectorAll('.gallery-item').forEach(item => {
            item.addEventListener('click', function() {
                const media = this.querySelector('img, video');
                if (media) {
                    const src = media.src || media.querySelector('source').src;
                    window.open(src, '_blank');
                }
            });
        });
    </script>
