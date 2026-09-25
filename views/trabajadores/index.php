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
    die('Error crítico: no se encontró el archivo de conexión en ' . htmlspecialchars($conexionFile));
}
require_once $conexionFile;
require_once __DIR__ . '/funciones_trabajador.php';
require_once __DIR__ . '/../components/avatar.php';

// Formulario "Nuevo trabajador": errores y datos enviados si crear.php lo rechazó.
['errores' => $erroresNuevo, 'old' => $oldNuevo] = tomarErroresFormulario();
$opcionesForm = opcionesFormularioTrabajador($conexion);
$cargosNuevo = cargosDeArea($conexion, $oldNuevo['id_area'] ?? '');

function valorNuevo(string $campo, string $defecto = ''): string {
    global $oldNuevo;
    return (string)($oldNuevo[$campo] ?? $defecto);
}

// =========================
// DATOS DEL USUARIO EN TOPBAR
// =========================
$nombres = $_SESSION['nombres'] ?? $_SESSION['nombre'] ?? 'Administrador';
$apellidos = $_SESSION['apellidos'] ?? '';
$rol_nombre = $_SESSION['rol_nombre'] ?? $_SESSION['rol'] ?? 'RRHH';

$inicial = strtoupper(
    substr($nombres, 0, 1) . 
    substr($apellidos !== '' ? $apellidos : $nombres, 0, 1)
);

// Compatibilidad por si en otra parte usas estos nombres
$row_nombre = $nombres;
$row_apellidos = $apellidos; 

/* =========================
   FILTROS Y PAGINACIÓN
========================= */

$buscar = trim($_GET['buscar'] ?? '');
$area   = trim($_GET['area'] ?? '');
$estado = trim($_GET['estado'] ?? '');

$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$pagina = max(1, $pagina);

$por_pag = 8;
$offset = ($pagina - 1) * $por_pag;

/* =========================
   FUNCIONES VISUALES
========================= */
if (!function_exists('avInit')) {
    function avInit($nombres, $apellidos) {
        $nombres = trim($nombres ?? '');
        $apellidos = trim($apellidos ?? '');

        $i1 = $nombres !== '' ? strtoupper(substr($nombres, 0, 1)) : '';
        $i2 = $apellidos !== '' ? strtoupper(substr($apellidos, 0, 1)) : '';

        return $i1 . $i2;
    }
}
if (!function_exists('generoNombre')) {
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
}

if (!function_exists('avColor')) {
    function avColor($id) {
        $colores = [
            'linear-gradient(135deg,#22c55e,#15803d)',
            'linear-gradient(135deg,#3b82f6,#1d4ed8)',
            'linear-gradient(135deg,#8b5cf6,#6d28d9)',
            'linear-gradient(135deg,#f59e0b,#d97706)',
            'linear-gradient(135deg,#ec4899,#be185d)',
            'linear-gradient(135deg,#14b8a6,#0f766e)'
        ];

        return $colores[((int)$id) % count($colores)];
    }
}
if (!function_exists('iniciales')) {
    function iniciales($nombres, $apellidos) {
        $n = trim($nombres);
        $a = trim($apellidos);

        $ini1 = $n !== '' ? strtoupper(substr($n, 0, 1)) : '';
        $ini2 = $a !== '' ? strtoupper(substr($a, 0, 1)) : '';

        return $ini1 . $ini2;
    }
}

if (!function_exists('avatarColor')) {
    function avatarColor($texto) {
        $colores = [
            'linear-gradient(135deg,#22c55e,#15803d)',
            'linear-gradient(135deg,#3b82f6,#1d4ed8)',
            'linear-gradient(135deg,#8b5cf6,#6d28d9)',
            'linear-gradient(135deg,#f59e0b,#d97706)',
            'linear-gradient(135deg,#ec4899,#be185d)',
            'linear-gradient(135deg,#14b8a6,#0f766e)'
        ];

        $index = abs(crc32($texto)) % count($colores);
        return $colores[$index];
    }
}

if (!function_exists('areaBadge')) {
    function areaBadge($area) {
        $areaSegura = htmlspecialchars($area, ENT_QUOTES, 'UTF-8');

        $map = [
            'Producción'      => 'background:#ecfdf5;color:#15803d;border:1px solid #bbf7d0;',
            'Administrativa'  => 'background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;',
            'Logística'       => 'background:#fff7ed;color:#c2410c;border:1px solid #fed7aa;',
            'Mantenimiento'   => 'background:#f5f3ff;color:#6d28d9;border:1px solid #ddd6fe;',
            'SST'             => 'background:#fef2f2;color:#b91c1c;border:1px solid #fecaca;',
            ];

        $style = $map[$area] ?? 'background:#f3f4f6;color:#374151;border:1px solid #e5e7eb;';

        return '<span class="badge-area" style="'.$style.'">'.$areaSegura.'</span>';
    }
}

/* =========================
   ESTADÍSTICAS
========================= */

$tw = 0;
$total_hombres = 0;
$total_mujeres = 0;
$nuevos_mes = 0;

try {
    $tw = (int)$conexion->query("SELECT COUNT(*) FROM trabajadores")->fetchColumn();

    $stmt = $conexion->prepare("SELECT COUNT(*) FROM trabajadores WHERE id_generos = 2");
    $stmt->execute();
    $total_hombres = (int)$stmt->fetchColumn();

    $stmt = $conexion->prepare("SELECT COUNT(*) FROM trabajadores WHERE id_generos = 1");
    $stmt->execute();
    $total_mujeres = (int)$stmt->fetchColumn();

    // Nuevos este mes = trabajadores cuya FECHA DE INGRESO cae en el mes calendario
    // actual (no la fecha en que se creó el registro: cargar hoy a alguien que
    // ingresó hace años no lo hace "nuevo").
    $stmt = $conexion->prepare("SELECT COUNT(*) FROM trabajadores
                                WHERE fecha_ingreso >= :inicio AND fecha_ingreso <= :fin");
    $stmt->execute([':inicio' => date('Y-m-01'), ':fin' => date('Y-m-t')]);
    $nuevos_mes = (int)$stmt->fetchColumn();
} catch (Exception $e) {
    $tw = 0;
    $total_hombres = 0;
    $total_mujeres = 0;
    $nuevos_mes = 0;
}

/* =========================
   CONSULTA DE TRABAJADORES
========================= */

$where = [];
$params = [];

$where = [];
$params = [];

/* ================================
   FILTROS
================================ */
$buscar = trim($_GET['buscar'] ?? '');
$area   = trim($_GET['area'] ?? '');

$where = [];
$params = [];

/* BUSCADOR */
if ($buscar !== '') {
    $where[] = "(
        t.nombres LIKE ?
        OR t.apellidos LIKE ?
        OR CONCAT(t.nombres, ' ', t.apellidos) LIKE ?
        OR t.numero_documento LIKE ?
        OR t.correo_personal LIKE ?
        OR t.celular LIKE ?
    )";

    $params[] = "%{$buscar}%";
    $params[] = "%{$buscar}%";
    $params[] = "%{$buscar}%";
    $params[] = "%{$buscar}%";
    $params[] = "%{$buscar}%";
    $params[] = "%{$buscar}%";
}

/* FILTRO POR ÁREA */
if ($area !== '') {
    $where[] = "t.id_area = ?";
    $params[] = (int)$area;
}

/* ================================
   LISTADO DE TRABAJADORES
================================ */
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
    t.tiene_hijos,
    t.numero_hijos,
    COALESCE(a.nombre_area, 'Sin área') AS nombre_area,
    COALESCE(c.nombre_cargo, 'Sin cargo') AS nombre_cargo
FROM trabajadores t
LEFT JOIN areas a ON t.id_area = a.id_areas
LEFT JOIN cargos c ON t.id_cargo = c.id_cargo
WHERE 1=1
";

if (!empty($where)) {
  $sql .= " AND " . implode(" AND ", $where);
}

$sql .= " ORDER BY t.id_trabajador DESC";

