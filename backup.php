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
$backups = $pdo->query(
    "SELECT b.archivo, b.tamano_kb, b.fecha, u.nombre AS usuario
     FROM historial_backups b LEFT JOIN usuarios u ON u.id=b.usuario_id
     ORDER BY b.id DESC LIMIT 200")->fetchAll();
$restauraciones = $pdo->query(
    "SELECT r.archivo, r.resultado, r.detalle, r.fecha, u.nombre AS usuario
     FROM historial_restauraciones r LEFT JOIN usuarios u ON u.id=r.usuario_id
     ORDER BY r.id DESC LIMIT 200")->fetchAll();
$tituloPagina = 'Administración · Backup';
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
  <a href="mantenimiento.php">Mantenimiento</a>
  <a href="backup.php" class="on">Backup</a>
</div>

<?php if ($flashOk): ?><div class="aviso aviso-ok"><?= h($flashOk) ?></div><?php endif; ?>
<?php if ($flashError): ?><div class="aviso aviso-error"><?= h($flashError) ?></div><?php endif; ?>


<div class="ap-cab">
  <span class="ap-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg></span>
  <div><h3>Backup</h3><p>Genere copias de seguridad de la base de datos y restáurelas de forma segura.</p></div>
</div>

<div class="ap-grid">
  <section class="ap-card">
    <div class="ap-card-cab">
      <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg></span>
      <div><h4>Copia de seguridad</h4><p>Genere un respaldo completo de la base de datos y descárguelo.</p></div>
    </div>
    <div class="ap-banner"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg><span>Realice copias periódicas para proteger la información. Cada respaldo queda registrado en el historial.</span></div>
    <form action="hacer_backup.php" method="POST">
      <button class="ap-btn" type="submit"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg> Generar respaldo</button>
    </form>
  </section>

  <section class="ap-card">
    <div class="ap-card-cab">
      <span class="ic rojo"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg></span>
      <div><h4>Restaurar base de datos</h4><p>Cargue un respaldo (.sql) para recuperar la información.</p></div>
    </div>
    <div class="ap-banner rojo"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg><span><strong>Acción delicada:</strong> restaurar reemplaza todos los datos actuales. El sistema hará un respaldo automático antes de continuar.</span></div>
    <form action="restaurar_bd.php" method="POST" enctype="multipart/form-data"
          onsubmit="return confirm('¿Seguro que deseas restaurar la base de datos? Esto reemplazará TODOS los datos actuales. Se creará un respaldo automático antes de continuar.');">
      <div class="ap-campo"><label>Archivo de respaldo (.sql) <span class="req">*</span></label>
        <div class="ap-archivo"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg><input type="file" name="respaldo" accept=".sql" required></div></div>
      <button class="ap-btn rojo" type="submit"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg> Restaurar</button>
    </form>
  </section>
</div>

<section class="ap-card hist" data-pag="5">
  <div class="ap-card-cab">
    <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg></span>
    <div class="grow"><h4>Historial de respaldos</h4><p>Copias de seguridad generadas del sistema.</p></div>
    <div class="hist-herr">
        <label class="hist-buscar"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg><input type="search" class="hist-q" placeholder="Buscar respaldo..." aria-label="Buscar"></label>
        
      </div>
  </div>
  <div class="hist-scroll">
  <table class="hist-tabla">
    <thead><tr><th>Archivo</th><th>Tamaño</th><th>Usuario</th><th>Fecha y hora</th><th class="acc">Acciones</th></tr></thead>
    <tbody>
      <?php foreach ($backups as $b):
            $existe = is_file(DIR_BACKUPS . DIRECTORY_SEPARATOR . $b['archivo']); ?>
        <tr data-fila <?php if ($existe): ?>data-descarga="descargar_backup.php?tipo=backup&amp;archivo=<?= urlencode($b['archivo']) ?>" data-descarga-texto="Descargar respaldo"<?php endif; ?>>
          <td><?= h($b['archivo']) ?></td>
          <td><?= $b['tamano_kb'] !== null ? h($b['tamano_kb']) . ' KB' : '—' ?></td>
          <td><?= h($b['usuario'] ?? '—') ?></td>
          <td><?= fdt($b['fecha']) ?></td>
          <td class="acc"><?php if ($existe): ?><button type="button" class="hist-acc" aria-label="Acciones">⋮</button><?php else: ?>—<?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <p class="hist-vacio" <?= $backups ? 'hidden' : '' ?>>Sin respaldos aún.</p>
  </div>
  <div class="hist-pie"><span class="hist-info"></span><div class="hist-pag"></div></div>
</section>

<section class="ap-card hist" data-pag="5">
  <div class="ap-card-cab">
    <span class="ic verde"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/><polyline points="12 7 12 12 15 14"/></svg></span>
    <div class="grow"><h4>Historial de restauraciones</h4><p>Restauraciones de la base de datos realizadas.</p></div>
    <div class="hist-herr">
        <label class="hist-buscar"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg><input type="search" class="hist-q" placeholder="Buscar archivo..." aria-label="Buscar"></label>
        <select class="hist-f" aria-label="Resultado"><option value="">Todos los resultados</option><option value="Exitosa">Exitosa</option><option value="Fallida">Fallida</option></select>
      </div>
  </div>
  <div class="hist-scroll">
  <table class="hist-tabla">
    <thead><tr><th>Archivo</th><th>Usuario</th><th>Fecha y hora</th><th>Resultado</th><th class="acc">Acciones</th></tr></thead>
    <tbody>
      <?php foreach ($restauraciones as $r): $ok = $r['resultado'] === 'Exitosa'; ?>
        <tr data-fila data-estado="<?= h($r['resultado']) ?>" data-detalle="<?= h($r['detalle'] ?? '') ?>" data-ver-texto="Ver detalle"
            data-titulo="Restauración <?= h(strtolower($r['resultado'])) ?>" data-sub="<?= h($r['archivo'] . ' · ' . fdt($r['fecha'])) ?>">
          <td><?= h($r['archivo']) ?></td>
          <td><?= h($r['usuario'] ?? '—') ?></td>
          <td><?= fdt($r['fecha']) ?></td>
          <td><span class="hist-chip <?= $ok ? '' : 'mal' ?>"><?= $ok ? '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm-1.2 14.4L6.6 12.2l1.4-1.4 2.8 2.8 5.2-5.2 1.4 1.4-6.6 6.6z"/></svg>' : '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm4.2 12.8l-1.4 1.4L12 13.4l-2.8 2.8-1.4-1.4 2.8-2.8-2.8-2.8 1.4-1.4 2.8 2.8 2.8-2.8 1.4 1.4-2.8 2.8z"/></svg>' ?> <?= h($r['resultado']) ?></span></td>
          <td class="acc"><button type="button" class="hist-acc" aria-label="Acciones">⋮</button></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <p class="hist-vacio" <?= $restauraciones ? 'hidden' : '' ?>>Sin restauraciones aún.</p>
  </div>
  <div class="hist-pie"><span class="hist-info"></span><div class="hist-pag"></div></div>
</section>

<script src="assets/js/historial.js"></script>
<?php require_once __DIR__ . '/includes/layout_bottom.php'; ?>
