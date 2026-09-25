<?php
require_once __DIR__ . '/../config/auth.php';
soloConsola();
// Tests de los componentes compartidos de notificaciones/confirmaciones y formularios, y de
// su uso en Contratación y Trabajadores: avisos de éxito, confirmación al terminar un
// contrato, validación sin globos del navegador, máscara de montos y campos de fecha.
// Ejecutar: php tests/NotificacionesTest.php
// El comportamiento en el navegador (cierre automático, X, teclear fechas) se verificó con
// Chrome real; aquí se comprueba el contrato de cada pieza y su conexión con las pantallas.

require __DIR__ . '/../config/conexion.php';
require __DIR__ . '/ayudante_web.php';
require __DIR__ . '/../views/components/notificaciones.php';

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

// Avisos que la página deja para mostrar al cargar (JSON dentro de <script data-aviso>).
function avisosDe(string $html): array {
    preg_match_all('#<script type="application/json" data-aviso>(.*?)</script>#s', $html, $m);
    return array_map(fn($j) => json_decode($j, true), $m[1]);
}

$raiz = dirname(__DIR__);
$js = file_get_contents("$raiz/assets/js/notificaciones.js");
$jsForm = file_get_contents("$raiz/assets/js/formularios.js");
$base = file_get_contents("$raiz/views/components/estilos_base.php");

echo "Componente de notificaciones y confirmaciones\n";
prueba('estilos_base.php carga notificaciones y formularios (JS sin defer: las pantallas los usan al cargar)', function () use ($base) {
    foreach (['assets/css/notificaciones.css', 'assets/css/formularios.css'] as $css) {
        afirmar(str_contains($base, $css), "No carga $css");
    }
    foreach (['assets/js/notificaciones.js', 'assets/js/formularios.js'] as $script) {
        afirmar((bool)preg_match('#<script src="\.\./\.\./' . preg_quote($script, '#') . '"></script>#', $base), "$script debe cargarse sin defer");
    }
    afirmar(str_contains($base, "require_once __DIR__ . '/notificaciones.php'"), 'No carga avisoAlCargar()');
});
prueba('avisoAlCargar() deja un aviso JSON con tipo, texto y acción', function () {
    $a = avisosDe(avisoAlCargar('ok', 'Hecho', ['texto' => 'Ir', 'href' => 'x.php?a=1&b=2']))[0];
    afirmar($a === ['tipo' => 'ok', 'texto' => 'Hecho', 'accion' => ['texto' => 'Ir', 'href' => 'x.php?a=1&b=2']], json_encode($a));
});
prueba('avisoAlCargar() no permite inyectar HTML ni cerrar el <script> (nombres de personas)', function () {
    $html = avisoAlCargar('ok', 'Se ha finalizado el contrato de </script><script>alert(1)</script>.');
    afirmar(substr_count($html, '</script>') === 1 && !str_contains($html, '<script>alert'), 'El texto rompe el <script>');
    afirmar(avisosDe($html)[0]['texto'] === 'Se ha finalizado el contrato de </script><script>alert(1)</script>.', 'Se alteró el texto');
});
prueba('el aviso se cierra solo (6 s), tiene X accesible y pausa al pasar el mouse', function () use ($js) {
    afirmar(str_contains($js, 'var DURACION = 6000;'), 'Duración por defecto distinta de 6 s');
    afirmar(str_contains($js, "setAttribute('aria-label', 'Cerrar aviso')"), 'La X no tiene etiqueta');
    afirmar(str_contains($js, "'mouseenter'") && str_contains($js, "'mouseleave'"), 'No pausa con el mouse');
    afirmar(str_contains($js, 'textContent = texto'), 'El texto debe insertarse como texto, no como HTML');
});
prueba('la confirmación es un diálogo accesible: Escape y Cancelar no ejecutan; en acciones peligrosas el foco empieza en Cancelar', function () use ($js) {
    foreach (["setAttribute('role', 'alertdialog')", "setAttribute('aria-modal', 'true')", "e.key === 'Escape'", 'terminar(false)', '(op.peligro ? no : si).focus()'] as $t) {
        afirmar(str_contains($js, $t), "Falta: $t");
    }
    afirmar(str_contains($js, 'form.dataset.confirmar'), 'No soporta formularios con data-confirmar');
});

