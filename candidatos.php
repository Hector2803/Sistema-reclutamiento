<?php
$tituloPagina = 'Gestión de Candidatos';
$activo = 'candidatos';
$estilosExtra = ['assets/css/candidatos.css', 'assets/css/usuarios.css', 'assets/css/evaluaciones.css'];

require_once __DIR__ . '/includes/guardia.php';
require_once __DIR__ . '/config/conexion.php';
$pdo = obtenerConexion();

/* Mensajes flash */
$flashOk    = $_SESSION['flash_ok']    ?? '';
$flashError = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_ok'], $_SESSION['flash_error']);

/* ---- Filtros recibidos (HU-09 / HU-11) ---- */
$fBuscar = trim($_GET['q'] ?? '');
$fVacante = $_GET['vacante'] ?? '';
$fEtapa  = $_GET['etapa'] ?? '';
$fEstado = $_GET['estado'] ?? '';
$fRecl   = $_GET['reclutador'] ?? '';
$fDesde  = $_GET['desde'] ?? '';
$fHasta  = $_GET['hasta'] ?? '';
// RF-11: filtro por rango de fecha de postulación. Las fechas se validan
// con un patrón estricto antes de usarse en la consulta.
if ($fDesde !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fDesde)) { $fDesde = ''; }
if ($fHasta !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fHasta)) { $fHasta = ''; }

/* ---- Alcance por rol (HU-11) ----
   Reclutador: solo ve SUS candidatos.
   Supervisor / Administrador: ven a todo el equipo y pueden filtrar por reclutador. */
$rolSesion  = $_SESSION['usuario_rol'] ?? '';
$esSupervisor = in_array($rolSesion, ['Supervisor','Administrador']);
$usuarioSes = (int)($_SESSION['usuario_id'] ?? 0);

/* ---- Postulaciones reales: postulante + campaña + etapa + reclutador ---- */
$sql =
    "SELECT p.id, p.codigo_seguimiento, p.estado, p.fecha_postulacion,
            po.dni, po.nombres, po.correo, po.telefono,
            c.puesto AS vacante,
            e.nombre AS etapa, e.color AS etapa_color,
            p.etapa_id,
            p.puntaje_cv,
            u.nombre AS reclutador,
            (SELECT r1.puntaje  FROM resultados_eval r1 WHERE r1.postulacion_id = p.id ORDER BY r1.id DESC LIMIT 1) AS test_puntaje,
            (SELECT r2.aprobado FROM resultados_eval r2 WHERE r2.postulacion_id = p.id ORDER BY r2.id DESC LIMIT 1) AS test_aprobado
     FROM postulaciones p
     INNER JOIN postulantes po ON po.id = p.postulante_id
     INNER JOIN campanas   c  ON c.id  = p.campana_id
     INNER JOIN etapas     e  ON e.id  = p.etapa_id
     LEFT  JOIN usuarios   u  ON u.id  = p.reclutador_id
     WHERE 1=1";
$params = [];
if (!$esSupervisor) {                       // reclutador: solo lo suyo
    $sql .= " AND p.reclutador_id = :yo";
    $params[':yo'] = $usuarioSes;
} elseif ($fRecl !== '' && ctype_digit($fRecl)) {   // supervisor filtra por reclutador
    $sql .= " AND p.reclutador_id = :rec";
    $params[':rec'] = (int)$fRecl;
}
if ($fBuscar !== '') {
    // Parámetros distintos: con consultas preparadas nativas no se puede repetir el mismo nombre
    $sql .= " AND (po.nombres LIKE :b1 OR po.dni LIKE :b2 OR po.correo LIKE :b3 OR po.telefono LIKE :b4)";
    $like = '%' . $fBuscar . '%';
    $params[':b1'] = $like; $params[':b2'] = $like; $params[':b3'] = $like; $params[':b4'] = $like;
}
if ($fVacante !== '' && ctype_digit($fVacante)) { $sql .= " AND c.id = :vac"; $params[':vac'] = (int)$fVacante; }
if ($fEtapa   !== '' && ctype_digit($fEtapa))   { $sql .= " AND p.etapa_id = :et"; $params[':et'] = (int)$fEtapa; }
if (in_array($fEstado, ['En Proceso','Seleccionado','Descartado'])) { $sql .= " AND p.estado = :es"; $params[':es'] = $fEstado; }
if ($fDesde !== '') { $sql .= " AND p.fecha_postulacion >= :desde"; $params[':desde'] = $fDesde . ' 00:00:00'; }
if ($fHasta !== '') { $sql .= " AND p.fecha_postulacion <= :hasta"; $params[':hasta'] = $fHasta . ' 23:59:59'; }
$sql .= " ORDER BY p.fecha_postulacion DESC";
$st = $pdo->prepare($sql);
$st->execute($params);
$candidatos = $st->fetchAll();

