<?php
require_once __DIR__ . '/../../config/auth.php';
requerirAcceso();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// =========================
// DATOS DEL USUARIO EN TOPBAR (mismo patrón que trabajadores.php)
// =========================
$nombres = $_SESSION['nombres'] ?? $_SESSION['nombre'] ?? 'Administrador';
$apellidos = $_SESSION['apellidos'] ?? '';
$rol_nombre = $_SESSION['rol_nombre'] ?? $_SESSION['rol'] ?? 'RRHH';

$inicial = strtoupper(
    substr($nombres, 0, 1) .
    substr($apellidos !== '' ? $apellidos : $nombres, 0, 1)
);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Vacaciones | PlastyPetco</title>
<?php
// Pendiente de migrar a assets/css/componentes.css (tarjetas, filtros, tabla, avatares):
// esta pantalla todavía usa su propio CSS con los mismos nombres de clase.
$componentesPendientes = true;
require __DIR__ . '/../components/estilos_base.php';
?>
<style>

/* ── LAYOUT ── */

/* ── SIDEBAR ── */

/* ── MAIN ── */

/* ── TOPBAR ── */

/* ── CONTENT ── */

/* ── PAGE HEADER ── */
.page-header{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:4px}
.page-header-left{display:flex;align-items:center;gap:16px}
.page-icon{width:52px;height:52px;background:var(--green-mist);border:1px solid rgba(45,223,110,0.2);border-radius:14px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.page-icon svg{width:26px;height:26px;stroke:var(--green-dim);fill:none;stroke-width:1.7}
.page-title{font-family:var(--tx-fuente-titulos);font-size:var(--tx-titulo-pagina);font-weight:var(--tx-peso-titulo-pagina);color:var(--tx-color);letter-spacing:-.4px;line-height:1.1}
.page-sub{font-size:var(--tx-subtitulo);font-weight:var(--tx-peso-normal);color:var(--tx-color-suave);margin-top:3px}
.page-header-right{display:flex;align-items:center;gap:10px}

/* ── MINI STATS ── */
.mini-stats-vac{display:grid;grid-template-columns:repeat(5,1fr);gap:14px}
.mini-stat{background:var(--white);border:1px solid var(--border);border-radius:16px;padding:18px 20px;display:flex;align-items:center;gap:14px;box-shadow:var(--shadow);transition:transform .2s,box-shadow .2s;animation:fadeUp .5s ease both}
.mini-stat:hover{transform:translateY(-2px);box-shadow:var(--shadow-md)}
.mini-stat-icon{width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.mini-stat-icon svg{width:22px;height:22px;stroke:currentColor;fill:none;stroke-width:1.8}
.mini-stat-num{font-family:'Syne',sans-serif;font-size:30px;font-weight:800;line-height:1}
.mini-stat-label{font-size:12.5px;color:var(--text-mid);margin-top:2px;font-weight:500}
.mini-stat-sub{font-size:11.5px;color:var(--text-soft);margin-top:2px}
.mini-stat:nth-child(1){animation-delay:.04s}
.mini-stat:nth-child(2){animation-delay:.08s}
.mini-stat:nth-child(3){animation-delay:.12s}
.mini-stat:nth-child(4){animation-delay:.16s}
.mini-stat:nth-child(5){animation-delay:.20s}
@keyframes fadeUp{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:none}}

.ic-green{background:#eafaf1;color:#1a9945}
.ic-blue{background:#eff6ff;color:#2563eb}
.ic-red{background:#fff1f2;color:#dc2626}
.ic-yellow{background:#fffbeb;color:#d97706}
.ic-purple{background:#f5f3ff;color:#7c3aed}
.nc-green{color:#1a9945}.nc-blue{color:#2563eb}.nc-red{color:#dc2626}
.nc-yellow{color:#d97706}.nc-purple{color:#7c3aed}

/* ── LAYOUT PRINCIPAL + PANEL LATERAL ── */
.vac-layout{display:flex;gap:16px;align-items:flex-start}
.vac-main-col{flex:1;min-width:0;display:flex;flex-direction:column;gap:16px}

/* ── CALENDARIO MINI ── */
.cal-panel{background:var(--white);border:1px solid var(--border);border-radius:16px;padding:20px;box-shadow:var(--shadow)}
.cal-panel-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px}
.cal-panel-title{font-family:'Syne',sans-serif;font-size:15px;font-weight:800;color:var(--text)}
.cal-nav-btns{display:flex;gap:6px}
.cal-nav-btn{width:28px;height:28px;border-radius:8px;border:1px solid var(--border);background:var(--white);display:flex;align-items:center;justify-content:center;cursor:pointer;color:var(--text-mid);transition:background .15s}
.cal-nav-btn:hover{background:var(--content-bg)}
.cal-nav-btn svg{width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2}
.cal-grid-vac{display:grid;grid-template-columns:repeat(7,1fr);gap:3px}
.cal-dow{font-size:10px;color:var(--text-soft);text-align:center;padding:4px 0;font-weight:700}
.cal-day-vac{font-size:12px;text-align:center;padding:6px 2px;border-radius:8px;color:var(--text-mid)}
.cal-day-vac.empty{visibility:hidden}
.cal-day-vac.today{background:var(--green);color:#021a08;font-weight:800}
.cal-day-vac.vac-ap{background:#dcfce7;color:#15803d;font-weight:700}
.cal-day-vac.vac-pe{background:#dbeafe;color:#1d4ed8;font-weight:700}
.cal-legend-vac{display:flex;gap:16px;margin-top:14px;font-size:11px;color:var(--text-soft);flex-wrap:wrap}
.cal-legend-vac span{display:flex;align-items:center;gap:6px}
.cal-legend-dot{width:9px;height:9px;border-radius:50%}

/* ── TABS ── */
.vac-tabs{display:flex;gap:8px;flex-wrap:wrap}
.vac-tab{display:flex;align-items:center;gap:6px;height:36px;padding:0 14px;border-radius:10px;border:1px solid var(--border);background:var(--white);font-size:12.5px;color:var(--text-mid);cursor:pointer;transition:all .18s;font-family:'DM Sans',sans-serif}
.vac-tab:hover{border-color:#b6dfc4}
.vac-tab.on{background:var(--green-mist);border-color:rgba(45,223,110,.3);color:var(--green-dark);font-weight:var(--tx-peso-enfasis)}
.vac-tab svg{width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:1.8}
.vac-tab-cnt{background:rgba(0,0,0,.06);border-radius:20px;padding:1px 7px;font-size:10.5px;font-weight:700}
.vac-tab.on .vac-tab-cnt{background:rgba(45,223,110,.25)}

/* ── FILTERS BAR (mismo patrón que trabajadores) ── */
.filters-bar{background:var(--white);border:1px solid var(--border);border-radius:14px;padding:14px 18px;display:flex;align-items:flex-end;gap:12px;flex-wrap:wrap;box-shadow:var(--shadow)}
.filter-group{display:flex;flex-direction:column;gap:4px}
.filter-label{font-size:10.5px;font-weight:600;color:var(--text-soft);text-transform:uppercase;letter-spacing:.5px}
.search-wrap{flex:1;min-width:220px;display:flex;align-items:center;gap:8px;background:var(--content-bg);border:1px solid var(--border);border-radius:10px;padding:9px 14px;transition:border-color .2s,box-shadow .2s}
.search-wrap:focus-within{border-color:#b6dfc4;box-shadow:0 0 0 3px rgba(45,223,110,0.07)}
.search-wrap svg{width:15px;height:15px;stroke:var(--text-soft);fill:none;stroke-width:1.8;flex-shrink:0}
.search-wrap input{background:none;border:none;outline:none;font-size:13.5px;color:var(--text);font-family:'DM Sans',sans-serif;width:100%}
.search-wrap input::placeholder{color:var(--text-soft)}
.filter-select,.filter-date{height:40px;background:var(--content-bg);border:1px solid var(--border);border-radius:10px;padding:0 12px;font-size:13px;color:var(--text-mid);font-family:'DM Sans',sans-serif;cursor:pointer;min-width:130px}
.filter-select:focus,.filter-date:focus{outline:none;border-color:#b6dfc4}

/* ── TABLE WRAP (idéntico a trabajadores) ── */
.table-wrap{background:var(--white);border:1px solid var(--border);border-radius:16px;overflow:hidden;box-shadow:var(--shadow);animation:fadeUp .5s .2s ease both}
.table-top{padding:14px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;gap:12px}
.table-count{font-size:13px;color:var(--text-soft)}
.table-count strong{color:var(--text);font-weight:600}

table{width:100%;border-collapse:collapse}
thead tr{background:var(--content-bg);border-bottom:1px solid var(--border)}
thead th{padding:11px 16px;text-align:left;font-size:11px;font-weight:700;color:var(--text-soft);text-transform:uppercase;letter-spacing:.8px;white-space:nowrap}
tbody tr{border-bottom:1px solid var(--border);transition:background .15s}
tbody tr:last-child{border-bottom:none}
tbody tr:hover{background:#f7fbf8}
tbody tr.sel{background:#eafaf1}
tbody td{padding:13px 16px;font-size:var(--tx-valor);color:var(--tx-color);vertical-align:middle}

.worker-cell{display:flex;align-items:center;gap:12px}
.worker-avatar{width:38px;height:38px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-family:'Syne',sans-serif;font-size:13px;font-weight:800;color:#fff;flex-shrink:0;box-shadow:0 2px 8px rgba(0,0,0,0.12)}
.worker-name{font-size:var(--tx-valor);font-weight:var(--tx-peso-enfasis);color:var(--tx-color);line-height:1.2}
.worker-id{font-size:11.5px;color:var(--text-soft);margin-top:1px}

.av-g{background:linear-gradient(135deg,#22c55e,#15803d)}
.av-t{background:linear-gradient(135deg,#14b8a6,#0f766e)}
.av-p{background:linear-gradient(135deg,#8b5cf6,#6d28d9)}
.av-o{background:linear-gradient(135deg,#f59e0b,#d97706)}
.av-b{background:linear-gradient(135deg,#3b82f6,#1d4ed8)}

.dias-badge{display:inline-flex;flex-direction:column;align-items:center;line-height:1.1}
.dias-badge .dias-n{font-family:'Syne',sans-serif;font-size:15px;font-weight:800;color:var(--text)}
.dias-badge .dias-l{font-size:9.5px;color:var(--text-soft)}

.prog-wrap{width:100%;height:5px;background:#eef2ef;border-radius:6px;overflow:hidden}
.prog-bar{height:100%;background:linear-gradient(90deg,var(--green),var(--green-dim));border-radius:6px}

.badge-vac{display:inline-flex;align-items:center;gap:6px;padding:5px 11px;border-radius:999px;font-size:11.5px;font-weight:700;white-space:nowrap}
.badge-vac .badge-dot{width:5px;height:5px;border-radius:50%;flex-shrink:0}
.badge-ap{background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0}
.badge-pe{background:#eff6ff;color:#2563eb;border:1px solid #bfdbfe}
.badge-re{background:#fff1f2;color:#dc2626;border:1px solid #fecaca}
.badge-di{background:#fff7ed;color:#c2410c;border:1px solid #fed7aa}

.acc-btns{display:flex;align-items:center;gap:4px}
.acc-btn{width:30px;height:30px;border-radius:8px;background:none;border:1px solid transparent;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:all .18s;color:var(--text-soft)}
.acc-btn:hover{background:var(--content-bg);border-color:var(--border);color:var(--green-dark)}
.acc-btn svg{width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:1.8}
.acc-btn.aok:hover{background:#f0fdf4;border-color:#bbf7d0;color:#16a34a}
.acc-btn.ano:hover{background:#fff1f2;border-color:#fecaca;color:#dc2626}

/* ── PANEL LATERAL (detalle / editar) ── */
.vac-side-panel{width:340px;flex-shrink:0;background:var(--white);border:1px solid var(--border);border-radius:16px;box-shadow:var(--shadow);position:sticky;top:calc(var(--topbar-h) + 24px);overflow:hidden}
.vac-side-panel.hidden{display:none}
.pn-empty-state{padding:44px 22px;text-align:center;color:var(--text-soft);font-size:12.5px;display:flex;flex-direction:column;align-items:center;gap:10px;line-height:1.5}
.pn-empty-state svg{width:34px;height:34px;stroke:var(--green-dim);fill:none;stroke-width:1.5;opacity:.6}
.pn-head{padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between}
.pn-title{font-family:var(--tx-fuente-titulos);font-size:var(--tx-titulo-seccion);font-weight:var(--tx-peso-titulo);color:var(--tx-color);letter-spacing:-.2px}
.pn-close-btn{width:28px;height:28px;border-radius:50%;border:1px solid var(--border);background:var(--content-bg);display:flex;align-items:center;justify-content:center;cursor:pointer;color:var(--text-mid)}
.pn-close-btn svg{width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2}
.pn-body{padding:18px 20px;display:flex;flex-direction:column;gap:14px}
.pn-worker-row{display:flex;align-items:center;gap:12px;padding-bottom:14px;border-bottom:1px solid var(--border)}
.pn-wname{font-size:var(--tx-valor);font-weight:var(--tx-peso-enfasis);color:var(--tx-color)}
.pn-wsub{font-size:11.5px;color:var(--text-soft);margin-top:2px}
.pn-sec-lbl{font-size:10.5px;font-weight:700;color:var(--green-dim);text-transform:uppercase;letter-spacing:.6px}
.pn-grid2{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.pn-frow{display:flex;flex-direction:column;gap:5px}
.pn-flbl{font-size:11px;font-weight:600;color:var(--text-mid)}
.pn-input,.pn-select,.pn-textarea{width:100%;background:var(--content-bg);border:1px solid var(--border);border-radius:10px;padding:0 12px;height:38px;font-size:13px;color:var(--text);font-family:'DM Sans',sans-serif;outline:none;transition:border-color .2s}
.pn-input:focus,.pn-select:focus,.pn-textarea:focus{border-color:#b6dfc4}
.pn-textarea{height:auto;min-height:70px;padding:10px 12px;resize:vertical}
.dias-resumen{background:var(--content-bg);border:1px solid var(--border);border-radius:12px;padding:12px 14px;display:flex;flex-direction:column;gap:6px}
.dr-row{display:flex;justify-content:space-between;font-size:12.5px;color:var(--text-mid)}
.dr-row.total{border-top:1px solid var(--border);padding-top:6px;margin-top:2px;font-weight:700;color:var(--text)}
.dr-title{font-size:10.5px;font-weight:700;color:var(--green-dim);text-transform:uppercase;letter-spacing:.6px;margin-bottom:2px}
.pn-foot{padding:14px 20px;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:10px}

/* ── MODAL (idéntico a trabajadores) ── */
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
.modal-close{width:32px;height:32px;border-radius:50%;background:var(--content-bg);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:18px;color:var(--text-mid);transition:background .2s;line-height:1}
.modal-close:hover{background:#fee2e2;color:#dc2626;border-color:#fecaca}
.modal-body{padding:20px 24px;display:flex;flex-direction:column;gap:14px}
.form-section{font-size:var(--tx-etiqueta);font-weight:var(--tx-peso-titulo);color:var(--tx-color-suave);text-transform:uppercase;letter-spacing:.8px;padding-bottom:8px;border-bottom:1px solid var(--border);margin-bottom:4px}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.form-row.g3{grid-template-columns:1fr 1fr 1fr}
.form-group{display:flex;flex-direction:column;gap:5px}
.form-group.full{grid-column:1/-1}
.form-label{font-size:var(--tx-etiqueta-form);font-weight:var(--tx-peso-enfasis);color:var(--tx-color-medio);letter-spacing:.3px}
.form-input,.form-select,.form-textarea{width:100%;height:42px;background:var(--content-bg);border:1px solid var(--border);border-radius:10px;padding:0 13px;font-size:13.5px;color:var(--text);font-family:'DM Sans',sans-serif;outline:none;transition:border-color .2s,box-shadow .2s}
.form-input:focus,.form-select:focus,.form-textarea:focus{border-color:#b6dfc4;box-shadow:0 0 0 3px rgba(45,223,110,0.07)}
.form-input::placeholder,.form-textarea::placeholder{color:var(--text-soft)}
.form-input[readonly]{background:#eef2ef;color:var(--green-dim);font-weight:var(--tx-peso-enfasis)}
.form-textarea{height:auto;min-height:76px;padding:12px 13px;resize:vertical}
.modal-foot{padding:0 24px 22px;display:flex;gap:10px;justify-content:flex-end;border-top:1px solid var(--border);padding-top:16px}

.info-box{margin-top:12px;background:var(--green-mist);border:1px solid rgba(45,223,110,.24);border-radius:12px;padding:12px 14px}
.info-box-row{display:flex;justify-content:space-between;font-size:12.5px;color:var(--text-mid);margin-top:4px}
.info-box-row:first-child{margin-top:0}
.info-box-row strong{color:var(--green-dark)}

@media(max-width:1150px){
  .vac-layout{flex-direction:column}
  .vac-side-panel{width:100%;position:static}
  .mini-stats-vac{grid-template-columns:repeat(3,1fr)}
}
@media(max-width:900px){
  .form-row,.form-row.g3{grid-template-columns:1fr}
  .form-group.full{grid-column:auto}
  .page-header{flex-direction:column;align-items:flex-start}
}
@media(max-width:640px){
  .mini-stats-vac{grid-template-columns:1fr 1fr}
}
</style>
</head>
<body>


<div class="layout">
<?php $paginaActiva = 'vacaciones'; require __DIR__ . '/../components/sidebar.php'; ?>

<!-- ══ MAIN ══ -->
<div class="main">
  <?php
  $tituloTopbar = 'Vacaciones';
  $busquedaTopbar = ['placeholder' => 'Buscar solicitudes, trabajadores...'];
  require __DIR__ . '/../components/topbar.php';
  ?>

  <div class="content">

    <div class="page-header">
      <div class="page-header-left">
        <div class="page-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        </div>
        <div>
          <div class="page-title">Gestión de Vacaciones</div>
          <div class="page-sub">Administra las solicitudes, aprueba periodos y controla los días disponibles por trabajador.</div>
        </div>
      </div>
      <div class="page-header-right">
        <a href="../dashboard/dashboard.php" class="btn btn-outline"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>Volver al Panel</a>
        <button class="btn btn-primary" onclick="abrirModal()"><svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>Nueva solicitud</button>
      </div>
    </div>

    <!-- STATS -->
    <div class="mini-stats-vac">
      <div class="mini-stat">
        <div class="mini-stat-icon ic-blue"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div>
        <div><div class="mini-stat-num nc-blue">5</div><div class="mini-stat-label">Solicitudes del mes</div><div class="mini-stat-sub">Junio 2026</div></div>
      </div>
      <div class="mini-stat">
        <div class="mini-stat-icon ic-yellow"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 16 14"/></svg></div>
        <div><div class="mini-stat-num nc-yellow">2</div><div class="mini-stat-label">Pendientes</div><div class="mini-stat-sub">Sin aprobar</div></div>
      </div>
      <div class="mini-stat">
        <div class="mini-stat-icon ic-green"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><polyline points="8 12 11 15 16 9"/></svg></div>
        <div><div class="mini-stat-num nc-green">2</div><div class="mini-stat-label">Aprobadas</div><div class="mini-stat-sub">Este mes</div></div>
      </div>
      <div class="mini-stat">
        <div class="mini-stat-icon ic-red"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg></div>
        <div><div class="mini-stat-num nc-red">1</div><div class="mini-stat-label">Rechazadas</div><div class="mini-stat-sub">Este mes</div></div>
      </div>
      <div class="mini-stat">
        <div class="mini-stat-icon ic-purple"><svg viewBox="0 0 24 24"><path d="M12 2v4"/><path d="M4.93 4.93l2.83 2.83"/><path d="M2 12h4"/><path d="M4.93 19.07l2.83-2.83"/><path d="M12 18a6 6 0 100-12 6 6 0 000 12z"/></svg></div>
        <div><div class="mini-stat-num nc-purple">28</div><div class="mini-stat-label">Días otorgados</div><div class="mini-stat-sub">Acumulado 2026</div></div>
      </div>
    </div>

    <div class="vac-layout">
      <div class="vac-main-col">

        <!-- CALENDARIO MINI -->
        <div class="cal-panel">
          <div class="cal-panel-head">
            <div class="cal-panel-title" id="calMes">Junio 2026</div>
            <div class="cal-nav-btns">
              <button class="cal-nav-btn" onclick="cambiarMes(-1)"><svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg></button>
              <button class="cal-nav-btn" onclick="cambiarMes(1)"><svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg></button>
            </div>
          </div>
          <div class="cal-grid-vac" id="calGrid"></div>
          <div class="cal-legend-vac">
            <span><span class="cal-legend-dot" style="background:#bbf7d0"></span>Aprobada</span>
            <span><span class="cal-legend-dot" style="background:#dbeafe"></span>Pendiente</span>
            <span><span class="cal-legend-dot" style="background:var(--green)"></span>Hoy</span>
          </div>
        </div>

        <!-- FILTROS -->
        <div class="filters-bar">
          <div class="filter-group" style="flex:1;min-width:220px">
            <span class="filter-label">Buscar</span>
            <div class="search-wrap">
              <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
              <input type="text" id="busqueda" placeholder="Buscar por trabajador..." oninput="filtrar()">
            </div>
          </div>
          <div class="filter-group">
            <span class="filter-label">Estado</span>
            <select class="filter-select" id="filtroEstado" onchange="filtrar()">
              <option value="">Todos los estados</option>
              <option>Aprobada</option><option>Pendiente</option><option>Rechazada</option><option>Disfrutando</option>
            </select>
          </div>
          <div class="filter-group">
            <span class="filter-label">Área</span>
            <select class="filter-select" id="filtroArea" onchange="filtrar()">
              <option value="">Todas las áreas</option>
              <option>Logística</option><option>Producción</option><option>SST</option><option>Administrativa</option>
            </select>
          </div>
          <div class="filter-group">
            <span class="filter-label">Desde</span>
            <input type="date" class="filter-date" id="filtroDesde" onchange="filtrar()">
          </div>
          <div class="filter-group">
            <span class="filter-label">Hasta</span>
            <input type="date" class="filter-date" id="filtroHasta" onchange="filtrar()">
          </div>
        </div>

        <!-- TABS -->
        <div class="vac-tabs">
          <div class="vac-tab on" data-tab="" onclick="cambiarTab(this)"><svg viewBox="0 0 24 24"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>Todas<span class="vac-tab-cnt">5</span></div>
          <div class="vac-tab" data-tab="Pendiente" onclick="cambiarTab(this)"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 16 14"/></svg>Pendientes<span class="vac-tab-cnt">2</span></div>
          <div class="vac-tab" data-tab="Aprobada" onclick="cambiarTab(this)"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><polyline points="8 12 11 15 16 9"/></svg>Aprobadas<span class="vac-tab-cnt">2</span></div>
          <div class="vac-tab" data-tab="Disfrutando" onclick="cambiarTab(this)"><svg viewBox="0 0 24 24"><path d="M12 2v4"/><path d="M4.93 4.93l2.83 2.83"/><path d="M2 12h4"/><path d="M12 18a6 6 0 100-12 6 6 0 000 12z"/></svg>Disfrutando<span class="vac-tab-cnt">0</span></div>
          <div class="vac-tab" data-tab="Rechazada" onclick="cambiarTab(this)"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>Rechazadas<span class="vac-tab-cnt">1</span></div>
        </div>

        <!-- TABLA -->
        <div class="table-wrap">
          <div class="table-top">
            <span class="table-count">Solicitudes de vacaciones</span>
          </div>
          <div style="overflow-x:auto">
            <table id="tablaVac">
              <thead>
                <tr>
                  <th>Trabajador</th>
                  <th>Área</th>
                  <th>Fecha inicio</th>
                  <th>Fecha fin</th>
                  <th>Días</th>
                  <th>Días disp.</th>
                  <th>Estado</th>
                  <th>Acciones</th>
                </tr>
              </thead>
              <tbody id="tbodyVac"></tbody>
            </table>
          </div>
        </div>

      </div><!-- /vac-main-col -->

      <!-- PANEL LATERAL -->
      <aside class="vac-side-panel hidden" id="panel">
        <div class="pn-empty-state" id="panelEmpty">
          <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
          <p>Selecciona el lápiz de una solicitud para editarla, o aprueba/rechaza directamente desde la tabla.</p>
        </div>

        <div id="panelContent" style="display:none;flex-direction:column">
          <div class="pn-head">
            <div class="pn-title" id="pnTitle">Detalle de solicitud</div>
            <button class="pn-close-btn" onclick="cerrarPanel()"><svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
          </div>
          <div class="pn-body">
            <div class="pn-worker-row">
              <div class="worker-avatar" id="pnAv" style="width:40px;height:40px;font-size:13px">--</div>
              <div>
                <div class="pn-wname" id="pnNombre">—</div>
                <div class="pn-wsub" id="pnSub">—</div>
              </div>
            </div>

            <div class="pn-sec-lbl">Período de vacaciones</div>
            <div class="pn-grid2">
              <div class="pn-frow">
                <span class="pn-flbl">Fecha inicio *</span>
                <input type="date" class="pn-input" id="eFIni" onchange="calcDias()">
              </div>
              <div class="pn-frow">
                <span class="pn-flbl">Fecha fin *</span>
                <input type="date" class="pn-input" id="eFFin" onchange="calcDias()">
              </div>
            </div>

            <div class="pn-frow">
              <span class="pn-flbl">Estado</span>
              <select class="pn-select" id="eEstado">
                <option>Pendiente</option>
                <option>Aprobada</option>
                <option>Rechazada</option>
                <option>Disfrutando</option>
              </select>
            </div>

            <div class="dias-resumen">
              <div class="dr-title">Resumen de días</div>
              <div class="dr-row"><span>Días solicitados</span><span id="drSol">0</span></div>
              <div class="dr-row"><span>Días disponibles</span><span id="drDisp">15</span></div>
              <div class="dr-row"><span>Días ya tomados</span><span id="drTom">0</span></div>
              <div class="dr-row total"><span>Saldo restante</span><span id="drSaldo">15</span></div>
              <div class="prog-wrap" style="margin-top:6px"><div class="prog-bar" id="drProg" style="width:0%"></div></div>
            </div>

            <div class="pn-frow">
              <span class="pn-flbl">Observaciones</span>
              <textarea class="pn-textarea" id="eObs"></textarea>
            </div>
          </div>
          <div class="pn-foot">
            <button class="btn btn-outline btn-sm" onclick="cerrarPanel()">Cancelar</button>
            <button class="btn btn-primary btn-sm" onclick="guardar()">Guardar cambios</button>
          </div>
        </div>
      </aside>

    </div><!-- /vac-layout -->

  </div><!-- /content -->
</div><!-- /main -->
</div><!-- /layout -->

<!-- MODAL NUEVA SOLICITUD -->
<div class="modal-backdrop" id="overlayNuevo">
  <div class="modal">
    <div class="modal-head">
      <div class="modal-head-left">
        <div class="modal-icon">
          <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        </div>
        <div>
          <div class="modal-title">Nueva solicitud de vacaciones</div>
          <div class="modal-sub">Registra un nuevo periodo de descanso</div>
        </div>
      </div>
      <button class="modal-close" onclick="cerrarModal()">&#215;</button>
    </div>

    <div class="modal-body">

      <div class="form-section">Trabajador</div>
      <div class="form-row">
        <div class="form-group full">
          <label class="form-label">Seleccionar trabajador *</label>
          <select class="form-select" id="mTrabajador" onchange="cargarDiasDisp()">
            <option value="">— Seleccione un trabajador —</option>
            <option value="13|PF|av-g|Paola Franco|Logística|12|3">Paola Franco — Logística</option>
            <option value="9|CV|av-t|Camilo Vargas|Producción|15|5">Camilo Vargas — Producción</option>
            <option value="12|PV|av-p|Paola Franco Vargas|SST|15|0">Paola Franco Vargas — SST</option>
            <option value="1|KV|av-o|Karen Paola Vaca Franco|Administrativa|15|7">Karen Paola Vaca Franco — Administrativa</option>
            <option value="3|PF|av-b|Paola Franco|Sin área|15|0">Paola Franco (ID:0003) — Sin área</option>
          </select>
        </div>
      </div>

      <div class="info-box" id="mDiasInfo" style="display:none">
        <div class="info-box-row"><span>Días disponibles:</span><strong id="mDispVal">—</strong></div>
        <div class="info-box-row"><span>Días ya tomados:</span><strong id="mTomVal">—</strong></div>
        <div class="prog-wrap" style="margin-top:8px"><div class="prog-bar" id="mProg" style="width:0%"></div></div>
      </div>

      <div class="form-section" style="margin-top:6px">Período</div>
      <div class="form-row g3">
        <div class="form-group">
          <label class="form-label">Fecha inicio *</label>
          <input type="date" class="form-input" id="mFIni" onchange="calcDiasModal()">
        </div>
        <div class="form-group">
          <label class="form-label">Fecha fin *</label>
          <input type="date" class="form-input" id="mFFin" onchange="calcDiasModal()">
        </div>
        <div class="form-group">
          <label class="form-label">Días calculados</label>
          <input type="number" class="form-input" id="mDias" readonly placeholder="Auto">
        </div>
      </div>

      <div class="form-section" style="margin-top:6px">Detalles</div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Estado inicial</label>
          <select class="form-select" id="mEstadoInicial">
            <option>Pendiente</option>
            <option>Aprobada</option>
          </select>
        </div>
      </div>
      <div class="form-group full">
        <label class="form-label">Observaciones</label>
        <textarea class="form-textarea" id="mObs" placeholder="Motivo, notas o condiciones especiales..."></textarea>
      </div>

    </div>
    <div class="modal-foot">
      <button class="btn btn-outline" onclick="cerrarModal()">Cancelar</button>
      <button class="btn btn-primary" onclick="registrarVacacion()">Registrar solicitud</button>
    </div>
  </div>
</div>

<script>
function toggleSidebar(){document.getElementById('sidebar').classList.toggle('open');document.getElementById('sidebarOverlay').classList.toggle('open')}
function closeSidebar(){document.getElementById('sidebar').classList.remove('open');document.getElementById('sidebarOverlay').classList.remove('open')}
document.querySelectorAll('.sidebar .nav-item, .sidebar .nav-logout').forEach(function(link){
  link.addEventListener('click', function(){ closeSidebar(); });
});
function toggleProfile(){document.getElementById('profileWrap').classList.toggle('open')}
document.addEventListener('click',function(e){var w=document.getElementById('profileWrap');if(w&&!w.contains(e.target))w.classList.remove('open')});
document.addEventListener('keydown',function(e){if((e.ctrlKey||e.metaKey)&&e.key==='k'){e.preventDefault();document.querySelector('.search-bar input').focus()}});

// ── ICONOS SVG REUTILIZABLES PARA FILAS ──
const ICO_EDIT = '<svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>';
const ICO_CHECK = '<svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>';
const ICO_X = '<svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';

// ── DATOS (mapean la BD: id_vacacion, fecha_inicio, fecha_fin, dias, estado, id_trabajador) ──
const vacaciones = [
  {id:1,ini:'PF',color:'av-g',nombre:'Paola Franco',idT:13,area:'Logística',fi:'2026-06-10',ff:'2026-06-24',dias:15,disp:15,tomados:0,estado:'Aprobada',obs:'Vacaciones anuales programadas.'},
  {id:2,ini:'CV',color:'av-t',nombre:'Camilo Vargas',idT:9,area:'Producción',fi:'2026-07-01',ff:'2026-07-05',dias:5,disp:15,tomados:5,estado:'Pendiente',obs:'Solicitud de descanso por acumulado.'},
  {id:3,ini:'PV',color:'av-p',nombre:'Paola Franco Vargas',idT:12,area:'SST',fi:'2026-05-01',ff:'2026-05-08',dias:8,disp:15,tomados:8,estado:'Rechazada',obs:'Rechazo por carga laboral en el período.'},
  {id:4,ini:'KV',color:'av-o',nombre:'Karen Paola Vaca',idT:1,area:'Administrativa',fi:'2026-06-15',ff:'2026-06-22',dias:8,disp:15,tomados:7,estado:'Aprobada',obs:''},
  {id:5,ini:'PF',color:'av-b',nombre:'Paola Franco',idT:3,area:'Sin área',fi:'2026-06-30',ff:'2026-07-03',dias:4,disp:15,tomados:0,estado:'Pendiente',obs:'Solicitud de vacaciones parciales.'},
];

const badgeMap = {Aprobada:'badge-ap',Pendiente:'badge-pe',Rechazada:'badge-re',Disfrutando:'badge-di'};
let filaActual = null, tabActual = '';

function fmtF(f){ if(!f) return '—'; const[y,m,d]=f.split('-'); return `${d}/${m}/${y}`; }
function diasEntre(fi,ff){ if(!fi||!ff) return 0; return Math.round((new Date(ff)-new Date(fi))/(1000*60*60*24))+1; }

// ── RENDER TABLA ─────────────────────────────────────────────────────────
function renderTabla(){
  const tb = document.getElementById('tbodyVac');
  tb.innerHTML = vacaciones.map((v,i)=>{
    const pct = Math.min(100, Math.round((v.tomados/v.disp)*100));
    return `
    <tr id="row-${i}" data-estado="${v.estado}" data-nombre="${v.nombre.toLowerCase()}" data-area="${v.area}" data-fi="${v.fi}">
      <td><div class="worker-cell"><div class="worker-avatar ${v.color}">${v.ini}</div>
        <div><div class="worker-name">${v.nombre}</div><div class="worker-id">ID: ${String(v.idT).padStart(4,'0')}</div></div></div></td>
      <td style="font-size:12.5px;color:var(--text-mid)">${v.area}</td>
      <td style="font-size:12.5px">${fmtF(v.fi)}</td>
      <td style="font-size:12.5px;color:var(--text-soft)">${fmtF(v.ff)}</td>
      <td><div class="dias-badge"><span class="dias-n">${v.dias}</span><span class="dias-l">días</span></div></td>
      <td>
        <div style="font-size:12px;font-weight:700;color:var(--text)">${v.disp} días</div>
        <div class="prog-wrap" style="margin-top:4px;width:80px"><div class="prog-bar" style="width:${pct}%"></div></div>
        <div style="font-size:10px;color:var(--text-soft);margin-top:2px">${v.tomados} tomados</div>
      </td>
      <td><span class="badge-vac ${badgeMap[v.estado]||'badge-pe'}"><span class="badge-dot"></span>${v.estado}</span></td>
      <td><div class="acc-btns">
        <button class="acc-btn" title="Editar" onclick="editarVac(${i})">${ICO_EDIT}</button>
        <button class="acc-btn aok" title="Aprobar" onclick="aprobar(${i})">${ICO_CHECK}</button>
        <button class="acc-btn ano" title="Rechazar" onclick="rechazar(${i})">${ICO_X}</button>
      </div></td>
    </tr>`}).join('');
}

// ── PANEL EDITAR ─────────────────────────────────────────────────────────
function editarVac(idx){
  const v = vacaciones[idx];
  resaltarFila(idx);
  const av = document.getElementById('pnAv');
  av.className = 'worker-avatar '+v.color; av.style.cssText='width:40px;height:40px;font-size:13px'; av.textContent=v.ini;
  document.getElementById('pnNombre').textContent = v.nombre;
  document.getElementById('pnSub').textContent    = 'ID: '+String(v.idT).padStart(4,'0')+' · '+v.area;
  document.getElementById('pnTitle').textContent  = 'Editar solicitud';
  document.getElementById('eFIni').value  = v.fi;
  document.getElementById('eFFin').value  = v.ff;
  document.getElementById('eObs').value   = v.obs||'';
  seleccionar('eEstado', v.estado);
  actualizarResumen(v.dias, v.disp, v.tomados);
  document.getElementById('panelEmpty').style.display   = 'none';
  document.getElementById('panelContent').style.display = 'flex';
  document.getElementById('panel').classList.remove('hidden');
}

function calcDias(){
  const fi = document.getElementById('eFIni').value;
  const ff = document.getElementById('eFFin').value;
  const d  = diasEntre(fi,ff);
  if(filaActual!==null){
    const v = vacaciones[filaActual];
    actualizarResumen(d, v.disp, v.tomados);
  }
}

function actualizarResumen(sol, disp, tom){
  const saldo = disp - tom;
  const pct   = Math.min(100, disp>0 ? Math.round(((tom+sol)/disp)*100) : 0);
  document.getElementById('drSol').textContent  = sol + ' días';
  document.getElementById('drDisp').textContent = disp + ' días';
  document.getElementById('drTom').textContent  = tom + ' días';
  document.getElementById('drSaldo').textContent= (saldo-sol) + ' días';
  document.getElementById('drProg').style.width = pct+'%';
}

function guardar(){
  if(filaActual===null) return;
  const v = vacaciones[filaActual];
  v.fi     = document.getElementById('eFIni').value;
  v.ff     = document.getElementById('eFFin').value;
  v.dias   = diasEntre(v.fi, v.ff);
  v.estado = document.getElementById('eEstado').value;
  v.obs    = document.getElementById('eObs').value;
  renderTabla(); filtrar(); cerrarPanel();
}

// ── APROBAR / RECHAZAR ────────────────────────────────────────────────────
function aprobar(idx){
  if(confirm(`¿Aprobar las vacaciones de ${vacaciones[idx].nombre}?`)){
    vacaciones[idx].estado='Aprobada';
    renderTabla(); filtrar();
    if(filaActual===idx){ seleccionar('eEstado','Aprobada'); }
  }
}
function rechazar(idx){
  if(confirm(`¿Rechazar las vacaciones de ${vacaciones[idx].nombre}?`)){
    vacaciones[idx].estado='Rechazada';
    renderTabla(); filtrar(); cerrarPanel();
  }
}

// ── MODAL NUEVA SOLICITUD ─────────────────────────────────────────────────
function abrirModal(){ document.getElementById('overlayNuevo').classList.add('open'); }
function cerrarModal(){ document.getElementById('overlayNuevo').classList.remove('open'); }
document.getElementById('overlayNuevo').addEventListener('click',function(e){ if(e.target===this) cerrarModal(); });

function cargarDiasDisp(){
  const sel  = document.getElementById('mTrabajador');
  const val  = sel.value;
  const info = document.getElementById('mDiasInfo');
  if(!val){ info.style.display='none'; return; }
  const parts = val.split('|');
  const disp  = parseInt(parts[5]);
  const tom   = parseInt(parts[6]);
  const pct   = Math.min(100, disp>0 ? Math.round((tom/disp)*100) : 0);
  document.getElementById('mDispVal').textContent = disp + ' días';
  document.getElementById('mTomVal').textContent  = tom + ' días';
  document.getElementById('mProg').style.width    = pct+'%';
  info.style.display='block';
}

function calcDiasModal(){
  const fi = document.getElementById('mFIni').value;
  const ff = document.getElementById('mFFin').value;
  document.getElementById('mDias').value = diasEntre(fi,ff) || '';
}

function registrarVacacion(){
  const sel = document.getElementById('mTrabajador');
  const fi  = document.getElementById('mFIni').value;
  const ff  = document.getElementById('mFFin').value;
  if(!sel.value||!fi||!ff){ alert('Completa trabajador y fechas.'); return; }
  const parts = sel.value.split('|');
  vacaciones.push({
    id: vacaciones.length+1,
    ini: parts[1], color: parts[2], nombre: parts[3],
    idT: parseInt(parts[0]), area: parts[4],
    fi, ff, dias: diasEntre(fi,ff),
    disp: parseInt(parts[5]), tomados: parseInt(parts[6]),
    estado: document.getElementById('mEstadoInicial').value || 'Pendiente',
    obs: document.getElementById('mObs').value || ''
  });
  renderTabla(); filtrar(); cerrarModal();
}

// ── PANEL HELPERS ─────────────────────────────────────────────────────────
function cerrarPanel(){
  if(filaActual!==null){ const f=document.getElementById('row-'+filaActual); if(f) f.classList.remove('sel'); filaActual=null; }
  document.getElementById('panelEmpty').style.display   = 'flex';
  document.getElementById('panelContent').style.display = 'none';
  document.getElementById('panel').classList.add('hidden');
}
function resaltarFila(idx){
  if(filaActual!==null){ const a=document.getElementById('row-'+filaActual); if(a) a.classList.remove('sel'); }
  const f=document.getElementById('row-'+idx); if(f) f.classList.add('sel');
  filaActual=idx;
}
function seleccionar(id,val){
  const s=document.getElementById(id);
  for(let i=0;i<s.options.length;i++) if(s.options[i].value===val||s.options[i].text===val){s.selectedIndex=i;break;}
}

// ── TABS ──────────────────────────────────────────────────────────────────
function cambiarTab(el){
  document.querySelectorAll('.vac-tab').forEach(t=>t.classList.remove('on')); el.classList.add('on');
  tabActual=el.dataset.tab; filtrar();
}

// ── FILTROS ───────────────────────────────────────────────────────────────
function filtrar(){
  const busq  = document.getElementById('busqueda').value.toLowerCase();
  const est   = document.getElementById('filtroEstado').value;
  const area  = document.getElementById('filtroArea').value;
  const desde = document.getElementById('filtroDesde').value;
  const hasta = document.getElementById('filtroHasta').value;
  document.querySelectorAll('#tablaVac tbody tr').forEach(tr=>{
    const ok = (!tabActual || tr.dataset.estado===tabActual)
      && (!busq  || tr.dataset.nombre.includes(busq))
      && (!est   || tr.dataset.estado===est)
      && (!area  || tr.dataset.area===area)
      && (!desde || tr.dataset.fi>=desde)
      && (!hasta || tr.dataset.fi<=hasta);
    tr.style.display = ok?'':'none';
  });
}

// ── CALENDARIO ────────────────────────────────────────────────────────────
let calFecha = new Date(2026,5,1);

function getDiasVac(year, month){
  const dias = {};
  vacaciones.forEach(v=>{
    let d = new Date(v.fi);
    const fin = new Date(v.ff);
    while(d <= fin){
      if(d.getFullYear()===year && d.getMonth()===month){
        dias[d.getDate()] = v.estado;
      }
      d.setDate(d.getDate()+1);
    }
  });
  return dias;
}

function renderCal(){
  const y = calFecha.getFullYear(), m = calFecha.getMonth();
  const meses = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
  document.getElementById('calMes').textContent = meses[m]+' '+y;
  const dias = getDiasVac(y,m);
  const hoy  = new Date();
  const primer = new Date(y,m,1).getDay();
  const total  = new Date(y,m+1,0).getDate();
  const dows   = ['Do','Lu','Ma','Mi','Ju','Vi','Sá'];
  let html = dows.map(d=>`<div class="cal-dow">${d}</div>`).join('');
  for(let i=0;i<primer;i++) html+=`<div class="cal-day-vac empty"></div>`;
  for(let d=1;d<=total;d++){
    const isHoy  = hoy.getFullYear()===y && hoy.getMonth()===m && hoy.getDate()===d;
    const vacEst = dias[d];
    let cls = 'cal-day-vac';
    if(isHoy) cls+=' today';
    else if(vacEst==='Aprobada') cls+=' vac-ap';
    else if(vacEst==='Pendiente') cls+=' vac-pe';
    html+=`<div class="${cls}">${d}</div>`;
  }
  document.getElementById('calGrid').innerHTML = html;
}

function cambiarMes(delta){
  calFecha.setMonth(calFecha.getMonth()+delta);
  renderCal();
}

// ── INIT ──────────────────────────────────────────────────────────────────
renderTabla();
renderCal();
</script>
</body>
</html>