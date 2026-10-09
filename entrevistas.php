<?php
$tituloPagina = 'Entrevistas';
$activo = 'entrevistas';
$estilosExtra = ['assets/css/candidatos.css', 'assets/css/entrevistas.css', 'assets/css/usuarios.css'];

require_once __DIR__ . '/includes/guardia.php';
require_once __DIR__ . '/config/conexion.php';
$pdo = obtenerConexion();

/* Mensajes flash */
$flashOk    = $_SESSION['flash_ok']    ?? '';
$flashError = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_ok'], $_SESSION['flash_error']);

/* ---------------- Filtros (GET) ---------------- */
$fQ      = trim($_GET['q'] ?? '');
$fEstado = $_GET['estado'] ?? '';
$fTipo   = $_GET['tipo'] ?? '';
$fEnt    = $_GET['entrevistador'] ?? '';
$fFecha  = $_GET['fecha'] ?? '';
$estadosValidos = ['Programada','En Curso','Completada','Cancelada'];

/* ---------------- KPIs ---------------- */
$conteo = [];
foreach ($pdo->query("SELECT estado, COUNT(*) c FROM entrevistas GROUP BY estado") as $r) {
    $conteo[$r['estado']] = (int)$r['c'];
}
$gTotal = array_sum($conteo);
$pct = fn($n) => $gTotal ? round($n * 100 / $gTotal, 1) . '% del total' : '0% del total';
$kpis = [
    ['ic'=>'azul',  'titulo'=>'Total Entrevistas','num'=>(string)$gTotal, 'pie'=>'100% del total'],
    ['ic'=>'azul2', 'titulo'=>'Programadas',      'num'=>(string)($conteo['Programada'] ?? 0),   'pie'=>$pct($conteo['Programada'] ?? 0)],
    ['ic'=>'ambar', 'titulo'=>'En Curso',         'num'=>(string)($conteo['En Curso'] ?? 0),     'pie'=>$pct($conteo['En Curso'] ?? 0)],
    ['ic'=>'morado','titulo'=>'Completadas',      'num'=>(string)($conteo['Completada'] ?? 0),   'pie'=>$pct($conteo['Completada'] ?? 0)],
    ['ic'=>'rojo',  'titulo'=>'Canceladas',       'num'=>(string)($conteo['Cancelada'] ?? 0),    'pie'=>$pct($conteo['Cancelada'] ?? 0)],
];

/* ---------------- Entrevistas reales ---------------- */
$sql = "SELECT en.id, en.tipo, en.fecha, en.hora, en.sala, en.estado, en.observaciones,
               en.entrevistador, en.rol_entrevistador, en.entrevistador_id,
               po.nombres, po.correo, po.telefono, po.dni,
               c.puesto AS vacante, c.codigo AS req,
               et.id AS postulacion_id, et.estado AS estado_postulacion,
               (SELECT e2.nombre FROM etapas e2 WHERE e2.id = et.etapa_id) AS etapa_candidato
          FROM entrevistas en
          INNER JOIN postulaciones et ON et.id = en.postulacion_id
          INNER JOIN postulantes   po ON po.id = et.postulante_id
          INNER JOIN campanas      c  ON c.id  = et.campana_id
         WHERE 1=1";
$params = [];
if ($fQ !== '') {
    $sql .= " AND (po.nombres LIKE :q1 OR po.dni LIKE :q2 OR po.correo LIKE :q3 OR c.puesto LIKE :q4 OR en.entrevistador LIKE :q5)";
    $like = '%' . $fQ . '%';
    $params[':q1'] = $like; $params[':q2'] = $like; $params[':q3'] = $like; $params[':q4'] = $like; $params[':q5'] = $like;
}
if (in_array($fEstado, $estadosValidos)) { $sql .= " AND en.estado = :est"; $params[':est'] = $fEstado; }
if ($fTipo !== '' && in_array($fTipo, ['Entrevista RH','Entrevista Técnica','Entrevista Cliente'])) {
    $sql .= " AND en.tipo = :tip"; $params[':tip'] = $fTipo;
}
if ($fEnt !== '' && ctype_digit($fEnt)) { $sql .= " AND en.entrevistador_id = :ent"; $params[':ent'] = (int)$fEnt; }
if ($fFecha === 'hoy')     { $sql .= " AND en.fecha = CURDATE()"; }
elseif ($fFecha === 'semana') { $sql .= " AND en.fecha BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)"; }
elseif ($fFecha === 'mes')    { $sql .= " AND en.fecha BETWEEN DATE_FORMAT(CURDATE(), '%Y-%m-01') AND LAST_DAY(CURDATE())"; }
$sql .= " ORDER BY en.fecha ASC, en.hora ASC";
$st = $pdo->prepare($sql);
$st->execute($params);
$entrevistas = $st->fetchAll();
$hayFiltros = ($fQ !== '' || $fEstado !== '' || $fTipo !== '' || $fEnt !== '' || $fFecha !== '');

