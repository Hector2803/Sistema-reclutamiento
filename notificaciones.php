<?php
/* Servicio JSON de la campanita: GET = lista; POST accion=leer = marcar todas como vistas. */
require_once __DIR__ . '/includes/guardia.php';
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/includes/notificaciones.php';
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
try {
    $pdo = obtenerConexion();
    $uid = (int)($_SESSION['usuario_id'] ?? 0);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (($_POST['accion'] ?? '') === 'leer') notifMarcarVistas($pdo, $uid);
    }
    echo json_encode(notifObtener($pdo, $uid), JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['items' => [], 'no_leidas' => 0, 'error' => 'No se pudieron cargar las notificaciones.']);
}
