-- Script: migrate_lugar_nacimiento.sql
-- Lugar de nacimiento como ciudad del catálogo DIVIPOLA (ver catalogo_divipola.sql).
-- El departamento se obtiene de la ciudad. La columna de texto lugar_nacimiento se
-- conserva: guarda lo que se escribió antes y, mientras no haya ciudad asignada,
-- marca el registro como "por revisar". Después de este script, ejecutar:
--   php db/migrar_lugar_nacimiento.php
ALTER TABLE trabajadores
  ADD COLUMN codigo_ciudad_nacimiento CHAR(5) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL AFTER lugar_nacimiento,
  ADD CONSTRAINT fk_trabajadores_ciudad_nacimiento FOREIGN KEY (codigo_ciudad_nacimiento) REFERENCES ciudades (codigo);
