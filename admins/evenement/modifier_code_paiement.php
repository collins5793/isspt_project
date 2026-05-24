<?php
session_start();
require_once "../../includes/db.php";

// 1. Vérification et récupération du code à modifier
$id = $_GET['id'] ?? null;
if (!$id) {
    $_SESSION['flash_error'] = "Identifiant du code de paiement manquant.";
    header("Location: liste_codes.php");
    exit;
}

try {
    // Récupération des données du code actuel
    $stmt = $pdo->prepare("SELECT * FROM codes_paiement WHERE id = ?");
    $stmt->execute([$id]);
    $code_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$code_data) {
        $_SESSION['flash_error'] = "Le code de paiement demandé n'existe pas.";
        header("Location: liste_codes.php");
        exit;
    }
} catch (Exception $e) {
    $_SESSION['flash_error'] = "Erreur de base de données : " . $e->getMessage();
    header("Location: liste_codes.php");
    exit;
}

// 2. Récupération des événements pour le menu déroulant
try {
    $evtStmt = $pdo->query("SELECT id_concours AS id, nom_concours AS titre, frais_participation AS montant FROM concours WHERE statut = 'ouvert' OR statut = 'en_cours' ORDER BY created_at DESC");
    $evenements = $evtStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $evenements = [];
}

// 3. Traitement de la mise à jour (formulaire soumis)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom_complet = trim($_POST['nom_complet'] ?? '');
    $evenement_id = $_POST['evenement_id'] ?? '';
    $montant_associe = $_POST['montant_associe'] ?? '';
    $statut = $_POST['statut'] ?? 'disponible';
    $date_expiration = !empty($_POST['date_expiration']) ? $_POST['date_expiration'] : null;

    if (empty($nom_complet) || empty($evenement_id) || empty($montant_associe)) {
        $error_message = "Veuillez remplir tous les champs obligatoires.";
    } else {
        try {
            $updateStmt = $pdo->prepare("
                UPDATE codes_paiement 
                SET nom_complet = ?, evenement_id = ?, montant_associe = ?, statut = ?, date_expiration = ? 
                WHERE id = ?
            ");
            
            $updateStmt->execute([
                $nom_complet,
                $evenement_id,
                $montant_associe,
                $statut,
                $date_expiration,
                $id
            ]);

            $_SESSION['flash_success'] = "Le code de paiement <strong>{$code_data['code']}</strong> a été mis à jour avec succès.";
            header("Location: liste_codes.php");
            exit;

        } catch (Exception $e) {
            $error_message = "Erreur lors de la modification : " . $e->getMessage();
        }
    }
}

ob_start();
?>

