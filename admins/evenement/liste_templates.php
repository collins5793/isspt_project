<?php
session_start();
// Inclusion de votre fichier de connexion centralisé
require_once "../../includes/db.php"; 

// 1. RÉCUPÉRATION DES TEMPLATES
try {
    $stmt = $pdo->query("
        SELECT * FROM ticket_templates 
        ORDER BY created_at DESC
    ");
    $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erreur lors de la récupération des templates : " . $e->getMessage());
}

// Début de la capture pour le Layout
ob_start();
?>

<style>
    /* Intégration et forçage de votre bloc :root */
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
    }

    /* Structure globale */
    .view-content-wrapper {
        font-family: var(--font-primary);
        background-color: var(--primary-900);
        color: var(--white);
        padding: var(--space-4);
        min-height: 100vh;
        box-sizing: border-box;
    }

    /* En-tête de la page */
    .studio-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: var(--space-5);
        gap: var(--space-4);
    }

    .title-section h2 {
        font-size: var(--font-size-xl);
        font-weight: 700;
        color: var(--white);
        margin: 0 0 var(--space-1) 0;
        letter-spacing: -0.02em;
    }

    .title-section p {
        font-size: var(--font-size-sm);
        color: var(--gray-400);
        margin: 0;
    }

    /* Bouton d'action principal */
    .btn-add-template {
        display: inline-flex;
        align-items: center;
        gap: var(--space-2);
        background-color: var(--accent-blue);
        color: var(--white);
        font-size: var(--font-size-sm);
        font-weight: 600;
        padding: 0.65rem 1.25rem;
        border-radius: var(--radius-md);
        text-decoration: none;
        border: none;
        box-shadow: var(--shadow-md);
        transition: transform var(--transition-fast), background-color var(--transition-fast), box-shadow var(--transition-fast);
        white-space: nowrap;
        cursor: pointer;
    }

    .btn-add-template:hover {
        background-color: #2475c9;
        transform: translateY(-1px);
        box-shadow: var(--shadow-lg);
    }

    /* Séparateur horizontal propre */
    .studio-divider {
        border: 0;
        height: 1px;
        background: rgba(255, 255, 255, 0.08);
        margin: var(--space-4) 0 var(--space-5) 0;
    }

    /* Grille de cartes adaptative en CSS pur */
    .studio-grid-container {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: var(--space-5);
    }

    /* Composant Carte unique (Card Template) */
    .ui-card-template {
        background-color: var(--primary-800);
        border: 1px solid rgba(255, 255, 255, 0.05);
        border-radius: var(--radius-lg);
        overflow: hidden;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        box-shadow: var(--shadow-sm);
        transition: transform var(--transition-base), border-color var(--transition-base), box-shadow var(--transition-base);
        text-align: left;
    }

    .ui-card-template:hover {
        transform: translateY(-4px);
        border-color: rgba(46, 134, 222, 0.3);
        box-shadow: var(--shadow-xl);
    }

    /* Zone supérieure de la carte : Aperçu de l'image */
    .card-media-preview {
        position: relative;
        background-color: var(--primary-700);
        height: 180px;
        width: 100%;
        overflow: hidden;
        display: flex;
        align-items: center;
        justify-content: center;
        border-bottom: 1px solid rgba(255, 255, 255, 0.04);
    }

    .card-media-preview img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform var(--transition-slow);
    }

    .ui-card-template:hover .card-media-preview img {
        transform: scale(1.04);
    }

    /* En cas d'image introuvable */
    .fallback-preview-icon {
        color: var(--gray-400);
        text-align: center;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: var(--space-1);
    }

    .fallback-preview-icon span:first-child {
        font-size: 2rem;
    }

    .fallback-preview-icon span:last-child {
        font-size: var(--font-size-xs);
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        opacity: 0.7;
    }

    /* Absolutes Pill / Badges de statut */
    .status-pill {
        position: absolute;
        top: var(--space-3);
        right: var(--space-3);
        font-size: var(--font-size-xs);
        font-weight: 700;
        padding: 0.25rem 0.65rem;
        border-radius: var(--radius-full);
        box-shadow: var(--shadow-sm);
        backdrop-filter: blur(4px);
        display: inline-flex;
        align-items: center;
        gap: var(--space-1);
    }

    .pill-active {
        background-color: rgba(16, 172, 132, 0.15);
        color: var(--accent-green);
        border: 1px solid rgba(16, 172, 132, 0.3);
    }

    .pill-active::before {
        content: '';
        width: 6px;
        height: 6px;
        background-color: var(--accent-green);
        border-radius: 50%;
        display: inline-block;
    }

    .pill-inactive {
        background-color: rgba(164, 176, 190, 0.15);
        color: var(--gray-300);
        border: 1px solid rgba(255, 255, 255, 0.08);
    }

    /* Corps textuel de la carte */
    .card-body-wrapper {
        padding: var(--space-4);
        flex-grow: 1;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        gap: var(--space-4);
    }

    .card-body-wrapper h3 {
        font-size: var(--font-size-md);
        font-weight: 600;
        color: var(--white);
        margin: 0 0 var(--space-1) 0;
        /* Limite le titre à une seule ligne pour éviter les brisures d'alignement */
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .card-body-wrapper .description-paragraph {
        font-size: var(--font-size-xs);
        color: var(--gray-400);
        margin: 0;
        line-height: 1.5;
        /* Limite la description à deux lignes maximum */
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        min-height: 36px;
    }

    /* Boîte des caractéristiques techniques */
    .technical-specs-container {
        background-color: var(--primary-700);
        border: 1px solid rgba(255, 255, 255, 0.03);
        border-radius: var(--radius-md);
        padding: var(--space-3);
        display: flex;
        flex-direction: column;
        gap: var(--space-2);
        font-size: var(--font-size-xs);
    }

    .spec-line {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .spec-title {
        color: var(--gray-400);
    }

    .spec-value {
        color: var(--white);
        font-weight: 500;
    }

    .spec-value-mono {
        font-family: monospace;
        background: rgba(0, 0, 0, 0.2);
        padding: 1px 4px;
        border-radius: var(--radius-sm);
        font-size: 11px;
    }

    /* Actions d'administration bas de page */
    .card-action-bar {
        display: flex;
        align-items: center;
        gap: var(--space-2);
        border-top: 1px solid rgba(255, 255, 255, 0.05);
        padding-top: var(--space-3);
    }

    .btn-card-action {
        flex-grow: 1;
        text-align: center;
        font-size: var(--font-size-xs);
        font-weight: 600;
        padding: 0.55rem var(--space-2);
        border-radius: var(--radius-sm);
        text-decoration: none;
        transition: all var(--transition-fast);
        border: none;
        cursor: pointer;
    }

    .btn-card-modify {
        background-color: rgba(46, 134, 222, 0.1);
        color: var(--accent-blue);
        border: 1px solid rgba(46, 134, 222, 0.25);
    }

    .btn-card-modify:hover {
        background-color: var(--accent-blue);
        color: var(--white);
    }

    .btn-card-delete {
        background-color: rgba(255, 71, 87, 0.08);
        color: var(--accent-red);
        border: 1px solid rgba(255, 71, 87, 0.2);
        padding-left: var(--space-3);
        padding-right: var(--space-3);
    }

    .btn-card-delete:hover {
        background-color: var(--accent-red);
        color: var(--white);
    }

    /* Carte d'état de liste vide (Empty State) */
    .empty-state-fallback {
        grid-column: 1 / -1;
        background: linear-gradient(180deg, var(--primary-800) 0%, var(--primary-900) 100%);
        border: 2px dashed rgba(255, 255, 255, 0.08);
        border-radius: var(--radius-xl);
        padding: var(--space-6) var(--space-4);
        text-align: center;
        max-width: 500px;
        margin: var(--space-6) auto;
    }

    .empty-state-fallback .fallback-icon {
        font-size: 3rem;
        margin-bottom: var(--space-3);
        display: block;
    }

    .empty-state-fallback h3 {
        font-size: var(--font-size-lg);
        color: var(--white);
        margin: 0 0 var(--space-2) 0;
    }

    .empty-state-fallback p {
        font-size: var(--font-size-sm);
        color: var(--gray-400);
        margin: 0 0 var(--space-5) 0;
        line-height: 1.5;
    }

    /* ==========================================================================
      📱 MEDIA QUERIES POUT UNE RESPONSIVITÉ SANS DÉFAUT
      ========================================================================== 
    */
    @media (max-width: 640px) {
        .studio-header {
            flex-direction: column;
            align-items: flex-start;
            gap: var(--space-3);
        }

        .btn-add-template {
            width: 100%;
            justify-content: center;
        }

        .studio-grid-container {
            grid-template-columns: 1fr; /* Passage à une colonne unique sur mobile */
            gap: var(--space-4);
        }

        .card-action-bar {
            flex-direction: column;
            gap: var(--space-2);
        }

        .btn-card-action {
            width: 100%;
        }
    }
</style>

<div class="view-content-wrapper">

    <div class="studio-header">
        <div class="title-section">
            <h2>Templates de Tickets</h2>
            <p>Visualisez, configurez et ajustez vos gabarits d'impression et de génération numérique.</p>
        </div>
        <div class="actions">
            <a href="ajouter_template.php" class="btn-add-template">
                <span>+</span> Ajouter un template
            </a>
        </div>
    </div>

    <hr class="studio-divider">

    <div class="studio-grid-container">
        <?php if (!empty($templates)): ?>
            <?php foreach ($templates as $template): ?>
                <div class="ui-card-template">
                    
                    <div class="card-media-preview">
                        <?php 
                        $imageURL = "../../" . htmlspecialchars($template['image_path']);
                        if (!empty($template['image_path']) && file_exists($imageURL)): 
                        ?>
                            <img src="<?= $imageURL ?>" alt="<?= htmlspecialchars($template['nom_template']) ?>">
                        <?php else: ?>
                            <div class="fallback-preview-icon">
                                <span>🖼️</span>
                                <span>Aucun rendu</span>
                            </div>
                        <?php endif; ?>

                        <div class="status-pill <?= $template['is_active'] ? 'pill-active' : 'pill-inactive' ?>">
                            <?= $template['is_active'] ? 'Actif' : 'Inactif' ?>
                        </div>
                    </div>

                    <div class="card-body-wrapper">
                        <div>
                            <h3 title="<?= htmlspecialchars($template['nom_template']) ?>">
                                <?= htmlspecialchars($template['nom_template']) ?>
                            </h3>
                            <p class="description-paragraph">
                                <?= !empty($template['description']) ? htmlspecialchars($template['description']) : 'Aucune description fournie.' ?>
                            </p>
                        </div>

                        <div class="technical-specs-container">
                            <div class="spec-line">
                                <span class="spec-title">Résolution :</span>
                                <span class="spec-value spec-value-mono"><?= intval($template['canvas_width']) ?> × <?= intval($template['canvas_height']) ?> px</span>
                            </div>
                            <div class="spec-line">
                                <span class="spec-title">Bords arrondis :</span>
                                <span class="spec-value"><?= intval($template['border_radius']) ?> px</span>
                            </div>
                            <div class="spec-line">
                                <span class="spec-title">Filtre (Overlay) :</span>
                                <span class="spec-value spec-value-mono" title="<?= htmlspecialchars($template['overlay_color']) ?>">
                                    <?= htmlspecialchars($template['overlay_color']) ?>
                                </span>
                            </div>
                        </div>

                        <div class="card-action-bar">
                            <a href="modifier_template.php?id=<?= $template['id_template'] ?>" class="btn-card-action btn-card-modify">
                                Modifier le modèle
                            </a>
                            <a href="supprimer_template.php?id=<?= $template['id_template'] ?>" 
                               onclick="return confirm('Voulez-vous vraiment supprimer ce template définitivement ?');"
                               class="btn-card-action btn-card-delete"
                               title="Supprimer">
                                Supprimer
                            </a>
                        </div>
                    </div>

                </div>
            <?php endforeach; ?>
        <?php else: ?>
            
            <div class="empty-state-fallback">
                <span class="fallback-icon">🎴</span>
                <h3>Aucun template disponible</h3>
                <p>Commencez par initialiser et téléverser votre premier modèle d'arrière-plan graphique pour vos événements.</p>
                <a href="ajouter_template.php" class="btn-add-template">
                    + Créer mon premier template
                </a>
            </div>
            
        <?php endif; ?>
    </div>
</div>

<?php
$content = ob_get_clean();
include "../layout.php";
?>