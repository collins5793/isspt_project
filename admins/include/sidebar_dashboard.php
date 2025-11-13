<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sidebar Admin</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<aside class="sidebar-admin open" id="sidebarAdmin">
  <div class="sidebar-admin-header">
    Espace Admin
  </div>
  <ul class="sidebar-admin-list">
    <!-- Tableau de bord -->
    <li class="sidebar-admin-item active" onclick="navigate(0)" title="Tableau de bord">
      <span class="sidebar-admin-icon"><i class="fas fa-home"></i></span>
      <span class="sidebar-admin-label">Tableau de bord</span>
    </li>

    <!-- Module Fêtes & activités -->
    <li class="sidebar-admin-item" onclick="navigate(1)" title="Activités">
      <span class="sidebar-admin-icon"><i class="fas fa-calendar-alt"></i></span>
      <span class="sidebar-admin-label">Fêtes & activités</span>
    </li>
    <li class="sidebar-admin-item" onclick="navigate(2)" title="Gestion tickets">
      <span class="sidebar-admin-icon"><i class="fas fa-ticket-alt"></i></span>
      <span class="sidebar-admin-label">Tickets & inscriptions</span>
    </li>
    <li class="sidebar-admin-item" onclick="navigate(3)" title="Historique activités">
      <span class="sidebar-admin-icon"><i class="fas fa-history"></i></span>
      <span class="sidebar-admin-label">Historique & export</span>
    </li>

    <!-- Module Résultats académiques -->
    <li class="sidebar-admin-item" onclick="navigate(4)" title="Résultats">
      <span class="sidebar-admin-icon"><i class="fas fa-graduation-cap"></i></span>
      <span class="sidebar-admin-label">Résultats académiques</span>
    </li>
    <li class="sidebar-admin-item" onclick="navigate(5)" title="Import CSV">
      <span class="sidebar-admin-icon"><i class="fas fa-file-csv"></i></span>
      <span class="sidebar-admin-label">Importer CSV</span>
    </li>
    <li class="sidebar-admin-item" onclick="navigate(6)" title="Statistiques">
      <span class="sidebar-admin-icon"><i class="fas fa-chart-line"></i></span>
      <span class="sidebar-admin-label">Statistiques / rapports</span>
    </li>

    <!-- Module Recueil d’épreuves -->
    <li class="sidebar-admin-item" onclick="navigate(7)" title="Épreuves">
      <span class="sidebar-admin-icon"><i class="fas fa-file-pdf"></i></span>
      <span class="sidebar-admin-label">Recueil d’épreuves</span>
    </li>

    <!-- Gestion utilisateurs -->
    <li class="sidebar-admin-item" onclick="navigate(8)" title="Ajouter admin">
      <span class="sidebar-admin-icon"><i class="fas fa-user-plus"></i></span>
      <span class="sidebar-admin-label">Ajouter admin</span>
    </li>
    <li class="sidebar-admin-item" onclick="navigate(9)" title="Liste admins">
      <span class="sidebar-admin-icon"><i class="fas fa-users-cog"></i></span>
      <span class="sidebar-admin-label">Liste admins</span>
    </li>

    <!-- Paramètres & profil -->
    <li class="sidebar-admin-item" onclick="navigate(10)" title="Profil">
      <span class="sidebar-admin-icon"><i class="fas fa-user-circle"></i></span>
      <span class="sidebar-admin-label">Profil & paramètres</span>
    </li>
    <li class="sidebar-admin-item" onclick="navigate(11)" title="Déconnexion">
      <span class="sidebar-admin-icon"><i class="fas fa-sign-out-alt"></i></span>
      <span class="sidebar-admin-label">Déconnexion</span>
    </li>
  </ul>
</aside>

<script>
  let activeItem = 0;

  function navigate(id) {
    activeItem = id;
    const items = document.querySelectorAll('.sidebar-admin-item');
    items.forEach((item, index) => {
      if(index === id) {
        item.classList.add('active');
        console.log('Navigation vers : ' + item.title);
        // Ici tu peux ajouter le code pour charger dynamiquement la page / section
      } else {
        item.classList.remove('active');
      }
    });
  }
</script>

</body>
</html>
