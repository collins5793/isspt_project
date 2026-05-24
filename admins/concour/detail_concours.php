<?php
session_start();
require_once "../../includes/db.php";

// 1. Récupération et vérification de l'ID du concours
$id_concours = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id_concours <= 0) {
    header("Location: concours.php");
    exit;
}

$errors = [];
$success_msg = false;

/* ==========================================================================
   TRAITEMENTS DES ACTIONS (POST)
   ========================================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // --- ACTION : CHANGER L'ÉTAPE / PHASE ACTUELLE ---
    if ($action === 'passer_etape') {
        $nouvelle_phase = !empty($_POST['phase_actuelle']) ? intval($_POST['phase_actuelle']) : null;
        try {
            $stmt = $pdo->prepare("UPDATE concours_participants SET phase_actuelle = ? WHERE id_concours = ? AND statut IN ('valide', 'qualifie', 'finaliste')");
            $stmt->execute([$nouvelle_phase, $id_concours]);
            $success_msg = "L'étape actuelle des participants actifs a été mise à jour !";
        } catch (Exception $e) {
            $errors[] = "Erreur étape : " . $e->getMessage();
        }
    }

    // --- ACTION : AJOUTER OU MODIFIER UN JURY ---
    if ($action === 'save_jury') {
        $id_jury = !empty($_POST['id_jury']) ? intval($_POST['id_jury']) : null;
        $nom = trim($_POST['nom_jury'] ?? '');
        $prenom = trim($_POST['prenom_jury'] ?? null);
        $email = trim($_POST['email_jury'] ?? null);
        $telephone = trim($_POST['tel_jury'] ?? null);
        $profession = trim($_POST['profession_jury'] ?? null);

        if (empty($nom)) {
            $errors[] = "Le nom du membre du jury est obligatoire.";
        } else {
            try {
                if ($id_jury) {
                    $stmt = $pdo->prepare("UPDATE concours_jury SET nom = ?, prenom = ?, email = ?, telephone = ?, profession = ? WHERE id_jury = ? AND id_concours = ?");
                    $stmt->execute([$nom, $prenom, $email, $telephone, $profession, $id_jury, $id_concours]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO concours_jury (id_concours, nom, prenom, email, telephone, profession) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$id_concours, $nom, $prenom, $email, $telephone, $profession]);
                }
                $success_msg = "Membre du jury enregistré avec succès !";
            } catch (Exception $e) {
                $errors[] = "Erreur Jury : " . $e->getMessage();
            }
        }
    }

    // --- ACTION : SUPPRIMER UN JURY ---
    if ($action === 'delete_jury') {
        $id_jury = intval($_POST['id_jury'] ?? 0);
        try {
            $stmt = $pdo->prepare("DELETE FROM concours_jury WHERE id_jury = ? AND id_concours = ?");
            $stmt->execute([$id_jury, $id_concours]);
            $success_msg = "Membre du jury retiré.";
        } catch (Exception $e) {
            $errors[] = "Impossible de supprimer ce jury.";
        }
    }

    // --- ACTION : CHANGER LE STATUT D'UN PARTICIPANT ---
    if ($action === 'update_participant_status') {
        $id_part = intval($_POST['id_participant'] ?? 0);
        $statut_part = $_POST['statut_participant'] ?? 'en_attente';
        try {
            $stmt = $pdo->prepare("UPDATE concours_participants SET statut = ? WHERE id_participant = ? AND id_concours = ?");
            $stmt->execute([$statut_part, $id_part, $id_concours]);
            $success_msg = "Statut du participant mis à jour.";
        } catch (Exception $e) {
            $errors[] = "Erreur statut participant.";
        }
    }

    // --- ACTION : SUPPRIMER UN PARTICIPANT ---
    if ($action === 'delete_participant') {
        $id_part = intval($_POST['id_participant'] ?? 0);
        try {
            $stmt = $pdo->prepare("DELETE FROM concours_participants WHERE id_participant = ? AND id_concours = ?");
            $stmt->execute([$id_part, $id_concours]);
            $success_msg = "Participant supprimé définitivement.";
        } catch (Exception $e) {
            $errors[] = "Erreur lors de la suppression du participant.";
        }
    }

    // --- ACTION : ENREGISTRER UNE NOTE DU JURY ---
    if ($action === 'save_note') {
        $id_part = intval($_POST['id_participant'] ?? 0);
        $id_jury = intval($_POST['id_jury'] ?? 0);
        $note = floatval($_POST['note'] ?? 0);
        $commentaire = trim($_POST['commentaire'] ?? null);

        try {
            // Vérifier si la note existe déjà
            $check = $pdo->prepare("SELECT id_note FROM concours_notes WHERE id_concours = ? AND id_participant = ? AND id_jury = ?");
            $check->execute([$id_concours, $id_part, $id_jury]);
            
            if ($check->fetch()) {
                $stmt = $pdo->prepare("UPDATE concours_notes SET note = ?, commentaire = ? WHERE id_concours = ? AND id_participant = ? AND id_jury = ?");
                $stmt->execute([$note, $commentaire, $id_concours, $id_part, $id_jury]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO concours_notes (id_concours, id_participant, id_jury, note, commentaire) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$id_concours, $id_part, $id_jury, $note, $commentaire]);
            }

            // Recalculer automatiquement la moyenne générale du participant (score)
            $calc = $pdo->prepare("SELECT AVG(note) as moyenne FROM concours_notes WHERE id_participant = ?");
            $calc->execute([$id_part]);
            $moyenne = floatval($calc->fetch(PDO::FETCH_ASSOC)['moyenne'] ?? 0);

            $upParticipant = $pdo->prepare("UPDATE concours_participants SET score = ? WHERE id_participant = ?");
            $upParticipant->execute([$moyenne, $id_part]);

            $success_msg = "Note attribuée et moyenne mise à jour avec succès !";
        } catch (Exception $e) {
            $errors[] = "Erreur attribution note : " . $e->getMessage();
        }
    }

    // --- ACTION : ENREGISTRER UN GAGNANT ---
    if ($action === 'save_gagnant') {
        $id_part = intval($_POST['id_participant'] ?? 0);
        $position = $_POST['position'] ?? 'gagnant';
        $titre_prix = trim($_POST['titre_prix'] ?? null);
        $montant = floatval($_POST['montant_prix'] ?? 0.00);
        $cadeau = trim($_POST['cadeau'] ?? null);

        try {
            $pdo->beginTransaction();

            // Insérer ou écraser dans la table des gagnants
            $stmt = $pdo->prepare("INSERT INTO concours_gagnants (id_concours, id_participant, position, titre_prix, montant_prix, cadeau) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$id_concours, $id_part, $position, $titre_prix, $montant, $cadeau]);

            // Mettre à jour le statut du participant en tant que 'gagnant'
            $upStatut = $pdo->prepare("UPDATE concours_participants SET statut = 'gagnant' WHERE id_participant = ?");
            $upStatut->execute([$id_part]);

            // Mettre automatiquement le concours au statut "termine" si c'est le vainqueur principal
            if ($position === 'gagnant' || $position === '1er') {
                $upConcours = $pdo->prepare("UPDATE concours SET statut = 'termine' WHERE id_concours = ?");
                $upConcours->execute([$id_concours]);
            }

            $pdo->commit();
            $success_msg = "Le lauréat a été officiellement enregistré !";
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Erreur enregistrement gagnant : " . $e->getMessage();
        }
    }
}

/* ==========================================================================
   CHARGEMENT DE TOUTES LES DONNÉES DE LA PAGE
   ========================================================================== */

