<?php
require_once __DIR__ . '/../../config/auth.php';
requerirAcceso();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$conexionFile = __DIR__ . '/../../config/conexion.php';

if (!file_exists($conexionFile)) {
    die('Error crítico: no se encontró el archivo de conexión.');
}

require_once $conexionFile;
require_once __DIR__ . '/funciones_trabajador.php';

/* =========================
   FUNCIONES
========================= */

function limpiar($dato) {
    if ($dato === null || $dato === '') {
        return 'Sin registrar';
    }

    return htmlspecialchars((string)$dato, ENT_QUOTES, 'UTF-8');
}

function campo($array, $posiblesCampos) {
    foreach ($posiblesCampos as $campo) {
        if (isset($array[$campo]) && $array[$campo] !== '' && $array[$campo] !== null) {
            return $array[$campo];
        }
    }

    return null;
}

function generoNombre($id_genero) {
    switch ((int)$id_genero) {
        case 1:
            return 'Femenino';
        case 2:
            return 'Masculino';
        case 3:
            return 'Otro';
        default:
            return 'Sin registrar';
    }
}

function estadoLaboral($estado) {
    return ((int)$estado === 1) ? 'Activo' : 'Inactivo';
}

/*
  Esta función busca nombres en tablas catálogo sin romper si una columna cambia.
  Sirve para áreas, cargos, EPS, sangre, estado civil, nacionalidad, etc.
*/
function catalogoNombre($conexion, $tabla, $idValor, $columnasId, $columnasNombre) {
    if ($idValor === null || $idValor === '') {
        return 'Sin registrar';
    }

    if (!preg_match('/^[a-zA-Z0-9_]+$/', $tabla)) {
        return 'Sin registrar';
    }

    static $cacheColumnas = [];

    try {
        if (!isset($cacheColumnas[$tabla])) {
            $cols = [];
            $stmtCols = $conexion->query("SHOW COLUMNS FROM `$tabla`");
            while ($row = $stmtCols->fetch(PDO::FETCH_ASSOC)) {
                $cols[] = $row['Field'];
            }
            $cacheColumnas[$tabla] = $cols;
        }

        $cols = $cacheColumnas[$tabla];

        $colId = null;
        foreach ($columnasId as $c) {
            if (in_array($c, $cols, true)) {
                $colId = $c;
                break;
            }
        }

        $colNombre = null;
        foreach ($columnasNombre as $c) {
            if (in_array($c, $cols, true)) {
                $colNombre = $c;
                break;
            }
        }

        if (!$colId || !$colNombre) {
            return 'Sin registrar';
        }

        $sql = "SELECT `$colNombre` FROM `$tabla` WHERE `$colId` = ? LIMIT 1";
        $stmt = $conexion->prepare($sql);
        $stmt->execute([(int)$idValor]);
        $valor = $stmt->fetchColumn();

        return $valor ?: 'Sin registrar';

    } catch (Exception $e) {
        return 'Sin registrar';
    }
}

/* =========================
   CONSULTA
========================= */

try {
    $sql = "
        SELECT *
        FROM trabajadores
        ORDER BY id_area ASC, apellidos ASC, nombres ASC
    ";

    $stmt = $conexion->prepare($sql);
    $stmt->execute();
    $trabajadores = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    die('Error al consultar trabajadores: ' . $e->getMessage());
}

/* =========================
   ORGANIZAR POR ÁREA
========================= */

$areas = [];

