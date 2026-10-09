<?php
/* ============================================================
   SSR - A365 | Registrar postulante por DNI  (núcleo tipo SARA)
   Flujo real:
     1) Valida DNI
     2) Consulta blacklist                (filtro SARA)
     3) ¿Postulante ya existe? -> reutiliza; si no, lo crea
     4) ¿Ya activo en OTRA campaña?       (filtro SARA)
     5) ¿Ya postuló a ESTA campaña?       (duplicado)
     6) Crea la postulación en etapa "Postulación" + código de
        seguimiento + primer registro en historial_etapas.
   ============================================================ */

require_once __DIR__ . '/includes/guardia.php';
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/includes/evaluar_cv.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: candidatos.php');
    exit;
}

$dni      = trim($_POST['dni'] ?? '');
$nombres  = trim($_POST['nombres'] ?? '');
$correo   = trim($_POST['correo'] ?? '');
$telefono = trim($_POST['telefono'] ?? '');
$distrito = trim($_POST['distrito'] ?? '');
$campanaId= (int)($_POST['campana_id'] ?? 0);
$origenId = ($_POST['origen_id'] ?? '') !== '' ? (int)$_POST['origen_id'] : null;
$usuarioId= $_SESSION['usuario_id'] ?? null;

// --- Validaciones básicas ---
if ($dni === '' || $nombres === '' || $campanaId === 0) {
    $_SESSION['flash_error'] = 'Completa DNI, nombres y campaña.';
    header('Location: candidatos.php'); exit;
}
if (!preg_match('/^[0-9]{8,15}$/', $dni)) {
    $_SESSION['flash_error'] = 'El DNI debe tener solo números (8 a 15 dígitos).';
    header('Location: candidatos.php'); exit;
}

