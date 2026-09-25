<?php
require_once __DIR__ . '/../config/auth.php';
soloConsola();
// Tests de la Ficha del Contrato (views/contratacion/ver.php) y del listado de Contratación:
// "Ver" abre una página completa con el trabajador real (antes un panel lateral mostraba la
// palabra "Trabajador"), acciones con etiqueta, sin ID en las filas y tarjetas que filtran.
// Ejecutar: php tests/ContratacionFichaTest.php

require __DIR__ . '/../config/conexion.php';
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

// Un contrato real con su trabajador, directo de la base (no del listado).
$real = $conexion->query("SELECT co.id_contrato, co.id_trabajador, t.nombres, t.apellidos, t.numero_documento
                          FROM contratos co JOIN trabajadores t ON t.id_trabajador = co.id_trabajador
                          ORDER BY co.id_contrato DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$idReal = (int)$real['id_contrato'];
$nombreReal = trim($real['nombres'] . ' ' . $real['apellidos']);
$ficha = ejecutarComoWeb('views/contratacion/ver.php', ['id' => (string)$idReal], 'GET');
$fichaHtml = $ficha['cuerpo'];
$listado = ejecutarComoWeb('views/contratacion/index.php', [], 'GET')['cuerpo'];

echo "Ficha del Contrato\n";
prueba('responde 200 sin errores de PHP', function () use ($ficha) {
    afirmar($ficha['status'] === 200, "Respondió {$ficha['status']}");
    afirmar(!preg_match('#<b>(Warning|Notice|Fatal error|Deprecated)</b>#', $ficha['cuerpo']), 'Hay errores de PHP');
});
prueba('muestra el nombre real del trabajador vinculado (no la palabra "Trabajador")', function () use ($fichaHtml, $nombreReal) {
    afirmar((bool)preg_match('#class="worker-main-name">([^<]+)<#', $fichaHtml, $m), 'No está el nombre en el encabezado');
    afirmar(trim(html_entity_decode($m[1])) === $nombreReal, "Muestra \"{$m[1]}\" en vez de \"$nombreReal\"");
});
prueba('el número de contrato y el del trabajador aparecen solo como referencia en la ficha', function () use ($fichaHtml, $idReal, $real) {
    afirmar(str_contains($fichaHtml, 'Contrato #' . str_pad((string)$idReal, 4, '0', STR_PAD_LEFT)), 'Falta el número de contrato');
    afirmar(str_contains($fichaHtml, 'Trabajador #' . str_pad((string)$real['id_trabajador'], 4, '0', STR_PAD_LEFT)), 'Falta el folio del trabajador');
});
prueba('secciones: datos, vigencia, nómina estimada, observaciones, documentos y registro', function () use ($fichaHtml) {
    foreach (['Datos del contrato', 'Vigencia', 'Nómina mensual', 'Estimado', 'Valor estimado de referencia', 'Observaciones',
              'Documentos de vinculación', 'Registro', 'Jornada', 'Modalidad', 'Periodo de prueba', 'Jefe inmediato'] as $t) {
        afirmar(str_contains($fichaHtml, $t), "Falta \"$t\"");
    }
});
prueba('los tres documentos se descargan de generar_documento.php con el id del contrato', function () use ($fichaHtml, $idReal) {
    foreach (['contrato', 'perfil', 'induccion'] as $tipo) {
        afirmar(str_contains($fichaHtml, "generar_documento.php?tipo=$tipo&amp;id=$idReal"), "Falta la descarga de $tipo");
    }
});
prueba('usa el diseño de ficha compartido y el mismo color de avatar que los listados', function () use ($fichaHtml, $real) {
    require_once __DIR__ . '/../views/components/avatar.php';
    afirmar(str_contains($fichaHtml, 'href="../../assets/css/ficha.css"'), 'No carga ficha.css');
    afirmar(str_contains($fichaHtml, 'class="worker-avatar-lg ' . claseAvatar($real['id_trabajador']) . '"'), 'El avatar no usa claseAvatar()');
    $fichaTrab = ejecutarComoWeb('views/trabajadores/ver.php', ['id' => (string)$real['id_trabajador']], 'GET')['cuerpo'];
    afirmar(str_contains($fichaTrab, 'href="../../assets/css/ficha.css"'), 'La ficha del trabajador no carga ficha.css');
    afirmar(str_contains($fichaTrab, 'class="worker-avatar-lg ' . claseAvatar($real['id_trabajador']) . '"'), 'La ficha del trabajador no usa el mismo color');
});
prueba('botones: volver, ver trabajador, editar (página propia) y renovar/terminar en el listado', function () use ($fichaHtml, $idReal, $real) {
    afirmar(str_contains($fichaHtml, 'href="../trabajadores/ver.php?id=' . (int)$real['id_trabajador'] . '"'), 'Falta "Ver trabajador"');
    $estado = $GLOBALS['conexion']->query("SELECT estado FROM contratos WHERE id_contrato = $idReal")->fetchColumn();
    if ($estado !== 'Terminado') {
        afirmar(str_contains($fichaHtml, "href=\"editar.php?id=$idReal\""), 'Editar no lleva a editar.php');
        afirmar(str_contains($fichaHtml, "href=\"index.php?abrir=renovar&amp;id=$idReal\""), 'Falta el botón renovar');
        // Terminar se hace desde la ficha, con confirmación (data-confirmar) y volviendo a ella.
        afirmar((bool)preg_match('#<form method="POST" action="guardar\.php" class="form-en-linea"\s+data-confirmar="[^"]+"#', $fichaHtml)
            && str_contains($fichaHtml, '<input type="hidden" name="accion" value="terminar">')
            && str_contains($fichaHtml, "<input type=\"hidden\" name=\"contrato_id\" value=\"$idReal\">"), 'Falta el botón terminar con confirmación');
    }
});
prueba('un id inexistente o inválido vuelve al listado con aviso', function () {
    foreach (['999999', 'abc', '0'] as $id) {
        $r = ejecutarComoWeb('views/contratacion/ver.php', ['id' => $id], 'GET');
        afirmar($r['status'] === 302 && str_contains($r['location'], 'index.php?mensaje=id_invalido'), "id=$id respondió {$r['status']} {$r['location']}");
    }
});
prueba('los enlaces antiguos al panel (?ver_contrato=ID) redirigen a la ficha', function () use ($idReal) {
    $r = ejecutarComoWeb('views/contratacion/index.php', ['ver_contrato' => (string)$idReal], 'GET');
    afirmar($r['status'] === 302 && str_contains($r['location'], "ver.php?id=$idReal"), "Respondió {$r['status']} {$r['location']}");
});

echo "\nListado de Contratación\n";
prueba('ya no existe el panel lateral de "Ver contrato"', function () use ($listado) {
    afirmar(!str_contains($listado, 'overlayVerContrato') && !str_contains($listado, 'view-drawer') && !str_contains($listado, 'function abrirVistaContrato'),
        'Sigue el panel lateral');
});
prueba('"Ver" es un enlace a la ficha y las cuatro acciones tienen etiqueta (data-tip y aria-label)', function () use ($listado) {
    preg_match_all('#<div class="acc-btns">(.*?)</div>\s*</td>#s', $listado, $filas);
    afirmar(count($filas[1]) > 0, 'No hay filas con acciones');
    foreach ($filas[1] as $acc) {
        afirmar((bool)preg_match('#<a href="ver\.php\?id=\d+" class="acc-btn" data-tip="Ver contrato" aria-label="Ver contrato">#', $acc), 'Ver no enlaza a la ficha');
        afirmar((bool)preg_match('#<a href="editar\.php\?id=\d+" class="acc-btn" data-tip="Editar contrato"#', $acc), 'Editar no enlaza a editar.php');
        afirmar(substr_count($acc, '<span class="acc-label">') === 4, 'Cada acción debe tener su texto (acc-label), como en Trabajadores');
        foreach (['Editar contrato', 'Renovar contrato', 'Terminar contrato'] as $t) {
            afirmar(str_contains($acc, "data-tip=\"$t\" aria-label=\"$t\""), "Falta la etiqueta \"$t\"");
        }
        afirmar(!str_contains($acc, 'title="'), 'Queda un title (tooltip duplicado)');
    }
});
prueba('las filas no muestran el ID del trabajador', function () use ($listado) {
    preg_match('#<tbody>(.*?)</tbody>#s', $listado, $m);
    afirmar(!preg_match('/\bID \d{4}\b/', $m[1]) && !str_contains($m[1], 'class="worker-id"'), 'Todavía aparece "ID 0000" en el listado');
});
prueba('las 4 tarjetas son filtros y su número coincide con las filas que filtran', function () use ($listado) {
    preg_match_all('#<div class="mini-stat mini-stat-filtro"[^>]*data-filtro-(estado|mes)="([^"]+)"[^>]*>.*?<div class="mini-stat-num[^"]*">(\d+)</div>#s', $listado, $t, PREG_SET_ORDER);
    afirmar(count($t) === 4, 'Se esperaban 4 tarjetas-filtro, hay ' . count($t));
    preg_match_all('#<tr\s+id="row-\d+"\s+data-estado="([^"]*)".*?data-inicio-mes="([^"]*)"#s', $listado, $filas, PREG_SET_ORDER);
    foreach ($t as [, $tipo, $valor, $num]) {
        $n = count(array_filter($filas, fn($f) => $tipo === 'estado' ? $f[1] === $valor : $f[2] === $valor));
        afirmar($n === (int)$num, "Tarjeta $tipo=$valor dice $num pero filtraría $n filas");
    }
    afirmar(str_contains($listado, 'id="filaSinResultados"'), 'Falta el mensaje de "sin resultados" del filtro');
});

prueba('botón "Volver al Panel" junto a "Nueva contratación" lleva al Dashboard', function () use ($listado) {
    afirmar((bool)preg_match('#<div class="page-header-right">\s*<a href="\.\./dashboard/dashboard\.php" class="btn btn-outline">.*?Volver al Panel</a>\s*<button[^>]*onclick="nuevoContrato\(\)"#s', $listado),
        'No está el botón Volver al Panel junto a Nueva contratación');
    afirmar(is_file(__DIR__ . '/../views/dashboard/dashboard.php'), 'El destino no existe');
    $r = ejecutarComoWeb('views/dashboard/dashboard.php', [], 'GET');
    afirmar($r['status'] === 200 && str_contains($r['cuerpo'], 'class="topbar-title">Panel de Control<'), 'El Dashboard no carga');
});
prueba('renovar: el asistente deja fijo al trabajador del contrato', function () use ($listado) {
    // Debe fijarse DESPUÉS de setDisabledForm(false), que vuelve a habilitar todos los campos.
    afirmar((bool)preg_match("#function renovarContrato\(idx\)\{(?:(?!\n  \}).)*setDisabledForm\(false\);\s*fijarTrabajador\(true\);#s", $listado), 'Renovar no fija al trabajador (o lo fija antes de rehabilitar el formulario)');
    afirmar(str_contains($listado, 'id="notaTrabajadorFijo"') && str_contains($listado, 'function fijarTrabajador'), 'Falta el bloqueo del trabajador');
});

echo "\nEditar Contrato (página de una pantalla)\n";
$edicion = ejecutarComoWeb('views/contratacion/editar.php', ['id' => (string)$idReal], 'GET');
prueba('responde 200 sin errores y es un formulario de una sola pantalla (sin asistente)', function () use ($edicion) {
    afirmar($edicion['status'] === 200 && !preg_match('#<b>(Warning|Notice|Fatal error|Deprecated)</b>#', $edicion['cuerpo']), 'Error al cargar');
    afirmar(!str_contains($edicion['cuerpo'], 'wizard') && substr_count($edicion['cuerpo'], '<form') === 1, 'No debería haber asistente');
    foreach (['Cargo y ubicación', 'Condiciones', 'Vigencia', 'Salario', 'Observaciones', 'Guardar cambios'] as $t) {
        afirmar(str_contains($edicion['cuerpo'], $t), "Falta \"$t\"");
    }
});
prueba('el trabajador se ve de solo lectura y el formulario no envía ningún campo de trabajador', function () use ($edicion, $nombreReal, $real) {
    $h = $edicion['cuerpo'];
    afirmar((bool)preg_match('#id="trabajadorNombre" type="text" value="' . preg_quote(htmlspecialchars($nombreReal), '#') . '" readonly#', $h), 'El nombre no está en solo lectura');
    afirmar((bool)preg_match('#id="trabajadorDocumento" type="text" value="' . preg_quote((string)$real['numero_documento'], '#') . '" readonly#', $h), 'El documento no está en solo lectura');
    afirmar(!str_contains($h, 'name="id_trabajador"') && !str_contains($h, 'buscar_trabajador'), 'El formulario permite enviar o buscar un trabajador');
    afirmar(str_contains($h, 'name="accion" value="actualizar"') && str_contains($h, 'name="volver" value="editar"'), 'Faltan los campos ocultos de edición');
});
prueba('editar: un id inexistente vuelve al listado', function () {
    $r = ejecutarComoWeb('views/contratacion/editar.php', ['id' => '999999'], 'GET');
    afirmar($r['status'] === 302 && str_contains($r['location'], 'index.php?mensaje=id_invalido'), "Respondió {$r['status']} {$r['location']}");
});
prueba('desde la página de edición, un rechazo vuelve al formulario con el motivo (sin guardar)', function () use ($idReal, $real) {
    $otro = (int)$GLOBALS['conexion']->query('SELECT id_trabajador FROM trabajadores WHERE id_trabajador <> ' . (int)$real['id_trabajador'] . ' LIMIT 1')->fetchColumn();
    $antes = huellaTablas($GLOBALS['conexion'], ['contratos']);
    $r = ejecutarComoWeb('views/contratacion/guardar.php', ['accion' => 'actualizar', 'contrato_id' => (string)$idReal, 'volver' => 'editar',
        'id_trabajador' => (string)$otro, 'jefe_inmediato' => 'Alguien Prueba', 'fecha_inicio' => '2026-01-01', 'salario_base' => '2000000',
        'id_tipos_contrato' => '1'], 'POST');
    afirmar($r['status'] === 302 && str_starts_with($r['location'], "editar.php?id=$idReal&mensaje=validacion&campo=id_trabajador"), "Redirigió a: {$r['location']}");
    afirmar(huellaTablas($GLOBALS['conexion'], ['contratos']) === $antes, 'Cambió la tabla contratos');
});
prueba('la consulta de contratos trae la fecha de ingreso (renovar ya no la reemplaza por la fecha de inicio)', function () use ($idReal, $real) {
    require_once __DIR__ . '/../views/contratacion/funciones_contrato.php';
    $fila = consultarContratos($GLOBALS['conexion'], $idReal)[0];
    $esperada = $GLOBALS['conexion']->query('SELECT fecha_ingreso FROM trabajadores WHERE id_trabajador = ' . (int)$real['id_trabajador'])->fetchColumn();
    afirmar(array_key_exists('fecha_ingreso', $fila) && $fila['fecha_ingreso'] === $esperada, 'fecha_ingreso: ' . json_encode($fila['fecha_ingreso'] ?? null));
});

echo "\nEditar y renovar: el trabajador del contrato no cambia (validado en el servidor)\n";
// Ejecuta el guardar.php real. Con $escritura, lo que guarde se deshace al final
// (tests/ejecutar_contrato.php, modo __escritura_de_prueba) y devuelve la fila resultante.
function guardarContrato(array $post, bool $escritura = false): array {
    $archivo = tempnam(sys_get_temp_dir(), 'contrato');
    file_put_contents($archivo, json_encode($post + ($escritura ? ['__escritura_de_prueba' => true] : [])));
    $salida = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/ejecutar_contrato.php') . ' ' . escapeshellarg($archivo) . ' 2>&1');
    unlink($archivo);
    $pos = strrpos((string)$salida, '@@RESULTADO@@');
    afirmar($pos !== false, "Salida inesperada:\n$salida");
    $r = json_decode(substr($salida, $pos + strlen('@@RESULTADO@@')), true);
    afirmar($r['contratos_sin_cambios'] === true, 'La tabla contratos quedó modificada después de la prueba');
    return $r;
}

// Contrato real de un trabajador activo, con su POST tal como lo enviaría el formulario.
$c = $conexion->query("SELECT co.*, t.id_area, t.id_cargo, t.fecha_ingreso FROM contratos co JOIN trabajadores t ON t.id_trabajador = co.id_trabajador
                       WHERE t.estado = 1 AND co.id_tipos_contrato IS NOT NULL AND TRIM(COALESCE(co.jefe_inmediato, '')) <> ''
                       ORDER BY co.id_contrato DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$otroTrabajador = (int)$conexion->query('SELECT id_trabajador FROM trabajadores WHERE estado = 1 AND id_trabajador <> ' . (int)$c['id_trabajador'] . ' LIMIT 1')->fetchColumn();
$postEdicion = [
    'contrato_id' => (string)$c['id_contrato'], 'id_area' => (string)$c['id_area'], 'id_cargo' => (string)$c['id_cargo'],
    'id_tipos_contrato' => (string)$c['id_tipos_contrato'], 'fecha_inicio' => $c['fecha_inicio'], 'fecha_fin' => (string)$c['fecha_fin'],
    'salario_base' => (string)(int)$c['salario_base'], 'auxilio_transporte' => (string)(int)$c['auxilio_transporte'],
    'jefe_inmediato' => $c['jefe_inmediato'], 'jornada' => $c['jornada'], 'modalidad' => $c['modalidad'],
    'periodo_prueba' => $c['periodo_prueba'], 'fecha_ingreso' => (string)$c['fecha_ingreso'],
];

foreach (['actualizar' => 'editar', 'renovar' => 'renovar'] as $accion => $nombre) {
    prueba("$nombre con OTRO trabajador se rechaza y no cambia nada", function () use ($postEdicion, $accion, $otroTrabajador) {
        $r = guardarContrato(['accion' => $accion, 'id_trabajador' => (string)$otroTrabajador] + $postEdicion)['respuesta'];
        afirmar(($r['ok'] ?? null) === false && ($r['campo'] ?? '') === 'id_trabajador', 'Debería rechazar: ' . json_encode($r, JSON_UNESCAPED_UNICODE));
        afirmar(str_contains($r['texto'] ?? '', 'no puede reasignarse'), 'Mensaje: ' . ($r['texto'] ?? ''));
    });
}
prueba('editar sin enviar el trabajador (página de edición) guarda y conserva el trabajador original', function () use ($postEdicion, $c) {
    $r = guardarContrato(['accion' => 'actualizar', 'jefe_inmediato' => 'Jefe De Prueba', 'observaciones' => 'Nota de prueba'] + $postEdicion, true);
    afirmar(($r['respuesta']['mensaje'] ?? '') === 'actualizado', 'No se guardó: ' . json_encode($r['respuesta'], JSON_UNESCAPED_UNICODE));
    afirmar((int)$r['fila_contrato']['id_trabajador'] === (int)$c['id_trabajador'], 'Cambió el trabajador del contrato');
    afirmar($r['fila_contrato']['jefe_inmediato'] === 'Jefe De Prueba' && $r['fila_contrato']['observaciones'] === 'Nota de prueba', 'No aplicó los cambios');
});
prueba('editar enviando el MISMO trabajador se acepta', function () use ($postEdicion, $c) {
    $r = guardarContrato(['accion' => 'actualizar', 'id_trabajador' => (string)$c['id_trabajador']] + $postEdicion, true);
    afirmar(($r['respuesta']['mensaje'] ?? '') === 'actualizado', 'No se guardó: ' . json_encode($r['respuesta'], JSON_UNESCAPED_UNICODE));
});
prueba('renovar con el mismo trabajador funciona y conserva las observaciones (el asistente no las envía)', function () use ($postEdicion, $c) {
    $r = guardarContrato(['accion' => 'renovar', 'id_trabajador' => (string)$c['id_trabajador']] + $postEdicion, true);
    afirmar(($r['respuesta']['mensaje'] ?? '') === 'renovado', 'No se renovó: ' . json_encode($r['respuesta'], JSON_UNESCAPED_UNICODE));
    afirmar((int)$r['fila_contrato']['id_trabajador'] === (int)$c['id_trabajador'], 'Cambió el trabajador');
    afirmar($r['fila_contrato']['observaciones'] === $c['observaciones'], 'Se perdieron las observaciones al renovar');
});
prueba('editar o renovar un contrato inexistente se rechaza', function () use ($postEdicion) {
    foreach (['actualizar', 'renovar'] as $accion) {
        $r = guardarContrato(['accion' => $accion, 'contrato_id' => '999999'] + $postEdicion)['respuesta'];
        afirmar(($r['mensaje'] ?? '') === 'id_invalido', "$accion respondió: " . json_encode($r, JSON_UNESCAPED_UNICODE));
    }
});

echo "\n" . ($total - $fallos) . " de $total pruebas pasaron.\n";
exit($fallos > 0 ? 1 : 0);
