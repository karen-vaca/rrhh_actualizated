<?php
// Validación de fechas compartida por Contratación, Novedades y Perfil de salud.
// Regla: una fecha inválida se RECHAZA con un mensaje; nunca se corrige en silencio
// (DateTime::createFromFormat('Y-m-d') convierte 2026-02-30 en 2026-03-02 sin avisar).

if (!function_exists('validarFechaReal')) {
    /**
     * Valida una fecha 'AAAA-MM-DD' exigiendo que el día exista.
     *
     * @return array{valor: ?string, error: ?string}
     */
    function validarFechaReal($raw, string $etiqueta, bool $obligatorio): array {
        $texto = trim((string)($raw ?? ''));
        if ($texto === '') {
            return $obligatorio
                ? ['valor' => null, 'error' => "$etiqueta es obligatoria."]
                : ['valor' => null, 'error' => null];
        }
        $dt = DateTime::createFromFormat('!Y-m-d', $texto);
        if (!$dt || $dt->format('Y-m-d') !== $texto) {
            return ['valor' => null, 'error' => "$etiqueta no es una fecha válida (ese día no existe)."];
        }
        return ['valor' => $texto, 'error' => null];
    }

    /**
     * Fecha de inicio (obligatoria) y fecha de fin (opcional) de un periodo: ambas deben
     * existir y el fin no puede ser anterior al inicio (pueden ser el mismo día).
     *
     * @return array{datos: array<string,?string>, errores: array<string,string>}
     */
    function validarRangoFechas(array $in, string $campoInicio = 'fecha_inicio', string $campoFin = 'fecha_fin'): array {
        $inicio = validarFechaReal($in[$campoInicio] ?? '', 'La fecha de inicio', true);
        $fin = validarFechaReal($in[$campoFin] ?? '', 'La fecha de fin', false);

        $errores = [];
        if ($inicio['error']) {
            $errores[$campoInicio] = $inicio['error'];
        }
        if ($fin['error']) {
            $errores[$campoFin] = $fin['error'];
        } elseif ($inicio['valor'] && $fin['valor'] && $fin['valor'] < $inicio['valor']) {
            $errores[$campoFin] = 'La fecha de fin no puede ser anterior a la fecha de inicio.';
        }

        return ['datos' => [$campoInicio => $inicio['valor'], $campoFin => $fin['valor']], 'errores' => $errores];
    }
}
