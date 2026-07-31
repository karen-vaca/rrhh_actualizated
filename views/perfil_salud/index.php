<?php
/**
 * ============================================================================
 *  MÓDULO: PERFIL DE SALUD (SG-SST)
 *  views/perfil_salud/index.php
 * ----------------------------------------------------------------------------
 *  Arquitectura: MPA tradicional (múltiples páginas), SIN enrutador central.
 *  Este archivo es autosuficiente: incluye su propia conexión, su propio
 *  sidebar (con rutas relativas ../carpeta/index.php) y su propia lógica.
 *
 *  Estados internos del módulo (no son "rutas" de la app, son sub-vistas
 *  de ESTE archivo, igual patrón que usas en trabajadores/index.php con
 *  ?mensaje=...):
 *    - index.php                     -> Listado de perfiles de salud
 *    - index.php?vista=detalle&id=N  -> Detalle del trabajador N
 *
 *  Patrón de interfaz: Listado (§23.2) + Detalle (§23.6) del Design System
 *  PlastyPetco RRHH v2.0. Componentes reutilizados: Page Header (§14.5),
 *  Mini Stats (§16.1), Filters Bar (§15.6), Tabla (§15), Panel (§16.2),
 *  Badges (§18), Modal (§17.1), Confirm Card (§17.2), Toast (§19.1),
 *  Timeline (§21.2, roadmap).
 * ============================================================================
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ----------------------------------------------------------------------
 * 1. CONEXIÓN — misma convención que usas en trabajadores/index.php
 *    Ajusta la profundidad de '../../' si tu estructura real difiere.
 * ------------------------------------------------------------------- */
$conexionFile = __DIR__ . '/../../config/conexion.php';
if (!file_exists($conexionFile)) {
    die('Error crítico: no se encontró el archivo de conexión en ' . htmlspecialchars($conexionFile));
}
require_once $conexionFile;

/* ----------------------------------------------------------------------
 * 2. DATOS DEL USUARIO EN TOPBAR (igual patrón que trabajadores/index.php)
 * ------------------------------------------------------------------- */
$nombres    = $_SESSION['nombres'] ?? $_SESSION['nombre'] ?? 'Administrador';
$apellidos  = $_SESSION['apellidos'] ?? '';
$rol_nombre = $_SESSION['rol_nombre'] ?? $_SESSION['rol'] ?? 'RRHH';
$inicial    = strtoupper(substr($nombres, 0, 1) . substr($apellidos !== '' ? $apellidos : $nombres, 0, 1));

/* ----------------------------------------------------------------------
 * 3. FUNCIONES VISUALES — con guardia function_exists() por si en el
 *    futuro las centralizas en includes/functions.php (§29.4)
 * ------------------------------------------------------------------- */
if (!function_exists('avInit')) {
    function avInit($nombres, $apellidos) {
        $n = trim($nombres ?? ''); $a = trim($apellidos ?? '');
        return ($n !== '' ? strtoupper(substr($n, 0, 1)) : '') . ($a !== '' ? strtoupper(substr($a, 0, 1)) : '');
    }
}
if (!function_exists('avatarColor')) {
    function avatarColor($texto) {
        $colores = [
            'linear-gradient(135deg,#22c55e,#15803d)', 'linear-gradient(135deg,#3b82f6,#1d4ed8)',
            'linear-gradient(135deg,#8b5cf6,#6d28d9)', 'linear-gradient(135deg,#f59e0b,#d97706)',
            'linear-gradient(135deg,#ec4899,#be185d)', 'linear-gradient(135deg,#14b8a6,#0f766e)',
        ];
        return $colores[abs(crc32((string)$texto)) % count($colores)];
    }
}
if (!function_exists('generoNombre')) {
    function generoNombre($id_genero) {
        switch ((int)$id_genero) {
            case 1: return 'Femenino';
            case 2: return 'Masculino';
            case 3: return 'Otro';
            default: return 'Sin definir';
        }
    }
}
if (!function_exists('areaBadge')) {
    function areaBadge($area) {
        $areaSegura = htmlspecialchars((string)$area, ENT_QUOTES, 'UTF-8');
        $map = [
            'Producción'     => 'background:#ecfdf5;color:#15803d;border:1px solid #bbf7d0;',
            'Administrativa' => 'background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;',
            'Logística'      => 'background:#fff7ed;color:#c2410c;border:1px solid #fed7aa;',
            'Mantenimiento'  => 'background:#f5f3ff;color:#6d28d9;border:1px solid #ddd6fe;',
            'SST'            => 'background:#fef2f2;color:#b91c1c;border:1px solid #fecaca;',
        ];
        $style = $map[$area] ?? 'background:#f3f4f6;color:#374151;border:1px solid #e5e7eb;';
        return '<span class="badge-area" style="' . $style . '">' . $areaSegura . '</span>';
    }
}

/** Badge de aptitud médica — ver nota de gobernanza (§28/§31) en la versión anterior */
function psBadgeAptitud(?string $aptitud): string {
    $mapa = [
        'apto'        => ['clase' => 'badge-ok',    'label' => 'Apto'],
        'restriccion' => ['clase' => 'badge-warn',  'label' => 'Apto con restricciones'],
        'no_apto'     => ['clase' => 'badge-urgent','label' => 'No apto'],
        'pendiente'   => ['clase' => 'badge-info',  'label' => 'Pendiente de evaluación'],
    ];
    $d = $mapa[$aptitud] ?? $mapa['pendiente'];
    return "<span class=\"badge-venc {$d['clase']}\">{$d['label']}</span>";
}
function psBadgeVencimiento(?string $fecha): string {
    if (!$fecha) return '<span class="badge-venc badge-info">Sin programar</span>';
    $dias = (strtotime($fecha) - time()) / 86400;
    if ($dias < 0)   return '<span class="badge-venc badge-urgent">Vencido</span>';
    if ($dias <= 30) return '<span class="badge-venc badge-warn">Por vencer</span>';
    return '<span class="badge-venc badge-ok">Vigente</span>';
}
function psFmtFecha(?string $f): string {
    if (!$f) return '—';
    $t = strtotime($f);
    return $t ? date('d/m/Y', $t) : '—';
}

