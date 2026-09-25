<?php
// Funciones de Contratación compartidas por el listado (index.php) y la ficha del
// contrato (ver.php): formato, estado visual, nómina estimada y la consulta de contratos
// con sus datos derivados (nombre, iniciales y color de avatar, área, cargo, estado).
// Se movieron desde index.php sin cambiar su lógica.

require_once __DIR__ . '/../components/avatar.php';

if (!function_exists('e')) {
function e($valor): string {
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}
}

if (!function_exists('fmtFecha')) {
function fmtFecha($fecha): string {
    if (!$fecha || $fecha === '0000-00-00') return '—';
    $time = strtotime((string)$fecha);
    return $time ? date('d/m/Y', $time) : '—';
}
}

if (!function_exists('fmtMoney')) {
function fmtMoney($valor): string {
    return '$' . number_format((float)$valor, 0, ',', '.');
}
}

if (!function_exists('calcularNominaMensual')) {
function calcularNominaMensual($salarioBase, $auxilioTransporte): array {
    $salario = max(0, (float)$salarioBase);
    $auxilio = max(0, (float)$auxilioTransporte);
    $salud = round($salario * 0.04);
    $pension = round($salario * 0.04);
    $totalDevengado = $salario + $auxilio;
    $totalDeducciones = $salud + $pension;

    return [
        'salario_base' => $salario,
        'auxilio_transporte' => $auxilio,
        'total_devengado' => $totalDevengado,
        'salud' => $salud,
        'pension' => $pension,
        'total_deducciones' => $totalDeducciones,
        'neto_pagar' => $totalDevengado - $totalDeducciones,
    ];
}
}

if (!function_exists('estadoVisual')) {
function estadoVisual(?string $estadoDb, ?string $fechaFin): string {
    if ($estadoDb === 'Terminado') return 'Terminado';

    if ($fechaFin && $fechaFin !== '0000-00-00') {
        $hoy = new DateTimeImmutable('today');
        $fin = DateTimeImmutable::createFromFormat('Y-m-d', $fechaFin);
        if ($fin) {
            if ($fin < $hoy) return 'Vencido';
            if ($fin <= $hoy->modify('+30 days')) return 'Por vencer';
        }
    }

    return 'Activo';
}
}

if (!function_exists('badgeClass')) {
function badgeClass(string $estado): string {
    return match ($estado) {
        'Activo' => 'status-active',
        'Por vencer' => 'status-warning',
        'Vencido', 'Inactivo' => 'status-danger',
        'Terminado' => 'status-muted',
        default => 'status-muted',
    };
}
}

if (!function_exists('columnasTabla')) {
function columnasTabla(PDO $conexion, string $tabla): array {
    try {
        $stmt = $conexion->query("SHOW COLUMNS FROM `$tabla`");
        return array_column($stmt->fetchAll(), 'Field');
    } catch (Throwable $e) {
        return [];
    }
}
}

if (!function_exists('tieneColumna')) {
function tieneColumna(array $columnas, string $columna): bool {
    return in_array($columna, $columnas, true);
}
}

// Contratos con trabajador, área, cargo y tipo. Con $idContrato devuelve solo ese contrato.
function consultarContratos(PDO $conexion, ?int $idContrato = null): array
{
    $where = $idContrato !== null ? 'WHERE co.id_contrato = :id' : '';
    $colsContratos = columnasTabla($conexion, 'contratos');

    $areaExpr = tieneColumna($colsContratos, 'id_area')
        ? 'COALESCE(co.id_area, t.id_area)'
        : 't.id_area';

    $cargoExpr = tieneColumna($colsContratos, 'id_cargo')
        ? 'COALESCE(co.id_cargo, t.id_cargo)'
        : 't.id_cargo';

    $tipoJoin = tieneColumna($colsContratos, 'id_tipos_contrato')
        ? 'LEFT JOIN tipos_contrato tc ON co.id_tipos_contrato = tc.id_tipos_contrato'
        : '';

    if (tieneColumna($colsContratos, 'id_tipos_contrato')) {
        $tipoSelect = 'tc.contrato AS tipo_contrato';
    } elseif (tieneColumna($colsContratos, 'tipo_contrato')) {
        $tipoSelect = 'co.tipo_contrato AS tipo_contrato';
    } else {
        $tipoSelect = "'Sin tipo' AS tipo_contrato";
    }

    $sql = "
        SELECT 
            co.*,
            t.nombres,
            t.apellidos,
            t.numero_documento,
            t.fecha_ingreso,
            t.estado AS estado_trabajador,
            $areaExpr AS id_area_final,
            $cargoExpr AS id_cargo_final,
            a.nombre_area,
            ca.nombre_cargo,
            $tipoSelect
        FROM contratos co
        LEFT JOIN trabajadores t ON co.id_trabajador = t.id_trabajador
        LEFT JOIN areas a ON $areaExpr = a.id_areas
        LEFT JOIN cargos ca ON $cargoExpr = ca.id_cargo
        $tipoJoin
        $where
        ORDER BY co.id_contrato DESC
    ";

    $stmt = $conexion->prepare($sql);
    $stmt->execute($idContrato !== null ? [':id' => $idContrato] : []);
    $contratos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($contratos as &$contrato) {
        $contrato['trabajador_nombre'] = trim(($contrato['nombres'] ?? '') . ' ' . ($contrato['apellidos'] ?? ''));

        $contrato['iniciales'] = inicialesAvatar($contrato['nombres'] ?? '', $contrato['apellidos'] ?? '');

        $contrato['avatar_class'] = claseAvatar($contrato['id_trabajador'] ?? 0);

        $contrato['area'] = $contrato['nombre_area'] ?? 'Sin área';
        $contrato['cargo'] = $contrato['nombre_cargo'] ?? 'Sin cargo';

        if (!isset($contrato['id_area'])) {
            $contrato['id_area'] = $contrato['id_area_final'] ?? null;
        }

        if (!isset($contrato['id_cargo'])) {
            $contrato['id_cargo'] = $contrato['id_cargo_final'] ?? null;
        }

        $estadoTrabajador = (int)($contrato['estado_trabajador'] ?? 1);

        if ($estadoTrabajador === 0) {
            $contrato['estado_mostrar'] = 'Inactivo';
        } else {
            $contrato['estado_mostrar'] = estadoVisual($contrato['estado'] ?? null, $contrato['fecha_fin'] ?? null);
        }

        if (($contrato['tipo_contrato'] ?? '') === '') {
            $contrato['tipo_contrato'] = 'Sin tipo';
        }
    }
    unset($contrato);

    return $contratos;
}
