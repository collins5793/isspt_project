<?php
session_start();
require_once '../../includes/db.php';

// ----------- Recherche & Filtre -------------
$search = trim($_GET['search'] ?? '');
$year_filter = $_GET['year'] ?? '';

// Construire la requête avec jointures optimisées et décompte des téléchargements
$sql = "
    SELECT e.id_epreuve, e.titre, e.file_path, e.niveau, e.date_ajout,
           c.nom_category,
           f.nom_filiere,
           m.nom_matiere,
           ay.label AS academic_year,
           a.nom AS admin_nom, a.prenom AS admin_prenom,
           COUNT(d.id_download) AS total_downloads
    FROM epreuves e
    LEFT JOIN epreuves_categories c ON e.id_category = c.id_category
    LEFT JOIN filieres f ON e.id_filiere = f.id_filiere
    LEFT JOIN matiere_epreuves m ON e.id_matiere = m.id_matiere
    LEFT JOIN academic_years ay ON e.academic_year_id = ay.id
    LEFT JOIN administrateurs a ON e.ajoute_par = a.id_admin
    LEFT JOIN epreuves_downloads d ON e.id_epreuve = d.id_epreuve
    WHERE 1
";

// Recherche sécurisée
if ($search !== "") {
    $sql .= " AND (e.titre LIKE :search OR m.nom_matiere LIKE :search)";
}

// Filtre année universitaire
if ($year_filter !== "") {
    $sql .= " AND ay.id = :year_filter";
}

// GROUP BY obligatoire avant le tri pour agréger le COUNT() correctement
$sql .= " GROUP BY e.id_epreuve, c.nom_category, f.nom_filiere, m.nom_matiere, ay.label, a.nom, a.prenom";
$sql .= " ORDER BY e.date_ajout DESC";

$stmt = $pdo->prepare($sql);

if ($search !== "") $stmt->bindValue(':search', "%$search%");
if ($year_filter !== "") $stmt->bindValue(':year_filter', $year_filter);

