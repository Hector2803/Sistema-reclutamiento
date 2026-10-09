<?php
$tituloPagina = 'Reportes';
$activo = 'reportes';
$estilosExtra = ['assets/css/reportes.css'];

require_once __DIR__ . '/includes/guardia.php';
require_once __DIR__ . '/config/conexion.php';
$pdo = obtenerConexion();

/* ===== Datos base ===== */
$totalReq   = (int)$pdo->query("SELECT COUNT(*) c FROM campanas")->fetch()['c'];
$totalPost  = (int)$pdo->query("SELECT COUNT(*) c FROM postulaciones")->fetch()['c'];
$selec      = (int)$pdo->query("SELECT COUNT(*) c FROM postulaciones WHERE estado='Seleccionado'")->fetch()['c'];
$descart    = (int)$pdo->query("SELECT COUNT(*) c FROM postulaciones WHERE estado='Descartado'")->fetch()['c'];
$enproc     = (int)$pdo->query("SELECT COUNT(*) c FROM postulaciones WHERE estado='En Proceso'")->fetch()['c'];
$tasaSel    = $totalPost ? round($selec*100/$totalPost,1) : 0;
$tasaDesc   = $totalPost ? round($descart*100/$totalPost,1) : 0;
$candXReq   = $totalReq ? round($totalPost/$totalReq,2) : 0;

/* ===== KPIs ===== */
$kpis = [
    ['ic'=>'azul',  'titulo'=>'Requerimientos', 'num'=>(string)$totalReq,  'delta'=>''],
    ['ic'=>'verde', 'titulo'=>'Candidatos',     'num'=>(string)$totalPost, 'delta'=>''],
    ['ic'=>'cian',  'titulo'=>'En Proceso',     'num'=>(string)$enproc,    'delta'=>''],
    ['ic'=>'morado','titulo'=>'Seleccionados',  'num'=>(string)$selec,     'delta'=>''],
    ['ic'=>'ambar', 'titulo'=>'Tasa de Selección','num'=>$tasaSel.'%',     'delta'=>''],
];

/* ===== Requerimientos por Estado (donut) ===== */
$reqEstado = [];
$colEst = ['Abierto'=>'#2f6fed','En Proceso'=>'#f5a623','Cerrado'=>'#22a06b'];
foreach (['Abierto','En Proceso','Cerrado'] as $es) {
    $n = (int)$pdo->query("SELECT COUNT(*) c FROM campanas WHERE estado=".$pdo->quote($es))->fetch()['c'];
    $pct = $totalReq ? round($n*100/$totalReq,1) : 0;
    if ($n>0) $reqEstado[] = [$es,$n,$pct,$colEst[$es]];
}
if (!$reqEstado) $reqEstado[] = ['Sin datos',1,100,'#e6eaf1'];

/* ===== Candidatos por Estado (donut) ===== */
$candEstado = [];
$mapCE = [['En Proceso',$enproc,'#2f6fed'],['Seleccionado',$selec,'#22a06b'],['Descartado',$descart,'#e30613']];
foreach ($mapCE as $x) {
    $pct = $totalPost ? round($x[1]*100/$totalPost,1) : 0;
    if ($x[1]>0) $candEstado[] = [$x[0],$x[1],$pct,$x[2]];
}
if (!$candEstado) $candEstado[] = ['Sin datos',1,100,'#e6eaf1'];

/* ===== EMBUDO: candidatos que alcanzaron cada etapa ===== */
$etapasOrd = $pdo->query("SELECT id,nombre,orden FROM etapas ORDER BY orden")->fetchAll();
$funnel = [];
$colFun = ['#0b3d91','#1e56c0','#3b74d6','#6499e2','#9cc0ef','#a9c4ea','#f0a37a','#e30613'];
$base = 0;
foreach ($etapasOrd as $i=>$e) {
    $n = (int)$pdo->query(
        "SELECT COUNT(DISTINCT postulacion_id) c FROM historial_etapas WHERE etapa_destino=".(int)$e['id'])->fetch()['c'];
    if ($i===0) $base = $n;
    $pct = $base ? round($n*100/$base,1) : 0;
    $w   = max(30, (int)round($pct));
    $funnel[] = [$e['nombre'], $n, $pct, $colFun[$i % count($colFun)], $w];
}

