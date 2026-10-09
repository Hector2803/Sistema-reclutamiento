<?php
/* ============================================================
   SSR - A365 | Segundo factor (TOTP) - solo administradores
   El administrador activa/desactiva el 2FA de SU cuenta.
   Una vez activo, procesar_login.php exige el código TOTP.
   ============================================================ */
$tituloPagina = 'Seguridad · Dos factores';
$activo       = '';
$estilosExtra = [];

require_once __DIR__ . '/includes/guardia.php';
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/includes/passwords.php';
require_once __DIR__ . '/includes/totp.php';

if (($_SESSION['usuario_rol'] ?? '') !== 'Administrador') {
    $_SESSION['flash_error'] = 'Esta configuración está disponible solo para administradores.';
    header('Location: dashboard.php');
    exit;
}

$flashOk    = $_SESSION['flash_ok']    ?? '';
$flashError = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_ok'], $_SESSION['flash_error']);

$pdo      = obtenerConexion();
$usuarioId = (int)($_SESSION['usuario_id'] ?? 0);
$error    = '';

/* ---------------- Acciones ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $codigo = trim($_POST['codigo'] ?? '');

    try {
        if ($accion === 'generar') {
            $secreto = totpClaveNueva();
            $pdo->prepare("UPDATE usuarios SET clave_2fa = :s, fa2_activo = 0 WHERE id = :id")
                ->execute([':s' => $secreto, ':id' => $usuarioId]);
            $_SESSION['flash_ok'] = 'Secreto generado. Escanea el código (o ingresa la clave) en tu app y confirma con el código de 6 dígitos.';

        } elseif ($accion === 'confirmar') {
            $st = $pdo->prepare("SELECT clave_2fa FROM usuarios WHERE id = :id");
            $st->execute([':id' => $usuarioId]);
            $secreto = (string)$st->fetchColumn();

            if ($secreto === '') {
                $error = 'Primero genera el secreto con el botón "Generar secreto".';
            } elseif (!totpValido($secreto, $codigo)) {
                $error = 'El código no es correcto. Revisa el reloj de tu app e inténtalo otra vez.';
            } else {
                $pdo->prepare("UPDATE usuarios SET fa2_activo = 1 WHERE id = :id")->execute([':id' => $usuarioId]);
                $_SESSION['flash_ok'] = 'Segundo factor ACTIVADO. A partir de ahora el login pedirá el código de la app.';
            }

        } elseif ($accion === 'desactivar') {
            $st = $pdo->prepare("SELECT clave_2fa FROM usuarios WHERE id = :id");
            $st->execute([':id' => $usuarioId]);
            $secreto = (string)$st->fetchColumn();

            if ($secreto === '') {
                $error = 'El segundo factor no está activo.';
            } elseif (!totpValido($secreto, $codigo)) {
                $error = 'Para desactivarlo debes ingresar un código válido de tu app.';
            } else {
                $pdo->prepare("UPDATE usuarios SET fa2_activo = 0, clave_2fa = NULL WHERE id = :id")
                    ->execute([':id' => $usuarioId]);
                $_SESSION['flash_ok'] = 'Segundo factor desactivado.';
            }
        }
    } catch (PDOException $e) {
        $error = 'No se pudo completar la operación. Intenta nuevamente.';
    }

    if ($error === '') {
        header('Location: dos_factores.php');
        exit;
    }
}

/* ---------------- Estado actual ---------------- */
$st = $pdo->prepare("SELECT usuario, nombre, clave_2fa, fa2_activo FROM usuarios WHERE id = :id");
$st->execute([':id' => $usuarioId]);
$yo = $st->fetch();
$activo2fa = $yo && (int)$yo['fa2_activo'] === 1;
$secreto   = $yo['clave_2fa'] ?? '';
$otpauth   = $secreto !== '' ? totpURL($secreto, $yo['usuario']) : '';

require_once __DIR__ . '/includes/layout_top.php';
?>

<div class="panel-encabezado">
  <div>
    <h2>Seguridad · Verificación en dos pasos (2FA)</h2>
    <p>Protege tu cuenta de administrador con un código temporal de tu celular, además de la contraseña.</p>
  </div>
</div>

