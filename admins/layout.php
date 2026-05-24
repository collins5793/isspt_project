<?php
// Vérification de session
if (!isset($_SESSION)) session_start();

// Vérifier si l'admin est connecté
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit;
}

// Configuration
define("BASE_URL", "/isspt_projet/admins/");
define("ASSETS_URL", BASE_URL . "assets/");

// Titre de la page
$page_title = $page_title ?? 'Dashboard Admin - ISSPT';

// Rôles autorisés
$allowed_roles = $allowed_roles ?? [];

// Vérification de rôle si spécifié
if (!empty($allowed_roles) && !in_array($_SESSION['admin_role'], $allowed_roles)) {
    header("Location: " . BASE_URL . "unauthorized.php");
    exit;
}

// Détection de la page active
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title><?= htmlspecialchars($page_title) ?></title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            /* Palette de couleurs principale */
            --primary: #080020;
            --primary-light: #1a183a;
            --primary-lighter: #2c2854;
            --accent: #BA281E;
            --accent-light: #d33f35;
            --accent-lighter: #ec564d;
            --accent-gradient: linear-gradient(135deg, #BA281E 0%, #d33f35 100%);
            
            /* Couleurs neutres - fond blanc */
            --white: #ffffff;
            --off-white: #fafafa;
            --light-gray: #f5f7fa;
            --gray-100: #eef2f7;
            --gray-200: #e5e9f0;
            --gray-300: #d3d9e2;
            --gray-400: #b8c1d1;
            --gray-500: #8a94a6;
            --gray-600: #6b7280;
            --gray-700: #4b5563;
            --gray-800: #374151;
            --gray-900: #1f2937;
            
            /* Couleurs d'accent secondaires */
            --gold: linear-gradient(135deg, #FFD700 0%, #FFE44D 100%);
            --silver: linear-gradient(135deg, #C0C0C0 0%, #E0E0E0 100%);
            --bronze: linear-gradient(135deg, #CD7F32 0%, #E3964A 100%);
            --grass-green: #2E8B57;
            --field-green: #228B22;
            --success: #10b981;
            --warning: #f59e0b;
            --info: #3b82f6;
            --danger: #ef4444;
            
            /* Effets de verre (glassmorphism) */
            --glass-bg: rgba(255, 255, 255, 0.95);
            --glass-border: rgba(255, 255, 255, 0.2);
            --glass-shadow: 0 8px 32px rgba(31, 38, 135, 0.07);
            
            /* Dimensions */
            --sidebar-width: 280px;
            --sidebar-width-collapsed: 70px;
            --header-height: 80px;
            --footer-height: 60px;
            
            /* Espacements */
            --space-xs: 0.25rem;   /* 4px */
            --space-sm: 0.5rem;    /* 8px */
            --space-md: 1rem;      /* 16px */
            --space-lg: 1.5rem;    /* 24px */
            --space-xl: 2rem;      /* 32px */
            --space-2xl: 3rem;     /* 48px */
            --space-3xl: 4rem;     /* 64px */
            
            /* Bordures */
            --radius-sm: 6px;
            --radius-md: 10px;
            --radius-lg: 16px;
            --radius-xl: 24px;
            --radius-2xl: 32px;
            --radius-full: 9999px;
            
            /* Ombres */
            --shadow-xs: 0 1px 2px rgba(0, 0, 0, 0.05);
            --shadow-sm: 0 4px 6px rgba(0, 0, 0, 0.07);
            --shadow-md: 0 10px 25px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 20px 50px rgba(0, 0, 0, 0.15);
            --shadow-xl: 0 25px 60px rgba(0, 0, 0, 0.2);
            --shadow-inner: inset 0 2px 4px rgba(0, 0, 0, 0.06);
            
            /* Transitions */
            --transition-fast: 150ms cubic-bezier(0.4, 0, 0.2, 1);
            --transition-base: 300ms cubic-bezier(0.4, 0, 0.2, 1);
            --transition-slow: 500ms cubic-bezier(0.4, 0, 0.2, 1);
            
            /* Typographie */
            --font-sans: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            --font-mono: 'SF Mono', 'Roboto Mono', Consolas, monospace;
            --text-xs: 0.75rem;    /* 12px */
            --text-sm: 0.875rem;   /* 14px */
            --text-base: 1rem;     /* 16px */
            --text-lg: 1.125rem;   /* 18px */
            --text-xl: 1.25rem;    /* 20px */
            --text-2xl: 1.5rem;    /* 24px */
            --text-3xl: 1.875rem;  /* 30px */
            --text-4xl: 2.25rem;   /* 36px */
            --text-5xl: 3rem;      /* 48px */
            
            /* Z-index */
            --z-sidebar: 1000;
            --z-overlay: 999;
            --z-mobile-toggle: 1001;
            --z-dropdown: 1002;
            --z-modal: 1003;
            --z-toast: 1004;
        }

        /* ============================================
           RESET & BASE STYLES
        ============================================ */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: var(--font-sans);
            background: var(--light-gray);
            color: var(--gray-800);
            line-height: 1.6;
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* ============================================
           ADMIN CONTAINER & LAYOUT
        ============================================ */
        .admin-container {
            display: flex;
            min-height: 100vh;
            background: linear-gradient(135deg, var(--off-white) 0%, var(--light-gray) 100%);
        }

        /* ============================================
           SIDEBAR TOGGLE (MOBILE)
        ============================================ */
        .sidebar-toggle {
            position: fixed;
            top: var(--space-lg);
            left: var(--space-lg);
            width: 48px;
            height: 48px;
            background: var(--white);
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-md);
            color: var(--gray-700);
            font-size: 1.25rem;
            cursor: pointer;
            z-index: var(--z-mobile-toggle);
            display: none;
            align-items: center;
            justify-content: center;
            transition: var(--transition-base);
            box-shadow: var(--shadow-md);
        }

        .sidebar-toggle:hover {
            background: var(--accent);
            color: var(--white);
            border-color: var(--accent);
            transform: translateY(-2px);
        }

        /* ============================================
           MAIN CONTENT AREA
        ============================================ */
        .main-content {
            flex: 1;
            min-height: 100vh;
            background: #080020;
            position: relative;
            transition: var(--transition-base);
            margin-left: 0;
        }

        /* Desktop Margins - Sidebar Adjustment */
        @media (min-width: 1200px) {
            .admin-sidebar:not(.collapsed) ~ .main-content {
                margin-left: var(--sidebar-width);
                width: calc(100% - var(--sidebar-width));
            }

            .admin-sidebar.collapsed ~ .main-content {
                margin-left: var(--sidebar-width-collapsed);
                width: calc(100% - var(--sidebar-width-collapsed));
            }
        }

        /* Mobile Full Width */
        @media (max-width: 1199px) {
            .main-content {
                margin-left: 0 !important;
                width: 100%;
            }
        }

        /* ============================================
           CONTENT WRAPPER
        ============================================ */
        .content-wrapper {
            padding: var(--space-xl);
            max-width: 1600px;
            margin: 0 auto;
            width: 100%;
            min-height: calc(100vh - var(--header-height) - var(--footer-height));
        }

        @media (max-width: 768px) {
            .content-wrapper {
                padding: var(--space-lg);
            }
        }

        @media (max-width: 576px) {
            .content-wrapper {
                padding: var(--space-md);
            }
        }

        /* ============================================
           BREADCRUMBS
        ============================================ */
        .breadcrumb-nav {
            margin-bottom: var(--space-xl);
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: var(--space-md) var(--space-lg);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
        }

        .breadcrumb {
            background: transparent;
            border-radius: 0;
            padding: 0;
            margin: 0;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: var(--space-sm);
        }

        .breadcrumb-item {
            display: flex;
            align-items: center;
            color: var(--gray-600);
            font-size: var(--text-sm);
            font-weight: 500;
        }

        .breadcrumb-item + .breadcrumb-item::before {
            content: "/";
            color: var(--gray-400);
            margin-right: var(--space-sm);
            font-weight: normal;
        }

        .breadcrumb-item a {
            color: var(--accent);
            text-decoration: none;
            transition: var(--transition-base);
            display: flex;
            align-items: center;
            gap: var(--space-xs);
        }

        .breadcrumb-item a:hover {
            color: var(--accent-light);
            text-decoration: none;
        }

        .breadcrumb-item.active {
            color: var(--gray-800);
            font-weight: 600;
        }

        .breadcrumb-item i {
            font-size: var(--text-sm);
        }

        /* ============================================
           FLASH MESSAGES / ALERTS
        ============================================ */
        .flash-messages {
            margin-bottom: var(--space-xl);
            animation: slideDown 0.3s ease-out;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .flash-messages .alert {
            border-radius: var(--radius-lg);
            border: none;
            box-shadow: var(--shadow-md);
            padding: var(--space-lg);
            display: flex;
            align-items: flex-start;
            gap: var(--space-md);
            margin-bottom: var(--space-md);
            position: relative;
            overflow: hidden;
        }

        .flash-messages .alert::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            border-radius: var(--radius-lg) 0 0 var(--radius-lg);
        }

        .flash-messages .alert-dismissible {
            padding-right: var(--space-2xl);
        }

        .alert-success {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(16, 185, 129, 0.05) 100%);
            color: var(--success);
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        .alert-success::before {
            background: var(--success);
        }

        .alert-danger {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.1) 0%, rgba(239, 68, 68, 0.05) 100%);
            color: var(--danger);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        .alert-danger::before {
            background: var(--danger);
        }

        .alert-warning {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.1) 0%, rgba(245, 158, 11, 0.05) 100%);
            color: var(--warning);
            border: 1px solid rgba(245, 158, 11, 0.2);
        }

        .alert-warning::before {
            background: var(--warning);
        }

        .alert-info {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.1) 0%, rgba(59, 130, 246, 0.05) 100%);
            color: var(--info);
            border: 1px solid rgba(59, 130, 246, 0.2);
        }

        .alert-info::before {
            background: var(--info);
        }

        .alert-icon {
            font-size: var(--text-xl);
            line-height: 1;
            margin-top: 2px;
        }

        .alert-content {
            flex: 1;
        }

        .alert-content h5 {
            margin-bottom: var(--space-xs);
            font-weight: 600;
        }

        .alert-content p {
            margin: 0;
            font-size: var(--text-sm);
        }

        .btn-close {
            position: absolute;
            top: var(--space-md);
            right: var(--space-md);
            opacity: 0.6;
            transition: var(--transition-base);
        }

        .btn-close:hover {
            opacity: 1;
            transform: rotate(90deg);
        }

        /* ============================================
           CONTENT AREA
        ============================================ */
        .content-area {
            animation: fadeInUp 0.4s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* ============================================
           RESPONSIVE ADJUSTMENTS
        ============================================ */
        @media (max-width: 1199px) {
            .sidebar-toggle {
                display: flex;
            }
            
            .admin-sidebar {
                transform: translateX(-100%);
                transition: transform var(--transition-base);
            }
            
            .admin-sidebar.mobile-open {
                transform: translateX(0);
            }
            
            .main-content.mobile-pushed {
                margin-left: var(--sidebar-width);
            }
        }

        @media (max-width: 768px) {
            :root {
                --space-xl: 1.5rem;
                --space-lg: 1.25rem;
                --space-md: 1rem;
            }
            
            .flash-messages .alert {
                flex-direction: column;
                gap: var(--space-sm);
                padding: var(--space-md);
            }
            
            .alert-icon {
                align-self: flex-start;
            }
        }

        @media (max-width: 576px) {
            .content-wrapper {
                padding: var(--space-md);
            }
            
            .breadcrumb-nav {
                padding: var(--space-sm) var(--space-md);
            }
            
            .breadcrumb {
                font-size: var(--text-xs);
            }
        }

        /* ============================================
           PRINT STYLES
        ============================================ */
        @media print {
            .sidebar-toggle,
            .admin-sidebar,
            .flash-messages,
            .breadcrumb-nav {
                display: none !important;
            }
            
            .main-content {
                margin-left: 0 !important;
                background: white !important;
            }
            
            .content-wrapper {
                padding: 0;
                max-width: none;
            }
        }

        /* ============================================
           ACCESSIBILITY
        ============================================ */
        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }

        .visually-hidden {
            position: absolute !important;
            width: 1px !important;
            height: 1px !important;
            padding: 0 !important;
            margin: -1px !important;
            overflow: hidden !important;
            clip: rect(0, 0, 0, 0) !important;
            white-space: nowrap !important;
            border: 0 !important;
        }

        /* ============================================
           CUSTOM UTILITIES
        ============================================ */
        .glass-card {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border: 1px solid var(--glass-border);
            box-shadow: var(--glass-shadow);
        }

        .hover-lift {
            transition: transform var(--transition-base), box-shadow var(--transition-base);
        }

        .hover-lift:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }

        .text-gradient {
            background: var(--accent-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .bg-gradient-primary {
            background: var(--accent-gradient);
        }

        /* ============================================
           ANIMATIONS
        ============================================ */
        @keyframes pulse {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: 0.7;
            }
        }

        @keyframes shimmer {
            0% {
                background-position: -1000px 0;
            }
            100% {
                background-position: 1000px 0;
            }
        }

        .loading-shimmer {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 1000px 100%;
            animation: shimmer 2s infinite linear;
        }

        /* ============================================
           SCROLLBAR STYLING
        ============================================ */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: var(--gray-100);
            border-radius: var(--radius-full);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--gray-400);
            border-radius: var(--radius-full);
            transition: var(--transition-base);
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--gray-500);
        }

        /* ============================================
           SELECTION
        ============================================ */
        ::selection {
            background: rgba(186, 40, 30, 0.2);
            color: var(--accent);
        }

        ::-moz-selection {
            background: rgba(186, 40, 30, 0.2);
            color: var(--accent);
        }

        /* ============================================
           FOCUS VISIBLE
        ============================================ */
        :focus-visible {
            outline: 2px solid var(--accent);
            outline-offset: 2px;
            border-radius: var(--radius-sm);
        }
    </style>