/* ---------------- Datos para modales ---------------- */
$disponibles = $pdo->query(
    "SELECT p.id, po.nombres, c.puesto, c.codigo, p.estado
       FROM postulaciones p
       INNER JOIN postulantes po ON po.id = p.postulante_id
       INNER JOIN campanas c ON c.id = p.campana_id
      WHERE p.estado = 'En Proceso'
      ORDER BY p.fecha_postulacion DESC")->fetchAll();

$usuarios = $pdo->query(
    "SELECT u.id, u.nombre, r.nombre AS rol
       FROM usuarios u INNER JOIN roles r ON r.id = u.rol_id
      WHERE u.estado = 1
      ORDER BY u.nombre")->fetchAll();

$entrevistadores = $pdo->query(
    "SELECT DISTINCT en.entrevistador_id, en.entrevistador, en.rol_entrevistador
       FROM entrevistas en WHERE en.entrevistador_id IS NOT NULL ORDER BY en.entrevistador")->fetchAll();

$tiposEntrevista = ['Entrevista RH','Entrevista Técnica','Entrevista Cliente'];

/* ---------------- Helpers ---------------- */
function iniciales(string $nombre): string {
    $p = preg_split('/\s+/', trim($nombre));
    return mb_strtoupper(mb_substr($p[0] ?? '', 0, 1) . mb_substr($p[1] ?? '', 0, 1));
}
function colorAvatar(string $txt): string {
    $cols = ['#3b82f6','#8b5cf6','#0ea5a4','#ec4899','#f59e0b','#22a06b','#d946ef','#6366f1'];
    $s = 0; foreach (str_split($txt) as $ch) { $s += ord($ch); }
    return $cols[$s % count($cols)];
}
function chipTipo(string $tipo): string {
    $m = ['Entrevista RH'=>'et-rh', 'Entrevista Técnica'=>'et-tecnica', 'Entrevista Cliente'=>'et-cliente'];
    $cl = $m[$tipo] ?? 'et-postulacion';
    return '<span class="chip ' . $cl . '">' . htmlspecialchars($tipo) . '</span>';
}
function chipEstado(string $est): string {
    $m = ['Programada'=>'est-programada','En Curso'=>'est-encurso','Completada'=>'est-completada','Cancelada'=>'est-cancelada'];
    $cl = $m[$est] ?? 'est-programada';
    return '<span class="chip ' . $cl . '">' . htmlspecialchars($est) . '</span>';
}

require_once __DIR__ . '/includes/layout_top.php';
?>

<!-- ===== Encabezado ===== -->
<div class="panel-encabezado">
  <div>
    <h2>Entrevistas</h2>
    <p>Programa, gestiona y da seguimiento a las entrevistas con candidatos.</p>
  </div>
  <div class="acciones-panel">
    <a class="btn-linea" href="entrevistas.php">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
      Actualizar
    </a>
    <button class="btn btn-azul" onclick="nuevaEntrevista()">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Programar Entrevista
    </button>
  </div>
</div>

<?php if ($flashOk): ?><div class="aviso aviso-ok"><?= htmlspecialchars($flashOk) ?></div><?php endif; ?>
<?php if ($flashError): ?><div class="aviso aviso-error"><?= htmlspecialchars($flashError) ?></div><?php endif; ?>

<?php if (!$disponibles): ?>
  <div class="aviso aviso-error">No hay candidatos en proceso: primero registra un candidato y asígnale una vacante para poder programar entrevistas.</div>
<?php endif; ?>

<!-- ===== KPIs (5) ===== -->
<div class="grid-kpis-5">
  <?php foreach ($kpis as $k): ?>
    <div class="kpi-cand">
      <span class="ic <?= in_array($k['ic'],['azul','azul2']) ? 'azul' : ($k['ic']==='morado'?'':$k['ic']) ?>"
            <?php if($k['ic']==='morado'): ?>style="background:#f0eafc;color:#7c4ddb"<?php elseif($k['ic']==='azul2'): ?>style="background:#e6f7f4;color:#0f9b8e"<?php endif; ?>>
        <?php if ($k['ic']==='azul'): ?>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        <?php elseif ($k['ic']==='azul2'): ?>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        <?php elseif ($k['ic']==='ambar'): ?>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        <?php elseif ($k['ic']==='morado'): ?>
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

<!-- ===== Filtros ===== -->
<form method="GET" class="filtros-cand">
  <div class="filtro-buscar">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
    <input type="text" name="q" value="<?= htmlspecialchars($fQ) ?>" placeholder="Buscar por candidato, cargo o entrevistador...">
  </div>
  <div class="filtro-campo"><label>Estado</label>
    <select name="estado">
      <option value="">Todos</option>
      <?php foreach ($estadosValidos as $ev): ?>
        <option value="<?= $ev ?>" <?= ($fEstado === $ev ? 'selected' : '') ?>><?= $ev ?></option>
      <?php endforeach; ?>
    </select></div>
  <div class="filtro-campo"><label>Etapa</label>
    <select name="tipo">
      <option value="">Todas</option>
      <?php foreach ($tiposEntrevista as $tv): ?>
        <option value="<?= htmlspecialchars($tv) ?>" <?= ($fTipo === $tv ? 'selected' : '') ?>><?= htmlspecialchars($tv) ?></option>
      <?php endforeach; ?>
    </select></div>
  <div class="filtro-campo"><label>Entrevistador</label>
    <select name="entrevistador">
      <option value="">Todos</option>
      <?php foreach ($entrevistadores as $ev): ?>
        <option value="<?= (int)$ev['entrevistador_id'] ?>" <?= ($fEnt == $ev['entrevistador_id'] ? 'selected' : '') ?>><?= htmlspecialchars($ev['entrevistador']) ?></option>
      <?php endforeach; ?>
    </select></div>
  <div class="filtro-campo"><label>Fecha</label>
    <select name="fecha">
      <option value="">Rango completo</option>
      <option value="hoy" <?= ($fFecha === 'hoy' ? 'selected' : '') ?>>Hoy</option>
      <option value="semana" <?= ($fFecha === 'semana' ? 'selected' : '') ?>>Próxima semana</option>
      <option value="mes" <?= ($fFecha === 'mes' ? 'selected' : '') ?>>Este mes</option>
    </select></div>
  <button class="btn-filtros" type="submit">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
    Filtros
  </button>
  <a class="btn-limpiar" href="entrevistas.php">Limpiar</a>
</form>

<!-- ===== Tabla ===== -->
<section class="tarjeta" style="padding:8px 18px 18px">
  <table class="tabla-cand">
    <thead>
      <tr>
        <th>CANDIDATO</th><th>CARGO / VACANTE</th><th>ETAPA</th><th>ENTREVISTADOR</th>
        <th>FECHA Y HORA</th><th>SALA / UBICACIÓN</th><th>ESTADO</th><th>ACCIONES</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$entrevistas): ?>
        <tr><td colspan="8" style="text-align:center;padding:30px;color:#6b7793">
          <?= $hayFiltros ? 'No hay entrevistas que coincidan con los filtros.' : 'Aún no hay entrevistas programadas. Usa "Programar Entrevista" para agendar la primera.' ?>
        </td></tr>
      <?php endif; ?>
      <?php foreach ($entrevistas as $e):
        $color = colorAvatar($e['nombres']);
        $fechaTs = strtotime($e['fecha']); ?>
        <tr>
          <td>
            <div class="cand-persona">
              <span class="cand-avatar" style="background:<?= $color ?>"><?= iniciales($e['nombres']) ?></span>
              <div class="cand-datos">
                <span class="nombre"><?= htmlspecialchars($e['nombres']) ?></span>
                <span class="contacto"><?= htmlspecialchars($e['correo'] ?: '—') ?></span>
                <span class="contacto"><?= htmlspecialchars($e['telefono'] ?: '—') ?></span>
              </div>
            </div>
          </td>
          <td class="cargo-vac">
            <div class="titulo"><?= htmlspecialchars($e['vacante']) ?></div>
            <div class="req"><?= htmlspecialchars($e['req']) ?></div>
          </td>
          <td><?= chipTipo($e['tipo']) ?></td>
          <td class="entrevistador">
            <div class="nom"><?= htmlspecialchars($e['entrevistador']) ?></div>
            <div class="rol"><?= htmlspecialchars($e['rol_entrevistador'] ?: '—') ?></div>
          </td>
          <td class="fecha"><?= date('d/m/Y', $fechaTs) ?><small><?= date('h:i A', strtotime($e['hora'])) ?></small></td>
          <td class="cand-vacante"><?= htmlspecialchars($e['sala'] ?: 'Por confirmar') ?></td>
          <td><?= chipEstado($e['estado']) ?></td>
          <td>
            <div class="acciones">
              <button class="icobtn" aria-label="Reprogramar" title="Reprogramar / editar"
                onclick='abrirEdicion(<?= json_encode([
                    'id' => (int)$e['id'],
                    'postulacion_id' => (int)$e['postulacion_id'],
                    'entrevistador_id' => (int)$e['entrevistador_id'],
                    'rol' => $e['rol_entrevistador'],
                    'tipo' => $e['tipo'],
                    'fecha' => $e['fecha'],
                    'hora' => substr($e['hora'], 0, 5),
                    'sala' => $e['sala'],
                    'estado' => $e['estado'],
                    'obs' => $e['observaciones'],
                ], JSON_UNESCAPED_UNICODE) ?>)'>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
              </button>
              <?php if ($e['estado'] !== 'Completada'): ?>
                <form action="guardar_entrevista.php" method="POST" style="display:inline" onsubmit="return confirm('¿Marcar esta entrevista como completada?')">
                  <input type="hidden" name="id" value="<?= (int)$e['id'] ?>">
                  <input type="hidden" name="accion" value="estado">
                  <input type="hidden" name="estado" value="Completada">
                  <button class="icobtn" aria-label="Completar" title="Marcar como completada" type="submit">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                  </button>
                </form>
              <?php endif; ?>
              <?php if ($e['estado'] !== 'Cancelada'): ?>
                <form action="guardar_entrevista.php" method="POST" style="display:inline" onsubmit="return confirm('¿Cancelar esta entrevista?')">
                  <input type="hidden" name="id" value="<?= (int)$e['id'] ?>">
                  <input type="hidden" name="accion" value="estado">
                  <input type="hidden" name="estado" value="Cancelada">
                  <button class="icobtn" aria-label="Cancelar" title="Cancelar entrevista" type="submit">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                  </button>
                </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <div class="tabla-pie">
    <span>Mostrando <?= count($entrevistas) ?> de <?= $gTotal ?> entrevistas</span>
  </div>
