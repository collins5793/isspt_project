<?php
session_start();
require_once '../includes/db.php';

// Sécurité : Vérification de la session admin (Recommandé)
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit;
}

// Traitement de la suppression
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $pdo->prepare("DELETE FROM academic_years WHERE id = ?");
    $stmt->execute([$id]);
    
    $_SESSION['success'] = "L'année universitaire a été supprimée avec succès.";
    header("Location: annees.php");
    exit;
}

// Récupérer les années avec un ordre chronologique inversé
$annees = $pdo->query("SELECT * FROM academic_years ORDER BY start_date DESC")->fetchAll(PDO::FETCH_ASSOC);

// Début de la mise en mémoire tampon pour injection dans layout.php
ob_start();
?>

<!-- Bloc de styles dédiés exploitant vos variables :root -->
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

    --bg-page: var(--primary-900);
    --bg-card: var(--primary-800);
    --bg-input: var(--primary-700);
    --bg-input-focus: var(--primary-600);
    --border-glass: rgba(255, 255, 255, 0.06);
    --border-focus: var(--accent-blue);
    }


    body {
        background-color: var(--bg-page);
        color: var(--gray-100);
        font-family: var(--font-primary);
    }

    /* En-tête de la section */
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: var(--space-6);
        padding-bottom: var(--space-4);
        border-bottom: 1px solid var(--border-glass);
    }

    .page-header h2 {
        font-size: var(--font-size-xl);
        font-weight: 700;
        color: var(--white);
        margin: 0;
        display: flex;
        align-items: center;
        gap: var(--space-3);
    }

    /* Conteneur principal de la table */
    .table-container {
        background-color: var(--bg-card);
        border: 1px solid var(--border-glass);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-xl);
        overflow: hidden;
        margin-bottom: var(--space-6);
    }

    /* Table custom stylisée */
    .custom-table {
        width: 100%;
        border-collapse: collapse;
        text-align: left;
        font-size: var(--font-size-sm);
    }

    .custom-table th {
        background-color: rgba(255, 255, 255, 0.02);
        color: var(--gray-400);
        font-weight: 600;
        text-transform: uppercase;
        font-size: var(--font-size-xs);
        letter-spacing: 0.5px;
        padding: var(--space-4) var(--space-5);
        border-bottom: 1px solid var(--border-glass);
    }

    .custom-table td {
        padding: var(--space-4) var(--space-5);
        border-bottom: 1px solid var(--border-glass);
        color: var(--gray-200);
        vertical-align: middle;
        transition: background-color var(--transition-fast);
    }

    .custom-table tbody tr:last-child td {
        border-bottom: none;
    }

    .custom-table tbody tr:hover td {
        background-color: var(--bg-row-hover);
        color: var(--white);
    }

    /* Badges pour l'état actuel */
    .badge-status {
        display: inline-flex;
        align-items: center;
        gap: var(--space-1);
        padding: var(--space-1) var(--space-3);
        border-radius: var(--radius-full);
        font-size: var(--font-size-xs);
        font-weight: 600;
    }

    .badge-current {
        background-color: rgba(16, 172, 132, 0.1);
        color: var(--accent-green);
        border: 1px solid rgba(16, 172, 132, 0.2);
    }

    .badge-past {
        background-color: rgba(255, 255, 255, 0.04);
        color: var(--gray-400);
        border: 1px solid var(--border-glass);
    }

    /* Boutons personnalisés */
    .btn-action {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: var(--space-2);
        font-family: var(--font-primary);
        font-size: var(--font-size-sm);
        font-weight: 600;
        border-radius: var(--radius-md);
        text-decoration: none;
        padding: var(--space-2) var(--space-4);
        transition: all var(--transition-fast);
        cursor: pointer;
        border: none;
    }

    .btn-add {
        background-color: var(--accent-blue);
        color: var(--white);
        box-shadow: var(--shadow-md);
    }

    .btn-add:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(46, 134, 222, 0.3);
    }

    /* Actions contextuelles de la table (icônes) */
    .btn-icon {
        width: 32px;
        height: 32px;
        border-radius: var(--radius-md);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: all var(--transition-fast);
        color: var(--gray-300);
        border: 1px solid var(--border-glass);
        background-color: rgba(255, 255, 255, 0.02);
        text-decoration: none;
    }

    .btn-icon:hover {
        color: var(--white);
    }

    .btn-edit-active:hover {
        background-color: var(--accent-blue);
        border-color: var(--accent-blue);
        box-shadow: 0 0 10px rgba(46, 134, 222, 0.2);
    }

    .btn-delete-active:hover {
        background-color: var(--accent-red);
        border-color: var(--accent-red);
        box-shadow: 0 0 10px rgba(255, 71, 87, 0.2);
    }

    .actions-wrapper {
        display: flex;
        gap: var(--space-2);
    }

    .text-empty {
        text-align: center;
        padding: var(--space-6) !important;
        color: var(--gray-400);
        font-style: italic;
    }
