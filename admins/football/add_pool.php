<?php
require_once '../../includes/db.php';
session_start();
$id_admin = $_SESSION['admin_id'] ?? null;
if (!$id_admin) die("Accès refusé");

// Charger saisons
$seasons = $pdo->query("SELECT id_season FROM football_seasons ORDER BY id_season DESC")->fetchAll(PDO::FETCH_ASSOC);

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $season_id = $_POST['season_id'] ?? null;
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';

    if (!empty($name) && !empty($season_id)) {
        $stmt = $pdo->prepare("INSERT INTO pools (season_id, name) VALUES (?,?)");
        $stmt->execute([$season_id, $name]);

        header("Location: pools_list.php?success=1");
        exit;
    } else {
        $error = "Veuillez remplir tous les champs correctement.";
    }
}

ob_start();
?>

<style>
    /* ==========================================================================
       VARIABLES RACINES & CONFIGURATION CORE
       ========================================================================== */
    :root {
        /* Colors - Dark Theme */
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

        /* Typography */
        --font-primary: 'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif;
        --font-size-xs: 0.75rem;
        --font-size-sm: 0.875rem;
        --font-size-md: 1rem;
        --font-size-lg: 1.125rem;
        --font-size-xl: 1.25rem;
        
        /* Spacing */
        --space-1: 0.25rem;
        --space-2: 0.5rem;
        --space-3: 0.75rem;
        --space-4: 1rem;
        --space-5: 1.5rem;
        --space-6: 2rem;
        
        /* Transitions */
        --transition-fast: 150ms cubic-bezier(0.4, 0, 0.2, 1);
        --transition-base: 300ms cubic-bezier(0.4, 0, 0.2, 1);
        
        /* Shadows */
        --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.1);
        --shadow-lg: 0 10px 25px rgba(0, 0, 0, 0.2);
        
        /* Border Radius */
        --radius-md: 8px;
        --radius-lg: 12px;
    }

    /* Interface Container */
    .page-container {
        font-family: var(--font-primary);
        color: var(--gray-100);
        max-width: 680px;
        margin: var(--space-6) auto;
        padding: 0 var(--space-4);
        box-sizing: border-box;
    }

    /* Header Stylisé */
    .page-header {
        margin-bottom: var(--space-5);
    }

    .page-header h2 {
        font-size: 1.6rem;
        font-weight: 700;
        color: var(--white);
        margin: 0 0 var(--space-1) 0;
        letter-spacing: -0.02em;
    }

    .page-header p {
        color: var(--gray-400);
        font-size: var(--font-size-sm);
        margin: 0;
    }

    /* Carte Principale */
    .form-card {
        background-color: var(--primary-800);
        border: 1px solid rgba(255, 255, 255, 0.05);
        border-radius: var(--radius-lg);
        padding: var(--space-5) var(--space-6);
        box-shadow: var(--shadow-lg);
    }

    /* Groupes de champs */
    .form-group {
        display: flex;
        flex-direction: column;
        gap: var(--space-2);
        margin-bottom: var(--space-5);
    }

    .form-group label {
        font-size: var(--font-size-sm);
        font-weight: 600;
        color: var(--gray-300);
        letter-spacing: 0.02em;
    }

    /* Inputs et Selects unifiés */
    .form-control {
        width: 100%;
        box-sizing: border-box;
        font-family: inherit;
        font-size: var(--font-size-md);
        color: var(--white);
        background-color: var(--primary-700);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: var(--radius-md);
        padding: 0.8rem var(--space-4);
        outline: none;
        transition: all var(--transition-fast) ease;
    }

    .form-control:focus {
        border-color: var(--accent-blue);
        background-color: var(--primary-600);
        box-shadow: 0 0 0 3px rgba(46, 134, 222, 0.15);
    }

    /* Flèche personnalisée pour le select natif */
    select.form-control {
        appearance: none;
        cursor: pointer;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%23ced6e0' viewBox='0 0 16 16'%3E%3Cpath fill-rule='evenodd' d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right var(--space-4) center;
        background-size: 12px;
        padding-right: var(--space-6);
    }

    /* Alertes d'erreur */
    .alert-error {
        background-color: rgba(255, 71, 87, 0.1);
        border: 1px solid rgba(255, 71, 87, 0.2);
        color: var(--accent-red);
        padding: var(--space-3) var(--space-4);
        border-radius: var(--radius-md);
        font-size: var(--font-size-sm);
        margin-bottom: var(--space-4);
    }

    /* Actions / Boutons */
    .form-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: var(--space-4);
        margin-top: var(--space-6);
        padding-top: var(--space-4);
        border-top: 1px solid rgba(255, 255, 255, 0.05);
    }

    .btn {
        font-family: inherit;
        font-size: var(--font-size-sm);
        font-weight: 600;
        padding: 0.75rem var(--space-5);
        border-radius: var(--radius-md);
        cursor: pointer;
        transition: all var(--transition-fast) ease;
        text-decoration: none;
        text-align: center;
    }

    .btn-primary {
        background-color: var(--accent-blue);
        color: var(--white);
        border: none;
        box-shadow: var(--shadow-md);
    }

    .btn-primary:hover {
        background-color: #4834d4; /* Teinte bleue plus vive au survol */
        transform: translateY(-1px);
    }

    .btn-primary:active {
        transform: translateY(0);
    }

    .btn-secondary {
        background-color: transparent;
        color: var(--gray-400);
        border: 1px solid rgba(255, 255, 255, 0.1);
    }

    .btn-secondary:hover {
        color: var(--white);
        border-color: rgba(255, 255, 255, 0.2);
        background-color: rgba(255, 255, 255, 0.02);
    }

    /* Responsivité Mobile sans défaut */
    @media (max-width: 480px) {
        .page-container {
            margin: var(--space-4) auto;
        }
        
        .form-card {
            padding: var(--space-4) var(--space-4);
        }

        .form-actions {
            flex-direction: column-reverse;
            gap: var(--space-3);
        }

        .btn {
            width: 100%;
        }
    }
</style>

<div class="page-container">
    
    <header class="page-header">
        <h2>Ajouter une poule</h2>
        <p>Créez une nouvelle subdivision de compétition pour la saison sélectionnée.</p>
    </header>

    <div class="form-card">
        <?php if ($error): ?>
            <div class="alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            
            <div class="form-group">
                <label for="season_id">Saison sportive</label>
                <select name="season_id" id="season_id" class="form-control" required>
                    <?php foreach ($seasons as $s): ?>
                        <option value="<?= $s['id_season'] ?>">Saison #<?= $s['id_season'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="name">Nom de la poule</label>
                <input type="text" name="name" id="name" class="form-control" placeholder="Ex: Poule A, Groupe Élite..." required>
            </div>

            <div class="form-actions">
                <a href="pools_list.php" class="btn btn-secondary">Annuler</a>
                <button type="submit" class="btn btn-primary">Créer la poule</button>
            </div>

        </form>
    </div>

</div>

<?php
$content = ob_get_clean();
include '../layout.php';
?>