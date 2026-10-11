<?php
$tituloPagina = 'Kanban de Reclutamiento';
$activo = 'candidatos';
$estilosExtra = ['assets/css/candidatos.css', 'assets/css/kanban.css'];

require_once __DIR__ . '/includes/guardia.php';
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/includes/alcance.php';
$pdo = obtenerConexion();

require_once __DIR__ . '/includes/flujo.php';

/* Etapas del embudo (columnas). «Postulación» es automática: el candidato pasa de inmediato
   a la evaluación, por eso el tablero empieza en la etapa 2. */
$etapas = $pdo->query("SELECT id, nombre, color, orden FROM etapas WHERE orden >= 2 ORDER BY orden ASC")->fetchAll();
$descEtapa = [
    2 => 'El postulante rinde el test en el portal',
    3 => 'Datos, CV y documentos',
    4 => 'Entrevista presencial',
    5 => 'Hasta 5 capacitaciones con asistencia',
    6 => 'Fin del proceso',
];

/* Postulaciones en proceso, agrupadas luego por etapa */
$filas = $pdo->query(
    "SELECT p.id, p.etapa_id, p.puntaje_cv, p.fecha_postulacion, p.val_datos, p.val_docs,
            po.nombres, po.dni, c.puesto,
            (SELECT r.puntaje FROM resultados_eval r WHERE r.postulacion_id = p.id ORDER BY r.id DESC LIMIT 1) AS test_puntaje,
            (SELECT r.aprobado FROM resultados_eval r WHERE r.postulacion_id = p.id ORDER BY r.id DESC LIMIT 1) AS test_aprobado,
            (SELECT COUNT(*) FROM capacitaciones k WHERE k.postulacion_id = p.id AND k.asistencia = 'Asistió') AS caps_ok,
            (SELECT COUNT(*) FROM capacitaciones k WHERE k.postulacion_id = p.id) AS caps_total
     FROM postulaciones p
     INNER JOIN postulantes po ON po.id = p.postulante_id
     INNER JOIN campanas   c  ON c.id  = p.campana_id
     WHERE p.estado IN ('En Proceso','Seleccionado')" . sqlAlcancePost('p.campana_id') . "
     ORDER BY p.fecha_postulacion DESC"
)->fetchAll();

/* Vacantes reales para el filtro (el reclutador solo ve las de su área) */
$vacantesFiltro = $pdo->query("SELECT DISTINCT puesto FROM campanas WHERE 1=1" . sqlAlcanceCampana('area_id') . " ORDER BY puesto")->fetchAll(PDO::FETCH_COLUMN);

/* Agrupar tarjetas por etapa_id */
$tarjetas = [];
foreach ($etapas as $e) { $tarjetas[$e['id']] = []; }
foreach ($filas as $f) { if (isset($tarjetas[$f['etapa_id']])) $tarjetas[$f['etapa_id']][] = $f; }

function iniciales(string $n): string {
    $p = preg_split('/\s+/', trim($n));
    return mb_strtoupper(mb_substr($p[0] ?? '', 0, 1) . mb_substr($p[1] ?? '', 0, 1));
}
function colorAvatar(string $txt): string {
    $cols = ['#3b82f6','#8b5cf6','#0ea5a4','#ec4899','#f59e0b','#22a06b','#d946ef','#6366f1'];
    $s=0; foreach (str_split($txt) as $ch) { $s+=ord($ch); }
    return $cols[$s % count($cols)];
}

require_once __DIR__ . '/includes/layout_top.php';
?>

<!-- ===== Encabezado ===== -->
<div class="panel-encabezado">
  <div>
    <h2>Kanban de Reclutamiento</h2>
    <p>Visualiza y gestiona el flujo de candidatos en cada etapa del proceso.</p>
  </div>
  <div class="acciones-panel">
    <button class="btn-linea" type="button" id="kbExportar">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
      Exportar
    </button>
    <a class="btn btn-azul" href="candidatos.php?nuevo=1">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Registrar candidato
    </a>
    <button class="btn-linea" type="button" id="kbPersonalizar">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="18" rx="1"/><rect x="14" y="3" width="7" height="11" rx="1"/></svg>
      Personalizar etapas
    </button>
  </div>
