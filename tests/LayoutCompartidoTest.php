<?php
require_once __DIR__ . '/../config/auth.php';
soloConsola();
// Tests del layout y la tipografía compartidos (assets/css/layout.css y tipografia.css).
// Antes cada pantalla tenía su propia copia del CSS del sidebar, la barra superior y el
// menú de perfil, y se fueron desincronizando (el título de la barra medía 24px en la
// ficha y 18–22px en el listado). Estos tests fallan si una pantalla vuelve a redefinir
// una clase del layout en su <style> o deja de cargar las hojas comunes.
// Ejecutar: php tests/LayoutCompartidoTest.php

require __DIR__ . '/ayudante_web.php';

$fallos = 0;
$total = 0;

function prueba(string $nombre, callable $fn): void {
    global $fallos, $total;
    $total++;
    try {
        $fn();
        echo "  OK    $nombre\n";
    } catch (Throwable $e) {
        $fallos++;
        echo "  FALLA $nombre\n        " . $e->getMessage() . "\n";
    }
}

function afirmar(bool $condicion, string $mensaje): void {
    if (!$condicion) {
        throw new Exception($mensaje);
    }
}

$raiz = dirname(__DIR__);
$pantallas = [
    'views/trabajadores/index.php', 'views/trabajadores/ver.php', 'views/trabajadores/editar.php',
    'views/contratacion/index.php', 'views/novedades/index.php', 'views/perfil_salud/index.php',
    'views/examenes/index.php', 'views/vacaciones/index.php', 'views/dashboard/dashboard.php',
    'views/components/en_construccion.php',
];

// Selectores (sin pseudo-clases de estado) de un texto CSS: "a,b{...}" -> ['a','b'].
function selectoresCss(string $css): array {
    $css = preg_replace('#/\*.*?\*/#s', '', $css);
    $css = preg_replace('/@keyframes[^{]*\{(?:[^{}]*\{[^{}]*\})*[^{}]*\}/', '', $css);
    preg_match_all('/([^{}]+)\{/', $css, $m);
    $out = [];
    foreach ($m[1] as $prelude) {
        $prelude = trim($prelude);
        if ($prelude === '' || $prelude[0] === '@') {
            continue;
        }
        foreach (explode(',', $prelude) as $sel) {
            $out[] = preg_replace('/\s+/', ' ', trim($sel));
        }
    }
    return $out;
}

$layout = file_get_contents("$raiz/assets/css/layout.css");
$tipografia = file_get_contents("$raiz/assets/css/tipografia.css");
$delLayout = array_values(array_diff(array_unique(selectoresCss($layout)), [':root']));

echo "Hojas comunes\n";
prueba('layout.css define el sidebar, la barra superior, el menú de perfil y el contenido', function () use ($delLayout) {
    foreach (['.sidebar', '.nav-item', '.topbar', '.topbar-title', '.search-bar', '.notif-badge', '.profile-btn', '.profile-dropdown', '.main', '.content', '.footer-app'] as $sel) {
        afirmar(in_array($sel, $delLayout, true), "Falta $sel en layout.css");
    }
});
prueba('el título de la barra usa la variable de la escala (--tx-topbar-titulo)', function () use ($layout, $tipografia) {
    afirmar((bool)preg_match('/\.topbar-title\{[^}]*font-size:var\(--tx-topbar-titulo\)/', $layout), 'layout.css no usa --tx-topbar-titulo');
    afirmar((bool)preg_match('/--tx-topbar-titulo:\s*clamp\(18px,2vw,22px\)/', $tipografia), 'tipografia.css no define --tx-topbar-titulo');
});
prueba('estilos_base.php carga las fuentes, tipografia.css y layout.css', function () use ($raiz) {
    $b = file_get_contents("$raiz/views/components/estilos_base.php");
    foreach (['fonts.googleapis.com', 'assets/css/tipografia.css', 'assets/css/layout.css'] as $x) {
        afirmar(str_contains($b, $x), "estilos_base.php no carga $x");
    }
});

echo "\nNinguna pantalla redefine el layout\n";
foreach ($pantallas as $p) {
    prueba("$p: incluye estilos_base.php y no redefine clases de layout.css", function () use ($raiz, $p, $delLayout) {
        $s = file_get_contents("$raiz/$p");
        afirmar(str_contains($s, "estilos_base.php'"), 'No incluye estilos_base.php');
        afirmar(!str_contains($s, 'fonts.googleapis.com/css2'), 'Carga sus propias fuentes (deben venir de estilos_base.php)');
        preg_match_all('#<style[^>]*>(.*?)</style>#s', $s, $m);
        $propios = selectoresCss(implode("\n", $m[1]));
        $repetidos = array_values(array_unique(array_intersect($propios, $delLayout)));
        afirmar($repetidos === [], 'Redefine en su <style>: ' . implode(' ', $repetidos));
        afirmar(!preg_match('/(^|[\s;{]):root\{[^}]*--(sidebar-w|topbar-h)\s*:/', implode("\n", $m[1])), 'Redefine --sidebar-w o --topbar-h');
    });
}

echo "\nLas pantallas cargan las hojas comunes\n";
$paginas = [
    'views/trabajadores/index.php' => [], 'views/trabajadores/ver.php' => ['id' => '9'],
    'views/trabajadores/editar.php' => ['id' => '9'], 'views/contratacion/index.php' => [],
    'views/novedades/index.php' => [], 'views/perfil_salud/index.php' => [], 'views/examenes/index.php' => [],
    'views/vacaciones/index.php' => [], 'views/dashboard/dashboard.php' => [], 'views/actividades/index.php' => [],
];
foreach ($paginas as $pag => $get) {
    prueba("$pag: responde 200 con layout.css, tipografia.css y la barra superior", function () use ($pag, $get) {
        $r = ejecutarComoWeb($pag, $get, 'GET');
        afirmar($r['status'] === 200, "Respondió {$r['status']}");
        $h = $r['cuerpo'];
        afirmar(str_contains($h, 'href="../../assets/css/layout.css"'), 'No carga layout.css');
        afirmar(str_contains($h, 'href="../../assets/css/tipografia.css"'), 'No carga tipografia.css');
        afirmar(str_contains($h, 'class="topbar-title"'), 'No tiene el título de la barra superior');
        afirmar(!preg_match('#<b>(Warning|Notice|Fatal error|Deprecated)</b>#', $h), 'Hay errores de PHP en la página');
    });
}

echo "\n" . ($total - $fallos) . " de $total pruebas pasaron.\n";
exit($fallos > 0 ? 1 : 0);
