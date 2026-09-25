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
require __DIR__ . '/../config/conexion.php';
$idContratoPrueba = (int)$conexion->query('SELECT MAX(id_contrato) FROM contratos')->fetchColumn();
$pantallas = [
    'views/trabajadores/index.php', 'views/trabajadores/ver.php', 'views/trabajadores/editar.php',
    'views/contratacion/index.php', 'views/novedades/index.php', 'views/perfil_salud/index.php',
    'views/examenes/index.php', 'views/vacaciones/index.php', 'views/dashboard/dashboard.php',
    'views/components/en_construccion.php', 'views/contratacion/ver.php', 'views/contratacion/editar.php',
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

echo "\nComponentes de contenido compartidos (assets/css/componentes.css)\n";
$componentes = file_get_contents("$raiz/assets/css/componentes.css");
$delComponentes = array_values(array_unique(selectoresCss($componentes)));
prueba('componentes.css define tarjetas, filtros, tabla, avatares y acciones; estilos_base.php lo carga', function () use ($raiz, $delComponentes, $componentes) {
    foreach (['.mini-stat', '.mini-stat-icon', '.filters-bar', '.search-wrap', '.table-wrap', 'thead th', '.worker-avatar', '.avatar-c0', '.avatar-c5', '.acc-btn'] as $sel) {
        afirmar(in_array($sel, $delComponentes, true), "Falta $sel en componentes.css");
    }
    afirmar(!str_contains($componentes, '::before{content:\'\';position:absolute;inset:0 0 auto'), 'Hay una barra de color superior en las tarjetas');
    afirmar(str_contains(file_get_contents("$raiz/views/components/estilos_base.php"), 'assets/css/componentes.css'), 'estilos_base.php no carga componentes.css');
});
// Pantallas ya migradas a los componentes compartidos (las demás se migran por etapas).
foreach (['views/trabajadores/index.php', 'views/contratacion/index.php'] as $p) {
    prueba("$p: no redefine clases de componentes.css ni tiene tarjetas con barra de color", function () use ($raiz, $p, $delComponentes) {
        $s = file_get_contents("$raiz/$p");
        preg_match_all('#<style[^>]*>(.*?)</style>#s', $s, $m);
        $css = implode("\n", $m[1]);
        $repetidos = array_values(array_unique(array_intersect(selectoresCss($css), $delComponentes)));
        afirmar($repetidos === [], 'Redefine en su <style>: ' . implode(' ', $repetidos));
        afirmar(!preg_match('/\.stat-card::before/', $css) && !str_contains($s, 'class="stat-card'), 'Usa tarjetas .stat-card con barra de color');
        afirmar(str_contains($s, "components/avatar.php'"), 'No usa el avatar compartido (components/avatar.php)');
    });
}
prueba('solo las pantallas antiguas conocidas desactivan componentes.css (ninguna nueva puede hacerlo)', function () use ($raiz) {
    $pendientes = [];
    foreach (glob("$raiz/views/*/*.php") as $f) {
        if (str_contains($f, '/views/components/')) {
            continue;   // estilos_base.php solo lo documenta
        }
        if (preg_match('/\$componentesPendientes\s*=\s*true/', file_get_contents($f))) {
            $pendientes[] = substr($f, strlen($raiz) + 1);
        }
    }
    sort($pendientes);
    $conocidas = ['views/dashboard/dashboard.php', 'views/examenes/index.php', 'views/novedades/index.php',
                  'views/perfil_salud/index.php', 'views/vacaciones/index.php'];
    afirmar($pendientes === $conocidas, 'Pantallas que desactivan componentes.css: ' . implode(', ', $pendientes));
});
prueba('avatar: la misma persona tiene el mismo color en Trabajadores y Contratación (claseAvatar por id)', function () use ($raiz) {
    require_once "$raiz/views/components/avatar.php";
    $colores = [];
    foreach (['views/trabajadores/index.php', 'views/contratacion/index.php'] as $pag) {
        $h = ejecutarComoWeb($pag, [], 'GET')['cuerpo'];
        preg_match_all('#class="worker-avatar (avatar-c\d)[^"]*">\s*([^<]*?)\s*</div>\s*<div[^>]*>\s*<div class="worker-name">([^<]+)</div>#', $h, $m, PREG_SET_ORDER);
        afirmar(count($m) > 0, "No se encontraron avatares en $pag");
        foreach ($m as [, $clase, , $nombre]) {
            $colores[trim(html_entity_decode($nombre))][$pag] = $clase;
        }
    }
    $comunes = array_filter($colores, fn($v) => count($v) === 2);
    afirmar(count($comunes) > 0, 'No hay trabajadores en ambas pantallas para comparar');
    foreach ($comunes as $nombre => $v) {
        afirmar(count(array_unique($v)) === 1, "$nombre tiene colores distintos: " . json_encode($v));
    }
});

echo "\nBotones: una sola base (assets/css/botones.css)\n";
$botones = file_get_contents("$raiz/assets/css/botones.css");
prueba('botones.css define la base y los roles, con la fuente de texto y un solo peso', function () use ($botones, $raiz) {
    foreach (['.btn', '.btn-primary', '.btn-outline', '.btn-outline-info', '.btn-outline-danger', '.btn-danger', '.btn-sm'] as $sel) {
        afirmar(in_array($sel, selectoresCss($botones), true), "Falta $sel");
    }
    afirmar((bool)preg_match('/\.btn\{[^}]*font-family:var\(--tx-fuente-texto[^}]*font-weight:var\(--tx-peso-medio/s', $botones), 'La base no fija la fuente de texto y el peso');
    afirmar(substr_count($botones, 'font-weight') === 1 && substr_count($botones, 'font-family') === 1, 'Los modificadores no deben cambiar fuente ni peso');
    afirmar(str_contains(file_get_contents("$raiz/views/components/estilos_base.php"), 'assets/css/botones.css'), 'estilos_base.php no carga botones.css');
});
foreach (array_merge($pantallas, ['views/trabajadores/imprimir.php', 'views/trabajadores/trabajadores_imprimir.php', 'views/trabajadores/trabajadores_pdf.php']) as $p) {
    prueba("$p: sin estilos de botón propios y cada .btn con su rol", function () use ($raiz, $p) {
        $s = file_get_contents("$raiz/$p");
        preg_match_all('#<style[^>]*>(.*?)</style>#s', $s, $m);
        $propios = array_filter(selectoresCss(implode("\n", $m[1])), fn($sel) =>
            preg_match('/^\.(btn|btn-[\w-]+|[\w-]+-btn)(?![\w-])/', $sel) && !preg_match('/^\.(acc-btn|pag-btn|cal-nav-btn|icon-btn|pn-close-btn|btn-icon)/', $sel));
        afirmar($propios === [], 'Define botones en su <style>: ' . implode(' ', array_unique($propios)));
        preg_match_all('/class="(btn(?: [^"]*)?)"/', $s, $clases);
        foreach ($clases[1] as $c) {
            afirmar((bool)preg_match('/\bbtn-(primary|outline|outline-info|outline-danger|danger)\b/', $c), "Botón sin rol: class=\"$c\"");
        }
    });
}

echo "\nSidebar y barra superior vienen de un solo componente\n";
foreach ($pantallas as $p) {
    prueba("$p: usa components/sidebar.php y topbar.php, sin copia propia", function () use ($raiz, $p) {
        $s = file_get_contents("$raiz/$p");
        afirmar(!preg_match('/<aside[^>]*class="sidebar/', $s), 'Tiene su propio <aside class="sidebar">');
        afirmar(!str_contains($s, '<header class="topbar"'), 'Tiene su propio <header class="topbar">');
        afirmar(!str_contains($s, 'class="sidebar-overlay"'), 'Tiene su propia capa .sidebar-overlay');
        afirmar((bool)preg_match("/require __DIR__ \. '[^']*sidebar\.php'/", $s), 'No incluye sidebar.php');
        afirmar((bool)preg_match("/require __DIR__ \. '[^']*topbar\.php'/", $s), 'No incluye topbar.php');
    });
}

// Menú tal como lo ve el navegador, sin la marca del ítem activo.
function menuRenderizado(string $html): string {
    afirmar((bool)preg_match('#<aside class="sidebar".*?</aside>#s', $html, $m), 'No se encontró el sidebar');
    return preg_replace(['/ active"/', '/ aria-current="page"/'], ['"', ''], $m[0]);
}

echo "\nLas pantallas cargan las hojas comunes\n";
$paginas = [
    'views/trabajadores/index.php' => [], 'views/trabajadores/ver.php' => ['id' => '9'],
    'views/trabajadores/editar.php' => ['id' => '9'], 'views/contratacion/index.php' => [],
    'views/novedades/index.php' => [], 'views/perfil_salud/index.php' => [], 'views/examenes/index.php' => [],
    'views/vacaciones/index.php' => [], 'views/dashboard/dashboard.php' => [], 'views/actividades/index.php' => [],
    'views/incidentes/index.php' => [], 'views/capacitaciones/index.php' => [], 'views/reportes/index.php' => [],
    'views/indicadores/index.php' => [], 'views/usuarios/index.php' => [], 'views/roles/index.php' => [],
    'views/contratacion/ver.php' => ['id' => (string)$GLOBALS['idContratoPrueba']],
    'views/contratacion/editar.php' => ['id' => (string)$GLOBALS['idContratoPrueba']],
];
$activas = [
    'views/trabajadores/index.php' => 'Trabajadores', 'views/trabajadores/ver.php' => 'Trabajadores',
    'views/trabajadores/editar.php' => 'Trabajadores', 'views/contratacion/index.php' => 'Contratación',
    'views/novedades/index.php' => 'Novedades', 'views/perfil_salud/index.php' => 'Perfil de Salud',
    'views/examenes/index.php' => 'Exámenes Médicos', 'views/vacaciones/index.php' => 'Vacaciones',
    'views/dashboard/dashboard.php' => 'Resumen', 'views/actividades/index.php' => 'Resumen',
    'views/incidentes/index.php' => 'Incidentes', 'views/capacitaciones/index.php' => 'Capacitaciones',
    'views/reportes/index.php' => 'Reportes', 'views/indicadores/index.php' => 'Indicadores',
    'views/usuarios/index.php' => 'Usuarios', 'views/roles/index.php' => 'Roles y Permisos',
    'views/contratacion/ver.php' => 'Contratación', 'views/contratacion/editar.php' => 'Contratación',
];
$menuReferencia = null;
foreach ($paginas as $pag => $get) {
    prueba("$pag: responde 200 con layout.css, tipografia.css, el mismo menú y la barra superior", function () use ($pag, $get, $activas, &$menuReferencia) {
        $r = ejecutarComoWeb($pag, $get, 'GET');
        afirmar($r['status'] === 200, "Respondió {$r['status']}");
        $h = $r['cuerpo'];
        // Menú: 13 opciones, el logo con "Petco" resaltado e idéntico en todas las pantallas.
        $menu = menuRenderizado($h);
        afirmar(substr_count($menu, 'class="nav-item"') === 13, 'El menú no tiene 13 opciones');
        afirmar(str_contains($menu, 'Roles y Permisos') && str_contains($menu, 'Plasty<em>Petco</em>'), 'Falta "Roles y Permisos" o el logo PlastyPetco');
        $menuReferencia ??= $menu;
        afirmar($menu === $menuReferencia, 'El menú lateral es distinto al de la primera pantalla');
        preg_match_all('#class="nav-item active"[^>]*>\s*<svg.*?</svg>([^<]+)#s', $h, $act);
        $esperada = $activas[$pag];
        afirmar(array_map('trim', $act[1]) === ($esperada === null ? [] : [$esperada]), 'Ítem activo: ' . json_encode($act[1], JSON_UNESCAPED_UNICODE));
        afirmar(substr_count($h, 'class="topbar"') === 1 && str_contains($h, 'id="profileWrap"') && str_contains($h, 'class="menu-toggle"'), 'Barra superior incompleta');
        afirmar(str_contains($h, 'href="../../assets/css/layout.css"'), 'No carga layout.css');
        afirmar(str_contains($h, 'href="../../assets/css/tipografia.css"'), 'No carga tipografia.css');
        afirmar(str_contains($h, 'class="topbar-title"'), 'No tiene el título de la barra superior');
        afirmar(!preg_match('#<b>(Warning|Notice|Fatal error|Deprecated)</b>#', $h), 'Hay errores de PHP en la página');
    });
}

echo "\n" . ($total - $fallos) . " de $total pruebas pasaron.\n";
exit($fallos > 0 ? 1 : 0);
