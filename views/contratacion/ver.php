<?php
require_once __DIR__ . '/../../config/auth.php';
requerirAcceso();
require_once __DIR__ . '/../../config/conexion.php';
require_once __DIR__ . '/funciones_contrato.php';

// Ficha del Contrato: página completa (antes era un panel lateral en el listado).
// Hace su propia consulta con consultarContratos(), la misma que usa el listado, así el
// trabajador vinculado, el estado y la nómina se calculan igual en ambos lados.
// Diseño compartido con la Ficha del Trabajador: assets/css/ficha.css.

$idContrato = (int)($_GET['id'] ?? 0);
$contrato = $idContrato > 0 ? (consultarContratos($conexion, $idContrato)[0] ?? null) : null;
if (!$contrato) {
    header('Location: index.php?mensaje=id_invalido');
    exit;
}

$estado = $contrato['estado_mostrar'] ?? 'Activo';
$claseEstado = match ($estado) {
    'Activo' => 'status-active',
    'Por vencer' => 'status-warning',
    'Terminado' => 'status-muted',
    default => 'status-inactive',   // Vencido, Inactivo
};
$terminado = ($contrato['estado'] ?? '') === 'Terminado';
$nomina = calcularNominaMensual($contrato['salario_base'] ?? 0, $contrato['auxilio_transporte'] ?? 0);
$idTrabajador = (int)($contrato['id_trabajador'] ?? 0);
$dato = static fn($v, string $vacio = 'Sin registrar') => trim((string)$v) !== '' ? (string)$v : $vacio;

// Situación de la vigencia en palabras (días hasta el fin o desde que venció).
$situacion = 'Contrato a término indefinido.';
if ($terminado) {
    $situacion = 'Contrato terminado.';
} elseif (!empty($contrato['fecha_fin']) && $contrato['fecha_fin'] !== '0000-00-00') {
    $dias = (int)(new DateTimeImmutable('today'))->diff(new DateTimeImmutable($contrato['fecha_fin']))->format('%r%a');
    $situacion = match (true) {
        $dias > 1 => "Vence en $dias días.",
        $dias === 1 => 'Vence mañana.',
        $dias === 0 => 'Vence hoy.',
        $dias === -1 => 'Venció ayer.',
        default => 'Venció hace ' . abs($dias) . ' días.',
    };
}

