<?php
require_once __DIR__ . '/../../config/auth.php';
requerirAcceso();
echo "PHP FUNCIONA EN ESTA RUTA";
echo "<br>Archivo: " . __FILE__;
echo "<br>Directorio: " . __DIR__;
echo "<br>PHP Version: " . phpversion();