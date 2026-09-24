<?php
// Sirve los documentos de expedientes y soportes (contratos, PDFs de novedades) solo
// a usuarios con sesión y rol RRHH. LocalValetDriver.php redirige aquí todas las
// peticiones a esas carpetas, para que no se puedan descargar sin iniciar sesión.

// Rutas internas (.env, .git, db/, tests/...): nunca se sirven, haya o no sesión.
if (!empty($_SERVER['RUTA_BLOQUEADA'])) {
    http_response_code(404);
    exit('Archivo no encontrado.');
}

require_once __DIR__ . '/config/auth.php';
requerirAcceso();

const CARPETAS_PROTEGIDAS = ['expedientes', 'uploads', 'novedades'];

$ruta = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '');
$ruta = ltrim(substr($ruta, strlen(urlBase())), '/');
$archivo = realpath(__DIR__ . '/' . $ruta);

// Solo archivos reales dentro de las carpetas protegidas (evita ../ y rutas raras).
$permitido = false;
foreach (CARPETAS_PROTEGIDAS as $carpeta) {
    $base = realpath(__DIR__ . '/' . $carpeta);
    if ($base && $archivo && str_starts_with($archivo, $base . DIRECTORY_SEPARATOR) && is_file($archivo)) {
        $permitido = true;
        break;
    }
}

if (!$permitido) {
    http_response_code(404);
    exit('Archivo no encontrado.');
}

$tipos = [
    'pdf'  => 'application/pdf',
    'png'  => 'image/png',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
];
$extension = strtolower(pathinfo($archivo, PATHINFO_EXTENSION));

header('Content-Type: ' . ($tipos[$extension] ?? 'application/octet-stream'));
header('Content-Length: ' . filesize($archivo));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
// PDF e imágenes se ven en el navegador; el resto se descarga.
$disposicion = in_array($extension, ['pdf', 'png', 'jpg', 'jpeg'], true) ? 'inline' : 'attachment';
header('Content-Disposition: ' . $disposicion . '; filename="' . basename($archivo) . '"');
readfile($archivo);
