<?php
/* ============================================================
   SSR - A365 | Controlador de la gestión del proceso del candidato
   Acciones: verificar (datos/documentos), agendar_entrevista,
   entrevista_estado, agendar_capacitacion, asistencia, avanzar, descartar.
   Todas respetan el flujo (includes/flujo.php) y el alcance por área.
   ============================================================ */
require_once __DIR__ . '/includes/guardia.php';
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/includes/alcance.php';
require_once __DIR__ . '/includes/flujo.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: candidatos.php'); exit; }

$postId = (int)($_POST['post'] ?? 0);
$accion = $_POST['accion'] ?? '';
$uid    = $_SESSION['usuario_id'] ?? null;

function volver(int $postId, bool $ok, string $msg): void {
    $_SESSION[$ok ? 'flash_ok' : 'flash_error'] = $msg;
    header('Location: ' . ($postId ? 'proceso.php?post=' . $postId : 'candidatos.php')); exit;
}
function fechaValida(string $f): bool { $d = DateTime::createFromFormat('Y-m-d', $f); return $d && $d->format('Y-m-d') === $f; }
function horaValida(string $h): bool { return (bool)preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $h); }

try {
    $pdo = obtenerConexion();
    $p = $postId ? flujoPostulacion($pdo, $postId) : null;
    if (!$p) volver(0, false, 'La postulación no existe.');
    if (!puedeGestionarPostulacion($pdo, $postId)) volver(0, false, 'Solo puedes gestionar a los candidatos de tu área.');
    if ($p['estado'] === 'Descartado') volver($postId, false, 'El candidato está descartado: ya no se puede modificar su proceso.');
    if ($p['estado'] === 'Seleccionado' && $accion !== 'avanzar') volver($postId, false, 'El proceso de este candidato ya finalizó.');
    $orden = (int)$p['orden'];
    $hoy = date('Y-m-d');

    switch ($accion) {

    case 'verificar':
        if ($orden !== 3) volver($postId, false, 'La verificación de datos y documentos se hace en la etapa de validación.');
        $pdo->prepare("UPDATE postulaciones SET val_datos = :d, val_docs = :c WHERE id = :id")
            ->execute([':d' => isset($_POST['val_datos']) ? 1 : 0, ':c' => isset($_POST['val_docs']) ? 1 : 0, ':id' => $postId]);
        volver($postId, true, 'Verificación guardada.');

    case 'avanzar':
        $dest = flujoEtapa($pdo, $orden + 1);
        if (!$dest) volver($postId, false, 'El proceso ya finalizó.');
        $err = flujoValidarCambio($pdo, $p, (int)$dest['orden']);
        if ($err) volver($postId, false, $err);
        $obs = trim($_POST['observacion'] ?? '');
        flujoMover($pdo, $postId, (int)$p['etapa_id'], $dest, $obs ?: null, $uid);
        volver($postId, true, $dest['orden'] == flujoUltimoOrden($pdo)
            ? 'Proceso finalizado: el candidato queda como Seleccionado y lo verá en su seguimiento.'
            : 'El candidato pasó a «' . $dest['nombre'] . '».');

    case 'descartar':
        $mot = (int)($_POST['motivo_id'] ?? 0);
        if ($mot <= 0) volver($postId, false, 'Debes seleccionar un motivo de descarte.');
        $q = $pdo->prepare("SELECT id FROM motivos_descarte WHERE id = :m"); $q->execute([':m' => $mot]);
        if (!$q->fetch()) volver($postId, false, 'El motivo de descarte no existe.');
        flujoDescartar($pdo, $postId, (int)$p['etapa_id'], $mot, trim($_POST['observacion'] ?? '') ?: null, $uid);
        volver($postId, true, 'Candidato descartado. El motivo quedó registrado y aparece en Reportes.');

    case 'agendar_entrevista':
        if ($orden !== 4) volver($postId, false, 'La entrevista se agenda en la etapa «Entrevista presencial».');
        $fecha = $_POST['fecha'] ?? ''; $hora = $_POST['hora'] ?? ''; $lugar = trim($_POST['lugar'] ?? '');
        $entId = (int)($_POST['entrevistador_id'] ?? 0) ?: (int)$uid;
        if (!fechaValida($fecha)) volver($postId, false, 'Selecciona la fecha de la entrevista.');
        if ($fecha < $hoy) volver($postId, false, 'La fecha de la entrevista no puede estar en el pasado.');
        if (!horaValida($hora)) volver($postId, false, 'Selecciona la hora de la entrevista.');
        if ($lugar === '' || mb_strlen($lugar) > 80) volver($postId, false, 'Indica el lugar de la entrevista presencial (máx. 80 caracteres).');
        $u = $pdo->prepare("SELECT nombre FROM usuarios WHERE id = :i AND estado = 1"); $u->execute([':i' => $entId]);
        $nom = $u->fetchColumn();
        if (!$nom) volver($postId, false, 'El entrevistador no existe o está inactivo.');
        $act = $pdo->prepare("SELECT COUNT(*) FROM entrevistas WHERE postulacion_id = :p AND estado IN ('Programada','En Curso')");
        $act->execute([':p' => $postId]);
        if ((int)$act->fetchColumn() > 0) volver($postId, false, 'Ya hay una entrevista programada para este candidato. Complétala o cancélala primero.');
        $pdo->prepare("INSERT INTO entrevistas (postulacion_id, entrevistador_id, entrevistador, tipo, fecha, hora, sala, estado, observaciones)
                       VALUES (:p,:u,:n,'Entrevista RH',:f,:h,:s,'Programada','Entrevista presencial')")
            ->execute([':p' => $postId, ':u' => $entId, ':n' => $nom, ':f' => $fecha, ':h' => strlen($hora) === 5 ? $hora . ':00' : $hora, ':s' => $lugar]);
        volver($postId, true, 'Entrevista presencial agendada para el ' . date('d/m/Y', strtotime($fecha)) . '. El candidato la verá en su seguimiento.');

    case 'entrevista_estado':
        if ($orden !== 4) volver($postId, false, 'Solo se gestiona la entrevista en la etapa «Entrevista presencial».');
        $eid = (int)($_POST['entrevista_id'] ?? 0); $est = $_POST['estado'] ?? '';
        if (!in_array($est, ['Completada', 'Cancelada'], true)) volver($postId, false, 'Estado de entrevista no válido.');
        $q = $pdo->prepare("SELECT id, fecha FROM entrevistas WHERE id = :i AND postulacion_id = :p"); $q->execute([':i' => $eid, ':p' => $postId]);
        $en = $q->fetch();
        if (!$en) volver($postId, false, 'La entrevista no existe.');
        if ($est === 'Completada' && $en['fecha'] > $hoy) volver($postId, false, 'No se puede verificar una entrevista que aún no se realiza.');
        $pdo->prepare("UPDATE entrevistas SET estado = :e WHERE id = :i")->execute([':e' => $est, ':i' => $eid]);
        volver($postId, true, $est === 'Completada' ? 'Entrevista verificada correctamente. Ya puedes pasar al candidato a capacitaciones.' : 'Entrevista cancelada. Puedes agendar otra.');

    case 'agendar_capacitacion':
        if ($orden !== 5) volver($postId, false, 'Las capacitaciones se agendan en la etapa «Capacitaciones».');
        $caps = flujoCapacitaciones($pdo, $postId);
        if (count($caps) >= FLUJO_MAX_CAPACITACIONES) volver($postId, false, 'Se alcanzó el máximo de ' . FLUJO_MAX_CAPACITACIONES . ' capacitaciones.');
        foreach ($caps as $c) if ($c['asistencia'] !== 'Asistió') volver($postId, false, 'Confirma la asistencia de la capacitación ' . $c['numero'] . ' antes de agendar la siguiente.');
        $tema = trim($_POST['tema'] ?? ''); $fecha = $_POST['fecha'] ?? ''; $hora = $_POST['hora'] ?? ''; $lugar = trim($_POST['lugar'] ?? '');
        if ($tema === '' || mb_strlen($tema) > 120) volver($postId, false, 'Indica el tema de la capacitación (máx. 120 caracteres).');
        if (!fechaValida($fecha)) volver($postId, false, 'Selecciona la fecha de la capacitación.');
        if ($fecha < $hoy) volver($postId, false, 'La fecha de la capacitación no puede estar en el pasado.');
        if (!horaValida($hora)) volver($postId, false, 'Selecciona la hora de la capacitación.');
        if (mb_strlen($lugar) > 100) volver($postId, false, 'El lugar supera los 100 caracteres.');
        $num = count($caps) + 1;
        $pdo->prepare("INSERT INTO capacitaciones (postulacion_id, numero, tema, fecha, hora, lugar, usuario_id) VALUES (:p,:n,:t,:f,:h,:l,:u)")
            ->execute([':p' => $postId, ':n' => $num, ':t' => $tema, ':f' => $fecha, ':h' => strlen($hora) === 5 ? $hora . ':00' : $hora, ':l' => $lugar ?: null, ':u' => $uid]);
        volver($postId, true, "Capacitación $num agendada para el " . date('d/m/Y', strtotime($fecha)) . '.');

    case 'asistencia':
        if ($orden !== 5) volver($postId, false, 'La asistencia se registra en la etapa «Capacitaciones».');
        $cid = (int)($_POST['capacitacion_id'] ?? 0); $val = $_POST['asistencia'] ?? '';
        if (!in_array($val, ['Asistió', 'No asistió'], true)) volver($postId, false, 'Valor de asistencia no válido.');
        $q = $pdo->prepare("SELECT * FROM capacitaciones WHERE id = :i AND postulacion_id = :p"); $q->execute([':i' => $cid, ':p' => $postId]);
        $cap = $q->fetch();
        if (!$cap) volver($postId, false, 'La capacitación no existe.');
        if ($cap['asistencia'] !== 'Pendiente') volver($postId, false, 'La asistencia de esta capacitación ya fue registrada.');
        if ($cap['fecha'] > $hoy) volver($postId, false, 'No se puede registrar la asistencia de una capacitación futura.');
        $pdo->prepare("UPDATE capacitaciones SET asistencia = :a WHERE id = :i")->execute([':a' => $val, ':i' => $cid]);
        if ($val === 'No asistió') {
            $mot = flujoMotivoId($pdo, 'Inasistencia a capacitaci%');
            if (!$mot) volver($postId, false, 'Falta el motivo «Inasistencia a capacitación»: ejecuta sql/flujo_proceso.sql.');
            flujoDescartar($pdo, $postId, (int)$p['etapa_id'], $mot, 'Capacitación ' . $cap['numero'] . ': ' . $cap['tema'], $uid);
            volver($postId, true, 'Inasistencia registrada: el candidato quedó descartado y el motivo aparece en Reportes.');
        }
        volver($postId, true, 'Asistencia confirmada.');

    default:
        volver($postId, false, 'Acción no válida.');
    }
} catch (PDOException $e) {
    volver($postId ?? 0, false, 'No se pudo guardar. Intenta nuevamente.');
}
