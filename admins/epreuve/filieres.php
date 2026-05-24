<?php
session_start();
require_once '../../includes/db.php';

// ---- TRAITEMENT SUPPRESSION (Sécurisé) ----
if (isset($_GET['delete'])) {
    $id_to_delete = intval($_GET['delete']);
    if ($id_to_delete > 0) {
        $stmt = $pdo->prepare("DELETE FROM filieres WHERE id_filiere = ?");
        $stmt->execute([$id_to_delete]);
    }
    header("Location: filieres.php");
    exit;
}

// ---- MOTEUR DE RECHERCHE ----
$search = trim($_GET['search'] ?? '');

$sql = "SELECT * FROM filieres WHERE 1";
$params = [];

if ($search !== "") {
    $sql .= " AND (nom_filiere LIKE :search OR description LIKE :search)";
    $params[':search'] = "%$search%";
}
$sql .= " ORDER BY nom_filiere ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$filieres = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ---- CAPTURE DU LAYOUT ----
ob_start();
?>

<style>
    /* -------------------------------------------------------------------------- */
    /* CONTAINER PRINCIPAL & TYPOGRAPHIE                                          */
    /* -------------------------------------------------------------------------- */
    .filiere-dashboard-wrapper {
        font-family: var(--font-primary);
        color: var(--white);
        max-width: 1200px;
        margin: 0 auto;
        padding: var(--space-4) var(--space-3);
    }

    /* -------------------------------------------------------------------------- */
    /* EN-TÊTE DYNAMIQUE (HERO CARD)                                              */
    /* -------------------------------------------------------------------------- */
    .dashboard-hero-card {
        background: linear-gradient(135deg, var(--primary-800) 0%, var(--primary-700) 100%);
        border: var(--sidebar-border);
        border-radius: var(--radius-lg);
        padding: var(--space-5) var(--space-4);
        box-shadow: var(--shadow-md);
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: var(--space-5);
        position: relative;
        overflow: hidden;
        gap: var(--space-4);
    }

    .dashboard-hero-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 4px;
        height: 100%;
        background: var(--accent-blue);
    }

    .hero-text-group {
        display: flex;
        align-items: center;
        gap: var(--space-4);
    }

    .hero-icon-container {
        background-color: rgba(46, 134, 222, 0.1);
        border: 1px solid rgba(46, 134, 222, 0.2);
        padding: var(--space-3);
        border-radius: var(--radius-md);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--accent-blue);
    }

    .dashboard-hero-card h1 {
        font-size: calc(var(--font-size-xl) * 1.25);
        font-weight: 700;
        margin: 0 0 var(--space-1) 0;
        letter-spacing: -0.02em;
    }

    .dashboard-hero-card p {
        color: var(--gray-400);
        font-size: var(--font-size-sm);
        margin: 0;
    }

    /* -------------------------------------------------------------------------- */
    /* MOTEUR DE RECHERCHE                                                        */
    /* -------------------------------------------------------------------------- */
    .search-filter-card {
        background-color: var(--primary-800);
        border: var(--sidebar-border);
        border-radius: var(--radius-lg);
        padding: var(--space-4);
        margin-bottom: var(--space-5);
        box-shadow: var(--shadow-lg);
    }

    .search-form-flex {
        display: flex;
        gap: var(--space-3);
        align-items: center;
    }

    .search-input-wrapper {
        position: relative;
        flex-grow: 1;
    }

    .search-input-wrapper svg {
        position: absolute;
        left: var(--space-3);
        top: 50%;
        transform: translateY(-50%);
        color: var(--gray-400);
        pointer-events: none;
    }

    .custom-field-control {
        width: 100%;
        box-sizing: border-box;
        font-family: var(--font-primary);
        font-size: var(--font-size-sm);
        background-color: var(--primary-900);
        border: 1px solid var(--primary-600);
        border-radius: var(--radius-md);
        color: var(--white);
        padding: var(--space-3) var(--space-3) var(--space-3) calc(var(--space-4) * 2.2);
        height: 46px;
        transition: border-color var(--transition-fast), box-shadow var(--transition-fast);
    }

    .custom-field-control:focus {
        outline: none;
        border-color: var(--accent-blue);
        box-shadow: 0 0 0 3px rgba(46, 134, 222, 0.15);
    }

    /* -------------------------------------------------------------------------- */
    /* SYSTEME DE BOUTONS ACCENTUÉS                                               */
    /* -------------------------------------------------------------------------- */
    .btn-action-trigger {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: var(--space-2);
        font-family: var(--font-primary);
        font-size: var(--font-size-sm);
        font-weight: 600;
        border-radius: var(--radius-md);
        text-decoration: none;
        padding: 0 var(--space-4);
        height: 46px;
        transition: all var(--transition-fast);
        cursor: pointer;
        border: none;
        white-space: nowrap;
    }

    .btn-accent-blue { background-color: var(--accent-blue); color: var(--white); box-shadow: 0 4px 12px rgba(46, 134, 222, 0.2); }
    .btn-accent-blue:hover { background-color: #2475c4; transform: translateY(-1px); }

    .btn-accent-blue-soft { background-color: rgba(46, 134, 222, 0.1); color: var(--accent-blue); border: 1px solid rgba(46, 134, 222, 0.2); }
    .btn-accent-blue-soft:hover { background-color: var(--accent-blue); color: var(--white); }

    /* Boutons d'action sur les lignes */
    .row-interactive-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 34px;
        height: 34px;
        border-radius: var(--radius-md);
        text-decoration: none;
        transition: all var(--transition-fast);
        border: none;
        cursor: pointer;
    }

    .btn-row-edit { background-color: rgba(46, 134, 222, 0.1); color: var(--accent-blue); border: 1px solid rgba(46, 134, 222, 0.15); }
    .btn-row-edit:hover { background-color: var(--accent-blue); color: var(--white); }

    .btn-row-delete { background-color: rgba(255, 71, 87, 0.1); color: var(--accent-red); border: 1px solid rgba(255, 71, 87, 0.15); }
    .btn-row-delete:hover { background-color: var(--accent-red); color: var(--white); }

    /* -------------------------------------------------------------------------- */
    /* COMPOSANT TABLEAU DE DONNÉES                                               */
    /* -------------------------------------------------------------------------- */
    .data-table-container {
        background-color: var(--primary-800);
        border: var(--sidebar-border);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-xl);
        overflow: hidden;
    }

    .table-view-scroller {
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    .structured-data-table {
        width: 100%;
        border-collapse: collapse;
        text-align: left;
        font-size: var(--font-size-sm);
    }

    .structured-data-table th {
        background-color: rgba(0, 0, 0, 0.2);
        color: var(--gray-400);
        font-weight: 600;
        text-transform: uppercase;
        font-size: var(--font-size-xs);
        letter-spacing: 0.05em;
        padding: var(--space-4);
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    }

    .structured-data-table td {
        padding: var(--space-4);
        color: var(--gray-100);
        border-bottom: 1px solid rgba(255, 255, 255, 0.03);
    }

    .structured-data-table tbody tr:hover td {
        background-color: rgba(255, 255, 255, 0.01);
    }

    .text-truncate-description {
        max-width: 450px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        color: var(--gray-300);
    }

    .actions-cell-alignment {
        display: flex;
        gap: var(--space-2);
        align-items: center;
    }

    /* -------------------------------------------------------------------------- */
    /* RESPONSIVITÉ SANS DEFAUT (MEDIA QUERIES)                                   */
    /* -------------------------------------------------------------------------- */
    @media (max-width: 768px) {
        .dashboard-hero-card {
            flex-direction: column;
            align-items: flex-start;
        }

        .dashboard-hero-card .btn-action-trigger {
            width: 100%;
        }

        .search-form-flex {
            flex-direction: column;
            align-items: stretch;
        }

        .search-form-flex .btn-action-trigger {
            width: 100%;
        }
    }
</style>

<div class="filiere-dashboard-wrapper">

    <!-- En-tête de Section Épuré -->
    <header class="dashboard-hero-card">
        <div class="hero-text-group">
            <div class="hero-icon-container">
                <svg width="26" height="26" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M22 10v6M2 10l10-5 10 5-10 5z"></path>
                    <path d="M6 12v5c0 2 2 3 6 3s6-1 6-3v-5"></path>
                </svg>
            </div>
            <div>
                <h1>Gestion des Filières</h1>
                <p>Configurez et pilotez les filières d'enseignement rattachées à votre établissement.</p>
            </div>
        </div>
        <a href="ajouter_filiere.php" class="btn-action-trigger btn-accent-blue">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            Ajouter une filière
        </a>
    </header>

    <!-- Zone de Filtrage / Moteur de Recherche -->
    <div class="search-filter-card">
        <form method="GET">
            <div class="search-form-flex">
                <div class="search-input-wrapper">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                           class="custom-field-control" placeholder="Rechercher par nom de filière ou mots-clés...">
                </div>
                <button type="submit" class="btn-action-trigger btn-accent-blue-soft">Filtrer la liste</button>
            </div>
        </form>
    </div>

    <!-- Conteneur d'affichage des données -->
    <div class="data-table-container">
        <div class="table-view-scroller">
            <table class="structured-data-table">
                <thead>
                    <tr>
                        <th style="width: 60px; text-align: center;">Index</th>
                        <th>Nom de la Filière</th>
                        <th>Description / Spécifications</th>
                        <th style="width: 110px; text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($filieres)): ?>
                        <tr>
                            <td colspan="4" style="text-align: center; color: var(--gray-400); padding: var(--space-6) 0;">
                                <div style="display: flex; flex-direction: column; align-items: center; gap: var(--space-2); opacity: 0.7;">
                                    <svg width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                        <circle cx="12" cy="12" r="10"></circle>
                                        <line x1="12" y1="8" x2="12" y2="12"></line>
                                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                                    </svg>
                                    <span>Aucun enregistrement ne correspond à vos critères de recherche.</span>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($filieres as $index => $filiere): ?>
                            <tr>
                                <td style="text-align: center; color: var(--gray-400); font-weight: 600;"><?= $index + 1 ?></td>
                                <td style="font-weight: 600; color: var(--white);"><?= htmlspecialchars($filiere['nom_filiere']) ?></td>
                                <td>
                                    <div class="text-truncate-description" title="<?= htmlspecialchars($filiere['description']) ?>">
                                        <?= !empty($filiere['description']) ? htmlspecialchars($filiere['description']) : '<span style="color:var(--gray-400); font-style:italic;">Aucune description fournie.</span>' ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="actions-cell-alignment">
                                        <a href="modifier_filiere.php?id=<?= $filiere['id_filiere'] ?>" class="row-interactive-btn btn-row-edit" title="Modifier">
                                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                                <path d="M18.5 2.5a2.121 2.121 0 1 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                            </svg>
                                        </a>
                                        <a href="filieres.php?delete=<?= $filiere['id_filiere'] ?>" class="row-interactive-btn btn-row-delete" title="Supprimer"
                                           onclick="return confirm('Êtes-vous sûr de vouloir supprimer définitivement cette filière ? Cette action est irréversible.')">
                                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                                <polyline points="3 6 5 6 21 6"></polyline>
                                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                                <line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line>
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