</section>

<!-- ===== Modal: Programar / Editar entrevista ===== -->
<div class="modal-fondo" id="modalEntrevista">
  <div class="modal-caja">
    <div class="modal-cab">
      <h3 id="titModalEntrevista">Programar Entrevista</h3>
      <button class="modal-x" onclick="cerrarModalEntrevista()" aria-label="Cerrar">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <form action="guardar_entrevista.php" method="POST" class="modal-form">
      <input type="hidden" name="id" id="entId" value="">
      <div class="mf-campo">
        <label>Candidato / Vacante *</label>
        <select name="postulacion_id" id="entPostulacion" required>
          <option value="">Seleccione...</option>
          <?php foreach ($disponibles as $d): ?>
            <option value="<?= (int)$d['id'] ?>">
              <?= htmlspecialchars($d['nombres'] . ' — ' . $d['puesto'] . ' (' . $d['codigo'] . ')') ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="mf-fila">
        <div class="mf-campo">
          <label>Entrevistador *</label>
          <select name="entrevistador_id" id="entUsuario" required onchange="cargarRol()">
            <option value="">Seleccione...</option>
            <?php foreach ($usuarios as $u): ?>
              <option value="<?= (int)$u['id'] ?>" data-rol="<?= htmlspecialchars($u['rol']) ?>"><?= htmlspecialchars($u['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mf-campo">
          <label>Cargo del entrevistador</label>
          <input type="text" name="rol_entrevistador" id="entRol" maxlength="80" placeholder="Ej. Jefe de Talento Humano">
        </div>
      </div>
      <div class="mf-fila">
        <div class="mf-campo">
          <label>Tipo de entrevista *</label>
          <select name="tipo" id="entTipo" required>
            <?php foreach ($tiposEntrevista as $tv): ?>
              <option value="<?= htmlspecialchars($tv) ?>"><?= htmlspecialchars($tv) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mf-campo">
          <label>Estado *</label>
          <select name="estado" id="entEstado" required>
            <?php foreach ($estadosValidos as $ev): ?>
              <option value="<?= $ev ?>"><?= $ev ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="mf-fila">
        <div class="mf-campo">
          <label>Fecha *</label>
          <input type="date" name="fecha" id="entFecha" required>
        </div>
        <div class="mf-campo">
          <label>Hora *</label>
          <input type="time" name="hora" id="entHora" required>
        </div>
      </div>
      <div class="mf-campo">
        <label>Sala / ubicación</label>
        <input type="text" name="sala" id="entSala" maxlength="80" placeholder="Ej. Sala 3 · Piso 2 o Link de videollamada">
      </div>
      <div class="mf-campo">
        <label>Observaciones</label>
        <input type="text" name="observaciones" id="entObs" maxlength="255" placeholder="Notas para el entrevistador (opcional)">
      </div>
      <div class="nota-sara">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
        El candidato verá la fecha y hora en su seguimiento por código.
      </div>
      <div class="modal-pie">
        <button type="button" class="btn-linea" onclick="cerrarModalEntrevista()">Cancelar</button>
        <button type="submit" class="btn btn-azul" id="btnEntGuardar">Programar</button>
      </div>
    </form>
  </div>
</div>

<script>
function cerrarModalEntrevista(){ document.getElementById('modalEntrevista').classList.remove('abierto'); }

function nuevaEntrevista(){
  document.getElementById('titModalEntrevista').textContent = 'Programar Entrevista';
  document.getElementById('btnEntGuardar').textContent = 'Programar';
  ['entId','entSala','entObs','entRol'].forEach(function(id){ document.getElementById(id).value = ''; });
  document.getElementById('entPostulacion').value = '';
  document.getElementById('entUsuario').value = '';
  document.getElementById('entTipo').value = 'Entrevista RH';
  document.getElementById('entEstado').value = 'Programada';
  document.getElementById('entFecha').value = '';
  document.getElementById('entHora').value = '';
  document.getElementById('modalEntrevista').classList.add('abierto');
}

function abrirEdicion(d){
  document.getElementById('titModalEntrevista').textContent = 'Editar entrevista';
  document.getElementById('btnEntGuardar').textContent = 'Guardar cambios';
  document.getElementById('entId').value = d.id;
  document.getElementById('entPostulacion').value = d.postulacion_id;
  document.getElementById('entUsuario').value = d.entrevistador_id || '';
  document.getElementById('entRol').value = d.rol || '';
  document.getElementById('entTipo').value = d.tipo;
  document.getElementById('entEstado').value = d.estado;
  document.getElementById('entFecha').value = d.fecha;
  document.getElementById('entHora').value = d.hora;
  document.getElementById('entSala').value = d.sala || '';
  document.getElementById('entObs').value = d.obs || '';
  document.getElementById('modalEntrevista').classList.add('abierto');
}

function cargarRol(){
  var sel = document.getElementById('entUsuario');
  var rol = sel.options[sel.selectedIndex] ? sel.options[sel.selectedIndex].getAttribute('data-rol') : '';
  document.getElementById('entRol').value = rol || '';
}
</script>

<?php require_once __DIR__ . '/includes/layout_bottom.php'; ?>