// 1. Infos du concours
$stmt = $pdo->prepare("SELECT c.*, y.label as annee_academique 
                       FROM concours c 
                       LEFT JOIN academic_years y ON c.academic_year_id = y.id 
                       WHERE c.id_concours = ?");
$stmt->execute([$id_concours]);
$concours = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$concours) {
    die("Concours introuvable.");
}

// 2. Les phases / étapes
$stmt = $pdo->prepare("SELECT * FROM concours_phases WHERE id_concours = ? ORDER BY ordre_phase ASC");
$stmt->execute([$id_concours]);
$phases = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Trouver l'étape actuelle basée sur l'état des participants (juste pour de l'affichage indicatif)
$phase_actuelle_id = null;

// 3. Les Membres du Jury
$stmt = $pdo->prepare("SELECT * FROM concours_jury WHERE id_concours = ? ORDER BY nom ASC");
$stmt->execute([$id_concours]);
$jurys = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 4. Les Participants (avec le compte de leurs votes)
$queryPart = "SELECT p.*, e.matricule, 
              (SELECT COUNT(*) FROM concours_votes v WHERE v.id_participant = p.id_participant) as total_votes,
              ph.nom_phase as phase_nom
              FROM concours_participants p
              LEFT JOIN etudiants e ON p.id_etudiant = e.id_etudiant
              LEFT JOIN concours_phases ph ON p.phase_actuelle = ph.id_phase
              WHERE p.id_concours = ?
              ORDER BY p.score DESC, total_votes DESC";
$stmt = $pdo->prepare($queryPart);
$stmt->execute([$id_concours]);
$participants = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 5. Les Lauréats / Gagnants déjà enregistrés
$stmt = $pdo->prepare("SELECT g.*, p.nom_complet, p.id_etudiant FROM concours_gagnants g 
                       JOIN concours_participants p ON g.id_participant = p.id_participant 
                       WHERE g.id_concours = ? ORDER BY g.position ASC");
$stmt->execute([$id_concours]);
$gagnants = $stmt->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>

<!-- EN-TÊTE DU CONCOURS -->
<div class="page-header-container">
    <div class="page-header-title">
        <div class="badge-type text-uppercase mb-1"><?= htmlspecialchars($concours['type_concours']) ?></div>
        <h2>🏆 <?= htmlspecialchars($concours['nom_concours']) ?></h2>
        <p class="text-muted"><i class="icon">📅</i> Année Académique : <strong><?= htmlspecialchars($concours['annee_academique'] ?? 'N/A') ?></strong> | Organisé par : <strong><?= htmlspecialchars($concours['organisateur'] ?? 'Non spécifié') ?></strong></p>
    </div>
    <div class="header-actions">
        <span class="badge-status status-<?= $concours['statut'] ?>"><?= ucfirst($concours['statut']) ?></span>
        <a href="modifier_concours.php?id=<?= $id_concours ?>" class="btn btn-warning">⚙️ Modifier</a>
        <a href="concours.php" class="btn btn-secondary">← Retour</a>
    </div>
</div>

<!-- ALERTES DE RETOUR NOTIFICATION -->
<?php if ($success_msg): ?>
    <div class="alert alert-success">🎉 <?= $success_msg ?></div>
<?php endif; ?>
<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul><?php foreach($errors as $e): ?> <li><?= htmlspecialchars($e) ?></li> <?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<!-- COMPTEURS CLÉS CLIGNOTANTS -->
<div class="stats-grid-dashboard mb-4">
    <div class="stat-box-mini">
        <span class="title">Participants</span>
        <span class="value"><?= count($participants) ?> <small>/ <?= $concours['max_participants'] ?? '∞' ?></small></span>
    </div>
    <div class="stat-box-mini">
        <span class="title">Membres Jury</span>
        <span class="value"><?= count($jurys) ?></span>
    </div>
    <div class="stat-box-mini">
        <span class="title">Votes Totaux</span>
        <span class="value"><?= array_sum(array_column($participants, 'total_votes')) ?></span>
    </div>
    <div class="stat-box-mini">
        <span class="title">Mode Sélection</span>
        <span class="value text-capitalize"><?= str_replace('_', ' ', $concours['mode_selection']) ?></span>
    </div>
</div>

<!-- CONTAINER DU SYSTÈME D'ONGLETS -->
<div class="tabs-container">
    <div class="tabs-header">
        <button class="tab-trigger active" onclick="openTab(event, 'tab-vue-ensemble')">📋 Vue d'ensemble</button>
        <button class="tab-trigger" onclick="openTab(event, 'tab-participants')">👥 Participants (<?= count($participants) ?>)</button>
        <button class="tab-trigger" onclick="openTab(event, 'tab-jury')">🧑‍⚖️ Jury & Notation (<?= count($jurys) ?>)</button>
        <button class="tab-trigger" onclick="openTab(event, 'tab-votes')">📊 Suivi des Votes</button>
        <button class="tab-trigger" onclick="openTab(event, 'tab-cloture')">👑 Clôture & Gagnants</button>
    </div>

    <!-- 1. ONGLET : VUE D'ENSEMBLE -->
    <div id="tab-vue-ensemble" class="tab-content active">
        <div class="grid-details-layout">
            <div class="detail-main-card">
                <h3>Description du Concours</h3>
                <p><?= nl2br(htmlspecialchars($concours['description'] ?? 'Aucune description disponible.')) ?></p>
                
                <h3 class="mt-4">Règlement intérieur</h3>
                <p class="text-muted"><?= nl2br(htmlspecialchars($concours['reglement'] ?? 'Aucun règlement spécifié.')) ?></p>

                <h3 class="mt-4">Conditions d'éligibilité</h3>
                <p class="text-muted"><?= nl2br(htmlspecialchars($concours['conditions_participation'] ?? 'Aucune condition spécifique.')) ?></p>
            </div>

            <div class="detail-sidebar-card">
                <h3>Informations Logistiques</h3>
                <ul class="info-list">
                    <li>📍 <strong>Lieu:</strong> <?= htmlspecialchars($concours['lieu'] ?? 'Non défini') ?></li>
                    <li>📅 <strong>Début:</strong> <?= date('d/m/Y H:i', strtotime($concours['date_debut'])) ?></li>
                    <li>📅 <strong>Clôture:</strong> <?= $concours['date_fin'] ? date('d/m/Y H:i', strtotime($concours['date_fin'])) : 'Non fixée' ?></li>
                    <li>⏳ <strong>Inscription limite:</strong> <?= $concours['date_limite_inscription'] ? date('d/m/Y H:i', strtotime($concours['date_limite_inscription'])) : 'Aucune' ?></li>
                    <li>💰 <strong>Frais d'inscription:</strong> <?= $concours['participation_gratuite'] ? '<span class="text-success">Gratuit</span>' : number_format($concours['frais_participation'], 0, ',', ' ') . ' FCFA' ?></li>
                </ul>

                <hr class="my-3">

                <!-- ETAPE / PHASE DU CONCOURS ACTUELLE -->
                <h3>Étape active du Concours</h3>
                <form action="" method="POST" class="mt-2">
                    <input type="hidden" name="action" value="passer_etape">
                    <div class="form-group">
                        <select name="phase_actuelle" class="form-control mb-2">
                            <option value="">-- Aucune phase active --</option>
                            <?php foreach ($phases as $ph): ?>
                                <option value="<?= $ph['id_phase'] ?>" 
                                    <?php 
                                        // On vérifie si un participant au moins possède cette phase
                                        $is_current = in_array($ph['id_phase'], array_column($participants, 'phase_actuelle'));
                                        if($is_current) echo 'selected';
                                    ?>>
                                    Étape <?= $ph['ordre_phase'] ?> : <?= htmlspecialchars($ph['nom_phase']) ?> (<?= htmlspecialchars($ph['type_phase']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-info btn-block btn-sm">Mettre à jour l'étape des candidats</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- 2. ONGLET : PARTICIPANTS -->
    <div id="tab-participants" class="tab-content">
        <div class="section-header-actions mb-3">
            <h3>Gestion des Participants engagés</h3>
        </div>

        <div class="table-responsive">
            <table class="table-modern-style">
                <thead>
                    <tr>
                        <th>N° Candidat</th>
                        <th>Nom Complet</th>
                        <th>Contact</th>
                        <th>Étape Actuelle</th>
                        <th>Score Moyen</th>
                        <th>Statut</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($participants)): ?>
                        <tr><td colspan="7" class="text-center text-muted">Aucun candidat enregistré pour le moment.</td></tr>
                    <?php else: ?>
                        <?php foreach ($participants as $p): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($p['numero_participant'] ?? 'N/A') ?></strong></td>
                                <td>
                                    <div class="user-avatar-cell">
                                        <span><?= htmlspecialchars($p['nom_complet'] ?? 'Étudiant ISSPT') ?></span>
                                        <?php if ($p['matricule']): ?><br><small class="text-muted">Matricule: <?= $p['matricule'] ?></small><?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <small><?= htmlspecialchars($p['email'] ?? '-') ?></small><br>
                                    <small><?= htmlspecialchars($p['telephone'] ?? '-') ?></small>
                                </td>
                                <td><span class="badge-phase"><?= htmlspecialchars($p['phase_nom'] ?? 'Inscription') ?></span></td>
                                <td><span class="text-primary font-weight-bold"><?= number_format($p['score'], 2) ?> / 20</span></td>
                                <td>
                                    <form action="" method="POST" class="inline-form">
                                        <input type="hidden" name="action" value="update_participant_status">
                                        <input type="hidden" name="id_participant" value="<?= $p['id_participant'] ?>">
                                        <select name="statut_participant" onchange="this.form.submit()" class="select-table-status status-<?= $p['statut'] ?>">
                                            <?php foreach(['en_attente','valide','refuse','qualifie','elimine','finaliste','gagnant'] as $st): ?>
                                                <option value="<?= $st ?>" <?= $p['statut'] === $st ? 'selected' : '' ?>><?= ucfirst($st) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </form>
                                </td>
                                <td class="text-center">
                                    <form action="" method="POST" onsubmit="return confirm('Supprimer ce candidat définitivement du concours ?');" style="display:inline;">
                                        <input type="hidden" name="action" value="delete_participant">
                                        <input type="hidden" name="id_participant" value="<?= $p['id_participant'] ?>">
                                        <button type="submit" class="btn-action-delete">🗑️</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 3. ONGLET : JURY & NOTATION -->
    <div id="tab-jury" class="tab-content">
        <div class="grid-details-layout">
            <!-- Formulaire Jury -->
            <div class="detail-sidebar-card">
                <h3 id="jury-form-title">Ajouter un Membre du Jury</h3>
                <hr>
                <form action="" method="POST" id="form-jury">
                    <input type="hidden" name="action" value="save_jury">
                    <input type="hidden" name="id_jury" id="id_jury" value="">

                    <div class="form-group mb-2">
                        <label>Nom <span class="required">*</span></label>
                        <input type="text" name="nom_jury" id="nom_jury" class="form-control" required>
                    </div>
                    <div class="form-group mb-2">
                        <label>Prénom</label>
                        <input type="text" name="prenom_jury" id="prenom_jury" class="form-control">
                    </div>
                    <div class="form-group mb-2">
                        <label>Profession / Titre</label>
                        <input type="text" name="profession_jury" id="profession_jury" class="form-control" placeholder="Ex: Expert IT, Musicien">
                    </div>
                    <div class="form-group mb-2">
                        <label>Email</label>
                        <input type="email" name="email_jury" id="email_jury" class="form-control">
                    </div>
                    <div class="form-group mb-3">
                        <label>Téléphone</label>
                        <input type="text" name="tel_jury" id="tel_jury" class="form-control">
                    </div>

                    <button type="submit" class="btn btn-success btn-block">💾 Enregistrer le jury</button>
                    <button type="button" onclick="resetJuryForm()" class="btn btn-secondary btn-block btn-sm mt-2" id="btn-cancel-jury" style="display:none;">Annuler la modification</button>
                </form>
            </div>

            <!-- Liste du Jury & Attribution Notes -->
            <div class="detail-main-card">
                <h3>Comité de Jury affecté</h3>
                <div class="table-responsive mb-4">
                    <table class="table-modern-style">
                        <thead>
                            <tr>
                                <th>Nom & Prénom</th>
                                <th>Expertise</th>
                                <th>Contact</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($jurys)): ?>
                                <tr><td colspan="4" class="text-center text-muted">Aucun jury désigné.</td></tr>
                            <?php else: ?>
                                <?php foreach($jurys as $j): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($j['nom'] . ' ' . ($j['prenom'] ?? '')) ?></strong></td>
                                        <td><?= htmlspecialchars($j['profession'] ?? 'Membre du comité') ?></td>
                                        <td><small><?= htmlspecialchars($j['email'] ?? '-') ?> / <?= htmlspecialchars($j['telephone'] ?? '-') ?></small></td>
                                        <td class="text-right">
                                            <button class="btn btn-sm btn-info" onclick='editJury(<?= json_encode($j) ?>)'>📝</button>
                                            <form action="" method="POST" class="inline-form" onsubmit="return confirm('Retirer ce jury ?');">
                                                <input type="hidden" name="action" value="delete_jury">
                                                <input type="hidden" name="id_jury" value="<?= $j['id_jury'] ?>">
                                                <button type="submit" class="btn btn-sm btn-danger">🗑️</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- GRILLE D'ATTRIBUTION DES NOTES -->
                <h3>✏️ Grille d'évaluation des Candidats</h3>
                <p class="text-muted small">Sélectionnez un membre du jury, un candidat et attribuez une note globale sur 20.</p>
                <form action="" method="POST" class="evaluation-form-box">
                    <input type="hidden" name="action" value="save_note">
                    <div class="form-grid-3">
                        <div class="form-group">
                            <label>Membre Évaluateur (Jury)</label>
                            <select name="id_jury" required class="form-control">
                                <option value="">-- Choisir le jury --</option>
                                <?php foreach($jurys as $j): ?>
                                    <option value="<?= $j['id_jury'] ?>"><?= htmlspecialchars($j['nom'] . ' ' . $j['prenom']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Candidat à évaluer</label>
                            <select name="id_participant" required class="form-control">
                                <option value="">-- Choisir le candidat --</option>
                                <?php foreach($participants as $p): ?>
                                    <option value="<?= $p['id_participant'] ?>">N°<?= $p['numero_participant'] ?> - <?= htmlspecialchars($p['nom_complet']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Note attribuée (/20)</label>
                            <input type="number" name="note" min="0" max="20" step="0.25" required class="form-control" placeholder="0.00">
                        </div>
                    </div>
                    <div class="form-group mt-2">
                        <label>Critères observés / Commentaire d'appréciation</label>
                        <input type="text" name="commentaire" class="form-control" placeholder="Ex: Excellente présentation orale, projet innovant...">
                    </div>
                    <button type="submit" class="btn btn-primary mt-3">Enregistrer la note de session</button>
                </form>
            </div>
        </div>
    </div>

    <!-- 4. ONGLET : SUIVI DES VOTES -->
    <div id="tab-votes" class="tab-content">
        <h3>Classement des votes en direct</h3>
        <p class="text-muted">Tableau récapitulatif du taux d'engagement du public si le mode de sélection inclut le vote populaire.</p>
        
        <div class="table-responsive mt-3">
            <table class="table-modern-style">
                <thead>
                    <tr>
                        <th>Rang populaire</th>
                        <th>Candidat</th>
                        <th>Étape Active</th>
                        <th>Total des Suffrages</th>
                        <th>Barre de popularité</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    // Trier temporairement par vote pour le classement graphique
                    $vote_ranking = $participants;
                    usort($vote_ranking, function($a, $b) { return $b['total_votes'] <=> $a['total_votes']; });
                    $max_votes_recorded = !empty($vote_ranking) ? intval($vote_ranking[0]['total_votes']) : 1;
                    if($max_votes_recorded == 0) $max_votes_recorded = 1;

                    $rank = 1;
                    foreach($vote_ranking as $vr): 
                        $pct = round(($vr['total_votes'] / $max_votes_recorded) * 100);
                    ?>
                        <tr>
                            <td><strong>#<?= $rank++ ?></strong></td>
                            <td><?= htmlspecialchars($vr['nom_complet']) ?> (N°<?= $vr['numero_participant'] ?>)</td>
                            <td><span class="badge-phase"><?= htmlspecialchars($vr['phase_nom'] ?? 'Inscription') ?></span></td>
                            <td><span class="badge badge-info px-3 py-2"><?= $vr['total_votes'] ?> vote(s)</span></td>
                            <td style="width: 40%;">
                                <div class="progress-bar-container" style="background:#eaecf4; border-radius:10px; height:12px; width:100%; overflow:hidden;">
                                    <div style="background:#4e73df; height:100%; width:<?= $pct ?>%;"></div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 5. ONGLET : CLÔTURE & GAGNANTS -->
    <div id="tab-cloture" class="tab-content">
        <div class="grid-details-layout">
            <!-- Formulaire d'attribution de prix -->
            <div class="detail-sidebar-card">
                <h3>Désigner un Lauréat</h3>
                <hr>
                <form action="" method="POST">
                    <input type="hidden" name="action" value="save_gagnant">

                    <div class="form-group mb-2">
                        <label>Sélectionner le vainqueur</label>
                        <select name="id_participant" required class="form-control">
                            <option value="">-- Choisir le lauréat --</option>
                            <?php foreach($participants as $p): ?>
                                <option value="<?= $p['id_participant'] ?>">N°<?= $p['numero_participant'] ?> - <?= htmlspecialchars($p['nom_complet']) ?> (Moy: <?= $p['score'] ?>/20)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group mb-2">
                        <label>Rang / Titre de Position</label>
                        <select name="position" class="form-control">
                            <option value="gagnant">Grand Gagnant (Vainqueur Principal)</option>
                            <option value="1er">1er Dauphin / Place 1</option>
                            <option value="2eme">2ème Dauphin / Place 2</option>
                            <option value="3eme">3ème Dauphin / Place 3</option>
                            <option value="finaliste">Simple Finaliste émérite</option>
                            <option value="prix_special">Prix Spécial du Jury</option>
                        </select>
                    </div>

                    <div class="form-group mb-2">
                        <label>Nom ou Label du Prix</label>
                        <input type="text" name="titre_prix" class="form-control" placeholder="Ex: Prix de l'innovation ISSPT">
                    </div>

                    <div class="form-group mb-2">
                        <label>Récompense financière (FCFA)</label>
                        <input type="number" name="montant_prix" value="0.00" step="5000" class="form-control">
                    </div>

                    <div class="form-group mb-3">
                        <label>Récompense Matérielle / Cadeau</label>
                        <input type="text" name="cadeau" class="form-control" placeholder="Ex: Ordinateur portable, Trophée">
                    </div>

                    <button type="submit" class="btn btn-warning btn-block" onsubmit="return confirm('Valider définitivement ce résultat de clôture ?');">👑 Publier le résultat</button>
                </form>
            </div>

            <!-- Tableau d'honneur -->
            <div class="detail-main-card">
                <h3>🏆 Tableau d'honneur des Lauréats</h3>
                <p class="text-muted">Liste des récompenses officielles décernées pour ce concours.</p>

                <div class="table-responsive mt-3">
                    <table class="table-modern-style">
                        <thead>
                            <tr>
                                <th>Distinction</th>
                                <th>Lauréat</th>
                                <th>Label Prix</th>
                                <th>Enveloppe</th>
                                <th>Cadeau Joint</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($gagnants)): ?>
                                <tr><td colspan="5" class="text-center text-muted">Aucun gagnant n'a encore été désigné pour cette édition.</td></tr>
                            <?php else: ?>
                                <?php foreach($gagnants as $g): ?>
                                    <tr style="background: #fffdf0;">
                                        <td><span class="badge-gold text-uppercase"><?= htmlspecialchars($g['position']) ?></span></td>
                                        <td><strong><?= htmlspecialchars($g['nom_complet']) ?></strong></td>
                                        <td><?= htmlspecialchars($g['titre_prix'] ?? 'Prix Classique') ?></td>
                                        <td><span class="text-success font-weight-bold"><?= number_format($g['montant_prix'], 0, ',', ' ') ?> FCFA</span></td>
                                        <td>🎁 <?= htmlspecialchars($g['cadeau'] ?? 'Aucun') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- SCRIPTS DE NAVIGATION DYNAMIQUE -->
<script>
    function openTab(evt, tabId) {
        const contents = document.querySelectorAll('.tab-content');
        contents.forEach(content => content.classList.remove('active'));

        const triggers = document.querySelectorAll('.tab-trigger');
        triggers.forEach(trigger => trigger.classList.remove('active'));

        document.getElementById(tabId).classList.add('active');
        evt.currentTarget.classList.add('active');
    }

    function editJury(juryData) {
        document.getElementById('jury-form-title').textContent = "Modifier le Jury";
        document.getElementById('id_jury').value = juryData.id_jury;
        document.getElementById('nom_jury').value = juryData.nom;
        document.getElementById('prenom_jury').value = juryData.prenom || '';
        document.getElementById('profession_jury').value = juryData.profession || '';
        document.getElementById('email_jury').value = juryData.email || '';
        document.getElementById('tel_jury').value = juryData.telephone || '';
        document.getElementById('btn-cancel-jury').style.display = 'block';
    }

    function resetJuryForm() {
        document.getElementById('jury-form-title').textContent = "Ajouter un Membre du Jury";
        document.getElementById('id_jury').value = '';
        document.getElementById('form-jury').reset();
        document.getElementById('btn-cancel-jury').style.display = 'none';
    }
</script>

<!-- STYLES ADDITIONNELS DÉDIÉS AUX COMPOSANTS DE GESTION -->
<style>
/* ==========================================================================
   1. RESET & BASES DE LA PAGE (Intégration du Thème Sombre)
   ========================================================================== */
:root {
    /* Vos variables conservées et respectées à 100% */
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
    --sidebar-width-collapsed: 70px;
    --sidebar-bg: linear-gradient(180deg, var(--primary-900) 0%, var(--primary-800) 100%);
    --sidebar-border: 1px solid rgba(255, 255, 255, 0.05);
    --sidebar-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);

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
    --transition-slow: 500ms cubic-bezier(0.4, 0, 0.2, 1);

    --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.12);
    --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.1);
    --shadow-lg: 0 10px 25px rgba(0, 0, 0, 0.2);
    --shadow-xl: 0 20px 50px rgba(0, 0, 0, 0.3);

    --radius-sm: 4px;
    --radius-md: 8px;
    --radius-lg: 12px;
    --radius-xl: 20px;
    --radius-full: 9999px;

    --z-sidebar: 1000;
    --z-overlay: 999;
    --z-mobile-toggle: 1001;
}


