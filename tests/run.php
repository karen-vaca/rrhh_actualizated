<?php
require_once __DIR__ . '/../config/auth.php';
soloConsola();
// Corre todos los tests del proyecto: php tests/run.php
$fallo = false;
foreach (glob(__DIR__ . "/*Test.php") as $test) {
    echo "\n== " . basename($test) . "\n";
    passthru(escapeshellarg(PHP_BINARY) . " " . escapeshellarg($test), $codigo);
    $fallo = $fallo || $codigo !== 0;
}
echo $fallo ? "\nHAY TESTS FALLANDO\n" : "\nTodos los tests pasaron\n";
exit($fallo ? 1 : 0);
