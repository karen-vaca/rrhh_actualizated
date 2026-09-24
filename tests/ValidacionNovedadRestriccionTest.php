<?php
require_once __DIR__ . '/../config/auth.php';
soloConsola();
// Tests de validación de fechas (compartida), Novedades y Restricciones médicas.
// (Los de Exámenes Médicos están en tests/ExamenesTest.php.)
// Ejecutar: php tests/ValidacionNovedadRestriccionTest.php
// Las pruebas "servidor" ejecutan las páginas reales con php-cgi (sesión, CSRF, sin
// JavaScript) con datos inválidos y verifican que se rechacen sin escribir nada.

require __DIR__ . '/../config/conexion.php';
require __DIR__ . '/../views/components/validaciones_fechas.php';
require __DIR__ . '/../views/perfil_salud/validaciones_restriccion.php';
require __DIR__ . '/ayudante_web.php';

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

// Ejecuta la página real y comprueba que rechaza con un texto y que las tablas no cambian.
function rechazoWeb(string $script, array $post, array $tablas, string $contiene): void {
    global $conexion;
    $antes = huellaTablas($conexion, $tablas);
    $r = ejecutarComoWeb($script, $post);
    $q = parametrosRedireccion($r['location']);
    $texto = ($q['texto'] ?? '') . ' ' . ($q['mensaje'] ?? '');
    afirmar($r['status'] === 302 && str_contains($texto, $contiene),
        "Debería rechazar mencionando '$contiene'. Respuesta: {$r['status']} {$r['location']}");
    afirmar(huellaTablas($conexion, $tablas) === $antes, 'Las tablas ' . implode(', ', $tablas) . ' cambiaron');
}

$rango = fn(array $c) => validarRangoFechas(array_merge(['fecha_inicio' => '2026-03-01', 'fecha_fin' => '2026-03-10'], $c))['errores'];
$restr = fn(array $c) => validarRestriccion(array_merge(['tipo' => 'Otra', 'fecha_inicio' => '2026-03-01', 'fecha_fin' => '', 'descripcion' => 'Concepto médico'], $c))['errores'];

echo "Validación de fechas (compartida)\n";
prueba('rango válido', fn() => afirmar($rango([]) === [], 'No debería haber errores'));
prueba('mismo día de inicio y fin es válido', fn() => afirmar($rango(['fecha_fin' => '2026-03-01']) === [], 'Mismo día debería ser válido'));
prueba('29 de febrero en año bisiesto (2028) es válido', fn() => afirmar($rango(['fecha_inicio' => '2028-02-29', 'fecha_fin' => '']) === [], '2028-02-29 existe'));
prueba('29 de febrero en año no bisiesto (2026) se rechaza', fn() => afirmar(str_contains($rango(['fecha_inicio' => '2026-02-29'])['fecha_inicio'] ?? '', 'no existe'), 'Debería rechazar'));
prueba('30 de febrero se rechaza (antes se convertía en 2 de marzo)', function () use ($rango) {
    $r = validarRangoFechas(['fecha_inicio' => '2026-02-30', 'fecha_fin' => '']);
    afirmar(isset($r['errores']['fecha_inicio']) && $r['datos']['fecha_inicio'] === null, 'No debería quedar ninguna fecha "corregida"');
});
prueba('fin anterior al inicio', fn() => afirmar(str_contains($rango(['fecha_fin' => '2026-02-28'])['fecha_fin'] ?? '', 'anterior'), 'Debería rechazar'));
prueba('fecha con texto', fn() => afirmar(isset($rango(['fecha_inicio' => 'mañana'])['fecha_inicio']), 'Debería rechazar'));

echo "\nNovedades\n";
$novedad = ['accion' => 'crear', 'id_trabajador' => '1', 'categoria' => 'Laboral', 'tipo_novedad' => 'Permiso',
            'fecha_inicio' => '2026-03-01', 'fecha_fin' => '2026-03-02', 'descripcion' => 'Prueba', 'estado' => 'Pendiente'];
prueba('servidor (pantalla real): crear con 2026-02-30 se rechaza y no escribe',
    fn() => rechazoWeb('views/novedades/index.php', array_merge($novedad, ['fecha_inicio' => '2026-02-30']), ['novedades'], 'no existe'));
