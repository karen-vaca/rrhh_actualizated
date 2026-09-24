<?php
require_once __DIR__ . '/../config/auth.php';
soloConsola();
// Ejecuta db/seed_generos_areas_cargo.sql usando la conexión del proyecto
require __DIR__ . '/../config/conexion.php';

$sqlFile = __DIR__ . '/seed_generos_areas_cargo.sql';
if (!file_exists($sqlFile)) {
    echo "ERROR: archivo de seed no encontrado: $sqlFile\n";
    exit(2);
}

$sql = file_get_contents($sqlFile);
try {
    $conexion->exec($sql);
    echo "OK: seed ejecutado correctamente\n";
} catch (Exception $e) {
    echo "ERROR al ejecutar seed: " . $e->getMessage() . "\n";
    exit(1);
}
