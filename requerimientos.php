<?php
$tituloPagina = 'Requerimientos / Vacantes';
$activo = 'requerimientos';
$estilosExtra = ['assets/css/candidatos.css', 'assets/css/requerimientos.css', 'assets/css/usuarios.css'];

require_once __DIR__ . '/includes/guardia.php';
require_once __DIR__ . '/config/conexion.php';
$pdo = obtenerConexion();

/* Mensajes flash */
$flashOk    = $_SESSION['flash_ok']    ?? '';
$flashError = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_ok'], $_SESSION['flash_error']);

/* Requerimientos reales (con nombre de área) */
$reqs = $pdo->query(
    "SELECT c.id, c.codigo, c.puesto, c.descripcion, c.vacantes,
            c.tipo, c.estado, c.prioridad, c.fecha_sol, c.publicado,
            c.ubicacion, c.modalidad, c.horario, c.remuneracion, c.fecha_ingreso,
            c.tareas, c.requisitos, c.beneficios, c.area_id,
            a.nombre AS area
     FROM campanas c
     INNER JOIN areas a ON a.id = c.area_id
     ORDER BY c.creado_en DESC"
)->fetchAll();

/* Áreas para el formulario y filtro */
$areas = $pdo->query("SELECT id, nombre FROM areas WHERE estado = 1 ORDER BY nombre")->fetchAll();

/* Áreas disponibles en "Nuevo requerimiento" (sin Marketing ni Operaciones) */
$areasForm = array_values(array_filter($areas, fn($a) => !in_array($a['nombre'], ['Marketing', 'Operaciones'], true)));

/* KPIs calculados */
$total = count($reqs);
$abiertos = $enproc = $cerrados = 0;
foreach ($reqs as $r) {
    if ($r['estado']==='Abierto') $abiertos++;
    elseif ($r['estado']==='En Proceso') $enproc++;
    elseif ($r['estado']==='Cerrado') $cerrados++;
}
$pct = fn($n)=> $total ? round($n*100/$total,1).'% del total' : '0% del total';
$kpis = [
    ['ic'=>'azul', 'titulo'=>'Total Requerimientos','num'=>(string)$total,   'pie'=>'100% del total'],
    ['ic'=>'verde','titulo'=>'Abiertos',            'num'=>(string)$abiertos,'pie'=>$pct($abiertos)],
    ['ic'=>'ambar','titulo'=>'En Proceso',          'num'=>(string)$enproc,  'pie'=>$pct($enproc)],
    ['ic'=>'rojo', 'titulo'=>'Cerrados',            'num'=>(string)$cerrados,'pie'=>$pct($cerrados)],
];

/* Solo Administrador y Supervisor registran requerimientos (HU-03) */
$puedeRegistrar = in_array(($_SESSION['usuario_rol'] ?? ''), ['Administrador','Supervisor']);

function claseTipo($t){ return $t==='Reemplazo' ? 'tipo-reemplazo' : 'tipo-nueva'; }
function claseEstado($e){ return ['Abierto'=>'est-abierto','En Proceso'=>'est-enproceso','Cerrado'=>'est-cerrado'][$e] ?? 'est-abierto'; }
function clasePrio($p){ return ['Alta'=>'prio-alta','Media'=>'prio-media','Baja'=>'prio-baja'][$p] ?? 'prio-media'; }
function fFecha($f){ return $f ? date('d/m/Y', strtotime($f)) : '—'; }

require_once __DIR__ . '/includes/layout_top.php';
?>

