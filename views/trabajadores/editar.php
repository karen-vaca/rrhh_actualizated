<?php
require_once __DIR__ . '/../../config/auth.php';
requerirAcceso();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../config/conexion.php';
require_once __DIR__ . '/funciones_trabajador.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    header('Location: index.php?mensaje=id_invalido');
    exit;
}

function e($valor) {
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

function inicialesPersona($nombres, $apellidos = '') {
    $nombres = trim((string)$nombres);
    $apellidos = trim((string)$apellidos);

    $i1 = $nombres !== '' ? strtoupper(mb_substr($nombres, 0, 1, 'UTF-8')) : '';
    $i2 = $apellidos !== '' ? strtoupper(mb_substr($apellidos, 0, 1, 'UTF-8')) : '';

    if ($i2 === '' && mb_strlen($nombres, 'UTF-8') > 1) {
        $partes = preg_split('/\s+/', $nombres);
        $i2 = isset($partes[1]) ? strtoupper(mb_substr($partes[1], 0, 1, 'UTF-8')) : '';
    }

    return $i1 . $i2;
}

function obtenerCatalogo(PDO $conexion, string $tabla, string $idColumna, array $columnasTexto, $idValor): string {
    if (!$idValor) {
        return 'Sin registrar';
    }

    try {
        $colsStmt = $conexion->query("SHOW COLUMNS FROM `$tabla`");
        $columnas = $colsStmt->fetchAll(PDO::FETCH_COLUMN);

        $columnaTexto = null;

        foreach ($columnasTexto as $col) {
            if (in_array($col, $columnas, true)) {
                $columnaTexto = $col;
                break;
            }
        }

        if (!$columnaTexto) {
            return 'Sin registrar';
        }

        $sql = "SELECT `$columnaTexto` FROM `$tabla` WHERE `$idColumna` = :id LIMIT 1";
        $stmt = $conexion->prepare($sql);
        $stmt->bindValue(':id', (int)$idValor, PDO::PARAM_INT);
        $stmt->execute();

        $valor = $stmt->fetchColumn();

        return $valor ?: 'Sin registrar';

    } catch (Exception $e) {
        return 'Sin registrar';
    }
}

function dato($valor) {
    $valor = trim((string)$valor);
    return $valor !== '' ? $valor : 'Sin registrar';
}

try {
    $sql = "
        SELECT 
            t.*,
            COALESCE(g.nombre, 'Sin definir') AS genero_nombre,
            COALESCE(a.nombre_area, 'Sin área') AS nombre_area,
            COALESCE(c.nombre_cargo, 'Sin cargo') AS nombre_cargo
        FROM trabajadores t
        LEFT JOIN generos g ON t.id_generos = g.id_generos
        LEFT JOIN areas a ON t.id_area = a.id_areas
        LEFT JOIN cargos c ON t.id_cargo = c.id_cargo
        WHERE t.id_trabajador = :id
        LIMIT 1
    ";

    $stmt = $conexion->prepare($sql);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    $trabajador = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$trabajador) {
        header('Location: index.php?mensaje=no_encontrado');
        exit;
    }

} catch (Exception $e) {
    die('Error al consultar trabajador: ' . $e->getMessage());
}

$nombresTrabajador = $trabajador['nombres'] ?? '';
$apellidosTrabajador = $trabajador['apellidos'] ?? '';
$nombreCompleto = trim($nombresTrabajador . ' ' . $apellidosTrabajador);
$inicialTrabajador = inicialesPersona($nombresTrabajador, $apellidosTrabajador);
// Si actualizar.php rechazó el formulario: errores por campo y datos que se habían enviado.
$documentoOriginal = $trabajador['numero_documento'] ?? 'Sin documento';
['errores' => $erroresEdicion, 'old' => $oldEdicion] = tomarErroresFormulario();
if ($oldEdicion) {
    $oldEdicion['celular'] = $oldEdicion['telefono'] ?? '';
    $trabajador = array_merge($trabajador, $oldEdicion);
}
foreach (catalogosTrabajador() as $campo => [$tabla, $idColumna, $nombreColumna]) {
    $trabajador[$campo] = idCanonico($conexion, $tabla, $idColumna, $nombreColumna, $trabajador[$campo] ?? '');
}

$estadoTrabajador = (int)($trabajador['estado'] ?? 1);
$documentoGuardado = $documentoOriginal ?? ($trabajador['numero_documento'] ?? 'Sin documento');

$tipoDocumento = obtenerCatalogo(
    $conexion,
    'tipos_documentos',
    'id_tipos_documentos',
    ['nombre_tipo_documento', 'tipo_documento', 'nombre_documento', 'nombre', 'descripcion'],
    $trabajador['id_tipos_documentos'] ?? null
);

$formacion = obtenerCatalogo(
    $conexion,
    'formacion_educativa',
    'id_formacion_educativa',
    ['nivel_academico', 'formacion_educativa', 'nombre', 'descripcion'],
    $trabajador['id_formacion_educativa'] ?? null
);

$nacionalidad = obtenerCatalogo(
    $conexion,
    'nacionalidad',
    'id_nacionalidad',
    ['nombre_nacionalidad', 'nacionalidad', 'nombre', 'descripcion'],
    $trabajador['id_nacionalidad'] ?? null
);

$tipoSangre = obtenerCatalogo(
    $conexion,
    'tipos_sangre',
    'id_sangre',
    ['tipo_sangre', 'nombre_sangre', 'nombre', 'descripcion'],
    $trabajador['id_sangre'] ?? null
);

$estadoCivil = obtenerCatalogo(
    $conexion,
    'estado_civil',
    'id_estado_civil',
    ['nombre_estado_civil', 'estado_civil', 'nombre', 'descripcion'],
    $trabajador['id_estado_civil'] ?? null
);

