<?php
session_start();
require_once '../../includes/db.php'; // connexion PDO

// --- Traitement des filtres et recherche ---
$search = $_GET['search'] ?? '';
$filiere = $_GET['filiere'] ?? '';
$annee = $_GET['annee'] ?? '';
$type = $_GET['type'] ?? '';
$niveau = $_GET['niveau'] ?? ''; 

// Requête SQL optimisée avec jointures et décompte des téléchargements
$query = "
    SELECT e.id_epreuve, e.titre, e.description, e.file_path, 
           e.niveau, e.date_ajout, e.is_public,
           c.nom_category AS type_epreuve,
           y.label AS annee_univ,
           f.nom_filiere,
           COUNT(d.id_download) AS total_downloads
    FROM epreuves e
    LEFT JOIN epreuves_categories c ON e.id_category = c.id_category
    LEFT JOIN academic_years y ON e.academic_year_id = y.id
    LEFT JOIN filieres f ON e.id_filiere = f.id_filiere
    LEFT JOIN epreuves_downloads d ON e.id_epreuve = d.id_epreuve
    WHERE 1=1
";

$params = []; 

if (!empty($search)) {
    $query .= " AND e.titre LIKE :search";
    $params[':search'] = "%$search%";
}
if (!empty($filiere)) {
    $query .= " AND f.nom_filiere = :filiere";
    $params[':filiere'] = $filiere;
}
if (!empty($annee)) {
    $query .= " AND y.label = :annee";
    $params[':annee'] = $annee;
}
if (!empty($type)) {
    $query .= " AND c.nom_category = :type";
    $params[':type'] = $type;
}
if (!empty($niveau)) {
    $query .= " AND e.niveau = :niveau";
    $params[':niveau'] = $niveau;
}

// Le GROUP BY est obligatoire à cause de l'utilisation de COUNT()
$query .= " GROUP BY e.id_epreuve, c.nom_category, y.label, f.nom_filiere";
$query .= " ORDER BY e.date_ajout DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$epreuves = $stmt->fetchAll(PDO::FETCH_ASSOC);

$is_admin = (isset($_SESSION['admin']) && $_SESSION['admin'] === true);

ob_start();
?>


