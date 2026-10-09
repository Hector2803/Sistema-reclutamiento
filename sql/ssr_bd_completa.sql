-- ============================================================
--  SSR - A365 | Base de datos UNIFICADA (SARA + EVALUAR)
--  Sistema de Seguimiento de Reclutamiento
--  Motor: MySQL / MariaDB (XAMPP)  ·  Charset: utf8mb4
--  Importar desde phpMyAdmin o MySQL Workbench.
-- ============================================================

DROP DATABASE IF EXISTS ssr_a365;
CREATE DATABASE ssr_a365 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ssr_a365;

-- Para poder recrear en orden sin problemas de llaves foráneas
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
--  1) CATÁLOGOS BÁSICOS
-- ============================================================

-- Áreas / campañas de negocio (ATC, Ventas, Técnica, etc.)
CREATE TABLE areas (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    nombre      VARCHAR(80)  NOT NULL UNIQUE,
    descripcion VARCHAR(200) DEFAULT NULL,
    estado      TINYINT(1)   NOT NULL DEFAULT 1,
    creado_en   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Roles del sistema (según la estructura real del equipo)
CREATE TABLE roles (
    id     INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB;

-- Orígenes / canales del postulante (para medir qué canal convierte)
CREATE TABLE origenes (
    id     INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(60) NOT NULL UNIQUE
) ENGINE=InnoDB;

-- Etapas del embudo de reclutamiento (ordenadas)
CREATE TABLE etapas (
    id     INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(60) NOT NULL,
    orden  INT NOT NULL,
    color  VARCHAR(20) DEFAULT '#2f6fed'
) ENGINE=InnoDB;

-- Motivos de descarte, asociados a la etapa donde suelen ocurrir
-- (etapa_id NULL = motivo general que aplica a cualquier etapa)
CREATE TABLE motivos_descarte (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    descripcion VARCHAR(120) NOT NULL,
    etapa_id    INT DEFAULT NULL,
    CONSTRAINT fk_motivo_etapa FOREIGN KEY (etapa_id) REFERENCES etapas(id)
) ENGINE=InnoDB;

-- ============================================================
--  2) USUARIOS DEL SISTEMA (reclutadores y jefaturas)
--     Cada usuario tiene un rol y (opcional) un área asignada.
-- ============================================================
CREATE TABLE usuarios (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    rol_id    INT NOT NULL,
    area_id   INT DEFAULT NULL,                 -- área/campaña que gestiona
    usuario   VARCHAR(50)  NOT NULL UNIQUE,
    nombre    VARCHAR(100) NOT NULL,
    correo    VARCHAR(120) DEFAULT NULL,
    clave     VARCHAR(255) NOT NULL,            -- hash bcrypt (password_hash)
    estado    TINYINT(1)   NOT NULL DEFAULT 1,  -- 1 activo, 0 inactivo
    ultimo_acceso DATETIME DEFAULT NULL,
    creado_en TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_usuario_rol  FOREIGN KEY (rol_id)  REFERENCES roles(id),
    CONSTRAINT fk_usuario_area FOREIGN KEY (area_id) REFERENCES areas(id)
) ENGINE=InnoDB;

-- ============================================================
--  3) CAMPAÑAS / REQUERIMIENTOS
--     Lo que Operaciones entrega a Reclutamiento.
-- ============================================================
CREATE TABLE campanas (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    codigo        VARCHAR(20)  NOT NULL UNIQUE,        -- ej. REQ-2024-036
    area_id       INT NOT NULL,
    puesto        VARCHAR(120) NOT NULL,
    descripcion   TEXT DEFAULT NULL,
    vacantes      INT NOT NULL DEFAULT 1,
    ubicacion     VARCHAR(120) DEFAULT NULL,
    horario       VARCHAR(120) DEFAULT NULL,
    remuneracion  DECIMAL(10,2) DEFAULT NULL,
    fecha_ingreso DATE DEFAULT NULL,
    prioridad     ENUM('Alta','Media','Baja') NOT NULL DEFAULT 'Media',
    tipo          ENUM('Nueva Posición','Reemplazo') NOT NULL DEFAULT 'Nueva Posición',
    estado        ENUM('Abierto','En Proceso','Cerrado') NOT NULL DEFAULT 'Abierto',
    publicado     TINYINT(1)   NOT NULL DEFAULT 0,     -- visible en el portal público
    fecha_sol     DATE DEFAULT NULL,
    creado_en     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_campana_area FOREIGN KEY (area_id) REFERENCES areas(id)
) ENGINE=InnoDB;

