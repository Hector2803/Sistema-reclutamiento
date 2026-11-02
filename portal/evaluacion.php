<?php
/* ============================================================
   SSR - Portal público | Evaluación del postulante (HU-08 / RF-09)
   El test lo rinde el POSTULANTE, fuera del panel interno:
     1) Se identifica con su código de seguimiento + DNI.
     2) Responde las preguntas (al azar del banco del área) con cronómetro.
     3) El servidor califica; las respuestas correctas nunca viajan al navegador.
     4) Aprobado -> etapa "Evaluación (test)" · Desaprobado -> Descartado
        con el motivo del puntaje mínimo. Todo queda en historial_etapas.
   El panel interno solo MUESTRA el resultado (solo lectura).
   ============================================================ */
require_once __DIR__ . '/datos_empleos.php';
require_once __DIR__ . '/../includes/flujo.php';

/* Sesión propia del portal (independiente de la del personal) */
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('ssr_portal');
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax',
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    ]);
    session_start();
}

$pdo    = obtenerConexion();
$codigo = strtoupper(trim($_REQUEST['codigo'] ?? ''));
$error  = '';
$aviso  = '';
$post   = null;

function volverAqui(string $codigo): void { header('Location: evaluacion.php?codigo=' . urlencode($codigo)); exit; }

