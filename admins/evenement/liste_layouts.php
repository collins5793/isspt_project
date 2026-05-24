<?php
session_start();
require_once '../../includes/db.php';

// Vérifier admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// Gestion de la suppression d'un layout
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id_layout = intval($_GET['id']);
    try {
        $pdo->beginTransaction();

        // 1. Désassocier le layout dans la table evenements pour éviter la rupture de contrainte clé étrangère
        $updateEvent = $pdo->prepare("UPDATE evenements SET layout_id = NULL WHERE layout_id = ?");
        $updateEvent->execute([$id_layout]);

        // 2. Supprimer le layout
        $deleteLayout = $pdo->prepare("DELETE FROM ticket_layouts WHERE id_layout = ?");
        $deleteLayout->execute([$id_layout]);

        $pdo->commit();
        header("Location: liste_layouts.php?delete_success=1");
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = "Erreur lors de la suppression : " . $e->getMessage();
    }
}

// RÉCUPÉRATION DE TOUS LES LAYOUTS AVEC LES INFOS DE L'ÉVÉNEMENT ET DU TEMPLATE DE FOND
try {
    $sql = "SELECT tl.*, e.nom_evenement, e.id_evenement, t.nom_template, t.image_path 
            FROM ticket_layouts tl
            JOIN evenements e ON tl.event_id = e.id_evenement
            JOIN ticket_templates t ON tl.template_id = t.id_template
            ORDER BY tl.id_layout DESC";
    $layouts = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erreur de récupération des données : " . $e->getMessage());
}

ob_start();
?>

<!-- Tailwind CSS injecté proprement pour la base structurelle de la page -->
<script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>

<!-- ==========================================================================
     SYSTÈME DE STYLE DÉDIÉ ET ADAPTÉ AU THÈME DARK PREMIUM
     ========================================================================== -->
