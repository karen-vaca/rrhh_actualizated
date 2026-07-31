<?php
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$idTrabajador = (int)($_POST['id_trabajador'] ?? 0);
$tipo         = trim($_POST['tipo'] ?? '');
$fechaInicio  = trim($_POST['fecha_inicio'] ?? '');
$fechaFin     = trim($_POST['fecha_fin'] ?? '') ?: null;
$descripcion  = trim($_POST['descripcion'] ?? '');

/* Validación mínima del lado del servidor — nunca confiar solo en el
   'required' del HTML (§14.8) */
if ($idTrabajador <= 0 || $tipo === '' || $fechaInicio === '' || $descripcion === '') {
    header('Location: index.php?vista=detalle&id=' . $idTrabajador
        . '&mensaje=' . urlencode('Faltan campos obligatorios. Verifica el formulario.')
        . '&tipo=warning');
    exit;
}

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