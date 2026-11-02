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

/* Si falta la tabla de capacitaciones (código nuevo con base antigua), aplica sql/flujo_proceso.sql */
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/migrar_flujo.php';

/* Perfil siempre actual: si el administrador cambia el rol o el ÁREA de un usuario (o lo desactiva)
   mientras éste tiene la sesión abierta, el cambio rige desde la siguiente página, sin volver a ingresar. */
try {
    $pdoG = obtenerConexion();
    $stG = $pdoG->prepare("SELECT u.nombre, u.estado, u.area_id, a.nombre AS area, r.nombre AS rol
                             FROM usuarios u INNER JOIN roles r ON r.id = u.rol_id
                             LEFT JOIN areas a ON a.id = u.area_id WHERE u.id = :id");
    $stG->execute([':id' => (int)$_SESSION['usuario_id']]);
    $perfilG = $stG->fetch();
    if (!$perfilG || (int)$perfilG['estado'] !== 1) {
        session_unset(); session_destroy();
        header('Location: login.php'); exit;
    }
    $_SESSION['usuario_nombre']  = $perfilG['nombre'];
    $_SESSION['usuario_rol']     = $perfilG['rol'];
    $_SESSION['usuario_area_id'] = $perfilG['area_id'] !== null ? (int)$perfilG['area_id'] : null;
    $_SESSION['usuario_area']    = $perfilG['area'];
} catch (Throwable $e) { /* si falla la consulta se conserva lo guardado en la sesión */ }


migrarFlujoSiHaceFalta();
