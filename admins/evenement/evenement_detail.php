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

// Contenu à injecter dans le layout
ob_start();
?>

<div class="page-header">
    <h2><?= htmlspecialchars($event['nom_evenement']) ?></h2>
    <a href="evenements.php" class="btn btn-secondary">Retour à la liste</a>
</div>

<div class="event-details">

    <section class="event-main">
        <h3>Détails de l'événement</h3>
        <p><strong>Type :</strong> <?= htmlspecialchars($event['type_evenement']) ?></p>
        <p><strong>Date :</strong> <?= date('d/m/Y H:i', strtotime($event['event_start'])) ?></p>
        <p><strong>Lieu :</strong> <?= htmlspecialchars($event['lieu'] ?? 'Non précisé') ?></p>
        <p><strong>Prix ticket :</strong> <?= number_format($event['prix_ticket'],2) ?> FCFA</p>
        <p><strong>Année académique :</strong> <?= htmlspecialchars($event['academic_year']) ?></p>
        <p><strong>Créé par :</strong> <?= htmlspecialchars($event['admin_prenom'] . ' ' . $event['admin_nom']) ?></p>
        <p><strong>Description :</strong><br><?= nl2br(htmlspecialchars($event['description'])) ?></p>
    </section>

    <section class="event-artistes">
        <h3>Artistes invités</h3>
        <?php if ($artistes): ?>
            <ul>
                <?php foreach ($artistes as $artiste): ?>
                    <li>
                        <?= htmlspecialchars($artiste['nom_artiste']) ?>
                        <?php if ($artiste['pseudonyme']): ?>(<?= htmlspecialchars($artiste['pseudonyme']) ?>)<?php endif; ?>
                        - <?= htmlspecialchars($artiste['role']) ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p>Aucun artiste invité.</p>
        <?php endif; ?>
    </section>

    <section class="event-participants">
        <h3>Participants</h3>
        <?php if ($participants): ?>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Nom / Participant</th>
                        <th>Statut</th>
                        <th>Présence</th>
                        <th>Date participation</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($participants as $i => $p): ?>
                        <tr>
                            <td><?= $i+1 ?></td>
                            <td>
                                <?= htmlspecialchars($p['etu_nom'] ?? $p['ext_name'] ?? 'Anonyme') ?>
                                <?= isset($p['etu_prenom']) ? ' ' . htmlspecialchars($p['etu_prenom']) : '' ?>
                            </td>
                            <td><?= htmlspecialchars($p['statut']) ?></td>
                            <td><?= $p['is_checked_in'] ? '✔' : '❌' ?></td>
                            <td><?= date('d/m/Y H:i', strtotime($p['date_participation'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>Aucun participant.</p>
        <?php endif; ?>
    </section>

    <section class="event-tickets">
        <h3>Tickets</h3>
        <?php if ($tickets): ?>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Participant</th>
                        <th>Code Ticket</th>
                        <th>Montant</th>
                        <th>Statut</th>
                        <th>Présence</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tickets as $i => $t): ?>
                        <tr>
                            <td><?= $i+1 ?></td>
                            <td>
                                <?= htmlspecialchars($t['etu_nom'] ?? $t['ext_name'] ?? 'Anonyme') ?>
                                <?= isset($t['etu_prenom']) ? ' ' . htmlspecialchars($t['etu_prenom']) : '' ?>
                            </td>
                            <td><?= htmlspecialchars($t['code_ticket']) ?></td>
                            <td><?= number_format($t['amount'],2) ?> FCFA</td>
                            <td><?= htmlspecialchars($t['statut']) ?></td>
                            <td><?= $t['is_checked_in'] ? '✔' : '❌' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>Aucun ticket émis.</p>
        <?php endif; ?>
    </section>

    <section class="event-galerie">
        <h3>Galerie</h3>
        <?php if ($galerie): ?>
            <div class="galerie-grid">
                <?php foreach ($galerie as $g): ?>
                    <?php if ($g['file_type'] === 'image'): ?>
                        <img src="../uploads/<?= htmlspecialchars($g['file_path']) ?>" alt="<?= htmlspecialchars($g['caption'] ?? '') ?>" class="galerie-image">
                    <?php elseif ($g['file_type'] === 'video'): ?>
                        <video controls class="galerie-video">
                            <source src="../uploads/<?= htmlspecialchars($g['file_path']) ?>" type="video/mp4">
                        </video>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p>Aucune image ou vidéo.</p>
        <?php endif; ?>
    </section>

    <section class="event-comments">
        <h3>Commentaires</h3>
        <?php if ($comments): ?>
            <ul>
                <?php foreach ($comments as $c): ?>
                    <li>
                        <strong>
                            <?= $c['user_type'] === 'admin' 
                                ? htmlspecialchars($c['admin_prenom'].' '.$c['admin_nom'])
                                : htmlspecialchars($c['etu_prenom'].' '.$c['etu_nom'])
                            ?>
                        </strong> :
                        <?= nl2br(htmlspecialchars($c['message'])) ?>
                        <?php if ($c['note'] !== null): ?>
                            <em>(Note: <?= $c['note'] ?>/10)</em>
                        <?php endif; ?>
                        <small><?= date('d/m/Y H:i', strtotime($c['date_commentaire'])) ?></small>
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
