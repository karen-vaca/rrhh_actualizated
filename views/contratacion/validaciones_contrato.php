<?php
// Validaciones del formulario de contratación. Las usa guardar.php y las cubren los
// tests de tests/ValidacionContratoTest.php. Regla general: un valor inválido se
// RECHAZA con un mensaje claro; nunca se "limpia" en silencio (antes, "2O00000" con la
// letra O se guardaba como 200000 sin avisar).

// Límite preventivo para evitar errores técnicos de MySQL por valores fuera de rango.
require_once __DIR__ . '/../components/validaciones_fechas.php';

// Valores legales vigentes (Colombia, 2026). Actualizar cada año.
//   Salario mínimo: Decreto 1469 de 2025. Auxilio de transporte: Decreto 1470 de 2025.
// El auxilio de transporte solo aplica a quien gana hasta 2 salarios mínimos.
const SALARIO_MINIMO_VIGENTE = 1750905;
const AUXILIO_TRANSPORTE_VIGENTE = 249095;
const TOPE_SALARIOS_MINIMOS_AUXILIO = 2;

// Auxilio de transporte que corresponde por ley a un salario dado.
function auxilioTransporteLegal(float $salario): float {
    return $salario > 0 && $salario <= TOPE_SALARIOS_MINIMOS_AUXILIO * SALARIO_MINIMO_VIGENTE
        ? (float)AUXILIO_TRANSPORTE_VIGENTE
        : 0.0;
}

if (!defined('MAX_VALOR_MONETARIO')) {
    define('MAX_VALOR_MONETARIO', 99999999.99);
}

/**
 * Valida un monto en pesos escrito por el usuario y lo convierte a número.
 *
 * Formatos aceptados (solo dígitos, con separadores colombianos opcionales):
 *   1300000   1.300.000   1.300.000,50   1300000,50   1300000.50
 * Cualquier otro carácter (letras, $, espacios internos, signos) se rechaza.
 *
 * @return array{valor: ?float, error: ?string}
 */
function validarMonto($raw, string $etiqueta, bool $obligatorio, bool $permiteCero): array {
    $texto = trim((string)($raw ?? ''));

    if ($texto === '') {
        return $obligatorio
            ? ['valor' => null, 'error' => "$etiqueta es obligatorio."]
            : ['valor' => 0.0, 'error' => null];
    }

    if (str_contains($texto, '-')) {
        return ['valor' => null, 'error' => "$etiqueta no puede ser negativo."];
    }

    $formatos = [
        '/^\d+$/',                            // 1300000
        '/^\d{1,3}(\.\d{3})+(,\d{1,2})?$/',   // 1.300.000 y 1.300.000,50
        '/^\d+,\d{1,2}$/',                    // 1300000,50
        '/^\d+\.\d{1,2}$/',                   // 1300000.50
    ];
    $formatoValido = false;
    foreach ($formatos as $patron) {
        if (preg_match($patron, $texto)) {
            $formatoValido = true;
            break;
        }
    }

    if (!$formatoValido) {
        return ['valor' => null, 'error' => preg_match('/^[\d.,]+$/', $texto)
            ? "$etiqueta tiene los puntos o comas mal ubicados. Escríbelo solo con números, por ejemplo 1300000."
            : "$etiqueta solo puede contener números, sin letras ni símbolos."];
    }

    // Normalizar a formato numérico de PHP.
    if (str_contains($texto, ',')) {
        $numero = str_replace(',', '.', str_replace('.', '', $texto));
    } elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $texto)) {
        $numero = str_replace('.', '', $texto);
    } else {
        $numero = $texto;
    }
    $valor = (float)$numero;

    if (!$permiteCero && $valor <= 0) {
        return ['valor' => null, 'error' => "$etiqueta debe ser mayor que cero."];
    }
    if ($valor > MAX_VALOR_MONETARIO) {
        return ['valor' => null, 'error' => "$etiqueta supera el máximo permitido."];
    }

    return ['valor' => $valor, 'error' => null];
}

/**
 * Fecha de inicio (obligatoria), fecha de fin (opcional) y que el fin no sea anterior
 * al inicio. Pueden ser el mismo día (contrato de un solo día).
 * La regla de fechas es compartida: ver views/components/validaciones_fechas.php.
 *
 * @return array{datos: array, errores: array<string,string>}
 */
function validarFechasContrato(array $in): array {
    return validarRangoFechas($in, 'fecha_inicio', 'fecha_fin');
}

// Jefe inmediato: no existe un catálogo de jefes (y los ya registrados no son
// trabajadores del sistema), así que es texto libre con formato de nombre de persona.
const PATRON_NOMBRE_JEFE = "/^[\\p{L}][\\p{L} .'-]*[\\p{L}.]$/u";

/** @return array{valor: ?string, error: ?string} */
function validarJefeInmediato($raw): array {
    $texto = trim(preg_replace('/\s+/u', ' ', (string)($raw ?? '')) ?? (string)$raw);
    if ($texto === '') {
        return ['valor' => null, 'error' => 'El jefe inmediato es obligatorio.'];
    }
    $largo = mb_strlen($texto, 'UTF-8');
    if ($largo < 3 || $largo > 120) {
        return ['valor' => null, 'error' => 'El nombre del jefe inmediato debe tener entre 3 y 120 caracteres.'];
    }
    if (!preg_match(PATRON_NOMBRE_JEFE, $texto)) {
        return ['valor' => null, 'error' => 'El nombre del jefe inmediato solo puede contener letras y espacios (sin números ni símbolos).'];
    }
    return ['valor' => $texto, 'error' => null];
}

/**
 * Todas las validaciones de datos escritos por el usuario en el formulario de
 * contrato, en el orden en que aparecen los campos.
 *
 * @return array{datos: array, errores: array<string,string>}
 */
function validarContratoPost(array $in): array {
    $jefe = validarJefeInmediato($in['jefe_inmediato'] ?? '');
    $fechas = validarFechasContrato($in);
    $montos = validarMontosContrato($in);

    $errores = [];
    if ($jefe['error']) {
        $errores['jefe_inmediato'] = $jefe['error'];
    }
    $errores += $fechas['errores'] + $montos['errores'];

    return [
        'datos' => ['jefe_inmediato' => $jefe['valor']] + $fechas['datos'] + $montos['datos'],
        'errores' => $errores,
    ];
}

/**
 * Valida los campos de dinero del contrato.
 *
 * @return array{datos: array{salario_base: ?float, auxilio_transporte: ?float}, errores: array<string,string>}
 */
function validarMontosContrato(array $in): array {
    $salario = validarMonto($in['salario_base'] ?? '', 'El salario', true, false);
    $auxilio = validarMonto($in['auxilio_transporte'] ?? '', 'El auxilio de transporte', false, true);

    $errores = [];
    if ($salario['error']) {
        $errores['salario_base'] = $salario['error'];
    }
    if ($auxilio['error']) {
        $errores['auxilio_transporte'] = $auxilio['error'];
    }

    return [
        'datos' => ['salario_base' => $salario['valor'], 'auxilio_transporte' => $auxilio['valor']],
        'errores' => $errores,
    ];
}
