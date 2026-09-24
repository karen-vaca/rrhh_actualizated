<?php
require_once __DIR__ . '/../config/auth.php';
soloConsola();
// Tests de validación del formulario de trabajadores.
// Ejecutar: php tests/ValidacionTrabajadorTest.php
// Solo consulta la base de datos (unicidad, catálogos, área/cargo); no escribe nada.

require __DIR__ . '/../config/conexion.php';
require __DIR__ . '/../views/trabajadores/funciones_trabajador.php';

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

// Valida $cambios sobre un trabajador válido y devuelve los errores.
function errores(array $cambios = [], ?int $idActual = null, bool $dns = false): array {
    global $conexion, $base;
    return validarTrabajador($conexion, array_merge($base, $cambios), $idActual, $dns)['errores'];
}

function tieneError(array $errores, string $campo, string $contiene = ''): void {
    afirmar(isset($errores[$campo]), "Se esperaba error en '$campo'. Errores: " . json_encode($errores, JSON_UNESCAPED_UNICODE));
    afirmar($contiene === '' || str_contains($errores[$campo], $contiene), "El error de '$campo' debería mencionar '$contiene', dice: {$errores[$campo]}");
}

// ── Datos de apoyo tomados de la base ──
$cargoValido = $conexion->query('SELECT id_cargo, id_area FROM cargos ORDER BY id_cargo LIMIT 1')->fetch();
$cargoOtraArea = $conexion->query("SELECT id_cargo FROM cargos WHERE id_area <> {$cargoValido['id_area']} LIMIT 1")->fetchColumn();
$existente = $conexion->query('SELECT id_trabajador, numero_documento, correo_personal FROM trabajadores ORDER BY id_trabajador LIMIT 1')->fetch();
$otro = $conexion->query("SELECT id_trabajador FROM trabajadores WHERE id_trabajador <> {$existente['id_trabajador']} LIMIT 1")->fetchColumn();
$idCC = $conexion->query("SELECT id_tipos_documentos FROM tipos_documentos WHERE tipo_de_documento = 'CC'")->fetchColumn();
$idPasaporte = $conexion->query("SELECT id_tipos_documentos FROM tipos_documentos WHERE tipo_de_documento LIKE '%pasaporte%'")->fetchColumn();

$hace = fn(string $intervalo): string => date('Y-m-d', strtotime($intervalo));

$base = [
    'nombres' => 'María José',
    'apellidos' => 'Peña Núñez',
    'id_tipos_documentos' => (string)$idCC,
    'numero_documento' => '9990001112',
    'id_generos' => '1',
    'fecha_nacimiento' => $hace('-30 years'),
    'lugar_nacimiento' => 'Tunja, Boyacá',
    'id_nacionalidad' => '1',
    'id_area' => (string)$cargoValido['id_area'],
    'id_cargo' => (string)$cargoValido['id_cargo'],
    'estado' => '1',
    'fecha_ingreso' => $hace('-1 year'),
    'correo_personal' => 'prueba.validacion.unica@gmail.com',
    'telefono' => '300 123 4567',
    'id_eps' => '1',
    'id_formacion_educativa' => '4',
    'id_sangre' => '1',
    'id_estado_civil' => '1',
    'id_grupos_etnicos' => '1',
    'orientacion_sexual' => '',
    'tiene_hijos' => '0',
    'talla_camisa' => 'M',
    'talla_pantalon' => '32',
    'talla_botas' => '40',
];

echo "Validación de trabajadores\n";

prueba('un trabajador válido no tiene errores', function () {
    $e = errores();
    afirmar($e === [], 'Errores inesperados: ' . json_encode($e, JSON_UNESCAPED_UNICODE));
});

