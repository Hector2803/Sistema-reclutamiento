/* Filtros de listas.
   1) .filtros-cand[data-filtra="<selector de la tabla>"]  → filtra en vivo las filas (tr[data-fila]) sin recargar.
   2) form.filtros-cand (GET)                              → se aplica solo al cambiar un filtro o al dejar de escribir. */
(function () {
  const norm = t => (t || '').toString().toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');

  // ---- 1) Filtrado en vivo ----
  document.querySelectorAll('.filtros-cand[data-filtra]').forEach(function (box) {
    const tabla = document.querySelector(box.dataset.filtra); if (!tabla) return;
    const tbody = tabla.querySelector('tbody');
    const q = box.querySelector('input[type=text]');
    const sels = Array.from(box.querySelectorAll('select[data-campo]'));
    const limpiar = box.querySelector('.btn-limpiar');
    const pie = (tabla.closest('section') || document).querySelector('.tabla-pie span');
    const nombre = box.dataset.nombre || 'registro';
    let ocupado = false;
    function aplicar() {
      if (ocupado) return; ocupado = true;
      const t = norm(q ? q.value : '');
      const filas = Array.from(tbody.querySelectorAll('tr[data-fila]'));
      let n = 0;
      filas.forEach(function (r) {
        const ok = (!t || norm(r.textContent).indexOf(t) > -1) &&
                   sels.every(s => !s.value || r.dataset[s.dataset.campo] === s.value);
        r.hidden = !ok; if (ok) n++;
      });
      let vacio = tbody.querySelector('tr.sin-resultados');
      if (filas.length && n === 0) {
        if (!vacio) {
          vacio = document.createElement('tr'); vacio.className = 'sin-resultados';
          const td = document.createElement('td'); td.colSpan = 12; td.style.cssText = 'text-align:center;color:var(--texto-3);padding:26px';
          td.textContent = 'No hay resultados que coincidan con los filtros.'; vacio.appendChild(td); tbody.appendChild(vacio);
        }
      } else if (vacio) vacio.remove();
      if (pie) pie.textContent = 'Mostrando ' + n + ' de ' + filas.length + ' ' + nombre + (filas.length === 1 ? '' : 's');
      ocupado = false;
    }
    if (q) { q.addEventListener('input', aplicar); q.addEventListener('keydown', e => { if (e.key === 'Enter') e.preventDefault(); }); }
    sels.forEach(s => s.addEventListener('change', aplicar));
    const bf = box.querySelector('.btn-filtros'); if (bf) bf.addEventListener('click', e => { e.preventDefault(); aplicar(); });
    if (limpiar) limpiar.addEventListener('click', function (e) { e.preventDefault(); if (q) q.value = ''; sels.forEach(s => s.value = ''); aplicar(); });
    new MutationObserver(function () { if (!ocupado) aplicar(); }).observe(tbody, { childList: true });
    aplicar();
  });

  // ---- 2) Formularios GET que se aplican solos ----
  document.querySelectorAll('form.filtros-cand').forEach(function (f) {
    const q = f.querySelector('input[type=text]');
    let tm;
    f.querySelectorAll('select, input[type=date]').forEach(el => el.addEventListener('change', () => f.submit()));
    if (q) {
      q.addEventListener('input', function () { clearTimeout(tm); tm = setTimeout(() => f.submit(), 700); });
      if (q.value) { q.focus(); const l = q.value.length; try { q.setSelectionRange(l, l); } catch (e) {} }
    }
  });
})();