-- ============================================================
--  4) BLACKLIST (validación tipo SARA)
-- ============================================================
CREATE TABLE blacklist (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    dni       VARCHAR(15) NOT NULL,
    motivo    VARCHAR(200) DEFAULT NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_blacklist_dni (dni)
) ENGINE=InnoDB;

-- ============================================================
--  5) POSTULANTES (la persona, identificada por DNI)
--     Puede tener cuenta propia (correo + clave) para el portal.
-- ============================================================
CREATE TABLE postulantes (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    dni          VARCHAR(15)  NOT NULL UNIQUE,
    nombres      VARCHAR(120) NOT NULL,
    correo       VARCHAR(120) DEFAULT NULL,
    telefono     VARCHAR(20)  DEFAULT NULL,
    distrito     VARCHAR(80)  DEFAULT NULL,     -- para medir descartes por distancia
    clave        VARCHAR(255) DEFAULT NULL,     -- hash; cuenta del portal (opcional)
    cv_ruta      VARCHAR(255) DEFAULT NULL,     -- ruta del archivo de CV
    creado_en    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_postulante_dni (dni)
) ENGINE=InnoDB;

-- ============================================================
--  6) POSTULACIONES (une postulante + campaña + etapa)
--     Es la fila central del seguimiento (SARA).
-- ============================================================
CREATE TABLE postulaciones (
    id                 INT AUTO_INCREMENT PRIMARY KEY,
    postulante_id      INT NOT NULL,
    campana_id         INT NOT NULL,
    etapa_id           INT NOT NULL,                -- etapa actual en el embudo
    reclutador_id      INT DEFAULT NULL,            -- usuario que la gestiona
    origen_id          INT DEFAULT NULL,            -- canal de captación
    codigo_seguimiento VARCHAR(20) NOT NULL UNIQUE, -- ej. A365-7F3K9
    puntaje_cv         INT DEFAULT NULL,            -- % de coincidencia del CV (HU-05)
    cv_detalle         VARCHAR(255) DEFAULT NULL,   -- palabras clave encontradas
    estado             ENUM('En Proceso','Seleccionado','Descartado') NOT NULL DEFAULT 'En Proceso',
    fecha_postulacion  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_post_postulante FOREIGN KEY (postulante_id) REFERENCES postulantes(id),
    CONSTRAINT fk_post_campana    FOREIGN KEY (campana_id)    REFERENCES campanas(id),
    CONSTRAINT fk_post_etapa      FOREIGN KEY (etapa_id)      REFERENCES etapas(id),
    CONSTRAINT fk_post_reclutador FOREIGN KEY (reclutador_id) REFERENCES usuarios(id),
    CONSTRAINT fk_post_origen     FOREIGN KEY (origen_id)     REFERENCES origenes(id),
    -- Un postulante no puede postular dos veces a la misma campaña
    UNIQUE KEY uq_postulante_campana (postulante_id, campana_id)
) ENGINE=InnoDB;

-- ============================================================
--  7) HISTORIAL DE ETAPAS (cada movimiento en el embudo)
--     Aquí se guarda el MOTIVO DE DESCARTE -> alimenta los reportes.
-- ============================================================
CREATE TABLE historial_etapas (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    postulacion_id INT NOT NULL,
    etapa_origen   INT DEFAULT NULL,           -- NULL cuando recién entra
    etapa_destino  INT NOT NULL,
    motivo_id      INT DEFAULT NULL,           -- obligatorio solo si fue descarte
    observacion    VARCHAR(255) DEFAULT NULL,
    usuario_id     INT DEFAULT NULL,           -- quién hizo el movimiento
    fecha          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_hist_post   FOREIGN KEY (postulacion_id) REFERENCES postulaciones(id),
    CONSTRAINT fk_hist_orig   FOREIGN KEY (etapa_origen)   REFERENCES etapas(id),
    CONSTRAINT fk_hist_dest   FOREIGN KEY (etapa_destino)  REFERENCES etapas(id),
    CONSTRAINT fk_hist_motivo FOREIGN KEY (motivo_id)      REFERENCES motivos_descarte(id),
    CONSTRAINT fk_hist_user   FOREIGN KEY (usuario_id)     REFERENCES usuarios(id)
) ENGINE=InnoDB;

