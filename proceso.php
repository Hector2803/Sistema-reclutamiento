<?php
/* ============================================================
   SSR - A365 | Gestión del proceso de un candidato
   Muestra en qué etapa está y qué debe hacer el reclutador:
   2 test (lo rinde el postulante) -> 3 validar datos/CV/documentos ->
   4 entrevista presencial -> 5 capacitaciones (hasta 5, con asistencia)
   -> 6 proceso finalizado. Solo ve candidatos de su área el reclutador.
   ============================================================ */
$tituloPagina = 'Gestión del proceso';
$activo = 'candidatos';
$estilosExtra = ['assets/css/candidatos.css', 'assets/css/proceso.css'];

require_once __DIR__ . '/includes/guardia.php';
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/includes/alcance.php';
require_once __DIR__ . '/includes/flujo.php';
$pdo = obtenerConexion();

$postId = (int)($_GET['post'] ?? 0);
$p = $postId ? flujoPostulacion($pdo, $postId) : null;
if (!$p) { $_SESSION['flash_error'] = 'La postulación no existe.'; header('Location: candidatos.php'); exit; }
if (!puedeGestionarPostulacion($pdo, $postId)) { $_SESSION['flash_error'] = 'Solo puedes gestionar a los candidatos de tu área.'; header('Location: candidatos.php'); exit; }

$flashOk = $_SESSION['flash_ok'] ?? ''; $flashError = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_ok'], $_SESSION['flash_error']);

$etapas  = $pdo->query("SELECT id, nombre, orden FROM etapas ORDER BY orden")->fetchAll();
$orden   = (int)$p['orden'];
$ultimo  = flujoUltimoOrden($pdo);
$activa  = $p['estado'] === 'En Proceso';
$descartado = $p['estado'] === 'Descartado';
$test    = flujoResultadoTest($pdo, $postId);
$cvApto  = flujoCvApto($p['puntaje_cv']);
$entrevistas = flujoEntrevistas($pdo, $postId);
$caps    = flujoCapacitaciones($pdo, $postId);
$motivos = $pdo->query("SELECT id, descripcion, etapa_id FROM motivos_descarte ORDER BY id")->fetchAll();
$motivosEtapa = array_values(array_filter($motivos, fn($m) => $m['etapa_id'] === null || (int)$m['etapa_id'] === (int)$p['etapa_id']));
$usuarios = $pdo->query("SELECT id, nombre FROM usuarios WHERE estado = 1 ORDER BY nombre")->fetchAll();
$falta = $activa ? flujoCondicionesSalida($pdo, $p) : null;
$sig = $orden < $ultimo ? flujoEtapa($pdo, $orden + 1) : null;
$h = fn($t) => htmlspecialchars((string)$t);
$entActiva = null; $entCompleta = false;
foreach ($entrevistas as $e) { if (in_array($e['estado'], ['Programada','En Curso'], true) && !$entActiva) $entActiva = $e; if ($e['estado'] === 'Completada') $entCompleta = true; }

require_once __DIR__ . '/includes/layout_top.php';
?>
<div class="panel-encabezado">
  <div>
    <h2>Gestión del proceso: <?= $h($p['nombres']) ?></h2>
    <p>DNI <?= $h($p['dni']) ?> · <?= $h($p['puesto']) ?> · <?= $h($p['req']) ?> · Código <?= $h($p['codigo_seguimiento']) ?> ·
       <span class="pr-chip <?= $descartado ? 'mal' : ($p['estado'] === 'Seleccionado' ? 'ok' : 'neu') ?>"><?= $h($p['estado']) ?></span></p>
  </div>
  <div class="acciones-panel"><a class="btn-linea" href="candidatos.php">Volver a candidatos</a></div>
</div>

<?php if ($flashOk): ?><div class="aviso aviso-ok"><?= $h($flashOk) ?></div><?php endif; ?>
<?php if ($flashError): ?><div class="aviso aviso-error"><?= $h($flashError) ?></div><?php endif; ?>

