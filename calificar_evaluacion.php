<?php
/* ============================================================
   SSR - A365 | Calificar evaluación (HU-08 / RF-09)
   - Califica en el servidor con las preguntas guardadas en sesión
   - Registra resultados_eval y respuestas
   - Aprobado  -> etapa "Evaluación (test)"
   - Desaprobado -> estado Descartado con motivo del 70 %
   - Todo queda en historial_etapas
   ============================================================ */
require_once __DIR__ . '/includes/guardia.php';
require_once __DIR__ . '/config/conexion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: candidatos.php'); exit; }

$postId = (int)($_POST['postulacion_id'] ?? 0);
$volver = 'rendir_evaluacion.php?post=' . $postId;
$clave  = 'examen_' . $postId;
$ex     = $_SESSION[$clave] ?? null;
$usuarioId = $_SESSION['usuario_id'] ?? null;

if (!$ex || empty($ex['preguntas'])) {
    $_SESSION['flash_error'] = 'La sesión de la evaluación expiró. Vuelve a iniciarla.';
    header('Location: ' . $volver); exit;
}

try {
    $pdo = obtenerConexion();

    /* Postulación y alcance */
    $st = $pdo->prepare("SELECT p.id, p.estado, p.etapa_id, p.reclutador_id, e.orden AS orden_actual
                         FROM postulaciones p INNER JOIN etapas e ON e.id = p.etapa_id WHERE p.id = :id");
    $st->execute([':id' => $postId]);
    $post = $st->fetch();
    $esSupervisor = in_array(($_SESSION['usuario_rol'] ?? ''), ['Supervisor','Administrador'], true);
    if (!$post || (!$esSupervisor && (int)$post['reclutador_id'] !== (int)$usuarioId)) {
        $_SESSION['flash_error'] = 'No tienes acceso a esta postulación.';
        header('Location: candidatos.php'); exit;
    }
    if ($post['estado'] !== 'En Proceso') {
        unset($_SESSION[$clave]);
        $_SESSION['flash_error'] = 'El candidato ya no está en proceso.';
        header('Location: ' . $volver); exit;
    }

    /* Evaluación */
    $st = $pdo->prepare("SELECT * FROM evaluaciones WHERE id = :e");
    $st->execute([':e' => $ex['eval']]);
    $eval = $st->fetch();

    /* Evitar doble calificación */
    $st = $pdo->prepare("SELECT id FROM resultados_eval WHERE postulacion_id = :p AND evaluacion_id = :e LIMIT 1");
    $st->execute([':p' => $postId, ':e' => $eval['id']]);
    if ($st->fetch()) {
        unset($_SESSION[$clave]);
        $_SESSION['flash_error'] = 'Esta evaluación ya fue calificada.';
        header('Location: ' . $volver); exit;
    }

    /* Opciones válidas y correctas de las preguntas servidas */
    $ids = array_map('intval', $ex['preguntas']);
    $marcas = implode(',', array_fill(0, count($ids), '?'));
    $st = $pdo->prepare("SELECT id, pregunta_id, correcta FROM opciones WHERE pregunta_id IN ($marcas)");
    $st->execute($ids);
    $opcionesDe = []; $correctaDe = [];
    foreach ($st->fetchAll() as $o) {
        $opcionesDe[(int)$o['pregunta_id']][] = (int)$o['id'];
        if ((int)$o['correcta'] === 1) $correctaDe[(int)$o['pregunta_id']] = (int)$o['id'];
    }

    /* Calificar */
    $enviadas = (array)($_POST['respuestas'] ?? []);
    $aciertos = 0; $filas = [];
    foreach ($ids as $pid) {
        $elegida = isset($enviadas[$pid]) ? (int)$enviadas[$pid] : null;
        if ($elegida !== null && !in_array($elegida, $opcionesDe[$pid] ?? [], true)) $elegida = null; // manipulada
        if ($elegida !== null && $elegida === ($correctaDe[$pid] ?? -1)) $aciertos++;
        $filas[] = [$pid, $elegida];
    }
    $puntaje  = (int)round($aciertos * 100 / count($ids));
    $aprobado = $puntaje >= (int)$eval['nota_minima'] ? 1 : 0;
    $fueraTiempo = (time() - (int)$ex['inicio']) > ((int)$eval['tiempo_limite'] * 60 + 60);

    /* Etapa "Evaluación (test)" y motivo del puntaje mínimo */
    $etEval = $pdo->query("SELECT id, orden FROM etapas WHERE nombre LIKE 'Evaluaci%' ORDER BY orden LIMIT 1")->fetch();
    $etapaDestino = $etEval ? (int)$etEval['id'] : (int)$post['etapa_id'];
    // No retroceder si el candidato ya estaba más adelante en el embudo
    if ($etEval && (int)$post['orden_actual'] > (int)$etEval['orden']) $etapaDestino = (int)$post['etapa_id'];

    $obs = ($aprobado ? 'Evaluación aprobada' : 'Evaluación no aprobada') . " con $puntaje% (mínimo {$eval['nota_minima']}%)"
         . ($fueraTiempo ? ' · entregada fuera de tiempo' : '');

    $pdo->beginTransaction();

    $pdo->prepare("INSERT INTO resultados_eval (postulacion_id, evaluacion_id, puntaje, aprobado) VALUES (:p, :e, :pt, :ap)")
        ->execute([':p'=>$postId, ':e'=>$eval['id'], ':pt'=>$puntaje, ':ap'=>$aprobado]);
    $resId = (int)$pdo->lastInsertId();

    $insR = $pdo->prepare("INSERT INTO respuestas (resultado_id, pregunta_id, opcion_id) VALUES (:r, :q, :o)");
    foreach ($filas as [$pid, $oid]) $insR->execute([':r'=>$resId, ':q'=>$pid, ':o'=>$oid]);

    if ($aprobado) {
        $pdo->prepare("UPDATE postulaciones SET etapa_id = :et WHERE id = :id")
            ->execute([':et'=>$etapaDestino, ':id'=>$postId]);
        $pdo->prepare("INSERT INTO historial_etapas (postulacion_id, etapa_origen, etapa_destino, observacion, usuario_id)
                       VALUES (:p, :o, :d, :obs, :u)")
            ->execute([':p'=>$postId, ':o'=>$post['etapa_id'], ':d'=>$etapaDestino, ':obs'=>$obs, ':u'=>$usuarioId]);
    } else {
        $mot = $pdo->query("SELECT id FROM motivos_descarte WHERE descripcion LIKE 'No alcanz%' LIMIT 1")->fetch();
        $motivoId = $mot ? (int)$mot['id'] : null;
        $pdo->prepare("UPDATE postulaciones SET etapa_id = :et, estado = 'Descartado' WHERE id = :id")
            ->execute([':et'=>$etapaDestino, ':id'=>$postId]);
        $pdo->prepare("INSERT INTO historial_etapas (postulacion_id, etapa_origen, etapa_destino, motivo_id, observacion, usuario_id)
                       VALUES (:p, :o, :d, :m, :obs, :u)")
            ->execute([':p'=>$postId, ':o'=>$post['etapa_id'], ':d'=>$etapaDestino, ':m'=>$motivoId, ':obs'=>$obs, ':u'=>$usuarioId]);
    }

    $pdo->commit();
    unset($_SESSION[$clave]);
    $_SESSION[$aprobado ? 'flash_ok' : 'flash_error'] = $obs . ($aprobado ? '.' : '. El candidato fue descartado automáticamente.');
    header('Location: ' . $volver); exit;

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    $_SESSION['flash_error'] = 'No se pudo calificar la evaluación. Intenta nuevamente.';
    header('Location: ' . $volver); exit;
}
