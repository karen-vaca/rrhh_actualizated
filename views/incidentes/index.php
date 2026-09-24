<?php
require_once __DIR__ . '/../../config/auth.php';
requerirAcceso();
$titulo = 'Incidentes';
$descripcion = 'Registro y seguimiento de incidentes y accidentes laborales.';
$modulo_activo = 'incidentes';
require __DIR__ . '/../components/en_construccion.php';
