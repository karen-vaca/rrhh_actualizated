<?php
require_once __DIR__ . '/../../config/auth.php';
requerirPost();
requerirAcceso(); // valida sesión, rol y token CSRF
require_once __DIR__ . '/../../config/conexion.php';

$id_trabajador = (int)($_POST['id_trabajador'] ?? 0);

if ($id_trabajador <= 0) {
    header("Location: index.php?mensaje=id_invalido");
    exit;
}

try {
    $sql = "UPDATE trabajadores
            SET estado = 1
            WHERE id_trabajador = :id_trabajador";

    $stmt = $conexion->prepare($sql);
    $stmt->bindParam(':id_trabajador', $id_trabajador, PDO::PARAM_INT);
    $stmt->execute();

    // Volver a la ficha si la acción se hizo desde ahí; si no, al listado.
    if (($_POST['volver'] ?? '') === 'ver') {
        header("Location: ver.php?id=" . $id_trabajador . "&mensaje=reactivado");
    } else {
        header("Location: index.php?mensaje=reactivado");
    }
    exit;

} catch (PDOException $e) {
    die("Error al reactivar trabajador: " . $e->getMessage());
}
