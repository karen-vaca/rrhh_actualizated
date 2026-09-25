<?php
require_once __DIR__ . '/../../config/auth.php';
requerirAcceso();
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
require_once __DIR__ . '/servicio_examenes.php';

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

/** Etiqueta legible por tipo de examen (catálogo tipo_examen) */
function tipoExamenLabel(string $tipo): string {
    global $tiposExamen;
    return $tiposExamen[$tipo]['nombre'] ?? ucfirst($tipo);
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
$tiposExamen = [];
$vencidos = $porVencer = $realizadosMes = $programados = 0;
$errorConsulta = null;
$trabajadoresParaSelect = [];

try {
    $tiposExamen = tiposExamen($conexion);

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
        $where[] = "te.codigo = ?";
        $params[] = $tipoFiltro;
    }
    if ($estadoFiltro !== '') {
        $where[] = "e.estado = ?";
        $params[] = $estadoFiltro;
    }

    $sql = "
        SELECT
            e.id_examenes_medicos AS id_examen, e.id_trabajador, te.codigo AS tipo, e.fecha_programada,
            e.fecha_realizado, e.resultado, e.estado, e.proxima_fecha, e.concepto_alturas,
            t.nombres, t.apellidos, t.numero_documento
        FROM examenes_medicos e
        JOIN tipo_examen te ON te.id_examen = e.id_examen
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
        . 'Verifica que se haya ejecutado db/migrate_examenes_medicos.sql.';
    // error_log($e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Exámenes Médicos | PlastyPetco</title>
<?php
// Pendiente de migrar a assets/css/componentes.css (tarjetas, filtros, tabla, avatares):
// esta pantalla todavía usa su propio CSS con los mismos nombres de clase.
$componentesPendientes = true;
require __DIR__ . '/../components/estilos_base.php';
?>
<style>
:root{--bg:#f2f5f3}
svg{fill:none;stroke:currentColor;stroke-width:1.8}
.page-header{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap}
.page-header-left{display:flex;align-items:center;gap:14px}
.page-icon{width:52px;height:52px;background:var(--green-mist);border:1px solid rgba(45,223,110,.2);border-radius:14px;
  display:flex;align-items:center;justify-content:center;color:var(--green-dark);flex-shrink:0}
.page-icon svg{width:24px;height:24px}
.page-title{font-family:var(--tx-fuente-titulos);font-size:var(--tx-titulo-pagina);font-weight:var(--tx-peso-titulo-pagina);color:var(--tx-color);letter-spacing:-.4px;line-height:1.1}
.page-sub{font-size:var(--tx-subtitulo);font-weight:var(--tx-peso-normal);color:var(--tx-color-suave);margin-top:3px}
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
.table-count{font-size:var(--tx-ayuda);color:var(--tx-color-suave)}
table{width:100%;border-collapse:collapse}
thead tr{background:var(--bg);border-bottom:1px solid var(--border)}
thead th{padding:11px 16px;font-size:11px;font-weight:700;color:var(--text-soft);text-transform:uppercase;
  letter-spacing:.8px;white-space:nowrap;text-align:left}
tbody tr{border-bottom:1px solid var(--border);transition:background .15s}
tbody tr:last-child{border-bottom:none}
tbody tr:hover{background:#f7fbf8}
tbody td{padding:13px 16px;font-size:var(--tx-valor);color:var(--tx-color);vertical-align:middle}
.worker-cell{display:flex;align-items:center;gap:10px}
.worker-avatar{width:38px;height:38px;border-radius:50%;display:flex;align-items:center;justify-content:center;
  color:#fff;font-family:'Syne';font-weight:700;font-size:13px;box-shadow:0 2px 8px rgba(0,0,0,.12);flex-shrink:0}
.worker-name{font-size:var(--tx-valor);font-weight:var(--tx-peso-enfasis);color:var(--tx-color);line-height:1.2}
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
.form-section-title{font-size:var(--tx-etiqueta);font-weight:var(--tx-peso-titulo);text-transform:uppercase;letter-spacing:.8px;
  color:var(--text-soft);border-bottom:1px solid var(--border);padding-bottom:8px;margin-bottom:14px}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px}
.form-group{display:flex;flex-direction:column;gap:5px}
.form-group.full{grid-column:span 2}
.form-label{font-size:var(--tx-etiqueta-form);font-weight:var(--tx-peso-enfasis);color:var(--tx-color-medio);letter-spacing:.3px}
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
.modal-title{font-family:var(--tx-fuente-titulos);font-size:var(--tx-titulo-modal);font-weight:var(--tx-peso-titulo-pagina);color:var(--tx-color)}
.modal-sub{font-size:var(--tx-ayuda);font-weight:var(--tx-peso-normal);color:var(--tx-color-suave);line-height:1.45;margin-top:3px}
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
@media(max-width:900px){
  .mini-stats{grid-template-columns:1fr 1fr}.form-row{grid-template-columns:1fr}.form-group.full{grid-column:span 1}}
@media(max-width:640px){thead th:nth-child(2),tbody td:nth-child(2){display:none}}
.form-help{margin-top:5px;font-size:11.5px;line-height:1.4;color:var(--text-soft)}
</style>
</head>
<body>
<div class="layout">

  <?php $paginaActiva = 'examenes'; require __DIR__ . '/../components/sidebar.php'; ?>

  <div class="main">
    <?php
    $tituloTopbar = 'Exámenes Médicos';
    $busquedaTopbar = ['placeholder' => 'Buscar trabajadores, exámenes...'];
    require __DIR__ . '/../components/topbar.php';
    ?>

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
            <?php foreach ($tiposExamen as $codigo => $t): ?>
              <option value="<?php echo htmlspecialchars($codigo) ?>" <?php echo $tipoFiltro === $codigo ? 'selected' : '' ?>><?php echo htmlspecialchars($t['nombre']) ?></option>
            <?php endforeach; ?>
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
                    $map = ['apto' => 'badge-ok', 'restriccion' => 'badge-warn', 'no_apto' => 'badge-urgent', 'pendiente' => 'badge-info'];
                    $lbl = ['apto' => 'Apto', 'restriccion' => 'Con restricción', 'no_apto' => 'No apto', 'pendiente' => 'Pendiente'];
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
          <form class="modal-body" method="POST" action="programar_examen.php"><?php echo campoCsrf(); ?>
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
                    <?php foreach ($tiposExamen as $codigo => $t): ?>
                      <option value="<?php echo htmlspecialchars($codigo) ?>" <?php echo $codigo === 'periodico' ? 'selected' : '' ?>><?php echo htmlspecialchars($t['nombre']) ?></option>
                    <?php endforeach; ?>
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
          <form class="modal-body" method="POST" action="registrar_resultado.php"><?php echo campoCsrf(); ?>
            <input type="hidden" name="id_examen" id="inputIdExamen" value="">
            <div class="form-section">
              <div class="form-row">
                <div class="form-group">
                  <label class="form-label">Fecha de realización</label>
                  <input class="form-input" type="date" name="fecha_realizado" required max="<?php echo date('Y-m-d') ?>">
                </div>
                <div class="form-group">
                  <label class="form-label">Concepto / Resultado</label>
                  <select class="form-select" name="resultado" required>
                    <option value="">Seleccione...</option>
                    <?php foreach (RESULTADOS_EXAMEN as $codigo => $nombre): ?>
                      <option value="<?php echo $codigo ?>"><?php echo htmlspecialchars($nombre) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="form-group full">
                  <label class="form-label">Concepto de trabajo en alturas (opcional)</label>
                  <select class="form-select" name="concepto_alturas">
                    <option value="">No aplica / no se evaluó</option>
                    <?php foreach (CONCEPTOS_ALTURAS as $codigo => $nombre): ?>
                      <option value="<?php echo $codigo ?>"><?php echo htmlspecialchars($nombre) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="form-group full">
                  <label class="form-label">Próxima fecha de examen (vencimiento)</label>
                  <input class="form-input" type="date" name="proxima_fecha">
                  <div class="form-help">Si la dejas vacía se calcula automáticamente: 1 año después de la fecha de realización. El examen de retiro no genera próximo examen.</div>
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