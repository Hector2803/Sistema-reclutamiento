<?php
/* ============================================================
   SSR - A365 | Alcance de datos por rol
   - Administrador y Supervisor: ven todas las áreas.
   - Reclutador: solo ve lo que corresponde a SU ÁREA
     (candidatos, entrevistas, tablero, indicadores).
   Si el reclutador no tiene un área asignada no ve ningún dato,
   para no exponer información de otras áreas por omisión.
   ============================================================ */

function alcanceEsReclutador(): bool {
    return ($_SESSION['usuario_rol'] ?? '') === 'Reclutador';
}

function alcanceAreaId(): int {
    return (int)($_SESSION['usuario_area_id'] ?? 0);
}

/* Fragmento SQL para tablas con una columna campana_id (postulaciones).
   Ejemplo: "SELECT ... FROM postulaciones p WHERE 1=1" . sqlAlcancePost('p.campana_id') */
function sqlAlcancePost(string $columnaCampana = 'campana_id'): string {
    if (!alcanceEsReclutador()) return '';
    $a = alcanceAreaId();
    if ($a <= 0) return ' AND 1=0';
    return " AND $columnaCampana IN (SELECT id FROM campanas WHERE area_id = $a)";
}

/* Fragmento SQL para la tabla campanas (columna area_id). */
function sqlAlcanceCampana(string $columnaArea = 'area_id'): string {
    if (!alcanceEsReclutador()) return '';
    $a = alcanceAreaId();
    if ($a <= 0) return ' AND 1=0';
    return " AND $columnaArea = $a";
}

/* ¿El usuario de la sesión puede ver/gestionar esta postulación? */
function puedeGestionarPostulacion(PDO $pdo, int $postulacionId): bool {
    if (!alcanceEsReclutador()) return true;
    $a = alcanceAreaId();
    if ($a <= 0) return false;
    $st = $pdo->prepare(
        "SELECT 1 FROM postulaciones p INNER JOIN campanas c ON c.id = p.campana_id
          WHERE p.id = :p AND c.area_id = :a LIMIT 1");
    $st->execute([':p' => $postulacionId, ':a' => $a]);
    return (bool)$st->fetch();
}
