<?php
// Menú lateral único de todas las pantallas internas (views/<modulo>/*.php).
// Antes cada pantalla tenía su propia copia y se desincronizaron (faltaba "Roles y
// Permisos", cambiaban los íconos, el logo y los enlaces). Ninguna pantalla debe
// volver a escribir su propio <aside class="sidebar">: tests/LayoutCompartidoTest.php
// lo verifica.
//
// Uso, dentro de <div class="layout">:
//   $paginaActiva = 'trabajadores';
//   require __DIR__ . '/../components/sidebar.php';
// $paginaActiva es una de las claves de $menuLateral (o vacía si ninguna aplica).
// Los estilos están en assets/css/layout.css.

$paginaActiva = $paginaActiva ?? '';

// Sección => [clave => [texto, enlace, contenido del ícono SVG (24x24)]]
$menuLateral = [
    'Principal' => [
        'dashboard' => ['Resumen', '../dashboard/dashboard.php', '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/>'],
    ],
    'Gestión' => [
        'trabajadores' => ['Trabajadores', '../trabajadores/index.php', '<path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/>'],
        'contratacion' => ['Contratación', '../contratacion/index.php', '<path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>'],
        'novedades' => ['Novedades', '../novedades/index.php', '<path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/>'],
        'vacaciones' => ['Vacaciones', '../vacaciones/index.php', '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>'],
    ],
    'SG-SST' => [
        'perfil_salud' => ['Perfil de Salud', '../perfil_salud/index.php', '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>'],
        'examenes' => ['Exámenes Médicos', '../examenes/index.php', '<path d="M22 12h-4l-3 9L9 3l-3 9H2"/>'],
        'incidentes' => ['Incidentes', '../incidentes/index.php', '<path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>'],
        'capacitaciones' => ['Capacitaciones', '../capacitaciones/index.php', '<path d="M2 3h6a4 4 0 014 4v14a3 3 0 00-3-3H2z"/><path d="M22 3h-6a4 4 0 00-4 4v14a3 3 0 013-3h7z"/>'],
    ],
    'Reportes' => [
        'reportes' => ['Reportes', '../reportes/index.php', '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>'],
        'indicadores' => ['Indicadores', '../indicadores/index.php', '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>'],
    ],
    'Configuración' => [
        'usuarios' => ['Usuarios', '../usuarios/index.php', '<circle cx="12" cy="8" r="4"/><path d="M20 21a8 8 0 10-16 0"/>'],
        'roles' => ['Roles y Permisos', '../roles/index.php', '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/>'],
    ],
];
?>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="cerrarMenuLateral()"></div>
<aside class="sidebar" id="sidebar">
  <div class="sidebar-head">
    <div class="sidebar-logo"><img src="../../assets/img/logo_plastypetco.png" alt="PlastyPetco"></div>
    <div class="sidebar-brand">Plasty<em>Petco</em></div>
  </div>
  <nav class="sidebar-nav">
<?php foreach ($menuLateral as $seccionMenu => $itemsMenu): ?>
    <div class="nav-section"><?= htmlspecialchars($seccionMenu) ?></div>
<?php foreach ($itemsMenu as $claveMenu => [$textoMenu, $enlaceMenu, $iconoMenu]): ?>
    <a href="<?= htmlspecialchars($enlaceMenu) ?>" class="nav-item<?= $claveMenu === $paginaActiva ? ' active' : '' ?>"<?= $claveMenu === $paginaActiva ? ' aria-current="page"' : '' ?>>
      <svg viewBox="0 0 24 24"><?= $iconoMenu ?></svg><?= htmlspecialchars($textoMenu) ?>
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
<script>
function alternarMenuLateral(){document.getElementById('sidebar').classList.toggle('open');document.getElementById('sidebarOverlay').classList.toggle('open')}
function cerrarMenuLateral(){document.getElementById('sidebar').classList.remove('open');document.getElementById('sidebarOverlay').classList.remove('open')}
</script>
