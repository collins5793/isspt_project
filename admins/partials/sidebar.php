<?php
// Vérification de session et sécurité
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

// Configuration


// Protection XSS
function clean($data) {
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

// Récupération données utilisateur
$userData = [];
$role = $_SESSION['admin_role'] ?? 'admin';

if ($role === 'bureau' && !empty($_SESSION['admin_id_etudiant'])) {
    $stmt = $pdo->prepare("SELECT nom, prenom, photo, email FROM etudiants WHERE id_etudiant = ?");
    $stmt->execute([$_SESSION['admin_id_etudiant']]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
    $userData['role'] = 'Bureau';
} else {
    $userData = [
        'prenom' => $_SESSION['admin_prenom'] ?? 'Admin',
        'nom' => $_SESSION['admin_nom'] ?? '',
        'photo' => $_SESSION['admin_photo'] ?? 'avatar.png',
        'email' => $_SESSION['admin_email'] ?? '',
        'role' => ucfirst($role)
    ];
}

// Statistiques
$stats = [
    'etudiants' => $pdo->query("SELECT COUNT(*) FROM etudiants")->fetchColumn(),
    'epreuves' => $pdo->query("SELECT COUNT(*) FROM epreuves")->fetchColumn(),
    'events' => $pdo->query("SELECT COUNT(*) FROM evenements")->fetchColumn(),
];
?>

<!DOCTYPE html>
<html lang="fr" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <meta name="description" content="Panel d'administration ISSPT">
    <link rel="stylesheet" href="<?= ASSETS_URL ?>css/sidebar.css">
    <link rel="stylesheet" href="<?= ASSETS_URL ?>css/main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <!-- Sidebar Container -->
    <div class="sidebar-container" role="navigation" aria-label="Menu principal">
        <!-- Mobile Toggle -->
        <button class="mobile-toggle" id="mobileToggle" aria-label="Menu" aria-expanded="false">
            <span class="burger-line"></span>
            <span class="burger-line"></span>
            <span class="burger-line"></span>
        </button>

        <!-- Overlay Mobile -->
        <div class="sidebar-overlay" id="sidebarOverlay" role="button" aria-label="Fermer le menu"></div>

        <!-- Sidebar -->
        <aside class="admin-sidebar" id="adminSidebar" data-state="expanded">
            <!-- Header -->
            <div class="sidebar-header">
                <a href="<?= BASE_URL ?>dashboard.php" class="sidebar-logo" aria-label="ISSPT Administration">
                    <i class="fas fa-graduation-cap logo-icon" aria-hidden="true"></i>
                    <div class="logo-text">
                        <span class="logo-main">ISSPT</span>
                        <span class="logo-sub">Administration</span>
                    </div>
                </a>
                <button class="sidebar-close" id="sidebarClose" aria-label="Fermer le menu">
                    <i class="fas fa-times" aria-hidden="true"></i>
                </button>
            </div>

            <!-- Profile Section -->
            <div class="sidebar-profile">
                <div class="profile-avatar-wrapper">
                    <img src="<?= BASE_URL ?>assets/default/<?= clean($userData['photo']) ?>" 
                         class="profile-avatar" 
                         alt="Photo de <?= clean($userData['prenom'] . ' ' . $userData['nom']) ?>"
                         loading="lazy"
                         onerror="this.src='<?= ASSETS_URL ?>default/avatar.png'">
                    <div class="profile-status" data-status="online" aria-label="En ligne"></div>
                </div>
                <div class="profile-info">
                    <h3 class="profile-name" title="<?= clean($userData['prenom'] . ' ' . $userData['nom']) ?>">
                        <?= clean($userData['prenom'] . ' ' . $userData['nom']) ?>
                    </h3>
                    <p class="profile-role">
                        <span class="role-badge" aria-label="Rôle: <?= clean($userData['role']) ?>">
                            <?= clean($userData['role']) ?>
                        </span>
                    </p>
                    <small class="profile-email" title="<?= clean($userData['email']) ?>">
                        <?= clean($userData['email']) ?>
                    </small>
                </div>
                <div class="profile-actions">
                    <a href="<?= BASE_URL ?>profile.php" class="profile-action-btn" 
                       aria-label="Modifier le profil" title="Profil">
                        <i class="fas fa-user-edit" aria-hidden="true"></i>
                    </a>
                    <button class="profile-action-btn sidebar-toggle" id="sidebarToggle" 
                            aria-label="Réduire le menu" title="Réduire">
                        <i class="fas fa-chevron-left" aria-hidden="true"></i>
                    </button>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="sidebar-stats">
                <div class="stat-card" role="status" aria-label="<?= $stats['etudiants'] ?> étudiants">
                    <div class="stat-icon" aria-hidden="true">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-content">
                        <span class="stat-value"><?= number_format($stats['etudiants']) ?></span>
                        <span class="stat-label">Étudiants</span>
                    </div>
                </div>
                <div class="stat-card" role="status" aria-label="<?= $stats['epreuves'] ?> épreuves">
                    <div class="stat-icon" aria-hidden="true">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <div class="stat-content">
                        <span class="stat-value"><?= number_format($stats['epreuves']) ?></span>
                        <span class="stat-label">Épreuves</span>
                    </div>
                </div>
                <div class="stat-card" role="status" aria-label="<?= $stats['events'] ?> événements">
                    <div class="stat-icon" aria-hidden="true">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-content">
                        <span class="stat-value"><?= number_format($stats['events']) ?></span>
                        <span class="stat-label">Événements</span>
                    </div>
                </div>
            </div>

            <!-- Navigation -->
            <nav class="sidebar-nav" aria-label="Navigation principale">
                <?php
                $currentPage = basename($_SERVER['PHP_SELF']);
                $currentDir = dirname($_SERVER['PHP_SELF']);
                
                // Configuration des menus
                $menus = [
                    'dashboard' => [
                        'icon' => 'fas fa-tachometer-alt',
                        'label' => 'Tableau de bord',
                        'link' => 'dashboard.php',
                        'active' => $currentPage == 'dashboard.php'
                    ],
                    'actualites' => [
                        'icon' => 'fas fa-newspaper',
                        'label' => 'Actualités',
                        'link' => 'actualite/actualites.php',
                        'active' => str_contains($currentDir, 'actualite')
                    ],
                    'users' => [
                        'icon' => 'fas fa-users-cog',
                        'label' => 'Gestion Utilisateurs',
                        'submenu' => [
                            ['icon' => 'fas fa-user-graduate', 'label' => 'Étudiants', 'link' => 'users/etudiants.php'],
                            ['icon' => 'fas fa-user-shield', 'label' => 'Administrateurs', 'link' => 'users/admins.php'],
                        ],
                        'active' => str_contains($currentDir, 'users') || $currentPage == 'roles.php'
                    ],
                    'academique' => [
                        'icon' => 'fas fa-graduation-cap',
                        'label' => 'Académique',
                        'submenu' => [
                            ['icon' => 'fas fa-calendar-alt', 'label' => 'Années académiques', 'link' => 'annees.php'],
                        //     ['icon' => 'fas fa-book', 'label' => 'Unités d\'enseignement', 'link' => 'ues.php'],
                        //     ['icon' => 'fas fa-book-open', 'label' => 'Matières', 'link' => 'matieres.php'],
                        //     ['icon' => 'fas fa-chart-bar', 'label' => 'Résultats', 'link' => 'resultats.php'],
                        //     ['icon' => 'fas fa-history', 'label' => 'Historique des notes', 'link' => 'notes_logs.php']
                        ],
                        'active' => in_array($currentPage, ['annees.php', 'ues.php', 'matieres.php', 'resultats.php', 'notes_logs.php'])
                    ],
                    'epreuves' => [
                        'icon' => 'fas fa-file-signature',
                        'label' => 'Épreuves & Examens',
                        'submenu' => [
                            ['icon' => 'fas fa-list', 'label' => 'Toutes les épreuves', 'link' => 'epreuve/index.php'],
                            ['icon' => 'fas fa-folder', 'label' => 'Catégories', 'link' => 'epreuve/categories.php'],
                            ['icon' => 'fas fa-book', 'label' => 'Matières d\'épreuves', 'link' => 'epreuve/matieres.php'],
                            ['icon' => 'fas fa-university', 'label' => 'Filières', 'link' => 'epreuve/filieres.php']
                        ],
                        'active' => str_contains($currentDir, 'epreuve')
                    ],
                    'evenements' => [
                        'icon' => 'fas fa-calendar-week',
                        'label' => 'Événements & Activités',
                        'submenu' => [
                            ['icon' => 'fas fa-calendar', 'label' => 'Événements', 'link' => 'evenement/evenements.php'],
                            ['icon' => 'fas fa-ticket-alt', 'label' => 'Billeterie', 'link' => 'tickets.php'],
                            ['icon' => 'fas fa-football-ball', 'label' => 'Activités & Clubs', 'link' => 'evenement/activites.php'],
                        ],
                        'active' => str_contains($currentDir, 'evenement') || in_array($currentPage, ['tickets.php', 'galerie.php'])
                    ],
                    'football' => [
                        'icon' => 'fas fa-futbol',
                        'label' => 'Football Universitaire',
                        'submenu' => [
                            ['icon' => 'fas fa-calendar', 'label' => 'Saisons', 'link' => 'football/season.php'],
                            ['icon' => 'fas fa-user', 'label' => 'Joueurs', 'link' => 'football/players.php'],
                            ['icon' => 'fas fa-running', 'label' => 'Matchs', 'link' => 'football/matches.php']
                        ],
                        'active' => str_contains($currentDir, 'football')
                    ],
                    'modules' => [
                        'icon' => 'fas fa-puzzle-piece',
                        'label' => 'Modules & Services',
                        'submenu' => [
                            ['icon' => 'fas fa-comments', 'label' => 'Messagerie', 'link' => 'messagerie.php'],
                            ['icon' => 'fas fa-folder-open', 'label' => 'Documents', 'link' => 'documents.php'],
                            ['icon' => 'fas fa-bell', 'label' => 'Notifications', 'link' => 'notifications.php'],
                            ['icon' => 'fas fa-clipboard-list', 'label' => 'Logs système', 'link' => 'logs.php']
                        ],
                        'active' => in_array($currentPage, ['messagerie.php', 'documents.php', 'notifications.php', 'logs.php'])
                    ],
                    'parametres' => [
                        'icon' => 'fas fa-cogs',
                        'label' => 'Paramètres',
                        'submenu' => [
                            ['icon' => 'fas fa-user-cog', 'label' => 'Profil', 'link' => 'profile.php'],
                            ['icon' => 'fas fa-sliders-h', 'label' => 'Configuration', 'link' => 'parametres.php'],
                            ['icon' => 'fas fa-palette', 'label' => 'Thème & Apparence', 'link' => 'themes.php'],
                            ['icon' => 'fas fa-shield-alt', 'label' => 'Sécurité', 'link' => 'securite.php']
                        ],
                        'active' => in_array($currentPage, ['profile.php', 'parametres.php', 'themes.php', 'securite.php'])
                    ]
                ];
                
                // Génération du menu
                foreach ($menus as $key => $menu) {
                    if (isset($menu['submenu'])) {
                        $isActive = $menu['active'];
                        ?>
                        <div class="nav-section <?= $isActive ? 'active' : '' ?>" data-menu="<?= $key ?>">
                            <button class="nav-title" aria-expanded="<?= $isActive ? 'true' : 'false' ?>">
                                <div class="nav-icon">
                                    <i class="<?= $menu['icon'] ?>" aria-hidden="true"></i>
                                </div>
                                <span class="nav-text"><?= $menu['label'] ?></span>
                                <i class="nav-arrow fas fa-chevron-<?= $isActive ? 'up' : 'down' ?>" aria-hidden="true"></i>
                            </button>
                            <div class="submenu" style="max-height: <?= $isActive ? '500px' : '0' ?>;">
                                <?php foreach ($menu['submenu'] as $subitem) {
                                    $isSubActive = basename($subitem['link']) == $currentPage;
                                ?>
                                <a href="<?= BASE_URL . $subitem['link'] ?>" 
                                   class="submenu-link <?= $isSubActive ? 'active' : '' ?>"
                                   aria-current="<?= $isSubActive ? 'page' : 'false' ?>">
                                    <i class="<?= $subitem['icon'] ?>" aria-hidden="true"></i>
                                    <span><?= $subitem['label'] ?></span>
                                </a>
                                <?php } ?>
                            </div>
                        </div>
                        <?php
                    } else {
                        ?>
                        <div class="nav-section">
                            <a href="<?= BASE_URL . $menu['link'] ?>" 
                               class="nav-link <?= $menu['active'] ? 'active' : '' ?>"
                               aria-current="<?= $menu['active'] ? 'page' : 'false' ?>">
                                <div class="nav-icon">
                                    <i class="<?= $menu['icon'] ?>" aria-hidden="true"></i>
                                </div>
                                <span class="nav-text"><?= $menu['label'] ?></span>
                            </a>
                        </div>
                        <?php
                    }
                }
                ?>
            </nav>

            <!-- Footer -->
            <div class="sidebar-footer">
                <div class="sidebar-time" aria-live="polite">
                    <i class="fas fa-clock" aria-hidden="true"></i>
                    <span id="sidebarClock"><?= date('H:i') ?></span>
                </div>
                <div class="sidebar-actions">
                    <a href="<?= BASE_URL ?>logout.php" class="logout-btn" aria-label="Se déconnecter">
                        <i class="fas fa-sign-out-alt" aria-hidden="true"></i>
                        <span class="logout-text">Déconnexion</span>
                    </a>
                </div>
            </div>
        </aside>
    </div>

    <!-- Scripts -->
    <script src="<?= ASSETS_URL ?>js/utils.js"></script>
    <script src="<?= ASSETS_URL ?>js/sidebar.js"></script>
</body>
</html>