<?php
require_once __DIR__ . '/../../config/auth.php';
requerirAcceso(ROLES_CUALQUIER_USUARIO);
$titulo = 'Configuración';
$descripcion = 'Preferencias de tu cuenta.';
$modulo_activo = '';
require __DIR__ . '/../components/en_construccion.php';
