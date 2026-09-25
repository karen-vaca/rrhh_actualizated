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

function limpiar($dato) {
    return htmlspecialchars($dato ?? 'No registrado', ENT_QUOTES, 'UTF-8');
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

try {
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

} catch (Exception $e) {
    die('Error al consultar trabajadores: ' . $e->getMessage());
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
    * {
        box-sizing: border-box;
    }

    body {
        margin: 0;
        padding: 30px;
        background: #f2f5f3;
        font-family: Arial, sans-serif;
        color: #111827;
    }

    .documento {
        max-width: 1150px;
        margin: 0 auto;
        background: #ffffff;
        padding: 30px;
        border-radius: 18px;
        box-shadow: 0 12px 35px rgba(0,0,0,.10);
    }

    .encabezado {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
        border-bottom: 4px solid #16a34a;
        padding-bottom: 18px;
        margin-bottom: 24px;
    }

    .encabezado h1 {
        margin: 0;
        color: #166534;
        font-size: 28px;
    }

    .encabezado p {
        margin: 6px 0 0;
        color: #4b5563;
        font-size: 14px;
    }

    .fecha {
        font-size: 13px;
        color: #4b5563;
        text-align: right;
    }

    .resumen {
        display: flex;
        gap: 12px;
        margin-bottom: 22px;
        flex-wrap: wrap;
    }

    .resumen-card {
        border: 1px solid #bbf7d0;
        background: #ecfdf5;
        color: #166534;
        padding: 12px 16px;
        border-radius: 12px;
        font-size: 14px;
        font-weight: bold;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        font-size: 12.5px;
    }

    thead {
        background: #052e16;
        color: #ffffff;
    }

    th {
        padding: 11px 10px;
        text-align: left;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: .5px;
    }

    td {
        padding: 10px;
        border-bottom: 1px solid #e5e7eb;
        vertical-align: middle;
    }

    tbody tr:nth-child(even) {
        background: #f9fafb;
    }

    .trabajador {
        font-weight: bold;
        color: #111827;
    }

    .documento-id {
        color: #4b5563;
        font-size: 12px;
    }

    .contacto {
        line-height: 1.35;
    }

    .estado-activo {
        display: inline-block;
        padding: 5px 10px;
        border-radius: 999px;
        background: #dcfce7;
        color: #166534;
        font-weight: bold;
        border: 1px solid #86efac;
        font-size: 11.5px;
    }

    .estado-inactivo {
        display: inline-block;
        padding: 5px 10px;
        border-radius: 999px;
        background: #fee2e2;
        color: #991b1b;
        font-weight: bold;
        border: 1px solid #fecaca;
        font-size: 11.5px;
    }

    .acciones {
        margin-top: 26px;
        display: flex;
        justify-content: flex-end;
        gap: 10px;
    }



    .sin-datos {
        text-align: center;
        padding: 30px;
        color: #6b7280;
        font-weight: bold;
    }

    @media print {
        body {
            background: #ffffff;
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

        @page {
            size: A4 landscape;
            margin: 12mm;
        }

        thead {
            background: #052e16 !important;
            color: #ffffff !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .estado-activo,
        .estado-inactivo,
        .resumen-card {
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
    }
</style>
</head>

<body>

<div class="documento">

    <div class="encabezado">
        <div>
            <h1>Listado de trabajadores</h1>
            <p>PlastyPetco - Sistema de Gestión RRHH + SG-SST</p>
        </div>

        <div class="fecha">
            Fecha de impresión:<br>
            <strong><?= date('d/m/Y h:i A') ?></strong>
        </div>
    </div>

    <div class="resumen">
        <div class="resumen-card">
            Total trabajadores: <?= count($trabajadores) ?>
        </div>
    </div>

    <?php if (!empty($trabajadores)): ?>

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
                        <td>
                            <div class="documento-id">
                                <?= limpiar($t['numero_documento']) ?>
                            </div>
                        </td>

                        <td>
                            <div class="trabajador">
                                <?= limpiar($t['nombres'] . ' ' . $t['apellidos']) ?>
                            </div>
                        </td>

                        <td>
                            <?= limpiar(generoNombre($t['id_generos'])) ?>
                        </td>

                        <td>
                            <div class="contacto">
                                <?= limpiar($t['correo_personal']) ?><br>
                                <?= limpiar($t['telefono']) ?>
                            </div>
                        </td>

                        <td>
                            <?= limpiar($t['nombre_area']) ?>
                        </td>

                        <td>
                            <?= limpiar($t['nombre_cargo']) ?>
                        </td>

                        <td>
                            <?= limpiar(formatoFecha($t['fecha_ingreso']) ?: null) ?>
                        </td>

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

    <?php else: ?>

        <div class="sin-datos">
            No hay trabajadores registrados para imprimir.
        </div>

    <?php endif; ?>

    <div class="acciones">
        <a href="index.php" class="btn btn-outline">Volver</a>
        <button type="button" onclick="window.print()" class="btn btn-primary">Imprimir</button>
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