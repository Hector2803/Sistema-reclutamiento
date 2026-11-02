<?php
/* ============================================================
   SSR - A365 | Desbloquear a un usuario (panel de administración)
   Un usuario queda bloqueado 15 minutos al tercer intento fallido.
   El Administrador puede liberarlo antes: los fallos recientes se
   marcan como «liberados» (ya no cuentan para el bloqueo, pero se
   conservan en la bitácora) y se registra quién lo desbloqueó.
   ============================================================ */

require_once __DIR__ . '/includes/guardia.php';
require_once __DIR__ . '/config/conexion.php';

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
    responder(false, 'No tienes permisos para desbloquear usuarios.');
}

$usuarioId = (int)($_POST['usuario_id'] ?? 0);

try {
    $pdo = obtenerConexion();

    $st = $pdo->prepare("SELECT usuario FROM usuarios WHERE id = :id LIMIT 1");
    $st->execute([':id' => $usuarioId]);
    $fila = $st->fetch();

    if (!$fila) {
        responder(false, 'Usuario no válido.');
    }

    $nombre = mb_substr($fila['usuario'], 0, 50);

    // Los fallos de los últimos 15 minutos dejan de contar (exito = 3: fallo liberado).
    $up = $pdo->prepare(
        "UPDATE intentos_login SET exito = 3
          WHERE exito = 0 AND usuario = :u
            AND creado_en > DATE_SUB(NOW(), INTERVAL 15 MINUTE)"
    );
    $up->execute([':u' => $nombre]);

    if ($up->rowCount() > 0) {
        $pdo->prepare(
            "INSERT INTO intentos_login (usuario, ip, exito, detalle)
             VALUES (:u, :ip, 2, :d)"
        )->execute([
            ':u'  => $nombre,
            ':ip' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            ':d'  => mb_substr('Desbloqueado por el administrador ' . ($_SESSION['usuario_nombre'] ?? ''), 0, 120),
        ]);
        responder(true, 'Usuario desbloqueado. Ya puede iniciar sesión.');
    } else {
        responder(true, 'El usuario no estaba bloqueado.');
    }
} catch (PDOException $e) {
    responder(false, 'No se pudo desbloquear al usuario. Intenta nuevamente.');
}

header('Location: usuarios.php');
exit;
