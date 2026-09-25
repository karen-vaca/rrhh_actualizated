<?php
// Barra superior única de todas las pantallas internas (views/<modulo>/*.php):
// botón de menú (celular), título del módulo, búsqueda, notificaciones y menú de perfil.
// Antes cada pantalla tenía su propia copia (menús de perfil distintos, contadores de
// notificación inventados, dos pantallas sin menú de perfil ni botón para abrir el
// menú lateral en celular). Ninguna pantalla debe escribir su propio
// <header class="topbar">: tests/LayoutCompartidoTest.php lo verifica.
//
// Uso, dentro de <div class="main"> (o <main class="main">):
//   $tituloTopbar = 'Trabajadores';
//   $busquedaTopbar = ['placeholder' => 'Buscar trabajadores...'];   // opcional: 'id', 'oninput'
//   $notificacionesTopbar = 4;                                        // opcional: contador real
//   require __DIR__ . '/../components/topbar.php';
// Los estilos están en assets/css/layout.css.

$tituloTopbar = $tituloTopbar ?? '';
$busquedaTopbar = ($busquedaTopbar ?? []) + ['placeholder' => 'Buscar...', 'id' => '', 'oninput' => ''];
$notificacionesTopbar = (int)($notificacionesTopbar ?? 0);

$nombresTopbar = trim((string)($_SESSION['nombres'] ?? $_SESSION['nombre'] ?? '')) ?: 'Administrador';
$apellidosTopbar = trim((string)($_SESSION['apellidos'] ?? ''));
$rolTopbar = trim((string)($_SESSION['rol_nombre'] ?? $_SESSION['rol'] ?? '')) ?: 'Sin rol';
$nombreCompletoTopbar = trim($nombresTopbar . ' ' . $apellidosTopbar);
$inicialesTopbar = mb_strtoupper(
    mb_substr($nombresTopbar, 0, 1) . mb_substr($apellidosTopbar !== '' ? $apellidosTopbar : mb_substr($nombresTopbar, 1), 0, 1)
);
$escTopbar = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
?>
<header class="topbar">
  <div class="topbar-left">
    <button class="menu-toggle" type="button" onclick="alternarMenuLateral()" aria-label="Abrir menú"><svg viewBox="0 0 24 24"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
    <span class="topbar-title"><?= $escTopbar($tituloTopbar) ?></span>
  </div>
  <div class="search-bar">
    <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
    <input type="text" placeholder="<?= $escTopbar($busquedaTopbar['placeholder']) ?>"<?= $busquedaTopbar['id'] !== '' ? ' id="' . $escTopbar($busquedaTopbar['id']) . '"' : '' ?><?= $busquedaTopbar['oninput'] !== '' ? ' oninput="' . $escTopbar($busquedaTopbar['oninput']) . '"' : '' ?>/>
    <span class="search-kbd">Ctrl+K</span>
  </div>
  <div class="topbar-right">
    <button class="notif-btn" type="button" title="Notificaciones">
      <svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>
<?php if ($notificacionesTopbar > 0): ?>
      <span class="notif-badge"><?= $notificacionesTopbar ?></span>
<?php endif; ?>
    </button>
    <div class="profile-wrap" id="profileWrap">
      <button class="profile-btn" type="button" onclick="alternarMenuPerfil(event)">
        <div class="profile-avatar"><?= $escTopbar($inicialesTopbar) ?></div>
        <div class="profile-info">
          <span class="profile-name"><?= $escTopbar($nombreCompletoTopbar) ?></span>
          <span class="profile-role"><?= $escTopbar($rolTopbar) ?></span>
        </div>
        <svg class="profile-chevron" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
      </button>
      <div class="profile-dropdown" id="profileDropdown">
        <div class="profile-dropdown-head">
          <div class="profile-avatar-lg"><?= $escTopbar($inicialesTopbar) ?></div>
          <div>
            <div class="profile-dd-nombre"><?= $escTopbar($nombreCompletoTopbar) ?></div>
            <div class="profile-dd-rol"><?= $escTopbar($rolTopbar) ?></div>
          </div>
        </div>
        <div class="profile-dropdown-body">
          <a href="../perfil/editar.php" class="profile-dd-item"><svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>Editar perfil</a>
          <a href="../perfil/cambiar-contrasena.php" class="profile-dd-item"><svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>Cambiar contraseña</a>
          <a href="../perfil/configuracion.php" class="profile-dd-item"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>Configuración</a>
          <div class="profile-dd-sep"></div>
          <a href="../../logout.php" class="profile-dd-item profile-dd-logout"><svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>Cerrar sesión</a>
        </div>
      </div>
    </div>
  </div>
</header>
<script>
function alternarMenuPerfil(e){if(e)e.stopPropagation();document.getElementById('profileWrap').classList.toggle('open')}
document.addEventListener('click',function(e){var w=document.getElementById('profileWrap');if(w&&!w.contains(e.target))w.classList.remove('open')});
</script>
