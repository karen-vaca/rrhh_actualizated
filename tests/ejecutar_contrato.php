<?php
require_once __DIR__ . '/../config/auth.php';
soloConsola();
// Ayudante de ValidacionContratoTest.php: ejecuta el guardar.php REAL de contratación
// con un POST dado (como si llegara del navegador, sin JavaScript) y devuelve su
// respuesta JSON. Solo se usa con datos que deben ser RECHAZADOS: la validación ocurre
// antes de cualquier escritura. Por seguridad, si la tabla contratos cambia, lo reporta.
//
// Uso: php tests/ejecutar_contrato.php <archivo-json-con-el-post>

require __DIR__ . '/../config/conexion.php';

$huella = fn() => md5(json_encode($conexion->query('SELECT * FROM contratos ORDER BY id_contrato')->fetchAll()));
$antes = $huella();

$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = json_decode(file_get_contents($argv[1]), true) + ['formato' => 'json'];

ob_start();
register_shutdown_function(function () use ($huella, $antes) {
    $salida = ob_get_clean();
    $pos = strpos($salida, '{');
    $respuesta = $pos !== false ? json_decode(substr($salida, $pos), true) : null;
    echo "\n@@RESULTADO@@" . json_encode([
        'respuesta' => $respuesta,
        'contratos_sin_cambios' => $huella() === $antes,
    ], JSON_UNESCAPED_UNICODE);
});

chdir(__DIR__ . '/../views/contratacion');
require __DIR__ . '/../views/contratacion/guardar.php';
