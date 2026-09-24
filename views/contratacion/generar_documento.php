<?php
require_once __DIR__ . '/../../config/auth.php';
requerirAcceso();
        /**
         * MOTOR DE GENERACIÓN DE DOCUMENTOS - PLASTYPETCO
         * Versión 3.0 - SIN TABLAS (solo párrafos)
         */

        ini_set('display_errors', 1);
        error_reporting(E_ALL);

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        require_once __DIR__ . '/../../vendor/autoload.php';
        require_once __DIR__ . '/../../config/conexion.php';
        require_once __DIR__ . '/includes/funciones.php';
require_once __DIR__ . '/includes/encabezado.php';
require_once __DIR__ . '/../components/lugares.php';
require_once __DIR__ . '/includes/pie.php';

        use PhpOffice\PhpWord\PhpWord;
        use PhpOffice\PhpWord\IOFactory;

        if (!isset($conexion) && isset($pdo)) {
            $conexion = $pdo;
        }

        if (!isset($conexion) || !($conexion instanceof PDO)) {
            die('Error: no se encontró conexión PDO.');
        }

        $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $conexion->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $tipo       = $_GET['tipo'] ?? '';
        $idContrato = (int)($_GET['id'] ?? 0);

        if (!$tipo || !$idContrato) {
            die('Error: Parámetros faltantes. Usa ?tipo=contrato|perfil|induccion&id=X');
        }

        // ============================================================
        // OBTENER DATOS
        // ============================================================
        try {
$sql = "SELECT

    -- CONTRATO
    c.id_contrato,
    c.fecha_inicio,
    c.fecha_fin,
    c.salario_base,
    c.auxilio_transporte,
    c.jornada,
    c.modalidad,
    c.periodo_prueba,
    c.estado,
    c.jefe_inmediato,

    -- TRABAJADOR
    t.id_trabajador,
    t.numero_documento,
    t.nombres,
    t.apellidos,
    t.fecha_nacimiento,
    t.lugar_nacimiento,
    t.codigo_ciudad_nacimiento,
    t.celular,
    t.correo_personal,

    -- DATOS LABORALES (con respaldo si datos_laborales está vacía)
    COALESCE(dl.fecha_ingreso, c.fecha_inicio) AS fecha_ingreso,

    -- EMPRESA
    e.nombre_empresa,

    -- SEDE
    s.planta,

    -- CARGO
    ca.id_cargo,
    ca.nombre_cargo,

    -- ÁREA
    a.id_areas,
    a.nombre_area,

    -- TIPO DE CONTRATO
    tc.contrato AS tipo_contrato_nombre

FROM contratos c

INNER JOIN trabajadores t
    ON c.id_trabajador = t.id_trabajador

LEFT JOIN datos_laborales dl
    ON dl.id_trabajador = t.id_trabajador

LEFT JOIN empresa e
    ON e.id_empresa = dl.id_empresa

LEFT JOIN cargos ca
    ON ca.id_cargo = COALESCE(dl.id_cargo, t.id_cargo)

LEFT JOIN areas a
    ON a.id_areas = COALESCE(dl.id_areas, t.id_area)

LEFT JOIN sedes s
    ON s.id_sede = dl.id_sede

INNER JOIN tipos_contrato tc
    ON tc.id_tipos_contrato = c.id_tipos_contrato

WHERE c.id_contrato = :id

LIMIT 1";
            
            $stmt = $conexion->prepare($sql);
            $stmt->execute([':id' => $idContrato]);
            $data = $stmt->fetch();
            
            if (!$data) {
                die('Error: Contrato no encontrado (ID ' . $idContrato . ').');
            }
        } catch (PDOException $e) {
            die('Error SQL: ' . $e->getMessage());
        }

        // ============================================================
        // DATOS DEL TRABAJADOR
        // ============================================================
        $nombreCompleto = trim(($data['nombres'] ?? '') . ' ' . ($data['apellidos'] ?? ''));
        $cedula         = $data['numero_documento'] ?? '0000000';
        $salario        = (float)($data['salario_base'] ?? 0);
        $auxilio        = (float)($data['auxilio_transporte'] ?? 0);
        $fechaInicio    = $data['fecha_inicio'] ?? date('Y-m-d');
        $fechaFin       = $data['fecha_fin'] ?? '';
        $nombreCargo    = $data['nombre_cargo'] ?? 'Sin cargo';
        $idCargo = (int)($data['id_cargo'] ?? 0);
        $idArea = (int)($data['id_areas'] ?? 0);
        $nombreArea     = $data['nombre_area'] ?? 'Sin área';
        $tipoContrato   = $data['tipo_contrato_nombre'] ?? 'Término Indefinido';
        $lugarNacimiento = nombreLugar($conexion, $data['codigo_ciudad_nacimiento'] ?? null) ?: ($data['lugar_nacimiento'] ?? '');
