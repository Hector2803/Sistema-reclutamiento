-- ============================================================
-- Seguridad de login (SSR A365)
-- 1) Auditoría y control de fuerza bruta (bloqueo a los 3 fallos)
-- 2) Cambio forzado de contraseña cuando el administrador la regenera
-- 3) Segundo factor (TOTP) para administradores
-- ============================================================

CREATE TABLE IF NOT EXISTS intentos_login (
  id         INT(11)      NOT NULL AUTO_INCREMENT,
  usuario    VARCHAR(50)  NOT NULL,
  ip         VARCHAR(45)  NOT NULL,
  exito      TINYINT(1)   NOT NULL DEFAULT 0,
  detalle    VARCHAR(120) DEFAULT NULL,
  creado_en  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_int_usuario (usuario, creado_en),
  KEY idx_int_ip (ip, creado_en),
  KEY idx_int_creado (creado_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS debe_cambiar_clave TINYINT(1)   NOT NULL DEFAULT 0;
ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS clave_2fa          VARCHAR(64)  DEFAULT NULL;
ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS fa2_activo         TINYINT(1)   NOT NULL DEFAULT 0;
