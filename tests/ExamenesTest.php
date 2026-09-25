<?php
require_once __DIR__ . '/../config/auth.php';
soloConsola();
// Tests de Exámenes Médicos: reglas, guardado real (examen + perfil de salud +
// historial) y endpoints. Ejecutar: php tests/ExamenesTest.php
// Los casos de guardado se ejecutan dentro de una transacción que se deshace al final
// de cada prueba; los de servidor usan php-cgi (sesión, CSRF, sin JavaScript) con datos
// inválidos y comprueban que las tablas no cambien.

require __DIR__ . '/../config/conexion.php';
require __DIR__ . '/../views/examenes/servicio_examenes.php';
require __DIR__ . '/ayudante_web.php';

$fallos = 0;
$total = 0;

function prueba(string $nombre, callable $fn): void {
    global $fallos, $total, $conexion;
    $total++;
    try {
        $fn();
        echo "  OK    $nombre\n";
    } catch (Throwable $e) {
        $fallos++;
        echo "  FALLA $nombre\n        " . $e->getMessage() . "\n";
    } finally {
        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }
    }
}

function afirmar(bool $condicion, string $mensaje): void {
    if (!$condicion) {
        throw new Exception($mensaje);
    }
}

function tieneError(array $errores, string $campo, string $contiene = ''): void {
    afirmar(isset($errores[$campo]), "Se esperaba error en '$campo'. Errores: " . json_encode($errores, JSON_UNESCAPED_UNICODE));
    afirmar($contiene === '' || str_contains($errores[$campo], $contiene), "El error de '$campo' debería mencionar '$contiene', dice: {$errores[$campo]}");
}

// Programa un examen dentro de la transacción de la prueba y devuelve su id.
function programar(array $cambios = []): int {
    global $conexion, $trabajador;
    $r = programarExamen($conexion, array_merge(['id_trabajador' => (string)$trabajador, 'tipo' => 'periodico', 'fecha_programada' => date('Y-m-d')], $cambios));
    afirmar($r['ok'], 'No se pudo programar: ' . json_encode($r['errores'], JSON_UNESCAPED_UNICODE));
    return $r['id'];
}

function examen(int $id): array {
    global $conexion;
    $s = $conexion->prepare('SELECT * FROM examenes_medicos WHERE id_examenes_medicos = ?');
    $s->execute([$id]);
    return $s->fetch(PDO::FETCH_ASSOC);
}

function perfil(int $idTrabajador): ?array {
    global $conexion;
    $s = $conexion->prepare('SELECT * FROM perfil_salud WHERE id_trabajador = ?');
    $s->execute([$idTrabajador]);
    return $s->fetch(PDO::FETCH_ASSOC) ?: null;
}

$trabajador = (int)$conexion->query('SELECT id_trabajador FROM trabajadores WHERE estado = 1 ORDER BY id_trabajador LIMIT 1')->fetchColumn();
$ayer = date('Y-m-d', strtotime('-1 day'));
$hace = fn(string $i) => date('Y-m-d', strtotime($i));

echo "Catálogo\n";
prueba('tipos: Ingreso, Periódico, Retiro y Post-incapacidad', function () use ($conexion) {
    $t = tiposExamen($conexion);
    afirmar(array_keys($t) === ['ingreso', 'periodico', 'retiro', 'post_incapacidad'], 'Tipos: ' . implode(', ', array_keys($t)));
});
prueba('periodicidad por defecto de 1 año (retiro no genera próximo examen)', function () use ($conexion) {
    $t = tiposExamen($conexion);
    afirmar($t['ingreso']['meses'] === 12 && $t['periodico']['meses'] === 12 && $t['post_incapacidad']['meses'] === 12 && $t['retiro']['meses'] === null, 'Vigencias inesperadas');
});
prueba('resultados iguales a los de perfil de salud', function () use ($conexion) {
    $col = $conexion->query("SHOW COLUMNS FROM perfil_salud LIKE 'aptitud'")->fetch(PDO::FETCH_ASSOC);
    afirmar($col['Type'] === "enum('" . implode("','", array_keys(RESULTADOS_EXAMEN)) . "')", "perfil_salud.aptitud es {$col['Type']}");
});