h2, h3, h4 {
    color: var(--white);
    font-weight: 600;
    margin-top: 0;
}

/* Utilitaires globaux indispensables */
.text-muted { color: var(--gray-400) !important; }
.text-success { color: var(--accent-green) !important; }
.text-primary { color: var(--accent-blue) !important; }
.text-uppercase { text-transform: uppercase; letter-spacing: 0.5px; }
.text-capitalize { text-transform: capitalize; }
.font-weight-bold { font-weight: 700; }
.mb-1 { margin-bottom: var(--space-1); }
.mb-2 { margin-bottom: var(--space-2); }
.mb-3 { margin-bottom: var(--space-3); }
.mb-4 { margin-bottom: var(--space-4); }
.mt-2 { margin-top: var(--space-2); }
.mt-3 { margin-top: var(--space-3); }
.mt-4 { margin-top: var(--space-4); }
.py-2 { padding-top: var(--space-2); padding-bottom: var(--space-2); }
.px-3 { padding-left: var(--space-3); padding-right: var(--space-3); }
hr { border: 0; border-top: 1px solid var(--primary-600); margin: var(--space-4) 0; }

/* ==========================================================================
   2. EN-TÊTE DE LA PAGE (Page Header)
   ========================================================================== */
.page-header-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: var(--space-4);
    background: var(--primary-800);
    padding: var(--space-5);
    border-radius: var(--radius-lg);
    border: 1px solid var(--primary-700);
    margin-bottom: var(--space-5);
    box-shadow: var(--shadow-md);
}

