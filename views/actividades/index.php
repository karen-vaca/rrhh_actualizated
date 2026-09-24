<?php
require_once __DIR__ . '/../../config/auth.php';
requerirAcceso();
$titulo = 'Actividad reciente';
$descripcion = 'Historial completo de la actividad del sistema.';
$modulo_activo = 'dashboard';
require __DIR__ . '/../components/en_construccion.php';
