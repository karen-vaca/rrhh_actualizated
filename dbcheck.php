<?php
require_once __DIR__ . '/config/auth.php';
soloConsola();
require __DIR__ . '/config/conexion.php';
$pdo = $conexion;
echo "SCHEMA:\n";
foreach ($pdo->query('SHOW COLUMNS FROM trabajadores') as $c) {
    echo $c['Field'] . ' ' . $c['Type'] . ' ' . $c['Null'] . ' ' . $c['Default'] . "\n";
}
echo "\nSAMPLE:\n";
foreach ($pdo->query('SELECT id_trabajador,nombres,apellidos,tiene_hijos,numero_hijos FROM trabajadores ORDER BY id_trabajador DESC LIMIT 10') as $row) {
    echo json_encode($row) . "\n";
}
