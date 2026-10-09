<?php
$tituloPagina = 'Administración · Usuarios';
$activo = 'administracion';
$estilosExtra = ['assets/css/candidatos.css', 'assets/css/usuarios.css'];

require_once __DIR__ . '/includes/guardia.php';
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/includes/correo.php';

// Solo el Administrador entra a este módulo
if (($_SESSION['usuario_rol'] ?? '') !== 'Administrador') {
    header('Location: dashboard.php'); exit;
}

$pdo = obtenerConexion();

/* Dominio de las cuentas de la empresa (se configura en la pestaña Correo) */
$dominio = correoDominio($pdo);

/* Mensajes flash (resultado de agregar usuario) */
$flashOk    = $_SESSION['flash_ok']    ?? '';
$flashError = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_ok'], $_SESSION['flash_error']);

/* ---- Lectura de usuarios reales (con rol y área) ---- */
$usuarios = $pdo->query(
    "SELECT u.id, u.usuario, u.nombre, u.correo, u.estado, u.ultimo_acceso,
            u.debe_cambiar_clave, u.fa2_activo, u.rol_id, u.area_id,
            r.nombre AS rol, a.nombre AS area
     FROM usuarios u
     INNER JOIN roles r ON r.id = u.rol_id
     LEFT  JOIN areas a ON a.id = u.area_id
     ORDER BY u.creado_en DESC"
)->fetchAll();

/* ---- Roles y áreas para el formulario y el filtro ---- */
$roles = $pdo->query("SELECT id, nombre FROM roles ORDER BY id")->fetchAll();
$areas = $pdo->query("SELECT id, nombre FROM areas WHERE estado = 1 ORDER BY nombre")->fetchAll();

/* ---- Bitácora de accesos (intentos de login) ---- */
$bitacora = $pdo->query(
    "SELECT usuario, ip, exito, detalle, creado_en
     FROM intentos_login
     ORDER BY creado_en DESC
     LIMIT 50"
)->fetchAll();
$bitOk   = 0;
$bitFail = 0;
foreach ($bitacora as $b) { if ((int)$b['exito'] === 1) $bitOk++; else $bitFail++; }

/* ---- Sesiones activas (menos de 30 min de inactividad) ---- */
$sesiones = [];
try {
    $sesiones = $pdo->query(
        "SELECT s.sesion, s.ip, s.creada_en, s.ultima_actividad,
                u.usuario, u.nombre, r.nombre AS rol
           FROM sesiones_activas s
           INNER JOIN usuarios u ON u.id = s.usuario_id
           INNER JOIN roles r    ON r.id = u.rol_id
          WHERE s.cerrada = 0
            AND s.ultima_actividad >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)
          ORDER BY s.ultima_actividad DESC"
    )->fetchAll();
} catch (PDOException $e) {
    $sesiones = [];
}
$miSesion = session_id();

/* ---- KPIs calculados ---- */
$total     = count($usuarios);
$activos   = 0;
foreach ($usuarios as $u) { if ((int)$u['estado'] === 1) $activos++; }
$inactivos = $total - $activos;
$numRoles  = count($roles);
$pct = fn($n) => $total ? round($n * 100 / $total, 1) . '% del total' : '0%';

$kpis = [
    ['ic'=>'azul', 'titulo'=>'Total Usuarios','num'=>(string)$total,     'pie'=>'100% del total'],
    ['ic'=>'verde','titulo'=>'Activos',       'num'=>(string)$activos,   'pie'=>$pct($activos)],
    ['ic'=>'rojo', 'titulo'=>'Inactivos',     'num'=>(string)$inactivos, 'pie'=>$pct($inactivos)],
    ['ic'=>'azul', 'titulo'=>'Roles',         'num'=>(string)$numRoles,  'pie'=>'Tipos de roles'],
];

$accesos = [
    ['candado','Roles y Permisos','Administra roles del sistema','roles'],
    ['libro','Bitácora de Accesos','Revisa los accesos de usuarios','bitacora'],
    ['monitor','Sesiones Activas','Gestiona sesiones activas','sesiones'],
    ['escudo','Políticas de Seguridad','Configura políticas de acceso','politicas'],
];

/* ¿El usuario en sesión puede administrar? (solo Administrador) */
$puedeAdmin = (($_SESSION['usuario_rol'] ?? '') === 'Administrador');

