<?php
/* ============================================================
   SSR - A365 | Gestión de usuarios (RF-09)
   acciones: crear | editar | estado | eliminar
   ============================================================ */

require_once __DIR__ . '/includes/guardia.php';
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/includes/passwords.php';
require_once __DIR__ . '/includes/correo.php';

/** Valida el nombre de la cuenta (sin dominio). */
function validarNombreCuenta(string $cuenta): ?string
{
    if ($cuenta === '') { return 'Escribe el nombre de la cuenta.'; }
    if (!preg_match('/^[A-Za-z0-9]([A-Za-z0-9._-]{0,62}[A-Za-z0-9])?$/', $cuenta)) {
        return 'El nombre de la cuenta solo puede tener letras, números, punto, guion y guion bajo (ej. h.crisostomo).';
    }
    return null;
}

/**
 * Responde la acción:
 *  - petición AJAX (header X-RequestedWith) -> JSON {ok, mensaje}
 *  - POST normal                            -> flash + redirección
 */
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

// Solo por POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: usuarios.php');
    exit;
}

// Solo un Administrador gestiona usuarios
if (($_SESSION['usuario_rol'] ?? '') !== 'Administrador') {
    responder(false, 'No tienes permisos para gestionar usuarios.');
}

$accion    = $_POST['accion'] ?? 'crear';
$usuarioId = (int)($_POST['usuario_id'] ?? 0);
$yoId      = (int)($_SESSION['usuario_id'] ?? 0);