-- ============================================================
--  8) EVALUACIONES (módulo EVALUAR)  ·  test por área
-- ============================================================
CREATE TABLE evaluaciones (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    area_id       INT NOT NULL,
    nombre        VARCHAR(120) NOT NULL,
    nota_minima   INT NOT NULL DEFAULT 70,      -- % para aprobar
    tiempo_limite INT NOT NULL DEFAULT 20,      -- minutos
    total_preguntas INT NOT NULL DEFAULT 20,
    estado        TINYINT(1) NOT NULL DEFAULT 1,
    CONSTRAINT fk_eval_area FOREIGN KEY (area_id) REFERENCES areas(id)
) ENGINE=InnoDB;

CREATE TABLE preguntas (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    evaluacion_id INT NOT NULL,
    enunciado     VARCHAR(400) NOT NULL,
    CONSTRAINT fk_preg_eval FOREIGN KEY (evaluacion_id) REFERENCES evaluaciones(id)
) ENGINE=InnoDB;

CREATE TABLE opciones (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    pregunta_id INT NOT NULL,
    texto       VARCHAR(300) NOT NULL,
    correcta    TINYINT(1) NOT NULL DEFAULT 0,
    CONSTRAINT fk_opcion_preg FOREIGN KEY (pregunta_id) REFERENCES preguntas(id)
) ENGINE=InnoDB;

-- Resultado del test, ligado a la POSTULACIÓN (no al postulante suelto)
CREATE TABLE resultados_eval (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    postulacion_id INT NOT NULL,
    evaluacion_id  INT NOT NULL,
    puntaje        INT NOT NULL DEFAULT 0,       -- % obtenido
    aprobado       TINYINT(1) NOT NULL DEFAULT 0,
    fecha          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_res_post FOREIGN KEY (postulacion_id) REFERENCES postulaciones(id),
    CONSTRAINT fk_res_eval FOREIGN KEY (evaluacion_id)  REFERENCES evaluaciones(id)
) ENGINE=InnoDB;

-- Detalle: qué respondió el postulante en cada pregunta
CREATE TABLE respuestas (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    resultado_id INT NOT NULL,
    pregunta_id  INT NOT NULL,
    opcion_id    INT DEFAULT NULL,
    CONSTRAINT fk_resp_result FOREIGN KEY (resultado_id) REFERENCES resultados_eval(id),
    CONSTRAINT fk_resp_preg   FOREIGN KEY (pregunta_id)  REFERENCES preguntas(id),
    CONSTRAINT fk_resp_opcion FOREIGN KEY (opcion_id)    REFERENCES opciones(id)
) ENGINE=InnoDB;

-- Palabras clave por área (para la evaluación automática del CV — HU-05)
CREATE TABLE palabras_clave (
    id       INT AUTO_INCREMENT PRIMARY KEY,
    area_id  INT NOT NULL,
    palabra  VARCHAR(60) NOT NULL,
    peso     INT NOT NULL DEFAULT 1,
    CONSTRAINT fk_pc_area FOREIGN KEY (area_id) REFERENCES areas(id)
) ENGINE=InnoDB;

-- ============================================================
--  ADMINISTRACIÓN / MANTENIMIENTO
-- ============================================================

