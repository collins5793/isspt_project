<?php

// On récupère le nom/prénom selon le rôle
if ($_SESSION['admin_role'] === 'bureau' && !empty($_SESSION['admin_id_etudiant'])) {
    $stmt = $db->prepare("SELECT nom, prenom FROM etudiants WHERE id_etudiant = ?");
    $stmt->execute([$_SESSION['admin_id_etudiant']]);
    $etudiant = $stmt->fetch(PDO::FETCH_ASSOC);

    $prenom = $etudiant['prenom'] ?? 'Bureau';
    $nom = $etudiant['nom'] ?? 'Membre';
} else {
    $prenom = $_SESSION['admin_prenom'] ?? '';
    $nom = $_SESSION['admin_nom'] ?? '';
}

// Définir la base URL de l'administration
define('BASE_URL', '/isspt_projet/admins/');
?>

<aside class="admin-sidebar">
    <div class="sidebar-profile">
        <img src="<?= BASE_URL ?>assets/img/admin.png" class="profile-avatar" alt="Profil">
        <h3 class="profile-name"><?= htmlspecialchars("$prenom $nom") ?></h3>
        <p class="profile-role">Administrateur</p>
        <a href="<?= BASE_URL ?>profile.php" class="profile-btn btn btn-sm btn-outline-primary">Voir Profil</a>
    </div>

    <nav class="sidebar-nav">
        <h4 class="sidebar-title">Menu Principal</h4>
        <a href="<?= BASE_URL ?>dashboard.php" class="sidebar-link">📊 Tableau de bord</a>
       <a href="<?= BASE_URL ?>actualite/actualites.php" class="sidebar-link">Actualite</a>

        <h4 class="sidebar-title collapsible">👥 Utilisateurs ▸</h4>
        <div class="submenu">
            <a href="<?= BASE_URL ?>users/etudiants.php" class="sidebar-link">👨‍🎓 Étudiants</a>
            <a href="<?= BASE_URL ?>users/admins.php" class="sidebar-link">🛡️ Administrateurs</a>
            <a href="<?= BASE_URL ?>roles.php" class="sidebar-link">🔐 Rôles & Permissions</a>
        </div>

        <h4 class="sidebar-title collapsible">📚 Académique ▸</h4>
        <div class="submenu">
            <a href="<?= BASE_URL ?>annees.php" class="sidebar-link">📅 Années académiques</a>
            <a href="<?= BASE_URL ?>ues.php" class="sidebar-link">📘 UE</a>
            <a href="<?= BASE_URL ?>matieres.php" class="sidebar-link">📗 Matières</a>
            <a href="<?= BASE_URL ?>resultats.php" class="sidebar-link">📝 Résultats</a>
            <a href="<?= BASE_URL ?>notes_logs.php" class="sidebar-link">📄 Historique des notes</a>
        </div>

        <h4 class="sidebar-title collapsible">📚 Gestion Épreuves ▸</h4>
        <div class="submenu">
            <a href="<?= BASE_URL ?>epreuve/index.php" class="sidebar-link">📄 Toutes les épreuves</a>
            <a href="<?= BASE_URL ?>epreuve/categories.php" class="sidebar-link">📂 Catégories</a>
            <a href="<?= BASE_URL ?>epreuve/matieres.php" class="sidebar-link">📘 Matières</a>
            <a href="<?= BASE_URL ?>epreuve/filieres.php" class="sidebar-link">🏛️ Filières</a>
        </div>


        <h4 class="sidebar-title collapsible">🎉 Événements ▸</h4>
        <div class="submenu">
            <a href="<?= BASE_URL ?>evenement/evenements.php" class="sidebar-link">🎊 Tous les événements</a>
            <a href="<?= BASE_URL ?>tickets.php" class="sidebar-link">🎟️ Tickets</a>
            <a href="<?= BASE_URL ?>evenement/activites.php" class="sidebar-link">⚽ Activités & Clubs</a>
            <a href="<?= BASE_URL ?>galerie.php" class="sidebar-link">🖼️ Galerie</a>
        </div>

        <h4 class="sidebar-title collapsible">⚽ Gestion Football ▸</h4>
        <div class="submenu">
            <a href="<?= BASE_URL ?>football/season.php" class="sidebar-link">📅 Saisons</a>
            <a href="<?= BASE_URL ?>football/players.php" class="sidebar-link">👟 Joueurs</a>
            <a href="<?= BASE_URL ?>football/matches.php" class="sidebar-link">🏆 Matchs</a>
        </div>

        <h4 class="sidebar-title collapsible">🧩 Modules ▸</h4>
        <div class="submenu">
            <a href="<?= BASE_URL ?>messagerie.php" class="sidebar-link">💬 Messagerie interne</a>
            <a href="<?= BASE_URL ?>documents.php" class="sidebar-link">📂 Documents</a>
            <a href="<?= BASE_URL ?>notifications.php" class="sidebar-link">🔔 Notifications</a>
            <a href="<?= BASE_URL ?>logs.php" class="sidebar-link">📜 Logs systèmes</a>
        </div>

        <h4 class="sidebar-title collapsible">⚙️ Paramètres ▸</h4>
        <div class="submenu">
            <a href="<?= BASE_URL ?>profile.php" class="sidebar-link">🧑‍💼 Profil</a>
            <a href="<?= BASE_URL ?>parametres.php" class="sidebar-link">⚙️ Configuration générale</a>
            <a href="<?= BASE_URL ?>themes.php" class="sidebar-link">🎨 Apparence & Thème</a>
            <a href="<?= BASE_URL ?>securite.php" class="sidebar-link">🔐 Sécurité</a>
        </div>

        <a href="<?= BASE_URL ?>logout.php" class="sidebar-link logout">🚪 Déconnexion</a>
    </nav>
</aside>

<script>
document.querySelectorAll('.collapsible').forEach(title => {
    title.addEventListener('click', () => {
        const submenu = title.nextElementSibling;
        submenu.style.display = submenu.style.display === 'flex' ? 'none' : 'flex';
    });
});
</script>
