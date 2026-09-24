-- Script: migrate_eps_pendiente.sql
-- La EPS de varios trabajadores quedó mal guardada por un bug del formulario de
-- crear (las opciones tenían ids que no coincidían con la tabla eps: "Nueva EPS"
-- se guardaba como Sura, "Sura" como Compensar...). Como es un dato de salud, se
-- deja vacía (NULL) para esos trabajadores: la ficha la marca como
-- "EPS pendiente de verificar" y editar.php obliga a volver a capturarla.

-- 1. Permitir EPS vacía.
ALTER TABLE trabajadores MODIFY id_eps INT NULL;

-- 2. Vaciar la EPS de los trabajadores afectados (lista confirmada por RRHH el
--    2026-09-24): 7 con "Sura" (id 1, que en el formulario era "Nueva EPS") y el 13
--    con "Compensar" (id 3, que en el formulario era "Sura"). Ejecutado el
--    2026-09-24; respaldo de los valores anteriores en
--    db/respaldos/eps_antes_de_vaciar_20260924.sql.
UPDATE trabajadores SET id_eps = NULL WHERE id_trabajador IN (1, 9, 12, 13, 14, 20, 23, 25);