function iniciales(string $nombre): string {
    $p = preg_split('/\s+/', trim($nombre));
    return mb_strtoupper(mb_substr($p[0] ?? '', 0, 1) . mb_substr($p[1] ?? '', 0, 1));
}
/* Clase de color del chip según el rol */
function rolChip(string $rol): string {
    $m = [
        'Administrador'=>'rol-administrador','Coordinador'=>'rol-coordinador',
        'Supervisor'=>'rol-supervisor','Team Leader'=>'rol-teamleader',
        'Analista'=>'rol-analista','Reclutador'=>'rol-reclutador',
    ];
    return $m[$rol] ?? 'rol-reclutador';
}
/* Color de avatar estable a partir del texto */
function colorAvatar(string $txt): string {
    $cols = ['#3b82f6','#8b5cf6','#0ea5a4','#ec4899','#f59e0b','#22a06b','#d946ef','#6366f1'];
    $s = 0; foreach (str_split($txt) as $c) { $s += ord($c); }
    return $cols[$s % count($cols)];
}
/* Formatea el último acceso */
function fechaAcceso(?string $dt): array {
    if (!$dt || $dt === '0000-00-00 00:00:00') return ['Nunca',''];
    $ts = strtotime($dt);
    return [date('d/m/Y', $ts), date('h:i A', $ts)];
}

require_once __DIR__ . '/includes/layout_top.php';
?>

<!-- ===== Encabezado ===== -->
<div class="panel-encabezado">
  <div>
    <h2>Administración</h2>
    <p>Gestión de usuarios y mantenimiento del sistema.</p>
  </div>
  <div class="acciones-panel">
    <button class="btn btn-azul" onclick="nuevaUsuario()">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
      Agregar Usuario
    </button>
  </div>
</div>

<!-- Sub-pestañas del módulo Administración -->
<div class="subtabs">
  <a href="usuarios.php" class="on">Usuarios</a>
  <a href="correo.php">Correo</a>
  <a href="mantenimiento.php">Mantenimiento</a>
</div>

<?php if ($flashOk): ?>
  <script>mostrarToast(<?= json_encode($flashOk, JSON_UNESCAPED_UNICODE) ?>, 'ok');</script>
<?php endif; ?>
<?php if ($flashError): ?>
  <script>mostrarToast(<?= json_encode($flashError, JSON_UNESCAPED_UNICODE) ?>, 'error');</script>
<?php endif; ?>

<!-- ===== KPIs ===== -->
<div class="grid-kpis-cand">
  <?php foreach ($kpis as $k): ?>
    <div class="kpi-cand">
      <span class="ic <?= $k['ic'] ?>">
        <?php if($k['titulo']==='Total Usuarios'): ?><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        <?php elseif($k['titulo']==='Activos'): ?><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><polyline points="16 11 18 13 22 9"/></svg>
        <?php elseif($k['titulo']==='Inactivos'): ?><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="17" y1="8" x2="22" y2="13"/><line x1="22" y1="8" x2="17" y2="13"/></svg>
        <?php else: ?><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l3 7h7l-5.5 4 2 7L12 16l-6.5 4 2-7L2 9h7z"/></svg><?php endif; ?>
      </span>
      <div class="txt">
        <small><?= htmlspecialchars($k['titulo']) ?></small>
        <div class="num"><?= htmlspecialchars($k['num']) ?></div>
        <div class="pie"><?= htmlspecialchars($k['pie']) ?></div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<!-- ===== Dos columnas ===== -->
