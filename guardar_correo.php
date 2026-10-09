<?php
/* ============================================================
   SSR - A365 | Configuración de correo SMTP (solo admin)
   acciones: guardar | probar | limpiar
   ============================================================ */

require_once __DIR__ . '/includes/guardia.php';
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/includes/correo.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: correo.php');
    exit;
}

if (($_SESSION['usuario_rol'] ?? '') !== 'Administrador') {
    $_SESSION['flash_error'] = 'No tienes permisos para configurar el correo.';
    header('Location: correo.php');
    exit;
}

$accion = $_POST['accion'] ?? 'guardar';

try {
    $pdo = obtenerConexion();

    /* ---------------------------------------------------------
       LIMPIAR HISTORIAL
       --------------------------------------------------------- */
    if ($accion === 'limpiar') {
        $pdo->exec("DELETE FROM correos_enviados");
        $_SESSION['flash_ok'] = 'Historial de correos vaciado.';
        header('Location: correo.php');
        exit;
    }

    /* ---------------------------------------------------------
       PROBAR ENVÍO
       --------------------------------------------------------- */
    if ($accion === 'probar') {
        $destino = trim($_POST['destino'] ?? '');

        if (!filter_var($destino, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['flash_error'] = 'Ingresa un correo de destino válido.';
            header('Location: correo.php');
            exit;
        }

        $html = correoPlantilla(
            'Correo de prueba',
            '<p style="font-size:13.5px;line-height:1.6;margin:0 0 10px">'
            . 'La configuración SMTP del sistema <strong>funciona correctamente</strong>.</p>'
            . '<p style="font-size:13.5px;color:#44506a;margin:0">'
            . 'Servidor: ' . htmlspecialchars(correoConfig($pdo)['host'])
            . ':' . (int)correoConfig($pdo)['puerto'] . '</p>',
            'Si ves este correo, las credenciales de los usuarios se entregarán sin problemas.'
        );

        [$ok, $msg] = correoEnviar($pdo, $destino, 'Prueba SMTP · Sistema de Reclutamiento', $html);

        $_SESSION[$ok ? 'flash_ok' : 'flash_error'] = $ok
            ? "Correo de prueba enviado a {$destino}."
            : "No se pudo enviar la prueba: {$msg}";
        header('Location: correo.php');
        exit;
    }

    /* ---------------------------------------------------------
       GUARDAR CONFIGURACIÓN
       --------------------------------------------------------- */
    $host      = trim($_POST['host'] ?? '');
    $puerto    = (int)($_POST['puerto'] ?? 0);
    $seguridad = $_POST['seguridad'] ?? 'tls';
    $usuario   = trim($_POST['usuario'] ?? '');
    $clave     = (string)($_POST['clave'] ?? '');
    $remNombre = trim($_POST['remitente_nombre'] ?? '');
    $remCorreo = trim($_POST['remitente_correo'] ?? '');
    $dominio   = strtolower(trim($_POST['dominio'] ?? ''));

    if ($host === '' || $puerto < 1 || $puerto > 65535) {
        $_SESSION['flash_error'] = 'Revisa el servidor SMTP y el puerto (entre 1 y 65535).';
    } elseif (!in_array($seguridad, ['tls', 'ssl', 'ninguna'], true)) {
        $_SESSION['flash_error'] = 'Tipo de seguridad no válido.';
    } elseif ($usuario === '') {
        $_SESSION['flash_error'] = 'El usuario SMTP es obligatorio.';
    } elseif ($remNombre === '') {
        $_SESSION['flash_error'] = 'El nombre del remitente es obligatorio.';
    } elseif (!filter_var($remCorreo, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['flash_error'] = 'El correo remitente no es válido.';
    } elseif (!preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/', $dominio)) {
        $_SESSION['flash_error'] = 'El dominio no es válido (ej. a365.com).';
    } else {
        $actual = correoConfig($pdo);
        $claveGuardada = ($clave === '') ? $actual['clave'] : $clave;

        $st = $pdo->prepare(
            "UPDATE config_correo
                SET host = :host, puerto = :puerto, seguridad = :seg,
                    usuario = :usuario, clave = :clave,
                    remitente_nombre = :rn, remitente_correo = :rc,
                    dominio = :dom
              WHERE id = 1"
        );
        $st->execute([
            ':host'   => $host,
            ':puerto' => $puerto,
            ':seg'    => $seguridad,
            ':usuario'=> $usuario,
            ':clave'  => $claveGuardada,
            ':rn'     => $remNombre,
            ':rc'     => $remCorreo,
            ':dom'    => $dominio,
        ]);

        $_SESSION['flash_ok'] = "Configuración guardada. Las cuentas se crearán como nombre@{$dominio}.";
    }

} catch (Throwable $e) {
    $_SESSION['flash_error'] = 'No se pudo guardar la configuración. Intenta nuevamente.';
}

header('Location: correo.php');
exit;
