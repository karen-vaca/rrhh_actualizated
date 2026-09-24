<?php
require_once __DIR__ . '/../../config/auth.php';
requerirAcceso();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../config/conexion.php';
require_once __DIR__ . '/../components/validaciones_fechas.php';

if (!isset($conexion) && isset($pdo)) {
    $conexion = $pdo;
}

if (!isset($conexion) || !($conexion instanceof PDO)) {
    die('Error: no se encontró una conexión PDO válida. Revisa ../../config/conexion.php');
}

$conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$conexion->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

function redirigirNovedades(string $mensaje, array $extra = []): void {
    header('Location: index.php?' . http_build_query(array_merge(['mensaje' => $mensaje], $extra)));
    exit;
}

// Fechas: deben existir (2026-02-30 se rechaza, antes se convertía en 2026-03-02) y el
// fin no puede ser anterior al inicio.
function fechasValidadasNovedades(): array {
    $rango = validarRangoFechas($_POST);
    if ($rango['errores']) {
        redirigirNovedades('validacion', ['texto' => reset($rango['errores'])]);
    }
    return [$rango['datos']['fecha_inicio'], $rango['datos']['fecha_fin']];
}

function limpiarNovedades($valor): string {
    return trim((string)($valor ?? ''));
}

function accionPermitidaNovedades(string $accion): bool {
    return in_array($accion, ['nuevo', 'editar', 'aprobar', 'rechazar', 'cerrar'], true);
}

function estadoValidoNovedades(string $estado): bool {
    return in_array($estado, ['Pendiente', 'Aprobada', 'Rechazada', 'Cerrada'], true);
}

function categoriaValidaNovedades(string $categoria): bool {
    return in_array($categoria, ['Laboral', 'Asistencia', 'Salud y SST', 'Disciplinaria'], true);
}

