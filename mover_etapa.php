<?php
/* ============================================================
   SSR - A365 | Actualizar etapa del candidato (HU-06 / RF-06)
   - Avanzar/cambiar de etapa, o descartar (con motivo obligatorio)
   - Registra el historial con etapa origen/destino, usuario y fecha
   ============================================================ */
require_once __DIR__ . '/includes/guardia.php';
require_once __DIR__ . '/config/conexion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: candidatos.php'); exit; }

// El tablero Kanban (kanban.php) envía los movimientos por AJAX (ajax=1) y
// espera una respuesta JSON en vez de una redirección.
$esAjax  = ($_POST['ajax'] ?? '') === '1';
function salirKanban(bool $ok, string $mensaje, ?string $etapaNombre = null, ?string $etapaColor = null): void {
    global $esAjax;
    if ($esAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => $ok, 'mensaje' => $mensaje, 'etapa_nombre' => $etapaNombre, 'etapa_color' => $etapaColor]);
        exit;
    }
    $_SESSION[$ok ? 'flash_ok' : 'flash_error'] = $mensaje;
    header('Location: candidatos.php'); exit;
}

$postId  = (int)($_POST['postulacion_id'] ?? 0);
$accion  = $_POST['accion'] ?? 'avanzar';
$obs     = trim($_POST['observacion'] ?? '');
$usuarioId = $_SESSION['usuario_id'] ?? null;

if ($postId === 0) {
    salirKanban(false, 'Postulación no válida.');
}

try {
    $pdo = obtenerConexion();

    $q = $pdo->prepare("SELECT etapa_id, estado, reclutador_id FROM postulaciones WHERE id = :id LIMIT 1");
    $q->execute([':id'=>$postId]);
    $post = $q->fetch();
    if (!$post) {
        salirKanban(false, 'La postulación no existe.');
    }
    $etapaOrigen = (int)$post['etapa_id'];

    // Alcance: el reclutador solo actualiza a sus propios candidatos
    $esSupervisor = in_array(($_SESSION['usuario_rol'] ?? ''), ['Supervisor','Administrador'], true);
    if (!$esSupervisor && (int)$post['reclutador_id'] !== (int)$usuarioId) {
        salirKanban(false, 'Solo puedes actualizar a los candidatos asignados a ti.');
    }

    if ($accion === 'descartar') {
        $motivoId = (int)($_POST['motivo_id'] ?? 0);
        if ($motivoId === 0) {
            salirKanban(false, 'Debes seleccionar un motivo de descarte.');
        }
        $pdo->prepare("UPDATE postulaciones SET estado = 'Descartado' WHERE id = :id")
            ->execute([':id'=>$postId]);
        $pdo->prepare(
            "INSERT INTO historial_etapas (postulacion_id, etapa_origen, etapa_destino, motivo_id, observacion, usuario_id)
             VALUES (:p, :o, :d, :m, :obs, :u)")
            ->execute([':p'=>$postId, ':o'=>$etapaOrigen, ':d'=>$etapaOrigen, ':m'=>$motivoId, ':obs'=>($obs?:null), ':u'=>$usuarioId]);
        salirKanban(true, 'Candidato descartado y registrado en el historial.');
    }

    // etapa_destino: nombre usado por el Kanban. etapa_id: nombre usado por el modal de Candidatos.
    $etapaDestino = (int)($_POST['etapa_destino'] ?? $_POST['etapa_id'] ?? 0);
    if ($etapaDestino === 0) {
        salirKanban(false, 'Selecciona la nueva etapa.');
    }

    $etInfo = $pdo->prepare("SELECT nombre, color FROM etapas WHERE id = :id LIMIT 1");
    $etInfo->execute([':id'=>$etapaDestino]);
    $etFila = $etInfo->fetch();
    if (!$etFila) {
        salirKanban(false, 'La etapa destino no existe.');
    }

    $ult = $pdo->query("SELECT id FROM etapas ORDER BY orden DESC LIMIT 1")->fetch();
    $nuevoEstado = ((int)$ult['id'] === $etapaDestino) ? 'Seleccionado' : 'En Proceso';

    $pdo->prepare("UPDATE postulaciones SET etapa_id = :et, estado = :es WHERE id = :id")
        ->execute([':et'=>$etapaDestino, ':es'=>$nuevoEstado, ':id'=>$postId]);
    $pdo->prepare(
        "INSERT INTO historial_etapas (postulacion_id, etapa_origen, etapa_destino, observacion, usuario_id)
         VALUES (:p, :o, :d, :obs, :u)")
        ->execute([':p'=>$postId, ':o'=>$etapaOrigen, ':d'=>$etapaDestino, ':obs'=>($obs?:null), ':u'=>$usuarioId]);

    salirKanban(true, 'Etapa actualizada correctamente.', $etFila['nombre'], $etFila['color']);

} catch (PDOException $e) {
    salirKanban(false, 'No se pudo actualizar la etapa. Intenta nuevamente.');
}
