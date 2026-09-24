<?php
require_once __DIR__ . '/../../config/auth.php';
requerirAcceso(ROLES_CUALQUIER_USUARIO);
$titulo = 'Cambiar contraseña';
$descripcion = 'Cambia la contraseña de tu cuenta.';
$modulo_activo = '';
require __DIR__ . '/../components/en_construccion.php';