function subirSoporteNovedades(): ?string {
    if (
        !isset($_FILES['soporte_archivo']) ||
        !is_array($_FILES['soporte_archivo']) ||
        ($_FILES['soporte_archivo']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE
    ) {
        return null;
    }

    $archivo = $_FILES['soporte_archivo'];

    if (($archivo['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        redirigirNovedades('archivo_invalido');
    }

    $tamano = (int)($archivo['size'] ?? 0);
    if ($tamano > 5 * 1024 * 1024) {
        redirigirNovedades('archivo_pesado');
    }

    $nombreOriginal = (string)($archivo['name'] ?? '');
    $extension = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));
    $permitidas = ['pdf', 'jpg', 'jpeg', 'png'];

    if (!in_array($extension, $permitidas, true)) {
        redirigirNovedades('archivo_invalido');
    }

    $baseDir = __DIR__ . '/../../uploads/novedades';

    if (!is_dir($baseDir)) {
        mkdir($baseDir, 0775, true);
    }

    $nombreSeguro = 'novedad_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
    $destino = $baseDir . DIRECTORY_SEPARATOR . $nombreSeguro;

    if (!move_uploaded_file((string)$archivo['tmp_name'], $destino)) {
        redirigirNovedades('archivo_invalido');
    }

    return $nombreSeguro;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigirNovedades('accion_invalida');
}

$accion = limpiarNovedades($_POST['accion'] ?? '');

if (!accionPermitidaNovedades($accion)) {
    redirigirNovedades('accion_invalida');
}

try {
    if ($accion === 'nuevo') {
        $idTrabajador = (int)($_POST['id_trabajador'] ?? 0);
        $categoria = limpiarNovedades($_POST['categoria'] ?? '');
        $tipoNovedad = limpiarNovedades($_POST['tipo_novedad'] ?? '');
        [$fechaInicio, $fechaFin] = fechasValidadasNovedades();
        $estado = limpiarNovedades($_POST['estado'] ?? 'Pendiente');
        $descripcion = limpiarNovedades($_POST['descripcion'] ?? '');
        $responsable = limpiarNovedades($_POST['responsable'] ?? '');
        $observaciones = limpiarNovedades($_POST['observaciones'] ?? '');

        if (
            $idTrabajador <= 0 ||
            $categoria === '' ||
            $tipoNovedad === '' ||
            !$fechaInicio ||
            $descripcion === ''
        ) {
            redirigirNovedades('datos_incompletos');
        }

        if (!categoriaValidaNovedades($categoria)) {
            redirigirNovedades('datos_incompletos');
        }

        if (!estadoValidoNovedades($estado)) {
            $estado = 'Pendiente';
        }

        $soporte = subirSoporteNovedades();

        $stmt = $conexion->prepare("
            INSERT INTO novedades (
                id_trabajador,
                categoria,
                tipo_novedad,
                fecha_inicio,
                fecha_fin,
                descripcion,
                estado,
                responsable,
                soporte_archivo,
                observaciones
            ) VALUES (
                :id_trabajador,
                :categoria,
                :tipo_novedad,
                :fecha_inicio,
                :fecha_fin,
                :descripcion,
                :estado,
                :responsable,
                :soporte_archivo,
                :observaciones
            )
        ");

        $stmt->execute([
            ':id_trabajador' => $idTrabajador,
            ':categoria' => $categoria,
            ':tipo_novedad' => $tipoNovedad,
            ':fecha_inicio' => $fechaInicio,
            ':fecha_fin' => $fechaFin,
            ':descripcion' => $descripcion,
            ':estado' => $estado,
            ':responsable' => $responsable,
            ':soporte_archivo' => $soporte,
            ':observaciones' => $observaciones,
        ]);

        redirigirNovedades('creado');
    }

    if ($accion === 'editar') {
        $idNovedad = (int)($_POST['id_novedad'] ?? 0);
        $categoria = limpiarNovedades($_POST['categoria'] ?? '');
        $tipoNovedad = limpiarNovedades($_POST['tipo_novedad'] ?? '');
        [$fechaInicio, $fechaFin] = fechasValidadasNovedades();
        $estado = limpiarNovedades($_POST['estado'] ?? 'Pendiente');
        $descripcion = limpiarNovedades($_POST['descripcion'] ?? '');
        $observaciones = limpiarNovedades($_POST['observaciones'] ?? '');

        if (
            $idNovedad <= 0 ||
            $categoria === '' ||
            $tipoNovedad === '' ||
            !$fechaInicio ||
            $descripcion === ''
        ) {
            redirigirNovedades('datos_incompletos');
        }

        if (!categoriaValidaNovedades($categoria)) {
            redirigirNovedades('datos_incompletos');
        }

        if (!estadoValidoNovedades($estado)) {
            $estado = 'Pendiente';
        }

        $soporte = subirSoporteNovedades();

        if ($soporte !== null) {
            $stmt = $conexion->prepare("
                UPDATE novedades
                SET categoria = :categoria,
                    tipo_novedad = :tipo_novedad,
                    fecha_inicio = :fecha_inicio,
                    fecha_fin = :fecha_fin,
                    descripcion = :descripcion,
                    estado = :estado,
                    soporte_archivo = :soporte_archivo,
                    observaciones = :observaciones,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id_novedad = :id_novedad
                LIMIT 1
            ");

            $stmt->execute([
                ':categoria' => $categoria,
                ':tipo_novedad' => $tipoNovedad,
                ':fecha_inicio' => $fechaInicio,
                ':fecha_fin' => $fechaFin,
                ':descripcion' => $descripcion,
                ':estado' => $estado,
                ':soporte_archivo' => $soporte,
                ':observaciones' => $observaciones,
                ':id_novedad' => $idNovedad,
            ]);
        } else {
            $stmt = $conexion->prepare("
                UPDATE novedades
                SET categoria = :categoria,
                    tipo_novedad = :tipo_novedad,
                    fecha_inicio = :fecha_inicio,
                    fecha_fin = :fecha_fin,
                    descripcion = :descripcion,
                    estado = :estado,
                    observaciones = :observaciones,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id_novedad = :id_novedad
                LIMIT 1
            ");

            $stmt->execute([
                ':categoria' => $categoria,
                ':tipo_novedad' => $tipoNovedad,
                ':fecha_inicio' => $fechaInicio,
                ':fecha_fin' => $fechaFin,
                ':descripcion' => $descripcion,
                ':estado' => $estado,
                ':observaciones' => $observaciones,
                ':id_novedad' => $idNovedad,
            ]);
        }

        redirigirNovedades('actualizado');
    }

    $idNovedad = (int)($_POST['id_novedad'] ?? 0);

    if ($idNovedad <= 0) {
        redirigirNovedades('id_invalido');
    }

    $nuevoEstado = null;
    $mensaje = null;

    if ($accion === 'aprobar') {
        $nuevoEstado = 'Aprobada';
        $mensaje = 'aprobada';
    } elseif ($accion === 'rechazar') {
        $nuevoEstado = 'Rechazada';
        $mensaje = 'rechazada';
    } elseif ($accion === 'cerrar') {
        $nuevoEstado = 'Cerrada';
        $mensaje = 'cerrada';
    }

    if ($nuevoEstado === null || $mensaje === null) {
        redirigirNovedades('accion_invalida');
    }

    $stmt = $conexion->prepare("
        UPDATE novedades
        SET estado = :estado,
            updated_at = CURRENT_TIMESTAMP
        WHERE id_novedad = :id_novedad
        LIMIT 1
    ");

    $stmt->execute([
        ':estado' => $nuevoEstado,
        ':id_novedad' => $idNovedad,
    ]);

    redirigirNovedades($mensaje);
} catch (Throwable $e) {
    error_log('Error guardar novedades: ' . $e->getMessage());
    redirigirNovedades('error');
}
