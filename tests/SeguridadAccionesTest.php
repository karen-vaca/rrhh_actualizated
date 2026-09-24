<?php
require_once __DIR__ . '/../config/auth.php';
soloConsola();
// Tests de seguridad de las acciones que cambian datos: activar/inactivar trabajador,
// finalizar restricción médica y familiares. Verifican que solo respondan a POST con
// sesión, rol Administrador y token CSRF válido (config/auth.php).
// Ejecutar: php tests/SeguridadAccionesTest.php
// Se ejecutan las páginas reales con php-cgi. Los casos que deben aceptarse usan un id
// inexistente, así que nunca cambian datos; aun así se comprueba que la tabla no cambie.

require __DIR__ . '/../config/conexion.php';
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

// Ejecuta la acción y comprueba el código HTTP esperado y que las tablas no cambien.
function accion(string $script, array $datos, int $esperado, array $tablas, string $metodo = 'POST', bool $conToken = true, ?int $rol = ROL_ADMINISTRADOR): array {
    global $conexion;
    $antes = huellaTablas($conexion, $tablas);
    $r = ejecutarComoWeb($script, $datos, $metodo, $conToken, $rol);
    afirmar($r['status'] === $esperado, "Debería responder $esperado, respondió {$r['status']} {$r['location']}");
    afirmar(huellaTablas($conexion, $tablas) === $antes, 'Cambió la tabla ' . implode(', ', $tablas));
    return $r;
}

// Un trabajador real, para comprobar que los intentos rechazados no lo tocan.
$real = (string)$conexion->query('SELECT id_trabajador FROM trabajadores ORDER BY id_trabajador LIMIT 1')->fetchColumn();
$inexistente = '999999';

echo "Seguridad de activar / inactivar trabajador\n";
foreach (['activar', 'inactivar'] as $a) {
    $script = "views/trabajadores/$a.php";
    prueba("$a: un enlace GET no ejecuta la acción (405)", fn() => accion($script, ['id' => $real, 'id_trabajador' => $real], 405, ['trabajadores'], 'GET'));
    prueba("$a: POST sin token CSRF se rechaza (403)", fn() => accion($script, ['id_trabajador' => $real], 403, ['trabajadores'], 'POST', false));
    prueba("$a: POST con token CSRF falso se rechaza (403)", fn() => accion($script, ['id_trabajador' => $real, 'csrf_token' => str_repeat('a', 64)], 403, ['trabajadores'], 'POST', false));
    prueba("$a: POST sin sesión se rechaza (401)", fn() => accion($script, ['id_trabajador' => $real], 401, ['trabajadores'], 'POST', true, null));
    prueba("$a: POST de un Colaborador se rechaza (403)", fn() => accion($script, ['id_trabajador' => $real], 403, ['trabajadores'], 'POST', true, ROL_COLABORADOR));
    prueba("$a: POST de Administrador con token válido se acepta", function () use ($script, $inexistente, $a) {
        $r = accion($script, ['id_trabajador' => $inexistente], 302, ['trabajadores']);
        $q = parametrosRedireccion($r['location']);
        afirmar(in_array($q['mensaje'] ?? '', ['reactivado', 'inactivado'], true), "Debería redirigir con el mensaje de éxito, fue: {$r['location']}");
    });
}

echo "\nOtras acciones que antes respondían a GET\n";
prueba('finalizar restricción: un enlace GET no ejecuta la acción (405)',
    fn() => accion('views/perfil_salud/finalizar_restriccion.php', ['id' => '1', 'trabajador' => $real], 405, ['restricciones_medicas', 'historial_medico'], 'GET'));
prueba('familiares: "eliminar" por GET no se ejecuta (405)',
    fn() => accion('views/trabajadores/familiares.php', ['action' => 'eliminar', 'trabajador_id' => $real, 'id' => '1'], 405, ['familiares_trabajador'], 'GET'));
prueba('familiares: "listar" por GET sigue permitido (200)',
    fn() => accion('views/trabajadores/familiares.php', ['action' => 'listar', 'trabajador_id' => $real], 200, ['familiares_trabajador'], 'GET'));

echo "\n" . ($total - $fallos) . " de $total pruebas pasaron.\n";
exit($fallos > 0 ? 1 : 0);
