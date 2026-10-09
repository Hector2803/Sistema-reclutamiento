-- ============================================================
-- SSR - A365 | Conexión total: portal público, entrevistas y organigrama
-- Ejecutar sobre la base ssr_a365
--   mysql --default-character-set=utf8mb4 -u root ssr_a365 < sql/conectar_todo.sql
-- ============================================================

-- 1) Nuevas columnas de campanas para el portal público
ALTER TABLE campanas
  ADD COLUMN modalidad ENUM('Presencial','Híbrido','Remoto') NOT NULL DEFAULT 'Presencial' AFTER ubicacion,
  ADD COLUMN tareas TEXT NULL AFTER descripcion,
  ADD COLUMN requisitos TEXT NULL AFTER tareas,
  ADD COLUMN beneficios TEXT NULL AFTER requisitos;

-- 2) Tabla de entrevistas (RF-04 / HU programación)
CREATE TABLE IF NOT EXISTS entrevistas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  postulacion_id INT NOT NULL,
  entrevistador_id INT NULL,
  entrevistador VARCHAR(120) NOT NULL,
  rol_entrevistador VARCHAR(80) NULL,
  tipo VARCHAR(60) NOT NULL DEFAULT 'Entrevista RH',
  fecha DATE NOT NULL,
  hora TIME NOT NULL,
  sala VARCHAR(80) NULL,
  estado ENUM('Programada','En Curso','Completada','Cancelada') NOT NULL DEFAULT 'Programada',
  observaciones VARCHAR(255) NULL,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_entrevistas_fecha (fecha),
  KEY idx_entrevistas_estado (estado),
  CONSTRAINT fk_ent_postulacion FOREIGN KEY (postulacion_id) REFERENCES postulaciones (id) ON DELETE CASCADE,
  CONSTRAINT fk_ent_usuario FOREIGN KEY (entrevistador_id) REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3) Contenido real de la vacante publicada (lo muestra el portal)
UPDATE campanas
   SET modalidad    = 'Presencial',
       descripcion  = 'Buscamos talento para brindar soporte y atención al cliente por canales digitales y telefónicos, resolviendo consultas con calidad, rapidez y enfoque en la experiencia del usuario.',
       tareas       = 'Atender consultas, solicitudes y reclamos por teléfono, chat y correo.\nRegistrar casos en el sistema y dar seguimiento oportuno.\nBrindar información clara sobre servicios, procesos y soluciones.\nCumplir indicadores de servicio, calidad y tiempos de respuesta.\nEscalar incidencias según el protocolo de atención.',
       requisitos   = 'Experiencia previa en call center, SAC o servicio al cliente.\nManejo básico de herramientas digitales y sistemas de registro.\nComunicación clara, redacción correcta y orientación al servicio.\nDisponibilidad para laborar en sede.\nSecundaria completa o estudios técnicos en curso o concluidos.',
       beneficios   = 'Ingreso a planilla según políticas de la empresa.\nCapacitación constante en atención al cliente y gestión SAC.\nBuen clima laboral y acompañamiento del equipo.\nOportunidades de desarrollo y línea de carrera.'
 WHERE id = 1;
