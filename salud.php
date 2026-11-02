<?php
/* ============================================================
   SSR - A365 | Comprobación de estado (para UptimeRobot u otro monitor)
   Sin sesión y sin datos sensibles: solo confirma que PHP y la base de datos responden.
     200 + "A365_OK"  -> todo bien
     503 + "A365_ERROR" -> la base de datos no responde
   ============================================================ */
header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-store');
try {
    require_once __DIR__ . '/config/conexion.php';
    $pdo = obtenerConexion();
    $pdo->query('SELECT 1')->fetchColumn();
    echo 'A365_OK';
} catch (Throwable $e) {
    http_response_code(503);
    echo 'A365_ERROR';
}
