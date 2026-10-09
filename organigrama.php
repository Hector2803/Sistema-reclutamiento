<?php
$tituloPagina = 'Organigrama de la Empresa';
$activo = 'organigrama';
$estilosExtra = ['assets/css/organigrama.css'];

require_once __DIR__ . '/includes/guardia.php';
require_once __DIR__ . '/config/conexion.php';
$pdo = obtenerConexion();

/* ---------------- Filtros ---------------- */
$fArea   = $_GET['area'] ?? '';
$fEstado = $_GET['estado'] ?? '';

/* Mensajes flash (edición de jefe de área) */
$flashOk    = $_SESSION['flash_ok']    ?? '';
$flashError = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_ok'], $_SESSION['flash_error']);

/* Solo Administrador edita los jefes de área */
$puedeEditar = (($_SESSION['usuario_rol'] ?? '') === 'Administrador');

/* ---------------- KPIs reales ---------------- */
$gAreas   = (int)$pdo->query("SELECT COUNT(*) c FROM areas WHERE estado = 1")->fetch()['c'];
$gTotal   = (int)$pdo->query("SELECT COUNT(*) c FROM usuarios")->fetch()['c'];
$gPlanilla= (int)$pdo->query("SELECT COUNT(*) c FROM usuarios WHERE estado = 1")->fetch()['c'];
$gVacantes= (int)$pdo->query("SELECT COALESCE(SUM(c.vacantes),0) c FROM campanas c WHERE c.publicado = 1 AND c.estado <> 'Cerrado'")->fetch()['c'];
$gInactivos = $gTotal - $gPlanilla;

$kpis = [
    ['azul',  'Total Áreas',   (string)$gAreas],
    ['cian',  'Total Personal',(string)$gTotal],
    ['morado','En Planilla',   (string)$gPlanilla],
    ['ambar', 'Vacantes',      (string)$gVacantes],
];

/* ---------------- Personal por área ---------------- */
$condEstado = '';
if (in_array($fEstado, ['1','0'], true)) { $condEstado = ' AND u.estado = ' . (int)$fEstado; }

