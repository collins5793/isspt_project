<?php
require_once '../includes/db.php';

// 1️⃣ — Récupération de l’année universitaire active
$sql = "SELECT * FROM academic_years WHERE is_current = 1 LIMIT 1";
$stmt = $pdo->query($sql);
$annee = $stmt->fetch(PDO::FETCH_ASSOC);

// Si aucune année active, on gère le cas :
if (!$annee) {
    die("<h2>Aucune année universitaire active</h2>");
}

// 2️⃣ — Récupération des événements (fêtes, compétitions, soirées)
$sql = "SELECT * FROM evenements 
        WHERE academic_year_id = ? 
        ORDER BY event_start DESC LIMIT 5";
$stmt = $pdo->prepare($sql);
$stmt->execute([$annee['id']]);
$evenements = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 3️⃣ — Récupération des activités
$sql = "SELECT * FROM activites 
        WHERE academic_year_id = ? 
        ORDER BY date_creation DESC LIMIT 5";
$stmt = $pdo->prepare($sql);
$stmt->execute([$annee['id']]);
$activites = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 4️⃣ — Récupération des équipes de football
$sql = "SELECT * FROM football_teams 
        WHERE academic_year_id = ? 
        ORDER BY created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$annee['id']]);
$teams = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>JET - Page d’accueil</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f9f9fb;
            margin: 0;
            padding: 0;
        }
        header {
            background: #0b5ed7;
            color: white;
            padding: 20px;
            text-align: center;
        }
        section {
            margin: 40px auto;
            width: 90%;
            max-width: 1000px;
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }
        h2 {
            color: #0b5ed7;
            border-bottom: 2px solid #ddd;
            padding-bottom: 8px;
        }
        ul { list-style: none; padding: 0; }
        li { padding: 8px 0; border-bottom: 1px solid #eee; }
        .team-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        .team-card {
            border: 1px solid #ddd;
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            background: #fafafa;
        }
        footer {
            text-align: center;
            padding: 15px;
            background: #0b5ed7;
            color: white;
            margin-top: 40px;
        }
    </style>
</head>
<body>

<header>
    <h1>🎓 Journées Étudiantes de l’Université</h1>
    <h3>Année universitaire : <?= htmlspecialchars($annee['label']) ?> 
        (<?= date('Y', strtotime($annee['start_date'])) ?> - <?= date('Y', strtotime($annee['end_date'])) ?>)
    </h3>
</header>

<main>
    <!-- Événements -->
    <section>
        <h2>📅 Événements à venir</h2>
        <?php if (count($evenements) > 0): ?>
            <ul>
                <?php foreach ($evenements as $evt): ?>
                    <li>
                        <strong><?= htmlspecialchars($evt['nom_evenement']) ?></strong> 
                        <em>(<?= ucfirst($evt['type_evenement']) ?>)</em><br>
                        <small>📍 <?= htmlspecialchars($evt['lieu'] ?? 'Lieu à confirmer') ?> — 
                        📆 <?= date('d/m/Y H:i', strtotime($evt['event_start'])) ?></small><br>
                        <?= nl2br(htmlspecialchars(substr($evt['description'], 0, 120))) ?>...
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p>Aucun événement enregistré pour cette année.</p>
        <?php endif; ?>
    </section>

    <!-- Activités -->
    <section>
        <h2>🎭 Activités étudiantes</h2>
        <?php if (count($activites) > 0): ?>
            <ul>
                <?php foreach ($activites as $act): ?>
                    <li>
                        <strong><?= htmlspecialchars($act['nom_activite']) ?></strong><br>
                        <?= nl2br(htmlspecialchars(substr($act['description'], 0, 120))) ?>...
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p>Aucune activité enregistrée pour cette année.</p>
        <?php endif; ?>
    </section>

    <!-- Football -->
    <section>
        <h2>⚽ Tournoi de Football</h2>
        <?php if (count($teams) > 0): ?>
            <div class="team-grid">
                <?php foreach ($teams as $team): ?>
                    <div class="team-card">
                        <h4><?= htmlspecialchars($team['name']) ?></h4>
                        <p>Coach : <?= htmlspecialchars($team['coach'] ?? 'N/A') ?></p>
                        <p><small>Créée le <?= date('d/m/Y', strtotime($team['created_at'])) ?></small></p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p>Aucune équipe enregistrée pour cette année.</p>
        <?php endif; ?>
    </section>
</main>

<footer>
    <p>&copy; <?= date('Y') ?> — JET Université | Tous droits réservés.</p>
</footer>

</body>
</html>
