<?php
/* ============================================================
   SSR - A365 | Banco de preguntas por área (HU-08 / RF-09)
   Administrador y Supervisor configuran la evaluación de cada área:
   nota mínima, tiempo límite, cantidad de preguntas y el banco.
   ============================================================ */
$tituloPagina = 'Evaluaciones · Banco de preguntas';
$activo = 'evaluaciones';
$estilosExtra = ['assets/css/candidatos.css', 'assets/css/usuarios.css', 'assets/css/evaluaciones.css'];

require_once __DIR__ . '/includes/guardia.php';
require_once __DIR__ . '/config/conexion.php';

if (!in_array(($_SESSION['usuario_rol'] ?? ''), ['Administrador','Supervisor'], true)) {
    header('Location: dashboard.php'); exit;
}
$pdo = obtenerConexion();

$flashOk    = $_SESSION['flash_ok']    ?? '';
$flashError = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_ok'], $_SESSION['flash_error']);

/* Áreas y área seleccionada */
$areas = $pdo->query("SELECT id, nombre FROM areas WHERE estado = 1 ORDER BY id")->fetchAll();
$areaId = (int)($_GET['area'] ?? ($areas[0]['id'] ?? 0));

/* Evaluación del área (puede no existir aún) */
$st = $pdo->prepare("SELECT * FROM evaluaciones WHERE area_id = :a ORDER BY id LIMIT 1");
$st->execute([':a' => $areaId]);
$evaluacion = $st->fetch();

/* Preguntas con sus opciones */
$preguntas = [];
if ($evaluacion) {
    $st = $pdo->prepare("SELECT id, enunciado FROM preguntas WHERE evaluacion_id = :e ORDER BY id");
    $st->execute([':e' => $evaluacion['id']]);
    $preguntas = $st->fetchAll();
    $stO = $pdo->prepare("SELECT texto, correcta FROM opciones WHERE pregunta_id = :p ORDER BY id");
    foreach ($preguntas as &$pq) {
        $stO->execute([':p' => $pq['id']]);
        $pq['opciones'] = $stO->fetchAll();
    }
    unset($pq);
}

/* Resultados registrados para esta evaluación */
$totalRendidas = 0; $aprobadas = 0;
if ($evaluacion) {
    $st = $pdo->prepare("SELECT COUNT(*) t, COALESCE(SUM(aprobado),0) a FROM resultados_eval WHERE evaluacion_id = :e");
    $st->execute([':e' => $evaluacion['id']]);
    $r = $st->fetch(); $totalRendidas = (int)$r['t']; $aprobadas = (int)$r['a'];
}

require_once __DIR__ . '/includes/layout_top.php';
?>

<div class="panel-encabezado">
  <div>
    <h2>Evaluaciones por área</h2>
    <p>Configura la prueba que rinde cada candidato según el área de su campaña.</p>
  </div>
</div>

<!-- Pestañas por área -->
<div class="subtabs">
  <?php foreach ($areas as $a): ?>
    <a href="banco_preguntas.php?area=<?= $a['id'] ?>" class="<?= (int)$a['id'] === $areaId ? 'on' : '' ?>"><?= htmlspecialchars($a['nombre']) ?></a>
  <?php endforeach; ?>
</div>

<?php if ($flashOk): ?><div class="aviso aviso-ok"><?= htmlspecialchars($flashOk) ?></div><?php endif; ?>
<?php if ($flashError): ?><div class="aviso aviso-error"><?= htmlspecialchars($flashError) ?></div><?php endif; ?>

