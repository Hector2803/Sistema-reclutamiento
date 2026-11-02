    </main>
  </div><!-- /contenido -->

  <!-- Pie de página (SOL-INF-002) -->
  <style>@media(max-width:900px){.pie-sistema{padding-bottom:84px!important}}</style>
  <footer class="pie-sistema" style="text-align:center;padding:20px 0;color:#666;font-size:.9rem;border-top:1px solid #eaeaea;margin-top:auto;width:100%;">
    SSR A365 v1.0.1 &middot; Soporte: soporte@a365.com
  </footer>

  <!-- ================= BARRA INFERIOR (solo móvil) ================= -->
  <nav class="nav-inferior" aria-label="Navegación principal">
    <?php foreach (['dashboard' => 'Dashboard', 'requerimientos' => 'Requerimientos', 'candidatos' => 'Candidatos', 'entrevistas' => 'Entrevistas'] as $k => $t):
            foreach ($menu as $m) if ($m['clave'] === $k && (!isset($m['roles']) || in_array(($_SESSION['usuario_rol'] ?? ''), $m['roles'], true))): ?>
      <a href="<?= $m['link'] ?>" class="<?= $activo === $k ? 'activo' : '' ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><?= $m['ico'] ?></svg>
        <span><?= $t ?></span>
      </a>
    <?php endif; endforeach; ?>
    <button type="button" id="btnMas">
      <svg viewBox="0 0 24 24" fill="currentColor"><circle cx="5" cy="12" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="19" cy="12" r="1.8"/></svg>
      <span>Más</span>
    </button>
  </nav>
</div><!-- /app -->

<script>
  // Contraer / expandir menú lateral
  document.getElementById('btnContraer').addEventListener('click', function () {
    document.getElementById('app').classList.toggle('menu-contraido');
  });

  // Cajón del menú en móvil
  (function () {
    const app = document.getElementById('app');
    const abrir  = () => app.classList.add('menu-abierto');
    const cerrar = () => app.classList.remove('menu-abierto');
    ['btnHamb', 'btnMas'].forEach(id => { const x = document.getElementById(id); if (x) x.addEventListener('click', abrir); });
    ['fondoMenu', 'cerrarMenu'].forEach(id => { const x = document.getElementById(id); if (x) x.addEventListener('click', cerrar); });
    document.querySelectorAll('.sidebar .menu-item').forEach(a => a.addEventListener('click', cerrar));
    document.addEventListener('keydown', e => { if (e.key === 'Escape') cerrar(); });
  })();


  // Campanita de notificaciones
  (function () {
    const btn = document.getElementById('btnCampana'), panel = document.getElementById('notifPanel');
    if (!btn || !panel) return;
    const lista = document.getElementById('notifLista'), badge = document.getElementById('notifBadge');
    const esc = t => String(t).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const hace = m => m < 1 ? 'Ahora' : m < 60 ? 'Hace ' + m + ' min' : m < 1440 ? 'Hace ' + Math.floor(m/60) + ' h' : 'Hace ' + Math.floor(m/1440) + ' d';
    function pintar(d) {
      const n = d.no_leidas || 0;
      badge.textContent = n > 99 ? '99+' : n; badge.hidden = n === 0;
      if (!d.items || !d.items.length) { lista.innerHTML = '<p class="notif-vacio">No tienes notificaciones recientes.</p>'; return; }
      lista.innerHTML = d.items.map(i =>
        '<a class="notif-item ' + (i.leida ? '' : 'nueva') + '" href="' + esc(i.link) + '">' +
        '<span class="notif-punto ' + esc(i.tipo) + '"></span><span class="notif-cuerpo"><strong>' + esc(i.titulo) + '</strong>' +
        '<span>' + esc(i.texto) + '</span><em>' + hace(i.hace) + '</em></span></a>').join('');
    }
    function cargar(post) {
      const o = post ? {method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: 'accion=leer', credentials: 'same-origin'} : {credentials: 'same-origin'};
      return fetch('notificaciones.php', o).then(r => r.json()).then(pintar)
        .catch(() => { lista.innerHTML = '<p class="notif-vacio">No se pudieron cargar las notificaciones.</p>'; });
    }
    const cerrar = () => { panel.hidden = true; btn.setAttribute('aria-expanded', 'false'); };
    btn.addEventListener('click', e => {
      e.stopPropagation();
      if (panel.hidden) { panel.hidden = false; btn.setAttribute('aria-expanded', 'true'); cargar(false); } else cerrar();
    });
    document.getElementById('notifLeer').addEventListener('click', e => { e.stopPropagation(); cargar(true); });
    panel.addEventListener('click', e => e.stopPropagation());
    document.addEventListener('click', cerrar);
    document.addEventListener('keydown', e => { if (e.key === 'Escape') cerrar(); });
    setInterval(() => { if (panel.hidden && !document.hidden) fetch('notificaciones.php', {credentials: 'same-origin'}).then(r => r.json()).then(d => { badge.textContent = d.no_leidas > 99 ? '99+' : d.no_leidas; badge.hidden = !d.no_leidas; }).catch(() => {}); }, 60000);
  })();

  // Menú desplegable del perfil
  const perfilMenu = document.getElementById('perfilMenu');
  const desplegable = document.getElementById('perfilDesplegable');
  if (perfilMenu) {
    perfilMenu.addEventListener('click', function (e) {
      e.stopPropagation();
      desplegable.classList.toggle('abierto');
    });
    document.addEventListener('click', () => desplegable.classList.remove('abierto'));
  }
</script>
</body>
</html>
