<?php
/* CP-05: cierre de sesión.
   Se vacía la sesión, se elimina la cookie de sesión y se destruye
   la sesión en el servidor; luego se redirige al login. */
require_once __DIR__ . '/includes/sesion.php';

sesionRegistrarSalida();

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}

session_destroy();

header('Location: login.php');
exit;