/* ----------------------------------------------------------------------
 * 4. ESTADO INTERNO DEL MÓDULO (no es enrutador de app, es de este archivo)
 * ------------------------------------------------------------------- */
$vista = $_GET['vista'] ?? 'listado';
$idSel = isset($_GET['id']) ? (int)$_GET['id'] : null;

$mensajeToast = isset($_GET['mensaje']) ? htmlspecialchars($_GET['mensaje'], ENT_QUOTES, 'UTF-8') : null;
$tipoToast    = $_GET['tipo'] ?? 'ok';

/* ----------------------------------------------------------------------
 * 5. CONSULTAS — todas con sentencias preparadas (§29.4)
 * ------------------------------------------------------------------- */
$buscar  = trim($_GET['buscar'] ?? '');
$area    = trim($_GET['area'] ?? '');
$aptitud = trim($_GET['aptitud'] ?? '');

$trabajadores = [];
$totalTrabajadores = $conRestriccion = $vencidos = $aptosSinRestriccion = 0;
$trabajadorActual = null;
$restricciones = [];
$historial = [];
$errorConsulta = null;

try {
    if ($vista === 'detalle' && $idSel) {
        /* ---------- DETALLE: datos generales + aptitud + restricciones + historial ---------- */
        $stmt = $conexion->prepare("
            SELECT
                t.id_trabajador, t.numero_documento, t.nombres, t.apellidos,
                t.id_generos, t.fecha_ingreso,
                COALESCE(a.nombre_area, 'Sin área') AS nombre_area,
                COALESCE(c.nombre_cargo, 'Sin cargo') AS nombre_cargo,
                ps.aptitud, ps.fecha_evaluacion, ps.fecha_vencimiento
            FROM trabajadores t
            LEFT JOIN areas a ON t.id_area = a.id_areas
            LEFT JOIN cargos c ON t.id_cargo = c.id_cargo
            LEFT JOIN perfil_salud ps ON ps.id_trabajador = t.id_trabajador
            WHERE t.id_trabajador = ?
        ");
        $stmt->execute([$idSel]);
        $trabajadorActual = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($trabajadorActual) {
            $stmtR = $conexion->prepare("
                SELECT id_restriccion, tipo, fecha_inicio, fecha_fin, descripcion
                FROM restricciones_medicas
                WHERE id_trabajador = ? AND estado = 'activa'
                ORDER BY fecha_inicio DESC
            ");
            $stmtR->execute([$idSel]);
            $restricciones = $stmtR->fetchAll(PDO::FETCH_ASSOC);

            $stmtH = $conexion->prepare("
                SELECT fecha, titulo, detalle
                FROM historial_medico
                WHERE id_trabajador = ?
                ORDER BY fecha DESC, id_historial DESC
            ");
            $stmtH->execute([$idSel]);
            $historial = $stmtH->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $vista = 'listado'; // id inexistente -> fallback seguro
        }
    }

    if ($vista === 'listado') {
        /* ---------- LISTADO: filtros + tabla ---------- */
        $where = ["1=1"];
        $params = [];

        if ($buscar !== '') {
            $where[] = "(t.nombres LIKE ? OR t.apellidos LIKE ? OR t.numero_documento LIKE ?)";
            $params[] = "%{$buscar}%"; $params[] = "%{$buscar}%"; $params[] = "%{$buscar}%";
        }
        if ($area !== '') {
            $where[] = "t.id_area = ?";
            $params[] = (int)$area;
        }
        if ($aptitud !== '') {
            $where[] = "COALESCE(ps.aptitud, 'pendiente') = ?";
            $params[] = $aptitud;
        }

        $sql = "
            SELECT
                t.id_trabajador, t.numero_documento, t.nombres, t.apellidos, t.id_generos,
                COALESCE(a.nombre_area, 'Sin área') AS nombre_area,
                COALESCE(ps.aptitud, 'pendiente') AS aptitud,
                ps.fecha_evaluacion, ps.fecha_vencimiento
            FROM trabajadores t
            LEFT JOIN areas a ON t.id_area = a.id_areas
            LEFT JOIN perfil_salud ps ON ps.id_trabajador = t.id_trabajador
            WHERE " . implode(' AND ', $where) . "
            ORDER BY t.id_trabajador DESC
        ";
        $stmt = $conexion->prepare($sql);
        $stmt->execute($params);
        $trabajadores = $stmt->fetchAll(PDO::FETCH_ASSOC);

        /* KPIs (§2.4.6) — sobre el universo completo, no sobre el filtrado */
        $totalTrabajadores = (int)$conexion->query("SELECT COUNT(*) FROM trabajadores")->fetchColumn();

        $conRestriccion = (int)$conexion->query("
            SELECT COUNT(DISTINCT id_trabajador) FROM restricciones_medicas WHERE estado = 'activa'
        ")->fetchColumn();

        $vencidos = (int)$conexion->query("
            SELECT COUNT(*) FROM perfil_salud WHERE fecha_vencimiento IS NOT NULL AND fecha_vencimiento < CURDATE()
        ")->fetchColumn();

        $aptosSinRestriccion = (int)$conexion->query("
            SELECT COUNT(*) FROM perfil_salud WHERE aptitud = 'apto'
        ")->fetchColumn();
    }
} catch (PDOException $e) {
    /* No exponemos detalles de la excepción al usuario final (§29.4) */
    $errorConsulta = 'No fue posible cargar la información de Perfil de Salud. '
                    . 'Verifica que las tablas perfil_salud, restricciones_medicas e historial_medico existan.';
    // error_log($e->getMessage()); // recomendado en producción
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Perfil de Salud | PlastyPetco</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
/* ==========================================================================
   TOKENS — Parte 2 del Design System (idénticos a trabajadores/index.php)
   ========================================================================== */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --green:#2ddf6e;--green-dim:#1a9945;--green-dark:#0d5c2e;
  --sidebar-bg:#050e07;--sidebar-w:220px;--topbar-h:64px;
  --content-bg:#f2f5f3;--white:#ffffff;--bg:#f2f5f3;
  --text:#0d1f11;--text-mid:#4a6655;--text-soft:#8aab96;
  --border:#e0ebe4;--green-mist:rgba(45,223,110,0.08);
  --shadow:0 2px 12px rgba(0,0,0,0.07);--shadow-md:0 6px 24px rgba(0,0,0,0.09);
}
html,body{height:100%}
body{font-family:'DM Sans',sans-serif;background:var(--content-bg);color:var(--text)}
svg{fill:none;stroke:currentColor;stroke-width:1.8}

/* Layout / Sidebar / Topbar — §3, §10, §11 (inmutables entre módulos) */
.layout{display:flex;min-height:100vh}
.sidebar{width:var(--sidebar-w);background:var(--sidebar-bg);display:flex;flex-direction:column;
  position:fixed;top:0;left:0;height:100vh;z-index:100;border-right:1px solid rgba(45,223,110,.1)}
.sidebar-head{padding:20px 18px 16px;border-bottom:1px solid rgba(45,223,110,.1);display:flex;align-items:center;gap:10px}
.sidebar-logo{width:36px;height:36px;border-radius:10px;background:var(--green-mist);display:flex;align-items:center;justify-content:center;color:var(--green)}
.sidebar-brand{font-family:'Syne';font-size:16px;font-weight:800;color:#fff}
.sidebar-brand em{font-style:normal;color:var(--green)}
.sidebar-tag{font-size:10px;color:rgba(45,223,110,.5);margin-top:3px}
.sidebar-nav{flex:1;padding:12px 10px;overflow-y:auto}
.nav-section{font-size:9.5px;letter-spacing:1.4px;text-transform:uppercase;color:rgba(45,223,110,.35);padding:12px 8px 5px;font-weight:700}
.nav-item{display:flex;align-items:center;gap:10px;padding:9px 10px;border-radius:10px;
  color:rgba(255,255,255,.45);font-size:13px;text-decoration:none;transition:all .18s;position:relative}
.nav-item svg{width:16px;height:16px;flex-shrink:0}
.nav-item:hover{background:rgba(45,223,110,.08);color:rgba(255,255,255,.85)}
.nav-item.active{background:rgba(45,223,110,.14);color:var(--green);font-weight:500}
.nav-item.active::before{content:'';position:absolute;left:0;top:22%;height:56%;width:3px;border-radius:2px;background:var(--green);box-shadow:0 0 8px var(--green)}
.sidebar-foot{padding:12px 10px;border-top:1px solid rgba(45,223,110,.08)}
.nav-logout{display:flex;align-items:center;gap:10px;padding:9px 10px;border-radius:10px;color:#f87171;font-size:13px;text-decoration:none}
.nav-logout svg{width:16px;height:16px}
.nav-logout:hover{background:rgba(248,113,113,.08)}

.main{margin-left:var(--sidebar-w);flex:1;display:flex;flex-direction:column;min-height:100vh}
.topbar{height:var(--topbar-h);background:#fff;border-bottom:1px solid var(--border);box-shadow:0 1px 8px rgba(0,0,0,.05);
  display:flex;align-items:center;justify-content:space-between;padding:0 28px;position:sticky;top:0;z-index:50}
.topbar-title{font-family:'Syne';font-size:clamp(18px,2vw,22px);font-weight:800}
.profile-avatar{width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--green),var(--green-dim));
  display:flex;align-items:center;justify-content:center;font-family:'Syne';font-weight:800;color:#021a08;font-size:13px}

.content{flex:1;padding:24px 28px;display:flex;flex-direction:column;gap:20px}

/* Page Header §14.5 */
.page-header{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap}
.page-header-left{display:flex;align-items:center;gap:14px}
.page-icon{width:52px;height:52px;background:var(--green-mist);border:1px solid rgba(45,223,110,.2);border-radius:14px;
  display:flex;align-items:center;justify-content:center;color:var(--green-dark);flex-shrink:0}
.page-icon svg{width:24px;height:24px}
.page-title{font-family:'Syne';font-size:clamp(20px,2.5vw,26px);font-weight:800}
.page-sub{font-size:13px;color:var(--text-soft);margin-top:3px}
.page-header-right{display:flex;gap:10px}

/* Botones §13 */
.btn{font-family:'DM Sans';font-size:13px;border-radius:10px;cursor:pointer;transition:all .2s;
  display:inline-flex;align-items:center;gap:7px;border:none;text-decoration:none}
.btn svg{width:16px;height:16px}
.btn-new{background:linear-gradient(135deg,var(--green),var(--green-dim));padding:10px 18px;
  font-family:'Syne';font-weight:700;color:#021a08;box-shadow:0 3px 14px rgba(45,223,110,.25)}
.btn-new:hover{transform:translateY(-1px);box-shadow:0 6px 20px rgba(45,223,110,.35)}
.btn-back{background:#fff;border:1px solid var(--border);padding:9px 16px;color:var(--text-mid)}
.btn-back:hover{border-color:#b6dfc4;background:#f7fbf8}
.btn-cancel{background:none;border:1px solid var(--border);padding:10px 20px;color:var(--text-mid)}
.btn-cancel:hover{background:var(--bg)}
.btn-filter{background:#fff;border:1px solid var(--border);height:40px;padding:0 16px;color:var(--text-mid)}
.btn-filter:hover{border-color:#b6dfc4;background:#f0f8f3}
.btn-clear{color:#dc2626;border-color:#fecaca}
.btn-clear:hover{background:#fff1f2;border-color:#fca5a5}
.btn-success{background:#16a34a;color:#fff;border-radius:12px;padding:11px 18px;font-weight:700;font-size:13.5px;box-shadow:0 8px 22px rgba(22,163,74,.22)}
.btn-success:hover{background:#15803d;transform:translateY(-1px)}
.acc-btn{width:30px;height:30px;border-radius:8px;background:none;border:1px solid transparent;
  display:flex;align-items:center;justify-content:center;color:var(--text-soft);transition:all .18s;cursor:pointer;text-decoration:none}
.acc-btn svg{width:15px;height:15px}
.acc-btn:hover{background:var(--bg);border-color:var(--border);color:var(--green-dark)}

/* Mini Stats §16.1 */
.mini-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:14px}
.stat-card{background:#fff;border:1px solid var(--border);border-radius:16px;padding:18px 20px;
  box-shadow:var(--shadow);transition:transform .2s,box-shadow .2s;animation:fadeUp .5s ease both}
.stat-card:hover{transform:translateY(-2px);box-shadow:var(--shadow-md)}
.stat-card:nth-child(1){animation-delay:.04s}.stat-card:nth-child(2){animation-delay:.08s}
.stat-card:nth-child(3){animation-delay:.12s}.stat-card:nth-child(4){animation-delay:.16s}
.stat-icon{width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center;margin-bottom:10px}
.stat-icon svg{width:18px;height:18px}
.ic-green{background:#f0fdf4;color:#16a34a}.ic-red{background:#fff1f2;color:#dc2626}
.ic-yellow{background:#fff7ed;color:#d97706}.ic-blue{background:#eff6ff;color:#2563eb}
.stat-num{font-family:'Syne';font-size:clamp(26px,3vw,34px);font-weight:800;line-height:1}
.stat-label{font-size:12.5px;color:var(--text-soft);margin-top:4px}

/* Filters Bar §15.6 */
.filters-bar{background:#fff;border:1px solid var(--border);border-radius:14px;padding:14px 18px;
  display:flex;gap:10px;flex-wrap:wrap;align-items:center;box-shadow:var(--shadow)}
.search-wrap{display:flex;align-items:center;gap:8px;background:var(--bg);border:1px solid var(--border);
  border-radius:10px;padding:8px 14px;flex:1;min-width:220px}
.search-wrap svg{width:15px;height:15px;color:var(--text-soft);flex-shrink:0}
.search-wrap input{border:none;background:none;outline:none;font-size:13.5px;width:100%;color:var(--text);font-family:'DM Sans'}
.search-wrap:focus-within{border-color:#b6dfc4;box-shadow:0 0 0 3px rgba(45,223,110,.07)}
.filter-select{height:40px;background:var(--bg);border:1px solid var(--border);border-radius:10px;
  padding:0 12px;font-size:13px;color:var(--text-mid);font-family:'DM Sans'}
.filter-select:focus{outline:none;border-color:#b6dfc4;box-shadow:0 0 0 3px rgba(45,223,110,.07)}

/* Tabla §15 */
.table-wrap{background:#fff;border:1px solid var(--border);border-radius:16px;box-shadow:var(--shadow);overflow:hidden}
.table-top{display:flex;justify-content:space-between;align-items:center;padding:14px 18px;border-bottom:1px solid var(--border)}
.table-count{font-size:12.5px;color:var(--text-soft)}
table{width:100%;border-collapse:collapse}
thead tr{background:var(--bg);border-bottom:1px solid var(--border)}
thead th{padding:11px 16px;font-size:11px;font-weight:700;color:var(--text-soft);text-transform:uppercase;
  letter-spacing:.8px;white-space:nowrap;text-align:left}
tbody tr{border-bottom:1px solid var(--border);transition:background .15s}
tbody tr:last-child{border-bottom:none}
tbody tr:hover{background:#f7fbf8}
tbody td{padding:13px 16px;font-size:13.5px;color:var(--text);vertical-align:middle}
.worker-cell{display:flex;align-items:center;gap:10px}
.worker-avatar{width:38px;height:38px;border-radius:50%;display:flex;align-items:center;justify-content:center;
  color:#fff;font-family:'Syne';font-weight:700;font-size:13px;box-shadow:0 2px 8px rgba(0,0,0,.12);flex-shrink:0}
.worker-name{font-size:13.5px;font-weight:600;line-height:1.2}
.worker-id{font-size:11.5px;color:var(--text-soft)}
.acc-btns{display:flex;gap:6px}

/* Badges §18 */
.badge-area{display:inline-flex;border-radius:20px;padding:3px 11px;font-size:11.5px;font-weight:500}
.badge-venc{border-radius:20px;padding:3px 9px;font-size:10.5px;font-weight:600;display:inline-flex;align-items:center;gap:5px}
.badge-ok{background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0}
.badge-warn{background:#fff7ed;color:#d97706;border:1px solid #fed7aa}
.badge-urgent{background:#fff1f2;color:#dc2626;border:1px solid #fecaca}
.badge-info{background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe}

/* Panel + Detalle §16.2 / §23.6 */
.panel{background:#fff;border:1px solid var(--border);border-radius:16px;padding:20px;box-shadow:var(--shadow)}
.panel-title{font-family:'Syne';font-size:14px;font-weight:700}
.detalle-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.detalle-grid .span-2{grid-column:span 2}
.ro-row{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.ro-group{display:flex;flex-direction:column;gap:4px;padding:10px 0;border-bottom:1px solid var(--border)}
.ro-group:last-child{border-bottom:none}
.ro-label{font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--text-soft)}
.ro-value{font-size:13.5px;font-weight:500}
.aptitud-box{display:flex;flex-direction:column;align-items:flex-start;gap:10px}
.aptitud-fechas{display:flex;gap:22px;margin-top:4px}
.restriccion-card{border:1px solid var(--border);border-radius:12px;padding:14px 16px;background:var(--bg);
  display:flex;justify-content:space-between;gap:12px;align-items:flex-start;margin-bottom:10px}
.restriccion-card:last-child{margin-bottom:0}
.restriccion-tipo{font-size:13.5px;font-weight:600}
.restriccion-fechas{font-size:11.5px;color:var(--text-soft);margin:3px 0 6px}
.restriccion-desc{font-size:12.5px;color:var(--text-mid)}
.sin-restricciones{padding:24px;text-align:center;color:var(--text-soft);font-size:13px}

/* Timeline §21.2 (roadmap) */
.timeline{position:relative;padding-left:6px}
.timeline-item{position:relative;display:flex;gap:14px;padding-bottom:20px}
.timeline-item:last-child{padding-bottom:0}
.timeline-icon{width:32px;height:32px;border-radius:50%;background:var(--green-mist);color:var(--green-dark);
  display:flex;align-items:center;justify-content:center;flex-shrink:0;border:2px solid #fff;box-shadow:0 0 0 1px var(--border)}
.timeline-icon svg{width:15px;height:15px}
.timeline-item:not(:last-child)::before{content:'';position:absolute;left:15px;top:32px;bottom:-4px;width:2px;background:var(--border)}
.timeline-title{font-size:13.5px;font-weight:600}
.timeline-detail{font-size:12.5px;color:var(--text-mid);margin-top:2px}
.timeline-date{font-size:11px;color:var(--text-soft);margin-top:3px}

/* Formularios §14 */
.form-section{margin-bottom:18px}
.form-section-title{font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;
  color:var(--text-soft);border-bottom:1px solid var(--border);padding-bottom:8px;margin-bottom:14px}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px}
.form-group{display:flex;flex-direction:column;gap:5px}
.form-group.full{grid-column:span 2}
.form-label{font-size:11px;font-weight:600;color:var(--text-mid)}
.form-input,.form-select{width:100%;height:42px;background:var(--bg);border:1px solid var(--border);border-radius:10px;
  padding:0 13px;font-size:13.5px;color:var(--text);outline:none;font-family:'DM Sans'}
textarea.form-input{height:auto;min-height:90px;padding:12px 14px;resize:vertical}
.form-input:focus,.form-select:focus{border-color:#b6dfc4;box-shadow:0 0 0 3px rgba(45,223,110,.07)}
.form-help{font-size:11.5px;color:var(--text-soft);margin-top:4px}

/* Modal §17.1 */
.modal-backdrop{position:fixed;inset:0;background:rgba(0,0,0,.35);backdrop-filter:blur(5px);
  display:none;align-items:center;justify-content:center;padding:20px;z-index:500}
.modal-backdrop.open{display:flex}
.modal{background:#fff;border-radius:20px;max-width:640px;width:100%;max-height:92vh;overflow-y:auto;
  box-shadow:0 28px 80px rgba(0,0,0,.14);animation:modalIn .3s cubic-bezier(.22,1,.36,1)}
.modal-head{display:flex;align-items:flex-start;gap:14px;padding:22px 24px 0}
.modal-icon{width:40px;height:40px;border-radius:11px;background:var(--green-mist);color:var(--green-dark);
  display:flex;align-items:center;justify-content:center;flex-shrink:0}
.modal-icon svg{width:19px;height:19px}
.modal-title{font-family:'Syne';font-size:17px;font-weight:800}
.modal-sub{font-size:12.5px;color:var(--text-soft);margin-top:3px}
.modal-close{width:32px;height:32px;border-radius:50%;border:none;background:none;color:var(--text-soft);
  margin-left:auto;cursor:pointer;display:flex;align-items:center;justify-content:center}
.modal-close:hover{background:#fff1f2;color:#dc2626}
.modal-body{padding:20px 24px}
.modal-foot{display:flex;justify-content:flex-end;gap:10px;padding:16px 24px 22px}

/* Confirm card §17.2 */
.confirm-backdrop{position:fixed;inset:0;background:rgba(0,0,0,.42);backdrop-filter:blur(4px);
  display:none;align-items:center;justify-content:center;padding:20px;z-index:700}
.confirm-backdrop.open{display:flex}
.confirm-card{background:#fff;border-radius:22px;max-width:520px;width:100%;
  box-shadow:0 28px 80px rgba(0,0,0,.18);animation:modalIn .28s cubic-bezier(.22,1,.36,1);padding:24px}
.confirm-head{display:flex;gap:14px;align-items:flex-start}
.confirm-icon{width:48px;height:48px;border-radius:14px;background:#f0fdf4;color:#16a34a;flex-shrink:0;
  display:flex;align-items:center;justify-content:center}
.confirm-icon svg{width:22px;height:22px}
.confirm-title{font-family:'Syne';font-size:20px;font-weight:800}
.confirm-desc{font-size:13px;color:var(--text-mid);margin-top:4px}
.confirm-message{margin-top:16px;padding:14px 16px;border-radius:14px;background:#fef3c7;color:#92400e;
  border:1px solid #fde68a;font-size:13.5px;font-weight:600}
.confirm-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:20px}

/* Toast §19.1 */
.toast-sistema{position:fixed;top:86px;right:28px;z-index:99999;min-width:280px;max-width:360px;
  padding:16px 18px;border-radius:16px;box-shadow:0 18px 45px rgba(0,0,0,.16);animation:toastEntrada .35s ease both}
.toast-sistema strong{display:block;font-size:14px;font-weight:800}
.toast-sistema span{display:block;font-size:12.5px;font-weight:500;opacity:.85;margin-top:2px}
.toast-ok{background:#dcfce7;color:#166534;border:1px solid #86efac}
.toast-warning{background:#fef3c7;color:#92400e;border:1px solid #fde68a}
.toast-error{background:#fff1f2;color:#b91c1c;border:1px solid #fecaca}

@keyframes fadeUp{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:none}}
@keyframes modalIn{from{opacity:0;transform:translateY(20px) scale(.97)}to{opacity:1;transform:none}}
@keyframes toastEntrada{from{opacity:0;transform:translateY(-10px)}to{opacity:1;transform:translateY(0)}}

.empty-state{padding:56px 20px;text-align:center}
.empty-icon{width:56px;height:56px;background:var(--green-mist);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px}
.empty-title{font-family:'Syne';font-size:16px;font-weight:700;margin-bottom:6px}
.empty-sub{font-size:13px;color:var(--text-soft)}
.error-banner{background:#fff1f2;border:1px solid #fecaca;color:#b91c1c;border-radius:14px;padding:14px 16px;font-size:13.5px}

@media(max-width:1100px){.detalle-grid{grid-template-columns:1fr}}
@media(max-width:900px){
  .sidebar{transform:translateX(-100%)}.sidebar.open{transform:translateX(0)}
  .main{margin-left:0}.content{padding:16px;gap:14px}.mini-stats{grid-template-columns:1fr 1fr}
  .form-row,.ro-row{grid-template-columns:1fr}.form-group.full{grid-column:span 1}
}
@media(max-width:640px){thead th:nth-child(2),tbody td:nth-child(2){display:none}}
</style>
</head>
<body>
<div class="layout">

  <!-- ═══════════════════ SIDEBAR — MPA, rutas relativas, sin PHP ═══════════════════ -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-head">
      <div class="sidebar-logo">
        <svg viewBox="0 0 24 24"><path d="M12 2 3 7v6c0 5 4 8 9 9 5-1 9-4 9-9V7z"/></svg>
      </div>
      <div>
        <div class="sidebar-brand">Plasty<em>Petco</em></div>
        <div class="sidebar-tag">RRHH · SG-SST</div>
      </div>
    </div>

    <nav class="sidebar-nav">

      <div class="nav-section">Principal</div>
      <a href="../dashboard/index.php" class="nav-item">
        <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/></svg>
        Resumen
      </a>

      <div class="nav-section">Gestión</div>
      <a href="../trabajadores/index.php" class="nav-item">
        <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
        Trabajadores
      </a>
      <a href="../contratacion/index.php" class="nav-item">
        <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
        Contratación
      </a>
      <a href="../novedades/index.php" class="nav-item">
        <svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>
        Novedades
      </a>
      <a href="../vacaciones/index.php" class="nav-item">
        <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        Vacaciones
      </a>

      <div class="nav-section">SG-SST</div>
      <a href="index.php" class="nav-item active">
        <svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        Perfil de Salud
      </a>
      <a href="../examenes/index.php" class="nav-item">
        <svg viewBox="0 0 24 24"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
        Exámenes Médicos
      </a>
      <a href="../incidentes/index.php" class="nav-item">
        <svg viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        Incidentes
      </a>
      <a href="../capacitaciones/index.php" class="nav-item">
        <svg viewBox="0 0 24 24"><path d="M2 3h6a4 4 0 014 4v14a3 3 0 00-3-3H2z"/><path d="M22 3h-6a4 4 0 00-4 4v14a3 3 0 013-3h7z"/></svg>
        Capacitaciones
      </a>

      <div class="nav-section">Reportes</div>
      <a href="../reportes/index.php" class="nav-item">
        <svg viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
        Reportes
      </a>
      <a href="../indicadores/index.php" class="nav-item">
        <svg viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
        Indicadores
      </a>

      <div class="nav-section">Configuración</div>
      <a href="../usuarios/index.php" class="nav-item">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M20 21a8 8 0 10-16 0"/></svg>
        Usuarios
      </a>
      <a href="../roles/index.php" class="nav-item">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
        Roles y Permisos
      </a>

    </nav>

    <div class="sidebar-foot">
      <a href="../../logout.php" class="nav-logout">
        <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        Cerrar sesión
      </a>
    </div>
  </aside>

  <div class="main">

    <!-- ═══════════════════ TOPBAR ═══════════════════ -->
    <header class="topbar">
      <div class="topbar-title">Perfil de Salud</div>
      <div class="profile-avatar"><?php echo htmlspecialchars($inicial) ?></div>
    </header>

    <div class="content">

<?php if ($errorConsulta): ?>
      <div class="error-banner"><?php echo htmlspecialchars($errorConsulta) ?></div>
<?php endif; ?>

<?php if ($vista === 'listado'): ?>

      <!-- ═══ LISTADO — Patrón §23.2 ═══ -->
      <div class="page-header">
        <div class="page-header-left">
          <div class="page-icon"><svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></div>
          <div>
            <div class="page-title">Perfil de Salud</div>
            <div class="page-sub">Consolidado de información médica ocupacional del personal</div>
          </div>
        </div>
      </div>

      <div class="mini-stats">
        <div class="stat-card">
          <div class="stat-icon ic-blue"><svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg></div>
          <div class="stat-num"><?php echo $totalTrabajadores ?></div>
          <div class="stat-label">Total trabajadores</div>
        </div>
        <div class="stat-card">
          <div class="stat-icon ic-yellow"><svg viewBox="0 0 24 24"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/></svg></div>
          <div class="stat-num"><?php echo $conRestriccion ?></div>
          <div class="stat-label">Con restricción activa</div>
        </div>
        <div class="stat-card">
          <div class="stat-icon ic-red"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></div>
          <div class="stat-num"><?php echo $vencidos ?></div>
          <div class="stat-label">Exámenes vencidos</div>
        </div>
        <div class="stat-card">
          <div class="stat-icon ic-green"><svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div>
          <div class="stat-num"><?php echo $aptosSinRestriccion ?></div>
          <div class="stat-label">Aptos sin restricción</div>
        </div>
      </div>

      <form method="get" action="index.php" id="formFiltros">
        <div class="filters-bar">
          <div class="search-wrap">
            <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" name="buscar" id="buscador" placeholder="Buscar por nombre o documento..." value="<?php echo htmlspecialchars($buscar) ?>">
          </div>
          <select class="filter-select" name="area" id="filtroArea">
            <option value="">Todas las áreas</option>
            <?php
            /* Poblado dinámico desde la tabla areas ya existente en tu BD */
            try {
                $stmtAreas = $conexion->query("SELECT id_areas, nombre_area FROM areas ORDER BY nombre_area");
                foreach ($stmtAreas->fetchAll(PDO::FETCH_ASSOC) as $a) {
                    $sel = ((string)$a['id_areas'] === $area) ? 'selected' : '';
                    echo '<option value="' . (int)$a['id_areas'] . '" ' . $sel . '>' . htmlspecialchars($a['nombre_area']) . '</option>';
                }
            } catch (PDOException $e) { /* tabla areas no disponible: se omite el listado */ }
            ?>
          </select>
          <select class="filter-select" name="aptitud" id="filtroAptitud">
            <option value="">Toda aptitud</option>
            <option value="apto" <?php echo $aptitud === 'apto' ? 'selected' : '' ?>>Apto</option>
            <option value="restriccion" <?php echo $aptitud === 'restriccion' ? 'selected' : '' ?>>Apto con restricciones</option>
            <option value="no_apto" <?php echo $aptitud === 'no_apto' ? 'selected' : '' ?>>No apto</option>
            <option value="pendiente" <?php echo $aptitud === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
          </select>
          <button type="submit" class="btn btn-filter">
            <svg viewBox="0 0 24 24"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>Filtrar
          </button>
          <a href="index.php" class="btn btn-filter btn-clear">
            <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>Limpiar
          </a>
        </div>
      </form>

      <div class="table-wrap">
        <div class="table-top">
          <div class="table-count" id="contador">Mostrando <strong><?php echo count($trabajadores) ?></strong> trabajador(es)</div>
        </div>

        <?php if (empty($trabajadores)): ?>
          <div class="empty-state">
            <div class="empty-icon"><svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></div>
            <div class="empty-title">No se encontraron perfiles de salud</div>
            <div class="empty-sub">Intenta ajustar los filtros o verifica que existan trabajadores registrados.</div>
          </div>
        <?php else: ?>
        <table id="tablaPerfiles">
          <thead>
            <tr>
              <th>Trabajador</th><th>Área</th><th>Aptitud médica</th>
              <th>Última evaluación</th><th>Vencimiento</th><th>Acciones</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($trabajadores as $t): ?>
            <tr>
              <td>
                <div class="worker-cell">
                  <div class="worker-avatar" style="background:<?php echo avatarColor($t['nombres'] . $t['apellidos']) ?>">
                    <?php echo avInit($t['nombres'], $t['apellidos']) ?>
                  </div>
                  <div>
                    <div class="worker-name"><?php echo htmlspecialchars($t['nombres'] . ' ' . $t['apellidos']) ?></div>
                    <div class="worker-id"><?php echo htmlspecialchars($t['numero_documento']) ?> · <?php echo generoNombre($t['id_generos']) ?></div>
                  </div>
                </div>
              </td>
              <td><?php echo areaBadge($t['nombre_area']) ?></td>
              <td><?php echo psBadgeAptitud($t['aptitud']) ?></td>
              <td><?php echo psFmtFecha($t['fecha_evaluacion']) ?></td>
              <td><?php echo psBadgeVencimiento($t['fecha_vencimiento']) ?></td>
              <td>
                <div class="acc-btns">
                  <a class="acc-btn" title="Ver perfil de salud" href="index.php?vista=detalle&id=<?php echo (int)$t['id_trabajador'] ?>">
                    <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                  </a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </div>

<?php elseif ($vista === 'detalle' && $trabajadorActual): $t = $trabajadorActual; ?>

      <!-- ═══ DETALLE — Patrón §23.6 ═══ -->
      <div class="page-header">
        <div class="page-header-left">
          <div class="page-icon"><svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></div>
          <div>
            <div class="page-title"><?php echo htmlspecialchars($t['nombres'] . ' ' . $t['apellidos']) ?></div>
            <div class="page-sub">Perfil de salud · <?php echo htmlspecialchars($t['numero_documento']) ?></div>
          </div>
        </div>
        <div class="page-header-right">
          <a class="btn btn-back" href="index.php">
            <svg viewBox="0 0 24 24"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
            Volver a Perfil de Salud
          </a>
        </div>
      </div>

      <div class="detalle-grid">

        <div class="panel">
          <div class="panel-title" style="margin-bottom:12px">Datos generales</div>
          <div class="ro-row">
            <div class="ro-group"><span class="ro-label">Nombre completo</span><span class="ro-value"><?php echo htmlspecialchars($t['nombres'] . ' ' . $t['apellidos']) ?></span></div>
            <div class="ro-group"><span class="ro-label">Documento</span><span class="ro-value"><?php echo htmlspecialchars($t['numero_documento']) ?></span></div>
            <div class="ro-group"><span class="ro-label">Área</span><span class="ro-value"><?php echo areaBadge($t['nombre_area']) ?></span></div>
            <div class="ro-group"><span class="ro-label">Cargo</span><span class="ro-value"><?php echo htmlspecialchars($t['nombre_cargo']) ?></span></div>
            <div class="ro-group"><span class="ro-label">Fecha de ingreso</span><span class="ro-value"><?php echo psFmtFecha($t['fecha_ingreso']) ?></span></div>
            <div class="ro-group"><span class="ro-label">Género</span><span class="ro-value"><?php echo generoNombre($t['id_generos']) ?></span></div>
          </div>
        </div>

        <div class="panel">
          <div class="panel-title" style="margin-bottom:12px">Aptitud médica actual</div>
          <div class="aptitud-box">
            <?php echo psBadgeAptitud($t['aptitud']) ?>
            <div class="aptitud-fechas">
              <div><span class="ro-label">Última evaluación</span><span class="ro-value"><?php echo psFmtFecha($t['fecha_evaluacion']) ?></span></div>
              <div><span class="ro-label">Próximo examen</span><span class="ro-value"><?php echo psFmtFecha($t['fecha_vencimiento']) ?> <?php echo psBadgeVencimiento($t['fecha_vencimiento']) ?></span></div>
            </div>
            <button class="btn btn-new" style="margin-top:14px" onclick="document.getElementById('backdropRestriccion').classList.add('open')">
              <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
              Registrar restricción médica
            </button>
          </div>
        </div>

        <div class="panel span-2">
          <div class="panel-title" style="margin-bottom:12px">Restricciones médicas activas</div>
          <?php if (empty($restricciones)): ?>
            <div class="sin-restricciones">Este trabajador no tiene restricciones médicas activas.</div>
          <?php else: foreach ($restricciones as $r): ?>
            <div class="restriccion-card">
              <div>
                <div class="restriccion-tipo"><?php echo htmlspecialchars($r['tipo']) ?></div>
                <div class="restriccion-fechas">
                  Desde <?php echo psFmtFecha($r['fecha_inicio']) ?><?php echo $r['fecha_fin'] ? ' hasta ' . psFmtFecha($r['fecha_fin']) : ' · sin fecha de finalización definida' ?>
                </div>
                <div class="restriccion-desc"><?php echo htmlspecialchars($r['descripcion']) ?></div>
              </div>
              <button class="acc-btn" title="Finalizar restricción"
                onclick="confirmarFinalizar(<?php echo (int)$r['id_restriccion'] ?>, '<?php echo htmlspecialchars(addslashes($r['tipo']), ENT_QUOTES) ?>', <?php echo (int)$t['id_trabajador'] ?>)">
                <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
              </button>
            </div>
          <?php endforeach; endif; ?>
        </div>

        <div class="panel span-2">
          <div class="panel-title" style="margin-bottom:14px">Historial médico</div>
          <?php if (empty($historial)): ?>
            <div class="sin-restricciones">Aún no hay eventos registrados en el historial médico.</div>
          <?php else: ?>
          <div class="timeline">
            <?php foreach ($historial as $h): ?>
              <div class="timeline-item">
                <div class="timeline-icon"><svg viewBox="0 0 24 24"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg></div>
                <div>
                  <div class="timeline-title"><?php echo htmlspecialchars($h['titulo']) ?></div>
                  <div class="timeline-detail"><?php echo htmlspecialchars($h['detalle']) ?></div>
                  <div class="timeline-date"><?php echo psFmtFecha($h['fecha']) ?></div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>

      </div>

      <!-- Modal Registrar restricción — POST real a guardar_restriccion.php -->
      <div class="modal-backdrop" id="backdropRestriccion" onclick="if(event.target===this)this.classList.remove('open')">
        <div class="modal">
          <div class="modal-head">
            <div class="modal-icon"><svg viewBox="0 0 24 24"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/></svg></div>
            <div>
              <div class="modal-title">Registrar restricción médica</div>
              <div class="modal-sub"><?php echo htmlspecialchars($t['nombres'] . ' ' . $t['apellidos']) ?></div>
            </div>
            <button class="modal-close" onclick="document.getElementById('backdropRestriccion').classList.remove('open')">
              <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
          </div>
          <form class="modal-body" method="POST" action="guardar_restriccion.php">
            <input type="hidden" name="id_trabajador" value="<?php echo (int)$t['id_trabajador'] ?>">
            <div class="form-section">
              <div class="form-section-title">Detalle de la restricción</div>
              <div class="form-row">
                <div class="form-group">
                  <label class="form-label">Tipo de restricción</label>
                  <select class="form-select" name="tipo" required>
                    <option value="">Seleccione...</option>
                    <option>No levantar cargas pesadas</option>
                    <option>Restricción de trabajo en alturas</option>
                    <option>Restricción de turnos nocturnos</option>
                    <option>Reubicación temporal de puesto</option>
                    <option>Otra</option>
                  </select>
                </div>
                <div class="form-group">
                  <label class="form-label">Fecha de inicio</label>
                  <input class="form-input" type="date" name="fecha_inicio" required>
                </div>
                <div class="form-group">
                  <label class="form-label">Fecha de finalización (opcional)</label>
                  <input class="form-input" type="date" name="fecha_fin">
                </div>
                <div class="form-group full">
                  <label class="form-label">Descripción / recomendación médica</label>
                  <textarea class="form-input" name="descripcion" rows="3" required
                    placeholder="Ej: Concepto médico, especialista remitente, próxima cita de control..."></textarea>
                </div>
              </div>
            </div>
            <div class="modal-foot" style="padding:0">
              <button type="button" class="btn btn-cancel" onclick="document.getElementById('backdropRestriccion').classList.remove('open')">Cancelar</button>
              <button type="submit" class="btn btn-new">Guardar restricción</button>
            </div>
          </form>
        </div>
      </div>

      <!-- Confirm card Finalizar restricción — GET real a finalizar_restriccion.php -->
      <div class="confirm-backdrop" id="backdropFinalizar">
        <div class="confirm-card">
          <div class="confirm-head">
            <div class="confirm-icon"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></div>
            <div>
              <div class="confirm-title">¿Finalizar esta restricción?</div>
              <div class="confirm-desc" id="descConfirmFinalizar">Esta acción la marcará como resuelta.</div>
            </div>
          </div>
          <div class="confirm-message">Verifica que exista concepto médico que respalde el levantamiento de la restricción.</div>
          <div class="confirm-actions">
            <button class="btn btn-cancel" onclick="document.getElementById('backdropFinalizar').classList.remove('open')">Cancelar</button>
            <a class="btn btn-success" id="linkEjecutarFinalizar" href="#">Sí, finalizar</a>
          </div>
        </div>
      </div>

<?php endif; ?>

    </div><!-- /content -->
  </div><!-- /main -->
</div><!-- /layout -->

<?php if ($mensajeToast): ?>
<div class="toast-sistema toast-<?php echo $tipoToast === 'warning' ? 'warning' : ($tipoToast === 'error' ? 'error' : 'ok') ?>" id="toast">
  <strong><?php echo $tipoToast === 'warning' ? 'Atención' : ($tipoToast === 'error' ? 'Error' : 'Listo') ?></strong>
  <span><?php echo $mensajeToast ?></span>
</div>
<?php endif; ?>

<script>
/* Confirm card — dos funciones (§29.3) */
function confirmarFinalizar(idRestriccion, tipo, idTrabajador) {
  document.getElementById('descConfirmFinalizar').textContent =
    'Esta acción marcará "' + tipo + '" como resuelta y se registrará en el historial médico.';
  document.getElementById('linkEjecutarFinalizar').href =
    'finalizar_restriccion.php?id=' + idRestriccion + '&trabajador=' + idTrabajador;
  document.getElementById('backdropFinalizar').classList.add('open');
}
document.addEventListener('keydown', function (e) {
  if (e.key === 'Escape') {
    document.querySelectorAll('.modal-backdrop.open, .confirm-backdrop.open').forEach(b => b.classList.remove('open'));
  }
});

/* Toast: auto-dismiss 5000ms + limpieza de query string (§19.1) */
(function () {
  var toast = document.getElementById('toast');
  if (!toast) return;
  setTimeout(function () {
    toast.style.transition = 'opacity .45s, transform .45s';
    toast.style.opacity = '0';
    toast.style.transform = 'translateY(-10px)';
    setTimeout(function () {
      toast.remove();
      var url = new URL(window.location.href);
      url.searchParams.delete('mensaje');
      url.searchParams.delete('tipo');
      window.history.replaceState({}, document.title, url.pathname + url.search);
    }, 450);
  }, 5000);
})();
</script>
</body>
</html>