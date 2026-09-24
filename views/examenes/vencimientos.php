<?php
require_once __DIR__ . '/../../config/auth.php';
requerirAcceso();
$titulo = 'Vencimientos de exámenes';
$descripcion = 'Listado de exámenes médicos próximos a vencer.';
$modulo_activo = 'examenes';
require __DIR__ . '/../components/en_construccion.php';