// ── Fecha de nacimiento ──
prueba('fecha de nacimiento futura', fn() => tieneError(errores(['fecha_nacimiento' => $hace('+1 day')]), 'fecha_nacimiento', 'futura'));
prueba('fecha de nacimiento de hoy', fn() => tieneError(errores(['fecha_nacimiento' => date('Y-m-d')]), 'fecha_nacimiento', 'futura'));
prueba('fecha de nacimiento muy antigua (más de 80 años)', fn() => tieneError(errores(['fecha_nacimiento' => $hace('-81 years')]), 'fecha_nacimiento', '80'));
prueba('menor de 18 años (caso Paola Franco, 2026-05-12)', fn() => tieneError(errores(['fecha_nacimiento' => '2026-05-12']), 'fecha_nacimiento', '18'));
prueba('fecha de nacimiento inexistente (30 de febrero)', fn() => tieneError(errores(['fecha_nacimiento' => '1990-02-30']), 'fecha_nacimiento', 'válida'));
prueba('fecha de nacimiento obligatoria', fn() => tieneError(errores(['fecha_nacimiento' => '']), 'fecha_nacimiento', 'obligatoria'));

// ── Fecha de ingreso ──
prueba('fecha de ingreso futura', fn() => tieneError(errores(['fecha_ingreso' => $hace('+1 day')]), 'fecha_ingreso', 'futura'));
prueba('fecha de ingreso antes de cumplir 18 años', fn() => tieneError(errores(['fecha_nacimiento' => $hace('-20 years'), 'fecha_ingreso' => $hace('-3 years')]), 'fecha_ingreso', '18'));
prueba('fecha de ingreso obligatoria', fn() => tieneError(errores(['fecha_ingreso' => '']), 'fecha_ingreso', 'obligatoria'));

// ── Correo ──
prueba('correo terminado en ".p" (caso Paola Franco)', fn() => tieneError(errores(['correo_personal' => 'paovf159@gmail.com.p']), 'correo_personal', 'no es válido'));
prueba('correo sin @', fn() => tieneError(errores(['correo_personal' => 'paovf159gmail.com']), 'correo_personal', 'una @'));
prueba('correo con coma (caso "danielada,-@gmail.com")', fn() => tieneError(errores(['correo_personal' => 'danielada,-@gmail.com']), 'correo_personal', '«,»'));
prueba('correo con espacio', fn() => tieneError(errores(['correo_personal' => 'ana maria@gmail.com']), 'correo_personal', 'espacios'));
prueba('correo con dos @', fn() => tieneError(errores(['correo_personal' => 'ana@@gmail.com']), 'correo_personal', 'una @'));
prueba('correo con punto justo antes de la @', fn() => tieneError(errores(['correo_personal' => 'ana.@gmail.com']), 'correo_personal', 'punto'));
prueba('correo con puntos seguidos', fn() => tieneError(errores(['correo_personal' => 'ana..paz@gmail.com']), 'correo_personal', 'punto'));
prueba('correo con guion antes de la @ es válido (lo permite el estándar)', function () {
    afirmar(!isset(errores(['correo_personal' => 'ana-@empresa.com'])['correo_personal']), 'ana-@empresa.com debería ser válido');
});
prueba('correo con punto, guion y + en la parte local es válido', function () {
    afirmar(!isset(errores(['correo_personal' => 'ana.paz-rrhh+plasty@empresa.com.co'])['correo_personal']), 'Debería ser válido');
});
prueba('correo obligatorio',fn() => tieneError(errores(['correo_personal' => '']), 'correo_personal', 'obligatorio'));
prueba('correo duplicado', function () use ($existente) {
    tieneError(errores(['correo_personal' => $existente['correo_personal']]), 'correo_personal', 'Ya existe');
});
prueba('al editar, el trabajador puede conservar su propio correo', function () use ($existente) {
    $e = errores(['correo_personal' => $existente['correo_personal'], 'numero_documento' => $existente['numero_documento']], (int)$existente['id_trabajador']);
    afirmar(!isset($e['correo_personal']), 'No debería marcar su propio correo como duplicado');
});
prueba('correo con dominio inexistente (requiere internet)', function () {
    if (!dominioCorreoExiste('x@gmail.com')) {
        echo "        (omitida: sin conexión a internet / DNS)\n";
        return;
    }
    tieneError(errores(['correo_personal' => 'alguien@dominio-que-no-existe-plastypet-xyz.com'], null, true), 'correo_personal', 'dominio');
});

