<?php
require_once __DIR__ . '/../../config/auth.php';
requerirPost();
requerirAcceso(); // valida sesión, rol y token CSRF
require_once __DIR__ . '/../../config/conexion.php';
require_once __DIR__ . '/servicio_examenes.php';

// Toda la lógica y las validaciones están en servicio_examenes.php.
function volverExamenes(string $mensaje, string $tipo): void {
    header('Location: index.php?' . http_build_query(['mensaje' => $mensaje, 'tipo' => $tipo]));
    exit;
}

try {
    $r = programarExamen($conexion, $_POST);
} catch (Throwable $e) {
    error_log('Exámenes médicos: ' . $e->getMessage());
    volverExamenes('Ocurrió un error al guardar. No se hicieron cambios.', 'error');
}

if (!$r['ok']) {
    volverExamenes('No se programó el examen: ' . reset($r['errores']), 'error');
}
volverExamenes('Examen programado correctamente.', 'ok');
