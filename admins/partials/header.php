<header class="admin-header">
    <div class="admin-header-left">
        <h1>Dashboard Admin</h1>
    </div>

    <div class="admin-header-right">
        <div class="admin-user">
            <img src="../uploads/admins/<?= $_SESSION['admin_photo'] ?? "default.png" ?>" class="admin-avatar">

            <div>
                <?php
                // On récupère le nom/prénom selon le rôle
                if ($_SESSION['admin_role'] === 'bureau' && !empty($_SESSION['admin_id_etudiant'])) {
                    // Connexion PDO $db déjà existante
                    $stmt = $db->prepare("SELECT nom, prenom FROM etudiants WHERE id_etudiant = ?");
                    $stmt->execute([$_SESSION['admin_id_etudiant']]);
                    $etudiant = $stmt->fetch(PDO::FETCH_ASSOC);

                    $prenom = $etudiant['prenom'] ?? 'Bureau';
                    $nom = $etudiant['nom'] ?? 'Membre';
                } else {
                    $prenom = $_SESSION['admin_prenom'] ?? '';
                    $nom = $_SESSION['admin_nom'] ?? '';
                }
                ?>
                <p class="admin-name"><?= htmlspecialchars($prenom . " " . $nom) ?></p>
                <small class="admin-role"><?= htmlspecialchars($_SESSION['admin_role']) ?></small>
            </div>
        </div>
    </div>
</header>