$fechaNacimiento = formatearFecha($data['fecha_nacimiento'] ?? '');

$celular = $data['celular'] ?? '';

$correo = $data['correo_personal'] ?? '';

$jornada = $data['jornada'] ?? 'Completa';

$modalidad = $data['modalidad'] ?? 'Presencial';

$periodoPrueba = $data['periodo_prueba'] ?? '2 meses';

$jefe = $data['jefe_inmediato'] ?? '';

$empresa       = $data['nombre_empresa'] ?? 'PLÁSTICOS Y PET DE COLOMBIA S.A.S';
$representante = 'DEISY PINTO OCAMPO';

$lugarNacimiento = nombreLugar($conexion, $data['codigo_ciudad_nacimiento'] ?? null) ?: ($data['lugar_nacimiento'] ?? '');
$fechaNacimiento = formatearFecha($data['fecha_nacimiento'] ?? '');
$celular         = $data['celular'] ?? '';
$correo          = $data['correo_personal'] ?? '';
$jornada         = $data['jornada'] ?? '';
$modalidad       = $data['modalidad'] ?? '';
$periodoPrueba   = $data['periodo_prueba'] ?? '';
$jefe            = $data['jefe_inmediato'] ?? '';
$planta          = $data['planta'] ?? 'Mosquera';

        // ============================================================
        // OBTENER FUNCIONES, SGI, INDUCCIONES
        // ============================================================
        $stmtFunc = $conexion->prepare("SELECT descripcion FROM funciones_cargo WHERE id_cargo = :id ORDER BY orden ASC");
        $stmtFunc->execute([':id' => $idCargo]);
        $funciones = $stmtFunc->fetchAll();

        $stmtSGI = $conexion->prepare("SELECT categoria, descripcion, aplica_todos FROM responsabilidades_sgi ORDER BY categoria, orden ASC");
        $stmtSGI->execute();
        $responsabilidades = $stmtSGI->fetchAll();

        $stmtInd = $conexion->prepare("SELECT tema FROM inducciones_area WHERE id_area = :id ORDER BY orden ASC");
        $stmtInd->execute([':id' => $idArea]);
        $inducciones = $stmtInd->fetchAll();

        // ============================================================
        // FUNCIONES AUXILIARES
        // ============================================================
        function numeroALetrasContrato($numero) {
            if (function_exists('numfmt_create')) {
                $formatter = new NumberFormatter("es_CO", NumberFormatter::SPELLOUT);
                $entero = floor($numero);
                // ICU inserta guiones suaves (U+00AD) como pista de silabeo en palabras como
                // "setecientos": se quitan para que el texto se pueda buscar y copiar bien.
                $texto = ucfirst(str_replace("\u{AD}", '', $formatter->format($entero)));
                return $texto . ' pesos m/cte';
            }
            return '$ ' . number_format($numero, 0, ',', '.') . ' pesos m/cte';
        }

        function formatearFechaContrato($fecha) {
            if (!$fecha || $fecha === '0000-00-00') return '________________';
            try {
                $dt = new DateTime($fecha);
                $meses = ['enero','febrero','marzo','abril','mayo','junio',
                        'julio','agosto','septiembre','octubre','noviembre','diciembre'];
                return $dt->format('d') . ' de ' . $meses[(int)$dt->format('m') - 1] . ' de ' . $dt->format('Y');
            } catch (Exception $e) {
                return '________________';
            }
        }

        function agregarParrafo($section, $texto, $bold = false, $size = 11, $align = 'both', $spaceAfter = 6) {
            $section->addText($texto, [
                'name' => 'Arial',
                'size' => $size,
                'bold' => $bold,
            ], [
                'alignment'  => $align,
                'spaceAfter' => $spaceAfter,
                'lineHeight' => 1.15,
            ]);
        }

function agregarClausula($section, $titulo)
{
    $section->addText(
        strtoupper($titulo),
        [
            'name' => 'Arial',
            'size' => 11,
            'bold' => true,
            'color' => '0B5394'
        ],
        [
            'spaceBefore' => 8,
            'spaceAfter' => 4,
            'alignment' => 'both'
        ]
    );
}