$hayFiltros = ($fBuscar!=='' || $fVacante!=='' || $fEtapa!=='' || $fEstado!=='' || $fRecl!=='' || $fDesde!=='' || $fHasta!=='');

/* ---- Lista de reclutadores y carga de trabajo (solo supervisor – HU-11) ---- */
$reclutadores = [];
$cargaEquipo  = [];
if ($esSupervisor) {
    $reclutadores = $pdo->query(
        "SELECT u.id, u.nombre FROM usuarios u INNER JOIN roles r ON r.id=u.rol_id
         WHERE r.nombre='Reclutador' AND u.estado=1 ORDER BY u.nombre")->fetchAll();
    $cargaEquipo = $pdo->query(
        "SELECT u.nombre, r.nombre AS rol,
                SUM(p.estado='En Proceso')   AS proceso,
                SUM(p.estado='Seleccionado') AS selec,
                SUM(p.estado='Descartado')   AS descart,
                COUNT(p.id) AS total
         FROM usuarios u
         INNER JOIN roles r ON r.id=u.rol_id
         LEFT JOIN postulaciones p ON p.reclutador_id=u.id
         WHERE r.nombre IN ('Reclutador','Supervisor','Administrador') AND u.estado=1
         GROUP BY u.id, u.nombre, r.nombre
         HAVING total > 0 OR rol = 'Reclutador'
         ORDER BY total DESC")->fetchAll();
}

/* ---- Campañas y orígenes para el formulario y filtros ---- */
$campanas = $pdo->query("SELECT id, codigo, puesto FROM campanas ORDER BY creado_en DESC")->fetchAll();
$origenes = $pdo->query("SELECT id, nombre FROM origenes ORDER BY id")->fetchAll();

/* ---- Etapas y motivos de descarte (para mover de etapa – HU-06/07) ---- */
$etapas  = $pdo->query("SELECT id, nombre FROM etapas ORDER BY orden")->fetchAll();
$motivos = $pdo->query("SELECT id, descripcion, etapa_id FROM motivos_descarte ORDER BY id")->fetchAll();

/* ---- KPIs (supervisor: todo el equipo · reclutador: solo lo suyo) ---- */
$alc = $esSupervisor ? '' : ' AND reclutador_id = ' . $usuarioSes;
$gTotal  = (int)$pdo->query("SELECT COUNT(*) c FROM postulaciones WHERE 1=1$alc")->fetch()['c'];
$gProc   = (int)$pdo->query("SELECT COUNT(*) c FROM postulaciones WHERE estado='En Proceso'$alc")->fetch()['c'];
$gSelec  = (int)$pdo->query("SELECT COUNT(*) c FROM postulaciones WHERE estado='Seleccionado'$alc")->fetch()['c'];
$gDesc   = (int)$pdo->query("SELECT COUNT(*) c FROM postulaciones WHERE estado='Descartado'$alc")->fetch()['c'];
$total   = count($candidatos);   // filtrados (para la tabla y paginación)
$pct = fn($n) => $gTotal ? round($n * 100 / $gTotal, 1) . '% del total' : '0% del total';
$kpis = [
    ['ic'=>'azul', 'titulo'=>'Total Candidatos','num'=>(string)$gTotal, 'pie'=>'100% del total'],
    ['ic'=>'ambar','titulo'=>'En Proceso',      'num'=>(string)$gProc,  'pie'=>$pct($gProc)],
    ['ic'=>'verde','titulo'=>'Seleccionados',   'num'=>(string)$gSelec, 'pie'=>$pct($gSelec)],
    ['ic'=>'rojo', 'titulo'=>'Descartados',     'num'=>(string)$gDesc,  'pie'=>$pct($gDesc)],
];

$puedeRegistrar = in_array(($_SESSION['usuario_rol'] ?? ''), ['Administrador','Supervisor','Reclutador']);

// Iniciales para el avatar
function iniciales(string $nombre): string {
    $p = preg_split('/\s+/', trim($nombre));
    return mb_strtoupper(mb_substr($p[0] ?? '', 0, 1) . mb_substr($p[1] ?? '', 0, 1));
}
function colorAvatar(string $txt): string {
    $cols = ['#3b82f6','#8b5cf6','#0ea5a4','#ec4899','#f59e0b','#22a06b','#d946ef','#6366f1'];
    $s = 0; foreach (str_split($txt) as $ch) { $s += ord($ch); }
    return $cols[$s % count($cols)];
}
// Chip de etapa con el color propio de la etapa (fondo tenue + texto del color)
function chipEtapa(string $nombre, string $hex): string {
    $hex = ltrim($hex, '#');
    $r = hexdec(substr($hex,0,2)); $g = hexdec(substr($hex,2,2)); $b = hexdec(substr($hex,4,2));
    return '<span class="chip" style="background:rgba('.$r.','.$g.','.$b.',.12);color:'.'#'.$hex.'">'.htmlspecialchars($nombre).'</span>';
}
function chipEstado(string $estado): string {
    $m = ['En Proceso'=>'es-proceso','Seleccionado'=>'es-seleccionado','Descartado'=>'es-descartado'];
    $cl = $m[$estado] ?? 'es-proceso';
    return '<span class="chip '.$cl.'">'.htmlspecialchars($estado).'</span>';
}
function fechaAplic(string $dt): array {
    $ts = strtotime($dt);
    return [date('d/m/Y', $ts), date('h:i A', $ts)];
}

require_once __DIR__ . '/includes/layout_top.php';
?>

<!-- ===== Encabezado ===== -->
<div class="panel-encabezado">
  <div>
    <h2>Gestión de Candidatos</h2>
    <p>Administra y da seguimiento a todos los candidatos del proceso de reclutamiento.</p>
  </div>
  <div class="acciones-panel">
    <button class="btn-linea">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
      Exportar
    </button>
    <button class="btn btn-azul" onclick="document.getElementById('modalPostulante').classList.add('abierto')">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Registrar Candidato
    </button>
  </div>
</div>

<?php if ($flashOk): ?><div class="aviso aviso-ok"><?= htmlspecialchars($flashOk) ?></div><?php endif; ?>
<?php if ($flashError): ?><div class="aviso aviso-error"><?= htmlspecialchars($flashError) ?></div><?php endif; ?>

<!-- ===== KPIs ===== -->
<div class="grid-kpis-cand">
  <?php foreach ($kpis as $k): ?>
    <div class="kpi-cand">
      <span class="ic <?= $k['ic'] ?>">
        <?php if ($k['ic']==='azul'): ?>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        <?php elseif ($k['ic']==='ambar'): ?>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 22h14M5 2h14M6 2v4a6 6 0 0 0 12 0V2M6 22v-4a6 6 0 0 1 12 0v4"/></svg>
        <?php elseif ($k['ic']==='verde'): ?>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        <?php else: ?>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
        <?php endif; ?>
      </span>
      <div class="txt">
        <small><?= htmlspecialchars($k['titulo']) ?></small>
        <div class="num"><?= htmlspecialchars($k['num']) ?></div>
        <div class="pie"><?= htmlspecialchars($k['pie']) ?></div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<!-- ===== Toggle Lista / Tablero ===== -->
<div class="barra-vista">
  <div class="vista-toggle">
    <a href="candidatos.php" class="on"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>Lista</a>
    <a href="kanban.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="18" rx="1"/><rect x="14" y="3" width="7" height="11" rx="1"/></svg>Tablero</a>
  </div>
</div>

<!-- ===== Dos columnas ===== -->
<div class="cand-layout">

  <!-- Columna izquierda: filtros + tabla -->
  <div>
    <!-- Filtros -->
    <form method="GET" class="filtros-cand">
      <div class="filtro-buscar">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input type="text" name="q" value="<?= htmlspecialchars($fBuscar) ?>" placeholder="Buscar candidato por nombre, DNI, email, teléfono...">
      </div>
      <div class="filtro-campo">
        <label>Vacante</label>
        <select name="vacante">
          <option value="">Todas</option>
          <?php foreach ($campanas as $cp): ?>
            <option value="<?= $cp['id'] ?>" <?= ($fVacante==$cp['id']?'selected':'') ?>><?= htmlspecialchars($cp['puesto']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="filtro-campo">
        <label>Etapa</label>
        <select name="etapa">
          <option value="">Todas</option>
          <?php foreach ($etapas as $et): ?>
            <option value="<?= $et['id'] ?>" <?= ($fEtapa==$et['id']?'selected':'') ?>><?= htmlspecialchars($et['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="filtro-campo">
        <label>Estado</label>
        <select name="estado">
          <option value="">Todos</option>
          <?php foreach (['En Proceso','Seleccionado','Descartado'] as $es): ?>
            <option <?= ($fEstado===$es?'selected':'') ?>><?= $es ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php if ($esSupervisor): ?>
      <div class="filtro-campo">
        <label>Reclutador</label>
        <select name="reclutador">
          <option value="">Todo el equipo</option>
          <?php foreach ($reclutadores as $rc): ?>
            <option value="<?= $rc['id'] ?>" <?= ($fRecl==$rc['id']?'selected':'') ?>><?= htmlspecialchars($rc['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>
      <div class="filtro-campo">
        <label>Desde</label>
        <input type="date" name="desde" value="<?= htmlspecialchars($fDesde) ?>">
      </div>
      <div class="filtro-campo">
        <label>Hasta</label>
        <input type="date" name="hasta" value="<?= htmlspecialchars($fHasta) ?>">
      </div>
      <button class="btn-filtros" type="submit">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
        Filtrar
      </button>
      <a class="btn-limpiar" href="candidatos.php">Limpiar</a>
    </form>

    <!-- Tabla -->
    <section class="tarjeta" style="padding:8px 18px 18px">
      <table class="tabla-cand">
        <thead>
          <tr>
            <th class="col-check"><input type="checkbox"></th>
            <th>CANDIDATO</th><th>VACANTE</th><th>CV</th><th>ETAPA ACTUAL</th>
            <th>ESTADO</th><th>FECHA APLICACIÓN</th><th>ACCIONES</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$candidatos): ?>
            <tr><td colspan="8" style="text-align:center;color:var(--texto-3);padding:28px">
              <?php if ($hayFiltros): ?>
                No se encontraron candidatos con los filtros aplicados. <a href="candidatos.php" style="color:var(--azul);font-weight:600">Limpiar filtros</a>
              <?php else: ?>
                Aún no hay postulantes registrados. Usa <strong>Registrar Candidato</strong> para agregar el primero (por DNI).
              <?php endif; ?>
            </td></tr>
          <?php endif; ?>
          <?php foreach ($candidatos as $c):
            [$fecha,$hora] = fechaAplic($c['fecha_postulacion']);
            $color = colorAvatar($c['dni']); ?>
            <tr>
              <td class="col-check"><input type="checkbox"></td>
              <td>
                <div class="cand-persona">
                  <span class="cand-avatar" style="background:<?= $color ?>"><?= iniciales($c['nombres']) ?></span>
                  <div class="cand-datos">
                    <span class="nombre"><?= htmlspecialchars($c['nombres']) ?></span>
                    <span class="contacto">DNI: <?= htmlspecialchars($c['dni']) ?><?= $c['telefono'] ? ' · '.htmlspecialchars($c['telefono']) : '' ?></span>
                    <span class="contacto"><?= htmlspecialchars($c['correo'] ?? '') ?></span>
                  </div>
                </div>
              </td>
              <td class="cand-vacante"><?= htmlspecialchars($c['vacante']) ?>
                <?php if ($esSupervisor): ?>
                  <small class="recl-asig">Reclutador: <?= htmlspecialchars($c['reclutador'] ?? 'Sin asignar') ?></small>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($c['puntaje_cv'] !== null):
                    $pc = (int)$c['puntaje_cv'];
                    $cls = $pc >= 70 ? 'es-activo' : ($pc >= 40 ? 'es-proceso' : 'es-inactivo'); ?>
                  <span class="chip <?= $cls ?>" title="Coincidencia con el perfil del área"><?= $pc ?>%</span>
                <?php else: ?>
                  <span style="color:var(--texto-3);font-size:12px">—</span>
                <?php endif; ?>
                <?php if ($c['test_puntaje'] !== null): ?>
                  <span class="test-mini <?= (int)$c['test_aprobado'] ? 'ok' : 'mal' ?>">Test <?= (int)$c['test_puntaje'] ?>%</span>
                <?php endif; ?>
              </td>
              <td><?= chipEtapa($c['etapa'], $c['etapa_color']) ?></td>
              <td><?= chipEstado($c['estado']) ?></td>
              <td class="fecha"><?= $fecha ?><small><?= $hora ?></small></td>
              <td>
                <div class="acciones">
                  <button class="icobtn" aria-label="Cambiar etapa" title="Cambiar etapa"
                    onclick='abrirCambioEtapa(<?= (int)$c["id"] ?>, <?= json_encode($c["nombres"]) ?>, <?= (int)$c["etapa_id"] ?>, <?= json_encode($c["etapa"]) ?>)'>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M13 6l6 6-6 6"/></svg>
                  </button>
                  <a class="icobtn" href="rendir_evaluacion.php?post=<?= (int)$c['id'] ?>" title="<?= $c['test_puntaje'] !== null ? 'Ver resultado de la evaluación' : 'Aplicar evaluación' ?>" aria-label="Evaluación">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                  </a>
                  <button class="icobtn" aria-label="Ver">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                  </button>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <div class="tabla-pie">
        <span>Mostrando <?= $total ? 1 : 0 ?> a <?= $total ?> de <?= $total ?> candidato<?= $total===1?'':'s' ?></span>
        <div class="paginacion">
          <button class="pag activa">1</button>
        </div>
      </div>
    </section>
  </div>

  <!-- Columna derecha: acciones -->
  <aside class="panel-lat">

    <?php if ($esSupervisor): ?>
    <!-- Carga del equipo (HU-11) -->
    <section class="tarjeta">
      <h3 style="margin-bottom:10px">Carga del equipo</h3>
      <?php if (!$cargaEquipo): ?>
        <p style="font-size:13px;color:var(--texto-3)">Aún no hay candidatos asignados.</p>
      <?php endif; ?>
      <?php foreach ($cargaEquipo as $ce):
        $maxT = max(1, (int)($cargaEquipo[0]['total'] ?? 1));
        $w = (int)round(((int)$ce['total']) * 100 / $maxT); ?>
        <div class="carga-item">
          <div class="carga-top">
            <span class="carga-nom"><?= htmlspecialchars($ce['nombre']) ?></span>
            <span class="carga-tot"><?= (int)$ce['total'] ?></span>
          </div>
          <div class="carga-barra"><div style="width:<?= $w ?>%"></div></div>
          <div class="carga-det">
            <span class="d-proc"><?= (int)$ce['proceso'] ?> en proceso</span>
            <span class="d-sel"><?= (int)$ce['selec'] ?> selec.</span>
            <span class="d-desc"><?= (int)$ce['descart'] ?> desc.</span>
          </div>
        </div>
      <?php endforeach; ?>
    </section>
    <?php endif; ?>

    <!-- Acciones rápidas -->
    <section class="tarjeta">
      <h3 style="margin-bottom:8px">Acciones Rápidas</h3>
      <div class="accion-rapida">
        <span class="ar-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg></span>
        <div class="ar-tx"><strong>Importar candidatos</strong><span>Desde Excel o CSV</span></div>
      </div>
      <div class="accion-rapida">
        <span class="ar-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16v16H4z"/><polyline points="22 6 12 13 2 6"/></svg></span>
        <div class="ar-tx"><strong>Enviar comunicación masiva</strong><span>A candidatos seleccionados</span></div>
      </div>
      <div class="accion-rapida">
        <span class="ar-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg></span>
        <div class="ar-tx"><strong>Generar reporte de candidatos</strong><span>Exportar datos filtrados</span></div>
      </div>
    </section>
  </aside>
</div>

<!-- ===== Modal: Registrar Candidato (por DNI) ===== -->
<div class="modal-fondo" id="modalPostulante">
  <div class="modal-caja">
    <div class="modal-cab">
      <h3>Registrar Candidato</h3>
      <button class="modal-x" onclick="document.getElementById('modalPostulante').classList.remove('abierto')" aria-label="Cerrar">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <form action="registrar_postulante.php" method="POST" class="modal-form" enctype="multipart/form-data">
      <div class="mf-fila">
        <div class="mf-campo">
          <label>DNI *</label>
          <input type="text" name="dni" required maxlength="15" pattern="[0-9]{8,15}" placeholder="Ej. 71234567" title="Solo números, 8 a 15 dígitos">
        </div>
        <div class="mf-campo">
          <label>Teléfono</label>
          <input type="text" name="telefono" maxlength="20" placeholder="Ej. 987654321">
        </div>
      </div>
      <div class="mf-campo">
        <label>Nombres completos *</label>
        <input type="text" name="nombres" required maxlength="120" placeholder="Ej. Juan Pérez García">
      </div>
      <div class="mf-fila">
        <div class="mf-campo">
          <label>Correo electrónico</label>
          <input type="email" name="correo" maxlength="120" placeholder="correo@ejemplo.com">
        </div>
        <div class="mf-campo">
          <label>Distrito</label>
          <input type="text" name="distrito" maxlength="80" placeholder="Ej. Magdalena">
        </div>
      </div>
      <div class="mf-fila">
        <div class="mf-campo">
          <label>Campaña / Vacante *</label>
          <select name="campana_id" required>
            <option value="">Seleccione...</option>
            <?php foreach ($campanas as $c): ?>
              <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['codigo'].' — '.$c['puesto']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mf-campo">
          <label>Origen / Canal</label>
          <select name="origen_id">
            <option value="">Sin especificar</option>
            <?php foreach ($origenes as $o): ?>
              <option value="<?= $o['id'] ?>"><?= htmlspecialchars($o['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="mf-campo">
        <label>CV del candidato (PDF, DOC o DOCX)</label>
        <input type="file" name="cv" accept=".pdf,.doc,.docx">
      </div>
      <div class="nota-sara">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        Al registrar se valida el DNI (lista negra, duplicados y si ya está activo en otra campaña).
      </div>
      <div class="modal-pie">
        <button type="button" class="btn-linea" onclick="document.getElementById('modalPostulante').classList.remove('abierto')">Cancelar</button>
        <button type="submit" class="btn btn-azul">Registrar y validar</button>
      </div>
    </form>
  </div>
</div>

<!-- ===== Modal: Cambiar etapa (HU-06) ===== -->
<div class="modal-fondo" id="modalEtapa">
  <div class="modal-caja">
    <div class="modal-cab">
      <h3>Actualizar etapa</h3>
      <button class="modal-x" onclick="document.getElementById('modalEtapa').classList.remove('abierto')" aria-label="Cerrar">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <form action="mover_etapa.php" method="POST" class="modal-form" id="formEtapa">
      <input type="hidden" name="postulacion_id" id="me_id">
      <p class="me-info">Candidato: <strong id="me_nombre"></strong><br>Etapa actual: <strong id="me_actual"></strong></p>

      <div class="mf-campo">
        <label>Acción</label>
        <select name="accion" id="me_accion" onchange="alternarMotivo()">
          <option value="avanzar">Avanzar / cambiar de etapa</option>
          <option value="descartar">Descartar candidato</option>
        </select>
      </div>

      <div class="mf-campo" id="box_etapa">
        <label>Nueva etapa</label>
        <select name="etapa_id" id="me_etapa">
          <?php foreach ($etapas as $e): ?>
            <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="mf-campo" id="box_motivo" style="display:none">
        <label>Motivo de descarte *</label>
        <select name="motivo_id" id="me_motivo">
          <option value="">Seleccione un motivo...</option>
          <?php foreach ($motivos as $m): ?>
            <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['descripcion']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="mf-campo">
        <label>Observación (opcional)</label>
        <input type="text" name="observacion" maxlength="255" placeholder="Comentario del reclutador">
      </div>

      <div class="modal-pie">
        <button type="button" class="btn-linea" onclick="document.getElementById('modalEtapa').classList.remove('abierto')">Cancelar</button>
        <button type="submit" class="btn btn-azul">Guardar cambio</button>
      </div>
    </form>
  </div>
</div>

<script>
function abrirCambioEtapa(id, nombre, etapaId, etapaNombre){
  document.getElementById('me_id').value = id;
  document.getElementById('me_nombre').textContent = nombre;
  document.getElementById('me_actual').textContent = etapaNombre;
  document.getElementById('me_etapa').value = etapaId;
  document.getElementById('me_accion').value = 'avanzar';
  alternarMotivo();
  document.getElementById('modalEtapa').classList.add('abierto');
}
function alternarMotivo(){
  var esDescarte = document.getElementById('me_accion').value === 'descartar';
  document.getElementById('box_motivo').style.display = esDescarte ? 'block' : 'none';
  document.getElementById('box_etapa').style.display  = esDescarte ? 'none' : 'block';
  document.getElementById('me_motivo').required = esDescarte;
}
</script>

<?php require_once __DIR__ . '/includes/layout_bottom.php'; ?>
