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





<style>
.page-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 20px;
}
.card {
    background: #fff;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 25px;
}
table {
    width: 100%;
    border-collapse: collapse;
}
th, td {
    padding: 8px 12px;
    border: 1px solid #ddd;
}
.btn-success {
    background: #28a745;
    color: #fff;
    border: none;
    padding: 6px 10px;
    border-radius: 4px;
}

.gallery {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 15px;
}
.media-box img,
.media-box video {
    width: 100%;
    border-radius: 6px;
}
.media-box {
    background: #f9f9f9;
    padding: 10px;
    border-radius: 8px;
    text-align: center;
}
.comment {
    border-bottom: 1px solid #ddd;
    padding: 10px 0;
}
.btn-danger {
    background: #dc3545;
    color: #fff;
    border: none;
    padding: 6px 10px;
    border-radius: 4px;
}

</style>

<?php
$content = ob_get_clean();
include "../layout.php";
?>