<!-- ===== Flujo ===== -->
<div class="pr-pasos">
  <?php foreach ($etapas as $e): $o = (int)$e['orden'];
    $cl = $o < $orden || ($o === $orden && $p['estado'] === 'Seleccionado') ? 'hecho' : ($o === $orden ? ($descartado ? 'descartado' : 'activo') : 'pend'); ?>
    <div class="pr-paso <?= $cl ?>"><div class="pr-bola"><?= $cl === 'hecho' ? '✓' : ($cl === 'descartado' ? '✕' : $o) ?></div><strong><?= $h($e['nombre']) ?></strong></div>
  <?php endforeach; ?>
</div>

<div class="pr-grid">
<div>
  <!-- ===== Filtros de validación ===== -->
  <section class="pr-card">
    <h3>Filtros de validación</h3>
    <div class="pr-filtro">
      <span class="ic <?= !$test ? 'pen' : ((int)$test['aprobado'] ? 'ok' : 'mal') ?>"><?= !$test ? '…' : ((int)$test['aprobado'] ? '✓' : '✕') ?></span>
      <div class="tx"><strong>1. Evaluación (test)</strong>
        <small><?= !$test ? 'Pendiente: la rinde el postulante en el portal con su código y DNI.' : 'Resultado: ' . (int)$test['puntaje'] . '% (' . ((int)$test['aprobado'] ? 'aprobada' : 'no aprobada') . ').' ?>
        <?php if ($test): ?> <a href="rendir_evaluacion.php?post=<?= $postId ?>">Ver detalle</a><?php endif; ?></small></div>
    </div>
    <div class="pr-filtro">
      <span class="ic <?= $p['puntaje_cv'] === null ? 'mal' : ($cvApto ? 'ok' : 'mal') ?>"><?= $cvApto ? '✓' : '✕' ?></span>
      <div class="tx"><strong>2. CV analizado por el sistema</strong>
        <small><?= $p['puntaje_cv'] === null ? 'Sin CV adjunto: el sistema no pudo analizarlo.' : 'Coincidencia con el requerimiento: ' . (int)$p['puntaje_cv'] . '% (mínimo ' . FLUJO_UMBRAL_CV . '%) → ' . ($cvApto ? 'apto' : 'no apto') . '.' ?>
        <?php if ($p['cv_detalle']): ?> Palabras halladas: <?= $h($p['cv_detalle']) ?>.<?php endif; ?></small></div>
    </div>
    <?php if ($orden === 3 && $activa): ?>
    <form action="guardar_proceso.php" method="POST">
      <input type="hidden" name="post" value="<?= $postId ?>"><input type="hidden" name="accion" value="verificar">
      <div class="pr-filtro"><span class="ic <?= $p['val_datos'] ? 'ok' : 'pen' ?>"><?= $p['val_datos'] ? '✓' : '…' ?></span>
        <div class="tx"><strong>3. Datos del postulante</strong><small>Nombres, DNI, teléfono y correo verificados.</small></div>
        <label><input type="checkbox" name="val_datos" value="1" <?= $p['val_datos'] ? 'checked' : '' ?>> Verificado</label></div>
      <div class="pr-filtro"><span class="ic <?= $p['val_docs'] ? 'ok' : 'pen' ?>"><?= $p['val_docs'] ? '✓' : '…' ?></span>
        <div class="tx"><strong>4. Documentos</strong><small>DNI, CV y demás documentos revisados.</small></div>
        <label><input type="checkbox" name="val_docs" value="1" <?= $p['val_docs'] ? 'checked' : '' ?>> Verificado</label></div>
      <div class="pr-acciones"><button class="btn btn-azul" type="submit">Guardar verificación</button></div>
    </form>
    <?php else: ?>
      <div class="pr-filtro"><span class="ic <?= $p['val_datos'] ? 'ok' : 'pen' ?>"><?= $p['val_datos'] ? '✓' : '…' ?></span><div class="tx"><strong>3. Datos del postulante</strong><small><?= $p['val_datos'] ? 'Verificados.' : 'Sin verificar.' ?></small></div></div>
      <div class="pr-filtro"><span class="ic <?= $p['val_docs'] ? 'ok' : 'pen' ?>"><?= $p['val_docs'] ? '✓' : '…' ?></span><div class="tx"><strong>4. Documentos</strong><small><?= $p['val_docs'] ? 'Verificados.' : 'Sin verificar.' ?></small></div></div>
    <?php endif; ?>
  </section>

  <!-- ===== Siguiente paso ===== -->
  <?php if ($activa && $sig): ?>
  <section class="pr-card <?= $falta ? 'pr-bloqueo' : 'pr-avance' ?>">
    <h3>Siguiente paso: <?= $h($sig['nombre']) ?></h3>
    <?php if ($falta): ?>
      <p class="pr-nota"><strong>Aún no se puede avanzar.</strong> <?= $h($falta) ?></p>
    <?php else: ?>
      <p class="pr-nota">Se cumplen las condiciones de la etapa actual.</p>
      <form action="guardar_proceso.php" method="POST">
        <input type="hidden" name="post" value="<?= $postId ?>"><input type="hidden" name="accion" value="avanzar">
        <div class="pr-form"><div class="full"><label>Observación (opcional)</label><input type="text" name="observacion" maxlength="255"></div></div>
        <div class="pr-acciones"><button class="btn btn-azul" type="submit"><?= $sig['orden'] == $ultimo ? 'Finalizar proceso' : 'Pasar a ' . $h($sig['nombre']) ?></button></div>
      </form>
    <?php endif; ?>
  </section>
  <?php elseif ($p['estado'] === 'Seleccionado'): ?>
  <section class="pr-card pr-avance"><h3>Proceso finalizado</h3><p class="pr-nota">El candidato completó todas las etapas. Lo ve en su seguimiento con el código <?= $h($p['codigo_seguimiento']) ?>.</p></section>
  <?php endif; ?>

  <!-- ===== Descartar ===== -->
  <?php if ($activa): ?>
  <section class="pr-card">
    <h3>Descartar candidato</h3>
    <form action="guardar_proceso.php" method="POST">
      <input type="hidden" name="post" value="<?= $postId ?>"><input type="hidden" name="accion" value="descartar">
      <div class="pr-form">
        <div class="full"><label>Motivo de descarte *</label>
          <select name="motivo_id" required><option value="">Seleccione un motivo...</option>
            <?php foreach ($motivosEtapa as $m): ?><option value="<?= (int)$m['id'] ?>"><?= $h($m['descripcion']) ?></option><?php endforeach; ?></select></div>
        <div class="full"><label>Observación (opcional)</label><input type="text" name="observacion" maxlength="255"></div>
      </div>
      <div class="pr-acciones"><button class="pr-btn mal" type="submit">Descartar</button></div>
    </form>
  </section>
  <?php endif; ?>