</div>

<!-- ===== Toolbar ===== -->
<div class="kb-toolbar">
  <div class="kb-buscar">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
    <input type="text" id="kbBuscar" placeholder="Buscar candidato o vacante...">
  </div>
  <select class="kb-select" id="kbVacante">
    <option value="">Todas las vacantes</option>
    <?php foreach ($vacantesFiltro as $vf): ?><option><?= htmlspecialchars($vf) ?></option><?php endforeach; ?>
  </select>
  <div class="kb-pop-wrap"><button class="kb-btn" type="button" id="kbFiltros">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
    Filtros
  </button>
    <div class="kb-pop" id="popFiltros" hidden>
      <label>Puntaje de CV mínimo</label>
      <select id="fCv"><option value="0">Todos</option><option value="50">50% o más (apto)</option><option value="70">70% o más</option></select>
      <label class="kb-chk"><input type="checkbox" id="fSoloCv"> Solo candidatos con CV</label>
      <div class="kb-pop-pie"><button type="button" class="kb-link" id="fLimpiar">Limpiar</button></div>
    </div>
  </div>
  <div class="kb-toggle">
    <a href="kanban.php" class="on"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="18" rx="1"/><rect x="14" y="3" width="7" height="11" rx="1"/></svg>Tablero</a>
    <a href="candidatos.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>Lista</a>
  </div>
  <div class="kb-pop-wrap"><button class="kb-config" type="button" id="kbConfig" title="Configuración del tablero" aria-label="Configuración del tablero"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg></button>
    <div class="kb-pop kb-pop-der" id="popConfig" hidden>
      <strong>Vista del tablero</strong>
      <label class="kb-chk"><input type="checkbox" id="cCompacta"> Tarjetas compactas</label>
      <label class="kb-chk"><input type="checkbox" id="cPuntaje" checked> Mostrar puntaje de CV</label>
      <label class="kb-chk"><input type="checkbox" id="cDni" checked> Mostrar DNI</label>
      <label class="kb-chk"><input type="checkbox" id="cEstado" checked> Mostrar avance (test, validación, capacitaciones)</label>
      <div class="kb-pop-pie"><button type="button" class="kb-link" id="cRestablecer">Restablecer</button></div>
    </div>
  </div>
</div>

