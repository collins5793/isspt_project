<?php
session_start();
require_once '../includes/db.php'; // connexion PDO
use Spatie\PdfToImage\Pdf;

// --- Vérification de la connexion (admin OU étudiant) ---
$isAdmin = isset($_SESSION['admin_id']);
$isEtudiant = isset($_SESSION['etudiant_id']);

if (!$isAdmin && !$isEtudiant) {
    // Si ni admin ni étudiant n’est connecté → redirection
    header("Location: ../etudiant/login_etudiant.php");
    exit;
}

// --- Traitement des filtres et recherche ---
$search = $_GET['search'] ?? '';
$filiere = $_GET['filiere'] ?? '';
$annee = $_GET['annee'] ?? '';
$type = $_GET['type'] ?? '';
$niveau = $_GET['niveau'] ?? '';

// --- Requête SQL principale ---
$query = "
    SELECT e.id_epreuve, e.titre, e.description, e.file_path, 
           e.niveau, e.date_ajout, e.is_public,
           c.nom_category AS type_epreuve,
           y.label AS annee_univ,
           f.nom_filiere
    FROM epreuves e
    LEFT JOIN epreuves_categories c ON e.id_category = c.id_category
    LEFT JOIN academic_years y ON e.academic_year_id = y.id
    LEFT JOIN filieres f ON e.id_filiere = f.id_filiere
    WHERE 1=1
";

$params = [];

if (!empty($search)) { 
    $query .= " AND e.titre LIKE :search"; 
    $params[':search'] = "%$search%"; 
}
if (!empty($filiere)) { 
    $query .= " AND f.nom_filiere = :filiere"; 
    $params[':filiere'] = $filiere; 
}
if (!empty($annee)) { 
    $query .= " AND y.label = :annee"; 
    $params[':annee'] = $annee; 
}
if (!empty($type)) { 
    $query .= " AND c.nom_category = :type"; 
    $params[':type'] = $type; 
}
if (!empty($niveau)) { 
    $query .= " AND e.niveau = :niveau"; 
    $params[':niveau'] = $niveau; 
}

$query .= " ORDER BY e.date_ajout DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$epreuves = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>📚 Recueil d'Épreuves Universitaires</title>
<link rel="stylesheet" href="assets/css/index.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<header>
    <h1>📚 Recueil d’Épreuves Universitaires</h1>
    <p>Consultez, recherchez et téléchargez les anciennes épreuves par filière et année.</p>
</header>

<div class="container">

    <!-- Barre de recherche et filtres -->
    <form method="GET" class="filters">
        <div style="flex:1; display:flex; gap:10px; flex-wrap:wrap;">
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Rechercher titre ou matière">
            <select name="filiere">
                <option value="">Filière</option>
                <option value="SIL" <?= $filiere=="SIL"?"selected":"" ?>>SIL</option>
                <option value="RIT" <?= $filiere=="RIT"?"selected":"" ?>>RIT</option>
                <option value="GIT" <?= $filiere=="GIT"?"selected":"" ?>>GIT</option>
            </select>
            <select name="annee">
                <option value="">Année</option>
                <option value="2024-2025" <?= $annee=="2024-2025"?"selected":"" ?>>2024-2025</option>
                <option value="2023-2024" <?= $annee=="2023-2024"?"selected":"" ?>>2023-2024</option>
                <option value="2022-2023" <?= $annee=="2022-2023"?"selected":"" ?>>2022-2023</option>
            </select>
            <select name="type">
                <option value="">Type</option>
                <option value="Examen national" <?= $type=="Examen national"?"selected":"" ?>>Examen national</option>
                <option value="Partiel" <?= $type=="Partiel"?"selected":"" ?>>Partiel</option>
                <option value="TP" <?= $type=="TP"?"selected":"" ?>>TP</option>
                <option value="Devoir surveillé" <?= $type=="Devoir surveillé"?"selected":"" ?>>Devoir surveillé</option>
            </select>
            <select name="niveau">
                <option value="">Niveau</option>
                <option value="1ère année" <?= $niveau=="1ère année"?"selected":"" ?>>1ère année</option>
                <option value="2ème année" <?= $niveau=="2ème année"?"selected":"" ?>>2ème année</option>
                <option value="3ème année" <?= $niveau=="3ème année"?"selected":"" ?>>3ème année</option>
                <option value="autre" <?= $niveau=="autre"?"selected":"" ?>>Autre</option>
            </select>
        </div>
        <div class="admin-buttons">
            <button type="submit">Filtrer</button>
            <?php if ($isAdmin): ?>
                <a href="ajouter_epreuve.php" class="btn-add">+ Ajouter</a>
            <?php endif; ?>
        </div>
    </form>
    <?php if (isset($_GET['message']) && $_GET['message'] === 'supprime'): ?>
        <p style="background:#d4edda; color:#155724; padding:10px; border-radius:5px;">✅ Épreuve supprimée avec succès.</p>
    <?php endif; ?>

    <!-- Cards -->
    <div class="epreuve-grid">
        <?php if (!empty($epreuves)): ?>
            <?php foreach($epreuves as $row): 
                $thumbPath = '../uploads/thumbs/' . pathinfo($row['file_path'], PATHINFO_FILENAME) . '.jpg';
                if(!file_exists($thumbPath)) $thumbPath = '../uploads/thumbs/pdf-icon.jpg';
            ?>
            <div class="epreuve-card"
                data-titre="<?= htmlspecialchars($row['titre']) ?>"
                data-filiere="<?= htmlspecialchars($row['nom_filiere']) ?>"
                data-annee="<?= htmlspecialchars($row['annee_univ']) ?>"
                data-type="<?= htmlspecialchars($row['type_epreuve']) ?>"
                data-niveau="<?= htmlspecialchars($row['niveau']) ?>"
                data-description="<?= htmlspecialchars($row['description']) ?>"
                data-file="<?= htmlspecialchars($row['file_path']) ?>">
                
                <img src="<?= $thumbPath ?>" alt="PDF" style="width:100%; height:200px; object-fit:cover; border-radius:5px; margin-bottom:10px;">
                
                <?php if ($isAdmin): ?>
                <div class="admin-actions">
                    <a href="modifier_epreuve.php?id=<?= $row['id_epreuve'] ?>" class="btn-edit" title="Modifier">✏️</a>
                    <a href="supprimer_epreuve.php?id=<?= $row['id_epreuve'] ?>" class="btn-delete" onclick="return confirm('Supprimer cette épreuve ?');" title="Supprimer">🗑️</a>
                </div>
                <?php endif; ?>

                <div class="epreuve-card-header"><?= htmlspecialchars($row['titre']) ?></div>
                <div>
                    <span class="badge badge-type"><?= htmlspecialchars($row['type_epreuve']) ?></span>
                    <span class="badge badge-niveau"><?= htmlspecialchars($row['niveau']) ?></span>
                </div>
                <div class="epreuve-card-footer"><?= htmlspecialchars($row['nom_filiere']) ?> | <?= htmlspecialchars($row['annee_univ']) ?></div>
                <a id="modal-download" class="btn-download" href="#" download>🔽 Télécharger</a>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p>Aucune épreuve trouvée.</p>
        <?php endif; ?>
    </div>