echo "\nProgramar examen\n";
prueba('programación válida', function () use ($conexion) {
    $conexion->beginTransaction();
    $e = examen(programar());
    afirmar($e['estado'] === 'programado' && $e['fecha_programada'] === date('Y-m-d') && $e['resultado'] === null, 'Examen mal guardado: ' . json_encode($e));
});
prueba('tipo inexistente', fn() => tieneError(validarProgramacionExamen($conexion, ['id_trabajador' => (string)$trabajador, 'tipo' => 'egreso', 'fecha_programada' => date('Y-m-d')])['errores'], 'tipo'));
prueba('trabajador inexistente', fn() => tieneError(validarProgramacionExamen($conexion, ['id_trabajador' => '999999', 'tipo' => 'ingreso', 'fecha_programada' => date('Y-m-d')])['errores'], 'id_trabajador', 'no existe'));
prueba('fecha programada inexistente (2026-02-30)', fn() => tieneError(validarProgramacionExamen($conexion, ['id_trabajador' => (string)$trabajador, 'tipo' => 'ingreso', 'fecha_programada' => '2026-02-30'])['errores'], 'fecha_programada', 'no existe'));
prueba('no permite dos exámenes del mismo tipo programados a la vez', function () use ($conexion, $trabajador) {
    $conexion->beginTransaction();
    programar();
    $r = programarExamen($conexion, ['id_trabajador' => (string)$trabajador, 'tipo' => 'periodico', 'fecha_programada' => date('Y-m-d')]);
    tieneError($r['errores'], 'tipo', 'ya tiene');
});
prueba('sí permite otro tipo mientras hay uno programado', function () use ($conexion, $trabajador) {
    $conexion->beginTransaction();
    programar();
    $r = programarExamen($conexion, ['id_trabajador' => (string)$trabajador, 'tipo' => 'post_incapacidad', 'fecha_programada' => date('Y-m-d')]);
    afirmar($r['ok'], 'Debería permitir otro tipo');
});

