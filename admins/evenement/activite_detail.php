<?php
session_start();
require_once "../../includes/db.php";

/* =========================
   Vérification ID
========================= */
$activiteId = $_GET['id'] ?? null;
if (!$activiteId) {
    header("Location: activites.php");
    exit;
}

/* =========================
   Validation inscription
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['valider_inscription'])) {

    $idInscription = (int) $_POST['id_inscription'];

    // Récupérer l'inscription
    $stmt = $pdo->prepare("
        SELECT * FROM inscriptions_activites
        WHERE id_inscription = ? AND id_activite = ?
    ");
    $stmt->execute([$idInscription, $activiteId]);
    $inscription = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($inscription && $inscription['statut'] === 'en_attente') {

        // Vérifier si déjà participant
        $check = $pdo->prepare("
            SELECT COUNT(*) FROM participants_activites
            WHERE id_activite = ? AND id_etudiant = ?
        ");
        $check->execute([$activiteId, $inscription['id_etudiant']]);

        if ($check->fetchColumn() == 0) {

            // Accepter inscription
            $pdo->prepare("
                UPDATE inscriptions_activites
                SET statut = 'acceptee'
                WHERE id_inscription = ?
            ")->execute([$idInscription]);

            // Ajouter comme participant
            $pdo->prepare("
                INSERT INTO participants_activites
                (id_activite, id_etudiant, academic_year_id)
                VALUES (?, ?, (
                    SELECT academic_year_id FROM activites WHERE id_activite = ?
                ))
            ")->execute([
                $activiteId,
                $inscription['id_etudiant'],
                $activiteId
            ]);
        }
    }

    header("Location: activite_detail.php?id=".$activiteId);
    exit;
}

/* =========================
   SUPPRESSION MEDIA
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_media'])) {

    $mediaId = (int) $_POST['id_media'];

    $stmt = $pdo->prepare("
        SELECT file_path FROM galerie
        WHERE id_media = ? AND activity_id = ?
    ");
    $stmt->execute([$mediaId, $activiteId]);
    $media = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($media) {
        // Supprimer fichier
        if (file_exists($media['file_path'])) {
            unlink($media['file_path']);
        }

        // Supprimer en base
        $pdo->prepare("DELETE FROM galerie WHERE id_media = ?")
            ->execute([$mediaId]);
    }

    header("Location: activite_detail.php?id=".$activiteId);
    exit;
}

/* =========================
   SUPPRESSION COMMENTAIRE
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_comment'])) {

    $commentId = (int) $_POST['id_commentaire'];

    $stmt = $pdo->prepare("
        DELETE FROM commentaires
        WHERE id_commentaire = ? AND activity_id = ?
    ");
    $stmt->execute([$commentId, $activiteId]);

    header("Location: activite_detail.php?id=".$activiteId);
    exit;
}




/* =========================
   Infos activité
========================= */
$stmt = $pdo->prepare("
    SELECT a.*, ay.label AS academic_year,
           ad.nom AS admin_nom, ad.prenom AS admin_prenom
    FROM activites a
    LEFT JOIN academic_years ay ON a.academic_year_id = ay.id
    LEFT JOIN administrateurs ad ON a.cree_par = ad.id_admin
    WHERE a.id_activite = ?
");
$stmt->execute([$activiteId]);
$activite = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$activite) {
    die("Activité introuvable.");
}

