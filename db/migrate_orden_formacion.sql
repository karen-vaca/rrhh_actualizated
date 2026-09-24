-- Script: migrate_orden_formacion.sql
-- Orden de los niveles de formación educativa en los formularios (de menor a mayor
-- nivel académico). Para agregar un nivel nuevo, basta con darle su número de orden.
ALTER TABLE formacion_educativa ADD COLUMN orden TINYINT UNSIGNED NULL AFTER nivel_academico;

UPDATE formacion_educativa SET orden = CASE nivel_academico
    WHEN 'Primaria'     THEN 10
    WHEN 'Bachiller'    THEN 20
    WHEN 'Técnico'      THEN 30
    WHEN 'Tecnólogo(a)' THEN 40
    WHEN 'Profesional'  THEN 50
    WHEN 'Posgrado'     THEN 60
END;
