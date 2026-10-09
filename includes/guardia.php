<?php
/* Guardia de sesión: se incluye al inicio de toda página protegida.
   CP-04: sin sesión activa, redirige al login.
   Además: si el administrador regeneró la contraseña del usuario,
   éste debe cambiarla antes de usar el sistema (cambiar_clave.php). */

require_once __DIR__ . '/sesion.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

/* Cambio forzado de contraseña: solo se permite cambiarla o salir. */
if (!empty($_SESSION['debe_cambiar_clave'])) {
    $scriptActual = basename($_SERVER['SCRIPT_NAME'] ?? '');
    if (!in_array($scriptActual, ['cambiar_clave.php', 'logout.php'], true)) {
        header('Location: cambiar_clave.php');
        exit;
    }
}