.page-header-title h2 {
    font-size: 1.75rem;
    margin-bottom: var(--space-2);
}

.page-header-title p {
    margin: 0;
    font-size: var(--font-size-sm);
}

.header-actions {
    display: flex;
    align-items: center;
    gap: var(--space-3);
}

/* ==========================================================================
   3. SYSTEME DE BOUTONS & BADGES
   ========================================================================== */
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: var(--space-2);
    font-family: var(--font-primary);
    font-size: var(--font-size-sm);
    font-weight: 600;
    padding: 0.6rem var(--space-4);
    border-radius: var(--radius-md);
    border: none;
    cursor: pointer;
    transition: var(--transition-fast);
    text-decoration: none;
    white-space: nowrap;
}

.btn-sm {
    padding: 0.4rem var(--space-3);
    font-size: var(--font-size-xs);
}

.btn-block {
    display: flex;
    width: 100%;
}

.btn-warning { background-color: #f1c40f; color: #000; }
.btn-warning:hover { background-color: #f39c12; transform: translateY(-1px); }

.btn-secondary { background-color: var(--primary-600); color: var(--gray-100); border: 1px solid var(--primary-700); }
.btn-secondary:hover { background-color: var(--primary-700); color: var(--white); }

.btn-success { background-color: var(--accent-green); color: var(--white); }
.btn-success:hover { opacity: 0.9; transform: translateY(-1px); }

.btn-info { background-color: var(--accent-blue); color: var(--white); }
.btn-info:hover { opacity: 0.9; transform: translateY(-1px); }

.btn-danger { background-color: var(--accent-red); color: var(--white); }
.btn-danger:hover { opacity: 0.9; }

/* Badges globaux */
.badge-type {
    display: inline-block;
    background: rgba(46, 134, 222, 0.15);
    color: var(--accent-blue);
    padding: var(--space-1) var(--space-3);
    border-radius: var(--radius-full);
    font-size: var(--font-size-xs);
    font-weight: 700;
}

.badge-status {
    padding: 0.5rem var(--space-4);
    border-radius: var(--radius-full);
    font-size: var(--font-size-xs);
    font-weight: 700;
    text-transform: uppercase;
}
/* Variations de statuts PHP dynamiques (ex: en_cours, cloture, etc.) */
.status-en_cours, .status-ouvert, .status-valide, .status-qualifie, .status-gagnant { 
    background: rgba(16, 172, 132, 0.15); color: var(--accent-green); border: 1px solid rgba(16, 172, 132, 0.3); 
}
.status-cloture, .status-termine, .status-refuse, .status-elimine { 
    background: rgba(255, 71, 87, 0.15); color: var(--accent-red); border: 1px solid rgba(255, 71, 87, 0.3); 
}
.status-en_attente { 
    background: rgba(241, 196, 15, 0.15); color: #f1c40f; border: 1px solid rgba(241, 196, 15, 0.3); 
}

/* ==========================================================================
   4. NOTIFICATIONS & ALERTES
   ========================================================================== */
.alert {
    padding: var(--space-4);
    border-radius: var(--radius-md);
    margin-bottom: var(--space-4);
    font-weight: 500;
    animation: fadeIn var(--transition-base);
}

.alert-success { background: rgba(16, 172, 132, 0.15); color: #26deac; border: 1px solid rgba(16, 172, 132, 0.2); }
.alert-danger { background: rgba(255, 71, 87, 0.15); color: #ff6b81; border: 1px solid rgba(255, 71, 87, 0.2); }
.alert ul { margin: 0; padding-left: var(--space-4); }

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-5px); }
    to { opacity: 1; transform: translateY(0); }
}

/* ==========================================================================
   5. MINI CARTES DE STATISTIQUES (Stats Dashboard Grid)
   ========================================================================== */
.stats-grid-dashboard {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: var(--space-4);
    margin-bottom: var(--space-5);
}

.stat-box-mini {
    background: var(--primary-800);
    border: 1px solid var(--primary-700);
    border-radius: var(--radius-lg);
    padding: var(--space-4);
    display: flex;
    flex-direction: column;
    gap: var(--space-2);
    box-shadow: var(--shadow-sm);
    transition: transform var(--transition-fast), box-shadow var(--transition-fast);
}

.stat-box-mini:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
    border-color: var(--primary-600);
}

