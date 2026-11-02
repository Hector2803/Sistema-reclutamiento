</main>
  </div><!-- /contenido -->

  <!-- Pie de página oficial -->
  <footer style="text-align: center; padding: 15px 0; color: #666; font-size: 0.9rem; border-top: 1px solid #eaeaea; margin-top: auto; width: 100%;">
    SSR A365 v1.0.1 &middot; Soporte: soporte@a365.com
  </footer>

</div><!-- /app -->

<script>
  // Contraer / expandir menú lateral
  document.getElementById('btnContraer').addEventListener('click', function () {
    document.getElementById('app').classList.toggle('menu-contraido');
  });

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
