<?php
session_start();
require_once "../../includes/db.php";

// Vérifier que l'admin est connecté
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// Récupérer la saison en cours
$seasonId = $_GET['season_id'] ?? null;
if (!$seasonId) {
    die("Saison non définie !");
}

// Récupérer toutes les pools de la saison
$stmt = $pdo->prepare("SELECT * FROM pools WHERE season_id = ?");
$stmt->execute([$seasonId]);
$pools = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer toutes les équipes de la saison
$stmt2 = $pdo->prepare("SELECT * FROM football_teams WHERE season_id = ?");
$stmt2->execute([$seasonId]);
$teams = $stmt2->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Pools - Football</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            /* Colors - Dark Theme (default) */
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

            /* Sidebar */
            --sidebar-width: 280px;
            --sidebar-width-collapsed: 70px;
            --sidebar-bg: linear-gradient(180deg, var(--primary-900) 0%, var(--primary-800) 100%);
            --sidebar-border: 1px solid rgba(255, 255, 255, 0.05);
            --sidebar-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            
            /* Typography */
            --font-primary: 'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif;
            --font-size-xs: 0.75rem;   /* 12px */
            --font-size-sm: 0.875rem;  /* 14px */
            --font-size-md: 1rem;      /* 16px */
            --font-size-lg: 1.125rem;  /* 18px */
            --font-size-xl: 1.25rem;   /* 20px */
            
            /* Spacing */
            --space-1: 0.25rem;   /* 4px */
            --space-2: 0.5rem;    /* 8px */
            --space-3: 0.75rem;   /* 12px */
            --space-4: 1rem;      /* 16px */
            --space-5: 1.5rem;    /* 24px */
            --space-6: 2rem;      /* 32px */
            
            /* Transitions */
            --transition-fast: 150ms cubic-bezier(0.4, 0, 0.2, 1);
            --transition-base: 300ms cubic-bezier(0.4, 0, 0.2, 1);
            --transition-slow: 500ms cubic-bezier(0.4, 0, 0.2, 1);
            
            /* Shadows */
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.12);
            --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 25px rgba(0, 0, 0, 0.2);
            --shadow-xl: 0 20px 50px rgba(0, 0, 0, 0.3);
            
            /* Border Radius */
            --radius-sm: 4px;
            --radius-md: 8px;
            --radius-lg: 12px;
            --radius-xl: 20px;
            --radius-full: 9999px;
            
            /* Z-index */
            --z-sidebar: 1000;
            --z-overlay: 999;
            --z-mobile-toggle: 1001;
        }

        /* --- Global Reset --- */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: var(--font-primary);
            background-color: var(--primary-900);
            background-image: radial-gradient(at top left, var(--primary-700), var(--primary-900));
            color: var(--gray-100);
            min-height: 100vh;
            padding: var(--space-5);
            display: flex;
            justify-content: center;
        }

        .container {
            width: 100%;
            max-width: 1100px;
            margin: var(--space-4) auto;
        }

        /* --- Main Header --- */
        .page-header {
            display: flex;
            align-items: center;
            gap: var(--space-4);
            margin-bottom: var(--space-6);
            padding-bottom: var(--space-4);
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }

        .page-header h1 {
            font-size: var(--font-size-xl);
            color: var(--white);
            font-weight: 600;
            letter-spacing: -0.5px;
        }

        .page-header i {
            color: var(--accent-blue);
            font-size: 1.5rem;
        }

        /* --- Pool Box Design --- */
        .pool-container {
            background: rgba(18, 12, 58, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: var(--radius-lg);
            padding: var(--space-5);
            margin-bottom: var(--space-5);
            box-shadow: var(--shadow-md);
            backdrop-filter: blur(8px);
            transition: transform var(--transition-base), border-color var(--transition-base);
        }

        .pool-container:hover {
            border-color: rgba(46, 134, 222, 0.3);
        }

        .pool-title {
            font-size: var(--font-size-lg);
            color: var(--white);
            margin-bottom: var(--space-4);
            display: flex;
            align-items: center;
            gap: var(--space-2);
        }

        .pool-title::before {
            content: '';
            display: inline-block;
            width: 4px;
            height: 18px;
            background: var(--accent-blue);
            border-radius: var(--radius-full);
        }

        /* --- Selection Area --- */
        .select-group {
            margin-bottom: var(--space-5);
        }

        .select-group label {
            display: block;
            font-size: var(--font-size-sm);
            font-weight: 600;
            color: var(--gray-300);
            margin-bottom: var(--space-2);
        }

        .team-select {
            width: 100%;
            max-width: 450px;
            background-color: var(--primary-800);
            color: var(--white);
            border: 1px solid var(--primary-600);
            border-radius: var(--radius-md);
            padding: var(--space-2);
            font-family: var(--font-primary);
            font-size: var(--font-size-sm);
            outline: none;
            transition: border-color var(--transition-fast);
        }

        .team-select:focus {
            border-color: var(--accent-blue);
        }

        .team-select option {
            padding: var(--space-2);
            background-color: var(--primary-800);
        }

        .team-select option:disabled {
            color: var(--gray-400);
            background-color: rgba(0, 0, 0, 0.15);
            font-style: italic;
        }

        /* --- Matches Table --- */
        .section-subtitle {
            font-size: var(--font-size-sm);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--gray-400);
            margin-bottom: var(--space-3);
        }

        .table-responsive {
            width: 100%;
            overflow-x: auto;
            border-radius: var(--radius-md);
            background: rgba(10, 1, 39, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.04);
        }

        .match-table {
            width: 100%;
            border-collapse: collapse;
            font-size: var(--font-size-sm);
            text-align: left;
        }

        .match-table th {
            background-color: var(--primary-600);
            color: var(--gray-200);
            font-weight: 600;
            padding: var(--space-3) var(--space-4);
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .match-table td {
            padding: var(--space-3) var(--space-4);
            color: var(--gray-100);
            border-bottom: 1px solid rgba(255, 255, 255, 0.03);
        }

        .match-table tbody tr:last-child td {
            border-bottom: none;
        }

        .match-table tbody tr:hover {
            background-color: rgba(255, 255, 255, 0.01);
        }

        .vs-text {
            color: var(--accent-red);
            font-weight: 700;
            font-style: italic;
            margin: 0 var(--space-2);
        }

        /* --- Form Controls --- */
        input[type="datetime-local"] {
            background-color: var(--primary-700);
            color: var(--white);
            border: 1px solid var(--primary-600);
            border-radius: var(--radius-sm);
            padding: var(--space-2);
            font-family: var(--font-primary);
            font-size: var(--font-size-xs);
            outline: none;
            transition: all var(--transition-fast);
        }

        input[type="datetime-local"]:focus {
            border-color: var(--accent-blue);
            background-color: var(--primary-600);
        }

        /* --- Sticky Action Bar --- */
        .actions-footer {
            margin-top: var(--space-6);
            display: flex;
            justify-content: flex-end;
        }

        .btn-submit {
            background: var(--accent-green);
            color: var(--white);
            font-family: var(--font-primary);
            font-size: var(--font-size-md);
            font-weight: 600;
            padding: var(--space-3) var(--space-5);
            border: none;
            border-radius: var(--radius-md);
            cursor: pointer;
            box-shadow: 0 4px 14px rgba(16, 172, 132, 0.3);
            transition: all var(--transition-fast);
            display: inline-flex;
            align-items: center;
            gap: var(--space-2);
        }

        .btn-submit:hover {
            background-color: #1dd1a1;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(16, 172, 132, 0.4);
        }

        .empty-state {
            text-align: center;
            color: var(--gray-400);
            font-style: italic;
            padding: var(--space-4);
        }

        /* --- Responsiveness & Screen Optimization --- */
        @media (max-width: 768px) {
            body {
                padding: var(--space-3);
            }

            .page-header h1 {
                font-size: var(--font-size-lg);
            }

            .pool-container {
                padding: var(--space-4);
            }

            .team-select {
                max-width: 100%;
            }

            /* Convert table layout into stack components on small devices */
            .match-table, .match-table thead, .match-table tbody, .match-table th, .match-table td, .match-table tr {
                display: block;
            }

            .match-table thead {
                display: none;
            }

            .match-table tr {
                border-bottom: 1px solid rgba(255, 255, 255, 0.08);
                padding: var(--space-3) 0;
            }

            .match-table tr:last-child {
                border-bottom: none;
            }

            .match-table td {
                border: none;
                padding: var(--space-2) var(--space-3);
                display: flex;
                justify-content: space-between;
                align-items: center;
                text-align: right;
            }

            .match-table td::before {
                content: attr(data-label);
                float: left;
                font-weight: 600;
                color: var(--gray-400);
                font-size: var(--font-size-xs);
                text-transform: uppercase;
            }

            input[type="datetime-local"] {
                width: 100%;
                max-width: 200px;
            }

            .btn-submit {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>

<div class="container">
    
    <header class="page-header">
        <i class="fa-solid fa-diagram-project"></i>
        <h1>Gestion des Pools — Saison <?= htmlspecialchars($seasonId) ?></h1>
    </header>

    <form id="poolsForm" method="POST" action="save_pools.php">
        <input type="hidden" name="season_id" value="<?= htmlspecialchars($seasonId) ?>">

        <?php foreach ($pools as $pool): ?>
        <div class="pool-container" data-pool-id="<?= $pool['pool_id'] ?>">
            <h3 class="pool-title"><?= htmlspecialchars($pool['name']) ?></h3>
            
            <div class="select-group">
                <label><i class="fa-solid fa-users-rectangle"></i> Affecter les équipes à la pool :</label>
                <select multiple class="team-select" data-pool-id="<?= $pool['pool_id'] ?>" rows="5">
                    <?php foreach ($teams as $team): ?>
                        <option value="<?= $team['team_id'] ?>"><?= htmlspecialchars($team['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <h4 class="section-subtitle"><i class="fa-solid fa-calendar-days"></i> Grille de confrontation</h4>
            <div class="table-responsive">
                <table class="match-table" id="matches-<?= $pool['pool_id'] ?>">
                    <thead>
                        <tr>
                            <th>Équipe Locale</th>
                            <th>Équipe Visiteuse</th>
                            <th>Date & Heure du Match</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="3" class="empty-state">Sélectionnez au moins 2 équipes pour générer les matchs automatiquement.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endforeach; ?>

        <div class="actions-footer">
            <button type="submit" class="btn-submit">
                <i class="fa-solid fa-floppy-disk"></i> Valider et enregistrer la configuration
            </button>
        </div>
    </form>
</div>

<script>
// Transfert des données serveur vers l'environnement JS
const allTeams = <?= json_encode($teams) ?>;

// Générateur de calendrier type Round-Robin
function generateMatches(selectedTeams) {
    let matches = [];
    for (let i = 0; i < selectedTeams.length; i++) {
        for (let j = i + 1; j < selectedTeams.length; j++) {
            matches.push({team1: selectedTeams[i], team2: selectedTeams[j]});
        }
    }
    return matches;
}

// Suivi et synchronisation des sélections d'équipes
document.querySelectorAll('.team-select').forEach(select => {
    select.addEventListener('change', function() {
        
        let selectedTeamIds = [];
        document.querySelectorAll('.team-select').forEach(s => {
            Array.from(s.selectedOptions).forEach(opt => {
                selectedTeamIds.push(opt.value);
            });
        });

        // Neutraliser le choix d'une équipe si déjà affectée à une autre pool
        document.querySelectorAll('.team-select').forEach(s => {
            Array.from(s.options).forEach(opt => {
                if (!Array.from(s.selectedOptions).map(o => o.value).includes(opt.value)) {
                    opt.disabled = selectedTeamIds.includes(opt.value);
                }
            });
        });

        // Extraction et reconstruction dynamique de la table des matchs
        const poolId = this.dataset.poolId;
        const selected = Array.from(this.selectedOptions).map(o => ({id: o.value, name: o.text}));
        const matches = generateMatches(selected);

        const tbody = document.querySelector(`#matches-${poolId} tbody`);
        tbody.innerHTML = '';

        if(matches.length === 0) {
            tbody.innerHTML = `<tr><td colspan="3" class="empty-state">Sélectionnez au moins 2 équipes pour générer les matchs automatiquement.</td></tr>`;
            return;
        }

        matches.forEach(m => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td data-label="Équipe Locale"><i class="fa-solid fa-shield-halved" style="color: var(--accent-blue); opacity: 0.7; margin-right: 6px;"></i> ${m.team1.name}</td>
                <td data-label="Équipe Visiteuse"><i class="fa-solid fa-shield-halved" style="color: var(--gray-400); opacity: 0.7; margin-right: 6px;"></i> ${m.team2.name}</td>
                <td data-label="Date du match">
                    <input type="datetime-local" name="match_datetime[${poolId}][]" required>
                </td>
                <input type="hidden" name="team1[${poolId}][]" value="${m.team1.id}">
                <input type="hidden" name="team2[${poolId}][]" value="${m.team2.id}">
            `;
            tbody.appendChild(row);
        });
    });
});
</script>

</body>
</html>