echo "\nContratación: avisos y confirmación al terminar\n";
prueba('"Contrato renovado correctamente." usa el componente (y ya no el aviso fijo anterior)', function () {
    $h = ejecutarComoWeb('views/contratacion/index.php', ['mensaje' => 'renovado'], 'GET')['cuerpo'];
    $a = avisosDe($h);
    afirmar(count($a) === 1 && $a[0]['tipo'] === 'ok' && $a[0]['texto'] === 'Contrato renovado correctamente.', json_encode($a, JSON_UNESCAPED_UNICODE));
    afirmar(!str_contains($h, 'toast-sistema'), 'Sigue el aviso fijo anterior');
});
prueba('al terminar, el aviso nombra al trabajador: "Se ha finalizado el contrato de …"', function () {
    $h = ejecutarComoWeb('views/contratacion/index.php', ['mensaje' => 'terminado', 'nombre' => 'Camila Avila'], 'GET')['cuerpo'];
    afirmar((avisosDe($h)[0]['texto'] ?? '') === 'Se ha finalizado el contrato de Camila Avila.', json_encode(avisosDe($h), JSON_UNESCAPED_UNICODE));
});
prueba('los errores también se avisan con el componente (tipo error, 10 s)', function () {
    $a = avisosDe(ejecutarComoWeb('views/contratacion/index.php', ['mensaje' => 'monto_invalido'], 'GET')['cuerpo'])[0] ?? [];
    afirmar(($a['tipo'] ?? '') === 'error' && ($a['duracion'] ?? 0) === 10000 && str_contains($a['texto'], 'No se guardaron cambios'), json_encode($a, JSON_UNESCAPED_UNICODE));
});
$listado = ejecutarComoWeb('views/contratacion/index.php', [], 'GET')['cuerpo'];
prueba('Terminar pide confirmación: "¿Estás seguro de finalizar el contrato de …? Esta acción no se puede deshacer."', function () use ($listado) {
    afirmar(str_contains($listado, "mensaje: '¿Estás seguro de finalizar el contrato de ' + quien + '? Esta acción no se puede deshacer.'"), 'Falta el mensaje de confirmación');
    afirmar((bool)preg_match("#Notificar\.confirmar\(\{.*?peligro: true.*?\}\)\.then\(function\(ok\)\{\s*if \(!ok\) return;#s", $listado), 'No espera la confirmación antes de enviar');
    afirmar(str_contains($listado, 'id="formTerminar"') && !str_contains($listado, 'overlayTerminar'), 'Sigue el modal anterior');
});
prueba('el servidor devuelve el nombre del trabajador al terminar (y no deja cambios en esta prueba)', function () use ($conexion) {
    $c = $conexion->query("SELECT c.id_contrato, TRIM(CONCAT(t.nombres, ' ', t.apellidos)) AS nombre FROM contratos c JOIN trabajadores t ON t.id_trabajador = c.id_trabajador ORDER BY c.id_contrato DESC LIMIT 1")->fetch();
    $archivo = tempnam(sys_get_temp_dir(), 'contrato');
    file_put_contents($archivo, json_encode(['accion' => 'terminar', 'contrato_id' => (string)$c['id_contrato'], '__escritura_de_prueba' => true]));
    $salida = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/ejecutar_contrato.php') . ' ' . escapeshellarg($archivo) . ' 2>&1');
    unlink($archivo);
    $r = json_decode(substr($salida, strrpos($salida, '@@RESULTADO@@') + 13), true);
    afirmar(($r['respuesta']['mensaje'] ?? '') === 'terminado' && ($r['respuesta']['nombre'] ?? '') === $c['nombre'], json_encode($r['respuesta'] ?? $salida, JSON_UNESCAPED_UNICODE));
    afirmar($r['contratos_sin_cambios'] === true, 'La prueba dejó cambios en contratos');
});
prueba('ficha del contrato: Terminar con confirmación (data-confirmar) y aviso al volver', function () use ($conexion) {
    $c = $conexion->query("SELECT c.id_contrato, TRIM(CONCAT(t.nombres, ' ', t.apellidos)) AS nombre FROM contratos c JOIN trabajadores t ON t.id_trabajador = c.id_trabajador
                           WHERE COALESCE(c.estado, '') <> 'Terminado' ORDER BY c.id_contrato DESC LIMIT 1")->fetch();
    $h = ejecutarComoWeb('views/contratacion/ver.php', ['id' => (string)$c['id_contrato'], 'mensaje' => 'terminado'], 'GET')['cuerpo'];
    afirmar(str_contains($h, 'data-confirmar="¿Estás seguro de finalizar el contrato de ' . htmlspecialchars($c['nombre']) . '? Esta acción no se puede deshacer."'), 'Falta la confirmación en la ficha');
    afirmar((avisosDe($h)[0]['texto'] ?? '') === 'Se ha finalizado el contrato de ' . $c['nombre'] . '.', 'Falta el aviso en la ficha');
});

echo "\nTrabajadores: aviso al crear\n";
prueba('crear.php vuelve al listado con mensaje=creado y el id del trabajador nuevo', function () use ($raiz) {
    afirmar(str_contains(file_get_contents("$raiz/views/trabajadores/crear.php"), 'header("Location: index.php?mensaje=creado&nuevo_id=" . (int)$conexion->lastInsertId());'), 'La redirección no lleva el id nuevo');
});
prueba('"Trabajador creado correctamente…" con botón "Ir a Contratación" que preselecciona al trabajador', function () {
    $a = avisosDe(ejecutarComoWeb('views/trabajadores/index.php', ['mensaje' => 'creado', 'nuevo_id' => '25'], 'GET')['cuerpo']);
    afirmar(count($a) === 1 && $a[0]['texto'] === 'Trabajador creado correctamente. Ahora puedes continuar registrando su contratación.', json_encode($a, JSON_UNESCAPED_UNICODE));
    afirmar(($a[0]['accion'] ?? null) === ['texto' => 'Ir a Contratación', 'href' => '../contratacion/index.php?nuevo=1&trabajador=25'], json_encode($a[0]['accion'] ?? null));
});
prueba('Contratación abre la nueva contratación con ese trabajador (?nuevo=1&trabajador=ID)', function () use ($listado) {
    afirmar((bool)preg_match("#params\.get\('nuevo'\) === '1'.*?nuevoContrato\(\);.*?aplicarTrabajadorSeleccionado\(opcion\)#s", $listado), 'No preselecciona al trabajador');
});
prueba('los demás avisos de Trabajadores también usan el componente', function () {
    foreach (['reactivado' => 'ok', 'inactivado' => 'aviso', 'id_invalido' => 'error'] as $msj => $tipo) {
        $h = ejecutarComoWeb('views/trabajadores/index.php', ['mensaje' => $msj], 'GET')['cuerpo'];
        afirmar((avisosDe($h)[0]['tipo'] ?? '') === $tipo && !str_contains($h, 'toast-sistema'), "$msj no usa el componente");
    }
});

echo "\nFormularios: sin globos del navegador, montos con máscara, fechas\n";
prueba('el asistente de contratación no usa validación nativa ni alert(): error en línea y no avanza', function () use ($listado) {
    afirmar(str_contains($listado, '<form id="formContrato" method="POST" action="guardar.php" novalidate>'), 'El formulario no tiene novalidate');
    afirmar(!preg_match('/\balert\(/', $listado), 'Quedan alert() en Contratación');
    afirmar((bool)preg_match('#function focoConAlerta\(id, mensaje\) \{\s*marcarErrorCampo\(id, mensaje\);#', $listado), 'Los errores de paso no se muestran en línea');
});
prueba('salario y auxilio usan la máscara compartida (data-dinero) y no se pide escribir "solo con números"', function () use ($listado) {
    afirmar(str_contains($listado, 'type="text" data-dinero id="salario_base_view"') && str_contains($listado, 'type="text" data-dinero id="auxilio_transporte_view"'), 'Falta data-dinero en el asistente');
    $edicion = ejecutarComoWeb('views/contratacion/editar.php', ['id' => (string)$GLOBALS['conexion']->query('SELECT MAX(id_contrato) FROM contratos')->fetchColumn()], 'GET')['cuerpo'];
    afirmar(substr_count($edicion, 'data-dinero') === 2 && str_contains($edicion, 'data-validar novalidate'), 'Falta máscara o validación propia en Editar contrato');
    foreach ([$listado, $edicion, file_get_contents(__DIR__ . '/../views/contratacion/validaciones_contrato.php')] as $txt) {
        afirmar(!preg_match('/Escr[ií]belo solo con n[uú]meros/u', $txt), 'Todavía se pide escribirlo solo con números');
    }
});
prueba('máscara de dinero: solo dígitos, puntos de miles automáticos, pegar con letras se rechaza con aviso', function () use ($jsForm) {
    afirmar(str_contains($jsForm, "return d.replace(/\\B(?=(\\d{3})+(?!\\d))/g, '.');"), 'No pone los puntos de miles');
    afirmar(str_contains($jsForm, "if (e.key && e.key.length === 1 && !/\\d/.test(e.key)) e.preventDefault();"), 'No bloquea letras');
    afirmar(str_contains($jsForm, "marcarError(input, 'Solo se pueden pegar valores numéricos"), 'No avisa al pegar texto con letras');
});
prueba('el servidor sigue aceptando montos con puntos de miles (lo que envía la máscara)', function () {
    require_once __DIR__ . '/../views/contratacion/validaciones_contrato.php';
    $r = validarMonto('1.750.905', 'El salario', true, false);
    afirmar($r['error'] === null && $r['valor'] === 1750905.0, json_encode($r));
});
prueba('ningún campo de fecha cambia su "min" mientras se escribe (causaba años como "0022")', function () use ($raiz) {
    foreach (['views/contratacion/index.php', 'views/novedades/index.php', 'views/perfil_salud/index.php'] as $f) {
        $s = file_get_contents("$raiz/$f");
        afirmar(!preg_match('/\b(fin|\$\(\'fecha_fin\'\))\.min\s*=/', $s), "$f todavía asigna min a la fecha de fin");
        afirmar(!preg_match("/(fin|inicio)\.addEventListener\('change', validar\)/", $s), "$f valida fechas en 'change' (Chrome lo dispara con cada dígito)");
    }
});

echo "\n" . ($total - $fallos) . " de $total pruebas pasaron.\n";
exit($fallos > 0 ? 1 : 0);
