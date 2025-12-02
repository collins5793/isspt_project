<?php
session_start();
require_once '../../includes/db.php';

// Vérifier si admin connecté
if (!isset($_SESSION['admin_id'])) {
    header('Location: connexion_admin.php');
    exit();
}

// Vérifier l'id de l'étudiant
if (!isset($_GET['id'])) {
    header('Location: liste_etudiants.php');
    exit();
}

$id_etudiant = intval($_GET['id']);

// ============================
// Infos personnelles
// ============================
$stmt = $pdo->prepare("SELECT * FROM etudiants WHERE id_etudiant = ?");
$stmt->execute([$id_etudiant]);
$etudiant = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$etudiant) {
    $_SESSION['message'] = "<div class='alert alert-warning'>Étudiant introuvable.</div>";
    header('Location: liste_etudiants.php');
    exit();
}

// ============================
// Activités de l'étudiant
// ============================
$activitesStmt = $pdo->prepare("
    SELECT a.*, ia.statut, ia.motif_refus, ay.label AS annee_scolaire
    FROM inscriptions_activites ia
    JOIN activites a ON ia.id_activite = a.id_activite
    JOIN academic_years ay ON a.academic_year_id = ay.id
    WHERE ia.id_etudiant = ?
    ORDER BY a.date_creation DESC
");
$activitesStmt->execute([$id_etudiant]);
$activites = $activitesStmt->fetchAll(PDO::FETCH_ASSOC);

// ============================
// Événements auxquels il a participé
// ============================
$evenementsStmt = $pdo->prepare("
    SELECT e.*, p.statut AS participation_statut, p.date_participation
    FROM participants_evenements p
    JOIN evenements e ON p.event_id = e.id_evenement
    WHERE p.user_id = ?
    ORDER BY e.event_start DESC
");
$evenementsStmt->execute([$id_etudiant]);
$evenements = $evenementsStmt->fetchAll(PDO::FETCH_ASSOC);

// ============================
// Équipe de foot et historique
// ============================
$teamsStmt = $pdo->prepare("
    SELECT tp.*, ft.name AS team_name, ft.coach, fs.label AS season_label
    FROM team_players tp
    JOIN football_teams ft ON tp.team_id = ft.team_id
    JOIN football_seasons fs ON tp.season_id = fs.id_season
    WHERE tp.user_id = ?
    ORDER BY fs.id_season DESC
");

$teamsStmt->execute([$id_etudiant]);
$teams = $teamsStmt->fetchAll(PDO::FETCH_ASSOC);

// ============================
// Commentaires foot
// ============================
$footCommentsStmt = $pdo->prepare("
    SELECT cf.*, a.nom AS admin_nom, a.prenom AS admin_prenom
    FROM commentaire_football cf
    LEFT JOIN administrateurs a ON cf.id_admin = a.id_admin
    WHERE cf.id_etudiant = ?
    ORDER BY cf.date_commentaire_football DESC
");
$footCommentsStmt->execute([$id_etudiant]);
$footComments = $footCommentsStmt->fetchAll(PDO::FETCH_ASSOC);

// ============================
// Affichage
// ============================
ob_start();
?>

<div class="page-header d-flex justify-content-between align-items-center mb-4">
    <h1>Détail de l'étudiant : <?= htmlspecialchars($etudiant['nom'] . ' ' . $etudiant['prenom']) ?></h1>
    <a href="liste_etudiants.php" class="btn btn-secondary">⬅ Retour à la liste</a>
</div>

<div class="row mb-4">
    <div class="col-md-4 text-center">
        <img src="<?= (!empty($etudiant['photo']) && file_exists('../uploads/photos_etudiants/'.$etudiant['photo'])) ? '../uploads/photos_etudiants/'.$etudiant['photo'] : '../assets/default/avatar.png' ?>" 
             class="img-fluid rounded mb-3" alt="Photo de <?= htmlspecialchars($etudiant['nom']) ?>">
        <ul class="list-group text-start">
            <li class="list-group-item"><strong>Matricule :</strong> <?= htmlspecialchars($etudiant['matricule']) ?></li>
            <li class="list-group-item"><strong>Email :</strong> <?= htmlspecialchars($etudiant['email']) ?></li>
            <li class="list-group-item"><strong>Téléphone :</strong> <?= htmlspecialchars($etudiant['telephone']) ?></li>
            <li class="list-group-item"><strong>Promotion :</strong> <?= htmlspecialchars($etudiant['promotion']) ?></li>
            <li class="list-group-item"><strong>Filière :</strong> <?= htmlspecialchars($etudiant['filiere']) ?></li>
            <li class="list-group-item"><strong>Statut :</strong> <?= htmlspecialchars($etudiant['statut']) ?></li>
            <li class="list-group-item"><strong>Date inscription :</strong> <?= htmlspecialchars($etudiant['date_inscription']) ?></li>
        </ul>
    </div>

    <div class="col-md-8">
        <!-- Activités -->
        <h3>Activités</h3>
        <?php if($activites): ?>
            <ul class="list-group mb-3">
                <?php foreach($activites as $act): ?>
                    <li class="list-group-item">
                        <strong><?= htmlspecialchars($act['nom_activite']) ?></strong> (<?= htmlspecialchars($act['annee_scolaire']) ?>)<br>
                        Statut: <?= htmlspecialchars($act['statut']) ?>
                        <?php if($act['motif_refus']): ?>
                            <br><em>Motif du refus: <?= htmlspecialchars($act['motif_refus']) ?></em>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p>Aucune activité inscrite.</p>
        <?php endif; ?>

        <!-- Événements -->
        <h3>Événements</h3>
        <?php if($evenements): ?>
            <ul class="list-group mb-3">
                <?php foreach($evenements as $ev): ?>
                    <li class="list-group-item">
                        <strong><?= htmlspecialchars($ev['nom_evenement']) ?></strong> - <?= htmlspecialchars($ev['type_evenement']) ?><br>
                        Lieu: <?= htmlspecialchars($ev['lieu']) ?> | Début: <?= htmlspecialchars($ev['event_start']) ?><br>
                        Participation: <?= htmlspecialchars($ev['participation_statut']) ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p>Aucun événement participé.</p>
        <?php endif; ?>

        <!-- Équipe de foot -->
        <h3>Équipe de Football</h3>
        <?php if($teams): ?>
            <ul class="list-group mb-3">
                <?php foreach($teams as $team): ?>
                    <li class="list-group-item">
                        <strong>Équipe :</strong> <?= htmlspecialchars($team['team_name']) ?> (<?= htmlspecialchars($team['season_label']) ?>)<br>
                        Coach: <?= htmlspecialchars($team['coach']) ?><br>
                        Poste: <?= htmlspecialchars($team['position']) ?> | N° maillot: <?= htmlspecialchars($team['shirt_number']) ?><br>
                        Capitaine: <?= $team['is_captain'] ? 'Oui' : 'Non' ?><br>
                        Période: <?= $team['joined_at'] ?> - <?= $team['left_at'] ?? 'Présent' ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p>Non membre d'une équipe de foot.</p>
        <?php endif; ?>

        <!-- Commentaires football -->
        <h3>Commentaires Football</h3>
        <?php if($footComments): ?>
            <ul class="list-group mb-3">
                <?php foreach($footComments as $c): ?>
                    <li class="list-group-item">
                        <?= htmlspecialchars($c['message']) ?><br>
                        Note: <?= htmlspecialchars($c['note'] ?? '-') ?> | Par: <?= htmlspecialchars($c['admin_nom'] ?? 'Étudiant') ?> <?= htmlspecialchars($c['admin_prenom'] ?? '') ?> 
                        | <?= $c['user_type'] === 'admin' ? 'Admin' : 'Étudiant' ?> | <?= $c['date_commentaire_football'] ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p>Aucun commentaire football.</p>
        <?php endif; ?>
    </div>
</div>

<?php
$content = ob_get_clean();
include '../layout.php';
?>
