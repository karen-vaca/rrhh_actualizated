<?php
require_once __DIR__ . '/../config/auth.php';
soloConsola();
// Tests de validación del formulario de contratación.
// Ejecutar: php tests/ValidacionContratoTest.php
// Las pruebas "servidor" ejecutan el guardar.php real (sin JavaScript) con datos
// inválidos y verifican que se rechacen sin escribir nada en la base.

require __DIR__ . '/../config/conexion.php';
require __DIR__ . '/../views/contratacion/validaciones_contrato.php';

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

function montoValido(string $texto, float $esperado): void {
    $r = validarMonto($texto, 'El salario', true, false);
    afirmar($r['error'] === null, "\"$texto\" debería ser válido, error: {$r['error']}");
    afirmar(abs($r['valor'] - $esperado) < 0.001, "\"$texto\" debería valer $esperado, vale {$r['valor']}");
}

function montoInvalido(string $texto, string $contiene, bool $obligatorio = true, bool $permiteCero = false): void {
    $r = validarMonto($texto, 'El salario', $obligatorio, $permiteCero);
    afirmar($r['error'] !== null, "\"$texto\" debería ser rechazado y se aceptó como {$r['valor']}");
    afirmar(str_contains($r['error'], $contiene), "El error de \"$texto\" debería mencionar '$contiene', dice: {$r['error']}");
}

// Ejecuta guardar.php real con un POST (sin JavaScript) y devuelve su respuesta.
function servidor(array $post): array {
    $archivo = tempnam(sys_get_temp_dir(), 'contrato');
    file_put_contents($archivo, json_encode($post));
    $salida = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/ejecutar_contrato.php') . ' ' . escapeshellarg($archivo) . ' 2>&1');
    unlink($archivo);
    $pos = strrpos((string)$salida, '@@RESULTADO@@');
    afirmar($pos !== false, "Salida inesperada:\n$salida");
    $r = json_decode(substr($salida, $pos + strlen('@@RESULTADO@@')), true);
    afirmar($r['contratos_sin_cambios'] === true, 'La tabla contratos cambió durante la prueba');
    return $r['respuesta'] ?? [];
}

function rechazoServidor(array $cambios, string $campo, string $contiene): void {
    global $postBase;
    $r = servidor(array_merge($postBase, $cambios));
    afirmar(($r['ok'] ?? null) === false, 'El servidor debería rechazar, respondió: ' . json_encode($r, JSON_UNESCAPED_UNICODE));
    afirmar(($r['mensaje'] ?? '') === 'validacion' && ($r['campo'] ?? '') === $campo,
        "Debería rechazar el campo '$campo', respondió: " . json_encode($r, JSON_UNESCAPED_UNICODE));
    afirmar(str_contains($r['texto'] ?? '', $contiene), "El mensaje debería mencionar '$contiene', dice: " . ($r['texto'] ?? ''));
}

$trabajador = (int)$conexion->query('SELECT id_trabajador FROM trabajadores ORDER BY id_trabajador LIMIT 1')->fetchColumn();
$tipoContrato = (int)$conexion->query('SELECT id_tipos_contrato FROM tipos_contrato ORDER BY id_tipos_contrato LIMIT 1')->fetchColumn();
$postBase = [
    'accion' => 'nuevo', 'id_trabajador' => (string)$trabajador, 'id_area' => '1', 'id_cargo' => '1',
    'id_tipos_contrato' => (string)$tipoContrato, 'fecha_ingreso' => '2026-01-01',
    'fecha_inicio' => '2026-01-01', 'fecha_fin' => '2026-12-31',
    'salario_base' => '1300000', 'auxilio_transporte' => '200000',
    'jefe_inmediato' => 'Daniel Avila', 'jornada' => 'Completa (46h/sem)', 'modalidad' => 'Presencial',
    'periodo_prueba' => 'Sin periodo',
];

echo "Validación de contratación\n";

// ── Montos: formatos válidos ──
prueba('salario solo con números', fn() => montoValido('1300000', 1300000));
prueba('salario con puntos de miles', fn() => montoValido('1.300.000', 1300000));
prueba('salario con miles y decimales (1.300.000,50)', fn() => montoValido('1.300.000,50', 1300000.50));
prueba('salario con coma decimal (1300000,50)', fn() => montoValido('1300000,50', 1300000.50));
prueba('salario con punto decimal (1300000.50)', fn() => montoValido('1300000.50', 1300000.50));
prueba('salario con espacios alrededor', fn() => montoValido('  1300000 ', 1300000));