</head>

<body>
    <div class="admin-container">
        <?php include __DIR__ . "/partials/sidebar.php"; ?>
    
    <!-- Main Content -->
    <main class="main-content" id="mainContent">
        <!-- Header -->
        <?php include __DIR__ . "/partials/header.php"; ?>
        
        <!-- Content Wrapper -->
        <div class="content-wrapper">
            <!-- Dynamic Content -->
            <div class="content-area" id="contentArea">
                <?php 
                if (isset($content)) {
                    echo $content;
                } else {
                    echo '<div class="alert alert-warning">Aucun contenu à afficher</div>';
                }
                ?>
            </div>
        </div>
        
        <!-- Footer -->
        <?php include __DIR__ . "/partials/footer.php"; ?>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Mobile sidebar toggle
        document.addEventListener('DOMContentLoaded', function() {
            const sidebarToggle = document.querySelector('.sidebar-toggle');
            const sidebar = document.querySelector('.admin-sidebar');
            const mainContent = document.querySelector('.main-content');
            const overlay = document.createElement('div');
            
            overlay.className = 'sidebar-overlay';
            document.body.appendChild(overlay);
            
            function openSidebar() {
                sidebar.classList.add('mobile-open');
                mainContent.classList.add('mobile-pushed');
                overlay.classList.add('active');
                document.body.style.overflow = 'hidden';
            }
            
            function closeSidebar() {
                sidebar.classList.remove('mobile-open');
                mainContent.classList.remove('mobile-pushed');
                overlay.classList.remove('active');
                document.body.style.overflow = '';
            }
            
            sidebarToggle.addEventListener('click', function() {
                if (sidebar.classList.contains('mobile-open')) {
                    closeSidebar();
                } else {
                    openSidebar();
                }
            });
            
            overlay.addEventListener('click', closeSidebar);
            
            // Close sidebar when clicking on a link (mobile)
            document.querySelectorAll('.nav-link, .submenu-link').forEach(link => {
                link.addEventListener('click', () => {
                    if (window.innerWidth < 1200) {
                        closeSidebar();
                    }
                });
            });
            
            // Close sidebar with Escape key
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && sidebar.classList.contains('mobile-open')) {
                    closeSidebar();
                }
            });
            
            // Responsive adjustments
            function handleResize() {
                if (window.innerWidth >= 1200) {
                    closeSidebar();
                }
            }
            
            window.addEventListener('resize', handleResize);
            
            // Initialize
            handleResize();
        });
    </script>
</body>
</html>