/* =========================
   Inscriptions
========================= */
$inscriptions = $pdo->prepare("
    SELECT i.*, e.nom, e.prenom
    FROM inscriptions_activites i
    LEFT JOIN etudiants e ON i.id_etudiant = e.id_etudiant
    WHERE i.id_activite = ?
    ORDER BY i.date_inscription DESC
");
$inscriptions->execute([$activiteId]);
$inscriptions = $inscriptions->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   Participants
========================= */
$participants = $pdo->prepare("
    SELECT p.*, e.nom, e.prenom
    FROM participants_activites p
    LEFT JOIN etudiants e ON p.id_etudiant = e.id_etudiant
    WHERE p.id_activite = ?
");
$participants->execute([$activiteId]);
$participants = $participants->fetchAll(PDO::FETCH_ASSOC);


/* =========================
   GALERIE
========================= */
$galerie = $pdo->prepare("
    SELECT * FROM galerie
    WHERE activity_id = ?
    ORDER BY uploaded_at DESC
");
$galerie->execute([$activiteId]);
$galerie = $galerie->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   COMMENTAIRES
========================= */
$commentaires = $pdo->prepare("
    SELECT c.*, 
           e.nom AS etu_nom, e.prenom AS etu_prenom,
           a.nom AS admin_nom, a.prenom AS admin_prenom
    FROM commentaires c
    LEFT JOIN etudiants e ON c.id_etudiant = e.id_etudiant
    LEFT JOIN administrateurs a ON c.id_admin = a.id_admin
    WHERE c.activity_id = ?
    ORDER BY c.date_commentaire DESC
");
$commentaires->execute([$activiteId]);
$commentaires = $commentaires->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>
<style>
    /* ==========================================================================
   Variables & Configuration Globale
   ========================================================================== */
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

    /* Base Layout Extra Tokens */
    --bg-main: #060018;
    --card-bg: #110933;
    --card-border: rgba(255, 255, 255, 0.06);
    --text-muted: #8a94a6;
}

/* Base resets pour intégration propre */
body {
    background-color: var(--bg-main);
    color: var(--gray-100);
    font-family: var(--font-primary);
    margin: 0;
    padding: var(--space-5);
    line-height: 1.5;
    -webkit-font-smoothing: antialiased;
}

/* Container principal de l'application (ajuste l'espace selon la présence de ta sidebar) */
.main-content, main, .content-wrapper {
    max-width: 1200px;
    margin: 0 auto;
    width: 100%;
    box-sizing: border-box;
}

/* ==========================================================================
   Header de la Page
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
    font-size: var(--font-size-xl);
    font-weight: 700;
    color: var(--white);
    margin: 0;
    letter-spacing: -0.02em;
}

/* ==========================================================================
   Composants Cartes (Sections)
   ========================================================================== */
.card {
    background: var(--card-bg);
    border: 1px solid var(--card-border);
    border-radius: var(--radius-lg);
    padding: var(--space-5);
    margin-bottom: var(--space-5);
    box-shadow: var(--shadow-md);
    transition: transform var(--transition-fast), box-shadow var(--transition-fast);
}

.card:hover {
    box-shadow: var(--shadow-lg);
}

.card h3 {
    font-size: var(--font-size-lg);
    font-weight: 600;
    color: var(--white);
    margin-top: 0;
    margin-bottom: var(--space-4);
    position: relative;
    padding-left: var(--space-3);
}

.card h3::before {
    content: '';
    position: absolute;
    left: 0;
    top: 15%;
    height: 70%;
    width: 4px;
    background: var(--accent-blue);
    border-radius: var(--radius-full);
}

.card p {
    margin: 0 0 var(--space-3) 0;
    color: var(--gray-200);
    font-size: var(--font-size-md);
}

.card p strong {
    color: var(--gray-400);
    font-weight: 500;
    display: inline-block;
    min-width: 160px;
}

.card p:last-child {
    margin-bottom: 0;
}

/* Section de description spécifique */
.card p br + text, 
.card p:has(br) {
    line-height: 1.7;
    color: var(--gray-100);
}

/* ==========================================================================
   Boutons & Formulaires
   ========================================================================== */
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-family: var(--font-primary);
    font-size: var(--font-size-sm);
    font-weight: 600;
    padding: var(--space-3) var(--space-5);
    border-radius: var(--radius-md);
    border: 1px solid transparent;
    cursor: pointer;
    text-decoration: none;
    transition: all var(--transition-fast);
    gap: var(--space-2);
    white-space: nowrap;
}

.btn-sm {
    padding: var(--space-2) var(--space-3);
    font-size: var(--font-size-xs);
    border-radius: var(--radius-sm);
}

.btn-primary {
    background-color: var(--accent-blue);
    color: var(--white);
}
.btn-primary:hover {
    background-color: #2475c4;
    box-shadow: 0 0 12px rgba(46, 134, 222, 0.4);
}

.btn-secondary {
    background-color: var(--primary-600);
    color: var(--gray-200);
    border: 1px solid rgba(255, 255, 255, 0.1);
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
    background-color: #0e9572;
    box-shadow: 0 0 12px rgba(16, 172, 132, 0.4);
}

.btn-danger {
    background-color: var(--accent-red);
    color: var(--white);
}
.btn-danger:hover {
    background-color: #ee3545;
    box-shadow: 0 0 12px rgba(255, 71, 87, 0.4);
}

form {
    display: inline-block;
    margin: 0;
}

/* ==========================================================================
   Tableaux (Inscriptions & Participants)
   ========================================================================== */
/* Wrapper pour forcer le scroll horizontal proprement sans casser le layout */
.card:has(table) {
    overflow-x: auto;
    scrollbar-width: thin;
    scrollbar-color: var(--primary-600) transparent;
}

table {
    width: 100%;
    border-collapse: collapse;
    text-align: left;
    margin-top: var(--space-2);
    font-size: var(--font-size-sm);
    min-width: 600px; /* Assure une structure lisible même sur mobile */
}

th {
    background-color: rgba(255, 255, 255, 0.03);
    color: var(--gray-400);
    font-weight: 600;
    text-transform: uppercase;
    font-size: var(--font-size-xs);
    letter-spacing: 0.05em;
    padding: var(--space-3) var(--space-4);
    border-bottom: 2px solid var(--primary-600);
}

td {
    padding: var(--space-4);
    border-bottom: 1px solid rgba(255, 255, 255, 0.04);
    color: var(--gray-200);
    vertical-align: middle;
}

tr:hover td {
    background-color: rgba(255, 255, 255, 0.01);
    color: var(--white);
}

tr:last-child td {
    border-bottom: none;
}

/* On stylise les indices de lignes */
td:first-child {
    color: var(--text-muted);
    font-weight: 600;
    width: 40px;
}

/* ==========================================================================
   Galerie Média
   ========================================================================== */
.gallery {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
    gap: var(--space-4);
    margin-top: var(--space-3);
}

.media-box {
    background: rgba(0, 0, 0, 0.2);
    border: 1px solid rgba(255, 255, 255, 0.04);
    border-radius: var(--radius-md);
    padding: var(--space-3);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    gap: var(--space-3);
    position: relative;
    overflow: hidden;
}

.media-box img, 
.media-box video {
    width: 100%;
    height: 160px;
    object-fit: cover;
    border-radius: var(--radius-sm);
    background-color: var(--primary-900);
}

.media-box p {
    font-size: var(--font-size-xs);
    color: var(--gray-300);
    margin: 0;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
}

.media-box .btn-danger {
    width: 100%;
    margin-top: auto;
}

/* ==========================================================================
   Zone de Commentaires
   ========================================================================== */
.comment {
    background: rgba(255, 255, 255, 0.02);
    border-left: 3px solid var(--primary-600);
    border-radius: 0 var(--radius-md) var(--radius-md) 0;
    padding: var(--space-4);
    margin-bottom: var(--space-4);
    position: relative;
    transition: background var(--transition-fast);
}

.comment:hover {
    background: rgba(255, 255, 255, 0.03);
}

.comment:last-of-type {
    margin-bottom: 0;
}

.comment strong {
    font-size: var(--font-size-sm);
    color: var(--white);
    display: inline-block;
    margin-right: var(--space-2);
}

/* Différenciation Admin / Étudiant discrète et pro */
.comment strong:contains('(Admin)') {
    color: var(--accent-blue);
}

.comment small {
    font-size: var(--font-size-xs);
    color: var(--text-muted);
    display: inline-block;
}

.comment p {
    margin: var(--space-2) 0;
    font-size: var(--font-size-md);
    color: var(--gray-100);
    word-break: break-word;
}

/* Note / Évaluation */
.comment p:has(strong) {
    font-size: var(--font-size-sm);
    color: var(--accent-blue);
    margin-bottom: var(--space-3);
}

.comment .btn-danger {
    position: absolute;
    top: var(--space-4);
    right: var(--space-4);
    opacity: 0.3; /* Reste discret tant qu'on ne passe pas dessus */
    transition: opacity var(--transition-fast);
}

.comment:hover .btn-danger {
    opacity: 1;
}

/* Message vide */
.card > p:only-of-type {
    color: var(--text-muted);
    font-style: italic;
    padding: var(--space-2) 0;
}

/* ==========================================================================
   Media Queries & Responsivité Totale
   ========================================================================== */

/* Tablettes et écrans intermédiaires */
@media (max-width: 768px) {
    body {
        padding: var(--space-3);
    }

    .card {
        padding: var(--space-4);
    }

    .card p strong {
        display: block;
        margin-bottom: var(--space-1);
    }
    
    .comment .btn-danger {
        position: relative;
        top: 0;
        right: 0;
        opacity: 1;
        margin-top: var(--space-2);
    }
}

/* Smartphones et petits écrans */
@media (max-width: 480px) {
    .page-header {
        flex-direction: column;
        align-items: flex-start;
        gap: var(--space-2);
    }

    .page-header .btn {
        width: 100%;
    }

    .gallery {
        grid-template-columns: 1fr; /* Une seule colonne sur tout petit écran */
    }

    .media-box img, 
    .media-box video {
        height: 200px; /* Légèrement plus grand pour le confort visuel sur mobile */
    }
}
</style>
<div class="page-header">
    <h2><?= htmlspecialchars($activite['nom_activite']) ?></h2>
    <a href="activites.php" class="btn btn-secondary">← Retour</a>
</div>

<!-- ================= DÉTAILS ================= -->
<section class="card">
    <h3>Détails de l’activité</h3>
    <p><strong>Année académique :</strong> <?= htmlspecialchars($activite['academic_year']) ?></p>
    <p><strong>Créée par :</strong> <?= htmlspecialchars($activite['admin_prenom'].' '.$activite['admin_nom']) ?></p>
    <p><strong>Accessibilité :</strong> <?= $activite['accessibilite'] ?></p>
    <p><strong>Type :</strong> <?= $activite['type_participation'] ?></p>

    <?php if ($activite['type_participation'] === 'equipe'): ?>
        <p><strong>Équipe :</strong>
            min <?= $activite['equipe_min'] ?> /
            max <?= $activite['equipe_max'] ?>
        </p>
    <?php endif; ?>

    <p><strong>Participants max :</strong> <?= $activite['max_participants'] ?? 'Illimité' ?></p>
    <p><strong>Date limite :</strong> <?= $activite['date_limite_inscription'] ?? '—' ?></p>
    <p><strong>Statut :</strong> <?= $activite['statut'] ?></p>

    <p><strong>Description :</strong><br>
        <?= nl2br(htmlspecialchars($activite['description'])) ?>
    </p>
</section>

<!-- ================= INSCRIPTIONS ================= -->
<section class="card">
    <h3>Inscriptions</h3>

    <?php if ($inscriptions): ?>
        <table>
            <tr>
                <th>#</th>
                <th>Étudiant</th>
                <th>Date</th>
                <th>Statut</th>
                <th>Action</th>
            </tr>

            <?php foreach ($inscriptions as $i => $ins): ?>
                <tr>
                    <td><?= $i+1 ?></td>
                    <td><?= htmlspecialchars($ins['prenom'].' '.$ins['nom']) ?></td>
                    <td><?= date('d/m/Y H:i', strtotime($ins['date_inscription'])) ?></td>
                    <td><?= $ins['statut'] ?></td>
                    <td>
                        <?php if ($ins['statut'] === 'en_attente'): ?>
                            <form method="POST">
                                <input type="hidden" name="id_inscription" value="<?= $ins['id_inscription'] ?>">
                                <button name="valider_inscription" class="btn btn-success btn-sm">
                                    Valider
                                </button>
                            </form>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php else: ?>
        <p>Aucune inscription.</p>
    <?php endif; ?>
</section>

<!-- ================= PARTICIPANTS ================= -->
<section class="card">
    <h3>Participants</h3>

    <?php if ($participants): ?>
        <table>
            <tr>
                <th>#</th>
                <th>Nom</th>
                <th>Rôle</th>
                <th>Statut</th>
                <th>Performance</th>
            </tr>

            <?php foreach ($participants as $i => $p): ?>
                <tr>
                    <td><?= $i+1 ?></td>
                    <td><?= htmlspecialchars($p['prenom'].' '.$p['nom']) ?></td>
                    <td><?= $p['role'] ?? '-' ?></td>
                    <td><?= $p['statut'] ?></td>
                    <td><?= $p['performance'] ?? '-' ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php else: ?>
        <p>Aucun participant.</p>
    <?php endif; ?>
</section>

<!-- ================= GALERIE ================= -->
<section class="card">
    <h3>Galerie</h3>

    <?php if ($galerie): ?>
        <div class="gallery">
            <?php foreach ($galerie as $g): ?>
                <div class="media-box">
                    <?php if ($g['file_type'] === 'image'): ?>
                        <img src="<?= $g['file_path'] ?>" alt="">
                    <?php else: ?>
                        <video controls>
                            <source src="<?= $g['file_path'] ?>">
                        </video>
                    <?php endif; ?>

                    <?php if ($g['caption']): ?>
                        <p><?= htmlspecialchars($g['caption']) ?></p>
                    <?php endif; ?>

                    <form method="POST" onsubmit="return confirm('Supprimer ce média ?')">
                        <input type="hidden" name="id_media" value="<?= $g['id_media'] ?>">
                        <button class="btn btn-danger btn-sm" name="delete_media">
                            Supprimer
                        </button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p>Aucun média pour cette activité.</p>
    <?php endif; ?>
</section>


<!-- ================= COMMENTAIRES ================= -->
<section class="card">
    <h3>Commentaires</h3>

    <?php if ($commentaires): ?>
        <?php foreach ($commentaires as $c): ?>
            <div class="comment">
                <strong>
                    <?= $c['user_type'] === 'admin'
                        ? $c['admin_prenom'].' '.$c['admin_nom'].' (Admin)'
                        : $c['etu_prenom'].' '.$c['etu_nom'].' (Étudiant)'
                    ?>
                </strong>

                <small><?= date('d/m/Y H:i', strtotime($c['date_commentaire'])) ?></small>

                <p><?= nl2br(htmlspecialchars($c['message'])) ?></p>

                <?php if ($c['note'] !== null): ?>
                    <p><strong>Note :</strong> <?= $c['note'] ?>/5</p>
                <?php endif; ?>

                <form method="POST" onsubmit="return confirm('Supprimer ce commentaire ?')">
                    <input type="hidden" name="id_commentaire" value="<?= $c['id_commentaire'] ?>">
                    <button class="btn btn-danger btn-sm" name="delete_comment">
                        Supprimer
                    </button>
                </form>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p>Aucun commentaire.</p>
    <?php endif; ?>
</section>


<?php
$content = ob_get_clean();
include "../layout.php";
?>
