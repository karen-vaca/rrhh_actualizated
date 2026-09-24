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

// Escenario opcional ('__preparar_sql', ej. dejar un trabajador inactivo): se aplica en
// una transacción que se deshace al final. Si guardar.php intentara escribir, fallaría
// al abrir su propia transacción, así que nunca quedan cambios.
$preparar = $_POST['__preparar_sql'] ?? null;
unset($_POST['__preparar_sql']);
if ($preparar) {
    $conexion->beginTransaction();
    $conexion->exec($preparar);
}

ob_start();
register_shutdown_function(function () use ($huella, $antes) {
    global $conexion;
    $salida = ob_get_clean();
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }
    $pos = strpos($salida, '{');
    $respuesta = $pos !== false ? json_decode(substr($salida, $pos), true) : null;
    echo "\n@@RESULTADO@@" . json_encode([
        'respuesta' => $respuesta,
        'contratos_sin_cambios' => $huella() === $antes,
    ], JSON_UNESCAPED_UNICODE);
});

chdir(__DIR__ . '/../views/contratacion');
require __DIR__ . '/../views/contratacion/guardar.php';
