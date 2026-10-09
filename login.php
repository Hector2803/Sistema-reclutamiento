<?php
require_once __DIR__ . '/includes/sesion.php';

// Si ya hay sesión activa, se redirige directamente al panel.
if (isset($_SESSION['usuario_id'])) {
    header('Location: dashboard.php');
    exit;
}

// Mensajes de error/estado provenientes del procesamiento del login.
$error   = $_SESSION['error']   ?? '';
$usuario = $_SESSION['usuario_previo'] ?? '';
unset($_SESSION['error'], $_SESSION['usuario_previo']);

// La sesión se cerró por inactividad (timeout de 30 minutos).
if (isset($_GET['expirada']) && $error === '') {
    $error = 'Tu sesión expiró por inactividad. Ingresa nuevamente.';
}
// Se cerró la sesión desde otro dispositivo/pestaña.
if (isset($_GET['cerrada']) && $error === '') {
    $error = 'La sesión se cerró correctamente.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SSR · Sistema de Reclutamiento</title>
  <link rel="stylesheet" href="assets/css/login.css">
</head>
<body>

  <div class="contenedor-login">

    <!-- ============ PANEL IZQUIERDO (branding) ============ -->
    <section class="panel-izquierdo">
      <img src="assets/img/logo_a365.png" alt="A365" class="logo-marca">

      <h1>Sistema de <strong>Reclutamiento</strong></h1>
      <div class="linea-roja"></div>
      <p class="subtitulo">Gestiona y encuentra el mejor talento para nuestra organización.</p>

      <div class="features">
        <div class="feature">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          <span>Talento<br>Calificado</span>
        </div>
        <div class="feature">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
          <span>Procesos<br>Eficientes</span>
        </div>
        <div class="feature">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
          <span>Mejores<br>Resultados</span>
        </div>
      </div>
    </section>

    <!-- ============ PANEL DERECHO (formulario) ============ -->
    <section class="panel-derecho">
      <img src="assets/img/logo_a365.png" alt="A365" class="logo-form">
      <h2>Bienvenido</h2>
      <p class="instruccion">Ingresa tus credenciales para continuar</p>

      <?php if (!empty($error)): ?>
        <div class="alerta alerta-error">
          <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          <?= htmlspecialchars($error) ?>
        </div>
      <?php endif; ?>

      <form action="procesar_login.php" method="POST" autocomplete="off" novalidate>

        <div class="campo">
          <label for="usuario">Usuario</label>
          <div class="input-icono">
            <svg class="ico-izq" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            <input type="text" id="usuario" name="usuario" placeholder="Ingresa tu usuario"
                   value="<?= htmlspecialchars($usuario) ?>" required autofocus maxlength="50">
          </div>
        </div>

        <div class="campo">
          <label for="clave">Contraseña</label>
          <div class="input-icono">
            <svg class="ico-izq" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            <input type="password" id="clave" name="clave" placeholder="Ingresa tu contraseña" required maxlength="100">
            <button type="button" class="btn-ojo" id="verClave" aria-label="Mostrar contraseña">
              <svg id="iconoOjo" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
          </div>
        </div>

        <div class="fila-opciones">
          <span class="enlace-olvido" style="color:var(--texto-3,#6b7793);font-size:13px">
            ¿Olvidaste tu contraseña? El administrador puede regenerarla desde Usuarios.
          </span>
        </div>

        <button type="submit" class="btn-entrar">Iniciar Sesión</button>
      </form>

      <div class="pie-seguro">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        Acceso seguro y confidencial
      </div>
    </section>

  </div>

  <script>
    // Mostrar / ocultar contraseña
    const btnOjo = document.getElementById('verClave');
    const inputClave = document.getElementById('clave');
    btnOjo.addEventListener('click', () => {
      const oculto = inputClave.type === 'password';
      inputClave.type = oculto ? 'text' : 'password';
    });
  </script>

</body>
</html>