// ── Montos: se rechazan (antes se "limpiaban" en silencio) ──
prueba('salario "2O00000" con letra O (antes se guardaba como 200000)', fn() => montoInvalido('2O00000', 'sin letras'));
prueba('salario con símbolo $', fn() => montoInvalido('$1300000', 'sin letras'));
prueba('salario con espacio interno', fn() => montoInvalido('1 300 000', 'sin letras'));
prueba('salario con letras al final ("1300000abc")', fn() => montoInvalido('1300000abc', 'sin letras'));
prueba('salario negativo', fn() => montoInvalido('-1300000', 'negativo'));
prueba('salario con puntos mal ubicados ("13.00.000")', fn() => montoInvalido('13.00.000', 'formato de número válido'));
prueba('salario en cero', fn() => montoInvalido('0', 'mayor que cero'));
prueba('salario vacío', fn() => montoInvalido('', 'obligatorio'));
prueba('salario por encima del máximo', fn() => montoInvalido('100000000', 'máximo'));
prueba('auxilio vacío se toma como 0', function () {
    $r = validarMonto('', 'El auxilio de transporte', false, true);
    afirmar($r['error'] === null && $r['valor'] === 0.0, 'Auxilio vacío debería valer 0');
});
prueba('auxilio en 0 es válido', function () {
    $r = validarMonto('0', 'El auxilio de transporte', false, true);
    afirmar($r['error'] === null && $r['valor'] === 0.0, 'Auxilio 0 debería ser válido');
});
prueba('auxilio "2OOOOO" con letras O', fn() => montoInvalido('2OOOOO', 'sin letras', false, true));

// ── Auxilio de transporte legal 2026 ($249.095 hasta 2 salarios mínimos) ──
prueba('auxilio legal: valores vigentes 2026', fn() => afirmar(SALARIO_MINIMO_VIGENTE === 1750905 && AUXILIO_TRANSPORTE_VIGENTE === 249095, 'Valores legales inesperados'));
prueba('auxilio legal: salario de $2.000.000 tiene auxilio de $249.095', fn() => afirmar(auxilioTransporteLegal(2000000) === 249095.0, 'Debería ser 249095'));
prueba('auxilio legal: salario mínimo tiene auxilio', fn() => afirmar(auxilioTransporteLegal(1750905) === 249095.0, 'Debería ser 249095'));
prueba('auxilio legal: exactamente 2 salarios mínimos ($3.501.810) sí tiene auxilio', fn() => afirmar(auxilioTransporteLegal(3501810) === 249095.0, 'El tope es inclusivo'));
prueba('auxilio legal: un peso por encima de 2 mínimos ya no tiene auxilio', fn() => afirmar(auxilioTransporteLegal(3501811) === 0.0, 'Debería ser 0'));
prueba('auxilio legal: sin salario no hay auxilio', fn() => afirmar(auxilioTransporteLegal(0) === 0.0, 'Debería ser 0'));
prueba('contratos #3, #6 y #12 quedaron con el auxilio legal y la nota de corrección', function () use ($conexion) {
    foreach ($conexion->query('SELECT id_contrato, salario_base, auxilio_transporte, observaciones FROM contratos WHERE id_contrato IN (3, 6, 12)') as $c) {
        afirmar((float)$c['auxilio_transporte'] === auxilioTransporteLegal((float)$c['salario_base']), "Contrato #{$c['id_contrato']} con auxilio {$c['auxilio_transporte']}");
        afirmar(str_contains((string)$c['observaciones'], 'Auxilio de transporte corregido'), "Contrato #{$c['id_contrato']} sin nota de corrección");
    }
});

prueba('todos los contratos tienen el auxilio legal que corresponde a su salario', function () use ($conexion) {
    foreach ($conexion->query('SELECT id_contrato, salario_base, auxilio_transporte FROM contratos') as $c) {
        $legal = auxilioTransporteLegal((float)$c['salario_base']);
        afirmar((float)$c['auxilio_transporte'] === $legal, "Contrato #{$c['id_contrato']}: auxilio {$c['auxilio_transporte']}, corresponde $legal");
    }
});
prueba('contratos #3 y #6: salario mínimo + auxilio = $2.000.000 en total', function () use ($conexion) {
    foreach ($conexion->query('SELECT id_contrato, salario_base, auxilio_transporte FROM contratos WHERE id_contrato IN (3, 6)') as $c) {
        afirmar((float)$c['salario_base'] === (float)SALARIO_MINIMO_VIGENTE && (float)$c['salario_base'] + (float)$c['auxilio_transporte'] === 2000000.0,
            "Contrato #{$c['id_contrato']}: {$c['salario_base']} + {$c['auxilio_transporte']}");
    }
});

