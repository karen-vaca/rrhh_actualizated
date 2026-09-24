<?php
// Página "en construcción" reutilizable para módulos que aún no existen.
// Uso (desde views/<modulo>/archivo.php):
//   $titulo = 'Incidentes';
//   $descripcion = 'Registro y seguimiento de incidentes laborales.';
//   $modulo_activo = 'incidentes';
//   require __DIR__ . '/../components/en_construccion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$titulo        = $titulo ?? 'Módulo';
$descripcion   = $descripcion ?? 'Este módulo está en desarrollo.';
$modulo_activo = $modulo_activo ?? '';

$nombres    = $_SESSION['nombres'] ?? $_SESSION['nombre'] ?? 'Administrador';
$apellidos  = $_SESSION['apellidos'] ?? '';
$rol_nombre = $_SESSION['rol_nombre'] ?? $_SESSION['rol'] ?? 'RRHH';

$inicial = strtoupper(
    substr($nombres, 0, 1) .
    substr($apellidos !== '' ? $apellidos : $nombres, 0, 1)
);

$nav = [
    'Principal' => [
        'dashboard' => ['Resumen', '../dashboard/dashboard.php', '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/>'],
    ],
    'Gestión' => [
        'trabajadores' => ['Trabajadores', '../trabajadores/index.php', '<path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/>'],
        'contratacion' => ['Contratación', '../contratacion/index.php', '<path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>'],
        'novedades'    => ['Novedades', '../novedades/index.php', '<path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/>'],
        'vacaciones'   => ['Vacaciones', '../vacaciones/index.php', '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>'],
    ],
    'SG-SST' => [
        'perfil_salud'   => ['Perfil de Salud', '../perfil_salud/index.php', '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>'],
        'examenes'       => ['Exámenes Médicos', '../examenes/index.php', '<path d="M22 12h-4l-3 9L9 3l-3 9H2"/>'],
        'incidentes'     => ['Incidentes', '../incidentes/index.php', '<path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>'],
        'capacitaciones' => ['Capacitaciones', '../capacitaciones/index.php', '<path d="M2 3h6a4 4 0 014 4v14a3 3 0 00-3-3H2z"/><path d="M22 3h-6a4 4 0 00-4 4v14a3 3 0 013-3h7z"/>'],
    ],
    'Reportes' => [
        'reportes'    => ['Reportes', '../reportes/index.php', '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>'],
        'indicadores' => ['Indicadores', '../indicadores/index.php', '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>'],
    ],
    'Configuración' => [
        'usuarios' => ['Usuarios', '../usuarios/index.php', '<circle cx="12" cy="8" r="4"/><path d="M20 21a8 8 0 10-16 0"/>'],
        'roles'    => ['Roles', '../roles/index.php', '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/>'],
    ],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title><?= htmlspecialchars($titulo) ?> | PlastyPetco</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet"/>
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

/* ── LAYOUT ── */
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
.sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:99;backdrop-filter:blur(3px)}

/* ── MAIN ── */
.main{margin-left:var(--sidebar-w);flex:1;display:flex;flex-direction:column;min-height:100vh}

/* ── TOPBAR ── */
.topbar{
  height:var(--topbar-h);background:var(--white);
  border-bottom:1px solid var(--border);
  display:flex;align-items:center;justify-content:space-between;
  padding:0 28px;position:sticky;top:0;z-index:50;
  box-shadow:0 1px 8px rgba(0,0,0,0.05);
}
.topbar-left{display:flex;align-items:center;gap:16px}
.menu-toggle{display:none;background:none;border:none;color:var(--text-mid);cursor:pointer;padding:4px}
.menu-toggle svg{width:20px;height:20px;stroke:currentColor;fill:none;stroke-width:1.8}
.topbar-title{font-family:'Syne',sans-serif;font-size:clamp(18px,2vw,22px);font-weight:800;color:var(--text);letter-spacing:-.4px}
.topbar-right{display:flex;align-items:center;gap:12px}

.profile-wrap{position:relative}
.profile-btn{display:flex;align-items:center;gap:10px;background:var(--content-bg);border:1px solid var(--border);border-radius:40px;padding:6px 14px 6px 6px;cursor:pointer;transition:all .2s}
.profile-avatar{width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--green),var(--green-dim));display:flex;align-items:center;justify-content:center;font-family:'Syne',sans-serif;font-size:14px;font-weight:800;color:#021a08;flex-shrink:0;box-shadow:0 0 10px rgba(45,223,110,0.3)}
.profile-info{display:flex;flex-direction:column;text-align:left;line-height:1.2}
.profile-name{font-size:13px;font-weight:600;color:var(--text)}
.profile-role{font-size:11px;color:var(--green-dim)}
.profile-chevron{width:15px;height:15px;color:var(--text-soft);transition:transform .25s;flex-shrink:0;stroke:currentColor;fill:none;stroke-width:1.8}
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

