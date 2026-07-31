<?php
// 1. Cargar las herramientas de Composer
require_once __DIR__ . '/../vendor/autoload.php';

// 2. Cargar las variables secretas del archivo .env
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// 3. Traer la URL desde la bóveda secreta
$url_db = $_ENV['RAILWAY_URL']; 

try {
    // Desarmamos la URL para sacar los datos
    $url_parseada = parse_url($url_db);

$host = "127.0.0.1";
$port = "3306";
$dbname = "railway";
$username = "root";
$password = "";

$dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    $opciones = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    
    // Intentamos conectar
    $conexion = new PDO($dsn, $username, $password, $opciones);
    
    // Si quieres probar si funciona, descomenta la siguiente línea quitando las dos barras (//)
    //  echo "¡Conexión segura y encriptada exitosa!";
    
} catch (PDOException $e) {
    die("Error crítico de conexión: " . $e->getMessage());
}
?>