$stmt = $conexion->prepare($sql);
$stmt->execute($params);
$trabajadores = $stmt->fetchAll(PDO::FETCH_ASSOC);
$total_filtrado = count($trabajadores);
// Consulta realizada con parámetros posicionales arriba.
// Si en el futuro usamos named params, reescribimos el bind aquí.
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Trabajadores | PlastyPetco</title>
<?php require __DIR__ . '/../components/estilos_base.php'; ?>
<style>
 

 
/* ── LAYOUT ── */
 
/* ── SIDEBAR ── */
 
 
/* leaf deco sidebar */
 
/* ── MAIN ── */
 
/* ── TOPBAR ── */
 
/* search bar */
 
/* topbar right */
 
/* profile pill */
 
/* dropdown */
 
/* ── CONTENT ── */
 
/* ── BANNER ── */
.banner{
  background:linear-gradient(135deg,var(--green-dark) 0%,#0d3d1e 50%,#1a5c2e 100%);
  border-radius:20px;padding:28px 32px;
  display:flex;align-items:center;justify-content:space-between;
  position:relative;overflow:hidden;min-height:130px;
}
.banner::before{content:'';position:absolute;inset:0;background:url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%232ddf6e' fill-opacity='0.04'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");pointer-events:none}
.banner-glow{position:absolute;right:15%;top:-40px;width:280px;height:280px;background:radial-gradient(circle,rgba(45,223,110,0.15),transparent 70%);pointer-events:none}
.banner-text{position:relative;z-index:1}
.banner-text h2{font-family:'Syne',sans-serif;font-size:clamp(20px,2.5vw,28px);font-weight:800;color:#fff;letter-spacing:-.4px;margin-bottom:6px}
.banner-text h2 span{color:var(--green)}
.banner-text p{font-size:13.5px;color:rgba(255,255,255,0.6);font-weight:300}
.banner-badge{position:relative;z-index:1;display:flex;align-items:center;gap:7px;background:rgba(45,223,110,0.15);border:1px solid rgba(45,223,110,0.3);border-radius:20px;padding:8px 16px;font-size:12px;color:var(--green);white-space:nowrap}
.pulse-dot{width:6px;height:6px;border-radius:50%;background:var(--green);box-shadow:0 0 8px var(--green);animation:pulse 2s infinite}
@keyframes pulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.4;transform:scale(.6)}}
 
/* ── STAT CARDS ── */
.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:14px}
.stat-card{
  background:var(--white);border:1px solid var(--border);border-radius:16px;
  padding:18px 20px;position:relative;overflow:hidden;
  box-shadow:var(--shadow);transition:transform .2s,box-shadow .2s;
  animation:fadeUp .5s ease both;
}
.stat-card:hover{transform:translateY(-2px);box-shadow:var(--shadow-md)}
.stat-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px}
.stat-icon{width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center}
.stat-icon svg{width:18px;height:18px;stroke:currentColor;fill:none;stroke-width:1.8}
.stat-trend{font-size:11px;font-weight:500;display:flex;align-items:center;gap:3px}
.stat-trend svg{width:12px;height:12px;stroke:currentColor;fill:none;stroke-width:2}
.trend-up{color:#16a34a}.trend-down{color:#dc2626}.trend-neutral{color:var(--text-soft)}
.stat-num{font-family:'Syne',sans-serif;font-size:clamp(26px,3vw,34px);font-weight:800;line-height:1;margin-bottom:3px}
.stat-label{font-size:12px;color:var(--text-soft);font-weight:400;margin-bottom:10px}
.stat-mini-chart{height:36px;width:100%}
 
/* icon color variants */
 
/* num color variants */
 
/* ── BOTTOM GRID (charts + sidebar panels) ── */
.bottom-grid{display:grid;grid-template-columns:1fr 1fr 320px;gap:16px}
 
.panel{background:var(--white);border:1px solid var(--border);border-radius:16px;padding:20px;box-shadow:var(--shadow)}
.panel-title{font-family:var(--tx-fuente-titulos);font-size:var(--tx-titulo-seccion);font-weight:var(--tx-peso-titulo);color:var(--tx-color);letter-spacing:-.2px;margin-bottom:4px}
.panel-sub{font-size:11.5px;color:var(--text-soft);margin-bottom:16px}
.panel-header{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:14px}
.panel-header-left .panel-title{margin-bottom:2px}
 
/* chart filter */
.chart-filter{font-size:11.5px;color:var(--text-soft);background:var(--content-bg);border:1px solid var(--border);border-radius:8px;padding:4px 10px;cursor:pointer;font-family:'DM Sans',sans-serif}
 
/* donut legend */
.donut-wrap{display:flex;align-items:center;gap:16px;flex-wrap:wrap}
.donut-chart-wrap{width:140px;height:140px;flex-shrink:0;position:relative}
.donut-center{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center}
.donut-center-num{font-family:'Syne',sans-serif;font-size:22px;font-weight:800;color:var(--text);line-height:1}
.donut-center-lbl{font-size:10px;color:var(--text-soft)}
.donut-legend{flex:1;display:flex;flex-direction:column;gap:7px}
.legend-item{display:flex;align-items:center;gap:8px;font-size:12px;color:var(--text-mid)}
.legend-dot{width:10px;height:10px;border-radius:50%;flex-shrink:0}
.legend-val{margin-left:auto;font-weight:600;color:var(--text)}
 
/* activity feed */
.activity-list{display:flex;flex-direction:column;gap:0}
.activity-item{display:flex;align-items:flex-start;gap:10px;padding:10px 0;border-bottom:1px solid var(--border)}
.activity-item:last-child{border-bottom:none}
.act-icon{width:32px;height:32px;border-radius:9px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.act-icon svg{width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:1.8}
.act-body{flex:1;min-width:0}
.act-title{font-size:12.5px;font-weight:500;color:var(--text);line-height:1.3}
.act-name{font-size:11.5px;color:var(--text-soft)}
.act-time{font-size:11px;color:var(--text-soft);white-space:nowrap;margin-top:2px}
.see-all{display:inline-flex;align-items:center;gap:5px;font-size:12px;color:var(--green-dim);font-weight:500;text-decoration:none;margin-top:10px}
.see-all svg{width:13px;height:13px;stroke:currentColor;fill:none;stroke-width:2}
.see-all:hover{color:var(--green-dark)}
 
/* ── BOTTOM ROW ── */
.bottom-row{display:grid;grid-template-columns:1fr 1fr 320px;gap:16px}
 
/* vencimientos */
.venc-item{display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--border)}
.venc-item:last-child{border-bottom:none}
.venc-icon{width:34px;height:34px;border-radius:9px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.venc-icon svg{width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:1.8}
.venc-body{flex:1;min-width:0}
.venc-name{font-size:12.5px;font-weight:500;color:var(--text)}
.venc-person{font-size:11.5px;color:var(--text-soft)}
.venc-meta{display:flex;align-items:center;gap:6px;flex-shrink:0}
.venc-date{font-size:11px;color:var(--text-soft)}
.venc-badge{font-size:10.5px;font-weight:600;border-radius:20px;padding:3px 9px;white-space:nowrap}
.badge-warn{background:#fff7ed;color:#d97706;border:1px solid #fed7aa}
.badge-ok{background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0}
.badge-urgent{background:#fff1f2;color:#dc2626;border:1px solid #fecaca}
 
/* ausentismo */
.absent-num{font-family:'Syne',sans-serif;font-size:36px;font-weight:800;color:var(--green-dark);line-height:1}
.absent-trend{display:flex;align-items:center;gap:5px;font-size:12px;color:#16a34a;margin:4px 0 14px}
.absent-trend svg{width:13px;height:13px;stroke:currentColor;fill:none;stroke-width:2}
.absent-row{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:14px}
.absent-mini{background:var(--content-bg);border:1px solid var(--border);border-radius:12px;padding:12px}
.absent-mini-num{font-family:'Syne',sans-serif;font-size:22px;font-weight:800;color:var(--text)}
.absent-mini-lbl{font-size:11px;color:var(--text-soft);margin-top:2px}
 
/* calendar */
.cal-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px}
.cal-month{font-family:'Syne',sans-serif;font-size:14px;font-weight:700;color:var(--text)}
.cal-nav{background:none;border:1px solid var(--border);border-radius:7px;width:26px;height:26px;display:flex;align-items:center;justify-content:center;cursor:pointer;color:var(--text-mid);transition:background .15s}
.cal-nav:hover{background:var(--content-bg)}
.cal-nav svg{width:13px;height:13px;stroke:currentColor;fill:none;stroke-width:2}
.cal-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:2px}
.cal-day-name{font-size:10px;color:var(--text-soft);text-align:center;padding:3px 0;font-weight:600}
.cal-day{font-size:12px;text-align:center;padding:5px 2px;border-radius:7px;cursor:default;color:var(--text-mid);transition:background .15s}
.cal-day:hover{background:var(--content-bg)}
.cal-day.today{background:var(--green);color:#021a08;font-weight:700;border-radius:50%;width:28px;height:28px;display:flex;align-items:center;justify-content:center;margin:0 auto}
.cal-day.empty{color:transparent;pointer-events:none}
 
/* animations */
.stat-card:nth-child(1){animation-delay:.04s}
.stat-card:nth-child(2){animation-delay:.08s}
.stat-card:nth-child(3){animation-delay:.12s}
.stat-card:nth-child(4){animation-delay:.16s}
.stat-card:nth-child(5){animation-delay:.20s}
 
/* sidebar overlay */
 
/* ── RESPONSIVE ── */
@media(max-width:1100px){
  .bottom-grid{grid-template-columns:1fr 1fr}
  .bottom-row{grid-template-columns:1fr 1fr}
  .bottom-grid > .panel:last-child{grid-column:span 2}
  .bottom-row > .panel:last-child{grid-column:span 2}
}
@media(max-width:900px){
  .bottom-grid,.bottom-row{grid-template-columns:1fr}
  .bottom-grid > .panel:last-child,.bottom-row > .panel:last-child{grid-column:auto}
}
@media(max-width:560px){
  .stats-grid{grid-template-columns:1fr 1fr}
  .banner{padding:20px}
  .banner-badge{display:none}
}
 
 
/* ── PAGE HEADER ── */
.page-header{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:4px}
.page-header-left{display:flex;align-items:center;gap:16px}
.page-icon{width:52px;height:52px;background:var(--green-mist);border:1px solid rgba(45,223,110,0.2);border-radius:14px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.page-icon svg{width:26px;height:26px;stroke:var(--green-dim);fill:none;stroke-width:1.7}
.page-title{font-family:var(--tx-fuente-titulos);font-size:var(--tx-titulo-pagina);font-weight:var(--tx-peso-titulo-pagina);color:var(--tx-color);letter-spacing:-.4px;line-height:1.1}
.page-sub{font-size:var(--tx-subtitulo);font-weight:var(--tx-peso-normal);color:var(--tx-color-suave);margin-top:3px}

 
/* ── MINI STATS ── */
 
/* ── FILTERS BAR ── */
 
/* ── TABLE WRAP ── */
 
/* ── TABLE ── */
.sort-th{display:inline-flex;align-items:center;gap:4px;cursor:pointer;transition:color .15s}
.sort-th:hover{color:var(--green-dark)}
.sort-th svg{width:11px;height:11px;stroke:currentColor;fill:none;stroke-width:2;opacity:.45}
 
/* worker cell */
.contact-email{font-size:12.5px;color:var(--text);font-weight:500}
.contact-phone{font-size:11.5px;color:var(--text-soft);margin-top:1px}
 
/* badges */
.badge-area{border-radius:20px;padding:3px 11px;font-size:11.5px;font-weight:500;display:inline-block}

 
/* action buttons */
/* Texto de las acciones (criterio de las action-pill de Bodega2): con espacio se ve
   el texto junto al ícono; en pantallas más angostas queda solo el ícono, con un
   tooltip propio que aparece al pasar el mouse y también al llegar con el teclado
   (el title nativo no hace esto último ni se ve en celular). El nombre accesible
   siempre lo da aria-label. */
 
/* ── PAGINATION ── */
.pagination{padding:14px 20px;border-top:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px}
.pag-info{font-size:var(--tx-ayuda);color:var(--tx-color-suave)}
.pag-btns{display:flex;align-items:center;gap:5px}
.pag-btn{min-width:34px;height:34px;border-radius:8px;background:none;border:1px solid var(--border);font-size:12.5px;color:var(--text-mid);cursor:pointer;display:flex;align-items:center;justify-content:center;padding:0 10px;gap:4px;transition:all .18s;font-family:'DM Sans',sans-serif;text-decoration:none}
.pag-btn:hover{background:var(--bg);border-color:#b6dfc4}
.pag-btn.active{background:var(--green);border-color:var(--green);color:#021a08;font-weight:700}
.pag-btn.disabled{opacity:.35;pointer-events:none}
.pag-btn svg{width:13px;height:13px;stroke:currentColor;fill:none;stroke-width:2}
 
/* ── EMPTY STATE ── */
.empty-state{padding:56px 20px;text-align:center}
.empty-icon{width:56px;height:56px;background:var(--green-mist);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px}
.empty-icon svg{width:26px;height:26px;stroke:var(--green-dim);fill:none;stroke-width:1.5}
.empty-title{font-family:'Syne',sans-serif;font-size:16px;font-weight:700;color:var(--text);margin-bottom:6px}
.empty-sub{font-size:13px;color:var(--text-soft)}
 
/* ── MODAL ── */
.modal-backdrop{position:fixed;inset:0;background:rgba(0,0,0,0.35);backdrop-filter:blur(5px);z-index:500;display:none;align-items:center;justify-content:center;padding:20px}
.modal-backdrop.open{display:flex}
.modal{background:var(--white);border:1px solid var(--border);border-radius:20px;width:100%;max-width:640px;max-height:92vh;overflow-y:auto;box-shadow:0 28px 80px rgba(0,0,0,0.14);animation:modalIn .3s cubic-bezier(.22,1,.36,1) both}
@keyframes modalIn{from{opacity:0;transform:translateY(20px) scale(.97)}to{opacity:1;transform:none}}
.modal-head{padding:22px 24px 16px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid var(--border)}
.modal-head-left{display:flex;align-items:center;gap:12px}
.modal-icon{width:40px;height:40px;background:var(--green-mist);border:1px solid rgba(45,223,110,0.2);border-radius:11px;display:flex;align-items:center;justify-content:center}
.modal-icon svg{width:20px;height:20px;stroke:var(--green-dim);fill:none;stroke-width:1.8}
.modal-title{font-family:var(--tx-fuente-titulos);font-size:var(--tx-titulo-modal);font-weight:var(--tx-peso-titulo-pagina);color:var(--tx-color)}
.modal-sub{font-size:var(--tx-ayuda);font-weight:var(--tx-peso-normal);color:var(--tx-color-suave);line-height:1.45;margin-top:1px}
.modal-close{width:32px;height:32px;border-radius:50%;background:var(--bg);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:18px;color:var(--text-mid);transition:background .2s;line-height:1}
.modal-close:hover{background:#fee2e2;color:#dc2626;border-color:#fecaca}
.modal-body{padding:20px 24px;display:flex;flex-direction:column;gap:14px}
.form-section{font-size:var(--tx-etiqueta);font-weight:var(--tx-peso-titulo);color:var(--tx-color-suave);text-transform:uppercase;letter-spacing:.8px;padding-bottom:8px;border-bottom:1px solid var(--border);margin-bottom:4px}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.form-group{display:flex;flex-direction:column;gap:5px}
.form-group.full{grid-column:span 2}
.form-label{font-size:var(--tx-etiqueta-form);font-weight:var(--tx-peso-enfasis);color:var(--tx-color-medio);letter-spacing:.3px}
.form-input,.form-select{width:100%;height:42px;background:var(--bg);border:1px solid var(--border);border-radius:10px;padding:0 13px;font-size:13.5px;color:var(--text);font-family:'DM Sans',sans-serif;outline:none;transition:border-color .2s,box-shadow .2s;appearance:none}
.form-input:focus,.form-select:focus{border-color:#b6dfc4;box-shadow:0 0 0 3px rgba(45,223,110,0.07)}
.form-input::placeholder{color:var(--text-soft)}
.modal-foot{padding:0 24px 22px;display:flex;gap:10px;justify-content:flex-end;border-top:1px solid var(--border);padding-top:16px}
 
/* ── RESPONSIVE EXTRA ── */
@media(max-width:900px){
  .form-row{grid-template-columns:1fr}
  .form-group.full{grid-column:auto}
  .page-header{flex-direction:column;align-items:flex-start}
}
@media(max-width:640px){
  thead th:nth-child(2),tbody td:nth-child(2),
  thead th:nth-child(4),tbody td:nth-child(4){display:none}
}
 
 /* ── TOAST SISTEMA ── */
/* ── MODAL CONFIRMAR INACTIVAR ── */
.confirm-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.42);
  backdrop-filter: blur(4px);
  z-index: 700;
  display: none;
  align-items: center;
  justify-content: center;
  padding: 20px;
}

.confirm-backdrop.open {
  display: flex;
}

.confirm-card {
  width: 100%;
  max-width: 520px;
  background: #ffffff;
  border: 1px solid var(--border);
  border-radius: 22px;
  padding: 24px;
  box-shadow: 0 28px 80px rgba(0, 0, 0, 0.18);
  animation: modalIn .28s cubic-bezier(.22,1,.36,1) both;
}

.confirm-head {
  display: flex;
  gap: 14px;
  align-items: flex-start;
}

.confirm-icon {
  width: 48px;
  height: 48px;
  border-radius: 14px;
  background: #fff1f2;
  border: 1px solid #fecaca;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.confirm-icon svg {
  width: 22px;
  height: 22px;
  stroke: #dc2626;
  fill: none;
  stroke-width: 2;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.confirm-card h3 {
  font-family: 'Syne', sans-serif;
  font-size: 20px;
  font-weight: 800;
  color: var(--text);
  margin-bottom: 6px;
}

.confirm-card p {
  font-size: 14px;
  color: var(--text-mid);
  line-height: 1.4;
  margin: 0;
}

.confirm-card p strong {
  color: var(--text);
  font-weight: 800;
}

.confirm-message {
  margin-top: 18px;
  padding: 14px 16px;
  border-radius: 14px;
  background: #fef3c7;
  color: #92400e;
  border: 1px solid #fde68a;
  font-size: 13.5px;
  font-weight: 600;
  line-height: 1.4;
}

.confirm-actions {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  margin-top: 22px;
}






</style>
<link rel="stylesheet" href="validacion_trabajador.css">
<link rel="stylesheet" href="../components/select_buscador.css">
</head>
<body>
<?php if (isset($_GET['debug']) && $_GET['debug'] === '1') {
  echo '<div style="position:fixed;top:60px;left:12px;background:#ef4444;color:#fff;z-index:99999;padding:8px 12px;border-radius:6px;font-weight:700;box-shadow:0 6px 18px rgba(0,0,0,0.2)">DEBUG: RENDER OK</div>';
}
?>
 

<div class="modal-backdrop" id="modalNuevo">
  <div class="modal">

    <div class="modal-head">
      <div class="modal-head-left">
        <div class="modal-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
            <path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/>
            <circle cx="9" cy="7" r="4"/>
            <line x1="19" y1="8" x2="19" y2="14"/>
            <line x1="22" y1="11" x2="16" y2="11"/>
          </svg>
        </div>
        <div>
          <div class="modal-title">Nuevo Trabajador</div>
          <div class="modal-sub">Completa la información del nuevo integrante</div>
        </div>
      </div>
      <button class="modal-close" onclick="closeModal()">&#215;</button>
    </div>

<form action="crear.php" method="POST" data-validar-trabajador novalidate><?php echo campoCsrf(); ?>      <div class="modal-body">

        <?php if ($erroresNuevo): ?>
          <div class="form-errors-summary">
            No se guardó el trabajador. Revisa <?php echo count($erroresNuevo) === 1 ? 'el campo marcado' : 'los ' . count($erroresNuevo) . ' campos marcados'; ?>.
          </div>
        <?php endif; ?>

        <div class="form-section">Información personal</div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Nombres</label>
            <input class="form-input<?php echo claseError($erroresNuevo, 'nombres'); ?>" type="text" name="nombres" maxlength="100" value="<?php echo htmlspecialchars(valorNuevo('nombres')); ?>" placeholder="Ej. María Alejandra" required>
            <?php echo mensajeError($erroresNuevo, 'nombres'); ?>
          </div>

          <div class="form-group">
            <label class="form-label">Apellidos</label>
            <input class="form-input<?php echo claseError($erroresNuevo, 'apellidos'); ?>" type="text" name="apellidos" maxlength="100" value="<?php echo htmlspecialchars(valorNuevo('apellidos')); ?>" placeholder="Ej. Torres García" required>
            <?php echo mensajeError($erroresNuevo, 'apellidos'); ?>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Tipo de documento</label>
            <select class="form-select<?php echo claseError($erroresNuevo, 'id_tipos_documentos'); ?>" name="id_tipos_documentos" required>
              <option value="">Seleccionar</option>
              <?php echo opcionesSelect($opcionesForm['id_tipos_documentos'], valorNuevo('id_tipos_documentos')); ?>
            </select>
            <?php echo mensajeError($erroresNuevo, 'id_tipos_documentos'); ?>
          </div>

          <div class="form-group">
            <label class="form-label">Número de documento</label>
            <input class="form-input<?php echo claseError($erroresNuevo, 'numero_documento'); ?>" type="text" name="numero_documento" maxlength="20" value="<?php echo htmlspecialchars(valorNuevo('numero_documento')); ?>" placeholder="Ej. 1234567890" required>
            <?php echo mensajeError($erroresNuevo, 'numero_documento'); ?>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Género</label>
            <select class="form-select<?php echo claseError($erroresNuevo, 'id_generos'); ?>" name="id_generos" required>
              <option value="">Seleccionar</option>
              <?php echo opcionesSelect($opcionesForm['id_generos'], valorNuevo('id_generos')); ?>
            </select>
            <?php echo mensajeError($erroresNuevo, 'id_generos'); ?>
          </div>

          <div class="form-group">
            <label class="form-label">Fecha de nacimiento</label>
            <input class="form-input<?php echo claseError($erroresNuevo, 'fecha_nacimiento'); ?>" type="date" name="fecha_nacimiento" min="<?php echo fechaMinimaNacimiento(); ?>" max="<?php echo fechaMaximaNacimiento(); ?>" value="<?php echo htmlspecialchars(valorNuevo('fecha_nacimiento')); ?>" required>
            <?php echo mensajeError($erroresNuevo, 'fecha_nacimiento'); ?>
          </div>
        </div>

        <div class="form-row">
          <?php
            // Lugar de nacimiento (opcional): departamento + ciudad del catálogo DIVIPOLA.
            echo camposLugarHtml(
                $conexion,
                ['departamento' => 'departamento_nacimiento', 'ciudad' => 'ciudad_nacimiento', 'etiqueta' => 'Lugar de nacimiento'],
                valorNuevo('ciudad_nacimiento') ?: null,
                valorNuevo('departamento_nacimiento') ?: null,
                $erroresNuevo,
                ['grupo' => 'form-group', 'label' => 'form-label', 'select' => 'form-select']
            );
          ?>
        </div>

        <div class="form-row">

          <div class="form-group">
            <label class="form-label">Nacionalidad</label>
            <select class="form-select<?php echo claseError($erroresNuevo, 'id_nacionalidad'); ?>" name="id_nacionalidad" required>
              <?php echo opcionesSelect($opcionesForm['id_nacionalidad'], valorNuevo('id_nacionalidad')); ?>
            </select>
            <?php echo mensajeError($erroresNuevo, 'id_nacionalidad'); ?>
          </div>
        </div>


 <div class="form-section" style="margin-top:6px">Información complementaria</div>

<div class="form-row">
    <div class="form-group">
        <label class="form-label">Formación educativa</label>
        <select class="form-select<?php echo claseError($erroresNuevo, 'id_formacion_educativa'); ?>" name="id_formacion_educativa" required>
            <option value="">Seleccionar</option>
            <?php echo opcionesSelect($opcionesForm['id_formacion_educativa'], valorNuevo('id_formacion_educativa')); ?>
        </select>
        <?php echo mensajeError($erroresNuevo, 'id_formacion_educativa'); ?>
    </div>

    <div class="form-group">
        <label class="form-label">Tipo de sangre <span style="font-weight:400;color:var(--text-soft)">(opcional)</span></label>
        <select class="form-select<?php echo claseError($erroresNuevo, 'id_sangre'); ?>" name="id_sangre">
            <option value="">Sin información</option>
            <?php echo opcionesSelect($opcionesForm['id_sangre'], valorNuevo('id_sangre')); ?>
        </select>
        <?php echo mensajeError($erroresNuevo, 'id_sangre'); ?>
    </div>
</div>

<div class="form-row">
    <div class="form-group">
        <label class="form-label">Estado civil</label>
        <select class="form-select<?php echo claseError($erroresNuevo, 'id_estado_civil'); ?>" name="id_estado_civil" required>
            <option value="">Seleccionar</option>
            <?php echo opcionesSelect($opcionesForm['id_estado_civil'], valorNuevo('id_estado_civil')); ?>
        </select>
        <?php echo mensajeError($erroresNuevo, 'id_estado_civil'); ?>
    </div>

    <div class="form-group">
        <label class="form-label">Grupo étnico</label>
        <select class="form-select<?php echo claseError($erroresNuevo, 'id_grupos_etnicos'); ?>" name="id_grupos_etnicos" required>
            <?php echo opcionesSelect($opcionesForm['id_grupos_etnicos'], valorNuevo('id_grupos_etnicos')); ?>
        </select>
        <?php echo mensajeError($erroresNuevo, 'id_grupos_etnicos'); ?>
    </div>
</div>

<!-- ORIENTACIÓN SEXUAL (dato sensible: solo se muestra en el formulario y la ficha individual) -->
<div class="form-row">
<div class="form-group full">
<label class="form-label">Orientación sexual / Identidad de género <span style="font-weight:400;color:var(--text-soft)">(opcional)</span></label>
<select class="form-select<?php echo claseError($erroresNuevo, 'orientacion_sexual'); ?>" name="orientacion_sexual">
<option value="">Seleccionar (opcional)</option>
<?php echo opcionesSelect($opcionesForm['orientacion_sexual'], valorNuevo('orientacion_sexual')); ?>
</select>
<?php echo mensajeError($erroresNuevo, 'orientacion_sexual'); ?>
</div>
</div>

<!-- HIJOS-->

<div class="form-section" style="margin-top:6px">Información familiar</div>
<div class="form-row">
  <div class="form-group">
    <label class="form-label">¿Tiene hijos?</label>
    <select class="form-select<?php echo claseError($erroresNuevo, 'tiene_hijos'); ?>" name="tiene_hijos" id="tieneHijos" onchange="toggleNumeroHijos()" required>
      <option value="0" <?php echo valorNuevo('tiene_hijos', '0') === '0' ? 'selected' : ''; ?>>No</option>
      <option value="1" <?php echo valorNuevo('tiene_hijos', '0') === '1' ? 'selected' : ''; ?>>Sí</option>
    </select>
    <?php echo mensajeError($erroresNuevo, 'tiene_hijos'); ?>
  </div>
  <div class="form-group" id="grupoNumeroHijos" style="display:none">
    <label class="form-label">Número de hijos</label>
    <input class="form-input<?php echo claseError($erroresNuevo, 'numero_hijos'); ?>" type="number" name="numero_hijos" id="numeroHijos" min="1" max="15" step="1" inputmode="numeric" value="<?php echo htmlspecialchars(valorNuevo('numero_hijos')); ?>" placeholder="1">
    <?php echo mensajeError($erroresNuevo, 'numero_hijos'); ?>
  </div>
</div>

<!-- ═══════════════════════════════════════════════ -->
<!-- CONTACTO Y DATOS LABORALES                      -->
<!-- ═══════════════════════════════════════════════ -->
<div class="form-section" style="margin-top:6px">Contacto y datos laborales</div>

<div class="form-row">
    <div class="form-group">
        <label class="form-label">Área</label>
        <select class="form-select<?php echo claseError($erroresNuevo, 'id_area'); ?>" id="id_area" name="id_area" required>
            <option value="">Seleccionar área</option>
            <?php echo opcionesSelect($opcionesForm['id_area'], valorNuevo('id_area')); ?>
        </select>
        <?php echo mensajeError($erroresNuevo, 'id_area'); ?>
    </div>

    <div class="form-group">
        <label class="form-label">Cargo</label>
        <select class="form-select<?php echo claseError($erroresNuevo, 'id_cargo'); ?>" id="id_cargo" name="id_cargo" required>
            <?php if ($cargosNuevo): ?>
                <option value="">Seleccionar cargo</option>
                <?php echo opcionesSelect($cargosNuevo, valorNuevo('id_cargo')); ?>
            <?php else: ?>
                <option value="">Seleccione primero un área</option>
            <?php endif; ?>
        </select>
        <?php echo mensajeError($erroresNuevo, 'id_cargo'); ?>
    </div>
</div>

<div class="form-row">
    <div class="form-group">
        <label class="form-label">Fecha de ingreso</label>
        <input class="form-input<?php echo claseError($erroresNuevo, 'fecha_ingreso'); ?>" type="date" name="fecha_ingreso" max="<?php echo date('Y-m-d'); ?>" value="<?php echo htmlspecialchars(valorNuevo('fecha_ingreso')); ?>" required>
        <?php echo mensajeError($erroresNuevo, 'fecha_ingreso'); ?>
    </div>

    <div class="form-group">
        <!-- celda vacía para mantener el grid de 2 columnas -->
    </div>
</div>

<div class="form-row">
    <div class="form-group">
        <label class="form-label">Correo electrónico</label>
        <input class="form-input<?php echo claseError($erroresNuevo, 'correo_personal'); ?>" type="email" name="correo_personal" maxlength="100"
               value="<?php echo htmlspecialchars(valorNuevo('correo_personal')); ?>"
               placeholder="correo@empresa.com" required>
        <?php echo mensajeError($erroresNuevo, 'correo_personal'); ?>
    </div>

    <div class="form-group">
        <label class="form-label">Teléfono / Celular</label>
        <input class="form-input<?php echo claseError($erroresNuevo, 'telefono'); ?>" type="tel" name="telefono" inputmode="numeric" maxlength="14"
               value="<?php echo htmlspecialchars(valorNuevo('telefono')); ?>"
               placeholder="3001234567" required>
        <?php echo mensajeError($erroresNuevo, 'telefono'); ?>
    </div>
</div>

<div class="form-row">
    <div class="form-group">
        <label class="form-label">EPS</label>
        <select class="form-select<?php echo claseError($erroresNuevo, 'id_eps'); ?>" name="id_eps" required>
            <option value="">Seleccionar EPS</option>
            <?php echo opcionesSelect($opcionesForm['id_eps'], valorNuevo('id_eps')); ?>
        </select>
        <?php echo mensajeError($erroresNuevo, 'id_eps'); ?>
    </div>

    <div class="form-group">
        <!-- celda vacía para mantener el grid de 2 columnas -->
    </div>
</div>


<!-- ═══════════════════════════════════════════ -->
<!-- NUEVA SECCIÓN: DOTACIÓN                     -->
<!-- ═══════════════════════════════════════════ -->
<div class="form-section" style="margin-top:6px">
    Dotación (tallas)
    <span style="font-weight:400;text-transform:none;letter-spacing:0;color:var(--text-soft);margin-left:6px;">
        · Para entrega de EPP y uniforme
    </span>
</div>

<div class="form-row">
    <div class="form-group">
        <label class="form-label">Talla camisa</label>
        <select class="form-select<?php echo claseError($erroresNuevo, 'talla_camisa'); ?>" name="talla_camisa">
            <option value="">Seleccionar</option>
            <?php echo opcionesSelect($opcionesForm['talla_camisa'], valorNuevo('talla_camisa')); ?>
        </select>
        <?php echo mensajeError($erroresNuevo, 'talla_camisa'); ?>
    </div>

    <div class="form-group">
        <label class="form-label">Talla pantalón</label>
        <input class="form-input<?php echo claseError($erroresNuevo, 'talla_pantalon'); ?>" type="text" name="talla_pantalon"
               value="<?php echo htmlspecialchars(valorNuevo('talla_pantalon')); ?>"
               placeholder="Ej. 32, 34, M, L"
               maxlength="10">
        <?php echo mensajeError($erroresNuevo, 'talla_pantalon'); ?>
    </div>
</div>

<div class="form-row">
    <div class="form-group">
        <label class="form-label">Talla botas</label>
        <input class="form-input<?php echo claseError($erroresNuevo, 'talla_botas'); ?>" type="text" name="talla_botas"
               value="<?php echo htmlspecialchars(valorNuevo('talla_botas')); ?>"
               placeholder="Ej. 39"
               maxlength="10">
        <?php echo mensajeError($erroresNuevo, 'talla_botas'); ?>
    </div>
    <div class="form-group">
        <!-- celda vacía para mantener el grid de 2 columnas -->
    </div>
</div>



<!-- SECCIÓN: OBSERVACIONES -->
<div class="form-section" style="margin-top:12px">
    Observaciones
</div>

<div class="form-group full">
    <label class="form-label">Observaciones adicionales</label>

    <textarea
        class="form-input<?php echo claseError($erroresNuevo, 'observaciones'); ?>"
        name="observaciones"
        maxlength="2000"
        rows="4"
        placeholder="Escribe aquí cualquier observación relevante sobre el trabajador..."
        style="
            resize:vertical;
            min-height:100px;
            padding:12px 14px;
            font-family:'DM Sans',sans-serif;
            width:100%;
        "
    ><?php echo htmlspecialchars(valorNuevo('observaciones')); ?></textarea>
    <?php echo mensajeError($erroresNuevo, 'observaciones'); ?>
</div>

</div>

<div class="modal-foot">
    <button type="button" class="btn btn-outline" onclick="closeModal()">
        Cancelar
    </button>

    <button type="submit" class="btn btn-primary">
        Guardar trabajador
    </button>
</div>


    </form>
  </div>
</div>
<form action="inactivar.php" method="POST" id="formInactivarListado" style="display:none"><?php echo campoCsrf(); ?>
  <input type="hidden" name="id_trabajador" id="idInactivarListado">
</form>
<div class="confirm-backdrop" id="modalConfirmarInactivar">
  <div class="confirm-card">

    <div class="confirm-head">
      <div class="confirm-icon">
        <svg viewBox="0 0 24 24">
          <path d="M3 6h18"/>
          <path d="M8 6V4h8v2"/>
          <path d="M19 6l-1 14H6L5 6"/>
          <path d="M10 11v5"/>
          <path d="M14 11v5"/>
        </svg>
      </div>

      <div>
        <h3>Marcar trabajador como inactivo</h3>
        <p>
          ¿Deseas marcar a 
          <strong id="nombreTrabajadorInactivar"></strong> 
          como inactivo?
        </p>
      </div>
    </div>

    <div class="confirm-message">
      No se eliminará de la base de datos. Podrás reactivarlo cuando sea necesario.
    </div>

    <div class="confirm-actions">
      <button type="button" class="btn btn-outline" onclick="cerrarModalInactivar()">
        Cancelar
      </button>

      <button type="button" class="btn btn-danger" onclick="ejecutarInactivar()">
        Marcar inactivo
      </button>
    </div>

  </div>
</div>
<div class="layout">
<?php $paginaActiva = 'trabajadores'; require __DIR__ . '/../components/sidebar.php'; ?>
 
<!-- ══ MAIN ══ -->
<!-- == MAIN == -->    
<div class="main">
<!-- TOPBAR -->
  <?php
  $tituloTopbar = 'Trabajadores';
  $busquedaTopbar = ['placeholder' => 'Buscar trabajadores, documentos, reportes...'];
  require __DIR__ . '/../components/topbar.php';
  ?>
 
  <!-- CONTENT -->
<!-- CONTENT -->
<div class="content">
 
  <div class="page-header">
    <div class="page-header-left">
      <div class="page-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg></div>
      <div><div class="page-title">Gesti&#243;n de Trabajadores</div><div class="page-sub">Administra la informaci&#243;n de tu equipo de trabajo.</div></div>
    </div>
    <div class="page-header-right">
      <a href="../dashboard/dashboard.php" class="btn btn-outline"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>Volver al Panel</a>
      <button class="btn btn-primary" onclick="openModal()"><svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>Nuevo Trabajador</button>
    </div>
  </div>
   <div class="mini-stats">

    <div class="mini-stat">
      <div class="mini-stat-icon ic-green">
        <svg viewBox="0 0 24 24">
          <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/>
          <circle cx="9" cy="7" r="4"/>
          <path d="M23 21v-2a4 4 0 00-3-3.87"/>
          <path d="M16 3.13a4 4 0 010 7.75"/>
        </svg>
      </div>
      <div class="mini-stat-body">
        <div class="mini-stat-num nc-green"><?php echo $tw?></div>
        <div class="mini-stat-label">Total trabajadores</div>
        <div class="mini-stat-sub">Activos en el sistema</div>
      </div>
    </div>

    <div class="mini-stat">
      <div class="mini-stat-icon ic-blue">
        <svg viewBox="0 0 24 24">
          <circle cx="12" cy="8" r="4"/>
          <path d="M20 21a8 8 0 10-16 0"/>
        </svg>
      </div>
      <div class="mini-stat-body">
        <div class="mini-stat-num nc-blue"><?php echo $total_hombres?></div>
        <div class="mini-stat-label">Hombres</div>
        <div class="mini-stat-sub"><?php echo $tw>0?round($total_hombres/$tw*100,1):0?>% del total</div>
      </div>
    </div>

    <div class="mini-stat">
      <div class="mini-stat-icon ic-purple">
        <svg viewBox="0 0 24 24">   
          <circle cx="12" cy="8" r="4"/>
          <path d="M20 21a8 8 0 10-16 0"/>
        </svg>
      </div>
      <div class="mini-stat-body">
        <div class="mini-stat-num nc-purple"><?php echo $total_mujeres?></div>
        <div class="mini-stat-label">Mujeres</div>
        <div class="mini-stat-sub"><?php echo $tw>0?round($total_mujeres/$tw*100,1):0?>% del total</div>
      </div>
    </div>

    <div class="mini-stat">
      <div class="mini-stat-icon ic-yellow">
        <svg viewBox="0 0 24 24">
          <path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/>
          <circle cx="9" cy="7" r="4"/>
          <line x1="19" y1="8" x2="19" y2="14"/>
          <line x1="22" y1="11" x2="16" y2="11"/>
        </svg>
      </div>
      <div class="mini-stat-body">
        <div class="mini-stat-num nc-yellow"><?php echo $nuevos_mes?></div>
        <div class="mini-stat-label">Nuevos este mes</div>
        <div class="mini-stat-sub">Ingresos recientes</div>
      </div>
    </div>

  </div>

 
 
<form id="filtroLocalTrabajadores" onsubmit="return false;">
  <div class="filters-bar">
    <div class="filter-group filter-principal">
      <span class="filter-label">Buscar</span>
      <div class="search-wrap">
        <svg viewBox="0 0 24 24">
          <circle cx="11" cy="11" r="8"/>
          <line x1="21" y1="21" x2="16.65" y2="16.65"/>
        </svg>

        <input 
          type="text" 
          id="buscarLocalTrabajador"
          placeholder="Buscar por nombre, documento o correo..."
          autocomplete="off"
        />
      </div>
    </div>

    <div class="filter-group">
      <span class="filter-label">&#193;rea</span>
      <select class="filter-select" id="areaLocalTrabajador">
        <option value="">Todas</option>
        <option value="Producción">Producción</option>
        <option value="Administrativa">Administrativa</option>
        <option value="Logística">Logística</option>
        <option value="Mantenimiento">Mantenimiento</option>
        <option value="Gestión Humana">Gestión Humana</option>
        <option value="SST">SST</option>
      </select>
    </div>

    <div class="filter-group">
      <span class="filter-label">Estado</span>
      <select class="filter-select" id="estadoLocalTrabajador">
        <option value="">Todos</option>
        <option value="activo">Activo</option>
        <option value="inactivo">Inactivo</option>
      </select>
    </div>

    <div class="filter-group" style="justify-content:flex-end">
      <span class="filter-label filter-label-hidden">x</span>
      <div class="filter-buttons">
        <button type="button" class="btn btn-outline" id="btnFiltrarLocal">
          <svg viewBox="0 0 24 24">
            <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>
          </svg>
          Filtrar
        </button>

        <button type="button" class="btn btn-outline-danger" id="btnLimpiarLocal">
          <svg viewBox="0 0 24 24">
            <line x1="18" y1="6" x2="6" y2="18"/>
            <line x1="6" y1="6" x2="18" y2="18"/>
          </svg>
          Limpiar
        </button>
      </div>
    </div>
  </div>
</form>
 
 
  <div class="table-wrap">
    <div class="table-top">
      <span class="table-count">Mostrando <strong><?php echo count($trabajadores)?></strong> de <strong><?php echo $total_filtrado?></strong> trabajadores</span>
      <div class="table-actions">
<a href="trabajadores_pdf.php" target="_blank" class="btn-icon" title="Generar PDF">  <svg viewBox="0 0 24 24">
    <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
    <polyline points="14 2 14 8 20 8"/>
    <line x1="16" y1="13" x2="8" y2="13"/>
    <line x1="16" y1="17" x2="8" y2="17"/>
  </svg>
</a>

<a href="trabajadores_imprimir.php" target="_blank" class="btn-icon" title="Imprimir">
  <svg viewBox="0 0 24 24">
    <polyline points="6 9 6 2 18 2 18 9"/>
    <path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/>
    <rect x="6" y="14" width="12" height="8"/>
  </svg>
</a>
      </div>
    </div>
 
    <?php if(empty($trabajadores)): ?>
    <div class="empty-state">
      <div class="empty-icon"><svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg></div>
      <div class="empty-title">No se encontraron trabajadores</div>
      <div class="empty-sub">Intenta ajustar los filtros o agrega un nuevo trabajador.</div>
    </div>
    <?php else: ?>
    <table>
      <thead><tr>
        <th><span class="sort-th">TRABAJADOR <svg viewBox="0 0 24 24"><path d="M7 15l5 5 5-5M7 9l5-5 5 5"/></svg></span></th>
        <th><span class="sort-th">DOCUMENTO <svg viewBox="0 0 24 24"><path d="M7 15l5 5 5-5M7 9l5-5 5 5"/></svg></span></th>
        <th><span class="sort-th">&#193;REA <svg viewBox="0 0 24 24"><path d="M7 15l5 5 5-5M7 9l5-5 5 5"/></svg></span></th>
        <th><span class="sort-th">HIJOS <svg viewBox="0 0 24 24"><path d="M7 15l5 5 5-5M7 9l5-5 5 5"/></svg></span></th>
        <th><span class="sort-th">CARGO <svg viewBox="0 0 24 24"><path d="M7 15l5 5 5-5M7 9l5-5 5 5"/></svg></span></th>
        <th><span class="sort-th">CORREO / CONTACTO <svg viewBox="0 0 24 24"><path d="M7 15l5 5 5-5M7 9l5-5 5 5"/></svg></span></th>
        <th><span class="sort-th">ESTADO <svg viewBox="0 0 24 24"><path d="M7 15l5 5 5-5M7 9l5-5 5 5"/></svg></span></th>
        <th>ACCIONES</th>
      </tr></thead>
  <tbody>
        <?php foreach($trabajadores as $t):
          $id=$t['id_trabajador']??$t['id']??0;
          $nom=$t['nombres']??'';
          $ape=$t['apellidos']??'';
          $doc=$t['numero_documento']??'-';
          $email=$t['correo_personal']??'-';
          $tel=$t['telefono']??'';
          $est=(int)($t['estado']??1);
          
          $ar=$t['nombre_area']??'';
          $carg=$t['nombre_cargo']??'-';
        ?>
        <tr>
          <td>
            <div class="worker-cell">
              <div class="worker-avatar <?php echo claseAvatar($id) ?>"><?php echo htmlspecialchars(inicialesAvatar($nom, $ape)) ?></div>
              <div>
                <div class="worker-name"><?php echo htmlspecialchars("$nom $ape")?></div>
                <div class="worker-id">
                  <?php echo htmlspecialchars(generoNombre($t['id_generos'] ?? 0)); ?>
                </div>
              </div>
            </div>
          </td>
          <td><?php echo htmlspecialchars($doc)?></td>
          <td>
            <?php
            if ($ar !== '') {
                echo areaBadge($ar);
            } else {
                echo '<span style="color:#9ca3af; font-size: 13px;">-</span>';
            }
            ?>
          </td>
          <td>
            <?php
            $tieneHijos = (int)($t['tiene_hijos'] ?? 0);
            $numHijos = (int)($t['numero_hijos'] ?? 0);

            if ($tieneHijos === 1) {
                echo '<span style="display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border-radius:999px;background:#eff6ff;color:#2563eb;border:1px solid #bfdbfe;font-size:12px;font-weight:700;">';
                echo '<svg viewBox="0 0 24 24" style="width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2;">';
                echo '<path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/>';
                echo '<circle cx="9" cy="7" r="4"/>';
                echo '<path d="M23 21v-2a4 4 0 00-3-3.87"/>';
                echo '<path d="M16 3.13a4 4 0 010 7.75"/>';
                echo '</svg>';
                if ($numHijos > 0) {
                    echo $numHijos . ' ' . ($numHijos === 1 ? 'hijo' : 'hijos');
                } else {
                    echo 'Sí';
                }
                echo '</span>';
            } else {
                echo '<span style="color:#9ca3af;font-size:13px;">Sin hijos</span>';
            }
            ?>
          </td>
          <td style="font-size: 13px; color: #374151;"><?php echo htmlspecialchars($carg)?></td>
          <td>
            <div class="contact-email"><?php echo htmlspecialchars($email)?></div>
            <?php if($tel):?><div class="contact-phone"><?php echo htmlspecialchars($tel)?></div><?php endif;?>
          </td>
          <td>
            <?php $est = (int)($t['estado'] ?? 1); ?>
            <?php if ($est === 1): ?>
              <span class="badge-activo">
                <span class="badge-dot dot-green"></span>
                Activo
              </span>
            <?php else: ?>
              <span class="badge-inactivo">
                <span class="badge-dot dot-red"></span>
                Inactivo
              </span>
            <?php endif; ?>
          </td>
          <td>
            <div class="acc-btns">

    <a href="ver.php?id=<?php echo $id?>" class="acc-btn" data-tip="Ver detalle" aria-label="Ver detalle">
      <svg viewBox="0 0 24 24" aria-hidden="true">
        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
        <circle cx="12" cy="12" r="3"/>
      </svg><span class="acc-label">Ver detalle</span>
    </a>

    <a href="editar.php?id=<?php echo $id?>" class="acc-btn" data-tip="Editar" aria-label="Editar">
      <svg viewBox="0 0 24 24" aria-hidden="true">
        <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/>
        <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>
      </svg><span class="acc-label">Editar</span>
    </a>

    <?php if ($est === 1): ?>

      <button type="button"
              class="acc-btn danger"
              data-tip="Desactivar" aria-label="Desactivar"
              onclick="confirmarEliminar(<?php echo $id?>,'<?php echo htmlspecialchars($nom . ' ' . $ape, ENT_QUOTES, 'UTF-8')?>')">
        <svg viewBox="0 0 24 24" aria-hidden="true">
          <path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/>
          <circle cx="8.5" cy="7" r="4"/>
          <line x1="18" y1="8" x2="23" y2="13"/>
          <line x1="23" y1="8" x2="18" y2="13"/>
        </svg><span class="acc-label">Desactivar</span>
      </button>

    <?php else: ?>

      <form action="activar.php" method="POST" style="display:inline"><?php echo campoCsrf(); ?>
        <input type="hidden" name="id_trabajador" value="<?php echo (int)$id ?>">
        <button type="submit" class="acc-btn reactivate" data-tip="Reactivar" aria-label="Reactivar">
        <svg viewBox="0 0 24 24" aria-hidden="true">
          <polyline points="1 4 1 10 7 10"/>
          <path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/>
        </svg><span class="acc-label">Reactivar</span>
        </button>
      </form>

    <?php endif; ?>

</div>
        </td>
      </tr>
      <?php endforeach;?>
      </tbody>
    </table>
      </div>
    </div>
    <?php endif;?>
  </div>
 
 
</div><!-- /content -->
<footer style="text-align:center;padding:12px;font-size:11.5px;color:var(--text-soft);border-top:1px solid var(--border);background:var(--white)">
  &#127807; <strong style="color:var(--green-dark)">PlastyPetco S.A.S</strong> &middot; Sistema de Gesti&#243;n RRHH + SG-SST &nbsp;&middot;&nbsp; Versi&#243;n 1.0.0
</footer>
</div><!-- /main -->
</div><!-- /layout -->

<?php
// Resultado de la última acción, con el componente compartido de avisos
// (views/components/notificaciones.php + assets/js/notificaciones.js).
switch ($_GET['mensaje'] ?? '') {
    case 'creado':
        $nuevoId = (int)($_GET['nuevo_id'] ?? 0);
        echo avisoAlCargar('ok', 'Trabajador creado correctamente. Ahora puedes continuar registrando su contratación.',
            $nuevoId > 0 ? ['texto' => 'Ir a Contratación', 'href' => '../contratacion/index.php?nuevo=1&trabajador=' . $nuevoId]
                         : ['texto' => 'Ir a Contratación', 'href' => '../contratacion/index.php'],
            null, 10000);
        break;
    case 'reactivado':
        echo avisoAlCargar('ok', 'El trabajador vuelve a estar activo en el sistema.', null, 'Trabajador reactivado correctamente.');
        break;
    case 'inactivado':
        echo avisoAlCargar('aviso', 'No se eliminó de la base de datos.', null, 'Trabajador marcado como inactivo.');
        break;
    case 'id_invalido':
        echo avisoAlCargar('error', 'No se pudo completar la acción.', null, 'ID de trabajador inválido.');
        break;
}
?>
 
<script>
function toggleSidebar(){document.getElementById('sidebar').classList.toggle('open');document.getElementById('sidebarOverlay').classList.toggle('open')}
function closeSidebar(){document.getElementById('sidebar').classList.remove('open');document.getElementById('sidebarOverlay').classList.remove('open')}
document.querySelectorAll('.sidebar .nav-item, .sidebar .nav-logout').forEach(function(link){
  link.addEventListener('click', function(){
    closeSidebar();
  });
});
function toggleProfile(){document.getElementById('profileWrap').classList.toggle('open')}
document.addEventListener('click',function(e){var w=document.getElementById('profileWrap');if(w&&!w.contains(e.target))w.classList.remove('open')});
function openModal(){document.getElementById('modalNuevo').classList.add('open')}
function closeModal(){document.getElementById('modalNuevo').classList.remove('open')}
document.getElementById('modalNuevo').addEventListener('click',function(e){if(e.target===this)closeModal()});
let trabajadorInactivarId = null;

function confirmarEliminar(id, nombre) {
    trabajadorInactivarId = id;

    const nombreSpan = document.getElementById('nombreTrabajadorInactivar');
    const modal = document.getElementById('modalConfirmarInactivar');

    if (nombreSpan) {
        nombreSpan.textContent = nombre;
    }

    if (modal) {
        modal.classList.add('open');
    }
}

function cerrarModalInactivar() {
    trabajadorInactivarId = null;

    const modal = document.getElementById('modalConfirmarInactivar');
    if (modal) {
        modal.classList.remove('open');
    }
}

function ejecutarInactivar() {
    if (trabajadorInactivarId !== null) {
        document.getElementById('idInactivarListado').value = trabajadorInactivarId;
        document.getElementById('formInactivarListado').submit();
    }
}
document.addEventListener('keydown',function(e){if((e.ctrlKey||e.metaKey)&&e.key==='k'){e.preventDefault();document.querySelector('.search-wrap input').focus()}});
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const inputBuscar = document.getElementById('buscarLocalTrabajador');
    const selectArea = document.getElementById('areaLocalTrabajador');
    const selectEstado = document.getElementById('estadoLocalTrabajador');
    const btnFiltrar = document.getElementById('btnFiltrarLocal');
    const btnLimpiar = document.getElementById('btnLimpiarLocal');

    const filas = Array.from(document.querySelectorAll('.table-wrap tbody tr'));

    function normalizar(texto) {
        return (texto || '')
            .toString()
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .trim();
    }

    function filtrarTrabajadores() {
        const busqueda = normalizar(inputBuscar.value);
        const area = normalizar(selectArea.value);
        const estado = normalizar(selectEstado.value);

        let visibles = 0;

        filas.forEach(function (fila) {
            const textoCompleto = normalizar(fila.innerText);

            const celdaArea = normalizar(
                fila.querySelector('td:nth-child(3)')?.innerText || ''
            );

            const celdaEstado = normalizar(
                fila.querySelector('td:nth-child(6)')?.innerText || ''
            );

            const coincideBusqueda =
                busqueda === '' || textoCompleto.includes(busqueda);

            const coincideArea =
                area === '' || celdaArea.includes(area);

            let coincideEstado = true;

            if (estado === 'activo') {
                coincideEstado = celdaEstado.includes('activo') && !celdaEstado.includes('inactivo');
            }

            if (estado === 'inactivo') {
                coincideEstado = celdaEstado.includes('inactivo');
            }

            const mostrar = coincideBusqueda && coincideArea && coincideEstado;

            fila.style.display = mostrar ? '' : 'none';

            if (mostrar) visibles++;
        });

        const tableCount = document.querySelector('.table-count');
        if (tableCount) {
            tableCount.innerHTML = `Mostrando <strong>${visibles}</strong> de <strong>${filas.length}</strong> trabajadores`;
        }

        const pagInfo = document.querySelector('.pag-info');
        if (pagInfo) {
            pagInfo.textContent = `Mostrando ${visibles} de ${filas.length} trabajadores`;
        }
    }

    inputBuscar.addEventListener('input', filtrarTrabajadores);
    selectArea.addEventListener('change', filtrarTrabajadores);
    selectEstado.addEventListener('change', filtrarTrabajadores);
    btnFiltrar.addEventListener('click', filtrarTrabajadores);

btnLimpiar.addEventListener('click', function () {
    inputBuscar.value = '';
    selectArea.value = '';
    selectEstado.value = '';
    filtrarTrabajadores();
});
});
</script>


  <script>
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
</script>
<script>

document.getElementById("id_area").addEventListener("change", function(){

    let area = this.value;

    let cargo = document.getElementById("id_cargo");

    cargo.innerHTML =
        "<option>Cargando...</option>";

    fetch("obtener_cargos.php?id_area=" + area)

    .then(res => res.json())

    .then(datos=>{

        cargo.innerHTML =
            '<option value="">Seleccionar cargo</option>';

        datos.forEach(c=>{

            cargo.innerHTML +=
            `<option value="${c.id_cargo}">
                ${c.nombre_cargo}
            </option>`;

        });

    });

});
/* ===========================
   CARGAR CARGOS POR ÁREA
=========================== */

const selectArea = document.getElementById("id_area");
const selectCargo = document.getElementById("id_cargo");

if(selectArea && selectCargo){

    selectArea.addEventListener("change", function(){

        let idArea = this.value;

        selectCargo.innerHTML =
            '<option value="">Cargando...</option>';

        if(idArea === ""){

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

                selectCargo.innerHTML += `
                    <option value="${cargo.id_cargo}">
                        ${cargo.nombre_cargo}
                    </option>
                `;

            });

        })

        .catch(error => {

            console.error(error);

            selectCargo.innerHTML =
                '<option value="">Error al cargar cargos</option>';

        });

    });

}
</script>
<?php echo scriptCiudadesPorDepartamento($conexion); ?>
<script src="../components/select_buscador.js"></script>
<script src="../components/lugares.js"></script>
<script src="validacion_trabajador.js"></script>
<?php if ($erroresNuevo): ?>
<script>
// crear.php rechazó el formulario: se reabre con los datos y errores por campo.
openModal();
toggleNumeroHijos();
</script>
<?php endif; ?>
</body>
</html>