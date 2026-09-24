<?php
// Driver de Herd/Valet para este sitio. Herd sirve directamente cualquier archivo que
// exista en la carpeta del proyecto (sin pasar por PHP), así que sin este driver se
// podían descargar sin sesión el .env, el repositorio .git, respaldos SQL, contratos
// y soportes. Este driver:
//   - bloquea (404) archivos internos que nunca deben servirse;
//   - envía los documentos (expedientes/, uploads/, novedades/) a archivo_protegido.php,
//     que exige sesión antes de entregarlos.
// Si el sistema se despliega en Apache o Nginx, estas reglas deben replicarse allí.

class LocalValetDriver extends \Valet\Drivers\BasicValetDriver
{
    // Rutas que nunca se sirven (ni estáticas ni ejecutadas).
    private const BLOQUEADAS = [
        '#^/\.#',                                   // .env, .git/, .gitignore, .claude/ ...
        '#^/(vendor|db|tests|config)(/|$)#i',       // código interno, respaldos y scripts
        '#^/(composer\.(json|lock)|LocalValetDriver\.php|README[^/]*)$#i',
        '#^/[^/]+\.(png|jpe?g|html|md|sql|log|txt)$#i', // capturas y volcados sueltos en la raíz
        '#^/(dbcheck|debug_test)\.php$#i',          // diagnósticos
    ];

    // Carpetas de documentos: solo con sesión, vía archivo_protegido.php.
    private const PROTEGIDAS = '#^/(expedientes|uploads|novedades)/#i';

    private function bloqueada(string $uri): bool
    {
        foreach (self::BLOQUEADAS as $patron) {
            if (preg_match($patron, $uri)) {
                return true;
            }
        }
        return false;
    }

    public function isStaticFile(string $sitePath, string $siteName, string $uri)/*: string|false */
    {
        $uri = rawurldecode($uri);
        if ($this->bloqueada($uri) || preg_match(self::PROTEGIDAS, $uri)) {
            return false;
        }
        return parent::isStaticFile($sitePath, $siteName, $uri);
    }

    public function frontControllerPath(string $sitePath, string $siteName, string $uri): ?string
    {
        $uri = rawurldecode($uri);

        if ($this->bloqueada($uri)) {
            $_SERVER['SCRIPT_FILENAME'] = $sitePath . '/archivo_protegido.php';
            $_SERVER['SCRIPT_NAME'] = '/archivo_protegido.php';
            $_SERVER['DOCUMENT_ROOT'] = $sitePath;
            $_SERVER['RUTA_BLOQUEADA'] = '1';
            return $sitePath . '/archivo_protegido.php';
        }

        if (preg_match(self::PROTEGIDAS, $uri)) {
            $_SERVER['SCRIPT_FILENAME'] = $sitePath . '/archivo_protegido.php';
            $_SERVER['SCRIPT_NAME'] = '/archivo_protegido.php';
            $_SERVER['DOCUMENT_ROOT'] = $sitePath;
            return $sitePath . '/archivo_protegido.php';
        }

        return parent::frontControllerPath($sitePath, $siteName, $uri);
    }
}