echo "\nRegistrar resultado (examen + perfil de salud + historial)\n";
prueba('resultado válido actualiza examen, perfil de salud e historial', function () use ($conexion, $trabajador, $ayer) {
    $conexion->beginTransaction();
    $historialAntes = (int)$conexion->query("SELECT COUNT(*) FROM historial_medico WHERE id_trabajador = $trabajador")->fetchColumn();
    $id = programar();
    $r = registrarResultadoExamen($conexion, ['id_examen' => (string)$id, 'fecha_realizado' => $ayer, 'resultado' => 'restriccion', 'concepto_alturas' => 'no_apto', 'observaciones' => 'Prueba']);
    afirmar($r['ok'], 'Errores: ' . json_encode($r['errores'], JSON_UNESCAPED_UNICODE));
    $e = examen($id);
    $proxima = date('Y-m-d', strtotime("$ayer +12 months"));
    afirmar($e['estado'] === 'realizado' && $e['resultado'] === 'restriccion' && $e['concepto_alturas'] === 'no_apto', 'Examen: ' . json_encode($e));
    afirmar($e['proxima_fecha'] === $proxima, "Próximo examen debería ser $proxima, es {$e['proxima_fecha']}");
    $p = perfil($trabajador);
    afirmar($p && $p['aptitud'] === 'restriccion' && $p['fecha_evaluacion'] === $ayer && $p['fecha_vencimiento'] === $proxima, 'Perfil: ' . json_encode($p));
    $historialDespues = (int)$conexion->query("SELECT COUNT(*) FROM historial_medico WHERE id_trabajador = $trabajador")->fetchColumn();
    afirmar($historialDespues === $historialAntes + 1, 'Debería agregarse 1 evento al historial');
});
prueba('próxima fecha escrita a mano tiene prioridad sobre la de 1 año', function () use ($conexion, $ayer, $hace) {
    $conexion->beginTransaction();
    $id = programar();
    registrarResultadoExamen($conexion, ['id_examen' => (string)$id, 'fecha_realizado' => $ayer, 'resultado' => 'apto', 'proxima_fecha' => $hace('+6 months')]);
    afirmar(examen($id)['proxima_fecha'] === $hace('+6 months'), 'Debería respetar la fecha escrita');
});
prueba('examen de retiro no genera próximo examen', function () use ($conexion, $trabajador, $ayer) {
    $conexion->beginTransaction();
    $id = programar(['tipo' => 'retiro']);
    registrarResultadoExamen($conexion, ['id_examen' => (string)$id, 'fecha_realizado' => $ayer, 'resultado' => 'apto']);
    afirmar(examen($id)['proxima_fecha'] === null && perfil($trabajador)['fecha_vencimiento'] === null, 'Retiro no debería tener vencimiento');
});
prueba('resultado "pendiente" deja el perfil en pendiente', function () use ($conexion, $trabajador, $ayer) {
    $conexion->beginTransaction();
    $id = programar();
    registrarResultadoExamen($conexion, ['id_examen' => (string)$id, 'fecha_realizado' => $ayer, 'resultado' => 'pendiente']);
    afirmar(perfil($trabajador)['aptitud'] === 'pendiente', 'Perfil debería quedar pendiente');
});
prueba('registrar tarde un examen más viejo no pisa el perfil del más reciente', function () use ($conexion, $trabajador, $ayer, $hace) {
    $conexion->beginTransaction();
    $nuevo = programar();
    $viejo = programar(['tipo' => 'post_incapacidad']);
    registrarResultadoExamen($conexion, ['id_examen' => (string)$nuevo, 'fecha_realizado' => $ayer, 'resultado' => 'apto']);
    registrarResultadoExamen($conexion, ['id_examen' => (string)$viejo, 'fecha_realizado' => $hace('-3 months'), 'resultado' => 'no_apto']);
    $p = perfil($trabajador);
    afirmar($p['aptitud'] === 'apto' && $p['fecha_evaluacion'] === $ayer, 'El perfil debería conservar el examen más reciente: ' . json_encode($p));
});
prueba('no se puede registrar dos veces el mismo examen', function () use ($conexion, $ayer) {
    $conexion->beginTransaction();
    $id = programar();
    registrarResultadoExamen($conexion, ['id_examen' => (string)$id, 'fecha_realizado' => $ayer, 'resultado' => 'apto']);
    $r = registrarResultadoExamen($conexion, ['id_examen' => (string)$id, 'fecha_realizado' => $ayer, 'resultado' => 'no_apto']);
    tieneError($r['errores'], 'id_examen', 'ya tiene');
});
prueba('fecha de realización futura', function () use ($conexion, $hace) {
    $conexion->beginTransaction();
    tieneError(validarResultadoExamen($conexion, ['id_examen' => (string)programar(), 'fecha_realizado' => $hace('+1 day'), 'resultado' => 'apto'])['errores'], 'fecha_realizado', 'futura');
});
prueba('próximo examen anterior o igual a la realización', function () use ($conexion, $ayer) {
    $conexion->beginTransaction();
    tieneError(validarResultadoExamen($conexion, ['id_examen' => (string)programar(), 'fecha_realizado' => $ayer, 'resultado' => 'apto', 'proxima_fecha' => $ayer])['errores'], 'proxima_fecha', 'posterior');
});
prueba('resultado fuera de la lista', function () use ($conexion, $ayer) {
    $conexion->beginTransaction();
    tieneError(validarResultadoExamen($conexion, ['id_examen' => (string)programar(), 'fecha_realizado' => $ayer, 'resultado' => 'bueno'])['errores'], 'resultado');
});
prueba('concepto de alturas fuera de la lista', function () use ($conexion, $ayer) {
    $conexion->beginTransaction();
    tieneError(validarResultadoExamen($conexion, ['id_examen' => (string)programar(), 'fecha_realizado' => $ayer, 'resultado' => 'apto', 'concepto_alturas' => 'tal vez'])['errores'], 'concepto_alturas');
});
prueba('examen inexistente', fn() => tieneError(validarResultadoExamen($conexion, ['id_examen' => '999999', 'fecha_realizado' => $ayer, 'resultado' => 'apto'])['errores'], 'id_examen', 'no existe'));

