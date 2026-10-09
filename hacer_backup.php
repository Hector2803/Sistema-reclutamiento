<?php
/* ============================================================
   SSR - A365 | Generar backup de la base de datos (RF-14)
   Usa mysqldump de XAMPP. Registra el respaldo y lo descarga.
   ============================================================ */
require_once __DIR__ . '/includes/guardia.php';
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/config/sistema.php';

if (($_SESSION['usuario_rol'] ?? '') !== 'Administrador') { header('Location: dashboard.php'); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: mantenimiento.php'); exit; }

$fecha  = date('Y-m-d_His');
$archivo = 'backup_' . DB_NOMBRE . '_' . $fecha . '.sql';
$ruta   = DIR_BACKUPS . DIRECTORY_SEPARATOR . $archivo;

$dump = RUTA_MYSQL_BIN . 'mysqldump.exe';
$pass = DB_CLAVE !== '' ? ' --password=' . escapeshellarg(DB_CLAVE) : '';
$cmd  = '"' . $dump . '" --user=' . escapeshellarg(DB_USUARIO) . $pass
      . ' --host=' . escapeshellarg(DB_HOST)
      . ' ' . escapeshellarg(DB_NOMBRE)
      . ' --result-file=' . escapeshellarg($ruta) . ' 2>&1';

$salida = []; $ret = 0;
@exec($cmd, $salida, $ret);

if ($ret !== 0 || !file_exists($ruta) || filesize($ruta) === 0) {
    $_SESSION['flash_error'] = 'No se pudo generar el respaldo. Verifica la ruta de MySQL en config/sistema.php.';
    header('Location: mantenimiento.php'); exit;
}

// Registrar en el historial
$kb = (int)round(filesize($ruta) / 1024);
$pdo = obtenerConexion();
$pdo->prepare("INSERT INTO historial_backups (archivo, tamano_kb, usuario_id) VALUES (:a,:k,:u)")
    ->execute([':a'=>$archivo, ':k'=>$kb, ':u'=>$_SESSION['usuario_id'] ?? null]);

// Descargar el archivo
header('Content-Description: File Transfer');
header('Content-Type: application/sql');
header('Content-Disposition: attachment; filename="' . $archivo . '"');
header('Content-Length: ' . filesize($ruta));
readfile($ruta);
exit;
