<?php
$tituloPagina = 'Panel de Control General';
$activo = 'dashboard';

require_once __DIR__ . '/includes/guardia.php';
require_once __DIR__ . '/config/conexion.php';
$pdo = obtenerConexion();

/* ---- KPIs reales ---- */
$vacTotales = (int)$pdo->query("SELECT COUNT(*) c FROM campanas WHERE estado <> 'Cerrado'")->fetch()['c'];
$enProceso  = (int)$pdo->query("SELECT COUNT(*) c FROM postulaciones WHERE estado='En Proceso'")->fetch()['c'];
$totalPost  = (int)$pdo->query("SELECT COUNT(*) c FROM postulaciones")->fetch()['c'];
$selec      = (int)$pdo->query("SELECT COUNT(*) c FROM postulaciones WHERE estado='Seleccionado'")->fetch()['c'];
$descart    = (int)$pdo->query("SELECT COUNT(*) c FROM postulaciones WHERE estado='Descartado'")->fetch()['c'];
$tasaAprob  = $totalPost ? round($selec * 100 / $totalPost, 1) : 0;
$tasaCaida  = $totalPost ? round($descart * 100 / $totalPost, 1) : 0;

$kpis = [
    ['titulo'=>'Vacantes Activas',      'valor'=>(string)$vacTotales, 'sub'=>'Requerimientos abiertos', 'color'=>'azul'],
    ['titulo'=>'Candidatos en Proceso', 'valor'=>(string)$enProceso,  'sub'=>'En el embudo',           'color'=>'azul'],
    ['titulo'=>'Tasa de Selección',     'valor'=>$tasaAprob.'%',      'sub'=>'Seleccionados',          'color'=>'verde'],
    ['titulo'=>'Tasa de Descarte',      'valor'=>$tasaCaida.'%',      'sub'=>'Candidatos descartados', 'color'=>'rojo'],
];

