<?php
/* ============================================================
   SSR - A365 | Reglas del flujo del proceso de reclutamiento
   1 Postulación -> 2 Evaluación (test, en el portal) ->
   3 Validación (datos, CV y documentos) -> 4 Entrevista presencial ->
   5 Capacitaciones (hasta 5, con asistencia) -> 6 Proceso finalizado
   Todas las pantallas y controladores usan estas funciones, de modo
   que el flujo se respeta igual desde la lista, el tablero o la
   pantalla de gestión del candidato.
   ============================================================ */

/* Las fechas del flujo (entrevistas, capacitaciones) usan la hora de Lima, igual que el resto del sistema */
date_default_timezone_set('America/Lima');

const FLUJO_UMBRAL_CV = 50;          // % mínimo de coincidencia del CV para ser "apto"
const FLUJO_MAX_CAPACITACIONES = 5;  // máximo de capacitaciones por candidato

/* Etapa por su número de orden (1..6) */
function flujoEtapa(PDO $pdo, int $orden): ?array {
    $st = $pdo->prepare("SELECT id, nombre, orden, color FROM etapas WHERE orden = :o LIMIT 1");
    $st->execute([':o' => $orden]);
    return $st->fetch() ?: null;
}
function flujoUltimoOrden(PDO $pdo): int {
    return (int)$pdo->query("SELECT MAX(orden) FROM etapas")->fetchColumn();
}

/* ¿El CV alcanza el puntaje mínimo? (sin CV = no apto) */
function flujoCvApto($puntaje): bool {
    return $puntaje !== null && (int)$puntaje >= FLUJO_UMBRAL_CV;
}

/* Último resultado de la evaluación (test) de la postulación */
function flujoResultadoTest(PDO $pdo, int $postId): ?array {
    $st = $pdo->prepare("SELECT puntaje, aprobado FROM resultados_eval WHERE postulacion_id = :p ORDER BY id DESC LIMIT 1");
    $st->execute([':p' => $postId]);
    return $st->fetch() ?: null;
}

