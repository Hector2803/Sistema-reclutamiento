<?php
/* ============================================================
   SSR - A365 | Cerrar la sesión de otro usuario (panel admin)
   Marca la sesión como cerrada; en el siguiente movimiento del
   usuario, includes/sesion.php la destruye y lo manda al login.
   ============================================================ */

require_once __DIR__ . '/includes/guardia.php';
require_once __DIR__ . '/config/conexion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: usuarios.php');
    exit;
}

if (($_SESSION['usuario_rol'] ?? '') !== 'Administrador') {
    $_SESSION['flash_error'] = 'No tienes permisos para cerrar sesiones.';
    header('Location: usuarios.php');
    exit;
}

$sesion = trim($_POST['sesion'] ?? '');

if (!preg_match('/^[A-Za-z0-9,-]{16,64}$/', $sesion)) {
    $_SESSION['flash_error'] = 'Sesión no válida.';
    header('Location: usuarios.php');
    exit;
}

if ($sesion === session_id()) {
    $_SESSION['flash_error'] = 'Esa es tu propia sesión. Usa el menú de perfil para salir.';
    header('Location: usuarios.php');
    exit;
}

try {
    $pdo = obtenerConexion();

    $st = $pdo->prepare(
        "UPDATE sesiones_activas SET cerrada = 1
          WHERE sesion = :s AND cerrada = 0"
    );
    $st->execute([':s' => $sesion]);

    if ($st->rowCount() > 0) {
        $_SESSION['flash_ok'] = 'Sesión cerrada. El usuario saldrá del sistema en su siguiente acción.';
    } else {
        $_SESSION['flash_error'] = 'Esa sesión ya no está activa.';
    }
} catch (PDOException $e) {
    $_SESSION['flash_error'] = 'No se pudo cerrar la sesión. Intenta nuevamente.';
}

header('Location: usuarios.php');
exit;
