<?php
// Sécurisation : vérifier si l'admin est connecté
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit;
}

// Récupération nom/prénom en fonction du rôle
$prenom = "";
$nom = "";
$adminPhoto = $_SESSION['admin_photo'] ?? "default.png";
$adminEmail = $_SESSION['admin_email'] ?? "";

if ($_SESSION['admin_role'] === 'bureau' && !empty($_SESSION['admin_id_etudiant'])) {
    $stmt = $pdo->prepare("SELECT nom, prenom FROM etudiants WHERE id_etudiant = ?");
    $stmt->execute([$_SESSION['admin_id_etudiant']]);
    $etu = $stmt->fetch(PDO::FETCH_ASSOC);
    $prenom = $etu['prenom'] ?? "Bureau";
    $nom = $etu['nom'] ?? "Membre";
} else {
    $prenom = $_SESSION['admin_prenom'] ?? "Admin";
    $nom = $_SESSION['admin_nom'] ?? "";
}

// Récupérer les notifications
$notifications = [
    'total' => 0,
    'unread' => 0,
    'items' => []
];
?>


    <style>
        /* ============================================
           VARIABLES GLOBALES
        ============================================= */
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
            
            /* Variables header */
            --header-height: 70px;
            --header-bg: var(--primary-blue);
            --header-border: 1px solid rgba(255, 255, 255, 0.1);
            --header-text: var(--white);
            --header-text-muted: rgba(255, 255, 255, 0.7);
            --header-hover: rgba(255, 255, 255, 0.05);
            --header-accent: var(--accent-red);
        }

        /* ============================================
           HEADER PRINCIPAL
        ============================================= */
        .admin-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: var(--header-height);
            background: var(--header-bg);
            backdrop-filter: blur(10px);
            border-bottom: var(--header-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 2rem;
            z-index: 900;
            transition: var(--transition);
            box-shadow: var(--shadow-md);
        }

        /* Ajustement pour sidebar */
        @media (min-width: 1201px) {
            .admin-sidebar:not(.collapsed) ~ .admin-header {
                left: 280px;
                right: 0;
            }
            
            .admin-sidebar.collapsed ~ .admin-header {
                left: 80px;
                right: 0;
            }
        }

        /* Section gauche */
        .header-left {
            display: flex;
            align-items: center;
            gap: 2rem;
            flex: 1;
        }

        .page-title h1 {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--header-text);
            margin-bottom: 0.25rem;
            line-height: 1.2;
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.85rem;
            color: var(--header-text-muted);
        }

        .breadcrumb a {
            color: var(--header-text-muted);
            text-decoration: none;
            transition: var(--transition);
        }

        .breadcrumb a:hover {
            color: var(--header-text);
        }

        .breadcrumb i {
            font-size: 0.7rem;
            opacity: 0.5;
        }

        /* Section droite */
        .header-right {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        /* Recherche */
        .search-container {
            position: relative;
        }

        .search-toggle {
            display: none;
            width: 40px;
            height: 40px;
            border-radius: var(--radius-sm);
            background: var(--header-hover);
            border: none;
            color: var(--header-text);
            cursor: pointer;
            transition: var(--transition);
        }

        .search-toggle:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .search-box {
            display: flex;
            align-items: center;
            background: rgba(255, 255, 255, 0.08);
            border-radius: var(--radius-sm);
            padding: 0.5rem 1rem;
            width: 300px;
            transition: var(--transition);
            position: relative;
        }

        .search-box:focus-within {
            background: rgba(255, 255, 255, 0.12);
            box-shadow: 0 0 0 2px rgba(211, 47, 47, 0.2);
        }

        .search-icon {
            color: var(--header-text-muted);
            margin-right: 0.75rem;
            font-size: 1rem;
        }

        .search-input {
            flex: 1;
            background: transparent;
            border: none;
            color: var(--header-text);
            font-size: 0.9rem;
            outline: none;
            width: 100%;
        }

        .search-input::placeholder {
            color: var(--header-text-muted);
        }

        .search-close {
            display: none;
            background: none;
            border: none;
            color: var(--header-text-muted);
            cursor: pointer;
            margin-left: 0.5rem;
            transition: var(--transition);
        }

        .search-close:hover {
            color: var(--header-text);
        }

        .search-results {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: var(--header-bg);
            border-radius: var(--radius-md);
            margin-top: 0.5rem;
            box-shadow: var(--shadow-lg);
            max-height: 400px;
            overflow-y: auto;
            z-index: 1000;
            border: var(--header-border);
        }

        /* Notifications */
        .notifications-container {
            position: relative;
        }

        .notification-btn {
            width: 40px;
            height: 40px;
            border-radius: var(--radius-sm);
            background: var(--header-hover);
            border: none;
            color: var(--header-text);
            cursor: pointer;
            transition: var(--transition);
            position: relative;
        }

        .notification-btn:hover {
            background: rgba(255, 255, 255, 0.1);
            color: var(--header-accent);
        }

        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--danger);
            color: white;
            font-size: 0.7rem;
            min-width: 18px;
            height: 18px;
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        .notifications-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            width: 350px;
            background: var(--header-bg);
            border-radius: var(--radius-md);
            margin-top: 0.5rem;
            box-shadow: var(--shadow-lg);
            display: none;
            z-index: 1000;
            border: var(--header-border);
            overflow: hidden;
        }

        .notifications-dropdown.show {
            display: block;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .dropdown-header {
            padding: 1rem;
            border-bottom: var(--header-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .dropdown-header h3 {
            font-size: 1rem;
            font-weight: 600;
            color: var(--header-text);
            margin: 0;
        }

        .mark-all-read {
            background: none;
            border: none;
            color: var(--header-accent);
            font-size: 0.8rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: var(--transition);
        }

        .mark-all-read:hover {
            opacity: 0.8;
        }

        .notifications-list {
            max-height: 300px;
            overflow-y: auto;
        }

        .notification-item {
            padding: 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            display: flex;
            gap: 1rem;
            transition: var(--transition);
            cursor: pointer;
        }

        .notification-item:hover {
            background: var(--header-hover);
        }

        .notification-item.unread {
            background: rgba(33, 150, 243, 0.05);
            border-left: 3px solid var(--header-accent);
        }

        .notification-icon {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--header-accent);
            flex-shrink: 0;
        }

        .notification-content {
            flex: 1;
        }

        .notification-text {
            color: var(--header-text);
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
            line-height: 1.4;
        }

        .notification-time {
            font-size: 0.75rem;
            color: var(--header-text-muted);
        }

        .empty-notifications {
            padding: 2rem 1rem;
            text-align: center;
            color: var(--header-text-muted);
        }

        .empty-notifications i {
            font-size: 2rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .dropdown-footer {
            padding: 1rem;
            border-top: var(--header-border);
            text-align: center;
        }

        .view-all {
            color: var(--header-accent);
            text-decoration: none;
            font-size: 0.9rem;
            transition: var(--transition);
        }

        .view-all:hover {
            text-decoration: underline;
        }

        /* Quick Actions */
        .quick-actions {
            position: relative;
        }

        .action-btn {
            width: 40px;
            height: 40px;
            border-radius: var(--radius-sm);
            background: var(--header-hover);
            border: none;
            color: var(--header-text);
            cursor: pointer;
            transition: var(--transition);
        }

        .action-btn:hover {
            background: var(--header-accent);
            transform: scale(1.05);
        }

        .actions-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            width: 220px;
            background: var(--header-bg);
            border-radius: var(--radius-md);
            margin-top: 0.5rem;
            box-shadow: var(--shadow-lg);
            display: none;
            z-index: 1000;
            border: var(--header-border);
        }

        .actions-dropdown.show {
            display: block;
            animation: slideDown 0.3s ease;
        }

        .action-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            color: var(--header-text);
            text-decoration: none;
            transition: var(--transition);
        }

        .action-item:hover {
            background: var(--header-hover);
        }

        .action-item i {
            width: 20px;
            text-align: center;
            color: var(--header-accent);
        }

        .dropdown-divider {
            height: 1px;
            background: rgba(255, 255, 255, 0.1);
            margin: 0.5rem 0;
        }

        /* Profil Utilisateur */
        .profile-container {
            position: relative;
        }

        .profile-btn {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.5rem;
            background: var(--header-hover);
            border: none;
            border-radius: var(--radius-sm);
            color: var(--header-text);
            cursor: pointer;
            transition: var(--transition);
            min-width: 160px;
        }

        .profile-btn:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .profile-avatar {
            position: relative;
            width: 36px;
            height: 36px;
        }

        .profile-avatar img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--header-accent);
        }

        .user-status {
            position: absolute;
            bottom: 0;
            right: 0;
            width: 10px;
            height: 10px;
            background: var(--success);
            border-radius: 50%;
            border: 2px solid var(--header-bg);
        }

        .profile-info {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            flex: 1;
        }

        .user-name {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--header-text);
            line-height: 1.2;
        }

        .user-role {
            font-size: 0.75rem;
            color: var(--header-text-muted);
            line-height: 1.2;
        }

        .dropdown-arrow {
            font-size: 0.8rem;
            transition: var(--transition);
        }

        .profile-btn.active .dropdown-arrow {
            transform: rotate(180deg);
        }

        .profile-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            width: 280px;
            background: var(--header-bg);
            border-radius: var(--radius-md);
            margin-top: 0.5rem;
            box-shadow: var(--shadow-lg);
            display: none;
            z-index: 1000;
            border: var(--header-border);
            overflow: hidden;
        }

        .profile-dropdown.show {
            display: block;
            animation: slideDown 0.3s ease;
        }

        .dropdown-profile-header {
            padding: 1.5rem;
            background: linear-gradient(135deg, rgba(211, 47, 47, 0.1), transparent);
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .dropdown-avatar {
            width: 50px;
            height: 50px;
        }

        .dropdown-avatar img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--header-accent);
        }

        .dropdown-profile-info h4 {
            font-size: 1rem;
            font-weight: 600;
            color: var(--header-text);
            margin-bottom: 0.25rem;
        }

        .dropdown-profile-info p {
            font-size: 0.85rem;
            color: var(--header-text-muted);
            margin-bottom: 0.5rem;
        }

        .dropdown-role {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            background: rgba(211, 47, 47, 0.2);
            color: var(--header-accent);
            font-size: 0.75rem;
            border-radius: 12px;
            font-weight: 500;
        }

        .dropdown-menu {
            padding: 0.5rem 0;
        }

        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.75rem 1.5rem;
            color: var(--header-text);
            text-decoration: none;
            transition: var(--transition);
        }

        .dropdown-item:hover {
            background: var(--header-hover);
        }

        .dropdown-item i {
            width: 20px;
            text-align: center;
            color: var(--header-text-muted);
        }

        .dropdown-item.logout {
            color: var(--danger);
        }

        .dropdown-item.logout:hover {
            background: rgba(244, 67, 54, 0.1);
        }

        .dropdown-item.logout i {
            color: var(--danger);
        }

        /* Menu Mobile */
        .mobile-menu-btn {
            display: none;
            width: 40px;
            height: 40px;
            border-radius: var(--radius-sm);
            background: var(--header-hover);
            border: none;
            color: var(--header-text);
            cursor: pointer;
            transition: var(--transition);
        }

        .mobile-menu-btn:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        /* Overlay */
        .header-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(2px);
            z-index: 800;
            display: none;
        }

        .header-overlay.active {
            display: block;
        }

        /* ============================================
           RESPONSIVE DESIGN
        ============================================= */
        @media (max-width: 1200px) {
            .admin-header {
                left: 0 !important;
                right: 0 !important;
                padding: 0 1rem;
            }
            
            .search-box {
                width: 250px;
            }
            
            .profile-btn {
                min-width: auto;
            }
            
            .profile-info {
                display: none;
            }
        }

        @media (max-width: 992px) {
            .search-box {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                width: 100%;
                border-radius: 0;
                padding: 1rem;
                background: var(--header-bg);
                z-index: 1001;
                display: none;
            }
            
            .search-box.show {
                display: flex;
            }
            
            .search-toggle {
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .search-close {
                display: block;
            }
            
            .notifications-dropdown {
                right: -100px;
                width: 300px;
            }
            
            .profile-dropdown {
                right: -50px;
            }
        }

        @media (max-width: 768px) {
            .page-title h1 {
                font-size: 1.25rem;
            }
            
            .breadcrumb {
                display: none;
            }
            
            .search-container,
            .quick-actions {
                display: none;
            }
            
            .mobile-menu-btn {
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .profile-btn {
                min-width: auto;
                padding: 0.25rem;
            }
            
            .notifications-dropdown,
            .profile-dropdown {
                position: fixed;
                top: var(--header-height);
                left: 0;
                right: 0;
                width: 100%;
                margin-top: 0;
                border-radius: 0;
                border: none;
                border-top: var(--header-border);
                max-height: calc(100vh - var(--header-height));
                overflow-y: auto;
            }
        }

        @media (max-width: 480px) {
            .admin-header {
                padding: 0 0.75rem;
            }
            
            .page-title h1 {
                font-size: 1.1rem;
            }
            
            .notification-btn,
            .action-btn,
            .profile-btn,
            .mobile-menu-btn {
                width: 36px;
                height: 36px;
            }
        }
    </style>
<!-- Header Principal -->
    <header class="admin-header">
        <!-- Section gauche avec titre et recherche -->
        <div class="header-left">
            <!-- Titre dynamique basé sur la page -->
            <div class="page-title">
                <h1 id="pageTitle"><?= $pageTitle ?? 'Tableau de bord' ?></h1>
                <div class="breadcrumb" id="breadcrumb">
                    <a href="<?= BASE_URL ?>dashboard.php">Dashboard</a>
                    <i class="fas fa-chevron-right"></i>
                    <span><?= $pageTitle ?? 'Accueil' ?></span>
                </div>
            </div>
        </div>

        <!-- Section droite avec actions et profil -->
        <div class="header-right">
            <!-- Barre de recherche -->
            <div class="search-container">
                <button class="search-toggle" id="searchToggle">
                    <i class="fas fa-search"></i>
                </button>
                <div class="search-box" id="searchBox">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" class="search-input" placeholder="Rechercher un étudiant, une épreuve...">
                    <button class="search-close" id="searchClose">
                        <i class="fas fa-times"></i>
                    </button>
                    <div class="search-results" id="searchResults"></div>
                </div>
            </div>

            <!-- Notifications -->
            <div class="notifications-container">
                <button class="notification-btn" id="notificationBtn">
                    <i class="fas fa-bell"></i>
                    <?php if ($notifications['unread'] > 0): ?>
                    <span class="notification-badge"><?= $notifications['unread'] ?></span>
                    <?php endif; ?>
                </button>
                <div class="notifications-dropdown" id="notificationsDropdown">
                    <div class="dropdown-header">
                        <h3>Notifications</h3>
                        <?php if ($notifications['unread'] > 0): ?>
                        <button class="mark-all-read" id="markAllRead">
                            <i class="fas fa-check-double"></i>
                            Tout marquer comme lu
                        </button>
                        <?php endif; ?>
                    </div>
                    <div class="notifications-list">
                        <?php if (empty($notifications['items'])): ?>
                        <div class="empty-notifications">
                            <i class="fas fa-bell-slash"></i>
                            <p>Aucune notification</p>
                        </div>
                        <?php else: ?>
                            <?php foreach ($notifications['items'] as $notification): ?>
                            <div class="notification-item <?= $notification['unread'] ? 'unread' : '' ?>">
                                <div class="notification-icon">
                                    <i class="fas fa-<?= $notification['icon'] ?>"></i>
                                </div>
                                <div class="notification-content">
                                    <p class="notification-text"><?= $notification['text'] ?></p>
                                    <span class="notification-time"><?= $notification['time'] ?></span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <div class="dropdown-footer">
                        <a href="<?= BASE_URL ?>notifications.php" class="view-all">
                            Voir toutes les notifications
                        </a>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="quick-actions">
                <button class="action-btn" id="quickActionBtn" title="Actions rapides">
                    <i class="fas fa-bolt"></i>
                </button>
                <div class="actions-dropdown" id="actionsDropdown">
                    <a href="<?= BASE_URL ?>users/etudiants.php" class="action-item">
                        <i class="fas fa-user-plus"></i>
                        <span>Ajouter un étudiant</span>
                    </a>
                    <a href="<?= BASE_URL ?>epreuve/index.php" class="action-item">
                        <i class="fas fa-file-upload"></i>
                        <span>Nouvelle épreuve</span>
                    </a>
                    <a href="<?= BASE_URL ?>evenement/evenements.php" class="action-item">
                        <i class="fas fa-calendar-plus"></i>
                        <span>Créer un événement</span>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="<?= BASE_URL ?>settings.php" class="action-item">
                        <i class="fas fa-cog"></i>
                        <span>Paramètres rapides</span>
                    </a>
                </div>
            </div>

            <!-- Profil Utilisateur -->
            <div class="profile-container">
                <button class="profile-btn" id="profileBtn">
                    <div class="profile-avatar">
                        <img src="../uploads/admins/<?= htmlspecialchars($adminPhoto) ?>" 
                             alt="<?= htmlspecialchars("$prenom $nom") ?>"
                             onerror="this.src='../assets/default/avatar.png'">
                        <div class="user-status"></div>
                    </div>
                    <div class="profile-info">
                        <span class="user-name"><?= htmlspecialchars($prenom) ?></span>
                        <span class="user-role"><?= htmlspecialchars(ucfirst($_SESSION['admin_role'])) ?></span>
                    </div>
                    <i class="fas fa-chevron-down dropdown-arrow"></i>
                </button>
                
                <div class="profile-dropdown" id="profileDropdown">
                    <div class="dropdown-profile-header">
                        <div class="dropdown-avatar">
                            <img src="../uploads/photos_etudiants/<?= htmlspecialchars($adminPhoto) ?>" 
                                 alt="<?= htmlspecialchars("$prenom $nom") ?>"
                                 onerror="this.src='../assets/default/avatar.png'">
                        </div>
                        <div class="dropdown-profile-info">
                            <h4><?= htmlspecialchars("$prenom $nom") ?></h4>
                            <p><?= htmlspecialchars($adminEmail) ?></p>
                            <span class="dropdown-role"><?= htmlspecialchars(ucfirst($_SESSION['admin_role'])) ?></span>
                        </div>
                    </div>
                    
                    <div class="dropdown-menu">
                        <a href="<?= BASE_URL ?>profile.php" class="dropdown-item">
                            <i class="fas fa-user-circle"></i>
                            <span>Mon profil</span>
                        </a>
                        <a href="<?= BASE_URL ?>settings.php" class="dropdown-item">
                            <i class="fas fa-cog"></i>
                            <span>Paramètres</span>
                        </a>
                        <a href="<?= BASE_URL ?>security.php" class="dropdown-item">
                            <i class="fas fa-shield-alt"></i>
                            <span>Sécurité</span>
                        </a>
                        
                        <div class="dropdown-divider"></div>
                        
                        <a href="<?= BASE_URL ?>help.php" class="dropdown-item">
                            <i class="fas fa-question-circle"></i>
                            <span>Aide & Support</span>
                        </a>
                        <a href="<?= BASE_URL ?>feedback.php" class="dropdown-item">
                            <i class="fas fa-comment-alt"></i>
                            <span>Donner votre avis</span>
                        </a>
                        
                        <div class="dropdown-divider"></div>
                        
                        <a href="<?= BASE_URL ?>logout.php" class="dropdown-item logout">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Déconnexion</span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Menu Mobile -->
            <button class="mobile-menu-btn" id="mobileMenuBtn">
                <i class="fas fa-ellipsis-v"></i>
            </button>
        </div>

        <!-- Overlay pour dropdowns mobile -->
        <div class="header-overlay" id="headerOverlay"></div>
    </header>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Variables
            const profileBtn = document.getElementById('profileBtn');
            const profileDropdown = document.getElementById('profileDropdown');
            const notificationBtn = document.getElementById('notificationBtn');
            const notificationsDropdown = document.getElementById('notificationsDropdown');
            const quickActionBtn = document.getElementById('quickActionBtn');
            const actionsDropdown = document.getElementById('actionsDropdown');
            const searchToggle = document.getElementById('searchToggle');
            const searchBox = document.getElementById('searchBox');
            const searchClose = document.getElementById('searchClose');
            const headerOverlay = document.getElementById('headerOverlay');
            const mobileMenuBtn = document.getElementById('mobileMenuBtn');
            const markAllRead = document.getElementById('markAllRead');
            const searchInput = document.querySelector('.search-input');
            
            // Toggle profile dropdown
            profileBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                profileDropdown.classList.toggle('show');
                profileBtn.classList.toggle('active');
                headerOverlay.classList.toggle('active');
                closeOtherDropdowns(profileDropdown);
            });
            
            // Toggle notifications dropdown
            notificationBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                notificationsDropdown.classList.toggle('show');
                closeOtherDropdowns(notificationsDropdown);
                headerOverlay.classList.toggle('active');
            });
            
            // Toggle quick actions dropdown
            quickActionBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                actionsDropdown.classList.toggle('show');
                closeOtherDropdowns(actionsDropdown);
                headerOverlay.classList.toggle('active');
            });
            
            // Toggle search box on mobile
            searchToggle.addEventListener('click', function() {
                searchBox.classList.add('show');
                searchInput.focus();
                headerOverlay.classList.add('active');
            });
            
            // Close search box
            searchClose.addEventListener('click', function() {
                searchBox.classList.remove('show');
                headerOverlay.classList.remove('active');
            });
            
            // Mark all notifications as read
            if (markAllRead) {
                markAllRead.addEventListener('click', function() {
                    const unreadItems = document.querySelectorAll('.notification-item.unread');
                    unreadItems.forEach(item => {
                        item.classList.remove('unread');
                    });
                    const badge = document.querySelector('.notification-badge');
                    if (badge) badge.remove();
                });
            }
            
            // Close dropdowns when clicking overlay
            headerOverlay.addEventListener('click', function() {
                closeAllDropdowns();
                headerOverlay.classList.remove('active');
                searchBox.classList.remove('show');
            });
            
            // Mobile menu button
            mobileMenuBtn.addEventListener('click', function() {
                const mobileMenu = document.createElement('div');
                mobileMenu.className = 'mobile-menu';
                mobileMenu.innerHTML = `
                    <div class="mobile-menu-content">
                        <a href="${BASE_URL}users/etudiants.php" class="mobile-menu-item">
                            <i class="fas fa-users"></i>
                            <span>Étudiants</span>
                        </a>
                        <a href="${BASE_URL}epreuve/index.php" class="mobile-menu-item">
                            <i class="fas fa-file-alt"></i>
                            <span>Épreuves</span>
                        </a>
                        <a href="${BASE_URL}evenement/evenements.php" class="mobile-menu-item">
                            <i class="fas fa-calendar"></i>
                            <span>Événements</span>
                        </a>
                        <a href="${BASE_URL}settings.php" class="mobile-menu-item">
                            <i class="fas fa-cog"></i>
                            <span>Paramètres</span>
                        </a>
                    </div>
                `;
                
                document.body.appendChild(mobileMenu);
                headerOverlay.classList.add('active');
                
                setTimeout(() => {
                    mobileMenu.classList.add('show');
                }, 10);
                
                headerOverlay.onclick = function() {
                    mobileMenu.classList.remove('show');
                    setTimeout(() => {
                        mobileMenu.remove();
                        headerOverlay.classList.remove('active');
                    }, 300);
                };
            });
            
            // Search functionality
            searchInput.addEventListener('input', debounce(function() {
                const query = this.value.trim();
                if (query.length > 2) {
                    performSearch(query);
                } else {
                    clearSearchResults();
                }
            }, 300));
            
            // Helper functions
            function closeOtherDropdowns(currentDropdown) {
                const dropdowns = [profileDropdown, notificationsDropdown, actionsDropdown];
                dropdowns.forEach(dropdown => {
                    if (dropdown !== currentDropdown && dropdown.classList.contains('show')) {
                        dropdown.classList.remove('show');
                    }
                });
            }
            
            function closeAllDropdowns() {
                [profileDropdown, notificationsDropdown, actionsDropdown].forEach(dropdown => {
                    dropdown.classList.remove('show');
                });
                profileBtn.classList.remove('active');
            }
            
            function debounce(func, wait) {
                let timeout;
                return function executedFunction(...args) {
                    const later = () => {
                        clearTimeout(timeout);
                        func(...args);
                    };
                    clearTimeout(timeout);
                    timeout = setTimeout(later, wait);
                };
            }
            
            function performSearch(query) {
                // Simulate search results
                const results = document.getElementById('searchResults');
                results.innerHTML = `
                    <div class="search-result-item">
                        <i class="fas fa-user"></i>
                        <div class="search-result-content">
                            <h4>Étudiant: John Doe</h4>
                            <p>Matricule: 2023001</p>
                        </div>
                    </div>
                    <div class="search-result-item">
                        <i class="fas fa-file-alt"></i>
                        <div class="search-result-content">
                            <h4>Épreuve: Mathématiques</h4>
                            <p>Date: 15/12/2023</p>
                        </div>
                    </div>
                `;
                results.style.display = 'block';
            }
            
            function clearSearchResults() {
                const results = document.getElementById('searchResults');
                results.innerHTML = '';
                results.style.display = 'none';
            }
            
            // Close dropdowns when clicking outside
            document.addEventListener('click', function(event) {
                if (!profileBtn.contains(event.target) && !profileDropdown.contains(event.target)) {
                    profileDropdown.classList.remove('show');
                    profileBtn.classList.remove('active');
                }
                
                if (!notificationBtn.contains(event.target) && !notificationsDropdown.contains(event.target)) {
                    notificationsDropdown.classList.remove('show');
                }
                
                if (!quickActionBtn.contains(event.target) && !actionsDropdown.contains(event.target)) {
                    actionsDropdown.classList.remove('show');
                }
                
                if (!searchToggle.contains(event.target) && !searchBox.contains(event.target)) {
                    if (window.innerWidth < 992) {
                        searchBox.classList.remove('show');
                    }
                }
            });
            
            // Update page title based on current page
            function updatePageTitle() {
                const path = window.location.pathname;
                const pageTitles = {
                    'dashboard.php': 'Tableau de bord',
                    'etudiants.php': 'Gestion des étudiants',
                    'admins.php': 'Administrateurs',
                    'epreuve/index.php': 'Épreuves',
                    'evenement/evenements.php': 'Événements',
                    'profile.php': 'Mon profil',
                    'settings.php': 'Paramètres'
                };
                
                for (const [page, title] of Object.entries(pageTitles)) {
                    if (path.includes(page)) {
                        document.getElementById('pageTitle').textContent = title;
                        break;
                    }
                }
            }
            
            updatePageTitle();
            
            // Handle window resize
            let resizeTimer;
            window.addEventListener('resize', function() {
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(function() {
                    if (window.innerWidth >= 992) {
                        headerOverlay.classList.remove('active');
                        searchBox.classList.remove('show');
                        closeAllDropdowns();
                    }
                }, 250);
            });
        });
    </script>