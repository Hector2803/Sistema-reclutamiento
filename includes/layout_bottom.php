    </main>
  </div><!-- /contenido -->
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
