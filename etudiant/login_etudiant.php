<?php
session_start();
require_once '../includes/db.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifiant = trim($_POST['identifiant']);
    $mot_de_passe = $_POST['mot_de_passe'];

    // Vérifier si l'identifiant correspond à un email ou un téléphone
    $sql = "SELECT * FROM etudiants WHERE (email = :identifiant OR telephone = :identifiant) AND statut = 'actif' LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['identifiant' => $identifiant]);
    $etudiant = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($etudiant && password_verify($mot_de_passe, $etudiant['mot_de_passe'])) {
        
        // Vérifier si l'étudiant est membre du bureau (admin)
        $sqlAdmin = "SELECT * FROM administrateurs WHERE id_etudiant = :id_etudiant AND role = 'bureau' LIMIT 1";
        $stmtAdmin = $pdo->prepare($sqlAdmin);
        $stmtAdmin->execute(['id_etudiant' => $etudiant['id_etudiant']]);
        $admin = $stmtAdmin->fetch(PDO::FETCH_ASSOC);

        $acceptTerms = isset($_POST['termsAccepted']) && $_POST['termsAccepted'] == '1' ? 1 : 0;

        if($acceptTerms && $etudiant['accepte_cgu'] != 1){
            $stmtUpdate = $pdo->prepare("UPDATE etudiants SET accepte_cgu = 1, date_acceptation_cgu = NOW() WHERE id_etudiant = :id");
            $stmtUpdate->execute(['id' => $etudiant['id_etudiant']]);
        }

        if ($admin) {
            $_SESSION['admin_id'] = $admin['id_admin'];
            $_SESSION['admin_role'] = $admin['role'];
            $_SESSION['admin_poste'] = $admin['poste_bureau'];
            header('Location: ../admins/dashboard.php');
            exit;
        } else {
            $_SESSION['etudiant_id'] = $etudiant['id_etudiant'];
            $_SESSION['etudiant_nom'] = $etudiant['nom'];
            $_SESSION['etudiant_prenom'] = $etudiant['prenom'];
            header('Location: ../index.php');
            exit;
        }

    } else {
        $message = "<div class='alert-error'><i class='fa-solid fa-circle-exclamation'></i> Identifiants incorrects ou compte inactif.</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion Étudiant | ISSPT</title>
    <!-- FontAwesome Pro Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">

    <meta name="description" content="Plateforme officielle d'accès aux ressources, épreuves et activités de l'Institut Supérieur Saint Paul Tarse.">

    <meta name="author" content="Étudiants de Système Informatique et Logiciel - ISSPT">

    <link rel="icon" type="image/png" href="../assets/images/logo.png">
    <link rel="apple-touch-icon" href="../assets/images/logo.png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    
    <style>
        :root {
            /* Colors - Dark Theme (default) */
            --primary-900: #080020;
            --primary-800: #0a0127;
            --primary-700: #120c3a;
            --primary-600: #1a1849;
            --accent-red: #ff4757;
            --accent-blue: #2e86de;
            --accent-green: #10ac84;
            --white: #ffffff;
            --gray-50: #f8f9fa;
            --gray-100: #f1f2f6;
            --gray-200: #dfe4ea;
            --gray-300: #ced6e0;
            --gray-400: #a4b0be;

            /* Sidebar */
            --sidebar-width: 280px;
            --sidebar-width-collapsed: 70px;
            --sidebar-bg: linear-gradient(180deg, var(--primary-900) 0%, var(--primary-800) 100%);
            --sidebar-border: 1px solid rgba(255, 255, 255, 0.05);
            --sidebar-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            
            /* Typography */
            --font-primary: 'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif;
            --font-size-xs: 0.75rem;   /* 12px */
            --font-size-sm: 0.875rem;  /* 14px */
            --font-size-md: 1rem;      /* 16px */
            --font-size-lg: 1.125rem;  /* 18px */
            --font-size-xl: 1.25rem;   /* 20px */
            
            /* Spacing */
            --space-1: 0.25rem;   /* 4px */
            --space-2: 0.5rem;    /* 8px */
            --space-3: 0.75rem;   /* 12px */
            --space-4: 1rem;      /* 16px */
            --space-5: 1.5rem;    /* 24px */
            --space-6: 2rem;      /* 32px */
            
            /* Transitions */
            --transition-fast: 150ms cubic-bezier(0.4, 0, 0.2, 1);
            --transition-base: 300ms cubic-bezier(0.4, 0, 0.2, 1);
            --transition-slow: 500ms cubic-bezier(0.4, 0, 0.2, 1);
            
            /* Shadows */
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.12);
            --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 25px rgba(0, 0, 0, 0.2);
            --shadow-xl: 0 20px 50px rgba(0, 0, 0, 0.3);
            
            /* Border Radius */
            --radius-sm: 4px;
            --radius-md: 8px;
            --radius-lg: 12px;
            --radius-xl: 20px;
            --radius-full: 9999px;
            
            /* Z-index */
            --z-sidebar: 1000;
            --z-overlay: 999;
            --z-mobile-toggle: 1001;
        }

        /* Base Resets */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: var(--font-primary);
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        body {
            background-color: var(--primary-900);
            background-image: 
                radial-gradient(at 0% 0%, rgba(46, 134, 222, 0.12) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(16, 172, 132, 0.08) 0px, transparent 50%),
                linear-gradient(135deg, var(--primary-900) 0%, var(--primary-800) 100__);
            background-attachment: fixed;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: var(--space-4);
            color: var(--white);
            overflow-x: hidden;
        }

        /* Layout Écran Split Pro (Grand Écran) */
        .page-wrapper {
            display: flex;
            width: 100%;
            max-width: 1000px;
            min-height: 620px;
            background: rgba(26, 24, 73, 0.25);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-xl);
            overflow: hidden;
            transition: box-shadow var(--transition-base);
        }

        .page-wrapper:hover {
            box-shadow: 0 30px 70px rgba(0, 0, 0, 0.5), 0 0 50px rgba(46, 134, 222, 0.15);
        }

        /* Section Visuelle Gauche */
        .brand-panel {
            flex: 1;
            background: linear-gradient(135deg, rgba(18, 12, 58, 0.85) 0%, rgba(10, 1, 39, 0.95) 100%);
            border-right: 1px solid rgba(255, 255, 255, 0.04);
            padding: var(--space-6);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
        }

        /* Éléments de décoration abstraits d'arrière-plan */
        .brand-panel::before {
            content: '';
            position: absolute;
            top: -20%;
            left: -20%;
            width: 300px;
            height: 300px;
            background: var(--accent-blue);
            filter: blur(120px);
            opacity: 0.15;
            pointer-events: none;
        }

        .brand-top {
            display: flex;
            align-items: center;
            gap: var(--space-3);
        }

        .brand-top img {
            height: 50px;
            width: auto;
            object-fit: contain;
        }

        .brand-meta h2 {
            font-size: var(--font-size-md);
            font-weight: 700;
            letter-spacing: 1px;
            color: var(--white);
            text-transform: uppercase;
        }

        .brand-meta span {
            font-size: var(--font-size-xs);
            color: var(--accent-blue);
            font-weight: 600;
        }

        .brand-showcase {
            margin: var(--space-5) 0;
        }

        .brand-showcase h1 {
            font-size: 2rem;
            font-weight: 800;
            line-height: 1.2;
            background: linear-gradient(135deg, var(--white) 30%, var(--gray-400) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: var(--space-3);
        }

        .brand-showcase p {
            color: var(--gray-400);
            font-size: var(--font-size-sm);
            line-height: 1.6;
        }

        .brand-footer {
            font-size: var(--font-size-xs);
            color: rgba(164, 176, 190, 0.4);
            display: flex;
            align-items: center;
            gap: var(--space-2);
        }

        /* Section Formulaire Droite */
        .form-panel {
            width: 460px;
            padding: var(--space-6) var(--space-5);
            display: flex;
            flex-direction: column;
            justify-content: center;
            background: rgba(8, 0, 32, 0.2);
        }

        .form-header {
            margin-bottom: var(--space-5);
        }

        .form-header h3 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--white);
            letter-spacing: -0.5px;
        }

        .form-header p {
            color: var(--gray-400);
            font-size: var(--font-size-sm);
            margin-top: var(--space-1);
        }

        /* Formulaire & Inputs */
        .form-group {
            margin-bottom: var(--space-4);
        }

        .form-label {
            display: block;
            color: var(--gray-200);
            font-size: var(--font-size-sm);
            font-weight: 500;
            margin-bottom: var(--space-2);
        }

        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-wrapper i {
            position: absolute;
            left: var(--space-4);
            color: rgba(164, 176, 190, 0.5);
            font-size: var(--font-size-md);
            transition: color var(--transition-fast), transform var(--transition-fast);
            pointer-events: none;
        }

        .form-control {
            width: 100%;
            padding: 14px var(--space-4) 14px 46px;
            background: rgba(8, 0, 32, 0.7);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: var(--radius-lg);
            color: var(--white);
            font-size: var(--font-size-md);
            outline: none;
            transition: border-color var(--transition-base), box-shadow var(--transition-base), background-color var(--transition-base);
        }

        .form-control::placeholder {
            color: rgba(164, 176, 190, 0.3);
        }

        .form-control:focus {
            border-color: var(--accent-blue);
            background: rgba(8, 0, 32, 0.9);
            box-shadow: 0 0 0 4px rgba(46, 134, 222, 0.15);
        }

        .form-control:focus + i {
            color: var(--accent-blue);
            transform: scale(1.05);
        }

        /* Notifications d'erreurs épurées */
        .alert-error {
            background: rgba(255, 71, 87, 0.1);
            border: 1px solid rgba(255, 71, 87, 0.3);
            border-radius: var(--radius-lg);
            color: #ff6b81;
            padding: var(--space-3) var(--space-4);
            font-size: var(--font-size-sm);
            margin-bottom: var(--space-4);
            display: flex;
            align-items: center;
            gap: var(--space-2);
            font-weight: 500;
            animation: shake 400ms ease-in-out;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-4px); }
            75% { transform: translateX(4px); }
        }

        /* Bouton Soumettre Haute Qualité */
        .btn-submit {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, var(--accent-blue) 0%, #1b6ca8 100%);
            border: none;
            border-radius: var(--radius-lg);
            color: var(--white);
            font-size: var(--font-size-md);
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(46, 134, 222, 0.25);
            transition: transform var(--transition-fast), filter var(--transition-fast), box-shadow var(--transition-fast);
            margin-top: var(--space-2);
            display: flex;
            justify-content: center;
            align-items: center;
            gap: var(--space-2);
        }

        .btn-submit:hover {
            filter: brightness(1.1);
            box-shadow: 0 6px 22px rgba(46, 134, 222, 0.35);
        }

        .btn-submit:active {
            transform: scale(0.98);
        }

        /* Footer formulaire */
        .form-footer {
            text-align: center;
            margin-top: var(--space-5);
        }

        .forgot-password-link {
            color: var(--gray-400);
            text-decoration: none;
            font-size: var(--font-size-sm);
            transition: color var(--transition-fast);
            font-weight: 500;
        }

        .forgot-password-link:hover {
            color: var(--white);
            text-decoration: underline;
        }

        /* Responsivité sans compromis (Breakpoints stratégiques) */
        @media (max-width: 900px) {
            .page-wrapper {
                max-width: 480px;
                flex-direction: column;
                min-height: auto;
            }

            .brand-panel {
                display: none; /* Cache le panneau d'affichage lourd sur tablette/mobile */
            }

            .form-panel {
                width: 100%;
                padding: var(--space-5) var(--space-4);
                background: rgba(26, 24, 73, 0.5);
            }
            
            /* Réintroduction légère du logo dans le header du formulaire pour le mobile */
            .form-header::before {
                content: '';
                display: block;
                width: 50px;
                height: 50px;
                background-image: url('../assets/images/logo.png');
                background-size: contain;
                background-repeat: no-repeat;
                background-position: left;
                margin-bottom: var(--space-4);
            }
        }

        @media (max-width: 480px) {
            body {
                padding: var(--space-3);
            }

            .page-wrapper {
                border-radius: var(--radius-lg);
            }

            .form-header h3 {
                font-size: var(--font-size-xl);
            }

            .form-control {
                padding: 13px var(--space-4) 13px 44px;
                font-size: var(--font-size-sm);
            }

            .btn-submit {
                padding: 13px;
            }
        }
    </style>
