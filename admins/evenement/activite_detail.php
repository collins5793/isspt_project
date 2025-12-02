<?php
session_start();
require_once "../../includes/db.php";

// Vérification de l'ID passé en GET
$activiteId = $_GET['id'] ?? null;
if (!$activiteId) {
    header("Location: activites.php");
    exit;
}

// Récupération des infos de l'activité
$stmt = $pdo->prepare("
    SELECT act.*, ay.label AS academic_year, a.nom AS admin_nom, a.prenom AS admin_prenom
    FROM activites act
    LEFT JOIN academic_years ay ON act.academic_year_id = ay.id
    LEFT JOIN administrateurs a ON act.cree_par = a.id_admin
    WHERE act.id_activite = ?
");
$stmt->execute([$activiteId]);
$activite = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$activite) {
    die("Activité introuvable.");
}

// Récupération des participants
$participants = $pdo->prepare("
    SELECT p.*, e.nom AS etu_nom, e.prenom AS etu_prenom
    FROM participants_activites p
    LEFT JOIN etudiants e ON p.id_etudiant = e.id_etudiant
    WHERE p.id_activite = ?
");
$participants->execute([$activiteId]);
$participants = $participants->fetchAll(PDO::FETCH_ASSOC);

// Récupération de la galerie
$galerie = $pdo->prepare("SELECT * FROM galerie WHERE activity_id = ?");
$galerie->execute([$activiteId]);
$galerie = $galerie->fetchAll(PDO::FETCH_ASSOC);

// Récupération des commentaires
$comments = $pdo->prepare("
    SELECT c.*, e.nom AS etu_nom, e.prenom AS etu_prenom, a.nom AS admin_nom, a.prenom AS admin_prenom
    FROM commentaires c
    LEFT JOIN etudiants e ON c.id_etudiant = e.id_etudiant
    LEFT JOIN administrateurs a ON c.id_admin = a.id_admin
    WHERE c.activity_id = ?
    ORDER BY c.date_commentaire DESC
");
$comments->execute([$activiteId]);
$comments = $comments->fetchAll(PDO::FETCH_ASSOC);

// Contenu à injecter dans le layout
ob_start();
?>

<div class="page-header">
    <h2><?= htmlspecialchars($activite['nom_activite']) ?></h2>
    <a href="activites.php" class="btn btn-secondary">Retour à la liste</a>
</div>

<div class="event-details">

    <section class="event-main">
        <h3>Détails de l'activité</h3>
        <p><strong>Année académique :</strong> <?= htmlspecialchars($activite['academic_year']) ?></p>
        <p><strong>Créé par :</strong> <?= htmlspecialchars($activite['admin_prenom'] . ' ' . $activite['admin_nom']) ?></p>
        <p><strong>Description :</strong><br><?= nl2br(htmlspecialchars($activite['description'])) ?></p>
        <p><strong>Conditions :</strong><br><?= nl2br(htmlspecialchars($activite['conditions'])) ?></p>
    </section>

    <section class="event-participants">
        <h3>Participants</h3>
        <?php if ($participants): ?>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Nom / Participant</th>
                        <th>Rôle</th>
                        <th>Statut</th>
                        <th>Performance</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($participants as $i => $p): ?>
                        <tr>
                            <td><?= $i+1 ?></td>
                            <td><?= htmlspecialchars($p['etu_nom'] . ' ' . $p['etu_prenom']) ?></td>
                            <td><?= htmlspecialchars($p['role'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($p['statut']) ?></td>
                            <td><?= htmlspecialchars($p['performance'] ?? '-') ?></td>
                            <td>
                                <a href="supprimer_participant.php?id_activite=<?= $activiteId ?>&id_participant=<?= $p['id_participation'] ?>" 
                                   class="btn btn-danger btn-sm"
                                   onclick="return confirm('Supprimer ce participant ?');">
                                   Supprimer
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>Aucun participant.</p>
        <?php endif; ?>
    </section>

    <section class="event-galerie">
        <h3>Galerie</h3>
        <?php if ($galerie): ?>
            <div class="galerie-grid">
                <?php foreach ($galerie as $g): ?>
                    <div class="galerie-item">
                        <?php if ($g['file_type'] === 'image'): ?>
                            <img src="../uploads/<?= htmlspecialchars($g['file_path']) ?>" alt="<?= htmlspecialchars($g['caption'] ?? '') ?>" class="galerie-image">
                        <?php elseif ($g['file_type'] === 'video'): ?>
                            <video controls class="galerie-video">
                                <source src="../uploads/<?= htmlspecialchars($g['file_path']) ?>" type="video/mp4">
                            </video>
                        <?php endif; ?>

                        <a href="delete_galerie.php?id=<?= $g['id_media'] ?>&activity=<?= $activiteId ?>"
                           class="btn btn-danger btn-sm mt-2"
                           onclick="return confirm('Supprimer cet élément ?');">
                            Supprimer
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p>Aucune image ou vidéo.</p>
        <?php endif; ?>
    </section>

    <section class="event-comments">
        <h3>Commentaires</h3>
        <?php if ($comments): ?>
            <ul class="list-group">
                <?php foreach ($comments as $c): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-start">
                        <div>
                            <strong>
                                <?= $c['user_type'] === 'admin'
                                    ? htmlspecialchars($c['admin_prenom'].' '.$c['admin_nom'])
                                    : htmlspecialchars($c['etu_prenom'].' '.$c['etu_nom']) ?>
                            </strong> :
                            <?= nl2br(htmlspecialchars($c['message'])) ?>
                            <?php if ($c['note'] !== null): ?>
                                <em>(Note: <?= $c['note'] ?>/10)</em>
                            <?php endif; ?>
                            <br>
                            <small><?= date('d/m/Y H:i', strtotime($c['date_commentaire'])) ?></small>
                        </div>
                        <a href="delete_comment.php?id=<?= $c['id_commentaire'] ?>&activity=<?= $activiteId ?>"
                           class="btn btn-danger btn-sm"
                           onclick="return confirm('Supprimer ce commentaire ?');">
                            Supprimer
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p>Aucun commentaire.</p>
        <?php endif; ?>
    </section>

</div>

<style>
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}
.event-details section {
    margin-bottom: 30px;
    background: #fff;
    padding: 15px;
    border-radius: 8px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.05);
}
.event-details table {
    width: 100%;
    border-collapse: collapse;
}
.event-details th, .event-details td {
    padding: 8px 12px;
    border: 1px solid #ddd;
}
.galerie-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 10px;
}
.galerie-image, .galerie-video {
    width: 100%;
    border-radius: 6px;
}
</style>

<?php
$content = ob_get_clean();
include "../layout.php";