.stat-box-mini .title {
    font-size: var(--font-size-sm);
    color: var(--gray-400);
    text-transform: uppercase;
    font-weight: 600;
    letter-spacing: 0.5px;
}

.stat-box-mini .value {
    font-size: 1.6rem;
    font-weight: 700;
    color: var(--white);
}

.stat-box-mini .value small {
    font-size: var(--font-size-sm);
    color: var(--gray-400);
}

/* ==========================================================================
   6. STRUCTURE DES ONGLETS (Tabs Layout)
   ========================================================================== */
.tabs-container {
    background: var(--primary-800);
    border-radius: var(--radius-xl);
    border: 1px solid var(--primary-700);
    box-shadow: var(--shadow-lg);
    overflow: hidden;
}

.tabs-header {
    display: flex;
    background: var(--primary-900);
    border-bottom: 1px solid var(--primary-700);
    padding: var(--space-2) var(--space-2) 0 var(--space-2);
    gap: var(--space-1);
    overflow-x: auto;
    scrollbar-width: none; /* Cache la scrollbar sur Firefox */
}

.tabs-header::-webkit-scrollbar {
    display: none; /* Cache la scrollbar sur Chrome/Safari */
}

.tab-trigger {
    background: transparent;
    border: none;
    color: var(--gray-400);
    padding: var(--space-4) var(--space-5);
    font-family: var(--font-primary);
    font-size: var(--font-size-sm);
    font-weight: 600;
    cursor: pointer;
    border-radius: var(--radius-md) var(--radius-md) 0 0;
    transition: var(--transition-fast);
    position: relative;
    white-space: nowrap;
}

