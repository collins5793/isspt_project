<?php
session_start();
require_once "../../includes/db.php";

// Vérification de l'ID passé en GET
$eventId = $_GET['id'] ?? null;
if (!$eventId) {
    header("Location: evenements.php");
    exit;
}

// Récupération des infos de l'événement
$stmt = $pdo->prepare("
    SELECT e.*, ay.label AS academic_year, a.nom AS admin_nom, a.prenom AS admin_prenom
    FROM evenements e
    LEFT JOIN academic_years ay ON e.academic_year_id = ay.id
    LEFT JOIN administrateurs a ON e.cree_par = a.id_admin
    WHERE e.id_evenement = ?
");
$stmt->execute([$eventId]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    die("Événement introuvable.");
}

// Récupération des artistes invités
$artistes = $pdo->prepare("SELECT * FROM evenement_artistes WHERE id_evenement = ?");
$artistes->execute([$eventId]);
$artistes = $artistes->fetchAll(PDO::FETCH_ASSOC);

// Récupération de la galerie
$galerie = $pdo->prepare("SELECT * FROM galerie WHERE event_id = ?");
$galerie->execute([$eventId]);
$galerie = $galerie->fetchAll(PDO::FETCH_ASSOC);

// Récupération des participants (internes et externes)
$participants = $pdo->prepare("
    SELECT p.*, e.nom AS etu_nom, e.prenom AS etu_prenom, ext.full_name AS ext_name
    FROM participants_evenements p
    LEFT JOIN etudiants e ON p.user_id = e.id_etudiant
    LEFT JOIN external_participants ext ON p.external_participant_id = ext.id
    WHERE p.event_id = ?
");
$participants->execute([$eventId]);
$participants = $participants->fetchAll(PDO::FETCH_ASSOC);

// Récupération des tickets
$tickets = $pdo->prepare("
    SELECT t.*, e.nom AS etu_nom, e.prenom AS etu_prenom, ext.full_name AS ext_name
    FROM tickets t
    LEFT JOIN etudiants e ON t.user_id = e.id_etudiant
    LEFT JOIN external_participants ext ON t.external_participant_id = ext.id
    WHERE t.event_id = ?
");
$tickets->execute([$eventId]);
$tickets = $tickets->fetchAll(PDO::FETCH_ASSOC);

// Récupération des commentaires
$comments = $pdo->prepare("
    SELECT c.*, e.nom AS etu_nom, e.prenom AS etu_prenom, a.nom AS admin_nom, a.prenom AS admin_prenom
    FROM commentaires c
    LEFT JOIN etudiants e ON c.id_etudiant = e.id_etudiant
    LEFT JOIN administrateurs a ON c.id_admin = a.id_admin
    WHERE c.event_id = ?
    ORDER BY c.date_commentaire DESC
");
$comments->execute([$eventId]);
$comments = $comments->fetchAll(PDO::FETCH_ASSOC);

// Calcul des statistiques rapides pour les badges du tableau de bord
$totalTicketsVendus = count($tickets);
$recetteTotale = array_sum(array_column($tickets, 'amount'));

// Contenu à injecter dans le layout
ob_start();
?>
<style>
    /* ==========================================================================
   RESET & BASE STYLES
   ========================================================================== */
*, *::before, *::after {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    font-family: var(--font-primary);
    background-color: var(--primary-900);
    color: var(--gray-100);
    font-size: var(--font-size-md);
    line-height: 1.6;
    -webkit-font-smoothing: antialiased;
    padding: var(--space-5);
    /* Ajustement automatique si une sidebar est présente sur l'application */
    margin-left: 0; 
    transition: margin var(--transition-base);
}

/* Scrollbar personnalisée pour le thème sombre */
::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}
::-webkit-scrollbar-track {
    background: var(--primary-900);
}
::-webkit-scrollbar-thumb {
    background: var(--primary-600);
    border-radius: var(--radius-full);
}
::-webkit-scrollbar-thumb:hover {
    background: var(--accent-blue);
}

/* ==========================================================================
   LAYOUT COMPONENTS (Layout, Containers & Grid)
   ========================================================================== */
.page-header-container {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--space-4);
    margin-bottom: var(--space-5);
    padding-bottom: var(--space-4);
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

.page-header-title {
    display: flex;
    align-items: center;
    gap: var(--space-3);
    flex-wrap: wrap;
}

.page-header-title h2 {
    font-size: 1.75rem;
    font-weight: 700;
    color: var(--white);
    letter-spacing: -0.02em;
}

/* Grilles structurelles */
.event-grid-layout {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: var(--space-5);
    align-items: start;
}

.event-tabs-container {
    display: grid;
    grid-template-columns: 1fr;
    gap: var(--space-5);
}

/* Cartes (Event Cards) */
.event-card {
    background: linear-gradient(145deg, var(--primary-800), var(--primary-700));
    border: 1px solid rgba(255, 255, 255, 0.05);
    border-radius: var(--radius-lg);
    padding: var(--space-5);
    box-shadow: var(--shadow-lg);
    transition: transform var(--transition-fast), box-shadow var(--transition-fast);
}

.event-card:hover {
    box-shadow: var(--shadow-xl);
}

.event-card h3 {
    font-size: var(--font-size-lg);
    color: var(--white);
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: var(--space-2);
}

.section-header-actions {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--space-3);
    flex-wrap: wrap;
}

hr {
    border: 0;
    height: 1px;
    background: rgba(255, 255, 255, 0.08);
    margin: var(--space-4) 0;
}

/* Espacements utilitaires */
.mt-3 { margin-top: var(--space-4); }
.mt-4 { margin-top: var(--space-5); }
.mb-5 { margin-bottom: var(--space-6); }
.py-3 { padding-top: var(--space-4); padding-bottom: var(--space-4); }

/* ==========================================================================
   BUTTONS & BADGES
   ========================================================================== */
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: var(--space-2);
    font-family: inherit;
    font-size: var(--font-size-sm);
    font-weight: 600;
    padding: 0.6rem 1.2rem;
    border-radius: var(--radius-md);
    border: 1px solid transparent;
    cursor: pointer;
    text-decoration: none;
    transition: all var(--transition-fast);
    white-space: nowrap;
}

.btn-sm {
    padding: 0.4rem 0.8rem;
    font-size: var(--font-size-xs);
}

.btn-block {
    display: flex;
    width: 100%;
}

.btn-secondary {
    background-color: var(--primary-600);
    color: var(--gray-100);
    border-color: rgba(255, 255, 255, 0.1);
}
.btn-secondary:hover {
    background-color: var(--primary-700);
    color: var(--white);
}

.btn-success {
    background-color: var(--accent-green);
    color: var(--white);
}
.btn-success:hover {
    filter: brightness(1.1);
    box-shadow: 0 0 12px rgba(16, 172, 132, 0.4);
}

.btn-warning {
    background-color: var(--accent-blue); /* Remplacement logique pour rester pro en dark-mode */
    color: var(--white);
}
.btn-warning:hover {
    filter: brightness(1.1);
}

.btn-danger {
    background-color: var(--accent-red);
    color: var(--white);
}
.btn-danger:hover {
    filter: brightness(1.1);
    box-shadow: 0 0 12px rgba(255, 71, 87, 0.4);
}

/* Badges */
.badge {
    display: inline-flex;
    align-items: center;
    padding: 0.25rem 0.75rem;
    font-size: var(--font-size-xs);
    font-weight: 700;
    border-radius: var(--radius-full);
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.badge-primary { background-color: rgba(46, 134, 222, 0.15); color: var(--accent-blue); border: 1px solid rgba(46, 134, 222, 0.3); }
.badge-secondary { background-color: rgba(164, 176, 190, 0.15); color: var(--gray-300); }
.badge-success { background-color: rgba(16, 172, 132, 0.15); color: var(--accent-green); }
.badge-warning { background-color: rgba(255, 71, 87, 0.15); color: var(--accent-red); } /* Aligné pour le contraste */
.badge-info { background-color: rgba(46, 134, 222, 0.15); color: var(--accent-blue); }
.badge-danger { background-color: rgba(255, 71, 87, 0.1); color: var(--gray-400); border: 1px solid rgba(255, 255, 255, 0.05); }

/* ==========================================================================
   INFORMATIONS GENERALES & STATS
   ========================================================================== */
.info-list p {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: var(--space-3) 0;
    border-bottom: 1px solid rgba(255, 255, 255, 0.04);
    font-size: var(--font-size-sm);
}

.info-list p:last-child {
    border-bottom: none;
}

.info-list strong {
    color: var(--gray-300);
    font-weight: 500;
}

.event-description h5 {
    color: var(--white);
    margin-bottom: var(--space-2);
    font-size: var(--font-size-md);
}

.event-description p {
    color: var(--gray-300);
    font-size: var(--font-size-sm);
    background: rgba(0, 0, 0, 0.15);
    padding: var(--space-3);
    border-radius: var(--radius-md);
    border-left: 3px solid var(--accent-blue);
}

/* Blocs de statistiques */
.stats-card {
    display: flex;
    flex-direction: column;
    gap: var(--space-3);
}

.stat-box {
    background: rgba(0, 0, 0, 0.2);
    border: 1px solid rgba(255, 255, 255, 0.03);
    padding: var(--space-4);
    border-radius: var(--radius-md);
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    transition: transform var(--transition-fast);
}

.stat-box:hover {
    transform: translateY(-2px);
    border-color: rgba(46, 134, 222, 0.3);
}

.stat-number {
    font-size: 2rem;
    font-weight: 700;
    color: var(--white);
    line-height: 1.2;
}

.stat-label {
    font-size: var(--font-size-xs);
    color: var(--gray-400);
    text-transform: uppercase;
    margin-top: var(--space-1);
    font-weight: 600;
}

.stat-box.success:hover {
    border-color: var(--accent-green);
}
.stat-box.success .stat-number {
    color: var(--accent-green);
}

/* ==========================================================================
   ARTISTS GRID
   ========================================================================== */
.artistes-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: var(--space-4);
}

.artiste-card {
    background: rgba(0, 0, 0, 0.15);
    border: 1px solid rgba(255, 255, 255, 0.04);
    border-radius: var(--radius-md);
    padding: var(--space-4);
    display: flex;
    flex-direction: column;
    gap: var(--space-3);
}

.artiste-photo {
    width: 100%;
    height: 180px;
    border-radius: var(--radius-md);
    overflow: hidden;
    background-color: var(--primary-900);
}

.artiste-photo img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform var(--transition-base);
}

