<?php
require_once __DIR__ . '/../../config/auth.php';
requerirAcceso();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$conexionFile = __DIR__ . '/../../config/conexion.php';
if (!file_exists($conexionFile)) {
    die('Error crítico: no se encontró el archivo de conexión en ' . htmlspecialchars($conexionFile));
}
require_once $conexionFile;
require_once __DIR__ . '/funciones_trabajador.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit();
}

try {
    // =========================
    // VALIDACIÓN (ver funciones_trabajador.php)
    // =========================
    ['datos' => $d, 'errores' => $errores] = validarTrabajador($conexion, $_POST);

    if ($errores) {
        guardarErroresFormulario($errores, $_POST);
        header('Location: index.php?abrir=nuevo');
        exit();
    }

    // =========================
    // INSERTAR TRABAJADOR
    // =========================
    $sql = "INSERT INTO trabajadores (
        id_tipos_documentos,
        numero_documento,
        nombres,
        apellidos,
        fecha_nacimiento,
        lugar_nacimiento,
        correo_personal,
        celular,
        id_generos,
        id_area,
        id_cargo,
        estado,
        fecha_ingreso,
        id_formacion_educativa,
        id_nacionalidad,
        id_sangre,
        id_estado_civil,
        id_eps,
        id_grupos_etnicos,
        tiene_hijos,
        numero_hijos,
        tiene_personas_cargo,
        observaciones_familiares,
        orientacion_sexual,
        talla_camisa,
        talla_pantalon,
        talla_botas,
        observaciones
    ) VALUES (
        :tipo_documento,
        :num,
        :nom,
        :ape,
        :fec,
        :lug,
        :correo,
        :telefono,
        :gen,
        :area,
        :cargo,
        :estado,
        :fecha_ingreso,
        :formacion,
        :nacionalidad,
        :sangre,
        :estado_civil,
        :eps,
        :grupo,
        :tiene_hijos,
        :numero_hijos,
        :tiene_personas_cargo,
        :observaciones_familiares,
        :orientacion_sexual,
        :talla_camisa,
        :talla_pantalon,
        :talla_botas,
        :observaciones
    )";

    $stmt = $conexion->prepare($sql);
    $stmt->execute([
        ':tipo_documento'           => $d['id_tipos_documentos'],
        ':num'                      => $d['numero_documento'],
        ':nom'                      => $d['nombres'],
        ':ape'                      => $d['apellidos'],
        ':fec'                      => $d['fecha_nacimiento'],
        ':lug'                      => $d['lugar_nacimiento'],
        ':correo'                   => $d['correo_personal'],
        ':telefono'                 => $d['celular'],
        ':gen'                      => $d['id_generos'],
        ':area'                     => $d['id_area'],
        ':cargo'                    => $d['id_cargo'],
        ':estado'                   => $d['estado'],
        ':fecha_ingreso'            => $d['fecha_ingreso'],
        ':formacion'                => $d['id_formacion_educativa'],
        ':nacionalidad'             => $d['id_nacionalidad'],
        ':sangre'                   => $d['id_sangre'],
        ':estado_civil'             => $d['id_estado_civil'],
        ':eps'                      => $d['id_eps'],
        ':grupo'                    => $d['id_grupos_etnicos'],
        ':tiene_hijos'              => $d['tiene_hijos'],
        ':numero_hijos'             => $d['numero_hijos'],
        ':tiene_personas_cargo'     => $d['tiene_personas_cargo'],
        ':observaciones_familiares' => $d['observaciones_familiares'],
        ':orientacion_sexual'       => $d['orientacion_sexual'],
        ':talla_camisa'             => $d['talla_camisa'],
        ':talla_pantalon'           => $d['talla_pantalon'],
        ':talla_botas'              => $d['talla_botas'],
        ':observaciones'            => $d['observaciones'],
    ]);

    header("Location: index.php?mensaje=exito");
    exit();
} catch (Exception $e) {
    die("Error al guardar: " . $e->getMessage());
}
