<?php
/* ============================================================
   SSR - A365 | Segundo factor (TOTP) - CP reforzado
   Se llega aquí después de validar la contraseña cuando el
   usuario (administrador) tiene 2FA activo.
   El código se verifica con el algoritmo TOTP (RFC 6238).
   ============================================================ */
require_once __DIR__ . '/includes/sesion.php';
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/includes/totp.php';

// Si ya terminó todo el ingreso, no tiene nada que hacer aquí.
if (isset($_SESSION['usuario_id'])) {
    header('Location: dashboard.php');
    exit;
}

$fa2 = $_SESSION['fa2'] ?? null;
if (!$fa2 || (int)$fa2['uid'] <= 0) {
    header('Location: login.php');
    exit;
}
if (time() > (int)$fa2['expira']) {
    unset($_SESSION['fa2']);
    $_SESSION['error'] = 'El tiempo para ingresar el código expiró. Inicia sesión nuevamente.';
    header('Location: login.php');
    exit;
}

$error = '';
$pdo   = obtenerConexion();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codigo = trim($_POST['codigo'] ?? '');

    $st = $pdo->prepare(
        "SELECT u.id, u.usuario, u.nombre, u.estado, u.rol_id, u.area_id,
                u.debe_cambiar_clave, u.clave_2fa, u.fa2_activo,
                r.nombre AS rol, a.nombre AS area
           FROM usuarios u
           INNER JOIN roles r ON r.id = u.rol_id
           LEFT  JOIN areas a ON a.id = u.area_id
          WHERE u.id = :id LIMIT 1");
    $st->execute([':id' => (int)$fa2['uid']]);
    $usuario = $st->fetch();

    if (!$usuario || (int)$usuario['estado'] !== 1 || (int)$usuario['fa2_activo'] !== 1 || !$usuario['clave_2fa']) {
        unset($_SESSION['fa2']);
        $_SESSION['error'] = 'Usuario o contraseña incorrectos.';
        header('Location: login.php');
        exit;
    }

    if (totpValido($usuario['clave_2fa'], $codigo)) {
        // Código correcto: se completa el inicio de sesión.
        iniciarSesionUsuario($usuario, $pdo);
        header('Location: dashboard.php');
        exit;
    }

    // Código incorrecto: se cuenta el intento (máximo 5 en 5 minutos).
    $_SESSION['fa2']['intentos'] = (int)($fa2['intentos'] ?? 0) + 1;
    if ($_SESSION['fa2']['intentos'] >= 5) {
        unset($_SESSION['fa2']);
        $_SESSION['error'] = 'Demasiados códigos incorrectos. Vuelve a iniciar sesión.';
        header('Location: login.php');
        exit;
    }
    $error = 'Código incorrecto. Revisa la app de autenticación e inténtalo otra vez.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SSR · Verificación en dos pasos</title>
  <link rel="stylesheet" href="assets/css/login.css">
  <style>
    .tarjeta-2fa{max-width:420px;margin:9vh auto;background:#fff;border-radius:16px;padding:34px 32px;box-shadow:0 20px 60px rgba(16,28,54,.18);text-align:center}
    .tarjeta-2fa h1{font-size:21px;color:#101c36;margin-bottom:8px}
    .tarjeta-2fa p{font-size:14px;color:#6b7793;line-height:1.55;margin-bottom:20px}
    .codigo-2fa{width:100%;max-width:230px;letter-spacing:12px;font-size:26px;text-align:center;padding:12px 10px 12px 22px;border:1px solid #d7dfeb;border-radius:10px;text-transform:numeric}
    .codigo-2fa:focus{outline:2px solid #2f6fed;border-color:transparent}
    .btn-2fa{margin-top:18px;width:100%;max-width:230px;background:#e2091b;color:#fff;border:none;border-radius:10px;padding:13px;font-size:15px;font-weight:700;cursor:pointer}
    .btn-2fa:hover{background:#c00817}
    .volver-2fa{display:inline-block;margin-top:16px;font-size:13px;color:#6b7793;text-decoration:underline}
    .ico-2fa{width:52px;height:52px;border-radius:50%;background:#eaf1fd;color:#2f6fed;display:inline-flex;align-items:center;justify-content:center;margin-bottom:14px}
    .ico-2fa svg{width:26px;height:26px}
  </style>
</head>
<body>
  <div class="tarjeta-2fa">
    <span class="ico-2fa">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
    </span>
    <h1>Verificación en dos pasos</h1>
    <p>Ingresa el código de 6 dígitos que muestra tu aplicación de autenticación (Google Authenticator, Authy, 1Password, Microsoft Authenticator).</p>

    <?php if ($error): ?>
      <div class="alerta alerta-error" style="text-align:left">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <form method="POST" autocomplete="one-time-code">
      <input class="codigo-2fa" type="text" name="codigo" inputmode="numeric" pattern="[0-9]{6}"
             maxlength="6" placeholder="000000" required autofocus title="Código de 6 dígitos">
      <div><button class="btn-2fa" type="submit">Verificar</button></div>
    </form>

    <a class="volver-2fa" href="logout.php">Volver al inicio de sesión</a>
  </div>
</body>
</html>
