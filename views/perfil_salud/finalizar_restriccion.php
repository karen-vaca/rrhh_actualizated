<?php
/**
 * ============================================================================
 *  ACCIÓN: Finalizar restricción médica
 *  views/perfil_salud/finalizar_restriccion.php
 * ----------------------------------------------------------------------------
 *  Regla obligatoria §28.8: esta acción solo se alcanza después de pasar
 *  por el .confirm-card del detalle (§17.2) — nunca se ejecuta directo
 *  desde un botón de fila sin confirmación previa.
 * ============================================================================
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/conexion.php';

$idRestriccion = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$idTrabajador  = isset($_GET['trabajador']) ? (int)$_GET['trabajador'] : 0;

if ($idRestriccion <= 0 || $idTrabajador <= 0) {
    header('Location: index.php?mensaje=' . urlencode('Identificador inválido.') . '&tipo=error');
    exit;
}

try {
    $conexion->beginTransaction();

    /* Traemos el tipo antes de actualizar, para dejar constancia en el historial */
    $stmtTipo = $conexion->prepare("SELECT tipo FROM restricciones_medicas WHERE id_restriccion = ? AND id_trabajador = ?");
    $stmtTipo->execute([$idRestriccion, $idTrabajador]);
    $tipo = $stmtTipo->fetchColumn();

    if ($tipo === false) {
        $conexion->rollBack();
        header('Location: index.php?vista=detalle&id=' . $idTrabajador
            . '&mensaje=' . urlencode('La restricción indicada no existe.') . '&tipo=error');
        exit;
    }

    $stmt = $conexion->prepare("
        UPDATE restricciones_medicas
        SET estado = 'finalizada', fecha_fin = COALESCE(fecha_fin, CURDATE()), finalizado_en = NOW()
        WHERE id_restriccion = ? AND id_trabajador = ?
    ");
    $stmt->execute([$idRestriccion, $idTrabajador]);

    $stmtHist = $conexion->prepare("
        INSERT INTO historial_medico (id_trabajador, fecha, titulo, detalle, creado_en)
        VALUES (?, CURDATE(), ?, ?, NOW())
    ");
    $stmtHist->execute([
        $idTrabajador,
        'Restricción médica finalizada',
        'Se dio por resuelta la restricción: ' . $tipo,
    ]);

    $conexion->commit();

    header('Location: index.php?vista=detalle&id=' . $idTrabajador
        . '&mensaje=' . urlencode('Restricción finalizada correctamente.') . '&tipo=ok');
    exit;

} catch (PDOException $e) {
    $conexion->rollBack();
    // error_log($e->getMessage());
    header('Location: index.php?vista=detalle&id=' . $idTrabajador
        . '&mensaje=' . urlencode('No se pudo finalizar la restricción.') . '&tipo=error');
    exit;
}