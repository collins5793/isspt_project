<?php
session_start();
require_once "../../includes/db.php";

/* =========================
   STATISTIQUES
========================= */

// Total actualités
$totalActualites = $pdo->query("SELECT COUNT(*) FROM actualites")->fetchColumn();

// Par statut
$statsStatut = $pdo->query("
    SELECT statut, COUNT(*) as total
    FROM actualites
    GROUP BY statut
")->fetchAll(PDO::FETCH_KEY_PAIR);

// Année avec plus d’actualités
$anneeTop = $pdo->query("
    SELECT ay.label, COUNT(a.id) as total
    FROM actualites a
    JOIN academic_years ay ON ay.id = a.id_academic_year
    GROUP BY ay.id
    ORDER BY total DESC
    LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

/* =========================
   LISTE DES ACTUALITÉS
========================= */

$stmt = $pdo->query("
    SELECT a.*, 
           ad.nom AS admin_nom, 
           ad.prenom AS admin_prenom, 
           ay.label AS academic_year
    FROM actualites a
    LEFT JOIN administrateurs ad ON a.id_admin = ad.id_admin
    LEFT JOIN academic_years ay ON a.id_academic_year = ay.id
    ORDER BY a.date_publication DESC
");
$actualites = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Messages session
$success = $_SESSION['success'] ?? null;
$error   = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);

ob_start();
?>

<!-- Conteneur Principal du Dashboard -->
<div class="dashboard-container">

    <!-- En-tête de la page -->
    <div class="page-header">
        <div class="header-text">
            <h2>Gestion des actualités</h2>
            <p class="header-subtitle">Suivez, éditez et publiez les annonces de la plateforme</p>
        </div>
        <a href="actualite_ajouter.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Nouvelle actualité
        </a>
    </div>

    <!-- Alertes de notifications -->
    <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <span><?= htmlspecialchars($success) ?></span>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i> <span><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>

    <!-- Grille des indicateurs (KPI) -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon icon-total">
                <i class="fas fa-newspaper"></i>
            </div>
            <div class="stat-info">
                <h4>Total Général</h4>
                <p><?= $totalActualites ?></p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon icon-publie">
                <i class="fas fa-check"></i>
            </div>
            <div class="stat-info">
                <h4>Publiées</h4>
                <p><?= $statsStatut['publie'] ?? 0 ?></p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon icon-brouillon">
                <i class="fas fa-file-alt"></i>
            </div>
            <div class="stat-info">
                <h4>Brouillons</h4>
                <p><?= $statsStatut['brouillon'] ?? 0 ?></p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon icon-archive">
                <i class="fas fa-archive"></i>
            </div>
            <div class="stat-info">
                <h4>Archivées</h4>
                <p><?= $statsStatut['archive'] ?? 0 ?></p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon icon-active">
                <i class="fas fa-calendar-check"></i>
            </div>
            <div class="stat-info">
                <h4>Année la plus active</h4>
                <p class="text-truncate"><?= htmlspecialchars($anneeTop['label'] ?? '—') ?></p>
            </div>
        </div>
    </div>

    <!-- Table des données -->
    <div class="table-card">
        <?php if ($actualites): ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 60px;">#</th>
                        <th>Titre</th>
                        <th>Année académique</th>
                        <th>Auteur</th>
                        <th>Statut</th>
                        <th>Date de publication</th>
                        <th style="width: 160px; text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($actualites as $i => $act): ?>
                    <tr>
                        <td class="td-index"><?= $i + 1 ?></td>
                        <td class="td-title"><?= htmlspecialchars($act['titre']) ?></td>
                        <td>
                            <span class="year-pill">
                                <i class="far fa-calendar-alt"></i> <?= htmlspecialchars($act['academic_year']) ?>
                            </span>
                        </td>
                        <td class="td-author">
                            <i class="far fa-user-circle"></i> <?= htmlspecialchars($act['admin_prenom'].' '.$act['admin_nom']) ?>
                        </td>
                        <td>
                            <span class="badge badge-<?= $act['statut'] ?>">
                                <span class="badge-dot"></span>
                                <?= ucfirst(htmlspecialchars($act['statut'])) ?>
                            </span>
                        </td>
                        <td class="td-date">
                            <i class="far fa-clock"></i> <?= date('d/m/Y à H:i', strtotime($act['date_publication'])) ?>
                        </td>
                        <td>
                            <div class="actions-group">
                                <a href="actualite_detail.php?id=<?= $act['id'] ?>" class="btn-action btn-info" title="Voir les détails">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="actualite_modifier.php?id=<?= $act['id'] ?>" class="btn-action btn-warning" title="Modifier l'actualité">
                                    <i class="fas fa-edit"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-folder-open"></i>
            <p>Aucune actualité enregistrée pour le moment.</p>
            <a href="actualite_ajouter.php" class="btn btn-primary" style="margin-top: var(--space-3)">Créer une annonce</a>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
/* ==========================================================================
   STYLE DU MODULE ACTUALITÉS - CHARTE GRAPHIQUE COMPATIBLE
   ========================================================================== */

/* Variables locales ou héritées pour la sécurité */
:root {
    --primary-900: #080020;
    --primary-800: #0a0127;
    --primary-700: #120c3a;
    --primary-600: #1a1849;
    --accent-red: #ff4757;
    --accent-blue: #2e86de;
    --accent-green: #10ac84;
    --white: #ffffff;
    --gray-100: #f1f2f6;
    --gray-400: #a4b0be;
}

/* Conteneur principal */
.dashboard-container {
    width: 100%;
    max-width: 1400px;
    margin: 0 auto;
    padding: var(--space-4) 0;
    font-family: var(--font-primary);
    color: var(--gray-100);
}

/* En-tête de page haut de gamme */
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: var(--space-6);
    flex-wrap: wrap;
    gap: var(--space-4);
}

.page-header h2 {
    font-size: 1.6rem;
    font-weight: 700;
    color: var(--white);
    letter-spacing: -0.5px;
    margin-bottom: 4px;
}

.header-subtitle {
    font-size: var(--font-size-sm);
    color: var(--gray-400);
}

/* Grille d'indicateurs de performance (KPI) */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: var(--space-4);
    margin-bottom: var(--space-6);
}

