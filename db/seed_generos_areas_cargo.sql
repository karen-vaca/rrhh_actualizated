-- Script: seed_generos_areas_cargo.sql
-- Valores base de los catálogos generos, areas y cargos (tal como están en uso).
-- Es idempotente: se puede ejecutar varias veces sin duplicar filas, gracias a los
-- índices únicos uq_generos_nombre, uq_areas_nombre y uq_cargos_nombre_area
-- (ver migrate_consolidar_generos.sql y migrate_indices_unicos_catalogos.sql).
-- Ejecutar: php db/run_seed.php

-- Géneros
INSERT INTO generos (nombre) VALUES
('Femenino'),
('Masculino'),
('Otro')
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre);

-- Áreas (nombres exactos de la tabla: "SST", no "Salud y Seguridad en el Trabajo")
INSERT INTO areas (nombre_area) VALUES
('Producción'),
('Administrativa'),
('Logística'),
('Mantenimiento'),
('SST')
ON DUPLICATE KEY UPDATE nombre_area = VALUES(nombre_area);

-- Cargos (tabla cargos; cada cargo pertenece a un área, que se busca por nombre)
INSERT INTO cargos (nombre_cargo, id_area)
SELECT c.nombre_cargo, a.id_areas
FROM (
    SELECT 'Operador de Sellado' AS nombre_cargo, 'Producción' AS nombre_area
    UNION ALL SELECT 'Supervisor de Sellado', 'Producción'
    UNION ALL SELECT 'Operador de Extrusión', 'Producción'
    UNION ALL SELECT 'Operador de Peletizado', 'Producción'
    UNION ALL SELECT 'Supervisor de Peletizado', 'Producción'
    UNION ALL SELECT 'Operador de Mezclas', 'Producción'
    UNION ALL SELECT 'Supervisor de Extrusión', 'Producción'
    UNION ALL SELECT 'Auxiliar de Producción', 'Producción'
    UNION ALL SELECT 'Operador línea de lavado', 'Producción'
    UNION ALL SELECT 'Operador línea de calor', 'Producción'
    UNION ALL SELECT 'Supervisor de planta de PET', 'Producción'
    UNION ALL SELECT 'Operador de aguas', 'Producción'
    UNION ALL SELECT 'Líder Línea de lavado', 'Producción'
    UNION ALL SELECT 'Operador de compactadora', 'Producción'
    UNION ALL SELECT 'Afilador de cuchillas', 'Producción'
    UNION ALL SELECT 'Operador de Aglutinado', 'Producción'
    UNION ALL SELECT 'Auxiliar de Despachos Bolsa', 'Logística'
    UNION ALL SELECT 'Auxiliar de bodega', 'Logística'
    UNION ALL SELECT 'Montacarguista', 'Logística'
    UNION ALL SELECT 'Conductor', 'Logística'
    UNION ALL SELECT 'Auxiliar de Recibo y despacho de materiales', 'Logística'
    UNION ALL SELECT 'Supervisor Bodega y Peletizado PET', 'Logística'
    UNION ALL SELECT 'Servicios Generales', 'Administrativa'
    UNION ALL SELECT 'Auxiliar de Servicios Generales', 'Administrativa'
    UNION ALL SELECT 'Asistente Administrativo', 'Administrativa'
    UNION ALL SELECT 'Jefe de Mantenimiento', 'Mantenimiento'
    UNION ALL SELECT 'Soldador', 'Mantenimiento'
    UNION ALL SELECT 'Auxiliar de Mantenimiento', 'Mantenimiento'
    UNION ALL SELECT 'Auxiliar SST', 'SST'
    UNION ALL SELECT 'Practicante SENA - Desarrollo de Software', 'Administrativa'
) c
JOIN areas a ON a.nombre_area = c.nombre_area
ON DUPLICATE KEY UPDATE nombre_cargo = VALUES(nombre_cargo);
