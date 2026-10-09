<?php
/* ============================================================
   SSR - A365 | Actualizar versión del sistema (RF-16)
   1) Valida el paquete .zip y el número de versión
   2) Respalda el CÓDIGO actual (zip) antes de aplicar
   3) Extrae el paquete y reemplaza archivos
      (conserva uploads/ y config/)
   4) Registra la versión en el historial
   ============================================================ */
require_once __DIR__ . '/includes/guardia.php';
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/config/sistema.php';

if (($_SESSION['usuario_rol'] ?? '') !== 'Administrador') { header('Location: dashboard.php'); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: mantenimiento.php'); exit; }

$pdo = obtenerConexion();
$usuarioId = $_SESSION['usuario_id'] ?? null;

// La actualización necesita la extensión zip de PHP
if (!class_exists('ZipArchive')) {
    $_SESSION['flash_error'] = 'Para actualizar la versión habilita la extensión zip de PHP: en XAMPP abre php.ini, quita el ";" de la línea ";extension=zip" y reinicia Apache.';
    header('Location: mantenimiento.php'); exit;
}

$version = trim($_POST['version'] ?? '');
$descr   = trim($_POST['descripcion'] ?? '');

// 1) Validaciones
if (!preg_match('/^[0-9]+\.[0-9]+\.[0-9]+$/', $version)) {
    $_SESSION['flash_error'] = 'El número de versión debe tener el formato X.Y.Z';
    header('Location: mantenimiento.php'); exit;
}
if (!isset($_FILES['paquete']) || $_FILES['paquete']['error'] !== UPLOAD_ERR_OK) {
    $_SESSION['flash_error'] = 'Selecciona un paquete .zip válido.';
    header('Location: mantenimiento.php'); exit;
}
if (strtolower(pathinfo($_FILES['paquete']['name'], PATHINFO_EXTENSION)) !== 'zip') {
    $_SESSION['flash_error'] = 'El paquete debe ser un archivo .zip';
    header('Location: mantenimiento.php'); exit;
}

$raiz = realpath(__DIR__);               // carpeta del sistema
$dirBackupsReal = realpath(DIR_BACKUPS); // normalizado, para comparar rutas de forma fiable (evita "config/../backups")
$nombrePaquete = 'update_v' . $version . '_' . date('Y-m-d_His') . '.zip';
$rutaPaquete   = DIR_BACKUPS . DIRECTORY_SEPARATOR . $nombrePaquete;
if (!move_uploaded_file($_FILES['paquete']['tmp_name'], $rutaPaquete)) {
    $_SESSION['flash_error'] = 'No se pudo procesar el paquete subido.';
    header('Location: mantenimiento.php'); exit;
}

// 2) Respaldo del CÓDIGO actual (por si hay que revertir)
$backupCode = DIR_BACKUPS . DIRECTORY_SEPARATOR . 'codigo_antes_v' . $version . '_' . date('Y-m-d_His') . '.zip';
$zipBk = new ZipArchive();
if ($zipBk->open($backupCode, ZipArchive::CREATE) === true) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raiz, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        $ruta = $file->getPathname();
        // No respaldar la propia carpeta de backups (evita recursión).
        // Se compara con la ruta normalizada (realpath); DIR_BACKUPS sin normalizar
        // (contiene "config/../backups") nunca coincidía con las rutas reales
        // devueltas por el iterador, así que cada respaldo terminaba incluyendo
        // también los respaldos anteriores y crecía sin control.
        if ($dirBackupsReal !== false && strpos($ruta, $dirBackupsReal . DIRECTORY_SEPARATOR) === 0) continue;
        $rel = ltrim(str_replace($raiz, '', $ruta), '\\/');
        $zipBk->addFile($ruta, $rel);
    }
    $zipBk->close();
}

// 3) Extraer el paquete y reemplazar (conservando uploads/ y config/)
$conservar = ['uploads', 'config', 'backups'];
$tmp = DIR_BACKUPS . DIRECTORY_SEPARATOR . 'tmp_update_' . time();
@mkdir($tmp, 0775, true);

$zip = new ZipArchive();
$exito = false; $detalle = '';
if ($zip->open($rutaPaquete) === true) {
    $zip->extractTo($tmp);
    $zip->close();

    // Si el zip trae una carpeta raíz única, entrar en ella
    $origen = $tmp;
    $items = array_values(array_diff(scandir($tmp), ['.','..']));
    if (count($items) === 1 && is_dir($tmp . DIRECTORY_SEPARATOR . $items[0])) {
        $origen = $tmp . DIRECTORY_SEPARATOR . $items[0];
    }

    // Copiar recursivamente respetando las carpetas a conservar
    copiarRecursivo($origen, $raiz, $conservar);
    $exito = true;
    $detalle = 'Actualización aplicada.';
} else {
    $detalle = 'No se pudo abrir el paquete .zip';
}

// Limpieza del temporal
borrarDir($tmp);

// 4) Registrar la versión
if ($exito) {
    $pdo->prepare("INSERT INTO versiones_sistema (version, descripcion, archivo, usuario_id)
                   VALUES (:v,:d,:a,:u)")
        ->execute([':v'=>$version, ':d'=>($descr?:null), ':a'=>$nombrePaquete, ':u'=>$usuarioId]);
    $_SESSION['flash_ok'] = "Sistema actualizado a la versión v$version. Se guardó un respaldo del código anterior.";
} else {
    $_SESSION['flash_error'] = "No se pudo aplicar la actualización: $detalle";
}
header('Location: mantenimiento.php'); exit;

/* ---------- utilidades ---------- */
function copiarRecursivo($origen, $destino, $conservar) {
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($origen, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST);
    foreach ($it as $item) {
        $rel = ltrim(str_replace($origen, '', $item->getPathname()), '\\/');
        $primera = explode(DIRECTORY_SEPARATOR, str_replace('/', DIRECTORY_SEPARATOR, $rel))[0];
        if (in_array($primera, $conservar, true)) continue; // no tocar uploads/ config/ backups/
        $dest = $destino . DIRECTORY_SEPARATOR . $rel;
        if ($item->isDir()) { if (!is_dir($dest)) @mkdir($dest, 0775, true); }
        else { @copy($item->getPathname(), $dest); }
    }
}
function borrarDir($dir) {
    if (!is_dir($dir)) return;
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $f) { $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname()); }
    @rmdir($dir);
}
