 <?php
require_once __DIR__ . '/../../config/auth.php';
requerirAcceso(ROLES_CUALQUIER_USUARIO);
$nombres  = $_SESSION['nombres']   ?? 'Usuario';
$apellidos= $_SESSION['apellidos'] ?? '';
$id_roles = $_SESSION['id_roles']  ?? 0;
$rol_nombre = $_SESSION['rol_nombre'] ?? 'Sin rol';
$inicial = strtoupper(mb_substr($nombres,0,1));
$meses=['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
$fecha_hoy = date('d').' de '.$meses[(int)date('m')-1].' de '.date('Y');
$hora=(int)date('H');
if($hora>=5&&$hora<12){$saludo='¡Buenos días';$emoji='☀️';}
elseif($hora>=12&&$hora<18){$saludo='¡Buenas tardes';$emoji='🌱';}
else{$saludo='¡Buenas noches';$emoji='🌙';}

require_once '../../config/conexion.php';
$tw=0;$activos=0;$inc=0;$caps=0;$exams=0;$contmes=0;$ausent=0;$dias_perd=0;$incapac=0;
try{
    $tw      =$conexion->query("SELECT COUNT(*) FROM trabajadores")->fetchColumn();
    $activos =$conexion->query("SELECT COUNT(*) FROM trabajadores WHERE estado=1")->fetchColumn();
    $contmes =$conexion->query("SELECT COUNT(*) FROM trabajadores WHERE MONTH(fecha_ingreso)=MONTH(NOW()) AND YEAR(fecha_ingreso)=YEAR(NOW())")->fetchColumn();
    $inc     =$conexion->query("SELECT COUNT(*) FROM incidentes WHERE MONTH(fecha)=MONTH(NOW()) AND YEAR(fecha)=YEAR(NOW())")->fetchColumn();
    $exams   =$conexion->query("SELECT COUNT(*) FROM examenes_medicos WHERE estado='pendiente'")->fetchColumn();
    $caps    =$conexion->query("SELECT COUNT(*) FROM capacitaciones WHERE estado='activa'")->fetchColumn();
}catch(Exception $e){}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Dashboard | PlastyPetco</title>
<?php
// Pendiente de migrar a assets/css/componentes.css (tarjetas, filtros, tabla, avatares):
// esta pantalla todavía usa su propio CSS con los mismos nombres de clase.
$componentesPendientes = true;
require __DIR__ . '/../components/estilos_base.php';
?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
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
.ic-green{background:#eafaf1;color:#1a9945}
.ic-blue{background:#eff6ff;color:#2563eb}
.ic-red{background:#fff1f2;color:#dc2626}
.ic-yellow{background:#fffbeb;color:#d97706}
.ic-purple{background:#f5f3ff;color:#7c3aed}

/* num color variants */
.nc-green{color:#1a9945}.nc-blue{color:#2563eb}.nc-red{color:#dc2626}
.nc-yellow{color:#d97706}.nc-purple{color:#7c3aed}

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
@keyframes fadeUp{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:none}}
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
</style>
</head>
<body>


<div class="layout">

<?php $paginaActiva = 'dashboard'; require __DIR__ . '/../components/sidebar.php'; ?>

<!-- ══ MAIN ══ -->
<div class="main">

  <!-- TOPBAR -->
  <?php
  $tituloTopbar = 'Panel de Control';
  $busquedaTopbar = ['placeholder' => 'Buscar trabajadores, documentos, reportes...'];
  require __DIR__ . '/../components/topbar.php';
  ?>

  <!-- CONTENT -->
  <div class="content">

    <!-- BANNER -->
    <div class="banner">
      <div class="banner-glow"></div>
      <div class="banner-text">
        <h2><?php echo $emoji.' '.$saludo.', ';?><span><?php echo htmlspecialchars($nombres);?></span>! 👋</h2>
        <p>Aquí tienes el resumen general de hoy — <?php echo $fecha_hoy;?></p>
      </div>
      <div class="banner-badge"><span class="pulse-dot"></span>Sistema activo</div>
    </div>

    <!-- STAT CARDS -->
    <div class="stats-grid">

      <div class="stat-card">
        <div class="stat-top">
          <div class="stat-icon ic-green"><svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg></div>
          <span class="stat-trend trend-up"><svg viewBox="0 0 24 24"><polyline points="18 15 12 9 6 15"/></svg><?php echo $contmes?> este mes</span>
        </div>
        <div class="stat-num nc-green"><?php echo $activos?></div>
        <div class="stat-label">Trabajadores activos</div>
        <canvas class="stat-mini-chart" id="chart0"></canvas>
      </div>

      <div class="stat-card">
        <div class="stat-top">
          <div class="stat-icon ic-blue"><svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg></div>
          <span class="stat-trend trend-up"><svg viewBox="0 0 24 24"><polyline points="18 15 12 9 6 15"/></svg>Nuevos ingresos</span>
        </div>
        <div class="stat-num nc-blue"><?php echo $contmes?></div>
        <div class="stat-label">Contratados este mes</div>
        <canvas class="stat-mini-chart" id="chart1"></canvas>
      </div>

      <div class="stat-card">
        <div class="stat-top">
          <div class="stat-icon ic-red"><svg viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div>
          <span class="stat-trend trend-down"><svg viewBox="0 0 24 24"><polyline points="18 9 12 15 6 9"/></svg>Mes en curso</span>
        </div>
        <div class="stat-num nc-red"><?php echo $inc?></div>
        <div class="stat-label">Incidentes SST</div>
        <canvas class="stat-mini-chart" id="chart2"></canvas>
      </div>

      <div class="stat-card">
        <div class="stat-top">
          <div class="stat-icon ic-yellow"><svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></div>
          <span class="stat-trend trend-neutral">Requieren atención</span>
        </div>
        <div class="stat-num nc-yellow"><?php echo $exams?></div>
        <div class="stat-label">Exámenes pendientes</div>
        <canvas class="stat-mini-chart" id="chart3"></canvas>
      </div>

      <div class="stat-card">
        <div class="stat-top">
          <div class="stat-icon ic-purple"><svg viewBox="0 0 24 24"><path d="M2 3h6a4 4 0 014 4v14a3 3 0 00-3-3H2z"/><path d="M22 3h-6a4 4 0 00-4 4v14a3 3 0 013-3h7z"/></svg></div>
          <span class="stat-trend trend-up"><svg viewBox="0 0 24 24"><polyline points="18 15 12 9 6 15"/></svg>En progreso</span>
        </div>
        <div class="stat-num nc-purple"><?php echo $caps?></div>
        <div class="stat-label">Capacitaciones activas</div>
        <canvas class="stat-mini-chart" id="chart4"></canvas>
      </div>

    </div>

    <!-- BOTTOM GRID: line chart + donut + activity -->
    <div class="bottom-grid">

      <!-- Indicadores SST line chart -->
      <div class="panel">
        <div class="panel-header">
          <div class="panel-header-left">
            <div class="panel-title">Indicadores SG-SST</div>
            <div class="panel-sub">Incidentes vs acciones correctivas</div>
          </div>
          <select class="chart-filter"><option>Últimos 6 meses</option><option>Este año</option></select>
        </div>
        <canvas id="sstChart" height="160"></canvas>
      </div>

      <!-- Donut distribución por área -->
      <div class="panel">
        <div class="panel-title">Distribución por área</div>
        <div class="panel-sub">Total de personal activo</div>
        <div class="donut-wrap">
          <div class="donut-chart-wrap">
            <canvas id="donutChart"></canvas>
            <div class="donut-center">
              <div class="donut-center-num"><?php echo $activos?></div>
              <div class="donut-center-lbl">Total</div>
            </div>
          </div>
          <div class="donut-legend">
            <div class="legend-item"><span class="legend-dot" style="background:#2ddf6e"></span>Producción<span class="legend-val">48</span></div>
            <div class="legend-item"><span class="legend-dot" style="background:#60a5fa"></span>Administrativa<span class="legend-val">32</span></div>
            <div class="legend-item"><span class="legend-dot" style="background:#fbbf24"></span>Logística<span class="legend-val">24</span></div>
            <div class="legend-item"><span class="legend-dot" style="background:#a78bfa"></span>Mantenimiento<span class="legend-val">16</span></div>
            <div class="legend-item"><span class="legend-dot" style="background:#d1d5db"></span>Otras<span class="legend-val">8</span></div>
          </div>
        </div>
      </div>

      <!-- Actividades recientes -->
      <div class="panel">
        <div class="panel-title">Actividades recientes</div>
        <div class="activity-list">
          <div class="activity-item">
            <div class="act-icon ic-green"><svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg></div>
            <div class="act-body"><div class="act-title">Nuevo trabajador registrado</div><div class="act-name">Juan Pérez García</div></div>
            <div class="act-time">10:30 a.m.</div>
          </div>
          <div class="activity-item">
            <div class="act-icon ic-yellow"><svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></div>
            <div class="act-body"><div class="act-title">Examen médico vencido</div><div class="act-name">María López Jiménez</div></div>
            <div class="act-time">09:15 a.m.</div>
          </div>
          <div class="activity-item">
            <div class="act-icon ic-red"><svg viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/></svg></div>
            <div class="act-body"><div class="act-title">Incidente reportado</div><div class="act-name">Área de Producción</div></div>
            <div class="act-time">Ayer</div>
          </div>
          <div class="activity-item">
            <div class="act-icon ic-purple"><svg viewBox="0 0 24 24"><path d="M2 3h6a4 4 0 014 4v14a3 3 0 00-3-3H2z"/><path d="M22 3h-6a4 4 0 00-4 4v14a3 3 0 013-3h7z"/></svg></div>
            <div class="act-body"><div class="act-title">Capacitación completada</div><div class="act-name">Trabajo en alturas</div></div>
            <div class="act-time">Ayer</div>
          </div>
          <div class="activity-item">
            <div class="act-icon ic-blue"><svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/></svg></div>
            <div class="act-body"><div class="act-title">Incapacidad aprobada</div><div class="act-name">Carlos Ramírez</div></div>
            <div class="act-time">2 días</div>
          </div>
        </div>
        <a href="../actividades/index.php" class="see-all">Ver todas <svg viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></a>
      </div>
    </div>

    <!-- BOTTOM ROW: vencimientos + ausentismo + calendario -->
    <div class="bottom-row">

      <!-- Próximos vencimientos -->
      <div class="panel">
        <div class="panel-title">Próximos vencimientos</div>
        <div class="panel-sub">Documentos y exámenes por vencer</div>
        <div class="venc-item">
          <div class="venc-icon ic-green"><svg viewBox="0 0 24 24"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg></div>
          <div class="venc-body"><div class="venc-name">Examen médico periódico</div><div class="venc-person">Ana Maria Torres</div></div>
          <div class="venc-meta"><span class="venc-date">28 May</span><span class="venc-badge badge-warn">3 días</span></div>
        </div>
        <div class="venc-item">
          <div class="venc-icon ic-yellow"><svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></div>
          <div class="venc-body"><div class="venc-name">Certificado de alturas</div><div class="venc-person">Luis Fernando Castro</div></div>
          <div class="venc-meta"><span class="venc-date">02 Jun</span><span class="venc-badge badge-ok">8 días</span></div>
        </div>
        <div class="venc-item">
          <div class="venc-icon ic-purple"><svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></div>
          <div class="venc-body"><div class="venc-name">Examen ocupacional</div><div class="venc-person">Pedro Alejandro Ruiz</div></div>
          <div class="venc-meta"><span class="venc-date">05 Jun</span><span class="venc-badge badge-ok">11 días</span></div>
        </div>
        <a href="../examenes/vencimientos.php" class="see-all">Ver todos los vencimientos <svg viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></a>
      </div>

      <!-- Ausentismo -->
      <div class="panel">
        <div class="panel-title">Ausentismo</div>
        <div class="panel-sub">Índice de ausentismo mensual</div>
        <div class="absent-num">2.4%</div>
        <div class="absent-trend"><svg viewBox="0 0 24 24"><polyline points="18 15 12 9 6 15"/></svg>0.6% vs mes anterior</div>
        <canvas id="ausentChart" height="70"></canvas>
        <div class="absent-row">
          <div class="absent-mini"><div class="absent-mini-num">18</div><div class="absent-mini-lbl">Días perdidos este mes</div></div>
          <div class="absent-mini"><div class="absent-mini-num">7</div><div class="absent-mini-lbl">Total incapacidades</div></div>
        </div>
      </div>

      <!-- Calendario -->
      <div class="panel">
        <div class="panel-title">Calendario</div>
        <div id="calendarWidget"></div>
      </div>

    </div>

  </div><!-- /content -->

  <!-- footer -->
  <footer style="text-align:center;padding:14px;font-size:11.5px;color:var(--text-soft);border-top:1px solid var(--border);background:var(--white)">
    🌱 <strong style="color:var(--green-dark)">PlastyPetco S.A.S</strong> · Sistema de Gestión RRHH + SG-SST &nbsp;·&nbsp; Versión 1.0.0
  </footer>

</div><!-- /main -->
</div><!-- /layout -->

<script>
/* ── SIDEBAR ── */
function toggleSidebar(){document.getElementById('sidebar').classList.toggle('open');document.getElementById('sidebarOverlay').classList.toggle('open')}
function closeSidebar(){document.getElementById('sidebar').classList.remove('open');document.getElementById('sidebarOverlay').classList.remove('open')}

/* ── PROFILE ── */
function toggleProfile(){document.getElementById('profileWrap').classList.toggle('open')}
document.addEventListener('click',function(e){const w=document.getElementById('profileWrap');if(w&&!w.contains(e.target))w.classList.remove('open')});

/* ── MINI SPARKLINE CHARTS ── */
const sparkData=[
  {data:[110,115,118,120,122,124,126,128],color:'#1a9945'},
  {data:[2,3,4,3,5,6,5,7],color:'#2563eb'},
  {data:[5,4,6,3,4,2,4,3],color:'#dc2626'},
  {data:[14,15,13,16,14,13,14,12],color:'#d97706'},
  {data:[3,4,3,5,4,5,5,5],color:'#7c3aed'},
];
sparkData.forEach((s,i)=>{
  const ctx=document.getElementById('chart'+i);
  if(!ctx)return;
  new Chart(ctx,{type:'line',data:{labels:Array(s.data.length).fill(''),datasets:[{data:s.data,borderColor:s.color,borderWidth:2,pointRadius:0,tension:.4,fill:true,backgroundColor:s.color+'18'}]},options:{plugins:{legend:{display:false},tooltip:{enabled:false}},scales:{x:{display:false},y:{display:false}},animation:{duration:800}}});
});

/* ── SST LINE CHART ── */
const sstCtx=document.getElementById('sstChart');
new Chart(sstCtx,{type:'line',data:{
  labels:['Dic','Ene','Feb','Mar','Abr','May'],
  datasets:[
    {label:'Incidentes',data:[8,12,9,14,11,20],borderColor:'#2ddf6e',backgroundColor:'rgba(45,223,110,0.08)',borderWidth:2.5,pointBackgroundColor:'#2ddf6e',pointRadius:4,tension:.4,fill:true},
    {label:'Acciones correctivas',data:[3,6,8,9,10,13],borderColor:'#60a5fa',backgroundColor:'rgba(96,165,250,0.06)',borderWidth:2,pointBackgroundColor:'#60a5fa',pointRadius:4,tension:.4,fill:true}
  ]
},options:{plugins:{legend:{labels:{font:{size:11},boxWidth:10,color:'#4a6655'}}},scales:{x:{grid:{color:'rgba(0,0,0,0.04)'},ticks:{font:{size:11},color:'#8aab96'}},y:{grid:{color:'rgba(0,0,0,0.04)'},ticks:{font:{size:11},color:'#8aab96'},beginAtZero:true}},animation:{duration:900}}});

/* ── DONUT CHART ── */
const donutCtx=document.getElementById('donutChart');
new Chart(donutCtx,{type:'doughnut',data:{
  labels:['Producción','Administrativa','Logística','Mantenimiento','Otras'],
  datasets:[{data:[48,32,24,16,8],backgroundColor:['#2ddf6e','#60a5fa','#fbbf24','#a78bfa','#d1d5db'],borderWidth:0,hoverOffset:4}]
},options:{cutout:'70%',plugins:{legend:{display:false},tooltip:{callbacks:{label:function(c){return c.label+': '+c.raw+' ('+Math.round(c.raw/128*100)+'%)'}}}}},animation:{animateRotate:true,duration:900}});

/* ── AUSENTISMO CHART ── */
const ausCtx=document.getElementById('ausentChart');
new Chart(ausCtx,{type:'line',data:{
  labels:['Ene','Feb','Mar','Abr','May'],
  datasets:[{data:[3.1,2.8,3.4,3.0,2.4],borderColor:'#2ddf6e',backgroundColor:'rgba(45,223,110,0.1)',borderWidth:2.5,pointRadius:3,pointBackgroundColor:'#2ddf6e',tension:.4,fill:true}]
},options:{plugins:{legend:{display:false}},scales:{x:{grid:{display:false},ticks:{font:{size:10},color:'#8aab96'}},y:{display:false}},animation:{duration:800}}});

/* ── CALENDARIO ── */
(function(){
  const now=new Date();let year=now.getFullYear(),month=now.getMonth();
  const meses=['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
  const dias=['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'];
  function render(){
    const first=new Date(year,month,1).getDay();
    const offset=first===0?6:first-1;
    const total=new Date(year,month+1,0).getDate();
    const today=new Date();
    let html=`<div class="cal-header"><button class="cal-nav" onclick="calPrev()"><svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg></button><span class="cal-month">${meses[month]} ${year}</span><button class="cal-nav" onclick="calNext()"><svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg></button></div>`;
    html+=`<div class="cal-grid">`;
    dias.forEach(d=>html+=`<div class="cal-day-name">${d}</div>`);
    for(let i=0;i<offset;i++)html+=`<div class="cal-day empty">0</div>`;
    for(let d=1;d<=total;d++){
      const isToday=d===today.getDate()&&month===today.getMonth()&&year===today.getFullYear();
      html+=`<div class="cal-day${isToday?' today':''}">${d}</div>`;
    }
    html+=`</div>`;
    document.getElementById('calendarWidget').innerHTML=html;
  }
  window.calPrev=function(){month--;if(month<0){month=11;year--}render()};
  window.calNext=function(){month++;if(month>11){month=0;year++}render()};
  render();
})();
</script>
</body>
</html>