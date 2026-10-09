<?php
require_once __DIR__ . '/datos_empleos.php';

$qs     = trim($_GET['q'] ?? '');
$qArea  = (int)($_GET['area'] ?? 0);
$qMod   = trim($_GET['mod'] ?? '');

$areasPortal = obtenerAreasPortal();
$EMPLEOS     = obtenerEmpleos(['q' => $qs, 'area' => $qArea]);
$totalOfertas = count(obtenerEmpleos());

if ($qMod !== '') {
    $EMPLEOS = array_filter($EMPLEOS, fn($e) => $e['modalidad'] === $qMod);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Bolsa de Trabajo · A365</title>
  <link rel="stylesheet" href="assets/portal.css">
</head>
<body>

<!-- ===== Cabecera ===== -->
<header class="nav">
  <div class="contenedor nav-in">
    <a href="index.php" class="nav-logo"><img src="../assets/img/logo_a365.png" alt="A365"></a>
    <nav class="nav-links">
      <a href="index.php" class="on">Bolsa de Trabajo</a>
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

<!-- ===== Hero ===== -->
<section class="hero">
  <div class="contenedor">
    <h1>Tenemos <em><?= $totalOfertas ?> oferta<?= $totalOfertas === 1 ? '' : 's' ?></em> de trabajo para ti</h1>
    <p>Encuentra tu próxima oportunidad en A365 y postula en línea. Talento que impulsa tu empresa.</p>
    <form class="hero-buscar" method="GET" action="index.php">
      <input type="text" name="q" value="<?= htmlspecialchars($qs) ?>" placeholder="Buscar por cargo, área o palabra clave...">
      <?php if ($qArea): ?><input type="hidden" name="area" value="<?= $qArea ?>"><?php endif; ?>
      <?php if ($qMod): ?><input type="hidden" name="mod" value="<?= htmlspecialchars($qMod) ?>"><?php endif; ?>
      <button class="btn btn-rojo" type="submit">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        Buscar
      </button>
    </form>
  </div>
</section>

<!-- ===== Bolsa ===== -->
<section class="seccion">
  <div class="contenedor bolsa">

    <!-- Filtros -->
    <aside class="filtros">
      <h3>Filtrar ofertas</h3>
      <form method="GET" action="index.php">
        <?php if ($qs): ?><input type="hidden" name="q" value="<?= htmlspecialchars($qs) ?>"><?php endif; ?>
        <div class="campo">
          <label>Categoría / Área</label>
          <select name="area">
            <option value="0">Todas las áreas</option>
            <?php foreach ($areasPortal as $a): ?>
              <option value="<?= (int)$a['id'] ?>" <?= $qArea === (int)$a['id'] ? 'selected' : '' ?>><?= htmlspecialchars($a['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="campo">
          <label>Modalidad</label>
          <select name="mod">
            <option value="">Todas</option>
            <?php foreach (['Presencial','Híbrido','Remoto'] as $m): ?>
              <option value="<?= $m ?>" <?= $qMod === $m ? 'selected' : '' ?>><?= $m ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button class="btn btn-azul" type="submit">Aplicar filtros</button>
        <?php if ($qs || $qArea || $qMod): ?><a href="index.php" style="display:block;text-align:center;margin-top:10px;font-size:13px;color:var(--texto-2)">Limpiar filtros</a><?php endif; ?>
      </form>
    </aside>

    <!-- Lista -->
    <div>
      <div class="lista-top">
        <h2>Mostrando <em><?= count($EMPLEOS) ?></em> vacantes</h2>
        <span class="orden">Ordenar por: Más recientes</span>
      </div>

      <?php foreach ($EMPLEOS as $id => $e):
        $modClase = ['Presencial'=>'tag-pres','Híbrido'=>'tag-hib','Remoto'=>'tag-rem'][$e['modalidad']] ?? 'tag-hib'; ?>
        <a href="trabajo.php?id=<?= $id ?>" class="empleo">
          <div class="empleo-top">
            <div class="empleo-logo">A365</div>
            <div class="empleo-info">
              <h3><?= htmlspecialchars($e['titulo']) ?></h3>
              <div class="empleo-meta">
                <span class="tag tag-area">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
                  <?= htmlspecialchars($e['area']) ?>
                </span>
                <span class="tag tag-ubi">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                  <?= htmlspecialchars($e['ubicacion']) ?>
                </span>
                <span class="tag <?= $modClase ?>"><?= htmlspecialchars($e['modalidad']) ?></span>
              </div>
            </div>
          </div>
          <p class="empleo-desc"><?= htmlspecialchars($e['resumen']) ?></p>
          <div class="empleo-pie">
            <span class="fecha">Publicado: <?= htmlspecialchars($e['publicado']) ?></span>
            <span class="ver">Ver detalle
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
            </span>
          </div>
        </a>
      <?php endforeach; ?>

      <?php if (!$EMPLEOS): ?>
        <div style="background:#fff;border:1px solid var(--borde);border-radius:12px;padding:34px 24px;text-align:center;color:var(--texto-2)">
          <strong style="display:block;font-size:16px;color:var(--texto);margin-bottom:6px">No hay vacantes que coincidan con tu búsqueda</strong>
          <span style="font-size:14px">Prueba con otro término o <a href="index.php" style="color:var(--azul)">limpia los filtros</a>.</span>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- ===== Footer ===== -->
<footer class="footer">
  <div class="contenedor footer-in">
    <div>
      <img src="../assets/img/logo_a365.png" alt="A365" style="background:#fff;padding:8px 12px;border-radius:8px">
      <p>A365 · Sistema de Reclutamiento. Talento que impulsa tu empresa.</p>
    </div>
    <div class="cols">
      <div>
        <h4>Empresa</h4>
        <a href="#">Quiénes somos</a>
        <a href="#">Nuestra experiencia</a>
        <a href="index.php">Bolsa de Trabajo</a>
      </div>
      <div>
        <h4>Contacto</h4>
        <a href="#">Lima, Perú</a>
        <a href="#">LinkedIn</a>
        <a href="#">www.a365.com</a>
      </div>
    </div>
  </div>
  <div class="footer-copy contenedor">© <?= date('Y') ?> A365 · Portal de empleo</div>
</footer>

</body>
</html>
