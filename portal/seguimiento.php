<?php
/* ============================================================
   SSR - Portal público | Seguimiento de postulación
   Consulta real por código de seguimiento (A365-XXXXX).
   ============================================================ */
require_once __DIR__ . '/datos_empleos.php';

$pdo    = obtenerConexion();
$codigo = strtoupper(trim($_REQUEST['codigo'] ?? ''));
$nuevo  = isset($_GET['nuevo']);
$error  = '';
$post   = null;
$etapasLista = [];
$indiceActual = -1;
$historial = [];

if ($codigo !== '') {
    if (!preg_match('/^A365-[A-Z0-9]{1,10}$/', $codigo)) {
        $error = 'El código tiene un formato no válido (ejemplo: A365-7F3K9).';
    } else {
        $st = $pdo->prepare(
            "SELECT p.id, p.estado, p.fecha_postulacion, p.puntaje_cv, p.codigo_seguimiento,
                    po.nombres, po.dni, c.puesto, c.codigo AS req_codigo, a.nombre AS area, c.ubicacion, c.modalidad,
                    e.id AS etapa_id, e.nombre AS etapa, e.orden
               FROM postulaciones p
               INNER JOIN postulantes po ON po.id = p.postulante_id
               INNER JOIN campanas   c  ON c.id  = p.campana_id
               INNER JOIN areas      a  ON a.id  = c.area_id
               INNER JOIN etapas     e  ON e.id  = p.etapa_id
              WHERE p.codigo_seguimiento = :cod
              LIMIT 1");
        $st->execute([':cod' => $codigo]);
        $post = $st->fetch();

        if (!$post) {
            $error = 'No encontramos una postulación con ese código. Verifica el código que recibiste.';
        } else {
            $etapasLista = $pdo->query("SELECT id, nombre, orden FROM etapas ORDER BY orden ASC")->fetchAll();
            foreach ($etapasLista as $i => $et) {
                if ((int)$et['id'] === (int)$post['etapa_id']) { $indiceActual = $i; break; }
            }
            $h = $pdo->prepare(
                "SELECT e.nombre, h.fecha, h.observacion
                   FROM historial_etapas h
                   INNER JOIN etapas e ON e.id = h.etapa_destino
                  WHERE h.postulacion_id = :p
                  ORDER BY h.fecha ASC, h.id ASC");
            $h->execute([':p' => $post['id']]);
            $historial = $h->fetchAll();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Consultar mi postulación · A365</title>
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
    <div class="nav-cta">
      <a href="seguimiento.php" class="btn btn-linea">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        Consultar mi postulación
      </a>
    </div>
  </div>
</header>

<section class="seccion">
  <div class="contenedor">
    <div class="detalle">

      <!-- ===== Resultado ===== -->
      <div class="detalle-main">
        <?php if ($nuevo && $post): ?>
          <div class="aviso-portal">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            <span>Postulación registrada. Tu código de seguimiento es <strong><?= htmlspecialchars($codigo) ?></strong>. Guárdalo para consultar tu avance.</span>
          </div>
        <?php endif; ?>

        <h1>Estado de tu postulación</h1>

        <?php if ($error): ?>
          <div class="aviso-error-portal">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <span><?= htmlspecialchars($error) ?></span>
          </div>
        <?php endif; ?>

        <?php if ($post): ?>
          <div class="detalle-emp">
            <span class="lg">A365</span>
            <div>
              <strong><?= htmlspecialchars($post['puesto']) ?></strong>
              <span><?= htmlspecialchars($post['area']) ?> · <?= htmlspecialchars($post['ubicacion'] ?: 'Lima, Perú') ?> · <?= htmlspecialchars($post['req_codigo']) ?></span>
            </div>
          </div>

          <p class="intro">
            Hola <strong><?= htmlspecialchars($post['nombres']) ?></strong>, tu postulación del
            <?= date('d/m/Y', strtotime($post['fecha_postulacion'])) ?> está en
            <strong><?= htmlspecialchars($post['estado']) ?></strong>, etapa
            <strong><?= htmlspecialchars($post['etapa']) ?></strong>.
            <?php if ($post['puntaje_cv'] !== null): ?>Puntaje de tu CV: <strong><?= (int)$post['puntaje_cv'] ?>%</strong>.<?php endif; ?>
          </p>

          <?php if ($post['estado'] === 'Descartado'): ?>
            <div class="aviso-error-portal">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
              <span>Tu postulación finalizó en esta vacante. Puedes postular a otras oportunidades abiertas.</span>
            </div>
          <?php elseif ($post['estado'] === 'Seleccionado'): ?>
            <div class="aviso-portal">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
              <span>¡Felicidades! Fuiste seleccionado en este proceso.</span>
            </div>
          <?php endif; ?>

          <div class="proceso" style="box-shadow:none;border:none;padding:18px 0 0">
            <div class="pasos">
              <?php foreach ($etapasLista as $i => $et):
                $clase = $i < $indiceActual ? 'hecho' : ($i === $indiceActual ? 'activo' : 'pend'); ?>
                <div class="paso <?= $clase ?>">
                  <div class="marca">
                    <div class="bola">
                      <?php if ($i < $indiceActual): ?>
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                      <?php else: ?><?= $i + 1 ?><?php endif; ?>
                    </div>
                    <div class="linea"></div>
                  </div>
                  <div class="tx">
                    <strong><?= htmlspecialchars($et['nombre']) ?></strong>
                    <span>
                      <?php if ($i < $indiceActual): ?>Completada
                      <?php elseif ($i === $indiceActual): ?>Etapa actual
                      <?php else: ?>Pendiente<?php endif; ?>
                    </span>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>

          <?php if ($historial): ?>
            <h2 style="margin-top:26px">Historial de movimientos</h2>
            <ul class="lista-check">
              <?php foreach ($historial as $h): ?>
                <li>
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                  <?= date('d/m/Y H:i', strtotime($h['fecha'])) ?> · <?= htmlspecialchars($h['nombre']) ?>
                  <?php if ($h['observacion']): ?> — <?= htmlspecialchars($h['observacion']) ?><?php endif; ?>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>

        <?php elseif (!$error): ?>
          <p class="intro">Ingresa el código que recibiste al postular para ver el avance de tu proceso de selección.</p>
          <div class="nota" style="margin-top:6px">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
            <span>El código tiene el formato <strong>A365-XXXXX</strong> y te fue entregado al finalizar tu postulación.</span>
          </div>
        <?php endif; ?>
      </div>

      <!-- ===== Formulario de consulta ===== -->
      <aside class="aside-detalle">
        <div class="card">
          <h3>Consultar por código</h3>
          <p style="font-size:13.5px;color:var(--texto-2);margin-bottom:16px">Ingresa el código que recibiste al postular.</p>
          <form action="seguimiento.php" method="GET">
            <div class="campo-a">
              <label for="cod">Código de seguimiento</label>
              <input type="text" id="cod" name="codigo" value="<?= htmlspecialchars($codigo) ?>" placeholder="Ej. A365-7F3K9" required pattern="A365-[A-Za-z0-9]{1,10}" title="Formato A365-XXXXX">
            </div>
            <button class="btn btn-azul btn-lg">Consultar</button>
          </form>
          <div class="auth-pie" style="margin-top:14px">¿Aún no postulas? <a href="index.php">Ver vacantes abiertas</a></div>
        </div>
      </aside>
    </div>
  </div>
</section>

<footer class="footer">
  <div class="contenedor footer-copy" style="border:none">© <?= date('Y') ?> A365 · Portal de empleo</div>
</footer>

</body>
</html>