<!-- ===== Encabezado ===== -->
<div class="panel-encabezado">
  <div>
    <h2>Requerimientos / Vacantes</h2>
    <p>Gestiona las posiciones solicitadas y publica vacantes para atraer talento.</p>
  </div>
  <div class="acciones-panel">
    <button class="btn-linea" type="button" title="Abrir la bolsa de trabajo pública"
            onclick="window.open('portal/index.php', '_blank')">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
      Ver portal público
    </button>
    <button class="btn-linea">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
      Exportar
    </button>
    <button class="btn btn-azul" onclick="nuevaReq()">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Nuevo Requerimiento
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
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 2h6a1 1 0 0 1 1 1v1h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2V3a1 1 0 0 1 1-1z"/><path d="M9 4h6"/></svg>
        <?php elseif ($k['ic']==='verde'): ?>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
        <?php elseif ($k['ic']==='ambar'): ?>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 22h14M5 2h14M6 2v4a6 6 0 0 0 12 0V2M6 22v-4a6 6 0 0 1 12 0v4"/></svg>
        <?php else: ?>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
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
<div class="filtros-cand">
  <div class="filtro-buscar">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
    <input type="text" placeholder="Buscar por cargo, área o código...">
  </div>
  <div class="filtro-campo"><label>Estado</label>
    <select><option>Todos</option><option>Abierto</option><option>En Proceso</option><option>Cerrado</option></select></div>
  <div class="filtro-campo"><label>Área</label>
    <select><option>Todas</option><?php foreach($areas as $a): ?><option><?= htmlspecialchars($a['nombre']) ?></option><?php endforeach; ?></select></div>
  <div class="filtro-campo"><label>Tipo</label>
    <select><option>Todos</option><option>Nueva Posición</option><option>Reemplazo</option></select></div>
  <div class="filtro-campo"><label>Prioridad</label>
    <select><option>Todas</option><option>Alta</option><option>Media</option><option>Baja</option></select></div>
  <button class="btn-filtros">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
    Filtros
  </button>
  <button class="btn-limpiar">Limpiar</button>
</div>

