<?php
/**
 * ============================================================================
 *  MÓDULO: EXÁMENES MÉDICOS (SG-SST)
 *  views/examenes/index.php
 * ----------------------------------------------------------------------------
 *  Ficha funcional: §2.4.7 del Design System PlastyPetco RRHH v2.0.
 *  Patrón de interfaz: Listado (§23.2) + Modal de programación (§23.3).
 *
 *  Este módulo es la FUENTE de la información que Perfil de Salud solo
 *  CONSOLIDA (ver conversación anterior). Al registrar un resultado aquí:
 *    1. Se actualiza examenes_medicos (fecha_realizado, resultado, estado)
 *    2. Se hace UPSERT de perfil_salud (aptitud, fecha_evaluacion, fecha_vencimiento)
 *    3. Se inserta un evento en historial_medico (alimenta el Timeline)
 *  Esta cadena vive en registrar_resultado.php, no en este archivo.
 * ============================================================================
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/conexion.php';

$nombres    = $_SESSION['nombres'] ?? $_SESSION['nombre'] ?? 'Administrador';
$apellidos  = $_SESSION['apellidos'] ?? '';
$inicial    = strtoupper(substr($nombres, 0, 1) . substr($apellidos !== '' ? $apellidos : $nombres, 0, 1));

if (!function_exists('avInit')) {
    function avInit($n, $a) {
        $n = trim($n ?? ''); $a = trim($a ?? '');
        return ($n !== '' ? strtoupper(substr($n, 0, 1)) : '') . ($a !== '' ? strtoupper(substr($a, 0, 1)) : '');
    }
}
if (!function_exists('avatarColor')) {
    function avatarColor($texto) {
        $colores = ['linear-gradient(135deg,#22c55e,#15803d)','linear-gradient(135deg,#3b82f6,#1d4ed8)',
            'linear-gradient(135deg,#8b5cf6,#6d28d9)','linear-gradient(135deg,#f59e0b,#d97706)',
            'linear-gradient(135deg,#ec4899,#be185d)','linear-gradient(135deg,#14b8a6,#0f766e)'];
        return $colores[abs(crc32((string)$texto)) % count($colores)];
    }
}

/** Etiqueta legible por tipo de examen */
function tipoExamenLabel(string $tipo): string {
    $map = ['ingreso' => 'Ingreso', 'periodico' => 'Periódico', 'egreso' => 'Egreso'];
    return $map[$tipo] ?? ucfirst($tipo);
}

/** Badge de vencimiento — misma terna oficial warn/ok/urgent (§18.3), ahora
 *  aplicada a su caso de uso canónico: vencimiento de examen programado. */
function examenBadgeEstado(string $estado, ?string $fechaProgramada): string {
    if ($estado === 'realizado') {
        return '<span class="badge-venc badge-ok">Realizado</span>';
    }
    if (!$fechaProgramada) {
        return '<span class="badge-venc badge-info">Sin fecha</span>';
    }
    $dias = (strtotime($fechaProgramada) - time()) / 86400;
    if ($dias < 0)   return '<span class="badge-venc badge-urgent">Vencido</span>';
    if ($dias <= 30) return '<span class="badge-venc badge-warn">Por vencer</span>';
    return '<span class="badge-venc badge-ok">Vigente</span>';
}
function fmtFechaExamen(?string $f): string {
    if (!$f) return '—';
    $t = strtotime($f);
    return $t ? date('d/m/Y', $t) : '—';
}

$mensajeToast = isset($_GET['mensaje']) ? htmlspecialchars($_GET['mensaje'], ENT_QUOTES, 'UTF-8') : null;
$tipoToast    = $_GET['tipo'] ?? 'ok';

$buscar = trim($_GET['buscar'] ?? '');
$tipoFiltro = trim($_GET['tipoExamen'] ?? '');
$estadoFiltro = trim($_GET['estado'] ?? '');

$examenes = [];
$vencidos = $porVencer = $realizadosMes = $programados = 0;
$errorConsulta = null;
$trabajadoresParaSelect = [];

