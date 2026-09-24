-- Script: migrate_consolidar_formacion.sql
-- El catálogo formacion_educativa tenía duplicados: "Profesional" (ids 2 y 7) y
-- "Tecnólogo(a)" / "Tecnólogo" (ids 1 y 6). Se conserva el id menor de cada uno,
-- se reasignan las referencias en trabajadores y se agrega un índice único para que
-- no se vuelvan a duplicar.

UPDATE trabajadores SET id_formacion_educativa = 1 WHERE id_formacion_educativa = 6;
UPDATE trabajadores SET id_formacion_educativa = 2 WHERE id_formacion_educativa = 7;

DELETE FROM formacion_educativa WHERE id_formacion_educativa IN (6, 7);

ALTER TABLE formacion_educativa ADD UNIQUE KEY uq_formacion_nivel (nivel_academico);
