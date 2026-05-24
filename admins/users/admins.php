<?php
session_start();
require_once '../../includes/db.php';

// Vérifier si l’admin est connecté
if (!isset($_SESSION['admin_id'])) {
    header('Location: connexion_admin.php');
    exit();
}

// =====================
// Récupération de la liste des admins
// =====================
$query = "
    SELECT a.*, 
           e.nom AS etu_nom, e.prenom AS etu_prenom, e.photo AS etu_photo, e.filiere AS etu_filiere, e.promotion AS etu_promotion
    FROM administrateurs a
    LEFT JOIN etudiants e ON a.id_etudiant = e.id_etudiant
    ORDER BY a.date_creation DESC
";
$stmt = $pdo->query($query);
$admins = $stmt->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>

<style>
    :root {
        --bg-page: var(--primary-900);
        --bg-card: var(--primary-800);
        --bg-badge-bureau: rgba(46, 134, 222, 0.15);
        --color-badge-bureau: var(--accent-blue);
        --bg-badge-super: rgba(255, 71, 87, 0.15);
        --color-badge-super: var(--accent-red);
        --border-glass: rgba(255, 255, 255, 0.06);
    }

    body {
        background-color: var(--bg-page);
        color: var(--gray-100);
        font-family: var(--font-primary);
    }

    /* En-tête de la page */
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: var(--space-6);
        padding-bottom: var(--space-4);
        border-bottom: 1px solid var(--border-glass);
        flex-wrap: wrap;
        gap: var(--space-4);
    }

    .page-header h1 {
        font-size: var(--font-size-xl);
        font-weight: 700;
        color: var(--white);
        margin: 0;
        letter-spacing: -0.5px;
    }

    /* Grille de cartes moderne */
    .admin-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: var(--space-5);
        margin-bottom: var(--space-6);
    }

    /* Design de la carte d'administrateur */
    .admin-card {
        background-color: var(--bg-card);
        border: 1px solid var(--border-glass);
        border-radius: var(--radius-lg);
        padding: var(--space-5);
        box-shadow: var(--shadow-lg);
        display: flex;
        flex-direction: column;
        align-items: center;
        position: relative;
        overflow: hidden;
        transition: transform var(--transition-base), box-shadow var(--transition-base), border-color var(--transition-base);
    }

    .admin-card:hover {
        transform: translateY(-4px);
        border-color: rgba(255, 255, 255, 0.12);
        box-shadow: var(--shadow-xl);
    }

    /* Zone Avatar circulaire avec halo */
    .avatar-wrapper {
        position: relative;
        width: 100px;
        height: 100px;
        margin-bottom: var(--space-4);
    }

    .admin-avatar {
        width: 100%;
        height: 100%;
        border-radius: var(--radius-full);
        object-fit: cover;
        border: 3px solid var(--primary-700);
        box-shadow: var(--shadow-md);
    }

    /* Indicateur de rôle (Badge) */
    .role-badge {
        font-size: var(--font-size-xs);
        font-weight: 700;
        text-transform: uppercase;
        padding: var(--space-1) var(--space-3);
        border-radius: var(--radius-full);
        margin-bottom: var(--space-3);
        letter-spacing: 0.5px;
    }

    .role-bureau {
        background-color: var(--bg-badge-bureau);
        color: var(--color-badge-bureau);
    }

    .role-autre {
        background-color: var(--bg-badge-super);
        color: var(--color-badge-super);
    }

    /* Typographies internes de la carte */
    .admin-name {
        font-size: var(--font-size-lg);
        font-weight: 600;
        color: var(--white);
        margin: 0 0 var(--space-1) 0;
        text-align: center;
    }

    .admin-meta {
        font-size: var(--font-size-sm);
        color: var(--gray-400);
        margin: 0 0 var(--space-2) 0;
        text-align: center;
    }

    .admin-subtext {
        font-size: var(--font-size-xs);
        color: var(--gray-300);
        background-color: var(--primary-700);
        padding: var(--space-1) var(--space-3);
        border-radius: var(--radius-md);
        margin-bottom: var(--space-4);
    }

    /* Actions groupe inférieur */
    .card-actions {
        margin-top: auto;
        display: flex;
        width: 100%;
        gap: var(--space-3);
        border-top: 1px solid var(--border-glass);
        padding-top: var(--space-4);
    }

    /* Boutons personnalisés */
    .btn-custom {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: var(--space-2);
        font-family: var(--font-primary);
        font-size: var(--font-size-sm);
        font-weight: 600;
        border-radius: var(--radius-md);
        text-decoration: none;
        padding: var(--space-2) var(--space-4);
        transition: all var(--transition-fast);
        cursor: pointer;
        border: none;
        flex: 1;
    }

    .btn-add {
        background-color: var(--accent-green);
        color: var(--white);
        box-shadow: var(--shadow-md);
    }

    .btn-add:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(16, 172, 132, 0.3);
    }

    .btn-edit {
        background-color: var(--primary-700);
        color: var(--gray-100);
        border: 1px solid var(--border-glass);
    }

    .btn-edit:hover {
        background-color: var(--primary-600);
        color: var(--white);
    }

    .btn-delete {
        background-color: transparent;
        color: var(--gray-400);
        border: 1px solid transparent;
    }

    .btn-delete:hover {
        background-color: rgba(255, 71, 87, 0.1);
        color: var(--accent-red);
        border-color: rgba(255, 71, 87, 0.2);
    }

    /* Alertes et notifications */
    .alert-custom {
        display: flex;
        align-items: center;
        gap: var(--space-3);
        border-radius: var(--radius-md);
        padding: var(--space-4);
        margin-bottom: var(--space-5);
        font-size: var(--font-size-sm);
    }

    .alert-success {
        background-color: rgba(16, 172, 132, 0.08);
        border: 1px solid rgba(16, 172, 132, 0.2);
        border-left: 4px solid var(--accent-green);
        color: #1dd1a1;
    }

    .alert-info {
        background-color: rgba(46, 134, 222, 0.08);
        border: 1px solid rgba(46, 134, 222, 0.2);
        border-left: 4px solid var(--accent-blue);
        color: #54a0ff;
        width: 100%;
        text-align: center;
        justify-content: center;
    }