.artiste-card:hover .artiste-photo img {
    transform: scale(1.05);
}

.artiste-info h4 {
    color: var(--white);
    font-size: var(--font-size-lg);
    margin-bottom: var(--space-1);
}

.artiste-desc {
    font-size: var(--font-size-xs);
    color: var(--gray-400);
    margin-top: var(--space-2);
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.artiste-actions {
    display: flex;
    gap: var(--space-2);
    margin-top: auto;
}

.artiste-actions .btn {
    flex: 1;
}

/* ==========================================================================
   CUSTOM RESPONSIVE TABLES
   ========================================================================== */
.table-responsive {
    width: 100%;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    border-radius: var(--radius-md);
    background: rgba(0, 0, 0, 0.15);
    border: 1px solid rgba(255, 255, 255, 0.04);
}

.table-custom {
    width: 100%;
    border-collapse: collapse;
    text-align: left;
    font-size: var(--font-size-sm);
}

.table-custom th {
    background-color: rgba(0, 0, 0, 0.25);
    color: var(--white);
    font-weight: 600;
    padding: var(--space-4);
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

.table-custom td {
    padding: var(--space-4);
    color: var(--gray-200);
    border-bottom: 1px solid rgba(255, 255, 255, 0.03);
    vertical-align: middle;
}

.table-custom tr:last-child td {
    border-bottom: none;
}

.table-custom tr:hover td {
    background-color: rgba(255, 255, 255, 0.02);
}

.code-text {
    font-family: monospace;
    background: var(--primary-900);
    padding: var(--space-1) var(--space-2);
    border-radius: var(--radius-sm);
    color: var(--accent-blue);
    font-size: var(--font-size-xs);
}

.fw-bold { font-weight: 600; }
.text-success { color: var(--accent-green) !important; }
.text-muted { color: var(--gray-400) !important; }
.text-center { text-align: center; }

/* ==========================================================================
   MEDIA GALLERY
   ========================================================================== */
.galerie-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: var(--space-4);
}

.galerie-item {
    background: rgba(0, 0, 0, 0.15);
    border: 1px solid rgba(255, 255, 255, 0.04);
    border-radius: var(--radius-md);
    padding: var(--space-2);
    display: flex;
    flex-direction: column;
}

.media-wrapper {
    position: relative;
    width: 100%;
    padding-top: 56.25%; /* Ratio 16:9 */
    border-radius: var(--radius-sm);
    overflow: hidden;
    background-color: var(--primary-900);
}

.media-wrapper img, 
.media-wrapper video {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    border: none;
}

.media-caption {
    font-size: var(--font-size-xs);
    color: var(--gray-300);
    margin-top: var(--space-2);
    text-align: center;
    font-weight: 500;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* ==========================================================================
   COMMENTS & FEEDBACKS SECTION
   ========================================================================== */
.comments-list {
    display: flex;
    flex-direction: column;
    gap: var(--space-3);
}

.comment-item {
    background: rgba(0, 0, 0, 0.15);
    border: 1px solid rgba(255, 255, 255, 0.04);
    border-radius: var(--radius-md);
    padding: var(--space-4);
    display: flex;
    justify-content: space-between;
    gap: var(--space-4);
    align-items: flex-start;
}

.comment-main-content {
    flex: 1;
}

.comment-header {
    display: flex;
    align-items: center;
    gap: var(--space-3);
    margin-bottom: var(--space-2);
    flex-wrap: wrap;
}

.comment-author {
    color: var(--white);
    font-weight: 600;
    font-size: var(--font-size-sm);
}

.comment-date {
    font-size: var(--font-size-xs);
    color: var(--gray-400);
}

.comment-message {
    color: var(--gray-200);
    font-size: var(--font-size-sm);
    white-space: pre-line;
}

.comment-rating {
    display: inline-block;
    margin-top: var(--space-2);
    font-size: var(--font-size-xs);
    background: rgba(46, 134, 222, 0.1);
    color: var(--accent-blue);
    padding: var(--space-1) var(--space-2);
    border-radius: var(--radius-sm);
    border: 1px solid rgba(46, 134, 222, 0.2);
}

.comment-actions {
    opacity: 0.4;
    transition: opacity var(--transition-fast);
}

.comment-item:hover .comment-actions {
    opacity: 1;
}

/* ==========================================================================
   RESPONSIVE DESIGN (Media Queries)
   ========================================================================== */

/* Écrans intermédiaires (Tablettes & Petits PC portables) */
@media (max-width: 992px) {
    .event-grid-layout {
        grid-template-columns: 1fr; /* Bascule la section latérale en dessous */
    }
    
    body {
        padding: var(--space-4);
    }
}

/* Mobiles (Smartphones) */
@media (max-width: 576px) {
    :root {
        --space-5: 1rem; /* Allege un peu le padding sur mobile */
    }

    body {
        padding: var(--space-3);
    }

    .page-header-container {
        flex-direction: column;
        align-items: flex-start;
        gap: var(--space-3);
    }

    .page-header-container .btn {
        width: 100%;
    }

    .section-header-actions {
        flex-direction: column;
        align-items: flex-start;
        gap: var(--space-2);
    }

    .section-header-actions .btn {
        width: 100%;
    }

    .artiste-card {
        padding: var(--space-3);
    }

    .comment-item {
        flex-direction: column;
        gap: var(--space-3);
    }

    .comment-actions {
        width: 100%;
        opacity: 1;
    }

    .comment-actions .btn {
        width: 100%;
        text-align: center;
    }
    
    /* Transformation des tableaux en liste cartes lisibles sur petit mobile */
    .table-responsive {
        border: none;
        background: transparent;
    }
    
    /* Optionnel mais ultra-pro si l'écran est minuscule (< 450px) */
    .galerie-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="page-header-container">
    <div class="page-header-title">
        <h2><?= htmlspecialchars($event['nom_evenement']) ?></h2>
        <span class="badge badge-primary"><?= htmlspecialchars($event['type_evenement']) ?></span>
    </div>
    <a href="evenements.php" class="btn btn-secondary">
        <i>←</i> Retour à la liste
    </a>
</div>

<!-- Grid Principale : Détails de l'événement et Statistiques -->
<div class="event-grid-layout">
    
    <!-- Section principale des détails -->
    <section class="event-card main-details-card">
        <h3><i class="icon">ℹ️</i> Informations Générales</h3>
        <hr>
        <div class="info-list">
            <p><strong>📅 Date & Heure :</strong> <?= date('d/m/Y à H:i', strtotime($event['event_start'])) ?></p>
            <p><strong>📍 Lieu :</strong> <?= htmlspecialchars($event['lieu'] ?? 'Non précisé') ?></p>
            <p><strong>🎫 Prix unitaire :</strong> <span class="text-success fw-bold"><?= number_format($event['prix_ticket'], 0, ',', ' ') ?> FCFA</span></p>
            <p><strong>🎓 Année Académique :</strong> <?= htmlspecialchars($event['academic_year'] ?? 'N/A') ?></p>
            <p><strong>👤 Organisé par :</strong> <?= htmlspecialchars(($event['admin_prenom'] ?? '') . ' ' . ($event['admin_nom'] ?? 'Administrateur')) ?></p>
        </div>
        <div class="event-description mt-3">
            <h5>Description</h5>
            <p><?= nl2br(htmlspecialchars($event['description'] ?? 'Aucune description fournie.')) ?></p>
        </div>
    </section>

    <!-- Section statistiques rapides -->
    <section class="event-card stats-card">
        <h3><i class="icon">📊</i> Vue d'ensemble</h3>
        <hr>
        <div class="stat-box">
            <span class="stat-number"><?= count($participants) ?></span>
            <span class="stat-label">Inscrits / Participants</span>
        </div>
        <div class="stat-box">
            <span class="stat-number"><?= $totalTicketsVendus ?></span>
            <span class="stat-label">Tickets Émis</span>
        </div>
        <div class="stat-box success">
            <span class="stat-number"><?= number_format($recetteTotale, 0, ',', ' ') ?></span>
            <span class="stat-label">Recettes (FCFA)</span>
        </div>
    </section>
</div>

<!-- Section Artistes Invités -->
<section class="event-card mt-4">
    <div class="section-header-actions">
        <h3><i class="icon">🎤</i> Artistes Invités</h3>
        <a href="ajouter_artiste.php?id_event=<?= $eventId ?>" class="btn btn-success btn-sm">+ Ajouter un artiste</a>
    </div>
    <hr>
    <?php if ($artistes): ?>
        <div class="artistes-grid">
            <?php foreach ($artistes as $artiste): ?>
                <div class="artiste-card">
                    <div class="artiste-photo">
                        <?php $photoPath = "../uploads/artistes/" . ($artiste['photo'] ?: "default.png"); ?>
                        <img src="<?= htmlspecialchars($photoPath) ?>" alt="Photo de <?= htmlspecialchars($artiste['nom_artiste']) ?>">
                    </div>
                    <div class="artiste-info">
                        <h4>
                            <?= htmlspecialchars($artiste['nom_artiste']) ?>
                            <?php if (!empty($artiste['pseudonyme'])): ?>
                                <small class="text-muted">"<?= htmlspecialchars($artiste['pseudonyme']) ?>"</small>
                            <?php endif; ?>
                        </h4>
                        <span class="badge badge-secondary"><?= htmlspecialchars($artiste['role'] ?? 'Artiste') ?></span>
                        
                        <?php if (!empty($artiste['description'])): ?>
                            <p class="artiste-desc"><?= nl2br(htmlspecialchars($artiste['description'])) ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="artiste-actions">
                        <a href="modifier_artiste.php?id_event=<?= $eventId ?>&nom=<?= urlencode($artiste['nom_artiste']) ?>" class="btn btn-warning btn-sm">Modifier</a>
                        <a href="supprimer_artiste.php?id_event=<?= $eventId ?>&nom=<?= urlencode($artiste['nom_artiste']) ?>" class="btn btn-danger btn-sm" onclick="return confirm('Supprimer cet artiste ?');">Supprimer</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p class="text-muted text-center py-3">Aucun artiste invité pour le moment.</p>
    <?php endif; ?>
</section>

<!-- Section Tableaux (Participants & Tickets) -->
<div class="event-tabs-container mt-4">
    
    <!-- Liste des Participants -->
    <section class="event-card">
        <h3><i class="icon">👥</i> Liste des Inscriptions</h3>
        <hr>
        <?php if ($participants): ?>
            <div class="table-responsive">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Nom & Prénom</th>
                            <th>Type/Statut</th>
                            <th>Présence</th>
                            <th>Date d'inscription</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($participants as $i => $p): ?>
                            <tr>
                                <td><?= $i+1 ?></td>
                                <td class="fw-bold">
                                    <?= htmlspecialchars($p['etu_nom'] ?? $p['ext_name'] ?? 'Anonyme') ?>
                                    <?= isset($p['etu_prenom']) ? ' ' . htmlspecialchars($p['etu_prenom']) : '' ?>
                                </td>
                                <td><span class="badge <?= $p['statut'] === 'externe' ? 'badge-warning' : 'badge-info' ?>"><?= htmlspecialchars($p['statut']) ?></span></td>
                                <td>
                                    <span class="badge <?= $p['is_checked_in'] ? 'badge-success' : 'badge-danger' ?>">
                                        <?= $p['is_checked_in'] ? '✔ Présent' : '❌ Absent' ?>
                                    </span>
                                </td>
                                <td class="text-muted"><?= date('d/m/Y H:i', strtotime($p['date_participation'])) ?></td>
                                <td>
                                    <a href="supprimer_participant.php?id_event=<?= $eventId ?>&id_participant=<?= $p['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Supprimer ce participant ?');">Retirer</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-muted text-center py-3">Aucun participant inscrit.</p>
        <?php endif; ?>
    </section>

    <!-- Liste des Tickets émis -->
    <section class="event-card mt-4">
        <h3><i class="icon">🎫</i> Gestion des Tickets Émis</h3>
        <hr>
        <?php if ($tickets): ?>
            <div class="table-responsive">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Détenteur</th>
                            <th>Code Ticket</th>
                            <th>Montant Payé</th>
                            <th>Statut Paiement</th>
                            <th>Validation</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tickets as $i => $t): ?>
                            <tr>
                                <td><?= $i+1 ?></td>
                                <td class="fw-bold">
                                    <?= htmlspecialchars($t['etu_nom'] ?? $t['ext_name'] ?? 'Anonyme') ?>
                                    <?= isset($t['etu_prenom']) ? ' ' . htmlspecialchars($t['etu_prenom']) : '' ?>
                                </td>
                                <td class="code-text"><?= htmlspecialchars($t['code_ticket']) ?></td>
                                <td class="text-success fw-bold"><?= number_format($t['amount'], 0, ',', ' ') ?> FCFA</td>
                                <td><span class="badge badge-success"><?= htmlspecialchars($t['statut']) ?></span></td>
                                <td>
                                    <span class="badge <?= $t['is_checked_in'] ? 'badge-success' : 'badge-danger' ?>">
                                        <?= $t['is_checked_in'] ? 'Scanné' : 'Non validé' ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-muted text-center py-3">Aucun ticket généré.</p>
        <?php endif; ?>
    </section>
</div>

<!-- Section Galerie Média -->
<section class="event-card mt-4">
    <h3><i class="icon">🖼️</i> Galerie Média</h3>
    <hr>
    <?php if ($galerie): ?>
        <div class="galerie-grid">
            <?php foreach ($galerie as $g): ?>
                <div class="galerie-item">
                    <div class="media-wrapper">
                        <?php if ($g['file_type'] === 'image'): ?>
                            <img src="../uploads/<?= htmlspecialchars($g['file_path']) ?>" alt="<?= htmlspecialchars($g['caption'] ?? 'Image galerie') ?>">
                        <?php elseif ($g['file_type'] === 'video'): ?>
                            <video controls>
                                <source src="../uploads/<?= htmlspecialchars($g['file_path']) ?>" type="video/mp4">
                            </video>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($g['caption'])): ?>
                        <p class="media-caption"><?= htmlspecialchars($g['caption']) ?></p>
                    <?php endif; ?>
                    <a href="delete_galerie.php?id=<?= $g['id'] ?>&event=<?= $eventId ?>" class="btn btn-danger btn-sm btn-block mt-2" onclick="return confirm('Supprimer cet élément ?');">Supprimer</a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p class="text-muted text-center py-3">La galerie est vide.</p>
    <?php endif; ?>
</section>

<!-- Section Commentaires et Feedbacks -->
<section class="event-card mt-4 mb-5">
    <h3><i class="icon">💬</i> Feedbacks & Commentaires</h3>
    <hr>
    <?php if ($comments): ?>
        <div class="comments-list">
            <?php foreach ($comments as $c): ?>
                <div class="comment-item">
                    <div class="comment-main-content">
                        <div class="comment-header">
                            <span class="comment-author">
                                <?= $c['user_type'] === 'admin'
                                    ? '<span class="badge badge-primary">Admin</span> ' . htmlspecialchars(($c['admin_prenom'] ?? '') . ' ' . ($c['admin_nom'] ?? ''))
                                    : htmlspecialchars(($c['etu_prenom'] ?? '') . ' ' . ($c['etu_nom'] ?? 'Anonyme')) ?>
                            </span>
                            <span class="comment-date"><?= date('d/m/Y à H:i', strtotime($c['date_commentaire'])) ?></span>
                        </div>
                        <p class="comment-message"><?= nl2br(htmlspecialchars($c['message'])) ?></p>
                        
                        <?php if ($c['note'] !== null): ?>
                            <div class="comment-rating">Note d'évaluation : <strong><?= $c['note'] ?> / 10</strong></div>
                        <?php endif; ?>
                    </div>
                    <div class="comment-actions">
                        <a href="delete_comment.php?id=<?= $c['id_commentaire'] ?>&event=<?= $eventId ?>" class="btn btn-danger btn-sm text-white" onclick="return confirm('Supprimer ce commentaire ?');">Supprimer</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p class="text-muted text-center py-3">Aucun commentaire publié pour cet événement.</p>
    <?php endif; ?>
</section>

<?php
$content = ob_get_clean();
include "../layout.php";
?>