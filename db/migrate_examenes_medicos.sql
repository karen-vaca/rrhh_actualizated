-- Script: migrate_examenes_medicos.sql
-- Deja las tablas de Exámenes Médicos como las usa views/examenes/ (antes la pantalla
-- consultaba columnas que no existían). Ambas tablas estaban vacías al migrar.

-- 1. Catálogo de tipos: código estable para la aplicación y vigencia en meses
--    (periodicidad por defecto de 1 año; NULL = no genera próximo examen).
ALTER TABLE tipo_examen
  ADD COLUMN codigo VARCHAR(30) NULL AFTER id_examen,
  ADD COLUMN meses_vigencia TINYINT UNSIGNED NULL AFTER nombre_examen;

INSERT INTO tipo_examen (codigo, nombre_examen, meses_vigencia)
SELECT n.codigo, n.nombre, n.meses FROM (
    SELECT 'ingreso' AS codigo, 'Ingreso' AS nombre, 12 AS meses UNION ALL
    SELECT 'periodico', 'Periódico', 12 UNION ALL
    SELECT 'retiro', 'Retiro', NULL UNION ALL
    SELECT 'post_incapacidad', 'Post-incapacidad', 12
) n
WHERE NOT EXISTS (SELECT 1 FROM tipo_examen t WHERE t.codigo = n.codigo);

ALTER TABLE tipo_examen
  MODIFY codigo VARCHAR(30) NOT NULL,
  ADD UNIQUE KEY uq_tipo_examen_codigo (codigo);

-- 2. Exámenes: programación, resultado (mismos valores que perfil_salud.aptitud),
--    estado, próximo examen y concepto de alturas opcional.
ALTER TABLE examenes_medicos
  CHANGE fecha_examen_medico fecha_realizado DATE NULL,
  DROP COLUMN concepto_medico,
  ADD COLUMN fecha_programada DATE NOT NULL AFTER id_examen,
  ADD COLUMN resultado ENUM('apto','restriccion','no_apto','pendiente') NULL AFTER fecha_realizado,
  ADD COLUMN estado ENUM('programado','realizado','cancelado') NOT NULL DEFAULT 'programado' AFTER resultado,
  ADD COLUMN proxima_fecha DATE NULL AFTER concepto_alturas,
  ADD COLUMN observaciones TEXT NULL AFTER proxima_fecha,
  ADD COLUMN creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN actualizado_en DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  ADD KEY idx_examenes_estado_fecha (estado, fecha_programada);
