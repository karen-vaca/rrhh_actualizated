<?php
require_once __DIR__ . '/../../config/auth.php';
requerirAcceso();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../config/conexion.php';
require_once __DIR__ . '/funciones_trabajador.php';

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
    ['tipo_de_documento', 'nombre_tipo_documento', 'tipo_documento', 'nombre_documento', 'nombre', 'descripcion'],
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

$nombres = $_SESSION['nombres'] ?? $_SESSION['nombre'] ?? 'Usuario';
$apellidos = $_SESSION['apellidos'] ?? '';
$rol_nombre = $_SESSION['rol_nombre'] ?? $_SESSION['rol'] ?? 'RRHH';
$inicial = inicialesPersona($nombres, $apellidos);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Ver trabajador | PlastyPetco</title>
<?php require __DIR__ . '/../components/estilos_base.php'; ?>

<style>

/* layout */

/* ── SIDEBAR ── */
 
 
/* leaf deco sidebar */

/* main */

/* content */
.page-header{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:4px}
.page-header-left{display:flex;align-items:center;gap:16px}
.page-icon{width:52px;height:52px;background:var(--green-mist);border:1px solid rgba(45,223,110,0.2);border-radius:14px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.page-icon svg{width:26px;height:26px;stroke:var(--green-dim);fill:none;stroke-width:1.7}
.page-title{font-family:var(--tx-fuente-titulos);font-size:var(--tx-titulo-pagina);font-weight:var(--tx-peso-titulo-pagina);color:var(--tx-color);letter-spacing:-.4px;line-height:1.1}
.page-sub{font-size:var(--tx-subtitulo);font-weight:var(--tx-peso-normal);color:var(--tx-color-suave);margin-top:3px}
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
.worker-main-name{font-family:var(--tx-fuente-titulos);font-size:var(--tx-nombre-encabezado);font-weight:var(--tx-peso-titulo-pagina);color:#fff;letter-spacing:-.4px;line-height:1.05;margin-bottom:8px}
.worker-main-role{font-size:var(--tx-valor);font-weight:var(--tx-peso-medio);color:rgba(255,255,255,.85);margin:-2px 0 10px}
.worker-main-meta{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.section-nota{margin-top:14px;padding-top:12px;border-top:1px solid var(--border);font-size:var(--tx-ayuda);color:var(--tx-color-suave)}
.meta-pill{border-radius:999px;padding:4px 10px;font-size:var(--tx-insignia);font-weight:var(--tx-peso-medio);background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.16);color:rgba(255,255,255,.86)}
.status-pill{position:relative;z-index:1;display:inline-flex;align-items:center;gap:6px;border-radius:999px;padding:7px 12px;font-size:12px;font-weight:700;white-space:nowrap}
.status-active{background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0}
.status-inactive{background:#fef2f2;color:#dc2626;border:1px solid #fecaca}
.status-dot{width:6px;height:6px;border-radius:50%;background:currentColor}
.profile-body{padding:20px 22px 22px}

.info-section{background:var(--white);border:1px solid var(--border);border-radius:18px;box-shadow:var(--shadow);padding:18px}
.info-section + .info-section{margin-top:14px}
.section-head{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:14px;padding-bottom:12px;border-bottom:1px solid var(--border)}
.section-title{font-family:var(--tx-fuente-titulos);font-size:var(--tx-titulo-seccion);font-weight:var(--tx-peso-titulo);color:var(--tx-color);letter-spacing:-.2px}
.section-tag{font-size:11px;font-weight:var(--tx-peso-enfasis);color:var(--green-dim);background:var(--green-mist);border:1px solid rgba(45,223,110,.18);border-radius:999px;padding:4px 9px}
.info-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:10px}
.info-item{background:#fbfdfb;border:1px solid #edf4ef;border-radius:13px;padding:12px}
.info-label{font-size:var(--tx-etiqueta);font-weight:var(--tx-peso-enfasis);color:var(--tx-color-suave);text-transform:uppercase;letter-spacing:.7px;margin-bottom:5px}
.info-value{font-size:var(--tx-valor);font-weight:var(--tx-peso-normal);color:var(--tx-color);line-height:1.35;word-break:break-word}
.side-column{display:flex;flex-direction:column;gap:18px}
.side-card{background:var(--white);border:1px solid var(--border);border-radius:18px;box-shadow:var(--shadow);padding:18px}
.side-card .info-grid{grid-template-columns:1fr}
.record-item{display:flex;align-items:flex-start;gap:11px;padding:10px 0;border-bottom:1px solid var(--border)}
.record-item:last-child{border-bottom:none;padding-bottom:0}
.record-dot{width:10px;height:10px;border-radius:50%;background:var(--green);box-shadow:0 0 0 5px var(--green-mist);margin-top:5px;flex-shrink:0}
.record-title{font-size:var(--tx-valor);font-weight:var(--tx-peso-medio);color:var(--tx-color)}
.record-sub{font-size:var(--tx-ayuda-chica);color:var(--tx-color-suave);margin-top:2px}

/* footer */

/* overlay and responsive */
@media(max-width:1100px){
  .view-grid{grid-template-columns:1fr}
}
@media(max-width:680px){
  .profile-hero{align-items:flex-start;flex-direction:column}
  .info-grid{grid-template-columns:1fr}
  .page-title{font-size:var(--tx-titulo-pagina)}
}

/* modal interno para inactivar trabajador */
.custom-modal-backdrop{
  position:fixed;
  inset:0;
  background:rgba(5,14,7,.56);
  backdrop-filter:blur(6px);
  display:none;
  align-items:center;
  justify-content:center;
  z-index:9999;
  padding:20px;
}
.custom-modal-backdrop.open{
  display:flex;
}
.custom-modal-card{
  width:min(440px,100%);
  background:var(--white);
  border:1px solid var(--border);
  border-radius:20px;
  padding:24px;
  box-shadow:0 28px 80px rgba(0,0,0,.22);
  animation:modalSoftIn .22s cubic-bezier(.22,1,.36,1) both;
}
@keyframes modalSoftIn{
  from{opacity:0;transform:translateY(12px) scale(.97)}
  to{opacity:1;transform:none}
}
.custom-modal-icon{
  width:46px;
  height:46px;
  border-radius:14px;
  background:#fff1f2;
  border:1px solid #fecaca;
  color:#dc2626;
  display:flex;
  align-items:center;
  justify-content:center;
  margin-bottom:14px;
}
.custom-modal-icon svg{
  width:23px;
  height:23px;
  stroke:currentColor;
  fill:none;
  stroke-width:1.8;
}
.custom-modal-card h3{
  font-family:var(--tx-fuente-titulos);
  font-size:var(--tx-titulo-modal);
  font-weight:var(--tx-peso-titulo-pagina);
  color:var(--text);
  letter-spacing:-.4px;
  margin-bottom:8px;
}
.custom-modal-card p{
  font-size:13.5px;
  color:var(--text-mid);
  line-height:1.55;
  margin-bottom:20px;
}
.custom-modal-card p strong{
  color:var(--text);
  font-weight:700;
}
.custom-modal-actions{
  display:flex;
  justify-content:flex-end;
  gap:10px;
}
.btn-modal-cancel,
.btn-modal-danger{
  height:40px;
  border-radius:10px;
  padding:0 16px;
  font-family:'DM Sans',sans-serif;
  font-size:13px;
  font-weight:var(--tx-peso-enfasis);
  cursor:pointer;
  transition:all .18s;
}
.btn-modal-cancel{
  border:1px solid var(--border);
  background:var(--white);
  color:var(--text-mid);
}
.btn-modal-cancel:hover{
  background:var(--content-bg);
}
.btn-modal-danger{
  border:1px solid #dc2626;
  background:#dc2626;
  color:#fff;
}
.btn-modal-danger:hover{
  background:#b91c1c;
  border-color:#b91c1c;
}

</style>
<style>
/* Folio: el id interno como dato secundario (referencia para soporte y auditoría) */
.folio{display:inline-block;margin-left:8px;font-size:11.5px;font-weight:500;color:var(--text-soft);letter-spacing:.2px;vertical-align:middle;white-space:nowrap}
.lugar-revisar{display:inline-block;margin-left:6px;font-size:10.5px;font-weight:700;color:#b45309;background:#fffbeb;border:1px solid #fde68a;border-radius:20px;padding:2px 8px;vertical-align:middle}
.nota-campo{font-size:var(--tx-ayuda);font-weight:var(--tx-peso-normal);color:var(--tx-color-suave);line-height:1.45}
.nota-campo strong{color:var(--text-mid)}
</style>
</head>

<body>


<div class="layout">

<?php $paginaActiva = 'trabajadores'; require __DIR__ . '/../components/sidebar.php'; ?>

<div class="main">

  <?php
  $tituloTopbar = 'Trabajadores';
  $busquedaTopbar = ['placeholder' => 'Buscar trabajadores, documentos, reportes...'];
  require __DIR__ . '/../components/topbar.php';
  ?>

  <div class="content">
    <?php
    $avisosFicha = [
        'actualizado' => 'Los cambios del trabajador se guardaron correctamente.',
        'reactivado'  => 'El trabajador fue reactivado.',
        'inactivado'  => 'El trabajador quedó inactivo. No se eliminó de la base de datos.',
    ];
    if (empty($trabajador['id_eps'])): ?>
      <div style="border:1px solid #fde68a;background:#fffbeb;color:#92400e;border-radius:12px;padding:11px 14px;font-size:13px;font-weight:600">
        EPS pendiente de verificar: el dato guardado no era confiable y se dejó vacío. Confírmalo con el trabajador y regístralo en "Editar".
      </div>
    <?php endif;
    if (isset($avisosFicha[$_GET['mensaje'] ?? ''])): ?>
      <div style="border:1px solid #bbf7d0;background:#f0fdf4;color:#166534;border-radius:12px;padding:11px 14px;font-size:13px;font-weight:600">
        <?php echo e($avisosFicha[$_GET['mensaje']]); ?>
      </div>
    <?php endif; ?>

    <div class="page-header">
      <div class="page-header-left">
        <div class="page-icon">
          <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
        </div>
        <div>
          <div class="page-title">Ficha del Trabajador</div>
          <div class="page-sub">Consulta detallada de la información personal, laboral y de contacto.</div>
        </div>
      </div>

      <div class="page-header-right">
        <a href="index.php" class="btn">
          <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
          Volver
        </a>
        <a href="editar.php?id=<?php echo (int)$trabajador['id_trabajador']; ?>" class="btn btn-primary">
          <svg viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 013 3L7 19l-4 1 1-4 12.5-12.5z"/></svg>
          Editar
        </a>

        <?php if ($estadoTrabajador === 1): ?>
          <button
            type="button"
            class="btn btn-danger"
            onclick='abrirModalInactivar(
              <?php echo (int)$trabajador["id_trabajador"]; ?>,
              <?php echo json_encode($nombreCompleto ?: "este trabajador", JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>
            )'
          >
            <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6M14 11v6"/></svg>
            Inactivar
          </button>
        <?php else: ?>
          <form action="activar.php" method="POST"><?php echo campoCsrf(); ?>
            <input type="hidden" name="volver" value="ver">
            <input type="hidden" name="id_trabajador" value="<?php echo (int)$trabajador['id_trabajador']; ?>">
            <button class="btn btn-blue" type="submit">
              <svg viewBox="0 0 24 24"><path d="M3 12a9 9 0 019-9 9.75 9.75 0 016.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 01-9 9 9.75 9.75 0 01-6.74-2.74L3 16"/><path d="M3 21v-5h5"/></svg>
              Reactivar
            </button>
          </form>
        <?php endif; ?>
      </div>
    </div>

    <div class="profile-card">
      <div class="profile-hero">
        <div class="profile-identity">
          <div class="worker-avatar-lg"><?php echo e($inicialTrabajador ?: 'TR'); ?></div>
          <div>
            <div class="worker-main-name"><?php echo e($nombreCompleto ?: 'Sin nombre'); ?><span class="folio" title="Identificador interno del trabajador (referencia para soporte)">Folio #<?php echo str_pad((int)$trabajador['id_trabajador'], 4, '0', STR_PAD_LEFT); ?></span></div>
            <div class="worker-main-role"><?php
              $areaCargo = array_filter([$trabajador['nombre_area'] ?? '', $trabajador['nombre_cargo'] ?? ''],
                  fn($v) => $v !== '' && !in_array($v, ['Sin área', 'Sin cargo'], true));
              echo $areaCargo ? e(implode(' · ', $areaCargo)) : 'Sin área ni cargo asignados';
            ?></div>
            <div class="worker-main-meta">
              <span class="meta-pill"><?php echo e($tipoDocumento); ?></span>
              <span class="meta-pill"><?php echo e($trabajador['numero_documento'] ?? 'Sin documento'); ?></span>
              <span class="meta-pill"><?php echo e($trabajador['genero_nombre'] ?? 'Sin género'); ?></span>
            </div>
          </div>
        </div>

        <?php if ($estadoTrabajador === 1): ?>
          <span class="status-pill status-active"><span class="status-dot"></span>Activo</span>
        <?php else: ?>
          <span class="status-pill status-inactive"><span class="status-dot"></span>Inactivo</span>
        <?php endif; ?>
      </div>

      <div class="profile-body">

        <div class="view-grid">

          <div>
            <section class="info-section">
              <div class="section-head">
                <div class="section-title">Información personal</div>
                <div class="section-tag">Datos base</div>
              </div>

              <div class="info-grid">
                <div class="info-item"><div class="info-label">Nombres</div><div class="info-value"><?php echo e(dato($trabajador['nombres'] ?? '')); ?></div></div>
                <div class="info-item"><div class="info-label">Apellidos</div><div class="info-value"><?php echo e(dato($trabajador['apellidos'] ?? '')); ?></div></div>
                <div class="info-item"><div class="info-label">Fecha nacimiento</div><div class="info-value"><?php echo e(dato(formatoFecha($trabajador['fecha_nacimiento'] ?? ''))); ?></div></div>
                <div class="info-item"><div class="info-label">Lugar nacimiento</div><div class="info-value"><?php
                  $lugarCatalogo = nombreLugar($conexion, $trabajador['codigo_ciudad_nacimiento'] ?? null);
                  if ($lugarCatalogo !== '') {
                      echo e($lugarCatalogo);
                  } elseif (trim((string)($trabajador['lugar_nacimiento'] ?? '')) !== '') {
                      echo e($trabajador['lugar_nacimiento']) . ' <span class="lugar-revisar" title="Dato antiguo en texto libre: elige departamento y ciudad en Editar">Por revisar</span>';
                  } else {
                      echo 'Sin registrar';
                  }
                ?></div></div>
                <div class="info-item"><div class="info-label">Nacionalidad</div><div class="info-value"><?php echo e($nacionalidad); ?></div></div>
                <div class="info-item"><div class="info-label">Estado civil</div><div class="info-value"><?php echo e($estadoCivil); ?></div></div>
                <div class="info-item"><div class="info-label">Grupo étnico</div><div class="info-value"><?php echo e($grupoEtnico); ?></div></div>
              </div>
            </section>

            <section class="info-section">
              <div class="section-head">
                <div class="section-title">Información laboral</div>
                <div class="section-tag">RRHH</div>
              </div>

              <div class="info-grid">
                <div class="info-item"><div class="info-label">Formación educativa</div><div class="info-value"><?php echo e($formacion); ?></div></div>
                <div class="info-item"><div class="info-label">Fecha de ingreso</div><div class="info-value"><?php echo e(dato(formatoFecha($trabajador['fecha_ingreso'] ?? ''))); ?></div></div>
              </div>
              <div class="section-nota">El área, el cargo y el estado se muestran arriba, en el encabezado de la ficha.</div>
            </section>
          </div>

          <div class="side-column">
            <section class="side-card">
              <div class="section-head">
                <div class="section-title">Contacto</div>
              </div>
              <div class="info-grid">
                <div class="info-item"><div class="info-label">Correo electrónico</div><div class="info-value"><?php echo e(dato($trabajador['correo_personal'] ?? '')); ?></div></div>
                <div class="info-item"><div class="info-label">Teléfono</div><div class="info-value"><?php echo e(dato($trabajador['celular'] ?? '')); ?></div></div>
              </div>
            </section>

            <section class="side-card">
              <div class="section-head">
                <div class="section-title">Salud / SG-SST</div>
              </div>
              <div class="info-grid">
                <div class="info-item"><div class="info-label">EPS</div><div class="info-value"><?php echo empty($trabajador['id_eps']) ? '<span style="display:inline-block;font-size:11px;font-weight:700;color:#b45309;background:#fffbeb;border:1px solid #fde68a;border-radius:20px;padding:3px 9px">EPS pendiente de verificar</span>' : e($eps); ?></div></div>
                <div class="info-item"><div class="info-label">Tipo de sangre</div><div class="info-value"><?php echo e($tipoSangre); ?></div></div>
              </div>
            </section>

            <section class="side-card">
              <div class="section-head">
                <div class="section-title">Registro</div>
              </div>
              <div class="record-item">
                <div class="record-dot"></div>
                <div>
                  <div class="record-title">Trabajador registrado</div>
                  <div class="record-sub"><?php echo e(dato(formatoFechaHora($trabajador['created_at'] ?? ''))); ?></div>
                </div>
              </div>
              <div class="record-item">
                <div class="record-dot"></div>
                <div>
                  <div class="record-title">Última actualización</div>
                  <div class="record-sub"><?php echo e(dato(formatoFechaHora($trabajador['updated_at'] ?? ''))); ?></div>
                </div>
              </div>
            </section>
          </div>

        </div>
      </div>
    </div>

  </div>

  <footer class="footer-app">
    <strong>PlastyPetco S.A.S</strong> &middot; Sistema de Gestión RRHH + SG-SST &nbsp;&middot;&nbsp; Versión 1.0.0
  </footer>

</div>
</div>


<div class="custom-modal-backdrop" id="modalInactivar">
  <div class="custom-modal-card">
    <div class="custom-modal-icon">
      <svg viewBox="0 0 24 24">
        <path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
        <line x1="12" y1="9" x2="12" y2="13"/>
        <line x1="12" y1="17" x2="12.01" y2="17"/>
      </svg>
    </div>

    <h3>¿Marcar este trabajador como inactivo?</h3>

    <p>
      El trabajador <strong id="nombreTrabajadorInactivar"></strong> no se eliminará de la base de datos.
      Solo cambiará su estado a inactivo y podrás reactivarlo después.
    </p>

    <form action="inactivar.php" method="POST" id="formInactivarTrabajador"><?php echo campoCsrf(); ?>
      <input type="hidden" name="volver" value="ver">
      <input type="hidden" name="id_trabajador" id="idTrabajadorInactivar">

      <div class="custom-modal-actions">
        <button type="button" class="btn-modal-cancel" onclick="cerrarModalInactivar()">Cancelar</button>
        <button type="submit" class="btn-modal-danger">Sí, inactivar</button>
      </div>
    </form>
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

function abrirModalInactivar(idTrabajador, nombreTrabajador){
  var modal = document.getElementById('modalInactivar');
  var inputId = document.getElementById('idTrabajadorInactivar');
  var nombre = document.getElementById('nombreTrabajadorInactivar');

  if(!modal || !inputId || !nombre) return;

  inputId.value = idTrabajador;
  nombre.textContent = nombreTrabajador || 'este trabajador';
  modal.classList.add('open');
}

function cerrarModalInactivar(){
  var modal = document.getElementById('modalInactivar');
  if(modal) modal.classList.remove('open');
}

document.addEventListener('keydown',function(e){
  if(e.key === 'Escape') cerrarModalInactivar();
});

document.addEventListener('click',function(e){
  var modal = document.getElementById('modalInactivar');
  if(modal && e.target === modal) cerrarModalInactivar();
});

</script>

</body>
</html>
