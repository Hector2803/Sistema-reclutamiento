-- ============================================================
-- SSR - A365 | Flujo definitivo del proceso de reclutamiento
--   1 Postulación -> 2 Evaluación (test, en el portal) ->
--   3 Validación (datos, CV y documentos) -> 4 Entrevista presencial ->
--   5 Capacitaciones (hasta 5, con asistencia) -> 6 Proceso finalizado
-- Es IDEMPOTENTE: puede ejecutarse varias veces y no hace nada si la base
-- ya está en el flujo nuevo. Sirve para bases instaladas con el flujo
-- anterior (con "Validación (SARA)", "Contacto telefónico", etc.).
-- No fija el nombre de la base: ejecutar con la base ya seleccionada.
-- ============================================================
SET NAMES utf8mb4;

-- 1) Marcas de validación que registra el reclutador (datos y documentos)
ALTER TABLE postulaciones
  ADD COLUMN IF NOT EXISTS val_datos TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS val_docs  TINYINT(1) NOT NULL DEFAULT 0;

-- 2) Capacitaciones (máximo 5 por postulación, con control de asistencia)
CREATE TABLE IF NOT EXISTS capacitaciones (
  id INT AUTO_INCREMENT PRIMARY KEY,
  postulacion_id INT NOT NULL,
  numero TINYINT NOT NULL,
  tema VARCHAR(120) NOT NULL,
  fecha DATE NOT NULL,
  hora TIME NOT NULL,
  lugar VARCHAR(100) NULL,
  asistencia ENUM('Pendiente','Asistió','No asistió') NOT NULL DEFAULT 'Pendiente',
  observacion VARCHAR(255) NULL,
  usuario_id INT NULL,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_cap_post_num (postulacion_id, numero),
  KEY idx_cap_fecha (fecha),
  CONSTRAINT fk_cap_postulacion FOREIGN KEY (postulacion_id) REFERENCES postulaciones (id) ON DELETE CASCADE,
  CONSTRAINT fk_cap_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3) Etapas nuevas (si no existen) con orden provisional
INSERT INTO etapas (nombre, orden, color)
  SELECT 'Validación (datos, CV y documentos)', 903, '#f5a623' FROM DUAL
   WHERE NOT EXISTS (SELECT 1 FROM etapas WHERE nombre = 'Validación (datos, CV y documentos)');
INSERT INTO etapas (nombre, orden, color)
  SELECT 'Entrevista presencial', 904, '#0ea5a4' FROM DUAL
   WHERE NOT EXISTS (SELECT 1 FROM etapas WHERE nombre = 'Entrevista presencial');
INSERT INTO etapas (nombre, orden, color)
  SELECT 'Capacitaciones', 905, '#6366f1' FROM DUAL
   WHERE NOT EXISTS (SELECT 1 FROM etapas WHERE nombre = 'Capacitaciones');
INSERT INTO etapas (nombre, orden, color)
  SELECT 'Proceso finalizado', 906, '#16a34a' FROM DUAL
   WHERE NOT EXISTS (SELECT 1 FROM etapas WHERE nombre = 'Proceso finalizado');

SET @e1 := (SELECT id FROM etapas WHERE nombre LIKE 'Postulaci%' ORDER BY orden LIMIT 1);
SET @e2 := (SELECT id FROM etapas WHERE nombre LIKE 'Evaluaci%'  ORDER BY orden LIMIT 1);
SET @e3 := (SELECT id FROM etapas WHERE nombre = 'Validación (datos, CV y documentos)');
SET @e4 := (SELECT id FROM etapas WHERE nombre = 'Entrevista presencial');
SET @e5 := (SELECT id FROM etapas WHERE nombre = 'Capacitaciones');
SET @e6 := (SELECT id FROM etapas WHERE nombre = 'Proceso finalizado');
-- etapas del flujo anterior (solo existen en bases viejas)
SET @vs := (SELECT id FROM etapas WHERE nombre LIKE 'Validaci%' AND nombre <> 'Validación (datos, CV y documentos)' LIMIT 1);
SET @co := (SELECT id FROM etapas WHERE nombre LIKE 'Contacto%' LIMIT 1);
SET @en := (SELECT id FROM etapas WHERE nombre LIKE 'Entrevista /%' LIMIT 1);
SET @ac := (SELECT id FROM etapas WHERE nombre LIKE 'Acepta%' LIMIT 1);
SET @an := (SELECT id FROM etapas WHERE nombre LIKE 'Antecedentes%' LIMIT 1);
SET @in := (SELECT id FROM etapas WHERE nombre = 'Ingresa' LIMIT 1);
SET @viejo := (@vs IS NOT NULL OR @co IS NOT NULL OR @en IS NOT NULL OR @ac IS NOT NULL OR @an IS NOT NULL OR @in IS NOT NULL);

