<?php
$tituloPagina = 'Administración · Correo';
$activo = 'administracion';
$estilosExtra = ['assets/css/candidatos.css', 'assets/css/usuarios.css'];

require_once __DIR__ . '/includes/guardia.php';
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/includes/correo.php';

// Solo Administrador
if (($_SESSION['usuario_rol'] ?? '') !== 'Administrador') {
    header('Location: dashboard.php'); exit;
}
$pdo = obtenerConexion();

$flashOk    = $_SESSION['flash_ok']    ?? '';
$flashError = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_ok'], $_SESSION['flash_error']);

$cfg        = correoConfig($pdo);
$listo      = correoListo($cfg);
$historial  = correoHistorial($pdo, 50);
$dominio    = correoDominio($pdo);

$eEnviados = 0; $eError = 0; $ePend = 0;
foreach ($historial as $h) {
    if ($h['estado'] === 'enviado') $eEnviados++;
    elseif ($h['estado'] === 'error') $eError++;
    else $ePend++;
}

function chipCorreo(string $e): string {
    if ($e === 'enviado') return 'es-activo';
    if ($e === 'error') return 'es-inactivo';
    return 'cl-pendiente';
}
function textoCorreo(string $e): string {
    if ($e === 'enviado') return 'Enviado';
    if ($e === 'error') return 'Error';
    return 'Sin configurar';
}

require_once __DIR__ . '/includes/layout_top.php';
?>

<div class="panel-encabezado">
  <div>
    <h2>Administración</h2>
    <p>Configuración del correo de la empresa y envío de credenciales.</p>
  </div>
  <div class="acciones-panel">
    <span class="version-chip">Estado: <strong><?= $listo ? 'Configurado' : 'Pendiente' ?></strong></span>
  </div>
</div>

<div class="subtabs">
  <a href="usuarios.php">Usuarios</a>
  <a href="correo.php" class="on">Correo</a>
  <a href="mantenimiento.php">Mantenimiento</a>
</div>

<?php if ($flashOk): ?><script>mostrarToast(<?= json_encode($flashOk, JSON_UNESCAPED_UNICODE) ?>, 'ok');</script><?php endif; ?>
<?php if ($flashError): ?><script>mostrarToast(<?= json_encode($flashError, JSON_UNESCAPED_UNICODE) ?>, 'error');</script><?php endif; ?>

<?php if (!$listo): ?>
  <div class="aviso aviso-error">
    El correo todavía no puede enviarse: completa el <strong>usuario SMTP</strong>, la
    <strong>contraseña de aplicación</strong> y el <strong>correo remitente</strong>.
    Mientras tanto, los correos quedarán registrados con el estado «Sin configurar».
  </div>
<?php endif; ?>

<!-- ===== KPIs ===== -->
<div class="grid-kpis-cand">
  <div class="kpi-cand"><span class="ic azul"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg></span>
    <div class="txt"><small>Estado SMTP</small><div class="num"><?= $listo ? 'Listo' : 'Pendiente' ?></div><div class="pie">Servidor <?= htmlspecialchars($cfg['host']) ?>:<?= (int)$cfg['puerto'] ?></div></div>
  </div>
  <div class="kpi-cand"><span class="ic verde"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span>
    <div class="txt"><small>Correos enviados</small><div class="num"><?= $eEnviados ?></div><div class="pie">Histórico reciente</div></div>
  </div>
  <div class="kpi-cand"><span class="ic rojo"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg></span>
    <div class="txt"><small>Con error</small><div class="num"><?= $eError ?></div><div class="pie">Revisar detalle</div></div>
  </div>
  <div class="kpi-cand"><span class="ic ambar"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></span>
    <div class="txt"><small>Sin configurar</small><div class="num"><?= $ePend ?></div><div class="pie">Pendientes de envío</div></div>
  </div>
</div>

