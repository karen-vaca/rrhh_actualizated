<?php
// Control de acceso del sistema (equivalente a los middleware `auth` + `role:` de
// Laravel). Cada página lo llama en su primera línea:
//
//   require_once __DIR__ . '/../../config/auth.php';
//   requerirAcceso();                        // sesión + rol Administrador (por defecto)
//   requerirAcceso(ROLES_CUALQUIER_USUARIO); // solo sesión (dashboard, perfil propio)
//
// Los scripts de mantenimiento (db/, tests/, diagnósticos) llaman soloConsola().

// Deben coincidir con la tabla `roles`.
const ROL_ADMINISTRADOR = 1;
const ROL_COLABORADOR   = 2;

// Módulos RRHH / SG-SST: solo Administrador. Un Colaborador es un empleado y no
// debe ver datos de otros trabajadores (salud, contratos, orientación sexual...).
const ROLES_RRHH = [ROL_ADMINISTRADOR];
const ROLES_CUALQUIER_USUARIO = [ROL_ADMINISTRADOR, ROL_COLABORADOR];

function sesionIniciada(): bool {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return !empty($_SESSION['logueado']) && !empty($_SESSION['id_usuario']);
}

// Ruta web de la raíz del proyecto (ej: '' en Herd, '/rrhh' si vive en una subcarpeta).
function urlBase(): string {
    $raiz = realpath(__DIR__ . '/..');
    $docRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '';
    $base = $docRoot !== '' && str_starts_with($raiz, $docRoot) ? substr($raiz, strlen($docRoot)) : '';
    return rtrim(str_replace('\\', '/', $base), '/');
}

// Peticiones hechas con fetch()/AJAX o que esperan JSON: se responde JSON en vez de redirigir.
function esPeticionJson(): bool {
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    return str_contains($accept, 'application/json')
        || strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest'
        || ($_SERVER['HTTP_SEC_FETCH_MODE'] ?? '') === 'cors'
        || (($_SERVER['HTTP_SEC_FETCH_DEST'] ?? '') === 'empty');
}

function denegarAcceso(int $codigo, string $mensaje): void {
    http_response_code($codigo);
    if (esPeticionJson()) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'mensaje' => $mensaje], JSON_UNESCAPED_UNICODE);
        exit;
    }
    header('Content-Type: text/html; charset=utf-8');
    $login = htmlspecialchars(urlBase() . '/index.php', ENT_QUOTES, 'UTF-8');
    $mensaje = htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8');
    echo <<<HTML
<!DOCTYPE html>
<html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Acceso denegado | PlastyPetco</title>
<style>
body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#f2f5f3;font-family:'DM Sans',system-ui,sans-serif;color:#0d1f11;padding:16px}
.card{background:#fff;border:1px solid #e0ebe4;border-radius:18px;padding:36px 32px;max-width:420px;text-align:center;box-shadow:0 2px 12px rgba(0,0,0,.07)}
.code{font-size:12px;font-weight:700;letter-spacing:.8px;color:#dc2626;background:#fff1f2;border:1px solid #fecaca;border-radius:20px;padding:4px 10px;display:inline-block;margin-bottom:12px}
h1{font-size:20px;margin:0 0 8px}p{font-size:14px;color:#4a6655;line-height:1.5;margin:0 0 22px}
a{display:inline-block;background:linear-gradient(135deg,#2ddf6e,#1a9945);color:#021a08;font-weight:700;font-size:13px;text-decoration:none;border-radius:10px;padding:10px 18px}
</style></head><body><div class="card"><span class="code">Error $codigo</span><h1>Acceso denegado</h1><p>$mensaje</p><a href="$login">Ir al inicio</a></div></body></html>
HTML;
    exit;
}

/**
 * Exige sesión iniciada y uno de los roles indicados. Sin sesión: redirige al login
 * (o 401 en JSON). Con sesión pero sin el rol: 403.
 */
function requerirAcceso(array $roles = ROLES_RRHH): void {
    if (PHP_SAPI === 'cli') {
        return; // tests y scripts de consola: quien los ejecuta ya tiene acceso al servidor
    }

    if (!sesionIniciada()) {
        if (esPeticionJson() || ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            denegarAcceso(401, 'Tu sesión expiró o no has iniciado sesión.');
        }
        header('Location: ' . urlBase() . '/index.php');
        exit;
    }

    if (!in_array((int)($_SESSION['id_roles'] ?? 0), $roles, true)) {
        denegarAcceso(403, 'No tienes permiso para acceder a esta sección.');
    }

    // Toda petición que puede cambiar datos (POST) debe traer el token CSRF.
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        verificarCsrf();
    }
}

// ── CSRF ──
// Un token aleatorio por sesión. Cada <form method="POST"> incluye campoCsrf(), y los
// fetch() pueden enviarlo en la cabecera X-CSRF-Token. requerirAcceso() lo valida en
// todos los POST, así que una página externa no puede enviar formularios en nombre
// de un usuario con sesión abierta.

function tokenCsrf(): string {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function campoCsrf(): string {
    return '<input type="hidden" name="csrf_token" value="' . tokenCsrf() . '">';
}

function verificarCsrf(): void {
    $enviado = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $esperado = $_SESSION['csrf_token'] ?? '';
    if ($esperado === '' || !is_string($enviado) || !hash_equals($esperado, $enviado)) {
        denegarAcceso(403, 'El formulario expiró o no es válido. Vuelve a la página anterior, recárgala e inténtalo de nuevo.');
    }
}

// Para acciones que cambian datos: un enlace (GET) nunca debe poder ejecutarlas.
function requerirPost(): void {
    if (PHP_SAPI !== 'cli' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        header('Allow: POST');
        denegarAcceso(405, 'Esta acción solo se puede ejecutar desde el botón correspondiente del sistema.');
    }
}

// Para scripts de mantenimiento y diagnóstico: nunca se ejecutan desde el navegador.
function soloConsola(): void {
    if (PHP_SAPI !== 'cli') {
        http_response_code(404);
        exit;
    }
}