.tab-trigger:hover {
    color: var(--white);
    background: rgba(255, 255, 255, 0.02);
}

.tab-trigger.active {
    color: var(--white);
    background: var(--primary-800);
}

.tab-trigger.active::after {
    content: '';
    position: absolute;
    bottom: -1px;
    left: 0;
    right: 0;
    height: 3px;
    background: var(--accent-blue);
    border-radius: var(--radius-full) var(--radius-full) 0 0;
}

.tab-content {
    display: none;
    padding: var(--space-5);
    animation: tabEffect var(--transition-base) forwards;
}

.tab-content.active {
    display: block;
}

@keyframes tabEffect {
    from { opacity: 0; transform: translateY(5px); }
    to { opacity: 1; transform: translateY(0); }
}

/* ==========================================================================
   7. DISPOSITION EN GRILLE (Layout interne des onglets : Main / Sidebar)
   ========================================================================== */
.grid-details-layout {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: var(--space-5);
    align-items: start;
}

.detail-main-card, .detail-sidebar-card {
    background: rgba(255, 255, 255, 0.01);
    border: 1px solid var(--primary-700);
    border-radius: var(--radius-lg);
    padding: var(--space-5);
}

.detail-sidebar-card {
    background: var(--primary-700);
}

/* Listes d'informations logistiques */
.info-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.info-list li {
    padding: var(--space-3) 0;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    font-size: var(--font-size-sm);
}

