<?php
require_once __DIR__ . '/../../config/auth.php';
requerirAcceso(ROLES_CUALQUIER_USUARIO);
$titulo = 'Editar perfil';
$descripcion = 'Actualiza tus datos personales.';
$modulo_activo = '';
require __DIR__ . '/../components/en_construccion.php';
