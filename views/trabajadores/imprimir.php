<?php
require_once __DIR__ . '/../../config/auth.php';
requerirAcceso();
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$conexionFile = __DIR__ . '/../../config/conexion.php';

if (!file_exists($conexionFile)) {
    die('Error: no se encontró conexión.');
}

require_once $conexionFile;
require_once __DIR__ . '/funciones_trabajador.php';

$sql = "
    SELECT
        t.id_trabajador,
        t.numero_documento,
        t.nombres,
        t.apellidos,
        t.correo_personal,
        t.celular AS telefono,
        t.fecha_ingreso,
        t.estado,
        t.id_generos,
        COALESCE(a.nombre_area, 'Sin área') AS nombre_area,
        COALESCE(c.nombre_cargo, 'Sin cargo') AS nombre_cargo
    FROM trabajadores t
    LEFT JOIN areas a ON t.id_area = a.id_areas
    LEFT JOIN cargos c ON t.id_cargo = c.id_cargo
    ORDER BY t.id_trabajador DESC
";

$stmt = $conexion->prepare($sql);
$stmt->execute();
$trabajadores = $stmt->fetchAll(PDO::FETCH_ASSOC);

function limpiar($dato) {
    return htmlspecialchars($dato ?? '', ENT_QUOTES, 'UTF-8');
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
            return 'Sin definir';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<!-- Botones con la misma tipografía del sistema (el documento sigue en Arial para imprimir) -->
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="../../assets/css/botones.css">
<meta charset="UTF-8">
<title>Imprimir trabajadores | PlastyPetco</title>

<style>
    body {
        font-family: Arial, sans-serif;
        background: #f3f6f4;
        color: #111827;
        margin: 0;
        padding: 30px;
    }

    .documento {
        background: #ffffff;
        max-width: 1100px;
        margin: auto;
        padding: 30px;
        border-radius: 14px;
        box-shadow: 0 10px 30px rgba(0,0,0,.08);
    }

    .header {
        border-bottom: 3px solid #16a34a;
        padding-bottom: 16px;
        margin-bottom: 22px;
    }

    .header h1 {
        margin: 0;
        color: #166534;
        font-size: 26px;
    }

    .header p {
        margin: 6px 0 0;
        color: #4b5563;
        font-size: 14px;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
    }

    thead {
        background: #ecfdf5;
    }

    th, td {
        border: 1px solid #d1d5db;
        padding: 9px 10px;
        text-align: left;
    }

    th {
        color: #166534;
        font-weight: bold;
    }

    .estado-activo {
        color: #16a34a;
        font-weight: bold;
    }

    .estado-inactivo {
        color: #dc2626;
        font-weight: bold;
    }

    .acciones {
        margin-top: 22px;
        text-align: right;
    }



    @media print {
        body {
            background: white;
            padding: 0;
        }

        .documento {
            box-shadow: none;
            border-radius: 0;
            max-width: 100%;
        }

        .acciones {
            display: none;
        }

        @page {
            size: A4 landscape;
            margin: 12mm;
        }
    }
</style>
</head>

<body>

<div class="documento">

    <div class="header">
        <h1>Listado de trabajadores</h1>
        <p>PlastyPetco - Sistema de Gestión RRHH + SG-SST</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Documento</th>
                <th>Trabajador</th>
                <th>Género</th>
                <th>Contacto</th>
                <th>Área</th>
                <th>Cargo</th>
                <th>Ingreso</th>
                <th>Estado</th>
            </tr>
        </thead>

        <tbody>
            <?php foreach ($trabajadores as $t): ?>
                <tr>
                    <td><?= limpiar($t['numero_documento']) ?></td>
                    <td><?= limpiar($t['nombres'] . ' ' . $t['apellidos']) ?></td>
                    <td><?= generoNombre($t['id_generos']) ?></td>
                    <td>
                        <?= limpiar($t['correo_personal']) ?><br>
                        <?= limpiar($t['telefono']) ?>
                    </td>
                    <td><?= limpiar($t['nombre_area']) ?></td>
                    <td><?= limpiar($t['nombre_cargo']) ?></td>
                    <td><?= limpiar(formatoFecha($t['fecha_ingreso'])) ?></td>
                    <td>
                        <?php if ((int)$t['estado'] === 1): ?>
                            <span class="estado-activo">Activo</span>
                        <?php else: ?>
                            <span class="estado-inactivo">Inactivo</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="acciones">
        <a href="index.php" class="btn btn-outline">Volver</a>
        <button onclick="window.print()" class="btn btn-primary">Imprimir</button>
    </div>

</div>

<script>
    window.onload = function () {
        setTimeout(function () {
            window.print();
        }, 500);
    };
</script>

</body>
</html>