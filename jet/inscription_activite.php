<?php
session_start();
require_once "../includes/db.php";

$isLogged = false;
$isAdmin = false;
$userName = '';
$userAvatar = '../assets/images/default-avatar.png';

// Vérification si un étudiant est connecté
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

// Vérification si un administrateur est connecté
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

$id_etudiant = $_SESSION['etudiant_id'] ?? $_SESSION['admin_id'] ?? null;
$id_etudiant_session = $id_etudiant;

if (!isset($_GET['id'])) die("Activité introuvable.");
$id_activite = intval($_GET['id']);

// Chargement des détails de l'activité
$stmt = $pdo->prepare("
    SELECT a.*, ay.label AS annee_academique 
    FROM activites a 
    JOIN academic_years ay ON ay.id = a.academic_year_id 
    WHERE a.id_activite = ?
");
$stmt->execute([$id_activite]);
$activite = $stmt->fetch();

if (!$activite) die("Activité inexistante.");

// Variables de contrôle d'accès
$maintenant = new DateTime();
$date_limite = $activite['date_limite_inscription'] ? new DateTime($activite['date_limite_inscription']) : null;
$est_cloturee = ($date_limite && $maintenant > $date_limite) || $activite['statut'] !== 'ouverte';
$reservee_isspt = ($activite['conditions'] === "etre etudiant de l'isspt");

// Vérification d'une inscription existante
$inscription_existante = null;
if ($isLogged) {
    $stmt = $pdo->prepare("
        SELECT i.*, g.nom_groupe 
        FROM inscriptions_activites i
        LEFT JOIN groupes_activites g ON i.id_groupe = g.id_groupe
        WHERE i.id_activite = ? AND (i.id_etudiant = ? OR g.createur_etudiant = ?)
    ");
    $stmt->execute([$id_activite, $id_etudiant_session, $id_etudiant_session]);
    $inscription_existante = $stmt->fetch();
}

// Traitement du Formulaire (POST)
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$inscription_existante && !$est_cloturee) {
    try {
        $pdo->beginTransaction();

        if ($activite['type_participation'] === 'individuel') {
            if ($isLogged) {
                $stmt = $pdo->prepare("INSERT INTO inscriptions_activites (id_activite, id_etudiant) VALUES (?, ?)");
                $stmt->execute([$id_activite, $id_etudiant_session]);
            } elseif (!$reservee_isspt) {
                $nom = trim($_POST['nom'] ?? '');
                $prenom = trim($_POST['prenom'] ?? '');
                if (empty($nom) || empty($prenom)) throw new Exception("Nom et prénom requis.");
                
                $stmt = $pdo->prepare("INSERT INTO inscriptions_activites (id_activite, nom, prenom) VALUES (?, ?, ?)");
                $stmt->execute([$id_activite, $nom, $prenom]);
            } else {
                throw new Exception("Cette activité est réservée aux étudiants ISSPT.");
            }
        } 
        else { // Équipe
            $nom_groupe = trim($_POST['nom_groupe'] ?? '');
            if (empty($nom_groupe)) throw new Exception("Le nom de l'équipe est obligatoire.");

            $membres = $_POST['membres'] ?? [];
            $json_membres = null;

            if (!$isLogged) {
                $membres_bruts = trim($_POST['membres_externes_texte'] ?? '');
                $liste = array_filter(array_map('trim', explode(',', $membres_bruts)));
                $json_membres = json_encode($liste);
            } else {
                $json_membres = json_encode($membres);
            }

            $stmt = $pdo->prepare("INSERT INTO groupes_activites (id_activite, nom_groupe, createur_etudiant, membres_externes) VALUES (?, ?, ?, ?)");
            $stmt->execute([$id_activite, $nom_groupe, $id_etudiant_session, $json_membres]);
            $id_groupe = $pdo->lastInsertId();

            $stmt = $pdo->prepare("INSERT INTO inscriptions_activites (id_activite, id_groupe) VALUES (?, ?)");
            $stmt->execute([$id_activite, $id_groupe]);
        }

        $pdo->commit();
        $success = "Inscription enregistrée avec succès !";
        header("Refresh:2");
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription - <?= htmlspecialchars($activite['nom_activite'] ?? '') ?> | ISSPT</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
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
            --warning2: #0026ff;
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
            min-height: 100vh;
            line-height: 1.6;
            margin-top: 50px;
        }

        .registration-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px 20px;
        }

        /* Header de l'activité */
        .activity-header {
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--secondary-blue) 100%);
            border-radius: var(--radius-lg);
            padding: 40px;
            color: var(--white);
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow-lg);
        }

        .activity-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--accent-red), #ff7043);
        }

        .activity-title {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 10px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }

        .activity-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid rgba(255,255,255,0.1);
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.95rem;
            opacity: 0.9;
        }

        .meta-item i {
            color: var(--accent-red);
        }

        /* Badges */
        .badge-custom {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.85rem;
        }

        .badge-open {
            background: rgba(76, 175, 80, 0.1);
            color: var(--success);
            border: 2px solid var(--success);
        }

        .badge-closed {
            background: rgba(244, 67, 54, 0.1);
            color: var(--danger);
            border: 2px solid var(--danger);
        }

        .badge-type {
            background: rgba(26, 35, 126, 0.1);
            color: var(--warning2);
            border: 2px solid var(--warning2);
        }

        .badge-access {
            background: rgba(255, 152, 0, 0.1);
            color: var(--warning);
            border: 2px solid var(--warning);
        }

        /* Section principale */
        .main-section {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 30px;
        }

        @media (max-width: 992px) {
            .main-section {
                grid-template-columns: 1fr;
            }
        }

        /* Cartes */
        .card-modern {
            background: var(--white);
            border-radius: var(--radius-md);
            padding: 30px;
            margin-bottom: 25px;
            box-shadow: var(--shadow-md);
            border: none;
            transition: var(--transition);
        }

        .card-modern:hover {
            box-shadow: var(--shadow-lg);
            transform: translateY(-3px);
        }

        .card-title {
            font-size: 1.4rem;
            color: var(--primary-blue);
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 3px solid var(--accent-red);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .card-title i {
            color: var(--accent-red);
        }

        /* Formulaire */
        .form-group {
            margin-bottom: 25px;
        }

        .form-label {
            font-weight: 600;
            color: var(--primary-blue);
            margin-bottom: 8px;
            display: block;
        }

        .form-control-custom {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid var(--gray-medium);
            border-radius: var(--radius-sm);
            font-size: 1rem;
            transition: var(--transition);
            background: var(--white);
        }

        .form-control-custom:focus {
            outline: none;
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgba(8, 0, 32, 0.1);
        }

        .form-select-custom {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid var(--gray-medium);
            border-radius: var(--radius-sm);
            font-size: 1rem;
            background: var(--white);
            cursor: pointer;
        }

        .form-select-custom:focus {
            outline: none;
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgba(8, 0, 32, 0.1);
        }

        .form-textarea {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid var(--gray-medium);
            border-radius: var(--radius-sm);
            font-size: 1rem;
            resize: vertical;
            min-height: 120px;
            transition: var(--transition);
        }

        .form-textarea:focus {
            outline: none;
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgba(8, 0, 32, 0.1);
        }

        /* Boutons */
        .btn-custom {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 16px 32px;
            border-radius: var(--radius-sm);
            font-weight: 600;
            text-decoration: none;
            border: none;
            cursor: pointer;
            font-size: 1rem;
            transition: var(--transition);
            width: 100%;
        }

        .btn-primary-custom {
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
            color: var(--white);
        }

        .btn-primary-custom:hover {
            background: linear-gradient(135deg, var(--secondary-blue), var(--primary-blue));
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(8, 0, 32, 0.2);
        }

        .btn-secondary-custom {
            background: var(--gray-light);
            color: var(--dark-blue);
            border: 2px solid var(--gray-medium);
        }

        .btn-secondary-custom:hover {
            background: var(--gray-medium);
        }

        .btn-success-custom {
            background: linear-gradient(135deg, var(--success), #45a049);
            color: var(--white);
        }

        .btn-success-custom:hover {
            background: linear-gradient(135deg, #45a049, var(--success));
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(76, 175, 80, 0.2);
        }

        /* Messages */
        .alert-message {
            padding: 20px;
            border-radius: var(--radius-sm);
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 15px;
            border-left: 4px solid;
        }

        .alert-success {
            background: rgba(76, 175, 80, 0.1);
            border-left-color: var(--success);
            color: #2e7d32;
        }

        .alert-error {
            background: rgba(244, 67, 54, 0.1);
            border-left-color: var(--danger);
            color: #c62828;
        }

        .alert-warning {
            background: rgba(255, 152, 0, 0.1);
            border-left-color: var(--warning);
            color: #ef6c00;
        }

        /* Sidebar */
        .sidebar-section {
            position: sticky;
            top: 30px;
            height: fit-content;
        }

        .status-card {
            background: var(--white);
            border-radius: var(--radius-md);
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: var(--shadow-md);
            border-top: 5px solid var(--accent-red);
            text-align: center;
        }

        .status-icon {
            font-size: 3rem;
            color: var(--accent-red);
            margin-bottom: 20px;
        }

        .status-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--primary-blue);
            margin-bottom: 15px;
        }

        /* Description */
        .activity-description {
            background: var(--white);
            border-radius: var(--radius-md);
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: var(--shadow-sm);
        }

        .description-text {
            white-space: pre-line;
            line-height: 1.8;
            color: #444;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .registration-container {
                padding: 20px 15px;
            }

            .activity-header {
                padding: 30px 20px;
            }

            .activity-title {
                font-size: 2rem;
            }

            .card-modern {
                padding: 20px;
            }

            .btn-custom {
                padding: 14px 24px;
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

        /* Scroll personnalisé */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: var(--gray-light);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--primary-blue);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--secondary-blue);
        }
    </style>
