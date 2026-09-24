<?php
require_once __DIR__ . '/../../config/auth.php';
requerirAcceso();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../../config/conexion.php';

header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Solo 'listar' se permite por GET; agregar/editar/eliminar exigen POST (y requerirAcceso ya validó el token CSRF).
if ($action !== 'listar') {
    requerirPost();
}
$trabajador_id = (int)($_POST['trabajador_id'] ?? $_GET['trabajador_id'] ?? 0);

if ($trabajador_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de trabajador inválido']);
    exit;
}

try {
    switch ($action) {
        case 'listar':
            $stmt = $conexion->prepare("
                SELECT * FROM familiares_trabajador 
                WHERE trabajador_id = ? 
                ORDER BY created_at DESC
            ");
            $stmt->execute([$trabajador_id]);
            $familiares = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $familiares]);
            break;

        case 'agregar':
            $nombre = trim($_POST['nombre'] ?? '');
            $tipo_documento = trim($_POST['tipo_documento'] ?? '');
            $numero_documento = trim($_POST['numero_documento'] ?? '');
            $parentesco = trim($_POST['parentesco'] ?? '');
            $fecha_nacimiento = $_POST['fecha_nacimiento'] ?? null;
            $dependiente = (int)($_POST['dependiente'] ?? 0);
            $contacto_emergencia = (int)($_POST['contacto_emergencia'] ?? 0);
            $observaciones = trim($_POST['observaciones'] ?? '');

            if (empty($nombre) || empty($parentesco)) {
                throw new Exception('Nombre y parentesco son obligatorios');
            }

            $stmt = $conexion->prepare("
                INSERT INTO familiares_trabajador 
                (trabajador_id, nombre, tipo_documento, numero_documento, parentesco, 
                 fecha_nacimiento, dependiente, contacto_emergencia, observaciones)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $trabajador_id, $nombre, $tipo_documento, $numero_documento, 
                $parentesco, $fecha_nacimiento ?: null, $dependiente, 
                $contacto_emergencia, $observaciones
            ]);

            echo json_encode(['success' => true, 'message' => 'Familiar agregado correctamente']);
            break;

        case 'editar':
            $id = (int)($_POST['id'] ?? 0);
            $nombre = trim($_POST['nombre'] ?? '');
            $tipo_documento = trim($_POST['tipo_documento'] ?? '');
            $numero_documento = trim($_POST['numero_documento'] ?? '');
            $parentesco = trim($_POST['parentesco'] ?? '');
            $fecha_nacimiento = $_POST['fecha_nacimiento'] ?? null;
            $dependiente = (int)($_POST['dependiente'] ?? 0);
            $contacto_emergencia = (int)($_POST['contacto_emergencia'] ?? 0);
            $observaciones = trim($_POST['observaciones'] ?? '');

            if ($id <= 0 || empty($nombre) || empty($parentesco)) {
                throw new Exception('Datos inválidos');
            }

            $stmt = $conexion->prepare("
                UPDATE familiares_trabajador 
                SET nombre = ?, tipo_documento = ?, numero_documento = ?, 
                    parentesco = ?, fecha_nacimiento = ?, dependiente = ?, 
                    contacto_emergencia = ?, observaciones = ?
                WHERE id = ? AND trabajador_id = ?
            ");
            $stmt->execute([
                $nombre, $tipo_documento, $numero_documento, $parentesco,
                $fecha_nacimiento ?: null, $dependiente, $contacto_emergencia,
                $observaciones, $id, $trabajador_id
            ]);

            echo json_encode(['success' => true, 'message' => 'Familiar actualizado correctamente']);
            break;

        case 'eliminar':
            $id = (int)($_POST['id'] ?? 0);
            
            if ($id <= 0) {
                throw new Exception('ID inválido');
            }

            $stmt = $conexion->prepare("
                DELETE FROM familiares_trabajador 
                WHERE id = ? AND trabajador_id = ?
            ");
            $stmt->execute([$id, $trabajador_id]);

            echo json_encode(['success' => true, 'message' => 'Familiar eliminado correctamente']);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Acción no válida']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>