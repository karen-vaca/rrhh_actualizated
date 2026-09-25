<?php
// Avisos (toast) generados desde PHP. Lo carga views/components/estilos_base.php, así que
// está disponible en todas las pantallas internas. El aviso lo muestra
// assets/js/notificaciones.js al cargar la página (se cierra solo a los 6 s o con la X).
//
// Uso, después de guardar (p. ej. con ?mensaje=renovado en la URL):
//   echo avisoAlCargar('ok', 'Contrato renovado correctamente.');
//   echo avisoAlCargar('ok', 'Trabajador creado correctamente.', ['texto' => 'Ir a Contratación', 'href' => '../contratacion/index.php']);
// Tipos: ok, error, aviso, info. El texto se muestra como texto (nunca como HTML).

if (!function_exists('avisoAlCargar')) {
    function avisoAlCargar(string $tipo, string $texto, ?array $accion = null, ?string $titulo = null, ?int $duracion = null): string
    {
        $datos = ['tipo' => $tipo, 'texto' => $texto];
        if ($titulo !== null) {
            $datos['titulo'] = $titulo;
        }
        if ($accion !== null) {
            $datos['accion'] = ['texto' => (string)$accion['texto'], 'href' => (string)$accion['href']];
        }
        if ($duracion !== null) {
            $datos['duracion'] = $duracion;
        }
        $json = json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        return '<script type="application/json" data-aviso>' . $json . "</script>\n";
    }
}