.info-list li:last-child {
    border-bottom: none;
}

/* ==========================================================================
   8. COMPOSANTS DE FORMULAIRES (Inputs & Boxes)
   ========================================================================== */
.form-group label {
    display: block;
    font-size: var(--font-size-sm);
    font-weight: 600;
    color: var(--gray-300);
    margin-bottom: var(--space-2);
}

.form-group .required {
    color: var(--accent-red);
}

.form-control {
    width: 100%;
    padding: 0.65rem var(--space-4);
    background-color: var(--primary-900);
    border: 1px solid var(--primary-600);
    border-radius: var(--radius-md);
    color: var(--white);
    font-family: var(--font-primary);
    font-size: var(--font-size-sm);
    transition: var(--transition-fast);
    box-sizing: border-box;
}

.form-control:focus {
    outline: none;
    border-color: var(--accent-blue);
    box-shadow: 0 0 0 3px rgba(46, 134, 222, 0.2);
}

.form-control::placeholder {
    color: rgba(255, 255, 255, 0.2);
}

/* Grilles de formulaires */
.form-grid-3 {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: var(--space-4);
}

.evaluation-form-box {
    background: var(--primary-900);
    border: 1px solid var(--primary-600);
    padding: var(--space-4);
    border-radius: var(--radius-lg);
}

