<?php
/* ============================================================
   SSR - A365 | Actualizar etapa del candidato (HU-06 / RF-06)
   - Avanzar/cambiar de etapa respetando el flujo (includes/flujo.php), o descartar (con motivo obligatorio)
   - Registra el historial con etapa origen/destino, usuario y fecha
   ============================================================ */
require_once __DIR__ . '/includes/guardia.php';
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/includes/alcance.php';
require_once __DIR__ . '/includes/flujo.php';

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

    // Alcance: el reclutador solo actualiza a los candidatos de su área
    if (!puedeGestionarPostulacion($pdo, $postId)) {
        salirKanban(false, 'Solo puedes actualizar a los candidatos de tu área.');
    }

    $pf = flujoPostulacion($pdo, $postId);

    if ($accion === 'descartar') {
        $motivoId = (int)($_POST['motivo_id'] ?? 0);
        if ($motivoId === 0) {
            salirKanban(false, 'Debes seleccionar un motivo de descarte.');
        }
        if ($pf['estado'] === 'Descartado') salirKanban(false, 'El candidato ya está descartado.');
        flujoDescartar($pdo, $postId, $etapaOrigen, $motivoId, $obs, $usuarioId);
        salirKanban(true, 'Candidato descartado y registrado en el historial.');
    }

    // etapa_destino: id usado por el Kanban. etapa_id: id usado por el modal de Candidatos.
    $etapaDestino = (int)($_POST['etapa_destino'] ?? $_POST['etapa_id'] ?? 0);
    if ($etapaDestino === 0) {
        salirKanban(false, 'Selecciona la nueva etapa.');
    }

    $etInfo = $pdo->prepare("SELECT id, nombre, color, orden FROM etapas WHERE id = :id LIMIT 1");
    $etInfo->execute([':id'=>$etapaDestino]);
    $etFila = $etInfo->fetch();
    if (!$etFila) {
        salirKanban(false, 'La etapa destino no existe.');
    }

    // Se respeta el flujo: una etapa a la vez y solo si se cumplen las condiciones de la etapa actual
    $err = flujoValidarCambio($pdo, $pf, (int)$etFila['orden']);
    if ($err) salirKanban(false, $err);

    flujoMover($pdo, $postId, $etapaOrigen, $etFila, $obs, $usuarioId);

    salirKanban(true, 'Etapa actualizada correctamente.', $etFila['nombre'], $etFila['color']);

} catch (PDOException $e) {
    salirKanban(false, 'No se pudo actualizar la etapa. Intenta nuevamente.');
}