/* Datos de la postulación con su etapa actual */
function flujoPostulacion(PDO $pdo, int $postId): ?array {
    $st = $pdo->prepare(
        "SELECT p.id, p.estado, p.etapa_id, p.puntaje_cv, p.cv_detalle, p.val_datos, p.val_docs, p.codigo_seguimiento,
                e.orden AS orden, e.nombre AS etapa, po.nombres, po.dni, po.correo, po.telefono,
                c.puesto, c.codigo AS req, c.area_id
           FROM postulaciones p
           INNER JOIN etapas e ON e.id = p.etapa_id
           INNER JOIN postulantes po ON po.id = p.postulante_id
           INNER JOIN campanas c ON c.id = p.campana_id
          WHERE p.id = :id LIMIT 1");
    $st->execute([':id' => $postId]);
    return $st->fetch() ?: null;
}

function flujoEntrevistas(PDO $pdo, int $postId): array {
    $st = $pdo->prepare("SELECT * FROM entrevistas WHERE postulacion_id = :p ORDER BY fecha DESC, hora DESC, id DESC");
    $st->execute([':p' => $postId]);
    return $st->fetchAll();
}
function flujoCapacitaciones(PDO $pdo, int $postId): array {
    $st = $pdo->prepare("SELECT * FROM capacitaciones WHERE postulacion_id = :p ORDER BY numero");
    $st->execute([':p' => $postId]);
    return $st->fetchAll();
}

/* Motivo de descarte por texto (LIKE) */
function flujoMotivoId(PDO $pdo, string $like): ?int {
    $st = $pdo->prepare("SELECT id FROM motivos_descarte WHERE descripcion LIKE :d ORDER BY id LIMIT 1");
    $st->execute([':d' => $like]);
    $id = $st->fetchColumn();
    return $id ? (int)$id : null;
}

/* Condiciones para SALIR de la etapa actual hacia la siguiente.
   Devuelve null si se puede avanzar, o el mensaje que explica qué falta. */
function flujoCondicionesSalida(PDO $pdo, array $p): ?string {
    $o = (int)$p['orden'];
    if ($o === 1) return null; // la postulación pasa sola al test
    if ($o === 2) {
        $t = flujoResultadoTest($pdo, (int)$p['id']);
        if (!$t) return 'El postulante aún no rinde la evaluación (la rinde en el portal con su código y DNI).';
        if (!(int)$t['aprobado']) return 'El postulante no aprobó la evaluación.';
        return null;
    }
    if ($o === 3) {
        $t = flujoResultadoTest($pdo, (int)$p['id']);
        if (!$t || !(int)$t['aprobado']) return 'Falta la evaluación aprobada del postulante.';
        if (!flujoCvApto($p['puntaje_cv'])) return 'El CV no alcanza el puntaje mínimo de ' . FLUJO_UMBRAL_CV . '% (o no tiene CV).';
        if (!(int)$p['val_datos']) return 'Falta verificar los datos del postulante.';
        if (!(int)$p['val_docs'])  return 'Falta verificar los documentos del postulante.';
        return null;
    }
    if ($o === 4) {
        $st = $pdo->prepare("SELECT COUNT(*) FROM entrevistas WHERE postulacion_id = :p AND estado = 'Completada'");
        $st->execute([':p' => $p['id']]);
        return ((int)$st->fetchColumn() > 0) ? null : 'La entrevista presencial debe estar realizada y verificada (estado Completada).';
    }
    if ($o === 5) {
        $caps = flujoCapacitaciones($pdo, (int)$p['id']);
        if (!$caps) return 'Debes programar al menos una capacitación.';
        foreach ($caps as $c) if ($c['asistencia'] !== 'Asistió') return 'Todas las capacitaciones deben tener la asistencia confirmada.';
        return null;
    }
    return 'El proceso ya finalizó.';
}

/* Valida un cambio de etapa pedido por un usuario. null = permitido. */
function flujoValidarCambio(PDO $pdo, array $p, int $ordenDestino): ?string {
    $rol = $_SESSION['usuario_rol'] ?? '';
    $o   = (int)$p['orden'];
    if ($p['estado'] === 'Descartado') return 'El candidato está descartado: no se puede mover de etapa.';
    if ($ordenDestino === $o) return 'El candidato ya está en esa etapa.';
    if ($ordenDestino < 2) return 'La etapa «Postulación» es automática y no se puede asignar manualmente.';
    if ($ordenDestino < $o) {
        return in_array($rol, ['Administrador', 'Supervisor'], true)
            ? null
            : 'Solo el Supervisor o el Administrador pueden devolver a un candidato a una etapa anterior.';
    }
    if ($ordenDestino > $o + 1) return 'Debes avanzar una etapa a la vez, respetando el flujo del proceso.';
    return flujoCondicionesSalida($pdo, $p);
}

/* Mueve la postulación y deja el historial. El estado pasa a «Seleccionado» en la última etapa. */
function flujoMover(PDO $pdo, int $postId, int $etapaOrigen, array $etapaDestino, ?string $obs, $usuarioId): void {
    $ultimo = ((int)$etapaDestino['orden'] === flujoUltimoOrden($pdo));
    $pdo->prepare("UPDATE postulaciones SET etapa_id = :e, estado = :s WHERE id = :id")
        ->execute([':e' => $etapaDestino['id'], ':s' => $ultimo ? 'Seleccionado' : 'En Proceso', ':id' => $postId]);
    $pdo->prepare("INSERT INTO historial_etapas (postulacion_id, etapa_origen, etapa_destino, observacion, usuario_id)
                   VALUES (:p,:o,:d,:obs,:u)")
        ->execute([':p' => $postId, ':o' => $etapaOrigen, ':d' => $etapaDestino['id'], ':obs' => ($obs ?: null), ':u' => $usuarioId]);
}

/* Descarta con motivo y deja el historial en la etapa donde ocurrió. */
function flujoDescartar(PDO $pdo, int $postId, int $etapaId, int $motivoId, ?string $obs, $usuarioId): void {
    $pdo->prepare("UPDATE postulaciones SET estado = 'Descartado' WHERE id = :id")->execute([':id' => $postId]);
    $pdo->prepare("INSERT INTO historial_etapas (postulacion_id, etapa_origen, etapa_destino, motivo_id, observacion, usuario_id)
                   VALUES (:p,:o,:d,:m,:obs,:u)")
        ->execute([':p' => $postId, ':o' => $etapaId, ':d' => $etapaId, ':m' => $motivoId, ':obs' => ($obs ?: null), ':u' => $usuarioId]);
}

/* Alta de una postulación: queda en «Postulación» y pasa de inmediato a «Evaluación (test)»,
   que el postulante rinde en el portal. Devuelve nada; deja los dos registros de historial. */
function flujoIniciarPostulacion(PDO $pdo, int $postId, $usuarioId): void {
    $e1 = flujoEtapa($pdo, 1); $e2 = flujoEtapa($pdo, 2);
    if (!$e1 || !$e2) return;
    $pdo->prepare("INSERT INTO historial_etapas (postulacion_id, etapa_origen, etapa_destino, usuario_id) VALUES (:p, NULL, :e, :u)")
        ->execute([':p' => $postId, ':e' => $e1['id'], ':u' => $usuarioId]);
    $pdo->prepare("UPDATE postulaciones SET etapa_id = :e WHERE id = :id")->execute([':e' => $e2['id'], ':id' => $postId]);
    $pdo->prepare("INSERT INTO historial_etapas (postulacion_id, etapa_origen, etapa_destino, observacion, usuario_id) VALUES (:p,:o,:d,:obs,:u)")
        ->execute([':p' => $postId, ':o' => $e1['id'], ':d' => $e2['id'], ':obs' => 'Pasa a la evaluación (test): la rinde el postulante en el portal', ':u' => $usuarioId]);
}
