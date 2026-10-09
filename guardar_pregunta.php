<?php
/* ============================================================
   SSR - A365 | Acciones del banco de preguntas (HU-08)
   accion = config | agregar | eliminar
   ============================================================ */
require_once __DIR__ . '/includes/guardia.php';
require_once __DIR__ . '/config/conexion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: banco_preguntas.php'); exit; }
if (!in_array(($_SESSION['usuario_rol'] ?? ''), ['Administrador','Supervisor'], true)) {
    header('Location: dashboard.php'); exit;
}

$accion = $_POST['accion'] ?? '';
$areaId = (int)($_POST['area_id'] ?? 0);
$volver = 'banco_preguntas.php?area=' . $areaId;

function salir(string $tipo, string $msg, string $url): void {
    $_SESSION[$tipo] = $msg;
    header('Location: ' . $url); exit;
}

try {
    $pdo = obtenerConexion();

    /* Evaluación actual del área */
    $st = $pdo->prepare("SELECT id FROM evaluaciones WHERE area_id = :a ORDER BY id LIMIT 1");
    $st->execute([':a' => $areaId]);
    $evalId = (int)($st->fetch()['id'] ?? 0);

    if ($accion === 'config') {
        $nombre = trim($_POST['nombre'] ?? '');
        $nota   = max(1, min(100, (int)($_POST['nota_minima'] ?? 70)));
        $tiempo = max(1, min(180, (int)($_POST['tiempo_limite'] ?? 20)));
        $total  = max(1, min(100, (int)($_POST['total_preguntas'] ?? 20)));
        if ($nombre === '' || $areaId === 0) salir('flash_error', 'Completa el nombre de la evaluación.', $volver);

        if ($evalId) {
            $pdo->prepare("UPDATE evaluaciones SET nombre=:n, nota_minima=:nm, tiempo_limite=:t, total_preguntas=:tp WHERE id=:id")
                ->execute([':n'=>$nombre, ':nm'=>$nota, ':t'=>$tiempo, ':tp'=>$total, ':id'=>$evalId]);
            salir('flash_ok', 'Configuración guardada.', $volver);
        }
        $pdo->prepare("INSERT INTO evaluaciones (area_id, nombre, nota_minima, tiempo_limite, total_preguntas) VALUES (:a,:n,:nm,:t,:tp)")
            ->execute([':a'=>$areaId, ':n'=>$nombre, ':nm'=>$nota, ':t'=>$tiempo, ':tp'=>$total]);
        salir('flash_ok', 'Evaluación creada. Ahora agrega sus preguntas.', $volver);
    }

    if ($accion === 'agregar') {
        if (!$evalId) salir('flash_error', 'Primero crea la evaluación del área.', $volver);
        $enunciado = trim($_POST['enunciado'] ?? '');
        $opciones  = array_map('trim', (array)($_POST['opciones'] ?? []));
        $correcta  = isset($_POST['correcta']) ? (int)$_POST['correcta'] : -1;

        if ($enunciado === '') salir('flash_error', 'Escribe el enunciado de la pregunta.', $volver);
        $validas = array_filter($opciones, fn($o) => $o !== '');
        if (count($validas) < 2) salir('flash_error', 'La pregunta necesita al menos 2 opciones.', $volver);
        if (!isset($opciones[$correcta]) || $opciones[$correcta] === '')
            salir('flash_error', 'Marca como correcta una opción que tenga texto.', $volver);

        $pdo->beginTransaction();
        $pdo->prepare("INSERT INTO preguntas (evaluacion_id, enunciado) VALUES (:e, :en)")
            ->execute([':e'=>$evalId, ':en'=>$enunciado]);
        $pregId = (int)$pdo->lastInsertId();
        $ins = $pdo->prepare("INSERT INTO opciones (pregunta_id, texto, correcta) VALUES (:p, :t, :c)");
        foreach ($opciones as $i => $txt) {
            if ($txt === '') continue;
            $ins->execute([':p'=>$pregId, ':t'=>$txt, ':c'=>($i === $correcta ? 1 : 0)]);
        }
        $pdo->commit();
        salir('flash_ok', 'Pregunta agregada al banco.', $volver);
    }

    if ($accion === 'eliminar') {
        $pregId = (int)($_POST['pregunta_id'] ?? 0);
        // Si ya fue respondida en alguna evaluación, se conserva para no romper el historial
        $st = $pdo->prepare("SELECT COUNT(*) c FROM respuestas WHERE pregunta_id = :p");
        $st->execute([':p' => $pregId]);
        if ((int)$st->fetch()['c'] > 0)
            salir('flash_error', 'No se puede eliminar: la pregunta ya forma parte de evaluaciones rendidas.', $volver);

        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM opciones WHERE pregunta_id = :p")->execute([':p'=>$pregId]);
        $pdo->prepare("DELETE FROM preguntas WHERE id = :p2 AND evaluacion_id = :e")->execute([':p2'=>$pregId, ':e'=>$evalId]);
        $pdo->commit();
        salir('flash_ok', 'Pregunta eliminada.', $volver);
    }

    salir('flash_error', 'Acción no válida.', $volver);

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    salir('flash_error', 'No se pudo completar la operación. Intenta nuevamente.', $volver);
}
