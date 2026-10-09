<?php
$tituloPagina = 'Kanban de Reclutamiento';
$activo = 'candidatos';
$estilosExtra = ['assets/css/candidatos.css', 'assets/css/kanban.css'];

require_once __DIR__ . '/includes/guardia.php';
require_once __DIR__ . '/config/conexion.php';
$pdo = obtenerConexion();

/* Etapas del embudo (columnas) desde la BD */
$etapas = $pdo->query("SELECT id, nombre, color FROM etapas ORDER BY orden ASC")->fetchAll();

/* Postulaciones en proceso, agrupadas luego por etapa */
$filas = $pdo->query(
    "SELECT p.id, p.etapa_id, p.puntaje_cv, p.fecha_postulacion,
            po.nombres, po.dni, c.puesto
     FROM postulaciones p
     INNER JOIN postulantes po ON po.id = p.postulante_id
     INNER JOIN campanas   c  ON c.id  = p.campana_id
     WHERE p.estado = 'En Proceso'
     ORDER BY p.fecha_postulacion DESC"
)->fetchAll();

/* Agrupar tarjetas por etapa_id */
$tarjetas = [];
foreach ($etapas as $e) { $tarjetas[$e['id']] = []; }
foreach ($filas as $f) { $tarjetas[$f['etapa_id']][] = $f; }

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
    <button class="btn-linea">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
      Exportar
    </button>
    <button class="btn btn-azul">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
      Personalizar Etapas
    </button>
  </div>
</div>

<!-- ===== Toolbar ===== -->
<div class="kb-toolbar">
  <div class="kb-buscar">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
    <input type="text" placeholder="Buscar candidato o vacante...">
  </div>
  <select class="kb-select"><option>Todas las vacantes</option><option>Ejecutivo de Ventas Corporativas</option><option>Analista de Soporte Técnico</option><option>Practicante de Marketing</option></select>
  <select class="kb-select"><option>Todos los reclutadores</option><option>Héctor Crisostomo</option><option>María Augusta Díaz</option></select>
  <button class="kb-btn">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
    Filtros
  </button>
  <div class="kb-toggle">
    <a href="kanban.php" class="on"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="18" rx="1"/><rect x="14" y="3" width="7" height="11" rx="1"/></svg>Tablero</a>
    <a href="candidatos.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>Lista</a>
  </div>
  <button class="kb-config"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg></button>
</div>

<!-- ===== Tablero ===== -->
<div class="kb-board">
  <?php foreach ($etapas as $col):
    $eid = (int)$col['id'];
    $lista = $tarjetas[$eid] ?? []; ?>
    <div class="kb-col" style="--col:<?= htmlspecialchars($col['color']) ?>">
      <div class="kb-col-head">
        <div class="kb-col-title">
          <span class="dot"></span>
          <h3><?= htmlspecialchars($col['nombre']) ?></h3>
          <span class="cuenta" data-col="<?= $eid ?>"><?= count($lista) ?></span>
        </div>
      </div>

      <div class="kb-cards" data-col="<?= $eid ?>">
        <?php foreach ($lista as $t):
          $color_av = colorAvatar($t['dni']);
          $score = $t['puntaje_cv']; ?>
          <div class="kb-card" draggable="true" data-id="<?= (int)$t['id'] ?>">
            <div class="kb-card-top">
              <span class="kb-avatar" style="background:<?= $color_av ?>"><?= iniciales($t['nombres']) ?></span>
              <div class="kb-card-info">
                <div class="nom"><?= htmlspecialchars($t['nombres']) ?></div>
                <div class="rol"><?= htmlspecialchars($t['puesto']) ?></div>
              </div>
              <span class="kb-card-menu"><svg viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="12" cy="19" r="1.6"/></svg></span>
            </div>
            <div class="kb-card-bot">
              <span class="fecha">DNI: <?= htmlspecialchars($t['dni']) ?></span>
              <?php if ($score !== null): ?>
                <span class="kb-score <?= (int)$score >= 70 ? 'alto' : 'bajo' ?>" title="Puntaje CV"><?= (int)$score ?>%</span>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <button class="kb-add">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Agregar candidato
      </button>
    </div>
  <?php endforeach; ?>
</div>

<!-- ===== Arrastrar y soltar (nativo, sin librerías) ===== -->
<script>
(function () {
  let tarjetaArrastrada = null;
  let colOrigen = null;

  document.querySelectorAll('.kb-card').forEach(function (card) {
    card.addEventListener('dragstart', function () {
      tarjetaArrastrada = card;
      colOrigen = card.closest('.kb-cards');
      setTimeout(() => card.classList.add('arrastrando'), 0);
    });
    card.addEventListener('dragend', function () {
      card.classList.remove('arrastrando');
    });
  });

  document.querySelectorAll('.kb-cards').forEach(function (zona) {
    zona.addEventListener('dragover', function (e) {
      e.preventDefault();
      zona.classList.add('drag-over');
    });
    zona.addEventListener('dragleave', function () {
      zona.classList.remove('drag-over');
    });
    zona.addEventListener('drop', function (e) {
      e.preventDefault();
      zona.classList.remove('drag-over');
      if (!tarjetaArrastrada || zona === colOrigen) return;

      const card = tarjetaArrastrada;
      const zonaDestino = zona;
      const zonaAnterior = colOrigen;
      const postId = card.getAttribute('data-id');
      const etapaDestino = zonaDestino.getAttribute('data-col');

      // Mover visualmente y ajustar contadores
      zonaDestino.appendChild(card);
      cambiarCuenta(zonaAnterior, -1);
      cambiarCuenta(zonaDestino, +1);

      // Persistir en la base (HU-06): actualiza etapa y guarda historial
      const datos = new FormData();
      datos.append('ajax', '1');
      datos.append('postulacion_id', postId);
      datos.append('etapa_destino', etapaDestino);

      fetch('mover_etapa.php', { method:'POST', body:datos })
        .then(r => r.json())
        .then(res => {
          if (!res.ok) {
            // Revertir si el servidor rechaza el cambio
            zonaAnterior.appendChild(card);
            cambiarCuenta(zonaDestino, -1);
            cambiarCuenta(zonaAnterior, +1);
            alert(res.mensaje || 'No se pudo mover el candidato.');
          }
        })
        .catch(() => {
          zonaAnterior.appendChild(card);
          cambiarCuenta(zonaDestino, -1);
          cambiarCuenta(zonaAnterior, +1);
          alert('Error de conexión al mover el candidato.');
        });
    });
  });

  function cambiarCuenta(zona, delta) {
    if (!zona) return;
    const clave = zona.getAttribute('data-col');
    const badge = document.querySelector('.cuenta[data-col="' + clave + '"]');
    if (badge) {
      const n = Math.max(0, (parseInt(badge.textContent, 10) || 0) + delta);
      badge.textContent = n;
    }
  }
})();
</script>

<?php require_once __DIR__ . '/includes/layout_bottom.php'; ?>
