<?php
require_once __DIR__ . '/../../config/auth.php';
requerirAcceso();
$titulo = 'Indicadores';
$descripcion = 'Indicadores de gestión de RRHH y SG-SST.';
$modulo_activo = 'indicadores';
require __DIR__ . '/../components/en_construccion.php';
