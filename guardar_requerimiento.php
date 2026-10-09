<?php
/* ============================================================
   SSR - A365 | Gestión de requerimientos (HU-03 / RF-03)
   acciones: crear | editar | publicar | estado | eliminar
   ============================================================ */
require_once __DIR__ . '/includes/guardia.php';
require_once __DIR__ . '/config/conexion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: requerimientos.php'); exit; }

if (!in_array(($_SESSION['usuario_rol'] ?? ''), ['Administrador','Supervisor'])) {
    $_SESSION['flash_error'] = 'No tienes permisos para gestionar requerimientos.';
    header('Location: requerimientos.php'); exit;
}

$accion  = $_POST['accion'] ?? 'crear';
$campId  = (int)($_POST['campana_id'] ?? 0);

try {
    $pdo = obtenerConexion();

    /* ---- Publicar / despublicar ---- */
    if ($accion === 'publicar') {
        $valor = (int)($_POST['valor'] ?? 0) === 1 ? 1 : 0;
        $st = $pdo->prepare("SELECT codigo FROM campanas WHERE id = :id");
        $st->execute([':id' => $campId]);
        $c = $st->fetch();

        if (!$c) {
            $_SESSION['flash_error'] = 'Requerimiento no encontrado.';
        } else {
            $pdo->prepare("UPDATE campanas SET publicado = :p WHERE id = :id")
                ->execute([':p' => $valor, ':id' => $campId]);
            $_SESSION['flash_ok'] = $valor
                ? "El requerimiento {$c['codigo']} está publicado en el portal."
                : "El requerimiento {$c['codigo']} se ocultó del portal.";
        }
        header('Location: requerimientos.php'); exit;
    }

    /* ---- Cerrar / reabrir ---- */
    if ($accion === 'estado') {
        $estado = in_array($_POST['valor'] ?? '', ['Abierto','En Proceso','Cerrado'], true)
            ? $_POST['valor'] : 'Cerrado';
        $st = $pdo->prepare("SELECT codigo FROM campanas WHERE id = :id");
        $st->execute([':id' => $campId]);
        $c = $st->fetch();

        if (!$c) {
            $_SESSION['flash_error'] = 'Requerimiento no encontrado.';
        } else {
            $pdo->prepare("UPDATE campanas SET estado = :e WHERE id = :id")
                ->execute([':e' => $estado, ':id' => $campId]);
            $_SESSION['flash_ok'] = "El requerimiento {$c['codigo']} ahora está: $estado.";
        }
        header('Location: requerimientos.php'); exit;
    }

    /* ---- Eliminar ---- */
    if ($accion === 'eliminar') {
        $st = $pdo->prepare("SELECT codigo FROM campanas WHERE id = :id");
        $st->execute([':id' => $campId]);
        $c = $st->fetch();

        if (!$c) {
            $_SESSION['flash_error'] = 'Requerimiento no encontrado.';
        } else {
            try {
                $pdo->prepare("DELETE FROM campanas WHERE id = :id")
                    ->execute([':id' => $campId]);
                $_SESSION['flash_ok'] = "Requerimiento {$c['codigo']} eliminado.";
            } catch (PDOException $e) {
                $_SESSION['flash_error'] =
                    "No se pudo eliminar {$c['codigo']} porque tiene postulaciones asociadas. Ciérralo en su lugar.";
            }
        }
        header('Location: requerimientos.php'); exit;
    }

    /* ---- Datos del formulario (crear / editar) ---- */
    $puesto     = trim($_POST['puesto'] ?? '');
    $descripcion= trim($_POST['descripcion'] ?? '');
    $areaId     = (int)($_POST['area_id'] ?? 0);
    $vacantes   = (int)($_POST['vacantes'] ?? 0);
    $tipo       = $_POST['tipo'] ?? 'Nueva Posición';
    $prioridad  = $_POST['prioridad'] ?? 'Media';
    $ubicacion  = trim($_POST['ubicacion'] ?? '');
    $horario    = trim($_POST['horario'] ?? '');
    $modalidad  = $_POST['modalidad'] ?? 'Presencial';
    $tareas     = trim($_POST['tareas'] ?? '');
    $requisitos = trim($_POST['requisitos'] ?? '');
    $beneficios = trim($_POST['beneficios'] ?? '');
    $remun      = ($_POST['remuneracion'] ?? '') !== '' ? (float)$_POST['remuneracion'] : null;
    $fechaIng   = ($_POST['fecha_ingreso'] ?? '') !== '' ? $_POST['fecha_ingreso'] : null;
    $publicado  = isset($_POST['publicado']) ? 1 : 0;

    if ($puesto === '' || $areaId === 0 || $vacantes < 1) {
        $_SESSION['flash_error'] = 'Completa el cargo, el área y al menos 1 vacante.';
        header('Location: requerimientos.php'); exit;
    }
    if (!in_array($tipo, ['Nueva Posición','Reemplazo'])) $tipo = 'Nueva Posición';
    if (!in_array($prioridad, ['Alta','Media','Baja'])) $prioridad = 'Media';
    if (!in_array($modalidad, ['Presencial','Híbrido','Remoto'])) $modalidad = 'Presencial';

    $params = [
        ':area'=>$areaId, ':puesto'=>$puesto, ':desc'=>($descripcion?:null),
        ':tar'=>($tareas?:null), ':req'=>($requisitos?:null), ':ben'=>($beneficios?:null),
        ':vac'=>$vacantes, ':ubi'=>($ubicacion?:null), ':mod'=>$modalidad,
        ':hor'=>($horario?:null), ':rem'=>$remun, ':fing'=>$fechaIng,
        ':prio'=>$prioridad, ':tipo'=>$tipo, ':pub'=>$publicado,
    ];

    /* ---- Editar ---- */
    if ($accion === 'editar') {
        if ($campId <= 0) {
            $_SESSION['flash_error'] = 'Requerimiento no encontrado.';
            header('Location: requerimientos.php'); exit;
        }

        $sql = "UPDATE campanas SET
                    area_id=:area, puesto=:puesto, descripcion=:desc,
                    tareas=:tar, requisitos=:req, beneficios=:ben,
                    vacantes=:vac, ubicacion=:ubi, modalidad=:mod, horario=:hor,
                    remuneracion=:rem, fecha_ingreso=:fing,
                    prioridad=:prio, tipo=:tipo, publicado=:pub
                WHERE id=:id";
        $params[':id'] = $campId;
        $pdo->prepare($sql)->execute($params);

        $_SESSION['flash_ok'] = 'Requerimiento actualizado correctamente.';
        header('Location: requerimientos.php'); exit;
    }

    /* ---- Crear ---- */
    $anio = date('Y');
    $stmt = $pdo->prepare("SELECT COUNT(*) AS n FROM campanas WHERE codigo LIKE :pref");
    $stmt->execute([':pref' => "REQ-$anio-%"]);
    $n = (int)$stmt->fetch()['n'] + 1;
    $codigo = sprintf('REQ-%s-%03d', $anio, $n);

    $sql = "INSERT INTO campanas
              (codigo, area_id, puesto, descripcion, tareas, requisitos, beneficios,
               vacantes, ubicacion, modalidad, horario,
               remuneracion, fecha_ingreso, prioridad, tipo, estado, publicado, fecha_sol)
            VALUES
              (:cod, :area, :puesto, :desc, :tar, :req, :ben,
               :vac, :ubi, :mod, :hor,
               :rem, :fing, :prio, :tipo, 'Abierto', :pub, CURDATE())";
    $params[':cod'] = $codigo;
    $pdo->prepare($sql)->execute($params);

    $_SESSION['flash_ok'] = "Requerimiento $codigo registrado correctamente.";
    header('Location: requerimientos.php'); exit;

} catch (PDOException $e) {
    $_SESSION['flash_error'] = 'No se pudo guardar el requerimiento. Intenta nuevamente.';
    header('Location: requerimientos.php'); exit;
}
