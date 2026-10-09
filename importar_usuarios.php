<?php
/* ============================================================
   SSR - A365 | Importar usuarios desde CSV
   Columnas: nombre, usuario, correo, rol  (opcional: area)
   Se crean con contraseña temporal y deben cambiarla al ingresar.
   ============================================================ */

require_once __DIR__ . '/includes/guardia.php';
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/includes/passwords.php';
require_once __DIR__ . '/includes/correo.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: usuarios.php');
    exit;
}

if (($_SESSION['usuario_rol'] ?? '') !== 'Administrador') {
    $_SESSION['flash_error'] = 'No tienes permisos para importar usuarios.';
    header('Location: usuarios.php');
    exit;
}

if (!isset($_FILES['csv']) || $_FILES['csv']['error'] !== UPLOAD_ERR_OK) {
    $_SESSION['flash_error'] = 'Selecciona un archivo CSV válido.';
    header('Location: usuarios.php');
    exit;
}

if ($_FILES['csv']['size'] > 512 * 1024) {
    $_SESSION['flash_error'] = 'El archivo supera el tamaño máximo de 512 KB.';
    header('Location: usuarios.php');
    exit;
}

$contenido = file_get_contents($_FILES['csv']['tmp_name']);
if ($contenido === false) {
    $_SESSION['flash_error'] = 'No se pudo leer el archivo.';
    header('Location: usuarios.php');
    exit;
}

// Quitar BOM (Excel) y saltos de Windows
$contenido = preg_replace('/^\xEF\xBB\xBF/', '', $contenido);
$lineas = preg_split('/\r\n|\n|\r/', trim($contenido));

$delim = (strpos($lineas[0] ?? '', ';') !== false) ? ';' : ',';
$filas = [];
foreach ($lineas as $l) { if (trim($l) !== '') $filas[] = str_getcsv($l, $delim); }
if (!$filas) {
    $_SESSION['flash_error'] = 'El archivo está vacío.';
    header('Location: usuarios.php');
    exit;
}

/* Encabezado opcional: si la primera fila contiene "usuario", se omite */
$primera = array_map(fn($c) => mb_strtolower(trim((string)$c)), $filas[0]);
if (in_array('usuario', $primera, true) || in_array('nombre', $primera, true)) {
    array_shift($filas);
    // Reordenar columnas si el encabezado lo indica
    $orden = [];
    foreach ($primera as $i => $col) {
        if (in_array($col, ['nombre','usuario','correo','email','rol','area'], true)) $orden[$col] = $i;
    }
    if (isset($orden['usuario'])) {
        $mapeado = [];
        foreach ($filas as $f) {
            $mapeado[] = [
                'nombre'  => $f[$orden['nombre']  ?? 0] ?? '',
                'usuario' => $f[$orden['usuario'] ?? 1] ?? '',
                'correo'  => $f[$orden['correo'] ?? $orden['email'] ?? 2] ?? '',
                'rol'     => $f[$orden['rol'] ?? 3] ?? '',
                'area'    => $f[$orden['area'] ?? 4] ?? '',
            ];
        }
        $filas = $mapeado;
    }
}

try {
    $pdo = obtenerConexion();

    $roles = [];
    foreach ($pdo->query("SELECT id, nombre FROM roles")->fetchAll() as $r) {
        $roles[mb_strtolower($r['nombre'])] = (int)$r['id'];
    }
    $areas = [];
    foreach ($pdo->query("SELECT id, nombre FROM areas WHERE estado = 1")->fetchAll() as $a) {
        $areas[mb_strtolower($a['nombre'])] = (int)$a['id'];
    }

    $existe = $pdo->prepare("SELECT id FROM usuarios WHERE usuario = :u");
    $insert = $pdo->prepare(
        "INSERT INTO usuarios (rol_id, area_id, usuario, nombre, correo, clave, estado, debe_cambiar_clave)
         VALUES (:rol, :area, :usuario, :nombre, :correo, :clave, 1, 1)"
    );

    $creados  = [];
    $omitidos = [];
    $errores  = [];

    foreach ($filas as $n => $f) {
        $filaNum = $n + 1;

        if (is_array($f) && isset($f['nombre'])) {
            $nombre  = trim((string)$f['nombre']);
            $usuario = trim((string)$f['usuario']);
            $correo  = trim((string)($f['correo'] ?? ''));
            $rolTxt  = trim((string)($f['rol'] ?? ''));
            $areaTxt = trim((string)($f['area'] ?? ''));
        } else {
            $nombre  = trim((string)($f[0] ?? ''));
            $usuario = trim((string)($f[1] ?? ''));
            $correo  = trim((string)($f[2] ?? ''));
            $rolTxt  = trim((string)($f[3] ?? ''));
            $areaTxt = trim((string)($f[4] ?? ''));
        }

        if ($nombre === '' && $usuario === '') continue;

        if ($nombre === '' || $usuario === '') {
            $errores[] = "Fila $filaNum: faltan nombre o usuario.";
            continue;
        }
        if (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $usuario)) {
            $errores[] = "Fila $filaNum: el usuario «{$usuario}» solo puede tener letras, números, punto, guion o guion bajo (3 a 50).";
            continue;
        }
        // Cuenta de la empresa: se le agrega el dominio configurado
        if (strpos($usuario, '@') === false) {
            $usuario = strtolower($usuario . '@' . correoDominio($pdo));
        }
        if ($correo !== '' && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            $errores[] = "Fila $filaNum: el correo «{$correo}» no es válido.";
            continue;
        }

        $rolId = $roles[mb_strtolower($rolTxt)] ?? null;
        if (!$rolId) {
            $errores[] = "Fila $filaNum: rol «{$rolTxt}» desconocido (usa: " . implode(', ', array_map('ucfirst', array_keys($roles))) . ").";
            continue;
        }
        $areaId = $areas[mb_strtolower($areaTxt)] ?? null;
        if ($areaTxt !== '' && !$areaId) {
            $errores[] = "Fila $filaNum: área «{$areaTxt}» no existe.";
            continue;
        }

        $existe->execute([':u' => $usuario]);
        if ($existe->fetch()) {
            $omitidos[] = $usuario;
            continue;
        }

        $clave = generarClaveTemporal();
        $insert->execute([
            ':rol'     => $rolId,
            ':area'    => $areaId,
            ':usuario' => $usuario,
            ':nombre'  => $nombre,
            ':correo'  => $correo !== '' ? $correo : null,
            ':clave'   => hashClave($clave),
        ]);
        $creados[] = $usuario . ' → ' . $clave;
    }

    if (!$creados && !$errores && !$omitidos) {
        $_SESSION['flash_error'] = 'No se encontraron filas para importar.';
        header('Location: usuarios.php');
        exit;
    }

    $msg = '';
    if ($creados) {
        $msg .= "Creados: " . count($creados) . " usuario(s). Comparte estas contraseñas temporales "
              . "(deberán cambiarlas al iniciar):\n- " . implode("\n- ", $creados) . "\n";
    }
    if ($omitidos) {
        $msg .= "Omitidos (ya existían): " . implode(', ', $omitidos) . "\n";
    }
    if ($errores) {
        $msg .= "Con errores:\n- " . implode("\n- ", $errores);
    }

    $_SESSION[$creados ? 'flash_ok' : 'flash_error'] = trim($msg);
    header('Location: usuarios.php');
    exit;

} catch (PDOException $e) {
    $_SESSION['flash_error'] = 'No se pudo importar el archivo. Verifica el formato e intenta nuevamente.';
    header('Location: usuarios.php');
    exit;
}
