-- ============================================================
-- SSR - A365 | Sesiones activas (panel de administración)
-- Registro de sesiones vivas para que el administrador pueda
-- ver quién está dentro y cerrarlas a distancia.
-- ============================================================

CREATE TABLE IF NOT EXISTS sesiones_activas (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  sesion            VARCHAR(64) NOT NULL,
  usuario_id        INT NOT NULL,
  ip                VARCHAR(45) NOT NULL DEFAULT '',
  creada_en         DATETIME NOT NULL,
  ultima_actividad  DATETIME NOT NULL,
  cerrada           TINYINT(1) NOT NULL DEFAULT 0,
  UNIQUE KEY uq_sesion (sesion),
  KEY idx_usuario (usuario_id),
  KEY idx_actividad (ultima_actividad),
  CONSTRAINT fk_sesiones_usuario
    FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