function agregarTextoContrato($section, $texto)
{
    $section->addText(
        $texto,
        [
            'name' => 'Arial',
            'size' => 10.5
        ],
        [
            'alignment' => 'both',
            'spaceAfter' => 6,
            'lineHeight' => 1.15
        ]
    );
}

        // ============================================================
        // CREAR DOCUMENTO
        // ============================================================
        try {
            $phpWord = new PhpWord();
            $phpWord->setDefaultFontName('Arial');
            $phpWord->setDefaultFontSize(11);
            
            $tituloDocumento = match($tipo){
                'contrato'  => 'Contrato Laboral',
                'perfil'    => 'Perfil de Cargo',
                'induccion' => 'Formato de Inducción',
                default     => 'Documento'
            };

            $logo = __DIR__ . '/../../assets/img/logo_plastypetco.png';

            $section = $phpWord->addSection([
                'marginTop'    => 1134,
                'marginRight'  => 1134,
                'marginBottom' => 1134,
                'marginLeft'   => 1134,
            ]);

            crearEncabezado($section, $tituloDocumento, $logo);
            crearPie($section);

            // (Encabezado y pie de página ya fueron agregados arriba mediante crearEncabezado()/crearPie())
            // ============================================================
            // CONTRATO LABORAL
            // ============================================================
            if ($tipo === 'contrato') {

agregarTitulo($section, 'CONTRATO INDIVIDUAL DE TRABAJO', 16);

agregarParrafo(
    $section,
    strtoupper($tipoContrato),
    true,
    12,
    'center',
    10
);

$introduccion = "Entre los suscritos a saber: {$representante}, quien actúa en representación legal de {$empresa}, ";
$introduccion .= "con domicilio en {$planta}, quien en adelante se denominará EL EMPLEADOR, ";
$introduccion .= "y por la otra {$nombreCompleto}, identificado(a) con cédula de ciudadanía No. {$cedula}, ";
$introduccion .= "quien para efectos del presente contrato se denominará EL TRABAJADOR, ";
$introduccion .= "se celebra el presente contrato individual de trabajo de conformidad con el Código Sustantivo del Trabajo y las siguientes cláusulas.";

agregarTextoContrato($section, $introduccion);


// ============================================================
// PRIMERA
// ============================================================

agregarClausula($section, 'PRIMERA. OBJETO');

agregarTextoContrato(
    $section,
    "EL EMPLEADOR contrata al TRABAJADOR para desempeñar el cargo de {$nombreCargo}, obligándose éste a ejecutar las funciones propias del cargo, las órdenes impartidas por sus superiores y las actividades asignadas dentro del Manual de Funciones, Reglamento Interno de Trabajo y demás procedimientos internos de la compañía."
);


// ============================================================
// SEGUNDA
// ============================================================

agregarClausula($section, 'SEGUNDA. LUGAR DE PRESTACIÓN DEL SERVICIO');

agregarTextoContrato(
    $section,
    "El trabajador prestará sus servicios principalmente en la planta ubicada en {$planta}. Sin embargo, acepta prestar sus servicios en cualquier sede, establecimiento o lugar donde EL EMPLEADOR desarrolle sus actividades cuando las necesidades del servicio así lo requieran."
);


// ============================================================
// TERCERA
// ============================================================

agregarClausula($section, 'TERCERA. JORNADA');

agregarTextoContrato(
    $section,
    "La jornada ordinaria será {$jornada}. EL EMPLEADOR podrá modificar horarios, establecer turnos diurnos, nocturnos o mixtos y programar trabajo suplementario conforme a la legislación laboral vigente y las necesidades del servicio."
);


// ============================================================
// CUARTA
// ============================================================

agregarClausula($section, 'CUARTA. SALARIO');

$textoSalario = "EL EMPLEADOR reconocerá un salario básico mensual de $" .
number_format($salario,0,',','.') .
" (" . numeroALetrasContrato($salario) . ").";

if($auxilio>0){
    $textoSalario .= " Además recibirá un auxilio de transporte por $" .
    number_format($auxilio,0,',','.') .
    ", cuando legalmente tenga derecho a él.";
}

agregarTextoContrato($section,$textoSalario);


// ============================================================
// QUINTA
// ============================================================

agregarClausula($section,'QUINTA. DURACIÓN');

if(stripos($tipoContrato,'indefinido')!==false){

    agregarTextoContrato(
        $section,
        "El presente contrato es a término indefinido y comienza a regir desde el ".
        formatearFechaContrato($fechaInicio).
        ". Las partes acuerdan un período de prueba de {$periodoPrueba}, conforme a la legislación laboral."
    );

}else{

    agregarTextoContrato(
        $section,
        "El presente contrato tendrá vigencia desde el ".
        formatearFechaContrato($fechaInicio).
        " hasta el ".
        formatearFechaContrato($fechaFin).
        ", pudiendo renovarse conforme a la ley."
    );

}

            } // cierra if ($tipo === 'contrato')
            // ============================================================
            // PERFIL DE CARGO
            // ============================================================
            elseif ($tipo === 'perfil') {
                
                agregarTitulo($section, 'PERFIL DE CARGO Y RESPONSABILIDADES', 14);
                agregarParrafo($section, "CÓDIGO: SGI-PCR-01", false, 9, 'right', 2);
                agregarParrafo($section, $empresa, true, 11, 'center', 8);
                
                agregarParrafo($section, '1. IDENTIFICACIÓN DEL CARGO', true, 12, 'left', 6);
                agregarParrafo($section, "Nombre del Cargo: {$nombreCargo}", false, 11, 'left', 4);
                agregarParrafo($section, "Área / Proceso: {$nombreArea}", false, 11, 'left', 4);
                agregarParrafo($section, "Nombre del Trabajador: {$nombreCompleto}", false, 11, 'left', 4);
                agregarParrafo($section, "Cédula: {$cedula}", false, 11, 'left', 8);
                
                agregarParrafo($section, '2. FUNCIONES Y ACTIVIDADES ESPECÍFICAS', true, 12, 'left', 6);
                if (count($funciones) > 0) {
                    foreach ($funciones as $i => $funcion) {
                        agregarParrafo($section, ($i + 1) . ". " . $funcion['descripcion'], false, 10, 'both', 3);
                    }
                }
                
                agregarParrafo($section, '', false, 11, 'both', 8);
                agregarParrafo($section, '3. RESPONSABILIDADES FRENTE AL SGI', true, 12, 'left', 6);
                
                $categorias = [];
                foreach ($responsabilidades as $r) {
                    $cat = $r['categoria'];
                    if (!isset($categorias[$cat])) $categorias[$cat] = [];
                    $categorias[$cat][] = $r;
                }
                
                $nombresCat = [
                    'CALIDAD' => 'Calidad',
                    'SST' => 'Seguridad y Salud en el Trabajo',
                    'AMBIENTAL' => 'Gestión Ambiental',
                    'TRAZABILIDAD_NTC6632' => 'Trazabilidad NTC 6632',
                    'ETICA' => 'Ética Empresarial',
                    'SAGRILAFT' => 'SAGRILAFT',
                    'GOBIERNO_CORPORATIVO' => 'Gobierno Corporativo',
                    'SEGURIDAD_INFORMACION' => 'Seguridad de la Información',
                    'CUMPLIMIENTO_LEGAL' => 'Cumplimiento Legal',
                ];
                
                foreach ($categorias as $cat => $items) {
                    $nombreCat = $nombresCat[$cat] ?? $cat;
                    agregarParrafo($section, "▸ {$nombreCat}", true, 10, 'left', 4);
                    foreach ($items as $item) {
                        $aplica = ($item['aplica_todos']) ? '[Aplica]' : '[Evaluar]';
                        agregarParrafo($section, "   • " . $item['descripcion'] . " " . $aplica, false, 9, 'both', 2);
                    }
                    agregarParrafo($section, '', false, 10, 'both', 4);
                }
}
            
            // ============================================================
            // INDUCCIÓN Y ENTRENAMIENTO
            // ============================================================
            elseif ($tipo === 'induccion') {
                
                agregarTitulo($section, 'FORMATO DE INDUCCIÓN Y ENTRENAMIENTO DEL PERSONAL', 13);
                agregarParrafo($section, "CÓDIGO: SGC-GH-IE-01 | Versión: 01", false, 9, 'right', 2);
                agregarParrafo($section, $empresa, true, 11, 'center', 8);
                
                agregarParrafo($section, '1. DATOS DEL TRABAJADOR', true, 12, 'left', 6);
                agregarParrafo($section, "Nombre completo: {$nombreCompleto}", false, 11, 'left', 4);
                agregarParrafo($section, "Cargo: {$nombreCargo}", false, 11, 'left', 4);
                agregarParrafo($section, "Área: {$nombreArea}", false, 11, 'left', 4);
                agregarParrafo($section, "Fecha de Ingreso: " . formatearFechaContrato($fechaInicio), false, 11, 'left', 8);
                
                agregarParrafo($section, '2. INDUCCIÓN GENERAL (Aplica a todos)', true, 12, 'left', 6);
                
                $generales = [
                    'Generalidades de la Empresa: Estructura organizacional, Misión, Visión, Valores corporativos.',
                    'Gestión Humana: Contrato de trabajo, afiliaciones a Seguridad Social, Horarios de trabajo, Reglamento Interno de Trabajo.',
                    'Seguridad y Salud en el Trabajo: Políticas y objetivos del SG-SST, Identificación de peligros y riesgos, Uso de EPP, Normas y procedimientos de seguridad, Plan de emergencias y evacuación.',
                    'Responsabilidades: Conocimiento del Manual de Funciones, Responsabilidades individuales frente al SG-SST.',
                ];
                
                foreach ($generales as $i => $g) {
                    agregarParrafo($section, "☐ " . ($i + 1) . ". " . $g, false, 10, 'both', 3);
                }
                
                agregarParrafo($section, '', false, 11, 'both', 8);
                agregarParrafo($section, "3. ENTRENAMIENTO EN EL PUESTO - {$nombreArea}", true, 12, 'left', 6);
                
                if (count($inducciones) > 0) {
                    foreach ($inducciones as $i => $ind) {
                        agregarParrafo($section, "☐ " . ($i + 1) . ". " . $ind['tema'], false, 10, 'both', 3);
                    }
                } else {
                    agregarParrafo($section, "(No se encontraron temas de inducción registrados para esta área.)", false, 10, 'both', 4);
                }
                
                agregarParrafo($section, '', false, 11, 'both', 24);
                agregarParrafo($section, '4. FIRMAS', true, 12, 'left', 6);
                agregarParrafo($section, '', false, 11, 'both', 24);
                
                // Firma Trabajador
                agregarParrafo($section, '_________________________________', false, 11, 'center', 2);
                agregarParrafo($section, $nombreCompleto, true, 10, 'center', 2);
                agregarParrafo($section, 'Trabajador', false, 10, 'center', 2);
                agregarParrafo($section, "C.C. {$cedula}", false, 10, 'center', 24);
                
                // Firma Jefe
                agregarParrafo($section, '_________________________________', false, 11, 'center', 2);
                agregarParrafo($section, 'Jefe Inmediato', true, 10, 'center', 2);
                agregarParrafo($section, '', false, 10, 'center', 24);
                
                // Firma Gestión Humana
                agregarParrafo($section, '_________________________________', false, 11, 'center', 2);
                agregarParrafo($section, 'Gestión Humana', true, 10, 'center', 2);
            }
            
            // ============================================================
            // GUARDAR Y DESCARGAR
            // ============================================================
            $nombreArchivo = '';
            switch ($tipo) {
                case 'contrato':
                    $nombreArchivo = "Contrato_" . preg_replace('/[^a-zA-Z0-9]/', '_', $nombreCompleto) . "_" . date('Ymd') . ".docx";
                    break;
                case 'perfil':
                    $nombreArchivo = "PerfilCargo_" . preg_replace('/[^a-zA-Z0-9]/', '_', $nombreCompleto) . "_" . date('Ymd') . ".docx";
                    break;
                case 'induccion':
                    $nombreArchivo = "Induccion_" . preg_replace('/[^a-zA-Z0-9]/', '_', $nombreCompleto) . "_" . date('Ymd') . ".docx";
                    break;
            }
            
            $carpetaExp = __DIR__ . '/../../expedientes/' . $idContrato;
            if (!is_dir($carpetaExp)) {
                mkdir($carpetaExp, 0755, true);
            }
            
            $rutaArchivo = $carpetaExp . '/' . $nombreArchivo;
            
            $objWriter = IOFactory::createWriter($phpWord, 'Word2007');
            $objWriter->save($rutaArchivo);
            
            // Registrar en la BD
            try {
                $stmtReg = $conexion->prepare("INSERT INTO documentos_generados (id_contrato, tipo_documento, ruta_archivo, generado_por) VALUES (:contrato, :tipo, :ruta, :usuario)");
                $stmtReg->execute([
                    ':contrato' => $idContrato,
                    ':tipo'     => $tipo === 'contrato' ? 'contrato' : ($tipo === 'perfil' ? 'perfil_cargo' : 'induccion'),
                    ':ruta'     => 'expedientes/' . $idContrato . '/' . $nombreArchivo,
                    ':usuario'  => $_SESSION['id_usuario'] ?? null,
                ]);
            } catch (Throwable $e) {
                // Si falla el registro, igual descargamos el archivo
            }
            
            // Limpiar buffer
            if (ob_get_level()) {
                ob_end_clean();
            }
            
            // Descargar archivo
            header('Content-Description: File Transfer');
            header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
            header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
            header('Content-Length: ' . filesize($rutaArchivo));
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            readfile($rutaArchivo);
            exit;
            
        } catch (Throwable $e) {
            die('Error al generar documento: ' . $e->getMessage() . '<br><br>Línea: ' . $e->getLine());
        }