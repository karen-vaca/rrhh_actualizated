-- Script: migrate_consolidar_generos.sql
-- El catálogo generos tenía 15 filas (Femenino/Masculino/Otro repetidos 5 veces)
-- porque seed_generos_areas_cargo.sql usa ON DUPLICATE KEY UPDATE pero la columna
-- `nombre` no tenía índice único. Deja una fila por nombre (la de id menor),
-- reasigna las referencias en trabajadores y agrega el índice único.

UPDATE trabajadores t
JOIN generos g ON g.id_generos = t.id_generos
JOIN (SELECT nombre, MIN(id_generos) AS id FROM generos GROUP BY nombre) c ON c.nombre = g.nombre
SET t.id_generos = c.id
WHERE t.id_generos <> c.id;

DELETE g FROM generos g
JOIN (SELECT nombre, MIN(id_generos) AS id FROM generos GROUP BY nombre) c ON c.nombre = g.nombre
WHERE g.id_generos <> c.id;

ALTER TABLE generos ADD UNIQUE KEY uq_generos_nombre (nombre);
