<?php
/* ============================================================
   SSR - Portal público | Postular a una vacante
   Crea el postulante, la postulación y el código de seguimiento.
   Al terminar redirige al seguimiento con el código.
   ============================================================ */
require_once __DIR__ . '/datos_empleos.php';
require_once __DIR__ . '/../includes/evaluar_cv.php';
require_once __DIR__ . '/../includes/passwords.php';

$errores = [];
$id      = (int)($_REQUEST['id'] ?? 0);
$e       = obtenerEmpleo($id);
if (!$e) { header('Location: index.php'); exit; }

/* ---------------- Registro ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $correo    = trim($_POST['correo'] ?? '');
    $clave     = (string)($_POST['clave'] ?? '');
    $clave2    = (string)($_POST['clave2'] ?? '');
    $dni       = trim($_POST['dni'] ?? '');
    $nombres   = trim($_POST['nombres'] ?? '');
    $telefono  = trim($_POST['telefono'] ?? '');
    $distrito  = trim($_POST['distrito'] ?? '');

    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) $errores[] = 'Ingresa un correo electrónico válido.';
    if (($errClave = validarClave($clave)) !== null)  $errores[] = $errClave;
    if ($clave !== $clave2)                          $errores[] = 'Las contraseñas no coinciden.';
    if (!preg_match('/^[0-9]{8,15}$/', $dni))        $errores[] = 'El DNI debe tener solo números (8 a 15 dígitos).';
    if ($nombres === '')                             $errores[] = 'Ingresa tus nombres completos.';

    $rutaCV = null; $rutaCVabs = null;
    if (isset($_FILES['cv']) && $_FILES['cv']['error'] !== UPLOAD_ERR_OK && $_FILES['cv']['error'] !== UPLOAD_ERR_NO_FILE) {
        $errores[] = 'No se pudo cargar el CV. Intenta nuevamente.';
    }
    if (isset($_FILES['cv']) && $_FILES['cv']['error'] === UPLOAD_ERR_OK) {
        $permitidos = ['pdf','doc','docx'];
        $ext = strtolower(pathinfo($_FILES['cv']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $permitidos))          $errores[] = 'El CV debe ser PDF, DOC o DOCX.';
        elseif ($_FILES['cv']['size'] > 5*1024*1024) $errores[] = 'El CV no debe superar los 5 MB.';
    }

    if (!$errores) {
        try {
            $pdo = obtenerConexion();

            // Filtro SARA: lista negra
            $bl = $pdo->prepare("SELECT motivo FROM blacklist WHERE dni = :dni LIMIT 1");
            $bl->execute([':dni' => $dni]);
            if ($fila = $bl->fetch()) {
                $motivo = $fila['motivo'] ? " (Motivo: {$fila['motivo']})" : '';
                $errores[] = "Tu DNI está observado y no puede continuar en este proceso$motivo.";
            }

            // ¿Existe el postulante? (por DNI o por correo)
            $q = $pdo->prepare("SELECT id FROM postulantes WHERE dni = :dni OR correo = :c LIMIT 1");
            $q->execute([':dni' => $dni, ':c' => $correo]);
            $existe = $q->fetch();

            if ($existe) {
                $postulanteId = (int)$existe['id'];
                $pdo->prepare("UPDATE postulantes SET nombres=:n, correo=:c, telefono=:t, distrito=:d, clave=:k WHERE id=:id")
                    ->execute([':n'=>$nombres, ':c'=>$correo, ':t'=>($telefono?:null), ':d'=>($distrito?:null),
                               ':k'=>hashClave($clave), ':id'=>$postulanteId]);
            } else {
                $pdo->prepare("INSERT INTO postulantes (dni, nombres, correo, telefono, distrito, clave)
                               VALUES (:dni, :n, :c, :t, :d, :k)")
                    ->execute([':dni'=>$dni, ':n'=>$nombres, ':c'=>$correo, ':t'=>($telefono?:null),
                               ':d'=>($distrito?:null), ':k'=>hashClave($clave)]);
                $postulanteId = (int)$pdo->lastInsertId();
            }

            // CV
            if (isset($_FILES['cv']) && $_FILES['cv']['error'] === UPLOAD_ERR_OK && empty($errores)) {
                $dir = __DIR__ . '/../uploads/cv';
                if (!is_dir($dir)) @mkdir($dir, 0775, true);
                $ext = strtolower(pathinfo($_FILES['cv']['name'], PATHINFO_EXTENSION));
                $nombre = 'cv_' . $dni . '_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['cv']['tmp_name'], $dir . '/' . $nombre)) {
                    $rutaCV = 'uploads/cv/' . $nombre;
                    $rutaCVabs = $dir . '/' . $nombre;
                    $pdo->prepare("UPDATE postulantes SET cv_ruta = :r WHERE id = :id")
                        ->execute([':r'=>$rutaCV, ':id'=>$postulanteId]);
                }
            }

            // Filtro SARA: activo en otra campaña
            $act = $pdo->prepare(
                "SELECT c.puesto FROM postulaciones p
                  INNER JOIN campanas c ON c.id = p.campana_id
                 WHERE p.postulante_id = :pid AND p.estado = 'En Proceso' AND p.campana_id <> :camp LIMIT 1");
            $act->execute([':pid'=>$postulanteId, ':camp'=>$e['id']]);
            if ($otra = $act->fetch()) {
                $errores[] = "Ya tienes una postulación activa en otra vacante: {$otra['puesto']}.";
            }

            // Duplicado en esta vacante
            if (!$errores) {
                $dup = $pdo->prepare("SELECT id FROM postulaciones WHERE postulante_id=:pid AND campana_id=:camp LIMIT 1");
                $dup->execute([':pid'=>$postulanteId, ':camp'=>$e['id']]);
                if ($dup->fetch()) $errores[] = 'Ya postulaste a esta vacante. Consulta el estado con tu código de seguimiento.';
            }

            if (!$errores) {
                $et = $pdo->query("SELECT id FROM etapas ORDER BY orden ASC LIMIT 1")->fetch();
                $etapaId  = (int)$et['id'];
                $codigo    = 'A365-' . strtoupper(substr(md5(uniqid((string)$postulanteId, true)), 0, 5));

                $pdo->prepare(
                    "INSERT INTO postulaciones
                        (postulante_id, campana_id, etapa_id, reclutador_id, origen_id, codigo_seguimiento, estado)
                     VALUES (:pid, :camp, :et, NULL, :ori, :cod, 'En Proceso')")
                    ->execute([':pid'=>$postulanteId, ':camp'=>$e['id'], ':et'=>$etapaId,
                               ':ori'=>1, ':cod'=>$codigo]);
                $postulacionId = (int)$pdo->lastInsertId();

                $pdo->prepare(
                    "INSERT INTO historial_etapas (postulacion_id, etapa_origen, etapa_destino, usuario_id)
                     VALUES (:post, NULL, :et, NULL)")
                    ->execute([':post'=>$postulacionId, ':et'=>$etapaId]);

                // Evaluación automática del CV (HU-05)
                if ($rutaCVabs) {
                    $areaId = (int)$pdo->query("SELECT area_id FROM campanas WHERE id=" . (int)$e['id'])->fetch()['area_id'];
                    $eval = evaluarCV(extraerTextoCV($rutaCVabs), $areaId, $pdo);
                    if ($eval['puntaje'] !== null) {
                        $detalle = implode(', ', array_slice($eval['coincidencias'], 0, 10));
                        $pdo->prepare("UPDATE postulaciones SET puntaje_cv = :p, cv_detalle = :d WHERE id = :id")
                            ->execute([':p'=>$eval['puntaje'], ':d'=>($detalle ?: null), ':id'=>$postulacionId]);
                    }
                }

                header('Location: seguimiento.php?codigo=' . urlencode($codigo) . '&nuevo=1');
                exit;
            }
        } catch (PDOException $ex) {
            $errores[] = 'No se pudo completar la postulación. Intenta nuevamente en unos minutos.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Postular · A365</title>
  <link rel="stylesheet" href="assets/portal.css">
</head>
<body>

<header class="nav">
  <div class="contenedor nav-in">
    <a href="index.php" class="nav-logo"><img src="../assets/img/logo_a365.png" alt="A365"></a>
    <nav class="nav-links">
      <a href="index.php">Bolsa de Trabajo</a>
      <a href="#">Quiénes somos</a>
      <a href="#">Nuestra experiencia</a>
    </nav>
  </div>
</header>

<section class="seccion">
  <div class="contenedor">
    <a href="trabajo.php?id=<?= (int)$e['id'] ?>" class="volver">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
      Volver al detalle del puesto
    </a>

    <div class="auth-wrap">
      <div class="auth-card">
        <div class="lg"><img src="../assets/img/logo_a365.png" alt="A365"></div>
        <h1>Postula a <?= htmlspecialchars($e['titulo']) ?></h1>
        <p class="sub">Completa tus datos. Al finalizar recibirás tu <strong>código de seguimiento</strong>.</p>

        <div class="puesto"><?= htmlspecialchars($e['area']) ?> · <?= htmlspecialchars($e['ubicacion']) ?> · <?= htmlspecialchars($e['modalidad']) ?></div>

        <?php foreach ($errores as $err): ?>
          <div class="aviso-error-portal">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <span><?= htmlspecialchars($err) ?></span>
          </div>
        <?php endforeach; ?>

        <form action="postular.php?id=<?= (int)$e['id'] ?>" method="POST" enctype="multipart/form-data">
          <div class="campo-a">
            <label for="nombres">Nombres y apellidos *</label>
            <input type="text" id="nombres" name="nombres" required maxlength="120" value="<?= htmlspecialchars($_POST['nombres'] ?? '') ?>" placeholder="Ej. Juan Pérez García">
          </div>
          <div class="campo-a">
            <label for="dni">DNI / documento *</label>
            <input type="text" id="dni" name="dni" required maxlength="15" pattern="[0-9]{8,15}" value="<?= htmlspecialchars($_POST['dni'] ?? '') ?>" placeholder="Ej. 71234567" title="Solo números, 8 a 15 dígitos">
          </div>
          <div class="campo-a">
            <label for="correo">Correo electrónico *</label>
            <input type="email" id="correo" name="correo" required value="<?= htmlspecialchars($_POST['correo'] ?? '') ?>" placeholder="tucorreo@ejemplo.com">
          </div>
          <div class="campo-a">
            <label for="telefono">Teléfono</label>
            <input type="text" id="telefono" name="telefono" maxlength="20" value="<?= htmlspecialchars($_POST['telefono'] ?? '') ?>" placeholder="Ej. 987654321">
          </div>
          <div class="campo-a">
            <label for="distrito">Distrito / ciudad</label>
            <input type="text" id="distrito" name="distrito" maxlength="80" value="<?= htmlspecialchars($_POST['distrito'] ?? '') ?>" placeholder="Ej. Magdalena">
          </div>
          <div class="campo-a">
            <label for="clave">Crea una contraseña *</label>
            <input type="password" id="clave" name="clave" required minlength="6"
                   pattern="(?=.*[A-Za-z])(?=.*[0-9]).{6,}"
                   title="Mínimo 6 caracteres, con al menos una letra y un número"
                   placeholder="Mínimo 6 caracteres, con letra y número">
            <div class="ayuda">Mínimo 6 caracteres, con al menos una letra y un número.</div>
            <div class="ayuda">La usarás para consultar tu postulación.</div>
          </div>
          <div class="campo-a">
            <label for="clave2">Repite la contraseña *</label>
            <input type="password" id="clave2" name="clave2" required minlength="6" placeholder="Repite tu contraseña">
          </div>
          <div class="campo-a">
            <label for="cv">Currículum (PDF, DOC o DOCX · máx. 5 MB)</label>
            <input type="file" id="cv" name="cv" accept=".pdf,.doc,.docx">
            <div class="ayuda">Opcional: acelera la revisión de tu perfil.</div>
          </div>
          <button type="submit" class="btn btn-rojo btn-lg">
            Enviar postulación
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
          </button>
        </form>

        <div class="auth-pie">¿Ya postulaste? <a href="seguimiento.php">Consulta tu postulación</a></div>
      </div>
    </div>
  </div>
</section>

<footer class="footer">
  <div class="contenedor footer-copy" style="border:none">© <?= date('Y') ?> A365 · Portal de empleo</div>
</footer>

</body>
</html>