<style>
    /* Reset & surcouche contextuelle de la page */
    .layouts-wrapper {
        font-family: var(--font-primary);
        display: flex;
        flex-direction: column;
        gap: var(--space-5);
        animation: fadeInPage 350ms cubic-bezier(0.4, 0, 0.2, 1);
    }

    @keyframes fadeInPage {
        from { opacity: 0; transform: translateY(8px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* En-tête de la vue */
    .view-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: var(--space-4);
        padding-bottom: var(--space-4);
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    }

    .view-title-area h2 {
        font-size: var(--font-size-xl);
        font-weight: 700;
        color: var(--white);
        margin-bottom: var(--space-1);
    }

    .view-title-area p {
        font-size: var(--font-size-sm);
        color: var(--gray-400);
    }

    /* Bouton Retour Premium */
    .btn-back-premium {
        background: var(--primary-600);
        color: var(--white);
        font-size: var(--font-size-sm);
        font-weight: 600;
        padding: var(--space-2) var(--space-4);
        border-radius: var(--radius-md);
        border: 1px solid rgba(255, 255, 255, 0.08);
        text-decoration: none;
        white-space: nowrap;
        transition: all var(--transition-fast);
    }

    .btn-back-premium:hover {
        background: var(--primary-700);
        border-color: rgba(255, 255, 255, 0.15);
        transform: translateX(-2px);
    }

    /* Alertes & Bannières de Statut */
    .alert-banner {
        padding: var(--space-4);
        border-radius: var(--radius-lg);
        font-size: var(--font-size-sm);
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: var(--space-2);
        box-shadow: var(--shadow-sm);
    }

    .alert-success {
        background: rgba(16, 172, 132, 0.1);
        border: 1px solid rgba(16, 172, 132, 0.25);
        color: #1dd1a1;
    }

    .alert-danger {
        background: rgba(255, 71, 87, 0.1);
        border: 1px solid rgba(255, 71, 87, 0.25);
        color: #ff6b6b;
    }

    /* Grille Principale */
    .layouts-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: var(--space-5);
    }

    /* Cartes de Layout Intelligentes */
    .layout-premium-card {
        background: linear-gradient(180deg, var(--primary-800) 0%, rgba(8, 0, 32, 0.85) 100%);
        border: 1px solid rgba(255, 255, 255, 0.05);
        border-radius: var(--radius-lg);
        overflow: hidden;
        box-shadow: var(--shadow-md);
        display: flex;
        flex-direction: column;
        transition: transform var(--transition-base), border-color var(--transition-base), box-shadow var(--transition-base);
    }

    .layout-premium-card:hover {
        transform: translateY(-4px);
        border-color: rgba(46, 134, 222, 0.3);
        box-shadow: var(--shadow-xl);
    }

    /* Aperçu de l'image de fond */
    .card-preview-zone {
        width: 100%;
        height: 170px;
        background: var(--primary-900);
        position: relative;
        overflow: hidden;
        display: flex;
        align-items: center;
        justify-content: center;
        border-bottom: 1px solid rgba(255, 255, 255, 0.04);
    }

    .card-preview-zone img {
        width: 100%;
        height: 100%;
        object-cover: cover;
        transition: transform var(--transition-slow);
    }

    .layout-premium-card:hover .card-preview-zone img {
        transform: scale(1.04);
    }

    /* Badge ID technique */
    .id-badge {
        absolute: absolute;
        top: var(--space-3);
        left: var(--space-3);
        background: rgba(8, 0, 32, 0.85);
        color: var(--gray-300);
        font-family: monospace;
        font-size: var(--font-size-xs);
        padding: var(--space-1) var(--space-2);
        border-radius: var(--radius-sm);
        border: 1px solid rgba(255, 255, 255, 0.08);
        backdrop-filter: blur(4px);
    }

    /* Rideau d'action au survol de l'image */
    .preview-overlay {
        position: absolute;
        inset: 0;
        background: rgba(8, 0, 32, 0.5);
        opacity: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: opacity var(--transition-base);
        backdrop-filter: blur(2px);
    }

    .card-preview-zone:hover .preview-overlay {
        opacity: 1;
    }

    .btn-overlay-studio {
        background: var(--white);
        color: var(--primary-900);
        font-size: var(--font-size-sm);
        font-weight: 700;
        padding: var(--space-2) var(--space-4);
        border-radius: var(--radius-md);
        text-decoration: none;
        box-shadow: var(--shadow-lg);
        transition: transform var(--transition-fast);
    }

    .btn-overlay-studio:hover {
        transform: scale(1.05);
    }

    /* Corps informatif de la carte */
    .card-body-content {
        padding: var(--space-4);
        flex: 1;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        gap: var(--space-3);
    }

    .layout-title {
        font-size: var(--font-size-md);
        font-weight: 600;
        color: var(--white);
        line-height: 1.4;
    }

    .info-line {
        display: flex;
        align-items: center;
        gap: var(--space-2);
        font-size: var(--font-size-sm);
        color: var(--gray-400);
    }

    .info-line strong {
        color: var(--gray-200);
    }

    .badge-template-tag {
        background: var(--primary-600);
        color: var(--accent-blue);
        font-family: monospace;
        font-size: var(--font-size-xs);
        padding: 2px 6px;
        border-radius: var(--radius-sm);
        border: 1px solid rgba(46, 134, 222, 0.2);
    }

    /* Bloc technique d'affichage des coordonnées (Style Canvas pro) */
    .technical-specs-box {
        background: rgba(255, 255, 255, 0.02);
        border: 1px solid rgba(255, 255, 255, 0.04);
        border-radius: var(--radius-md);
        padding: var(--space-2) var(--space-3);
        font-family: monospace;
        font-size: 11px;
        color: var(--gray-400);
        display: flex;
        flex-direction: column;
        gap: 2px;
    }

    .specs-row {
        display: flex;
        justify-content: space-between;
    }

    .specs-highlight {
        color: var(--accent-blue);
    }

    /* Footer d'actions */
    .card-footer-actions {
        display: flex;
        gap: var(--space-2);
        border-top: 1px solid rgba(255, 255, 255, 0.05);
        padding-top: var(--space-3);
    }

    .btn-footer-edit {
        flex: 1;
        background: rgba(46, 134, 222, 0.1);
        color: var(--accent-blue);
        border: 1px solid rgba(46, 134, 222, 0.25);
        font-size: var(--font-size-sm);
        font-weight: 600;
        text-align: center;
        padding: var(--space-2);
        border-radius: var(--radius-md);
        text-decoration: none;
        transition: all var(--transition-fast);
    }

    .btn-footer-edit:hover {
        background: var(--accent-blue);
        color: var(--white);
        box-shadow: 0 0 10px rgba(46, 134, 222, 0.2);
    }

    .btn-footer-delete {
        background: rgba(255, 71, 87, 0.1);
        color: var(--accent-red);
        border: 1px solid rgba(255, 71, 87, 0.25);
        padding: var(--space-2) var(--space-3);
        border-radius: var(--radius-md);
        transition: all var(--transition-fast);
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .btn-footer-delete:hover {
        background: var(--accent-red);
        color: var(--white);
    }

    /* Empty State (Pas de layouts enregistrés) */
    .empty-layouts-card {
        grid-column: 1 / -1;
        background: linear-gradient(180deg, var(--primary-800) 0%, var(--primary-900) 100%);
        border: 1px solid rgba(255, 255, 255, 0.05);
        border-radius: var(--radius-xl);
        padding: var(--space-6);
        text-align: center;
        box-shadow: var(--shadow-lg);
    }

    .empty-layouts-icon {
        font-size: 3.5rem;
        margin-bottom: var(--space-3);
        display: inline-block;
    }

    .btn-create-event {
        display: inline-block;
        margin-top: var(--space-4);
        background: var(--accent-green);
        color: var(--white);
        font-weight: 600;
        font-size: var(--font-size-sm);
        padding: var(--space-2) var(--space-5);
        border-radius: var(--radius-md);
        text-decoration: none;
        transition: all var(--transition-fast);
    }

    .btn-create-event:hover {
        filter: brightness(1.1);
        box-shadow: 0 0 12px rgba(16, 172, 132, 0.3);
    }

    /* ==========================================================================
       MEDIA QUERIES - RESPONSIVITÉ COMPLEXE ÉCRANS ET SMARTPHONES
       ========================================================================== */
    @media (max-width: 640px) {
        .view-header {
            flex-direction: column;
            align-items: flex-start;
            gap: var(--space-3);
        }

        .btn-back-premium {
            width: 100%;
            text-align: center;
        }

        .layouts-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="layouts-wrapper text-left">

    <!-- 🎨 CONFIGURATIONS / EN-TÊTE -->
    <div class="view-header">
        <div class="view-title-area">
            <h2>🎨 Configurations de Tickets (Layouts)</h2>
            <p>Visualisez, modifiez ou clonez les structures de positionnement des textes et QR codes sur vos tickets.</p>
        </div>
        <a href="evenements.php" class="btn-back-premium">
            ◄ Retour aux Événements
        </a>
    </div>

    <!-- ZONE ALERTES ET NOTIFICATIONS -->
    <?php if (isset($_GET['delete_success'])): ?>
        <div class="alert-banner alert-success">
            <span>✅</span> Le layout a été supprimé avec succès et l'événement associé a été détaché.
        </div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <div class="alert-banner alert-danger">
            <span>❌</span> <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <!-- GRILLE DÉDIÉE DES LAYOUTS -->
    <div class="layouts-grid">
        <?php if (empty($layouts)): ?>
            <div class="empty-layouts-card">
                <div class="empty-layouts-icon">🎫</div>
                <h3 class="text-xl font-bold text-white mb-2">Aucun layout configuré</h3>
                <p class="text-sm text-gray-400 max-w-md mx-auto">
                    Les configurations d'emplacements s'affichent ici dès que vous créez un événement requérant un ticket ou via le studio graphique.
                </p>
                <a href="ajouter_evenement.php" class="btn-create-event">
                    + Créer un Événement
                </a>
            </div>
        <?php else: ?>
            <?php foreach ($layouts as $layout): ?>
                <div class="layout-premium-card">
                    
                    <!-- Bloc Supérieur : Aperçu de l'image -->
                    <div class="card-preview-zone">
                        <img src="../../<?= htmlspecialchars($layout['image_path']) ?>" alt="Template Background">
                        
                        <!-- Badge Technique ID -->
                        <div class="id-badge">ID: #<?= $layout['id_layout'] ?></div>
                        
                        <!-- Overlay d'accès rapide -->
                        <div class="preview-overlay">
                            <a href="ajouter_layout.php?id=<?= $layout['id_evenement'] ?>" class="btn-overlay-studio">
                                ⚙️ Ouvrir le Studio
                            </a>
                        </div>
                    </div>

                    <!-- Bloc Inférieur : Corps d'informations -->
                    <div class="card-body-content">
                        <div class="flex flex-col gap-2">
                            <h3 class="layout-title truncate" title="<?= htmlspecialchars($layout['layout_name']) ?>">
                                <?= htmlspecialchars($layout['layout_name']) ?>
                            </h3>
                            
                            <div class="flex flex-col gap-1">
                                <div class="info-line">
                                    <span>📅</span>
                                    <span class="truncate">
                                        Événement : <strong title="<?= htmlspecialchars($layout['nom_evenement']) ?>"><?= htmlspecialchars($layout['nom_evenement']) ?></strong>
                                    </span>
                                </div>
                                <div class="info-line">
                                    <span>🖼️</span>
                                    <span class="truncate">
                                        Fond : <span class="badge-template-tag"><?= htmlspecialchars($layout['nom_template']) ?></span>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Paramètres de coordonnées Canvas -->
                        <div class="technical-specs-box">
                            <div class="specs-row">
                                <div>QR: <span class="specs-highlight">X=<?= $layout['qr_x'] ?>, Y=<?= $layout['qr_y'] ?></span></div>
                                <div>Logo: <span class="specs-highlight">X=<?= $layout['logo_x'] ?>, Y=<?= $layout['logo_y'] ?></span></div>
                            </div>
                            <div class="specs-row truncate">
                                <div class="truncate">Titre: <?= $layout['event_name_font_size'] ?>px &bull; <span style="color: <?= htmlspecialchars($layout['event_name_color']) ?>; text-shadow: 0 0 2px #000;"><?= htmlspecialchars($layout['event_name_color']) ?></span></div>
                            </div>
                        </div>

                        <!-- Boutons d'action -->
                        <div class="card-footer-actions">
                            <a href="ajouter_layout.php?id=<?= $layout['id_evenement'] ?>" class="btn-footer-edit">
                                ✏️ Éditer les Positions
                            </a>
                            <a href="liste_layouts.php?action=delete&id=<?= $layout['id_layout'] ?>" 
                               onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce layout ? Cela désassociera également la configuration graphique de son événement lié.');" 
                               class="btn-footer-delete" 
                               title="Supprimer définitivement la configuration">
                                🗑️
                            </a>
                        </div>
                    </div>

                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php
$content = ob_get_clean();
include "../layout.php";
?>