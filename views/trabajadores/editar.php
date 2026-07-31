<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../config/conexion.php';

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
$estadoTrabajador = (int)($trabajador['estado'] ?? 1);

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

$nombres = $_SESSION['nombres'] ?? $_SESSION['nombre'] ?? 'Karen Paola';
$apellidos = $_SESSION['apellidos'] ?? 'Vaca Franco';
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

$tiposDocumentos = obtenerOpcionesCatalogo($conexion, 'tipos_documentos', 'id_tipos_documentos', ['nombre_tipo_documento', 'tipo_documento', 'nombre_documento', 'nombre', 'descripcion'], [
    ['id' => 1, 'nombre' => 'Cédula de ciudadanía'],
    ['id' => 2, 'nombre' => 'Cédula de extranjería'],
    ['id' => 3, 'nombre' => 'Pasaporte']
]);

$generos = obtenerOpcionesCatalogo($conexion, 'generos', 'id_generos', ['nombre', 'genero', 'nombre_genero', 'descripcion'], [
    ['id' => 1, 'nombre' => 'Femenino'],
    ['id' => 2, 'nombre' => 'Masculino'],
    ['id' => 3, 'nombre' => 'Otro']
]);

$nacionalidades = obtenerOpcionesCatalogo($conexion, 'nacionalidad', 'id_nacionalidad', ['nombre_nacionalidad', 'nacionalidad', 'nombre', 'descripcion'], [
    ['id' => 1, 'nombre' => 'Colombiana'],
    ['id' => 2, 'nombre' => 'Venezolano'],
    ['id' => 3, 'nombre' => 'Otra']
]);

$formaciones = obtenerOpcionesCatalogo($conexion, 'formacion_educativa', 'id_formacion_educativa', ['nivel_academico', 'formacion_educativa', 'nombre', 'descripcion'], [
    ['id' => 1, 'nombre' => 'Primaria'],
    ['id' => 2, 'nombre' => 'Bachiller'],
    ['id' => 3, 'nombre' => 'Técnico'],
    ['id' => 4, 'nombre' => 'Tecnólogo'],
    ['id' => 5, 'nombre' => 'Profesional'],
    ['id' => 6, 'nombre' => 'Posgrado']
]);

$tiposSangre = obtenerOpcionesCatalogo($conexion, 'tipos_sangre', 'id_sangre', ['tipo_sangre', 'nombre_sangre', 'nombre', 'descripcion'], [
    ['id' => 1, 'nombre' => 'O+'], ['id' => 2, 'nombre' => 'O-'], ['id' => 3, 'nombre' => 'A+'], ['id' => 4, 'nombre' => 'A-'],
    ['id' => 5, 'nombre' => 'B+'], ['id' => 6, 'nombre' => 'B-'], ['id' => 7, 'nombre' => 'AB+'], ['id' => 8, 'nombre' => 'AB-']
]);

$estadosCiviles = obtenerOpcionesCatalogo($conexion, 'estado_civil', 'id_estado_civil', ['nombre_estado_civil', 'estado_civil', 'nombre', 'descripcion'], [
    ['id' => 1, 'nombre' => 'Soltero(a)'],
    ['id' => 2, 'nombre' => 'Casado(a)'],
    ['id' => 3, 'nombre' => 'Unión libre']
]);

$gruposEtnicos = obtenerOpcionesCatalogo($conexion, 'grupos_etnicos', 'id_grupos_etnicos', ['nombre_grupo_etnico', 'grupo_etnico', 'nombre', 'descripcion'], [
    ['id' => 1, 'nombre' => 'No aplica'],
    ['id' => 2, 'nombre' => 'Indígena'],
    ['id' => 3, 'nombre' => 'Afrocolombiano'],
    ['id' => 4, 'nombre' => 'Raizal'],
    ['id' => 5, 'nombre' => 'Palenquero'],
    ['id' => 6, 'nombre' => 'ROM / Gitano']
]);

$epsOpciones = obtenerOpcionesCatalogo($conexion, 'eps', 'id_eps', ['nombre_eps', 'eps', 'nombre', 'descripcion'], [
    ['id' => 1, 'nombre' => 'Nueva EPS'],
    ['id' => 2, 'nombre' => 'Sanitas'],
    ['id' => 3, 'nombre' => 'Sura'],
    ['id' => 4, 'nombre' => 'Compensar'],
    ['id' => 5, 'nombre' => 'Famisanar']
]);

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
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>

<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --green:#2ddf6e;--green-dim:#1a9945;--green-dark:#0d5c2e;
  --sidebar-bg:#050e07;--sidebar-w:220px;--topbar-h:64px;
  --content-bg:#f2f5f3;--white:#ffffff;
  --text:#0d1f11;--text-mid:#4a6655;--text-soft:#8aab96;
  --border:#e0ebe4;--border-dark:rgba(45,223,110,0.18);
  --green-mist:rgba(45,223,110,0.08);
  --shadow:0 2px 12px rgba(0,0,0,0.07);
  --shadow-md:0 6px 24px rgba(0,0,0,0.09);
}
html,body{height:100%;overflow-x:hidden}
body{font-family:'DM Sans',sans-serif;background:var(--content-bg);color:var(--text)}

