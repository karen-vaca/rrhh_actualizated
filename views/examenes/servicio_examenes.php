<?php
// Reglas y operaciones de Exámenes Médicos. Las usan programar_examen.php y
// registrar_resultado.php (que solo validan acceso y redirigen) y las cubren los tests
// de tests/ExamenesTest.php.
//
// Registrar un resultado es la FUENTE de lo que Perfil de Salud consolida:
//   1. actualiza el examen (realizado, resultado, próximo examen);
//   2. actualiza perfil_salud (aptitud, fecha de evaluación, vencimiento);
//   3. agrega un evento en historial_medico (alimenta el timeline).

require_once __DIR__ . '/../components/validaciones_fechas.php';

// Mismos valores que perfil_salud.aptitud.
const RESULTADOS_EXAMEN = [
    'apto'        => 'Apto',
    'restriccion' => 'Apto con restricciones',
    'no_apto'     => 'No apto',
    'pendiente'   => 'Pendiente',
];

// Concepto de trabajo en alturas (campo opcional del resultado; vacío = no aplica).
const CONCEPTOS_ALTURAS = [
    'apto'    => 'Apto para trabajo en alturas',
    'no_apto' => 'No apto para trabajo en alturas',
];

// Catálogo de tipos: codigo => [id, nombre, meses_vigencia].
function tiposExamen(PDO $conexion): array {
    $tipos = [];
    foreach ($conexion->query('SELECT id_examen, codigo, nombre_examen, meses_vigencia FROM tipo_examen ORDER BY id_examen') as $t) {
        $tipos[$t['codigo']] = [
            'id' => (int)$t['id_examen'],
            'nombre' => $t['nombre_examen'],
            'meses' => $t['meses_vigencia'] !== null ? (int)$t['meses_vigencia'] : null,
        ];
    }
    return $tipos;
}

// Próximo examen por defecto: fecha de realización + vigencia del tipo (1 año por
// defecto). Un tipo sin vigencia (retiro) no genera próximo examen.
function proximaFechaPorDefecto(string $fechaRealizado, ?int $meses): ?string {
    if (!$meses) {
        return null;
    }
    return (new DateTime($fechaRealizado))->modify("+$meses months")->format('Y-m-d');
}

/**
 * Valida la programación de un examen.
 * @return array{datos: array, errores: array<string,string>}
 */