</style>

<div class="container-fluid">

    <!-- Toast de succès -->
    <?php if (isset($_GET['success'])): ?>
        <div class="alert-custom alert-success">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span>Administrateur ajouté avec succès !</span>
        </div>
    <?php endif; ?>

    <!-- En-tête -->
    <div class="page-header">
        <h1>Liste des administrateurs</h1>
        <a href="ajouter_admin.php" class="btn-custom btn-add">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <line x1="12" y1="5" x2="12" y2="19"></line>
                <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            Ajouter un administrateur
        </a>
    </div>

    <!-- Grille des profils -->
    <div class="admin-grid">
        <?php if (!empty($admins)): ?>
            <?php foreach ($admins as $a): ?>
                <?php
                // Logique de traitement des types d'affichage d'images et données associées
                if ($a['role'] === 'bureau' && !empty($a['etu_photo']) && file_exists('../uploads/photos_etudiants/' . $a['etu_photo'])) {
                    $photo = '../uploads/photos_etudiants/' . $a['etu_photo'];
                    $nom = $a['etu_nom'];
                    $prenom = $a['etu_prenom'];
                    $info_sup = ($a['etu_filiere'] ?? '') . ' - ' . ($a['etu_promotion'] ?? '');
                    $is_bureau = true;
                } else {
                    $photo = '../assets/default/avatar.png';
                    $nom = $a['nom'] ?? '';
                    $prenom = $a['prenom'] ?? '';
                    $info_sup = $a['role'];
                    $is_bureau = false;
                }
                ?>
                
                <div class="admin-card">
                    <!-- Cadre photo circulaire -->
                    <div class="avatar-wrapper">
                        <img src="<?= htmlspecialchars($photo) ?>" class="admin-avatar" alt="Avatar">
                    </div>

                    <!-- Badge de rôle adaptatif -->
                    <span class="role-badge <?= $is_bureau ? 'role-bureau' : 'role-autre' ?>">
                        <?= htmlspecialchars($a['role']) ?>
                    </span>

                    <!-- Informations textuelles -->
                    <h3 class="admin-name"><?= htmlspecialchars($nom . ' ' . $prenom) ?></h3>
                    <p class="admin-meta"><?= htmlspecialchars($info_sup) ?></p>
                    
                    <?php if ($is_bureau): ?>
                        <div class="admin-subtext">
                            Poste : <strong><?= htmlspecialchars($a['poste_bureau'] ?? 'Membre') ?></strong>
                        </div>
                    <?php endif; ?>

                    <!-- Barre d'actions -->
                    <div class="card-actions">
                        <a href="modifier_admin.php?id=<?= $a['id_admin'] ?>" class="btn-custom btn-edit">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"></path>
                                <path d="M18.5 2.5a2.121 2.121 0 113 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                            </svg>
                            Modifier
                        </a>
                        <a href="supprimer_admin.php?id=<?= $a['id_admin'] ?>" class="btn-custom btn-delete" 
                           onclick="return confirm('Voulez-vous vraiment supprimer cet administrateur ?')">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <polyline points="3 6 5 6 21 6"></polyline>
                                <path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"></path>
                            </svg>
                            Supprimer
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="alert-custom alert-info">
                <span>Aucun administrateur trouvé dans la base de données.</span>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
$content = ob_get_clean();
include '../layout.php';
?>