// ── Documento ──
prueba('documento duplicado', function () use ($existente) {
    tieneError(errores(['numero_documento' => $existente['numero_documento']]), 'numero_documento', 'Ya existe');
});
prueba('al editar, el trabajador puede conservar su propio documento', function () use ($existente) {
    $e = errores(['numero_documento' => $existente['numero_documento'], 'correo_personal' => $existente['correo_personal']], (int)$existente['id_trabajador']);
    afirmar(!isset($e['numero_documento']), 'No debería marcar su propio documento como duplicado');
});
prueba('al editar, no puede usar el documento de otro trabajador', function () use ($existente, $otro) {
    tieneError(errores(['numero_documento' => $existente['numero_documento']], (int)$otro), 'numero_documento', 'Ya existe');
});
prueba('cédula con letras', fn() => tieneError(errores(['numero_documento' => '12AB5678']), 'numero_documento', 'dígitos'));
prueba('cédula demasiado corta', fn() => tieneError(errores(['numero_documento' => '12345']), 'numero_documento', 'dígitos'));
prueba('cédula con puntos se normaliza', function () {
    global $conexion, $base;
    $r = validarTrabajador($conexion, array_merge($base, ['numero_documento' => '9.990.001.112']), null, false);
    afirmar(!isset($r['errores']['numero_documento']) && $r['datos']['numero_documento'] === '9990001112', 'Debería quedar 9990001112');
});
prueba('pasaporte alfanumérico es válido', function () use ($idPasaporte) {
    $e = errores(['id_tipos_documentos' => (string)$idPasaporte, 'numero_documento' => 'AB123456']);
    afirmar(!isset($e['numero_documento']), 'Pasaporte alfanumérico debería ser válido');
});

// ── Área / Cargo ──
prueba('cargo que no pertenece al área', fn() => tieneError(errores(['id_cargo' => (string)$cargoOtraArea]), 'id_cargo', 'no pertenece'));
prueba('cargo obligatorio', fn() => tieneError(errores(['id_cargo' => '']), 'id_cargo'));
prueba('área inexistente', fn() => tieneError(errores(['id_area' => '99999']), 'id_area'));

// ── Nombres ──
prueba('nombres con números', fn() => tieneError(errores(['nombres' => 'Juan2']), 'nombres', 'letras'));
prueba('apellidos con símbolos', fn() => tieneError(errores(['apellidos' => 'Pérez@']), 'apellidos', 'letras'));
prueba('nombres de 1 carácter', fn() => tieneError(errores(['nombres' => 'J']), 'nombres', '2 y 100'));
prueba('texto con codificación inválida no se trata como vacío', fn() => tieneError(errores(['apellidos' => "P\xE9rez"]), 'apellidos', 'letras'));
prueba('nombres con tildes y ñ son válidos', function () {
    $e = errores(['nombres' => 'Íñigo Ángel', 'apellidos' => 'Muñoz Güell']);
    afirmar(!isset($e['nombres']) && !isset($e['apellidos']), 'Tildes y ñ deberían ser válidas');
});

// ── Teléfono ──
prueba('teléfono con letras', fn() => tieneError(errores(['telefono' => '300ABC4567']), 'telefono'));
prueba('teléfono de 8 dígitos', fn() => tieneError(errores(['telefono' => '30012345']), 'telefono'));
prueba('teléfono de 6 dígitos (caso "366666" de los datos de prueba)', fn() => tieneError(errores(['telefono' => '366666']), 'telefono', '10 dígitos'));
prueba('teléfono fijo antiguo de 7 dígitos', fn() => tieneError(errores(['telefono' => '7451234']), 'telefono', '10 dígitos'));
prueba('teléfono de 11 dígitos', fn() => tieneError(errores(['telefono' => '30012345678']), 'telefono', '10 dígitos'));
prueba('teléfono de 10 dígitos que no es celular ni fijo', fn() => tieneError(errores(['telefono' => '1234567890']), 'telefono'));
prueba('celular de 10 dígitos con espacios es válido', function () {
    afirmar(!isset(errores(['telefono' => '310 558 7411'])['telefono']), 'Celular de 10 dígitos debería ser válido');
});
prueba('fijo de 10 dígitos (601...) es válido', function () {
    afirmar(!isset(errores(['telefono' => '6017451234'])['telefono']), 'Fijo 60X de 10 dígitos debería ser válido');
});