$personas = $pdo->query(
    "SELECT u.id, u.nombre, u.estado, u.area_id, r.nombre AS rol
       FROM usuarios u INNER JOIN roles r ON r.id = u.rol_id
      WHERE 1=1$condEstado
      ORDER BY r.id DESC, u.nombre")->fetchAll();

$porArea = [];
$gerencia = [];
foreach ($personas as $p) {
    if ($p['area_id'] === null) { $gerencia[] = $p; }
    else { $porArea[(int)$p['area_id']][] = $p; }
}

$areas = $pdo->query("SELECT id, nombre, descripcion, jefe FROM areas WHERE estado = 1 ORDER BY nombre")->fetchAll();
if ($fArea !== '' && ctype_digit($fArea)) {
    $areas = array_values(array_filter($areas, fn($a) => (int)$a['id'] === (int)$fArea));
    $gerencia = ($fArea === '0') ? $gerencia : [];
}

/* Vacantes abiertas por área */
$vacantesPorArea = [];
foreach ($pdo->query("SELECT area_id, codigo, puesto, vacantes FROM campanas WHERE publicado = 1 AND estado <> 'Cerrado'") as $v) {
    $vacantesPorArea[(int)$v['area_id']][] = $v;
}

/* ---------------- Resumen por área (donut) ---------------- */
$paleta = ['#2f6fed','#f5a623','#ef4444','#7c4ddb','#22a06b','#0ea5a4','#ec4899','#6366f1'];
$conteos = $pdo->query(
    "SELECT COALESCE(u.area_id, 0) AS aid, a.nombre AS area, COUNT(*) n
       FROM usuarios u LEFT JOIN areas a ON a.id = u.area_id
      GROUP BY u.area_id, a.nombre
      ORDER BY n DESC")->fetchAll();

$resumen = [];
foreach ($conteos as $i => $c) {
    $nom = $c['area'] ?: 'Gerencia General';
    $pct = $gTotal ? round($c['n'] * 100 / $gTotal, 1) : 0;
    $resumen[] = [$nom, (int)$c['n'], $pct, $paleta[$i % count($paleta)]];
}

/* ---------------- Personal por estado ---------------- */
$estados = [];
foreach ([['verde','Activo', $gPlanilla], ['rojo','Inactivo', $gInactivos]] as $e) {
    [$ic, $et, $n] = $e;
    $estados[] = [$ic, $et, (string)$n, $gTotal ? round($n * 100 / $gTotal, 1) . '%' : '0%'];
}

/* ---------------- Helpers ---------------- */
function iniciales(string $n): string {
    $p = preg_split('/\s+/', trim($n));
    return mb_strtoupper(mb_substr($p[0] ?? '', 0, 1) . mb_substr($p[1] ?? '', 0, 1));
}
function colorAvatar(string $txt): string {
    $cols = ['#3b82f6','#8b5cf6','#0ea5a4','#ec4899','#f59e0b','#22a06b','#d946ef','#6366f1'];
    $s = 0; foreach (str_split($txt) as $ch) { $s += ord($ch); }
    return $cols[$s % count($cols)];
}
function donutSVG(array $datos): string {
    $r=47;$cx=$cy=59;$sw=18;$circ=2*M_PI*$r;$off=0;$s='';
    foreach ($datos as $d){ [$et,$n,$pct,$col]=$d;
        $largo=($pct/100)*$circ;$gap=$circ-$largo;
        $s.='<circle cx="'.$cx.'" cy="'.$cy.'" r="'.$r.'" fill="none" stroke="'.$col.'" stroke-width="'.$sw.'" stroke-dasharray="'.round($largo,2).' '.round($gap,2).'" stroke-dashoffset="'.round(-$off,2).'" transform="rotate(-90 '.$cx.' '.$cy.')"/>';
        $off+=$largo;
    } return $s;
}

$chev = '<svg class="chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>';
$icPersona = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>';
$icGrupo = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>';
$coloresNodo = ['ic-azul','ic-verde','ic-morado','ic-cian','ic-ambar','ic-rojo'];

require_once __DIR__ . '/includes/layout_top.php';

/** Renderiza el listado de personas de un grupo */
function pintaPersonas(array $lista): void {
    foreach ($lista as $p):
        $act = $p['estado'] ? 'ok' : 'bad';
        $txt = $p['estado'] ? 'Activo' : 'Inactivo'; ?>
        <div class="persona">
          <span class="av" style="background:<?= colorAvatar($p['nombre']) ?>"><?= iniciales($p['nombre']) ?></span>
          <div class="datos"><div class="nm"><?= htmlspecialchars($p['nombre']) ?> <span style="color:#8a94a6;font-weight:400">(<?= htmlspecialchars($p['rol']) ?>)</span></div></div>
          <span class="estado <?= $act ?>"><span class="pt"></span><?= $txt ?></span>
        </div>
    <?php endforeach;
}
?>

<!-- ===== Encabezado ===== -->
<div class="panel-encabezado">
  <div>
    <h2>Organigrama de la Empresa</h2>
    <p>Visualiza la estructura organizacional y el personal por área.</p>
  </div>
</div>

<?php if ($flashOk): ?>
  <div class="aviso aviso-ok"><?= htmlspecialchars($flashOk) ?></div>
<?php endif; ?>
<?php if ($flashError): ?>
  <div class="aviso aviso-error"><?= htmlspecialchars($flashError) ?></div>
<?php endif; ?>

<div class="org-layout">

  <!-- ===== Columna izquierda ===== -->
  <div>
    <!-- KPIs -->
    <div class="org-kpis">
      <?php foreach ($kpis as $k): [$ic,$t,$n]=$k; ?>
        <div class="org-kpi">
          <span class="ic <?= $ic ?>">
            <?php if($t==='Total Áreas'): ?><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
            <?php elseif($t==='Total Personal'): ?><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/></svg>
            <?php elseif($t==='En Planilla'): ?><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><polyline points="16 11 18 13 22 9"/></svg>
            <?php else: ?><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg><?php endif; ?>
          </span>
          <div><small><?= htmlspecialchars($t) ?></small><div class="num"><?= htmlspecialchars($n) ?></div></div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- ===== Nodo raíz: Gerencia ===== -->
    <?php $actG = count(array_filter($gerencia, fn($p) => $p['estado'])); ?>
    <div class="nodo abierto">
      <div class="nodo-head">
        <?= $chev ?>
        <span class="nodo-ic ic-azul"><?= $icPersona ?></span>
        <div class="nodo-tit"><span class="n">Gerencia General <em>- Dirección</em></span></div>
        <span class="badge-conteo"><?= $actG ?> / <?= count($gerencia) ?></span>
        <span class="estado <?= $actG ? 'ok' : '' ?>"><span class="pt"></span><?= $actG ? 'Activo' : 'Sin personal' ?></span>
      </div>
      <div class="nodo-body">
        <?php if ($gerencia): ?>
          <div class="grupo abierto" style="border:none">
            <div class="grupo-body" style="padding-left:0">
              <?php pintaPersonas($gerencia); ?>
            </div>
          </div>
        <?php else: ?>
          <div class="persona vacante">
            <span class="av"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg></span>
            <div class="datos"><div class="nm">Sin personal asignado a este filtro</div></div>
            <span class="chip-sincubrir">Sin cubrir</span>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- ===== Áreas ===== -->
    <?php foreach ($areas as $i => $a):
      $miembros = $porArea[(int)$a['id']] ?? [];
      $act = count(array_filter($miembros, fn($p) => $p['estado']));
      $vacs = $vacantesPorArea[(int)$a['id']] ?? [];
      $nVac = array_sum(array_map(fn($v) => (int)$v['vacantes'], $vacs));
      $colNodo = $coloresNodo[$i % count($coloresNodo)];
      $abierta = $miembros || $vacs; ?>

      <div class="nodo <?= $abierta ? 'abierto' : '' ?>">
        <div class="nodo-head">
          <?= $chev ?>
          <span class="nodo-ic <?= $colNodo ?>"><?= $icGrupo ?></span>
          <div class="nodo-tit"><span class="n"><?= htmlspecialchars($a['nombre']) ?>
            <?php if ($a['descripcion']): ?><em>- <?= htmlspecialchars($a['descripcion']) ?></em><?php endif; ?></span>
            <span class="jefe-lin">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><polyline points="16 11 18 13 22 9"/></svg>
              <span class="jl-et">Jefe:</span>
              <b class="<?= $a['jefe'] ? '' : 'sin-jefe' ?>"><?= $a['jefe'] ? htmlspecialchars($a['jefe']) : 'Sin asignar' ?></b>
              <?php if ($puedeEditar): ?>
                <button type="button" class="btn-jefe" title="Editar nombre del jefe"
                        onclick="event.stopPropagation(); editarJefe(this)"
                        data-area="<?= (int)$a['id'] ?>"
                        data-titulo="<?= htmlspecialchars($a['nombre'], ENT_QUOTES) ?>"
                        data-jefe="<?= htmlspecialchars($a['jefe'] ?? '', ENT_QUOTES) ?>">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4z"/></svg>
                </button>
              <?php endif; ?>
            </span>
          </div>
          <span class="badge-conteo"><?= $act ?> / <?= count($miembros) ?></span>
          <?php if ($nVac): ?><span class="chip-falta"><?= $nVac ?> vacante<?= $nVac > 1 ? 's' : '' ?></span><?php endif; ?>
          <span class="estado <?= $act ? 'ok' : '' ?>"><span class="pt"></span><?= $act ? 'Activo' : 'Sin personal' ?></span>
        </div>
        <?php if ($abierta): ?>
        <div class="nodo-body">
          <?php if ($miembros):
            // Agrupa por rol (jerarquía)
            $porRol = [];
            foreach ($miembros as $m) { $porRol[$m['rol']][] = $m; }
            foreach ($porRol as $rol => $lista): ?>
              <div class="grupo abierto">
                <div class="grupo-head">
                  <?= $chev ?>
                  <span class="gic"><?= $icGrupo ?></span>
                  <span class="gt"><?= htmlspecialchars($rol) ?>s (<?= count($lista) ?>)</span>
                  <span class="badge-conteo"><?= count(array_filter($lista, fn($p) => $p['estado'])) ?> / <?= count($lista) ?></span>
                  <?php $faltan = count($lista) - count(array_filter($lista, fn($p) => $p['estado'])); ?>
                  <?php if ($faltan > 0): ?><span class="chip-falta">Inactivos: <?= $faltan ?></span>
                  <?php else: ?><span class="chip-completo">Completo</span><?php endif; ?>
                </div>
                <div class="grupo-body">
                  <?php pintaPersonas($lista); ?>
                </div>
              </div>
            <?php endforeach;
          else: ?>
            <div class="persona vacante">
              <span class="av"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg></span>
              <div class="datos"><div class="nm">Sin personal en esta área</div></div>
              <span class="chip-sincubrir">Sin cubrir</span>
            </div>
          <?php endif; ?>

          <?php if ($vacs): ?>
            <div class="grupo abierto">
              <div class="grupo-head">
                <?= $chev ?>
                <span class="gic"><?= $icGrupo ?></span>
                <span class="gt">Vacantes abiertas (<?= $nVac ?>)</span>
                <span class="chip-falta">Por cubrir</span>
              </div>
              <div class="grupo-body">
                <?php foreach ($vacs as $v): ?>
                  <div class="persona vacante">
                    <span class="av"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg></span>
                    <div class="datos"><div class="nm"><?= htmlspecialchars($v['puesto']) ?></div><div class="cg"><?= htmlspecialchars($v['codigo']) ?></div></div>
                    <span class="chip-sincubrir"><?= (int)$v['vacantes'] ?> sin cubrir</span>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>

    <?php if (!$areas && $gerencia): ?>
      <div class="aviso aviso-ok">Mostrando solo la gerencia por el filtro aplicado.</div>
    <?php endif; ?>
  </div>

  <!-- ===== Columna derecha ===== -->
  <aside class="org-lat">

    <!-- Filtros -->
    <form method="GET" class="tarjeta">
      <h3 style="margin-bottom:16px"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg> Filtros</h3>
      <div class="filtro-b">
        <label>Área</label>
        <select name="area">
          <option value="">Todas las áreas</option>
          <option value="0" <?= ($fArea === '0' ? 'selected' : '') ?>>Solo Gerencia General</option>
          <?php foreach ($pdo->query("SELECT id, nombre FROM areas WHERE estado = 1 ORDER BY nombre") as $ar): ?>
            <option value="<?= (int)$ar['id'] ?>" <?= ($fArea == $ar['id'] ? 'selected' : '') ?>><?= htmlspecialchars($ar['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="filtro-b">
        <label>Estado</label>
        <select name="estado">
          <option value="">Todos</option>
          <option value="1" <?= ($fEstado === '1' ? 'selected' : '') ?>>Activo</option>
          <option value="0" <?= ($fEstado === '0' ? 'selected' : '') ?>>Inactivo</option>
        </select>
      </div>
      <button class="btn-limpiar-f" type="submit"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg> Aplicar filtros</button>
      <a class="btn-limpiar-f" href="organigrama.php" style="margin-top:8px"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg> Limpiar filtros</a>
    </form>

    <!-- Resumen por área -->
    <section class="tarjeta">
      <h3 style="margin-bottom:14px">Resumen por área</h3>
      <div class="org-donut">
        <div class="org-donut-box">
          <svg viewBox="0 0 118 118"><?= $resumen ? donutSVG($resumen) : '' ?></svg>
          <div class="org-donut-centro"><span class="n"><?= $gTotal ?></span><span class="t">Personas</span></div>
        </div>
        <div class="org-leyenda">
          <?php foreach ($resumen as $d): [$et,$n,$pct,$col]=$d; ?>
            <div class="it"><span class="pt" style="background:<?= $col ?>"></span><span class="et"><?= htmlspecialchars($et) ?></span><span class="vv"><?= $n ?> (<?= $pct ?>%)</span></div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <!-- Personal por estado -->
    <section class="tarjeta">
      <h3 style="margin-bottom:8px">Personal por estado</h3>
      <?php foreach ($estados as $e): [$ic,$et,$n,$pct]=$e; ?>
        <div class="pe-item">
          <span class="pic <?= $ic ?>">
            <?php if($ic==='verde'): ?><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><polyline points="16 11 18 13 22 9"/></svg>
            <?php else: ?><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="17" y1="8" x2="22" y2="13"/><line x1="22" y1="8" x2="17" y2="13"/></svg><?php endif; ?>
          </span>
          <span class="et"><?= htmlspecialchars($et) ?></span>
          <span class="vv"><?= $n ?> <span>(<?= $pct ?>)</span></span>
        </div>
      <?php endforeach; ?>
    </section>

    <!-- Info editar -->
    <div class="org-info">
      <div class="cab">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
        <div><strong>¿Necesitas actualizar información?</strong><span>La estructura se actualiza desde la gestión de áreas y usuarios.</span></div>
      </div>
      <?php if (($_SESSION['usuario_rol'] ?? '') === 'Administrador'): ?>
        <a class="btn-editar" href="usuarios.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4z"/></svg> Gestionar personal</a>
      <?php endif; ?>
    </div>
  </aside>
</div>

<!-- ===== Modal: editar jefe de área ===== -->
<?php if ($puedeEditar): ?>
<div class="modal-fondo" id="modalJefe" onclick="cerrarModal('modalJefe')">
  <div class="modal-caja" onclick="event.stopPropagation()">
    <div class="modal-cab">
      <h3 id="jTit">Editar jefe de área</h3>
      <button type="button" class="modal-x" aria-label="Cerrar" onclick="cerrarModal('modalJefe')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <form method="POST" action="guardar_area_jefe.php" class="modal-form">
      <input type="hidden" name="area_id" id="jArea">
      <div class="mf-campo">
        <label>Nombre del jefe responsable *</label>
        <input type="text" name="jefe" id="jNombre" maxlength="120" required
               placeholder="Ej. María López" autocomplete="off">
        <span class="mf-ayuda">Se muestra en el organigrama como responsable del área.</span>
      </div>
      <div class="modal-pie">
        <button type="button" class="btn-sec" onclick="cerrarModal('modalJefe')">Cancelar</button>
        <button type="submit" class="btn btn-azul">Guardar nombre</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- ===== Colapsar / expandir nodos ===== -->
<script>
document.querySelectorAll('.nodo-head, .grupo-head').forEach(function (h) {
  h.addEventListener('click', function (e) {
    if (e.target.closest('.kebab') || e.target.closest('.btn-jefe')) return;
    const cont = h.closest('.nodo, .grupo');
    if (cont) cont.classList.toggle('abierto');
    e.stopPropagation();
  });
});

function cerrarModal(id) { document.getElementById(id).classList.remove('abierto'); }

function editarJefe(btn) {
  const d = btn.dataset;
  document.getElementById('jTit').textContent = 'Jefe de ' + d.titulo;
  document.getElementById('jArea').value = d.area;
  document.getElementById('jNombre').value = d.jefe || '';
  document.getElementById('modalJefe').classList.add('abierto');
  setTimeout(function () { document.getElementById('jNombre').focus(); }, 60);
}

document.addEventListener('keydown', function (e) {
  if (e.key === 'Escape') { const m = document.getElementById('modalJefe'); if (m) cerrarModal('modalJefe'); }
});
</script>

<?php require_once __DIR__ . '/includes/layout_bottom.php'; ?>
