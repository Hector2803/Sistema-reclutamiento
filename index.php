<?php
/* Punto de entrada del sistema.
   Si ya hay sesión activa va al panel; si no, al login.
   Es el archivo que NetBeans abre al dar Run. */
require_once __DIR__ . '/includes/sesion.php';

if (isset($_SESSION['usuario_id'])) {
    header('Location: dashboard.php');
} else {
    header('Location: login.php');
}
exit;
