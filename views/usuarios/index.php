<?php
require_once __DIR__ . '/../../config/auth.php';
requerirAcceso();
$titulo = 'Usuarios';
$descripcion = 'Administración de las cuentas de acceso al sistema.';
$modulo_activo = 'usuarios';
require __DIR__ . '/../components/en_construccion.php';
