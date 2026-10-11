<?php
/* ============================================================
   SSR - Guardar entrevista (crear, editar o cambiar estado)
   RF-04: programación y seguimiento de entrevistas.
   ============================================================ */
require_once __DIR__ . '/includes/guardia.php';
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/includes/alcance.php';
require_once __DIR__ . '/includes/flujo.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: entrevistas.php');
    exit;
}

$pdo    = obtenerConexion();
$estados = ['Programada','En Curso','Completada','Cancelada'];
$tipos   = ['Entrevista RH','Entrevista Técnica','Entrevista Cliente'];
$id      = (int)($_POST['id'] ?? 0);

try {
    /* ---------- Cambio rápido de estado (botones de la tabla) ---------- */
    if (($_POST['accion'] ?? '') === 'estado') {
        $estado = $_POST['estado'] ?? '';
        if (!$id || !in_array($estado, $estados, true)) {
            throw new Exception('Datos inválidos para actualizar el estado.');
        }
        $existe = $pdo->prepare("SELECT id, postulacion_id FROM entrevistas WHERE id = :id");
        $existe->execute([':id' => $id]);
        $entExiste = $existe->fetch();
        if (!$entExiste) throw new Exception('La entrevista ya no existe.');
        if (!puedeGestionarPostulacion($pdo, (int)$entExiste['postulacion_id'])) {
            throw new Exception('Solo puedes gestionar entrevistas de candidatos de tu área.');
        }

        $pdo->prepare("UPDATE entrevistas SET estado = :e WHERE id = :id")
            ->execute([':e' => $estado, ':id' => $id]);

        $_SESSION['flash_ok'] = "Entrevista marcada como $estado.";
        header('Location: entrevistas.php');
        exit;
    }

    /* ---------- Crear / editar desde el modal ---------- */
    $postulacionId   = (int)($_POST['postulacion_id'] ?? 0);
    $entrevistadorId = (int)($_POST['entrevistador_id'] ?? 0);
    $rol             = trim($_POST['rol_entrevistador'] ?? '');
    $tipo            = $_POST['tipo'] ?? '';
    $estado          = $_POST['estado'] ?? 'Programada';
    $fecha           = $_POST['fecha'] ?? '';
    $hora            = $_POST['hora'] ?? '';
    $sala            = trim($_POST['sala'] ?? '');
    $obs             = trim($_POST['observaciones'] ?? '');

    $errores = [];
    if (!in_array($tipo, $tipos, true))                 $errores[] = 'Selecciona un tipo de entrevista válido.';
    if (!in_array($estado, $estados, true))             $errores[] = 'Selecciona un estado válido.';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha))   $errores[] = 'Selecciona la fecha de la entrevista.';
    if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $hora)) $errores[] = 'Selecciona la hora de la entrevista.';
    if ($sala !== '' && mb_strlen($sala) > 80)          $errores[] = 'La sala/ubicación supera los 80 caracteres.';
    if ($obs  !== '' && mb_strlen($obs) > 255)          $errores[] = 'Las observaciones superan los 255 caracteres.';

    if ($postulacionId > 0) {
        $q = $pdo->prepare("SELECT id FROM postulaciones WHERE id = :id");
        $q->execute([':id' => $postulacionId]);
        if (!$q->fetch()) $errores[] = 'La postulación seleccionada ya no existe.';
        elseif (!puedeGestionarPostulacion($pdo, $postulacionId)) $errores[] = 'Solo puedes programar entrevistas de candidatos de tu área.';
        elseif ($id === 0) {
            // Solo se agenda entrevista a quien ya pasó la validación (etapa «Entrevista presencial»)
            $pf = flujoPostulacion($pdo, $postulacionId);
            if (!$pf || (int)$pf['orden'] !== 4 || $pf['estado'] !== 'En Proceso') $errores[] = 'Solo se agenda la entrevista a candidatos que ya aprobaron la validación (etapa «Entrevista presencial»).';
        }
    } else {
        $errores[] = 'Selecciona al candidato / vacante.';
    }

    if ($entrevistadorId > 0) {
        $u = $pdo->prepare("SELECT nombre FROM usuarios WHERE id = :id AND estado = 1");
        $u->execute([':id' => $entrevistadorId]);
        $usr = $u->fetch();
        if (!$usr) $errores[] = 'El entrevistador seleccionado no existe o está inactivo.';
        else $nombreEntrevistador = $usr['nombre'];
    } else {
        $errores[] = 'Selecciona al entrevistador.';
        $nombreEntrevistador = '';
    }

    if (!$errores) {
        $horaDb = strlen($hora) === 5 ? $hora . ':00' : $hora;

        if ($id > 0) {
            $q = $pdo->prepare("SELECT id, postulacion_id FROM entrevistas WHERE id = :id");
            $q->execute([':id' => $id]);
            $entEd = $q->fetch();
            if (!$entEd) $errores[] = 'La entrevista a editar ya no existe.';
            elseif (!puedeGestionarPostulacion($pdo, (int)$entEd['postulacion_id'])) $errores[] = 'Solo puedes editar entrevistas de candidatos de tu área.';
        }
    }

    if (!$errores) {
        if ($id > 0) {
            $st = $pdo->prepare(
                "UPDATE entrevistas
                    SET postulacion_id = :post, entrevistador_id = :uid, entrevistador = :ent,
                        rol_entrevistador = :rol, tipo = :tipo, fecha = :f, hora = :h,
                        sala = :sala, estado = :est, observaciones = :obs
                  WHERE id = :id");
            $st->execute([
                ':post'=>$postulacionId, ':uid'=>$entrevistadorId, ':ent'=>$nombreEntrevistador,
                ':rol'=>($rol ?: null), ':tipo'=>$tipo, ':f'=>$fecha, ':h'=>$horaDb,
                ':sala'=>($sala ?: null), ':est'=>$estado, ':obs'=>($obs ?: null), ':id'=>$id,
            ]);
            $_SESSION['flash_ok'] = 'Entrevista actualizada correctamente.';
        } else {
            $st = $pdo->prepare(
                "INSERT INTO entrevistas
                    (postulacion_id, entrevistador_id, entrevistador, rol_entrevistador,
                     tipo, fecha, hora, sala, estado, observaciones)
                 VALUES (:post, :uid, :ent, :rol, :tipo, :f, :h, :sala, :est, :obs)");
            $st->execute([
                ':post'=>$postulacionId, ':uid'=>$entrevistadorId, ':ent'=>$nombreEntrevistador,
                ':rol'=>($rol ?: null), ':tipo'=>$tipo, ':f'=>$fecha, ':h'=>$horaDb,
                ':sala'=>($sala ?: null), ':est'=>$estado, ':obs'=>($obs ?: null),
            ]);
            $_SESSION['flash_ok'] = "Entrevista programada para el $fecha.";
        }
        header('Location: entrevistas.php');
        exit;
    }

    $_SESSION['flash_error'] = $errores[0];
} catch (PDOException $e) {
    $_SESSION['flash_error'] = 'No se pudo guardar la entrevista. Intenta nuevamente.';
} catch (Exception $e) {
    $_SESSION['flash_error'] = $e->getMessage();
}

header('Location: entrevistas.php');
exit;