<div class="cand-layout">

  <div>
    <!-- Filtros -->
    <div class="filtros-cand">
      <div class="filtro-buscar">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input type="text" placeholder="Buscar por nombre, email o usuario...">
      </div>
      <div class="filtro-campo"><label>Rol</label>
        <select><option>Todos</option>
          <?php foreach ($roles as $r): ?><option><?= htmlspecialchars($r['nombre']) ?></option><?php endforeach; ?>
        </select></div>
      <div class="filtro-campo"><label>Estado</label>
        <select><option>Todos</option><option>Activo</option><option>Inactivo</option></select></div>
      <button class="btn-filtros">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
        Filtros
      </button>
      <button class="btn-limpiar">Limpiar</button>
    </div>

    <!-- Tabla -->
    <section class="tarjeta" style="padding:8px 18px 18px">
      <table class="tabla-cand">
        <thead>
          <tr>
            <th class="col-check"><input type="checkbox"></th>
            <th>USUARIO</th>
            <th>EMAIL</th>
            <th>ROL</th><th>ESTADO</th><th>ÚLTIMO ACCESO</th><th>ACCIONES</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$usuarios): ?>
            <tr><td colspan="7" style="text-align:center;color:var(--texto-3);padding:26px">No hay usuarios registrados.</td></tr>
          <?php endif; ?>
          <?php foreach ($usuarios as $u):
            $rolC = rolChip($u['rol']);
            $color = colorAvatar($u['usuario']);
            [$fAcc,$hAcc] = fechaAcceso($u['ultimo_acceso']);
            $esC = (int)$u['estado'] === 1 ? 'es-activo' : 'es-inactivo';
            $esT = (int)$u['estado'] === 1 ? 'Activo' : 'Inactivo'; ?>
            <tr>
              <td class="col-check"><input type="checkbox"></td>
              <td>
                <div class="usuario-cel">
                  <span class="cand-avatar" style="background:<?= $color ?>"><?= iniciales($u['nombre']) ?></span>
                  <span class="txt-cel">
                    <span class="n"><?= htmlspecialchars($u['nombre']) ?></span>
                    <span class="c<?= str_contains((string)$u['usuario'], '@') ? '' : ' sin-cuenta' ?>"><?= htmlspecialchars($u['usuario']) ?></span>
                  </span>
                </div>
              </td>
              <td><?= htmlspecialchars($u['correo'] ?? '—') ?></td>
              <td><span class="chip <?= $rolC ?>"><?= htmlspecialchars($u['rol']) ?></span></td>
              <td>
                <span class="chip <?= $esC ?>"><?= $esT ?></span>
                <?php if (!empty($u['debe_cambiar_clave'])): ?>
                  <div style="margin-top:6px"><span class="chip es-inactivo" style="background:#fff4e5;color:#a86a06">Clave regenerada · cambio pendiente</span></div>
                <?php endif; ?>
              </td>
              <td class="fecha"><?= $fAcc ?><?php if($hAcc): ?><small><?= $hAcc ?></small><?php endif; ?></td>
              <td>
                <div class="acciones">
                  <button class="icobtn" type="button" aria-label="Editar" title="Editar usuario" onclick="editarUsuario(this)"
                    data-id="<?= (int)$u['id'] ?>"
                    data-nombre="<?= htmlspecialchars($u['nombre'], ENT_QUOTES) ?>"
                    data-usuario="<?= htmlspecialchars($u['usuario'], ENT_QUOTES) ?>"
                    data-correo="<?= htmlspecialchars($u['correo'] ?? '', ENT_QUOTES) ?>"
                    data-rol="<?= (int)$u['rol_id'] ?>"
                    data-area="<?= (int)($u['area_id'] ?? 0) ?>"
                    data-est="<?= (int)$u['estado'] ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4z"/></svg>
                  </button>
                  <button class="icobtn" type="button" aria-label="Ver" title="Ver detalle" onclick="verUsuario(this)"
                    data-nombre="<?= htmlspecialchars($u['nombre'], ENT_QUOTES) ?>"
                    data-usuario="<?= htmlspecialchars($u['usuario'], ENT_QUOTES) ?>"
                    data-correo="<?= htmlspecialchars($u['correo'] ?? '', ENT_QUOTES) ?>"
                    data-rol="<?= htmlspecialchars($u['rol'], ENT_QUOTES) ?>"
                    data-area="<?= htmlspecialchars($u['area'] ?? '', ENT_QUOTES) ?>"
                    data-est="<?= (int)$u['estado'] === 1 ? 'Activo' : 'Inactivo' ?>"
                    data-acceso="<?= $fAcc ?><?= $hAcc ? ' · ' . $hAcc : '' ?>"
                    data-2fa="<?= (int)$u['fa2_activo'] === 1 ? 'Sí' : 'No' ?>"
                    data-pendiente="<?= (int)$u['debe_cambiar_clave'] === 1 ? 'Sí' : 'No' ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                  </button>
                  <div class="con-menu">
                    <button class="icobtn" type="button" aria-label="Más" title="Más acciones" onclick="toggleMenu(event, this)">
                      <svg viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="12" cy="19" r="1.6"/></svg>
                    </button>
                    <div class="menu-kebab">
                      <?php if ($puedeAdmin): ?>
                        <form action="guardar_usuario.php" method="POST" data-ajax="1">
                          <input type="hidden" name="accion" value="estado">
                          <input type="hidden" name="usuario_id" value="<?= (int)$u['id'] ?>">
                          <input type="hidden" name="estado" value="<?= (int)$u['estado'] === 1 ? 0 : 1 ?>">
                          <button type="submit" class="<?= (int)$u['estado'] === 1 ? 'op-inactivar' : 'op-activar' ?>">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18.36 6.64a9 9 0 1 1-12.73 0"/><line x1="12" y1="2" x2="12" y2="12"/></svg>
                            <?= (int)$u['estado'] === 1 ? 'Desactivar' : 'Activar' ?>
                          </button>
                        </form>
                        <form action="regenerar_clave.php" method="POST" data-ajax="1"
                              onsubmit="return confirm('Se generará una contraseña temporal para <?= htmlspecialchars(addslashes($u['usuario'])) ?> y deberá cambiarla al iniciar sesión. ¿Continuar?')">
                          <input type="hidden" name="usuario_id" value="<?= (int)$u['id'] ?>">
                          <button type="submit" class="op-clave">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/><path d="M12 15v2"/></svg>
                            Regenerar contraseña
                          </button>
                        </form>
                        <form action="guardar_usuario.php" method="POST" data-ajax="1"
                              onsubmit="return confirm('Se eliminará el usuario <?= htmlspecialchars(addslashes($u['usuario'])) ?>. Esta acción no se puede deshacer. ¿Continuar?')">
                          <input type="hidden" name="accion" value="eliminar">
                          <input type="hidden" name="usuario_id" value="<?= (int)$u['id'] ?>">
                          <button type="submit" class="op-eliminar">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                            Eliminar usuario
                          </button>
                        </form>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <div class="tabla-pie">
        <span>Mostrando <?= $total ? 1 : 0 ?> a <?= $total ?> de <?= $total ?> usuario<?= $total===1?'':'s' ?></span>
        <div class="paginacion">
          <button class="pag activa">1</button>
        </div>
      </div>
    </section>
  </div>

  <!-- Panel derecho -->
  <aside class="panel-lat">
    <section class="tarjeta">
      <h3 style="margin-bottom:6px">Gestión de Accesos</h3>
      <?php foreach($accesos as $a): [$ico,$tit,$sub,$act]=$a; ?>
        <?php if ($act): ?>
          <div class="acceso-item con-accion" role="button" tabindex="0" onclick="abrirModal('modal<?= ucfirst($act) ?>')">
        <?php else: ?>
          <div class="acceso-item">
        <?php endif; ?>
          <span class="ai-ic">
            <?php if($ico==='candado'): ?><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            <?php elseif($ico==='libro'): ?><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
            <?php elseif($ico==='monitor'): ?><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
            <?php else: ?><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg><?php endif; ?>
          </span>
          <div class="ai-tx"><strong><?= $tit ?></strong><span><?= $sub ?></span></div>
        </div>
      <?php endforeach; ?>
    </section>

    <section class="tarjeta">
      <h3 style="margin-bottom:8px">Acciones Rápidas</h3>
      <div class="accion-rapida" role="button" tabindex="0" onclick="abrirModal('modalImportar')">
        <span class="ar-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg></span>
        <div class="ar-tx"><strong>Importar Usuarios</strong><span>Desde archivo CSV</span></div>
      </div>
      <div class="accion-rapida" role="button" tabindex="0" onclick="nuevaUsuario()">
        <span class="ar-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16v16H4z"/><polyline points="22 6 12 13 2 6"/></svg></span>
        <div class="ar-tx"><strong>Invitar Usuario</strong><span>Crear cuenta y compartir acceso</span></div>
      </div>
    </section>
  </aside>
