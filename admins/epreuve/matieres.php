<?php
session_start();
require_once '../../includes/db.php';

// Action de suppression sécurisée (Idéalement à passer en POST, mais conservé en GET pour rester compatible avec votre logique actuelle)
if (isset($_GET['delete'])) {
    $id_delete = intval($_GET['delete']);
    $stmtDelete = $pdo->prepare("DELETE FROM matiere_epreuves WHERE id_matiere = ?");
    $stmtDelete->execute([$id_delete]);
    header("Location: matieres.php");
    exit;
}

// Récupération et assainissement de la recherche
$search = trim($_GET['search'] ?? '');

$sql = "SELECT m.*, f.nom_filiere
        FROM matiere_epreuves m
        LEFT JOIN filieres f ON m.id_filiere = f.id_filiere
        WHERE 1 ";

if ($search !== "") {
    $sql .= " AND (m.nom_matiere LIKE :s OR f.nom_filiere LIKE :s)";
}

$sql .= " ORDER BY m.nom_matiere ASC";

$stmt = $pdo->prepare($sql);
if ($search !== "") {
    $stmt->bindValue(":s", "%$search%");
}
$stmt->execute();
$matieres = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ---- Début de la capture du contenu pour le layout ----
ob_start();
?>

<style>
    /* Container principal calibré */
    .dashboard-container {
        font-family: var(--font-primary);
        color: var(--white);
        max-width: 1400px;
        margin: 0 auto;
        padding: var(--space-4) var(--space-3);
    }

    /* En-tête haut de gamme style "Glassmorphism" opaque */
    .page-hero-header {
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

    .page-hero-header::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 4px;
        height: 100%;
        background: var(--accent-blue);
    }

    .header-info-flex {
        display: flex;
        align-items: center;
        gap: var(--space-4);
    }

    .header-icon-wrapper {
        background-color: rgba(46, 134, 222, 0.1);
        border: 1px solid rgba(46, 134, 222, 0.2);
        padding: var(--space-3);
        border-radius: var(--radius-md);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--accent-blue);
    }

    .header-meta-text h1 {
        font-size: calc(var(--font-size-xl) * 1.2);
        font-weight: 700;
        margin: 0 0 var(--space-1) 0;
        letter-spacing: -0.01em;
    }

    .header-meta-text p {
        color: var(--gray-400);
        font-size: var(--font-size-sm);
        margin: 0;
    }

    /* Boutons revisités */
    .btn-custom {
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
        height: 44px;
        transition: all var(--transition-fast);
        cursor: pointer;
        border: none;
        white-space: nowrap;
    }

    .btn-accent-blue { 
        background-color: var(--accent-blue); 
        color: var(--white); 
        box-shadow: 0 4px 12px rgba(46, 134, 222, 0.2); 
    }
    .btn-accent-blue:hover { 
        background-color: #2475c4; 
        transform: translateY(-1px); 
    }

    .btn-search-submit { 
        background-color: var(--primary-600); 
        color: var(--white); 
        border: 1px solid rgba(255, 255, 255, 0.05); 
    }
    .btn-search-submit:hover { 
        background-color: #22205a; 
    }

    /* Actions contextuelles du tableau */
    .table-action-trigger {
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

    .action-modify { 
        background-color: rgba(46, 134, 222, 0.1); 
        color: var(--accent-blue); 
        border: 1px solid rgba(46, 134, 222, 0.15); 
    }
    .action-modify:hover { 
        background-color: var(--accent-blue); 
        color: var(--white); 
    }

    .action-remove { 
        background-color: rgba(255, 71, 87, 0.1); 
        color: var(--accent-red); 
        border: 1px solid rgba(255, 71, 87, 0.15); 
    }
    .action-remove:hover { 
        background-color: var(--accent-red); 
        color: var(--white); 
    }

    /* Barre de filtrage moderne */
    .filter-card-panel {
        background-color: var(--primary-800);
        border: var(--sidebar-border);
        border-radius: var(--radius-lg);
        padding: var(--space-4);
        margin-bottom: var(--space-5);
        box-shadow: var(--shadow-lg);
    }

    .filter-form-grid {
        display: grid;
        grid-template-columns: 1fr auto;
        gap: var(--space-3);
        align-items: center;
    }

    .input-with-inline-icon {
        position: relative;
        width: 100%;
    }

    .input-with-inline-icon svg {
        position: absolute;
        left: var(--space-3);
        top: 50%;
        transform: translateY(-50%);
        color: var(--gray-400);
        pointer-events: none;
    }

    .form-input-field {
        width: 100%;
        box-sizing: border-box;
        font-family: var(--font-primary);
        font-size: var(--font-size-sm);
        background-color: var(--primary-900);
        border: 1px solid var(--primary-600);
        border-radius: var(--radius-md);
        color: var(--white);
        height: 44px;
        padding: var(--space-2) var(--space-3) var(--space-2) calc(var(--space-4) * 2.2);
        transition: border-color var(--transition-fast), box-shadow var(--transition-fast);
    }

    .form-input-field:focus {
        outline: none;
        border-color: var(--accent-blue);
        box-shadow: 0 0 0 3px rgba(46, 134, 222, 0.15);
    }

    /* Architecture du Tableau de données */
    .table-data-wrapper {
        background-color: var(--primary-800);
        border: var(--sidebar-border);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-xl);
        overflow: hidden;
    }

    .responsive-overflow-layer {
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    .custom-dashboard-table {
        width: 100%;
        border-collapse: collapse;
        text-align: left;
        font-size: var(--font-size-sm);
    }

    .custom-dashboard-table th {
        background-color: rgba(0, 0, 0, 0.2);
        color: var(--gray-400);
        font-weight: 600;
        text-transform: uppercase;
        font-size: var(--font-size-xs);
        letter-spacing: 0.05em;
        padding: var(--space-4) var(--space-3);
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        white-space: nowrap;
    }

    .custom-dashboard-table td {
        padding: var(--space-4) var(--space-3);
        color: var(--gray-100);
        border-bottom: 1px solid rgba(255, 255, 255, 0.03);
        vertical-align: middle;
    }

    .custom-dashboard-table tbody tr:hover td {
        background-color: rgba(255, 255, 255, 0.01);
    }

    /* Typographies spécifiques aux cellules */
    .cell-title-bold {
        color: var(--white);
        font-weight: 600;
    }

    .cell-description-text {
        color: var(--gray-300);
        max-width: 350px;
        white-space: normal;
        word-break: break-word;
        line-height: 1.4;
    }

    .badge-filiere-tag {
        display: inline-flex;
        align-items: center;
        padding: var(--space-1) var(--space-2);
        border-radius: var(--radius-sm);
        font-size: var(--font-size-xs);
        font-weight: 600;
        background-color: rgba(255, 255, 255, 0.05);
        color: var(--gray-200);
        border: 1px solid rgba(255, 255, 255, 0.05);
        white-space: nowrap;
    }

    .cell-date-format {
        color: var(--gray-400);
        font-size: var(--font-size-xs);
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .actions-flex-gap {
        display: flex;
        gap: var(--space-2);
        align-items: center;
    }

    /* Responsivité & Flexibilité Écrans */
    @media (max-width: 768px) {
        .page-hero-header {
            flex-direction: column;
            align-items: flex-start;
            padding: var(--space-4);
        }

        .page-hero-header .btn-custom {
            width: 100%;
        }

        .filter-form-grid {
            grid-template-columns: 1fr;
            gap: var(--space-2);
        }

        .filter-form-grid .btn-custom {
            width: 100%;
        }
    }
</style>

<div class="dashboard-container">

    <!-- En-tête de page -->
    <header class="page-hero-header">
        <div class="header-info-flex">
            <div class="header-icon-wrapper">
                <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                    <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
                </svg>
            </div>
            <div class="header-meta-text">
                <h1>Gestion des Matières</h1>
                <p>Configurez et listez les matières d'enseignement associées à vos filières.</p>
            </div>
        </div>
        <a href="ajouter_matiere.php" class="btn-custom btn-accent-blue">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <line x1="12" y1="5" x2="12" y2="19"></line>
                <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            Ajouter une matière
        </a>
    </header>

    <!-- Zone de filtrage -->
    <div class="filter-card-panel">
        <form method="GET">
            <div class="filter-form-grid">
                <div class="input-with-inline-icon">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                           class="form-input-field" placeholder="Rechercher une matière ou une filière...">
                </div>
                <button type="submit" class="btn-custom btn-search-submit">Rechercher</button>
            </div>
        </form>
    </div>

    <!-- Tableau de données -->
    <div class="table-data-wrapper">
        <div class="responsive-overflow-layer">
            <table class="custom-dashboard-table">
                <thead>
                    <tr>
                        <th style="width: 60px; text-align: center;">ID</th>
                        <th>Intitulé de la Matière</th>
                        <th>Filière Rattachée</th>
                        <th>Description contextuelle</th>
                        <th>Date de création</th>
                        <th style="width: 110px; text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if(empty($matieres)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; color: var(--gray-400); padding: var(--space-6) 0;">
                            <div style="display: flex; flex-direction: column; align-items: center; gap: var(--space-2); opacity: 0.5;">
                                <svg width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <line x1="12" y1="8" x2="12" y2="12"></line>
                                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                                </svg>
                                <span>Aucun enregistrement ne correspond à vos critères de recherche.</span>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach($matieres as $i => $m): ?>
                    <tr>
                        <td style="text-align: center; color: var(--gray-400); font-weight: 600;"><?= $i+1 ?></td>
                        <td class="cell-title-bold"><?= htmlspecialchars($m['nom_matiere']) ?></td>
                        <td>
                            <?php if(!empty($m['nom_filiere'])): ?>
                                <span class="badge-filiere-tag"><?= htmlspecialchars($m['nom_filiere']) ?></span>
                            <?php else: ?>
                                <span class="cell-meta-gray" style="font-style: italic;">Non assignée</span>
                            <?php endif; ?>
                        </td>
                        <td class="cell-description-text">
                            <?= !empty($m['description']) ? htmlspecialchars($m['description']) : '<span style="color:var(--gray-400); font-style:italic;">Aucune description renseignée.</span>' ?>
                        </td>
                        <td class="cell-date-format">
                            <?= !empty($m['created_at']) ? date('d/m/Y H:i', strtotime($m['created_at'])) : '--/--/----' ?>
                        </td>
                        <td>
                            <div class="actions-flex-gap">
                                <a href="modifier_matiere.php?id=<?= $m['id_matiere'] ?>" class="table-action-trigger action-modify" title="Modifier la fiche">
                                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                        <path d="M18.5 2.5a2.121 2.121 0 1 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                    </svg>
                                </a>
                                <a href="matieres.php?delete=<?= $m['id_matiere'] ?>" class="table-action-trigger action-remove" title="Supprimer la matière"
                                   onclick="return confirm('Attention ! Confirmez-vous la suppression irréversible de cette matière ?')">
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