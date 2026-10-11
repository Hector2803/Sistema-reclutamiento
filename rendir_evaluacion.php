<?php
/* ============================================================
   SSR - A365 | Resultado de la evaluación del candidato (HU-08 / RF-09)
   SOLO LECTURA: el postulante rinde el test en el portal público
   (portal/evaluacion.php) con su código de seguimiento y DNI.
   Aquí el personal únicamente consulta el resultado y el detalle.
   El reclutador solo ve candidatos de su área.
   ============================================================ */
$tituloPagina = 'Evaluación del candidato';
$activo = 'candidatos';
$estilosExtra = ['assets/css/candidatos.css', 'assets/css/usuarios.css', 'assets/css/evaluaciones.css'];

require_once __DIR__ . '/includes/guardia.php';
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/includes/alcance.php';
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

/* Alcance: el reclutador solo consulta candidatos de su área */
$esSupervisor = in_array(($_SESSION['usuario_rol'] ?? ''), ['Supervisor','Administrador'], true);
if (!puedeGestionarPostulacion($pdo, $postId)) {
    $_SESSION['flash_error'] = 'Solo puedes consultar candidatos de tu área.';
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

require_once __DIR__ . '/includes/layout_top.php';
?>

<div class="panel-encabezado">
  <div>
    <h2>Resultado de la evaluación: <?= htmlspecialchars($eval['nombre'] ?? $post['area']) ?></h2>
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

<?php else: ?>
  <section class="tarjeta">
    <p class="ev-nota">
      <?php if (!$eval): ?>
        El área «<?= htmlspecialchars($post['area']) ?>» aún no tiene una evaluación configurada.
      <?php elseif ($post['estado'] !== 'En Proceso'): ?>
        El candidato está en estado «<?= htmlspecialchars($post['estado']) ?>» y no rindió la evaluación.
      <?php else: ?>
        <strong>Evaluación pendiente.</strong> El postulante la rinde por su cuenta desde el portal de empleo,
        ingresando su código de seguimiento y su DNI. El resultado aparecerá aquí automáticamente cuando la envíe.
      <?php endif; ?>
    </p>
    <?php if ($esSupervisor && !$eval): ?><a class="btn btn-azul" href="banco_preguntas.php?area=<?= (int)$post['area_id'] ?>">Configurar evaluación del área</a><?php endif; ?>
  </section>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/layout_bottom.php'; ?>
