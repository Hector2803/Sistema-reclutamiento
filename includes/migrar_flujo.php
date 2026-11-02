<?php
/* ============================================================
   SSR - A365 | Migración automática al flujo de 6 etapas
   Si la base de datos aún no tiene la tabla "capacitaciones" o las
   columnas val_datos / val_docs (por ejemplo, se actualizó el código
   pero no se importó sql/flujo_proceso.sql), este archivo ejecuta ese
   mismo script una sola vez. El script es idempotente.
   Si el usuario de la base no tiene permisos, no se rompe la página:
   se registra el error y debe importarse el script a mano.
   ============================================================ */
function migrarFlujoSiHaceFalta(): void
{
    if (defined('MIGRAR_FLUJO_AUTO') && !MIGRAR_FLUJO_AUTO) return;   // desactivar: define('MIGRAR_FLUJO_AUTO', false) en config/sistema.php
    static $hecho = false;
    if ($hecho) return;
    $hecho = true;

    try {
        $pdo = obtenerConexion();
        $tabla = $pdo->query("SHOW TABLES LIKE 'capacitaciones'")->fetchColumn();
        $col   = $pdo->query("SHOW COLUMNS FROM postulaciones LIKE 'val_docs'")->fetchColumn();
        // ¿Quedan etapas del flujo anterior (o la tabla de etapas no tiene exactamente las 6 del flujo)?
        $total  = (int)$pdo->query("SELECT COUNT(*) FROM etapas")->fetchColumn();
        $nuevas = (int)$pdo->query("SELECT COUNT(*) FROM etapas WHERE nombre IN ('Postulación','Evaluación (test)','Validación (datos, CV y documentos)','Entrevista presencial','Capacitaciones','Proceso finalizado')")->fetchColumn();
        $viejas = (int)$pdo->query("SELECT COUNT(*) FROM etapas WHERE nombre LIKE 'Validaci%' AND nombre <> 'Validación (datos, CV y documentos)' OR nombre LIKE 'Contacto%' OR nombre LIKE 'Entrevista /%' OR nombre LIKE 'Acepta%' OR nombre LIKE 'Antecedentes%' OR nombre = 'Ingresa'")->fetchColumn();
        if ($tabla && $col && $total === 6 && $nuevas === 6 && $viejas === 0) return;

        // Protección: no reintentar en cada petición (si la migración falla, se espera 10 minutos)
        $dirLock = is_dir(__DIR__ . '/../backups') && is_writable(__DIR__ . '/../backups') ? __DIR__ . '/../backups' : sys_get_temp_dir();
        $lock = $dirLock . '/.migracion_flujo_intento';
        if (is_file($lock) && (time() - (int)@filemtime($lock)) < 600) return;
        @touch($lock);

        $archivo = __DIR__ . '/../sql/flujo_proceso.sql';
        if (!is_file($archivo)) return;
        $sql = file_get_contents($archivo);
        $sql = preg_replace('/^\s*--.*$/m', '', $sql);                 // quita comentarios
        $sentencias = preg_split('/;\s*(?:\r?\n|$)/', $sql);
        foreach ($sentencias as $s) {
            $s = trim($s);
            if ($s === '') continue;
            try { $pdo->exec($s); }
            catch (Throwable $e) { error_log('SSR migrar_flujo (' . substr($s, 0, 60) . '): ' . $e->getMessage()); throw $e; }
        }
    } catch (Throwable $e) {
        error_log('SSR migrar_flujo: ' . $e->getMessage());
    }
}