foreach ($trabajadores as $t) {

    $idArea = campo($t, ['id_area']);

    $nombreArea = catalogoNombre(
        $conexion,
        'areas',
        $idArea,
        ['id_areas', 'id_area', 'id'],
        ['nombre_area', 'area', 'nombre']
    );

    if ($nombreArea === 'Sin registrar') {
        $nombreArea = 'Sin área';
    }

    $idCargo = campo($t, ['id_cargo']);

    $cargo = catalogoNombre(
        $conexion,
        'cargos',
        $idCargo,
        ['id_cargo', 'id_cargos', 'id'],
        ['nombre_cargo', 'cargo', 'nombre']
    );

    $tipoDocumento = catalogoNombre(
        $conexion,
        'tipos_documentos',
        campo($t, ['id_tipos_documentos', 'id_tipo_documento']),
        ['id_tipos_documentos', 'id_tipo_documento', 'id'],
        ['tipo_documento', 'nombre_tipo_documento', 'nombre', 'descripcion']
    );

    $nacionalidad = catalogoNombre(
        $conexion,
        'nacionalidad',
        campo($t, ['id_nacionalidad']),
        ['id_nacionalidad', 'id'],
        ['nacionalidad', 'nombre_nacionalidad', 'nombre']
    );

    $estadoCivil = catalogoNombre(
        $conexion,
        'estado_civil',
        campo($t, ['id_estado_civil']),
        ['id_estado_civil', 'id'],
        ['estado_civil', 'nombre_estado_civil', 'nombre']
    );

    $grupoEtnico = catalogoNombre(
        $conexion,
        'grupos_etnicos',
        campo($t, ['id_grupos_etnicos', 'id_grupo_etnico']),
        ['id_grupos_etnicos', 'id_grupo_etnico', 'id'],
        ['grupo_etnico', 'nombre_grupo_etnico', 'nombre']
    );

    $eps = catalogoNombre(
        $conexion,
        'eps',
        campo($t, ['id_eps']),
        ['id_eps', 'id'],
        ['eps', 'nombre_eps', 'nombre']
    );

    $tipoSangre = catalogoNombre(
        $conexion,
        'sangre',
        campo($t, ['id_sangre']),
        ['id_sangre', 'id'],
        ['tipo_sangre', 'sangre', 'nombre']
    );

    $formacion = catalogoNombre(
        $conexion,
        'formacion_educativa',
        campo($t, ['id_formacion_educativa']),
        ['id_formacion_educativa', 'id'],
        ['formacion_educativa', 'nombre_formacion', 'nombre']
    );

    $t['_area_nombre'] = $nombreArea;
    $t['_cargo_nombre'] = $cargo;
    $t['_tipo_documento'] = $tipoDocumento;
    $t['_genero_nombre'] = generoNombre(campo($t, ['id_generos']));
    $t['_nacionalidad'] = $nacionalidad;
    $t['_estado_civil'] = $estadoCivil;
    $t['_grupo_etnico'] = $grupoEtnico;
    $t['_eps'] = $eps;
    $t['_tipo_sangre'] = $tipoSangre;
    $t['_formacion'] = $formacion;
    $t['_estado_laboral'] = estadoLaboral(campo($t, ['estado']));

    $areas[$nombreArea][] = $t;
}

ksort($areas);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<!-- Botones con la misma tipografía del sistema (el documento sigue en Arial para imprimir) -->
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="../../assets/css/botones.css">
<meta charset="UTF-8">
<title>PDF trabajadores por área | PlastyPetco</title>

<style>
    * {
        box-sizing: border-box;
    }

    body {
        margin: 0;
        padding: 28px;
        background: #f2f5f3;
        font-family: Arial, sans-serif;
        color: #06130a;
    }

    .documento {
        max-width: 1200px;
        margin: 0 auto;
        background: #ffffff;
        border-radius: 18px;
        padding: 30px;
        box-shadow: 0 16px 45px rgba(0,0,0,.10);
    }

    .header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 20px;
        border-bottom: 4px solid #22c55e;
        padding-bottom: 18px;
        margin-bottom: 26px;
    }

    .header h1 {
        margin: 0;
        font-size: 30px;
        color: #052e16;
        letter-spacing: -0.5px;
    }

    .header p {
        margin: 6px 0 0;
        color: #4b6654;
        font-size: 14px;
    }

    .fecha {
        text-align: right;
        font-size: 13px;
        color: #4b6654;
        line-height: 1.4;
    }

    .resumen {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
        margin-bottom: 25px;
    }

    .resumen-card {
        background: #ecfdf5;
        color: #166534;
        border: 1px solid #86efac;
        border-radius: 14px;
        padding: 12px 16px;
        font-weight: bold;
        font-size: 14px;
    }