try {
    $pdo = obtenerConexion();

    /* --------------------------------------------------------
       DESACTIVAR / ACTIVAR
       -------------------------------------------------------- */
    if ($accion === 'estado') {
        $estado = (int)($_POST['estado'] ?? 0) === 1 ? 1 : 0;
        $ok     = false;
        $msg    = 'Usuario no encontrado.';

        if ($usuarioId > 0 && $usuarioId === $yoId) {
            $msg = 'No puedes desactivar tu propia cuenta.';
        } elseif ($usuarioId > 0) {
            $st = $pdo->prepare("SELECT usuario FROM usuarios WHERE id = :id");
            $st->execute([':id' => $usuarioId]);
            $u = $st->fetch();

            if (!$u) {
                $msg = 'Usuario no encontrado.';
            } else {
                $pdo->prepare("UPDATE usuarios SET estado = :e WHERE id = :id")
                    ->execute([':e' => $estado, ':id' => $usuarioId]);
                $ok  = true;
                $msg = $estado
                    ? "Usuario \"{$u['usuario']}\" activado."
                    : "Usuario \"{$u['usuario']}\" desactivado.";
            }
        }
        responder($ok, $msg);
    }

    /* --------------------------------------------------------
       ELIMINAR
       -------------------------------------------------------- */
    if ($accion === 'eliminar') {
        $ok  = false;
        $msg = 'Usuario no encontrado.';

        if ($usuarioId > 0 && $usuarioId === $yoId) {
            $msg = 'No puedes eliminar tu propia cuenta.';
        } elseif ($usuarioId > 0) {
            $st = $pdo->prepare("SELECT usuario FROM usuarios WHERE id = :id");
            $st->execute([':id' => $usuarioId]);
            $u = $st->fetch();

            if (!$u) {
                $msg = 'Usuario no encontrado.';
            } else {
                try {
                    $pdo->prepare("DELETE FROM usuarios WHERE id = :id")
                        ->execute([':id' => $usuarioId]);
                    $ok  = true;
                    $msg = 'Usuario eliminado correctamente.';
                } catch (PDOException $e) {
                    $msg =
                        "No se pudo eliminar \"{$u['usuario']}\" porque tiene registros en el sistema (entrevistas, postulaciones o respaldos). Desáctivalo en su lugar.";
                }
            }
        }
        responder($ok, $msg);
    }

    /* --------------------------------------------------------
       DATOS DEL FORMULARIO (crear / editar)
       -------------------------------------------------------- */
    $nombre  = trim($_POST['nombre']  ?? '');
    $cuenta  = trim($_POST['cuenta']  ?? '');
    $correo  = trim($_POST['correo'] ?? '');
    $clave   = $_POST['clave'] ?? '';
    $rolId   = (int)($_POST['rol_id'] ?? 0);
    $areaId  = ($_POST['area_id'] ?? '') !== '' ? (int)$_POST['area_id'] : null;
    $estado  = (int)($_POST['estado'] ?? 1) === 1 ? 1 : 0;

    $dominio = correoDominio($pdo);

    if ($nombre === '' || $cuenta === '' || $rolId === 0) {
        responder(false, 'Completa todos los campos obligatorios.');
    }

    if (($errCuenta = validarNombreCuenta($cuenta)) !== null) {
        responder(false, $errCuenta);
    }

    if ($correo !== '' && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        responder(false, 'El correo real no es válido.');
    }

    // CP-08: mínimo 6 caracteres, con letra y número (si se envía contraseña)
    if ($clave !== '' && ($errClave = validarClave($clave)) !== null) {
        responder(false, $errClave);
    }

    /* --------------------------------------------------------
       EDITAR
       -------------------------------------------------------- */
    if ($accion === 'editar') {
        if ($usuarioId <= 0) {
            responder(false, 'Usuario no encontrado.');
        }

        $stAct = $pdo->prepare("SELECT usuario FROM usuarios WHERE id = :id");
        $stAct->execute([':id' => $usuarioId]);
        $actual = $stAct->fetch();

        if (!$actual) {
            responder(false, 'Usuario no encontrado.');
        }

        // Las cuentas con correo conservan el dominio configurado;
        // las creadas antes de la política se dejan como están.
        $usuario = str_contains((string)$actual['usuario'], '@')
            ? strtolower($cuenta . '@' . $dominio)
            : (string)$actual['usuario'];

        // No puede existir otra cuenta con el mismo nombre,
        // con dominio (@a365.com) o heredada (sin dominio).
        $ck = $pdo->prepare(
            "SELECT id FROM usuarios
              WHERE (usuario = :u OR SUBSTRING_INDEX(usuario, '@', 1) = :cuenta)
                AND id <> :id
              LIMIT 1"
        );
        $ck->execute([':u' => $usuario, ':cuenta' => strtolower($cuenta), ':id' => $usuarioId]);
        if ($ck->fetch()) {
            responder(false, 'Ese nombre de cuenta ya está en uso (con o sin dominio).');
        }

        $sql = "UPDATE usuarios
                   SET nombre = :nombre, usuario = :usuario, correo = :correo,
                       rol_id = :rol, area_id = :area, estado = :est";
        $params = [
            ':nombre'  => $nombre,
            ':usuario' => $usuario,
            ':correo'  => $correo !== '' ? $correo : null,
            ':rol'     => $rolId,
            ':area'    => $areaId,
            ':est'     => $estado,
        ];
        if ($clave !== '') {
            $sql .= ", clave = :clave";
            $params[':clave'] = hashClave($clave);
        }
        $sql .= " WHERE id = :id";
        $params[':id'] = $usuarioId;

        $pdo->prepare($sql)->execute($params);
        responder(true, "Usuario \"{$usuario}\" actualizado.");
    }

    /* --------------------------------------------------------
       CREAR (sin campo de contraseña: se genera una temporal
       y se envía al correo personal de la persona)
       -------------------------------------------------------- */
    if ($correo === '') {
        responder(false, 'El correo personal es obligatorio: a esa dirección se envían las credenciales.');
    }

    $clave = generarClaveTemporal();

    // La cuenta de la empresa se arma con el dominio configurado
    $usuario = strtolower($cuenta . '@' . $dominio);

    // No puede existir otra cuenta con el mismo nombre,
    // con dominio (@a365.com) o heredada (sin dominio).
    $ck = $pdo->prepare(
        "SELECT id FROM usuarios
          WHERE usuario = :u OR SUBSTRING_INDEX(usuario, '@', 1) = :cuenta
          LIMIT 1"
    );
    $ck->execute([':u' => $usuario, ':cuenta' => strtolower($cuenta)]);
    if ($ck->fetch()) {
        responder(false, 'Esa cuenta ya está en uso (con o sin dominio).');
    }

    $sql = "INSERT INTO usuarios (rol_id, area_id, usuario, nombre, correo, clave, estado, debe_cambiar_clave)
            VALUES (:rol, :area, :usuario, :nombre, :correo, :clave, :estado, 1)";
    $pdo->prepare($sql)->execute([
        ':rol'     => $rolId,
        ':area'    => $areaId,
        ':usuario' => $usuario,
        ':nombre'  => $nombre,
        ':correo'  => $correo,
        ':clave'   => hashClave($clave),
        ':estado'  => $estado,
    ]);
    $nuevoId = (int)$pdo->lastInsertId();

    // Envío de credenciales al correo personal de la persona
    $html = correoCredenciales($nombre, $usuario, $clave, true);
    [$okEnv, $msgEnv] = correoEnviar($pdo, $correo, 'Tus credenciales de acceso · SSR A365', $html, $nuevoId);

    responder(true, 'Usuario creado correctamente.');

} catch (PDOException $e) {
    responder(false, 'No se pudo guardar el usuario. Intenta nuevamente.');
}
