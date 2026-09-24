<?php
require_once __DIR__ . '/../../config/auth.php';
requerirAcceso();
/**
 * ============================================================================
 *  ACCIÓN: Guardar restricción médica
 *  views/perfil_salud/guardar_restriccion.php
 * ----------------------------------------------------------------------------
 *  Recibe el POST del modal "Registrar restricción médica" en index.php,
 *  inserta la restricción y un evento en el historial médico, y redirige
 *  de vuelta al detalle con el toast correspondiente (§19.1 / §29.3).
 * ============================================================================
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/conexion.php';
require_once __DIR__ . '/validaciones_restriccion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$idTrabajador = (int)($_POST['id_trabajador'] ?? 0);

/* Validación del lado del servidor — nunca confiar solo en el 'required' del HTML
   (§14.8): tipo de la lista, fechas que existan, fin >= inicio y descripción.
   Ver validaciones_restriccion.php. */
if ($idTrabajador <= 0) {
    header('Location: index.php?mensaje=' . urlencode('Faltan campos obligatorios. Verifica el formulario.') . '&tipo=warning');
    exit;
}
$validacion = validarRestriccion($_POST);
if ($validacion['errores']) {
    header('Location: index.php?vista=detalle&id=' . $idTrabajador
        . '&mensaje=' . urlencode('No se guardó la restricción: ' . reset($validacion['errores']))
        . '&tipo=error');
    exit;
}
$tipo        = $validacion['datos']['tipo'];
$fechaInicio = $validacion['datos']['fecha_inicio'];
$fechaFin    = $validacion['datos']['fecha_fin'];
$descripcion = $validacion['datos']['descripcion'];

try {
    $conexion->beginTransaction();

    $stmt = $conexion->prepare("
        INSERT INTO restricciones_medicas
            (id_trabajador, tipo, fecha_inicio, fecha_fin, descripcion, estado, creado_en)
        VALUES
            (?, ?, ?, ?, ?, 'activa', NOW())
    ");
    $stmt->execute([$idTrabajador, $tipo, $fechaInicio, $fechaFin, $descripcion]);

    $stmtHist = $conexion->prepare("
        INSERT INTO historial_medico (id_trabajador, fecha, titulo, detalle, creado_en)
        VALUES (?, ?, ?, ?, NOW())
    ");
    $stmtHist->execute([
        $idTrabajador,
        $fechaInicio,
        'Restricción médica registrada',
        $tipo . ' — ' . $descripcion,
    ]);

    $conexion->commit();

    header('Location: index.php?vista=detalle&id=' . $idTrabajador
        . '&mensaje=' . urlencode('Restricción médica registrada correctamente.')
        . '&tipo=ok');
    exit;

} catch (PDOException $e) {
    $conexion->rollBack();
    // error_log($e->getMessage()); // recomendado en producción
    header('Location: index.php?vista=detalle&id=' . $idTrabajador
        . '&mensaje=' . urlencode('No se pudo guardar la restricción médica.')
        . '&tipo=error');
    exit;
}