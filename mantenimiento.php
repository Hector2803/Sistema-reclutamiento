<?php
$tituloPagina = 'Administración · Mantenimiento';
$activo = 'administracion';
$estilosExtra = ['assets/css/candidatos.css', 'assets/css/usuarios.css'];

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

// Versión vigente + historial de versiones
$verActual = $pdo->query("SELECT version, fecha FROM versiones_sistema ORDER BY id DESC LIMIT 1")->fetch();
$versiones = $pdo->query(
    "SELECT v.version, v.descripcion, v.archivo, v.fecha, u.nombre AS usuario
     FROM versiones_sistema v LEFT JOIN usuarios u ON u.id=v.usuario_id
     ORDER BY v.id DESC")->fetchAll();

// Historial de backups y restauraciones
$backups = $pdo->query(
    "SELECT b.archivo, b.tamano_kb, b.fecha, u.nombre AS usuario
     FROM historial_backups b LEFT JOIN usuarios u ON u.id=b.usuario_id
     ORDER BY b.id DESC LIMIT 10")->fetchAll();
$restauraciones = $pdo->query(
    "SELECT r.archivo, r.resultado, r.fecha, u.nombre AS usuario
     FROM historial_restauraciones r LEFT JOIN usuarios u ON u.id=r.usuario_id
     ORDER BY r.id DESC LIMIT 10")->fetchAll();

function fdt($dt){ return $dt ? date('d/m/Y H:i', strtotime($dt)) : '—'; }

require_once __DIR__ . '/includes/layout_top.php';
?>

<div class="panel-encabezado">
  <div>
    <h2>Administración</h2>
    <p>Gestión de usuarios y mantenimiento del sistema.</p>
  </div>
  <div class="acciones-panel">
    <span class="version-chip">Versión actual: <strong>v<?= htmlspecialchars($verActual['version'] ?? SISTEMA_VERSION_BASE) ?></strong></span>
  </div>
</div>

<div class="subtabs">
  <a href="usuarios.php">Usuarios</a>
  <a href="correo.php">Correo</a>
  <a href="mantenimiento.php" class="on">Mantenimiento</a>
</div>

<?php if ($flashOk): ?><div class="aviso aviso-ok"><?= htmlspecialchars($flashOk) ?></div><?php endif; ?>
<?php if ($flashError): ?><div class="aviso aviso-error"><?= htmlspecialchars($flashError) ?></div><?php endif; ?>

<div class="mant-grid">

  <!-- ===== BACKUP ===== -->
  <section class="tarjeta">
    <h3 class="mant-h"><span class="mant-ic azul"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg></span> Copia de seguridad</h3>
    <p class="mant-desc">Genera un respaldo completo de la base de datos y descárgalo. Realiza copias periódicas para proteger la información.</p>
    <form action="hacer_backup.php" method="POST">
      <button class="btn btn-azul" type="submit">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        Generar respaldo
      </button>
    </form>
    <h4 class="mant-sub">Historial de respaldos</h4>
    <table class="tabla-mini">
      <thead><tr><th>Archivo</th><th>Tamaño</th><th>Usuario</th><th>Fecha y hora</th></tr></thead>
      <tbody>
        <?php if(!$backups): ?><tr><td colspan="4" class="vacio">Sin respaldos aún.</td></tr><?php endif; ?>
        <?php foreach($backups as $b): ?>
          <tr><td><?= htmlspecialchars($b['archivo']) ?></td><td><?= $b['tamano_kb']!==null?htmlspecialchars($b['tamano_kb']).' KB':'—' ?></td><td><?= htmlspecialchars($b['usuario'] ?? '—') ?></td><td><?= fdt($b['fecha']) ?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </section>

  <!-- ===== RESTAURAR ===== -->
  <section class="tarjeta">
    <h3 class="mant-h"><span class="mant-ic rojo"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg></span> Restaurar base de datos</h3>
    <div class="mant-alerta">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
      <span><strong>Acción delicada:</strong> restaurar reemplaza todos los datos actuales. El sistema hará un respaldo automático antes de continuar.</span>
    </div>
    <form action="restaurar_bd.php" method="POST" enctype="multipart/form-data"
          onsubmit="return confirm('¿Seguro que deseas restaurar la base de datos? Esto reemplazará TODOS los datos actuales. Se creará un respaldo automático antes de continuar.');">
      <div class="mf-campo"><label>Archivo de respaldo (.sql)</label>
        <input type="file" name="respaldo" accept=".sql" required></div>
      <button class="btn btn-rojo" type="submit">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg>
        Restaurar
      </button>
    </form>
    <h4 class="mant-sub">Historial de restauraciones</h4>
    <table class="tabla-mini">
      <thead><tr><th>Archivo</th><th>Resultado</th><th>Usuario</th><th>Fecha y hora</th></tr></thead>
      <tbody>
        <?php if(!$restauraciones): ?><tr><td colspan="4" class="vacio">Sin restauraciones aún.</td></tr><?php endif; ?>
        <?php foreach($restauraciones as $r): ?>
          <tr><td><?= htmlspecialchars($r['archivo']) ?></td>
              <td><span class="chip <?= $r['resultado']==='Exitosa'?'es-activo':'es-inactivo' ?>"><?= htmlspecialchars($r['resultado']) ?></span></td>
              <td><?= htmlspecialchars($r['usuario'] ?? '—') ?></td><td><?= fdt($r['fecha']) ?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </section>

  <!-- ===== VERSIÓN / ACTUALIZACIÓN ===== -->
  <section class="tarjeta mant-full">
    <h3 class="mant-h"><span class="mant-ic verde"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v6m0 0l3-3m-3 3L9 5"/><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg></span> Versión del sistema</h3>
    <div class="mant-alerta">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
      <span>La actualización reemplaza los archivos del sistema (excepto <em>uploads/</em> y <em>config/</em>). Se creará un respaldo del código actual antes de aplicar el paquete.</span>
    </div>
    <form action="actualizar_version.php" method="POST" enctype="multipart/form-data"
          onsubmit="return confirm('¿Aplicar la actualización? Se reemplazarán los archivos del sistema. Se hará un respaldo del código actual antes de continuar.');">
      <div class="mf-fila">
        <div class="mf-campo"><label>Número de versión *</label>
          <input type="text" name="version" required placeholder="Ej. 1.1.0" pattern="[0-9]+\.[0-9]+\.[0-9]+" title="Formato X.Y.Z"></div>
        <div class="mf-campo"><label>Paquete de actualización (.zip) *</label>
          <input type="file" name="paquete" accept=".zip" required></div>
      </div>
      <div class="mf-campo"><label>Notas de la versión (changelog)</label>
        <input type="text" name="descripcion" maxlength="255" placeholder="Ej. Se agregó el módulo de mantenimiento y correcciones."></div>
      <button class="btn btn-azul" type="submit">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
        Subir y aplicar actualización
      </button>
    </form>

    <h4 class="mant-sub">Historial de versiones</h4>
    <table class="tabla-mini">
      <thead><tr><th>Versión</th><th>Notas (changelog)</th><th>Paquete</th><th>Usuario</th><th>Fecha y hora</th></tr></thead>
      <tbody>
        <?php foreach($versiones as $v): ?>
          <tr><td><strong>v<?= htmlspecialchars($v['version']) ?></strong></td>
              <td><?= htmlspecialchars($v['descripcion'] ?? '—') ?></td>
              <td><?= htmlspecialchars($v['archivo'] ?? '—') ?></td>
              <td><?= htmlspecialchars($v['usuario'] ?? 'Sistema') ?></td>
              <td><?= fdt($v['fecha']) ?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </section>
</div>

<?php require_once __DIR__ . '/includes/layout_bottom.php'; ?>