$eps = obtenerCatalogo(
    $conexion,
    'eps',
    'id_eps',
    ['nombre_eps', 'eps', 'nombre', 'descripcion'],
    $trabajador['id_eps'] ?? null
);

$grupoEtnico = obtenerCatalogo(
    $conexion,
    'grupos_etnicos',
    'id_grupos_etnicos',
    ['nombre_grupo_etnico', 'grupo_etnico', 'nombre', 'descripcion'],
    $trabajador['id_grupos_etnicos'] ?? null
);

$nombres = $_SESSION['nombres'] ?? $_SESSION['nombre'] ?? 'Usuario';
$apellidos = $_SESSION['apellidos'] ?? '';
$rol_nombre = $_SESSION['rol_nombre'] ?? $_SESSION['rol'] ?? 'RRHH';
$inicial = inicialesPersona($nombres, $apellidos);


function obtenerOpcionesCatalogo(PDO $conexion, string $tabla, string $idColumna, array $columnasTexto, array $fallback = []): array {
    try {
        $colsStmt = $conexion->query("SHOW COLUMNS FROM `$tabla`");
        $columnas = $colsStmt->fetchAll(PDO::FETCH_COLUMN);

        $columnaTexto = null;
        foreach ($columnasTexto as $col) {
            if (in_array($col, $columnas, true)) {
                $columnaTexto = $col;
                break;
            }
        }

        if (!$columnaTexto || !in_array($idColumna, $columnas, true)) {
            return $fallback;
        }

        $sql = "SELECT `$idColumna` AS id, `$columnaTexto` AS nombre FROM `$tabla` ORDER BY `$idColumna` ASC";
        $stmt = $conexion->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $rows ?: $fallback;
    } catch (Exception $e) {
        return $fallback;
    }
}

function selectedOpt($actual, $valor): string {
    return (string)$actual === (string)$valor ? 'selected' : '';
}

function checkedOpt($actual, $valor): string {
    return (string)$actual === (string)$valor ? 'checked' : '';
}

$opcionesForm = opcionesFormularioTrabajador($conexion);
$tiposDocumentos = $opcionesForm['id_tipos_documentos'];
$generos         = $opcionesForm['id_generos'];
$nacionalidades  = $opcionesForm['id_nacionalidad'];
$formaciones     = $opcionesForm['id_formacion_educativa'];
$tiposSangre     = $opcionesForm['id_sangre'];
$estadosCiviles  = $opcionesForm['id_estado_civil'];
$gruposEtnicos   = $opcionesForm['id_grupos_etnicos'];
$epsOpciones     = $opcionesForm['id_eps'];

$areasOpciones = obtenerOpcionesCatalogo(
    $conexion,
    'areas',
    'id_areas',
    ['nombre_area', 'area', 'nombre', 'descripcion'],
    [
        ['id' => 1, 'nombre' => 'Producción'],
        ['id' => 2, 'nombre' => 'Administrativa'],
        ['id' => 3, 'nombre' => 'Logística'],
        ['id' => 4, 'nombre' => 'Mantenimiento'],
        ['id' => 5, 'nombre' => 'SST']
    ]
);

$cargosOpciones = obtenerOpcionesCatalogo(
    $conexion,
    'cargos',
    'id_cargo',
    ['nombre_cargo', 'cargo', 'nombre', 'descripcion']
);
// Obtener los cargos del área seleccionada
$cargosArea = [];