<style>
    :root {
        /* Intégration stricte de vos variables globales */
        --primary-900: #080020;
        --primary-800: #0a0127;
        --primary-700: #120c3a;
        --primary-600: #1a1849;
        --accent-red: #ff4757;
        --accent-blue: #2e86de;
        --accent-green: #10ac84;
        --accent-warning: #f1c40f;
        --white: #ffffff;
        --gray-50: #f8f9fa;
        --gray-100: #f1f2f6;
        --gray-200: #dfe4ea;
        --gray-300: #ced6e0;
        --gray-400: #a4b0be;

        --font-primary: 'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif;
        
        --font-size-xs: 0.75rem;
        --font-size-sm: 0.875rem;
        --font-size-md: 1rem;
        --font-size-lg: 1.125rem;
        --font-size-xl: 1.25rem;

        --space-1: 0.25rem;
        --space-2: 0.5rem;
        --space-3: 0.75rem;
        --space-4: 1rem;
        --space-5: 1.5rem;
        --space-6: 2rem;

        --transition-fast: 150ms cubic-bezier(0.4, 0, 0.2, 1);
        --transition-base: 300ms cubic-bezier(0.4, 0, 0.2, 1);

        --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.12);
        --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.1);
        --shadow-lg: 0 10px 25px rgba(0, 0, 0, 0.2);
        --shadow-xl: 0 20px 50px rgba(0, 0, 0, 0.3);

        --radius-sm: 4px;
        --radius-md: 8px;
        --radius-lg: 12px;
        --radius-xl: 20px;
    }

    .page-wrapper {
        font-family: var(--font-primary);
        color: var(--gray-100);
        animation: fadeIn var(--transition-base);
    }

    /* En-tête de page */
    .page-header-container {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: var(--space-4);
        margin-bottom: var(--space-5);
        padding-bottom: var(--space-4);
        border-bottom: 1px solid rgba(255, 255, 255, 0.06);
    }

    .page-header-title h2 {
        font-size: 1.65rem;
        font-weight: 700;
        color: var(--white);
        letter-spacing: -0.5px;
    }

    .page-header-title p {
        color: var(--gray-400);
        font-size: var(--font-size-sm);
        margin-top: var(--space-1);
    }

    /* Badge Statique pour le Code en cours d'édition */
    .code-badge-lock {
        display: inline-block;
        font-family: 'Courier New', Courier, monospace;
        font-size: var(--font-size-lg);
        font-weight: 700;
        color: var(--white);
        background: rgba(46, 134, 222, 0.12);
        padding: var(--space-2) var(--space-4);
        border-radius: var(--radius-md);
        border: 1px solid rgba(46, 134, 222, 0.3);
        letter-spacing: 1px;
    }

    /* Boutons */
    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: var(--space-2);
        font-family: inherit;
        font-size: var(--font-size-sm);
        font-weight: 600;
        padding: 0.7rem var(--space-5);
        border-radius: var(--radius-md);
        border: 1px solid transparent;
        cursor: pointer;
        transition: all var(--transition-fast);
        text-decoration: none;
    }

    .btn:active { transform: scale(0.98); }

    .btn-primary {
        background: linear-gradient(135deg, var(--accent-blue) 0%, #1e52a4 100%);
        color: var(--white);
        box-shadow: 0 4px 15px rgba(46, 134, 222, 0.25);
    }

    .btn-primary:hover {
        background: linear-gradient(135deg, #48dbfb 0%, var(--accent-blue) 100%);
        box-shadow: 0 6px 20px rgba(46, 134, 222, 0.4);
    }

    .btn-secondary {
        background-color: var(--primary-600);
        color: var(--gray-200);
        border-color: rgba(255, 255, 255, 0.08);
    }

    .btn-secondary:hover { background-color: var(--primary-700); color: var(--white); }

    /* Card Formulaire */
    .form-container-card {
        background: linear-gradient(180deg, var(--primary-800) 0%, var(--primary-900) 100%);
        border: 1px solid rgba(255, 255, 255, 0.05);
        border-radius: var(--radius-xl);
        box-shadow: var(--shadow-xl);
        padding: var(--space-5) var(--space-6);
        max-width: 750px;
        margin: 0 auto;
    }

    /* Inputs & Structure */
    .form-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: var(--space-4);
    }

    .form-group {
        display: flex;
        flex-direction: column;
        gap: var(--space-2);
    }

    .form-group.full-width { grid-column: span 2; }

    .form-group label {
        font-size: var(--font-size-xs);
        font-weight: 600;
        color: var(--gray-300);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .form-group label .required { color: var(--accent-red); margin-left: var(--space-1); }

    .form-control {
        width: 100%;
        background-color: var(--primary-600);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: var(--radius-md);
        padding: 0.75rem var(--space-4);
        color: var(--white);
        font-family: inherit;
        font-size: var(--font-size-sm);
        transition: all var(--transition-fast);
    }

    .form-control:focus {
        outline: none;
        border-color: var(--accent-blue);
        box-shadow: 0 0 0 3px rgba(46, 134, 222, 0.15);
        background-color: var(--primary-700);
    }

    .form-actions-row {
        display: flex;
        justify-content: flex-end;
        align-items: center;
        gap: var(--space-4);
        margin-top: var(--space-5);
        padding-top: var(--space-4);
        border-top: 1px solid rgba(255, 255, 255, 0.05);
    }

    /* Alert */
    .alert-danger {
        padding: var(--space-4);
        border-radius: var(--radius-lg);
        margin-bottom: var(--space-5);
        font-size: var(--font-size-sm);
        border-left: 4px solid var(--accent-red);
        background: rgba(255, 71, 87, 0.08);
        color: #ff6b81;
    }

    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

    /* Responsivité Mobile */
    @media (max-width: 768px) {
        .page-header-container { flex-direction: column; align-items: flex-start; gap: var(--space-3); }
        .form-container-card { padding: var(--space-4) var(--space-4); }
        .form-grid { grid-template-columns: 1fr; gap: var(--space-3); }
        .form-group.full-width { grid-column: span 1; }
        .form-actions-row { flex-direction: column-reverse; gap: var(--space-2); }
        .form-actions-row .btn { width: 100%; }
    }
</style>

<div class="page-wrapper">

    <div class="page-header-container">
        <div class="page-header-title">
            <h2>⚙️ Édition Code de Paiement</h2>
            <p>Modification des détails d'accès pour l'auditeur externe.</p>
        </div>
        <div>
            <span class="code-badge-lock"><?= htmlspecialchars($code_data['code']) ?></span>
        </div>
    </div>

    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error_message); ?></div>
    <?php endif; ?>

    <div class="form-container-card">
        <form method="POST" action="">
            <div class="form-grid">
                
                <div class="form-group full-width">
                    <label for="nom_complet">Nom complet de l'auditeur <span class="required">*</span></label>
                    <input type="text" name="nom_complet" id="nom_complet" class="form-control" 
                           value="<?= htmlspecialchars($code_data['nom_complet']) ?>" required autocomplete="off">
                </div>

                <div class="form-group">
                    <label for="evenement_id">Événement Associé <span class="required">*</span></label>
                    <select name="evenement_id" id="evenement_id" class="form-control" required onchange="updatePrice(this)">
                        <option value="" disabled>Choisir un événement...</option>
                        <?php foreach ($evenements as $evt): ?>
                            <option value="<?= $evt['id'] ?>" data-price="<?= $evt['montant'] ?>" 
                                    <?= $code_data['evenement_id'] == $evt['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($evt['titre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="montant_associe">Montant lié (FCFA) <span class="required">*</span></label>
                    <input type="number" step="0.01" name="montant_associe" id="montant_associe" class="form-control" 
                           value="<?= htmlspecialchars($code_data['montant_associe']) ?>" required>
                </div>

                <div class="form-group">
                    <label for="statut">Statut d'accès <span class="required">*</span></label>
                    <select name="statut" id="statut" class="form-control" required>
                        <option value="disponible" <?= $code_data['statut'] === 'disponible' ? 'selected' : '' ?>>Disponible (Actif)</option>
                        <option value="utilise" <?= $code_data['statut'] === 'utilise' ? 'selected' : '' ?>>Utilisé</option>
                        <option value="expire" <?= $code_data['statut'] === 'expire' ? 'selected' : '' ?>>Expiré / Révoqué</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="date_expiration">Date limite de validité</label>
                    <input type="datetime-local" name="date_expiration" id="date_expiration" class="form-control"
                           value="<?= $code_data['date_expiration'] ? date('Y-m-d\TH:i', strtotime($code_data['date_expiration'])) : '' ?>">
                </div>

            </div>

            <div class="form-actions-row">
                <a href="liste_codes.php" class="btn btn-secondary">Retour à la liste</a>
                <button type="submit" class="btn btn-primary">💾 Enregistrer les modifications</button>
            </div>
        </form>
    </div>
</div>

<script>
    function updatePrice(selectElement) {
        const selectedOption = selectElement.options[selectElement.selectedIndex];
        const price = selectedOption.getAttribute('data-price');
        if (price !== null) {
            document.getElementById('montant_associe').value = price;
        }
    }
</script>

<?php
$content = ob_get_clean();
include "../layout.php";
?>