-- 4) Equivalencias etapa anterior -> etapa nueva (sin tablas temporales, para que
--    funcione también en hostings que no dan ese permiso)
--    Etapa donde está HOY cada candidato en proceso:
UPDATE postulaciones SET etapa_id = CASE
    WHEN etapa_id = @vs THEN @e2
    WHEN etapa_id = @co THEN @e3
    WHEN etapa_id = @en THEN @e4
    WHEN etapa_id = @ac THEN @e5
    WHEN etapa_id = @an THEN @e5
    WHEN etapa_id = @in THEN @e6
    WHEN etapa_id = @e1 THEN @e2
    WHEN etapa_id = @e2 THEN @e3
    ELSE etapa_id END
 WHERE @viejo;
--    Historial y motivos (el historial conserva su texto):
UPDATE historial_etapas SET etapa_origen = CASE
    WHEN etapa_origen = @vs THEN @e2 WHEN etapa_origen = @co THEN @e3 WHEN etapa_origen = @en THEN @e4
    WHEN etapa_origen = @ac THEN @e5 WHEN etapa_origen = @an THEN @e3 WHEN etapa_origen = @in THEN @e6
    ELSE etapa_origen END
 WHERE @viejo;
UPDATE historial_etapas SET etapa_destino = CASE
    WHEN etapa_destino = @vs THEN @e2 WHEN etapa_destino = @co THEN @e3 WHEN etapa_destino = @en THEN @e4
    WHEN etapa_destino = @ac THEN @e5 WHEN etapa_destino = @an THEN @e3 WHEN etapa_destino = @in THEN @e6
    ELSE etapa_destino END
 WHERE @viejo;
UPDATE motivos_descarte SET etapa_id = CASE
    WHEN etapa_id = @vs THEN @e2 WHEN etapa_id = @co THEN @e3 WHEN etapa_id = @en THEN @e4
    WHEN etapa_id = @ac THEN @e5 WHEN etapa_id = @an THEN @e3 WHEN etapa_id = @in THEN @e6
    ELSE etapa_id END
 WHERE @viejo;
-- el motivo del puntaje mínimo pertenece a la evaluación (test)
UPDATE motivos_descarte SET etapa_id = @e2 WHERE descripcion LIKE 'No alcanz%' AND @e2 IS NOT NULL;
-- el rechazo de la propuesta se produce al llegar a las capacitaciones
UPDATE motivos_descarte SET etapa_id = @e5 WHERE descripcion LIKE 'Rechaza la propuesta%' AND @e5 IS NOT NULL;
-- movimientos que quedaron "de una etapa a la misma" sin motivo se eliminan
DELETE FROM historial_etapas WHERE etapa_origen = etapa_destino AND motivo_id IS NULL AND etapa_origen IS NOT NULL;

-- 5) Motivos nuevos
INSERT INTO motivos_descarte (descripcion, etapa_id)
  SELECT 'CV no cumple el perfil de la vacante', @e3 FROM DUAL
   WHERE NOT EXISTS (SELECT 1 FROM motivos_descarte WHERE descripcion = 'CV no cumple el perfil de la vacante');
INSERT INTO motivos_descarte (descripcion, etapa_id)
  SELECT 'Documentos o datos incompletos o no verificables', @e3 FROM DUAL
   WHERE NOT EXISTS (SELECT 1 FROM motivos_descarte WHERE descripcion = 'Documentos o datos incompletos o no verificables');
INSERT INTO motivos_descarte (descripcion, etapa_id)
  SELECT 'Inasistencia a capacitación', @e5 FROM DUAL
   WHERE NOT EXISTS (SELECT 1 FROM motivos_descarte WHERE descripcion = 'Inasistencia a capacitación');

-- 6) Se eliminan las etapas del flujo anterior y se ordenan las nuevas
DELETE FROM etapas WHERE id IN (IFNULL(@vs,0), IFNULL(@co,0), IFNULL(@en,0), IFNULL(@ac,0), IFNULL(@an,0), IFNULL(@in,0));
UPDATE etapas SET nombre='Postulación',       orden=1, color='#2f6fed' WHERE id=@e1;
UPDATE etapas SET nombre='Evaluación (test)', orden=2, color='#7c4ddb' WHERE id=@e2;
UPDATE etapas SET orden=3, color='#f5a623' WHERE id=@e3;
UPDATE etapas SET orden=4, color='#0ea5a4' WHERE id=@e4;
UPDATE etapas SET orden=5, color='#6366f1' WHERE id=@e5;
UPDATE etapas SET orden=6, color='#16a34a' WHERE id=@e6;
