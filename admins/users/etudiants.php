<?php
session_start();
require_once "../../includes/db.php";

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login_admin.php");
    exit();
}

// Récupération des filtres
$search = $_GET['search'] ?? '';
$filiere = $_GET['filiere'] ?? '';
$promotion = $_GET['promotion'] ?? '';
$statut = $_GET['statut'] ?? '';

$query = "SELECT * FROM etudiants WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (nom LIKE :s OR prenom LIKE :s OR matricule LIKE :s)";
    $params['s'] = "%$search%";
}
if (!empty($filiere)) { $query .= " AND filiere = :filiere"; $params['filiere'] = $filiere; }
if (!empty($promotion)) { $query .= " AND promotion = :promotion"; $params['promotion'] = $promotion; }
if (!empty($statut)) { $query .= " AND statut = :statut"; $params['statut'] = $statut; }

$query .= " ORDER BY nom ASC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$etudiants = $stmt->fetchAll();

$filieres = $pdo->query("SELECT DISTINCT filiere FROM etudiants WHERE filiere IS NOT NULL")->fetchAll();
$promotions = $pdo->query("SELECT DISTINCT promotion FROM etudiants WHERE promotion IS NOT NULL")->fetchAll();

ob_start();
?>

<style>
    /* Intégration des styles personnalisés */
    .dashboard-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-5); }
    .dashboard-header h1 { color: var(--white); font-size: var(--font-size-xl); margin: 0; }
    
    .filter-bar { 
        background: var(--primary-800); 
        padding: var(--space-4); 
        border-radius: var(--radius-lg); 
        border: var(--sidebar-border);
        display: flex; gap: var(--space-3); 
        margin-bottom: var(--space-5);
        align-items: center;
    }
    .filter-bar input, .filter-bar select { 
        background: var(--primary-900); border: 1px solid var(--primary-700); 
        color: var(--white); padding: 10px 15px; border-radius: var(--radius-md); 
        flex: 1; outline: none; transition: var(--transition-fast);
    }
    .filter-bar input:focus { border-color: var(--accent-blue); }

    .data-table-wrapper { background: var(--primary-800); border-radius: var(--radius-lg); overflow: hidden; border: var(--sidebar-border); }
    .data-table { width: 100%; border-collapse: collapse; }
    .data-table th { background: var(--primary-700); color: var(--gray-300); padding: var(--space-4); text-align: left; font-size: var(--font-size-sm); }
    .data-table td { padding: var(--space-4); color: var(--white); border-bottom: 1px solid rgba(255,255,255,0.03); }
    .data-table tr:hover { background: var(--primary-600); cursor: pointer; }
    
    .status-pill { padding: 4px 12px; border-radius: var(--radius-full); font-size: var(--font-size-xs); text-transform: uppercase; font-weight: bold; }
    .bg-green { background: var(--accent-green); color: white; }
    .bg-red { background: var(--accent-red); color: white; }

    .btn-custom { padding: 10px 20px; border-radius: var(--radius-md); text-decoration: none; font-weight: 600; transition: var(--transition-base); }
    .btn-add { background: var(--accent-green); color: white; }
    .btn-nav { background: var(--primary-700); color: white; }
    
    .action-links a { margin-left: var(--space-2); padding: 6px 12px; border-radius: var(--radius-sm); font-size: var(--font-size-xs); text-decoration: none; }
</style>

<div class="content-wrapper" style="padding: var(--space-5);">
    <div class="dashboard-header">
        <h1>Gestion des Étudiants</h1>
        <div style="display:flex; gap: 10px;">
            <a href="bureau.php" class="btn-custom btn-nav">Voir le Bureau</a>
            <a href="ajouter_etudiant.php" class="btn-custom btn-add">+ Ajouter un Étudiant</a>
        </div>
    </div>

    <?php if (isset($_GET['success'])): ?>
        <div style="background: var(--accent-green); padding: 10px; border-radius: var(--radius-md); margin-bottom: 20px;">Étudiant ajouté avec succès !</div>
    <?php endif; ?>

    <form method="GET" class="filter-bar">
        <input type="text" name="search" placeholder="Rechercher par nom, matricule..." value="<?= htmlspecialchars($search) ?>">
        <select name="filiere"><option value="">Toutes filières</option><?php foreach($filieres as $f): ?><option value="<?=$f['filiere']?>" <?=($f['filiere']==$filiere)?'selected':''?>><?=$f['filiere']?></option><?php endforeach; ?></select>
        <select name="promotion"><option value="">Promotion</option><?php foreach($promotions as $p): ?><option value="<?=$p['promotion']?>" <?=($p['promotion']==$promotion)?'selected':''?>><?=$p['promotion']?></option><?php endforeach; ?></select>
        <select name="statut">
            <option value="">Statut</option>
            <option value="actif" <?=($statut=="actif")?"selected":""?>>Actif</option>
            <option value="inactif" <?=($statut=="inactif")?"selected":""?>>Inactif</option>
        </select>
        <button class="btn-custom" style="background: var(--accent-blue); color: white;">Appliquer</button>
    </form>

    <div class="data-table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Photo</th><th>Matricule</th><th>Nom</th><th>Prénom</th><th>Filière</th><th>Promotion</th><th>Statut</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($etudiants as $e): ?>
                <tr onclick="window.location='detail_etudiant.php?id=<?= $e['id_etudiant'] ?>'">
                    <td>
                        <img src="<?= (!empty($e['photo']) && file_exists('../uploads/photos_etudiants/'.$e['photo'])) ? '../uploads/photos_etudiants/'.$e['photo'] : '../assets/default/avatar.png' ?>" width="40" style="border-radius:50%; border: 2px solid var(--primary-700);">
                    </td>
                    <td><?= htmlspecialchars($e['matricule']) ?></td>
                    <td><?= htmlspecialchars($e['nom']) ?></td>
                    <td><?= htmlspecialchars($e['prenom']) ?></td>
                    <td><?= htmlspecialchars($e['filiere']) ?></td>
                    <td><?= htmlspecialchars($e['promotion']) ?></td>
                    <td>
                        <span class="status-pill <?= $e['statut'] == 'actif' ? 'bg-green' : 'bg-red' ?>">
                            <?= htmlspecialchars($e['statut']) ?>
                        </span>
                    </td>
                    <td class="action-links" onclick="event.stopPropagation();">
                        <a href="modifier_etudiant.php?id=<?=$e['id_etudiant']?>" style="background:var(--primary-600); color:white;">Modifier</a>
                        <a href="supprimer_etudiant.php?id=<?=$e['id_etudiant']?>" style="background:var(--accent-red); color:white;" onclick="return confirm('Confirmer la suppression ?')">Supprimer</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
$content = ob_get_clean();
include "../layout.php";
?>