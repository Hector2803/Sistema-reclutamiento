<?php
/* ============================================================
   SSR - A365 | Notificaciones (campanita)
   Se generan a partir de lo que ocurre en el sistema, ya filtradas
   por el alcance del rol (el Reclutador solo ve su área):
     - candidatos nuevos que postularon
     - avances de etapa, seleccionados y descartes
     - entrevistas programadas para hoy o mañana
     - requerimientos nuevos
   "Leída" = ocurrió antes de la última vez que el usuario marcó
   sus notificaciones como vistas (tabla notificaciones_vistas, se
   crea sola la primera vez).
   ============================================================ */
require_once __DIR__ . '/alcance.php';

const NOTIF_DIAS = 14;     // antigüedad máxima
const NOTIF_MAX  = 25;     // máximo de elementos en la lista

function notifTabla(PDO $pdo): void {
    static $ok = false;
    if ($ok) return;
    $pdo->exec("CREATE TABLE IF NOT EXISTS notificaciones_vistas (
        usuario_id INT NOT NULL PRIMARY KEY,
        visto_en   DATETIME NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $ok = true;
}

function notifVistoEn(PDO $pdo, int $uid): string {
    $st = $pdo->prepare("SELECT visto_en FROM notificaciones_vistas WHERE usuario_id = :u");
    $st->execute([':u' => $uid]);
    $v = $st->fetchColumn();
    return $v ?: '1970-01-01 00:00:00';
}

function notifMarcarVistas(PDO $pdo, int $uid): void {
    notifTabla($pdo);
    $pdo->prepare("INSERT INTO notificaciones_vistas (usuario_id, visto_en) VALUES (:u, NOW())
                   ON DUPLICATE KEY UPDATE visto_en = NOW()")->execute([':u' => $uid]);
}

/* Devuelve ['items' => [...], 'no_leidas' => n]. Cada item: tipo, titulo, texto, link, hace (min), leida. */
function notifObtener(PDO $pdo, int $uid): array {
    notifTabla($pdo);
    $visto = notifVistoEn($pdo, $uid);
    $dias  = NOTIF_DIAS;
    $items = [];

    // 1) Movimientos de candidatos (historial de etapas)
    $rows = $pdo->query(
        "SELECT h.fecha ts, TIMESTAMPDIFF(MINUTE, h.fecha, NOW()) hace, h.motivo_id, h.etapa_origen, h.etapa_destino,
                e.nombre etapa, e.orden, pt.nombres, c.puesto, c.codigo, p.id pid
           FROM historial_etapas h
           INNER JOIN postulaciones p ON p.id = h.postulacion_id
           INNER JOIN postulantes pt ON pt.id = p.postulante_id
           INNER JOIN campanas c ON c.id = p.campana_id
           INNER JOIN etapas e ON e.id = h.etapa_destino
          WHERE h.etapa_origen IS NOT NULL AND h.fecha >= DATE_SUB(NOW(), INTERVAL $dias DAY)"
        . sqlAlcancePost('p.campana_id') . "
          ORDER BY h.fecha DESC, h.id DESC LIMIT 60")->fetchAll();
    $ultimaOrden = (int)$pdo->query("SELECT MAX(orden) FROM etapas")->fetchColumn();
    foreach ($rows as $r) {
        $link = 'proceso.php?post=' . (int)$r['pid'];
        if ($r['motivo_id']) {
            $tipo = 'rojo';  $tit = 'Candidato descartado';
            $txt  = $r['nombres'] . ' · ' . $r['puesto'] . ' (etapa: ' . $r['etapa'] . ')';
        } elseif ((int)$r['orden'] === $ultimaOrden) {
            $tipo = 'verde'; $tit = 'Candidato seleccionado';
            $txt  = $r['nombres'] . ' completó el proceso · ' . $r['puesto'];
        } elseif ((int)$r['orden'] === 2) {
            $tipo = 'azul';  $tit = 'Nuevo candidato';
            $txt  = $r['nombres'] . ' postuló a ' . $r['puesto'] . ' (' . $r['codigo'] . ')';
        } else {
            $tipo = 'azul';  $tit = 'Avance de etapa';
            $txt  = $r['nombres'] . ' pasó a «' . $r['etapa'] . '» · ' . $r['puesto'];
        }
        $items[] = ['tipo' => $tipo, 'titulo' => $tit, 'texto' => $txt, 'link' => $link,
                    'ts' => $r['ts'], 'hace' => (int)$r['hace']];
    }

    // 2) Entrevistas de hoy y mañana
    $rows = $pdo->query(
        "SELECT en.fecha, en.hora, pt.nombres, c.puesto, p.id pid,
                TIMESTAMP(DATE_SUB(en.fecha, INTERVAL 1 DAY)) ts,
                GREATEST(0, TIMESTAMPDIFF(MINUTE, TIMESTAMP(DATE_SUB(en.fecha, INTERVAL 1 DAY)), NOW())) hace,
                (en.fecha = CURDATE()) hoy
           FROM entrevistas en
           INNER JOIN postulaciones p ON p.id = en.postulacion_id
           INNER JOIN postulantes pt ON pt.id = p.postulante_id
           INNER JOIN campanas c ON c.id = p.campana_id
          WHERE en.estado = 'Programada' AND en.fecha BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 1 DAY)"
        . sqlAlcanceEntrevista('p.campana_id') . "
          ORDER BY en.fecha, en.hora LIMIT 15")->fetchAll();
    foreach ($rows as $r) {
        $items[] = ['tipo' => 'ambar', 'titulo' => $r['hoy'] ? 'Entrevista hoy' : 'Entrevista mañana',
                    'texto' => substr($r['hora'], 0, 5) . ' · ' . $r['nombres'] . ' · ' . $r['puesto'],
                    'link' => 'entrevistas.php', 'ts' => $r['ts'], 'hace' => (int)$r['hace']];
    }

    // 2b) Entrevistas que me asignaron (últimos días)
    $uid2 = (int)$uid;
    $rows = $pdo->query(
        "SELECT pt.nombres, c.puesto, en.fecha, en.hora, en.creado_en ts,
                TIMESTAMPDIFF(MINUTE, en.creado_en, NOW()) hace
           FROM entrevistas en
           INNER JOIN postulaciones p ON p.id = en.postulacion_id
           INNER JOIN postulantes pt ON pt.id = p.postulante_id
           INNER JOIN campanas c ON c.id = p.campana_id
          WHERE en.entrevistador_id = $uid2 AND en.estado IN ('Programada','En Curso')
            AND en.creado_en >= DATE_SUB(NOW(), INTERVAL $dias DAY)
          ORDER BY en.creado_en DESC LIMIT 10")->fetchAll();
    foreach ($rows as $r) {
        $items[] = ['tipo' => 'ambar', 'titulo' => 'Entrevista asignada a ti',
                    'texto' => date('d/m', strtotime($r['fecha'])) . ' ' . substr($r['hora'], 0, 5) . ' · ' . $r['nombres'] . ' · ' . $r['puesto'],
                    'link' => 'entrevistas.php', 'ts' => $r['ts'], 'hace' => (int)$r['hace']];
    }

    // 3) Requerimientos nuevos (el Reclutador no ve el módulo de requerimientos)
    $rows = alcanceEsReclutador() ? [] : $pdo->query(
        "SELECT codigo, puesto, creado_en ts, TIMESTAMPDIFF(MINUTE, creado_en, NOW()) hace
           FROM campanas WHERE creado_en >= DATE_SUB(NOW(), INTERVAL $dias DAY)"
        . sqlAlcanceCampana('area_id') . " ORDER BY creado_en DESC LIMIT 10")->fetchAll();
    foreach ($rows as $r) {
        $items[] = ['tipo' => 'morado', 'titulo' => 'Nuevo requerimiento',
                    'texto' => $r['codigo'] . ' · ' . $r['puesto'], 'link' => 'requerimientos.php',
                    'ts' => $r['ts'], 'hace' => (int)$r['hace']];
    }

    usort($items, fn($a, $b) => $a['hace'] <=> $b['hace']);
    $items = array_slice($items, 0, NOTIF_MAX);
    $n = 0;
    foreach ($items as &$it) {
        $it['leida'] = ($it['ts'] <= $visto);
        if (!$it['leida']) $n++;
    }
    unset($it);
    return ['items' => $items, 'no_leidas' => $n];
}
