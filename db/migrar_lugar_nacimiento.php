<?php
require_once __DIR__ . '/../config/auth.php';
soloConsola();
// Asigna la ciudad del catálogo DIVIPOLA a partir del texto de lugar_nacimiento.
// Solo asigna cuando la coincidencia es única (ver interpretarLugarTexto()); lo demás
// queda para revisión manual. Nunca borra el texto original. Se puede ejecutar de
// nuevo: solo toca trabajadores que aún no tienen ciudad.
// Ejecutar: php db/migrar_lugar_nacimiento.php

require __DIR__ . '/../config/conexion.php';
require __DIR__ . '/../views/components/lugares.php';

$r = migrarLugaresNacimiento($conexion);

foreach ($r['asignados'] as $t) {
    echo "  asignado  #{$t['id_trabajador']} \"{$t['lugar_nacimiento']}\" -> " . nombreLugar($conexion, $t['codigo']) . " ({$t['codigo']})\n";
}
echo "\nAsignados: " . count($r['asignados']) . " | Para revisión manual: " . count($r['revision']) . "\n";
foreach ($r['revision'] as $t) {
    echo "  REVISAR   #{$t['id_trabajador']} {$t['nombres']} {$t['apellidos']}: \"{$t['lugar_nacimiento']}\" — {$t['motivo']}\n";
}
