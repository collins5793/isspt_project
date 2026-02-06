<?php
session_start();
require_once "../../includes/db.php";

// Sécurité : admin connecté
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit;
}

// Vérifier ID
$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: actualites.php");
    exit;
}

// Charger l’actualité complète
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

// Badge statut
function statutBadge($statut) {
    return match ($statut) {
        'publie'    => '<span class="badge badge-success">Publié</span>',
        'brouillon' => '<span class="badge badge-warning">Brouillon</span>',
        'archive'   => '<span class="badge badge-secondary">Archivé</span>',
        default     => '<span class="badge">Inconnu</span>',
    };
}

ob_start();
?>

<div class="page-header">
    <h2>Détail de l’actualité</h2>
    <div>
        <a href="actualites.php" class="btn btn-secondary">← Retour</a>
        <a href="actualite_modifier.php?id=<?= $actualite['id'] ?>" class="btn btn-warning">Modifier</a>
        <a href="actualite_supprimer.php?id=<?= $actualite['id'] ?>"
           class="btn btn-danger"
           onclick="return confirm('Voulez-vous vraiment supprimer cette actualité ?');">
            Supprimer
        </a>
    </div>
</div>

<div class="card">

    <h1 class="title"><?= htmlspecialchars($actualite['titre']) ?></h1>

    <div class="meta">
        <?= statutBadge($actualite['statut']) ?>
        <span>📅 <?= date('d/m/Y H:i', strtotime($actualite['date_publication'])) ?></span>
        <span>👤 <?= htmlspecialchars($actualite['admin_prenom'].' '.$actualite['admin_nom']) ?></span>
        <span>🎓 <?= htmlspecialchars($actualite['academic_year']) ?></span>
    </div>

    <hr>

    <div class="section">
        <h3>Contenu</h3>
        <p><?= nl2br(htmlspecialchars($actualite['contenu'])) ?></p>
    </div>

    <?php if (!empty($actualite['mot_du_president'])): ?>
    <div class="section president">
        <h3>Mot du Président</h3>
        <blockquote>
            <?= nl2br(htmlspecialchars($actualite['mot_du_president'])) ?>
        </blockquote>
    </div>
    <?php endif; ?>

</div>

<style>
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}
.card {
    background: #fff;
    padding: 30px;
    border-radius: 10px;
    box-shadow: 0 3px 10px rgba(0,0,0,0.08);
    max-width: 900px;
}
.title {
    margin-bottom: 10px;
}
.meta {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
    font-size: 0.9rem;
    color: #555;
    margin-bottom: 15px;
}
.section {
    margin-top: 20px;
}
.section h3 {
    margin-bottom: 10px;
}
blockquote {
    background: #f9f9f9;
    padding: 15px 20px;
    border-left: 5px solid rgb(8,0,32);
    font-style: italic;
}
.btn {
    padding: 6px 14px;
    border-radius: 6px;
    text-decoration: none;
    color: #fff;
    font-size: 0.9rem;
}
.btn-secondary { background: #6c757d; }
.btn-warning   { background: #f0ad4e; }
.btn-danger    { background: #d9534f; }

.badge {
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 0.75rem;
    color: #fff;
}
.badge-success { background: #28a745; }
.badge-warning { background: #ffc107; color: #000; }
.badge-secondary { background: #6c757d; }
</style>

<?php
$content = ob_get_clean();
include "../layout.php";
?>