<!-- ===== Configuración SMTP ===== -->
<section class="tarjeta" style="margin-bottom:16px">
  <h3 style="margin-bottom:4px">Configuración SMTP</h3>
  <p style="font-size:13px;color:var(--texto-2);margin:0 0 16px">
    Con estos datos el sistema envía las credenciales cuando creas un usuario y cuando regeneras su contraseña.
    En Gmail usa una <strong>contraseña de aplicación</strong> (Google Cuenta → Seguridad → Contraseñas de aplicaciones).
  </p>

  <form action="guardar_correo.php" method="POST" class="correo-grid">
    <input type="hidden" name="accion" value="guardar">
    <div class="mf-campo">
      <label>Servidor SMTP (host) *</label>
      <input type="text" name="host" required maxlength="120" value="<?= htmlspecialchars($cfg['host']) ?>" placeholder="smtp.gmail.com">
    </div>
    <div class="mf-campo">
      <label>Puerto *</label>
      <input type="number" name="puerto" required min="1" max="65535" value="<?= (int)$cfg['puerto'] ?>">
    </div>
    <div class="mf-campo">
      <label>Seguridad</label>
      <select name="seguridad">
        <option value="tls" <?= $cfg['seguridad'] === 'tls' ? 'selected' : '' ?>>TLS (STARTTLS) · 587</option>
        <option value="ssl" <?= $cfg['seguridad'] === 'ssl' ? 'selected' : '' ?>>SSL (HTTPS) · 465</option>
        <option value="ninguna" <?= $cfg['seguridad'] === 'ninguna' ? 'selected' : '' ?>>Sin cifrado</option>
      </select>
    </div>
    <div class="mf-campo">
      <label>Usuario SMTP *</label>
      <input type="text" name="usuario" required maxlength="190" value="<?= htmlspecialchars($cfg['usuario'] ?? '') ?>" placeholder="tu.cuenta@gmail.com" autocomplete="off">
    </div>
    <div class="mf-campo">
      <label>Contraseña de aplicación</label>
      <input type="password" name="clave" maxlength="255" value="" placeholder="<?= $cfg['clave'] !== '' && $cfg['clave'] !== null ? '•••••••• (conservar la actual)' : 'Ej. abcd efgh ijkl mnop' ?>" autocomplete="new-password">
      <span class="mf-ayuda">Déjala vacía para conservar la guardada.</span>
    </div>
    <div class="mf-campo">
      <label>Nombre del remitente *</label>
      <input type="text" name="remitente_nombre" required maxlength="120" value="<?= htmlspecialchars($cfg['remitente_nombre']) ?>" placeholder="A365 Reclutamiento">
    </div>
    <div class="mf-campo">
      <label>Correo remitente *</label>
      <input type="email" name="remitente_correo" required maxlength="190" value="<?= htmlspecialchars($cfg['remitente_correo'] ?? '') ?>" placeholder="tu.cuenta@gmail.com">
      <span class="mf-ayuda">Debe coincidir con la cuenta SMTP (en Gmail, la misma dirección).</span>
    </div>
    <div class="mf-campo">
      <label>Dominio de la empresa *</label>
      <input type="text" name="dominio" required maxlength="120" value="<?= htmlspecialchars($cfg['dominio']) ?>" placeholder="a365.com">
      <span class="mf-ayuda">El sistema arma cada cuenta: nombre escrito + @ + dominio (ej. h.crisostomo@<?= htmlspecialchars($cfg['dominio']) ?>).</span>
    </div>

    <div class="correo-acciones">
      <button type="submit" class="btn btn-azul">Guardar configuración</button>
    </div>
  </form>

  <form action="guardar_correo.php" method="POST" style="margin-top:12px">
    <input type="hidden" name="accion" value="limpiar">
    <button type="submit" class="btn-linea" onclick="return confirm('Se eliminarán todos los correos del historial. ¿Continuar?')">Limpiar historial</button>
  </form>
</section>

<!-- ===== Probar envío ===== -->
<section class="tarjeta" style="margin-bottom:16px">
  <h3 style="margin-bottom:4px">Probar envío</h3>
  <p style="font-size:13px;color:var(--texto-2);margin:0 0 14px">
    Envía un correo de prueba para verificar que la configuración funciona antes de crear usuarios.
  </p>
  <form action="guardar_correo.php" method="POST" class="correo-test">
    <input type="hidden" name="accion" value="probar">
    <div class="mf-campo" style="margin:0">
      <label>Correo de destino *</label>
      <input type="email" name="destino" required maxlength="190" placeholder="destinatario@ejemplo.com">
    </div>
    <button type="submit" class="btn btn-azul">Enviar prueba</button>
  </form>
</section>

<!-- ===== Historial ===== -->
<section class="tarjeta">
  <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:14px;flex-wrap:wrap">
    <h3 style="margin:0">Historial de correos</h3>
    <span class="bit-nota">Últimos <?= count($historial) ?> registros</span>
  </div>

  <?php if (!$historial): ?>
    <div class="bit-vacio">Aún no se han enviado correos desde el sistema.</div>
  <?php else: ?>
    <div class="bit-scroll">
      <table class="tabla-mini">
        <thead>
          <tr><th>FECHA Y HORA</th><th>DESTINATARIO</th><th>ASUNTO</th><th>ESTADO</th><th>DETALLE</th></tr>
        </thead>
        <tbody>
          <?php foreach ($historial as $h): ?>
            <tr>
              <td><?= date('d/m/Y H:i', strtotime($h['creado_en'])) ?></td>
              <td><?= htmlspecialchars($h['destinatario']) ?></td>
              <td><?= htmlspecialchars($h['asunto']) ?></td>
              <td><span class="chip <?= chipCorreo($h['estado']) ?>"><?= textoCorreo($h['estado']) ?></span></td>
              <td style="max-width:340px;white-space:normal;font-size:12.5px;color:var(--texto-2)"><?= htmlspecialchars($h['detalle']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="bit-nota" style="margin-top:10px">
      Cada correo se guarda completo aquí para que el administrador pueda verificar qué información recibió el usuario.
    </div>
  <?php endif; ?>
</section>

<?php require_once __DIR__ . '/includes/layout_bottom.php'; ?>
