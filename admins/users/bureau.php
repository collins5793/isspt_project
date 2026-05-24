<?php
session_start();
require_once '../../includes/db.php';

// Vérifier si l’admin est connecté
if (!isset($_SESSION['admin_id'])) {
    header('Location: connexion_admin.php');
    exit();
}

// =====================
//   RÉCUPÉRER LES MEMBRES DU BUREAU
// =====================
$stmt = $pdo->query("
    SELECT a.id_admin, a.poste_bureau, e.*
    FROM administrateurs a
    LEFT JOIN etudiants e ON e.id_etudiant = a.id_etudiant
    WHERE a.role = 'bureau'
    ORDER BY a.poste_bureau ASC, e.nom ASC
");
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>

<!-- Styles spécifiques pour un rendu UI/UX Premium -->
<style>
    :root {
        /* Intégration de vos variables */
        --primary-900: #080020;
        --primary-800: #0a0127;
        --primary-700: #120c3a;
        --primary-600: #1a1849;
        --accent-red: #ff4757;
        --accent-blue: #2e86de;
        --accent-green: #10ac84;
        --white: #ffffff;
        --gray-50: #f8f9fa;
        --gray-100: #f1f2f6;
        --gray-200: #dfe4ea;
        --gray-300: #ced6e0;
        --gray-400: #a4b0be;

        --font-primary: 'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif;
        --transition-base: 300ms cubic-bezier(0.4, 0, 0.2, 1);
        --shadow-lg: 0 10px 25px rgba(0, 0, 0, 0.2);
        --radius-lg: 12px;
        --radius-full: 9999px;
    }

    body {
        font-family: var(--font-primary);
        background-color: var(--primary-900);
        color: var(--white);
    }

    /* En-tête de page */
    .page-header-title {
        font-size: 1.75rem;
        font-weight: 700;
        color: var(--white);
        letter-spacing: -0.02em;
    }

    /* Bouton Ajouter personnalisé */
    .btn-add-member {
        background-color: var(--accent-blue);
        color: var(--white);
        border: none;
        padding: 0.6rem 1.2rem;
        border-radius: var(--radius-md);
        font-weight: 600;
        transition: var(--transition-fast);
        box-shadow: 0 4px 12px rgba(46, 134, 222, 0.2);
    }
    .btn-add-member:hover {
        background-color: #2475c4;
        color: var(--white);
        transform: translateY(-1px);
    }

    /* Cartes des membres (Style Dashboard Sombre) */
    .member-card {
        background: var(--primary-800);
        border: 1px solid rgba(255, 255, 255, 0.05);
        border-radius: var(--radius-lg);
        overflow: hidden;
        transition: var(--transition-base);
        box-shadow: var(--shadow-md);
    }
    .member-card:hover {
        transform: translateY(-5px);
        border-color: rgba(46, 134, 222, 0.3);
        box-shadow: var(--shadow-xl);
    }

    /* Conteneur image & overlay */
    .member-image-wrapper {
        position: relative;
        height: 260px;
        overflow: hidden;
        background: var(--primary-700);
    }
    .member-img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: var(--transition-base);
    }
    .member-card:hover .member-img {
        scale: 1.04;
    }

    /* Badge de Poste */
    .badge-poste {
        position: absolute;
        bottom: 12px;
        left: 12px;
        background: rgba(8, 0, 32, 0.75);
        backdrop-filter: blur(8px);
        color: var(--white);
        border: 1px solid rgba(255, 255, 255, 0.1);
        padding: 6px 14px;
        border-radius: var(--radius-full);
        font-size: var(--font-size-xs);
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    /* Corps de la carte */
    .member-card-body {
        padding: var(--space-4);
    }
    .member-name {
        font-size: var(--font-size-lg);
        font-weight: 600;
        color: var(--white);
        margin-bottom: 4px;
    }
    .member-meta {
        font-size: var(--font-size-sm);
        color: var(--gray-400);
        margin-bottom: var(--space-4);
    }

    /* Boutons d'action contextuels */
    .btn-action-edit {
        background: rgba(255, 255, 255, 0.05);
        color: var(--gray-200);
        border: 1px solid rgba(255, 255, 255, 0.1);
        transition: var(--transition-fast);
    }
    .btn-action-edit:hover {
        background: var(--primary-600);
        color: var(--white);
        border-color: var(--accent-blue);
    }
    .btn-action-danger {
        background: rgba(255, 71, 87, 0.1);
        color: var(--accent-red);
        border: 1px solid rgba(255, 71, 87, 0.2);
        transition: var(--transition-fast);
    }
    .btn-action-danger:hover {
        background: var(--accent-red);
        color: var(--white);
    }

    /* Alertes vides */
    .custom-alert {
        background: var(--primary-800);
        border: 1px dashed rgba(255, 255, 255, 0.1);
        color: var(--gray-300);
        border-radius: var(--radius-lg);
        padding: var(--space-6);
    }
</style>

<!-- Zone En-tête -->
<div class="page-header d-flex justify-content-between align-items-center mb-5 mt-3">
    <div>
        <h1 class="page-header-title">Membres du Bureau Étudiant</h1>
        <p class="text-muted small mb-0">Gérez l'équipe dirigeante et l'attribution de leurs rôles.</p>
    </div>
    <a href="ajouter_bureau.php" class="btn btn-add-member d-flex align-items-center gap-2">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 4v16m8-8H4"></path></svg>
        Ajouter un membre
    </a>
</div>

<!-- Grille des membres -->
<div class="row g-4">
    <?php if (!empty($members)): ?>
        <?php foreach ($members as $m): ?>
            <div class="col-xl-3 col-lg-4 col-md-6">
                <div class="card member-card h-100" onclick="window.location='detail_bureau.php?id=<?= $m['id_admin'] ?>'">
                    
                    <!-- Wrapper de la photo de profil -->
                    <div class="member-image-wrapper">
                        <?php if (!empty($m['photo']) && file_exists('../uploads/photos_etudiants/' . $m['photo'])): ?>
                            <img src="../uploads/photos_etudiants/<?= htmlspecialchars($m['photo']) ?>" class="member-img" alt="Photo de <?= htmlspecialchars($m['nom']) ?>">
                        <?php else: ?>
                            <img src="../assets/default/avatar.png" class="member-img" alt="Avatar par défaut">
                        <?php endif; ?>
                        
                        <!-- Badge du poste -->
                        <div class="badge-poste">
                            <?= htmlspecialchars($m['poste_bureau'] ?? 'Membre') ?>
                        </div>
                    </div>

                    <!-- Contenu textuel -->
                    <div class="member-card-body text-center d-flex flex-column justify-content-between">
                        <div>
                            <h5 class="member-name"><?= htmlspecialchars($m['nom'] . ' ' . $m['prenom']) ?></h5>
                            <p class="member-meta mb-3">
                                <?= htmlspecialchars($m['filiere'] ?? 'Filière non spécifiée') ?> 
                                <span class="mx-1 text-muted">•</span> 
                                <?= htmlspecialchars($m['promotion'] ?? 'N/A') ?>
                            </p>
                        </div>
                        
                        <!-- Actions de gestion -->
                        <div class="d-flex justify-content-center gap-2 mt-auto" onclick="event.stopPropagation();">
                            <a href="modifier_bureau.php?id=<?= $m['id_admin'] ?>" class="btn btn-sm btn-action-edit px-3 py-2 rounded-3 d-flex align-items-center gap-1">
                                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                Modifier
                            </a>
                            <a href="supprimer_bureau.php?id=<?= $m['id_admin'] ?>" class="btn btn-sm btn-action-danger px-3 py-2 rounded-3 d-flex align-items-center gap-1" onclick="return confirm('Voulez-vous vraiment retirer ce membre du bureau ?')">
                                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                Supprimer
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <!-- État vide (Empty State) -->
        <div class="col-12">
            <div class="custom-alert text-center d-flex flex-column align-items-center py-5">
                <svg width="48" height="48" class="text-muted mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                <h5 class="fw-semibold text-white mb-1">Aucun membre enregistré</h5>
                <p class="text-muted small mb-3">La liste du bureau exécutif étudiant est actuellement vide.</p>
                <a href="ajouter_bureau.php" class="btn btn-sm btn-add-member">Ajouter le premier membre</a>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
include '../layout.php';
?>