/* ── CONTENT ── */
.content{flex:1;padding:24px 28px;display:flex;align-items:center;justify-content:center}
.wip-card{background:var(--white);border:1px solid var(--border);border-radius:20px;box-shadow:var(--shadow);padding:44px 40px;max-width:480px;width:100%;text-align:center}
.wip-icon{width:64px;height:64px;margin:0 auto 18px;background:var(--green-mist);border:1px solid rgba(45,223,110,0.2);border-radius:18px;display:flex;align-items:center;justify-content:center;color:var(--green-dim)}
.wip-icon svg{width:30px;height:30px;stroke:currentColor;fill:none;stroke-width:1.7}
.wip-badge{display:inline-block;font-size:10.5px;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:#d97706;background:#fffbeb;border:1px solid #fde68a;border-radius:20px;padding:4px 10px;margin-bottom:12px}
.wip-title{font-family:'Syne',sans-serif;font-size:24px;font-weight:800;letter-spacing:-.4px;margin-bottom:8px}
.wip-text{font-size:14px;color:var(--text-mid);line-height:1.55;margin-bottom:24px}
.btn-back{display:inline-flex;align-items:center;gap:7px;background:linear-gradient(135deg,var(--green),var(--green-dim));border:none;border-radius:10px;padding:10px 18px;font-family:'Syne',sans-serif;font-size:13px;font-weight:700;color:#021a08;text-decoration:none;box-shadow:0 3px 14px rgba(45,223,110,0.25);transition:all .2s}
.btn-back:hover{transform:translateY(-1px);box-shadow:0 6px 20px rgba(45,223,110,0.35)}
.btn-back svg{width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:2}

@media(max-width:900px){
  .sidebar{transform:translateX(-100%)}
  .sidebar.open{transform:translateX(0)}
  .sidebar-overlay.open{display:block}
  .main{margin-left:0}
  .menu-toggle{display:flex}
  .topbar{padding:0 16px}
  .content{padding:16px}
}
@media(max-width:640px){
  .profile-info{display:none}
  .profile-btn{padding:4px}
  .wip-card{padding:32px 22px}
}
</style>
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<div class="layout">
<!-- ══ SIDEBAR ══ -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-head">
    <div class="sidebar-logo"><img src="../../assets/img/logo_plastypetco.png" alt="PlastyPetco"></div>
    <div>
      <div class="sidebar-brand">Plasty<em>Petco</em></div>
    </div>
  </div>
  <nav class="sidebar-nav">
<?php foreach ($nav as $seccion => $items): ?>
    <div class="nav-section"><?= htmlspecialchars($seccion) ?></div>
<?php foreach ($items as $clave => [$label, $href, $icono]): ?>
    <a href="<?= $href ?>" class="nav-item<?= $clave === $modulo_activo ? ' active' : '' ?>">
      <svg viewBox="0 0 24 24"><?= $icono ?></svg><?= htmlspecialchars($label) ?>
    </a>
<?php endforeach; ?>
<?php endforeach; ?>
  </nav>
  <div class="sidebar-foot">
    <a href="../../logout.php" class="nav-logout">
      <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>Cerrar sesión
    </a>
  </div>
</aside>

<!-- ══ MAIN ══ -->
<div class="main">
  <header class="topbar">
    <div class="topbar-left">
      <button class="menu-toggle" onclick="toggleSidebar()"><svg viewBox="0 0 24 24"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
      <span class="topbar-title"><?= htmlspecialchars($titulo) ?></span>
    </div>
    <div class="topbar-right">
      <div class="profile-wrap" id="profileWrap">
        <button class="profile-btn" onclick="toggleProfile()">
          <div class="profile-avatar"><?= htmlspecialchars($inicial) ?></div>
          <div class="profile-info">
            <span class="profile-name"><?= htmlspecialchars($nombres . ' ' . $apellidos) ?></span>
            <span class="profile-role"><?= htmlspecialchars($rol_nombre) ?></span>
          </div>
          <svg class="profile-chevron" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
        </button>
        <div class="profile-dropdown" id="profileDropdown">
          <div class="profile-dropdown-head">
            <div class="profile-avatar-lg"><?= htmlspecialchars($inicial) ?></div>
            <div>
              <div style="font-size:13px;font-weight:600;color:var(--text)"><?= htmlspecialchars($nombres . ' ' . $apellidos) ?></div>
              <div style="font-size:11px;color:var(--green-dim);margin-top:2px"><?= htmlspecialchars($rol_nombre) ?></div>
            </div>
          </div>
          <div class="profile-dropdown-body">
            <a href="../perfil/editar.php" class="profile-dd-item"><svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>Editar perfil</a>
            <div class="profile-dd-sep"></div>
            <a href="../../logout.php" class="profile-dd-item profile-dd-logout"><svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>Cerrar sesión</a>
          </div>
        </div>
      </div>
    </div>
  </header>

  <div class="content">
    <div class="wip-card">
      <div class="wip-icon">
        <svg viewBox="0 0 24 24"><path d="M14.7 6.3a1 1 0 000 1.4l1.6 1.6a1 1 0 001.4 0l3.77-3.77a6 6 0 01-7.94 7.94l-6.91 6.91a2.12 2.12 0 01-3-3l6.91-6.91a6 6 0 017.94-7.94l-3.76 3.76z"/></svg>
      </div>
      <span class="wip-badge">En construcción</span>
      <div class="wip-title"><?= htmlspecialchars($titulo) ?></div>
      <p class="wip-text"><?= htmlspecialchars($descripcion) ?><br>Estamos trabajando en esta sección; estará disponible pronto.</p>
      <a href="../dashboard/dashboard.php" class="btn-back">
        <svg viewBox="0 0 24 24"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>Volver al resumen
      </a>
    </div>
  </div>
</div>
</div>

<script>
function toggleSidebar(){document.getElementById('sidebar').classList.toggle('open');document.getElementById('sidebarOverlay').classList.toggle('open')}
function closeSidebar(){document.getElementById('sidebar').classList.remove('open');document.getElementById('sidebarOverlay').classList.remove('open')}
function toggleProfile(){document.getElementById('profileWrap').classList.toggle('open')}
document.addEventListener('click',function(e){var w=document.getElementById('profileWrap');if(w&&!w.contains(e.target))w.classList.remove('open')});
</script>
</body>
</html>