echo "\nServidor (páginas reales, sin JavaScript)\n";
$tablas = ['examenes_medicos', 'perfil_salud', 'historial_medico'];
function rechazoExamen(string $script, array $post, string $contiene): void {
    global $conexion, $tablas;
    $antes = huellaTablas($conexion, $tablas);
    $r = ejecutarComoWeb($script, $post);
    $q = parametrosRedireccion($r['location']);
    afirmar($r['status'] === 302 && ($q['tipo'] ?? '') === 'error' && str_contains($q['mensaje'] ?? '', $contiene),
        "Debería rechazar mencionando '$contiene'. Respuesta: {$r['status']} {$r['location']}");
    afirmar(huellaTablas($conexion, $tablas) === $antes, 'Las tablas cambiaron');
}
prueba('programar: tipo inválido se rechaza y no escribe', fn() => rechazoExamen('views/examenes/programar_examen.php', ['id_trabajador' => (string)$trabajador, 'tipo' => 'egreso', 'fecha_programada' => date('Y-m-d')], 'tipo de examen'));
prueba('programar: fecha inexistente se rechaza y no escribe', fn() => rechazoExamen('views/examenes/programar_examen.php', ['id_trabajador' => (string)$trabajador, 'tipo' => 'ingreso', 'fecha_programada' => '2026-04-31'], 'no existe'));
prueba('registrar: examen inexistente se rechaza y no escribe', fn() => rechazoExamen('views/examenes/registrar_resultado.php', ['id_examen' => '999999', 'fecha_realizado' => $ayer, 'resultado' => 'apto'], 'no existe'));
prueba('programar y registrar por GET no se ejecutan (405)', function () use ($conexion, $tablas) {
    $antes = huellaTablas($conexion, $tablas);
    foreach (['programar_examen', 'registrar_resultado'] as $f) {
        $r = ejecutarComoWeb("views/examenes/$f.php", [], 'GET');
        afirmar($r['status'] === 405, "$f por GET respondió {$r['status']}");
    }
    afirmar(huellaTablas($conexion, $tablas) === $antes, 'Las tablas cambiaron');
});
prueba('programar sin token CSRF se rechaza (403)', function () use ($conexion, $tablas, $trabajador) {
    $antes = huellaTablas($conexion, $tablas);
    $r = ejecutarComoWeb('views/examenes/programar_examen.php', ['id_trabajador' => (string)$trabajador, 'tipo' => 'ingreso', 'fecha_programada' => date('Y-m-d')], 'POST', false);
    afirmar($r['status'] === 403 && huellaTablas($conexion, $tablas) === $antes, "Respondió {$r['status']}");
});
prueba('la pantalla carga con los dos formularios habilitados (sin bloqueo "en desarrollo")', function () {
    $r = ejecutarComoWeb('views/examenes/index.php', [], 'GET');
    $h = $r['cuerpo'];
    afirmar($r['status'] === 200 && !str_contains($h, 'error-banner"><'), 'La página no cargó bien');
    afirmar(!str_contains($h, 'en desarrollo') && !str_contains($h, '<fieldset disabled'), 'Sigue el bloqueo');
    afirmar(preg_match_all('/<button type="submit" class="btn btn-primary">(Programar|Guardar resultado)<\/button>/', $h) === 2, 'Los botones de guardar deberían estar habilitados');
    afirmar(str_contains($h, 'name="concepto_alturas"') && str_contains($h, 'Post-incapacidad'), 'Faltan opciones nuevas');
});

echo "\n" . ($total - $fallos) . " de $total pruebas pasaron.\n";
exit($fallos > 0 ? 1 : 0);