/* ===== Motivos de descarte (barras) ===== */
$motivosDesc = $pdo->query(
    "SELECT m.descripcion, COUNT(*) c
     FROM historial_etapas h INNER JOIN motivos_descarte m ON m.id=h.motivo_id
     GROUP BY m.id ORDER BY c DESC LIMIT 6")->fetchAll();
$totalDesc = array_sum(array_map(fn($x)=>(int)$x['c'], $motivosDesc));
$fuentes = [];
foreach ($motivosDesc as $md) {
    $pct = $totalDesc ? round($md['c']*100/$totalDesc) : 0;
    $fuentes[] = [$md['descripcion'], $pct];
}

/* ===== Resumen general ===== */
$resumen = [
    ['users','Candidatos por requerimiento', (string)$candXReq],
    ['check','Tasa de selección',            $tasaSel.'%'],
    ['reloj','Tasa de descarte',             $tasaDesc.'%'],
    ['users','Total descartados',            (string)$descart],
];

/* ===== Evolución: candidatos registrados por día (últimos 8 días) ===== */
$dias = [];
for ($i=7;$i>=0;$i--) $dias[date('Y-m-d', strtotime("-$i day"))] = 0;
$reg = $pdo->query(
    "SELECT DATE(fecha_postulacion) d, COUNT(*) c FROM postulaciones
     WHERE fecha_postulacion >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
     GROUP BY DATE(fecha_postulacion)")->fetchAll();
foreach ($reg as $r) { if (isset($dias[$r['d']])) $dias[$r['d']] = (int)$r['c']; }
$series = [ ['Candidatos registrados', '#2f6fed', array_values($dias)] ];
$ejeX = array_map(fn($d)=>date('d/m', strtotime($d)), array_keys($dias));

/* ---------- Helpers de gráficas ---------- */
function donutSVG(array $datos): string {
    $r=52;$cx=$cy=66;$sw=20;$circ=2*M_PI*$r;$off=0;$s='';
    foreach ($datos as $d){ [$et,$n,$pct,$col]=$d;
        $largo=($pct/100)*$circ;$gap=$circ-$largo;
        $s.='<circle cx="'.$cx.'" cy="'.$cy.'" r="'.$r.'" fill="none" stroke="'.$col.'" stroke-width="'.$sw.'" stroke-dasharray="'.round($largo,2).' '.round($gap,2).'" stroke-dashoffset="'.round(-$off,2).'" transform="rotate(-90 '.$cx.' '.$cy.')"/>';
        $off+=$largo;
    } return $s;
}
function lineChart(array $series, array $ejeX): string {
    $W=560;$H=230;$pl=34;$pr=12;$pt=12;$pb=26;
    $areaW=$W-$pl-$pr;$areaH=$H-$pt-$pb;
    // Máximo dinámico según los datos (mínimo 5 para que no quede plano)
    $max=1;
    foreach($series as $s){ foreach($s[2] as $v){ if($v>$max) $max=$v; } }
    $max=max(5,(int)ceil($max*1.2));
    $paso=$areaW/max(1,count($series[0][2])-1);
    $svg='<svg class="grafica-lineas" viewBox="0 0 '.$W.' '.$H.'" preserveAspectRatio="none">';
    $pasoY = $max/5;
    for($k=0;$k<=5;$k++){
        $v=$k*$pasoY;
        $y=$pt+$areaH-($v/$max)*$areaH;
        $svg.='<line x1="'.$pl.'" y1="'.round($y,1).'" x2="'.($W-$pr).'" y2="'.round($y,1).'" stroke="#eef1f6" stroke-width="1"/>';
        $svg.='<text x="'.($pl-8).'" y="'.round($y+3,1).'" font-size="9" fill="#8a94a6" text-anchor="end">'.round($v).'</text>';
    }
    $labs=count($ejeX);
    foreach($ejeX as $i=>$lab){
        $x=$pl+($i/max(1,$labs-1))*$areaW;
        $svg.='<text x="'.round($x,1).'" y="'.($H-8).'" font-size="9" fill="#8a94a6" text-anchor="middle">'.$lab.'</text>';
    }
    foreach($series as $s){ [$nom,$col,$datos]=$s;$pts=[];
        foreach($datos as $i=>$val){$x=$pl+$i*$paso;$y=$pt+$areaH-($val/$max)*$areaH;$pts[]=round($x,1).','.round($y,1);}
        $svg.='<polyline points="'.implode(' ',$pts).'" fill="none" stroke="'.$col.'" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"/>';
        foreach($datos as $i=>$val){$x=$pl+$i*$paso;$y=$pt+$areaH-($val/$max)*$areaH;$svg.='<circle cx="'.round($x,1).'" cy="'.round($y,1).'" r="2.4" fill="'.$col.'"/>';}
    }
    return $svg.'</svg>';
}

require_once __DIR__ . '/includes/layout_top.php';
?>

<!-- ===== Encabezado ===== -->
<div class="panel-encabezado">
  <div>
    <h2>Reportes</h2>
    <p>Analiza y visualiza el rendimiento de tus procesos de reclutamiento.</p>
  </div>
  <div class="acciones-panel">
    <div class="rango-fecha">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
      01/05/2024 - 31/05/2024
      <svg class="chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
    </div>
    <button class="btn btn-azul">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
      Exportar Reporte
    </button>
  </div>
</div>

<!-- ===== KPIs ===== -->
<div class="rep-kpis">
  <?php foreach ($kpis as $k): ?>
    <div class="rep-kpi">
      <div class="cab">
        <span class="ic <?= $k['ic'] ?>">
          <?php if($k['ic']==='azul'): ?><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 2h6a1 1 0 0 1 1 1v1h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2V3a1 1 0 0 1 1-1z"/></svg>
          <?php elseif($k['ic']==='verde'): ?><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
          <?php elseif($k['ic']==='morado'): ?><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
          <?php elseif($k['ic']==='ambar'): ?><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 22h14M5 2h14M6 2v4a6 6 0 0 0 12 0V2M6 22v-4a6 6 0 0 1 12 0v4"/></svg>
          <?php else: ?><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg><?php endif; ?>
        </span>
        <span class="titulo"><?= htmlspecialchars($k['titulo']) ?></span>
      </div>
      <div class="num"><?= htmlspecialchars($k['num']) ?></div>
      <?php if($k['delta']!==''): ?><div class="delta"><?= htmlspecialchars($k['delta']) ?> <span>vs periodo anterior</span></div><?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>

<!-- ===== Fila 1: donut / embudo / donut ===== -->
<div class="rep-fila3">

  <section class="tarjeta">
    <h3 style="margin-bottom:16px">Requerimientos por Estado</h3>
    <div class="donut-wrap">
      <div class="donut-box">
        <svg viewBox="0 0 132 132"><?= donutSVG($reqEstado) ?></svg>
        <div class="donut-centro"><span class="n"><?= $totalReq ?></span><span class="t">Total</span></div>
      </div>
      <div class="donut-leyenda2">
        <?php foreach($reqEstado as $d): [$et,$n,$pct,$col]=$d; ?>
          <div class="leyenda-item2"><span class="punto" style="background:<?= $col ?>"></span><span class="et"><?= $et ?></span><span class="vv"><?= $n ?> (<?= $pct ?>%)</span></div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="tarjeta">
    <h3 style="margin-bottom:16px">Candidatos por Etapa</h3>
    <div class="funnel">
      <div class="funnel-graf">
        <?php foreach($funnel as $i=>$f): [$et,$n,$pct,$col,$w]=$f; ?>
          <div class="funnel-seg" style="background:<?= $col ?>;width:<?= $w ?>%"></div>
        <?php endforeach; ?>
      </div>
      <div class="funnel-lista">
        <?php foreach($funnel as $f): [$et,$n,$pct,$col,$w]=$f; ?>
          <div class="fl"><span class="nom"><?= $et ?></span><span class="val"><?= $n ?> <em>(<?= $pct ?>%)</em></span></div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="tarjeta">
    <h3 style="margin-bottom:16px">Candidatos por Estado</h3>
    <div class="donut-wrap">
      <div class="donut-box">
        <svg viewBox="0 0 132 132"><?= donutSVG($candEstado) ?></svg>
        <div class="donut-centro"><span class="n"><?= $totalPost ?></span><span class="t">Total</span></div>
      </div>
      <div class="donut-leyenda2">
        <?php foreach($candEstado as $d): [$et,$n,$pct,$col]=$d; ?>
          <div class="leyenda-item2"><span class="punto" style="background:<?= $col ?>"></span><span class="et"><?= $et ?></span><span class="vv"><?= $n ?> (<?= $pct ?>%)</span></div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
</div>

<!-- ===== Fila 2: fuentes / evolución / resumen ===== -->
<div class="rep-fila3">

  <section class="tarjeta">
    <h3 style="margin-bottom:16px">Motivos de Descarte</h3>
    <?php if (!$fuentes): ?>
      <p style="font-size:13px;color:var(--texto-3)">Aún no hay candidatos descartados registrados.</p>
    <?php endif; ?>
    <?php foreach($fuentes as $f): [$nom,$pc]=$f; ?>
      <div class="rep-fuente">
        <div class="top"><span class="nom"><?= $nom ?></span><span class="pc"><?= $pc ?>%</span></div>
        <div class="rep-barra"><div style="width:<?= $pc ?>%"></div></div>
      </div>
    <?php endforeach; ?>
  </section>

  <section class="tarjeta">
    <div class="linea-cab" style="justify-content:space-between">
      <h3>Evolución de Actividad</h3>
      <div class="filtro-fecha" style="padding:6px 10px">Diario
        <svg class="chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
      </div>
    </div>
    <div class="linea-leyenda">
      <?php foreach($series as $s): [$nom,$col,$d]=$s; ?>
        <span><span class="pt" style="background:<?= $col ?>"></span><?= $nom ?></span>
      <?php endforeach; ?>
    </div>
    <?= lineChart($series, $ejeX) ?>
  </section>

  <section class="tarjeta">
    <h3 style="margin-bottom:6px">Resumen General</h3>
    <?php foreach($resumen as $r): [$ico,$txt,$val]=$r; ?>
      <div class="resumen-item">
        <span class="ri-ic">
          <?php if($ico==='reloj'): ?><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
          <?php elseif($ico==='check'): ?><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
          <?php else: ?><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg><?php endif; ?>
        </span>
        <span class="ri-tx"><?= $txt ?></span>
        <span class="ri-val"><?= $val ?></span>
      </div>
    <?php endforeach; ?>
  </section>
</div>

<?php require_once __DIR__ . '/includes/layout_bottom.php'; ?>
