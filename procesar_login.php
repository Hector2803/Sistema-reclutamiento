<?php
/* ============================================================
   SSR - A365 | Procesamiento del inicio de sesión
   RF-01  : login con acceso según rol.
   RNF-02 : autenticación con contraseñas protegidas (hash).
   CP-02  : mensaje genérico sin revelar qué campo falló.
   CP-09  : las cuentas inactivas no ingresan.
   CP-59  : se regenera el id de sesión al autenticarse.
   Control de fuerza bruta: bloqueo a los 3 fallos + auditoría
   en la tabla intentos_login (usuario, IP, fecha y resultado).
   ============================================================ */

require_once __DIR__ . '/includes/sesion.php';
require_once __DIR__ . '/config/conexion.php';

// Solo se aceptan envíos por POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

// Recolección y limpieza de datos.
$usuario = trim($_POST['usuario'] ?? '');
$clave   = $_POST['clave'] ?? '';
$ip      = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

/**
 * Registra el resultado de un intento en la tabla de auditoría.
 * No interrumpe el login si la tabla falla.
 */
function registrarIntento(PDO $pdo, string $usuario, string $ip, bool $exito, ?string $detalle = null): void
{
    try {
        $pdo->prepare("INSERT INTO intentos_login (usuario, ip, exito, detalle)
                       VALUES (:u, :ip, :e, :d)")
            ->execute([':u' => mb_substr($usuario, 0, 50), ':ip' => $ip, ':e' => $exito ? 1 : 0, ':d' => $detalle]);
    } catch (PDOException $e) { /* auditoría no crítica */ }
}

/** Cuenta los intentos fallidos recientes de un usuario o de una IP. */
function contarFallos(PDO $pdo, string $campo, string $valor, int $minutos): int
{
    $sql = "SELECT COUNT(*) c FROM intentos_login
             WHERE exito = 0
               AND creado_en > DATE_SUB(NOW(), INTERVAL $minutos MINUTE)
               AND $campo = :v";
    try {
        $st = $pdo->prepare($sql);
        $st->execute([':v' => $valor]);
        return (int)$st->fetch()['c'];
    } catch (PDOException $e) {
        return 0;
    }
}

function errorLogin(string $mensaje, string $usuario = ''): void
{
    $_SESSION['error'] = $mensaje;
    $_SESSION['usuario_previo'] = $usuario;
    header('Location: login.php');
    exit;
}

// Validación básica de campos vacíos.
// CP-02: se usa el mismo mensaje genérico para no revelar cuál de los dos falló.
if ($usuario === '' || $clave === '') {
    errorLogin('Usuario o contraseña incorrectos.', $usuario);
}

try {
    $pdo = obtenerConexion();

    /* ---------------- Control de fuerza bruta ---------------- */
    try {
        $pdo->exec("DELETE FROM intentos_login WHERE creado_en < DATE_SUB(NOW(), INTERVAL 1 DAY)");
    } catch (PDOException $e) { /* la tabla existe desde la migración */ }

    $fallosUsuario = contarFallos($pdo, 'usuario', mb_substr($usuario, 0, 50), 15);
    $fallosIp      = contarFallos($pdo, 'ip', $ip, 15);

    if ($fallosUsuario >= 3) {
        errorLogin('Demasiados intentos fallidos para este usuario. Espera 15 minutos y vuelve a intentarlo.', $usuario);
    }
    if ($fallosIp >= 10) {
        errorLogin('Demasiados intentos fallidos desde esta conexión. Espera 15 minutos y vuelve a intentarlo.', $usuario);
    }

    /* ---------------- Consulta del usuario ---------------- */
    // Consulta preparada (previene inyección SQL).
    $sql = "SELECT u.id, u.usuario, u.nombre, u.clave, u.estado,
                   u.rol_id, u.area_id, u.debe_cambiar_clave,
                   u.clave_2fa, u.fa2_activo,
                   r.nombre AS rol,
                   a.nombre AS area
            FROM usuarios u
            INNER JOIN roles r ON r.id = u.rol_id
            LEFT  JOIN areas a ON a.id = u.area_id
            WHERE u.usuario = :usuario
            LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':usuario' => $usuario]);
    $fila = $stmt->fetch();

    $claveOk = ($fila && password_verify($clave, $fila['clave']));

    if ($claveOk) {
        // CP-09: la cuenta debe estar activa.
        if ((int)$fila['estado'] !== 1) {
            registrarIntento($pdo, $usuario, $ip, false, 'cuenta inactiva');
            errorLogin('Tu cuenta está inactiva. Contacta al administrador.', $usuario);
        }

        // Éxito: se limpian los fallos previos y se audita el acceso.
        registrarIntento($pdo, $usuario, $ip, true, 'password correcto');
        try {
            $pdo->prepare("DELETE FROM intentos_login WHERE exito = 0 AND usuario = :u")
                ->execute([':u' => mb_substr($usuario, 0, 50)]);
        } catch (PDOException $e) { /* ignorar */ }

        // Segundo factor (TOTP) para administradores con 2FA activo.
        if ((int)$fila['fa2_activo'] === 1 && $fila['clave_2fa'] !== null) {
            $_SESSION['fa2'] = [
                'uid'      => (int)$fila['id'],
                'expira'   => time() + 300,   // 5 minutos para ingresar el código
                'intentos' => 0,
            ];
            header('Location: verificar_2fa.php');
            exit;
        }

        iniciarSesionUsuario($fila, $pdo);
        header('Location: dashboard.php');
        exit;
    }

    /* ---------------- Credenciales incorrectas ---------------- */
    registrarIntento($pdo, $usuario, $ip, false, $fila ? 'clave incorrecta' : 'usuario inexistente');
    usleep(500000);   // 0,5 s de pausa: dificulta la prueba automática

    $fallosRestantes = 3 - ($fallosUsuario + 1);
    $msg = 'Usuario o contraseña incorrectos.';
    if ($fallosRestantes <= 0) {
        $msg = 'Usuario o contraseña incorrectos. Superaste el límite de intentos: espera 15 minutos.';
    }
    errorLogin($msg, $usuario);

} catch (PDOException $e) {
    errorLogin('Ocurrió un error al validar el acceso. Intenta nuevamente.', $usuario);
}
