<?php
session_start();
require_once '../../includes/db.php';

// Vérifier si l’admin est connecté
if (!isset($_SESSION['admin_id'])) {
    header('Location: connexion_admin.php');
    exit();
}

$message = '';

// =====================
// Traitement du formulaire
// =====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_etudiant'])) {
    $id_etudiant = intval($_POST['id_etudiant']);
    $poste_bureau = $_POST['poste_bureau'] ?? 'Membre';

    // Vérifier si l'étudiant est déjà membre du bureau
    $check = $pdo->prepare("SELECT id_admin FROM administrateurs WHERE role = 'bureau' AND id_etudiant = ?");
    $check->execute([$id_etudiant]);

    if ($check->rowCount() > 0) {
        $message = "<div class='alert alert-warning'>
                        <i class='fas fa-exclamation-triangle'></i> Cet étudiant est déjà membre du bureau.
                    </div>";
    } else {
        $stmt = $pdo->prepare("INSERT INTO administrateurs (role, id_etudiant, poste_bureau, date_creation) VALUES ('bureau', ?, ?, NOW())");
        $result = $stmt->execute([$id_etudiant, $poste_bureau]);

        if ($result) {
            header("Location: bureau.php?success=1");
            exit();
        } else {
            $message = "<div class='alert alert-danger'>
                            <i class='fas fa-times-circle'></i> Erreur lors de l'ajout du membre.
                        </div>";
        }
    }
}

// =====================
// Recherche et Filtrage
// =====================
$search = $_GET['search'] ?? '';
$query = "SELECT * FROM etudiants WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (nom LIKE :s OR prenom LIKE :s OR matricule LIKE :s)";
    $params['s'] = "%$search%";
}

$query .= " ORDER BY nom ASC LIMIT 24"; // Limite pour performance
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$etudiants = $stmt->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>

