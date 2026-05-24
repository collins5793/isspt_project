<?php
session_start();
require_once "../../includes/db.php";

// -------------------------------
//  Récupération des années académiques
// -------------------------------
$years = $pdo->query("
    SELECT id, label, is_current 
    FROM academic_years 
    ORDER BY start_date DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Déterminer l'année active
$defaultYear = null;
foreach ($years as $y) {
    if ($y['is_current']) {
        $defaultYear = $y['id'];
        break;
    }
}
if (!$defaultYear && !empty($years)) {
    $defaultYear = $years[0]['id']; // fallback
}

// Année sélectionnée via GET
$selectedYear = isset($_GET['year']) ? intval($_GET['year']) : $defaultYear;

// -------------------------------
//  Récupération des activités filtrées
// -------------------------------
$stmt = $pdo->prepare("
    SELECT act.*, 
           a.nom AS admin_nom, 
           a.prenom AS admin_prenom, 
           ay.label AS academic_year
    FROM activites act
    LEFT JOIN administrateurs a ON act.cree_par = a.id_admin
    LEFT JOIN academic_years ay ON act.academic_year_id = ay.id
    WHERE act.academic_year_id = ?
    ORDER BY act.date_creation DESC
");

$stmt->execute([$selectedYear]);
$activites = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Contenu à injecter dans le layout
ob_start();
?>

<style>
    /* ==========================================================================
       Design System & Tokens (Dark Premium)
       ========================================================================== */
    :root {
        /* Intégration stricte de tes variables */
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

        /* Tokens de composition ajoutés pour le raffinement du layout */
        --bg-main: #060018;
        --card-bg: #110933;
        --card-border: rgba(255, 255, 255, 0.06);
        --text-muted: #8a94a6;
    }

    /* Conteneur principal */
    .activities-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: var(--space-4);
        box-sizing: border-box;
    }

    /* ==========================================================================
       Header & Zone de Contrôles
       ========================================================================== */
    .page-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: var(--space-4);
        margin-bottom: var(--space-5);
        padding-bottom: var(--space-4);
        border-bottom: 1px solid var(--card-border);
    }

    .page-header h2 {
        font-size: 1.75rem;
        font-weight: 700;
        color: var(--white);
        margin: 0;
        letter-spacing: -0.02em;
    }

    /* Filtre stylisé */
    .filter-zone {
        background: var(--card-bg);
        border: 1px solid var(--card-border);
        border-radius: var(--radius-lg);
        padding: var(--space-4);
        margin-bottom: var(--space-6);
        box-shadow: var(--shadow-sm);
    }

    .filter-form {
        display: flex;
        align-items: center;
        gap: var(--space-3);
    }

    .filter-form label {
        font-size: var(--font-size-sm);
        font-weight: 600;
        color: var(--gray-300);
        white-space: nowrap;
    }

    .filter-form select {
        background-color: var(--primary-900);
        color: var(--white);
        border: 1px solid var(--card-border);
        border-radius: var(--radius-md);
        padding: var(--space-2) var(--space-4);
        font-family: var(--font-primary);
        font-size: var(--font-size-sm);
        cursor: pointer;
        transition: border-color var(--transition-fast);
        min-width: 220px;
        outline: none;
    }

    .filter-form select:focus {
        border-color: var(--accent-blue);
        box-shadow: 0 0 0 2px rgba(46, 134, 222, 0.2);
    }

    /* ==========================================================================
       Grille et Cartes d'Activités
       ========================================================================== */
    .events-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        gap: var(--space-5);
    }

    .event-card {
        background: var(--card-bg);
        border: 1px solid var(--card-border);
        border-radius: var(--radius-lg);
        padding: var(--space-5);
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        box-shadow: var(--shadow-md);
        transition: transform var(--transition-base), box-shadow var(--transition-base), border-color var(--transition-base);
        position: relative;
        overflow: hidden;
    }

    .event-card:hover {
        transform: translateY(-4px);
        box-shadow: var(--shadow-xl);
        border-color: rgba(46, 134, 222, 0.3);
    }

    /* Indicateur esthétique sur le côté gauche de la carte */
    .event-card::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 4px;
        background: linear-gradient(180deg, var(--accent-blue) 0%, var(--primary-600) 100%);
        opacity: 0.7;
    }

    .event-title {
        font-size: var(--font-size-lg);
        font-weight: 600;
        color: var(--white);
        margin-top: 0;
        margin-bottom: var(--space-4);
        line-height: 1.4;
    }

    .event-details {
        display: flex;
        flex-direction: column;
        gap: var(--space-3);
        margin-bottom: var(--space-5);
    }

    .detail-item {
        margin: 0;
        font-size: var(--font-size-sm);
        line-height: 1.6;
        color: var(--gray-200);
    }

    .detail-item strong {
        color: var(--gray-400);
        font-weight: 600;
        display: block;
        font-size: var(--font-size-xs);
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 2px;
    }

    .detail-item span {
        display: block;
        background: rgba(0, 0, 0, 0.15);
        padding: var(--space-2) var(--space-3);
        border-radius: var(--radius-sm);
        border-left: 2px solid var(--primary-600);
    }

    /* Mises en valeur spécifiques */
    .detail-inline {
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: rgba(255, 255, 255, 0.02);
        padding: var(--space-2) var(--space-3);
        border-radius: var(--radius-sm);
        font-size: var(--font-size-xs);
    }

    .detail-inline strong {
        margin-bottom: 0;
    }

    /* ==========================================================================
       Boutons & Actions
       ========================================================================== */
    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-family: var(--font-primary);
        font-size: var(--font-size-sm);
        font-weight: 600;
        padding: var(--space-3) var(--space-4);
        border-radius: var(--radius-md);
        border: 1px solid transparent;
        cursor: pointer;
        text-decoration: none;
        transition: all var(--transition-fast);
        gap: var(--space-2);
        text-align: center;
    }

    .btn-primary {
        background-color: var(--accent-blue);
        color: var(--white);
    }
    .btn-primary:hover {
        background-color: #2475c4;
        box-shadow: 0 4px 12px rgba(46, 134, 222, 0.3);
    }

    .event-actions {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: var(--space-2);
        margin-top: auto;
    }

    /* Le bouton "Voir détails" prend toute la largeur au-dessus des deux autres boutons */
    .btn-info {
        grid-column: span 2;
        background-color: var(--primary-600);
        color: var(--white);
        border: 1px solid rgba(255, 255, 255, 0.08);
    }
    .btn-info:hover {
        background-color: var(--primary-700);
        border-color: var(--accent-blue);
    }

    .btn-warning {
        background-color: transparent;
        color: var(--gray-200);
        border: 1px solid var(--primary-600);
    }
    .btn-warning:hover {
        background-color: rgba(255, 255, 255, 0.05);
        color: var(--white);
    }

    .btn-danger {
        background-color: transparent;
        color: var(--accent-red);
        border: 1px solid rgba(255, 71, 87, 0.2);
    }
    .btn-danger:hover {
        background-color: var(--accent-red);
        color: var(--white);
        box-shadow: 0 4px 12px rgba(255, 71, 87, 0.2);
    }

    /* État vide */
    .empty-state {
        grid-column: 1 / -1;
        text-align: center;
        padding: var(--space-6) var(--space-4);
        background: var(--card-bg);
        border: 1px dashed var(--card-border);
        border-radius: var(--radius-lg);
        color: var(--text-muted);
    }

    .empty-state p {
        margin: 0;
        font-size: var(--font-size-md);
    }

    /* ==========================================================================
       Responsivité Totale sans défauts
       ========================================================================== */
    @media (max-width: 768px) {
        .page-header {
            flex-direction: column;
            align-items: flex-start;
            gap: var(--space-3);
        }

        .page-header .actions, 
        .page-header .btn {
            width: 100%;
        }

        .filter-form {
            flex-direction: column;
            align-items: flex-start;
            gap: var(--space-2);
        }

        .filter-form select {
            width: 100%;
        }

        .events-grid {
            grid-template-columns: 1fr; /* Passage sur une seule colonne */
            gap: var(--space-4);
        }
    }

    @media (max-width: 480px) {
        .event-actions {
            grid-template-columns: 1fr; /* Empilement complet des boutons d'action */
        }
        .btn-info {
            grid-column: span 1;
        }
    }
