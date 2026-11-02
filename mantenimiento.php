<?php
require_once __DIR__ . '/includes/guardia.php';
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/config/sistema.php';

// Solo Administrador
if (($_SESSION['usuario_rol'] ?? '') !== 'Administrador') {
    header('Location: dashboard.php'); exit;
}
$pdo = obtenerConexion();

$flashOk    = $_SESSION['flash_ok']    ?? '';
$flashError = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_ok'], $_SESSION['flash_error']);
function fdt($dt){ return $dt ? date('d/m/Y H:i', strtotime($dt)) : '—'; }
function h($t){ return htmlspecialchars((string)$t, ENT_QUOTES, 'UTF-8'); }
$verActual = $pdo->query("SELECT version, fecha FROM versiones_sistema ORDER BY id DESC LIMIT 1")->fetch();
$versiones = $pdo->query(
    "SELECT v.version, v.descripcion, v.archivo, v.fecha, u.nombre AS usuario
     FROM versiones_sistema v LEFT JOIN usuarios u ON u.id=v.usuario_id
     ORDER BY v.id DESC")->fetchAll();
$tituloPagina = 'Administración · Mantenimiento';
$activo = 'administracion';
$estilosExtra = ['assets/css/candidatos.css', 'assets/css/usuarios.css', 'assets/css/admin_panel.css'];
require_once __DIR__ . '/includes/layout_top.php';
?>

<div class="panel-encabezado">
  <div>
    <h2>Administración</h2>
    <p>Gestión de usuarios y mantenimiento del sistema.</p>
  </div>
  <div class="acciones-panel">
    <span class="version-chip">Versión actual: <strong>v<?= h($verActual['version'] ?? SISTEMA_VERSION_BASE) ?></strong></span>
  </div>
</div>

<div class="subtabs">
  <a href="usuarios.php">Usuarios</a>
  <a href="correo.php">Correo</a>
  <a href="mantenimiento.php" class="on">Mantenimiento</a>
  <a href="backup.php">Backup</a>
</div>

<?php if ($flashOk): ?><div class="aviso aviso-ok"><?= h($flashOk) ?></div><?php endif; ?>
<?php if ($flashError): ?><div class="aviso aviso-error"><?= h($flashError) ?></div><?php endif; ?>


<div class="ap-cab">
  <span class="ap-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/></svg></span>
  <div><h3>Versión del sistema</h3><p>Cargue y aplique actualizaciones del sistema de forma segura.</p></div>
</div>

<div class="ap-banner"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg><span>La actualización reemplaza los archivos del sistema (excepto <em>uploads/</em> y <em>config/</em>). Se creará un respaldo del código actual antes de aplicar el paquete.</span></div>

<section class="ap-card">
  <div class="ap-card-cab">
    <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg></span>
    <div><h4>Aplicar actualización</h4><p>Seleccione el paquete de actualización y proporcione la información de la versión.</p></div>
  </div>
  <form action="actualizar_version.php" method="POST" enctype="multipart/form-data"
        onsubmit="return confirm('¿Aplicar la actualización? Se reemplazarán los archivos del sistema. Se hará un respaldo del código actual antes de continuar.');">
    <div class="ap-form-fila">
      <div class="ap-campo"><label for="apVersion">Número de versión <span class="req">*</span></label>
        <input type="text" id="apVersion" name="version" required placeholder="Ej. 1.1.0" pattern="[0-9]+\.[0-9]+\.[0-9]+" title="Formato X.Y.Z"></div>
      <div class="ap-campo"><label>Paquete de actualización (.zip) <span class="req">*</span></label>
        <div class="ap-archivo"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg><input type="file" name="paquete" accept=".zip" required></div></div>
    </div>
    <div class="ap-campo"><label for="apNotas">Notas de la versión (changelog)</label>
      <textarea id="apNotas" name="descripcion" rows="2" maxlength="255" placeholder="Ej. Se agregó el módulo de mantenimiento y correcciones."></textarea>
      <div class="ap-contador" id="apContador">0/255</div></div>
    <button class="ap-btn" type="submit"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg> Actualizar</button>
  </form>
</section>

<section class="ap-card hist" data-pag="5">
  <div class="ap-card-cab">
    <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/><polyline points="12 7 12 12 15 14"/></svg></span>
    <div class="grow"><h4>Historial de versiones</h4><p>Listado de actualizaciones aplicadas al sistema.</p></div>
    <div class="hist-herr">
        <label class="hist-buscar"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg><input type="search" class="hist-q" placeholder="Buscar paquete..." aria-label="Buscar"></label>
        <select class="hist-f" aria-label="Estado"><option value="">Todos los estados</option><option value="Aplicada">Aplicada</option></select>
      </div>
  </div>
  <div class="hist-scroll">
  <table class="hist-tabla">
    <thead><tr><th>Versión</th><th>Paquete</th><th>Usuario</th><th>Fecha y hora</th><th>Estado</th><th class="acc">Acciones</th></tr></thead>
    <tbody>
      <?php foreach ($versiones as $v):
            $existe = $v['archivo'] && is_file(DIR_BACKUPS . DIRECTORY_SEPARATOR . $v['archivo']); ?>
        <tr data-fila data-estado="Aplicada" data-detalle="<?= h($v['descripcion'] ?? '') ?>" data-ver-texto="Ver notas de la versión"
            data-titulo="Versión v<?= h($v['version']) ?>" data-sub="<?= h(($v['archivo'] ?? 'Versión base') . ' · ' . fdt($v['fecha'])) ?>"
            <?php if ($existe): ?>data-descarga="descargar_backup.php?tipo=paquete&amp;archivo=<?= urlencode($v['archivo']) ?>" data-descarga-texto="Descargar paquete"<?php endif; ?>>
          <td>v<?= h($v['version']) ?></td>
          <td><?= h($v['archivo'] ?? '—') ?></td>
          <td><?= h($v['usuario'] ?? 'Sistema') ?></td>
          <td><?= fdt($v['fecha']) ?></td>
          <td><span class="hist-chip"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm-1.2 14.4L6.6 12.2l1.4-1.4 2.8 2.8 5.2-5.2 1.4 1.4-6.6 6.6z"/></svg> Aplicada</span></td>
          <td class="acc"><button type="button" class="hist-acc" aria-label="Acciones">⋮</button></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <p class="hist-vacio" hidden>Sin resultados.</p>
  </div>
  <div class="hist-pie"><span class="hist-info"></span><div class="hist-pag"></div></div>
</section>

<script src="assets/js/historial.js"></script>
<?php require_once __DIR__ . '/includes/layout_bottom.php'; ?>
