<?php
/* ============================================================
   SSR - Claro Ecuador | Conexión a la base de datos (PDO)
   Ajusta las credenciales según tu instalación de XAMPP.
   ============================================================ */

define('DB_HOST', 'localhost');
define('DB_NOMBRE', 'ssr_a365');   // base unificada (SARA + EVALUAR)
define('DB_USUARIO', 'root');   // usuario por defecto de XAMPP
define('DB_CLAVE', '');         // por defecto XAMPP no tiene contraseña
define('DB_CHARSET', 'utf8mb4');

function obtenerConexion(): PDO
{
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NOMBRE . ";charset=" . DB_CHARSET;

    $opciones = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        return new PDO($dsn, DB_USUARIO, DB_CLAVE, $opciones);
    } catch (PDOException $e) {
        // En producción no se muestra el detalle del error al usuario.
        die("Error de conexión con la base de datos. Verifica que XAMPP (MySQL) esté activo.");
    }
}
