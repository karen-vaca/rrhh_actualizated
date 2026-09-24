-- Script: migrate_indices_unicos_catalogos.sql
-- Índices únicos para que seed_generos_areas_cargo.sql sea idempotente: su
-- ON DUPLICATE KEY UPDATE solo evita duplicados si existe una clave única.
-- (generos ya lo tiene desde migrate_consolidar_generos.sql)
ALTER TABLE areas  ADD UNIQUE KEY uq_areas_nombre (nombre_area);
ALTER TABLE cargos ADD UNIQUE KEY uq_cargos_nombre_area (nombre_cargo, id_area);
