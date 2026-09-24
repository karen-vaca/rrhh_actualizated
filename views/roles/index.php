<?php
require_once __DIR__ . '/../../config/auth.php';
requerirAcceso();
$titulo = 'Roles';
$descripcion = 'Definición de roles y permisos de los usuarios.';
$modulo_activo = 'roles';
require __DIR__ . '/../components/en_construccion.php';