/* Datos de la postulación (si el código es válido) */
function cargarPostulacion(PDO $pdo, string $codigo) {
    if (!preg_match('/^A365-[A-Z0-9]{1,10}$/', $codigo)) return null;
    $st = $pdo->prepare(
        "SELECT p.id, p.estado, p.etapa_id, po.nombres, po.dni, c.puesto, c.area_id, a.nombre AS area, e.orden AS orden_actual
           FROM postulaciones p
           INNER JOIN postulantes po ON po.id = p.postulante_id
           INNER JOIN campanas   c  ON c.id  = p.campana_id
           INNER JOIN areas      a  ON a.id  = c.area_id
           INNER JOIN etapas     e  ON e.id  = p.etapa_id
          WHERE p.codigo_seguimiento = :c LIMIT 1");
    $st->execute([':c' => $codigo]);
    return $st->fetch() ?: null;
}

$accion = $_POST['accion'] ?? '';

/* ---------- 1) Identificación: código + DNI ---------- */
if ($accion === 'entrar') {
    $dni = trim($_POST['dni'] ?? '');
    $_SESSION['pev_fallos'] = (int)($_SESSION['pev_fallos'] ?? 0);
    if ($_SESSION['pev_fallos'] >= 5) {
        $error = 'Demasiados intentos fallidos. Cierra el navegador y vuelve a intentarlo más tarde.';
    } else {
        $p = cargarPostulacion($pdo, $codigo);
        if ($p && hash_equals((string)$p['dni'], $dni)) {
            $_SESSION['pev_ok'][$p['id']] = true;
            $_SESSION['pev_fallos'] = 0;
            volverAqui($codigo);
        }
        $_SESSION['pev_fallos']++;
        usleep(400000);
        $error = 'El código o el DNI no coinciden. Verifica tus datos.';
    }
}

$post = cargarPostulacion($pdo, $codigo);
$autenticado = $post && !empty($_SESSION['pev_ok'][$post['id']]);

/* Evaluación configurada para el área de la vacante */
$eval = null; $resultado = null;
if ($autenticado) {
    $st = $pdo->prepare("SELECT * FROM evaluaciones WHERE area_id = :a AND estado = 1 ORDER BY id LIMIT 1");
    $st->execute([':a' => $post['area_id']]);
    $eval = $st->fetch() ?: null;
    if ($eval) {
        $st = $pdo->prepare("SELECT * FROM resultados_eval WHERE postulacion_id = :p AND evaluacion_id = :e ORDER BY id DESC LIMIT 1");
        $st->execute([':p' => $post['id'], ':e' => $eval['id']]);
        $resultado = $st->fetch() ?: null;
    }
}

/* ---------- 2) Calificación (en el servidor) ---------- */
if ($accion === 'calificar' && $autenticado && $eval && !$resultado) {
    $clave = 'examen_' . $post['id'];
    $ex    = $_SESSION[$clave] ?? null;
    if (!$ex || empty($ex['preguntas']) || (int)$ex['eval'] !== (int)$eval['id']) {
        $_SESSION['pev_msg'] = 'La sesión de la evaluación expiró. Vuelve a iniciarla.';
        volverAqui($codigo);
    }
    if ($post['estado'] !== 'En Proceso' || (int)$post['orden_actual'] !== 2) {
        unset($_SESSION[$clave]);
        $_SESSION['pev_msg'] = 'Tu postulación ya no está en proceso.';
        volverAqui($codigo);
    }
    try {
        $ids = array_map('intval', $ex['preguntas']);
        $marcas = implode(',', array_fill(0, count($ids), '?'));
        $st = $pdo->prepare("SELECT id, pregunta_id, correcta FROM opciones WHERE pregunta_id IN ($marcas)");
        $st->execute($ids);
        $opcionesDe = []; $correctaDe = [];
        foreach ($st->fetchAll() as $o) {
            $opcionesDe[(int)$o['pregunta_id']][] = (int)$o['id'];
            if ((int)$o['correcta'] === 1) $correctaDe[(int)$o['pregunta_id']] = (int)$o['id'];
        }

        $enviadas = (array)($_POST['respuestas'] ?? []);
        $aciertos = 0; $filas = [];
        foreach ($ids as $pid) {
            $elegida = isset($enviadas[$pid]) ? (int)$enviadas[$pid] : null;
            if ($elegida !== null && !in_array($elegida, $opcionesDe[$pid] ?? [], true)) $elegida = null; // manipulada
            if ($elegida !== null && $elegida === ($correctaDe[$pid] ?? -1)) $aciertos++;
            $filas[] = [$pid, $elegida];
        }
        $puntaje  = (int)round($aciertos * 100 / count($ids));
        $aprobado = $puntaje >= (int)$eval['nota_minima'] ? 1 : 0;
        $fueraTiempo = (time() - (int)$ex['inicio']) > ((int)$eval['tiempo_limite'] * 60 + 60);

        // Aprobado -> pasa a «Validación (datos, CV y documentos)»; desaprobado -> queda descartado en la evaluación
        $etVal = flujoEtapa($pdo, 3);
        $etapaDestino = ($aprobado && $etVal) ? (int)$etVal['id'] : (int)$post['etapa_id'];

        $obs = ($aprobado ? 'Evaluación aprobada' : 'Evaluación no aprobada') . " con $puntaje% (mínimo {$eval['nota_minima']}%)"
             . ($fueraTiempo ? ' · entregada fuera de tiempo' : '') . ' · rendida por el postulante en el portal';

        $pdo->beginTransaction();
        $pdo->prepare("INSERT INTO resultados_eval (postulacion_id, evaluacion_id, puntaje, aprobado) VALUES (:p,:e,:pt,:ap)")
            ->execute([':p'=>$post['id'], ':e'=>$eval['id'], ':pt'=>$puntaje, ':ap'=>$aprobado]);
        $resId = (int)$pdo->lastInsertId();
        $insR = $pdo->prepare("INSERT INTO respuestas (resultado_id, pregunta_id, opcion_id) VALUES (:r,:q,:o)");
        foreach ($filas as [$pid, $oid]) $insR->execute([':r'=>$resId, ':q'=>$pid, ':o'=>$oid]);

        if ($aprobado) {
            $pdo->prepare("UPDATE postulaciones SET etapa_id = :et WHERE id = :id")->execute([':et'=>$etapaDestino, ':id'=>$post['id']]);
            $pdo->prepare("INSERT INTO historial_etapas (postulacion_id, etapa_origen, etapa_destino, observacion, usuario_id)
                           VALUES (:p,:o,:d,:obs,NULL)")
                ->execute([':p'=>$post['id'], ':o'=>$post['etapa_id'], ':d'=>$etapaDestino, ':obs'=>$obs]);
        } else {
            $mot = $pdo->query("SELECT id FROM motivos_descarte WHERE descripcion LIKE 'No alcanz%' LIMIT 1")->fetch();
            $pdo->prepare("UPDATE postulaciones SET etapa_id = :et, estado = 'Descartado' WHERE id = :id")
                ->execute([':et'=>$etapaDestino, ':id'=>$post['id']]);
            $pdo->prepare("INSERT INTO historial_etapas (postulacion_id, etapa_origen, etapa_destino, motivo_id, observacion, usuario_id)
                           VALUES (:p,:o,:d,:m,:obs,NULL)")
                ->execute([':p'=>$post['id'], ':o'=>$post['etapa_id'], ':d'=>$etapaDestino, ':m'=>($mot ? (int)$mot['id'] : null), ':obs'=>$obs]);
        }
        $pdo->commit();
        unset($_SESSION[$clave]);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['pev_msg'] = 'No se pudo registrar tu evaluación. Intenta nuevamente.';
    }
    volverAqui($codigo);
}

if (!empty($_SESSION['pev_msg'])) { $aviso = $_SESSION['pev_msg']; unset($_SESSION['pev_msg']); }

/* ---------- 3) Preparar el examen (se guarda en sesión: recargar no cambia las preguntas) ---------- */
$preguntas = []; $restante = 0; $bloqueo = '';
if ($autenticado && !$resultado) {
    if (!$eval) {
        $bloqueo = 'Esta vacante aún no tiene una evaluación habilitada. El equipo de Reclutamiento te contactará.';
    } elseif ($post['estado'] !== 'En Proceso') {
        $bloqueo = 'Tu postulación está en estado «' . $post['estado'] . '» y no puede rendir la evaluación.';
    } elseif ((int)$post['orden_actual'] !== 2) {
        $bloqueo = 'La evaluación no está habilitada en la etapa actual de tu postulación.';
    } else {
        $clave = 'examen_' . $post['id'];
        $ex = $_SESSION[$clave] ?? null;
        if (!$ex || (int)$ex['eval'] !== (int)$eval['id']) {
            $st = $pdo->prepare("SELECT id FROM preguntas WHERE evaluacion_id = :e ORDER BY RAND() LIMIT " . (int)$eval['total_preguntas']);
            $st->execute([':e' => $eval['id']]);
            $ids = array_map('intval', array_column($st->fetchAll(), 'id'));
            $orden = [];
            $stO = $pdo->prepare("SELECT id FROM opciones WHERE pregunta_id = :p");
            foreach ($ids as $pid) {
                $stO->execute([':p' => $pid]);
                $ops = array_map('intval', array_column($stO->fetchAll(), 'id'));
                shuffle($ops);
                $orden[$pid] = $ops;
            }
            $ex = ['eval' => (int)$eval['id'], 'preguntas' => $ids, 'opciones' => $orden, 'inicio' => time()];
            $_SESSION[$clave] = $ex;
        }
        if (!$ex['preguntas']) {
            $bloqueo = 'El banco de preguntas de esta evaluación está vacío. El equipo de Reclutamiento te contactará.';
            unset($_SESSION[$clave]);
        } else {
            $stQ = $pdo->prepare("SELECT enunciado FROM preguntas WHERE id = :id");
            $stT = $pdo->prepare("SELECT texto FROM opciones WHERE id = :id");
            foreach ($ex['preguntas'] as $pid) {
                $stQ->execute([':id' => $pid]);
                $ops = [];
                foreach ($ex['opciones'][$pid] as $oid) { $stT->execute([':id' => $oid]); $ops[] = ['id' => $oid, 'texto' => $stT->fetch()['texto']]; }
                $preguntas[] = ['id' => $pid, 'enunciado' => $stQ->fetch()['enunciado'], 'opciones' => $ops];
            }
            $restante = max(0, (int)$eval['tiempo_limite'] * 60 - (time() - (int)$ex['inicio']));
        }
    }
}
$h = fn($t) => htmlspecialchars((string)$t, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Mi evaluación · A365</title>
  <link rel="stylesheet" href="assets/portal.css">
  <style>
    .ev-wrap{max-width:760px;margin:0 auto}
    .ev-q{background:#fff;border:1px solid var(--borde,#e3e8f0);border-radius:12px;padding:16px 18px;margin-bottom:12px}
    .ev-q p{font-weight:600;margin:0 0 10px}
    .ev-opc{display:flex;gap:10px;align-items:flex-start;padding:9px 10px;border-radius:9px;cursor:pointer}
    .ev-opc:hover{background:#f0f4fb}
    .ev-opc input{margin-top:3px}
    .ev-barra{position:sticky;top:0;z-index:5;display:flex;justify-content:space-between;align-items:center;background:#fff;border:1px solid var(--borde,#e3e8f0);border-radius:12px;padding:12px 18px;margin-bottom:14px}
    .ev-reloj{font-weight:800;font-size:20px;color:#0b3d91}
    .ev-reloj.poco{color:#d92d20}
    .ev-res{text-align:center;padding:30px 20px}
    .ev-res .num{font-size:54px;font-weight:800}
    .ev-res.ok .num{color:#16a34a}.ev-res.mal .num{color:#d92d20}
  </style>
</head>
<body>

<header class="nav">
  <div class="contenedor nav-in">
    <a href="index.php" class="nav-logo"><img src="../assets/img/logo_a365_fiestas.png" alt="A365"></a>
    <nav class="nav-links">
      <a href="index.php">Bolsa de Trabajo</a>
      <a href="#">Quiénes somos</a>
      <a href="#">Nuestra experiencia</a>
    </nav>
    <div class="nav-cta"><a href="seguimiento.php" class="btn btn-linea">Consultar mi postulación</a></div>
  </div>
</header>

<section class="seccion">
  <div class="contenedor ev-wrap">

    <h1>Evaluación del postulante</h1>

    <?php if ($error): ?><div class="aviso-error-portal"><span><?= $h($error) ?></span></div><?php endif; ?>
    <?php if ($aviso): ?><div class="aviso-error-portal"><span><?= $h($aviso) ?></span></div><?php endif; ?>

    <?php if (!$autenticado): ?>
      <!-- ===== Identificación ===== -->
      <p class="intro">Para rendir tu evaluación ingresa el código de seguimiento que recibiste al postular y tu DNI.</p>
      <div class="card" style="max-width:460px">
        <form method="POST" action="evaluacion.php" autocomplete="off">
          <input type="hidden" name="accion" value="entrar">
          <div class="campo-a">
            <label for="cod">Código de seguimiento</label>
            <input type="text" id="cod" name="codigo" value="<?= $h($codigo) ?>" placeholder="Ej. A365-7F3K9" required pattern="A365-[A-Za-z0-9]{1,10}">
          </div>
          <div class="campo-a">
            <label for="dni">DNI / documento</label>
            <input type="text" id="dni" name="dni" inputmode="numeric" placeholder="Tu número de documento" required>
          </div>
          <button class="btn btn-azul btn-lg">Continuar</button>
        </form>
      </div>

    <?php elseif ($resultado): ?>
      <!-- ===== Resultado (sin detalle de respuestas) ===== -->
      <p class="intro">Hola <strong><?= $h($post['nombres']) ?></strong>, esta es tu evaluación para <strong><?= $h($post['puesto']) ?></strong>.</p>
      <div class="card ev-res <?= (int)$resultado['aprobado'] ? 'ok' : 'mal' ?>">
        <div class="num"><?= (int)$resultado['puntaje'] ?>%</div>
        <h2><?= (int)$resultado['aprobado'] ? 'Evaluación aprobada' : 'Evaluación no aprobada' ?></h2>
        <p>Nota mínima: <?= (int)$eval['nota_minima'] ?>% · Rendida el <?= date('d/m/Y H:i', strtotime($resultado['fecha'])) ?></p>
        <p><?= (int)$resultado['aprobado']
              ? 'El equipo de Reclutamiento continuará con tu proceso. Consulta tu avance con tu código.'
              : 'Tu postulación finalizó en esta vacante. Puedes postular a otras oportunidades abiertas.' ?></p>
        <a class="btn btn-azul" href="seguimiento.php?codigo=<?= $h(urlencode($codigo)) ?>">Ver mi seguimiento</a>
      </div>

    <?php elseif ($bloqueo): ?>
      <div class="card"><p><?= $h($bloqueo) ?></p>
        <a class="btn btn-azul" href="seguimiento.php?codigo=<?= $h(urlencode($codigo)) ?>">Ver mi seguimiento</a></div>

    <?php else: ?>
      <!-- ===== Examen ===== -->
      <p class="intro">Hola <strong><?= $h($post['nombres']) ?></strong>. Evaluación: <strong><?= $h($eval['nombre']) ?></strong> · <?= $h($post['puesto']) ?>.
        Tienes <?= (int)$eval['tiempo_limite'] ?> minutos. Al terminar el tiempo se enviará automáticamente.</p>
      <div class="ev-barra">
        <span><?= count($preguntas) ?> preguntas · Nota mínima <?= (int)$eval['nota_minima'] ?>%</span>
        <span class="ev-reloj" id="reloj">--:--</span>
      </div>
      <form method="POST" action="evaluacion.php" id="formExamen">
        <input type="hidden" name="accion" value="calificar">
        <input type="hidden" name="codigo" value="<?= $h($codigo) ?>">
        <?php foreach ($preguntas as $n => $q): ?>
          <section class="ev-q">
            <p><?= $n + 1 ?>. <?= $h($q['enunciado']) ?></p>
            <?php foreach ($q['opciones'] as $op): ?>
              <label class="ev-opc">
                <input type="radio" name="respuestas[<?= (int)$q['id'] ?>]" value="<?= (int)$op['id'] ?>">
                <span><?= $h($op['texto']) ?></span>
              </label>
            <?php endforeach; ?>
          </section>
        <?php endforeach; ?>
        <button class="btn btn-azul btn-lg" type="submit">Finalizar y enviar</button>
      </form>
      <script>
      (function () {
        var restante = <?= (int)$restante ?>, form = document.getElementById('formExamen'),
            reloj = document.getElementById('reloj'), enviado = false;
        function enviar() { if (!enviado) { enviado = true; form.submit(); } }
        function pintar() {
          var m = Math.floor(restante / 60), s = restante % 60;
          reloj.textContent = (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
          reloj.classList.toggle('poco', restante <= 60);
        }
        pintar();
        if (restante <= 0) { enviar(); return; }
        setInterval(function () { restante--; pintar(); if (restante <= 0) enviar(); }, 1000);
        form.addEventListener('submit', function (e) {
          if (enviado) return;
          var total = <?= count($preguntas) ?>, resp = form.querySelectorAll('input[type=radio]:checked').length;
          if (resp < total && !confirm('Hay ' + (total - resp) + ' pregunta(s) sin responder. ¿Enviar de todos modos?')) { e.preventDefault(); return; }
          enviado = true;
        });
      })();
      </script>
    <?php endif; ?>

  </div>
</section>

<footer class="footer">
  <div class="contenedor footer-copy" style="border:none">© <?= date('Y') ?> A365 · Portal de empleo</div>
</footer>

</body>
</html>