-- Historial de copias de seguridad (backups)
CREATE TABLE historial_backups (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    archivo    VARCHAR(200) NOT NULL,
    tamano_kb  INT DEFAULT NULL,
    usuario_id INT DEFAULT NULL,
    fecha      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_bk_user FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB;

-- Historial de restauraciones
CREATE TABLE historial_restauraciones (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    archivo    VARCHAR(200) NOT NULL,
    resultado  ENUM('Exitosa','Fallida') NOT NULL DEFAULT 'Exitosa',
    detalle    VARCHAR(255) DEFAULT NULL,
    usuario_id INT DEFAULT NULL,
    fecha      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_rs_user FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB;

-- Historial de versiones / actualizaciones del sistema
CREATE TABLE versiones_sistema (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    version     VARCHAR(20) NOT NULL,
    descripcion TEXT DEFAULT NULL,          -- changelog / notas de la versión
    archivo     VARCHAR(200) DEFAULT NULL,  -- paquete ZIP aplicado
    usuario_id  INT DEFAULT NULL,
    fecha       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ver_user FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
--  DATOS INICIALES (semilla)
-- ============================================================

-- Áreas / campañas
INSERT INTO areas (nombre, descripcion) VALUES
('Atención al Cliente','Atención y soporte al cliente (SAC/ATC)'),
('Ventas','Ventas, retenciones y seguros'),
('Técnica','Soporte y operaciones técnicas'),
('Marketing','Campañas y contenidos'),
('Operaciones','Coordinación y operaciones logísticas');

-- Roles (según el informe: 3 roles)
INSERT INTO roles (nombre) VALUES
('Administrador'),('Supervisor'),('Reclutador');

-- Orígenes / canales
INSERT INTO origenes (nombre) VALUES
('Portal web'),('Computrabajo'),('PAND PE'),('Redes sociales'),
('Referido'),('Trabajo de campo'),('Base anterior');

-- Etapas del embudo (según el flujo real de A365)
INSERT INTO etapas (nombre, orden, color) VALUES
('Postulación',            1, '#2f6fed'),
('Validación (SARA)',      2, '#3b82f6'),
('Contacto telefónico',    3, '#7c4ddb'),
('Entrevista / Role Play', 4, '#f5a623'),
('Acepta propuesta',       5, '#0ea5a4'),
('Evaluación (test)',      6, '#22a06b'),
('Antecedentes',           7, '#6366f1'),
('Ingresa',                8, '#16a34a');

-- Motivos de descarte por etapa
-- Validación (SARA)
INSERT INTO motivos_descarte (descripcion, etapa_id) VALUES
('En blacklist', 2),
('Activo en otra campaña', 2),
('Postulación reiterada', 2),
-- Contacto telefónico
('No interesado por remuneración', 3),
('No interesado por horario', 3),
('No interesado por distancia / ubicación', 3),
('No contesta / número equivocado', 3),
-- Entrevista / Role Play
('No cumple el perfil', 4),
('Competencias comunicativas insuficientes', 4),
('No asistió a la entrevista', 4),
-- Acepta propuesta
('Rechaza la propuesta', 5),
-- Evaluación (test)
('No alcanzó el puntaje mínimo (70%)', 6),
-- Antecedentes
('Observación en antecedentes', 7),
-- General
('Desistió del proceso', NULL);

-- Usuarios de prueba (clave para todos: "admin123" con hash bcrypt $2y$)
-- rol_id: 1=Administrador  2=Supervisor  3=Reclutador
INSERT INTO usuarios (rol_id, area_id, usuario, nombre, correo, clave, estado) VALUES
(1, NULL, 'admin',    'Administrador del Sistema', 'admin@a365.com',
 '$2y$10$P91sByA2UP1rksjysT29b..G33WY8k0oWRjE.Ddr8n1Qa82hiGsMu', 1),
(2, 1,    'supervisor','Supervisor de Reclutamiento','supervisor@a365.com',
 '$2y$10$P91sByA2UP1rksjysT29b..G33WY8k0oWRjE.Ddr8n1Qa82hiGsMu', 1),
(3, 1,    'reclutador','Reclutador de Campaña',     'reclutador@a365.com',
 '$2y$10$P91sByA2UP1rksjysT29b..G33WY8k0oWRjE.Ddr8n1Qa82hiGsMu', 1);

-- Campaña de ejemplo (publicada, para el portal)
INSERT INTO campanas (codigo, area_id, puesto, descripcion, vacantes, ubicacion, horario, remuneracion, fecha_ingreso, prioridad, tipo, estado, publicado, fecha_sol) VALUES
('REQ-2026-001', 1, 'SAC – Asesora de Atención al Cliente',
 'Atención al cliente por canales digitales y telefónicos.', 5, 'Magdalena, Lima',
 'Full time rotativo', 1130.00, '2026-02-01', 'Alta', 'Nueva Posición', 'Abierto', 1, '2026-01-10');

-- Evaluación de ejemplo para el área ATC
INSERT INTO evaluaciones (area_id, nombre, nota_minima, tiempo_limite, total_preguntas) VALUES
(1, 'Evaluación de Aptitud - Atención al Cliente', 70, 20, 20);

-- Palabras clave por área (ejemplo) para la evaluación del CV
INSERT INTO palabras_clave (area_id, palabra, peso) VALUES
(1,'atención al cliente',2),(1,'call center',2),(1,'servicio',1),(1,'comunicación',1),(1,'reclamos',1),(1,'excel',1),
(2,'ventas',2),(2,'comercial',2),(2,'negociación',1),(2,'metas',1),(2,'clientes',1),(2,'retención',1),
(3,'soporte',2),(3,'técnico',2),(3,'hardware',1),(3,'software',1),(3,'redes',1),(3,'windows',1);

-- ============================================================
--  FIN DEL SCRIPT
--  Usuarios de prueba -> clave: admin123  (admin, lais, mateo, mrecluta)
-- ============================================================

-- Versión inicial del sistema (semilla)
INSERT INTO versiones_sistema (version, descripcion, usuario_id) VALUES
('1.0.0', 'Versión inicial del Sistema de Gestión y Seguimiento de Reclutamiento (SSR): login, gestión de usuarios, requerimientos y registro de candidatos con CV.', NULL);