<!-- ===== Tablero ===== -->
<div class="kb-toast" id="kbToast" hidden></div>
<div class="kb-board" id="kbBoard">
  <?php foreach ($etapas as $col):
    $eid = (int)$col['id'];
    $lista = $tarjetas[$eid] ?? []; ?>
    <div class="kb-col" data-orden="<?= (int)$col['orden'] ?>" style="--col:<?= htmlspecialchars($col['color']) ?>">
      <div class="kb-col-head">
        <div class="kb-col-title">
          <span class="dot"></span>
          <h3><?= htmlspecialchars($col['nombre']) ?></h3>
          <span class="cuenta" data-col="<?= $eid ?>"><?= count($lista) ?></span>
        </div>
        <div class="kb-col-desc"><?= htmlspecialchars($descEtapa[(int)$col['orden']] ?? '') ?></div>
      </div>

      <div class="kb-cards" data-col="<?= $eid ?>">
        <?php foreach ($lista as $t):
          $color_av = colorAvatar($t['dni']);
          $score = $t['puntaje_cv'];
          $ord = (int)$col['orden'];
          $chips = [];
          if ($ord === 2) $chips[] = ['pen', 'Test pendiente'];
          if ($ord >= 3 && $t['test_puntaje'] !== null) $chips[] = [(int)$t['test_aprobado'] ? 'ok' : 'mal', 'Test ' . (int)$t['test_puntaje'] . '%'];
          if ($ord === 3) { $chips[] = [(int)$t['val_datos'] ? 'ok' : 'pen', 'Datos']; $chips[] = [(int)$t['val_docs'] ? 'ok' : 'pen', 'Docs']; }
          if ($ord === 5) $chips[] = [((int)$t['caps_total'] > 0 && (int)$t['caps_ok'] === (int)$t['caps_total']) ? 'ok' : 'pen', 'Cap. ' . (int)$t['caps_ok'] . '/' . (int)$t['caps_total']];
          if ($ord === 6) $chips[] = ['ok', 'Finalizado'];
        ?>
          <div class="kb-card" draggable="true" data-id="<?= (int)$t['id'] ?>" data-puesto="<?= htmlspecialchars($t['puesto']) ?>"
               data-nombre="<?= htmlspecialchars($t['nombres']) ?>" data-dni="<?= htmlspecialchars($t['dni']) ?>" data-cv="<?= $score === null ? '' : (int)$score ?>"
               data-txt="<?= htmlspecialchars(mb_strtolower($t['nombres'] . ' ' . $t['dni'] . ' ' . $t['puesto'])) ?>">
            <div class="kb-card-top">
              <span class="kb-avatar" style="background:<?= $color_av ?>"><?= iniciales($t['nombres']) ?></span>
              <div class="kb-card-info">
                <div class="nom"><?= htmlspecialchars($t['nombres']) ?></div>
                <div class="rol"><?= htmlspecialchars($t['puesto']) ?></div>
              </div>
              <a class="kb-card-menu" href="proceso.php?post=<?= (int)$t['id'] ?>" draggable="false" title="Gestionar proceso" aria-label="Gestionar proceso"><svg viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="12" cy="19" r="1.6"/></svg></a>
            </div>
            <div class="kb-card-bot">
              <span class="fecha kb-dni">DNI: <?= htmlspecialchars($t['dni']) ?></span>
              <?php if ($score !== null): ?>
                <span class="kb-score kb-puntaje <?= (int)$score >= FLUJO_UMBRAL_CV ? 'alto' : 'bajo' ?>" title="Puntaje CV (apto desde <?= FLUJO_UMBRAL_CV ?>%)"><?= (int)$score ?>%</span>
              <?php endif; ?>
            </div>
            <?php if ($chips): ?><div class="kb-chips kb-estado"><?php foreach ($chips as $ch): ?><span class="kb-chip <?= $ch[0] ?>"><?= htmlspecialchars($ch[1]) ?></span><?php endforeach; ?></div><?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<!-- ===== Modal: personalizar etapas (columnas visibles) ===== -->
