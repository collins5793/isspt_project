<?php
session_start();
require_once "../../includes/db.php";

// Vérifier l'authentification de l'admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// -------------------------------
// 1. Récupération des années académiques
// -------------------------------
$years = $pdo->query("
    SELECT id, label, is_current 
    FROM academic_years 
    ORDER BY start_date DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Déterminer l'année active par défaut
$defaultYear = null;
foreach ($years as $y) {
    if ($y['is_current']) {
        $defaultYear = $y['id'];
        break;
    }
}
if (!$defaultYear && !empty($years)) {
    $defaultYear = $years[0]['id']; 
}

// Année sélectionnée via GET
$selectedYear = isset($_GET['year']) ? intval($_GET['year']) : $defaultYear;

// -------------------------------
// 2. Récupération des événements filtrés avec état du ticket
// -------------------------------
$stmt = $pdo->prepare("
    SELECT e.*, 
           a.nom AS admin_nom, 
           a.prenom AS admin_prenom, 
           ay.label AS academic_year,
           tl.id_layout AS has_active_layout
    FROM evenements e
    LEFT JOIN administrateurs a ON e.cree_par = a.id_admin
    LEFT JOIN academic_years ay ON e.academic_year_id = ay.id
    LEFT JOIN ticket_layouts tl ON e.id_evenement = tl.event_id
    WHERE e.academic_year_id = ?
    ORDER BY e.event_start DESC
");

$stmt->execute([$selectedYear]);
$evenements = $stmt->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>

<!-- ==========================================================================
     STYLE COMPLÉMENTAIRE DÉDIÉ À LA PAGE ÉVÉNEMENTS
     (Utilise strictement le système de variables :root fourni)
     ========================================================================== -->
<style>
    /* Structure générale & conteneur principal */
    .events-page-container {
        display: flex;
        flex-direction: column;
        gap: var(--space-5);
        animation: fadeIn 400ms ease-out;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* Header de la page */
    .events-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: var(--space-4);
        padding-bottom: var(--space-4);
        border-bottom: 1px solid rgba(255, 255, 255, 0.06);
    }

    .events-header h2 {
        font-size: var(--font-size-xl);
        font-weight: 700;
        color: var(--white);
        display: flex;
        align-items: center;
        gap: var(--space-2);
    }

    /* Zone de filtrage */
    .filter-section {
        background: linear-gradient(145deg, var(--primary-800), var(--primary-700));
        border: 1px solid rgba(255, 255, 255, 0.04);
        border-radius: var(--radius-lg);
        padding: var(--space-4);
        box-shadow: var(--shadow-sm);
    }

    .filter-form-group {
        display: flex;
        align-items: center;
        gap: var(--space-3);
    }

    .filter-label {
        font-size: var(--font-size-sm);
        color: var(--gray-300);
        font-weight: 500;
        white-space: nowrap;
    }

    .custom-select {
        background-color: var(--primary-900);
        color: var(--white);
        border: 1px solid rgba(255, 255, 255, 0.1);
        padding: var(--space-2) var(--space-4);
        border-radius: var(--radius-md);
        font-family: var(--font-primary);
        font-size: var(--font-size-sm);
        outline: none;
        cursor: pointer;
        transition: border-color var(--transition-fast);
        min-width: 240px;
    }

    .custom-select:focus {
        border-color: var(--accent-blue);
        box-shadow: 0 0 0 2px rgba(46, 134, 222, 0.2);
    }

    /* Grille Responsive des événements */
    .events-responsive-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: var(--space-5);
    }

    /* Carte Événement Premium Dark */
    .premium-event-card {
        background: linear-gradient(180deg, var(--primary-800) 0%, rgba(10, 1, 39, 0.8) 100%);
        border: 1px solid rgba(255, 255, 255, 0.05);
        border-radius: var(--radius-lg);
        padding: var(--space-5);
        box-shadow: var(--shadow-md);
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        transition: transform var(--transition-base), box-shadow var(--transition-base), border-color var(--transition-base);
        position: relative;
        overflow: hidden;
    }

    .premium-event-card:hover {
        transform: translateY(-4px);
        border-color: rgba(46, 134, 222, 0.3);
        box-shadow: var(--shadow-xl);
    }

    /* Badges de statuts */
    .badges-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: var(--space-2);
        margin-bottom: var(--space-3);
    }

    .type-badge {
        background: rgba(46, 134, 222, 0.15);
        color: var(--accent-blue);
        border: 1px solid rgba(46, 134, 222, 0.3);
        padding: 0.25rem 0.6rem;
        font-size: var(--font-size-xs);
        font-weight: 700;
        border-radius: var(--radius-sm);
        text-transform: uppercase;
    }

    .ticket-badge {
        font-size: var(--font-size-xs);
        font-weight: 600;
        padding: 0.25rem 0.6rem;
        border-radius: var(--radius-full);
    }

    .badge-ready { background: rgba(16, 172, 132, 0.15); color: var(--accent-green); border: 1px solid rgba(16, 172, 132, 0.2); }
    .badge-warn { background: rgba(255, 71, 87, 0.15); color: var(--accent-red); border: 1px solid rgba(255, 71, 87, 0.2); }
    .badge-free { background: rgba(164, 176, 190, 0.15); color: var(--gray-300); }

    /* Typographie interne des cartes */
    .event-title {
        font-size: var(--font-size-lg);
        font-weight: 600;
        color: var(--white);
        margin-bottom: var(--space-2);
        line-height: 1.4;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .event-desc {
        font-size: var(--font-size-sm);
        color: var(--gray-400);
        margin-bottom: var(--space-4);
        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
        overflow: hidden;
        min-height: 4.2em;
    }

    /* Métadonnées */
    .event-meta-list {
        display: flex;
        flex-direction: column;
        gap: var(--space-2);
        border-top: 1px solid rgba(255, 255, 255, 0.05);
        padding-top: var(--space-3);
        margin-bottom: var(--space-4);
    }

    .meta-item {
        display: flex;
        align-items: center;
        gap: var(--space-2);
        font-size: var(--font-size-sm);
        color: var(--gray-200);
    }

    .meta-item icon {
        width: 18px;
        display: inline-block;
        text-align: center;
    }

    .meta-author {
        font-size: var(--font-size-xs);
        color: var(--gray-400);
        border-top: 1px solid rgba(255, 255, 255, 0.03);
        padding-top: var(--space-2);
        margin-top: var(--space-1);
    }

    /* Actions bouton */
    .card-actions-wrapper {
        display: flex;
        gap: var(--space-2);
        margin-top: auto;
    }

    .card-actions-wrapper .btn-action {
        flex: 1;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: var(--space-1);
        padding: 0.55rem var(--space-2);
        font-size: var(--font-size-sm);
        font-weight: 600;
        border-radius: var(--radius-md);
        text-decoration: none;
        cursor: pointer;
        transition: all var(--transition-fast);
        border: none;
        font-family: var(--font-primary);
    }

    .btn-action-view { background: var(--primary-600); color: var(--gray-100); border: 1px solid rgba(255, 255, 255, 0.05); }
    .btn-action-view:hover { background: var(--primary-700); color: var(--white); }

    .btn-action-edit { background: var(--accent-blue); color: var(--white); }
    .btn-action-edit:hover { filter: brightness(1.1); box-shadow: 0 0 10px rgba(46, 134, 222, 0.3); }

    .btn-action-studio { background: linear-gradient(135deg, #f97316 0%, #dd5e05 100%); color: var(--white); }
    .btn-action-studio:hover { filter: brightness(1.1); box-shadow: 0 0 10px rgba(249, 115, 22, 0.3); }

    .btn-action-delete { background: rgba(255, 71, 87, 0.1); color: var(--accent-red); border: 1px solid rgba(255, 71, 87, 0.2); max-width: 45px; }
    .btn-action-delete:hover { background: var(--accent-red); color: var(--white); }

    /* État vide */
    .empty-state-card {
        grid-column: 1 / -1;
        background: linear-gradient(145deg, var(--primary-800), var(--primary-700));
        border: 1px solid rgba(255, 255, 255, 0.05);
        border-radius: var(--radius-lg);
        padding: var(--space-6);
        text-align: center;
        color: var(--gray-400);
        box-shadow: var(--shadow-md);
    }

    .empty-icon {
        font-size: 3rem;
        display: block;
        margin-bottom: var(--space-3);
    }

    /* Base boutons d'en-tête global */
    .btn-header-add {
        background-color: var(--accent-green);
        color: var(--white);
        padding: 0.65rem 1.25rem;
        font-weight: 600;
        font-size: var(--font-size-sm);
        border-radius: var(--radius-md);
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: var(--space-2);
        transition: all var(--transition-fast);
        border: none;
    }
    .btn-header-add:hover {
        filter: brightness(1.1);
        box-shadow: 0 0 12px rgba(16, 172, 132, 0.4);
    }

    /* ==========================================================================
       MEDIA QUERIES - RESPONSIVITÉ SANS FAILLE
       ========================================================================== */
    @media (max-width: 768px) {
        .events-header {
            flex-direction: column;
            align-items: flex-start;
            gap: var(--space-3);
        }

        .btn-header-add {
            width: 100%;
            justify-content: center;
        }

        .filter-form-group {
            flex-direction: column;
            align-items: flex-start;
            gap: var(--space-2);
            width: 100%;
        }

        .custom-select {
            width: 100%;
        }
    }

    @media (max-width: 480px) {
        .events-responsive-grid {
            grid-template-columns: 1fr;
        }
        
        .card-actions-wrapper {
            flex-wrap: wrap;
        }

        .btn-action-delete {
            max-width: none;
            width: 100%;
            order: 3; /* Aligne la suppression en dessous sur très petit écran */
        }
    }
</style>

<div class="events-page-container">

    <!-- 📅 EN-TÊTE DE LA PAGE -->
    <div class="events-header">
        <h2>📅 Liste des Événements</h2>
        <a href="ajouter_evenement.php" class="btn-header-add">
            <span>+</span> Ajouter un événement
        </a>
    </div>

    <!-- 🔍 BARRE DE FILTRE -->
    <div class="filter-section">
        <form method="GET" id="filterForm">
            <div class="filter-form-group">
                <label for="year" class="filter-label">Année Académique :</label>
                <select name="year" id="year" class="custom-select" onchange="document.getElementById('filterForm').submit();">
                    <?php foreach ($years as $year): ?>
                        <option value="<?= $year['id'] ?>" <?= ($year['id'] == $selectedYear) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($year['label']) ?> <?= $year['is_current'] ? ' (En cours)' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>

    <!-- 🎴 GRILLE DES ÉVÉNEMENTS -->
    <div class="events-responsive-grid">
        <?php if (!empty($evenements)): ?>
            <?php foreach ($evenements as $event): 
                $admin_complet = trim(($event['admin_prenom'] ?? '') . ' ' . ($event['admin_nom'] ?? ''));
                $createur = !empty($admin_complet) ? $admin_complet : "Système / Inconnu";
            ?>
                <div class="premium-event-card">
                    <!-- Section Contenu supérieur -->
                    <div>
                        <!-- En-tête interne de la carte -->
                        <div class="badges-row">
                            <span class="type-badge">
                                <?= htmlspecialchars($event['type_evenement']) ?>
                            </span>
                            
                            <!-- Statut Billetterie / Layout -->
                            <?php if ($event['has_ticket'] == 1): ?>
                                <?php if ($event['has_active_layout']): ?>
                                    <span class="ticket-badge badge-ready">🎫 Ticket Prêt</span>
                                <?php else: ?>
                                    <span class="ticket-badge badge-warn">⚠️ Studio requis</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="ticket-badge badge-free">Entrée Libre</span>
                            <?php endif; ?>
                        </div>

                        <!-- Titre de l'événement -->
                        <h3 class="event-title" title="<?= htmlspecialchars($event['nom_evenement']) ?>">
                            <?= htmlspecialchars($event['nom_evenement']) ?>
                        </h3>
                        
                        <!-- Description -->
                        <div class="event-desc">
                            <?= !empty($event['description']) ? htmlspecialchars($event['description']) : '<i>Aucune description fournie.</i>' ?>
                        </div>

                        <!-- Métadonnées détaillées -->
                        <div class="event-meta-list">
                            <div class="meta-item">
                                <icon>🕒</icon> 
                                <span><strong>Date :</strong> <?= date('d/m/Y à H:i', strtotime($event['event_start'])) ?></span>
                            </div>
                            <div class="meta-item">
                                <icon>📍</icon> 
                                <span class="truncate"><strong>Lieu :</strong> <?= htmlspecialchars($event['lieu'] ?? 'Non précisé') ?></span>
                            </div>
                            <div class="meta-item">
                                <icon>💰</icon> 
                                <span><strong>Prix :</strong> <?= number_format($event['prix_ticket'], 0, '.', ' ') ?> FCFA</span>
                            </div>
                            <div class="meta-item meta-author">
                                <icon>👤</icon>
                                <span>Par : <?= htmlspecialchars($createur) ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- 🛠️ ACTIONS DISPONIBLES (Toujours calées en bas de carte) -->
                    <div class="card-actions-wrapper">
                        <a href="evenement_detail.php?id=<?= $event['id_evenement'] ?>" class="btn-action btn-action-view" title="Détails complets">
                            👁️ <span class="sm-hidden">Détails</span>
                        </a>
                        
                        <!-- Routage intelligent vers le Studio ou la modification standard -->
                        <?php if ($event['has_ticket'] == 1 && !$event['has_active_layout']): ?>
                            <a href="ajouter_layout.php?id=<?= $event['id_evenement'] ?>" class="btn-action btn-action-studio" title="Configurer le design du ticket">
                                🎨 Studio
                            </a>
                        <?php else: ?>
                            <a href="modifier_evenement.php?id=<?= $event['id_evenement'] ?>" class="btn-action btn-action-edit" title="Modifier l'événement">
                                ✏️ Modifier
                            </a>
                        <?php endif; ?>

                        <a href="evenement_supprimer.php?id=<?= $event['id_evenement'] ?>" class="btn-action btn-action-delete"
                           onclick="return confirm('Êtes-vous certain de vouloir supprimer définitivement cet événement ainsi que ses dépendances ?');" title="Supprimer définitivement">
                            🗑️
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <!-- État vide si aucune données -->
            <div class="empty-state-card">
                <span class="empty-icon">📂</span>
                <p>Aucun événement enregistré ou planifié pour l'année académique sélectionnée.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
$content = ob_get_clean();
include "../layout.php";
?>