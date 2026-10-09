-- ============================================================
--  SSR - A365 | ACTUALIZACIÓN de base de datos (incremental)
--  Ejecuta este archivo UNA vez en phpMyAdmin (pestaña SQL) sobre
--  la base ssr_a365. NO borra datos. Es seguro: usa IF NOT EXISTS.
--  (Solo agrega lo nuevo de HU-05, Administración y deja lista HU-06.)
-- ============================================================
USE ssr_a365;

-- 1) HU-05: columnas para el puntaje del CV en las postulaciones
ALTER TABLE postulaciones
  ADD COLUMN IF NOT EXISTS puntaje_cv INT DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS cv_detalle VARCHAR(255) DEFAULT NULL;

-- 2) HU-05: tabla de palabras clave por área
CREATE TABLE IF NOT EXISTS palabras_clave (
    id       INT AUTO_INCREMENT PRIMARY KEY,
    area_id  INT NOT NULL,
    palabra  VARCHAR(60) NOT NULL,
    peso     INT NOT NULL DEFAULT 1,
    CONSTRAINT fk_pc_area FOREIGN KEY (area_id) REFERENCES areas(id)
) ENGINE=InnoDB;

-- Palabras clave de ejemplo (solo si la tabla está vacía)
INSERT INTO palabras_clave (area_id, palabra, peso)
SELECT * FROM (
  SELECT 1,'atención al cliente',2 UNION ALL SELECT 1,'call center',2 UNION ALL
  SELECT 1,'servicio',1 UNION ALL SELECT 1,'comunicación',1 UNION ALL
  SELECT 1,'reclamos',1 UNION ALL SELECT 1,'excel',1 UNION ALL
  SELECT 2,'ventas',2 UNION ALL SELECT 2,'comercial',2 UNION ALL
  SELECT 2,'negociación',1 UNION ALL SELECT 2,'metas',1 UNION ALL
  SELECT 2,'clientes',1 UNION ALL SELECT 2,'retención',1 UNION ALL
  SELECT 3,'soporte',2 UNION ALL SELECT 3,'técnico',2 UNION ALL
  SELECT 3,'hardware',1 UNION ALL SELECT 3,'software',1 UNION ALL
  SELECT 3,'redes',1 UNION ALL SELECT 3,'windows',1
) AS nuevos
WHERE NOT EXISTS (SELECT 1 FROM palabras_clave);

-- 3) Administración: historial de backups
CREATE TABLE IF NOT EXISTS historial_backups (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    archivo    VARCHAR(200) NOT NULL,
    tamano_kb  INT DEFAULT NULL,
    usuario_id INT DEFAULT NULL,
    fecha      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_bk_user FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB;

-- 4) Administración: historial de restauraciones
CREATE TABLE IF NOT EXISTS historial_restauraciones (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    archivo    VARCHAR(200) NOT NULL,
    resultado  ENUM('Exitosa','Fallida') NOT NULL DEFAULT 'Exitosa',
    detalle    VARCHAR(255) DEFAULT NULL,
    usuario_id INT DEFAULT NULL,
    fecha      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_rs_user FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB;

-- 5) Administración: historial de versiones del sistema
CREATE TABLE IF NOT EXISTS versiones_sistema (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    version     VARCHAR(20) NOT NULL,
    descripcion TEXT DEFAULT NULL,
    archivo     VARCHAR(200) DEFAULT NULL,
    usuario_id  INT DEFAULT NULL,
    fecha       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ver_user FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB;

-- Versión inicial (solo si no hay ninguna registrada)
INSERT INTO versiones_sistema (version, descripcion, usuario_id)
SELECT '1.0.0', 'Versión inicial del Sistema de Gestión y Seguimiento de Reclutamiento (SSR).', NULL
WHERE NOT EXISTS (SELECT 1 FROM versiones_sistema);

-- ============================================================
--  LISTO. HU-06 (mover etapas) no necesita cambios de BD.
-- ============================================================