<style>
    /* -------------------------------------------------------------------------- */
    /* STRUCTURE ET EN-TÊTE PREMIUM                                               */
    /* -------------------------------------------------------------------------- */
    .archive-container {
        font-family: var(--font-primary);
        color: var(--white);
        max-width: 1200px;
        margin: 0 auto;
        padding: var(--space-4) var(--space-3);
    }

    .hero-header-card {
        background: linear-gradient(135deg, var(--primary-800) 0%, var(--primary-700) 100%);
        border: var(--sidebar-border);
        border-radius: var(--radius-lg);
        padding: var(--space-5) var(--space-4);
        text-align: center;
        margin-bottom: var(--space-5);
        box-shadow: var(--shadow-md);
        position: relative;
        overflow: hidden;
    }

    .hero-header-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 4px;
        height: 100%;
        background: var(--accent-blue);
    }

    .hero-header-card h1 {
        font-size: calc(var(--font-size-xl) * 1.3);
        font-weight: 700;
        margin: 0 0 var(--space-2) 0;
        letter-spacing: -0.02em;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: var(--space-3);
    }

    .hero-header-card p {
        color: var(--gray-400);
        font-size: var(--font-size-md);
        margin: 0;
        max-width: 600px;
        margin: 0 auto;
    }

    /* BARRE D'ACTIONS ADMIN */
    .admin-actions-bar {
        display: flex;
        align-items: center;
        gap: var(--space-3);
        margin-bottom: var(--space-4);
        flex-wrap: wrap;
    }

    /* -------------------------------------------------------------------------- */
    /* CONFIGURATION DU MOTEUR DE RECHERCHE ET FILTRES GRILLE                      */
    /* -------------------------------------------------------------------------- */
    .filter-panel-card {
        background-color: var(--primary-800);
        border: var(--sidebar-border);
        border-radius: var(--radius-lg);
        padding: var(--space-4);
        margin-bottom: var(--space-5);
        box-shadow: var(--shadow-lg);
    }

    .filter-grid-layout {
        display: grid;
        grid-template-columns: 2fr repeat(4, 1fr) auto;
        gap: var(--space-3);
        align-items: center;
    }

    .search-input-box {
        position: relative;
    }

    .search-input-box svg {
        position: absolute;
        left: var(--space-3);
        top: 50%;
        transform: translateY(-50%);
        color: var(--gray-400);
        pointer-events: none;
    }

    .custom-input-control,
    .custom-select-control {
        width: 100%;
        box-sizing: border-box;
        font-family: var(--font-primary);
        font-size: var(--font-size-sm);
        background-color: var(--primary-900);
        border: 1px solid var(--primary-700);
        border-radius: var(--radius-md);
        color: var(--white);
        padding: var(--space-3) var(--space-3);
        height: 44px;
        transition: border-color var(--transition-fast), box-shadow var(--transition-fast);
    }

    .custom-input-control {
        padding-left: calc(var(--space-4) * 2.2);
    }

    /* Custom Select avec flèche SVG épurée */
    .custom-select-control {
        appearance: none;
        -webkit-appearance: none;
        padding-right: var(--space-5);
        background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='24' height='24' viewBox='0 0 24 24' fill='none' stroke='%23a4b0be' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><polyline points='6 9 12 15 18 9'></polyline></svg>");
        background-repeat: no-repeat;
        background-position: right var(--space-2) center;
        background-size: 14px;
        cursor: pointer;
    }

    .custom-select-control option {
        background-color: var(--primary-900);
        color: var(--white);
    }

    .custom-input-control:focus,
    .custom-select-control:focus {
        outline: none;
        border-color: var(--accent-blue);
        box-shadow: 0 0 0 3px rgba(46, 134, 222, 0.15);
    }

    /* -------------------------------------------------------------------------- */
    /* BOUTONS AVANCÉS (DESIGN SYSTEM)                                            */
    /* -------------------------------------------------------------------------- */
    .btn-premium {
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

    .btn-blue { background-color: var(--accent-blue); color: var(--white); box-shadow: 0 4px 12px rgba(46, 134, 222, 0.2); }
    .btn-blue:hover { background-color: #2475c4; transform: translateY(-1px); }

    .btn-green { background-color: var(--accent-green); color: var(--white); box-shadow: 0 4px 12px rgba(16, 172, 132, 0.2); }
    .btn-green:hover { background-color: #0e9673; transform: translateY(-1px); }

    .btn-outline-gray { background-color: transparent; color: var(--gray-300); border: 1px solid var(--primary-600); }
    .btn-outline-gray:hover { background-color: var(--primary-700); color: var(--white); border-color: var(--gray-400); }

    /* Action Tableaux miniatures */
    .btn-table-action {
        display: inline-flex;
        align-items: center;
        gap: var(--space-2);
        padding: var(--space-2) var(--space-3);
        border-radius: var(--radius-sm);
        font-size: var(--font-size-xs);
        font-weight: 600;
        text-decoration: none;
        transition: all var(--transition-fast);
    }

    .action-download { background-color: rgba(46, 134, 222, 0.1); color: var(--accent-blue); border: 1px solid rgba(46, 134, 222, 0.15); }
    .action-download:hover { background-color: var(--accent-blue); color: var(--white); }

    .action-edit { background-color: rgba(255, 159, 67, 0.1); color: #ff9f43; border: 1px solid rgba(255, 159, 67, 0.15); }
    .action-edit:hover { background-color: #ff9f43; color: var(--primary-900); }

    /* -------------------------------------------------------------------------- */
    /* STRUCTURE ET DONNÉES DU TABLEAU DE BORD                                    */
    /* -------------------------------------------------------------------------- */
    .data-table-card {
        background-color: var(--primary-800);
        border: var(--sidebar-border);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-xl);
        overflow: hidden;
    }

    .table-header-title-area {
        background-color: var(--primary-700);
        padding: var(--space-4);
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    }

    .table-header-title-area h5 {
        margin: 0;
        font-size: var(--font-size-md);
        font-weight: 600;
        color: var(--gray-100);
    }

    .responsive-table-scroller {
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    .premium-data-table {
        width: 100%;
        border-collapse: collapse;
        text-align: left;
        font-size: var(--font-size-sm);
    }

    .premium-data-table th {
        background-color: rgba(0, 0, 0, 0.15);
        color: var(--gray-300);
        font-weight: 600;
        text-transform: uppercase;
        font-size: var(--font-size-xs);
        letter-spacing: 0.03em;
        padding: var(--space-4);
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    }

    .premium-data-table td {
        padding: var(--space-4);
        color: var(--gray-100);
        border-bottom: 1px solid rgba(255, 255, 255, 0.02);
    }

    .premium-data-table tbody tr:hover td {
        background-color: rgba(255, 255, 255, 0.01);
    }

    /* Badges contextuels */
    .tag-badge {
        display: inline-flex;
        padding: 4px var(--space-2);
        border-radius: var(--radius-sm);
        font-size: var(--font-size-xs);
        font-weight: 500;
        background-color: var(--primary-700);
        border: 1px solid rgba(255, 255, 255, 0.05);
    }

    .tag-filiere { border-left: 3px solid var(--accent-blue); color: var(--white); }
    .tag-niveau { border-left: 3px solid var(--accent-green); color: var(--gray-200); }

    /* -------------------------------------------------------------------------- */
    /* PAGINATION LOGIC UI                                                       */
    /* -------------------------------------------------------------------------- */
    .premium-pagination-list {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: var(--space-2);
        list-style: none;
        padding: 0;
        margin: var(--space-5) 0;
    }

    .pagination-link-item {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 36px;
        height: 36px;
        padding: 0 var(--space-2);
        border-radius: var(--radius-md);
        color: var(--gray-300);
        text-decoration: none;
        font-size: var(--font-size-sm);
        font-weight: 500;
        background-color: var(--primary-800);
        border: var(--sidebar-border);
        transition: all var(--transition-fast);
    }

    .pagination-link-item:hover {
        border-color: var(--accent-blue);
        color: var(--white);
    }

    .pagination-link-item.active {
        background-color: var(--accent-blue);
        color: var(--white);
        border-color: var(--accent-blue);
        box-shadow: 0 2px 8px rgba(46, 134, 222, 0.3);
    }

    .pagination-link-item.disabled {
        opacity: 0.3;
        pointer-events: none;
    }

    /* -------------------------------------------------------------------------- */
    /* GRILLE ET RESPONSIVITÉ TOUS ÉCRANS                                        */
    /* -------------------------------------------------------------------------- */
    @media (max-width: 1100px) {
        .filter-grid-layout {
            grid-template-columns: 1fr 1fr;
        }
        .filter-grid-layout .search-input-box {
            grid-column: span 2;
        }
        .filter-grid-layout .btn-premium {
            grid-column: span 2;
            width: 100%;
        }
    }

    @media (max-width: 576px) {
        .filter-grid-layout {
            grid-template-columns: 1fr;
        }
        .filter-grid-layout .search-input-box,
        .filter-grid-layout .btn-premium {
            grid-column: span 1;
        }
        .admin-actions-bar .btn-premium {
            width: 100%;
        }
    }
</style>
<div class="archive-container">

    <!-- En-tête Principal de l'application -->
    <header class="hero-header-card">
        <h1>
            <svg width="28" height="28" fill="none" stroke="var(--accent-blue)" stroke-width="2.5" viewBox="0 0 24 24">
                <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
            </svg>
            Recueil d’Épreuves Universitaires
        </h1>
        <p>Consultez, recherchez et téléchargez les archives de compositions et d'examens de votre département.</p>
    </header>

    <!-- Rendu Conditionnel de la zone Administrateur -->
    <div class="admin-actions-bar">
        <a href="ajouter_epreuve.php" class="btn-premium btn-green">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            Ajouter une épreuve
        </a>
        <a href="gerer_epreuves.php" class="btn-premium btn-outline-gray">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="3"></circle>
                <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
            </svg>
            Gérer la base de données
        </a>
    </div>

    <!-- Moteur de Filtres Multi-critères -->
    <div class="filter-panel-card">
        <form method="GET">
            <div class="filter-grid-layout">
                
                <!-- Recherche libre par texte -->
                <div class="search-input-box">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" class="custom-input-control" placeholder="Rechercher un intitulé, un mot...">
                </div>

                <!-- Sélecteur : Filières -->
                <div>
                    <select name="filiere" class="custom-select-control">
                        <option value="">Filière</option>
                        <option value="SIL" <?= $filiere === "SIL" ? "selected" : "" ?>>SIL</option>
                        <option value="RIT" <?= $filiere === "RIT" ? "selected" : "" ?>>RIT</option>
                        <option value="GIT" <?= $filiere === "GIT" ? "selected" : "" ?>>GIT</option>
                    </select>
                </div>

                <!-- Sélecteur : Année Universitaire -->
                <div>
                    <select name="annee" class="custom-select-control">
                        <option value="">Année</option>
                        <option value="2024-2025" <?= $annee === "2024-2025" ? "selected" : "" ?>>2024-2025</option>
                        <option value="2023-2024" <?= $annee === "2023-2024" ? "selected" : "" ?>>2023-2024</option>
                        <option value="2022-2023" <?= $annee === "2022-2023" ? "selected" : "" ?>>2022-2023</option>
                    </select>
                </div>

                <!-- Sélecteur : Catégorie d'épreuve -->
                <div>
                    <select name="type" class="custom-select-control">
                        <option value="">Type</option>
                        <option value="Examen national" <?= $type === "Examen national" ? "selected" : "" ?>>Examen national</option>
                        <option value="Partiel" <?= $type === "Partiel" ? "selected" : "" ?>>Partiel</option>
                        <option value="TP" <?= $type === "TP" ? "selected" : "" ?>>TP</option>
                        <option value="Devoir surveillé" <?= $type === "Devoir surveillé" ? "selected" : "" ?>>Devoir surveillé</option>
                    </select>
                </div>

                <!-- Sélecteur : Niveau de formation -->
                <div>
                    <select name="niveau" class="custom-select-control">
                        <option value="">Niveau</option>
                        <option value="1ère année" <?= $niveau === "1ère année" ? "selected" : "" ?>>1ère année</option>
                        <option value="2ème année" <?= $niveau === "2ème année" ? "selected" : "" ?>>2ème année</option>
                        <option value="3ème année" <?= $niveau === "3ème année" ? "selected" : "" ?>>3ème année</option>
                        <option value="autre" <?= $niveau === "autre" ? "selected" : "" ?>>Autre</option>
                    </select>
                </div>

                <!-- Soumission -->
                <button type="submit" class="btn-premium btn-blue">Filtrer les résultats</button>
            </div>
        </form>
    </div>

    <!-- Panneau de Listing des épreuves -->
    <div class="data-table-card">
        <div class="table-header-title-area">
            <h5>Épreuves disponibles dans le recueil informatique</h5>
        </div>
        <div class="responsive-table-scroller">
            <table class="premium-data-table">
                <thead>
                    <tr>
                        <th style="width: 50px; text-align: center;">#</th>
                        <th>Intitulé de l'épreuve</th>
                        <th>Filière</th>
                        <th>Année académique</th>
                        <th>Type d'évaluation</th>
                        <th>Niveau</th>
                        <th style="text-align: center;">Téléchargements</th>
                        <th>Fichier</th>
                        <?php if ($is_admin): ?>
                            <th style="width: 100px; text-align: center;">Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($epreuves)): ?>
                        <?php foreach ($epreuves as $index => $row): ?>
                            <tr>
                                <td style="text-align: center; color: var(--gray-400); font-weight: 600;"><?= $index + 1 ?></td>
                                <td style="font-weight: 600; color: var(--white);"><?= htmlspecialchars($row['titre']) ?></td>
                                <td><span class="tag-badge tag-filiere"><?= htmlspecialchars($row['nom_filiere'] ?? 'N/A') ?></span></td>
                                <td style="color: var(--gray-300);"><?= htmlspecialchars($row['annee_univ'] ?? 'N/A') ?></td>
                                <td><span style="color: var(--accent-blue); font-weight: 500;"><?= htmlspecialchars($row['type_epreuve'] ?? 'Général') ?></span></td>
                                <td><span class="tag-badge tag-niveau"><?= htmlspecialchars($row['niveau']) ?></span></td>
                                <!-- Affichage du nombre de téléchargements -->
                                <td style="text-align: center; font-weight: 600; color: var(--gray-300);">
                                    <?= (int)$row['total_downloads'] ?>
                                </td>
                                <td>
                                    <a href="../uploads/<?= htmlspecialchars($row['file_path']) ?>" class="btn-table-action action-download" download>
                                        <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                            <polyline points="7 10 12 15 17 10"></polyline>
                                            <line x1="12" y1="15" x2="12" y2="3"></line>
                                        </svg>
                                        Télécharger
                                    </a>
                                </td>
                                <?php if ($is_admin): ?>
                                    <td style="text-align: center;">
                                        <a href="modifier_epreuve.php?id=<?= $row['id_epreuve'] ?>" class="btn-table-action action-edit">
                                            <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                                <path d="M18.5 2.5a2.121 2.121 0 1 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                            </svg>
                                            Modifier
                                        </a>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="<?= $is_admin ? 9 : 8 ?>" style="text-align: center; color: var(--gray-400); padding: var(--space-6) 0;">
                                <div style="display: flex; flex-direction: column; align-items: center; gap: var(--space-2);">
                                    <svg width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                        <circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                                    </svg>
                                    <span>Aucune archive d'épreuve ne correspond aux filtres appliqués.</span>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination Graphique Premium -->
    <nav aria-label="Navigation des pages">
        <ul class="premium-pagination-list">
            <li><a class="pagination-link-item disabled" href="#">Précédent</a></li>
            <li><a class="pagination-link-item active" href="#">1</a></li>
            <li><a class="pagination-link-item" href="#">2</a></li>
            <li><a class="pagination-link-item" href="#">Suivant</a></li>
        </ul>
    </nav>

    <!-- Pied de page contextuel de la vue -->
    <footer style="text-align: center; margin-top: var(--space-6); color: var(--gray-400); font-size: var(--font-size-xs);">
        <p>&copy; <?= date('Y') ?> ISSPT - Tous droits réservés | <a href="../index.php" style="color: var(--accent-blue); text-decoration: none;">Retour à l'accueil</a></p>
    </footer>

</div>

<?php
$content = ob_get_clean();
include '../layout.php';
?>