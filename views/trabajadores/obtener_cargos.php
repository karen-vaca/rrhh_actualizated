<?php
require_once __DIR__ . '/../../config/auth.php';
requerirAcceso();

require_once '../../config/conexion.php';

header('Content-Type: application/json');

$id_area = isset($_GET['id_area']) ? (int)$_GET['id_area'] : 0;

$sql = "
SELECT
    id_cargo,
    nombre_cargo
FROM cargos
WHERE id_area = ?
ORDER BY nombre_cargo
";

$stmt = $conexion->prepare($sql);
$stmt->execute([$id_area]);

echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));