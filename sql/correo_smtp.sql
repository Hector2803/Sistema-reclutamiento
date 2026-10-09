-- ============================================================
-- Configuración de correo SMTP + historial de correos
-- SSR - A365 | Base de datos: ssr_a365
-- ============================================================

CREATE TABLE IF NOT EXISTS config_correo (
  id               TINYINT UNSIGNED PRIMARY KEY,
  host             VARCHAR(120) NOT NULL DEFAULT 'smtp.gmail.com',
  puerto           SMALLINT UNSIGNED NOT NULL DEFAULT 587,
  seguridad        ENUM('tls','ssl','ninguna') NOT NULL DEFAULT 'tls',
  usuario          VARCHAR(190) NULL,
  clave            VARCHAR(255) NULL,
  remitente_nombre VARCHAR(120) NOT NULL DEFAULT 'A365 Reclutamiento',
  remitente_correo VARCHAR(190) NULL,
  dominio          VARCHAR(120) NOT NULL DEFAULT 'a365.com',
  activo           TINYINT(1) NOT NULL DEFAULT 1,
  actualizado_en   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                            ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO config_correo (id, host, puerto, seguridad, dominio)
VALUES (1, 'smtp.gmail.com', 587, 'tls', 'a365.com')
ON DUPLICATE KEY UPDATE id = id;

CREATE TABLE IF NOT EXISTS correos_enviados (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id    INT UNSIGNED NULL,
  destinatario  VARCHAR(190) NOT NULL,
  asunto        VARCHAR(190) NOT NULL,
  cuerpo        MEDIUMTEXT NOT NULL,
  estado        VARCHAR(20) NOT NULL,
  detalle       VARCHAR(500) NULL,
  creado_en     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX ix_creado (creado_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
