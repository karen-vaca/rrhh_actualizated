<?php
// Validaciones de las restricciones médicas (Perfil de salud). Las usa
// guardar_restriccion.php; el formulario de index.php arma su lista de tipos desde
// TIPOS_RESTRICCION para que frontend y backend acepten exactamente los mismos.
// Cubiertas por tests/ValidacionRestriccionTest.php.

require_once __DIR__ . '/../components/validaciones_fechas.php';

const TIPOS_RESTRICCION = [
    'No levantar cargas pesadas',
    'Restricción de trabajo en alturas',
    'Restricción de turnos nocturnos',
    'Reubicación temporal de puesto',
    'Otra',
];

/**
 * @return array{datos: array, errores: array<string,string>}
 */
function validarRestriccion(array $in): array {
    $errores = [];

    $tipo = trim((string)($in['tipo'] ?? ''));
    if ($tipo === '') {
        $errores['tipo'] = 'Selecciona el tipo de restricción.';
    } elseif (!in_array($tipo, TIPOS_RESTRICCION, true)) {
        $errores['tipo'] = 'El tipo de restricción no es válido. Elige una opción de la lista.';
    }

    $rango = validarRangoFechas($in, 'fecha_inicio', 'fecha_fin');
    $errores += $rango['errores'];

    $descripcion = trim((string)($in['descripcion'] ?? ''));
    if ($descripcion === '') {
        $errores['descripcion'] = 'La descripción o recomendación médica es obligatoria.';
    }

    return [
        'datos' => ['tipo' => $tipo, 'descripcion' => $descripcion] + $rango['datos'],
        'errores' => $errores,
    ];
}
