<?php
// Validaciones y formato compartidos por las vistas de trabajadores.
// validarTrabajador() es la única fuente de reglas del backend: la usan crear.php
// y actualizar.php, y la cubren los tests de tests/ValidacionTrabajadorTest.php.

require_once __DIR__ . '/../components/lugares.php';

// Edad mínima y máxima para registrar un trabajador.
const EDAD_MINIMA_TRABAJADOR = 18;
const EDAD_MAXIMA_TRABAJADOR = 80;

const MAX_HIJOS = 15;

// Formato práctico de correo (el mismo que usa validacion_trabajador.js):
// parte local con letras, números y . _ % + - ' (sin puntos al inicio, al final ni
// seguidos), @, dominio con letras/números/guiones y extensión final de 2+ letras.
const PATRON_CORREO = "/^[a-z0-9_%+'-]+(\\.[a-z0-9_%+'-]+)*@([a-z0-9]([a-z0-9-]*[a-z0-9])?\\.)+[a-z]{2,}$/i";

const TALLAS_CAMISA = ['XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL'];

const ORIENTACIONES_SEXUALES = [
    'heterosexual'   => 'Heterosexual',
    'homosexual'     => 'Homosexual',
    'bisexual'       => 'Bisexual',
    'pansexual'      => 'Pansexual',
    'asexual'        => 'Asexual',
    'otra'           => 'Otra',
    'no_especificar' => 'Prefiero no especificar',
];

// Devuelve la fecha en formato DD/MM/YYYY, o '' si viene vacía o no es válida.
function formatoFecha($fecha): string {
    $fecha = trim((string)($fecha ?? ''));
    if ($fecha === '' || str_starts_with($fecha, '0000-00-00')) {
        return '';
    }
    $time = strtotime($fecha);
    return $time ? date('d/m/Y', $time) : '';
}

// Igual que formatoFecha, pero con hora (DD/MM/YYYY hh:mm AM/PM).
function formatoFechaHora($fecha): string {
    $fecha = trim((string)($fecha ?? ''));
    if ($fecha === '' || str_starts_with($fecha, '0000-00-00')) {
        return '';
    }
    $time = strtotime($fecha);
    return $time ? date('d/m/Y h:i A', $time) : '';
}

// Límites para los date pickers de fecha de nacimiento.
function fechaMaximaNacimiento(): string {
    return date('Y-m-d', strtotime('-' . EDAD_MINIMA_TRABAJADOR . ' years'));
}

function fechaMinimaNacimiento(): string {
    return date('Y-m-d', strtotime('-' . EDAD_MAXIMA_TRABAJADOR . ' years'));
}

// Convierte 'Y-m-d' a DateTime, o null si no es una fecha real (ej: 1990-02-30).
function parsearFecha(string $fecha): ?DateTime {
    $dt = DateTime::createFromFormat('!Y-m-d', $fecha);
    return ($dt && $dt->format('Y-m-d') === $fecha) ? $dt : null;
}

// Formato de correo: FILTER_VALIDATE_EMAIL de PHP (estándar) Y el patrón práctico
// PATRON_CORREO. filter_var solo no basta: acepta dominios como "gmail.com.p" y
// caracteres raros que el estándar permite pero nadie usa (ej: comillas, llaves).
function correoValido($correo): bool {
    $correo = trim((string)($correo ?? ''));
    return filter_var($correo, FILTER_VALIDATE_EMAIL) !== false
        && preg_match(PATRON_CORREO, $correo) === 1;
}

// Mensaje específico para un correo inválido: dice qué está mal, no solo "no es válido".
function mensajeCorreoInvalido(string $correo): string {
    $ejemplo = ' Ejemplo: nombre@gmail.com';
    if (substr_count($correo, '@') !== 1) {
        return 'El correo debe tener exactamente una @.' . $ejemplo;
    }
    if (preg_match('/\s/u', $correo)) {
        return 'El correo no puede tener espacios.' . $ejemplo;
    }
    if (preg_match_all("/[^a-z0-9@._%+'-]/iu", $correo, $m)) {
        $caracteres = implode(' ', array_map(fn($c) => '«' . $c . '»', array_unique($m[0])));
        return 'El correo tiene caracteres no permitidos: ' . $caracteres . '.' . $ejemplo;
    }
    if (preg_match('/(^\.|\.@|\.\.)/', $correo)) {
        return 'El correo tiene un punto mal ubicado (al inicio, repetido o justo antes de la @).' . $ejemplo;
    }
    return 'El correo electrónico no es válido. Revisa lo que va después de la @.' . $ejemplo;
}