/* Specific Select in Tables */
.select-table-status {
    padding: 0.35rem var(--space-3);
    border-radius: var(--radius-full);
    font-family: var(--font-primary);
    font-size: var(--font-size-xs);
    font-weight: 600;
    background-color: var(--primary-800);
    color: var(--white);
    border: 1px solid var(--primary-600);
    cursor: pointer;
    outline: none;
}

/* ==========================================================================
   9. TABLEAUX MODERNES (Responsive Tables)
   ========================================================================== */
.table-responsive {
    width: 100%;
    overflow-x: auto;
    border-radius: var(--radius-lg);
    border: 1px solid var(--primary-700);
    background: var(--primary-800);
}

.table-modern-style {
    width: 100%;
    border-collapse: collapse;
    text-align: left;
    font-size: var(--font-size-sm);
}

.table-modern-style th {
    background-color: var(--primary-900);
    color: var(--gray-300);
    font-weight: 600;
    padding: var(--space-4);
    border-bottom: 2px solid var(--primary-700);
    text-transform: uppercase;
    font-size: var(--font-size-xs);
    letter-spacing: 0.5px;
}

.table-modern-style td {
    padding: var(--space-4);
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    color: var(--gray-100);
    vertical-align: middle;
}

.table-modern-style tbody tr:hover {
    background-color: rgba(255, 255, 255, 0.02);
}

/* Cellules spécifiques */
.user-avatar-cell {
    line-height: 1.4;
}

.badge-phase {
    display: inline-block;
    background: var(--primary-600);
    border: 1px solid var(--primary-700);
    padding: 0.25rem var(--space-3);
    border-radius: var(--radius-md);
    font-size: var(--font-size-xs);
    color: var(--gray-300);
}

/* Boutons d'actions épurés dans les tableaux */
.btn-action-delete {
    background: transparent;
    border: none;
    cursor: pointer;
    font-size: var(--font-size-lg);
    padding: var(--space-1);
    border-radius: var(--radius-sm);
    transition: var(--transition-fast);
}

.btn-action-delete:hover {
    background: rgba(255, 71, 87, 0.15);
    transform: scale(1.1);
}

/* Barre de progression des votes */
.progress-bar-container {
    background: var(--primary-900) !important;
    border: 1px solid var(--primary-700);
}

.progress-bar-container > div {
    background: linear-gradient(90deg, var(--accent-blue) 0%, #48dbfb 100%) !important;
    border-radius: var(--radius-full);
}

/* Tableau d'honneur (Lauréats) */
.table-modern-style tr[style*="background: #fffdf0;"] {
    background: rgba(241, 196, 15, 0.04) !important;
    border-left: 4px solid #f1c40f;
}

.badge-gold {
    background: linear-gradient(135deg, #f1c40f 0%, #f39c12 100%);
    color: #000;
    font-weight: 700;
    font-size: var(--font-size-xs);
    padding: 0.25rem var(--space-3);
    border-radius: var(--radius-sm);
    display: inline-block;
}

/* ==========================================================================
   10. SÉCURITÉ RESPONSIVE (Media Queries globales)
   ========================================================================== */

/* Écrans de taille moyenne (Tablettes / Petits ordinateurs) */
@media (max-width: 992px) {
    .grid-details-layout {
        grid-template-columns: 1fr; /* Passage sur une colonne unique */
        gap: var(--space-4);
    }
    
    .page-header-container {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .header-actions {
        width: 100%;
        justify-content: flex-start;
        flex-wrap: wrap;
    }
}

/* Écrans de smartphones */
@media (max-width: 576px) {
    body {
        padding: var(--space-3);
    }

    .page-header-container {
        padding: var(--space-4);
    }

    .page-header-title h2 {
        font-size: var(--font-size-xl);
    }

    .tab-content {
        padding: var(--space-4) var(--space-2);
    }

    .form-grid-3 {
        grid-template-columns: 1fr;
    }

    .header-actions .btn {
        width: 100%; /* Les boutons de l'en-tête prennent toute la largeur sur mobile */
    }
}
</style>

<?php
$content = ob_get_clean();
include "../layout.php";
?>