</style>

<div class="activities-container">
    <!-- ENTÊTE DE LA PAGE -->
    <div class="page-header">
        <h2>Activités</h2>
        <div class="actions">
            <a href="ajouter_activite.php" class="btn btn-primary">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                Ajouter une activité
            </a>
        </div>
    </div>

    <!-- ZONE FILTRE PAR ANNÉE ACADÉMIQUE -->
    <div class="filter-zone">
        <form method="GET" class="filter-form">
            <label for="year">Année académique</label>
            <select name="year" id="year" onchange="this.form.submit()">
                <?php foreach ($years as $year): ?>
                    <option value="<?= $year['id'] ?>" <?= ($year['id'] == $selectedYear) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($year['label']) ?>
                        <?= $year['is_current'] ? ' (en cours)' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <!-- GRILLE D'AFFICHAGE DES ACTIVITÉS -->
    <div class="events-grid">
        <?php if (!empty($activites)): ?>
            <?php foreach ($activites as $act): ?>
                <div class="event-card">
                    <div>
                        <h3 class="event-title"><?= htmlspecialchars($act['nom_activite']) ?></h3>
                        
                        <div class="event-details">
                            <?php if (!empty($act['description'])): ?>
                                <div class="detail-item">
                                    strong>Description</strong>
                                    <span><?= nl2br(htmlspecialchars($act['description'])) ?></span>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($act['conditions'])): ?>
                                <div class="detail-item">
                                    strong>Conditions d'accès</strong>
                                    <span><?= nl2br(htmlspecialchars($act['conditions'])) ?></span>
                                </div>
                            <?php endif; ?>

                            <div class="detail-inline">
                                strong>Année Académique</strong>
                                <span style="background:transparent; padding:0; border:none; color: var(--accent-blue); font-weight:600;">
                                    <?= htmlspecialchars($act['academic_year']) ?>
                                </span>
                            </div>

                            <div class="detail-inline">
                                strong>Créateur</strong>
                                <span style="background:transparent; padding:0; border:none; color: var(--gray-300);">
                                    <?= htmlspecialchars($act['admin_prenom'] . ' ' . $act['admin_nom']) ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- BOUTONS D'ACTION CONFIGURÉS EN COMPOSITION GRILLE -->
                    <div class="event-actions">
                        <a href="activite_detail.php?id=<?= $act['id_activite'] ?>" class="btn btn-info">Voir les détails</a>
                        <a href="modifier_activite.php?id=<?= $act['id_activite'] ?>" class="btn btn-warning">Modifier</a>
                        <a href="activite_supprimer.php?id=<?= $act['id_activite'] ?>" class="btn btn-danger"
                           onclick="return confirm('Voulez-vous vraiment supprimer cette activité définitivement ?');">
                            Supprimer
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <p>Aucune activité répertoriée pour cette année académique.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
$content = ob_get_clean();
include "../layout.php";
?>