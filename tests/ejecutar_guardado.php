<?php
require_once __DIR__ . '/../config/auth.php';
soloConsola();
// Ayudante de GuardarTrabajadorTest.php: ejecuta crear.php o actualizar.php con un
// POST dado, dentro de una transacción que SIEMPRE se deshace al terminar.
// Imprime en JSON la fila resultante (o los errores de validación) y no deja datos.
//
// Uso: php tests/ejecutar_guardado.php crear|actualizar <archivo-json-con-el-post>

[$_, $accion, $archivoPost] = $argv;

require __DIR__ . '/../config/conexion.php';
$conexion->beginTransaction();

$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = json_decode(file_get_contents($archivoPost), true);
$_SESSION = [];

register_shutdown_function(function () use ($conexion, $accion) {
    $salida = ['errores' => $_SESSION['form_trabajador']['errores'] ?? []];
    if (!$salida['errores']) {
        $id = $accion === 'crear' ? (int)$conexion->lastInsertId() : (int)$_POST['id_trabajador'];
        $stmt = $conexion->prepare('SELECT * FROM trabajadores WHERE id_trabajador = ?');
        $stmt->execute([$id]);
        $salida['fila'] = $stmt->fetch();
    }
    $conexion->rollBack();
    echo "\n@@RESULTADO@@" . json_encode($salida, JSON_UNESCAPED_UNICODE);
});

chdir(__DIR__ . '/../views/trabajadores');
require __DIR__ . "/../views/trabajadores/$accion.php";