$stmt->execute();
$epreuves = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les années pour le composant select
$years = $pdo->query("SELECT id, label FROM academic_years ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

// ---- Début de capture du Layout ----
ob_start();
?>

<style>
    /* -------------------------------------------------------------------------- */
    /* ENVELOPPE DE PAGE GLOBALE                                                 */
    /* -------------------------------------------------------------------------- */
    .dashboard-view-container {
        font-family: var(--font-primary);
        color: var(--white);
        max-width: 1400px;
        margin: 0 auto;
        padding: var(--space-4) var(--space-3);
    }

    /* -------------------------------------------------------------------------- */
    /* EN-TÊTE DE LA PAGE (HERO STYLE)                                            */
    /* -------------------------------------------------------------------------- */
    .dashboard-header-hero {
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

    .dashboard-header-hero::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 4px;
        height: 100%;
        background: var(--accent-blue);
    }

    .header-context-flex {
        display: flex;
        align-items: center;
        gap: var(--space-4);
    }

    .header-icon-box {
        background-color: rgba(46, 134, 222, 0.1);
        border: 1px solid rgba(46, 134, 222, 0.2);
        padding: var(--space-3);
        border-radius: var(--radius-md);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--accent-blue);
    }

    .header-title-text h1 {
        font-size: calc(var(--font-size-xl) * 1.25);
        font-weight: 700;
        margin: 0 0 var(--space-1) 0;
        letter-spacing: -0.02em;
    }

    .header-title-text p {
        color: var(--gray-400);
        font-size: var(--font-size-sm);
        margin: 0;
    }

    /* -------------------------------------------------------------------------- */
    /* CONFIGURATION DES BOUTONS ACCENTUÉS                                        */
    /* -------------------------------------------------------------------------- */
    .interactive-btn {
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

    .btn-brand-accent { background-color: var(--accent-blue); color: var(--white); box-shadow: 0 4px 12px rgba(46, 134, 222, 0.2); }
    .btn-brand-accent:hover { background-color: #2475c4; transform: translateY(-1px); }

    .btn-filter-submit { background-color: var(--primary-600); color: var(--white); border: 1px solid rgba(255, 255, 255, 0.05); }
    .btn-filter-submit:hover { background-color: #22205a; }

    /* Boutons d'action contextuels du tableau */
    .row-action-btn {
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

    .action-edit { background-color: rgba(46, 134, 222, 0.1); color: var(--accent-blue); border: 1px solid rgba(46, 134, 222, 0.15); }
    .action-edit:hover { background-color: var(--accent-blue); color: var(--white); }

    .action-delete { background-color: rgba(255, 71, 87, 0.1); color: var(--accent-red); border: 1px solid rgba(255, 71, 87, 0.15); }
    .action-delete:hover { background-color: var(--accent-red); color: var(--white); }

    .action-download { background-color: rgba(16, 172, 132, 0.1); color: var(--accent-green); border: 1px solid rgba(16, 172, 132, 0.15); }
    .action-download:hover { background-color: var(--accent-green); color: var(--white); }

    /* -------------------------------------------------------------------------- */
    /* MODULE DE FILTRAGE ET RECHERCHE                                           */
    /* -------------------------------------------------------------------------- */
    .search-filter-card {
        background-color: var(--primary-800);
        border: var(--sidebar-border);
        border-radius: var(--radius-lg);
        padding: var(--space-4);
        margin-bottom: var(--space-5);
        box-shadow: var(--shadow-lg);
    }

    .filter-grid-layout {
        display: grid;
        grid-template-columns: 1fr 280px auto;
        gap: var(--space-3);
        align-items: center;
    }

    .input-icon-placement {
        position: relative;
        width: 100%;
    }

    .input-icon-placement svg {
        position: absolute;
        left: var(--space-3);
        top: 50%;
        transform: translateY(-50%);
        color: var(--gray-400);
        pointer-events: none;
    }

    .custom-input-element, .custom-select-element {
        width: 100%;
        box-sizing: border-box;
        font-family: var(--font-primary);
        font-size: var(--font-size-sm);
        background-color: var(--primary-900);
        border: 1px solid var(--primary-600);
        border-radius: var(--radius-md);
        color: var(--white);
        height: 44px;
        transition: border-color var(--transition-fast), box-shadow var(--transition-fast);
    }

    .custom-input-element {
        padding: var(--space-2) var(--space-3) var(--space-2) calc(var(--space-4) * 2.2);
    }

    .custom-select-element {
        padding: var(--space-2) var(--space-5) var(--space-2) var(--space-3);
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='none' stroke='%23a4b0be' stroke-width='2' viewBox='0 0 24 24'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right var(--space-3) center;
    }

    .custom-input-element:focus, .custom-select-element:focus {
        outline: none;
        border-color: var(--accent-blue);
        box-shadow: 0 0 0 3px rgba(46, 134, 222, 0.15);
    }

    /* -------------------------------------------------------------------------- */
    /* CADRE DU TABLEAU & STRATÉGIE DE RESPONSIVITÉ RETINA                        */
    /* -------------------------------------------------------------------------- */
    .data-table-frame {
        background-color: var(--primary-800);
        border: var(--sidebar-border);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-xl);
        overflow: hidden;
    }

    .table-responsive-layer {
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    .premium-data-table {
        width: 100%;
        border-collapse: collapse;
        text-align: left;
        font-size: var(--font-size-sm);
        white-space: nowrap;
    }

    .premium-data-table th {
        background-color: rgba(0, 0, 0, 0.15);
        color: var(--gray-400);
        font-weight: 600;
        text-transform: uppercase;
        font-size: var(--font-size-xs);
        letter-spacing: 0.05em;
        padding: var(--space-4) var(--space-3);
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    }

    .premium-data-table td {
        padding: var(--space-4) var(--space-3);
        color: var(--gray-100);
        border-bottom: 1px solid rgba(255, 255, 255, 0.03);
        vertical-align: middle;
    }

    .premium-data-table tbody tr:hover td {
        background-color: rgba(255, 255, 255, 0.01);
    }

    /* Cellules typées */
    .cell-title-accent {
        color: var(--white);
        font-weight: 600;
    }

    .cell-meta-gray {
        color: var(--gray-400);
        font-size: var(--font-size-xs);
    }

    /* Badges UI */
    .badge-ui {
        display: inline-flex;
        align-items: center;
        padding: var(--space-1) var(--space-2);
        border-radius: var(--radius-sm);
        font-size: var(--font-size-xs);
        font-weight: 600;
        letter-spacing: 0.02em;
    }
    .badge-primary { background-color: rgba(46, 134, 222, 0.15); color: var(--accent-blue); border: 1px solid rgba(46, 134, 222, 0.2); }
    .badge-neutral { background-color: rgba(255, 255, 255, 0.05); color: var(--gray-300); border: 1px solid rgba(255, 255, 255, 0.05); }

    .action-cluster {
        display: flex;
        gap: var(--space-2);
        align-items: center;
    }

    /* -------------------------------------------------------------------------- */
    /* RÈGLES DE COMPACTAGE POUR SMARTPHONES ET TABLETTES                         */
    /* -------------------------------------------------------------------------- */
    @media (max-width: 992px) {
        .filter-grid-layout {
            grid-template-columns: 1fr 1fr;
        }
        .filter-grid-layout .btn-filter-submit {
            grid-column: span 2;
            width: 100%;
        }
    }

    @media (max-width: 768px) {
        .dashboard-header-hero {
            flex-direction: column;
            align-items: flex-start;
            padding: var(--space-4);
        }

        .dashboard-header-hero .interactive-btn {
            width: 100%;
        }

        .filter-grid-layout {
            grid-template-columns: 1fr;
            gap: var(--space-2);
        }

        .filter-grid-layout .btn-filter-submit {
            grid-column: span 1;
        }
    }
</style>

<div class="dashboard-view-container">

    <!-- En-tête Global de la Vue -->
    <header class="dashboard-header-hero">
        <div class="header-context-flex">
            <div class="header-icon-box">
                <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                    <line x1="16" y1="13" x2="8" y2="13"></line>
                    <line x1="16" y1="17" x2="8" y2="17"></line>
                    <polyline points="10 9 9 9 8 9"></polyline>
                </svg>
            </div>
            <div class="header-title-text">
                <h1>Banque d'Épreuves</h1>
                <p>Consultez, filtrez et gérez les documents d'évaluation archivés.</p>
            </div>
        </div>
        <a href="ajouter_epreuve.php" class="interactive-btn btn-brand-accent">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            Ajouter une épreuve
        </a>
    </header>

    <!-- Zone de Filtrage Asymétrique -->
    <div class="search-filter-card">
        <form method="GET">
            <div class="filter-grid-layout">
                <div class="input-icon-placement">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                           class="custom-input-element" placeholder="Rechercher par titre ou intitulé de matière...">
                </div>
                
                <div>
                    <select name="year" class="custom-select-element">
                        <option value="">Toutes les années académiques</option>
                        <?php foreach($years as $y): ?>
                            <option value="<?= $y['id'] ?>" <?= $year_filter == $y['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($y['label']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" class="interactive-btn btn-filter-submit">Filtrer</button>
            </div>
        </form>
    </div>

    <!-- Conteneur d'affichage des Données Sécurisé -->
    <div class="data-table-frame">
        <div class="table-responsive-layer">
            <table class="premium-data-table">
                <thead>
                    <tr>
                        <th style="width: 50px; text-align: center;">ID</th>
                        <th>Intitulé de l'Épreuve</th>
                        <th>Catégorie</th>
                        <th>Matière</th>
                        <th>Filière</th>
                        <th>Année</th>
                        <th>Niveau</th>
                        <th>Auteur</th>
                        <th>Date d'ajout</th>
                        <th style="text-align: center;">Téléch.</th>
                        <th style="width: 120px; text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if(empty($epreuves)): ?>
                    <tr>
                        <!-- Changement du colspan à 11 pour intégrer la nouvelle colonne sans briser la structure -->
                        <td colspan="11" style="text-align: center; color: var(--gray-400); padding: var(--space-6) 0;">
                            <div style="display: flex; flex-direction: column; align-items: center; gap: var(--space-2); opacity: 0.6;">
                                <svg width="36" height="36" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <line x1="12" y1="8" x2="12" y2="12"></line>
                                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                                </svg>
                                <span>Aucune épreuve répertoriée dans la base de données actuelle.</span>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach($epreuves as $i => $e): ?>
                    <tr>
                        <td style="text-align: center; color: var(--gray-400); font-weight: 600;"><?= $i+1 ?></td>
                        <td class="cell-title-accent"><?= htmlspecialchars($e['titre']) ?></td>
                        <td><span class="badge-ui badge-primary"><?= htmlspecialchars($e['nom_category'] ?? 'N/A') ?></span></td>
                        <td><?= htmlspecialchars($e['nom_matiere'] ?? 'N/A') ?></td>
                        <td><span class="badge-ui badge-neutral"><?= htmlspecialchars($e['nom_filiere'] ?? 'N/A') ?></span></td>
                        <td style="font-variant-numeric: tabular-nums;"><?= htmlspecialchars($e['academic_year'] ?? 'N/A') ?></td>
                        <td><?= htmlspecialchars($e['niveau']) ?></td>
                        <td class="cell-meta-gray"><?= htmlspecialchars(($e['admin_prenom'] ?? '') . ' ' . ($e['admin_nom'] ?? 'Robot')) ?></td>
                        <td class="cell-meta-gray" style="font-variant-numeric: tabular-nums;"><?= date('d/m/Y', strtotime($e['date_ajout'])) ?></td>
                        
                        <!-- Valeur dynamique des téléchargements -->
                        <td style="text-align: center; font-weight: 600; color: var(--accent-blue, #3b82f6);">
                            <?= (int)$e['total_downloads'] ?>
                        </td>

                        <td>
                            <div class="action-cluster">
                                <a href="modifier_epreuve.php?id=<?= $e['id_epreuve'] ?>" class="row-action-btn action-edit" title="Modifier l'épreuve">
                                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                        <path d="M18.5 2.5a2.121 2.121 0 1 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                    </svg>
                                </a>
                                <a href="supprimer_epreuve.php?id=<?= $e['id_epreuve'] ?>" class="row-action-btn action-delete" title="Retirer l'épreuve"
                                   onclick="return confirm('Voulez-vous vraiment supprimer définitivement cette épreuve ?')">
                                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                        <polyline points="3 6 5 6 21 6"></polyline>
                                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                        <line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line>
                                    </svg>
                                </a>
                                <a href="uploads/<?= htmlspecialchars($e['file_path']) ?>" class="row-action-btn action-download" download title="Télécharger le fichier joint">
                                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v4"></path>
                                        <polyline points="7 10 12 15 17 10"></polyline>
                                        <line x1="12" y1="15" x2="12" y2="3"></line>
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