try {
    /* Trabajadores para el <select> del modal "Programar examen" */
    $trabajadoresParaSelect = $conexion->query("
        SELECT id_trabajador, nombres, apellidos, numero_documento
        FROM trabajadores
        WHERE estado = 1
        ORDER BY nombres, apellidos
    ")->fetchAll(PDO::FETCH_ASSOC);

    $where = ["1=1"];
    $params = [];
    if ($buscar !== '') {
        $where[] = "(t.nombres LIKE ? OR t.apellidos LIKE ? OR t.numero_documento LIKE ?)";
        $params[] = "%{$buscar}%"; $params[] = "%{$buscar}%"; $params[] = "%{$buscar}%";
    }
    if ($tipoFiltro !== '') {
        $where[] = "e.tipo = ?";
        $params[] = $tipoFiltro;
    }
    if ($estadoFiltro !== '') {
        $where[] = "e.estado = ?";
        $params[] = $estadoFiltro;
    }

    $sql = "
        SELECT
            e.id_examen, e.id_trabajador, e.tipo, e.fecha_programada,
            e.fecha_realizado, e.resultado, e.estado,
            t.nombres, t.apellidos, t.numero_documento
        FROM examenes_medicos e
        JOIN trabajadores t ON t.id_trabajador = e.id_trabajador
        WHERE " . implode(' AND ', $where) . "
        ORDER BY
            (e.estado = 'programado') DESC,
            e.fecha_programada ASC
    ";
    $stmt = $conexion->prepare($sql);
    $stmt->execute($params);
    $examenes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    /* KPIs (§2.4.7) */
    $vencidos = (int)$conexion->query("
        SELECT COUNT(*) FROM examenes_medicos
        WHERE estado = 'programado' AND fecha_programada < CURDATE()
    ")->fetchColumn();

    $porVencer = (int)$conexion->query("
        SELECT COUNT(*) FROM examenes_medicos
        WHERE estado = 'programado'
          AND fecha_programada BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    ")->fetchColumn();

    $realizadosMes = (int)$conexion->query("
        SELECT COUNT(*) FROM examenes_medicos
        WHERE estado = 'realizado'
          AND MONTH(fecha_realizado) = MONTH(CURDATE())
          AND YEAR(fecha_realizado) = YEAR(CURDATE())
    ")->fetchColumn();

    $programados = (int)$conexion->query("
        SELECT COUNT(*) FROM examenes_medicos WHERE estado = 'programado'
    ")->fetchColumn();

} catch (PDOException $e) {
    $errorConsulta = 'No fue posible cargar la información de Exámenes Médicos. '
        . 'Verifica que la tabla examenes_medicos exista (examenes_medicos.sql).';
    // error_log($e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Exámenes Médicos | PlastyPetco</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
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
.nav-logout:hover{background:rgba(248,113,113,.08)}
.main{margin-left:var(--sidebar-w);flex:1;display:flex;flex-direction:column;min-height:100vh}
.topbar{height:var(--topbar-h);background:#fff;border-bottom:1px solid var(--border);box-shadow:0 1px 8px rgba(0,0,0,.05);
  display:flex;align-items:center;justify-content:space-between;padding:0 28px;position:sticky;top:0;z-index:50}
.topbar-title{font-family:'Syne';font-size:clamp(18px,2vw,22px);font-weight:800}
.profile-avatar{width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--green),var(--green-dim));
  display:flex;align-items:center;justify-content:center;font-family:'Syne';font-weight:800;color:#021a08;font-size:13px}
.content{flex:1;padding:24px 28px;display:flex;flex-direction:column;gap:20px}
.page-header{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap}
.page-header-left{display:flex;align-items:center;gap:14px}
.page-icon{width:52px;height:52px;background:var(--green-mist);border:1px solid rgba(45,223,110,.2);border-radius:14px;
  display:flex;align-items:center;justify-content:center;color:var(--green-dark);flex-shrink:0}
.page-icon svg{width:24px;height:24px}
.page-title{font-family:'Syne';font-size:clamp(20px,2.5vw,26px);font-weight:800}
.page-sub{font-size:13px;color:var(--text-soft);margin-top:3px}
.page-header-right{display:flex;gap:10px}
.btn{font-family:'DM Sans';font-size:13px;border-radius:10px;cursor:pointer;transition:all .2s;
  display:inline-flex;align-items:center;gap:7px;border:none;text-decoration:none}
.btn svg{width:16px;height:16px}
.btn-new{background:linear-gradient(135deg,var(--green),var(--green-dim));padding:10px 18px;
  font-family:'Syne';font-weight:700;color:#021a08;box-shadow:0 3px 14px rgba(45,223,110,.25)}
.btn-new:hover{transform:translateY(-1px);box-shadow:0 6px 20px rgba(45,223,110,.35)}
.btn-cancel{background:none;border:1px solid var(--border);padding:10px 20px;color:var(--text-mid)}
.btn-cancel:hover{background:var(--bg)}
.btn-filter{background:#fff;border:1px solid var(--border);height:40px;padding:0 16px;color:var(--text-mid)}
.btn-filter:hover{border-color:#b6dfc4;background:#f0f8f3}
.btn-clear{color:#dc2626;border-color:#fecaca}
.btn-clear:hover{background:#fff1f2;border-color:#fca5a5}
.acc-btn{width:30px;height:30px;border-radius:8px;background:none;border:1px solid transparent;
  display:flex;align-items:center;justify-content:center;color:var(--text-soft);transition:all .18s;cursor:pointer;text-decoration:none}
.acc-btn svg{width:15px;height:15px}
.acc-btn:hover{background:var(--bg);border-color:var(--border);color:var(--green-dark)}
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
.badge-tipo{display:inline-flex;border-radius:20px;padding:3px 11px;font-size:11.5px;font-weight:500;
  background:#f3f4f6;color:#374151;border:1px solid #e5e7eb}
.badge-venc{border-radius:20px;padding:3px 9px;font-size:10.5px;font-weight:600;display:inline-flex;align-items:center;gap:5px}
.badge-ok{background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0}
.badge-warn{background:#fff7ed;color:#d97706;border:1px solid #fed7aa}
.badge-urgent{background:#fff1f2;color:#dc2626;border:1px solid #fecaca}
.badge-info{background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe}
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
@media(max-width:900px){.sidebar{transform:translateX(-100%)}.sidebar.open{transform:translateX(0)}.main{margin-left:0}
  .content{padding:16px;gap:14px}.mini-stats{grid-template-columns:1fr 1fr}.form-row{grid-template-columns:1fr}.form-group.full{grid-column:span 1}}
@media(max-width:640px){thead th:nth-child(2),tbody td:nth-child(2){display:none}}
</style>
</head>
<body>
<div class="layout">

  <!-- SIDEBAR — MPA, rutas relativas -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-head">
      <div class="sidebar-logo"><svg viewBox="0 0 24 24"><path d="M12 2 3 7v6c0 5 4 8 9 9 5-1 9-4 9-9V7z"/></svg></div>
      <div><div class="sidebar-brand">Plasty<em>Petco</em></div><div class="sidebar-tag">RRHH · SG-SST</div></div>
    </div>
    <nav class="sidebar-nav">
      <div class="nav-section">Principal</div>
      <a href="../dashboard/index.php" class="nav-item">
        <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/></svg>Resumen
      </a>
      <div class="nav-section">Gestión</div>
      <a href="../trabajadores/index.php" class="nav-item">
        <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>Trabajadores
      </a>
      <a href="../contratacion/index.php" class="nav-item">
        <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>Contratación
      </a>
      <a href="../novedades/index.php" class="nav-item">
        <svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/></svg>Novedades
      </a>
      <a href="../vacaciones/index.php" class="nav-item">
        <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>Vacaciones
      </a>
      <div class="nav-section">SG-SST</div>
      <a href="../perfil_salud/index.php" class="nav-item">
        <svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>Perfil de Salud
      </a>
      <a href="index.php" class="nav-item active">
        <svg viewBox="0 0 24 24"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>Exámenes Médicos
      </a>
      <a href="../incidentes/index.php" class="nav-item">
        <svg viewBox="0 0 24 24"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/></svg>Incidentes
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
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/></svg>Roles y Permisos
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
      <div class="topbar-title">Exámenes Médicos</div>
      <div class="profile-avatar"><?php echo htmlspecialchars($inicial) ?></div>
    </header>

    <div class="content">

<?php if ($errorConsulta): ?>
      <div class="error-banner"><?php echo htmlspecialchars($errorConsulta) ?></div>
<?php endif; ?>

      <div class="page-header">
        <div class="page-header-left">
          <div class="page-icon"><svg viewBox="0 0 24 24"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg></div>
          <div>
            <div class="page-title">Exámenes Médicos</div>
            <div class="page-sub">Programación y control de exámenes ocupacionales (ingreso, periódico, egreso)</div>
          </div>
        </div>
        <div class="page-header-right">
          <button class="btn btn-new" onclick="document.getElementById('backdropProgramar').classList.add('open')">
            <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Programar examen
          </button>
        </div>
      </div>

      <div class="mini-stats">
        <div class="stat-card">
          <div class="stat-icon ic-red"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></div>
          <div class="stat-num"><?php echo $vencidos ?></div>
          <div class="stat-label">Exámenes vencidos</div>
        </div>
        <div class="stat-card">
          <div class="stat-icon ic-yellow"><svg viewBox="0 0 24 24"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/></svg></div>
          <div class="stat-num"><?php echo $porVencer ?></div>
          <div class="stat-label">Por vencer (30 días)</div>
        </div>
        <div class="stat-card">
          <div class="stat-icon ic-green"><svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div>
          <div class="stat-num"><?php echo $realizadosMes ?></div>
          <div class="stat-label">Realizados este mes</div>
        </div>
        <div class="stat-card">
          <div class="stat-icon ic-blue"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/></svg></div>
          <div class="stat-num"><?php echo $programados ?></div>
          <div class="stat-label">Programados</div>
        </div>
      </div>

      <form method="get" action="index.php">
        <div class="filters-bar">
          <div class="search-wrap">
            <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" name="buscar" placeholder="Buscar por nombre o documento..." value="<?php echo htmlspecialchars($buscar) ?>">
          </div>
          <select class="filter-select" name="tipoExamen">
            <option value="">Todos los tipos</option>
            <option value="ingreso" <?php echo $tipoFiltro === 'ingreso' ? 'selected' : '' ?>>Ingreso</option>
            <option value="periodico" <?php echo $tipoFiltro === 'periodico' ? 'selected' : '' ?>>Periódico</option>
            <option value="egreso" <?php echo $tipoFiltro === 'egreso' ? 'selected' : '' ?>>Egreso</option>
          </select>
          <select class="filter-select" name="estado">
            <option value="">Todos los estados</option>
            <option value="programado" <?php echo $estadoFiltro === 'programado' ? 'selected' : '' ?>>Programado</option>
            <option value="realizado" <?php echo $estadoFiltro === 'realizado' ? 'selected' : '' ?>>Realizado</option>
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
          <div class="table-count">Mostrando <strong><?php echo count($examenes) ?></strong> examen(es)</div>
        </div>

        <?php if (empty($examenes)): ?>
          <div class="empty-state">
            <div class="empty-icon"><svg viewBox="0 0 24 24"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg></div>
            <div class="empty-title">No hay exámenes registrados</div>
            <div class="empty-sub">Programa un examen con el botón "+ Programar examen".</div>
          </div>
        <?php else: ?>
        <table>
          <thead>
            <tr><th>Trabajador</th><th>Tipo</th><th>Fecha programada</th><th>Resultado</th><th>Estado</th><th>Acciones</th></tr>
          </thead>
          <tbody>
          <?php foreach ($examenes as $e): ?>
            <tr>
              <td>
                <div class="worker-cell">
                  <div class="worker-avatar" style="background:<?php echo avatarColor($e['nombres'] . $e['apellidos']) ?>">
                    <?php echo avInit($e['nombres'], $e['apellidos']) ?>
                  </div>
                  <div>
                    <div class="worker-name"><?php echo htmlspecialchars($e['nombres'] . ' ' . $e['apellidos']) ?></div>
                    <div class="worker-id"><?php echo htmlspecialchars($e['numero_documento']) ?></div>
                  </div>
                </div>
              </td>
              <td><span class="badge-tipo"><?php echo tipoExamenLabel($e['tipo']) ?></span></td>
              <td><?php echo fmtFechaExamen($e['fecha_programada']) ?></td>
              <td>
                <?php if ($e['resultado']): ?>
                  <?php
                    $map = ['apto' => 'badge-ok', 'restriccion' => 'badge-warn', 'no_apto' => 'badge-urgent'];
                    $lbl = ['apto' => 'Apto', 'restriccion' => 'Con restricción', 'no_apto' => 'No apto'];
                  ?>
                  <span class="badge-venc <?php echo $map[$e['resultado']] ?>"><?php echo $lbl[$e['resultado']] ?></span>
                <?php else: ?>
                  <span class="badge-venc badge-info">—</span>
                <?php endif; ?>
              </td>
              <td><?php echo examenBadgeEstado($e['estado'], $e['fecha_programada']) ?></td>
              <td>
                <div class="acc-btns">
                  <?php if ($e['estado'] === 'programado'): ?>
                    <button type="button" class="acc-btn" title="Registrar resultado"
                      onclick="abrirRegistrarResultado(<?php echo (int)$e['id_examen'] ?>, '<?php echo htmlspecialchars(addslashes($e['nombres'] . ' ' . $e['apellidos']), ENT_QUOTES) ?>')">
                      <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                    </button>
                  <?php else: ?>
                    <a class="acc-btn" title="Ver en Perfil de Salud" href="../perfil_salud/index.php?vista=detalle&id=<?php echo (int)$e['id_trabajador'] ?>">
                      <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    </a>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </div>

      <!-- Modal: Programar examen (§23.3) -->
      <div class="modal-backdrop" id="backdropProgramar" onclick="if(event.target===this)this.classList.remove('open')">
        <div class="modal">
          <div class="modal-head">
            <div class="modal-icon"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/></svg></div>
            <div><div class="modal-title">Programar examen médico</div><div class="modal-sub">Selecciona el trabajador y el tipo de examen</div></div>
            <button class="modal-close" onclick="document.getElementById('backdropProgramar').classList.remove('open')">
              <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
          </div>
          <form class="modal-body" method="POST" action="programar_examen.php">
            <div class="form-section">
              <div class="form-row">
                <div class="form-group full">
                  <label class="form-label">Trabajador</label>
                  <select class="form-select" name="id_trabajador" required>
                    <option value="">Seleccione...</option>
                    <?php foreach ($trabajadoresParaSelect as $tw): ?>
                      <option value="<?php echo (int)$tw['id_trabajador'] ?>">
                        <?php echo htmlspecialchars($tw['nombres'] . ' ' . $tw['apellidos'] . ' — ' . $tw['numero_documento']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="form-group">
                  <label class="form-label">Tipo de examen</label>
                  <select class="form-select" name="tipo" required>
                    <option value="ingreso">Ingreso</option>
                    <option value="periodico" selected>Periódico</option>
                    <option value="egreso">Egreso</option>
                  </select>
                </div>
                <div class="form-group">
                  <label class="form-label">Fecha programada</label>
                  <input class="form-input" type="date" name="fecha_programada" required>
                </div>
              </div>
            </div>
            <div class="modal-foot" style="padding:0">
              <button type="button" class="btn btn-cancel" onclick="document.getElementById('backdropProgramar').classList.remove('open')">Cancelar</button>
              <button type="submit" class="btn btn-new">Programar</button>
            </div>
          </form>
        </div>
      </div>

      <!-- Modal: Registrar resultado — este es el que UPSERT-ea perfil_salud -->
      <div class="modal-backdrop" id="backdropResultado" onclick="if(event.target===this)this.classList.remove('open')">
        <div class="modal">
          <div class="modal-head">
            <div class="modal-icon"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></div>
            <div><div class="modal-title">Registrar resultado</div><div class="modal-sub" id="modalResultadoSub">—</div></div>
            <button class="modal-close" onclick="document.getElementById('backdropResultado').classList.remove('open')">
              <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
          </div>
          <form class="modal-body" method="POST" action="registrar_resultado.php">
            <input type="hidden" name="id_examen" id="inputIdExamen" value="">
            <div class="form-section">
              <div class="form-row">
                <div class="form-group">
                  <label class="form-label">Fecha de realización</label>
                  <input class="form-input" type="date" name="fecha_realizado" required>
                </div>
                <div class="form-group">
                  <label class="form-label">Concepto / Resultado</label>
                  <select class="form-select" name="resultado" required>
                    <option value="apto">Apto</option>
                    <option value="restriccion">Apto con restricciones</option>
                    <option value="no_apto">No apto</option>
                  </select>
                </div>
                <div class="form-group full">
                  <label class="form-label">Próxima fecha de examen (vencimiento)</label>
                  <input class="form-input" type="date" name="proxima_fecha">
                </div>
                <div class="form-group full">
                  <label class="form-label">Observaciones</label>
                  <textarea class="form-input" name="observaciones" rows="3" placeholder="Recomendaciones, restricciones detectadas, entidad que realiza el examen..."></textarea>
                </div>
              </div>
            </div>
            <div class="modal-foot" style="padding:0">
              <button type="button" class="btn btn-cancel" onclick="document.getElementById('backdropResultado').classList.remove('open')">Cancelar</button>
              <button type="submit" class="btn btn-new">Guardar resultado</button>
            </div>
          </form>
        </div>
      </div>

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
function abrirRegistrarResultado(idExamen, nombreTrabajador) {
  document.getElementById('inputIdExamen').value = idExamen;
  document.getElementById('modalResultadoSub').textContent = nombreTrabajador;
  document.getElementById('backdropResultado').classList.add('open');
}
document.addEventListener('keydown', function (e) {
  if (e.key === 'Escape') {
    document.querySelectorAll('.modal-backdrop.open').forEach(b => b.classList.remove('open'));
  }
});
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