prueba('servidor (pantalla real): crear con fin anterior al inicio se rechaza y no escribe',
    fn() => rechazoWeb('views/novedades/index.php', array_merge($novedad, ['fecha_fin' => '2026-02-01']), ['novedades'], 'anterior'));
prueba('servidor (pantalla real): actualizar con fin anterior al inicio se rechaza y no escribe',
    fn() => rechazoWeb('views/novedades/index.php', array_merge($novedad, ['accion' => 'actualizar', 'id_novedad' => '1', 'fecha_fin' => '2026-02-01']), ['novedades'], 'anterior'));
prueba('servidor (guardar.php alterno): nuevo con 2026-04-31 se rechaza y no escribe',
    fn() => rechazoWeb('views/novedades/guardar.php', array_merge($novedad, ['accion' => 'nuevo', 'fecha_inicio' => '2026-04-31']), ['novedades'], 'no existe'));
prueba('servidor (guardar.php alterno): editar con fin anterior al inicio se rechaza y no escribe',
    fn() => rechazoWeb('views/novedades/guardar.php', array_merge($novedad, ['accion' => 'editar', 'id_novedad' => '1', 'fecha_fin' => '2026-02-01']), ['novedades'], 'anterior'));
prueba('servidor: sin token CSRF se rechaza (403) y no escribe', function () use ($conexion, $novedad) {
    $antes = huellaTablas($conexion, ['novedades']);
    $r = ejecutarComoWeb('views/novedades/index.php', $novedad, 'POST', false);
    afirmar($r['status'] === 403, "Debería responder 403, respondió {$r['status']}");
    afirmar(huellaTablas($conexion, ['novedades']) === $antes, 'La tabla novedades cambió');
});

echo "\nRestricciones médicas (Perfil de salud)\n";
prueba('restricción válida', fn() => afirmar($restr([]) === [], 'No debería haber errores: ' . json_encode($restr([]), JSON_UNESCAPED_UNICODE)));
prueba('cada tipo de la lista es válido', function () use ($restr) {
    foreach (TIPOS_RESTRICCION as $t) {
        afirmar($restr(['tipo' => $t]) === [], "\"$t\" debería ser válido");
    }
});
prueba('tipo fuera de la lista', fn() => afirmar(str_contains($restr(['tipo' => 'Inventado'])['tipo'] ?? '', 'no es válido'), 'Debería rechazar'));
prueba('tipo vacío', fn() => afirmar(isset($restr(['tipo' => ''])['tipo']), 'Debería rechazar'));
prueba('fecha de inicio inexistente', fn() => afirmar(str_contains($restr(['fecha_inicio' => '2026-06-31'])['fecha_inicio'] ?? '', 'no existe'), 'Debería rechazar'));
prueba('fecha de inicio que no es fecha', fn() => afirmar(isset($restr(['fecha_inicio' => 'hoy'])['fecha_inicio']), 'Debería rechazar'));
prueba('fin anterior al inicio', fn() => afirmar(str_contains($restr(['fecha_fin' => '2026-02-01'])['fecha_fin'] ?? '', 'anterior'), 'Debería rechazar'));
prueba('descripción vacía', fn() => afirmar(isset($restr(['descripcion' => '  '])['descripcion']), 'Debería rechazar'));

$restriccion = ['id_trabajador' => '1', 'tipo' => 'Otra', 'fecha_inicio' => '2026-03-01', 'fecha_fin' => '', 'descripcion' => 'Prueba'];
$tablasSalud = ['restricciones_medicas', 'historial_medico'];
prueba('servidor: tipo fuera de la lista se rechaza y no escribe',
    fn() => rechazoWeb('views/perfil_salud/guardar_restriccion.php', array_merge($restriccion, ['tipo' => 'Inventado']), $tablasSalud, 'no es válido'));
prueba('servidor: fecha "hoy" se rechaza y no escribe',
    fn() => rechazoWeb('views/perfil_salud/guardar_restriccion.php', array_merge($restriccion, ['fecha_inicio' => 'hoy']), $tablasSalud, 'no existe'));
prueba('servidor: fin anterior al inicio se rechaza y no escribe',
    fn() => rechazoWeb('views/perfil_salud/guardar_restriccion.php', array_merge($restriccion, ['fecha_fin' => '2026-01-01']), $tablasSalud, 'anterior'));

echo "\n" . ($total - $fallos) . " de $total pruebas pasaron.\n";
exit($fallos > 0 ? 1 : 0);