</div>

<div>
  <!-- ===== Entrevista presencial ===== -->
  <section class="pr-card">
    <h3>Entrevista presencial</h3>
    <?php if ($entrevistas): ?>
      <table class="pr-lista"><thead><tr><th>Fecha</th><th>Lugar</th><th>Entrevistador</th><th>Estado</th><th></th></tr></thead><tbody>
      <?php foreach ($entrevistas as $e): ?>
        <tr><td><?= date('d/m/Y', strtotime($e['fecha'])) ?> <?= substr($e['hora'], 0, 5) ?></td><td><?= $h($e['sala']) ?></td><td><?= $h($e['entrevistador']) ?></td>
          <td><span class="pr-chip <?= $e['estado'] === 'Completada' ? 'ok' : ($e['estado'] === 'Cancelada' ? 'mal' : 'pen') ?>"><?= $h($e['estado']) ?></span></td>
          <td><?php if ($orden === 4 && $activa && in_array($e['estado'], ['Programada','En Curso'], true)): ?>
            <form action="guardar_proceso.php" method="POST" style="display:inline"><input type="hidden" name="post" value="<?= $postId ?>"><input type="hidden" name="accion" value="entrevista_estado"><input type="hidden" name="entrevista_id" value="<?= (int)$e['id'] ?>">
              <button class="pr-btn ok" name="estado" value="Completada" type="submit">Verificada</button>
              <button class="pr-btn mal" name="estado" value="Cancelada" type="submit">Cancelar</button></form>
          <?php endif; ?></td></tr>
      <?php endforeach; ?></tbody></table>
    <?php else: ?><p class="pr-nota">Aún no se agenda la entrevista.</p><?php endif; ?>
    <?php if ($orden === 4 && $activa && !$entActiva && !$entCompleta): ?>
      <form action="guardar_proceso.php" method="POST">
        <input type="hidden" name="post" value="<?= $postId ?>"><input type="hidden" name="accion" value="agendar_entrevista">
        <div class="pr-form">
          <div><label>Fecha *</label><input type="date" name="fecha" min="<?= date('Y-m-d') ?>" required></div>
          <div><label>Hora *</label><input type="time" name="hora" required></div>
          <div><label>Lugar (presencial) *</label><input type="text" name="lugar" maxlength="80" placeholder="Ej. Sede Magdalena, sala 2" required></div>
          <div><label>Entrevistador</label><select name="entrevistador_id"><?php foreach ($usuarios as $u): ?><option value="<?= (int)$u['id'] ?>" <?= (int)$u['id'] === (int)($_SESSION['usuario_id'] ?? 0) ? 'selected' : '' ?>><?= $h($u['nombre']) ?></option><?php endforeach; ?></select></div>
        </div>
        <div class="pr-acciones"><button class="btn btn-azul" type="submit">Agendar entrevista</button></div>
      </form>
    <?php endif; ?>
  </section>

  <!-- ===== Capacitaciones ===== -->
  <section class="pr-card">
    <h3>Capacitaciones (máximo <?= FLUJO_MAX_CAPACITACIONES ?>)</h3>
    <?php if ($caps): ?>
      <table class="pr-lista"><thead><tr><th>N.°</th><th>Tema</th><th>Fecha</th><th>Asistencia</th><th></th></tr></thead><tbody>
      <?php foreach ($caps as $c): ?>
        <tr><td><?= (int)$c['numero'] ?></td><td><?= $h($c['tema']) ?><br><small><?= $h($c['lugar']) ?></small></td><td><?= date('d/m/Y', strtotime($c['fecha'])) ?> <?= substr($c['hora'], 0, 5) ?></td>
          <td><span class="pr-chip <?= $c['asistencia'] === 'Asistió' ? 'ok' : ($c['asistencia'] === 'No asistió' ? 'mal' : 'pen') ?>"><?= $h($c['asistencia']) ?></span></td>
          <td><?php if ($orden === 5 && $activa && $c['asistencia'] === 'Pendiente'): ?>
            <form action="guardar_proceso.php" method="POST" style="display:inline"><input type="hidden" name="post" value="<?= $postId ?>"><input type="hidden" name="accion" value="asistencia"><input type="hidden" name="capacitacion_id" value="<?= (int)$c['id'] ?>">
              <button class="pr-btn ok" name="asistencia" value="Asistió" type="submit">Asistió</button>
              <button class="pr-btn mal" name="asistencia" value="No asistió" type="submit" onclick="return confirm('Si no asistió, el candidato quedará descartado. ¿Continuar?')">No asistió</button></form>
          <?php endif; ?></td></tr>
      <?php endforeach; ?></tbody></table>
    <?php else: ?><p class="pr-nota">Aún no hay capacitaciones agendadas.</p><?php endif; ?>
    <?php
      $pendiente = false; foreach ($caps as $c) if ($c['asistencia'] !== 'Asistió') $pendiente = true;
      if ($orden === 5 && $activa && count($caps) < FLUJO_MAX_CAPACITACIONES && !$pendiente): ?>
      <form action="guardar_proceso.php" method="POST">
        <input type="hidden" name="post" value="<?= $postId ?>"><input type="hidden" name="accion" value="agendar_capacitacion">
        <div class="pr-form">
          <div class="full"><label>Tema de la capacitación <?= count($caps) + 1 ?> *</label><input type="text" name="tema" maxlength="120" required placeholder="Ej. Inducción y herramientas de atención"></div>
          <div><label>Fecha *</label><input type="date" name="fecha" min="<?= date('Y-m-d') ?>" required></div>
          <div><label>Hora *</label><input type="time" name="hora" required></div>
          <div class="full"><label>Lugar</label><input type="text" name="lugar" maxlength="100" placeholder="Ej. Sede Magdalena, aula 1"></div>
        </div>
        <div class="pr-acciones"><button class="btn btn-azul" type="submit">Agendar capacitación <?= count($caps) + 1 ?></button></div>
      </form>
    <?php elseif ($orden === 5 && $activa && $pendiente): ?><p class="pr-nota">Confirma la asistencia pendiente para poder agendar la siguiente.</p><?php endif; ?>
  </section>
</div>
</div>
<?php require_once __DIR__ . '/includes/layout_bottom.php'; ?>
