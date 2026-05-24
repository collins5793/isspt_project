<?php
session_start();
require_once '../../includes/db.php';

// Sécurité : Vérifier si l'admin est connecté (Décommentez si nécessaire)
// if (!isset($_SESSION['admin_id'])) { header('Location: connexion_admin.php'); exit; }

// =====================
//      TRAITEMENT DELETE
// =====================
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $pdo->prepare("DELETE FROM epreuves_categories WHERE id_category = ?")->execute([$id]);
    header("Location: categories.php");
    exit;
}

// =====================
//      RECHERCHE & FILTRE
// =====================
$search = $_GET['search'] ?? '';

$sql = "SELECT c.*, a.nom AS admin_nom, a.prenom AS admin_prenom
        FROM epreuves_categories c
        LEFT JOIN administrateurs a ON c.created_by = a.id_admin
        WHERE 1 ";

if ($search !== "") {
    $sql .= " AND c.nom_category LIKE :s ";
}

$sql .= " ORDER BY c.nom_category ASC";

$stmt = $pdo->prepare($sql);
if ($search !== "") $stmt->bindValue(":s", "%$search%");
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ---- Début de la mise en mémoire tampon ----
ob_start();
?>

<style>
    /* -------------------------------------------------------------------------- */
    /* STRUCTURE ET CONTENEUR GLOBAL                                              */
    /* -------------------------------------------------------------------------- */
    .dashboard-view {
        font-family: var(--font-primary);
        color: var(--white);
        padding: var(--space-2) 0;
    }

    /* En-tête Pro */
    .view-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: var(--space-5);
        padding-bottom: var(--space-4);
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        flex-wrap: wrap;
        gap: var(--space-4);
    }

    .view-header h2 {
        font-size: var(--font-size-xl);
        font-weight: 700;
        margin: 0;
        display: flex;
        align-items: center;
        gap: var(--space-3);
        letter-spacing: -0.02em;
    }

    /* -------------------------------------------------------------------------- */
    /* BARRE DE RECHERCHE / FILTRE                                                */
    /* -------------------------------------------------------------------------- */
    .search-card {
        background-color: var(--primary-800);
        border: var(--sidebar-border);
        border-radius: var(--radius-lg);
        padding: var(--space-4);
        margin-bottom: var(--space-5);
        box-shadow: var(--shadow-md);
    }

    .search-form-flex {
        display: flex;
        gap: var(--space-3);
        width: 100%;
    }

    .search-input-wrapper {
        position: relative;
        flex-grow: 1;
    }

    .search-input-wrapper svg {
        position: absolute;
        left: var(--space-4);
        top: 50%;
        transform: translateY(-50%);
        color: var(--gray-400);
        pointer-events: none;
    }

    .form-input-custom {
        width: 100%;
        box-sizing: border-box;
        background-color: var(--primary-900);
        border: 1px solid var(--primary-700);
        border-radius: var(--radius-md);
        color: var(--white);
        font-family: var(--font-primary);
        font-size: var(--font-size-sm);
        padding: var(--space-3) var(--space-4) var(--space-3) calc(var(--space-4) * 2.5);
        transition: border-color var(--transition-fast), box-shadow var(--transition-fast);
    }

    .form-input-custom:focus {
        outline: none;
        border-color: var(--accent-blue);
        box-shadow: 0 0 0 3px rgba(46, 134, 222, 0.15);
    }

    /* -------------------------------------------------------------------------- */
    /* BOUTONS STYLE PREMIUM                                                      */
    /* -------------------------------------------------------------------------- */
    .btn-action-premium {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: var(--space-2);
        font-family: var(--font-primary);
        font-size: var(--font-size-sm);
        font-weight: 600;
        border-radius: var(--radius-md);
        text-decoration: none;
        padding: var(--space-3) var(--space-4);
        transition: all var(--transition-fast);
        cursor: pointer;
        border: none;
        white-space: nowrap;
    }

    .btn-add-primary {
        background-color: var(--accent-blue);
        color: var(--white);
        box-shadow: 0 4px 12px rgba(46, 134, 222, 0.2);
    }

    .btn-add-primary:hover {
        background-color: #2475c4;
        transform: translateY(-1px);
        box-shadow: 0 6px 16px rgba(46, 134, 222, 0.3);
    }

    .btn-search-dark {
        background-color: var(--primary-700);
        color: var(--white);
        border: 1px solid rgba(255, 255, 255, 0.05);
    }

    .btn-search-dark:hover {
        background-color: var(--primary-600);
        border-color: var(--accent-blue);
    }

    /* -------------------------------------------------------------------------- */
    /* DATA TABLE & WRAPPER                                                      */
    /* -------------------------------------------------------------------------- */
    .table-premium-container {
        background-color: var(--primary-800);
        border: var(--sidebar-border);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-xl);
        overflow: hidden;
        width: 100%;
    }

    .table-responsive-box {
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    .table-custom-data {
        width: 100%;
        border-collapse: collapse;
        text-align: left;
        font-size: var(--font-size-sm);
    }

    .table-custom-data th {
        background-color: var(--primary-700);
        color: var(--gray-300);
        font-weight: 600;
        text-transform: uppercase;
        font-size: var(--font-size-xs);
        letter-spacing: 0.05em;
        padding: var(--space-4);
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    }

    .table-custom-data td {
        padding: var(--space-4);
        color: var(--gray-100);
        border-bottom: 1px solid rgba(255, 255, 255, 0.03);
        transition: background-color var(--transition-fast);
    }

    .table-custom-data tbody tr:hover td {
        background-color: rgba(255, 255, 255, 0.02);
    }

    /* Colonne description raccourcie au besoin */
    .desc-cell {
        color: var(--gray-400);
        max-width: 300px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .author-badge {
        display: inline-flex;
        align-items: center;
        gap: var(--space-2);
        background-color: var(--primary-700);
        padding: 4px var(--space-3);
        border-radius: var(--radius-sm);
        font-size: var(--font-size-xs);
        font-weight: 500;
        color: var(--white);
        border: 1px solid rgba(255, 255, 255, 0.03);
    }

    .date-text {
        font-size: var(--font-size-xs);
        color: var(--gray-400);
    }

    /* Actions Contextuelles (Boutons Modifier/Supprimer) */
    .row-actions-cell {
        display: flex;
        align-items: center;
        gap: var(--space-2);
    }

    .btn-mini-action {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 32px;
        height: 32px;
        border-radius: var(--radius-md);
        text-decoration: none;
        transition: all var(--transition-fast);
        border: none;
        cursor: pointer;
    }

    .action-edit {
        background-color: rgba(46, 134, 222, 0.1);
        color: var(--accent-blue);
        border: 1px solid rgba(46, 134, 222, 0.15);
    }

    .action-edit:hover {
        background-color: var(--accent-blue);
        color: var(--white);
    }

    .action-delete {
        background-color: rgba(255, 71, 87, 0.1);
        color: var(--accent-red);
        border: 1px solid rgba(255, 71, 87, 0.15);
    }

    .action-delete:hover {
        background-color: var(--accent-red);
        color: var(--white);
    }

    /* État de liste vide */
    .empty-state-wrapper {
        padding: var(--space-6) var(--space-4);
        text-align: center;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: var(--space-3);
    }

    .empty-state-wrapper p {
        color: var(--gray-400);
        margin: 0;
        font-size: var(--font-size-sm);
    }

    /* -------------------------------------------------------------------------- */
    /* RESPONSIVITÉ ET COMPORTEMENT SUR ÉCRAN INTERMÉDIAIRE                       */
    /* -------------------------------------------------------------------------- */
    @media (max-width: 768px) {
        .search-form-flex {
            flex-direction: column;
            align-items: stretch;
        }

        .btn-search-dark {
            width: 100%;
        }
    }

    @media (max-width: 576px) {
        .view-header {
            flex-direction: column;
            align-items: fill;
        }

        .btn-add-primary {
            width: 100%;
        }
    }
</style>

<div class="dashboard-view">

    <div class="view-header">
        <h2>
            <svg width="22" height="22" fill="none" stroke="var(--accent-blue)" stroke-width="2.5" viewBox="0 0 24 24">
                <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>
            </svg>
            Catégories d’épreuves
        </h2>
        <a href="ajouter_categorie.php" class="btn-action-premium btn-add-primary">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <line x1="12" y1="5" x2="12" y2="19"></line>
                <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            Ajouter une catégorie
        </a>
    </div>

    <div class="search-card">
        <form method="GET">
            <div class="search-form-flex">
                <div class="search-input-wrapper">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                    <input type="text" name="search" class="form-input-custom" 
                           placeholder="Rechercher une catégorie par nom ou mot-clé..." 
                           value="<?= htmlspecialchars($search) ?>" autocomplete="off">
                </div>
                <button type="submit" class="btn-action-premium btn-search-dark">Rechercher</button>
            </div>
        </form>
    </div>

    <div class="table-premium-container">
        <div class="table-responsive-box">
            <table class="table-custom-data">
                <thead>
                    <tr>
                        <th style="width: 60px;">#</th>
                        <th>Nom de la catégorie</th>
                        <th>Description</th>
                        <th>Créée par</th>
                        <th>Date de création</th>
                        <th style="width: 100px; text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($categories)): ?>
                    <tr>
                        <td colspan="6">
                            <div class="empty-state-wrapper">
                                <svg width="36" height="36" fill="none" stroke="var(--gray-400)" stroke-width="1.5" viewBox="0 0 24 24">
                                    <path d="M22 12h-4l-3 9L9 3l-3 9H2"></path>
                                </svg>
                                <p>Aucune catégorie d'épreuve n'a été trouvée dans le système.</p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($categories as $i => $cat): ?>
                    <tr>
                        <td><strong><?= $i + 1 ?></strong></td>
                        <td style="font-weight: 600; color: var(--white);">
                            <?= htmlspecialchars($cat['nom_category']) ?>
                        </td>
                        <td>
                            <div class="desc-cell" title="<?= htmlspecialchars($cat['description']) ?>">
                                <?= !empty($cat['description']) ? htmlspecialchars($cat['description']) : '<span style="opacity: 0.4; font-style: italic;">Aucune description renseignée</span>' ?>
                            </div>
                        </td>
                        <td>
                            <div class="author-badge">
                                <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="12" cy="7" r="4"></circle>
                                </svg>
                                <?= htmlspecialchars(($cat['admin_prenom'] ?? '') . " " . ($cat['admin_nom'] ?? 'Système')) ?>
                            </div>
                        </td>
                        <td>
                            <span class="date-text">
                                <?= date('d/m/Y à H:i', strtotime($cat['created_at'])) ?>
                            </span>
                        </td>
                        <td>
                            <div class="row-actions-cell">
                                <a href="modifier_categorie.php?id=<?= $cat['id_category'] ?>" class="btn-mini-action action-edit" title="Modifier">
                                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                        <path d="M18.5 2.5a2.121 2.121 0 1 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                    </svg>
                                </a>
                                <a href="categories.php?delete=<?= $cat['id_category'] ?>" class="btn-mini-action action-delete" title="Supprimer"
                                   onclick="return confirm('Êtes-vous sûr de vouloir supprimer définitivement cette catégorie ? Cela peut impacter les épreuves associées.')">
                                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <polyline points="3 6 5 6 21 6"></polyline>
                                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                    </svg>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include '../layout.php';
?>