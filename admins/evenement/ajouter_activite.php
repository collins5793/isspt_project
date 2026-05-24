<?php
session_start();
require_once "../../includes/db.php";

$errors = [];

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nom = trim($_POST['nom_activite'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $conditions = $_POST['conditions'] ?? null;
    $academic_year_id = $_POST['academic_year_id'] ?? null;
    $accessibilite = $_POST['accessibilite'] ?? 'isspt';
    $type_participation = $_POST['type_participation'] ?? 'individuel';
    $equipe_min = $_POST['equipe_min'] ?: null;
    $equipe_max = $_POST['equipe_max'] ?: null;
    $max_participants = $_POST['max_participants'] ?: null;
    $date_limite = $_POST['date_limite_inscription'] ?: null;
    $statut = $_POST['statut'] ?? 'ouverte';

    $cree_par = $_SESSION['admin_id'] ?? null;

    // Validations
    if (!$nom) $errors[] = "Le nom de l’activité est obligatoire.";
    if (!$academic_year_id) $errors[] = "L’année académique est obligatoire.";

    if ($type_participation === 'equipe') {
        if (!$equipe_min || !$equipe_max) {
            $errors[] = "Les tailles d’équipe sont obligatoires pour une activité en équipe.";
        }
        if ($equipe_min > $equipe_max) {
            $errors[] = "Le nombre minimum ne peut pas dépasser le maximum.";
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("
            INSERT INTO activites (
                nom_activite, description, conditions,
                academic_year_id, cree_par,
                accessibilite, type_participation,
                equipe_min, equipe_max,
                max_participants, date_limite_inscription, statut
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $nom, $description, $conditions,
            $academic_year_id, $cree_par,
            $accessibilite, $type_participation,
            $equipe_min, $equipe_max,
            $max_participants, $date_limite, $statut
        ]);

        header("Location: activites.php");
        exit;
    }
}

// Années académiques
$years = $pdo->query("
    SELECT id, label FROM academic_years ORDER BY is_current DESC, label DESC
")->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>

<style>
    /* ==========================================================================
       Design System Tokens & Adaptations (Dark Theme)
       ========================================================================== */
    :root {
        --bg-main: #060018;
        --card-bg: #110933;
        --card-border: rgba(255, 255, 255, 0.06);
        --input-bg: #080020;
        --text-muted: #8a94a6;
        --focus-border: #2e86de;
    }

    .form-page-container {
        max-width: 800px;
        margin: 0 auto;
        padding: var(--space-4);
        box-sizing: border-box;
    }

    /* ==========================================================================
       Header Styles
       ========================================================================== */
    .page-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: var(--space-4);
        margin-bottom: var(--space-5);
        padding-bottom: var(--space-4);
        border-bottom: 1px solid var(--card-border);
    }

    .page-header h2 {
        font-size: 1.75rem;
        font-weight: 700;
        color: var(--white);
        margin: 0;
        letter-spacing: -0.02em;
    }

    /* ==========================================================================
       Boutons
       ========================================================================== */
    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-family: var(--font-primary);
        font-size: var(--font-size-sm);
        font-weight: 600;
        padding: var(--space-3) var(--space-5);
        border-radius: var(--radius-md);
        border: 1px solid transparent;
        cursor: pointer;
        text-decoration: none;
        transition: all var(--transition-fast);
        gap: var(--space-2);
        white-space: nowrap;
    }

    .btn-primary {
        background-color: var(--accent-blue);
        color: var(--white);
        width: 100%;
        margin-top: var(--space-4);
        font-size: var(--font-size-md);
    }

    .btn-primary:hover {
        background-color: #2475c4;
        box-shadow: 0 4px 12px rgba(46, 134, 222, 0.3);
    }

    .btn-secondary {
        background-color: var(--primary-600);
        color: var(--gray-200);
        border: 1px solid rgba(255, 255, 255, 0.08);
    }

    .btn-secondary:hover {
        background-color: var(--primary-700);
        color: var(--white);
    }

    /* ==========================================================================
       Alertes d'erreur
       ========================================================================== */
    .alert {
        background: rgba(255, 71, 87, 0.1);
        border: 1px solid rgba(255, 71, 87, 0.25);
        border-radius: var(--radius-md);
        padding: var(--space-4);
        margin-bottom: var(--space-5);
    }

    .alert ul {
        margin: 0;
        padding-left: var(--space-4);
        color: var(--accent-red);
        font-size: var(--font-size-sm);
    }

    .alert li {
        margin-bottom: var(--space-1);
    }

    .alert li:last-child {
        margin-bottom: 0;
    }

    /* ==========================================================================
       Formulaire & Intrants (Inputs)
       ========================================================================== */
    .form-container {
        background: var(--card-bg);
        border: 1px solid var(--card-border);
        border-radius: var(--radius-xl);
        padding: var(--space-6);
        box-shadow: var(--shadow-lg);
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: var(--space-4) var(--space-5);
    }

    /* Gestion des largeurs de ligne du formulaire */
    .form-group {
        display: flex;
        flex-direction: column;
        gap: var(--space-2);
        grid-column: span 1;
    }

    .form-group.full-width {
        grid-column: span 2;
    }

    .form-group label {
        font-size: var(--font-size-sm);
        font-weight: 600;
        color: var(--gray-300);
    }

    .form-control {
        background-color: var(--input-bg);
        color: var(--white);
        border: 1px solid var(--card-border);
        border-radius: var(--radius-md);
        padding: var(--space-3) var(--space-4);
        font-family: var(--font-primary);
        font-size: var(--font-size-md);
        outline: none;
        transition: border-color var(--transition-fast), box-shadow var(--transition-fast);
        width: 100%;
        box-sizing: border-box;
    }

    .form-control:focus {
        border-color: var(--focus-border);
        box-shadow: 0 0 0 3px rgba(46, 134, 222, 0.15);
    }

    textarea.form-control {
        min-height: 120px;
        resize: vertical;
    }

    /* Style spécifique pour l'input type date & datetime pour uniformiser le Dark Mode */
    input[type="datetime-local"]::-webkit-calendar-picker-indicator {
        filter: invert(1);
        cursor: pointer;
    }

    /* ==========================================================================
       Champs dynamiques Équipe (Animation controlée)
       ========================================================================== */
    .equipe-fields-wrapper {
        grid-column: span 2;
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: var(--space-4) var(--space-5);
        background: rgba(0, 0, 0, 0.15);
        padding: 0;
        margin: 0;
        border-radius: var(--radius-lg);
        border: 0 solid rgba(255, 255, 255, 0.02);
        max-height: 0;
        overflow: hidden;
        transition: max-height var(--transition-base), padding var(--transition-base), border-width var(--transition-fast), margin var(--transition-base);
    }

    .equipe-fields-wrapper.active {
        max-height: 200px;
        padding: var(--space-4);
        margin: var(--space-2) 0;
        border-width: 1px;
    }

    /* ==========================================================================
       Responsivité et Media Queries
       ========================================================================== */
    @media (max-width: 768px) {
        .form-container {
            grid-template-columns: 1fr;
            padding: var(--space-4);
            gap: var(--space-4);
        }

        .form-group, 
        .form-group.full-width,
        .equipe-fields-wrapper {
            grid-column: span 1;
        }

        .equipe-fields-wrapper.active {
            grid-template-columns: 1fr;
            max-height: 300px;
        }

        .page-header {
            flex-direction: column;
            align-items: flex-start;
            gap: var(--space-3);
        }

        .page-header .btn {
            width: 100%;
        }
    }
</style>

<div class="form-page-container">
    <!-- ENTÊTE DE LA PAGE -->
    <div class="page-header">
        <h2>Nouvelle activité</h2>
        <a href="activites.php" class="btn btn-secondary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
            Retour
        </a>
    </div>

    <!-- ZONE DE NOTIFICATION DES ERREURS -->
    <?php if ($errors): ?>
        <div class="alert alert-danger">
            <ul>
                <?php foreach ($errors as $e): ?>
                    <li><?= htmlspecialchars($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- FORMULAIRE DE CRÉATION -->
    <form method="POST" class="form-container">

        <div class="form-group full-width">
            <label for="nom_activite">Nom de l’activité *</label>
            <input type="text" id="nom_activite" name="nom_activite" class="form-control" placeholder="Ex: Tournoi de Football ISSPT 2026" required>
        </div>

        <div class="form-group full-width">
            <label for="description">Description</label>
            <textarea id="description" name="description" class="form-control" placeholder="Décrivez l'activité, le programme, les objectifs..."></textarea>
        </div>

        <div class="form-group">
            <label for="conditions">Conditions de participation</label>
            <select id="conditions" name="conditions" class="form-control">
                <option value="">-- Aucune --</option>
                <option value="etre etudiant de l'isspt">Être étudiant ISSPT</option>
                <option value="ouvert à tous">Ouvert à tous</option>
            </select>
        </div>

        <div class="form-group">
            <label for="academic_year_id">Année académique *</label>
            <select id="academic_year_id" name="academic_year_id" class="form-control" required>
                <option value="">-- Sélectionner --</option>
                <?php foreach ($years as $y): ?>
                    <option value="<?= $y['id'] ?>"><?= htmlspecialchars($y['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="accessibilite">Accessibilité</label>
            <select id="accessibilite" name="accessibilite" class="form-control">
                <option value="isspt">ISSPT uniquement</option>
                <option value="public">Tout public</option>
            </select>
        </div>

        <div class="form-group">
            <label for="typeParticipation">Type de participation</label>
            <select id="typeParticipation" name="type_participation" class="form-control">
                <option value="individuel" selected>Individuelle</option>
                <option value="equipe">Par équipe</option>
            </select>
        </div>

        <!-- ZONE DYNAMIQUE POUR LES ÉQUIPES (CONTROLLÉE VIA CLASSE CSS ACTIVE) -->
        <div id="equipeFields" class="equipe-fields-wrapper">
            <div class="form-group">
                <label for="equipe_min">Membres minimum par équipe</label>
                <input type="number" id="equipe_min" name="equipe_min" class="form-control" min="1" placeholder="2">
            </div>
            <div class="form-group">
                <label for="equipe_max">Membres maximum par équipe</label>
                <input type="number" id="equipe_max" name="equipe_max" class="form-control" min="1" placeholder="10">
            </div>
        </div>

        <div class="form-group">
            <label for="max_participants">Nombre max d'inscriptions</label>
            <input type="number" id="max_participants" name="max_participants" class="form-control" min="1" placeholder="Ex: 50 (Laissez vide si illimité)">
        </div>

        <div class="form-group">
            <label for="date_limite_inscription">Date limite d’inscription</label>
            <input type="datetime-local" id="date_limite_inscription" name="date_limite_inscription" class="form-control">
        </div>

        <div class="form-group full-width">
            <label for="statut">Statut initial</label>
            <select id="statut" name="statut" class="form-control">
                <option value="brouillon">Brouillon (Masqué)</option>
                <option value="ouverte" selected>Ouverte (Inscriptions possibles)</option>
                <option value="fermee">Fermée</option>
                <option value="terminee">Terminée</option>
            </select>
        </div>

        <div class="form-group full-width">
            <button type="submit" class="btn btn-primary">Créer l’activité</button>
        </div>
    </form>
</div>

<script>
document.getElementById('typeParticipation').addEventListener('change', function () {
    const equipeFields = document.getElementById('equipeFields');
    if (this.value === 'equipe') {
        equipeFields.classList.add('active');
    } else {
        equipeFields.classList.remove('active');
        // Optionnel : vide les champs si l'utilisateur change d'avis
        document.getElementById('equipe_min').value = '';
        document.getElementById('equipe_max').value = '';
    }
});
</script>

<?php
$content = ob_get_clean();
include "../layout.php";
?>