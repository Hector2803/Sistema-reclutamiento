<?php
/* ============================================================
   SSR - A365 | Descarga de respaldos y paquetes del historial
   Solo Administrador. Solo se entregan archivos que figuran en
   el historial (historial_backups / versiones_sistema) y que
   están dentro de la carpeta backups/ (sin rutas relativas).
   ============================================================ */
require_once __DIR__ . '/includes/guardia.php';
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/config/sistema.php';

if (($_SESSION['usuario_rol'] ?? '') !== 'Administrador') { http_response_code(403); exit('Acceso denegado'); }

$tipo    = $_GET['tipo'] ?? '';
$archivo = $_GET['archivo'] ?? '';
$noEncontrado = function () { $_SESSION['flash_error'] = 'El archivo ya no está disponible en el servidor.'; header('Location: ' . (($_GET['tipo'] ?? '') === 'paquete' ? 'mantenimiento.php' : 'backup.php')); exit; };

if ($archivo === '' || $archivo !== basename($archivo) || !in_array($tipo, ['backup', 'paquete'], true)) { http_response_code(400); exit('Solicitud inválida'); }

$pdo = obtenerConexion();
$st = $pdo->prepare($tipo === 'backup'
    ? "SELECT 1 FROM historial_backups WHERE archivo = :a LIMIT 1"
    : "SELECT 1 FROM versiones_sistema WHERE archivo = :a LIMIT 1");
$st->execute([':a' => $archivo]);
if (!$st->fetchColumn()) { http_response_code(404); exit('Archivo no registrado'); }

$ruta = DIR_BACKUPS . DIRECTORY_SEPARATOR . $archivo;
if (!is_file($ruta)) { $noEncontrado(); }

header('Content-Description: File Transfer');
header('Content-Type: ' . ($tipo === 'backup' ? 'application/sql' : 'application/zip'));
header('Content-Disposition: attachment; filename="' . str_replace('"', '', $archivo) . '"');
header('Content-Length: ' . filesize($ruta));
header('Cache-Control: no-store');
readfile($ruta);
exit;