</div>

<!-- Modal -->
<div class="modal-epreuve" id="modal-epreuve">
    <div class="modal-content">
        <span class="modal-close" id="modal-close">&times;</span>
        <h2 id="modal-titre"></h2>
        <p><strong>Filière:</strong> <span id="modal-filiere"></span></p>
        <p><strong>Année:</strong> <span id="modal-annee"></span></p>
        <p><strong>Type:</strong> <span id="modal-type"></span></p>
        <p><strong>Niveau:</strong> <span id="modal-niveau"></span></p>
        <p id="modal-description"></p>
        <a id="modal-download" class="btn-download" href="#" download>🔽 Télécharger</a>
    </div>
</div>

<footer class="footer-epreuves">
  <div class="footer-container">
    
    <!-- Section A propos / Infos -->
    <div class="footer-section about">
      <h3>À propos</h3>
      <p>
        Institut Supérieur Saint Paul Tarse – Module Recueil d’Épreuves. 
        Centralisez et consultez les examens, TD, TP et concours par filière et année universitaire.
      </p>
    </div>

    <!-- Section Liens rapides -->
    <div class="footer-section links">
      <h3>Liens rapides</h3>
      <ul>
        <li><a href="dashboard_admin.php">Tableau de bord</a></li>
        <li><a href="ajouter_epreuve.php">Ajouter une épreuve</a></li>
        <li><a href="afficher_epreuves.php">Consulter les épreuves</a></li>
        <li><a href="historique_epreuves.php">Historique des épreuves</a></li>
      </ul>
    </div>

    <!-- Section Contact -->
    <div class="footer-section contact">
      <h3>Contact</h3>
      <p>Email : contact@isspt.edu</p>
      <p>Téléphone : +229 97 00 00 00</p>
      <p>Adresse : Abomey-Calavi, Bénin</p>
    </div>

    <!-- Section Réseaux sociaux -->
    <div class="footer-section social">
      <h3>Réseaux sociaux</h3>
      <ul class="social-icons">
        <li><a href="#" target="_blank"><i class="fab fa-facebook-f"></i></a></li>
        <li><a href="#" target="_blank"><i class="fab fa-twitter"></i></a></li>
        <li><a href="#" target="_blank"><i class="fab fa-linkedin-in"></i></a></li>
        <li><a href="#" target="_blank"><i class="fab fa-instagram"></i></a></li>
      </ul>
    </div>

  </div>

  <div class="footer-bottom">
    <p>&copy; <?= date('Y') ?> Institut Supérieur Saint Paul Tarse. Tous droits réservés.</p>
  </div>
</footer>


<script>
const cards = document.querySelectorAll('.epreuve-card');
const modal = document.getElementById('modal-epreuve');
const modalClose = document.getElementById('modal-close');

cards.forEach(card => {
    card.addEventListener('click', (e) => {
        // empêcher que cliquer sur les boutons admin ouvre le modal
        if (e.target.closest('.btn-edit') || e.target.closest('.btn-delete')) return;
        document.getElementById('modal-titre').innerText = card.dataset.titre;
        document.getElementById('modal-filiere').innerText = card.dataset.filiere;
        document.getElementById('modal-annee').innerText = card.dataset.annee;
        document.getElementById('modal-type').innerText = card.dataset.type;
        document.getElementById('modal-niveau').innerText = card.dataset.niveau;
        document.getElementById('modal-description').innerText = card.dataset.description;
        document.getElementById('modal-download').href = '../uploads/' + card.dataset.file;
        modal.style.display = 'flex';
    });
});

modalClose.addEventListener('click', () => modal.style.display = 'none');
window.addEventListener('click', (e) => { if (e.target === modal) modal.style.display = 'none'; });
</script>

</body>
</html>