</head>
<body>
    <?php include "../includes/header.php"; ?>

    <div class="registration-container">
        <!-- En-tête de l'activité -->
        <div class="activity-header fade-in">
            <div class="d-flex justify-content-between align-items-start mb-4">
                <h1 class="activity-title"><?= htmlspecialchars($activite['nom_activite'] ?? '') ?></h1>
                <div>
                    <?php if ($est_cloturee): ?>
                        <span class="badge-custom badge-closed">
                            <i class="fas fa-lock"></i> Inscriptions fermées
                        </span>
                    <?php else: ?>
                        <span class="badge-custom badge-open">
                            <i class="fas fa-lock-open"></i> Inscriptions ouvertes
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            
            <p style="font-size: 1.1rem; opacity: 0.9;">
                Année académique : <strong><?= htmlspecialchars($activite['annee_academique'] ?? '') ?></strong>
            </p>
            
            <div class="activity-meta">
                <div class="meta-item">
                    <i class="fas fa-users"></i>
                    <span>Type : <?= ucfirst($activite['type_participation'] ?? '') ?></span>
                    <span class="badge-custom badge-type ms-2"><?= ucfirst($activite['type_participation'] ?? '') ?></span>
                </div>
                <div class="meta-item">
                    <i class="fas fa-door-open"></i>
                    <span>Accès : <?= ucfirst($activite['conditions'] ?? '') ?></span>
                    <span class="badge-custom badge-access ms-2"><?= ucfirst($activite['conditions'] ?? '') ?></span>
                </div>
                <div class="meta-item">
                    <i class="fas fa-clock"></i>
                    <span>Date limite : 
                        <?= $activite['date_limite_inscription'] ? date('d/m/Y H:i', strtotime($activite['date_limite_inscription'])) : 'Illimitée' ?>
                    </span>
                </div>
            </div>
        </div>

        <div class="main-section">
            <!-- Contenu principal -->
            <div class="main-content">
                <!-- Description -->
                <div class="activity-description fade-in">
                    <h3 style="color: var(--primary-blue); margin-bottom: 20px; border-bottom: 2px solid var(--light-blue); padding-bottom: 10px;">
                        <i class="fas fa-align-left"></i> Description
                    </h3>
                    <div class="description-text">
                        <?= nl2br(htmlspecialchars($activite['description'] ?? '')) ?>
                    </div>
                </div>

                <!-- Formulaire / Statut -->
                <div class="card-modern fade-in">
                    <h2 class="card-title">
                        <i class="fas fa-file-signature"></i> 
                        <?= $inscription_existante ? 'Statut d\'inscription' : 'Formulaire d\'inscription' ?>
                    </h2>

                    <?php if ($success): ?>
                        <div class="alert-message alert-success fade-in">
                            <i class="fas fa-check-circle fa-2x"></i>
                            <div>
                                <h4 style="margin-bottom: 5px;">Inscription réussie !</h4>
                                <p><?= htmlspecialchars($success) ?></p>
                            </div>
                        </div>
                    <?php elseif ($error): ?>
                        <div class="alert-message alert-error fade-in">
                            <i class="fas fa-exclamation-circle fa-2x"></i>
                            <div>
                                <h4 style="margin-bottom: 5px;">Erreur</h4>
                                <p><?= htmlspecialchars($error) ?></p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($inscription_existante): ?>
                        <div class="text-center py-4">
                            <div style="font-size: 5rem; color: var(--success); margin-bottom: 20px;">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <h3 style="color: var(--success); margin-bottom: 20px;">Vous êtes déjà inscrit !</h3>
                            <div style="background: var(--gray-light); padding: 20px; border-radius: var(--radius-sm); max-width: 400px; margin: 0 auto;">
                                <p style="margin-bottom: 10px;">
                                    <strong>Statut :</strong> 
                                    <span class="badge-custom" style="background: rgba(33, 150, 243, 0.1); color: #2196f3; border: 2px solid #2196f3;">
                                        <?= strtoupper($inscription_existante['statut'] ?? '') ?>
                                    </span>
                                </p>
                                <?php if($inscription_existante['nom_groupe']): ?>
                                    <p style="margin-bottom: 0;">
                                        <strong>Équipe :</strong> 
                                        <span style="color: var(--primary-blue); font-weight: 600;">
                                            <?= htmlspecialchars($inscription_existante['nom_groupe'] ?? '') ?>
                                        </span>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>

                    <?php elseif ($est_cloturee): ?>
                        <div class="alert-message alert-warning fade-in">
                            <i class="fas fa-exclamation-triangle fa-2x"></i>
                            <div>
                                <h4 style="margin-bottom: 5px;">Inscriptions terminées</h4>
                                <p>Désolé, les inscriptions pour cette activité sont terminées ou fermées.</p>
                            </div>
                        </div>

                    <?php elseif ($reservee_isspt && !$isLogged): ?>
                        <div class="text-center py-4">
                            <div style="font-size: 5rem; color: var(--accent-red); margin-bottom: 20px;">
                                <i class="fas fa-lock"></i>
                            </div>
                            <h3 style="color: var(--primary-blue); margin-bottom: 15px;">
                                Activité réservée aux étudiants ISSPT
                            </h3>
                            <p style="color: var(--gray-dark); margin-bottom: 30px;">
                                Cette activité est exclusivement réservée aux étudiants de l'ISSPT.
                                Connectez-vous avec votre compte étudiant pour vous inscrire.
                            </p>
                            <a href="login.php" class="btn-custom btn-primary-custom" style="width: auto; padding: 14px 40px;">
                                <i class="fas fa-sign-in-alt"></i> Se connecter pour s'inscrire
                            </a>
                        </div>

                    <?php else: ?>
                        <form method="POST" action="" class="fade-in">
                            <?php if ($activite['type_participation'] === 'individuel'): ?>
                                <?php if (!$isLogged): ?>
                                    <div class="form-group">
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Nom</label>
                                                <input type="text" name="nom" class="form-control-custom" placeholder="Votre nom" required>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Prénom</label>
                                                <input type="text" name="prenom" class="form-control-custom" placeholder="Votre prénom" required>
                                            </div>
                                        </div>
                                        <div class="alert-message" style="background: rgba(33, 150, 243, 0.1); border-left-color: #2196f3; color: #1565c0;">
                                            <i class="fas fa-info-circle"></i>
                                            Étudiants ISSPT ? Connectez-vous pour un suivi automatique de votre inscription.
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="alert-message" style="background: rgba(76, 175, 80, 0.1); border-left-color: var(--success); color: #2e7d32;">
                                        <i class="fas fa-user-check"></i>
                                        Vous allez vous inscrire en tant que <strong><?= htmlspecialchars($userName) ?></strong>
                                    </div>
                                <?php endif; ?>

                            <?php else: // Participation en ÉQUIPE ?>
                                <div class="form-group">
                                    <label class="form-label">
                                        <i class="fas fa-flag"></i> Nom de l'équipe
                                    </label>
                                    <input type="text" name="nom_groupe" class="form-control-custom" 
                                           placeholder="Ex: Les Aigles de l'ISSPT, Les Champions..." required>
                                </div>

                                <?php if ($isLogged): ?>
                                    <div class="form-group">
                                        <label class="form-label">
                                            <i class="fas fa-user-friends"></i> Sélectionnez vos coéquipiers (ISSPT)
                                        </label>
                                        <select name="membres[]" class="form-select-custom" multiple size="5">
                                            <?php
                                            $etudiants = $pdo->query("SELECT id_etudiant, nom, prenom FROM etudiants WHERE statut='actif' AND id_etudiant != $id_etudiant_session")->fetchAll();
                                            foreach($etudiants as $e): 
                                            ?>
                                                <option value="<?= $e['id_etudiant'] ?>">
                                                    <?= htmlspecialchars($e['nom']) ?> <?= htmlspecialchars($e['prenom']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div style="color: var(--gray-dark); font-size: 0.9rem; margin-top: 8px;">
                                            <i class="fas fa-info-circle"></i> Maintenez Ctrl (ou Cmd) pour sélectionner plusieurs coéquipiers
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="form-group">
                                        <label class="form-label">
                                            <i class="fas fa-users"></i> Noms des membres (séparés par des virgules)
                                        </label>
                                        <textarea name="membres_externes_texte" class="form-textarea" 
                                                  rows="3" 
                                                  placeholder="Ex: Jean Dupont, Marie Sarr, Pierre Ndiaye..."></textarea>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>

                            <div style="margin-top: 40px; padding-top: 25px; border-top: 2px solid var(--gray-light);">
                                <button type="submit" class="btn-custom btn-success-custom">
                                    <i class="fas fa-paper-plane"></i> Confirmer l'inscription
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="sidebar-section">
                <div class="status-card fade-in">
                    <div class="status-icon">
                        <i class="fas fa-<?= $inscription_existante ? 'check-circle' : ($est_cloturee ? 'lock' : 'clipboard-check') ?>"></i>
                    </div>
                    <h3 class="status-title">
                        <?= $inscription_existante ? 'Inscription confirmée' : ($est_cloturee ? 'Inscriptions fermées' : 'Inscription ouverte') ?>
                    </h3>
                    <p style="color: var(--gray-dark); margin-bottom: 25px;">
                        <?php if ($inscription_existante): ?>
                            Votre inscription a été enregistrée avec succès. Vous recevrez une confirmation par email.
                        <?php elseif ($est_cloturee): ?>
                            La période d'inscription pour cette activité est terminée.
                        <?php else: ?>
                            Il reste 
                            <?php if ($date_limite): 
                                $interval = $maintenant->diff($date_limite);
                                echo $interval->format('%d jours et %h heures');
                            else: ?>
                            pas de limite de temps
                            <?php endif; ?> 
                            pour s'inscrire.
                        <?php endif; ?>
                    </p>
                    
                    <?php if (!$inscription_existante && !$est_cloturee): ?>
                        <a href="details_activite.php?id=<?= $id_activite ?>" class="btn-custom btn-secondary-custom">
                            <i class="fas fa-eye"></i> Voir les détails de l'activité
                        </a>
                    <?php endif; ?>
                </div>

                <div class="card-modern fade-in">
                    <h3 class="card-title">
                        <i class="fas fa-info-circle"></i> Informations importantes
                    </h3>
                    <ul style="list-style: none; padding: 0;">
                        <li style="margin-bottom: 15px; padding-left: 25px; position: relative;">
                            <i class="fas fa-check-circle" style="color: var(--success); position: absolute; left: 0;"></i>
                            Vérifiez vos informations avant de soumettre
                        </li>
                        <li style="margin-bottom: 15px; padding-left: 25px; position: relative;">
                            <i class="fas fa-check-circle" style="color: var(--success); position: absolute; left: 0;"></i>
                            Vous recevrez un email de confirmation
                        </li>
                        <li style="margin-bottom: 15px; padding-left: 25px; position: relative;">
                            <i class="fas fa-check-circle" style="color: var(--success); position: absolute; left: 0;"></i>
                            Contactez l'administration pour toute modification
                        </li>
                        <li style="padding-left: 25px; position: relative;">
                            <i class="fas fa-check-circle" style="color: var(--success); position: absolute; left: 0;"></i>
                            Présentez-vous 15 minutes avant le début
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Animation des éléments au chargement
        document.addEventListener('DOMContentLoaded', function() {
            const elements = document.querySelectorAll('.fade-in');
            elements.forEach((el, index) => {
                el.style.opacity = '0';
                el.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    el.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                    el.style.opacity = '1';
                    el.style.transform = 'translateY(0)';
                }, index * 100);
            });

            // Confirmation avant soumission
            const form = document.querySelector('form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const confirmMessage = "Confirmez-vous votre inscription ? Cette action est définitive.";
                    if (!confirm(confirmMessage)) {
                        e.preventDefault();
                    }
                });
            }

            // Amélioration de l'expérience du formulaire
            const inputs = document.querySelectorAll('.form-control-custom, .form-select-custom, .form-textarea');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.style.transform = 'translateY(-2px)';
                });
                
                input.addEventListener('blur', function() {
                    this.parentElement.style.transform = 'translateY(0)';
                });
            });
        });
    </script>
</body>
</html>