<div class="ev-grid">

  <!-- Configuración -->
  <section class="tarjeta">
    <h3 class="ev-h">Configuración de la evaluación</h3>
    <form action="guardar_pregunta.php" method="POST">
      <input type="hidden" name="accion" value="config">
      <input type="hidden" name="area_id" value="<?= $areaId ?>">
      <div class="mf-campo">
        <label>Nombre de la evaluación</label>
        <input type="text" name="nombre" maxlength="120" required
               value="<?= htmlspecialchars($evaluacion['nombre'] ?? '') ?>" placeholder="Ej. Evaluación de aptitud comercial">
      </div>
      <div class="mf-fila ev-fila3">
        <div class="mf-campo"><label>Nota mínima (%)</label>
          <input type="number" name="nota_minima" min="1" max="100" required value="<?= (int)($evaluacion['nota_minima'] ?? 70) ?>"></div>
        <div class="mf-campo"><label>Tiempo (min)</label>
          <input type="number" name="tiempo_limite" min="1" max="180" required value="<?= (int)($evaluacion['tiempo_limite'] ?? 20) ?>"></div>
        <div class="mf-campo"><label>N.º de preguntas</label>
          <input type="number" name="total_preguntas" min="1" max="100" required value="<?= (int)($evaluacion['total_preguntas'] ?? 20) ?>"></div>
      </div>
      <button class="btn btn-azul" type="submit"><?= $evaluacion ? 'Guardar configuración' : 'Crear evaluación' ?></button>
    </form>

    <div class="ev-stats">
      <div><strong><?= count($preguntas) ?></strong><span>preguntas en el banco</span></div>
      <div><strong><?= $totalRendidas ?></strong><span>evaluaciones rendidas</span></div>
      <div><strong><?= $totalRendidas ? round($aprobadas * 100 / $totalRendidas) : 0 ?>%</strong><span>tasa de aprobación</span></div>
    </div>
    <?php if ($evaluacion && count($preguntas) < (int)$evaluacion['total_preguntas']): ?>
      <p class="ev-nota">El banco tiene menos preguntas que las configuradas; se tomarán todas las disponibles (<?= count($preguntas) ?>).</p>
    <?php endif; ?>
  </section>

  <!-- Nueva pregunta -->
  <section class="tarjeta">
    <h3 class="ev-h">Agregar pregunta</h3>
    <?php if (!$evaluacion): ?>
      <p class="ev-nota">Primero crea la evaluación de esta área.</p>
    <?php else: ?>
    <form action="guardar_pregunta.php" method="POST">
      <input type="hidden" name="accion" value="agregar">
      <input type="hidden" name="area_id" value="<?= $areaId ?>">
      <div class="mf-campo"><label>Enunciado *</label>
        <input type="text" name="enunciado" maxlength="400" required placeholder="Escribe la pregunta"></div>
      <p class="ev-ayuda">Escribe las opciones y marca la correcta.</p>
      <?php for ($i = 0; $i < 4; $i++): ?>
        <label class="ev-opc">
          <input type="radio" name="correcta" value="<?= $i ?>" <?= $i === 0 ? 'required' : '' ?>>
          <input type="text" name="opciones[]" maxlength="300" <?= $i < 2 ? 'required' : '' ?>
                 placeholder="Opción <?= chr(65 + $i) ?><?= $i >= 2 ? ' (opcional)' : '' ?>">
        </label>
      <?php endfor; ?>
      <button class="btn btn-azul" type="submit">Agregar al banco</button>
    </form>
    <?php endif; ?>
  </section>

  <!-- Listado -->
  <section class="tarjeta ev-full">
    <h3 class="ev-h">Banco de preguntas (<?= count($preguntas) ?>)</h3>
    <?php if (!$preguntas): ?>
      <p class="ev-nota">Aún no hay preguntas para esta área.</p>
    <?php endif; ?>
    <ol class="ev-lista">
      <?php foreach ($preguntas as $pq): ?>
        <li>
          <div class="ev-preg">
            <span><?= htmlspecialchars($pq['enunciado']) ?></span>
            <form action="guardar_pregunta.php" method="POST" onsubmit="return confirm('¿Eliminar esta pregunta del banco?');">
              <input type="hidden" name="accion" value="eliminar">
              <input type="hidden" name="area_id" value="<?= $areaId ?>">
              <input type="hidden" name="pregunta_id" value="<?= $pq['id'] ?>">
              <button class="icobtn" type="submit" title="Eliminar" aria-label="Eliminar">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/></svg>
              </button>
            </form>
          </div>
          <div class="ev-opciones">
            <?php foreach ($pq['opciones'] as $op): ?>
              <span class="<?= (int)$op['correcta'] === 1 ? 'correcta' : '' ?>"><?= htmlspecialchars($op['texto']) ?></span>
            <?php endforeach; ?>
          </div>
        </li>
      <?php endforeach; ?>
    </ol>
  </section>
</div>

<?php require_once __DIR__ . '/includes/layout_bottom.php'; ?>
