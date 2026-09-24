<?php
require_once __DIR__ . '/../config/auth.php';
soloConsola();
require __DIR__ . '/../config/conexion.php';

echo "Conexión OK\n";

foreach (['trabajadores','generos','areas',''] as $tabla) {
    echo "--- $tabla ---\n";
    try {
        $rows = $conexion->query("SELECT * FROM $tabla LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
        var_export($rows);
        echo "\n";
    } catch (Exception $e) {
        echo "ERROR $tabla: " . $e->getMessage() . "\n";
    }
}