<!-- ===== Tabla ===== -->
<section class="tarjeta" style="padding:8px 18px 18px">
  <table class="tabla-cand tabla-cargo">
    <thead>
      <tr>
        <th class="col-check"><input type="checkbox"></th>
        <th>CÓDIGO</th><th>CARGO / POSICIÓN</th><th>ÁREA</th><th>TIPO</th>
        <th>VACANTES</th><th>ESTADO</th><th>PRIORIDAD</th><th>FECHA SOLICITUD</th><th>ACCIONES</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$reqs): ?>
        <tr><td colspan="10" style="text-align:center;color:var(--texto-3);padding:28px">
          No hay requerimientos registrados. Usa <strong>Nuevo Requerimiento</strong> para crear el primero.
        </td></tr>
      <?php endif; ?>
      <?php foreach ($reqs as $r): ?>
        <tr>
          <td class="col-check"><input type="checkbox"></td>
          <td class="codigo"><?= htmlspecialchars($r['codigo']) ?></td>
          <td class="cargo-cel">
            <div class="titulo"><?= htmlspecialchars($r['puesto']) ?>
              <?php if((int)$r['publicado']===1): ?><span class="chip" style="background:var(--verde-suave);color:var(--verde);font-size:10.5px;padding:2px 8px;margin-left:6px">Publicado</span><?php endif; ?>
            </div>
            <div class="desc"><?= htmlspecialchars($r['descripcion'] ?? '') ?></div>
          </td>
          <td><?= htmlspecialchars($r['area']) ?></td>
          <td><span class="chip <?= claseTipo($r['tipo']) ?>"><?= htmlspecialchars($r['tipo']) ?></span></td>
          <td class="vacantes-num"><?= (int)$r['vacantes'] ?></td>
          <td><span class="chip <?= claseEstado($r['estado']) ?>"><?= htmlspecialchars($r['estado']) ?></span></td>
          <td><span class="chip <?= clasePrio($r['prioridad']) ?>"><?= htmlspecialchars($r['prioridad']) ?></span></td>
          <td class="fecha"><?= fFecha($r['fecha_sol']) ?></td>
          <td>
            <div class="acciones">
              <?php if ($puedeRegistrar): ?>
              <button class="icobtn" type="button" aria-label="Editar" title="Editar requerimiento" onclick="editarReq(this)"
                data-id="<?= (int)$r['id'] ?>"
                data-puesto="<?= htmlspecialchars($r['puesto'], ENT_QUOTES) ?>"
                data-descripcion="<?= htmlspecialchars($r['descripcion'] ?? '', ENT_QUOTES) ?>"
                data-area="<?= (int)$r['area_id'] ?>"
                data-area-nombre="<?= htmlspecialchars($r['area'], ENT_QUOTES) ?>"
                data-vacantes="<?= (int)$r['vacantes'] ?>"
                data-tipo="<?= htmlspecialchars($r['tipo'], ENT_QUOTES) ?>"
                data-prioridad="<?= htmlspecialchars($r['prioridad'], ENT_QUOTES) ?>"
                data-ubicacion="<?= htmlspecialchars($r['ubicacion'] ?? '', ENT_QUOTES) ?>"
                data-horario="<?= htmlspecialchars($r['horario'] ?? '', ENT_QUOTES) ?>"
                data-remuneracion="<?= htmlspecialchars($r['remuneracion'] !== null ? rtrim(rtrim($r['remuneracion'], '0'), '.') : '', ENT_QUOTES) ?>"
                data-fecha="<?= htmlspecialchars($r['fecha_ingreso'] ?? '', ENT_QUOTES) ?>"
                data-modalidad="<?= htmlspecialchars($r['modalidad'], ENT_QUOTES) ?>"
                data-tareas="<?= htmlspecialchars($r['tareas'] ?? '', ENT_QUOTES) ?>"
                data-requisitos="<?= htmlspecialchars($r['requisitos'] ?? '', ENT_QUOTES) ?>"
                data-beneficios="<?= htmlspecialchars($r['beneficios'] ?? '', ENT_QUOTES) ?>"
                data-publicado="<?= (int)$r['publicado'] ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4z"/></svg>
              </button>
              <?php endif; ?>
              <button class="icobtn" type="button" aria-label="Ver" title="Ver detalle" onclick="verReq(this)"
                data-id="<?= (int)$r['id'] ?>"
                data-codigo="<?= htmlspecialchars($r['codigo'], ENT_QUOTES) ?>"
                data-puesto="<?= htmlspecialchars($r['puesto'], ENT_QUOTES) ?>"
                data-descripcion="<?= htmlspecialchars($r['descripcion'] ?? '', ENT_QUOTES) ?>"
                data-area="<?= htmlspecialchars($r['area'], ENT_QUOTES) ?>"
                data-tipo="<?= htmlspecialchars($r['tipo'], ENT_QUOTES) ?>"
                data-vacantes="<?= (int)$r['vacantes'] ?>"
                data-estado="<?= htmlspecialchars($r['estado'], ENT_QUOTES) ?>"
                data-prioridad="<?= htmlspecialchars($r['prioridad'], ENT_QUOTES) ?>"
                data-fecha="<?= fFecha($r['fecha_sol']) ?>"
                data-publicado="<?= (int)$r['publicado'] === 1 ? 'Sí' : 'No' ?>"
                data-ubicacion="<?= htmlspecialchars($r['ubicacion'] ?? '—', ENT_QUOTES) ?>"
                data-modalidad="<?= htmlspecialchars($r['modalidad'], ENT_QUOTES) ?>"
                data-horario="<?= htmlspecialchars($r['horario'] ?? '—', ENT_QUOTES) ?>"
                data-remuneracion="<?= $r['remuneracion'] !== null ? 'S/ ' . number_format((float)$r['remuneracion'], 2, ',', '.') : '—' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              </button>
              <div class="con-menu">
                <button class="icobtn" type="button" aria-label="Más" title="Más acciones" onclick="toggleMenu(event, this)">
                  <svg viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="12" cy="19" r="1.6"/></svg>
                </button>
                <div class="menu-kebab">
                  <?php if ((int)$r['publicado'] === 1 && $r['estado'] !== 'Cerrado'): ?>
                    <button type="button" class="op-portal" title="Ver la publicación tal como la ve el postulante"
                            onclick="window.open('portal/trabajo.php?id=<?= (int)$r['id'] ?>', '_blank')">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                      Ver en el portal
                    </button>
                  <?php else: ?>
                    <button type="button" class="op-off" disabled title="Publica la vacante para verla en el portal">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
                      No publicada en el portal
                    </button>
                  <?php endif; ?>
                  <?php if ($puedeRegistrar): ?>
                    <form action="guardar_requerimiento.php" method="POST">
                      <input type="hidden" name="accion" value="publicar">
                      <input type="hidden" name="campana_id" value="<?= (int)$r['id'] ?>">
                      <input type="hidden" name="valor" value="<?= (int)$r['publicado'] === 1 ? 0 : 1 ?>">
                      <button type="submit" class="<?= (int)$r['publicado'] === 1 ? 'op-inactivar' : 'op-activar' ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        <?= (int)$r['publicado'] === 1 ? 'Despublicar' : 'Publicar vacante' ?>
                      </button>
                    </form>
                    <form action="guardar_requerimiento.php" method="POST">
                      <input type="hidden" name="accion" value="estado">
                      <input type="hidden" name="campana_id" value="<?= (int)$r['id'] ?>">
                      <input type="hidden" name="valor" value="<?= $r['estado'] === 'Cerrado' ? 'Abierto' : 'Cerrado' ?>">
                      <button type="submit" class="<?= $r['estado'] === 'Cerrado' ? 'op-activar' : 'op-inactivar' ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
                        <?= $r['estado'] === 'Cerrado' ? 'Reabrir vacante' : 'Cerrar vacante' ?>
                      </button>
                    </form>
                    <form action="guardar_requerimiento.php" method="POST"
                          onsubmit="return confirm('Se eliminará el requerimiento <?= htmlspecialchars(addslashes($r['codigo'])) ?>. Esta acción no se puede deshacer. ¿Continuar?')">
                      <input type="hidden" name="accion" value="eliminar">
                      <input type="hidden" name="campana_id" value="<?= (int)$r['id'] ?>">
                      <button type="submit" class="op-eliminar">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                        Eliminar requerimiento
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
    <span>Mostrando <?= $total?1:0 ?> a <?= $total ?> de <?= $total ?> requerimiento<?= $total===1?'':'s' ?></span>
    <div class="paginacion"><button class="pag activa">1</button></div>
  </div>