<style>
    /* Intégration fine des variables pour cette page */
    .page-container {
        animation: fadeIn 0.4s ease-out;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* En-tête */
    .header-section {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: var(--space-6);
        border-bottom: 1px solid rgba(255,255,255,0.05);
        padding-bottom: var(--space-4);
    }

    .header-section h1 {
        font-size: var(--font-size-xl);
        font-weight: 700;
        color: var(--white);
    }

    /* Barre de recherche Pro */
    .search-wrapper {
        position: relative;
        max-width: 500px;
        margin-bottom: var(--space-6);
    }

    .search-wrapper i {
        position: absolute;
        left: 15px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--gray-400);
    }

    .search-input {
        width: 100%;
        background: var(--primary-800);
        border: 1px solid rgba(255,255,255,0.1);
        border-radius: var(--radius-md);
        padding: 12px 12px 12px 45px;
        color: var(--white);
        transition: var(--transition-fast);
    }

    .search-input:focus {
        outline: none;
        border-color: var(--accent-blue);
        box-shadow: 0 0 0 4px rgba(46, 134, 222, 0.15);
        background: var(--primary-700);
    }

    /* Grille de cartes */
    .student-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: var(--space-5);
    }

    /* Carte Étudiant Style Modern */
    .student-card {
        background: var(--primary-800);
        border: var(--sidebar-border);
        border-radius: var(--radius-lg);
        overflow: hidden;
        transition: var(--transition-base);
        position: relative;
        display: flex;
        flex-direction: column;
    }

    .student-card:hover {
        transform: translateY(-5px);
        box-shadow: var(--shadow-lg);
        border-color: rgba(255,255,255,0.15);
    }

    .image-container {
        height: 180px;
        width: 100%;
        background: var(--primary-700);
        position: relative;
    }

    .student-img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .card-content {
        padding: var(--space-4);
        text-align: center;
        flex-grow: 1;
    }

    .student-name {
        font-size: var(--font-size-md);
        font-weight: 600;
        color: var(--white);
        margin-bottom: 4px;
    }

    .student-info {
        font-size: var(--font-size-xs);
        color: var(--gray-400);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: var(--space-4);
    }

    /* Formulaire dans la carte */
    .add-form {
        background: rgba(0,0,0,0.2);
        padding: var(--space-3);
        border-radius: var(--radius-md);
        margin-top: auto;
    }

    .post-select {
        width: 100%;
        background: var(--primary-900);
        border: 1px solid rgba(255,255,255,0.1);
        color: var(--gray-100);
        padding: 8px;
        border-radius: var(--radius-sm);
        margin-bottom: var(--space-3);
        font-size: var(--font-size-sm);
    }

    /* Boutons */
    .btn-pro {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 20px;
        border-radius: var(--radius-md);
        font-weight: 600;
        font-size: var(--font-size-sm);
        transition: var(--transition-fast);
        border: none;
        cursor: pointer;
        text-decoration: none;
    }

    .btn-add-bureau {
        background: var(--accent-green);
        color: var(--white);
        width: 100%;
        justify-content: center;
    }

    .btn-add-bureau:hover {
        filter: brightness(1.1);
        transform: scale(1.02);
    }

    .btn-back {
        background: transparent;
        color: var(--gray-300);
        border: 1px solid rgba(255,255,255,0.1);
    }

    .btn-back:hover {
        background: rgba(255,255,255,0.05);
        color: var(--white);
    }

    /* Alertes stylisées */
    .alert {
        padding: var(--space-4);
        border-radius: var(--radius-md);
        margin-bottom: var(--space-5);
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .alert-warning { background: rgba(255, 159, 67, 0.1); color: #ff9f43; border: 1px solid rgba(255, 159, 67, 0.2); }
    .alert-danger { background: rgba(255, 71, 87, 0.1); color: #ff4757; border: 1px solid rgba(255, 71, 87, 0.2); }

    .empty-state {
        text-align: center;
        padding: var(--space-6);
        background: var(--primary-800);
        border-radius: var(--radius-lg);
        color: var(--gray-400);
    }
</style>

<div class="page-container">
    <div class="header-section">
        <h1><i class="fas fa-user-plus"></i> Ajouter un membre au bureau</h1>
        <a href="bureau.php" class="btn-pro btn-back">
            <i class="fas fa-arrow-left"></i> Retour à la liste
        </a>
    </div>

    <?= $message ?>

    <div class="search-wrapper">
        <form method="GET">
            <i class="fas fa-search"></i>
            <input type="text" name="search" class="search-input" 
                   placeholder="Rechercher par nom, prénom ou matricule..." 
                   value="<?= htmlspecialchars($search) ?>">
        </form>
    </div>

    <div class="student-grid">
        <?php if (!empty($etudiants)): ?>
            <?php foreach ($etudiants as $e): ?>
                <div class="student-card">
                    <div class="image-container">
                        <?php 
                        $photo_path = !empty($e['photo']) ? '../uploads/photos_etudiants/' . $e['photo'] : '../assets/default/avatar.png';
                        ?>
                        <img src="<?= htmlspecialchars($photo_path) ?>" class="student-img" alt="Photo étudiant">
                    </div>

                    <div class="card-content">
                        <h3 class="student-name"><?= htmlspecialchars($e['prenom'] . ' ' . $e['nom']) ?></h3>
                        <p class="student-info"><?= htmlspecialchars($e['filiere'] ?? 'N/A') ?> <br> Promo <?= htmlspecialchars($e['promotion'] ?? 'N/A') ?></p>

                        <div class="add-form">
                            <form method="POST">
                                <input type="hidden" name="id_etudiant" value="<?= $e['id_etudiant'] ?>">
                                <label class="d-none">Poste</label>
                                <select name="poste_bureau" class="post-select">
                                    <option value="Membre">-- Sélectionner un poste --</option>
                                    <option value="Président">Président</option>
                                    <option value="Vice-Président">Vice-président</option>
                                    <option value="Trésorier">Trésorier</option>
                                    <option value="Secrétaire">Secrétaire</option>
                                    <option value="Organisateur">Organisateur</option>
                                    <option value="Comptable">Comptable</option>
                                </select>
                                <button type="submit" class="btn-pro btn-add-bureau">
                                    <i class="fas fa-plus-circle"></i> Confirmer
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-search-minus fa-3x mb-3"></i>
                <p>Aucun étudiant ne correspond à votre recherche.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
$content = ob_get_clean();
include '../layout.php';
?>