</style>

<div class="container-fluid">

    <!-- En-tête de section structuré -->
    <div class="page-header">
        <h2>
            <svg width="22" height="22" fill="none" stroke="var(--accent-blue)" stroke-width="2.5" viewBox="0 0 24 24">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                <line x1="16" y1="2" x2="16" y2="6"></line>
                <line x1="8" y1="2" x2="8" y2="6"></line>
                <line x1="3" y1="10" x2="21" y2="10"></line>
            </svg>
            Années universitaires
        </h2>
        <a href="ajouter_annee.php" class="btn-action btn-add">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <line x1="12" y1="5" x2="12" y2="19"></line>
                <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            Ajouter une année
        </a>
    </div>

    <!-- Table Responsive Card Wrap -->
    <div class="table-container">
        <table class="custom-table">
            <thead>
                <tr>
                    <th style="width: 70px;">#</th>
                    <th>Label / Libellé</th>
                    <th>Date de début</th>
                    <th>Date de fin</th>
                    <th style="width: 140px;">Statut</th>
                    <th style="width: 100px; text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($annees)): ?>
                    <?php $i = 1; ?>
                    <?php foreach ($annees as $a): ?>
                        <tr>
                            <td><span style="color: var(--gray-400); font-weight: 600;"><?= $i ?></span></td>
                            <td style="font-weight: 600; color: var(--white);">
                                <?= htmlspecialchars($a['label']) ?>
                            </td>
                            <td><?= date('d/m/Y', strtotime($a['start_date'])) ?></td>
                            <td><?= date('d/m/Y', strtotime($a['end_date'])) ?></td>
                            <td>
                                <?php if ($a['is_current']): ?>
                                    <span class="badge-status badge-current">
                                        <span style="width: 6px; height: 6px; background-color: var(--accent-green); border-radius: 50%;"></span>
                                        Actuelle
                                    </span>
                                <?php else: ?>
                                    <span class="badge-status badge-past">Précédente</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="actions-wrapper" style="justify-content: flex-end;">
                                    <!-- Bouton Éditer -->
                                    <a href="modifier_annee.php?id=<?= $a['id'] ?>" class="btn-icon btn-edit-active" title="Modifier l'année">
                                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                            <path d="M12 20h9"></path>
                                            <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path>
                                        </svg>
                                    </a>
                                    <!-- Bouton Supprimer -->
                                    <a href="annees.php?delete=<?= $a['id'] ?>" 
                                       class="btn-icon btn-delete-active" 
                                       title="Supprimer l'année"
                                       onclick="return confirm('Voulez-vous vraiment supprimer définitivement cette année universitaire ?');">
                                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                            <polyline points="3 6 5 6 21 6"></polyline>
                                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                            <line x1="10" y1="11" x2="10" y2="17"></line>
                                            <line x1="14" y1="11" x2="14" y2="17"></line>
                                        </svg>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php $i++; ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-empty">
                            Aucune année universitaire enregistrée pour le moment.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
// Récupération du flux et injection dans l'architecture principale
$content = ob_get_clean();
include 'layout.php';
?>