if (!empty($trabajador['id_area'])) {

    $stmt = $conexion->prepare("
        SELECT id_cargo, nombre_cargo
        FROM cargos
        WHERE id_area = ?
        ORDER BY nombre_cargo
    ");

    $stmt->execute([$trabajador['id_area']]);
    $cargosArea = $stmt->fetchAll(PDO::FETCH_ASSOC);

}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Editar trabajador | PlastyPetco</title>
<?php require __DIR__ . '/../components/estilos_base.php'; ?>

<style>

/* layout */

/* ── SIDEBAR ── */
 
 
/* leaf deco sidebar */

/* main */

/* content */
.page-header{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:4px}
.page-header-left{display:flex;align-items:center;gap:16px}
.page-icon{width:52px;height:52px;background:var(--green-mist);border:1px solid rgba(45,223,110,0.2);border-radius:14px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.page-icon svg{width:26px;height:26px;stroke:var(--green-dim);fill:none;stroke-width:1.7}
.page-title{font-family:var(--tx-fuente-titulos);font-size:var(--tx-titulo-pagina);font-weight:var(--tx-peso-titulo-pagina);color:var(--tx-color);letter-spacing:-.4px;line-height:1.1}
.page-sub{font-size:var(--tx-subtitulo);font-weight:var(--tx-peso-normal);color:var(--tx-color-suave);margin-top:3px}
.page-header-right{display:flex;align-items:center;gap:10px;flex-wrap:wrap}

/* buttons */
.btn{height:40px;border-radius:11px;border:1px solid var(--border);display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:0 14px;font-family:'DM Sans',sans-serif;font-size:13px;font-weight:600;text-decoration:none;cursor:pointer;transition:all .18s;background:var(--white);color:var(--text-mid)}
.btn svg{width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:2}
.btn:hover{transform:translateY(-1px);box-shadow:var(--shadow)}
.btn-primary{background:linear-gradient(135deg,var(--green),var(--green-dim));color:#021a08;border-color:transparent;box-shadow:0 6px 18px rgba(45,223,110,.22)}
.btn-danger{background:#fff1f2;color:#dc2626;border-color:#fecaca}
.btn-blue{background:#eff6ff;color:#2563eb;border-color:#bfdbfe}

/* profile view */
.view-grid{display:grid;grid-template-columns:1.25fr .75fr;gap:18px}
.profile-card{background:var(--white);border:1px solid var(--border);border-radius:20px;box-shadow:var(--shadow-md);overflow:hidden}
.profile-hero{position:relative;padding:24px 26px;background:linear-gradient(135deg,#0d5c2e 0%,#0d3d1e 55%,#145c33 100%);min-height:154px;display:flex;align-items:center;justify-content:space-between;gap:20px}
.profile-hero::before{content:'';position:absolute;inset:0;background:radial-gradient(circle at 85% 20%,rgba(45,223,110,.22),transparent 32%);pointer-events:none}
.profile-identity{position:relative;z-index:1;display:flex;align-items:center;gap:18px;min-width:0}
.worker-avatar-lg{width:76px;height:76px;border-radius:22px;background:linear-gradient(135deg,var(--green),var(--green-dim));display:flex;align-items:center;justify-content:center;font-family:'Syne',sans-serif;font-size:24px;font-weight:800;color:#021a08;box-shadow:0 14px 30px rgba(0,0,0,.22),0 0 0 1px rgba(255,255,255,.14) inset;flex-shrink:0}
.worker-main-name{font-family:var(--tx-fuente-titulos);font-size:var(--tx-nombre-encabezado);font-weight:var(--tx-peso-titulo-pagina);color:#fff;letter-spacing:-.4px;line-height:1.05;margin-bottom:8px}
.worker-main-meta{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.meta-pill{border-radius:999px;padding:4px 10px;font-size:var(--tx-insignia);font-weight:var(--tx-peso-medio);background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.16);color:rgba(255,255,255,.86)}
.status-pill{position:relative;z-index:1;display:inline-flex;align-items:center;gap:6px;border-radius:999px;padding:7px 12px;font-size:12px;font-weight:700;white-space:nowrap}
.status-active{background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0}
.status-inactive{background:#f9fafb;color:#6b7280;border:1px solid #e5e7eb}
.status-dot{width:6px;height:6px;border-radius:50%;background:currentColor}
.profile-body{padding:20px 22px 22px}
.quick-row{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:18px}
.quick-box{background:var(--content-bg);border:1px solid var(--border);border-radius:14px;padding:13px}
.quick-label{font-size:var(--tx-etiqueta);font-weight:var(--tx-peso-enfasis);color:var(--tx-color-suave);text-transform:uppercase;letter-spacing:.7px;margin-bottom:5px}
.quick-value{font-size:var(--tx-valor);font-weight:var(--tx-peso-normal);color:var(--tx-color);line-height:1.35}

.info-section{background:var(--white);border:1px solid var(--border);border-radius:18px;box-shadow:var(--shadow);padding:18px}
.info-section + .info-section{margin-top:14px}
.section-head{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:14px;padding-bottom:12px;border-bottom:1px solid var(--border)}
.section-title{font-family:var(--tx-fuente-titulos);font-size:var(--tx-titulo-seccion);font-weight:var(--tx-peso-titulo);color:var(--tx-color);letter-spacing:-.2px}
.section-tag{font-size:11px;font-weight:var(--tx-peso-enfasis);color:var(--green-dim);background:var(--green-mist);border:1px solid rgba(45,223,110,.18);border-radius:999px;padding:4px 9px}
.info-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:10px}
.info-item{background:#fbfdfb;border:1px solid #edf4ef;border-radius:13px;padding:12px}
.info-label{font-size:var(--tx-etiqueta);font-weight:var(--tx-peso-enfasis);color:var(--tx-color-suave);text-transform:uppercase;letter-spacing:.7px;margin-bottom:5px}
.info-value{font-size:var(--tx-valor);font-weight:var(--tx-peso-normal);color:var(--tx-color);line-height:1.35;word-break:break-word}
.side-column{display:flex;flex-direction:column;gap:18px}
.side-card{background:var(--white);border:1px solid var(--border);border-radius:18px;box-shadow:var(--shadow);padding:18px}
.side-card .info-grid{grid-template-columns:1fr}
.record-item{display:flex;align-items:flex-start;gap:11px;padding:10px 0;border-bottom:1px solid var(--border)}
.record-item:last-child{border-bottom:none;padding-bottom:0}
.record-dot{width:10px;height:10px;border-radius:50%;background:var(--green);box-shadow:0 0 0 5px var(--green-mist);margin-top:5px;flex-shrink:0}
.record-title{font-size:var(--tx-valor);font-weight:var(--tx-peso-medio);color:var(--tx-color)}
.record-sub{font-size:var(--tx-ayuda-chica);color:var(--tx-color-suave);margin-top:2px}

/* footer */

/* overlay and responsive */
@media(max-width:1100px){
  .view-grid{grid-template-columns:1fr}
}
@media(max-width:680px){
  .profile-hero{align-items:flex-start;flex-direction:column}
  .quick-row{grid-template-columns:1fr}
  .info-grid{grid-template-columns:1fr}
  .page-title{font-size:var(--tx-titulo-pagina)}
}

/* ── EDITAR TRABAJADOR ── */
.edit-card{background:var(--white);border:1px solid var(--border);border-radius:20px;box-shadow:var(--shadow-md);overflow:hidden}
.edit-hero{position:relative;padding:22px 26px;background:linear-gradient(135deg,#0d5c2e 0%,#0d3d1e 55%,#145c33 100%);display:flex;align-items:center;justify-content:space-between;gap:18px;overflow:hidden}
.edit-hero::before{content:'';position:absolute;inset:0;background:radial-gradient(circle at 85% 20%,rgba(45,223,110,.22),transparent 32%);pointer-events:none}
.edit-identity{position:relative;z-index:1;display:flex;align-items:center;gap:16px;min-width:0}
.edit-avatar{width:68px;height:68px;border-radius:20px;background:linear-gradient(135deg,var(--green),var(--green-dim));display:flex;align-items:center;justify-content:center;font-family:'Syne',sans-serif;font-size:22px;font-weight:800;color:#021a08;box-shadow:0 14px 30px rgba(0,0,0,.22),0 0 0 1px rgba(255,255,255,.14) inset;flex-shrink:0}
.edit-name{font-family:var(--tx-fuente-titulos);font-size:var(--tx-nombre-encabezado);font-weight:var(--tx-peso-titulo-pagina);color:#fff;letter-spacing:-.4px;line-height:1.05;margin-bottom:8px}
.edit-meta{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.edit-pill{border-radius:999px;padding:4px 10px;font-size:var(--tx-insignia);font-weight:var(--tx-peso-medio);background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.16);color:rgba(255,255,255,.86)}
.edit-hint{position:relative;z-index:1;color:rgba(255,255,255,.70);font-size:var(--tx-ayuda-chica);max-width:260px;text-align:right;line-height:1.35}
.edit-body{padding:22px}
.edit-section{border:1px solid var(--border);border-radius:16px;background:#fbfdfc;margin-bottom:16px;overflow:hidden}
.edit-section:last-child{margin-bottom:0}
.edit-section-head{padding:14px 16px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;gap:10px;background:#f7fbf8}
.edit-section-title{font-family:var(--tx-fuente-titulos);font-size:var(--tx-titulo-seccion);font-weight:var(--tx-peso-titulo);color:var(--tx-color);letter-spacing:-.2px}
.edit-section-tag{font-size:var(--tx-etiqueta);font-weight:var(--tx-peso-enfasis);text-transform:uppercase;letter-spacing:.7px;color:var(--green-dim);background:var(--green-mist);border:1px solid rgba(45,223,110,.16);border-radius:999px;padding:4px 9px}
.edit-form-grid{padding:16px;display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
.edit-field{display:flex;flex-direction:column;gap:6px;min-width:0}
.edit-field.full{grid-column:1 / -1}
.edit-label{font-size:var(--tx-etiqueta-form);font-weight:var(--tx-peso-enfasis);color:var(--tx-color-medio);letter-spacing:.3px}
.edit-input,.edit-select{width:100%;height:42px;background:var(--white);border:1px solid var(--border);border-radius:10px;padding:0 13px;font-size:13.5px;color:var(--text);font-family:'DM Sans',sans-serif;outline:none;transition:border-color .2s,box-shadow .2s}
.edit-select{appearance:none;padding-right:34px;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='13' height='13' viewBox='0 0 24 24' fill='none' stroke='%238aab96' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 12px center}
.edit-input:focus,.edit-select:focus{border-color:#b6dfc4;box-shadow:0 0 0 3px rgba(45,223,110,0.07)}
.edit-input::placeholder{color:var(--text-soft)}
.edit-actions{position:sticky;bottom:0;z-index:5;background:rgba(255,255,255,.92);backdrop-filter:blur(12px);border-top:1px solid var(--border);padding:14px 22px;display:flex;justify-content:flex-end;gap:10px;flex-wrap:wrap}
.edit-alert{border-radius:12px;padding:11px 14px;font-size:13px;font-weight:600;margin-bottom:14px;border:1px solid #fecaca;background:#fff1f2;color:#dc2626}
@media(max-width:700px){.edit-hero{flex-direction:column;align-items:flex-start}.edit-hint{text-align:left;max-width:none}.edit-form-grid{grid-template-columns:1fr}.edit-actions .btn{flex:1}}

.edit-section-sub{font-family:var(--tx-fuente-texto);font-size:var(--tx-ayuda);font-weight:var(--tx-peso-normal);color:var(--tx-color-suave);letter-spacing:0;margin-left:4px}
.edit-pill-estado.is-activo{background:rgba(45,223,110,.22);border-color:rgba(45,223,110,.45);color:#fff}
.edit-pill-estado.is-inactivo{background:rgba(248,113,113,.25);border-color:rgba(248,113,113,.5);color:#fff}
.edit-estado-nota{font-size:var(--tx-ayuda-chica);color:rgba(255,255,255,.72)}
</style>
<link rel="stylesheet" href="validacion_trabajador.css">
<link rel="stylesheet" href="../components/select_buscador.css">
<style>
/* Folio: el id interno como dato secundario (referencia para soporte y auditoría) */
.folio{display:inline-block;margin-left:8px;font-size:11.5px;font-weight:500;color:var(--text-soft);letter-spacing:.2px;vertical-align:middle;white-space:nowrap}
.lugar-revisar{display:inline-block;margin-left:6px;font-size:10.5px;font-weight:700;color:#b45309;background:#fffbeb;border:1px solid #fde68a;border-radius:20px;padding:2px 8px;vertical-align:middle}
.nota-campo{font-size:var(--tx-ayuda);font-weight:var(--tx-peso-normal);color:var(--tx-color-suave);line-height:1.45}
.nota-campo strong{color:var(--text-mid)}
</style>
</head>

<body>


<div class="layout">

<?php $paginaActiva = 'trabajadores'; require __DIR__ . '/../components/sidebar.php'; ?>

<div class="main">

  <?php
  $tituloTopbar = 'Trabajadores';
  $busquedaTopbar = ['placeholder' => 'Buscar trabajadores, documentos, reportes...'];
  require __DIR__ . '/../components/topbar.php';
  ?>

  <div class="content">

    <div class="page-header">
      <div class="page-header-left">
        <div class="page-icon">
          <svg viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 013 3L7 19l-4 1 1-4 12.5-12.5z"/></svg>
        </div>
        <div>
          <div class="page-title">Editar Trabajador</div>
          <div class="page-sub">Actualiza la información personal, laboral y de contacto del trabajador.</div>
        </div>
      </div>

      <div class="page-header-right">
        <a href="index.php" class="btn">
          <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
          Volver
        </a>
        <a href="ver.php?id=<?php echo (int)$trabajador['id_trabajador']; ?>" class="btn">
          <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
          Ver ficha
        </a>
      </div>
    </div>

    <?php if ($erroresEdicion): ?>
      <div class="edit-alert">No se guardaron los cambios. Revisa <?php echo count($erroresEdicion) === 1 ? 'el campo marcado' : 'los ' . count($erroresEdicion) . ' campos marcados'; ?>.</div>
    <?php endif; ?>

    <form action="actualizar.php" method="POST" class="edit-card" data-validar-trabajador novalidate><?php echo campoCsrf(); ?>
      <input type="hidden" name="id_trabajador" value="<?php echo (int)$trabajador['id_trabajador']; ?>">

      <div class="edit-hero">
        <div class="edit-identity">
          <div class="edit-avatar"><?php echo e($inicialTrabajador ?: 'TR'); ?></div>
          <div>
            <div class="edit-name"><?php echo e($nombreCompleto ?: 'Sin nombre'); ?><span class="folio" title="Identificador interno del trabajador (referencia para soporte)">Folio #<?php echo str_pad((int)$trabajador['id_trabajador'], 4, '0', STR_PAD_LEFT); ?></span></div>
            <div class="edit-meta">
              <span class="edit-pill" title="Número de documento guardado (se edita en Información personal)"><?php echo e($documentoGuardado); ?></span>
              <span class="edit-pill edit-pill-estado <?php echo $estadoTrabajador === 1 ? 'is-activo' : 'is-inactivo'; ?>"><?php echo $estadoTrabajador === 1 ? 'Activo' : 'Inactivo'; ?></span>
              <span class="edit-estado-nota">Se cambia con <?php echo $estadoTrabajador === 1 ? '"Inactivar"' : '"Reactivar"'; ?> desde la ficha del trabajador, no desde este formulario.</span>
            </div>
          </div>
        </div>
        <div class="edit-hint">Los cambios quedarán guardados en la base de datos y podrás verificarlos en la ficha del trabajador.</div>
      </div>

      <div class="edit-body">

        <section class="edit-section">
          <div class="edit-section-head">
            <div class="edit-section-title">Información personal</div>
            <div class="edit-section-tag">Datos base</div>
          </div>

          <div class="edit-form-grid">
            <div class="edit-field">
              <label class="edit-label">Nombres</label>
              <input class="edit-input<?php echo claseError($erroresEdicion, 'nombres'); ?>" type="text" name="nombres" value="<?php echo e($trabajador['nombres'] ?? ''); ?>" required>
<?php echo mensajeError($erroresEdicion, 'nombres'); ?>
            </div>

            <div class="edit-field">
              <label class="edit-label">Apellidos</label>
              <input class="edit-input<?php echo claseError($erroresEdicion, 'apellidos'); ?>" type="text" name="apellidos" value="<?php echo e($trabajador['apellidos'] ?? ''); ?>" required>
<?php echo mensajeError($erroresEdicion, 'apellidos'); ?>
            </div>

            <div class="edit-field">
              <label class="edit-label">Tipo de documento</label>
              <select class="edit-select<?php echo claseError($erroresEdicion, 'id_tipos_documentos'); ?>" name="id_tipos_documentos" required>
                <?php foreach ($tiposDocumentos as $op): ?>
                  <option value="<?php echo (int)$op['id']; ?>" <?php echo selectedOpt($trabajador['id_tipos_documentos'] ?? '', $op['id']); ?>><?php echo e($op['nombre']); ?></option>
                <?php endforeach; ?>
              </select>
<?php echo mensajeError($erroresEdicion, 'id_tipos_documentos'); ?>
            </div>

            <div class="edit-field">
              <label class="edit-label">Número de documento</label>
              <input class="edit-input<?php echo claseError($erroresEdicion, 'numero_documento'); ?>" type="text" name="numero_documento" value="<?php echo e($trabajador['numero_documento'] ?? ''); ?>" required>
<?php echo mensajeError($erroresEdicion, 'numero_documento'); ?>
            </div>

            <div class="edit-field">
              <label class="edit-label">Género</label>
              <select class="edit-select<?php echo claseError($erroresEdicion, 'id_generos'); ?>" name="id_generos" required>
                <?php foreach ($generos as $op): ?>
                  <option value="<?php echo (int)$op['id']; ?>" <?php echo selectedOpt($trabajador['id_generos'] ?? '', $op['id']); ?>><?php echo e($op['nombre']); ?></option>
                <?php endforeach; ?>
              </select>
<?php echo mensajeError($erroresEdicion, 'id_generos'); ?>
            </div>

            <div class="edit-field">
              <label class="edit-label">Fecha de nacimiento</label>
              <input class="edit-input<?php echo claseError($erroresEdicion, 'fecha_nacimiento'); ?>" type="date" name="fecha_nacimiento" min="<?php echo fechaMinimaNacimiento(); ?>" max="<?php echo fechaMaximaNacimiento(); ?>" required value="<?php echo e($trabajador['fecha_nacimiento'] ?? ''); ?>">
<?php echo mensajeError($erroresEdicion, 'fecha_nacimiento'); ?>
            </div>

            <?php
              // Lugar de nacimiento: departamento + ciudad (DIVIPOLA). Si se reenvía tras un error, se respeta lo elegido.
              echo camposLugarHtml(
                  $conexion,
                  ['departamento' => 'departamento_nacimiento', 'ciudad' => 'ciudad_nacimiento', 'etiqueta' => 'Lugar de nacimiento'],
                  $trabajador['ciudad_nacimiento'] ?? ($trabajador['codigo_ciudad_nacimiento'] ?? null),
                  $trabajador['departamento_nacimiento'] ?? null,
                  $erroresEdicion,
                  ['grupo' => 'edit-field', 'label' => 'edit-label', 'select' => 'edit-select']
              );
            ?>
            <?php if (empty($trabajador['codigo_ciudad_nacimiento']) && trim((string)($trabajador['lugar_nacimiento'] ?? '')) !== ''): ?>
            <div class="edit-field full nota-campo">
              <span class="lugar-revisar">Por revisar</span> Antes se escribió como texto: <strong>"<?php echo e($trabajador['lugar_nacimiento']); ?>"</strong>. Elige el departamento y la ciudad para corregirlo; mientras no lo hagas, ese texto se conserva.
            </div>
            <?php endif; ?>


            <div class="edit-field">
              <label class="edit-label">Nacionalidad</label>
              <select class="edit-select<?php echo claseError($erroresEdicion, 'id_nacionalidad'); ?>" name="id_nacionalidad">
                <?php foreach ($nacionalidades as $op): ?>
                  <option value="<?php echo (int)$op['id']; ?>" <?php echo selectedOpt($trabajador['id_nacionalidad'] ?? '', $op['id']); ?>><?php echo e($op['nombre']); ?></option>
                <?php endforeach; ?>
              </select>
<?php echo mensajeError($erroresEdicion, 'id_nacionalidad'); ?>
            </div>
          </div>
        </section>

        <section class="edit-section">
          <div class="edit-section-head">
            <div class="edit-section-title">Información complementaria</div>
            <div class="edit-section-tag">Perfil</div>
          </div>

          <div class="edit-form-grid">
            <div class="edit-field">
              <label class="edit-label">Formación educativa</label>
              <select class="edit-select<?php echo claseError($erroresEdicion, 'id_formacion_educativa'); ?>" name="id_formacion_educativa">
                <?php foreach ($formaciones as $op): ?>
                  <option value="<?php echo (int)$op['id']; ?>" <?php echo selectedOpt($trabajador['id_formacion_educativa'] ?? '', $op['id']); ?>><?php echo e($op['nombre']); ?></option>
                <?php endforeach; ?>
              </select>
<?php echo mensajeError($erroresEdicion, 'id_formacion_educativa'); ?>
            </div>

            <div class="edit-field">
              <label class="edit-label">Tipo de sangre</label>
              <select class="edit-select<?php echo claseError($erroresEdicion, 'id_sangre'); ?>" name="id_sangre">
                <option value="">Sin información</option>
                <?php foreach ($tiposSangre as $op): ?>
                  <option value="<?php echo (int)$op['id']; ?>" <?php echo selectedOpt($trabajador['id_sangre'] ?? '', $op['id']); ?>><?php echo e($op['nombre']); ?></option>
                <?php endforeach; ?>
              </select>
<?php echo mensajeError($erroresEdicion, 'id_sangre'); ?>
            </div>

            <div class="edit-field">
              <label class="edit-label">Estado civil</label>
              <select class="edit-select<?php echo claseError($erroresEdicion, 'id_estado_civil'); ?>" name="id_estado_civil">
                <?php foreach ($estadosCiviles as $op): ?>
                  <option value="<?php echo (int)$op['id']; ?>" <?php echo selectedOpt($trabajador['id_estado_civil'] ?? '', $op['id']); ?>><?php echo e($op['nombre']); ?></option>
                <?php endforeach; ?>
              </select>
<?php echo mensajeError($erroresEdicion, 'id_estado_civil'); ?>
            </div>

<div class="edit-field">
<label class="edit-label">Grupo étnico</label>
<select class="edit-select<?php echo claseError($erroresEdicion, 'id_grupos_etnicos'); ?>" name="id_grupos_etnicos">
<?php foreach ($gruposEtnicos as $op): ?>
<option value="<?php echo (int)$op['id']; ?>" <?php echo selectedOpt($trabajador['id_grupos_etnicos'] ?? '', $op['id']); ?>><?php echo e($op['nombre']); ?></option>
<?php endforeach; ?>
</select>
<?php echo mensajeError($erroresEdicion, 'id_grupos_etnicos'); ?>
</div>
<div class="edit-field full">
<label class="edit-label">Orientación sexual / Identidad de género</label>
<select class="edit-select<?php echo claseError($erroresEdicion, 'orientacion_sexual'); ?>" name="orientacion_sexual">
<option value="">Seleccionar (opcional)</option>
<?php echo opcionesSelect($opcionesForm['orientacion_sexual'], $trabajador['orientacion_sexual'] ?? ''); ?>
</select>
<?php echo mensajeError($erroresEdicion, 'orientacion_sexual'); ?>
</div>
</div>
</section>

<section class="edit-section">
<div class="edit-section-head">
<div class="edit-section-title">Información familiar</div>
<div class="edit-section-tag">Familia</div>
</div>
<div class="edit-form-grid">
<div class="edit-field">
<label class="edit-label">¿Tiene hijos?</label>
<select class="edit-select<?php echo claseError($erroresEdicion, 'tiene_hijos'); ?>" name="tiene_hijos" id="tieneHijos" onchange="toggleNumeroHijos()">
<option value="0" <?php echo selectedOpt($trabajador['tiene_hijos'] ?? '0', '0'); ?>>No</option>
<option value="1" <?php echo selectedOpt($trabajador['tiene_hijos'] ?? '0', '1'); ?>>Sí</option>
</select>
<?php echo mensajeError($erroresEdicion, 'tiene_hijos'); ?>
</div>
<div class="edit-field" id="grupoNumeroHijos" style="display:<?php echo ($trabajador['tiene_hijos'] ?? '0') === '1' ? 'flex' : 'none'; ?>">
<label class="edit-label">Número de hijos</label>
<input class="edit-input<?php echo claseError($erroresEdicion, 'numero_hijos'); ?>" type="number" name="numero_hijos" id="numeroHijos" min="1" max="15" step="1" inputmode="numeric" value="<?php echo e($trabajador['numero_hijos'] ?? ''); ?>" placeholder="0">
<?php echo mensajeError($erroresEdicion, 'numero_hijos'); ?>
</div>
</div>
</section>

<section class="edit-section">
<div class="edit-section-head">
<div class="edit-section-title">Contacto y datos laborales</div>
<div class="edit-section-tag">RRHH</div>
</div>

          <div class="edit-form-grid">
            <div class="edit-field">
              <label class="edit-label">Correo electrónico</label>
              <input class="edit-input<?php echo claseError($erroresEdicion, 'correo_personal'); ?>" type="email" name="correo_personal" maxlength="100" required value="<?php echo e($trabajador['correo_personal'] ?? ''); ?>" placeholder="correo@empresa.com">
<?php echo mensajeError($erroresEdicion, 'correo_personal'); ?>
            </div>

            <div class="edit-field">
              <label class="edit-label">Teléfono</label>
              <input class="edit-input<?php echo claseError($erroresEdicion, 'telefono'); ?>" type="tel" name="telefono" inputmode="numeric" maxlength="14" required value="<?php echo e($trabajador['celular'] ?? ''); ?>" placeholder="3001234567">
<?php echo mensajeError($erroresEdicion, 'telefono'); ?>
            </div>

<div class="edit-field">
    <label class="edit-label">Área</label>

    <select class="edit-select<?php echo claseError($erroresEdicion, 'id_area'); ?>" id="id_area" name="id_area" required>

        <option value="">Seleccionar área</option>

        <?php
        $stmtAreas = $conexion->query("
            SELECT id_areas, nombre_area
            FROM areas
            ORDER BY nombre_area
        ");

        while($area = $stmtAreas->fetch(PDO::FETCH_ASSOC)):
        ?>

            <option
                value="<?= $area['id_areas']; ?>"
                <?= ($area['id_areas'] == $trabajador['id_area']) ? 'selected' : ''; ?>>
                <?= htmlspecialchars($area['nombre_area']); ?>
            </option>

        <?php endwhile; ?>

    </select>
<?php echo mensajeError($erroresEdicion, 'id_area'); ?>
</div>

 <div class="edit-field">
    <label class="edit-label">Cargo</label>

    <select class="edit-select<?php echo claseError($erroresEdicion, 'id_cargo'); ?>" id="id_cargo" name="id_cargo" required>
        <option value="">Seleccione un cargo</option>

        <?php foreach ($cargosArea as $cargo): ?>

            <option
                value="<?= $cargo['id_cargo']; ?>"
                <?= selectedOpt($trabajador['id_cargo'], $cargo['id_cargo']); ?>>

                <?= e($cargo['nombre_cargo']); ?>

            </option>

        <?php endforeach; ?>

    </select>
<?php echo mensajeError($erroresEdicion, 'id_cargo'); ?>

</div>

            <div class="edit-field">
              <label class="edit-label">EPS</label>
              <select class="edit-select<?php echo claseError($erroresEdicion, 'id_eps'); ?>" name="id_eps" required>
                <option value=""><?php echo empty($trabajador['id_eps']) ? 'Pendiente de verificar — seleccionar EPS' : 'Seleccionar EPS'; ?></option>
                <?php foreach ($epsOpciones as $op): ?>
                  <option value="<?php echo (int)$op['id']; ?>" <?php echo selectedOpt($trabajador['id_eps'] ?? '', $op['id']); ?>><?php echo e($op['nombre']); ?></option>
                <?php endforeach; ?>
              </select>
<?php echo mensajeError($erroresEdicion, 'id_eps'); ?>
            </div>

            <div class="edit-field">
              <label class="edit-label">Fecha de ingreso</label>
              <input class="edit-input<?php echo claseError($erroresEdicion, 'fecha_ingreso'); ?>" type="date" name="fecha_ingreso" max="<?php echo date('Y-m-d'); ?>" required value="<?php echo e($trabajador['fecha_ingreso'] ?? ''); ?>">
<?php echo mensajeError($erroresEdicion, 'fecha_ingreso'); ?>
            </div>
          </div>
        </section>

        <section class="edit-section">
          <div class="edit-section-head">
            <div class="edit-section-title">Dotación (tallas) <span class="edit-section-sub">· Para entrega de EPP y uniforme</span></div>
          </div>
          <div class="edit-form-grid">
<div class="edit-field">
    <label class="edit-label">Talla camisa</label>
    <select class="edit-select<?php echo claseError($erroresEdicion, 'talla_camisa'); ?>" name="talla_camisa">
        <option value="">Seleccionar</option>
        <option value="XS" <?php echo selectedOpt($trabajador['talla_camisa'] ?? '', 'XS'); ?>>XS</option>
        <option value="S" <?php echo selectedOpt($trabajador['talla_camisa'] ?? '', 'S'); ?>>S</option>
        <option value="M" <?php echo selectedOpt($trabajador['talla_camisa'] ?? '', 'M'); ?>>M</option>
        <option value="L" <?php echo selectedOpt($trabajador['talla_camisa'] ?? '', 'L'); ?>>L</option>
        <option value="XL" <?php echo selectedOpt($trabajador['talla_camisa'] ?? '', 'XL'); ?>>XL</option>
        <option value="XXL" <?php echo selectedOpt($trabajador['talla_camisa'] ?? '', 'XXL'); ?>>XXL</option>
        <option value="XXXL" <?php echo selectedOpt($trabajador['talla_camisa'] ?? '', 'XXXL'); ?>>XXXL</option>
    </select>
<?php echo mensajeError($erroresEdicion, 'talla_camisa'); ?>
</div>

<div class="edit-field">
    <label class="edit-label">Talla pantalón</label>
    <input class="edit-input<?php echo claseError($erroresEdicion, 'talla_pantalon'); ?>" type="text" name="talla_pantalon"
           value="<?php echo e($trabajador['talla_pantalon'] ?? ''); ?>"
           placeholder="Ej. 32, 34, M, L"
           maxlength="10">
<?php echo mensajeError($erroresEdicion, 'talla_pantalon'); ?>
</div>

<div class="edit-field">
    <label class="edit-label">Talla botas</label>
    <input class="edit-input<?php echo claseError($erroresEdicion, 'talla_botas'); ?>" type="text" maxlength="10" name="talla_botas"
           value="<?php echo e($trabajador['talla_botas'] ?? ''); ?>"
           placeholder="Ej. 39">
<?php echo mensajeError($erroresEdicion, 'talla_botas'); ?>
</div>

<div class="edit-field">
    <!-- Celda vacía para mantener el grid de 2 columnas -->
</div>
          </div>
        </section>

<section class="edit-section">
    <div class="edit-section-head">
        <div class="edit-section-title">Observaciones</div>
        <div class="edit-section-tag">Notas</div>
    </div>
    <div class="edit-form-grid">
        <div class="edit-field full">
            <label class="edit-label">Observaciones adicionales</label>
            <textarea class="edit-input<?php echo claseError($erroresEdicion, 'observaciones'); ?>" name="observaciones" rows="5" maxlength="2000"
                      placeholder="Escribe aquí cualquier observación relevante sobre el trabajador (antecedentes, recomendaciones, notas de RRHH, etc.)..."
                      style="height:auto; padding:12px 14px; resize:vertical; font-family:'DM Sans',sans-serif; line-height:1.5;"><?php echo e($trabajador['observaciones'] ?? ''); ?></textarea>
<?php echo mensajeError($erroresEdicion, 'observaciones'); ?>
        </div>
    </div>
</section>

      </div>

      <div class="edit-actions">
        <a href="ver.php?id=<?php echo (int)$trabajador['id_trabajador']; ?>" class="btn">Cancelar</a>
        <button type="submit" class="btn btn-primary">
          <svg viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
          Guardar cambios
        </button>
      </div>
    </form>

  </div>

  <footer class="footer-app">
    <strong>PlastyPetco S.A.S</strong> &middot; Sistema de Gestión RRHH + SG-SST &nbsp;&middot;&nbsp; Versión 1.0.0
  </footer>

</div>
</div>

<script>
function toggleSidebar(){
document.getElementById('sidebar').classList.toggle('open');
document.getElementById('sidebarOverlay').classList.toggle('open');
}
function closeSidebar(){
document.getElementById('sidebar').classList.remove('open');
document.getElementById('sidebarOverlay').classList.remove('open');
}
function toggleProfile(){
document.getElementById('profileWrap').classList.toggle('open');
}
document.addEventListener('click',function(e){
var w=document.getElementById('profileWrap');
if(w && !w.contains(e.target)) w.classList.remove('open');
});

function toggleNumeroHijos() {
    const tieneHijos = document.getElementById('tieneHijos');
    const grupoNumeroHijos = document.getElementById('grupoNumeroHijos');
    const numeroHijos = document.getElementById('numeroHijos');
    if (tieneHijos && grupoNumeroHijos && numeroHijos) {
        if (tieneHijos.value === '1') {
            grupoNumeroHijos.style.display = 'flex';
            numeroHijos.disabled = false;
            numeroHijos.required = true;
            if (numeroHijos.value === '0') numeroHijos.value = '';
        } else {
            // "No": el número de hijos es 0 y no se puede editar (el servidor también lo fuerza a 0).
            grupoNumeroHijos.style.display = 'none';
            numeroHijos.value = '0';
            numeroHijos.disabled = true;
            numeroHijos.required = false;
        }
    }
}
// ==========================================
// CARGAR CARGOS SEGÚN EL ÁREA (EDITAR)
// ==========================================
document.addEventListener("DOMContentLoaded", function () {

    const selectArea = document.getElementById("id_area");
    const selectCargo = document.getElementById("id_cargo");

    if (!selectArea || !selectCargo) return;

    // Cargo que ya tiene asignado el trabajador
    const cargoSeleccionado = selectCargo.value;

    function cargarCargos(idArea, cargoActual = null) {

        if (idArea === "") {
            selectCargo.innerHTML =
                '<option value="">Seleccione primero un área</option>';
            return;
        }

        fetch("obtener_cargos.php?id_area=" + idArea)

            .then(response => response.json())

            .then(cargos => {

                selectCargo.innerHTML =
                    '<option value="">Seleccionar cargo</option>';

                cargos.forEach(cargo => {

                    const option = document.createElement("option");

                    option.value = cargo.id_cargo;
                    option.textContent = cargo.nombre_cargo;

                    if (cargoActual && cargo.id_cargo == cargoActual) {
                        option.selected = true;
                    }

                    selectCargo.appendChild(option);

                });

            })

            .catch(error => {

                console.error(error);

                selectCargo.innerHTML =
                    '<option value="">Error al cargar cargos</option>';

            });

    }

    // Al abrir la edición
    cargarCargos(selectArea.value, cargoSeleccionado);

    // Cuando cambia el área
    selectArea.addEventListener("change", function () {
        cargarCargos(this.value);
    });

});

toggleNumeroHijos();
</script>
<?php echo scriptCiudadesPorDepartamento($conexion); ?>
<script src="../components/select_buscador.js"></script>
<script src="../components/lugares.js"></script>
<script src="validacion_trabajador.js"></script>
</body>
</html>
