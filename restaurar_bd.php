<?php
/* ============================================================
   SSR - A365 | Restaurar base de datos (RF-15)
   1) Valida el archivo .sql
   2) Hace un respaldo AUTOMÁTICO antes de restaurar
   3) Restaura con el cliente mysql
   4) Registra el resultado en el historial
   ============================================================ */
require_once __DIR__ . '/includes/guardia.php';
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/config/sistema.php';

if (($_SESSION['usuario_rol'] ?? '') !== 'Administrador') { header('Location: dashboard.php'); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: mantenimiento.php'); exit; }

$pdo = obtenerConexion();
$usuarioId = $_SESSION['usuario_id'] ?? null;

// 1) Validar archivo
if (!isset($_FILES['respaldo']) || $_FILES['respaldo']['error'] !== UPLOAD_ERR_OK) {
    $_SESSION['flash_error'] = 'Selecciona un archivo .sql válido.';
    header('Location: mantenimiento.php'); exit;
}
$ext = strtolower(pathinfo($_FILES['respaldo']['name'], PATHINFO_EXTENSION));
if ($ext !== 'sql') {
    $_SESSION['flash_error'] = 'El archivo debe tener extensión .sql';
    header('Location: mantenimiento.php'); exit;
}
if ($_FILES['respaldo']['size'] > 50 * 1024 * 1024) {
    $_SESSION['flash_error'] = 'El archivo supera el tamaño permitido (50 MB).';
    header('Location: mantenimiento.php'); exit;
}

$nombreSubido = 'restauracion_' . date('Y-m-d_His') . '.sql';
$rutaSubido   = DIR_BACKUPS . DIRECTORY_SEPARATOR . $nombreSubido;
if (!move_uploaded_file($_FILES['respaldo']['tmp_name'], $rutaSubido)) {
    $_SESSION['flash_error'] = 'No se pudo procesar el archivo subido.';
    header('Location: mantenimiento.php'); exit;
}

$pass    = DB_CLAVE !== '' ? ' --password=' . escapeshellarg(DB_CLAVE) : '';
$credc   = ' --user=' . escapeshellarg(DB_USUARIO) . $pass . ' --host=' . escapeshellarg(DB_HOST);

// 2) Respaldo AUTOMÁTICO antes de restaurar (seguridad)
$autoBk  = 'auto_antes_restaurar_' . date('Y-m-d_His') . '.sql';
$rutaAuto= DIR_BACKUPS . DIRECTORY_SEPARATOR . $autoBk;
$dump = '"' . RUTA_MYSQL_BIN . 'mysqldump.exe"' . $credc . ' ' . escapeshellarg(DB_NOMBRE)
      . ' --result-file=' . escapeshellarg($rutaAuto) . ' 2>&1';
@exec($dump, $o1, $r1);

// 3) Restaurar: mysql db -e "source archivo"
$mysql = '"' . RUTA_MYSQL_BIN . 'mysql.exe"' . $credc . ' ' . escapeshellarg(DB_NOMBRE)
       . ' -e ' . escapeshellarg('source ' . str_replace('\\','/',$rutaSubido)) . ' 2>&1';
$salida = []; $ret = 0;
@exec($mysql, $salida, $ret);

$exito = ($ret === 0);
$detalle = $exito ? 'Restauración completada.' : ('Error: ' . implode(' ', array_slice($salida,0,3)));

// 4) Registrar en historial
$pdo->prepare("INSERT INTO historial_restauraciones (archivo, resultado, detalle, usuario_id)
               VALUES (:a,:res,:det,:u)")
    ->execute([':a'=>$_FILES['respaldo']['name'], ':res'=>$exito?'Exitosa':'Fallida', ':det'=>$detalle, ':u'=>$usuarioId]);

$_SESSION[$exito ? 'flash_ok' : 'flash_error'] =
    $exito ? 'Base de datos restaurada correctamente. Se guardó un respaldo previo automático.'
           : 'No se pudo restaurar. Se conservó un respaldo previo automático. Verifica la ruta de MySQL.';
header('Location: mantenimiento.php'); exit;
