<?php
/* Utilidad de apoyo (solo administradores).
   Ábrela en el navegador: http://localhost/SSR_Claro/generar_hash.php?clave=miClave
   Copia el hash resultante en la columna "clave" al crear usuarios nuevos.
   CP-04: toda página interna incluye guardia.php. */

require_once __DIR__ . '/includes/guardia.php';

if (($_SESSION['usuario_rol'] ?? '') !== 'Administrador') {
    header('Location: dashboard.php');
    exit;
}

$clave = $_GET['clave'] ?? '';
if ($clave !== '') {
    echo "Contraseña: " . htmlspecialchars($clave) . "<br>";
    echo "Hash: <code>" . password_hash($clave, PASSWORD_DEFAULT) . "</code>";
} else {
    echo 'Pasa la contraseña por la URL, ejemplo: ?clave=admin123';
}
