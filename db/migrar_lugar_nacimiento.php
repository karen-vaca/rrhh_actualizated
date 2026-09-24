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

$pendientes = $conexion->query("SELECT id_trabajador, nombres, apellidos, lugar_nacimiento FROM trabajadores
                                WHERE codigo_ciudad_nacimiento IS NULL AND TRIM(COALESCE(lugar_nacimiento, '')) <> ''
                                ORDER BY id_trabajador")->fetchAll(PDO::FETCH_ASSOC);

$asignar = $conexion->prepare('UPDATE trabajadores SET codigo_ciudad_nacimiento = ? WHERE id_trabajador = ? AND codigo_ciudad_nacimiento IS NULL');
$migrados = 0;
$revision = [];

$conexion->beginTransaction();
foreach ($pendientes as $t) {
    $r = interpretarLugarTexto($conexion, $t['lugar_nacimiento']);
    if ($r['codigo']) {
        $asignar->execute([$r['codigo'], $t['id_trabajador']]);
        $migrados++;
        echo "  asignado  #{$t['id_trabajador']} \"{$t['lugar_nacimiento']}\" -> " . nombreLugar($conexion, $r['codigo']) . " ({$r['codigo']})\n";
    } else {
        $revision[] = $t + ['motivo' => $r['motivo']];
    }
}
$conexion->commit();

echo "\nAsignados: $migrados | Para revisión manual: " . count($revision) . "\n";
foreach ($revision as $t) {
    echo "  REVISAR   #{$t['id_trabajador']} {$t['nombres']} {$t['apellidos']}: \"{$t['lugar_nacimiento']}\" — {$t['motivo']}\n";
}
