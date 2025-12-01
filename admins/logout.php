<?php
session_start();

// Supprimer toutes les variables de session
$_SESSION = [];

// Détruire complètement la session
session_destroy();

// Empêcher de revenir en arrière sur les pages protégées
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// Redirection vers l'accueil du site (à la racine)
header("Location: /index.php");
exit();