// El dominio del correo debe existir (registro MX o, en su defecto, A).
function dominioCorreoExiste(string $correo): bool {
    $dominio = substr(strrchr($correo, '@'), 1);
    return $dominio !== '' && (checkdnsrr($dominio, 'MX') || checkdnsrr($dominio, 'A'));
}

// Opciones de un catálogo para un <select>. Si el catálogo tiene nombres
// repetidos (ej: generos), se muestra una sola vez cada nombre (el id menor).
// Si la tabla tiene una columna `orden` (ej: formacion_educativa, por nivel académico),
// las opciones salen en ese orden; si no, en el orden en que se crearon (id).
function opcionesCatalogo(PDO $conexion, string $tabla, string $idColumna, string $nombreColumna): array {
    static $tieneOrden = [];
    $tieneOrden[$tabla] ??= (bool)$conexion->query("SHOW COLUMNS FROM `$tabla` LIKE 'orden'")->fetch();
    $ordenarPor = $tieneOrden[$tabla] ? "MIN(`orden`), MIN(`$idColumna`)" : "MIN(`$idColumna`)";

    $sql = "SELECT MIN(`$idColumna`) AS id, `$nombreColumna` AS nombre
            FROM `$tabla`
            GROUP BY `$nombreColumna`
            ORDER BY $ordenarPor";
    return $conexion->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

// Para catálogos con nombres repetidos: devuelve el id que muestra opcionesCatalogo()
// para el mismo nombre (ej: generos 5 "Femenino" -> 1), así el <select> lo marca.
function idCanonico(PDO $conexion, string $tabla, string $idColumna, string $nombreColumna, $id) {
    if (!ctype_digit((string)$id)) {
        return $id;
    }
    $stmt = $conexion->prepare("SELECT MIN(t2.`$idColumna`) FROM `$tabla` t1
                                JOIN `$tabla` t2 ON t2.`$nombreColumna` = t1.`$nombreColumna`
                                WHERE t1.`$idColumna` = ?");
    $stmt->execute([(int)$id]);
    return $stmt->fetchColumn() ?: $id;
}

function existeEnCatalogo(PDO $conexion, string $tabla, string $idColumna, int $id): bool {
    $stmt = $conexion->prepare("SELECT 1 FROM `$tabla` WHERE `$idColumna` = ? LIMIT 1");
    $stmt->execute([$id]);
    return (bool)$stmt->fetchColumn();
}

// Catálogos cerrados del formulario: campo => [tabla, columna id, columna nombre].
function catalogosTrabajador(): array {
    return [
        'id_tipos_documentos'    => ['tipos_documentos', 'id_tipos_documentos', 'tipo_de_documento'],
        'id_generos'             => ['generos', 'id_generos', 'nombre'],
        'id_nacionalidad'        => ['nacionalidad', 'id_nacionalidad', 'nacionalidad'],
        'id_eps'                 => ['eps', 'id_eps', 'nombre_eps'],
        'id_formacion_educativa' => ['formacion_educativa', 'id_formacion_educativa', 'nivel_academico'],
        'id_sangre'              => ['tipos_sangre', 'id_sangre', 'tipo_sangre'],
        'id_estado_civil'        => ['estado_civil', 'id_estado_civil', 'nombre_estado_civil'],
        'id_grupos_etnicos'      => ['grupos_etnicos', 'id_grupos_etnicos', 'grupo_etnico'],
    ];
}

/**
 * Valida y normaliza los datos del formulario de trabajador.
 *
 * @param array    $in           Normalmente $_POST.
 * @param int|null $idActual     id del trabajador al editar (para la unicidad), null al crear.
 * @param bool     $verificarDns Comprobar que el dominio del correo exista (se desactiva en tests).
 * @return array{datos: array, errores: array<string,string>}
 */
function validarTrabajador(PDO $conexion, array $in, ?int $idActual = null, bool $verificarDns = true): array {
    $errores = [];
    $datos = [];
    // Si el texto no es UTF-8 válido, preg_replace devuelve null: se conserva tal cual
    // para que falle la regla del campo en vez de parecer vacío.
    $txt = fn(string $campo): string => trim(preg_replace('/\s+/u', ' ', (string)($in[$campo] ?? '')) ?? (string)$in[$campo]);

    // ── Nombres / Apellidos ──
    foreach (['nombres' => 'Los nombres', 'apellidos' => 'Los apellidos'] as $campo => $etiqueta) {
        $valor = $txt($campo);
        $largo = mb_strlen($valor, 'UTF-8');
        if ($valor === '') {
            $errores[$campo] = "$etiqueta son obligatorios.";
        } elseif (!preg_match('/^[\p{L}]+( [\p{L}]+)*$/u', $valor)) {
            $errores[$campo] = "$etiqueta solo pueden contener letras y espacios.";
        } elseif ($largo < 2 || $largo > 100) {
            $errores[$campo] = "$etiqueta deben tener entre 2 y 100 caracteres.";
        }
        $datos[$campo] = $valor;
    }

    // ── Catálogos cerrados ──
    $opcionales = ['id_sangre'];
    $mensajesCatalogo = [
        'id_tipos_documentos'    => 'Selecciona un tipo de documento válido.',
        'id_generos'             => 'Selecciona un género válido.',
        'id_nacionalidad'        => 'Selecciona una nacionalidad válida.',
        'id_eps'                 => 'Selecciona una EPS válida.',
        'id_formacion_educativa' => 'Selecciona una formación educativa válida.',
        'id_sangre'              => 'Selecciona un tipo de sangre válido (O+, O-, A+, A-, B+, B-, AB+, AB-).',
        'id_estado_civil'        => 'Selecciona un estado civil válido.',
        'id_grupos_etnicos'      => 'Selecciona un grupo étnico válido.',
    ];
    foreach (catalogosTrabajador() as $campo => [$tabla, $idColumna]) {
        $valor = $txt($campo);
        if ($valor === '' && in_array($campo, $opcionales, true)) {
            $datos[$campo] = null;
            continue;
        }
        if (!ctype_digit($valor) || !existeEnCatalogo($conexion, $tabla, $idColumna, (int)$valor)) {
            $errores[$campo] = $mensajesCatalogo[$campo];
            $datos[$campo] = null;
            continue;
        }
        $datos[$campo] = (int)$valor;
    }

    // ── Número de documento (patrón según tipo + único) ──
    $documento = preg_replace('/[\s.\-]/', '', (string)($in['numero_documento'] ?? ''));
    $datos['numero_documento'] = $documento;
    if ($documento === '') {
        $errores['numero_documento'] = 'El número de documento es obligatorio.';
    } elseif (!isset($errores['id_tipos_documentos'])) {
        $stmt = $conexion->prepare('SELECT tipo_de_documento FROM tipos_documentos WHERE id_tipos_documentos = ?');
        $stmt->execute([$datos['id_tipos_documentos']]);
        $esPasaporte = stripos((string)$stmt->fetchColumn(), 'pasaporte') !== false;

        if ($esPasaporte && !preg_match('/^[A-Za-z0-9]{5,20}$/', $documento)) {
            $errores['numero_documento'] = 'El pasaporte debe tener entre 5 y 20 letras o números.';
        } elseif (!$esPasaporte && !preg_match('/^\d{6,10}$/', $documento)) {
            $errores['numero_documento'] = 'El número de documento debe tener solo dígitos (entre 6 y 10).';
        }
    }
    if ($documento !== '' && !isset($errores['numero_documento'])) {
        $stmt = $conexion->prepare('SELECT COUNT(*) FROM trabajadores WHERE numero_documento = ? AND id_trabajador <> ?');
        $stmt->execute([$documento, $idActual ?? 0]);
        if ((int)$stmt->fetchColumn() > 0) {
            $errores['numero_documento'] = 'Ya existe otro trabajador con este número de documento.';
        }
    }

    // ── Fecha de nacimiento ──
    $hoy = new DateTime('today');
    $fechaNac = $txt('fecha_nacimiento');
    $nacimiento = $fechaNac !== '' ? parsearFecha($fechaNac) : null;
    $datos['fecha_nacimiento'] = $fechaNac;
    if ($fechaNac === '') {
        $errores['fecha_nacimiento'] = 'La fecha de nacimiento es obligatoria.';
    } elseif (!$nacimiento) {
        $errores['fecha_nacimiento'] = 'La fecha de nacimiento no es una fecha válida.';
    } elseif ($nacimiento >= $hoy) {
        $errores['fecha_nacimiento'] = 'La fecha de nacimiento no puede ser hoy ni una fecha futura.';
    } elseif ($nacimiento->diff($hoy)->y < EDAD_MINIMA_TRABAJADOR) {
        $errores['fecha_nacimiento'] = 'El trabajador debe tener al menos ' . EDAD_MINIMA_TRABAJADOR . ' años.';
    } elseif ($nacimiento->diff($hoy)->y > EDAD_MAXIMA_TRABAJADOR) {
        $errores['fecha_nacimiento'] = 'La fecha de nacimiento indica más de ' . EDAD_MAXIMA_TRABAJADOR . ' años. Revisa el año.';
    }

    // ── Lugar de nacimiento: departamento + ciudad del catálogo DIVIPOLA (opcional) ──
    // Deben ir juntos y ser coherentes (no una ciudad de otro departamento). Si se elige
    // una ciudad, se borra el texto libre antiguo (que solo marcaba "por revisar"); si no
    // se elige nada, el texto antiguo se conserva tal cual.
    $lugar = validarLugar($conexion, $in['departamento_nacimiento'] ?? '', $in['ciudad_nacimiento'] ?? '',
                          'departamento_nacimiento', 'ciudad_nacimiento');
    $errores += $lugar['errores'];
    if ($lugar['codigo']) {
        $datos['codigo_ciudad_nacimiento'] = $lugar['codigo'];
        $datos['lugar_nacimiento'] = null;
    }

    // ── Área / Cargo (el cargo debe pertenecer al área) ──
    $area = $txt('id_area');
    $cargo = $txt('id_cargo');
    $datos['id_area'] = ctype_digit($area) ? (int)$area : null;
    $datos['id_cargo'] = ctype_digit($cargo) ? (int)$cargo : null;
    if (!$datos['id_area'] || !existeEnCatalogo($conexion, 'areas', 'id_areas', $datos['id_area'])) {
        $errores['id_area'] = 'Selecciona un área válida.';
    }
    if (!$datos['id_cargo']) {
        $errores['id_cargo'] = 'Selecciona un cargo.';
    } elseif (!isset($errores['id_area'])) {
        $stmt = $conexion->prepare('SELECT COUNT(*) FROM cargos WHERE id_cargo = ? AND id_area = ?');
        $stmt->execute([$datos['id_cargo'], $datos['id_area']]);
        if ((int)$stmt->fetchColumn() === 0) {
            $errores['id_cargo'] = 'El cargo seleccionado no pertenece al área elegida.';
        }
    }

    // ── Estado laboral ──
    $estado = $txt('estado');
    if (!in_array($estado, ['0', '1'], true)) {
        $errores['estado'] = 'Selecciona un estado laboral válido.';
    }
    $datos['estado'] = (int)$estado;

    // ── Fecha de ingreso (no futura y posterior a nacimiento + edad mínima) ──
    $fechaIng = $txt('fecha_ingreso');
    $ingreso = $fechaIng !== '' ? parsearFecha($fechaIng) : null;
    $datos['fecha_ingreso'] = $fechaIng;
    if ($fechaIng === '') {
        $errores['fecha_ingreso'] = 'La fecha de ingreso es obligatoria.';
    } elseif (!$ingreso) {
        $errores['fecha_ingreso'] = 'La fecha de ingreso no es una fecha válida.';
    } elseif ($ingreso > $hoy) {
        $errores['fecha_ingreso'] = 'La fecha de ingreso no puede ser una fecha futura.';
    } elseif ($nacimiento && !isset($errores['fecha_nacimiento'])) {
        $ingresoMinimo = (clone $nacimiento)->modify('+' . EDAD_MINIMA_TRABAJADOR . ' years');
        if ($ingreso < $ingresoMinimo) {
            $errores['fecha_ingreso'] = 'La fecha de ingreso debe ser posterior a que el trabajador cumpliera '
                . EDAD_MINIMA_TRABAJADOR . ' años (' . $ingresoMinimo->format('d/m/Y') . ').';
        }
    }

    // ── Correo (formato, dominio existente y único) ──
    $correo = mb_strtolower($txt('correo_personal'), 'UTF-8');
    $datos['correo_personal'] = $correo;
    if ($correo === '') {
        $errores['correo_personal'] = 'El correo electrónico es obligatorio.';
    } elseif (mb_strlen($correo, 'UTF-8') > 100) {
        $errores['correo_personal'] = 'El correo no puede superar 100 caracteres.';
    } elseif (!correoValido($correo)) {
        $errores['correo_personal'] = mensajeCorreoInvalido($correo);
    } elseif ($verificarDns && !dominioCorreoExiste($correo)) {
        $errores['correo_personal'] = 'El dominio del correo no existe. Revisa lo que va después de la @.';
    } else {
        $stmt = $conexion->prepare('SELECT COUNT(*) FROM trabajadores WHERE correo_personal = ? AND id_trabajador <> ?');
        $stmt->execute([$correo, $idActual ?? 0]);
        if ((int)$stmt->fetchColumn() > 0) {
            $errores['correo_personal'] = 'Ya existe otro trabajador con este correo electrónico.';
        }
    }

    // ── Teléfono / Celular: exactamente 10 dígitos (formato colombiano: celular 3XX, fijo 60X) ──
    $celular = preg_replace('/[\s\-()]/', '', (string)($in['telefono'] ?? ''));
    $datos['celular'] = $celular;
    if ($celular === '') {
        $errores['telefono'] = 'El teléfono es obligatorio.';
    } elseif (!preg_match('/^(3\d{9}|60\d{8})$/', $celular)) {
        $errores['telefono'] = 'El teléfono debe tener exactamente 10 dígitos (celular que empiece por 3, o fijo que empiece por 60).';
    }

    // ── Orientación sexual (opcional, lista cerrada) ──
    $orientacion = $txt('orientacion_sexual');
    if ($orientacion !== '' && !array_key_exists($orientacion, ORIENTACIONES_SEXUALES)) {
        $errores['orientacion_sexual'] = 'Selecciona una opción válida.';
    }
    $datos['orientacion_sexual'] = $orientacion !== '' ? $orientacion : null;

    // ── Hijos y personas a cargo ──
    $tieneHijos = $txt('tiene_hijos');
    if (!in_array($tieneHijos, ['0', '1'], true)) {
        $errores['tiene_hijos'] = 'Indica si el trabajador tiene hijos.';
    }
    $datos['tiene_hijos'] = $tieneHijos === '1' ? 1 : 0;
    // "No" => el número de hijos se fuerza a 0, llegue lo que llegue del formulario.
    $datos['numero_hijos'] = 0;
    if ($tieneHijos === '1') {
        $numHijos = $txt('numero_hijos');
        if ($numHijos === '') {
            $errores['numero_hijos'] = 'Indicaste que tiene hijos: escribe cuántos (mínimo 1).';
        } elseif (!ctype_digit($numHijos)) {
            // ctype_digit rechaza letras ("O5"), signos, decimales y espacios.
            $errores['numero_hijos'] = 'El número de hijos debe ser un número entero, sin letras ni símbolos.';
        } elseif ((int)$numHijos < 1) {
            $errores['numero_hijos'] = 'Indicaste que tiene hijos: el número debe ser al menos 1. Si no tiene, elige "No" en "¿Tiene hijos?".';
        } elseif ((int)$numHijos > MAX_HIJOS) {
            $errores['numero_hijos'] = 'El número de hijos no puede ser mayor a ' . MAX_HIJOS . '.';
        } else {
            $datos['numero_hijos'] = (int)$numHijos;
        }
    }
    $datos['tiene_personas_cargo'] = $txt('tiene_personas_cargo') === '1' ? 1 : 0;

    // ── Dotación ──
    $camisa = strtoupper($txt('talla_camisa'));
    if ($camisa !== '' && !in_array($camisa, TALLAS_CAMISA, true)) {
        $errores['talla_camisa'] = 'Selecciona una talla de camisa válida.';
    }
    $datos['talla_camisa'] = $camisa !== '' ? $camisa : null;
    foreach (['talla_pantalon' => 'pantalón', 'talla_botas' => 'botas'] as $campo => $etiqueta) {
        $valor = $txt($campo);
        if (mb_strlen($valor, 'UTF-8') > 10) {
            $errores[$campo] = "La talla de $etiqueta no puede superar 10 caracteres.";
        }
        $datos[$campo] = $valor !== '' ? $valor : null;
    }

    // ── Observaciones ──
    foreach (['observaciones', 'observaciones_familiares'] as $campo) {
        $valor = trim((string)($in[$campo] ?? ''));
        if (mb_strlen($valor, 'UTF-8') > 2000) {
            $errores[$campo] = 'Las observaciones no pueden superar 2000 caracteres.';
        }
        $datos[$campo] = $valor !== '' ? $valor : null;
    }

    return ['datos' => $datos, 'errores' => $errores];
}

// ── Ayudas para las vistas: errores por campo y valores enviados ──

// Guarda errores y datos enviados en sesión para mostrarlos al volver al formulario.
function guardarErroresFormulario(array $errores, array $old): void {
    unset($old['id_trabajador']);
    $_SESSION['form_trabajador'] = ['errores' => $errores, 'old' => $old];
}

// Lee (una sola vez) los errores y datos guardados por guardarErroresFormulario().
function tomarErroresFormulario(): array {
    $form = $_SESSION['form_trabajador'] ?? ['errores' => [], 'old' => []];
    unset($_SESSION['form_trabajador']);
    return $form;
}

// <option> de un catálogo ([['id'=>..,'nombre'=>..], ...]) marcando el seleccionado.
function opcionesSelect(array $opciones, $seleccionado): string {
    $html = '';
    foreach ($opciones as $op) {
        $sel = (string)$op['id'] === (string)$seleccionado ? ' selected' : '';
        $html .= '<option value="' . htmlspecialchars((string)$op['id'], ENT_QUOTES, 'UTF-8') . '"' . $sel . '>'
            . htmlspecialchars((string)$op['nombre'], ENT_QUOTES, 'UTF-8') . '</option>';
    }
    return $html;
}

// Opciones de todos los catálogos del formulario, listas para opcionesSelect().
function opcionesFormularioTrabajador(PDO $conexion): array {
    $opciones = [];
    foreach (catalogosTrabajador() as $campo => [$tabla, $idColumna, $nombreColumna]) {
        $opciones[$campo] = opcionesCatalogo($conexion, $tabla, $idColumna, $nombreColumna);
    }
    $opciones['id_area'] = opcionesCatalogo($conexion, 'areas', 'id_areas', 'nombre_area');
    $opciones['orientacion_sexual'] = array_map(
        fn($id, $nombre) => ['id' => $id, 'nombre' => $nombre],
        array_keys(ORIENTACIONES_SEXUALES),
        ORIENTACIONES_SEXUALES
    );
    $opciones['talla_camisa'] = array_map(fn($t) => ['id' => $t, 'nombre' => $t], TALLAS_CAMISA);
    return $opciones;
}

function cargosDeArea(PDO $conexion, $idArea): array {
    if (!ctype_digit((string)$idArea)) {
        return [];
    }
    $stmt = $conexion->prepare('SELECT id_cargo AS id, nombre_cargo AS nombre FROM cargos WHERE id_area = ? ORDER BY nombre_cargo');
    $stmt->execute([(int)$idArea]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function claseError(array $errores, string $campo): string {
    return isset($errores[$campo]) ? ' has-error' : '';
}

function mensajeError(array $errores, string $campo): string {
    return isset($errores[$campo])
        ? '<div class="field-error">' . htmlspecialchars($errores[$campo], ENT_QUOTES, 'UTF-8') . '</div>'
        : '';
}
