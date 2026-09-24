<?php
require_once __DIR__ . '/../config/auth.php';
soloConsola();
// Tests de: catálogo DIVIPOLA y migración del lugar de nacimiento, cascada
// departamento → ciudad, estado laboral (solo activar/inactivar lo cambian) y folio.
// Ejecutar: php tests/LugaresEstadoFolioTest.php
// No deja cambios: el guardado usa transacciones que se deshacen y las páginas se
// prueban con php-cgi comprobando que las tablas no cambien.

require __DIR__ . '/../config/conexion.php';
require __DIR__ . '/../views/trabajadores/funciones_trabajador.php';
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

// Ejecuta crear.php / actualizar.php reales con rollback (ver ejecutar_guardado.php).
function guardar(string $accion, array $post): array {
    $archivo = tempnam(sys_get_temp_dir(), 'post');
    file_put_contents($archivo, json_encode($post));
    $salida = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/ejecutar_guardado.php') . " $accion " . escapeshellarg($archivo) . ' 2>&1');
    unlink($archivo);
    $pos = strrpos((string)$salida, '@@RESULTADO@@');
    afirmar($pos !== false, "Salida inesperada:\n$salida");
    return json_decode(substr($salida, $pos + strlen('@@RESULTADO@@')), true);
}

// Un trabajador válido para enviar a crear/actualizar.
$cargo = $conexion->query('SELECT id_cargo, id_area FROM cargos ORDER BY id_cargo LIMIT 1')->fetch();
$post = [
    'nombres' => 'Prueba', 'apellidos' => 'Lugares Estado', 'id_tipos_documentos' => '1', 'numero_documento' => '9990004441',
    'id_generos' => '1', 'fecha_nacimiento' => date('Y-m-d', strtotime('-30 years')), 'id_nacionalidad' => '1',
    'id_area' => (string)$cargo['id_area'], 'id_cargo' => (string)$cargo['id_cargo'],
    'fecha_ingreso' => date('Y-m-d', strtotime('-1 year')), 'correo_personal' => 'prueba.lugares.estado@gmail.com',
    'telefono' => '3001234567', 'id_eps' => '2', 'id_formacion_educativa' => '4', 'id_estado_civil' => '1',
    'id_grupos_etnicos' => '1', 'tiene_hijos' => '0',
];
$lugar = fn(string $dep, string $ciu) => validarLugar($conexion, $dep, $ciu, 'departamento_nacimiento', 'ciudad_nacimiento');

echo "Catálogo DIVIPOLA\n";
prueba('33 departamentos y 1.122 municipios', function () use ($conexion) {
    $d = (int)$conexion->query('SELECT COUNT(*) FROM departamentos')->fetchColumn();
    $c = (int)$conexion->query('SELECT COUNT(*) FROM ciudades')->fetchColumn();
    afirmar($d === 33 && $c === 1122, "Hay $d departamentos y $c municipios");
});
prueba('toda ciudad pertenece a un departamento existente', fn() => afirmar((int)$conexion->query('SELECT COUNT(*) FROM ciudades c LEFT JOIN departamentos d ON d.codigo = c.codigo_departamento WHERE d.codigo IS NULL')->fetchColumn() === 0, 'Hay ciudades huérfanas'));
prueba('códigos DANE con cero inicial y nombres legibles', function () use ($conexion) {
    afirmar(nombreLugar($conexion, '05001') === 'Medellín, Antioquia', nombreLugar($conexion, '05001'));
    afirmar(nombreLugar($conexion, '15001') === 'Tunja, Boyacá', nombreLugar($conexion, '15001'));
    afirmar(nombreLugar($conexion, '11001') === 'Bogotá, D.C.', nombreLugar($conexion, '11001'));
});

