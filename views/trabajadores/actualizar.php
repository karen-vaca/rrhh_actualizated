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
require_once __DIR__ . '/funciones_trabajador.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$id_trabajador = isset($_POST['id_trabajador']) ? (int)$_POST['id_trabajador'] : 0;

if ($id_trabajador <= 0) {
    header('Location: index.php?mensaje=id_invalido');
    exit;
}

try {
    $stmt = $conexion->prepare("SELECT id_trabajador FROM trabajadores WHERE id_trabajador = :id LIMIT 1");
    $stmt->bindValue(':id', $id_trabajador, PDO::PARAM_INT);
    $stmt->execute();

    if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
        header('Location: index.php?mensaje=no_encontrado');
        exit;
    }

    // =========================
    // VALIDACIÓN (ver funciones_trabajador.php)
    // =========================
    ['datos' => $datos, 'errores' => $errores] = validarTrabajador($conexion, $_POST, $id_trabajador);

    if ($errores) {
        guardarErroresFormulario($errores, $_POST);
        header('Location: editar.php?id=' . $id_trabajador);
        exit;
    }

    $columnasStmt = $conexion->query("SHOW COLUMNS FROM trabajadores");
    $columnas = $columnasStmt->fetchAll(PDO::FETCH_COLUMN);

    $sets = [];
    $params = [];

    foreach ($datos as $columna => $valor) {
        if (!in_array($columna, $columnas, true)) {
            continue;
        }

        $sets[] = "`$columna` = :$columna";
        $params[":$columna"] = $valor;
    }

    if (in_array('updated_at', $columnas, true)) {
        $sets[] = "`updated_at` = NOW()";
    }

    $sql = "UPDATE trabajadores SET " . implode(', ', $sets) . " WHERE id_trabajador = :id_trabajador";
    $stmtUpdate = $conexion->prepare($sql);

    foreach ($params as $key => $valor) {
        if ($valor === null) {
            $stmtUpdate->bindValue($key, null, PDO::PARAM_NULL);
        } elseif (is_int($valor)) {
            $stmtUpdate->bindValue($key, $valor, PDO::PARAM_INT);
        } else {
            $stmtUpdate->bindValue($key, $valor, PDO::PARAM_STR);
        }
    }

    $stmtUpdate->bindValue(':id_trabajador', $id_trabajador, PDO::PARAM_INT);
    $stmtUpdate->execute();

    header('Location: ver.php?id=' . $id_trabajador . '&mensaje=actualizado');
    exit;

} catch (Exception $e) {
    die('Error al actualizar trabajador: ' . $e->getMessage());
}
