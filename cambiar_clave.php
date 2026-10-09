<?php
/* ============================================================
   SSR - A365 | Cambio de contraseña
   - Forzado: cuando el administrador regenera la clave
     (debe_cambiar_clave = 1), el usuario no puede usar el
     sistema hasta cambiarla (lo intercepta guardia.php).
   - Voluntario: cualquier usuario también puede cambiarla.
   ============================================================ */
require_once __DIR__ . '/includes/guardia.php';
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/includes/passwords.php';

$pdo      = obtenerConexion();
$forzado  = !empty($_SESSION['debe_cambiar_clave']);
$usuarioId = (int)($_SESSION['usuario_id'] ?? 0);
$error    = '';
$ok       = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nueva  = (string)($_POST['clave_nueva'] ?? '');
    $repite = (string)($_POST['clave_repite'] ?? '');

    if (($err = validarClave($nueva)) !== null) {
        $error = $err;
    } elseif ($nueva !== $repite) {
        $error = 'Las contraseñas no coinciden.';
    } else {
        try {
            $st = $pdo->prepare("SELECT clave FROM usuarios WHERE id = :id");
            $st->execute([':id' => $usuarioId]);
            $actual = (string)$st->fetchColumn();

            if ($actual !== '' && password_verify($nueva, $actual)) {
                $error = 'La nueva contraseña debe ser distinta de la actual.';
            } else {
                $pdo->prepare("UPDATE usuarios SET clave = :c, debe_cambiar_clave = 0 WHERE id = :id")
                    ->execute([':c' => hashClave($nueva), ':id' => $usuarioId]);

                // La sesión queda "limpia" con nuevo id.
                session_regenerate_id(true);
                $_SESSION['debe_cambiar_clave'] = false;
                $_SESSION['ultima_actividad']   = time();

                header('Location: dashboard.php?clave_cambiada=1');
                exit;
            }
        } catch (PDOException $e) {
            $error = 'No se pudo actualizar la contraseña. Intenta nuevamente.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SSR · Cambio de contraseña</title>
  <link rel="stylesheet" href="assets/css/login.css">
  <style>
    .tarjeta-clave{max-width:430px;margin:8vh auto;background:#fff;border-radius:16px;padding:32px;box-shadow:0 20px 60px rgba(16,28,54,.18)}
    .tarjeta-clave h1{font-size:21px;color:#101c36;margin-bottom:8px}
    .tarjeta-clave .sub{font-size:14px;color:#6b7793;line-height:1.55;margin-bottom:20px}
    .campo-clave{margin-bottom:16px;text-align:left}
    .campo-clave label{display:block;font-size:13px;font-weight:600;color:#3d4a63;margin-bottom:6px}
    .campo-clave input{width:100%;padding:12px 14px;border:1px solid #d7dfeb;border-radius:10px;font-size:15px}
    .campo-clave input:focus{outline:2px solid #2f6fed;border-color:transparent}
    .reglas{background:#f5f8fd;border:1px solid #e3eaf6;border-radius:10px;padding:12px 14px;font-size:13px;color:#3d4a63;line-height:1.7;margin-bottom:18px}
    .btn-guardar{width:100%;background:#e2091b;color:#fff;border:none;border-radius:10px;padding:13px;font-size:15px;font-weight:700;cursor:pointer}
    .btn-guardar:hover{background:#c00817}
    .aviso-forzado{background:#fff4e5;border:1px solid #f5d9a4;color:#a86a06;border-radius:10px;padding:12px 14px;font-size:13.5px;margin-bottom:18px;line-height:1.55}
    .salir{display:block;text-align:center;margin-top:14px;font-size:13px;color:#6b7793}
  </style>
</head>
<body>
  <div class="tarjeta-clave">
    <h1><?= $forzado ? 'Debes cambiar tu contraseña' : 'Cambiar contraseña' ?></h1>
    <p class="sub">
      <?= $forzado
            ? 'Un administrador regeneró tu contraseña temporal. Crea una nueva para continuar usando el sistema.'
            : 'Actualiza tu contraseña de acceso al panel.' ?>
    </p>

    <?php if ($error): ?>
      <div class="alerta alerta-error">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <div class="reglas">
      <strong>La contraseña debe:</strong>
      <br>Tener al menos <strong>6 caracteres</strong>.
      <br>Incluir <strong>una letra</strong> y <strong>un número</strong>.
      <br>Ser distinta de la actual.
    </div>

    <form method="POST">
      <div class="campo-clave">
        <label for="clave_nueva">Nueva contraseña</label>
        <input type="password" id="clave_nueva" name="clave_nueva" required minlength="6" maxlength="100" autofocus>
      </div>
      <div class="campo-clave">
        <label for="clave_repite">Repite la nueva contraseña</label>
        <input type="password" id="clave_repite" name="clave_repite" required minlength="6" maxlength="100">
      </div>
      <button class="btn-guardar" type="submit">Guardar contraseña</button>
    </form>

    <?php if (!$forzado): ?>
      <a class="salir" href="dashboard.php">Volver al panel</a>
    <?php endif; ?>
    <a class="salir" href="logout.php">Cerrar sesión</a>
  </div>
</body>
</html>
