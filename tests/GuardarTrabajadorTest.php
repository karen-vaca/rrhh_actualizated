<?php
require_once __DIR__ . '/../config/auth.php';
soloConsola();
// Tests de integración: ejecutan crear.php y actualizar.php reales contra la base de
// datos, dentro de una transacción que se deshace (ver ejecutar_guardado.php).
// Ejecutar: php tests/GuardarTrabajadorTest.php

require __DIR__ . '/../config/conexion.php';

$fallos = 0;
$total = 0;

function prueba(string $nombre, callable $fn): void {
    global $fallos, $total;
    $total++;
    try {
        $fn();
        echo "  OK    $nombre\n";
    } catch (Throwable $e) {
        $fallos++;
        echo "  FALLA $nombre\n        " . $e->getMessage() . "\n";
    }
}

function afirmar(bool $condicion, string $mensaje): void {
    if (!$condicion) {
        throw new Exception($mensaje);
    }
}

function guardar(string $accion, array $post): array {
    $archivo = tempnam(sys_get_temp_dir(), 'post');
    file_put_contents($archivo, json_encode($post));
    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/ejecutar_guardado.php') . " $accion " . escapeshellarg($archivo) . ' 2>&1';
    $salida = shell_exec($cmd);
    unlink($archivo);
    $pos = strrpos((string)$salida, '@@RESULTADO@@');
    afirmar($pos !== false, "Salida inesperada:\n$salida");
    return json_decode(substr($salida, $pos + strlen('@@RESULTADO@@')), true);
}

$cargo = $conexion->query('SELECT id_cargo, id_area FROM cargos ORDER BY id_cargo LIMIT 1')->fetch();
$totalAntes = (int)$conexion->query('SELECT COUNT(*) FROM trabajadores')->fetchColumn();

$post = [
    'nombres' => 'Prueba', 'apellidos' => 'Integración Rollback',
    'id_tipos_documentos' => '1', 'numero_documento' => '9990001113',
    'id_generos' => '1', 'fecha_nacimiento' => date('Y-m-d', strtotime('-30 years')),
    'departamento_nacimiento' => '15', 'ciudad_nacimiento' => '15001', 'id_nacionalidad' => '1',
    'id_area' => (string)$cargo['id_area'], 'id_cargo' => (string)$cargo['id_cargo'],
    'fecha_ingreso' => date('Y-m-d', strtotime('-1 year')),
    'correo_personal' => 'prueba.rollback.plastypet@gmail.com', 'telefono' => '300 123 4567',
    'id_eps' => '4', 'id_formacion_educativa' => '4', 'id_sangre' => '',
    'id_estado_civil' => '1', 'id_grupos_etnicos' => '3', 'orientacion_sexual' => '',
    'tiene_hijos' => '1', 'numero_hijos' => '2',
    'talla_camisa' => 'M', 'talla_pantalon' => '32', 'talla_botas' => '40',
];

echo "Guardado de trabajadores (con rollback)\n";

prueba('crear guarda todos los campos, incluidos fecha de ingreso y estado', function () use ($post) {
    $r = guardar('crear', $post);
    afirmar($r['errores'] === [], 'Errores: ' . json_encode($r['errores'], JSON_UNESCAPED_UNICODE));
    $f = $r['fila'];
    afirmar($f['fecha_ingreso'] === $post['fecha_ingreso'], "fecha_ingreso no se guardó: " . var_export($f['fecha_ingreso'], true));
    afirmar((int)$f['estado'] === 1, 'un trabajador nuevo debería quedar Activo');
    afirmar($f['codigo_ciudad_nacimiento'] === '15001' && $f['lugar_nacimiento'] === null, 'lugar de nacimiento: ' . json_encode([$f['codigo_ciudad_nacimiento'], $f['lugar_nacimiento']]));
    afirmar($f['celular'] === '3001234567', "celular debería guardarse sin espacios: {$f['celular']}");
    afirmar((int)$f['id_eps'] === 4, 'id_eps no coincide');
    afirmar((int)$f['id_grupos_etnicos'] === 3, 'grupo étnico no coincide');
    afirmar($f['id_sangre'] === null, 'tipo de sangre vacío debería guardarse como NULL');
    afirmar((int)$f['numero_hijos'] === 2, 'numero_hijos no coincide');
});

prueba('crear rechaza datos inválidos y no inserta nada', function () use ($post) {
    $r = guardar('crear', array_merge($post, ['fecha_nacimiento' => '2026-05-12', 'correo_personal' => 'x@gmail.com.p']));
    afirmar(isset($r['errores']['fecha_nacimiento'], $r['errores']['correo_personal']), 'Debería rechazar fecha y correo');
    afirmar(!isset($r['fila']), 'No debería haber fila');
});

prueba('actualizar guarda cambios válidos', function () use ($conexion, $post, $cargo) {
    $t = $conexion->query('SELECT id_trabajador, numero_documento, correo_personal FROM trabajadores ORDER BY id_trabajador LIMIT 1')->fetch();
    $r = guardar('actualizar', array_merge($post, [
        'id_trabajador' => (string)$t['id_trabajador'],
        'numero_documento' => $t['numero_documento'],
        'correo_personal' => $t['correo_personal'],
        'nombres' => 'Nombre Editado',
    ]));
    afirmar($r['errores'] === [], 'Errores: ' . json_encode($r['errores'], JSON_UNESCAPED_UNICODE));
    afirmar($r['fila']['nombres'] === 'Nombre Editado', 'No se actualizó el nombre');
    afirmar($r['fila']['fecha_ingreso'] === $post['fecha_ingreso'], 'No se actualizó la fecha de ingreso');
});

prueba('actualizar rechaza un cargo de otra área', function () use ($conexion, $post, $cargo) {
    $t = $conexion->query('SELECT id_trabajador, numero_documento, correo_personal FROM trabajadores ORDER BY id_trabajador LIMIT 1')->fetch();
    $otroCargo = $conexion->query("SELECT id_cargo FROM cargos WHERE id_area <> {$cargo['id_area']} LIMIT 1")->fetchColumn();
    $r = guardar('actualizar', array_merge($post, [
        'id_trabajador' => (string)$t['id_trabajador'],
        'numero_documento' => $t['numero_documento'],
        'correo_personal' => $t['correo_personal'],
        'id_cargo' => (string)$otroCargo,
    ]));
    afirmar(isset($r['errores']['id_cargo']), 'Debería rechazar el cargo');
});

prueba('la base de datos queda igual después de las pruebas', function () use ($conexion, $totalAntes) {
    $totalDespues = (int)$conexion->query('SELECT COUNT(*) FROM trabajadores')->fetchColumn();
    afirmar($totalDespues === $totalAntes, "Había $totalAntes trabajadores y ahora hay $totalDespues");
    $nombre = $conexion->query("SELECT COUNT(*) FROM trabajadores WHERE nombres = 'Nombre Editado'")->fetchColumn();
    afirmar((int)$nombre === 0, 'Quedó un cambio de prueba sin deshacer');
});

echo "\n" . ($total - $fallos) . " de $total pruebas pasaron.\n";
exit($fallos > 0 ? 1 : 0);
