<?php
/* ============================================================
   SSR - A365 | Edición del jefe responsable de un área
   (organigrama: HU "editar los nombres de cada jefe a cargo
   de cada área")
   POST: area_id, jefe
   ============================================================ */

require_once __DIR__ . '/includes/guardia.php';
require_once __DIR__ . '/config/conexion.php';

// Solo por POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: organigrama.php');
    exit;
}

// Solo un Administrador gestiona el organigrama
if (($_SESSION['usuario_rol'] ?? '') !== 'Administrador') {
    $_SESSION['flash_error'] = 'No tienes permisos para editar el jefe de un área.';
    header('Location: organigrama.php');
    exit;
}

$areaId = (int)($_POST['area_id'] ?? 0);
$jefe   = trim((string)($_POST['jefe'] ?? ''));

// Se normalizan espacios y se dejan solo letras/números y puntuación básica
$jefe = preg_replace('/\s+/u', ' ', $jefe);
$jefe = preg_replace('/[^A-Za-z0-9ÁÉÍÓÚÜÑáéíóúüñ .,°\'()\/&-]/u', '', $jefe);
$jefe = trim($jefe);

try {
    $pdo = obtenerConexion();

    $st = $pdo->prepare("SELECT id, nombre FROM areas WHERE id = :id");
    $st->execute([':id' => $areaId]);
    $area = $st->fetch();

    if (!$area) {
        $_SESSION['flash_error'] = 'Área no encontrada.';
    } elseif ($jefe === '') {
        $_SESSION['flash_error'] = 'Ingresa el nombre del jefe responsable.';
    } elseif (mb_strlen($jefe) > 120) {
        $_SESSION['flash_error'] = 'El nombre no puede superar los 120 caracteres.';
    } else {
        $upd = $pdo->prepare("UPDATE areas SET jefe = :jefe WHERE id = :id");
        $upd->execute([':jefe' => $jefe, ':id' => $areaId]);
        $_SESSION['flash_ok'] = 'Jefe de «' . $area['nombre'] . '» actualizado: ' . $jefe;
    }
} catch (Throwable $e) {
    $_SESSION['flash_error'] = 'No se pudo guardar el cambio. Intenta nuevamente.';
}

header('Location: organigrama.php');
exit;