</section>

<!-- ===== Modal: Nuevo / Editar Requerimiento ===== -->
<div class="modal-fondo" id="modalReq">
  <div class="modal-caja">
    <div class="modal-cab">
      <h3 id="modalReqTitulo">Nuevo Requerimiento</h3>
      <button class="modal-x" onclick="cerrarModal('modalReq')" aria-label="Cerrar">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <form action="guardar_requerimiento.php" method="POST" class="modal-form">
      <input type="hidden" name="accion" id="rAccion" value="crear">
      <input type="hidden" name="campana_id" id="rId" value="">
      <div class="mf-campo">
        <label>Cargo / Posición *</label>
        <input type="text" name="puesto" id="rPuesto" required maxlength="120" placeholder="Ej. Asesor de Atención al Cliente">
      </div>
      <div class="mf-campo">
        <label>Descripción (se muestra en el portal)</label>
        <textarea name="descripcion" id="rDesc" rows="2" maxlength="400" placeholder="Resumen de la vacante para el portal público" style="width:100%;padding:9px 12px;border:1px solid var(--borde);border-radius:8px;font-family:inherit;font-size:13.5px;resize:vertical"></textarea>
      </div>
      <div class="mf-fila">
        <div class="mf-campo">
          <label>Área *</label>
          <select name="area_id" id="rArea" required>
            <option value="">Seleccione...</option>
            <?php foreach($areasForm as $a): ?><option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['nombre']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="mf-campo">
          <label>N.º de vacantes *</label>
          <input type="number" name="vacantes" id="rVac" required min="1" value="1">
        </div>
      </div>
      <div class="mf-fila">
        <div class="mf-campo">
          <label>Tipo</label>
          <select name="tipo" id="rTipo"><option>Nueva Posición</option><option>Reemplazo</option></select>
        </div>
        <div class="mf-campo">
          <label>Prioridad</label>
          <select name="prioridad" id="rPrio"><option>Alta</option><option>Media</option><option>Baja</option></select>
        </div>
      </div>
      <div class="mf-fila">
        <div class="mf-campo">
          <label>Ubicación</label>
          <input type="text" name="ubicacion" id="rUbic" maxlength="120" placeholder="Ej. Magdalena, Lima">
        </div>
        <div class="mf-campo">
          <label>Horario</label>
          <input type="text" name="horario" id="rHor" maxlength="120" placeholder="Ej. Full time rotativo">
        </div>
      </div>
      <div class="mf-fila">
        <div class="mf-campo">
          <label>Remuneración (S/)</label>
          <input type="number" name="remuneracion" id="rRem" min="0" step="0.01" placeholder="Ej. 1130">
        </div>
        <div class="mf-campo">
          <label>Fecha de ingreso</label>
          <input type="date" name="fecha_ingreso" id="rFecha">
        </div>
      </div>
      <div class="mf-fila">
        <div class="mf-campo">
          <label>Modalidad</label>
          <select name="modalidad" id="rMod">
            <option value="Presencial">Presencial</option>
            <option value="Híbrido">Híbrido</option>
            <option value="Remoto">Remoto</option>
          </select>
        </div>
        <div class="mf-campo">
          <label>&nbsp;</label>
          <span style="font-size:12px;color:var(--texto-3)">Se muestra en la ficha del portal.</span>
        </div>
      </div>
      <div class="mf-campo">
        <label>Tareas y responsabilidades del portal (un punto por línea)</label>
        <textarea name="tareas" id="rTareas" rows="3" placeholder="Atender consultas por teléfono y correo.&#10;Registrar casos en el sistema." style="width:100%;padding:9px 12px;border:1px solid var(--borde);border-radius:8px;font-family:inherit;font-size:13.5px;resize:vertical"></textarea>
      </div>
      <div class="mf-campo">
        <label>Requisitos del portal (un punto por línea)</label>
        <textarea name="requisitos" id="rReq" rows="3" placeholder="Experiencia previa en el área.&#10;Manejo de herramientas digitales." style="width:100%;padding:9px 12px;border:1px solid var(--borde);border-radius:8px;font-family:inherit;font-size:13.5px;resize:vertical"></textarea>
      </div>
      <div class="mf-campo">
        <label>Beneficios del portal (un punto por línea)</label>
        <textarea name="beneficios" id="rBen" rows="3" placeholder="Ingreso a planilla.&#10;Capacitación continua." style="width:100%;padding:9px 12px;border:1px solid var(--borde);border-radius:8px;font-family:inherit;font-size:13.5px;resize:vertical"></textarea>
      </div>
      <label class="mf-check"><input type="checkbox" name="publicado" id="rPub" value="1"> Publicar la vacante (visible para captación)</label>
      <div class="modal-pie">
        <button type="button" class="btn-linea" onclick="cerrarModal('modalReq')">Cancelar</button>
        <button type="submit" class="btn btn-azul" id="btnGuardarReq">Guardar requerimiento</button>
      </div>
    </form>
  </div>
