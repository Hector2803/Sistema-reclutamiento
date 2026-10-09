<?php
/* ============================================================
   SSR - Portal público | Datos desde la base de datos
   Reemplaza el arreglo de demostración: ahora todo sale de
   la tabla campanas (publicadas) + areas + palabras_clave.
   ============================================================ */
require_once __DIR__ . '/../config/conexion.php';

/** Divide un TEXT con un punto por línea en un arreglo de ítems. */
function lineasTexto(?string $txt): array {
    if ($txt === null || trim($txt) === '') return [];
    $salida = [];
    foreach (preg_split('/\r\n|\r|\n/', $txt) as $l) {
        $l = trim($l);
        if ($l !== '') $salida[] = $l;
    }
    return $salida;
}

/** Áreas activas (para el filtro del portal). */
function obtenerAreasPortal(): array {
    $pdo = obtenerConexion();
    return $pdo->query("SELECT id, nombre FROM areas WHERE estado = 1 ORDER BY nombre")->fetchAll();
}

/** Habilidades del área = palabras clave usadas para evaluar el CV. */
function habilidadesDelArea(int $areaId): array {
    $pdo = obtenerConexion();
    $st = $pdo->prepare("SELECT palabra FROM palabras_clave WHERE area_id = :a ORDER BY peso DESC, id LIMIT 8");
    $st->execute([':a' => $areaId]);
    return array_column($st->fetchAll(), 'palabra');
}

/** Texto amigable "hace X días". */
function haceCuanto(string $fecha): string {
    $d = (int)((time() - strtotime($fecha)) / 86400);
    if ($d <= 0) return 'Hoy';
    if ($d === 1) return 'Ayer';
    if ($d < 30) return "Hace $d días";
    $m = (int)($d / 30);
    return $m === 1 ? 'Hace 1 mes' : "Hace $m meses";
}

/** Convierte una fila de campanas en la ficha que usan las vistas. */
function armaFicha(array $c): array {
    $resumen = trim($c['descripcion'] ?? '');
    if ($resumen === '') {
        $resumen = 'Únete a A365 como ' . $c['puesto'] . ' en el área de ' . $c['area'] . '.';
    }
    return [
        'id'          => (int)$c['id'],
        'codigo'      => $c['codigo'],
        'titulo'      => $c['puesto'],
        'area'        => $c['area'],
        'ubicacion'   => $c['ubicacion'] ?: 'Lima, Perú',
        'modalidad'   => $c['modalidad'],
        'horario'     => $c['horario'] ?: '—',
        'remuneracion'=> $c['remuneracion'],
        'vacantes'    => (int)$c['vacantes'],
        'tipo'        => $c['tipo'],
        'prioridad'   => $c['prioridad'],
        'publicado'   => haceCuanto($c['creado_en']),
        'vence'       => $c['fecha_ingreso'] ? date('d/m/Y', strtotime($c['fecha_ingreso'])) : 'Sin fecha límite',
        'resumen'     => $resumen,
        'tareas'      => lineasTexto($c['tareas']),
        'requisitos'  => lineasTexto($c['requisitos']),
        'beneficios'  => lineasTexto($c['beneficios']),
        'habilidades' => habilidadesDelArea((int)$c['area_id']),
    ];
}

/**
 * Vacantes publicadas. Filtros: ['q'=>texto, 'area'=>id]
 * Devuelve [id => ficha].
 */
function obtenerEmpleos(array $filtro = []): array {
    $pdo = obtenerConexion();
    $sql = "SELECT c.*, a.nombre AS area
              FROM campanas c
              INNER JOIN areas a ON a.id = c.area_id
             WHERE c.publicado = 1 AND c.estado <> 'Cerrado'";
    $params = [];

    $q = trim($filtro['q'] ?? '');
    if ($q !== '') {
        $sql .= " AND (c.puesto LIKE :q1 OR a.nombre LIKE :q2 OR c.descripcion LIKE :q3 OR c.ubicacion LIKE :q4)";
        $like = '%' . $q . '%';
        $params[':q1'] = $like;
        $params[':q2'] = $like;
        $params[':q3'] = $like;
        $params[':q4'] = $like;
    }
    $area = (int)($filtro['area'] ?? 0);
    if ($area > 0) {
        $sql .= " AND c.area_id = :area";
        $params[':area'] = $area;
    }
    $sql .= " ORDER BY c.creado_en DESC";

    $st = $pdo->prepare($sql);
    $st->execute($params);

    $empleos = [];
    foreach ($st->fetchAll() as $c) {
        $f = armaFicha($c);
        $empleos[$f['id']] = $f;
    }
    return $empleos;
}

/** Una sola vacante publicada (o null). */
function obtenerEmpleo(int $id): ?array {
    $pdo = obtenerConexion();
    $st = $pdo->prepare(
        "SELECT c.*, a.nombre AS area
           FROM campanas c
           INNER JOIN areas a ON a.id = c.area_id
          WHERE c.id = :id AND c.publicado = 1 AND c.estado <> 'Cerrado' LIMIT 1");
    $st->execute([':id' => $id]);
    $c = $st->fetch();
    return $c ? armaFicha($c) : null;
}
