<?php
/* ============================================================
   SSR - A365 | Aplicar evaluación al candidato (HU-08 / RF-09)
   - Toma preguntas al azar del banco del área de la campaña
   - Cronómetro con envío automático al terminar el tiempo
   - Las respuestas correctas nunca se envían al navegador
   - Si ya rindió, muestra el resultado y el detalle
   ============================================================ */
$tituloPagina = 'Evaluación del candidato';
$activo = 'candidatos';
$estilosExtra = ['assets/css/candidatos.css', 'assets/css/usuarios.css', 'assets/css/evaluaciones.css'];

require_once __DIR__ . '/includes/guardia.php';
require_once __DIR__ . '/config/conexion.php';
$pdo = obtenerConexion();

$postId = (int)($_GET['post'] ?? 0);
$flashOk    = $_SESSION['flash_ok']    ?? '';
$flashError = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_ok'], $_SESSION['flash_error']);

/* Postulación + candidato + área */
$st = $pdo->prepare(
    "SELECT p.id, p.estado, p.reclutador_id, po.nombres, po.dni, c.puesto, c.area_id, a.nombre AS area, e.nombre AS etapa
     FROM postulaciones p
     INNER JOIN postulantes po ON po.id = p.postulante_id
     INNER JOIN campanas c ON c.id = p.campana_id
     INNER JOIN areas a ON a.id = c.area_id
     INNER JOIN etapas e ON e.id = p.etapa_id
     WHERE p.id = :id LIMIT 1");
$st->execute([':id' => $postId]);
$post = $st->fetch();
if (!$post) { $_SESSION['flash_error'] = 'La postulación no existe.'; header('Location: candidatos.php'); exit; }

/* Alcance: el reclutador solo evalúa a sus candidatos */
$esSupervisor = in_array(($_SESSION['usuario_rol'] ?? ''), ['Supervisor','Administrador'], true);
if (!$esSupervisor && (int)$post['reclutador_id'] !== (int)($_SESSION['usuario_id'] ?? 0)) {
    $_SESSION['flash_error'] = 'Solo puedes evaluar a los candidatos asignados a ti.';
    header('Location: candidatos.php'); exit;
}

/* Evaluación del área */
$st = $pdo->prepare("SELECT * FROM evaluaciones WHERE area_id = :a AND estado = 1 ORDER BY id LIMIT 1");
$st->execute([':a' => $post['area_id']]);
$eval = $st->fetch();

/* ¿Ya rindió? */
$resultado = null; $detalle = [];
if ($eval) {
    $st = $pdo->prepare("SELECT * FROM resultados_eval WHERE postulacion_id = :p AND evaluacion_id = :e ORDER BY id DESC LIMIT 1");
    $st->execute([':p' => $postId, ':e' => $eval['id']]);
    $resultado = $st->fetch();
    if ($resultado) {
        $st = $pdo->prepare(
            "SELECT q.enunciado, oe.texto AS elegida, COALESCE(oe.correcta,0) AS acierto,
                    (SELECT oc.texto FROM opciones oc WHERE oc.pregunta_id = q.id AND oc.correcta = 1 LIMIT 1) AS correcta
             FROM respuestas r
             INNER JOIN preguntas q ON q.id = r.pregunta_id
             LEFT JOIN opciones oe ON oe.id = r.opcion_id
             WHERE r.resultado_id = :r ORDER BY r.id");
        $st->execute([':r' => $resultado['id']]);
        $detalle = $st->fetchAll();
    }
}

/* Preparar el examen (se guarda en sesión para que recargar no cambie las preguntas) */
$preguntas = []; $restante = 0; $motivoBloqueo = '';
if (!$resultado) {
    if (!$eval) {
        $motivoBloqueo = 'El área "' . $post['area'] . '" aún no tiene una evaluación configurada.';
    } elseif ($post['estado'] !== 'En Proceso') {
        $motivoBloqueo = 'El candidato está en estado "' . $post['estado'] . '" y no puede rendir la evaluación.';
    } else {
        $clave = 'examen_' . $postId;
        $ex = $_SESSION[$clave] ?? null;
        if (!$ex || (int)$ex['eval'] !== (int)$eval['id']) {
            $st = $pdo->prepare("SELECT id FROM preguntas WHERE evaluacion_id = :e ORDER BY RAND() LIMIT " . (int)$eval['total_preguntas']);
            $st->execute([':e' => $eval['id']]);
            $ids = array_map('intval', array_column($st->fetchAll(), 'id'));
            $orden = [];
            $stO = $pdo->prepare("SELECT id FROM opciones WHERE pregunta_id = :p");
            foreach ($ids as $pid) {
                $stO->execute([':p' => $pid]);
                $ops = array_map('intval', array_column($stO->fetchAll(), 'id'));
                shuffle($ops);
                $orden[$pid] = $ops;
            }
            $ex = ['eval' => (int)$eval['id'], 'preguntas' => $ids, 'opciones' => $orden, 'inicio' => time()];
            $_SESSION[$clave] = $ex;
        }
        if (!$ex['preguntas']) {
            $motivoBloqueo = 'El banco de preguntas del área está vacío.';
            unset($_SESSION[$clave]);
        } else {
            $stQ = $pdo->prepare("SELECT enunciado FROM preguntas WHERE id = :id");
            $stT = $pdo->prepare("SELECT texto FROM opciones WHERE id = :id");
            foreach ($ex['preguntas'] as $pid) {
                $stQ->execute([':id' => $pid]);
                $ops = [];
                foreach ($ex['opciones'][$pid] as $oid) { $stT->execute([':id' => $oid]); $ops[] = ['id' => $oid, 'texto' => $stT->fetch()['texto']]; }
                $preguntas[] = ['id' => $pid, 'enunciado' => $stQ->fetch()['enunciado'], 'opciones' => $ops];
            }
            $restante = max(0, (int)$eval['tiempo_limite'] * 60 - (time() - (int)$ex['inicio']));
        }
    }
}

require_once __DIR__ . '/includes/layout_top.php';
?>

<div class="panel-encabezado">
  <div>
    <h2>Evaluación: <?= htmlspecialchars($eval['nombre'] ?? $post['area']) ?></h2>
    <p><?= htmlspecialchars($post['nombres']) ?> · DNI <?= htmlspecialchars($post['dni']) ?> · <?= htmlspecialchars($post['puesto']) ?></p>
  </div>
  <div class="acciones-panel"><a class="btn-linea" href="candidatos.php">Volver a candidatos</a></div>
</div>

<?php if ($flashOk): ?><div class="aviso aviso-ok"><?= htmlspecialchars($flashOk) ?></div><?php endif; ?>
<?php if ($flashError): ?><div class="aviso aviso-error"><?= htmlspecialchars($flashError) ?></div><?php endif; ?>

<?php if ($resultado): ?>
  <!-- ===== Resultado ===== -->
  <section class="tarjeta ev-resultado <?= (int)$resultado['aprobado'] ? 'ok' : 'mal' ?>">
    <div class="ev-res-num"><?= (int)$resultado['puntaje'] ?>%</div>
    <div>
      <h3><?= (int)$resultado['aprobado'] ? 'Evaluación aprobada' : 'Evaluación no aprobada' ?></h3>
      <p>Nota mínima: <?= (int)$eval['nota_minima'] ?>% · Aciertos: <?= count(array_filter($detalle, fn($d) => (int)$d['acierto'] === 1)) ?> de <?= count($detalle) ?> · Rendida el <?= date('d/m/Y H:i', strtotime($resultado['fecha'])) ?></p>
    </div>
  </section>
  <section class="tarjeta">
    <h3 class="ev-h">Detalle de respuestas</h3>
    <ol class="ev-lista">
      <?php foreach ($detalle as $d): ?>
        <li class="<?= (int)$d['acierto'] ? 'acierto' : 'error' ?>">
          <div class="ev-preg"><span><?= htmlspecialchars($d['enunciado']) ?></span></div>
          <div class="ev-det">
            Respondió: <strong><?= htmlspecialchars($d['elegida'] ?? 'Sin responder') ?></strong>
            <?php if (!(int)$d['acierto']): ?> · Correcta: <strong><?= htmlspecialchars($d['correcta'] ?? '—') ?></strong><?php endif; ?>
          </div>
        </li>
      <?php endforeach; ?>
    </ol>
  </section>

<?php elseif ($motivoBloqueo): ?>
  <section class="tarjeta"><p class="ev-nota"><?= htmlspecialchars($motivoBloqueo) ?></p>
    <?php if ($esSupervisor && !$eval): ?><a class="btn btn-azul" href="banco_preguntas.php?area=<?= (int)$post['area_id'] ?>">Configurar evaluación del área</a><?php endif; ?>
  </section>

<?php else: ?>
  <!-- ===== Examen ===== -->
  <div class="ev-barra">
    <span><?= count($preguntas) ?> preguntas · Nota mínima <?= (int)$eval['nota_minima'] ?>%</span>
    <span class="ev-reloj" id="reloj">--:--</span>
  </div>
  <form action="calificar_evaluacion.php" method="POST" id="formExamen">
    <input type="hidden" name="postulacion_id" value="<?= $postId ?>">
    <?php foreach ($preguntas as $n => $q): ?>
      <section class="tarjeta ev-q">
        <p class="ev-q-tit"><span><?= $n + 1 ?>.</span> <?= htmlspecialchars($q['enunciado']) ?></p>
        <?php foreach ($q['opciones'] as $op): ?>
          <label class="ev-q-opc">
            <input type="radio" name="respuestas[<?= $q['id'] ?>]" value="<?= $op['id'] ?>">
            <span><?= htmlspecialchars($op['texto']) ?></span>
          </label>
        <?php endforeach; ?>
      </section>
    <?php endforeach; ?>
    <div class="ev-enviar"><button class="btn btn-azul" type="submit">Finalizar y calificar</button></div>
  </form>

  <script>
  (function () {
    var restante = <?= (int)$restante ?>, form = document.getElementById('formExamen'),
        reloj = document.getElementById('reloj'), enviado = false;
    function enviar() { if (!enviado) { enviado = true; form.submit(); } }
    function pintar() {
      var m = Math.floor(restante / 60), s = restante % 60;
      reloj.textContent = (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
      reloj.classList.toggle('poco', restante <= 60);
    }
    pintar();
    if (restante <= 0) { enviar(); return; }
    setInterval(function () { restante--; pintar(); if (restante <= 0) enviar(); }, 1000);
    form.addEventListener('submit', function (e) {
      if (enviado) return;
      var total = <?= count($preguntas) ?>, resp = form.querySelectorAll('input[type=radio]:checked').length;
      if (resp < total && !confirm('Hay ' + (total - resp) + ' pregunta(s) sin responder. ¿Finalizar de todos modos?')) { e.preventDefault(); return; }
      enviado = true;
    });
  })();
  </script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/layout_bottom.php'; ?>
