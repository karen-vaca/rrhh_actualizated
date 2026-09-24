-- Script: migrate_catalogos_trabajador.sql
-- 1. Completa el catálogo de grupos étnicos (antes solo tenía "Ninguno" y el
--    formulario enviaba ids 2-6 que no existían).
-- 2. Permite dejar vacío el tipo de sangre (campo opcional en el formulario).
INSERT INTO grupos_etnicos (grupo_etnico)
SELECT g.nombre FROM (
    SELECT 'Indígena' AS nombre UNION ALL
    SELECT 'Afrocolombiano' UNION ALL
    SELECT 'Raizal' UNION ALL
    SELECT 'Palenquero' UNION ALL
    SELECT 'Rrom (Gitano)'
) g
WHERE NOT EXISTS (SELECT 1 FROM grupos_etnicos e WHERE e.grupo_etnico = g.nombre);

ALTER TABLE trabajadores MODIFY id_sangre INT NULL;