.area-section {
    margin-top: 22px;
    page-break-inside: auto;
}

.area-title {
    background: #052e16;
    color: #ffffff;
    padding: 9px 12px;
    border-radius: 8px 8px 0 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.area-title h2 {
    margin: 0;
    font-size: 15px;
}

.area-title span {
    font-size: 11px;
    background: rgba(34,197,94,.18);
    border: 1px solid rgba(134,239,172,.35);
    padding: 4px 9px;
    border-radius: 999px;
}

.tabla-excel-wrap {
    width: 100%;
    overflow-x: auto;
    border: 1px solid #cfdcd4;
    border-top: none;
    border-radius: 0 0 8px 8px;
    margin-bottom: 18px;
}

.tabla-excel {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
    font-size: 9.2px;
    background: #ffffff;
}

.tabla-excel th {
    background: #eaf7ef;
    color: #0d5c2e;
    border: 1px solid #cfdcd4;
    padding: 5px 4px;
    text-align: left;
    font-weight: 800;
    text-transform: uppercase;
    font-size: 8.3px;
    line-height: 1.15;
}

.tabla-excel td {
    border: 1px solid #dce8df;
    padding: 5px 4px;
    vertical-align: top;
    color: #111827;
    line-height: 1.2;
    word-break: break-word;
}

.tabla-excel tbody tr:nth-child(even) {
    background: #f8faf9;
}

.tabla-excel tbody tr:hover {
    background: #ecfdf5;
}

.col-id {
    width: 38px;
}

.col-nombre {
    width: 110px;
}

.col-doc {
    width: 76px;
}

.col-genero {
    width: 58px;
}

.col-contacto {
    width: 115px;
}

.col-area {
    width: 78px;
}

.col-cargo {
    width: 95px;
}

.col-salud {
    width: 70px;
}

.col-fecha {
    width: 70px;
}

.col-estado {
    width: 58px;
}

.badge-activo,
.badge-inactivo {
    display: inline-block;
    padding: 3px 7px;
    border-radius: 999px;
    font-size: 8.5px;
    font-weight: 800;
    white-space: nowrap;
}

.badge-activo {
    background: #dcfce7;
    color: #166534;
    border: 1px solid #86efac;
}

.badge-inactivo {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fecaca;
}

    .sin-datos {
        text-align: center;
        padding: 45px 20px;
        color: #6b7280;
        font-weight: bold;
    }

    .acciones {
        margin-top: 30px;
        display: flex;
        justify-content: flex-end;
        gap: 10px;
    }



    @media print {
        body {
            background: white;
            padding: 0;
        }

        .documento {
            max-width: 100%;
            box-shadow: none;
            border-radius: 0;
            padding: 0;
        }

        .acciones {
            display: none;
        }

        .area-title,
        .resumen-card,
        .badge-activo,
        .badge-inactivo {
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .area-section {
            page-break-inside: avoid;
        }

.tabla-excel {
    page-break-inside: auto;
}

.tabla-excel tr {
    page-break-inside: avoid;
    page-break-after: auto;
}

.tabla-excel thead {
    display: table-header-group;
}

.tabla-excel th {
    background: #eaf7ef !important;
    color: #0d5c2e !important;
}
        @page {
            size: A4 landscape;
            margin: 10mm;
        }
    }
</style>
</head>

<body>

<div class="documento">

    <div class="header">
        <div>
            <h1>Reporte de trabajadores por área</h1>
            <p>PlastyPetco - Sistema de Gestión RRHH + SG-SST</p>
        </div>

        <div class="fecha">
            Fecha de generación:<br>
            <strong><?= date('d/m/Y h:i A') ?></strong>
        </div>
    </div>

    <div class="resumen">
        <div class="resumen-card">
            Total trabajadores: <?= count($trabajadores) ?>
        </div>

        <div class="resumen-card">
            Total áreas: <?= count($areas) ?>
        </div>
    </div>

    <?php if (!empty($areas)): ?>

        <?php foreach ($areas as $nombreArea => $listaTrabajadores): ?>

            <section class="area-section">

                <div class="area-title">
                    <h2>Área: <?= limpiar($nombreArea) ?></h2>
                    <span><?= count($listaTrabajadores) ?> empleado(s)</span>
                </div>

<div class="tabla-excel-wrap">

    <table class="tabla-excel">
        <thead>
            <tr>
                <th class="col-id">ID</th>
                <th class="col-nombre">Trabajador</th>
                <th class="col-doc">Tipo doc.</th>
                <th class="col-doc">Documento</th>
                <th class="col-genero">Género</th>
                <th class="col-fecha">Nacimiento</th>
                <th class="col-area">Nacionalidad</th>
                <th class="col-area">Estado civil</th>
                <th class="col-contacto">Contacto</th>
                <th class="col-area">Área</th>
                <th class="col-cargo">Cargo</th>
                <th class="col-cargo">Formación</th>
                <th class="col-salud">EPS</th>
                <th class="col-salud">Sangre</th>
                <th class="col-fecha">Ingreso</th>
                <th class="col-estado">Estado</th>
            </tr>
        </thead>

        <tbody>
            <?php foreach ($listaTrabajadores as $t): ?>

                <?php
                    $idTrabajador = campo($t, ['id_trabajador']);
                    $idInterno = '#' . str_pad((string)$idTrabajador, 4, '0', STR_PAD_LEFT);

                    $nombreCompleto = trim(
                        (campo($t, ['nombres']) ?? '') . ' ' . 
                        (campo($t, ['apellidos']) ?? '')
                    );

                    $telefono = campo($t, ['celular', 'telefono']);
                    $correo = campo($t, ['correo_personal', 'correo']);
                    $fechaNacimiento = campo($t, ['fecha_nacimiento']);
                    $fechaIngreso = campo($t, ['fecha_ingreso']);
                ?>

                <tr>
                    <td><?= limpiar($idInterno) ?></td>

                    <td>
                        <strong><?= limpiar($nombreCompleto) ?></strong>
                    </td>

                    <td><?= limpiar($t['_tipo_documento']) ?></td>

                    <td><?= limpiar(campo($t, ['numero_documento'])) ?></td>

                    <td><?= limpiar($t['_genero_nombre']) ?></td>

                    <td><?= limpiar(formatoFecha($fechaNacimiento)) ?></td>

                    <td><?= limpiar($t['_nacionalidad']) ?></td>

                    <td><?= limpiar($t['_estado_civil']) ?></td>

                    <td>
                        <?= limpiar($correo) ?><br>
                        <?= limpiar($telefono) ?>
                    </td>

                    <td><?= limpiar($t['_area_nombre']) ?></td>

                    <td><?= limpiar($t['_cargo_nombre']) ?></td>

                    <td><?= limpiar($t['_formacion']) ?></td>

                    <td><?= limpiar($t['_eps']) ?></td>

                    <td><?= limpiar($t['_tipo_sangre']) ?></td>

                    <td><?= limpiar(formatoFecha($fechaIngreso)) ?></td>

                    <td>
                        <?php if ($t['_estado_laboral'] === 'Activo'): ?>
                            <span class="badge-activo">Activo</span>
                        <?php else: ?>
                            <span class="badge-inactivo">Inactivo</span>
                        <?php endif; ?>
                    </td>
                </tr>

            <?php endforeach; ?>
        </tbody>
    </table>

</div>

</section>
        <?php endforeach; ?>

    <?php else: ?>

        <div class="sin-datos">
            No hay trabajadores registrados para generar el PDF.
        </div>

    <?php endif; ?>

    <div class="acciones">
        <a href="index.php" class="btn btn-outline">Volver</a>
        <button type="button" onclick="window.print()" class="btn btn-primary">Guardar como PDF</button>
    </div>

</div>

<script>
    window.onload = function () {
        setTimeout(function () {
            window.print();
        }, 700);
    };
</script>

</body>
</html>