</div>

<!-- ===== Modal: Agregar / Editar Usuario ===== -->
<div class="modal-fondo" id="modalUsuario">
  <div class="modal-caja">
    <div class="modal-cab">
      <h3 id="modalUsuarioTitulo">Agregar Usuario</h3>
      <button class="modal-x" onclick="cerrarModal('modalUsuario')" aria-label="Cerrar">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <form action="guardar_usuario.php" method="POST" class="modal-form" data-ajax="1">
      <input type="hidden" name="accion" id="fAccion" value="crear">
      <input type="hidden" name="usuario_id" id="fId" value="">
      <div class="mf-fila">
        <div class="mf-campo">
          <label>Nombre completo</label>
          <input type="text" name="nombre" id="fNombre" required maxlength="100" placeholder="Ej. Juan Pérez">
        </div>
        <div class="mf-campo">
          <label>Usuario (correo corporativo) *</label>
          <input type="text" name="cuenta" id="fCuenta" required maxlength="64"
                 placeholder="Ej. jperez" autocomplete="off" oninput="actualizaCuenta()">
          <span class="mf-ayuda"><span id="fCuentaAyuda">Se arma con el dominio:</span> <strong id="fCuentaVista">jperez@<?= htmlspecialchars($dominio) ?></strong></span>
        </div>
      </div>
      <div class="mf-campo">
        <label>Correo personal (credenciales) *</label>
        <input type="email" name="correo" id="fCorreo" maxlength="120" placeholder="persona@ejemplo.com" required>
        <span class="mf-ayuda">Dirección personal de la persona: aquí llegan la cuenta y la contraseña temporal.</span>
      </div>
      <div class="mf-fila">
        <div class="mf-campo">
          <label>Rol</label>
          <select name="rol_id" id="fRol" required>
            <option value="">Seleccione...</option>
            <?php foreach ($roles as $r): ?>
              <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mf-campo">
          <label>Área asignada</label>
          <select name="area_id" id="fArea">
            <option value="">Sin área (todas)</option>
            <?php foreach ($areas as $a): ?>
              <option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="mf-fila">
        <div class="mf-campo">
          <label>Estado</label>
          <select name="estado" id="fEstado"><option value="1">Activo</option><option value="0">Inactivo</option></select>
        </div>
      </div>
      <div class="mf-campo" id="fClaveNota" style="display:none">
        <div class="bit-nota" style="background:#f4f7fc;border:1px dashed var(--borde);border-radius:9px;padding:10px 12px">
          Al guardar se generará una <strong>contraseña temporal</strong> y se enviará al correo personal;
          la persona deberá cambiarla al iniciar sesión. Para cambiarla después usa
          «Regenerar contraseña» en el menú ⋮ de la lista.
        </div>
      </div>
      <div class="modal-pie">
        <button type="button" class="btn-linea" onclick="cerrarModal('modalUsuario')">Cancelar</button>
        <button type="submit" class="btn btn-azul" id="btnGuardarUsuario">Guardar usuario</button>
      </div>
    </form>
  </div>
