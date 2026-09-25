<?php
require_once __DIR__ . '/../../config/auth.php';
requerirAcceso();
require_once __DIR__ . '/../../config/conexion.php';
require_once __DIR__ . '/funciones_contrato.php';
require_once __DIR__ . '/validaciones_contrato.php';

// Editar Contrato: formulario de una sola pantalla (misma estructura que Editar Trabajador).
// El trabajador se muestra de solo lectura y el formulario no envía id_trabajador: un
// contrato no se reasigna. guardar.php además rechaza cualquier id_trabajador distinto del
// original (por si el formulario se manipula). Crear y renovar siguen usando el asistente.

$idContrato = (int)($_GET['id'] ?? 0);
$contrato = $idContrato > 0 ? (consultarContratos($conexion, $idContrato)[0] ?? null) : null;
if (!$contrato) {
    header('Location: index.php?mensaje=id_invalido');
    exit;
}
if (($contrato['estado'] ?? '') === 'Terminado') {
    header('Location: ver.php?id=' . $idContrato . '&mensaje=terminado_no_editable');
    exit;
}

$areas = $conexion->query('SELECT id_areas, nombre_area FROM areas ORDER BY nombre_area')->fetchAll(PDO::FETCH_ASSOC);
$cargos = $conexion->query('SELECT id_cargo, nombre_cargo FROM cargos ORDER BY nombre_cargo')->fetchAll(PDO::FETCH_ASSOC);
$tipos = $conexion->query('SELECT id_tipos_contrato, contrato FROM tipos_contrato ORDER BY contrato')->fetchAll(PDO::FETCH_ASSOC);
$sugerenciasJefe = $conexion->query("
    SELECT DISTINCT nombre FROM (
        SELECT TRIM(CONCAT(nombres, ' ', apellidos)) AS nombre FROM trabajadores WHERE estado = 1
        UNION
        SELECT TRIM(jefe_inmediato) FROM contratos WHERE jefe_inmediato IS NOT NULL AND TRIM(jefe_inmediato) <> ''
    ) j ORDER BY nombre
")->fetchAll(PDO::FETCH_COLUMN);
$st = $conexion->prepare('SELECT fecha_ingreso FROM trabajadores WHERE id_trabajador = ?');
$st->execute([(int)$contrato['id_trabajador']]);
$fechaIngreso = (string)($st->fetchColumn() ?: '');

$opcionesJornada = ['Completa (46h/sem)', 'Medio tiempo', 'Por turnos', 'Flexible'];
$opcionesModalidad = ['Presencial', 'Remoto', 'Híbrido'];
$opcionesPrueba = ['Sin periodo', '1 mes', '2 meses', '3 meses (máximo)'];

// Si el guardado fue rechazado, guardar.php vuelve aquí con el motivo.
$error = match ($_GET['mensaje'] ?? '') {
    'validacion' => $_GET['texto'] ?? 'Hay datos no válidos.',
    'datos_incompletos' => 'Faltan datos obligatorios.',
    'monto_invalido' => 'El salario o el auxilio de transporte no son válidos.',
    'error' => 'Ocurrió un error al guardar. Intenta de nuevo.',
    default => null,
};
$campoError = $_GET['campo'] ?? '';

$sel = static fn($a, $b) => (string)$a === (string)$b ? ' selected' : '';
$monto = static fn($v) => number_format((float)$v, 0, ',', '.');
$estado = $contrato['estado_mostrar'] ?? 'Activo';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Editar Contrato | PlastyPetco</title>
<?php require __DIR__ . '/../components/estilos_base.php'; ?>
<link rel="stylesheet" href="../../assets/css/ficha.css">
<link rel="stylesheet" href="../../assets/css/edicion.css">
<style>
textarea.edit-input{height:auto;min-height:96px;padding:10px 13px;line-height:1.5;resize:vertical}
.edit-input[readonly]{background:#f6f9f7;color:var(--text-mid);cursor:not-allowed}
.edit-ayuda{font-size:var(--tx-ayuda-chica);color:var(--tx-color-suave);margin-top:4px;line-height:1.4}
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
    <div class="page-header">
      <div class="page-header-left">
        <div class="page-icon">
          <svg viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 013 3L7 19l-4 1 1-4 12.5-12.5z"/></svg>
        </div>
        <div>
          <div class="page-title tx-titulo-pagina">Editar Contrato</div>
          <div class="page-sub tx-subtitulo">Actualiza las condiciones, la vigencia y el salario del contrato.</div>
        </div>
      </div>
      <div class="page-header-right">
        <a href="index.php" class="btn btn-outline">
          <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
          Volver
        </a>
        <a href="ver.php?id=<?= $idContrato ?>" class="btn btn-outline">
          <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
          Ver ficha
        </a>
      </div>
    </div>

    <?php if ($error): ?>
      <div class="edit-alert" role="alert"><?= e($error) ?></div>
    <?php endif; ?>

    <form action="guardar.php" method="POST" class="edit-card" id="formEditarContrato" data-validar novalidate><?= campoCsrf() ?>
      <input type="hidden" name="accion" value="actualizar">
      <input type="hidden" name="contrato_id" value="<?= $idContrato ?>">
      <input type="hidden" name="volver" value="editar">

      <div class="edit-hero">
        <div class="edit-identity">
          <div class="edit-avatar <?= e($contrato['avatar_class']) ?>"><?= e($contrato['iniciales']) ?></div>
          <div>
            <div class="edit-name"><?= e($contrato['trabajador_nombre'] ?: 'Trabajador sin nombre') ?><span class="folio" title="Número interno del contrato (referencia para soporte)">Contrato #<?= str_pad((string)$idContrato, 4, '0', STR_PAD_LEFT) ?></span></div>
            <div class="edit-meta">
              <span class="edit-pill"><?= e($contrato['numero_documento'] ?: 'Sin documento') ?></span>
              <span class="edit-pill"><?= e($estado) ?></span>
            </div>
          </div>
        </div>
        <div class="edit-hint">El trabajador de un contrato no se puede cambiar. Si es incorrecto, crea un contrato nuevo para el trabajador correcto.</div>
      </div>

      <div class="edit-body">
        <section class="edit-section">
          <div class="edit-section-head">
            <div class="edit-section-title">Trabajador</div>
            <div class="edit-section-tag">Solo lectura</div>
          </div>
          <div class="edit-form-grid">
            <div class="edit-field">
              <label class="edit-label" for="trabajadorNombre">Nombre</label>
              <input class="edit-input" id="trabajadorNombre" type="text" value="<?= e($contrato['trabajador_nombre']) ?>" readonly aria-readonly="true">
            </div>
            <div class="edit-field">
              <label class="edit-label" for="trabajadorDocumento">Documento</label>
              <input class="edit-input" id="trabajadorDocumento" type="text" value="<?= e($contrato['numero_documento']) ?>" readonly aria-readonly="true">
            </div>
          </div>
        </section>

        <section class="edit-section">
          <div class="edit-section-head">
            <div class="edit-section-title">Cargo y ubicación</div>
            <div class="edit-section-tag">RRHH</div>
          </div>
          <div class="edit-form-grid">
            <div class="edit-field">
              <label class="edit-label" for="id_area">Área *</label>
              <select class="edit-select" name="id_area" id="id_area" required>
                <option value="">Seleccionar área</option>
                <?php foreach ($areas as $a): ?>
                  <option value="<?= (int)$a['id_areas'] ?>"<?= $sel($a['id_areas'], $contrato['id_area'] ?? '') ?>><?= e($a['nombre_area']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="edit-field">
              <label class="edit-label" for="id_cargo">Cargo *</label>
              <select class="edit-select" name="id_cargo" id="id_cargo" required>
                <option value="">Seleccionar cargo</option>
                <?php foreach ($cargos as $cg): ?>
                  <option value="<?= (int)$cg['id_cargo'] ?>"<?= $sel($cg['id_cargo'], $contrato['id_cargo'] ?? '') ?>><?= e($cg['nombre_cargo']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="edit-field">
              <label class="edit-label" for="jefe_inmediato">Jefe inmediato *</label>
              <input class="edit-input<?= $campoError === 'jefe_inmediato' ? ' has-error' : '' ?>" type="text" name="jefe_inmediato" id="jefe_inmediato" maxlength="120" list="listaJefes" required value="<?= e($contrato['jefe_inmediato'] ?? '') ?>">
              <datalist id="listaJefes">
                <?php foreach ($sugerenciasJefe as $j): ?><option value="<?= e($j) ?>"><?php endforeach; ?>
              </datalist>
            </div>
            <div class="edit-field">
              <label class="edit-label" for="fecha_ingreso">Fecha de ingreso del trabajador</label>
              <input class="edit-input" type="date" name="fecha_ingreso" id="fecha_ingreso" value="<?= e($fechaIngreso) ?>">
              <div class="edit-ayuda">Se guarda en la ficha del trabajador.</div>
            </div>
          </div>
        </section>

        <section class="edit-section">
          <div class="edit-section-head">
            <div class="edit-section-title">Condiciones</div>
            <div class="edit-section-tag">Contrato</div>
          </div>
          <div class="edit-form-grid">
            <div class="edit-field">
              <label class="edit-label" for="id_tipos_contrato">Tipo de contrato *</label>
              <select class="edit-select" name="id_tipos_contrato" id="id_tipos_contrato" required>
                <option value="">Seleccionar</option>
                <?php foreach ($tipos as $t): ?>
                  <option value="<?= (int)$t['id_tipos_contrato'] ?>"<?= $sel($t['id_tipos_contrato'], $contrato['id_tipos_contrato'] ?? '') ?>><?= e($t['contrato']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="edit-field">
              <label class="edit-label" for="jornada">Jornada</label>
              <select class="edit-select" name="jornada" id="jornada">
                <?php foreach ($opcionesJornada as $o): ?><option<?= $sel($o, $contrato['jornada'] ?? '') ?>><?= e($o) ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="edit-field">
              <label class="edit-label" for="modalidad">Modalidad</label>
              <select class="edit-select" name="modalidad" id="modalidad">
                <?php foreach ($opcionesModalidad as $o): ?><option<?= $sel($o, $contrato['modalidad'] ?? '') ?>><?= e($o) ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="edit-field">
              <label class="edit-label" for="periodo_prueba">Periodo de prueba</label>
              <select class="edit-select" name="periodo_prueba" id="periodo_prueba">
                <?php foreach ($opcionesPrueba as $o): ?><option<?= $sel($o, $contrato['periodo_prueba'] ?? '') ?>><?= e($o) ?></option><?php endforeach; ?>
              </select>
            </div>
          </div>
        </section>

        <section class="edit-section">
          <div class="edit-section-head">
            <div class="edit-section-title">Vigencia</div>
            <div class="edit-section-tag">Fechas</div>
          </div>
          <div class="edit-form-grid">
            <div class="edit-field">
              <label class="edit-label" for="fecha_inicio">Fecha de inicio *</label>
              <input class="edit-input<?= $campoError === 'fecha_inicio' ? ' has-error' : '' ?>" type="date" name="fecha_inicio" id="fecha_inicio" required value="<?= e($contrato['fecha_inicio'] ?? '') ?>">
            </div>
            <div class="edit-field">
              <label class="edit-label" for="fecha_fin">Fecha de fin</label>
              <input class="edit-input<?= $campoError === 'fecha_fin' ? ' has-error' : '' ?>" type="date" name="fecha_fin" id="fecha_fin" value="<?= e($contrato['fecha_fin'] ?? '') ?>">
              <div class="edit-ayuda">Déjala vacía si el contrato es a término indefinido.</div>
            </div>
          </div>
        </section>

        <section class="edit-section">
          <div class="edit-section-head">
            <div class="edit-section-title">Salario</div>
            <div class="edit-section-tag">Nómina</div>
          </div>
          <div class="edit-form-grid">
            <div class="edit-field">
              <label class="edit-label" for="salario_base">Salario base mensual *</label>
              <input class="edit-input<?= $campoError === 'salario_base' ? ' has-error' : '' ?>" type="text" data-dinero name="salario_base" id="salario_base" required value="<?= e($monto($contrato['salario_base'] ?? 0)) ?>">
              <div class="edit-ayuda">Sin auxilio de transporte. Los puntos de miles se ponen solos.</div>
            </div>
            <div class="edit-field">
              <label class="edit-label" for="auxilio_transporte">Auxilio de transporte</label>
              <input class="edit-input<?= $campoError === 'auxilio_transporte' ? ' has-error' : '' ?>" type="text" data-dinero name="auxilio_transporte" id="auxilio_transporte" value="<?= e($monto($contrato['auxilio_transporte'] ?? 0)) ?>">
              <div class="edit-ayuda">Valor legal <?= date('Y') ?>: $<?= $monto(AUXILIO_TRANSPORTE_VIGENTE) ?>, solo si el salario es hasta <?= TOPE_SALARIOS_MINIMOS_AUXILIO ?> salarios mínimos ($<?= $monto(TOPE_SALARIOS_MINIMOS_AUXILIO * SALARIO_MINIMO_VIGENTE) ?>).</div>
            </div>
          </div>
        </section>

        <section class="edit-section">
          <div class="edit-section-head">
            <div class="edit-section-title">Observaciones</div>
            <div class="edit-section-tag">Notas</div>
          </div>
          <div class="edit-form-grid">
            <div class="edit-field full">
              <label class="edit-label" for="observaciones">Observaciones del contrato</label>
              <textarea class="edit-input" name="observaciones" id="observaciones" rows="4"><?= e($contrato['observaciones'] ?? '') ?></textarea>
            </div>
          </div>
        </section>
      </div>

      <div class="edit-actions">
        <a href="ver.php?id=<?= $idContrato ?>" class="btn btn-outline">Cancelar</a>
        <button type="submit" class="btn btn-primary">
          <svg viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
          Guardar cambios
        </button>
      </div>
    </form>
  </div>

  <footer class="footer-app">
    <strong>PlastyPetco S.A.S</strong> &middot; Sistema de Gestión RRHH + SG-SST &nbsp;&middot;&nbsp; Versión 1.0.0
  </footer>
</div>
</div>
<script>
// Reglas propias además de los campos obligatorios (validación compartida: data-validar,
// assets/js/formularios.js). Se revisan al enviar; el error aparece bajo el campo.
document.addEventListener('DOMContentLoaded', function () {
  var f = document.getElementById('formEditarContrato');
  f.reglasValidacion = [
    function () {
      var ini = f.elements.fecha_inicio, fin = f.elements.fecha_fin;
      return ini.value && fin.value && fin.value < ini.value
        ? { campo: fin, mensaje: 'La fecha de fin no puede ser anterior a la fecha de inicio.' } : null;
    },
    function () {
      var s = f.elements.salario_base;
      return s.value && Number(Formularios.soloDigitos(s.value)) <= 0
        ? { campo: s, mensaje: 'El salario debe ser mayor que cero.' } : null;
    }
  ];
});
</script>
</body>
</html>