.stat-card {
    background: linear-gradient(135deg, var(--primary-800) 0%, var(--primary-700) 100%);
    border: 1px solid rgba(255, 255, 255, 0.04);
    border-radius: var(--radius-lg);
    padding: var(--space-4);
    display: flex;
    align-items: center;
    gap: var(--space-4);
    box-shadow: var(--shadow-md);
    transition: transform var(--transition-fast), box-shadow var(--transition-fast), border-color var(--transition-fast);
}

.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-lg);
    border-color: rgba(255, 255, 255, 0.1);
}

.stat-icon {
    width: 48px;
    height: 48px;
    border-radius: var(--radius-md);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    flex-shrink: 0;
}

/* Variations subtiles des icônes de statistiques */
.icon-total { background: rgba(255, 255, 255, 0.05); color: var(--white); }
.icon-publie { background: rgba(16, 172, 132, 0.12); color: #1dd1a1; }
.icon-brouillon { background: rgba(234, 181, 7, 0.12); color: #feca57; }
.icon-archive { background: rgba(255, 71, 87, 0.12); color: #ff6b81; }
.icon-active { background: rgba(46, 134, 222, 0.12); color: #54a0ff; }

.stat-info h4 {
    font-size: var(--font-size-xs);
    color: var(--gray-400);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 2px;
}

.stat-info p {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--white);
}

.text-truncate {
    max-width: 140px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    font-size: var(--font-size-sm) !important;
}

/* Card du tableau */
.table-card {
    background-color: var(--primary-800);
    border: 1px solid rgba(255, 255, 255, 0.04);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-xl);
    overflow: hidden;
}

.table-responsive {
    width: 100%;
    overflow-x: auto;
}

/* Style de la table globale */
.table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    text-align: left;
    font-size: var(--font-size-sm);
}

.table th {
    background-color: var(--primary-700);
    color: var(--gray-300);
    font-weight: 600;
    padding: var(--space-4) var(--space-5);
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    text-transform: uppercase;
    font-size: var(--font-size-xs);
    letter-spacing: 0.5px;
}

.table td {
    padding: var(--space-4) var(--space-5);
    border-bottom: 1px solid rgba(255, 255, 255, 0.03);
    color: var(--gray-200);
    vertical-align: middle;
}

.table tbody tr {
    transition: background-color var(--transition-fast);
}

.table tbody tr:hover {
    background-color: rgba(255, 255, 255, 0.02);
}

.table tbody tr:last-child td {
    border-bottom: none;
}

/* Formatage spécifique des cellules */
.td-index {
    color: var(--gray-400);
    font-weight: 600;
}

.td-title {
    color: var(--white) !important;
    font-weight: 600;
    max-width: 300px;
}

.year-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background-color: var(--primary-600);
    padding: 4px 10px;
    border-radius: var(--radius-sm);
    font-size: var(--font-size-xs);
    color: var(--gray-100);
}