</div>

<!-- ===== Modal: Ver requerimiento ===== -->
<div class="modal-fondo" id="modalVerReq">
  <div class="modal-caja" style="max-width:520px">
    <div class="modal-cab">
      <h3>Ficha del requerimiento</h3>
      <button class="modal-x" onclick="cerrarModal('modalVerReq')" aria-label="Cerrar">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="modal-form">
      <div class="ficha-cab">
        <div>
          <strong id="rvPuesto">—</strong>
          <span id="rvCodigo">—</span>
        </div>
      </div>
      <p id="rvDesc" style="font-size:13.5px;color:var(--texto-2);margin:6px 0 0;line-height:1.5"></p>
      <dl class="ficha-datos" id="rvDatos"></dl>
      <div class="modal-pie">
        <a class="btn-linea" id="rvPortal" href="#" target="_blank" rel="noopener" style="text-decoration:none;display:none">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
          Ver en el portal
        </a>
        <button type="button" class="btn-linea" onclick="cerrarModal('modalVerReq')">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<script>
function cerrarModal(id){ document.getElementById(id).classList.remove('abierto'); }
function abrirModal(id){ document.getElementById(id).classList.add('abierto'); }

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
document.addEventListener('keydown', e => { if (e.key === 'Escape') cerrarMenus(); });

