<?php
/* ============================================================
   SSR - A365 | Regenerar contraseña de un usuario (solo admin)
   Genera una clave temporal, la guarda hasheada y marca
   debe_cambiar_clave = 1: al iniciar sesión con esa clave
   temporal, guardia.php obliga a cambiarla antes de entrar.
   La clave temporal se muestra UNA sola vez en el mensaje.
   Respuesta AJAX -> JSON {ok, mensaje}; POST normal -> flash.
   ============================================================ */
require_once __DIR__ . '/includes/guardia.php';
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/includes/passwords.php';
require_once __DIR__ . '/includes/correo.php';

/** Responde JSON (AJAX) o flash + redirección (POST normal). */
function responder(bool $ok, string $mensaje): void
{
    $xrh = strtoupper($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ($_SERVER['HTTP_X_REQUESTEDWITH'] ?? ''));
    if ($xrh === 'XMLHTTPREQUEST') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => $ok, 'mensaje' => $mensaje], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $_SESSION[$ok ? 'flash_ok' : 'flash_error'] = $mensaje;
    header('Location: usuarios.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: usuarios.php');
    exit;
}
if (($_SESSION['usuario_rol'] ?? '') !== 'Administrador') {
    responder(false, 'No tienes permisos para regenerar contraseñas.');
}

$usuarioId = (int)($_POST['usuario_id'] ?? 0);
if ($usuarioId <= 0) {
    responder(false, 'Usuario no válido.');
}

$ok  = true;
$msg = '';

try {
    $pdo = obtenerConexion();

    $st = $pdo->prepare("SELECT id, usuario, nombre, correo FROM usuarios WHERE id = :id");
    $st->execute([':id' => $usuarioId]);
    $objetivo = $st->fetch();

    if (!$objetivo) {
        responder(false, 'El usuario ya no existe.');
    }

    $temporal = generarClaveTemporal(10);

    $pdo->prepare("UPDATE usuarios SET clave = :c, debe_cambiar_clave = 1 WHERE id = :id")
        ->execute([':c' => hashClave($temporal), ':id' => $usuarioId]);

    // Envío de la clave temporal al correo real de la persona
    $correoReal = (string)($objetivo['correo'] ?? '');
    if ($correoReal !== '') {
        $html = correoCredenciales($objetivo['nombre'], $objetivo['usuario'], $temporal, true);
        [$okEnv, $msgEnv] = correoEnviar(
            $pdo, $correoReal, 'Tu contraseña temporal · SSR A365', $html, (int)$objetivo['id']
        );
    } else {
        [$okEnv, $msgEnv] = [false, 'la persona no tiene correo real registrado'];
    }

    $msg = 'Se regeneró la contraseña correctamente.';
} catch (PDOException $e) {
    $ok  = false;
    $msg = 'No se pudo regenerar la contraseña. Intenta nuevamente.';
}

responder($ok, $msg);