/* layout */
.layout{display:flex;min-height:100vh}

/* ── SIDEBAR ── */
.sidebar{
  width:var(--sidebar-w);background:var(--sidebar-bg);
  display:flex;flex-direction:column;
  position:fixed;top:0;left:0;height:100vh;z-index:100;
  transition:transform .3s cubic-bezier(.22,1,.36,1);
  border-right:1px solid rgba(45,223,110,0.1);
}
.sidebar-head{padding:20px 18px 16px;border-bottom:1px solid rgba(45,223,110,0.1);display:flex;align-items:center;gap:10px}
.sidebar-logo{width:36px;height:36px;flex-shrink:0}
.sidebar-logo img{width:100%;height:100%;object-fit:contain;mix-blend-mode:screen}
.sidebar-brand{font-family:'Syne',sans-serif;font-size:16px;font-weight:800;color:#fff;letter-spacing:-.3px;line-height:1}
.sidebar-brand em{font-style:normal;color:var(--green)}
.sidebar-tag{font-size:10px;color:rgba(45,223,110,0.5);margin-top:3px}
 
.sidebar-nav{flex:1;padding:12px 10px;display:flex;flex-direction:column;gap:2px;overflow-y:auto}
.nav-section{font-size:9.5px;letter-spacing:1.4px;text-transform:uppercase;color:rgba(45,223,110,0.35);padding:12px 8px 5px;font-weight:700}
.nav-item{display:flex;align-items:center;gap:10px;padding:9px 10px;border-radius:10px;color:rgba(255,255,255,0.45);font-size:13px;text-decoration:none;transition:all .18s;position:relative}
.nav-item svg{width:16px;height:16px;stroke:currentColor;fill:none;stroke-width:1.8;flex-shrink:0}
.nav-item:hover{background:rgba(45,223,110,0.08);color:rgba(255,255,255,0.85)}
.nav-item.active{background:rgba(45,223,110,0.14);color:var(--green);font-weight:500}
.nav-item.active::before{content:'';position:absolute;left:0;top:22%;height:56%;width:3px;border-radius:2px;background:var(--green);box-shadow:0 0 8px var(--green)}
.sidebar-foot{padding:12px 10px;border-top:1px solid rgba(45,223,110,0.08)}
.nav-logout{display:flex;align-items:center;gap:10px;padding:9px 10px;border-radius:10px;color:#f87171;font-size:13px;text-decoration:none;transition:background .18s}
.nav-logout svg{width:16px;height:16px;stroke:currentColor;fill:none;stroke-width:1.8}
.nav-logout:hover{background:rgba(248,113,113,0.08)}
 
/* leaf deco sidebar */
.sidebar-leaf{position:absolute;bottom:60px;right:-8px;opacity:.18;pointer-events:none}

/* main */
.main{margin-left:var(--sidebar-w);min-height:100vh;display:flex;flex-direction:column;width:calc(100% - var(--sidebar-w))}
.topbar{height:var(--topbar-h);background:rgba(255,255,255,0.92);backdrop-filter:blur(12px);border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;padding:0 28px;position:sticky;top:0;z-index:50}
.topbar-left{display:flex;align-items:center;gap:14px}
.menu-toggle{display:none;width:36px;height:36px;border:1px solid var(--border);border-radius:10px;background:var(--white);align-items:center;justify-content:center;color:var(--text-mid);cursor:pointer}
.menu-toggle svg{width:18px;height:18px;stroke:currentColor;fill:none;stroke-width:2}
.topbar-title{font-family:'Syne',sans-serif;font-size:24px;font-weight:800;color:var(--text);letter-spacing:-.5px}

.search-bar{width:min(360px,34vw);height:40px;background:var(--content-bg);border:1px solid var(--border);border-radius:12px;display:flex;align-items:center;gap:9px;padding:0 10px}
.search-bar svg{width:16px;height:16px;stroke:var(--text-soft);fill:none;stroke-width:1.8}
.search-bar input{border:none;background:transparent;outline:none;width:100%;font-family:'DM Sans',sans-serif;font-size:13px;color:var(--text-mid)}
.search-kbd{font-size:10.5px;color:var(--text-soft);border:1px solid var(--border);border-radius:5px;padding:2px 5px;background:var(--white)}

.topbar-right{display:flex;align-items:center;gap:12px}
.notif-btn{position:relative;width:36px;height:36px;border:1px solid var(--border);border-radius:50%;background:var(--white);display:flex;align-items:center;justify-content:center;color:var(--text-mid);cursor:pointer}
.notif-btn svg{width:17px;height:17px;stroke:currentColor;fill:none;stroke-width:1.8}
.notif-badge{position:absolute;top:-4px;right:-2px;background:var(--green);color:#021a08;border-radius:50%;font-size:9px;font-weight:800;width:16px;height:16px;display:flex;align-items:center;justify-content:center}

.profile-wrap{position:relative}
.profile-btn{height:44px;border:1px solid var(--border);background:var(--white);border-radius:22px;display:flex;align-items:center;gap:10px;padding:4px 12px 4px 5px;cursor:pointer;box-shadow:0 1px 2px rgba(0,0,0,.02)}
.profile-avatar{width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,var(--green),var(--green-dim));display:flex;align-items:center;justify-content:center;font-family:'Syne',sans-serif;font-size:14px;font-weight:800;color:#021a08;flex-shrink:0;box-shadow:0 0 10px rgba(45,223,110,0.3)}
.profile-info{display:flex;flex-direction:column;text-align:left;line-height:1.2}
.profile-name{font-size:13px;font-weight:600;color:var(--text)}
.profile-role{font-size:11px;color:var(--green-dim)}
.profile-chevron{width:15px;height:15px;color:var(--text-soft);transition:transform .25s;flex-shrink:0}
.profile-wrap.open .profile-chevron{transform:rotate(180deg)}
.profile-dropdown{position:absolute;top:calc(100% + 8px);right:0;width:230px;background:var(--white);border:1px solid var(--border);border-radius:16px;overflow:hidden;box-shadow:var(--shadow-md);display:none;animation:ddIn .2s cubic-bezier(.22,1,.36,1) both;z-index:200}
.profile-wrap.open .profile-dropdown{display:block}
@keyframes ddIn{from{opacity:0;transform:translateY(-6px) scale(.97)}to{opacity:1;transform:none}}
.profile-dropdown-head{padding:14px;display:flex;align-items:center;gap:10px;border-bottom:1px solid var(--border);background:var(--content-bg)}
.profile-avatar-lg{width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,var(--green),var(--green-dim));display:flex;align-items:center;justify-content:center;font-family:'Syne',sans-serif;font-size:16px;font-weight:800;color:#021a08;flex-shrink:0}
.profile-dropdown-body{padding:6px}
.profile-dd-item{display:flex;align-items:center;gap:9px;padding:9px 11px;border-radius:9px;font-size:13px;color:var(--text-mid);text-decoration:none;transition:background .15s,color .15s}
.profile-dd-item svg{width:14px;height:14px;flex-shrink:0;stroke:currentColor;fill:none;stroke-width:1.8}
.profile-dd-item:hover{background:var(--green-mist);color:var(--green-dark)}
.profile-dd-sep{height:1px;background:var(--border);margin:5px 0}
.profile-dd-logout{color:#dc2626 !important}
.profile-dd-logout:hover{background:#fff1f2 !important}

/* content */
.content{flex:1;padding:24px 28px;display:flex;flex-direction:column;gap:20px}
.page-header{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:4px}
.page-header-left{display:flex;align-items:center;gap:16px}
.page-icon{width:52px;height:52px;background:var(--green-mist);border:1px solid rgba(45,223,110,0.2);border-radius:14px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.page-icon svg{width:26px;height:26px;stroke:var(--green-dim);fill:none;stroke-width:1.7}
.page-title{font-family:'Syne',sans-serif;font-size:32px;font-weight:800;letter-spacing:-1px;color:var(--text);line-height:1}
.page-sub{font-size:13px;color:var(--text-soft);margin-top:7px}
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
.worker-main-name{font-family:'Syne',sans-serif;font-size:26px;font-weight:800;color:#fff;letter-spacing:-.7px;line-height:1.05;margin-bottom:8px}
.worker-main-meta{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.meta-pill{border-radius:999px;padding:4px 10px;font-size:11.5px;font-weight:600;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.16);color:rgba(255,255,255,.86)}
.status-pill{position:relative;z-index:1;display:inline-flex;align-items:center;gap:6px;border-radius:999px;padding:7px 12px;font-size:12px;font-weight:700;white-space:nowrap}
.status-active{background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0}
.status-inactive{background:#f9fafb;color:#6b7280;border:1px solid #e5e7eb}
.status-dot{width:6px;height:6px;border-radius:50%;background:currentColor}
.profile-body{padding:20px 22px 22px}
.quick-row{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:18px}
.quick-box{background:var(--content-bg);border:1px solid var(--border);border-radius:14px;padding:13px}
.quick-label{font-size:10.5px;color:var(--text-soft);font-weight:700;text-transform:uppercase;letter-spacing:.7px;margin-bottom:5px}
.quick-value{font-size:14px;font-weight:700;color:var(--text);line-height:1.25}

.info-section{background:var(--white);border:1px solid var(--border);border-radius:18px;box-shadow:var(--shadow);padding:18px}
.info-section + .info-section{margin-top:14px}
.section-head{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:14px;padding-bottom:12px;border-bottom:1px solid var(--border)}
.section-title{font-family:'Syne',sans-serif;font-size:16px;font-weight:800;color:var(--text);letter-spacing:-.3px}
.section-tag{font-size:11px;font-weight:700;color:var(--green-dim);background:var(--green-mist);border:1px solid rgba(45,223,110,.18);border-radius:999px;padding:4px 9px}
.info-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:10px}
.info-item{background:#fbfdfb;border:1px solid #edf4ef;border-radius:13px;padding:12px}
.info-label{font-size:10.5px;color:var(--text-soft);font-weight:700;text-transform:uppercase;letter-spacing:.7px;margin-bottom:5px}
.info-value{font-size:13.5px;color:var(--text);font-weight:600;line-height:1.35;word-break:break-word}
.side-column{display:flex;flex-direction:column;gap:18px}
.side-card{background:var(--white);border:1px solid var(--border);border-radius:18px;box-shadow:var(--shadow);padding:18px}
.side-card .info-grid{grid-template-columns:1fr}
.record-item{display:flex;align-items:flex-start;gap:11px;padding:10px 0;border-bottom:1px solid var(--border)}
.record-item:last-child{border-bottom:none;padding-bottom:0}
.record-dot{width:10px;height:10px;border-radius:50%;background:var(--green);box-shadow:0 0 0 5px var(--green-mist);margin-top:5px;flex-shrink:0}
.record-title{font-size:13px;font-weight:700;color:var(--text)}
.record-sub{font-size:12px;color:var(--text-soft);margin-top:2px}

/* footer */
.footer-app{text-align:center;padding:12px;font-size:11.5px;color:var(--text-soft);border-top:1px solid var(--border);background:var(--white)}
.footer-app strong{color:var(--green-dark)}

/* overlay and responsive */
.sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:99;backdrop-filter:blur(3px)}
@media(max-width:1100px){
  .view-grid{grid-template-columns:1fr}
}
@media(max-width:900px){
  .sidebar{transform:translateX(-100%)}
  .sidebar.open{transform:translateX(0)}
  .sidebar-overlay.open{display:block}
  .main{margin-left:0;width:100%}
  .menu-toggle{display:flex}
  .topbar{padding:0 16px}
  .content{padding:16px;gap:14px}
  .search-bar{width:160px}
  .search-kbd{display:none}
}
@media(max-width:680px){
  .profile-hero{align-items:flex-start;flex-direction:column}
  .quick-row{grid-template-columns:1fr}
  .info-grid{grid-template-columns:1fr}
  .page-title{font-size:26px}
  .profile-info{display:none}
  .profile-btn{padding:4px}
}


/* ── EDITAR TRABAJADOR ── */
.edit-card{background:var(--white);border:1px solid var(--border);border-radius:20px;box-shadow:var(--shadow-md);overflow:hidden}
.edit-hero{position:relative;padding:22px 26px;background:linear-gradient(135deg,#0d5c2e 0%,#0d3d1e 55%,#145c33 100%);display:flex;align-items:center;justify-content:space-between;gap:18px;overflow:hidden}
.edit-hero::before{content:'';position:absolute;inset:0;background:radial-gradient(circle at 85% 20%,rgba(45,223,110,.22),transparent 32%);pointer-events:none}
.edit-identity{position:relative;z-index:1;display:flex;align-items:center;gap:16px;min-width:0}
.edit-avatar{width:68px;height:68px;border-radius:20px;background:linear-gradient(135deg,var(--green),var(--green-dim));display:flex;align-items:center;justify-content:center;font-family:'Syne',sans-serif;font-size:22px;font-weight:800;color:#021a08;box-shadow:0 14px 30px rgba(0,0,0,.22),0 0 0 1px rgba(255,255,255,.14) inset;flex-shrink:0}
.edit-name{font-family:'Syne',sans-serif;font-size:24px;font-weight:800;color:#fff;letter-spacing:-.6px;line-height:1.05;margin-bottom:8px}
.edit-meta{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.edit-pill{border-radius:999px;padding:4px 10px;font-size:11.5px;font-weight:600;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.16);color:rgba(255,255,255,.86)}
.edit-hint{position:relative;z-index:1;color:rgba(255,255,255,.70);font-size:12px;max-width:260px;text-align:right;line-height:1.35}
.edit-body{padding:22px}
.edit-section{border:1px solid var(--border);border-radius:16px;background:#fbfdfc;margin-bottom:16px;overflow:hidden}
.edit-section:last-child{margin-bottom:0}
.edit-section-head{padding:14px 16px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;gap:10px;background:#f7fbf8}
.edit-section-title{font-family:'Syne',sans-serif;font-size:14px;font-weight:800;color:var(--text);letter-spacing:-.2px}
.edit-section-tag{font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.7px;color:var(--green-dim);background:var(--green-mist);border:1px solid rgba(45,223,110,.16);border-radius:999px;padding:4px 9px}
.edit-form-grid{padding:16px;display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
.edit-field{display:flex;flex-direction:column;gap:6px;min-width:0}
.edit-field.full{grid-column:1 / -1}
.edit-label{font-size:11.5px;font-weight:700;color:var(--text-mid);letter-spacing:.2px}
.edit-input,.edit-select{width:100%;height:42px;background:var(--white);border:1px solid var(--border);border-radius:10px;padding:0 13px;font-size:13.5px;color:var(--text);font-family:'DM Sans',sans-serif;outline:none;transition:border-color .2s,box-shadow .2s}
.edit-select{appearance:none;padding-right:34px;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='13' height='13' viewBox='0 0 24 24' fill='none' stroke='%238aab96' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 12px center}
.edit-input:focus,.edit-select:focus{border-color:#b6dfc4;box-shadow:0 0 0 3px rgba(45,223,110,0.07)}
.edit-input::placeholder{color:var(--text-soft)}
.edit-actions{position:sticky;bottom:0;z-index:5;background:rgba(255,255,255,.92);backdrop-filter:blur(12px);border-top:1px solid var(--border);padding:14px 22px;display:flex;justify-content:flex-end;gap:10px;flex-wrap:wrap}
.edit-alert{border-radius:12px;padding:11px 14px;font-size:13px;font-weight:600;margin-bottom:14px;border:1px solid #fecaca;background:#fff1f2;color:#dc2626}
@media(max-width:700px){.edit-hero{flex-direction:column;align-items:flex-start}.edit-hint{text-align:left;max-width:none}.edit-form-grid{grid-template-columns:1fr}.edit-actions .btn{flex:1}}

</style>
</head>

<body>

<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<div class="layout">

<aside class="sidebar" id="sidebar">
  <div class="sidebar-head">
    <div class="sidebar-logo"><img src="data:image/png;base64,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" alt="PlastyPetco"/></div>
    <div>
      <div class="sidebar-brand">Plasty<em>Petco</em></div>
      <div class="sidebar-tag"></div> 
    </div>
  </div>
  <nav class="sidebar-nav">
    <div class="nav-section">Principal</div>
    <a href="../dashboard/dashboard.php" class="nav-item">
      <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/></svg>Resumen
    </a>
    <div class="nav-section">Gestión</div>
    <a href="index.php" class="nav-item active">
      <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>Trabajadores
    </a>
<a href="index.php" class="nav-item">
        <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>Contratación
    </a>
    <a href="../novedades/index.php" class="nav-item">
      <svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>Novedades
    </a>
    <a href="../vacaciones/index.php" class="nav-item">
      <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>Vacaciones
    </a>
    <div class="nav-section">SG-SST</div>
    <a href="../perfil_salud/index.php" class="nav-item">
      <svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>Perfil de Salud
    </a>
    <a href="../examenes/index.php" class="nav-item">
      <svg viewBox="0 0 24 24"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>Exámenes Médicos
    </a>
    <a href="../incidentes/index.php" class="nav-item">
      <svg viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>Incidentes
    </a>
    <a href="../capacitaciones/index.php" class="nav-item">
      <svg viewBox="0 0 24 24"><path d="M2 3h6a4 4 0 014 4v14a3 3 0 00-3-3H2z"/><path d="M22 3h-6a4 4 0 00-4 4v14a3 3 0 013-3h7z"/></svg>Capacitaciones
    </a>
    <div class="nav-section">Reportes</div>
    <a href="../reportes/index.php" class="nav-item">
      <svg viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>Reportes
    </a>
    <a href="../indicadores/index.php" class="nav-item">
      <svg viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>Indicadores
    </a>
    <div class="nav-section">Configuración</div>
    <a href="../usuarios/index.php" class="nav-item">
      <svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M20 21a8 8 0 10-16 0"/></svg>Usuarios
    </a>
    <a href="../roles/index.php" class="nav-item">
      <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>Roles y Permisos
    </a>
  </nav>
  <div class="sidebar-foot">
    <a href="../../logout.php" class="nav-logout">
      <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>Cerrar sesión
    </a>
  </div>
</aside>

<div class="main">

  <header class="topbar">
    <div class="topbar-left">
      <button class="menu-toggle" onclick="toggleSidebar()">
        <svg viewBox="0 0 24 24"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
      </button>
      <span class="topbar-title">Trabajadores</span>
    </div>

    <div class="search-bar">
      <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input type="text" placeholder="Buscar trabajadores, documentos, reportes..."/>
      <span class="search-kbd">Ctrl+K</span>
    </div>

    <div class="topbar-right">
      <button class="notif-btn">
        <svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>
        <span class="notif-badge">3</span>
      </button>

      <div class="profile-wrap" id="profileWrap">
        <button class="profile-btn" onclick="toggleProfile()">
          <div class="profile-avatar"><?php echo e($inicial ?: 'AD'); ?></div>
          <div class="profile-info">
            <span class="profile-name"><?php echo e(trim($nombres . ' ' . $apellidos)); ?></span>
            <span class="profile-role"><?php echo e($rol_nombre); ?></span>
          </div>
          <svg class="profile-chevron" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
        </button>

        <div class="profile-dropdown" id="profileDropdown">
          <div class="profile-dropdown-head">
            <div class="profile-avatar-lg"><?php echo e($inicial ?: 'AD'); ?></div>
            <div>
              <div style="font-size:13px;font-weight:600;color:var(--text)"><?php echo e(trim($nombres . ' ' . $apellidos)); ?></div>
              <div style="font-size:11px;color:var(--green-dim);margin-top:2px"><?php echo e($rol_nombre); ?></div>
            </div>
          </div>
          <div class="profile-dropdown-body">
            <a href="#" class="profile-dd-item"><svg viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 013 3L7 19l-4 1 1-4 12.5-12.5z"/></svg>Editar perfil</a>
            <a href="#" class="profile-dd-item"><svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>Cambiar contraseña</a>
            <a href="#" class="profile-dd-item"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 01-2.83 2.83l-.06-.06A1.65 1.65 0 0015 19.4a1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.6 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.6a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>Configuración</a>
            <div class="profile-dd-sep"></div>
            <a href="../../logout.php" class="profile-dd-item profile-dd-logout"><svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>Cerrar sesión</a>
          </div>
        </div>
      </div>
    </div>
  </header>

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

    <?php if (isset($_GET['mensaje']) && $_GET['mensaje'] === 'doc_duplicado'): ?>
      <div class="edit-alert">El número de documento ya existe en otro trabajador. Verifica el dato antes de guardar.</div>
    <?php endif; ?>

    <form action="actualizar.php" method="POST" class="edit-card">
      <input type="hidden" name="id_trabajador" value="<?php echo (int)$trabajador['id_trabajador']; ?>">

      <div class="edit-hero">
        <div class="edit-identity">
          <div class="edit-avatar"><?php echo e($inicialTrabajador ?: 'TR'); ?></div>
          <div>
            <div class="edit-name"><?php echo e($nombreCompleto ?: 'Sin nombre'); ?></div>
            <div class="edit-meta">
              <span class="edit-pill">ID #<?php echo str_pad((int)$trabajador['id_trabajador'], 4, '0', STR_PAD_LEFT); ?></span>
              <span class="edit-pill"><?php echo e($trabajador['numero_documento'] ?? 'Sin documento'); ?></span>
              <span class="edit-pill"><?php echo $estadoTrabajador === 1 ? 'Activo' : 'Inactivo'; ?></span>
            </div>
          </div>
        </div>
        <div class="edit-hint">Los cambios quedarán guardados en la base de datos y podrás verificarlos en la ficha del trabajador.</div>
      </div>
      <!-- ═══════════════════════════════════════════════ -->
<!-- OBSERVACIONES                                    -->
<!-- ═══════════════════════════════════════════════ -->
<section class="edit-section">
    <div class="edit-section-head">
        <div class="edit-section-title">Observaciones</div>
        <div class="edit-section-tag">Notas</div>
    </div>
    <div class="edit-form-grid">
        <div class="edit-field full">
            <label class="edit-label">Observaciones adicionales</label>
            <textarea class="edit-input" name="observaciones" rows="5"
                      placeholder="Escribe aquí cualquier observación relevante sobre el trabajador (antecedentes, recomendaciones, notas de RRHH, etc.)..."
                      style="height:auto; padding:12px 14px; resize:vertical; font-family:'DM Sans',sans-serif; line-height:1.5;"><?php echo e($trabajador['observaciones'] ?? ''); ?></textarea>
        </div>
    </div>
</section>

      <div class="edit-body">

        <section class="edit-section">
          <div class="edit-section-head">
            <div class="edit-section-title">Información personal</div>
            <div class="edit-section-tag">Datos base</div>
          </div>

          <div class="edit-form-grid">
            <div class="edit-field">
              <label class="edit-label">Nombres</label>
              <input class="edit-input" type="text" name="nombres" value="<?php echo e($trabajador['nombres'] ?? ''); ?>" required>
            </div>

            <div class="edit-field">
              <label class="edit-label">Apellidos</label>
              <input class="edit-input" type="text" name="apellidos" value="<?php echo e($trabajador['apellidos'] ?? ''); ?>" required>
            </div>

            <div class="edit-field">
              <label class="edit-label">Tipo de documento</label>
              <select class="edit-select" name="id_tipos_documentos" required>
                <?php foreach ($tiposDocumentos as $op): ?>
                  <option value="<?php echo (int)$op['id']; ?>" <?php echo selectedOpt($trabajador['id_tipos_documentos'] ?? '', $op['id']); ?>><?php echo e($op['nombre']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="edit-field">
              <label class="edit-label">Número de documento</label>
              <input class="edit-input" type="text" name="numero_documento" value="<?php echo e($trabajador['numero_documento'] ?? ''); ?>" required>
            </div>

            <div class="edit-field">
              <label class="edit-label">Género</label>
              <select class="edit-select" name="id_generos" required>
                <?php foreach ($generos as $op): ?>
                  <option value="<?php echo (int)$op['id']; ?>" <?php echo selectedOpt($trabajador['id_generos'] ?? '', $op['id']); ?>><?php echo e($op['nombre']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="edit-field">
              <label class="edit-label">Fecha de nacimiento</label>
              <input class="edit-input" type="date" name="fecha_nacimiento" value="<?php echo e($trabajador['fecha_nacimiento'] ?? ''); ?>">
            </div>

            <div class="edit-field">
              <label class="edit-label">Lugar de nacimiento</label>
              <input class="edit-input" type="text" name="lugar_nacimiento" value="<?php echo e($trabajador['lugar_nacimiento'] ?? ''); ?>" placeholder="Ej. Tunja, Boyacá">
            </div>


            <div class="edit-field">
              <label class="edit-label">Nacionalidad</label>
              <select class="edit-select" name="id_nacionalidad">
                <?php foreach ($nacionalidades as $op): ?>
                  <option value="<?php echo (int)$op['id']; ?>" <?php echo selectedOpt($trabajador['id_nacionalidad'] ?? '', $op['id']); ?>><?php echo e($op['nombre']); ?></option>
                <?php endforeach; ?>
              </select>
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
              <select class="edit-select" name="id_formacion_educativa">
                <?php foreach ($formaciones as $op): ?>
                  <option value="<?php echo (int)$op['id']; ?>" <?php echo selectedOpt($trabajador['id_formacion_educativa'] ?? '', $op['id']); ?>><?php echo e($op['nombre']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="edit-field">
              <label class="edit-label">Tipo de sangre</label>
              <select class="edit-select" name="id_sangre">
                <?php foreach ($tiposSangre as $op): ?>
                  <option value="<?php echo (int)$op['id']; ?>" <?php echo selectedOpt($trabajador['id_sangre'] ?? '', $op['id']); ?>><?php echo e($op['nombre']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="edit-field">
              <label class="edit-label">Estado civil</label>
              <select class="edit-select" name="id_estado_civil">
                <?php foreach ($estadosCiviles as $op): ?>
                  <option value="<?php echo (int)$op['id']; ?>" <?php echo selectedOpt($trabajador['id_estado_civil'] ?? '', $op['id']); ?>><?php echo e($op['nombre']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>

<div class="edit-field">
<label class="edit-label">Grupo étnico</label>
<select class="edit-select" name="id_grupos_etnicos">
<?php foreach ($gruposEtnicos as $op): ?>
<option value="<?php echo (int)$op['id']; ?>" <?php echo selectedOpt($trabajador['id_grupos_etnicos'] ?? '', $op['id']); ?>><?php echo e($op['nombre']); ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="edit-field full">
<label class="edit-label">Orientación sexual / Identidad de género</label>
<select class="edit-select" name="orientacion_sexual">
<option value="">Seleccionar (opcional)</option>
<option value="heterosexual" <?php echo selectedOpt($trabajador['orientacion_sexual'] ?? '', 'heterosexual'); ?>>Heterosexual</option>
<option value="homosexual" <?php echo selectedOpt($trabajador['orientacion_sexual'] ?? '', 'homosexual'); ?>>Homosexual</option>
<option value="bisexual" <?php echo selectedOpt($trabajador['orientacion_sexual'] ?? '', 'bisexual'); ?>>Bisexual</option>
<option value="pansexual" <?php echo selectedOpt($trabajador['orientacion_sexual'] ?? '', 'pansexual'); ?>>Pansexual</option>
<option value="asexual" <?php echo selectedOpt($trabajador['orientacion_sexual'] ?? '', 'asexual'); ?>>Asexual</option>
<option value="otra" <?php echo selectedOpt($trabajador['orientacion_sexual'] ?? '', 'otra'); ?>>Otra</option>
<option value="no_especificar" <?php echo selectedOpt($trabajador['orientacion_sexual'] ?? '', 'no_especificar'); ?>>Prefiero no especificar</option>
</select>
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
<select class="edit-select" name="tiene_hijos" id="tieneHijos" onchange="toggleNumeroHijos()">
<option value="0" <?php echo selectedOpt($trabajador['tiene_hijos'] ?? '0', '0'); ?>>No</option>
<option value="1" <?php echo selectedOpt($trabajador['tiene_hijos'] ?? '0', '1'); ?>>Sí</option>
</select>
</div>
<div class="edit-field" id="grupoNumeroHijos" style="display:<?php echo ($trabajador['tiene_hijos'] ?? '0') === '1' ? 'flex' : 'none'; ?>">
<label class="edit-label">Número de hijos</label>
<input class="edit-input" type="number" name="numero_hijos" id="numeroHijos" min="0" max="20" value="<?php echo e($trabajador['numero_hijos'] ?? ''); ?>" placeholder="0">
</div>
<div class="edit-field full">
<div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;padding:14px 16px;display:flex;align-items:flex-start;gap:12px">
<svg viewBox="0 0 24 24" style="width:20px;height:20px;stroke:#16a34a;fill:none;stroke-width:1.8;flex-shrink:0;margin-top:2px">
<circle cx="12" cy="12" r="10"/>
<line x1="12" y1="16" x2="12" y2="12"/>
<line x1="12" y1="8" x2="12.01" y2="8"/>
</svg>
<div style="font-size:13px;color:#166534;line-height:1.5">
Los detalles de familiares (hijos, cónyuge, padres u otros dependientes) podrán registrarlos después desde la ficha del trabajador.
</div>
</div>
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
              <input class="edit-input" type="email" name="correo_personal" value="<?php echo e($trabajador['correo_personal'] ?? ''); ?>" placeholder="correo@empresa.com">
            </div>

            <div class="edit-field">
              <label class="edit-label">Teléfono</label>
              <input class="edit-input" type="tel" name="telefono" value="<?php echo e($trabajador['celular'] ?? ''); ?>" placeholder="300 000 0000">
            </div>

<div class="edit-field">
    <label class="edit-label">Área</label>

    <select class="edit-select" id="id_area" name="id_area" required>

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
</div>

 <div class="edit-field">
    <label class="edit-label">Cargo</label>

    <select class="edit-select" id="id_cargo" name="id_cargo">
        <option value="">Seleccione un cargo</option>

        <?php foreach ($cargosArea as $cargo): ?>

            <option
                value="<?= $cargo['id_cargo']; ?>"
                <?= selectedOpt($trabajador['id_cargo'], $cargo['id_cargo']); ?>>

                <?= e($cargo['nombre_cargo']); ?>

            </option>

        <?php endforeach; ?>

    </select>

</div>

            <div class="edit-field">
              <label class="edit-label">EPS</label>
              <select class="edit-select" name="id_eps">
                <?php foreach ($epsOpciones as $op): ?>
                  <option value="<?php echo (int)$op['id']; ?>" <?php echo selectedOpt($trabajador['id_eps'] ?? '', $op['id']); ?>><?php echo e($op['nombre']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="edit-field">
              <label class="edit-label">Estado laboral</label>
              <select class="edit-select" name="estado">
                <option value="1" <?php echo selectedOpt($estadoTrabajador, 1); ?>>Activo</option>
                <option value="0" <?php echo selectedOpt($estadoTrabajador, 0); ?>>Inactivo</option>
              </select>
            </div>
            <!-- ══════════════════════════════════════════════ -->
<!-- DOTACIÓN (TALLAS) - Sub-sección visual           -->
<!-- ═══════════════════════════════════════════════ -->
<div class="edit-field full" style="margin-top:8px; padding-top:16px; border-top:1px dashed var(--border);">
    <div style="font-size:11px; font-weight:700; color:var(--text-soft); text-transform:uppercase; letter-spacing:.7px; margin-bottom:12px;">
        Dotación (tallas)
        <span style="font-weight:400; text-transform:none; letter-spacing:0; color:var(--text-soft); margin-left:6px;">
            · Para entrega de EPP y uniforme
        </span>
    </div>
</div>

<div class="edit-field">
    <label class="edit-label">Talla camisa</label>
    <select class="edit-select" name="talla_camisa">
        <option value="">Seleccionar</option>
        <option value="XS" <?php echo selectedOpt($trabajador['talla_camisa'] ?? '', 'XS'); ?>>XS</option>
        <option value="S" <?php echo selectedOpt($trabajador['talla_camisa'] ?? '', 'S'); ?>>S</option>
        <option value="M" <?php echo selectedOpt($trabajador['talla_camisa'] ?? '', 'M'); ?>>M</option>
        <option value="L" <?php echo selectedOpt($trabajador['talla_camisa'] ?? '', 'L'); ?>>L</option>
        <option value="XL" <?php echo selectedOpt($trabajador['talla_camisa'] ?? '', 'XL'); ?>>XL</option>
        <option value="XXL" <?php echo selectedOpt($trabajador['talla_camisa'] ?? '', 'XXL'); ?>>XXL</option>
        <option value="XXXL" <?php echo selectedOpt($trabajador['talla_camisa'] ?? '', 'XXXL'); ?>>XXXL</option>
    </select>
</div>

<div class="edit-field">
    <label class="edit-label">Talla pantalón</label>
    <input class="edit-input" type="text" name="talla_pantalon"
           value="<?php echo e($trabajador['talla_pantalon'] ?? ''); ?>"
           placeholder="Ej. 32, 34, M, L"
           maxlength="10">
</div>

<div class="edit-field">
    <label class="edit-label">Talla botas</label>
    <input class="edit-input" type="number" name="talla_botas"
           value="<?php echo e($trabajador['talla_botas'] ?? ''); ?>"
           placeholder="Ej. 39"
           min="30" max="50" step="1">
</div>

<div class="edit-field">
    <!-- Celda vacía para mantener el grid de 2 columnas -->
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
            numeroHijos.required = true;
        } else {
            grupoNumeroHijos.style.display = 'none';
            numeroHijos.value = '';
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
</body>
</html>