/* ---- Actividad reciente (desde el historial de etapas) ---- */
$actividad = [];
$rows = $pdo->query(
    "SELECT h.fecha, h.motivo_id,
            po.nombres, c.puesto,
            eo.nombre AS etapa_ori, ed.nombre AS etapa_dest,
            m.descripcion AS motivo
     FROM historial_etapas h
     INNER JOIN postulaciones p ON p.id = h.postulacion_id
     INNER JOIN postulantes   po ON po.id = p.postulante_id
     INNER JOIN campanas      c  ON c.id = p.campana_id
     LEFT  JOIN etapas eo ON eo.id = h.etapa_origen
     LEFT  JOIN etapas ed ON ed.id = h.etapa_destino
     LEFT  JOIN motivos_descarte m ON m.id = h.motivo_id
     ORDER BY h.id DESC LIMIT 6")->fetchAll();
foreach ($rows as $r) {
    if ($r['motivo_id']) {
        $actividad[] = ['ico'=>'alerta','titulo'=>'Candidato descartado','desc'=>$r['nombres'].' – '.$r['motivo'],'hora'=>date('d/m H:i',strtotime($r['fecha']))];
    } elseif ($r['etapa_ori'] === null) {
        $actividad[] = ['ico'=>'cand','titulo'=>'Nuevo candidato registrado','desc'=>$r['nombres'].' – '.$r['puesto'],'hora'=>date('d/m H:i',strtotime($r['fecha']))];
    } else {
        $actividad[] = ['ico'=>'ok','titulo'=>'Cambio de etapa: '.$r['etapa_dest'],'desc'=>$r['nombres'],'hora'=>date('d/m H:i',strtotime($r['fecha']))];
    }
}

/* ---- Vacantes recientes (con conteo de candidatos) ---- */
$vacantes = [];
$vr = $pdo->query(
    "SELECT c.puesto, c.prioridad, c.estado, a.nombre AS area,
            (SELECT COUNT(*) FROM postulaciones p WHERE p.campana_id=c.id) AS cand
     FROM campanas c INNER JOIN areas a ON a.id=c.area_id
     ORDER BY c.creado_en DESC LIMIT 5")->fetchAll();
foreach ($vr as $v) {
    $vacantes[] = ['puesto'=>$v['puesto'],'cliente'=>$v['area'],'cand'=>(int)$v['cand'],
                   'etapa'=>$v['estado'],'prio'=>$v['prioridad'],'estado'=>$v['estado']];
}

require_once __DIR__ . '/includes/layout_top.php';
?>

<!-- ===== Encabezado del panel + acciones ===== -->
<div class="panel-encabezado">
  <div>
    <h2>Panel de Control General</h2>
    <p>Monitorea el estado de tus procesos de reclutamiento en tiempo real.</p>
  </div>
  <div class="acciones-panel">
    <a class="btn btn-rojo" href="requerimientos.php">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Nuevo Requerimiento
    </a>
    <a class="btn btn-azul" href="candidatos.php">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
      Registrar Candidato
    </a>
  </div>
</div>

<!-- ===== Tarjetas KPI ===== -->
<div class="grid-kpis">
  <?php foreach ($kpis as $k): ?>
    <div class="kpi">
      <div class="kpi-top">
        <span class="kpi-titulo"><?= htmlspecialchars($k['titulo']) ?></span>
        <span class="kpi-icono <?= $k['color'] ?>">
          <?php if ($k['color']==='rojo'): ?>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
          <?php elseif ($k['color']==='verde'): ?>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
          <?php elseif ($k['titulo']==='Candidatos en Proceso'): ?>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
          <?php else: ?>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
          <?php endif; ?>
        </span>
      </div>
      <div class="kpi-valor"><?= htmlspecialchars($k['valor']) ?></div>
      <div class="kpi-sub"><?= htmlspecialchars($k['sub']) ?></div>
    </div>
  <?php endforeach; ?>
</div>

<!-- ===== Fila: Vacantes recientes + Actividad reciente ===== -->
<div class="grid-medio">

  <!-- Vacantes recientes -->
  <section class="tarjeta vacantes">
    <div class="tarjeta-cabecera">
      <h3>Vacantes Recientes</h3>
      <a href="requerimientos.php" class="ver-todas">Ver todas</a>
    </div>

    <table class="tabla">
      <thead>
        <tr>
          <th>Puesto</th><th>Cliente</th><th>Candidatos</th>
          <th>Etapa Actual</th><th>Prioridad</th><th>Estado</th><th></th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$vacantes): ?>
          <tr><td colspan="7" style="text-align:center;color:var(--texto-3);padding:22px">Aún no hay requerimientos registrados.</td></tr>
        <?php endif; ?>
        <?php foreach ($vacantes as $v): ?>
          <tr>
            <td class="td-puesto"><?= htmlspecialchars($v['puesto']) ?></td>
            <td><?= htmlspecialchars($v['cliente']) ?></td>
            <td><?= $v['cand'] ?></td>
            <td><span class="chip-etapa"><?= htmlspecialchars($v['etapa']) ?></span></td>
            <td><span class="chip-prio <?= strtolower($v['prio']) ?>"><?= $v['prio'] ?></span></td>
            <td><span class="chip-estado"><?= htmlspecialchars($v['estado']) ?></span></td>
            <td class="td-menu">
              <svg viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="12" cy="19" r="1.6"/></svg>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <div class="tabla-pie">
      <span>Mostrando 1 a 5 de 12 vacantes</span>
      <div class="paginacion">
        <button class="pag" aria-label="Anterior">‹</button>
        <button class="pag activa">1</button>
        <button class="pag">2</button>
        <button class="pag">3</button>
        <button class="pag" aria-label="Siguiente">›</button>
      </div>
    </div>
  </section>

  <!-- Actividad reciente -->
  <section class="tarjeta actividad">
    <div class="tarjeta-cabecera">
      <h3>Actividad reciente</h3>
      <a href="#" class="ver-todas">Ver todas</a>
    </div>

    <ul class="lista-actividad">
      <?php if (!$actividad): ?>
        <li><div class="act-texto"><span>Sin actividad reciente todavía.</span></div></li>
      <?php endif; ?>
      <?php foreach ($actividad as $a): ?>
        <li>
          <span class="act-ico <?= $a['ico'] ?>">
            <?php if ($a['ico']==='req'): ?>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
            <?php elseif ($a['ico']==='cand'): ?>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
            <?php elseif ($a['ico']==='cal'): ?>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            <?php elseif ($a['ico']==='ok'): ?>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            <?php else: ?>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            <?php endif; ?>
          </span>
          <div class="act-texto">
            <strong><?= htmlspecialchars($a['titulo']) ?></strong>
            <span><?= htmlspecialchars($a['desc']) ?></span>
          </div>
          <span class="act-hora"><?= htmlspecialchars($a['hora']) ?></span>
        </li>
      <?php endforeach; ?>
    </ul>

    <a href="#" class="btn-ver-actividades">Ver todas las actividades →</a>
  </section>
</div>

<?php require_once __DIR__ . '/includes/layout_bottom.php'; ?>
