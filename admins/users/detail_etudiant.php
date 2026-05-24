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

<style>
    :root {
        /* Intégration de vos variables */
        --primary-900: #080020; --primary-800: #0a0127; --primary-700: #120c3a;
        --primary-600: #1a1849; --accent-blue: #2e86de; --accent-green: #10ac84;
        --white: #ffffff; --gray-100: #f1f2f6; --gray-300: #ced6e0; --gray-400: #a4b0be;
        --radius-lg: 12px;
    }

    .profile-container { background: var(--primary-900); color: var(--white); min-height: 100vh; }
    
    /* Carte Profil Sidebar */
    .profile-sidebar {
        background: var(--primary-800);
        border: 1px solid rgba(255, 255, 255, 0.05);
        border-radius: var(--radius-lg);
        padding: var(--space-4);
        position: sticky; top: 20px;
    }
    .profile-img { width: 150px; height: 150px; object-fit: cover; border: 4px solid var(--primary-700); border-radius: 50%; margin-bottom: 1rem; }
    
    /* Sections Content */
    .info-card {
        background: var(--primary-800);
        border: 1px solid rgba(255, 255, 255, 0.05);
        border-radius: var(--radius-lg);
        padding: var(--space-4);
        margin-bottom: var(--space-4);
    }
    .section-title { font-size: 1.2rem; font-weight: 600; color: var(--accent-blue); margin-bottom: var(--space-3); display: flex; align-items: center; gap: 10px; }
    
    /* Liste stylisée */
    .data-list { list-style: none; padding: 0; }
    .data-item { padding: 12px; border-bottom: 1px solid rgba(255,255,255,0.05); display: flex; justify-content: space-between; }
    .data-item:last-child { border: none; }
    .badge { padding: 4px 10px; border-radius: var(--radius-full); font-size: 0.8rem; background: var(--primary-600); }
</style>

<div class="container-fluid py-4">
    <!-- En-tête -->
    <div class="d-flex justify-content-between align-items-center mb-5">
        <h2 class="text-white">Profil Étudiant</h2>
        <a href="liste_etudiants.php" class="btn btn-outline-light btn-sm">⬅ Retour à la liste</a>
    </div>

    <div class="row">
        <!-- Colonne Gauche : Infos -->
        <div class="col-md-4">
            <div class="profile-sidebar text-center">
                <img src="<?= (!empty($etudiant['photo']) && file_exists('../uploads/photos_etudiants/'.$etudiant['photo'])) ? '../uploads/photos_etudiants/'.$etudiant['photo'] : '../assets/default/avatar.png' ?>" class="profile-img">
                <h4 class="mb-1"><?= htmlspecialchars($etudiant['prenom'] . ' ' . $etudiant['nom']) ?></h4>
                <p class="text-muted"><?= htmlspecialchars($etudiant['filiere']) ?></p>
                
                <div class="text-start mt-4">
                    <p><strong>Matricule:</strong> <span class="text-info"><?= htmlspecialchars($etudiant['matricule']) ?></span></p>
                    <p><strong>Email:</strong> <?= htmlspecialchars($etudiant['email']) ?></p>
                    <p><strong>Tel:</strong> <?= htmlspecialchars($etudiant['telephone']) ?></p>
                    <p><strong>Promotion:</strong> <?= htmlspecialchars($etudiant['promotion']) ?></p>
                </div>
            </div>
        </div>

        <!-- Colonne Droite : Activités & Foot -->
        <div class="col-md-8">
            
            <!-- Activités -->
            <div class="info-card">
                <div class="section-title">📂 Activités</div>
                <?php if($activites): ?>
                    <div class="data-list">
                        <?php foreach($activites as $act): ?>
                            <div class="data-item">
                                <div><strong><?= htmlspecialchars($act['nom_activite']) ?></strong> <br><small class="text-muted"><?= $act['annee_scolaire'] ?></small></div>
                                <div><span class="badge"><?= htmlspecialchars($act['statut']) ?></span></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?><p class="text-muted">Aucune activité enregistrée.</p><?php endif; ?>
            </div>

            <!-- Événements -->
            <div class="info-card">
                <div class="section-title">🎉 Événements</div>
                <div class="row">
                    <?php foreach($evenements as $ev): ?>
                        <div class="col-md-6 mb-2">
                            <div class="p-3 rounded" style="background:var(--primary-700)">
                                <strong><?= htmlspecialchars($ev['nom_evenement']) ?></strong><br>
                                <small><?= htmlspecialchars($ev['event_start']) ?> | <?= htmlspecialchars($ev['lieu']) ?></small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Foot -->
            <div class="info-card">
                <div class="section-title">⚽ Équipe de Football</div>
                <?php if($teams): ?>
                    <table class="table table-dark table-sm table-hover">
                        <thead><tr><th>Équipe</th><th>Saison</th><th>Poste</th></tr></thead>
                        <tbody>
                            <?php foreach($teams as $team): ?>
                                <tr>
                                    <td><?= htmlspecialchars($team['team_name']) ?></td>
                                    <td><?= htmlspecialchars($team['season_label']) ?></td>
                                    <td><?= htmlspecialchars($team['position']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?><p class="text-muted">Aucune équipe associée.</p><?php endif; ?>
            </div>

        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include '../layout.php';
?>