// ── Fechas del contrato ──
$errFechas = fn(array $c) => validarFechasContrato(array_merge(['fecha_inicio' => '2026-01-01', 'fecha_fin' => '2026-12-31'], $c))['errores'];
prueba('fechas: fin posterior al inicio es válido', fn() => afirmar($errFechas([]) === [], 'No debería haber errores'));
prueba('fechas: fin el mismo día del inicio es válido (contrato de un día)', fn() => afirmar($errFechas(['fecha_fin' => '2026-01-01']) === [], 'Mismo día debería ser válido'));
prueba('fechas: sin fecha de fin es válido (término indefinido)', fn() => afirmar($errFechas(['fecha_fin' => '']) === [], 'Sin fin debería ser válido'));
prueba('fechas: fin anterior al inicio', function () use ($errFechas) {
    $e = $errFechas(['fecha_fin' => '2025-12-31']);
    afirmar(str_contains($e['fecha_fin'] ?? '', 'anterior'), 'Debería rechazar fin < inicio: ' . json_encode($e, JSON_UNESCAPED_UNICODE));
});
prueba('fechas: inicio inexistente (2026-02-30)', function () use ($errFechas) {
    $e = $errFechas(['fecha_inicio' => '2026-02-30']);
    afirmar(str_contains($e['fecha_inicio'] ?? '', 'no existe'), 'Debería rechazar 30 de febrero');
});
prueba('fechas: fin inexistente (2026-04-31)', function () use ($errFechas) {
    $e = $errFechas(['fecha_fin' => '2026-04-31']);
    afirmar(str_contains($e['fecha_fin'] ?? '', 'no existe'), 'Debería rechazar 31 de abril');
});
prueba('fechas: inicio obligatorio', fn() => afirmar(isset($errFechas(['fecha_inicio' => ''])['fecha_inicio']), 'Inicio vacío debería rechazarse'));
prueba('fechas: formato distinto a AAAA-MM-DD', fn() => afirmar(isset($errFechas(['fecha_inicio' => '01/01/2026'])['fecha_inicio']), 'Formato dd/mm/aaaa debería rechazarse'));

// ── Jefe inmediato ──
$jefe = fn(string $v) => validarJefeInmediato($v);
prueba('jefe: nombre normal con tildes', fn() => afirmar($jefe('María José Peña')['error'] === null, 'Debería ser válido'));
prueba('jefe: con punto, guion y apóstrofo ("Ing. Ana-María O\'Neil")', fn() => afirmar($jefe("Ing. Ana-María O'Neil")['error'] === null, 'Debería ser válido'));
prueba('jefe: espacios repetidos se normalizan', function () use ($jefe) {
    $r = $jefe('  Daniel    Avila ');
    afirmar($r['error'] === null && $r['valor'] === 'Daniel Avila', 'Debería quedar "Daniel Avila", quedó ' . var_export($r['valor'], true));
});
prueba('jefe: con números', fn() => afirmar(str_contains((string)$jefe('Daniel 123')['error'], 'sin números'), 'Debería rechazar números'));
prueba('jefe: con símbolos', fn() => afirmar(str_contains((string)$jefe('Daniel @vila')['error'], 'sin números ni símbolos'), 'Debería rechazar @'));
prueba('jefe: vacío', fn() => afirmar(str_contains((string)$jefe('')['error'], 'obligatorio'), 'Debería ser obligatorio'));
prueba('jefe: muy corto', fn() => afirmar(str_contains((string)$jefe('Al')['error'], 'entre 3 y 120'), 'Debería rechazar 2 letras'));
prueba('jefe: más de 120 caracteres', fn() => afirmar(str_contains((string)$jefe(str_repeat('a', 121))['error'], 'entre 3 y 120'), 'Debería rechazar 121'));

// ── Servidor real, sin JavaScript ──
prueba('servidor: fecha de fin anterior al inicio se rechaza y no escribe', fn() => rechazoServidor(['fecha_inicio' => '2026-06-01', 'fecha_fin' => '2026-05-01'], 'fecha_fin', 'anterior'));
prueba('servidor: fecha de inicio inexistente (2026-02-30) se rechaza y no escribe', fn() => rechazoServidor(['fecha_inicio' => '2026-02-30'], 'fecha_inicio', 'no existe'));
prueba('servidor: jefe inmediato con números se rechaza y no escribe', fn() => rechazoServidor(['jefe_inmediato' => 'Jefe 123'], 'jefe_inmediato', 'sin números'));
prueba('servidor: jefe inmediato vacío se rechaza y no escribe', fn() => rechazoServidor(['jefe_inmediato' => ''], 'jefe_inmediato', 'obligatorio'));
prueba('servidor: "nuevo" con salario "2O00000" se rechaza y no escribe', fn() => rechazoServidor(['salario_base' => '2O00000'], 'salario_base', 'sin letras'));
prueba('servidor: "nuevo" con auxilio "2OOOOO" se rechaza y no escribe', fn() => rechazoServidor(['auxilio_transporte' => '2OOOOO'], 'auxilio_transporte', 'sin letras'));
prueba('servidor: "actualizar" con salario "1.3OO.OOO" se rechaza y no escribe', fn() => rechazoServidor(['accion' => 'actualizar', 'contrato_id' => '1', 'salario_base' => '1.3OO.OOO'], 'salario_base', 'sin letras'));
prueba('servidor: "renovar" con salario negativo se rechaza y no escribe', fn() => rechazoServidor(['accion' => 'renovar', 'contrato_id' => '1', 'salario_base' => '-5'], 'salario_base', 'negativo'));

echo "\n" . ($total - $fallos) . " de $total pruebas pasaron.\n";
exit($fallos > 0 ? 1 : 0);
