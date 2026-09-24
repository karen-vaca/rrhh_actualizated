<?php
// Hora de Colombia para todo el sistema. PHP venía en UTC y MySQL en America/Bogota:
// entre las 7 p. m. y la medianoche PHP ya creía que era el día siguiente (afectaba
// "fecha no futura", edades y el contador "Nuevos este mes").
date_default_timezone_set('America/Bogota');

// 1. Cargar las herramientas de Composer
require_once __DIR__ . '/../vendor/autoload.php';

// 2. Cargar las variables del archivo .env (ver .env.example)
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->safeLoad();

// 3. Datos de conexión a MySQL local
$host     = $_ENV['DB_HOST'] ?? '127.0.0.1';
$port     = $_ENV['DB_PORT'] ?? '3306';
$dbname   = $_ENV['DB_DATABASE'] ?? 'railway';
$username = $_ENV['DB_USERNAME'] ?? 'root';
$password = $_ENV['DB_PASSWORD'] ?? '';

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    $opciones = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    $conexion = new PDO($dsn, $username, $password, $opciones);

    // Si quieres probar si funciona, descomenta la siguiente línea quitando las dos barras (//)
    //  echo "¡Conexión exitosa!";

} catch (PDOException $e) {
    die("Error crítico de conexión: " . $e->getMessage());
}
