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
<?php require __DIR__ . '/estilos_base.php'; ?>
<style>

/* ── LAYOUT ── */

/* ── SIDEBAR ── */

/* ── MAIN ── */

/* ── TOPBAR ── */

/* ── CONTENT ── */
/* Centra el aviso dentro del área de contenido común */
.content-centrada{align-items:center;justify-content:center}
.wip-card{background:var(--white);border:1px solid var(--border);border-radius:20px;box-shadow:var(--shadow);padding:44px 40px;max-width:480px;width:100%;text-align:center}
.wip-icon{width:64px;height:64px;margin:0 auto 18px;background:var(--green-mist);border:1px solid rgba(45,223,110,0.2);border-radius:18px;display:flex;align-items:center;justify-content:center;color:var(--green-dim)}
.wip-icon svg{width:30px;height:30px;stroke:currentColor;fill:none;stroke-width:1.7}
.wip-badge{display:inline-block;font-size:10.5px;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:#d97706;background:#fffbeb;border:1px solid #fde68a;border-radius:20px;padding:4px 10px;margin-bottom:12px}
.wip-title{font-family:var(--tx-fuente-titulos);font-size:var(--tx-titulo-pagina);font-weight:var(--tx-peso-titulo-pagina);color:var(--tx-color);letter-spacing:-.4px;line-height:1.1;margin-bottom:8px}
.wip-text{font-size:var(--tx-valor);color:var(--tx-color-medio);line-height:1.55;margin-bottom:24px}

@media(max-width:640px){
  .wip-card{padding:32px 22px}
}
</style>
</head>
<body>


<div class="layout">
<?php $paginaActiva = $modulo_activo; require __DIR__ . '/sidebar.php'; ?>

<!-- ══ MAIN ══ -->
<div class="main">
  <?php
  $tituloTopbar = $titulo;
  $busquedaTopbar = ['placeholder' => 'Buscar...'];
  require __DIR__ . '/topbar.php';
  ?>

  <div class="content content-centrada">
    <div class="wip-card">
      <div class="wip-icon">
        <svg viewBox="0 0 24 24"><path d="M14.7 6.3a1 1 0 000 1.4l1.6 1.6a1 1 0 001.4 0l3.77-3.77a6 6 0 01-7.94 7.94l-6.91 6.91a2.12 2.12 0 01-3-3l6.91-6.91a6 6 0 017.94-7.94l-3.76 3.76z"/></svg>
      </div>
      <span class="wip-badge">En construcción</span>
      <div class="wip-title"><?= htmlspecialchars($titulo) ?></div>
      <p class="wip-text"><?= htmlspecialchars($descripcion) ?><br>Estamos trabajando en esta sección; estará disponible pronto.</p>
      <a href="../dashboard/dashboard.php" class="btn btn-outline">
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
