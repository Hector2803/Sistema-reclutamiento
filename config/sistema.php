<?php
/* ============================================================
   SSR - A365 | Configuración del sistema (mantenimiento)
   Ajusta estas rutas según tu instalación de XAMPP en Windows.
   ============================================================ */

// Ruta a las herramientas de MySQL de XAMPP (incluye la barra final)
// Por defecto en Windows: C:\xampp\mysql\bin\
define('RUTA_MYSQL_BIN', 'C:\\xampp\\mysql\\bin\\');

// Versión de referencia del sistema (la versión vigente se lee de la BD)
define('SISTEMA_VERSION_BASE', '1.0.0');

// Carpeta donde se guardan los respaldos (BD y código)
define('DIR_BACKUPS', __DIR__ . '/../backups');

// Asegura que exista la carpeta de respaldos
if (!is_dir(DIR_BACKUPS)) { @mkdir(DIR_BACKUPS, 0775, true); }
