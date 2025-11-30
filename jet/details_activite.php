<?php
session_start();
require_once "../includes/db.php"; // Connexion PDO


// Initialisation des variables
$isLogged = false;
$isAdmin = false;
$userName = '';
$userAvatar = '../assets/images/default-avatar.png'; // avatar par défaut

// Vérification si un étudiant est connecté
if (isset($_SESSION['etudiant_id'])) {
    $stmt = $pdo->prepare("SELECT nom, prenom, photo FROM etudiants WHERE id_etudiant = ? AND statut = 'actif'");
    $stmt->execute([$_SESSION['etudiant_id']]);
    $etudiant = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($etudiant) {
        $isLogged = true;
        $userName = $etudiant['prenom'] . ' ' . $etudiant['nom'];
        if (!empty($etudiant['photo'])) $userAvatar = '../uploads/etudiants/' . $etudiant['photo'];
    } else {
        // Déconnecter si l'étudiant n'existe pas ou est inactif
        session_unset();
        session_destroy();
        header("Location: login.php");
        exit;
    }
}

// Vérification si un administrateur est connecté
if (isset($_SESSION['admin_id'])) {
    $stmt = $pdo->prepare("SELECT nom, prenom, role FROM administrateurs WHERE id_admin = ?");
    $stmt->execute([$_SESSION['admin_id']]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($admin) {
        $isLogged = true;
        $isAdmin = true;
        $userName = $admin['prenom'] . ' ' . $admin['nom'];
    } else {
        session_unset();
        session_destroy();
        header("Location: login.php");
        exit;
    }
}
if (!isset($_GET['id'])) {
    die("Activité introuvable.");
}

$id = intval($_GET['id']);

// --- TRAITEMENT FORMULAIRE COMMENTAIRE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $message = trim($_POST['message']);

    if (!empty($message)) {
        // Ici, on suppose que l'utilisateur connecté est un étudiant
        // Remplacez par l'ID réel de l'étudiant connecté depuis la session
        $id_etudiant = $_SESSION['id_etudiant'] ?? null;

        if ($isLogged) {
            $stmt = $pdo->prepare("
                INSERT INTO commentaires (activity_id, id_etudiant, message, user_type)
                VALUES (:activity_id, :id_etudiant, :message, 'etudiant')
            ");
            $stmt->execute([
                'activity_id' => $id,
                'id_etudiant' => $id_etudiant,
                'message' => $message
            ]);

            // Redirection pour éviter le re-post
            header("Location: details_activite.php?id=$id");
            exit();
        } else {
            $error_commentaire = "Vous devez être connecté pour commenter.";
        }
    } else {
        $error_commentaire = "Le message ne peut pas être vide.";
    }
}

// Charger l’activité
$req = $pdo->prepare("
    SELECT a.*, ay.label AS annee_academique, ad.nom AS admin_nom, ad.prenom AS admin_prenom
    FROM activites a
    JOIN academic_years ay ON ay.id = a.academic_year_id
    LEFT JOIN administrateurs ad ON ad.id_admin = a.cree_par
    WHERE a.id_activite = ?
");
$req->execute([$id]);
$activite = $req->fetch();

if (!$activite) {
    die("Activité non trouvée.");
}

// Charger la galerie
$galerie = $pdo->prepare("SELECT * FROM galerie WHERE activity_id = ?");
$galerie->execute([$id]);

// Charger les participants
$participants = $pdo->prepare("
    SELECT p.*, e.nom, e.prenom, e.matricule
    FROM participants_activites p
    JOIN etudiants e ON e.id_etudiant = p.id_etudiant
    WHERE p.id_activite = ?
");
$participants->execute([$id]);

// Charger les commentaires
$commentaires = $pdo->prepare("
    SELECT c.*, e.nom AS etu_nom, e.prenom AS etu_prenom, ad.nom AS admin_nom, ad.prenom AS admin_prenom
    FROM commentaires c
    LEFT JOIN etudiants e ON e.id_etudiant = c.id_etudiant
    LEFT JOIN administrateurs ad ON ad.id_admin = c.id_admin
    WHERE c.activity_id = ?
    ORDER BY c.date_commentaire DESC
");
$commentaires->execute([$id]);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Détails Activité - <?= htmlspecialchars($activite['nom_activite']) ?></title>
<style>
    /* =============================================
   DÉTAIL ACTIVITÉ UNIVERSITAIRE - ISSPT
   STYLE PREMIUM & MODERNE
   ============================================= */

/* ==================== VARIABLES CSS ==================== */
:root {
  /* Couleurs principales */
  --primary: rgb(8, 0, 32);
  --secondary: rgb(186, 40, 30);
  --white: #ffffff;
  --gray-light: #cccccc;
  --gray-dark: #222222;
  --glass-bg: rgba(255, 255, 255, 0.05);
  --glass-bg-light: rgba(255, 255, 255, 0.08);
  
  /* Typographie */
  --font-primary: 'Segoe UI', system-ui, -apple-system, sans-serif;
  --font-heading: 'Montserrat', 'Arial Black', sans-serif;
  
  /* Espacements */
  --spacing-xs: 0.5rem;
  --spacing-sm: 1rem;
  --spacing-md: 1.5rem;
  --spacing-lg: 2rem;
  --spacing-xl: 3rem;
  --spacing-xxl: 5rem;
  
  /* Bordures */
  --border-radius-sm: 8px;
  --border-radius-md: 12px;
  --border-radius-lg: 16px;
  --border-radius-xl: 24px;
  
  /* Ombres */
  --shadow-soft: 0 4px 20px rgba(0, 0, 0, 0.15);
  --shadow-medium: 0 8px 30px rgba(0, 0, 0, 0.25);
  --shadow-heavy: 0 15px 50px rgba(0, 0, 0, 0.35);
  --shadow-glow: 0 12px 30px rgba(186, 40, 30, 0.5);
  
  /* Transitions */
  --transition-speed: 0.35s;
  --transition-fast: 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  --transition-normal: 0.35s cubic-bezier(0.4, 0, 0.2, 1);
  --transition-slow: 0.5s cubic-bezier(0.4, 0, 0.2, 1);
}

/* ==================== RESET & BASE ==================== */
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

html {
  scroll-behavior: smooth;
}

body {
  font-family: var(--font-primary);
  background: var(--primary);
  color: var(--white);
  line-height: 1.7;
  overflow-x: hidden;
  min-height: 100vh;
  background-image: 
    radial-gradient(circle at 10% 20%, rgba(186, 40, 30, 0.1) 0%, transparent 20%),
    radial-gradient(circle at 90% 80%, rgba(186, 40, 30, 0.05) 0%, transparent 20%);
}

.container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 var(--spacing-md);
}

/* ==================== ANIMATIONS ==================== */
@keyframes fadeIn {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}

@keyframes fadeUp {
  from {
    opacity: 0;
    transform: translateY(30px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes fadeDown {
  from {
    opacity: 0;
    transform: translateY(-30px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes slideLeft {
  from {
    opacity: 0;
    transform: translateX(30px);
  }
  to {
    opacity: 1;
    transform: translateX(0);
  }
}

@keyframes slideRight {
  from {
    opacity: 0;
    transform: translateX(-30px);
  }
  to {
    opacity: 1;
    transform: translateX(0);
  }
}

@keyframes scaleIn {
  from {
    opacity: 0;
    transform: scale(0.95);
  }
  to {
    opacity: 1;
    transform: scale(1);
  }
}

@keyframes glowPulse {
  0%, 100% {
    box-shadow: var(--shadow-medium);
  }
  50% {
    box-shadow: var(--shadow-glow);
  }
}

/* Classes d'animation */
.fade-in {
  animation: fadeIn 0.8s var(--transition-slow) both;
}

.fade-up {
  animation: fadeUp 0.6s var(--transition-slow) both;
}

.fade-down {
  animation: fadeDown 0.6s var(--transition-slow) both;
}

.slide-left {
  animation: slideLeft 0.6s var(--transition-slow) both;
}

.slide-right {
  animation: slideRight 0.6s var(--transition-slow) both;
}

.scale-in {
  animation: scaleIn 0.4s var(--transition-slow) both;
}

/* ==================== 1️⃣ HEADER & TITRE ACTIVITÉ ==================== */
.header {
  text-align: center;
  padding: var(--spacing-xxl) var(--spacing-md);
  background: linear-gradient(135deg, var(--primary) 0%, rgba(8, 0, 32, 0.9) 100%);
  position: relative;
  overflow: hidden;
  animation: fadeDown 0.8s var(--transition-slow);
}

.header::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: 
    radial-gradient(ellipse at 50% 20%, rgba(186, 40, 30, 0.15) 0%, transparent 50%);
  pointer-events: none;
}

.header h1 {
  font-family: var(--font-heading);
  font-size: clamp(2.2rem, 4vw, 3rem);
  font-weight: 800;
  margin-bottom: var(--spacing-sm);
  text-shadow: 0 0 20px rgba(186, 40, 30, 0.3);
  position: relative;
  z-index: 2;
}

.header p {
  font-size: 1.2rem;
  color: var(--gray-light);
  position: relative;
  z-index: 2;
}

.header strong {
  color: var(--secondary);
  font-weight: 600;
}

/* ==================== 2️⃣ CARTES ==================== */
.card {
  background: var(--glass-bg-light);
  backdrop-filter: blur(20px);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: var(--border-radius-md);
  padding: var(--spacing-xl);
  margin: var(--spacing-lg) 0;
  transition: var(--transition-normal);
  position: relative;
  overflow: hidden;
  box-shadow: var(--shadow-soft);
  animation: fadeUp 0.6s var(--transition-slow) both;
}

/* Délais stagger pour les cartes */
.card:nth-child(1) { animation-delay: 0.1s; }
.card:nth-child(2) { animation-delay: 0.2s; }
.card:nth-child(3) { animation-delay: 0.3s; }
.card:nth-child(4) { animation-delay: 0.4s; }
.card:nth-child(5) { animation-delay: 0.5s; }

.card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 3px;
  background: linear-gradient(90deg, var(--secondary), transparent);
  transform: scaleX(0);
  transition: var(--transition-normal);
}

.card:hover {
  transform: translateY(-5px);
  box-shadow: var(--shadow-heavy);
  border-color: var(--secondary);
}

.card:hover::before {
  transform: scaleX(1);
}

.card h2 {
  font-family: var(--font-heading);
  font-size: 1.6rem;
  font-weight: 700;
  margin-bottom: var(--spacing-md);
  color: var(--white);
  position: relative;
  padding-bottom: var(--spacing-xs);
}

.card h2::after {
  content: '';
  position: absolute;
  bottom: 0;
  left: 0;
  width: 50px;
  height: 2px;
  background: var(--secondary);
  border-radius: 1px;
}

.card p {
  color: var(--gray-light);
  line-height: 1.8;
  margin-bottom: var(--spacing-sm);
}

/* ==================== 3️⃣ GALERIE ==================== */
.galerie {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: var(--spacing-md);
  margin-top: var(--spacing-md);
}

.galerie img,
.galerie video {
  width: 100%;
  height: 200px;
  object-fit: cover;
  border-radius: var(--border-radius-sm);
  transition: var(--transition-normal);
  box-shadow: var(--shadow-soft);
  cursor: pointer;
  animation: scaleIn 0.6s var(--transition-slow) both;
}

/* Délais stagger pour la galerie */
.galerie img:nth-child(1),
.galerie video:nth-child(1) { animation-delay: 0.1s; }
.galerie img:nth-child(2),
.galerie video:nth-child(2) { animation-delay: 0.2s; }
.galerie img:nth-child(3),
.galerie video:nth-child(3) { animation-delay: 0.3s; }
.galerie img:nth-child(4),
.galerie video:nth-child(4) { animation-delay: 0.4s; }

.galerie img:hover,
.galerie video:hover {
  transform: scale(1.05);
  box-shadow: var(--shadow-glow);
}

/* ==================== 4️⃣ TABLEAU PARTICIPANTS ==================== */
.participants {
  width: 100%;
  border-collapse: collapse;
  margin-top: var(--spacing-md);
  background: var(--glass-bg);
  border-radius: var(--border-radius-sm);
  overflow: hidden;
  box-shadow: var(--shadow-soft);
  animation: fadeUp 0.6s var(--transition-slow) 0.3s both;
}

.participants th {
  background: var(--secondary);
  color: var(--white);
  padding: var(--spacing-md);
  text-align: left;
  font-weight: 600;
  text-transform: uppercase;
  font-size: 0.9rem;
  letter-spacing: 0.5px;
}

.participants td {
  padding: var(--spacing-md);
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
  color: var(--gray-light);
  transition: var(--transition-fast);
}

.participants tr:nth-child(even) {
  background: rgba(255, 255, 255, 0.02);
}

.participants tr:hover {
  background: rgba(186, 40, 30, 0.1);
  box-shadow: 0 0 15px rgba(186, 40, 30, 0.2);
}

.participants tr:hover td {
  color: var(--white);
}

/* ==================== 5️⃣ COMMENTAIRES & FORMULAIRE ==================== */
.comment {
  background: var(--glass-bg);
  backdrop-filter: blur(10px);
  border: 1px solid rgba(255, 255, 255, 0.05);
  border-radius: var(--border-radius-md);
  padding: var(--spacing-md);
  margin-bottom: var(--spacing-md);
  transition: var(--transition-normal);
  animation: fadeUp 0.6s var(--transition-slow) both;
}

.comment:hover {
  border-color: rgba(255, 255, 255, 0.1);
  transform: translateX(5px);
}

.comment strong {
  color: var(--secondary);
  font-size: 1rem;
  display: block;
  margin-bottom: var(--spacing-xs);
}

.comment small {
  color: var(--gray-light);
  opacity: 0.7;
  font-size: 0.8rem;
  margin-bottom: var(--spacing-sm);
  display: block;
}

.comment p {
  color: var(--gray-light);
  line-height: 1.6;
  margin: 0;
}

/* Formulaire */
form {
  margin-top: var(--spacing-md);
}

textarea {
  width: 100%;
  padding: var(--spacing-md);
  background: var(--glass-bg);
  border: 1px solid rgba(255, 255, 255, 0.2);
  border-radius: var(--border-radius-md);
  color: var(--white);
  font-family: var(--font-primary);
  font-size: 1rem;
  resize: vertical;
  min-height: 120px;
  transition: var(--transition-normal);
  margin-bottom: var(--spacing-md);
}

textarea::placeholder {
  color: var(--gray-light);
}

textarea:focus {
  outline: none;
  border-color: var(--secondary);
  box-shadow: 0 0 0 2px rgba(186, 40, 30, 0.3);
  background: rgba(255, 255, 255, 0.05);
}

/* Boutons */
.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: var(--spacing-sm) var(--spacing-lg);
  background: var(--secondary);
  color: var(--white);
  border: none;
  border-radius: var(--border-radius-md);
  font-weight: 600;
  text-decoration: none;
  transition: var(--transition-normal);
  cursor: pointer;
  font-size: 1rem;
  position: relative;
  overflow: hidden;
}

.btn::before {
  content: '';
  position: absolute;
  top: 0;
  left: -100%;
  width: 100%;
  height: 100%;
  background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
  transition: var(--transition-normal);
}

.btn:hover {
  transform: translateY(-2px);
  box-shadow: var(--shadow-glow);
}

.btn:hover::before {
  left: 100%;
}

/* Message d'erreur */
[style*="color:red"] {
  background: rgba(244, 67, 54, 0.1);
  color: #f44336 !important;
  padding: var(--spacing-md);
  border-radius: var(--border-radius-md);
  border: 1px solid rgba(244, 67, 54, 0.3);
  margin-bottom: var(--spacing-md);
  animation: fadeIn 0.5s ease;
}

/* ==================== 6️⃣ FOOTER ==================== */
footer {
  background: var(--primary);
  color: var(--gray-light);
  padding: var(--spacing-xxl) 0 var(--spacing-xl);
  margin-top: var(--spacing-xxl);
  border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.footer-content {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: var(--spacing-xl);
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 var(--spacing-md);
}

.footer-column h3 {
  color: var(--secondary);
  font-family: var(--font-heading);
  font-size: 1.2rem;
  margin-bottom: var(--spacing-md);
  position: relative;
}

.footer-column h3::after {
  content: '';
  position: absolute;
  bottom: -8px;
  left: 0;
  width: 30px;
  height: 2px;
  background: var(--secondary);
  border-radius: 1px;
}

.footer-column ul {
  list-style: none;
}

.footer-column ul li {
  margin-bottom: var(--spacing-sm);
}

.footer-column a {
  color: var(--gray-light);
  text-decoration: none;
  transition: var(--transition-normal);
  position: relative;
  padding-bottom: 2px;
}

.footer-column a::after {
  content: '';
  position: absolute;
  bottom: 0;
  left: 0;
  width: 0;
  height: 1px;
  background: var(--secondary);
  transition: var(--transition-normal);
}

.footer-column a:hover {
  color: var(--white);
}

.footer-column a:hover::after {
  width: 100%;
}

.social-icons {
  display: flex;
  gap: var(--spacing-sm);
  margin-top: var(--spacing-md);
}

.social-icons a {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: var(--glass-bg);
  display: flex;
  align-items: center;
  justify-content: center;
  transition: var(--transition-normal);
  border: 1px solid rgba(255, 255, 255, 0.1);
}

.social-icons a:hover {
  background: var(--secondary);
  transform: translateY(-3px);
  box-shadow: var(--shadow-glow);
}

.footer-bottom {
  text-align: center;
  margin-top: var(--spacing-xl);
  padding-top: var(--spacing-md);
  border-top: 1px solid rgba(255, 255, 255, 0.1);
  color: var(--gray-light);
  font-size: 0.9rem;
}

/* ==================== RESPONSIVE DESIGN ==================== */
@media (max-width: 900px) {
  .galerie {
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  }
  
  .participants {
    font-size: 0.9rem;
  }
  
  .participants th,
  .participants td {
    padding: var(--spacing-sm);
  }
  
  .footer-content {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 768px) {
  .card {
    padding: var(--spacing-lg);
  }
  
  .header {
    padding: var(--spacing-xl) var(--spacing-md);
  }
}

@media (max-width: 600px) {
  :root {
    --spacing-xs: 0.25rem;
    --spacing-sm: 0.75rem;
    --spacing-md: 1rem;
    --spacing-lg: 1.5rem;
    --spacing-xl: 2rem;
    --spacing-xxl: 3rem;
  }
  
  .galerie {
    grid-template-columns: 1fr;
  }
  
  .participants {
    display: block;
    overflow-x: auto;
    white-space: nowrap;
  }
  
  .footer-content {
    grid-template-columns: 1fr;
    text-align: center;
  }
  
  .footer-column h3::after {
    left: 50%;
    transform: translateX(-50%);
  }
  
  .social-icons {
    justify-content: center;
  }
  
  .card h2 {
    font-size: 1.4rem;
  }
}

/* ==================== ACCESSIBILITÉ ==================== */
@media (prefers-reduced-motion: reduce) {
  * {
    animation-duration: 0.01ms !important;
    animation-iteration-count: 1 !important;
    transition-duration: 0.01ms !important;
  }
}

/* Focus visible pour accessibilité */
.btn:focus-visible,
textarea:focus-visible,
.footer-column a:focus-visible,
.social-icons a:focus-visible {
  outline: 2px solid var(--secondary);
  outline-offset: 2px;
}

/* États de chargement */
.skeleton {
  background: linear-gradient(90deg, var(--glass-bg) 25%, rgba(255,255,255,0.1) 50%, var(--glass-bg) 75%);
  background-size: 200% 100%;
  animation: loading 1.5s infinite;
}

@keyframes loading {
  0% {
    background-position: 200% 0;
  }
  100% {
    background-position: -200% 0;
  }
}
</style>
</head>
<body>
    <?php include "../includes/header.php"; ?>

<div class="header">
    <h1><?= htmlspecialchars($activite['nom_activite']) ?></h1>
    <p>Année académique : <strong><?= $activite['annee_academique'] ?></strong></p>
</div>

<div class="container">

    <!-- DESCRIPTION -->
    <div class="card">
        <h2>Description</h2>
        <p><?= nl2br(htmlspecialchars($activite['description'])) ?></p>
    </div>

    <!-- CONDITIONS -->
    <div class="card">
        <h2>Conditions de Participation</h2>
        <p><?= nl2br(htmlspecialchars($activite['conditions'])) ?></p>
    </div>

    <!-- GALERIE -->
    <div class="card">
        <h2>Galerie</h2>
        <div class="galerie">
            <?php foreach ($galerie as $media): ?>
                <?php if ($media['file_type'] === 'image'): ?>
                    <img src="<?= $media['file_path'] ?>" alt="">
                <?php else: ?>
                    <video src="<?= $media['file_path'] ?>" controls></video>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- PARTICIPANTS -->
    <div class="card">
        <h2>Participants</h2>

        <table class="participants">
            <tr>
                <th>Nom</th>
                <th>Matricule</th>
                <th>Role</th>
                <th>Statut</th>
                <th>Performance</th>
            </tr>

            <?php foreach ($participants as $p): ?>
            <tr>
                <td><?= $p['nom']." ".$p['prenom'] ?></td>
                <td><?= $p['matricule'] ?></td>
                <td><?= $p['role'] ?? "—" ?></td>
                <td><?= $p['statut'] ?></td>
                <td><?= $p['performance'] ?? "—" ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <!-- COMMENTAIRES -->
    <div class="card">
        <h2>Commentaires</h2>

        <?php foreach ($commentaires as $c): ?>
            <div class="comment">
                <strong>
                    <?php if ($c['user_type'] === 'admin'): ?>
                        Admin : <?= $c['admin_nom']." ".$c['admin_prenom'] ?>
                    <?php else: ?>
                        <?= $c['etu_nom']." ".$c['etu_prenom'] ?>
                    <?php endif; ?>
                </strong>
                <br>
                <small><?= $c['date_commentaire'] ?></small>
                <p><?= nl2br(htmlspecialchars($c['message'])) ?></p>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- FORMULAIRE COMMENTAIRE -->
    <div class="card">
    <h2>Laisser un commentaire</h2>

    <?php if (!empty($error_commentaire)): ?>
        <p style="color:red;"><?= $error_commentaire ?></p>
    <?php endif; ?>

    <?php if($isLogged): ?>
    <form action="" method="POST">
        <input type="hidden" name="id_activite" value="<?= $id ?>">
        <textarea name="message" required rows="4" style="width:100%;padding:10px;border-radius:8px;"></textarea>
        <br><br>
        <button class="btn">Envoyer</button>
    </form>
    <?php else: ?>
        <p>Vous devez être connecté pour commenter.</p>
    <?php endif; ?>
</div>


</div>
<?php include "../includes/footer.php"; ?>

</body>
</html>