</div>

<!-- ===== Modal: Ver usuario ===== -->
<div class="modal-fondo" id="modalVer">
  <div class="modal-caja" style="max-width:480px">
    <div class="modal-cab">
      <h3>Ficha del usuario</h3>
      <button class="modal-x" onclick="cerrarModal('modalVer')" aria-label="Cerrar">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="modal-form">
      <div class="ficha-cab">
        <span class="cand-avatar" id="vAvatar" style="background:#3b82f6">--</span>
        <div>
          <strong id="vNombre">—</strong>
          <span id="vUsuario">—</span>
        </div>
      </div>
      <dl class="ficha-datos" id="vDatos"></dl>
      <div class="modal-pie">
        <button type="button" class="btn-linea" onclick="cerrarModal('modalVer')">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<!-- ===== Modal: Bitácora de accesos ===== -->
<div class="modal-fondo" id="modalBitacora">
  <div class="modal-caja" style="max-width:720px">
    <div class="modal-cab">
      <h3>Bitácora de accesos</h3>
      <button class="modal-x" onclick="cerrarModal('modalBitacora')" aria-label="Cerrar">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="modal-form">
      <div class="bit-resumen">
        <span class="chip es-activo"><?= $bitOk ?> exitoso<?= $bitOk === 1 ? '' : 's' ?></span>
        <span class="chip es-inactivo"><?= $bitFail ?> fallido<?= $bitFail === 1 ? '' : 's' ?></span>
        <span class="bit-nota">Últimos <?= count($bitacora) ?> registros</span>
      </div>

      <?php if (!$bitacora): ?>
        <div class="bit-vacio">No hay intentos de acceso registrados.</div>
      <?php else: ?>
        <div class="bit-scroll">
          <table class="tabla-mini">
            <thead>
              <tr><th>FECHA Y HORA</th><th>USUARIO</th><th>IP</th><th>RESULTADO</th><th>DETALLE</th></tr>
            </thead>
            <tbody>
              <?php foreach ($bitacora as $b): $ok = (int)$b['exito'] === 1; ?>
                <tr>
                  <td><?= date('d/m/Y H:i', strtotime($b['creado_en'])) ?></td>
                  <td><?= htmlspecialchars($b['usuario']) ?></td>
                  <td><?= htmlspecialchars($b['ip']) ?></td>
                  <td><span class="chip <?= $ok ? 'es-activo' : 'es-inactivo' ?>"><?= $ok ? 'Éxito' : 'Fallido' ?></span></td>
                  <td><?= htmlspecialchars($b['detalle']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="bit-nota" style="margin-top:10px">
          Los registros se conservan 24 horas. Los fallos de un usuario se limpian automáticamente al iniciar sesión correctamente.
        </div>
      <?php endif; ?>

      <div class="modal-pie">
        <button type="button" class="btn-linea" onclick="cerrarModal('modalBitacora')">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<!-- ===== Modal: Sesiones activas ===== -->
<div class="modal-fondo" id="modalSesiones">
  <div class="modal-caja" style="max-width:820px">
    <div class="modal-cab">
      <h3>Sesiones activas <span class="chip es-activo" style="margin-left:8px;vertical-align:middle"><?= count($sesiones) ?></span></h3>
      <button class="modal-x" onclick="cerrarModal('modalSesiones')" aria-label="Cerrar">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="modal-form">
      <div class="bit-nota" style="margin-bottom:10px">
        Sesiones con menos de 30 minutos de inactividad. Al cerrar una sesión, ese usuario deberá volver a iniciar.
      </div>

      <?php if (!$sesiones): ?>
        <div class="bit-vacio">No hay sesiones activas en este momento.</div>
      <?php else: ?>
        <div class="bit-scroll">
          <table class="tabla-mini">
            <thead>
              <tr><th>USUARIO</th><th>ROL</th><th>IP</th><th>INICIO</th><th>ÚLTIMA ACTIVIDAD</th><th></th></tr>
            </thead>
            <tbody>
              <?php foreach ($sesiones as $s): $mia = ($s['sesion'] === $miSesion); ?>
                <tr>
                  <td>
                    <strong style="font-weight:600"><?= htmlspecialchars($s['usuario']) ?></strong>
                    <br><small style="color:var(--texto-3)"><?= htmlspecialchars($s['nombre']) ?></small>
                  </td>
                  <td><span class="chip <?= rolChip($s['rol']) ?>"><?= htmlspecialchars($s['rol']) ?></span></td>
                  <td><?= htmlspecialchars($s['ip']) ?></td>
                  <td><?= date('d/m/Y H:i', strtotime($s['creada_en'])) ?></td>
                  <td><?= date('d/m/Y H:i', strtotime($s['ultima_actividad'])) ?></td>
                  <td style="text-align:right">
                    <?php if ($mia): ?>
                      <span class="chip es-activo">Tu sesión</span>
                    <?php else: ?>
                      <form action="cerrar_sesion_usuario.php" method="POST" style="display:inline"
                            onsubmit="return confirm('Se cerrará la sesión de <?= htmlspecialchars(addslashes($s['usuario'])) ?>. ¿Continuar?')">
                        <input type="hidden" name="sesion" value="<?= htmlspecialchars($s['sesion']) ?>">
                        <button type="submit" class="btn-mini-rojo">Cerrar sesión</button>
                      </form>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>

      <div class="modal-pie">
        <button type="button" class="btn-linea" onclick="cerrarModal('modalSesiones')">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<!-- ===== Modal: Roles y Permisos ===== -->
<div class="modal-fondo" id="modalRoles">
  <div class="modal-caja" style="max-width:720px">
    <div class="modal-cab">
      <h3>Roles y Permisos</h3>
      <button class="modal-x" onclick="cerrarModal('modalRoles')" aria-label="Cerrar">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="modal-form">
      <?php
        $matriz = [
            ['Ver dashboard, candidatos, entrevistas y organigrama', 1, 1, 1],
            ['Registrar candidatos y moverlos de etapa',               1, 1, 1],
            ['Crear / editar / publicar / cerrar requerimientos',      1, 1, 0],
            ['Banco de preguntas y calificar evaluaciones',           1, 1, 0],
            ['Gestionar usuarios, 2FA y regenerar contraseñas',       1, 0, 0],
            ['Gestionar personal del organigrama',                    1, 0, 0],
            ['Mantenimiento: respaldos, restaurar y actualizar',       1, 0, 0],
        ];
      ?>
      <table class="tabla-mini tabla-permisos">
        <thead>
          <tr><th>ACCESO</th><th class="col-ok">ADMIN</th><th class="col-ok">SUP.</th><th class="col-ok">RECL.</th></tr>
        </thead>
        <tbody>
          <?php foreach ($matriz as $m): ?>
            <tr>
              <td><?= htmlspecialchars($m[0]) ?></td>
              <?php for ($i = 1; $i <= 3; $i++): ?>
                <td class="col-ok"><?= $m[$i] ? '<span class="si">✓</span>' : '<span class="no">✕</span>' ?></td>
              <?php endfor; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <div class="bit-nota" style="margin-top:12px">
        • Reportes, Kanban y el tablero principal están disponibles para cualquier usuario con sesión activa
        (hoy no tienen restricción por rol).<br>
        • Solo existen tres roles en el sistema: Administrador, Supervisor y Reclutador.
      </div>
      <div class="modal-pie">
        <button type="button" class="btn-linea" onclick="cerrarModal('modalRoles')">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<!-- ===== Modal: Políticas de Seguridad ===== -->
<div class="modal-fondo" id="modalPoliticas">
  <div class="modal-caja" style="max-width:640px">
    <div class="modal-cab">
      <h3>Políticas de Seguridad</h3>
      <button class="modal-x" onclick="cerrarModal('modalPoliticas')" aria-label="Cerrar">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="modal-form">
      <dl class="ficha-datos" style="grid-template-columns:190px 1fr">
        <dt>Bloqueo por intentos</dt>
        <dd>3 fallos por usuario en 15 minutos (y 10 por IP en 15 minutos). Cada intento queda auditado.</dd>

        <dt>Contraseña</dt>
        <dd>Mínimo 6 caracteres, con al menos una letra y un número. Guardadas con bcrypt (coste 12).</dd>

        <dt>Clave regenerada</dt>
        <dd>El administrador genera una temporal y el usuario debe cambiarla antes de usar el sistema.</dd>

        <dt>Sesión</dt>
        <dd>Cookie HttpOnly + SameSite=Lax, id regenerado al entrar e inactividad máxima de 30 minutos.</dd>

        <dt>Verificación 2 pasos</dt>
        <dd>TOTP de 6 dígitos cada 30 segundos, disponible para los administradores.</dd>

        <dt>Auditoría</dt>
        <dd>Intentos de acceso en la bitácora (24 horas) y último acceso por usuario en la tabla de usuarios.</dd>

        <dt>Cierre a distancia</dt>
        <dd>Desde «Sesiones activas» el administrador puede cerrar la sesión de cualquier usuario.</dd>
      </dl>
      <div class="modal-pie">
        <button type="button" class="btn-linea" onclick="cerrarModal('modalPoliticas')">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<!-- ===== Modal: Importar usuarios ===== -->
<div class="modal-fondo" id="modalImportar">
  <div class="modal-caja" style="max-width:600px">
    <div class="modal-cab">
      <h3>Importar Usuarios (CSV)</h3>
      <button class="modal-x" onclick="cerrarModal('modalImportar')" aria-label="Cerrar">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <form action="importar_usuarios.php" method="POST" enctype="multipart/form-data" class="modal-form">
      <div class="bit-nota" style="margin-bottom:12px">
        Columnas (con o sin encabezado): <strong>nombre, usuario, correo, rol</strong> y opcionalmente
        <strong>área</strong>. Usa coma o punto y coma como separador.
      </div>
      <div class="bit-ejemplo">Juan Pérez,jperez,juan@a365.com,Reclutador<br>Ana Torres,atorres,ana@a365.com,Supervisor,Operaciones</div>
      <div class="mf-campo" style="margin-top:14px">
        <label>Archivo CSV</label>
        <input type="file" name="csv" accept=".csv,text/csv" required>
      </div>
      <div class="bit-nota">
        Los usuarios se crean <strong>activos</strong> con una contraseña temporal que verás al finalizar
        la importación; deberán cambiarla antes de usar el sistema. Máximo 512 KB.
      </div>
      <div class="modal-pie">
        <button type="button" class="btn-linea" onclick="cerrarModal('modalImportar')">Cancelar</button>
        <button type="submit" class="btn btn-azul">Importar</button>
      </div>
    </form>
  </div>
</div>

<script>
const DOMINIO = <?= json_encode($dominio) ?>;

/* ---- Guardado por AJAX: sin recargar la página, aviso con toast ---- */
async function enviarFormAjax(frm){
  try{
    const resp = await fetch(frm.action, {
      method: 'POST',
      body: new FormData(frm),
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });
    return await resp.json();
  }catch(e){
    return { ok: false, mensaje: 'No se pudo completar la acción. Intenta nuevamente.' };
  }
}

async function refrescarTablaUsuarios(){
  try{
    const resp = await fetch('usuarios.php');
    const doc  = new DOMParser().parseFromString(await resp.text(), 'text/html');
    const nuevo = doc.querySelector('.tabla-cand tbody');
    const actual = document.querySelector('.tabla-cand tbody');
    if (nuevo && actual) actual.innerHTML = nuevo.innerHTML;
    const numsNuevos = doc.querySelectorAll('.grid-kpis-cand .kpi-cand .num');
    const numsAct    = document.querySelectorAll('.grid-kpis-cand .kpi-cand .num');
    numsNuevos.forEach((n, i) => { if (numsAct[i]) numsAct[i].textContent = n.textContent; });
    const pieN = doc.querySelector('.tabla-pie span');
    const pieA = document.querySelector('.tabla-pie span');
    if (pieN && pieA) pieA.textContent = pieN.textContent;
  }catch(e){ /* si falla el refresco, la página sigue funcionando */ }
}

function cerrarModal(id){ document.getElementById(id).classList.remove('abierto'); }

function abrirModal(id){ document.getElementById(id).classList.add('abierto'); }

function inicialesDe(nombre){
  const p = nombre.trim().split(/\s+/);
  return ((p[0]||'')[0] || '').toUpperCase() + ((p[1]||'')[0] || '').toUpperCase();
}

/* ---- Cuenta: vista previa del usuario de acceso ---- */
function limpiaCuenta(v){
  return (v || '').toLowerCase().replace(/[^a-z0-9._-]/g, '');
}
function actualizaCuenta(){
  const c = document.getElementById('fCuenta');
  const limpio = limpiaCuenta(c.value);
  document.getElementById('fCuentaVista').textContent =
    limpio ? limpio + '@' + DOMINIO : 'nombre@' + DOMINIO;
}

/* ---- Nuevo usuario: deja el modal en modo crear ---- */
function nuevaUsuario(){
  const f = document.getElementById('modalUsuario').querySelector('form');
  f.reset();
  const cuenta = document.getElementById('fCuenta');
  cuenta.readOnly = false;
  cuenta.value = '';
  document.getElementById('fCuentaAyuda').textContent = 'Se arma con el dominio:';
  actualizaCuenta();
  // Al crear NO se escribe contraseña: se genera una temporal y se envía al correo personal
  document.getElementById('fClaveNota').style.display = '';
  document.getElementById('fCorreo').required = true;
  document.getElementById('modalUsuarioTitulo').textContent = 'Agregar Usuario';
  document.getElementById('btnGuardarUsuario').textContent = 'Guardar usuario';
  document.getElementById('fAccion').value = 'crear';
  document.getElementById('fId').value = '';
  abrirModal('modalUsuario');
}

/* ---- Editar: carga los datos en el modal ---- */
function editarUsuario(btn){
  const d = btn.dataset;
  document.getElementById('modalUsuarioTitulo').textContent = 'Editar Usuario';
  document.getElementById('btnGuardarUsuario').textContent = 'Guardar cambios';
  document.getElementById('fAccion').value = 'editar';
  document.getElementById('fId').value = d.id;
  document.getElementById('fNombre').value = d.nombre;
  const cuenta = document.getElementById('fCuenta');
  const esCorreo = (d.usuario || '').includes('@');
  cuenta.value = esCorreo ? d.usuario.split('@')[0] : d.usuario;
  cuenta.readOnly = !esCorreo;
  cuenta.required = esCorreo;
  document.getElementById('fCuentaAyuda').textContent = esCorreo
    ? 'Cuenta de acceso:'
    : 'Cuenta heredada (sin dominio, no se modifica):';
  document.getElementById('fCuentaVista').textContent = d.usuario || '—';
  document.getElementById('fCorreo').value = d.correo;
  document.getElementById('fCorreo').required = false;
  document.getElementById('fRol').value = d.rol;
  document.getElementById('fArea').value = d.area;
  document.getElementById('fEstado').value = d.est;
  // La contraseña no se edita aquí: se regenera desde el menú ⋮ de la lista
  document.getElementById('fClaveNota').style.display = 'none';
  abrirModal('modalUsuario');
}

/* ---- Ver: ficha de solo lectura ---- */
function verUsuario(btn){
  const d = btn.dataset;
  document.getElementById('vAvatar').textContent = inicialesDe(d.nombre);
  document.getElementById('vNombre').textContent = d.nombre;
  document.getElementById('vUsuario').textContent = d.usuario;
  const filas = [
    ['Correo personal', d.correo || '—'],
    ['Rol', d.rol],
    ['Área', d.area || 'Sin área (todas)'],
    ['Estado', d.est],
    ['Último acceso', d.acceso],
    ['Verificación en dos pasos', d['2fa']],
    ['Clave regenerada', d.pendiente === 'Sí' ? 'Sí · cambio obligatorio al iniciar' : 'No']
  ];
  document.getElementById('vDatos').innerHTML = filas.map(f =>
    '<dt>' + f[0] + '</dt><dd>' + f[1] + '</dd>').join('');
  abrirModal('modalVer');
}

/* ---- Menú contextual (⋮) ---- */
function cerrarMenus(){
  document.querySelectorAll('.menu-kebab.abierto').forEach(m => m.classList.remove('abierto'));
}
function toggleMenu(e, btn){
  e.stopPropagation();
  const m = btn.parentElement.querySelector('.menu-kebab');
  const estaba = m.classList.contains('abierto');
  cerrarMenus();
  if (estaba) return;
  const r = btn.getBoundingClientRect();
  m.classList.add('abierto');
  const h = m.offsetHeight, w = m.offsetWidth;
  let top = r.bottom + 6;
  if (top + h > window.innerHeight - 8) top = Math.max(8, r.top - 6 - h);
  let left = r.right - w;
  if (left < 8) left = 8;
  if (left + w > window.innerWidth - 8) left = Math.max(8, window.innerWidth - w - 8);
  m.style.top = top + 'px';
  m.style.left = left + 'px';
}
document.addEventListener('click', cerrarMenus);
window.addEventListener('scroll', cerrarMenus, true);
window.addEventListener('resize', cerrarMenus);
document.addEventListener('keydown', e => { if (e.key === 'Escape') { cerrarMenus(); } });

/* ---- Formularios con envío AJAX (modal, activar/desactivar,
        regenerar contraseña, eliminar): todo responde con toast ---- */
document.querySelectorAll('form[data-ajax="1"]').forEach(frm => {
  frm.addEventListener('submit', async function(ev){
    if (ev.defaultPrevented) return;                 // confirm() cancelado
    ev.preventDefault();
    if (frm.dataset.enviando === '1') return;         // evita doble envío
    frm.dataset.enviando = '1';
    const btn = frm.querySelector('[type="submit"]');
    const htmlBtn = btn ? btn.innerHTML : '';
    if (btn){ btn.disabled = true; btn.innerHTML = 'Guardando...'; }
    const datos = await enviarFormAjax(frm);
    if (btn){ btn.disabled = false; btn.innerHTML = htmlBtn; }
    frm.dataset.enviando = '';
    if (datos && datos.ok){
      if (frm.closest('#modalUsuario')) cerrarModal('modalUsuario');
      cerrarMenus();
      await refrescarTablaUsuarios();
      mostrarToast(datos.mensaje, 'ok');
    } else {
      mostrarToast((datos && datos.mensaje) || 'No se pudo completar la acción.', 'error');
    }
  });
});
</script>

<?php require_once __DIR__ . '/includes/layout_bottom.php'; ?>
