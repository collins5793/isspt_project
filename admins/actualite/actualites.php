<?php
session_start();
require_once "../../includes/db.php";

/* =========================
   STATISTIQUES
========================= */

// Total actualités
$totalActualites = $pdo->query("SELECT COUNT(*) FROM actualites")->fetchColumn();

// Par statut
$statsStatut = $pdo->query("
    SELECT statut, COUNT(*) as total
    FROM actualites
    GROUP BY statut
")->fetchAll(PDO::FETCH_KEY_PAIR);

// Année avec plus d’actualités
$anneeTop = $pdo->query("
    SELECT ay.label, COUNT(a.id) as total
    FROM actualites a
    JOIN academic_years ay ON ay.id = a.id_academic_year
    GROUP BY ay.id
    ORDER BY total DESC
    LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

/* =========================
   LISTE DES ACTUALITÉS
========================= */

$stmt = $pdo->query("
    SELECT a.*, 
           ad.nom AS admin_nom, 
           ad.prenom AS admin_prenom, 
           ay.label AS academic_year
    FROM actualites a
    LEFT JOIN administrateurs ad ON a.id_admin = ad.id_admin
    LEFT JOIN academic_years ay ON a.id_academic_year = ay.id
    ORDER BY a.date_publication DESC
");
$actualites = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Messages session
$success = $_SESSION['success'] ?? null;
$error   = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);

ob_start();
?>

<div class="page-header">
    <h2>Gestion des actualités</h2>
    <a href="actualite_ajouter.php" class="btn btn-primary">➕ Nouvelle actualité</a>
</div>

<!-- STATISTIQUES -->
<div class="stats-grid">
    <div class="stat-card">
        <h4>Total</h4>
        <p><?= $totalActualites ?></p>
    </div>
    <div class="stat-card green">
        <h4>Publiées</h4>
        <p><?= $statsStatut['publie'] ?? 0 ?></p>
    </div>
    <div class="stat-card yellow">
        <h4>Brouillons</h4>
        <p><?= $statsStatut['brouillon'] ?? 0 ?></p>
    </div>
    <div class="stat-card red">
        <h4>Archivées</h4>
        <p><?= $statsStatut['archive'] ?? 0 ?></p>
    </div>
    <div class="stat-card blue">
        <h4>Année la plus active</h4>
        <p><?= $anneeTop['label'] ?? '—' ?></p>
    </div>
</div>

<?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if ($actualites): ?>
<table class="table">
    <thead>
        <tr>
            <th>#</th>
            <th>Titre</th>
            <th>Année</th>
            <th>Auteur</th>
            <th>Statut</th>
            <th>Date</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($actualites as $i => $act): ?>
        <tr>
            <td><?= $i + 1 ?></td>
            <td><?= htmlspecialchars($act['titre']) ?></td>
            <td><?= htmlspecialchars($act['academic_year']) ?></td>
            <td><?= htmlspecialchars($act['admin_prenom'].' '.$act['admin_nom']) ?></td>
            <td>
                <span class="badge <?= $act['statut'] ?>">
                    <?= ucfirst($act['statut']) ?>
                </span>
            </td>
            <td><?= date('d/m/Y H:i', strtotime($act['date_publication'])) ?></td>
            <td>
                <a href="actualite_detail.php?id=<?= $act['id'] ?>" class="btn btn-info btn-sm">Voir</a>
                <a href="actualite_modifier.php?id=<?= $act['id'] ?>" class="btn btn-warning btn-sm">Modifier</a>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php else: ?>
<p>Aucune actualité enregistrée.</p>
<?php endif; ?>

<style>
.page-header{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:20px;
}
.stats-grid{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(180px,1fr));
    gap:15px;
    margin-bottom:25px;
}
.stat-card{
    background:#fff;
    border-radius:10px;
    padding:15px;
    text-align:center;
    box-shadow:0 4px 10px rgba(0,0,0,0.08);
}
.stat-card h4{margin:0;font-size:0.9rem;color:#555;}
.stat-card p{font-size:1.8rem;margin:5px 0;font-weight:bold;}
.green{border-left:5px solid #28a745;}
.yellow{border-left:5px solid #ffc107;}
.red{border-left:5px solid #dc3545;}
.blue{border-left:5px solid #007bff;}

.table{
    width:100%;
    border-collapse:collapse;
}
.table th,.table td{
    padding:12px;
    border:1px solid #e0e0e0;
}
.badge{
    padding:5px 10px;
    border-radius:20px;
    color:#fff;
    font-size:0.8rem;
}
.badge.publie{background:#28a745;}
.badge.brouillon{background:#ffc107;color:#000;}
.badge.archive{background:#6c757d;}

.btn{padding:6px 10px;border-radius:6px;color:#fff;text-decoration:none;font-size:0.85rem;}
.btn-primary{background:#080020;}
.btn-warning{background:#f0ad4e;}
.btn-info{background:#17a2b8;}
.btn-sm{margin-right:4px;}

.alert{padding:12px;border-radius:8px;margin-bottom:15px;}
.alert-success{background:#d4edda;color:#155724;}
.alert-danger{background:#f8d7da;color:#721c24;}
</style>

<?php
$content = ob_get_clean();
include "../layout.php";
?>