function validarProgramacionExamen(PDO $conexion, array $in): array {
    $errores = [];
    $tipos = tiposExamen($conexion);

    $idTrabajador = (string)($in['id_trabajador'] ?? '');
    if (!ctype_digit($idTrabajador)) {
        $errores['id_trabajador'] = 'Selecciona un trabajador.';
    } else {
        $stmt = $conexion->prepare('SELECT estado FROM trabajadores WHERE id_trabajador = ?');
        $stmt->execute([(int)$idTrabajador]);
        $estado = $stmt->fetchColumn();
        if ($estado === false) {
            $errores['id_trabajador'] = 'El trabajador seleccionado no existe.';
        } elseif ((int)$estado !== 1) {
            $errores['id_trabajador'] = 'El trabajador está inactivo; no se le pueden programar exámenes.';
        }
    }

    $tipo = trim((string)($in['tipo'] ?? ''));
    if (!isset($tipos[$tipo])) {
        $errores['tipo'] = 'Selecciona un tipo de examen válido.';
    }

    $fecha = validarFechaReal($in['fecha_programada'] ?? '', 'La fecha programada', true);
    if ($fecha['error']) {
        $errores['fecha_programada'] = $fecha['error'];
    }

    // No dos exámenes pendientes del mismo tipo para el mismo trabajador.
    if (!$errores) {
        $stmt = $conexion->prepare("SELECT COUNT(*) FROM examenes_medicos
                                    WHERE id_trabajador = ? AND id_examen = ? AND estado = 'programado'");
        $stmt->execute([(int)$idTrabajador, $tipos[$tipo]['id']]);
        if ((int)$stmt->fetchColumn() > 0) {
            $errores['tipo'] = 'Este trabajador ya tiene un examen de ' . mb_strtolower($tipos[$tipo]['nombre'], 'UTF-8')
                . ' programado. Registra su resultado antes de programar otro.';
        }
    }

    return [
        'datos' => [
            'id_trabajador' => (int)$idTrabajador,
            'id_examen' => $tipos[$tipo]['id'] ?? null,
            'fecha_programada' => $fecha['valor'],
        ],
        'errores' => $errores,
    ];
}

/** @return array{ok: bool, id: ?int, errores: array<string,string>} */
function programarExamen(PDO $conexion, array $in): array {
    $v = validarProgramacionExamen($conexion, $in);
    if ($v['errores']) {
        return ['ok' => false, 'id' => null, 'errores' => $v['errores']];
    }
    $d = $v['datos'];
    $stmt = $conexion->prepare("INSERT INTO examenes_medicos (id_trabajador, id_examen, fecha_programada, estado)
                                VALUES (?, ?, ?, 'programado')");
    $stmt->execute([$d['id_trabajador'], $d['id_examen'], $d['fecha_programada']]);
    return ['ok' => true, 'id' => (int)$conexion->lastInsertId(), 'errores' => []];
}

/**
 * Valida el registro del resultado de un examen programado.
 * @return array{datos: array, errores: array<string,string>, examen: ?array}
 */
function validarResultadoExamen(PDO $conexion, array $in): array {
    $errores = [];

    $idExamen = (string)($in['id_examen'] ?? '');
    $examen = null;
    if (ctype_digit($idExamen)) {
        $stmt = $conexion->prepare('SELECT e.*, t.codigo AS tipo_codigo, t.nombre_examen, t.meses_vigencia
                                    FROM examenes_medicos e JOIN tipo_examen t ON t.id_examen = e.id_examen
                                    WHERE e.id_examenes_medicos = ?');
        $stmt->execute([(int)$idExamen]);
        $examen = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    if (!$examen) {
        return ['datos' => [], 'errores' => ['id_examen' => 'El examen no existe.'], 'examen' => null];
    }
    if ($examen['estado'] !== 'programado') {
        return ['datos' => [], 'errores' => ['id_examen' => 'Este examen ya tiene un resultado registrado.'], 'examen' => $examen];
    }

    $fecha = validarFechaReal($in['fecha_realizado'] ?? '', 'La fecha de realización', true);
    if ($fecha['error']) {
        $errores['fecha_realizado'] = $fecha['error'];
    } elseif ($fecha['valor'] > date('Y-m-d')) {
        $errores['fecha_realizado'] = 'La fecha de realización no puede ser una fecha futura.';
    }

    $resultado = trim((string)($in['resultado'] ?? ''));
    if (!array_key_exists($resultado, RESULTADOS_EXAMEN)) {
        $errores['resultado'] = 'Selecciona un resultado válido (apto, apto con restricciones, no apto o pendiente).';
    }

    $alturas = trim((string)($in['concepto_alturas'] ?? ''));
    if ($alturas !== '' && !array_key_exists($alturas, CONCEPTOS_ALTURAS)) {
        $errores['concepto_alturas'] = 'Selecciona un concepto de alturas válido.';
    }

    // Próximo examen: si se deja vacío se calcula con la vigencia del tipo.
    $proxima = validarFechaReal($in['proxima_fecha'] ?? '', 'La próxima fecha de examen', false);
    if ($proxima['error']) {
        $errores['proxima_fecha'] = $proxima['error'];
    } elseif ($proxima['valor'] && $fecha['valor'] && $proxima['valor'] <= $fecha['valor']) {
        $errores['proxima_fecha'] = 'La próxima fecha de examen debe ser posterior a la fecha de realización.';
    }

    $observaciones = trim((string)($in['observaciones'] ?? ''));
    if (mb_strlen($observaciones, 'UTF-8') > 2000) {
        $errores['observaciones'] = 'Las observaciones no pueden superar 2000 caracteres.';
    }

    $proximaFinal = $proxima['valor'];
    if (!$errores && $proximaFinal === null) {
        $proximaFinal = proximaFechaPorDefecto($fecha['valor'], $examen['meses_vigencia'] !== null ? (int)$examen['meses_vigencia'] : null);
    }

    return [
        'datos' => [
            'id_examen' => (int)$idExamen,
            'fecha_realizado' => $fecha['valor'],
            'resultado' => $resultado,
            'concepto_alturas' => $alturas !== '' ? $alturas : null,
            'proxima_fecha' => $proximaFinal,
            'observaciones' => $observaciones !== '' ? $observaciones : null,
        ],
        'errores' => $errores,
        'examen' => $examen,
    ];
}

/**
 * Registra el resultado: examen + perfil de salud + historial, todo o nada.
 * Si ya hay una transacción abierta (tests), se usa esa en vez de abrir otra.
 *
 * @return array{ok: bool, errores: array<string,string>, datos: array}
 */
function registrarResultadoExamen(PDO $conexion, array $in): array {
    $v = validarResultadoExamen($conexion, $in);
    if ($v['errores']) {
        return ['ok' => false, 'errores' => $v['errores'], 'datos' => []];
    }
    $d = $v['datos'];
    $examen = $v['examen'];

    $transaccionPropia = !$conexion->inTransaction();
    if ($transaccionPropia) {
        $conexion->beginTransaction();
    }
    try {
        $stmt = $conexion->prepare("UPDATE examenes_medicos
            SET estado = 'realizado', fecha_realizado = ?, resultado = ?, concepto_alturas = ?,
                proxima_fecha = ?, observaciones = ?
            WHERE id_examenes_medicos = ? AND estado = 'programado'");
        $stmt->execute([$d['fecha_realizado'], $d['resultado'], $d['concepto_alturas'],
                        $d['proxima_fecha'], $d['observaciones'], $d['id_examen']]);
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('El examen cambió mientras se registraba el resultado.');
        }

        // Perfil de salud: solo se actualiza si este es el examen más reciente del
        // trabajador (registrar tarde un examen viejo no debe pisar uno nuevo).
        $stmt = $conexion->prepare("
            INSERT INTO perfil_salud (id_trabajador, aptitud, fecha_evaluacion, fecha_vencimiento, actualizado_en)
            VALUES (:t, :a, :f, :v, NOW())
            ON DUPLICATE KEY UPDATE
                aptitud           = IF(fecha_evaluacion IS NULL OR VALUES(fecha_evaluacion) >= fecha_evaluacion, VALUES(aptitud), aptitud),
                fecha_vencimiento = IF(fecha_evaluacion IS NULL OR VALUES(fecha_evaluacion) >= fecha_evaluacion, VALUES(fecha_vencimiento), fecha_vencimiento),
                actualizado_en    = IF(fecha_evaluacion IS NULL OR VALUES(fecha_evaluacion) >= fecha_evaluacion, NOW(), actualizado_en),
                fecha_evaluacion  = IF(fecha_evaluacion IS NULL OR VALUES(fecha_evaluacion) >= fecha_evaluacion, VALUES(fecha_evaluacion), fecha_evaluacion)
        ");
        $stmt->execute([':t' => $examen['id_trabajador'], ':a' => $d['resultado'], ':f' => $d['fecha_realizado'], ':v' => $d['proxima_fecha']]);

        $detalle = 'Resultado: ' . RESULTADOS_EXAMEN[$d['resultado']];
        if ($d['concepto_alturas']) {
            $detalle .= ' · ' . CONCEPTOS_ALTURAS[$d['concepto_alturas']];
        }
        $detalle .= ' · Próximo examen: ' . ($d['proxima_fecha'] ? date('d/m/Y', strtotime($d['proxima_fecha'])) : 'no aplica');
        if ($d['observaciones']) {
            $detalle .= ' · ' . $d['observaciones'];
        }
        $stmt = $conexion->prepare('INSERT INTO historial_medico (id_trabajador, fecha, titulo, detalle, creado_en) VALUES (?, ?, ?, ?, NOW())');
        $stmt->execute([$examen['id_trabajador'], $d['fecha_realizado'], 'Examen médico de ' . mb_strtolower($examen['nombre_examen'], 'UTF-8'), $detalle]);

        if ($transaccionPropia) {
            $conexion->commit();
        }
    } catch (Throwable $e) {
        if ($transaccionPropia && $conexion->inTransaction()) {
            $conexion->rollBack();
        }
        throw $e;
    }

    return ['ok' => true, 'errores' => [], 'datos' => $d];
}