// ── Catálogos cerrados ──
prueba('tipo de sangre inexistente', fn() => tieneError(errores(['id_sangre' => '99']), 'id_sangre'));
prueba('tipo de sangre vacío es válido (opcional)', function () {
    afirmar(!isset(errores(['id_sangre' => ''])['id_sangre']), 'Sangre vacía debería ser válida');
});
prueba('formación educativa ordenada por nivel académico', function () {
    global $conexion;
    $nombres = array_column(opcionesCatalogo($conexion, 'formacion_educativa', 'id_formacion_educativa', 'nivel_academico'), 'nombre');
    $esperado = ['Primaria', 'Bachiller', 'Técnico', 'Tecnólogo(a)', 'Profesional', 'Posgrado'];
    afirmar($nombres === $esperado, 'Orden: ' . implode(', ', $nombres));
});
prueba('catálogos sin columna "orden" siguen ordenados por id (EPS)', function () {
    global $conexion;
    $ids = array_map('intval', array_column(opcionesCatalogo($conexion, 'eps', 'id_eps', 'nombre_eps'), 'id'));
    $ordenados = $ids;
    sort($ordenados);
    afirmar($ids === $ordenados, 'Ids: ' . implode(', ', $ids));
});
prueba('texto libre en un catálogo',fn() => tieneError(errores(['id_generos' => 'Femenino']), 'id_generos'));
prueba('EPS inexistente', fn() => tieneError(errores(['id_eps' => '999']), 'id_eps'));
prueba('estado laboral fuera de la lista', fn() => tieneError(errores(['estado' => '5']), 'estado'));
prueba('orientación sexual fuera de la lista', fn() => tieneError(errores(['orientacion_sexual' => 'cualquiera']), 'orientacion_sexual'));

// ── Hijos y tallas ──
prueba('"¿tiene hijos?" obligatorio', fn() => tieneError(errores(['tiene_hijos' => '']), 'tiene_hijos'));
prueba('tiene hijos pero sin número', fn() => tieneError(errores(['tiene_hijos' => '1', 'numero_hijos' => '']), 'numero_hijos', 'mínimo 1'));
prueba('número de hijos con letra (caso "O5")', fn() => tieneError(errores(['tiene_hijos' => '1', 'numero_hijos' => 'O5']), 'numero_hijos', 'sin letras'));
prueba('número de hijos decimal', fn() => tieneError(errores(['tiene_hijos' => '1', 'numero_hijos' => '2.5']), 'numero_hijos', 'entero'));
prueba('número de hijos negativo', fn() => tieneError(errores(['tiene_hijos' => '1', 'numero_hijos' => '-2']), 'numero_hijos', 'entero'));
prueba('"Sí" tiene hijos pero número 0 (inconsistente)', fn() => tieneError(errores(['tiene_hijos' => '1', 'numero_hijos' => '0']), 'numero_hijos', 'al menos 1'));
prueba('número de hijos mayor a 15', fn() => tieneError(errores(['tiene_hijos' => '1', 'numero_hijos' => '16']), 'numero_hijos', '15'));
prueba('"No" tiene hijos fuerza el número a 0 aunque llegue otro valor', function () {
    global $conexion, $base;
    $r = validarTrabajador($conexion, array_merge($base, ['tiene_hijos' => '0', 'numero_hijos' => 'O5']), null, false);
    afirmar(!isset($r['errores']['numero_hijos']) && $r['datos']['numero_hijos'] === 0, 'Con "No" debería guardarse 0 sin error');
});
prueba('"Sí" con 15 hijos es válido', function () {
    global $conexion, $base;
    $r = validarTrabajador($conexion, array_merge($base, ['tiene_hijos' => '1', 'numero_hijos' => '15']), null, false);
    afirmar(!isset($r['errores']['numero_hijos']) && $r['datos']['numero_hijos'] === 15, 'Debería guardarse 15');
});
prueba('talla de pantalón demasiado larga', fn() => tieneError(errores(['talla_pantalon' => str_repeat('X', 11)]), 'talla_pantalon'));
prueba('talla de camisa fuera de la lista', fn() => tieneError(errores(['talla_camisa' => 'XXXXL']), 'talla_camisa'));

echo "\n" . ($total - $fallos) . " de $total pruebas pasaron.\n";
exit($fallos > 0 ? 1 : 0);