</head>
<body>

<div class="page-wrapper">
    <!-- Panneau de Gauche : Identité Institutionnelle (Masqué sur Mobile) -->
    <div class="brand-panel">
        <div class="brand-top">
            <img src="../assets/images/logo.png" alt="ISSPT Logo" onerror="this.style.display='none'">
            <div class="brand-meta">
                <h2>ISSPT</h2>
                <span>Espace Numérique</span>
            </div>
        </div>
        
        <div class="brand-showcase">
            <h1>Vivez votre expérience universitaire.</h1>
            <p>Accéder aux anciens epreuves et vivez pleinement la Journée de l'Étudiant Tarsien </p>
        </div>
        
        <div class="brand-footer">
            <i class="fa-solid fa-circle-nodes"></i>
            <span>Système de Gestion Intégré ISSPT</span>
        </div>
    </div>

    <!-- Panneau de Droite : Formulaire d'authentification -->
    <div class="form-panel">
        <div class="form-header">
            <h3>Portail Authentification</h3>
            <p>Renseignez vos accès pour ouvrir votre session.</p>
        </div>

        <!-- Zone d'erreurs -->
        <?= $message; ?>

        <form method="POST">
            <!-- Identifiant -->
            <div class="form-group">
                <label class="form-label">Email ou Numéro de téléphone</label>
                <div class="input-wrapper">
                    <input type="text" name="identifiant" class="form-control" placeholder="nom@etudiant.isspt.tg ou téléphone" required autocomplete="username">
                    <i class="fa-solid fa-user-graduate"></i>
                </div>
            </div>

            <!-- Mot de passe -->
            <div class="form-group">
                <label class="form-label">Mot de passe</label>
                <div class="input-wrapper">
                    <input type="password" name="mot_de_passe" class="form-control" placeholder="••••••••" required autocomplete="current-password">
                    <i class="fa-solid fa-key"></i>
                </div>
            </div>

            <!-- Stockage local sécurisé des CGU -->
            <input type="hidden" name="termsAccepted" id="termsAccepted" value="0">
            <script>
            document.addEventListener("DOMContentLoaded", function() {
                if(localStorage.getItem('termsAccepted') === 'true'){
                    document.getElementById('termsAccepted').value = '1';
                }
            });
            </script>

            <!-- Bouton d'action -->
            <button type="submit" class="btn-submit">
                <span>Se connecter</span>
                <i class="fa-solid fa-arrow-right-to-bracket"></i>
            </button>

            <!-- Liens d'aide -->
            <div class="form-footer">
                <a href="#" class="forgot-password-link">Identifiants ou mot de passe perdu ? Contacter le bureau des étudiants</a>
            </div>
        </form>
    </div>
</div>

</body>
</html>