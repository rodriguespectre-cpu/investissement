<?php

session_start();

/*
|--------------------------------------------------------------------------
| Déconnexion administration
|--------------------------------------------------------------------------
*/

// Supprime uniquement les variables de session admin
unset(
    $_SESSION['admin_id'],
    $_SESSION['admin_username'],
    $_SESSION['admin_email'],
    $_SESSION['admin_role']
);

// Régénère l'identifiant de session
session_regenerate_id(true);

// Redirection vers la connexion admin
header('Location: login.php');
exit;
