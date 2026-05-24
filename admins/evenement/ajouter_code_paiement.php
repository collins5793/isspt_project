<?php
session_start();
require_once "../../includes/db.php";

// 1. Récupération des événements pour l'association dans le select
try {
    // On suppose que votre table d'événements s'appelle 'evenements' ou 'concours'
    // Ajustez le nom de la table ou des colonnes si nécessaire
    $evtStmt = $pdo->query("SELECT id_concours AS id, nom_concours AS titre, frais_participation AS montant FROM concours WHERE statut = 'ouvert' OR statut = 'en_cours' ORDER BY created_at DESC");
    $evenements = $evtStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $evenements = [];
}

// 2. Traitement du formulaire d'ajout
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom_complet = trim($_POST['nom_complet'] ?? '');
    $evenement_id = $_POST['evenement_id'] ?? '';
    $montant_associe = $_POST['montant_associe'] ?? '';
    $date_expiration = !empty($_POST['date_expiration']) ? $_POST['date_expiration'] : null;

    if (empty($nom_complet) || empty($evenement_id) || empty($montant_associe)) {
        $_SESSION['flash_error'] = "Veuillez remplir tous les champs obligatoires (Nom complet, Événement et Montant).";
    } else {
        try {
            $code_genere = '';
            $is_unique = false;

            // Boucle de sécurité pour garantir l'unicité absolue du code unique
            while (!$is_unique) {
                // Génère un code propre de type : EVT-A8F2-99B1
                $token = strtoupper(bin2hex(random_bytes(4))); 
                $code_genere = "EVT-" . substr($token, 0, 4) . "-" . substr($token, 4, 4);

                // Vérification de l'existence en BDD
                $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM codes_paiement WHERE code = ?");
                $checkStmt->execute([$code_genere]);
                if ($checkStmt->fetchColumn() == 0) {
                    $is_unique = true;
                }
            }

            // Insertion dans la table codes_paiement
            $insertStmt = $pdo->prepare("
                INSERT INTO codes_paiement (code, nom_complet, statut, montant_associe, evenement_id, date_expiration) 
                VALUES (?, ?, 'disponible', ?, ?, ?)
            ");
            
            $insertStmt->execute([
                $code_genere,
                $nom_complet,
                $montant_associe,
                $evenement_id,
                $date_expiration
            ]);

            $_SESSION['flash_success'] = "Le code de paiement a été généré avec succès ! Code : <strong>$code_genere</strong> pour $nom_complet.";
            header("Location: ajouter_code_paiement.php"); // Redirection pour éviter le double envoi au rafraîchissement
            exit;

        } catch (Exception $e) {
            $_SESSION['flash_error'] = "Erreur lors de la génération du code : " . $e->getMessage();
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
        --white: #ffffff;
        --gray-50: #f8f9fa;
        --gray-100: #f1f2f6;
        --gray-200: #dfe4ea;
        --gray-300: #ced6e0;
        --gray-400: #a4b0be;

        --sidebar-width: 280px;
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
        --radius-full: 9999px;
    }

    /* --- Style Principal & Structure --- */
    .page-wrapper {
        font-family: var(--font-primary);
        color: var(--gray-100);
        background-color: transparent;
        animation: fadeIn var(--transition-base);
    }

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

    /* --- Boutons Pro --- */
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
        white-space: nowrap;
    }

    .btn:active { transform: scale(0.98); }

    .btn-primary {
        background: linear-gradient(135deg, var(--accent-green) 0%, #0c8f6e 100%);
        color: var(--white);
        box-shadow: 0 4px 15px rgba(16, 172, 132, 0.25);
    }

    .btn-primary:hover {
        background: linear-gradient(135deg, #1dd1a1 0%, var(--accent-green) 100%);
        box-shadow: 0 6px 20px rgba(16, 172, 132, 0.4);
    }

    .btn-secondary {
        background-color: var(--primary-600);
        color: var(--gray-200);
        border-color: rgba(255, 255, 255, 0.08);
    }

    .btn-secondary:hover {
        background-color: var(--primary-700);
        color: var(--white);
    }

    /* --- Card du Formulaire Épuré --- */
    .form-container-card {
        background: linear-gradient(180deg, var(--primary-800) 0%, var(--primary-900) 100%);
        border: 1px solid rgba(255, 255, 255, 0.05);
        border-radius: var(--radius-xl);
        box-shadow: var(--shadow-xl);
        padding: var(--space-5) var(--space-6);
        max-width: 750px;
        margin: 0 auto;
    }

    /* --- Architecture Inputs --- */
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

    .form-group.full-width {
        grid-column: span 2;
    }

    .form-group label {
        font-size: var(--font-size-xs);
        font-weight: 600;
        color: var(--gray-300);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .form-group label .required {
        color: var(--accent-red);
        margin-left: var(--space-1);
    }

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

    .form-control::placeholder {
        color: var(--gray-400);
        opacity: 0.6;
    }

    /* Zone d'actions en bas */
    .form-actions-row {
        display: flex;
        justify-content: flex-end;
        align-items: center;
        gap: var(--space-4);
        margin-top: var(--space-5);
        padding-top: var(--space-4);
        border-top: 1px solid rgba(255, 255, 255, 0.05);
    }

    /* --- Messages Flash modernisés --- */
    .alert {
        padding: var(--space-4);
        border-radius: var(--radius-lg);
        margin-bottom: var(--space-5);
        font-size: var(--font-size-sm);
        border-left: 4px solid transparent;
        backdrop-filter: blur(10px);
        animation: slideDown var(--transition-base);
    }

    .alert-success {
        background: rgba(16, 172, 132, 0.08);
        border-color: var(--accent-green);
        color: #1dd1a1;
    }

    .alert-danger {
        background: rgba(255, 71, 87, 0.08);
        border-color: var(--accent-red);
        color: #ff6b81;
    }

    /* --- Animations --- */
    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    @keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

    /* ==========================================================================
       RESPONSIVITÉ SANS DÉFAUT (TOUT ÉCRAN)
       ========================================================================== */
    @media (max-width: 768px) {
        .page-header-container {
            flex-direction: column;
            align-items: flex-start;
            gap: var(--space-3);
        }

        .page-header-container .btn {
            width: 100%;
        }

        .form-container-card {
            padding: var(--space-4) var(--space-4);
        }

        .form-grid {
            grid-template-columns: 1fr; /* Passage sur une seule colonne */
            gap: var(--space-3);
        }

        .form-group.full-width {
            grid-column: span 1;
        }

        .form-actions-row {
            flex-direction: column-reverse;
            gap: var(--space-2);
        }

        .form-actions-row .btn {
            width: 100%;
        }
    }
</style>

<div class="page-wrapper">

    <div class="page-header-container">
        <div class="page-header-title">
            <h2>🔑 Générateur de Code Externe</h2>
            <p>Créez des accès uniques pour les personnes hors établissement afin qu'elles récupèrent leur ticket.</p>
        </div>
        <a href="liste_codes.php" class="btn btn-secondary">📋 Voir la liste des codes</a>
    </div>

    <?php if (isset($_SESSION['flash_success'])): ?>
        <div class="alert alert-success"><?= $_SESSION['flash_success']; unset($_SESSION['flash_success']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['flash_error'])): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div>
    <?php endif; ?>

    <div class="form-container-card">
        <form method="POST" action="">
            <div class="form-grid">
                
                <div class="form-group full-width">
                    <label for="nom_complet">Nom complet de l'auditeur <span class="required">*</span></label>
                    <input type="text" name="nom_complet" id="nom_complet" class="form-control" placeholder="Ex: Jean Marc KOFFI" required autocomplete="off">
                </div>

                <div class="form-group">
                    <label for="evenement_id">Associer à un Événement <span class="required">*</span></label>
                    <select name="evenement_id" id="evenement_id" class="form-control" required onchange="updatePrice(this)">
                        <option value="" disabled selected>Choisir un événement...</option>
                        <?php foreach ($evenements as $evt): ?>
                            <option value="<?= $evt['id'] ?>" data-price="<?= $evt['montant'] ?>">
                                <?= htmlspecialchars($evt['titre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="montant_associe">Montant à payer (FCFA) <span class="required">*</span></label>
                    <input type="number" step="0.01" name="montant_associe" id="montant_associe" class="form-control" placeholder="Ex: 5000" required>
                </div>

                <div class="form-group full-width">
                    <label for="date_expiration">Date d'expiration du code (Optionnel)</label>
                    <input type="datetime-local" name="date_expiration" id="date_expiration" class="form-control">
                </div>

            </div>

            <div class="form-actions-row">
                <a href="concours.php" class="btn btn-secondary">Annuler</a>
                <button type="submit" class="btn btn-primary">⚡ Générer le Code Unique</button>
            </div>
        </form>
    </div>

</div>

<script>
    function updatePrice(selectElement) {
        // Récupère l'option courante sélectionnée
        const selectedOption = selectElement.options[selectElement.selectedIndex];
        // Extrait le prix stocké dans l'attribut data-price
        const price = selectedOption.getAttribute('data-price');
        // Injecte la valeur trouvée directement dans le champ de saisie du montant
        if (price !== null) {
            document.getElementById('montant_associe').value = price;
        }
    }
</script>

<?php
$content = ob_get_clean();
include "../layout.php";
?>