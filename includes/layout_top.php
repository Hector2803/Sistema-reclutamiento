<?php
/* Cabecera común: barra lateral + barra superior.
   Antes de incluir este archivo define:
     $tituloPagina  -> título del <title> y del área
     $activo        -> clave del menú activo (dashboard, requerimientos, candidatos,
                        kanban, entrevistas, reportes, usuarios)
*/
require_once __DIR__ . '/guardia.php';

$tituloPagina = $tituloPagina ?? 'Panel';
$activo       = $activo ?? 'dashboard';
$estilosExtra = $estilosExtra ?? [];   // hojas de estilo adicionales por página
$nombre       = $_SESSION['usuario_nombre'] ?? 'Usuario';
$rol          = ucfirst($_SESSION['usuario_rol'] ?? '');

// Definición del menú lateral (ícono SVG + etiqueta + clave + enlace).
$menu = [
    ['clave' => 'dashboard',     'texto' => 'Dashboard',              'link' => 'dashboard.php',
     'ico' => '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>'],
    ['clave' => 'requerimientos','texto' => 'Requerimientos / Vacantes','link' => 'requerimientos.php',
     'ico' => '<rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>'],
    ['clave' => 'candidatos',    'texto' => 'Candidatos',             'link' => 'candidatos.php',
     'ico' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>'],
    ['clave' => 'entrevistas',   'texto' => 'Entrevistas',            'link' => 'entrevistas.php',
     'ico' => '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>'],
    ['clave' => 'evaluaciones',  'texto' => 'Evaluaciones',           'link' => 'banco_preguntas.php', 'roles' => ['Administrador','Supervisor'],
     'ico' => '<path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>'],
    ['clave' => 'reportes',      'texto' => 'Reportes',               'link' => 'reportes.php',
     'ico' => '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>'],
    ['clave' => 'organigrama',   'texto' => 'Organigrama',            'link' => 'organigrama.php',
     'ico' => '<rect x="9" y="3" width="6" height="5" rx="1"/><rect x="3" y="16" width="6" height="5" rx="1"/><rect x="15" y="16" width="6" height="5" rx="1"/><path d="M6 16v-3h12v3M12 8v5"/>'],
    ['clave' => 'administracion','texto' => 'Administración',         'link' => 'usuarios.php', 'rol' => 'Administrador',
     'ico' => '<path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>'],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SSR · <?= htmlspecialchars($tituloPagina) ?></title>
  <link rel="stylesheet" href="assets/css/dashboard.css?v=<?= @filemtime(__DIR__ . '/../assets/css/dashboard.css') ?>">
  <?php foreach ($estilosExtra as $css): ?>
  <link rel="stylesheet" href="<?= htmlspecialchars($css) ?>?v=<?= @filemtime(dirname(__DIR__) . '/' . $css) ?>">
  <?php endforeach; ?>
  <script>
  /* Notificación emergente (toast): aparece arriba a la derecha,
     se cierra sola a los 5 segundos o con la ✕. */
  function mostrarToast(mensaje, tipo){
    tipo = (tipo === 'error') ? 'error' : 'ok';
    let caja = document.getElementById('toastCaja');
    if (!caja){
      caja = document.createElement('div');
      caja.id = 'toastCaja';
      caja.className = 'toast-caja';
      caja.setAttribute('role', 'status');
      document.body.appendChild(caja);
    }
    const t = document.createElement('div');
    t.className = 'toast ' + tipo;
    const ic = tipo === 'error'
      ? '<svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>'
      : '<svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>';
    t.innerHTML = ic
      + '<div class="tx"></div>'
      + '<button class="x" type="button" aria-label="Cerrar">'
      + '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>'
      + '</button>';
    t.querySelector('.tx').textContent = String(mensaje == null ? '' : mensaje);
    caja.appendChild(t);
    requestAnimationFrame(() => t.classList.add('visible'));
    const cerrar = () => {
      t.classList.remove('visible');
      setTimeout(() => t.remove(), 300);
    };
    t.querySelector('.x').addEventListener('click', cerrar);
    setTimeout(cerrar, 5000);
  }
  </script>
</head>
<body>
<div class="app" id="app">

  <!-- ================= BARRA LATERAL ================= -->
  <aside class="sidebar">
    <div class="sidebar-logo">
      <img src="assets/img/logo_a365.png" alt="A365">
    </div>

    <nav class="menu">
      <?php foreach ($menu as $m): ?>
        <?php if (isset($m['rol']) && ($_SESSION['usuario_rol'] ?? '') !== $m['rol']) continue; ?>
        <?php if (isset($m['roles']) && !in_array(($_SESSION['usuario_rol'] ?? ''), $m['roles'], true)) continue; ?>
        <a href="<?= $m['link'] ?>" class="menu-item <?= $activo === $m['clave'] ? 'activo' : '' ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><?= $m['ico'] ?></svg>
          <span><?= htmlspecialchars($m['texto']) ?></span>
        </a>
      <?php endforeach; ?>
    </nav>

    <button class="btn-contraer" id="btnContraer">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="11 17 6 12 11 7"/><polyline points="18 17 13 12 18 7"/></svg>
      <span>Contraer menú</span>
    </button>
  </aside>

  <!-- ================= CONTENIDO ================= -->
  <div class="contenido">

    <!-- ===== BARRA SUPERIOR ===== -->
    <header class="topbar">
      <div class="topbar-marca">
        <h1>A365-Recruit <span class="sep">|</span> <em>SSR Claro Ecuador</em></h1>
        <p>Asistencia • Logística • Contact Center</p>
      </div>

      <div class="topbar-acciones">
        <button class="campana" aria-label="Notificaciones">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
          <span class="badge">5</span>
        </button>

        <div class="perfil">
          <div class="avatar"><?= strtoupper(substr($nombre, 0, 1)) ?></div>
          <div class="perfil-datos">
            <strong><?= htmlspecialchars($rol) ?></strong>
            <span><?= htmlspecialchars($nombre) ?></span>
          </div>
          <div class="perfil-menu" id="perfilMenu">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
            <div class="perfil-desplegable" id="perfilDesplegable">
              <?php if (($_SESSION['usuario_rol'] ?? '') === 'Administrador'): ?>
                <a href="dos_factores.php">Seguridad · 2FA</a>
              <?php endif; ?>
              <a href="cambiar_clave.php">Cambiar contraseña</a>
              <a href="logout.php">Cerrar sesión</a>
            </div>
          </div>
        </div>
      </div>
    </header>

    <!-- ===== ÁREA PRINCIPAL (aquí entra el contenido de cada página) ===== -->
    <main class="principal">
