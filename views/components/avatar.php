<?php
// Avatar de iniciales de un trabajador, igual en todo el sistema.
// El color depende solo del id del trabajador (id % 6, el mismo criterio que usaba el
// listado de Trabajadores), así la misma persona tiene el mismo color en Trabajadores,
// Contratación o cualquier otra pantalla. Los colores están en assets/css/componentes.css
// (.avatar-c0 … .avatar-c5).
//
// Uso:
//   require_once __DIR__ . '/../components/avatar.php';
//   echo '<div class="worker-avatar ' . claseAvatar($idTrabajador) . '">' . htmlspecialchars(inicialesAvatar($nombres, $apellidos)) . '</div>';

if (!function_exists('claseAvatar')) {
    function claseAvatar($idTrabajador): string
    {
        return 'avatar-c' . (abs((int)$idTrabajador) % 6);
    }
}

if (!function_exists('inicialesAvatar')) {
    // Primera letra del primer nombre y del primer apellido, en mayúscula (seguro con tildes y ñ).
    // Devuelve texto sin escapar: quien lo imprime lo escapa (htmlspecialchars / e()).
    function inicialesAvatar($nombres, $apellidos): string
    {
        $n = trim((string)$nombres);
        $a = trim((string)$apellidos);
        $ini = mb_strtoupper(mb_substr($n, 0, 1) . mb_substr($a !== '' ? $a : mb_substr($n, 1), 0, 1));
        return $ini !== '' ? $ini : 'TR';
    }
}