echo "\nMigración del lugar de nacimiento (texto libre → ciudad)\n";
$casos = [
    ['Tunja', '15001'], ['Tunja, Boyacá', '15001'], ['  tunja ,boyaca ', '15001'], ['TUNJA - BOYACA', '15001'],
    ['Medellín', '05001'], ['Bogotá', '11001'], ['Bogota D.C.', '11001'], ['Cali', '76001'], ['Cúcuta', '54001'],
    ['Barbosa, Santander', '68077'], ['Barbosa, Antioquia', '05079'],
];
foreach ($casos as [$texto, $codigo]) {
    prueba("\"$texto\" → $codigo", function () use ($conexion, $texto, $codigo) {
        $r = interpretarLugarTexto($conexion, $texto);
        afirmar($r['codigo'] === $codigo, 'Obtuvo ' . var_export($r, true));
    });
}
$revisar = [
    ['Barbosa', 'Hay 2'],                      // existe en 2 departamentos: ambiguo
    ['San Andrés', 'Hay 2'],
    ['Tunja, Antioquia', 'No existe'],         // combinación imposible
    ['Cartagena, Caquetá', 'No existe'],
    ['Tunja, Narnia', 'No se reconoce'],
    ['Atlantis', 'No se encontró'],
    ['No especificado', 'no indica un lugar'],
];
foreach ($revisar as [$texto, $motivo]) {
    prueba("\"$texto\" queda para revisión (no se asigna mal)", function () use ($conexion, $texto, $motivo) {
        $r = interpretarLugarTexto($conexion, $texto);
        afirmar($r['codigo'] === null && str_contains((string)$r['motivo'], $motivo), 'Obtuvo ' . var_export($r, true));
    });
}
// Escenario controlado: 4 trabajadores sin ciudad y con distintos textos, dentro de una
// transacción que se deshace (no depende de cómo estén los datos reales).
function conEscenarioMigracion(PDO $conexion, callable $fn): void {
    $ids = $conexion->query('SELECT id_trabajador FROM trabajadores ORDER BY id_trabajador LIMIT 4')->fetchAll(PDO::FETCH_COLUMN);
    afirmar(count($ids) === 4, 'Se necesitan 4 trabajadores para el escenario');
    $textos = ['Tunja, Boyacá', 'Barbosa', 'No especificado', 'Medellín'];
    $conexion->beginTransaction();
    try {
        // Solo los 4 del escenario quedan con texto: el resto, sin lugar (no participa).
        $conexion->exec('UPDATE trabajadores SET codigo_ciudad_nacimiento = NULL, lugar_nacimiento = NULL');
        $st = $conexion->prepare('UPDATE trabajadores SET lugar_nacimiento = ? WHERE id_trabajador = ?');
        foreach ($ids as $i => $id) {
            $st->execute([$textos[$i], $id]);
        }
        $fn(array_combine($ids, $textos));
    } finally {
        $conexion->rollBack();
    }
}
prueba('migración: asigna solo coincidencias únicas y deja el resto para revisión', function () use ($conexion) {
    conEscenarioMigracion($conexion, function (array $casos) use ($conexion) {
        $r = migrarLugaresNacimiento($conexion);
        $asignados = array_column($r['asignados'], 'codigo', 'id_trabajador');
        $revision = array_column($r['revision'], 'lugar_nacimiento', 'id_trabajador');
        $ids = array_keys($casos);
        afirmar($asignados == [$ids[0] => '15001', $ids[3] => '05001'], 'Asignados: ' . json_encode($asignados));
        afirmar($revision == [$ids[1] => 'Barbosa', $ids[2] => 'No especificado'], 'Revisión: ' . json_encode($revision, JSON_UNESCAPED_UNICODE));
    });
});
prueba('migración: nunca borra el texto original (ni en los asignados)', function () use ($conexion) {
    conEscenarioMigracion($conexion, function (array $casos) use ($conexion) {
        migrarLugaresNacimiento($conexion);
        $st = $conexion->prepare('SELECT lugar_nacimiento FROM trabajadores WHERE id_trabajador = ?');
        foreach ($casos as $id => $texto) {
            $st->execute([$id]);
            afirmar($st->fetchColumn() === $texto, "El trabajador #$id perdió su texto original");
        }
    });
});
prueba('migración: ejecutarla dos veces no cambia nada la segunda vez', function () use ($conexion) {
    conEscenarioMigracion($conexion, function () use ($conexion) {
        migrarLugaresNacimiento($conexion);
        $antes = huellaTablas($conexion, ['trabajadores']);
        $r = migrarLugaresNacimiento($conexion);
        afirmar($r['asignados'] === [] && huellaTablas($conexion, ['trabajadores']) === $antes, 'La segunda ejecución modificó trabajadores');
    });
});
prueba('la ficha marca "Por revisar" y muestra el texto original', function () use ($conexion) {
    $id = $conexion->query("SELECT id_trabajador FROM trabajadores WHERE codigo_ciudad_nacimiento IS NULL
                            AND TRIM(COALESCE(lugar_nacimiento, '')) <> '' ORDER BY id_trabajador LIMIT 1")->fetchColumn();
    if (!$id) {
        echo "        (omitida: ningún trabajador tiene hoy el lugar por revisar)\n";
        return;
    }
    $texto = $conexion->query("SELECT lugar_nacimiento FROM trabajadores WHERE id_trabajador = $id")->fetchColumn();
    $h = ejecutarComoWeb('views/trabajadores/ver.php', ['id' => (string)$id], 'GET')['cuerpo'];
    afirmar(str_contains($h, htmlspecialchars($texto) . ' <span class="lugar-revisar"'), 'No aparece la marca de revisión');
});

echo "\nCascada departamento → ciudad\n";
prueba('pareja válida', fn() => afirmar($lugar('15', '15001') === ['codigo' => '15001', 'errores' => []], 'Debería ser válida'));
prueba('ambos vacíos es válido (campo opcional)', fn() => afirmar($lugar('', '') === ['codigo' => null, 'errores' => []], 'Debería ser válido'));
prueba('ciudad de otro departamento (Tunja con Antioquia) se rechaza', fn() => afirmar(str_contains($lugar('05', '15001')['errores']['ciudad_nacimiento'] ?? '', 'no pertenece'), 'Debería rechazar'));
prueba('departamento sin ciudad', fn() => afirmar(isset($lugar('15', '')['errores']['ciudad_nacimiento']), 'Debería pedir la ciudad'));
prueba('ciudad sin departamento', fn() => afirmar(isset($lugar('', '15001')['errores']['departamento_nacimiento']), 'Debería pedir el departamento'));
prueba('códigos que no existen', function () use ($lugar) {
    afirmar(isset($lugar('77', '')['errores']['departamento_nacimiento']), 'Departamento 77 no existe');
    afirmar(isset($lugar('15', '15999')['errores']['ciudad_nacimiento']), 'Ciudad 15999 no existe');
    afirmar(isset($lugar('15', 'Tunja')['errores']['ciudad_nacimiento']), 'Texto libre no es un código');
});
prueba('servidor (actualizar real, sin JavaScript): combinación imposible se rechaza y no escribe', function () use ($conexion, $post) {
    $antes = huellaTablas($conexion, ['trabajadores']);
    $t = $conexion->query('SELECT id_trabajador, numero_documento, correo_personal FROM trabajadores ORDER BY id_trabajador LIMIT 1')->fetch();
    $r = ejecutarComoWeb('views/trabajadores/actualizar.php', array_merge($post, [
        'id_trabajador' => (string)$t['id_trabajador'], 'numero_documento' => $t['numero_documento'], 'correo_personal' => $t['correo_personal'],
        'departamento_nacimiento' => '05', 'ciudad_nacimiento' => '15001',
    ]));
    afirmar($r['status'] === 302 && str_contains($r['location'], 'editar.php'), "Respuesta: {$r['status']} {$r['location']}");
    afirmar(huellaTablas($conexion, ['trabajadores']) === $antes, 'La tabla trabajadores cambió');
});
// Trabajador 9 con un lugar antiguo por revisar, preparado dentro de la transacción del guardado.
$porRevisar = fn() => [
    '__preparar_sql' => "UPDATE trabajadores SET codigo_ciudad_nacimiento = NULL, lugar_nacimiento = 'Texto antiguo de prueba' WHERE id_trabajador = 9",
    'id_trabajador' => '9',
    'numero_documento' => $conexion->query('SELECT numero_documento FROM trabajadores WHERE id_trabajador = 9')->fetchColumn(),
    'correo_personal' => $conexion->query('SELECT correo_personal FROM trabajadores WHERE id_trabajador = 9')->fetchColumn(),
];
prueba('elegir ciudad al editar asigna el código y limpia el texto antiguo', function () use ($post, $porRevisar) {
    $r = guardar('actualizar', array_merge($post, $porRevisar(), ['departamento_nacimiento' => '15', 'ciudad_nacimiento' => '15001']));
    afirmar($r['errores'] === [], 'Errores: ' . json_encode($r['errores'], JSON_UNESCAPED_UNICODE));
    afirmar($r['fila']['codigo_ciudad_nacimiento'] === '15001' && $r['fila']['lugar_nacimiento'] === null, 'Fila: ' . json_encode([$r['fila']['codigo_ciudad_nacimiento'], $r['fila']['lugar_nacimiento']]));
});
prueba('editar sin elegir lugar conserva el texto antiguo', function () use ($post, $porRevisar) {
    $r = guardar('actualizar', array_merge($post, $porRevisar()));
    afirmar($r['errores'] === [] && $r['fila']['lugar_nacimiento'] === 'Texto antiguo de prueba' && $r['fila']['codigo_ciudad_nacimiento'] === null, 'Fila: ' . json_encode($r['fila'] ?? $r['errores'], JSON_UNESCAPED_UNICODE));
});
prueba('los formularios usan selects con buscador en cascada (crear y editar)', function () {
    foreach ([['views/trabajadores/index.php', []], ['views/trabajadores/editar.php', ['id' => '9']]] as [$pag, $q]) {
        $h = ejecutarComoWeb($pag, $q, 'GET')['cuerpo'];
        afirmar(preg_match('/<select[^>]*name="departamento_nacimiento"[^>]*data-buscador[^>]*data-cascada-ciudad="sel_ciudad_nacimiento"/', $h) === 1, "$pag: falta el departamento con buscador");
        afirmar(preg_match('/<select[^>]*id="sel_ciudad_nacimiento"[^>]*name="ciudad_nacimiento"[^>]*data-buscador/', $h) === 1, "$pag: falta la ciudad con buscador");
        afirmar(str_contains($h, 'window.CIUDADES_POR_DEPARTAMENTO') && str_contains($h, 'select_buscador.js') && str_contains($h, 'lugares.js'), "$pag: faltan los scripts");
        afirmar(!preg_match('/name="lugar_nacimiento"/', $h), "$pag: sigue el campo de texto libre");
    }
});

echo "\nEstado laboral (solo activar / inactivar lo cambian)\n";
prueba('crear siempre deja al trabajador Activo, aunque se envíe estado=0', function () use ($post) {
    $r = guardar('crear', $post + ['estado' => '0']);
    afirmar($r['errores'] === [] && (int)$r['fila']['estado'] === 1, 'Fila: ' . json_encode($r['fila'] ?? $r['errores']));
});
prueba('actualizar ignora un estado enviado en el formulario', function () use ($conexion, $post) {
    $t = $conexion->query('SELECT id_trabajador, numero_documento, correo_personal, estado FROM trabajadores WHERE estado = 1 ORDER BY id_trabajador LIMIT 1')->fetch();
    $r = guardar('actualizar', array_merge($post, [
        'id_trabajador' => (string)$t['id_trabajador'], 'numero_documento' => $t['numero_documento'], 'correo_personal' => $t['correo_personal'], 'estado' => '0',
    ]));
    afirmar($r['errores'] === [] && (int)$r['fila']['estado'] === 1, 'El estado cambió por el formulario de edición');
});
prueba('el validador no devuelve ningún estado', function () use ($conexion, $post) {
    $v = validarTrabajador($conexion, $post + ['estado' => '0'], null, false);
    afirmar(!array_key_exists('estado', $v['datos']), 'validarTrabajador() no debería incluir estado');
});
prueba('crear y editar no tienen campo de estado', function () {
    foreach ([['views/trabajadores/index.php', []], ['views/trabajadores/editar.php', ['id' => '9']]] as [$pag, $q]) {
        $h = ejecutarComoWeb($pag, $q, 'GET')['cuerpo'];
        afirmar(!preg_match('/<(select|input)[^>]*name="estado"/', $h), "$pag todavía tiene un campo de estado");
    }
});
prueba('en todo el código, solo activar.php e inactivar.php cambian el estado de un trabajador', function () {
    $encontrados = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/../views', FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if ($f->getExtension() !== 'php') {
            continue;
        }
        $codigo = file_get_contents($f->getPathname());
        // UPDATE trabajadores ... estado = ...   o   'estado' / estado en una lista de columnas de INSERT/UPDATE dinámico
        if (preg_match('/UPDATE\s+trabajadores\b[^;]*?\bestado\s*=/is', $codigo) || preg_match("/['\"]estado\s*=\s*1['\"]/", $codigo)) {
            $encontrados[] = str_replace('\\', '/', substr(realpath($f->getPathname()), strlen(realpath(__DIR__ . '/..')) + 1));
        }
    }
    sort($encontrados);
    afirmar($encontrados === ['views/trabajadores/activar.php', 'views/trabajadores/inactivar.php'], 'Archivos que cambian el estado: ' . implode(', ', $encontrados));
});
prueba('contratar a un trabajador inactivo se rechaza (ya no lo activa en silencio)', function () use ($conexion) {
    $t = (int)$conexion->query('SELECT id_trabajador FROM trabajadores WHERE estado = 1 ORDER BY id_trabajador LIMIT 1')->fetchColumn();
    $tipo = (int)$conexion->query('SELECT id_tipos_contrato FROM tipos_contrato ORDER BY 1 LIMIT 1')->fetchColumn();
    $antesTrab = huellaTablas($conexion, ['trabajadores']);
    $archivo = tempnam(sys_get_temp_dir(), 'contrato');
    file_put_contents($archivo, json_encode([
        '__preparar_sql' => "UPDATE trabajadores SET estado = 0 WHERE id_trabajador = $t",
        'accion' => 'nuevo', 'id_trabajador' => (string)$t, 'id_area' => '1', 'id_cargo' => '1', 'id_tipos_contrato' => (string)$tipo,
        'fecha_ingreso' => '2026-01-01', 'fecha_inicio' => '2026-01-01', 'fecha_fin' => '2026-12-31',
        'salario_base' => '1750905', 'auxilio_transporte' => '249095', 'jefe_inmediato' => 'Daniel Avila',
        'jornada' => 'Completa (46h/sem)', 'modalidad' => 'Presencial', 'periodo_prueba' => 'Sin periodo',
    ]));
    $salida = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/ejecutar_contrato.php') . ' ' . escapeshellarg($archivo) . ' 2>&1');
    unlink($archivo);
    $r = json_decode(substr($salida, strrpos($salida, '@@RESULTADO@@') + 13), true);
    afirmar(($r['respuesta']['ok'] ?? null) === false && str_contains($r['respuesta']['texto'] ?? '', 'inactivo'), 'Respuesta: ' . json_encode($r, JSON_UNESCAPED_UNICODE));
    afirmar($r['contratos_sin_cambios'] && huellaTablas($conexion, ['trabajadores']) === $antesTrab, 'Quedaron cambios en la base');
});

echo "\nFolio (ID como dato secundario)\n";
prueba('ficha y edición muestran el folio junto al nombre y no hay campo para el ID', function () {
    foreach (['views/trabajadores/ver.php', 'views/trabajadores/editar.php'] as $pag) {
        $h = ejecutarComoWeb($pag, ['id' => '9'], 'GET')['cuerpo'];
        afirmar(str_contains($h, '<span class="folio"') && str_contains($h, 'Folio #0009'), "$pag: falta el folio");
        afirmar(!preg_match('/<input(?![^>]*type="hidden")[^>]*name="id_trabajador"/', $h), "$pag: el ID no debería ser editable");
    }
});

echo "\n" . ($total - $fallos) . " de $total pruebas pasaron.\n";
exit($fallos > 0 ? 1 : 0);
