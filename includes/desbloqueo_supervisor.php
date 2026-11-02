<?php
/* ============================================================
   SSR - A365 | Vista del Supervisor en usuarios.php (SOL-ACC-002)
   Muestra SOLO a los usuarios de su misma área y el botón de
   desbloqueo. No incluye altas, ediciones, claves ni bajas:
   eso sigue siendo exclusivo del Administrador.
   Se incluye desde usuarios.php (ya cargó guardia y conexión).
   ============================================================ */

$tituloPagina = 'Desbloquear cuentas';
$activo       = 'desbloqueo';

$pdo     = obtenerConexion();
$areaSup = (int)($_SESSION['usuario_area_id'] ?? 0);
$miId    = (int)($_SESSION['usuario_id'] ?? 0);

$flashOk    = $_SESSION['flash_ok']    ?? '';
$flashError = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_ok'], $_SESSION['flash_error']);

$usuariosArea = [];
$nombreArea   = '';
$bloqueados   = [];

if ($areaSup > 0) {
    $st = $pdo->prepare(
        "SELECT u.id, u.usuario, u.nombre, u.estado, u.ultimo_acceso, r.nombre AS rol, a.nombre AS area
           FROM usuarios u
           INNER JOIN roles r ON r.id = u.rol_id
           LEFT  JOIN areas a ON a.id = u.area_id
          WHERE u.area_id = :a AND u.id <> :yo AND r.nombre NOT IN ('Administrador', 'Supervisor')
          ORDER BY u.nombre"
    );
    $st->execute([':a' => $areaSup, ':yo' => $miId]);
    $usuariosArea = $st->fetchAll();
    $nombreArea   = $usuariosArea[0]['area'] ?? '';

    // Usuarios bloqueados por 3 intentos fallidos (ventana de 15 min), igual que en el panel del Administrador
    try {
        $lista = $pdo->query(
            "SELECT usuario FROM intentos_login
              WHERE exito = 0 AND creado_en > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
              GROUP BY usuario HAVING COUNT(*) >= 3"
        )->fetchAll();
        $stHasta = $pdo->prepare(
            "SELECT creado_en FROM intentos_login
              WHERE exito = 0 AND usuario = :u AND creado_en > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
              ORDER BY creado_en DESC LIMIT 1 OFFSET 2"
        );
        foreach ($lista as $l) {
            $stHasta->execute([':u' => $l['usuario']]);
            $t = $stHasta->fetchColumn();
            if ($t) { $bloqueados[$l['usuario']] = date('H:i', strtotime($t) + 15 * 60); }
        }
    } catch (PDOException $e) { $bloqueados = []; }
}

require_once __DIR__ . '/layout_top.php';
?>

<div class="panel-encabezado">
  <div>
    <h2>Desbloquear cuentas</h2>
    <p>Libera a los usuarios de tu área que se bloquearon por tres intentos fallidos.</p>
  </div>
</div>

<?php if ($flashOk): ?><script>mostrarToast(<?= json_encode($flashOk, JSON_UNESCAPED_UNICODE) ?>, 'ok');</script><?php endif; ?>
<?php if ($flashError): ?><script>mostrarToast(<?= json_encode($flashError, JSON_UNESCAPED_UNICODE) ?>, 'error');</script><?php endif; ?>

<?php if ($areaSup <= 0): ?>
  <section class="tarjeta" style="padding:22px">
    Tu cuenta no tiene un área asignada. Pide al Administrador que te la asigne para poder desbloquear usuarios.
  </section>
<?php else: ?>
  <section class="tarjeta" style="padding:8px 18px 18px">
    <table class="tabla-cand">
      <thead>
        <tr><th>USUARIO</th><th>ROL</th><th>ESTADO</th><th>ÚLTIMO ACCESO</th><th>ACCIÓN</th></tr>
      </thead>
      <tbody>
        <?php if (!$usuariosArea): ?>
          <tr><td colspan="5" style="text-align:center;color:var(--texto-3);padding:26px">No hay otros usuarios en tu área.</td></tr>
        <?php endif; ?>
        <?php foreach ($usuariosArea as $u):
            $activoU   = (int)$u['estado'] === 1;
            $bloqHasta = $bloqueados[$u['usuario']] ?? null;
            $estT      = !$activoU ? 'Inactivo' : ($bloqHasta ? 'Bloqueado' : 'Activo');
            $esC       = $estT === 'Activo' ? 'es-activo' : ($estT === 'Bloqueado' ? 'es-proceso' : 'es-inactivo');
            $acc       = ($u['ultimo_acceso'] && $u['ultimo_acceso'] !== '0000-00-00 00:00:00')
                         ? date('d/m/Y h:i A', strtotime($u['ultimo_acceso'])) : 'Nunca';
            $nombreJs  = htmlspecialchars(addslashes($u['nombre']), ENT_QUOTES); ?>
          <tr>
            <td><span class="n"><?= htmlspecialchars($u['nombre']) ?></span><br><small style="color:var(--texto-3)"><?= htmlspecialchars($u['usuario']) ?></small></td>
            <td><?= htmlspecialchars($u['rol']) ?></td>
            <td><span class="chip <?= $esC ?>"<?= $bloqHasta ? ' title="Bloqueado hasta las ' . htmlspecialchars($bloqHasta) . '"' : '' ?>><?= $estT ?></span></td>
            <td class="fecha"><?= $acc ?></td>
            <td>
              <form action="desbloquear_usuario.php" method="POST"
                    onsubmit="return confirm('¿Desbloquear a <?= $nombreJs ?>? Podrá iniciar sesión de inmediato.')">
                <input type="hidden" name="usuario_id" value="<?= (int)$u['id'] ?>">
                <button type="submit" class="icobtn i-ok" aria-label="Desbloquear"
                        <?= $bloqHasta ? 'title="Desbloquear usuario"' : 'disabled title="El usuario no está bloqueado"' ?>>
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 9.9-1"/></svg>
                </button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <p style="margin:12px 2px 0;color:var(--texto-3);font-size:12.5px">
      Solo ves a los usuarios de tu área<?= $nombreArea ? ' (' . htmlspecialchars($nombreArea) . ')' : '' ?>. Cada desbloqueo queda en la bitácora con tu nombre.
    </p>
  </section>
<?php endif; ?>

<?php require_once __DIR__ . '/layout_bottom.php'; ?>
