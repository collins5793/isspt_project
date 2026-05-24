<?php
session_start();
require_once "../../includes/db.php";

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit;
}

$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: actualites.php");
    exit;
}

$stmt = $pdo->prepare("
    SELECT a.*, 
           ad.nom AS admin_nom, 
           ad.prenom AS admin_prenom,
           ay.label AS academic_year
    FROM actualites a
    LEFT JOIN administrateurs ad ON ad.id_admin = a.id_admin
    LEFT JOIN academic_years ay ON ay.id = a.id_academic_year
    WHERE a.id = ?
");
$stmt->execute([$id]);
$actualite = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$actualite) {
    $_SESSION['error'] = "Actualité introuvable.";
    header("Location: actualites.php");
    exit;
}

function statutBadge($statut) {
    return match ($statut) {
        'publie'    => '<span class="status-pill status-pill-publie"><span class="pill-dot"></span>Publié</span>',
        'brouillon' => '<span class="status-pill status-pill-brouillon"><span class="pill-dot"></span>Brouillon</span>',
        'archive'   => '<span class="status-pill status-pill-archive"><span class="pill-dot"></span>Archivé</span>',
        default     => '<span class="status-pill"><span class="pill-dot"></span>Inconnu</span>',
    };
}

ob_start();
?>

<style>
    :root {
        --bg-page: var(--primary-900);
        --bg-card: var(--primary-800);
        --bg-meta-block: var(--primary-700);
        --border-glass: rgba(255, 255, 255, 0.06);
    }

    body {
        background-color: var(--bg-page);
        color: var(--gray-100);
        font-family: var(--font-primary);
    }

    .detail-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: var(--space-4);
        margin-bottom: var(--space-6);
        padding-bottom: var(--space-4);
        border-bottom: 1px solid var(--border-glass);
    }

    .detail-header h2 {
        font-size: var(--font-size-xl);
        font-weight: 700;
        color: var(--white);
        letter-spacing: -0.5px;
        margin: 0;
    }

    .action-button-group {
        display: inline-flex;
        gap: var(--space-2);
    }

    .btn-adm {
        display: inline-flex;
        align-items: center;
        gap: var(--space-2);
        padding: var(--space-2) var(--space-4);
        font-size: var(--font-size-sm);
        font-weight: 600;
        border-radius: var(--radius-md);
        text-decoration: none;
        transition: all var(--transition-fast);
        cursor: pointer;
        border: none;
    }

    .btn-adm-back { background-color: transparent; color: var(--gray-300); border: 1px solid var(--border-glass); }
    .btn-adm-back:hover { background-color: var(--primary-700); color: var(--white); }

    .btn-adm-edit { background-color: rgba(46, 134, 222, 0.15); color: #54a0ff; border: 1px solid rgba(46, 134, 222, 0.2); }
    .btn-adm-edit:hover { background-color: var(--accent-blue); color: var(--white); transform: translateY(-1px); }

    .btn-adm-delete { background-color: rgba(255, 71, 87, 0.12); color: #ff6b81; border: 1px solid rgba(255, 71, 87, 0.15); }
    .btn-adm-delete:hover { background-color: var(--accent-red); color: var(--white); transform: translateY(-1px); }

    .article-layout {
        display: grid;
        grid-template-columns: 1fr 280px;
        gap: var(--space-5);
        align-items: start;
    }

    @media (max-width: 992px) {
        .article-layout { grid-template-columns: 1fr; }
    }

    .article-main-card {
        background-color: var(--bg-card);
        border: 1px solid var(--border-glass);
        border-radius: var(--radius-lg);
        padding: var(--space-6);
        box-shadow: var(--shadow-xl);
    }

    .article-sidebar-card {
        background-color: var(--bg-card);
        border: 1px solid var(--border-glass);
        border-radius: var(--radius-lg);
        padding: var(--space-5);
        box-shadow: var(--shadow-lg);
    }

    .article-main-title {
        font-family: var(--font-primary);
        font-size: 1.75rem;
        font-weight: 700;
        color: var(--white);
        line-height: 1.3;
        margin-top: 0;
        margin-bottom: var(--space-5);
        letter-spacing: -0.5px;
    }

    .section-subtitle {
        font-size: var(--font-size-xs);
        text-transform: uppercase;
        letter-spacing: 0.75px;
        color: var(--gray-400);
        font-weight: 700;
        margin-top: 0;
        margin-bottom: var(--space-3);
        display: flex;
        align-items: center;
        gap: var(--space-2);
    }

    .section-subtitle svg { color: var(--accent-blue); }

    .article-body-text {
        font-size: calc(var(--font-size-md) - 0.05rem);
        color: var(--gray-100);
        line-height: 1.75;
        white-space: pre-line;
    }

    .president-blockquote-box {
        margin-top: var(--space-6);
        background: linear-gradient(145deg, rgba(255, 255, 255, 0.01) 0%, rgba(255, 255, 255, 0.03) 100%);
        border-left: 4px solid var(--accent-blue);
        border-radius: 0 var(--radius-md) var(--radius-md) 0;
        padding: var(--space-4) var(--space-5);
        border-top: 1px solid var(--border-glass);
        border-right: 1px solid var(--border-glass);
        border-bottom: 1px solid var(--border-glass);
    }

    .president-text {
        font-style: italic;
        color: var(--gray-200);
        font-size: var(--font-size-sm);
        line-height: 1.6;
    }

    .sidebar-heading {
        font-size: var(--font-size-sm);
        font-weight: 600;
        color: var(--white);
        margin-top: 0;
        margin-bottom: var(--space-4);
        padding-bottom: var(--space-2);
        border-bottom: 1px solid var(--border-glass);
    }

    .meta-item-row {
        display: flex;
        flex-direction: column;
        gap: var(--space-1);
        margin-bottom: var(--space-4);
    }

    .meta-item-row:last-of-type { margin-bottom: 0; }

    .meta-row-label {
        font-size: var(--font-size-xs);
        color: var(--gray-400);
        font-weight: 500;
    }

    .meta-row-value {
        font-size: var(--font-size-sm);
        color: var(--gray-100);
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: var(--space-2);
    }

    .meta-row-value svg { color: var(--gray-400); width: 14px; }

    .status-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: var(--space-1) var(--space-3);
        border-radius: var(--radius-full);
        font-size: var(--font-size-xs);
        font-weight: 600;
        width: fit-content;
    }

    .pill-dot { width: 6px; height: 6px; border-radius: 50%; }

    .status-pill-publie { background-color: rgba(16, 172, 132, 0.12); color: #1dd1a1; }
    .status-pill-publie .pill-dot { background-color: #1dd1a1; }

    .status-pill-brouillon { background-color: rgba(254, 202, 87, 0.12); color: #feca57; }
    .status-pill-brouillon .pill-dot { background-color: #feca57; }

    .status-pill-archive { background-color: rgba(164, 176, 190, 0.12); color: #a4b0be; }
    .status-pill-archive .pill-dot { background-color: #a4b0be; }
</style>

<div class="container-fluid">

    <div class="detail-header">
        <h2>Détails de l'actualité</h2>
        <div class="action-button-group">
            <a href="actualites.php" class="btn-adm btn-adm-back">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                Retour
            </a>
            <a href="actualite_modifier.php?id=<?= $actualite['id'] ?>" class="btn-adm btn-adm-edit">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                Modifier
            </a>
            <a href="actualite_supprimer.php?id=<?= $actualite['id'] ?>" class="btn-adm btn-adm-delete"
               onclick="return confirm('Voulez-vous vraiment détruire définitivement cette actualité ?');">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                Supprimer
            </a>
        </div>
    </div>

    <div class="article-layout">
        
        <div class="article-main-card">
            <h1 class="article-main-title"><?= htmlspecialchars($actualite['titre']) ?></h1>
            
            <div class="section-content-area">
                <h3 class="section-subtitle">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h7"/></svg>
                    Corps du communiqué
                </h3>
                <div class="article-body-text">
                    <?= nl2br(htmlspecialchars($actualite['contenu'])) ?>
                </div>
            </div>

            <?php if (!empty($actualite['mot_du_president'])): ?>
                <div class="president-blockquote-box">
                    <h3 class="section-subtitle" style="color: var(--accent-blue);">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                        Le mot de la Présidence
                    </h3>
                    <div class="president-text">
                        <?= nl2br(htmlspecialchars($actualite['mot_du_president'])) ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="article-sidebar-card">
            <h3 class="sidebar-heading">Informations</h3>
            
            <div class="meta-item-row">
                <span class="meta-row-label">Statut actuel</span>
                <div style="margin-top: 2px;">
                    <?= statutBadge($actualite['statut']) ?>
                </div>
            </div>

            <div class="meta-item-row">
                <span class="meta-row-label">Date de publication</span>
                <div class="meta-row-value">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span><?= date('d/m/Y à H:i', strtotime($actualite['date_publication'])) ?></span>
                </div>
            </div>

            <div class="meta-item-row">
                <span class="meta-row-label">Rédacteur</span>
                <div class="meta-row-value">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    <span><?= htmlspecialchars($actualite['admin_prenom'] . ' ' . $actualite['admin_nom']) ?></span>
                </div>
            </div>

            <div class="meta-item-row">
                <span class="meta-row-label">Année Académique</span>
                <div class="meta-row-value">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 14l9-5-9-5-9 5 9 5z"/><path d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/></svg>
                    <span><?= htmlspecialchars($actualite['academic_year']) ?></span>
                </div>
            </div>
        </div>

    </div>
</div>

<?php
$content = ob_get_clean();
include "../layout.php";
?>