<?php
if (!isset($_SESSION)) session_start();

// Vérifier si l’admin est connecté
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Admin - ISSPT</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f3f4f6;
}

.admin-header {
    background: white;
    padding: 15px 25px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #ddd;
}

.admin-user {
    display: flex;
    align-items: center;
    gap: 10px;
}

.admin-avatar {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    object-fit: cover;
}

.admin-container {
    display: flex;
    min-height: 100vh;
}

.admin-sidebar {
    width: 250px;
    background: white;
    padding: 20px;
    border-right: 1px solid #ddd;
}

.sidebar-title {
    margin: 20px 0 5px;
    color: #666;
    font-size: 12px;
    text-transform: uppercase;
}

.sidebar-link {
    display: block;
    padding: 8px 0;
    color: #333;
    text-decoration: none;
}

.sidebar-link:hover {
    color: #0d6efd;
}

.admin-content {
    padding: 30px;
    flex: 1;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
}

.stat-card {
    background: white;
    padding: 20px;
    border-radius: 10px;
    text-align: center;
    border: 1px solid #eee;
}

.submenu {
    display: none;
    margin-left: 15px;
    border-left: 2px solid #ddd;
    padding-left: 10px;
}

.collapsible-title {
    cursor: pointer;
    user-select: none;
}

    </style>
</head>

<body>

    <?php include __DIR__ . "/partials/header.php"; ?>

    <div class="admin-container">

        <?php include __DIR__ . "/partials/sidebar.php"; ?>

        <main class="admin-content">
            <?php 
                // rendu de la page
                if (isset($content)) echo $content;
            ?>
        </main>

    </div>

    <script>
document.querySelector('.collapsible-title').addEventListener('click', function () {
    const submenu = document.querySelector('.submenu');
    submenu.style.display = submenu.style.display === 'block' ? 'none' : 'block';

    // Change arrow ▸ ↘
    if (submenu.style.display === 'block') {
        this.textContent = '⚽ Gestion Football ↘';
    } else {
        this.textContent = '⚽ Gestion Football ▸';
    }
});
</script>

</body>
</html>
