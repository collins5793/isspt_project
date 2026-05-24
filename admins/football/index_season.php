<?php
session_start();
require_once '../../includes/db.php'; // connexion PDO

// Vérifier si l'utilisateur est admin
$isAdmin = isset($_SESSION['admin_id']);

// --- Supprimer une saison si action demandée ---
if ($isAdmin && isset($_GET['delete'])) {
    $id_to_delete = (int) $_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM football_seasons WHERE id_season = ?");
    $stmt->execute([$id_to_delete]);
    header("Location: football_seasons.php?message=deleted");
    exit;
}

// --- Récupérer toutes les saisons ---
$stmt = $pdo->query("
    SELECT fs.*, ay.label AS academic_year
    FROM football_seasons fs
    JOIN academic_years ay ON fs.academic_year_id = ay.id
    ORDER BY fs.created_at DESC
");
$seasons = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Saisons de Football</title>
    <link rel="stylesheet" href="../assets/css/index.css">
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

        /* --- Global Reset & Page Base --- */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: var(--font-primary);
            background-color: var(--primary-900);
            background-image: radial-gradient(at top right, var(--primary-800), var(--primary-900));
            color: var(--gray-100);
            min-height: 100vh;
            padding: var(--space-5);
            display: flex;
            justify-content: center;
            align-items: flex-start;
        }

        /* --- Main Layout Container --- */
        .dashboard-container {
            width: 100%;
            max-width: 1200px;
            background: rgba(18, 12, 58, 0.6);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: var(--radius-xl);
            padding: var(--space-6);
            box-shadow: var(--shadow-xl);
            margin-top: var(--space-4);
        }

        /* --- Header Styling --- */
        .page-header {
            display: flex;
            flex-direction: column;
            gap: var(--space-4);
            margin-bottom: var(--space-6);
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            padding-bottom: var(--space-5);
        }

        @media (min-width: 768px) {
            .page-header {
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
            }
        }

        .page-header h1 {
            font-size: var(--font-size-xl);
            font-weight: 600;
            letter-spacing: -0.5px;
            display: flex;
            align-items: center;
            gap: var(--space-3);
            color: var(--white);
        }

        .page-header h1 i {
            color: var(--accent-blue);
        }

        /* --- Buttons System --- */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: var(--space-2);
            font-family: var(--font-primary);
            font-size: var(--font-size-sm);
            font-weight: 600;
            padding: var(--space-3) var(--space-5);
            border-radius: var(--radius-md);
            border: none;
            cursor: pointer;
            text-decoration: none;
            transition: all var(--transition-fast);
        }

        .btn-add {
            background-color: var(--accent-blue);
            color: var(--white);
            box-shadow: 0 4px 12px rgba(46, 134, 222, 0.3);
        }

        .btn-add:hover {
            background-color: #48dbfb;
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(46, 134, 222, 0.4);
        }

        /* --- Feedback Banner --- */
        .alert-success {
            background: rgba(16, 172, 132, 0.15);
            border-left: 4px solid var(--accent-green);
            color: #55efc4;
            padding: var(--space-4);
            border-radius: var(--radius-md);
            margin-bottom: var(--space-5);
            font-size: var(--font-size-sm);
            display: flex;
            align-items: center;
            gap: var(--space-3);
            animation: fadeIn 400ms ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* --- Modern Responsive Table Component --- */
        .table-responsive {
            width: 100%;
            overflow-x: auto;
            border-radius: var(--radius-lg);
            background: rgba(10, 1, 39, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: var(--font-size-sm);
        }

        th {
            background-color: rgba(26, 24, 73, 0.8);
            color: var(--gray-300);
            font-weight: 600;
            text-transform: uppercase;
            font-size: var(--font-size-xs);
            letter-spacing: 0.5px;
            padding: var(--space-4);
            border-bottom: 2px solid rgba(255, 255, 255, 0.05);
        }

        td {
            padding: var(--space-4);
            color: var(--gray-100);
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            transition: background-color var(--transition-fast);
        }

        tr:last-child td {
            border-bottom: none;
        }

        tr:hover td {
            background-color: rgba(255, 255, 255, 0.02);
        }

        /* --- Status Badges --- */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: var(--space-2);
            padding: var(--space-1) var(--space-3);
            border-radius: var(--radius-full);
            font-size: var(--font-size-xs);
            font-weight: 600;
        }

        .badge-active {
            background: rgba(16, 172, 132, 0.15);
            color: #55efc4;
            border: 1px solid rgba(16, 172, 132, 0.3);
        }

        .badge-inactive {
            background: rgba(255, 71, 87, 0.15);
            color: #ff6b81;
            border: 1px solid rgba(255, 71, 87, 0.3);
        }

        /* --- Action Controls --- */
        .actions-cell {
            display: flex;
            align-items: center;
            gap: var(--space-2);
        }

        .action-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: var(--radius-md);
            text-decoration: none;
            transition: all var(--transition-fast);
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        .action-link-view { color: var(--accent-blue); }
        .action-link-edit { color: var(--gray-300); }
        .action-link-delete { color: var(--accent-red); }

        .action-link:hover {
            transform: translateY(-2px);
            background: rgba(255, 255, 255, 0.1);
        }

        .action-link-view:hover { border-color: var(--accent-blue); background: rgba(46, 134, 222, 0.1); }
        .action-link-edit:hover { border-color: var(--white); background: rgba(255, 255, 255, 0.1); }
        .action-link-delete:hover { border-color: var(--accent-red); background: rgba(255, 71, 87, 0.1); }

        .empty-row {
            text-align: center;
            color: var(--gray-400);
            padding: var(--space-6) !important;
            font-style: italic;
        }

        /* --- Mobile Responsive Rules Breakpoint --- */
        @media (max-width: 768px) {
            body {
                padding: var(--space-3);
            }
            
            .dashboard-container {
                padding: var(--space-4);
            }

            /* On transforme le tableau d'affichage classique en liste de cartes fluides */
            table, thead, tbody, th, td, tr { 
                display: block; 
            }
            
            thead tr { 
                position: absolute;
                top: -9999px;
                left: -9999px;
            }
            
            tr {
                background: rgba(26, 24, 73, 0.4);
                border: 1px solid rgba(255, 255, 255, 0.05);
                border-radius: var(--radius-lg);
                margin-bottom: var(--space-4);
                padding: var(--space-2);
            }
            
            td { 
                border: none;
                border-bottom: 1px solid rgba(255, 255, 255, 0.03); 
                position: relative;
                padding-left: 50%; 
                text-align: right;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }

            td:last-child {
                border-bottom: none;
            }
            
            td:before { 
                content: attr(data-label);
                float: left;
                font-weight: 600;
                color: var(--gray-400);
                font-size: var(--font-size-xs);
                text-transform: uppercase;
            }

            .actions-cell {
                justify-content: flex-end;
                width: 100%;
            }
        }
    </style>
</head>
<body>

<div class="dashboard-container">
    
    <!-- Top Action Area -->
    <header class="page-header">
        <h1><i class="fa-solid fa-calendar-days"></i> Gestion des saisons</h1>
        <?php if ($isAdmin): ?>
            <a href="add_season.php" class="btn btn-add">
                <i class="fa-solid fa-plus"></i> Nouvelle saison
            </a>
        <?php endif; ?>
    </header>

    <!-- Feedback Message System -->
    <?php if (isset($_GET['message']) && $_GET['message'] === 'deleted'): ?>
        <div class="alert-success">
            <i class="fa-solid fa-circle-check"></i>
            <span>Saison supprimée avec succès du système.</span>
        </div>
    <?php endif; ?>

    <!-- Master Data Table -->
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Label</th>
                    <th>Année académique</th>
                    <th>Statut</th>
                    <th>Créée le</th>
                    <th>Détails</th>
                    <?php if ($isAdmin): ?>
                        <th>Actions</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($seasons)): ?>
                    <?php foreach ($seasons as $season): ?>
                        <tr>
                            <td data-label="ID">#<?= htmlspecialchars($season['id_season']) ?></td>
                            <td data-label="Label" style="font-weight: 600; color: var(--white);">
                                <?= htmlspecialchars($season['label']) ?>
                            </td>
                            <td data-label="Année académique"><?= htmlspecialchars($season['academic_year']) ?></td>
                            <td data-label="Statut">
                                <?php if ($season['is_active']): ?>
                                    <span class="badge badge-active"><i class="fa-solid fa-circle-dot"></i> Activer</span>
                                <?php else: ?>
                                    <span class="badge badge-inactive"><i class="fa-regular fa-circle"></i> Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Créée le"><?= htmlspecialchars(date('d/m/Y', strtotime($season['created_at']))) ?></td>
                            <td data-label="Détails">
                                <a href="season_details.php?id=<?= $season['id_season'] ?>" class="action-link action-link-view" title="Voir les détails complets">
                                    <i class="fa-solid fa-eye"></i>
                                </a>
                            </td>
                            <?php if ($isAdmin): ?>
                                <td data-label="Actions">
                                    <div class="actions-cell">
                                        <a href="edit_season.php?id=<?= $season['id_season'] ?>" class="action-link action-link-edit" title="Modifier la saison">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </a>
                                        <a href="?delete=<?= $season['id_season'] ?>" class="action-link action-link-delete" 
                                           onclick="return confirm('Êtes-vous sûr de vouloir supprimer définitivement cette saison ?');" title="Supprimer">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </a>
                                    </div>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="<?= $isAdmin ? 7 : 6 ?>" class="empty-row">
                            <i class="fa-solid fa-folder-open" style="display:block; font-size: 24px; margin-bottom: 10px; color: var(--primary-600);"></i>
                            Aucune saison de football n'a été trouvée pour le moment.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>