<?php if ($flashOk): ?><div class="aviso aviso-ok"><?= htmlspecialchars($flashOk) ?></div><?php endif; ?>
<?php if ($flashError): ?><div class="aviso aviso-error"><?= htmlspecialchars($flashError) ?></div><?php endif; ?>
<?php if ($error): ?><div class="aviso aviso-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div style="display:grid;grid-template-columns:minmax(0,1fr) minmax(0,340px);gap:20px;align-items:start">

  <!-- Estado -->
  <section class="tarjeta" style="padding:24px">
    <h3 style="margin-bottom:14px">Estado de tu cuenta</h3>
    <div style="display:flex;gap:14px;align-items:center;flex-wrap:wrap;margin-bottom:16px">
      <span class="chip <?= $activo2fa ? 'es-activo' : 'es-inactivo' ?>"><?= $activo2fa ? '2FA activo' : '2FA inactivo' ?></span>
      <span style="color:var(--texto-2);font-size:14px">
        <?= htmlspecialchars($yo['nombre'] ?? '') ?> (<?= htmlspecialchars($yo['usuario'] ?? '') ?>)
      </span>
    </div>

    <p style="font-size:14.5px;color:var(--texto-2);line-height:1.65;margin-bottom:18px">
      <?php if ($activo2fa): ?>
        Tu cuenta está protegida: al iniciar sesión, además de la contraseña, deberás ingresar el
        código de 6 dígitos que muestra tu aplicación de autenticación.
      <?php elseif ($secreto !== ''): ?>
        Ya generaste el secreto, pero <strong>aún no se ha confirmado</strong>. Ingresa un código de la
        app en el formulario para activarlo.
      <?php else: ?>
        Aún no está activo. Al activarlo, el login pedirá un código de 6 dígitos de tu app
        <strong>después</strong> de la contraseña.
      <?php endif; ?>
      <br><br>
      <strong>Apps compatibles:</strong> Google Authenticator, Authy, Microsoft Authenticator, 1Password, Aegis.
    </p>

    <?php if ($secreto !== ''): ?>
      <div style="background:#f5f8fd;border:1px solid #e3eaf6;border-radius:10px;padding:16px 18px">
        <strong style="font-size:13.5px;display:block;margin-bottom:8px">Clave secreta (ingrésala manualmente en la app):</strong>
        <code style="font-size:17px;letter-spacing:3px;word-break:break-all;display:block;margin-bottom:12px"><?= htmlspecialchars($secreto) ?></code>
        <strong style="font-size:13.5px;display:block;margin-bottom:6px">O copia este enlace en la app:</strong>
        <code style="font-size:12.5px;word-break:break-all;display:block;color:#2f6fed"><?= htmlspecialchars($otpauth) ?></code>
        <p style="font-size:12.5px;color:#6b7793;margin-top:10px">
          Mantén este secreto en privado: quien lo tenga podrá generar tus códigos.
        </p>
      </div>
    <?php endif; ?>

    <?php if ($secreto === ''): ?>
      <form method="POST" style="margin-top:18px">
        <input type="hidden" name="accion" value="generar">
        <button class="btn btn-azul" type="submit">Generar secreto</button>
      </form>
    <?php endif; ?>
  </section>

  <!-- Formularios -->
  <section class="tarjeta" style="padding:24px">
    <?php if ($secreto !== '' && !$activo2fa): ?>
      <h3 style="margin-bottom:6px">Confirmar activación</h3>
      <p style="font-size:13.5px;color:var(--texto-2);margin-bottom:16px">Ingresa el código que muestra tu app ahora mismo.</p>
      <form method="POST">
        <input type="hidden" name="accion" value="confirmar">
        <div style="margin-bottom:14px">
          <label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px">Código de 6 dígitos</label>
          <input type="text" name="codigo" inputmode="numeric" pattern="[0-9]{6}" maxlength="6"
                 required autofocus placeholder="000000"
                 style="width:100%;padding:11px 13px;border:1px solid var(--borde,#d7dfeb);border-radius:9px;font-size:18px;letter-spacing:6px;text-align:center">
        </div>
        <button class="btn btn-azul" type="submit">Activar 2FA</button>
      </form>
    <?php elseif ($activo2fa): ?>
      <h3 style="margin-bottom:6px">Desactivar 2FA</h3>
      <p style="font-size:13.5px;color:var(--texto-2);margin-bottom:16px">
        Se requiere un código válido de tu app para desactivarlo (evita que alguien con acceso al panel lo apague).
      </p>
      <form method="POST">
        <input type="hidden" name="accion" value="desactivar">
        <div style="margin-bottom:14px">
          <label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px">Código de 6 dígitos</label>
          <input type="text" name="codigo" inputmode="numeric" pattern="[0-9]{6}" maxlength="6"
                 required autofocus placeholder="000000"
                 style="width:100%;padding:11px 13px;border:1px solid var(--borde,#d7dfeb);border-radius:9px;font-size:18px;letter-spacing:6px;text-align:center">
        </div>
        <button class="btn btn-linea" type="submit">Desactivar 2FA</button>
      </form>
    <?php else: ?>
      <h3 style="margin-bottom:6px">¿Cómo funciona?</h3>
      <ol style="font-size:14px;color:var(--texto-2);line-height:1.9;padding-left:18px">
        <li>Pulsa <strong>Generar secreto</strong>.</li>
        <li>Agrega el secreto a tu app (escaneando o pegando la clave).</li>
        <li>Confirma con el código de 6 dígitos.</li>
        <li>Al iniciar sesión te pedirá ese código además de la contraseña.</li>
      </ol>
      <p style="font-size:13px;color:var(--texto-3);margin-top:12px">
        Si pierdes el celular, un administrador puede regenerarte la contraseña desde
        <a href="usuarios.php">Administración → Usuarios</a>.
      </p>
    <?php endif; ?>
  </section>
</div>

<?php require_once __DIR__ . '/includes/layout_bottom.php'; ?>
