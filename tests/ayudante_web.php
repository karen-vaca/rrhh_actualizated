<?php
require_once __DIR__ . '/../config/auth.php';
soloConsola();
// Ayudante de tests: ejecuta una página real del sistema con php-cgi, igual que el
// servidor web (con sesión de Administrador y token CSRF), sin necesitar Herd ni un
// navegador. Devuelve el código HTTP, la redirección (Location) y el cuerpo.

/**
 * @param string $script Ruta relativa a la raíz del proyecto, ej. 'views/novedades/index.php'.
 * @return array{status: int, location: string, cuerpo: string}
 */
function ejecutarComoWeb(string $script, array $post, string $metodo = 'POST', bool $conToken = true, ?int $rol = ROL_ADMINISTRADOR): array {
    $raiz = realpath(__DIR__ . '/..');
    $archivo = $raiz . '/' . $script;
    $cgi = dirname(PHP_BINARY) . DIRECTORY_SEPARATOR . (PHP_OS_FAMILY === 'Windows' ? 'php-cgi.exe' : 'php-cgi');
    if (!is_file($cgi)) {
        throw new RuntimeException("No se encontró php-cgi en $cgi");
    }

    // Sesión de prueba (Administrador) en la misma carpeta de sesiones que usa PHP.
    $idSesion = 'test' . bin2hex(random_bytes(12));
    $token = bin2hex(random_bytes(32));
    $carpeta = ini_get('session.save_path') ?: sys_get_temp_dir();
    $archivoSesion = $carpeta . DIRECTORY_SEPARATOR . 'sess_' . $idSesion;
    // $rol = null simula un visitante sin sesión iniciada.
    if ($rol !== null) {
        file_put_contents($archivoSesion, 'logueado|b:1;id_usuario|i:1;id_roles|i:' . $rol . ';csrf_token|s:64:"' . $token . '";');
    }

    $cuerpo = $metodo === 'POST' ? http_build_query($conToken ? $post + ['csrf_token' => $token] : $post) : '';
    $entorno = array_merge(getenv(), [
        'REDIRECT_STATUS'   => '200',
        'GATEWAY_INTERFACE' => 'CGI/1.1',
        'REQUEST_METHOD'    => $metodo,
        'CONTENT_TYPE'      => 'application/x-www-form-urlencoded',
        'CONTENT_LENGTH'    => (string)strlen($cuerpo),
        'SCRIPT_FILENAME'   => $archivo,
        'SCRIPT_NAME'       => '/' . $script,
        'REQUEST_URI'       => '/' . $script . ($metodo === 'GET' && $post ? '?' . http_build_query($post) : ''),
        'QUERY_STRING'      => $metodo === 'GET' ? http_build_query($post) : '',
        'DOCUMENT_ROOT'     => $raiz,
        'SERVER_NAME'       => 'localhost',
        'HTTP_HOST'         => 'localhost',
        'HTTP_COOKIE'       => 'PHPSESSID=' . $idSesion,
    ]);

    try {
        $proceso = proc_open([$cgi], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $tubos, dirname($archivo), $entorno);
        fwrite($tubos[0], $cuerpo);
        fclose($tubos[0]);
        $salida = stream_get_contents($tubos[1]);
        stream_get_contents($tubos[2]);
        fclose($tubos[1]);
        fclose($tubos[2]);
        proc_close($proceso);
    } finally {
        @unlink($archivoSesion);
    }

    [$cabeceras, $html] = array_pad(preg_split("/\r?\n\r?\n/", $salida, 2), 2, '');
    $status = preg_match('/^Status:\s*(\d+)/mi', $cabeceras, $m) ? (int)$m[1] : 200;
    $location = preg_match('/^Location:\s*(.+)$/mi', $cabeceras, $m) ? trim($m[1]) : '';
    if ($location !== '' && $status === 200) {
        $status = 302;
    }
    return ['status' => $status, 'location' => $location, 'cuerpo' => $html];
}

// Mensaje (?mensaje=...&texto=...) de una redirección, ya decodificado.
function parametrosRedireccion(string $location): array {
    parse_str((string)parse_url($location, PHP_URL_QUERY), $q);
    return $q;
}

// Huella de una o varias tablas, para comprobar que una prueba no escribió nada.
function huellaTablas(PDO $conexion, array $tablas): string {
    $datos = [];
    foreach ($tablas as $tabla) {
        $datos[$tabla] = $conexion->query("SELECT * FROM `$tabla`")->fetchAll(PDO::FETCH_ASSOC);
    }
    return md5(json_encode($datos));
}