<div class="modal-fondo" id="modalEtapasKb">
  <div class="modal-caja">
    <div class="modal-cab"><h3>Personalizar etapas del tablero</h3>
      <button class="modal-x" type="button" id="kbCerrarModal" aria-label="Cerrar"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button></div>
    <div class="modal-form">
      <p class="me-info">El orden de las etapas es el flujo oficial del proceso y no se puede cambiar. Aquí eliges qué columnas ver en tu tablero.</p>
      <?php foreach ($etapas as $col): ?>
        <label class="kb-chk kb-chk-et"><input type="checkbox" class="kbVerCol" data-orden="<?= (int)$col['orden'] ?>" checked>
          <span class="dot" style="background:<?= htmlspecialchars($col['color']) ?>"></span> <?= (int)$col['orden'] ?>. <?= htmlspecialchars($col['nombre']) ?></label>
      <?php endforeach; ?>
      <div class="modal-pie">
        <button type="button" class="btn-linea" id="kbTodasCols">Mostrar todas</button>
        <button type="button" class="btn btn-azul" id="kbAplicarCols">Listo</button>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  var KEY = 'ssr_kanban_cfg';
  var cfg = { compacta:false, puntaje:true, dni:true, estado:true, ocultas:[], cvMin:0, soloCv:false };
  try { var g = JSON.parse(localStorage.getItem(KEY) || 'null'); if (g) for (var k in g) cfg[k] = g[k]; } catch (e) {}
  function guardar() { try { localStorage.setItem(KEY, JSON.stringify(cfg)); } catch (e) {} }
  var $ = function (id) { return document.getElementById(id); };
  var board = $('kbBoard');

  /* ---- Aplicar configuración de vista ---- */
  function aplicarVista() {
    board.classList.toggle('compacta', !!cfg.compacta);
    board.classList.toggle('sin-puntaje', !cfg.puntaje);
    board.classList.toggle('sin-dni', !cfg.dni);
    board.classList.toggle('sin-estado', !cfg.estado);
    document.querySelectorAll('.kb-col').forEach(function (c) {
      c.style.display = cfg.ocultas.indexOf(parseInt(c.dataset.orden, 10)) !== -1 ? 'none' : '';
    });
    $('cCompacta').checked = !!cfg.compacta; $('cPuntaje').checked = !!cfg.puntaje; $('cDni').checked = !!cfg.dni; $('cEstado').checked = !!cfg.estado;
    document.querySelectorAll('.kbVerCol').forEach(function (x) { x.checked = cfg.ocultas.indexOf(parseInt(x.dataset.orden, 10)) === -1; });
    $('fCv').value = String(cfg.cvMin); $('fSoloCv').checked = !!cfg.soloCv;
  }

  /* ---- Filtros: búsqueda, vacante, puntaje ---- */
  var q = $('kbBuscar'), v = $('kbVacante');
  function filtrar() {
    var t = q.value.trim().toLowerCase(), vac = v.value, min = parseInt(cfg.cvMin, 10) || 0;
    document.querySelectorAll('.kb-card').forEach(function (c) {
      var cv = c.dataset.cv === '' ? null : parseInt(c.dataset.cv, 10);
      var ok = (!t || c.dataset.txt.indexOf(t) !== -1) && (!vac || c.dataset.puesto === vac)
            && (!min || (cv !== null && cv >= min)) && (!cfg.soloCv || cv !== null);
      c.style.display = ok ? '' : 'none';
    });
  }
  q.addEventListener('input', filtrar); v.addEventListener('change', filtrar);
  $('fCv').addEventListener('change', function () { cfg.cvMin = parseInt(this.value, 10) || 0; guardar(); filtrar(); });
  $('fSoloCv').addEventListener('change', function () { cfg.soloCv = this.checked; guardar(); filtrar(); });
  $('fLimpiar').addEventListener('click', function () { cfg.cvMin = 0; cfg.soloCv = false; q.value = ''; v.value = ''; guardar(); aplicarVista(); filtrar(); });

  /* ---- Popovers (Filtros y engranaje) ---- */
  function pop(btn, panel) {
    btn.addEventListener('click', function (e) {
      e.stopPropagation();
      document.querySelectorAll('.kb-pop').forEach(function (p) { if (p !== panel) p.hidden = true; });
      panel.hidden = !panel.hidden;
    });
    panel.addEventListener('click', function (e) { e.stopPropagation(); });
  }
  pop($('kbFiltros'), $('popFiltros')); pop($('kbConfig'), $('popConfig'));
  document.addEventListener('click', function () { document.querySelectorAll('.kb-pop').forEach(function (p) { p.hidden = true; }); });
  [['cCompacta','compacta'],['cPuntaje','puntaje'],['cDni','dni'],['cEstado','estado']].forEach(function (x) {
    $(x[0]).addEventListener('change', function () { cfg[x[1]] = this.checked; guardar(); aplicarVista(); });
  });
  $('cRestablecer').addEventListener('click', function () { cfg.compacta = false; cfg.puntaje = cfg.dni = cfg.estado = true; cfg.ocultas = []; guardar(); aplicarVista(); });

  /* ---- Personalizar etapas (columnas visibles) ---- */
  var modal = $('modalEtapasKb');
  $('kbPersonalizar').addEventListener('click', function () { modal.classList.add('abierto'); });
  function cerrarModal() { modal.classList.remove('abierto'); }
  $('kbCerrarModal').addEventListener('click', cerrarModal);
  $('kbAplicarCols').addEventListener('click', function () {
    cfg.ocultas = []; document.querySelectorAll('.kbVerCol').forEach(function (x) { if (!x.checked) cfg.ocultas.push(parseInt(x.dataset.orden, 10)); });
    guardar(); aplicarVista(); cerrarModal();
  });
  $('kbTodasCols').addEventListener('click', function () { document.querySelectorAll('.kbVerCol').forEach(function (x) { x.checked = true; }); });

  /* ---- Exportar (CSV de lo que se ve en el tablero) ---- */
  $('kbExportar').addEventListener('click', function () {
    var filas = [['Etapa', 'Candidato', 'DNI', 'Vacante', 'Puntaje CV']];
    document.querySelectorAll('.kb-col').forEach(function (col) {
      if (col.style.display === 'none') return;
      var et = col.querySelector('h3').textContent.trim();
      col.querySelectorAll('.kb-card').forEach(function (c) {
        if (c.style.display === 'none') return;
        filas.push([et, c.dataset.nombre, c.dataset.dni, c.dataset.puesto, c.dataset.cv === '' ? '' : c.dataset.cv + '%']);
      });
    });
    var csv = '﻿' + filas.map(function (f) { return f.map(function (x) { return '"' + String(x).replace(/"/g, '""') + '"'; }).join(','); }).join('\r\n');
    var a = document.createElement('a');
    a.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }));
    a.download = 'tablero_candidatos_' + new Date().toISOString().slice(0, 10) + '.csv';
    document.body.appendChild(a); a.click(); a.remove();
    aviso('Se exportaron ' + (filas.length - 1) + ' candidatos.', true);
  });

  /* ---- Avisos ---- */
  var tt;
  function aviso(msg, ok) {
    var t = $('kbToast'); t.textContent = msg; t.className = 'kb-toast ' + (ok ? 'ok' : 'mal'); t.hidden = false;
    clearTimeout(tt); tt = setTimeout(function () { t.hidden = true; }, 5000);
  }

  /* ---- Arrastrar y soltar (nativo): el servidor valida el flujo ---- */
  var arrastrada = null, colOrigen = null;
  document.querySelectorAll('.kb-card').forEach(function (card) {
    card.addEventListener('dragstart', function () { arrastrada = card; colOrigen = card.closest('.kb-cards'); setTimeout(function () { card.classList.add('arrastrando'); }, 0); });
    card.addEventListener('dragend', function () { card.classList.remove('arrastrando'); });
    card.addEventListener('dblclick', function () { location.href = 'proceso.php?post=' + card.dataset.id; });
  });
  document.querySelectorAll('.kb-cards').forEach(function (zona) {
    zona.addEventListener('dragover', function (e) { e.preventDefault(); zona.classList.add('drag-over'); });
    zona.addEventListener('dragleave', function () { zona.classList.remove('drag-over'); });
    zona.addEventListener('drop', function (e) {
      e.preventDefault(); zona.classList.remove('drag-over');
      if (!arrastrada || zona === colOrigen) return;
      var card = arrastrada, destino = zona, anterior = colOrigen;
      destino.appendChild(card); cuenta(anterior, -1); cuenta(destino, +1);
      var datos = new FormData();
      datos.append('ajax', '1'); datos.append('postulacion_id', card.dataset.id); datos.append('etapa_destino', destino.dataset.col);
      function revertir(msg) { anterior.appendChild(card); cuenta(destino, -1); cuenta(anterior, +1); aviso(msg, false); }
      fetch('mover_etapa.php', { method: 'POST', body: datos })
        .then(function (r) { return r.json(); })
        .then(function (res) { if (!res.ok) revertir(res.mensaje || 'No se pudo mover el candidato.'); else aviso('Candidato movido a «' + res.etapa_nombre + '».', true); })
        .catch(function () { revertir('Error de conexión al mover el candidato.'); });
    });
  });
  function cuenta(zona, d) {
    if (!zona) return;
    var b = document.querySelector('.cuenta[data-col="' + zona.getAttribute('data-col') + '"]');
    if (b) b.textContent = Math.max(0, (parseInt(b.textContent, 10) || 0) + d);
  }

  aplicarVista(); filtrar();
})();
</script>

<?php require_once __DIR__ . '/includes/layout_bottom.php'; ?>