function nuevaReq(){
  const f = document.getElementById('modalReq').querySelector('form');
  f.reset();
  document.getElementById('modalReqTitulo').textContent = 'Nuevo Requerimiento';
  document.getElementById('btnGuardarReq').textContent = 'Guardar requerimiento';
  document.getElementById('rAccion').value = 'crear';
  document.getElementById('rId').value = '';
  abrirModal('modalReq');
}

function editarReq(btn){
  const d = btn.dataset;
  const set = (id, v) => { document.getElementById(id).value = v; };
  document.getElementById('modalReqTitulo').textContent = 'Editar Requerimiento';
  document.getElementById('btnGuardarReq').textContent = 'Guardar cambios';
  document.getElementById('rAccion').value = 'editar';
  set('rId', d.id);
  set('rPuesto', d.puesto);
  set('rDesc', d.descripcion);
  set('rArea', d.area);
  // Si el área quedó fuera de la lista del formulario (Marketing/Operaciones),
  // se agrega la opción para no perder el valor al editar.
  const selArea = document.getElementById('rArea');
  if (d.area !== '' && !Array.from(selArea.options).some(o => o.value === String(d.area))) {
    const op = document.createElement('option');
    op.value = d.area;
    op.textContent = d.areaNombre || ('Área ' + d.area);
    selArea.appendChild(op);
    selArea.value = d.area;
  }
  set('rVac', d.vacantes);
  set('rTipo', d.tipo);
  set('rPrio', d.prioridad);
  set('rUbic', d.ubicacion);
  set('rHor', d.horario);
  set('rRem', d.remuneracion);
  set('rFecha', d.fecha);
  set('rMod', d.modalidad);
  set('rTareas', d.tareas);
  set('rReq', d.requisitos);
  set('rBen', d.beneficios);
  document.getElementById('rPub').checked = d.publicado === '1';
  abrirModal('modalReq');
}

function verReq(btn){
  const d = btn.dataset;
  document.getElementById('rvPuesto').textContent = d.puesto;
  document.getElementById('rvCodigo').textContent = d.codigo;
  document.getElementById('rvDesc').textContent = d.descripcion || 'Sin descripción registrada.';
  const filas = [
    ['Área', d.area],
    ['Tipo', d.tipo],
    ['Vacantes', d.vacantes],
    ['Estado', d.estado],
    ['Prioridad', d.prioridad],
    ['Publicado en el portal', d.publicado],
    ['Fecha de solicitud', d.fecha],
    ['Ubicación', d.ubicacion || '—'],
    ['Modalidad', d.modalidad],
    ['Horario', d.horario || '—'],
    ['Remuneración', d.remuneracion]
  ];
  document.getElementById('rvDatos').innerHTML = filas.map(f =>
    '<dt>' + f[0] + '</dt><dd>' + f[1] + '</dd>').join('');
  // Enlace a la publicación pública (solo si está publicada y abierta)
  const rvPortal = document.getElementById('rvPortal');
  if (d.publicado === 'Sí' && d.estado !== 'Cerrado') {
    rvPortal.href = 'portal/trabajo.php?id=' + d.id;
    rvPortal.style.display = 'inline-flex';
  } else {
    rvPortal.href = '#';
    rvPortal.style.display = 'none';
  }
  abrirModal('modalVerReq');
}
</script>

<?php require_once __DIR__ . '/includes/layout_bottom.php'; ?>