try {
    $pdo = obtenerConexion();

    // (2) BLACKLIST
    $bl = $pdo->prepare("SELECT motivo FROM blacklist WHERE dni = :dni LIMIT 1");
    $bl->execute([':dni' => $dni]);
    if ($fila = $bl->fetch()) {
        $m = $fila['motivo'] ? " (Motivo: {$fila['motivo']})" : '';
        $_SESSION['flash_error'] = "El DNI $dni está en lista negra y no puede continuar$m.";
        header('Location: candidatos.php'); exit;
    }

    // (3) ¿Existe el postulante?
    $q = $pdo->prepare("SELECT id FROM postulantes WHERE dni = :dni LIMIT 1");
    $q->execute([':dni' => $dni]);
    $postulante = $q->fetch();

    if ($postulante) {
        $postulanteId = (int)$postulante['id'];
        // Actualiza datos de contacto por si cambiaron
        $pdo->prepare("UPDATE postulantes SET nombres=:n, correo=:c, telefono=:t, distrito=:d WHERE id=:id")
            ->execute([':n'=>$nombres, ':c'=>($correo?:null), ':t'=>($telefono?:null), ':d'=>($distrito?:null), ':id'=>$postulanteId]);
    } else {
        $ins = $pdo->prepare("INSERT INTO postulantes (dni, nombres, correo, telefono, distrito)
                              VALUES (:dni, :n, :c, :t, :d)");
        $ins->execute([':dni'=>$dni, ':n'=>$nombres, ':c'=>($correo?:null), ':t'=>($telefono?:null), ':d'=>($distrito?:null)]);
        $postulanteId = (int)$pdo->lastInsertId();
    }

    // (3b) Carga del CV (HU-04): validar tipo/tamaño y guardarlo
    $rutaCVabs = null;   // ruta absoluta para evaluar el CV luego
    if (isset($_FILES['cv']) && $_FILES['cv']['error'] === UPLOAD_ERR_OK) {
        $permitidos = ['pdf','doc','docx'];
        $ext = strtolower(pathinfo($_FILES['cv']['name'], PATHINFO_EXTENSION));
        $tam = $_FILES['cv']['size'];
        if (!in_array($ext, $permitidos)) {
            $_SESSION['flash_error'] = 'El CV debe ser PDF, DOC o DOCX.';
            header('Location: candidatos.php'); exit;
        }
        if ($tam > 5 * 1024 * 1024) { // 5 MB máx
            $_SESSION['flash_error'] = 'El CV no debe superar los 5 MB.';
            header('Location: candidatos.php'); exit;
        }
        $dir = __DIR__ . '/uploads/cv';
        if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
        $nombreArchivo = 'cv_' . $dni . '_' . time() . '.' . $ext;
        $destino = $dir . '/' . $nombreArchivo;
        if (move_uploaded_file($_FILES['cv']['tmp_name'], $destino)) {
            $rutaCV = 'uploads/cv/' . $nombreArchivo;
            $rutaCVabs = $destino;
            $pdo->prepare("UPDATE postulantes SET cv_ruta = :ruta WHERE id = :id")
                ->execute([':ruta'=>$rutaCV, ':id'=>$postulanteId]);
        }
    }

    // (4) ¿Activo en OTRA campaña? (postulación En Proceso en campaña distinta)
    $act = $pdo->prepare(
        "SELECT c.puesto FROM postulaciones p
         INNER JOIN campanas c ON c.id = p.campana_id
         WHERE p.postulante_id = :pid AND p.estado = 'En Proceso' AND p.campana_id <> :camp
         LIMIT 1");
    $act->execute([':pid'=>$postulanteId, ':camp'=>$campanaId]);
    if ($otra = $act->fetch()) {
        $_SESSION['flash_error'] = "Este postulante ya está activo en otra campaña: {$otra['puesto']}.";
        header('Location: candidatos.php'); exit;
    }

    // (5) ¿Ya postuló a ESTA campaña?
    $dup = $pdo->prepare("SELECT id FROM postulaciones WHERE postulante_id=:pid AND campana_id=:camp LIMIT 1");
    $dup->execute([':pid'=>$postulanteId, ':camp'=>$campanaId]);
    if ($dup->fetch()) {
        $_SESSION['flash_error'] = 'Este postulante ya está registrado en esta campaña.';
        header('Location: candidatos.php'); exit;
    }

    // Etapa inicial "Postulación" (orden = 1)
    $et = $pdo->query("SELECT id FROM etapas ORDER BY orden ASC LIMIT 1")->fetch();
    $etapaId = (int)$et['id'];

    // Código de seguimiento único
    $codigo = 'A365-' . strtoupper(substr(md5(uniqid((string)$postulanteId, true)), 0, 5));

    // (6) Crear la postulación
    $pdo->prepare(
        "INSERT INTO postulaciones
            (postulante_id, campana_id, etapa_id, reclutador_id, origen_id, codigo_seguimiento, estado)
         VALUES (:pid, :camp, :et, :rec, :ori, :cod, 'En Proceso')")
        ->execute([
            ':pid'=>$postulanteId, ':camp'=>$campanaId, ':et'=>$etapaId,
            ':rec'=>$usuarioId, ':ori'=>$origenId, ':cod'=>$codigo,
        ]);
    $postulacionId = (int)$pdo->lastInsertId();

    // Primer registro en el historial de etapas (entrada al embudo)
    $pdo->prepare(
        "INSERT INTO historial_etapas (postulacion_id, etapa_origen, etapa_destino, usuario_id)
         VALUES (:post, NULL, :et, :usr)")
        ->execute([':post'=>$postulacionId, ':et'=>$etapaId, ':usr'=>$usuarioId]);

    // (7) Evaluación automática del CV por palabras clave (HU-05)
    $msgCV = '';
    if ($rutaCVabs) {
        // Área de la campaña
        $areaStmt = $pdo->prepare("SELECT area_id FROM campanas WHERE id = :c LIMIT 1");
        $areaStmt->execute([':c'=>$campanaId]);
        $areaId = (int)($areaStmt->fetch()['area_id'] ?? 0);

        $texto = extraerTextoCV($rutaCVabs);
        $eval  = evaluarCV($texto, $areaId, $pdo);

        if ($eval['puntaje'] !== null) {
            $detalle = implode(', ', array_slice($eval['coincidencias'], 0, 10));
            $pdo->prepare("UPDATE postulaciones SET puntaje_cv = :p, cv_detalle = :d WHERE id = :id")
                ->execute([':p'=>$eval['puntaje'], ':d'=>($detalle ?: null), ':id'=>$postulacionId]);
            $msgCV = " Puntaje de CV: {$eval['puntaje']}%.";
        }
    }

    $_SESSION['flash_ok'] = "Candidato registrado. Código de seguimiento: $codigo.$msgCV";
    header('Location: candidatos.php'); exit;

} catch (PDOException $e) {
    $_SESSION['flash_error'] = 'No se pudo registrar el candidato. Intenta nuevamente.';
    header('Location: candidatos.php'); exit;
}
