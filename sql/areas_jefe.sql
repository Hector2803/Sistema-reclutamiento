-- ============================================================
-- Jefe responsable por área (visualización y edición en el
-- organigrama, HU: "editar los nombres de cada jefe a cargo
-- de cada área")
-- Ejecutar sobre la base: ssr_a365
-- ============================================================
ALTER TABLE areas
  ADD COLUMN jefe VARCHAR(120) NULL AFTER descripcion;

-- Ejemplo de actualización manual (opcional):
-- UPDATE areas SET jefe = 'Nombre del jefe' WHERE id = 1;
