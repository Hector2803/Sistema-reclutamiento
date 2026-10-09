<?php
/* ============================================================
   Bootstrap de sesión (CP-59 y endurecimiento de cookies)
   - strict_mode: rechaza IDs de sesión fijados por el cliente.
   - Cookie HttpOnly + SameSite=Lax (el JS no puede leerla).
   - Timeout de inactividad: 30 minutos.
   - Registro de sesiones activas (panel de administración).
   Todas las páginas (vía guardia.php) pasan por aquí.
   ============================================================ */

require_once __DIR__ . '/../config/conexion.php';

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    $esSeguro = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $esSeguro,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    /* --- Timeout por inactividad (30 min) --- */
    $ttlSesion = 30 * 60;
    if (isset($_SESSION['usuario_id'])) {
        $ultimaActividad = (int)($_SESSION['ultima_actividad'] ?? time());
        if ((time() - $ultimaActividad) > $ttlSesion) {
            sesionRegistrarSalida();
            $_SESSION = [];
            session_destroy();
            setcookie(session_name(), '', time() - 42000, '/');
            header('Location: login.php?expirada=1');
            exit;
        }
        $_SESSION['ultima_actividad'] = time();

        /* --- Sesiones activas + cierre a distancia --- */
        sesionRegistrarActividad();
    }
}

/**
 * Marca la sesión como cerrada y destruye el estado local.
 * Se usa en el logout normal y cuando el administrador
 * cierra la sesión de otro usuario desde el panel.
 */
function sesionRegistrarSalida(): void
{
    try {
        $pdo = obtenerConexion();
        $pdo->prepare("DELETE FROM sesiones_activas WHERE sesion = :s")
            ->execute([':s' => session_id()]);
    } catch (Throwable $e) { /* no crítico */ }
}

/**
 * Guarda/actualiza el registro de la sesión actual y comprueba si el
 * administrador la cerró a distancia (en ese caso se cierra aquí).
 * Se ignora cualquier error: la funcionalidad no debe romper la página.
 */
function sesionRegistrarActividad(): void
{
    try {
        $pdo  = obtenerConexion();
        $sid  = session_id();
        $ip   = $_SERVER['REMOTE_ADDR'] ?? '';
        $uid  = (int)($_SESSION['usuario_id'] ?? 0);
        if ($uid <= 0 || $sid === '') return;

        $st = $pdo->prepare("SELECT cerrada FROM sesiones_activas WHERE sesion = :s");
        $st->execute([':s' => $sid]);
        $reg = $st->fetch();

        if ($reg && (int)$reg['cerrada'] === 1) {
            // Cerrada desde el panel: se elimina el registro y la sesión.
            $pdo->prepare("DELETE FROM sesiones_activas WHERE sesion = :s")
                ->execute([':s' => $sid]);
            $_SESSION = [];
            session_destroy();
            setcookie(session_name(), '', time() - 42000, '/');
            header('Location: login.php?cerrada=1');
            exit;
        }

        if ($reg) {
            $pdo->prepare(
                "UPDATE sesiones_activas
                    SET ultima_actividad = NOW(), ip = :ip, cerrada = 0
                  WHERE sesion = :s"
            )->execute([':ip' => $ip, ':s' => $sid]);
        } else {
            $pdo->prepare(
                "INSERT INTO sesiones_activas (sesion, usuario_id, ip, creada_en, ultima_actividad, cerrada)
                 VALUES (:s, :u, :ip, NOW(), NOW(), 0)"
            )->execute([':s' => $sid, ':u' => $uid, ':ip' => $ip]);
        }

        // Limpieza ocasional de sesiones viejas (1 de cada 50 visitas).
        if (random_int(1, 50) === 1) {
            $pdo->exec("DELETE FROM sesiones_activas WHERE ultima_actividad < NOW() - INTERVAL 1 DAY");
        }
    } catch (Throwable $e) { /* no crítico */ }
}

/**
 * Completa el inicio de sesión de un usuario ya validado.
 * - CP-59: regenera el id de sesión (evita fijación).
 * - CP-10: nunca se guarda la contraseña, solo datos del perfil.
 * - Actualiza el registro de acceso (ultimo_acceso).
 */
function iniciarSesionUsuario(array $u, PDO $pdo): void
{
    session_regenerate_id(true);

    $_SESSION['usuario_id']        = (int)$u['id'];
    $_SESSION['usuario_nombre']    = $u['nombre'];
    $_SESSION['usuario_rol']       = $u['rol'];
    $_SESSION['usuario_rol_id']    = (int)$u['rol_id'];
    $_SESSION['usuario_area']      = $u['area'] ?? null;
    $_SESSION['usuario_area_id']   = $u['area_id'] ?? null;
    $_SESSION['debe_cambiar_clave']= !empty($u['debe_cambiar_clave']);
    $_SESSION['ultima_actividad']  = time();

    unset($_SESSION['fa2']);   // ya no hace falta el paso del segundo factor

    try {
        $pdo->prepare("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = :id")
            ->execute([':id' => $u['id']]);

        $pdo->prepare("DELETE FROM sesiones_activas WHERE sesion = :s")
            ->execute([':s' => session_id()]);
        $pdo->prepare(
            "INSERT INTO sesiones_activas (sesion, usuario_id, ip, creada_en, ultima_actividad, cerrada)
             VALUES (:s, :u, :ip, NOW(), NOW(), 0)"
        )->execute([
            ':s'  => session_id(),
            ':u'  => (int)$u['id'],
            ':ip' => $_SERVER['REMOTE_ADDR'] ?? '',
        ]);
    } catch (PDOException $e) { /* no crítico */ }
}
