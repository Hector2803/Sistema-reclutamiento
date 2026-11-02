/* Historiales con búsqueda, filtro, paginación y menú de acciones (Versión del sistema / Backup) */
(function () {
  function mostrarModal(titulo, sub, texto) {
    const f = document.createElement('div'); f.className = 'hist-modal';
    f.innerHTML = '<div role="dialog" aria-modal="true"><h5></h5><div class="sub"></div><pre></pre><button type="button" class="ap-btn">Cerrar</button></div>';
    f.querySelector('h5').textContent = titulo; f.querySelector('.sub').textContent = sub; f.querySelector('pre').textContent = texto;
    const cerrar = () => { f.remove(); document.removeEventListener('keydown', esc); };
    const esc = e => { if (e.key === 'Escape') cerrar(); };
    f.addEventListener('click', e => { if (e.target === f) cerrar(); });
    f.querySelector('button').addEventListener('click', cerrar);
    document.addEventListener('keydown', esc);
    document.body.appendChild(f); f.querySelector('button').focus();
  }

  document.querySelectorAll('.hist').forEach(function (h) {
    const filas = Array.from(h.querySelectorAll('tbody tr[data-fila]'));
    const por = parseInt(h.dataset.pag || '5', 10);
    const q = h.querySelector('.hist-q'), f = h.querySelector('.hist-f');
    const info = h.querySelector('.hist-info'), pagEl = h.querySelector('.hist-pag'), vacio = h.querySelector('.hist-vacio');
    let pag = 1;
    function filtradas() {
      const t = q ? q.value.trim().toLowerCase() : '', e = f ? f.value : '';
      return filas.filter(r => (!t || r.textContent.toLowerCase().indexOf(t) > -1) && (!e || r.dataset.estado === e));
    }
    function pintar() {
      const lista = filtradas(), total = lista.length, paginas = Math.max(1, Math.ceil(total / por));
      if (pag > paginas) pag = paginas;
      const ini = (pag - 1) * por;
      filas.forEach(r => r.hidden = true);
      lista.slice(ini, ini + por).forEach(r => r.hidden = false);
      if (vacio) vacio.hidden = total > 0;
      if (info) info.textContent = total ? 'Mostrando ' + (ini + 1) + ' a ' + Math.min(ini + por, total) + ' de ' + total + ' resultados' : 'Sin resultados';
      if (pagEl) {
        pagEl.innerHTML = ''; pagEl.hidden = paginas <= 1;
        const b = (t, p, on, dis) => { const x = document.createElement('button'); x.type = 'button'; x.textContent = t; if (on) x.className = 'on'; x.disabled = !!dis; x.addEventListener('click', () => { pag = p; pintar(); }); pagEl.appendChild(x); };
        b('‹', pag - 1, false, pag === 1);
        for (let i = 1; i <= paginas; i++) if (i === 1 || i === paginas || Math.abs(i - pag) <= 1) b(String(i), i, i === pag);
        b('›', pag + 1, false, pag === paginas);
      }
    }
    if (q) q.addEventListener('input', () => { pag = 1; pintar(); });
    if (f) f.addEventListener('change', () => { pag = 1; pintar(); });
    pintar();
  });

  // Menú de acciones (⋮)
  let abierto = null;
  const cerrarMenu = () => { if (abierto) { abierto.remove(); abierto = null; } };
  document.addEventListener('click', function (e) {
    const b = e.target.closest('.hist-acc');
    if (!b) { cerrarMenu(); return; }
    e.stopPropagation();
    const mismo = abierto && abierto._b === b; cerrarMenu(); if (mismo) return;
    const tr = b.closest('tr'), m = document.createElement('div'); m.className = 'hist-menu'; m._b = b;
    if (tr.dataset.detalle !== undefined) {
      const x = document.createElement('button'); x.type = 'button'; x.textContent = tr.dataset.verTexto || 'Ver detalle';
      x.addEventListener('click', () => { cerrarMenu(); mostrarModal(tr.dataset.titulo || '', tr.dataset.sub || '', tr.dataset.detalle || 'Sin información adicional.'); });
      m.appendChild(x);
    }
    if (tr.dataset.descarga) {
      const a = document.createElement('a'); a.href = tr.dataset.descarga; a.textContent = tr.dataset.descargaTexto || 'Descargar'; m.appendChild(a);
    }
    document.body.appendChild(m);
    const r = b.getBoundingClientRect();
    m.style.top = Math.min(r.bottom + 4, window.innerHeight - m.offsetHeight - 8) + 'px';
    m.style.left = Math.max(8, Math.min(r.right - m.offsetWidth, window.innerWidth - m.offsetWidth - 8)) + 'px';
    abierto = m;
  });
  window.addEventListener('scroll', cerrarMenu, true); window.addEventListener('resize', cerrarMenu);
  document.addEventListener('keydown', e => { if (e.key === 'Escape') cerrarMenu(); });

  // Contador del changelog
  const ta = document.getElementById('apNotas'), ct = document.getElementById('apContador');
  if (ta && ct) { const u = () => ct.textContent = ta.value.length + '/' + ta.maxLength; ta.addEventListener('input', u); u(); }
})();