.td-author, .td-date {
    color: var(--gray-400);
    white-space: nowrap;
}

.td-author i, .td-date i {
    margin-right: 4px;
}

/* Badges de statuts modernes */
.badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 12px;
    border-radius: var(--radius-full);
    font-size: var(--font-size-xs);
    font-weight: 600;
}

.badge-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
}

.badge-publie { background-color: rgba(16, 172, 132, 0.12); color: #1dd1a1; }
.badge-publie .badge-dot { background-color: #1dd1a1; }

.badge-brouillon { background-color: rgba(254, 202, 87, 0.12); color: #feca57; }
.badge-brouillon .badge-dot { background-color: #feca57; }

.badge-archive { background-color: rgba(164, 176, 190, 0.12); color: #a4b0be; }
.badge-archive .badge-dot { background-color: #a4b0be; }

/* Boutons d'action compacts */
.actions-group {
    display: flex;
    justify-content: center;
    gap: 8px;
}

.btn-action {
    width: 32px;
    height: 32px;
    border-radius: var(--radius-md);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: var(--white);
    text-decoration: none;
    transition: transform var(--transition-fast), background-color var(--transition-fast);
}

.btn-action:hover {
    transform: scale(1.08);
}

.btn-action.btn-info { background-color: rgba(46, 134, 222, 0.2); color: #54a0ff; }
.btn-action.btn-info:hover { background-color: var(--accent-blue); color: var(--white); }

.btn-action.btn-warning { background-color: rgba(254, 202, 87, 0.15); color: #feca57; }
.btn-action.btn-warning:hover { background-color: #ff9f43; color: var(--primary-900); font-weight: bold; }

/* Bouton principal Ajouter */
.btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: var(--space-2) var(--space-4);
    border-radius: var(--radius-md);
    font-weight: 600;
    font-size: var(--font-size-sm);
    text-decoration: none;
    cursor: pointer;
    transition: all var(--transition-fast);
}

.btn-primary {
    background-color: var(--accent-blue);
    color: var(--white);
    border: 1px solid transparent;
    box-shadow: 0 4px 12px rgba(46, 134, 222, 0.2);
}

.btn-primary:hover {
    background-color: #48dbfb;
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(46, 134, 222, 0.3);
}

/* Alertes stylisées */
.alert {
    display: flex;
    align-items: center;
    gap: var(--space-3);
    padding: var(--space-3) var(--space-4);
    border-radius: var(--radius-md);
    margin-bottom: var(--space-5);
    font-size: var(--font-size-sm);
    animation: fadeIn var(--transition-base);
}

.alert-success {
    background-color: rgba(16, 172, 132, 0.1);
    border: 1px solid rgba(16, 172, 132, 0.2);
    color: #1dd1a1;
}

.alert-danger {
    background-color: rgba(255, 71, 87, 0.1);
    border: 1px solid rgba(255, 71, 87, 0.2);
    color: #ff6b81;
}

/* État vide */
.empty-state {
    text-align: center;
    padding: var(--space-6) var(--space-4);
    color: var(--gray-400);
}

.empty-state i {
    font-size: 3rem;
    margin-bottom: var(--space-3);
    opacity: 0.3;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-4px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Responsive Table */
@media (max-width: 768px) {
    .page-header {
        flex-direction: column;
        align-items: flex-start;
    }
    .btn-primary { width: 100%; justify-content: center; }
}
</style>

<?php
$content = ob_get_clean();
include "../layout.php";
?>