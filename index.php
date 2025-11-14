<?php
// --- Connexion à la base de données ---
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "universite_db"; // adapte selon ton nom de base

try {
    $db = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

// --- Récupération de l'année académique en cours ---
$stmt = $db->query("SELECT * FROM academic_years WHERE is_current = 1 LIMIT 1");
$current_year = $stmt->fetch(PDO::FETCH_ASSOC);
$year_id = $current_year['id'] ?? null;

// --- Récupération du président actuel ---
$president = null;
if ($year_id) {
    $stmt = $db->prepare("
        SELECT a.id_admin, e.nom, e.prenom, e.photo
        FROM administrateurs a
        JOIN etudiants e ON a.id_etudiant = e.id_etudiant
        WHERE a.poste_bureau = 'président'
        LIMIT 1
    ");
    $stmt->execute();
    $president = $stmt->fetch(PDO::FETCH_ASSOC);
}

// --- Récupération des actualités du président pour l'année en cours ---
$actualites = [];
$mot_du_president = null;
if ($president && $year_id) {
    $stmt = $db->prepare("
        SELECT titre, contenu, mot_du_president, date_publication 
        FROM actualites
        WHERE id_admin = ? AND id_academic_year = ?
        ORDER BY date_publication DESC
        LIMIT 3
    ");
    $stmt->execute([$president['id_admin'], $year_id]);
    $actualites = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Si le mot du président existe, prendre le dernier
    foreach ($actualites as $actu) {
        if (!empty($actu['mot_du_president'])) {
            $mot_du_president = $actu['mot_du_president'];
            break; // prend le premier trouvé
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>ISSPT - Portail Universitaire</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background-color: #f9f9f9; }
        header, footer { background-color: #003366; color: white; text-align: center; padding: 15px 0; }
        header h1 { margin: 0; font-size: 28px; }
        .container { width: 90%; margin: 30px auto; }
        .top-section { display: flex; justify-content: space-between; align-items: center; }
        .intro { flex: 2; }
        .auth-buttons { flex: 1; text-align: right; }
        .auth-buttons a { text-decoration: none; color: white; background-color: #003366; padding: 8px 15px; border-radius: 5px; margin-left: 10px; }
        .auth-buttons a:hover { background-color: #0055aa; }
        .section { margin-top: 40px; }
        h2 { color: #003366; border-bottom: 2px solid #003366; display: inline-block; padding-bottom: 5px; }
        .actualites { background: white; padding: 15px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .actualite { margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #ccc; }
        .actualite h3 { margin: 0; color: #003366; }
        .actualite small { color: gray; }
        .modules { display: flex; flex-wrap: wrap; gap: 20px; }
        .module-card { flex: 1 1 calc(25% - 20px); background: white; border-radius: 8px; text-align: center; padding: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); transition: transform 0.3s; }
        .module-card:hover { transform: scale(1.03); }
        .module-card a { text-decoration: none; color: #003366; font-weight: bold; }

        /* Nouvelle disposition président */
        .president-section { display: flex; flex-direction: column; gap: 20px; }
        .president-bottom { display: flex; align-items: flex-start; gap: 20px; }
        .president-photo { flex-shrink: 0; }
        .president-photo img { width: 100px; height: 100px; border-radius: 50%; object-fit: cover; }
        .mot-president { flex: 1; background: #f0f0f0; padding: 10px; border-radius: 6px; font-style: italic; }
        .president-nom { text-align: center; font-weight: bold; margin-top: 10px; }
    </style>
</head>
<body>

<header>
    <h1>Institut Saint Paul de Tarse (ISSPT)</h1>
    <p>Portail universitaire – Bienvenue sur la plateforme des étudiants</p>
</header>

<div class="container">

    <div class="top-section">
        <div class="intro">
            <h2>Bienvenue sur la plateforme universitaire</h2>
            <p>
                Ce portail vous permet d’accéder facilement à plusieurs services : 
                consultation d’épreuves anciennes, informations sur les sorties pédagogiques,
                activités étudiantes et résultats académiques.
            </p>
        </div>
        <div class="auth-buttons">
            <a href="connexion.php">Se connecter</a>
            <a href="inscription.php">Créer un compte</a>
        </div>
    </div>

    <!-- Section Président et Actualités -->
    <?php if ($president): ?>
    <div class="section actualites president-section">

        <!-- 1️⃣ Actualités -->
        <?php if (!empty($actualites)): ?>
            <?php foreach ($actualites as $actu): ?>
                <div class="actualite">
                    <h3><?= htmlspecialchars($actu['titre']) ?></h3>
                    <small>Publié le <?= date("d/m/Y à H:i", strtotime($actu['date_publication'])) ?></small>
                    <p><?= nl2br(htmlspecialchars($actu['contenu'])) ?></p>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p>Aucune actualité publiée pour le moment.</p>
        <?php endif; ?>

        <!-- 2️⃣ Photo + Mot du président -->
        <?php if ($mot_du_president): ?>
        <div class="president-bottom">
            <div class="president-photo">
                <img src="<?= htmlspecialchars($president['photo']) ?>" alt="Photo du président">
            </div>
            <div class="mot-president">
                <strong>Mot du Président :</strong><br>
                <?= nl2br(htmlspecialchars($mot_du_president)) ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- 3️⃣ Nom et mandat -->
        <div class="president-nom">
            <?= htmlspecialchars($president['prenom'] . ' ' . $president['nom']) ?><br>
            Président du Bureau des Étudiants - Mandat <?= htmlspecialchars($current_year['label'] ?? '') ?>
        </div>

    </div>
    <?php endif; ?>

    <!-- Section Modules -->
    <div class="section">
        <h2>Modules disponibles</h2>
        <div class="modules">
            <div class="module-card"><a href="modules/epreuves.php">📘 Épreuves anciennes</a></div>
            <div class="module-card"><a href="modules/sorties.php">🚌 Sorties pédagogiques</a></div>
            <div class="module-card"><a href="modules/activites.php">🎉 Activités étudiantes</a></div>
            <div class="module-card"><a href="modules/resultats.php">📊 Consulter vos résultats</a></div>
        </div>
    </div>
</div>

<footer>
    <p>&copy; <?= date("Y") ?> Institut Saint Paul de Tarse - Tous droits réservés.</p>
    <a href="admin/login.php" style="opacity:0;">Admin</a>
</footer>

</body>
</html>


