<?php
// Lugares de Colombia (catálogo DIVIPOLA del DANE: tablas departamentos y ciudades,
// cargadas desde db/catalogo_divipola.sql). Lo usan los formularios que piden un lugar
// (departamento + ciudad en cascada) y la migración del antiguo texto libre.

if (!function_exists('normalizarLugar')) {

    // Minúsculas, sin tildes, sin signos y sin "D.C.", para comparar nombres escritos a mano.
    function normalizarLugar(?string $texto): string {
        $t = mb_strtolower(trim((string)$texto), 'UTF-8');
        $t = strtr($t, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
        $t = preg_replace('/\bd\s*\.?\s*c\b\.?/u', ' ', $t);   // "D.C.", "DC"
        $t = preg_replace('/[^a-z0-9 ]+/u', ' ', $t);
        return trim(preg_replace('/\s+/', ' ', $t));
    }

    // Departamentos: codigo => nombre (orden alfabético).
    function departamentosColombia(PDO $conexion): array {
        return $conexion->query('SELECT codigo, nombre FROM departamentos ORDER BY nombre')->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    // Ciudades agrupadas por departamento, para la cascada del navegador:
    // [codigo_departamento => [[codigo, nombre], ...]].
    function ciudadesPorDepartamento(PDO $conexion): array {
        $grupos = [];
        foreach ($conexion->query('SELECT codigo, codigo_departamento, nombre FROM ciudades ORDER BY nombre') as $c) {
            $grupos[$c['codigo_departamento']][] = [$c['codigo'], $c['nombre']];
        }
        return $grupos;
    }

    // Datos de una ciudad (con su departamento), o null si el código no existe.
    function ciudadColombia(PDO $conexion, ?string $codigo): ?array {
        if (!$codigo) {
            return null;
        }
        $stmt = $conexion->prepare('SELECT c.codigo, c.nombre, c.codigo_departamento, d.nombre AS departamento
                                    FROM ciudades c JOIN departamentos d ON d.codigo = c.codigo_departamento
                                    WHERE c.codigo = ?');
        $stmt->execute([$codigo]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    // "Tunja, Boyacá" a partir del código de ciudad.
    function nombreLugar(PDO $conexion, ?string $codigoCiudad): string {
        $c = ciudadColombia($conexion, $codigoCiudad);
        if (!$c) {
            return '';
        }
        return $c['nombre'] === $c['departamento'] ? $c['nombre'] : $c['nombre'] . ', ' . $c['departamento'];
    }

    /**
     * Valida la pareja departamento + ciudad de un formulario (ambos opcionales, pero
     * juntos). Rechaza combinaciones imposibles (una ciudad de otro departamento).
     *
     * @return array{codigo: ?string, errores: array<string,string>}
     */
    function validarLugar(PDO $conexion, $departamento, $ciudad, string $campoDepartamento, string $campoCiudad): array {
        $dep = trim((string)($departamento ?? ''));
        $ciu = trim((string)($ciudad ?? ''));

        if ($dep === '' && $ciu === '') {
            return ['codigo' => null, 'errores' => []];
        }
        if ($dep === '') {
            return ['codigo' => null, 'errores' => [$campoDepartamento => 'Selecciona el departamento.']];
        }
        if (!preg_match('/^\d{2}$/', $dep) || !isset(departamentosColombia($conexion)[$dep])) {
            return ['codigo' => null, 'errores' => [$campoDepartamento => 'Selecciona un departamento de la lista.']];
        }
        if ($ciu === '') {
            return ['codigo' => null, 'errores' => [$campoCiudad => 'Selecciona la ciudad o municipio.']];
        }
        $c = preg_match('/^\d{5}$/', $ciu) ? ciudadColombia($conexion, $ciu) : null;
        if (!$c) {
            return ['codigo' => null, 'errores' => [$campoCiudad => 'Selecciona una ciudad o municipio de la lista.']];
        }
        if ($c['codigo_departamento'] !== $dep) {
            return ['codigo' => null, 'errores' => [$campoCiudad => 'La ciudad seleccionada no pertenece al departamento elegido.']];
        }
        return ['codigo' => $c['codigo'], 'errores' => []];
    }

    /**
     * HTML de la pareja Departamento + Ciudad (selects con buscador en cascada). Requiere
     * en la página select_buscador.js/.css, lugares.js y scriptCiudadesPorDepartamento().
     *
     * @param array $campos  ['departamento' => name, 'ciudad' => name, 'etiqueta' => texto]
     * @param array $estilo  ['grupo' => clase del contenedor, 'label' => clase de la etiqueta, 'select' => clase del select]
     */
    function camposLugarHtml(PDO $conexion, array $campos, ?string $codigoCiudad, ?string $codigoDepartamento,
                             array $errores, array $estilo): string {
        $e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
        $ciudad = ciudadColombia($conexion, $codigoCiudad);
        $dep = $codigoDepartamento ?: ($ciudad['codigo_departamento'] ?? '');
        $idCiudad = 'sel_' . $campos['ciudad'];

        $err = function (string $campo) use ($errores, $e) {
            return isset($errores[$campo]) ? '<div class="field-error">' . $e($errores[$campo]) . '</div>' : '';
        };
        $clase = fn(string $campo) => $estilo['select'] . (isset($errores[$campo]) ? ' has-error' : '');

        $opcionesDep = '<option value="">Seleccione el departamento...</option>';
        foreach (departamentosColombia($conexion) as $cod => $nom) {
            $cod = str_pad((string)$cod, 2, '0', STR_PAD_LEFT);
            $opcionesDep .= '<option value="' . $cod . '"' . ($cod === $dep ? ' selected' : '') . '>' . $e($nom) . '</option>';
        }
        // La lista de ciudades la arma lugares.js; aquí solo va la seleccionada (sin JS igual se envía).
        $opcionesCiudad = '<option value="">' . ($dep ? 'Seleccione la ciudad o municipio...' : 'Primero elija el departamento') . '</option>';
        if ($ciudad) {
            $opcionesCiudad .= '<option value="' . $e($ciudad['codigo']) . '" selected>' . $e($ciudad['nombre']) . '</option>';
        }

        return '<div class="' . $estilo['grupo'] . '">'
            . '<label class="' . $estilo['label'] . '" for="sel_' . $e($campos['departamento']) . '">' . $e($campos['etiqueta']) . ' — departamento</label>'
            . '<select class="' . $clase($campos['departamento']) . '" id="sel_' . $e($campos['departamento']) . '" name="' . $e($campos['departamento']) . '" data-buscador data-cascada-ciudad="' . $e($idCiudad) . '">' . $opcionesDep . '</select>'
            . $err($campos['departamento'])
            . '</div>'
            . '<div class="' . $estilo['grupo'] . '">'
            . '<label class="' . $estilo['label'] . '" for="' . $e($idCiudad) . '">' . $e($campos['etiqueta']) . ' — ciudad o municipio</label>'
            . '<select class="' . $clase($campos['ciudad']) . '" id="' . $e($idCiudad) . '" name="' . $e($campos['ciudad']) . '" data-buscador>' . $opcionesCiudad . '</select>'
            . $err($campos['ciudad'])
            . '</div>';
    }

    // <script> con las ciudades por departamento para la cascada de lugares.js (una vez por página).
    function scriptCiudadesPorDepartamento(PDO $conexion): string {
        $datos = [];
        foreach (ciudadesPorDepartamento($conexion) as $dep => $ciudades) {
            $datos[str_pad((string)$dep, 2, '0', STR_PAD_LEFT)] = $ciudades;
        }
        return '<script>window.CIUDADES_POR_DEPARTAMENTO = ' . json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . ';</script>';
    }

    /**
     * Migra el lugar de nacimiento en texto libre a la ciudad del catálogo, para los
     * trabajadores que aún no tienen ciudad. Solo asigna coincidencias únicas; nunca
     * borra el texto original. Si ya hay una transacción abierta (tests), la usa.
     *
     * @return array{asignados: array, revision: array}
     */
    function migrarLugaresNacimiento(PDO $conexion): array {
        $pendientes = $conexion->query("SELECT id_trabajador, nombres, apellidos, lugar_nacimiento FROM trabajadores
                                        WHERE codigo_ciudad_nacimiento IS NULL AND TRIM(COALESCE(lugar_nacimiento, '')) <> ''
                                        ORDER BY id_trabajador")->fetchAll(PDO::FETCH_ASSOC);
        $asignar = $conexion->prepare('UPDATE trabajadores SET codigo_ciudad_nacimiento = ? WHERE id_trabajador = ? AND codigo_ciudad_nacimiento IS NULL');
        $resultado = ['asignados' => [], 'revision' => []];

        $propia = !$conexion->inTransaction();
        if ($propia) {
            $conexion->beginTransaction();
        }
        foreach ($pendientes as $t) {
            $r = interpretarLugarTexto($conexion, $t['lugar_nacimiento']);
            if ($r['codigo']) {
                $asignar->execute([$r['codigo'], $t['id_trabajador']]);
                $resultado['asignados'][] = $t + ['codigo' => $r['codigo']];
            } else {
                $resultado['revision'][] = $t + ['motivo' => $r['motivo']];
            }
        }
        if ($propia) {
            $conexion->commit();
        }
        return $resultado;
    }

    /**
     * Interpreta un lugar escrito como texto libre ("Tunja", "Tunja, Boyacá"...).
     * Solo asigna una ciudad cuando la coincidencia es única y sin ambigüedad; en
     * cualquier otro caso devuelve el motivo para revisarlo a mano.
     *
     * @return array{codigo: ?string, motivo: ?string}
     */
    function interpretarLugarTexto(PDO $conexion, ?string $texto): array {
        $n = normalizarLugar($texto);
        if ($n === '') {
            return ['codigo' => null, 'motivo' => null];
        }
        $sinDato = ['no especificado', 'no especifica', 'sin dato', 'sin datos', 'sin informacion', 'n a', 'na',
                    'ninguno', 'no aplica', 'desconocido', 'no registra', 'no sabe'];
        if (in_array($n, $sinDato, true)) {
            return ['codigo' => null, 'motivo' => 'El texto no indica un lugar ("' . trim((string)$texto) . '").'];
        }

        // Índices normalizados del catálogo.
        static $cache = null;
        if ($cache === null) {
            $cache = ['deps' => [], 'ciudades' => []];
            foreach (departamentosColombia($conexion) as $cod => $nom) {
                // (string): PHP convierte la llave "15" en el entero 15 (pero deja "05" como texto).
                $cache['deps'][normalizarLugar($nom)] = (string)$cod;
            }
            // Formas cortas usuales de algunos departamentos.
            $cache['deps'] += ['valle' => '76', 'guajira' => '44', 'san andres' => '88', 'bogota' => '11',
                               'norte santander' => '54', 'san andres y providencia' => '88'];
            foreach ($conexion->query('SELECT codigo, codigo_departamento, nombre FROM ciudades') as $c) {
                $cache['ciudades'][normalizarLugar($c['nombre'])][] = $c;
            }
        }

        if (in_array($n, ['bogota', 'santa fe de bogota', 'santafe de bogota'], true)) {
            return ['codigo' => '11001', 'motivo' => null];
        }

        // "Ciudad, Departamento" (también con " - " o "/").
        $partes = preg_split('/\s*(?:,|\s-\s|\/)\s*/u', trim((string)$texto), 2);
        $ciudad = normalizarLugar($partes[0]);
        $departamento = isset($partes[1]) ? normalizarLugar($partes[1]) : '';
        $candidatas = $cache['ciudades'][$ciudad] ?? [];

        // Nombres de uso común que en DIVIPOLA tienen otro nombre oficial.
        $nombresComunes = [
            'cali' => 'santiago de cali', 'cucuta' => 'san jose de cucuta', 'cartagena' => 'cartagena de indias',
            'tumaco' => 'san andres de tumaco', 'mompox' => 'santa cruz de mompox', 'mompos' => 'santa cruz de mompox',
            'tolu' => 'santiago de tolu', 'buga' => 'guadalajara de buga',
        ];
        if (!$candidatas && isset($nombresComunes[$ciudad])) {
            $candidatas = $cache['ciudades'][$nombresComunes[$ciudad]] ?? [];
        }

        if ($departamento !== '') {
            $codDep = $cache['deps'][$departamento] ?? null;
            if (!$codDep) {
                return ['codigo' => null, 'motivo' => 'No se reconoce el departamento "' . trim($partes[1]) . '".'];
            }
            foreach ($candidatas as $c) {
                if ($c['codigo_departamento'] === $codDep) {
                    return ['codigo' => $c['codigo'], 'motivo' => null];
                }
            }
            return ['codigo' => null, 'motivo' => 'No existe un municipio "' . trim($partes[0]) . '" en ' . trim($partes[1]) . '.'];
        }

        if (count($candidatas) === 1) {
            return ['codigo' => $candidatas[0]['codigo'], 'motivo' => null];
        }
        if (count($candidatas) > 1) {
            return ['codigo' => null, 'motivo' => 'Hay ' . count($candidatas) . ' municipios llamados "' . trim((string)$texto) . '"; falta el departamento.'];
        }
        return ['codigo' => null, 'motivo' => 'No se encontró el municipio "' . trim((string)$texto) . '" en el catálogo DIVIPOLA.'];
    }
}
