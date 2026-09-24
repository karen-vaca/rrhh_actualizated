<?php
require_once __DIR__ . '/../../config/auth.php';
requerirAcceso();
$titulo = 'Reportes';
$descripcion = 'Generación de reportes de recursos humanos y SG-SST.';
$modulo_activo = 'reportes';
require __DIR__ . '/../components/en_construccion.php';
