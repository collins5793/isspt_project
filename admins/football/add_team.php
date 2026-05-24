<?php
require_once '../../includes/db.php';
session_start();

$id_admin = $_SESSION['admin_id'] ?? null;

// Récupération des saisons de manière plus explicite (avec tri décroissant)
$stmt = $pdo->query("SELECT id_season FROM football_seasons ORDER BY id_season DESC");
$seasons = $stmt->fetchAll(PDO::FETCH_ASSOC);

$error_msg = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $season_id = filter_input(INPUT_POST, 'season_id', FILTER_VALIDATE_INT);
    $name = trim($_POST['name'] ?? '');
    $coach = trim($_POST['coach'] ?? '');

    if ($season_id && !empty($name)) {
        $sql = "INSERT INTO football_teams (season_id, name, coach, created_by) VALUES (?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$season_id, $name, $coach, $id_admin]);

        header("Location: teams_list.php?success=1");
        exit;
    } else {
        $error_msg = "Veuillez remplir correctement tous les champs obligatoires.";
    }
}

ob_start();
?>

<style>
    /* ==========================================================================
       CONTEXTE DE LA PAGE ET FORMULAIRE PREMIUM
       ========================================================================== */
    .view-container {
        font-family: var(--font-primary);
        background-color: var(--primary-900);
        color: var(--white);
        min-height: calc(100vh - 4rem);
        padding: var(--space-5) var(--space-4);
        display: flex;
        justify-content: center;
        align-items: center;
        box-sizing: border-box;
    }

    .form-card {
        background: var(--primary-800);
        border: 1px solid rgba(255, 255, 255, 0.03);
        border-radius: var(--radius-xl);
        width: 100%;
        max-width: 540px;
        padding: var(--space-6);
        box-shadow: var(--shadow-xl);
        box-sizing: border-box;
        transition: transform var(--transition-base), border-color var(--transition-base);
    }

    .form-card:hover {
        border-color: rgba(46, 134, 222, 0.2);
    }

    /* En-tête de la carte */
    .form-header {
        text-align: center;
        margin-bottom: var(--space-6);
    }

    .form-header .icon-badge {
        background-color: var(--primary-600);
        color: var(--accent-blue);
        width: 56px;
        height: 56px;
        border-radius: var(--radius-lg);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-bottom: var(--space-4);
        border: 1px solid rgba(46, 134, 222, 0.2);
        box-shadow: var(--shadow-md);
    }

    .form-header h2 {
        font-size: var(--font-size-xl);
        font-weight: 700;
        color: var(--white);
        margin: 0 0 var(--space-2) 0;
        letter-spacing: -0.02em;
    }

    .form-header p {
        color: var(--gray-400);
        font-size: var(--font-size-sm);
        margin: 0;
    }

    /* Groupes d'inputs et labels */
    .form-body {
        display: flex;
        flex-direction: column;
        gap: var(--space-4);
    }

    .input-group {
        display: flex;
        flex-direction: column;
        gap: var(--space-2);
        position: relative;
    }

    .input-group label {
        font-size: var(--font-size-sm);
        color: var(--gray-200);
        font-weight: 600;
        letter-spacing: 0.01em;
    }

    /* Éléments de formulaire standardisés */
    .input-control {
        width: 100%;
        box-sizing: border-box;
        font-family: inherit;
        font-size: var(--font-size-sm);
        color: var(--white);
        background-color: var(--primary-700);
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: var(--radius-md);
        padding: 0.85rem var(--space-4);
        outline: none;
        transition: all var(--transition-fast) ease;
    }

    .input-control:focus {
        border-color: var(--accent-blue);
        background-color: var(--primary-600);
        box-shadow: 0 0 0 4px rgba(46, 134, 222, 0.15);
    }

    /* Style spécifique pour le select */
    select.input-control {
        appearance: none;
        cursor: pointer;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%23ced6e0' viewBox='0 0 16 16'%3E%3Cpath fill-rule='evenodd' d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 1.25rem center;
        background-size: 12px;
        padding-right: 3rem;
    }

    /* Actions et boutons */
    .form-actions {
        margin-top: var(--space-3);
    }

    .btn-submit {
        width: 100%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-family: inherit;
        font-size: var(--font-size-md);
        font-weight: 600;
        color: var(--white);
        background-color: var(--accent-blue);
        border: none;
        border-radius: var(--radius-md);
        padding: 0.9rem var(--space-4);
        cursor: pointer;
        box-shadow: var(--shadow-md);
        transition: all var(--transition-base) ease;
        gap: var(--space-2);
    }

    .btn-submit:hover {
        background-color: #1e74cd;
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(46, 134, 222, 0.3);
    }

    .btn-submit:active {
        transform: translateY(0);
    }

    /* Message d'erreur personnalisé */
    .alert-danger {
        background-color: rgba(255, 71, 87, 0.1);
        border: 1px solid rgba(255, 71, 87, 0.2);
        color: var(--accent-red);
        padding: var(--space-3) var(--space-4);
        border-radius: var(--radius-md);
        font-size: var(--font-size-sm);
        margin-bottom: var(--space-4);
        display: flex;
        align-items: center;
        gap: var(--space-2);
    }

    /* Responsivité fluide */
    @media (max-width: 576px) {
        .form-card {
            padding: var(--space-4);
            border-radius: var(--radius-lg);
        }
        .form-header h2 {
            font-size: var(--font-size-lg);
        }
    }
</style>

<div class="view-container">
    <div class="form-card">
        
        <header class="form-header">
            <div class="icon-badge">
                <!-- Icône SVG représentant un maillot/équipe -->
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
            </div>
            <h2>Créer une Équipe</h2>
            <p>Renseignez les détails ci-dessous pour inclure un nouveau club.</p>
        </header>

        <?php if ($error_msg): ?>
            <div class="alert-danger">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                <span><?= htmlspecialchars($error_msg) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" class="form-body">
            
            <div class="input-group">
                <label for="season_id">Saison rattachée</label>
                <select name="season_id" id="season_id" class="input-control" required>
                    <option value="" disabled selected hidden>Sélectionnez une saison sportive...</option>
                    <?php foreach ($seasons as $s): ?>
                        <option value="<?= htmlspecialchars($s['id_season']) ?>">
                            Saison #<?= htmlspecialchars($s['id_season']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="input-group">
                <label for="name">Nom officiel de l'équipe</label>
                <input type="text" name="name" id="name" class="input-control" placeholder="Ex: AS Dragons Volants" required autocomplete="off">
            </div>

            <div class="input-group">
                <label for="coach">Nom de l'entraîneur principal</label>
                <input type="text" name="coach" id="coach" class="input-control" placeholder="Ex: Coach Jean-Pierre" autocomplete="off">
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-submit">
                    <span>Enregistrer l'équipe</span>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                </button>
            </div>

        </form>
    </div>
</div>

<?php
$content = ob_get_clean();
include '../layout.php';
?>