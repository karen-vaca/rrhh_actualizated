<?php
require_once __DIR__ . '/../../config/auth.php';
requerirAcceso();
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    ini_set('log_errors', 1);
    error_reporting(E_ALL);

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    require_once '../../config/conexion.php';
    require_once __DIR__ . '/validaciones_contrato.php';

    if (!isset($conexion) && isset($pdo)) {
        $conexion = $pdo;
    }

    if (!isset($conexion) || !($conexion instanceof PDO)) {
        die('Error: no se encontró una conexión PDO válida. Revisa ../../config/conexion.php');
    }

    $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conexion->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // MAX_VALOR_MONETARIO (límite de salario/auxilio) se define en validaciones_contrato.php.

    function post(string $key, $default = '') {
   return isset($_POST[$key]) ? trim((string)$_POST[$key]) : $default;
    }

    function postInt(string $key): ?int {
        $value = post($key);
        return $value !== '' ? (int)$value : null;
    }

    function redirectWith(string $mensaje, array $extra = []): void {
        if (post('formato') === 'json') {
            $exitosos = ['creado', 'actualizado', 'renovado', 'terminado'];
            $ok = in_array($mensaje, $exitosos, true);

            header('Content-Type: application/json; charset=utf-8');
            http_response_code($ok ? 200 : 422);
            echo json_encode(
                array_merge(['ok' => $ok, 'mensaje' => $mensaje], $extra),
                JSON_UNESCAPED_UNICODE
            );
            exit;
        }

        // Desde la página de edición (volver=editar): si se guardó, a la ficha del contrato;
        // si no, de vuelta al formulario con el aviso.
        $idVolver = (int)post('contrato_id');
        if (post('volver') === 'editar' && $idVolver > 0) {
            $destino = in_array($mensaje, ['actualizado', 'renovado', 'terminado'], true) ? 'ver.php' : 'editar.php';
            header('Location: ' . $destino . '?' . http_build_query(array_merge(['id' => $idVolver, 'mensaje' => $mensaje], $extra)));
            exit;
        }

        header('Location: index.php?' . http_build_query(array_merge(['mensaje' => $mensaje], $extra)));
        exit;
    }

    function columnaExiste(PDO $conexion, string $tabla, string $columna): bool {
        static $cache = [];
        $key = $tabla . '.' . $columna;

        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        $stmt = $conexion->prepare("
            SELECT COUNT(*) AS total
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = :tabla
            AND COLUMN_NAME = :columna
            LIMIT 1
        ");

        $stmt->execute([
            ':tabla' => $tabla,
            ':columna' => $columna,
        ]);

        $row = $stmt->fetch();
        return $cache[$key] = ((int)($row['total'] ?? 0) > 0);
    }

    function estadoContrato(?string $fechaFin): string {
        if (!$fechaFin || $fechaFin === '0000-00-00') {
            return 'Activo';
        }

        $hoy = new DateTimeImmutable('today');
        $fin = DateTimeImmutable::createFromFormat('Y-m-d', $fechaFin);

        if (!$fin) {
            return 'Activo';
        }

        if ($fin < $hoy) {
            return 'Vencido';
        }

        if ($fin <= $hoy->modify('+30 days')) {
            return 'Por vencer';
        }

        return 'Activo';
    }

    // Contratar o renovar exige un trabajador activo. Antes, contratar lo activaba en
    // silencio; ahora el estado solo cambia desde Trabajadores (activar / inactivar).
    function exigirTrabajadorActivo(PDO $conexion, ?int $idTrabajador): void {
        if (!$idTrabajador) {
            return;
        }
        $stmt = $conexion->prepare('SELECT estado FROM trabajadores WHERE id_trabajador = ?');
        $stmt->execute([$idTrabajador]);
        $estado = $stmt->fetchColumn();
        if ($estado !== false && (int)$estado !== 1) {
            redirectWith('validacion', [
                'campo' => 'id_trabajador',
                'texto' => 'El trabajador está inactivo. Reactívalo desde Trabajadores antes de crear o renovar su contrato.',
            ]);
        }
    }

    function actualizarTrabajadorDesdeContrato(
        PDO $conexion,
        int $idTrabajador,
        ?int $idArea,
        ?int $idCargo,
        ?string $fechaIngreso
    ): void {
        // No toca el estado del trabajador: solo activar.php / inactivar.php lo cambian.
        $sets = [];
        $params = [':id_trabajador' => $idTrabajador];

        if ($idArea !== null && columnaExiste($conexion, 'trabajadores', 'id_area')) {
            $sets[] = 'id_area = :id_area';
            $params[':id_area'] = $idArea;
        }

        if ($idCargo !== null && columnaExiste($conexion, 'trabajadores', 'id_cargo')) {
            $sets[] = 'id_cargo = :id_cargo';
            $params[':id_cargo'] = $idCargo;
        }

        if ($fechaIngreso && columnaExiste($conexion, 'trabajadores', 'fecha_ingreso')) {
            $sets[] = 'fecha_ingreso = :fecha_ingreso';
            $params[':fecha_ingreso'] = $fechaIngreso;
        }

        if (!$sets) {
            return;
        }

        $sql = 'UPDATE trabajadores SET ' . implode(', ', $sets) . ' WHERE id_trabajador = :id_trabajador';
        $stmt = $conexion->prepare($sql);
        $stmt->execute($params);
    }

    function obtenerIdTrabajadorContrato(PDO $conexion, int $idContrato): ?int {
        $stmt = $conexion->prepare('SELECT id_trabajador FROM contratos WHERE id_contrato = :id_contrato LIMIT 1');
        $stmt->execute([':id_contrato' => $idContrato]);
        $row = $stmt->fetch();

        return $row ? (int)$row['id_trabajador'] : null;
    }

    // Editar y renovar trabajan siempre sobre el trabajador ORIGINAL del contrato. Si el
    // formulario envía otro id_trabajador (campo manipulado), se rechaza: un contrato no se
    // reasigna; si el trabajador es incorrecto se elimina el contrato y se crea uno nuevo.
    function trabajadorDelContrato(PDO $conexion, ?int $idContrato, ?int $idEnviado): int {
        $original = $idContrato ? obtenerIdTrabajadorContrato($conexion, $idContrato) : null;
        if (!$original) {
            redirectWith('id_invalido');
        }
        if ($idEnviado !== null && $idEnviado !== $original) {
            redirectWith('validacion', [
                'campo' => 'id_trabajador',
                'texto' => 'Un contrato no puede reasignarse a otro trabajador. Si el trabajador es incorrecto, crea un contrato nuevo.',
            ]);
        }
        return $original;
    }

    // Si alguna validación falla, responde con el mensaje exacto del campo (JSON para
    // el formulario de nueva contratación, redirección con aviso para editar/renovar).
    function rechazarValidacion(array $errores): void {
        $campo = array_key_first($errores);
        redirectWith('validacion', ['campo' => $campo, 'texto' => $errores[$campo]]);
    }

    function datosContratoPost(): array {
        $idTiposContrato = postInt('id_tipos_contrato');

        // Jefe, fechas y montos se validan (nunca se corrigen en silencio).
        // Ver validaciones_contrato.php.
        $validacion = validarContratoPost($_POST);
        if ($validacion['errores']) {
            rechazarValidacion($validacion['errores']);
        }
        $v = $validacion['datos'];
        $fechaInicio = $v['fecha_inicio'];
        $fechaFin = $v['fecha_fin'];
        $salarioBase = $v['salario_base'];

        return [
            'id_trabajador' => postInt('id_trabajador'),
            'id_area' => postInt('id_area'),
            'id_cargo' => postInt('id_cargo'),
            'id_tipos_contrato' => $idTiposContrato,
            'tipo_contrato' => post('tipo_contrato'),
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin,
            'fecha_ingreso' => post('fecha_ingreso') ?: $fechaInicio,
            'salario_base' => $salarioBase,
            'auxilio_transporte' => $v['auxilio_transporte'],
            'estado' => estadoContrato($fechaFin),
            'jefe_inmediato' => $v['jefe_inmediato'],
            'jornada' => post('jornada') ?: 'Completa (46h/sem)',
            'modalidad' => post('modalidad') ?: 'Presencial',
            'periodo_prueba' => post('periodo_prueba') ?: 'Sin periodo',
            // null = el formulario no trae el campo (asistente de renovación): se conservan las existentes.
            'observaciones' => array_key_exists('observaciones', $_POST) ? (post('observaciones') ?: '') : null,
        ];
    }

    function validarDatosContrato(array $data, bool $requiereTrabajador = true): void {
        if ($requiereTrabajador && empty($data['id_trabajador'])) {
            redirectWith('datos_incompletos');
        }

        if (empty($data['id_tipos_contrato']) && empty($data['tipo_contrato'])) {
            redirectWith('datos_incompletos');
        }

        if (empty($data['fecha_inicio'])) {
            redirectWith('datos_incompletos');
        }

        if (
            $data['salario_base'] <= 0 ||
            $data['auxilio_transporte'] < 0 ||
            $data['salario_base'] > MAX_VALOR_MONETARIO ||
            $data['auxilio_transporte'] > MAX_VALOR_MONETARIO
        ) {
            redirectWith('monto_invalido');
        }
    }

    function insertarContrato(PDO $conexion, array $data): void {
        $cols = ['id_trabajador', 'fecha_inicio', 'fecha_fin', 'salario_base', 'auxilio_transporte', 'estado', 'jefe_inmediato', 'jornada', 'modalidad', 'periodo_prueba', 'observaciones'];
        $params = [
            ':id_trabajador' => $data['id_trabajador'],
            ':fecha_inicio' => $data['fecha_inicio'],
            ':fecha_fin' => $data['fecha_fin'],
            ':salario_base' => $data['salario_base'],
            ':auxilio_transporte' => $data['auxilio_transporte'],
            ':estado' => $data['estado'],
            ':jefe_inmediato' => $data['jefe_inmediato'],
            ':jornada' => $data['jornada'],
            ':modalidad' => $data['modalidad'],
            ':periodo_prueba' => $data['periodo_prueba'],
            ':observaciones' => $data['observaciones'] ?: null,
        ];

        if (columnaExiste($conexion, 'contratos', 'id_area')) {
            $cols[] = 'id_area';
            $params[':id_area'] = $data['id_area'];
        }

        if (columnaExiste($conexion, 'contratos', 'id_cargo')) {
            $cols[] = 'id_cargo';
            $params[':id_cargo'] = $data['id_cargo'];
        }

        if (columnaExiste($conexion, 'contratos', 'id_tipos_contrato')) {
            $cols[] = 'id_tipos_contrato';
            $params[':id_tipos_contrato'] = $data['id_tipos_contrato'];
        } elseif (columnaExiste($conexion, 'contratos', 'tipo_contrato')) {
            $cols[] = 'tipo_contrato';
            $params[':tipo_contrato'] = $data['tipo_contrato'];
        }

        $placeholders = array_map(fn($col) => ':' . $col, $cols);

        $sql = 'INSERT INTO contratos (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $placeholders) . ')';
        $stmt = $conexion->prepare($sql);
        $stmt->execute($params);
    }

    function actualizarContrato(PDO $conexion, int $idContrato, array $data, bool $forzarActivo = false): void {
        $sets = [
            'fecha_inicio = :fecha_inicio',
            'fecha_fin = :fecha_fin',
            'salario_base = :salario_base',
            'auxilio_transporte = :auxilio_transporte',
            'estado = :estado',
            'jefe_inmediato = :jefe_inmediato',
            'jornada = :jornada',
            'modalidad = :modalidad',
            'periodo_prueba = :periodo_prueba',
        ];

        $params = [
            ':fecha_inicio' => $data['fecha_inicio'],
            ':fecha_fin' => $data['fecha_fin'],
            ':salario_base' => $data['salario_base'],
            ':auxilio_transporte' => $data['auxilio_transporte'],
            ':estado' => $forzarActivo ? 'Activo' : $data['estado'],
            ':jefe_inmediato' => $data['jefe_inmediato'],
            ':jornada' => $data['jornada'],
            ':modalidad' => $data['modalidad'],
            ':periodo_prueba' => $data['periodo_prueba'],
            ':id_contrato' => $idContrato,
        ];

        if ($data['observaciones'] !== null) {
            $sets[] = 'observaciones = :observaciones';
            $params[':observaciones'] = $data['observaciones'] !== '' ? $data['observaciones'] : null;
        }

        // El trabajador de un contrato nunca cambia (ver trabajadorDelContrato()).

        if (columnaExiste($conexion, 'contratos', 'id_area')) {
            $sets[] = 'id_area = :id_area';
            $params[':id_area'] = $data['id_area'];
        }

        if (columnaExiste($conexion, 'contratos', 'id_cargo')) {
            $sets[] = 'id_cargo = :id_cargo';
            $params[':id_cargo'] = $data['id_cargo'];
        }

        if (columnaExiste($conexion, 'contratos', 'id_tipos_contrato')) {
            $sets[] = 'id_tipos_contrato = :id_tipos_contrato';
            $params[':id_tipos_contrato'] = $data['id_tipos_contrato'];
        } elseif (columnaExiste($conexion, 'contratos', 'tipo_contrato')) {
            $sets[] = 'tipo_contrato = :tipo_contrato';
            $params[':tipo_contrato'] = $data['tipo_contrato'];
        }

        $sql = 'UPDATE contratos SET ' . implode(', ', $sets) . ' WHERE id_contrato = :id_contrato';
        $stmt = $conexion->prepare($sql);
        $stmt->execute($params);
    }

    function esErrorMontoInvalido(Throwable $e): bool {
        $message = $e->getMessage();
        $code = (string)$e->getCode();

        return $code === '22003'
            || str_contains($message, '1264')
            || str_contains($message, 'Out of range value')
            || str_contains($message, 'Numeric value out of range');
    }

    $accion = post('accion');

    try {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirectWith('metodo_invalido');
        }

        if ($accion === 'nuevo') {
            $data = datosContratoPost();
            validarDatosContrato($data, true);
            exigirTrabajadorActivo($conexion, $data['id_trabajador']);

            $conexion->beginTransaction();

            insertarContrato($conexion, $data);

            $nuevoIdContrato = (int)$conexion->lastInsertId();

            actualizarTrabajadorDesdeContrato(
                $conexion,
                (int)$data['id_trabajador'],
                $data['id_area'],
                $data['id_cargo'],
                $data['fecha_ingreso']
            );

            $conexion->commit();
            redirectWith('creado', ['id_contrato' => $nuevoIdContrato]);
        }

        if ($accion === 'actualizar' || $accion === 'editar') {
            $idContrato = postInt('contrato_id');
            $data = datosContratoPost();

            $idTrabajador = trabajadorDelContrato($conexion, $idContrato, $data['id_trabajador']);
            $data['id_trabajador'] = $idTrabajador;

            validarDatosContrato($data, false);

            $conexion->beginTransaction();

            actualizarContrato($conexion, $idContrato, $data, false);

            actualizarTrabajadorDesdeContrato(
                $conexion,
                $idTrabajador,
                $data['id_area'],
                $data['id_cargo'],
                $data['fecha_ingreso']
            );

            $conexion->commit();
            redirectWith('actualizado');
        }

        if ($accion === 'renovar') {
            $idContrato = postInt('contrato_id');
            $data = datosContratoPost();

            $idTrabajador = trabajadorDelContrato($conexion, $idContrato, $data['id_trabajador']);

            validarDatosContrato($data, false);
            exigirTrabajadorActivo($conexion, $idTrabajador);

            $conexion->beginTransaction();

            $data['id_trabajador'] = $idTrabajador;
            $data['estado'] = 'Activo';

            actualizarContrato($conexion, $idContrato, $data, true);

            actualizarTrabajadorDesdeContrato(
                $conexion,
                $idTrabajador,
                $data['id_area'],
                $data['id_cargo'],
                $data['fecha_ingreso'] ?: $data['fecha_inicio']
            );

            $conexion->commit();
            redirectWith('renovado');
        }

        if ($accion === 'terminar') {
            $idContrato = postInt('contrato_id');

            if (!$idContrato) {
                redirectWith('id_invalido');
            }

            $stmt = $conexion->prepare(" 
                UPDATE contratos
                SET estado = 'Terminado',
                    fecha_fin = CASE
                        WHEN fecha_fin IS NULL OR fecha_fin > CURDATE() THEN CURDATE()
                        ELSE fecha_fin
                    END
                WHERE id_contrato = :id_contrato
            ");
            $stmt->execute([':id_contrato' => $idContrato]);

            // El aviso nombra al trabajador: "Se ha finalizado el contrato de …".
            $stmtNombre = $conexion->prepare("SELECT TRIM(CONCAT(t.nombres, ' ', t.apellidos)) FROM contratos c
                                              JOIN trabajadores t ON t.id_trabajador = c.id_trabajador WHERE c.id_contrato = ?");
            $stmtNombre->execute([$idContrato]);
            redirectWith('terminado', ['nombre' => (string)$stmtNombre->fetchColumn()]);
        }

        redirectWith('accion_invalida');
    } catch (Throwable $e) {
        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }

        error_log('[Contratacion guardar.php] ' . $e->getMessage());

        if (esErrorMontoInvalido($e)) {
            redirectWith('monto_invalido');
        }

        redirectWith('error', ['detalle' => $e->getMessage()]);
    }