// Documentos de vinculación: se generan bajo demanda (generar_documento.php) y cada
// generación queda registrada en documentos_generados.
$documentos = [
    'contrato' => ['Contrato laboral', 'CT', 'contract', 'contrato'],
    'perfil_cargo' => ['Perfil de cargo', 'PC', 'profile', 'perfil'],
    'induccion' => ['Formato de inducción', 'IN', 'training', 'induccion'],
];
$generados = [];
try {
    $st = $conexion->prepare('SELECT tipo_documento, MAX(fecha_generacion) AS ultima FROM documentos_generados WHERE id_contrato = ? GROUP BY tipo_documento');
    $st->execute([$idContrato]);
    $generados = $st->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (Throwable $e) {
    $generados = [];
}
$fechaHora = static fn($v) => $v ? date('d/m/Y h:i A', strtotime((string)$v)) : '—';
// Resultado de la última acción (componente compartido de avisos: views/components/notificaciones.php).
$nombreTrabajador = trim((string)($contrato['trabajador_nombre'] ?? '')) ?: 'este trabajador';
$aviso = match ($_GET['mensaje'] ?? '') {
    'actualizado' => ['ok', 'Contrato actualizado correctamente.'],
    'terminado' => ['ok', 'Se ha finalizado el contrato de ' . $nombreTrabajador . '.'],
    'terminado_no_editable' => ['aviso', 'Este contrato está terminado: ya no se puede editar. Para continuar la relación laboral, renuévalo o crea una contratación nueva.'],
    default => null,
};
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Ficha del Contrato | PlastyPetco</title>
<?php require __DIR__ . '/../components/estilos_base.php'; ?>
<link rel="stylesheet" href="../../assets/css/ficha.css">
<style>
/* Resumen de nómina y documentos (mismos estilos que el asistente de contratación) */
.doc-icon{width:38px;height:38px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-weight:900}
.doc-icon.contract{background:#e8f8ed;color:#128a3d}
.doc-icon.profile{background:#eff6ff;color:#2563eb}
.doc-icon.training{background:#fff7ed;color:#c2410c}
.doc-list{display:flex;flex-direction:column;gap:10px}
.doc-list-item{display:flex;align-items:center;gap:12px;background:#fbfdfb;border:1px solid var(--border);border-radius:14px;padding:12px}
.doc-list-info{flex:1;min-width:0}
.doc-list-title{font-size:13px;font-weight:var(--tx-peso-enfasis)}
.doc-list-meta{font-size:11px;color:var(--text-soft);margin-top:3px}
.doc-status{display:inline-flex;align-items:center;border-radius:999px;background:#ecfdf3;color:#128a3d;border:1px solid #baf7cf;font-size:10.5px;font-weight:var(--tx-peso-titulo);padding:4px 8px}
.nomina-box{background:#f7fffa;border:1px solid rgba(45,223,110,.28);border-radius:18px;padding:15px}
.nomina-row{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:7px 0;font-size:13.5px;color:var(--text-mid)}
.nomina-row strong{color:var(--text);font-weight:var(--tx-peso-enfasis)}
.nomina-row.deduction strong{color:#ef4444}
.nomina-row.total{border-top:1px solid rgba(45,223,110,.28);margin-top:8px;padding-top:13px;font-size:15px;font-weight:var(--tx-peso-titulo);color:var(--text)}
.nomina-row.total strong{font-family:var(--tx-fuente-titulos);color:#128a3d;font-size:18px}
.nomina-note{font-size:11px;color:var(--text-soft);line-height:1.35;margin-top:10px}
.nomina-aviso{font-size:11.5px;font-weight:600;color:#92400e;background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:6px 10px;margin-bottom:8px}
.badge-estimado{display:inline-block;vertical-align:middle;margin-left:6px;font-size:9.5px;font-weight:700;letter-spacing:.5px;text-transform:uppercase;color:#92400e;background:#fffbeb;border:1px solid #fde68a;border-radius:20px;padding:2px 8px}
.doc-list-item .btn{height:34px;padding:0 12px}
.form-en-linea{display:inline-flex;margin:0}
.observaciones{font-size:var(--tx-valor);color:var(--tx-color);line-height:1.55;white-space:pre-line;word-break:break-word}
</style>
</head>
<body>
<div class="layout">
<?php $paginaActiva = 'contratacion'; require __DIR__ . '/../components/sidebar.php'; ?>

<div class="main">
  <?php
  $tituloTopbar = 'Contratación';
  $busquedaTopbar = ['placeholder' => 'Buscar contratos, documentos, cargos...'];
  require __DIR__ . '/../components/topbar.php';
  ?>

  <div class="content">
    <?= $aviso ? avisoAlCargar($aviso[0], $aviso[1]) : '' ?>
    <div class="page-header">
      <div class="page-header-left">
        <div class="page-icon">
          <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
        </div>
        <div>
          <div class="page-title tx-titulo-pagina">Ficha del Contrato</div>
          <div class="page-sub tx-subtitulo">Condiciones, vigencia, nómina estimada y documentos de vinculación.</div>
        </div>
      </div>
      <div class="page-header-right">
        <a href="index.php" class="btn">
          <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
          Volver
        </a>
        <a href="../trabajadores/ver.php?id=<?= $idTrabajador ?>" class="btn">
          <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          Ver trabajador
        </a>
        <?php if (!$terminado): ?>
          <a href="editar.php?id=<?= $idContrato ?>" class="btn btn-primary">
            <svg viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 013 3L7 19l-4 1 1-4 12.5-12.5z"/></svg>
            Editar
          </a>
          <a href="index.php?abrir=renovar&amp;id=<?= $idContrato ?>" class="btn btn-blue">
            <svg viewBox="0 0 24 24"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 11-2.12-9.36L23 10"/></svg>
            Renovar
          </a>
          <!-- Terminar: confirmación compartida (data-confirmar, assets/js/notificaciones.js) y
               vuelve a esta ficha con el aviso "Se ha finalizado el contrato de …". -->
          <form method="POST" action="guardar.php" class="form-en-linea"
                data-confirmar="¿Estás seguro de finalizar el contrato de <?= e($nombreTrabajador) ?>? Esta acción no se puede deshacer."
                data-confirmar-titulo="Finalizar contrato" data-confirmar-boton="Finalizar contrato"><?= campoCsrf() ?>
            <input type="hidden" name="accion" value="terminar">
            <input type="hidden" name="contrato_id" value="<?= $idContrato ?>">
            <input type="hidden" name="volver" value="editar">
            <button type="submit" class="btn btn-danger">
              <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
              Terminar
            </button>
          </form>
        <?php endif; ?>
      </div>
    </div>

    <div class="profile-card">
      <div class="profile-hero">
        <div class="profile-identity">
          <div class="worker-avatar-lg <?= e($contrato['avatar_class']) ?>"><?= e($contrato['iniciales']) ?></div>
          <div>
            <div class="worker-main-name"><?= e($dato($contrato['trabajador_nombre'] ?? '', 'Trabajador sin nombre')) ?><span class="folio" title="Número interno del contrato (referencia para soporte)">Contrato #<?= str_pad((string)$idContrato, 4, '0', STR_PAD_LEFT) ?></span></div>
            <div class="worker-main-role"><?= e(implode(' · ', array_filter([$contrato['cargo'] ?? '', $contrato['area'] ?? '']))) ?></div>
            <div class="worker-main-meta">
              <span class="meta-pill"><?= e($contrato['tipo_contrato'] ?? 'Sin tipo') ?></span>
              <span class="meta-pill"><?= e($dato($contrato['numero_documento'] ?? '', 'Sin documento')) ?></span>
              <span class="meta-pill" title="Folio del trabajador (referencia para soporte)">Trabajador #<?= str_pad((string)$idTrabajador, 4, '0', STR_PAD_LEFT) ?></span>
            </div>
          </div>
        </div>
        <span class="status-pill <?= $claseEstado ?>"><span class="status-dot"></span><?= e($estado) ?></span>
      </div>

      <div class="profile-body">
        <div class="view-grid">
          <div>
            <section class="info-section">
              <div class="section-head">
                <div class="section-title">Datos del contrato</div>
                <div class="section-tag">Contrato</div>
              </div>
              <div class="info-grid">
                <div class="info-item"><div class="info-label">Cargo</div><div class="info-value"><?= e($contrato['cargo']) ?></div></div>
                <div class="info-item"><div class="info-label">Área</div><div class="info-value"><?= e($contrato['area']) ?></div></div>
                <div class="info-item"><div class="info-label">Tipo de contrato</div><div class="info-value"><?= e($contrato['tipo_contrato']) ?></div></div>
                <div class="info-item"><div class="info-label">Estado</div><div class="info-value"><?= e($estado) ?></div></div>
                <div class="info-item"><div class="info-label">Jornada</div><div class="info-value"><?= e($dato($contrato['jornada'] ?? '')) ?></div></div>
                <div class="info-item"><div class="info-label">Modalidad</div><div class="info-value"><?= e($dato($contrato['modalidad'] ?? '')) ?></div></div>
                <div class="info-item"><div class="info-label">Jefe inmediato</div><div class="info-value"><?= e($dato($contrato['jefe_inmediato'] ?? '')) ?></div></div>
              </div>
            </section>

            <section class="info-section">
              <div class="section-head">
                <div class="section-title">Vigencia</div>
                <div class="section-tag">Fechas</div>
              </div>
              <div class="info-grid">
                <div class="info-item"><div class="info-label">Fecha de inicio</div><div class="info-value"><?= e(fmtFecha($contrato['fecha_inicio'] ?? null)) ?></div></div>
                <div class="info-item"><div class="info-label">Fecha de fin</div><div class="info-value"><?= !empty($contrato['fecha_fin']) && $contrato['fecha_fin'] !== '0000-00-00' ? e(fmtFecha($contrato['fecha_fin'])) : 'Indefinido' ?></div></div>
                <div class="info-item"><div class="info-label">Periodo de prueba</div><div class="info-value"><?= e($dato($contrato['periodo_prueba'] ?? '', 'Sin periodo')) ?></div></div>
              </div>
              <div class="section-nota"><?= e($situacion) ?></div>
            </section>

            <section class="info-section">
              <div class="section-head">
                <div class="section-title">Observaciones</div>
                <div class="section-tag">Notas</div>
              </div>
              <div class="observaciones"><?= e($dato($contrato['observaciones'] ?? '', 'Sin observaciones.')) ?></div>
            </section>
          </div>

          <div class="side-column">
            <section class="side-card">
              <div class="section-head">
                <div class="section-title">Nómina mensual <span class="badge-estimado">Estimado</span></div>
              </div>
              <div class="nomina-box">
                <div class="nomina-aviso" role="note">Valor estimado de referencia — no es la liquidación oficial de nómina.</div>
                <div class="nomina-row"><span>Salario base</span><strong><?= e(fmtMoney($nomina['salario_base'])) ?></strong></div>
                <div class="nomina-row"><span>Auxilio de transporte</span><strong><?= e(fmtMoney($nomina['auxilio_transporte'])) ?></strong></div>
                <div class="nomina-row"><span>Total devengado</span><strong><?= e(fmtMoney($nomina['total_devengado'])) ?></strong></div>
                <div class="nomina-row deduction"><span>Salud (4%)</span><strong>-<?= e(fmtMoney($nomina['salud'])) ?></strong></div>
                <div class="nomina-row deduction"><span>Pensión (4%)</span><strong>-<?= e(fmtMoney($nomina['pension'])) ?></strong></div>
                <div class="nomina-row deduction"><span>Total deducciones</span><strong>-<?= e(fmtMoney($nomina['total_deducciones'])) ?></strong></div>
                <div class="nomina-row total"><span>Neto a pagar</span><strong><?= e(fmtMoney($nomina['neto_pagar'])) ?></strong></div>
                <div class="nomina-note">Cálculo aproximado: salud y pensión (4 % cada una) sobre el salario base; el auxilio de transporte suma al total devengado. No incluye horas extra, recargos, otras deducciones ni retenciones.</div>
              </div>
            </section>

            <section class="side-card">
              <div class="section-head">
                <div class="section-title">Documentos de vinculación</div>
              </div>
              <div class="doc-list">
                <?php foreach ($documentos as $clave => [$nombreDoc, $icono, $claseDoc, $tipoParam]): ?>
                  <div class="doc-list-item">
                    <div class="doc-icon <?= $claseDoc ?>"><?= $icono ?></div>
                    <div class="doc-list-info">
                      <div class="doc-list-title"><?= e($nombreDoc) ?></div>
                      <div class="doc-list-meta"><?= isset($generados[$clave]) ? 'Última generación: ' . e($fechaHora($generados[$clave])) : 'Aún no generado' ?></div>
                    </div>
                    <a class="btn" href="generar_documento.php?tipo=<?= $tipoParam ?>&amp;id=<?= $idContrato ?>" target="_blank" rel="noopener" aria-label="Descargar <?= e(mb_strtolower($nombreDoc)) ?>">Descargar</a>
                  </div>
                <?php endforeach; ?>
              </div>
              <div class="section-nota">Cada descarga genera el documento Word con los datos actuales del contrato.</div>
            </section>

            <section class="side-card">
              <div class="section-head">
                <div class="section-title">Registro</div>
              </div>
              <div class="record-item">
                <div class="record-dot"></div>
                <div>
                  <div class="record-title">Contrato registrado</div>
                  <div class="record-sub"><?= e($fechaHora($contrato['created_at'] ?? null)) ?></div>
                </div>
              </div>
              <div class="record-item">
                <div class="record-dot"></div>
                <div>
                  <div class="record-title">Última actualización</div>
                  <div class="record-sub"><?= e($fechaHora($contrato['updated_at'] ?? null)) ?></div>
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
</body>
</html>
