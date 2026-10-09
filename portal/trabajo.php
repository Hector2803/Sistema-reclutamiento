<?php
require_once __DIR__ . '/datos_empleos.php';
$id = (int)($_GET['id'] ?? 1);
$e = obtenerEmpleo($id);
if (!$e) { header('Location: index.php'); exit; }
$modClase = ['Presencial'=>'tag-pres','Híbrido'=>'tag-hib','Remoto'=>'tag-rem'][$e['modalidad']] ?? 'tag-hib';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($e['titulo']) ?> · A365</title>
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
    <a href="index.php" class="volver">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
      Volver a la bolsa de trabajo
    </a>

    <div class="detalle">
      <!-- Principal -->
      <div class="detalle-main">
        <h1><?= htmlspecialchars($e['titulo']) ?></h1>
        <div class="detalle-emp">
          <span class="lg">A365</span>
          <div><strong>A365</strong><span><?= htmlspecialchars($e['area']) ?> · <?= htmlspecialchars($e['ubicacion']) ?></span></div>
        </div>

        <p class="intro"><?= htmlspecialchars($e['resumen']) ?></p>

        <h2>Principales tareas y responsabilidades</h2>
        <ul class="lista-check">
          <?php foreach (($e['tareas'] ?: ['Brindar soporte y atención dentro del área asignada.']) as $t): ?>
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg><?= htmlspecialchars($t) ?></li>
          <?php endforeach; ?>
        </ul>

        <h2>Requisitos</h2>
        <ul class="lista-check">
          <?php foreach (($e['requisitos'] ?: ['Perfil alineado al puesto y disponibilidad inmediata.']) as $t): ?>
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg><?= htmlspecialchars($t) ?></li>
          <?php endforeach; ?>
        </ul>

        <h2>Beneficios</h2>
        <ul class="lista-check">
          <?php foreach (($e['beneficios'] ?: ['Beneficios de ley y ambiente de trabajo A365.']) as $t): ?>
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg><?= htmlspecialchars($t) ?></li>
          <?php endforeach; ?>
        </ul>

        <?php if ($e['habilidades']): ?>
        <h2>Principales habilidades</h2>
        <div class="hab">
          <?php foreach ($e['habilidades'] as $h): ?>
            <span><?= htmlspecialchars($h) ?></span>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="nota">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
          <span>Al dar clic en <strong>Postularme</strong> crearás tu cuenta de candidato, completarás tus datos, cargarás tu CV y rendirás una evaluación. La postulación estará completa cuando termines todas las etapas.</span>
        </div>
      </div>

      <!-- Sidebar -->
      <aside class="aside-detalle">
        <div class="card">
          <h3>Vista general del empleo</h3>
          <div class="vg-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            <div><small>Fecha de publicación</small><strong><?= htmlspecialchars($e['publicado']) ?></strong></div>
          </div>
          <div class="vg-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            <div><small>Fecha de ingreso</small><strong><?= htmlspecialchars($e['vence']) ?></strong></div>
          </div>
          <div class="vg-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
            <div><small>Ubicación</small><strong><?= htmlspecialchars($e['ubicacion']) ?></strong></div>
          </div>
          <div class="vg-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
            <div><small>Área</small><strong><?= htmlspecialchars($e['area']) ?></strong></div>
          </div>
          <div class="vg-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            <div><small>Modalidad</small><strong><?= htmlspecialchars($e['modalidad']) ?></strong></div>
          </div>
          <div class="vg-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            <div><small>Horario</small><strong><?= htmlspecialchars($e['horario']) ?></strong></div>
          </div>
          <div class="vg-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
            <div><small>Remuneración</small><strong><?= $e['remuneracion'] !== null ? 'S/ ' . number_format((float)$e['remuneracion'], 2) : 'A convenir' ?></strong></div>
          </div>
          <div class="vg-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            <div><small>Vacantes</small><strong><?= (int)$e['vacantes'] ?></strong></div>
          </div>

          <a href="postular.php?id=<?= $id ?>" class="btn btn-rojo btn-lg" style="margin-top:18px">
            Postularme
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
          </a>
        </div>

        <!-- Proceso -->
        <div class="proceso">
          <h3>¿Cómo es el proceso?</h3>
          <div class="pasos">
            <?php
            $pasos = [
              ['Registro','Crea tu cuenta con correo y contraseña'],
              ['Datos y CV','Completa tu perfil y sube tu CV'],
              ['Test de evaluación','Responde el test de tu área'],
              ['Revisión','El reclutador evalúa tu postulación'],
              ['Resultado','Conoce si continúas en el proceso'],
            ];
            foreach ($pasos as $i => $p): ?>
              <div class="paso pend">
                <div class="marca"><div class="bola"><?= $i+1 ?></div><div class="linea"></div></div>
                <div class="tx"><strong><?= $p[0] ?></strong><span><?= $p[1] ?></span></div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </aside>
    </div>
  </div>
</section>

<footer class="footer">
  <div class="contenedor footer-in">
    <div>
      <img src="../assets/img/logo_a365.png" alt="A365" style="background:#fff;padding:8px 12px;border-radius:8px">
      <p>A365 · Sistema de Reclutamiento. Talento que impulsa tu empresa.</p>
    </div>
    <div class="cols">
      <div><h4>Empresa</h4><a href="#">Quiénes somos</a><a href="#">Nuestra experiencia</a><a href="index.php">Bolsa de Trabajo</a></div>
      <div><h4>Contacto</h4><a href="#">Lima, Perú</a><a href="#">LinkedIn</a><a href="#">www.a365.com</a></div>
    </div>
  </div>
  <div class="footer-copy contenedor">© <?= date('Y') ?> A365 · Portal de empleo</div>
</footer>

</body>
</html>
