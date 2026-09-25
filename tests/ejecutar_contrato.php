<?php
require_once __DIR__ . '/../config/auth.php';
soloConsola();
// Ayudante de ValidacionContratoTest.php: ejecuta el guardar.php REAL de contratación
// con un POST dado (como si llegara del navegador, sin JavaScript) y devuelve su
// respuesta JSON. Solo se usa con datos que deben ser RECHAZADOS: la validación ocurre
// antes de cualquier escritura. Por seguridad, si la tabla contratos cambia, lo reporta.
//
// Uso: php tests/ejecutar_contrato.php <archivo-json-con-el-post>
//
// Modo '__escritura_de_prueba' => true: para probar casos ACEPTADOS (editar, renovar). La
// conexión se reemplaza por ConexionDePrueba: las transacciones de guardar.php se vuelven
// SAVEPOINT dentro de una transacción externa que SIEMPRE se deshace al final. Devuelve la
// fila del contrato tal como quedó (antes de deshacer) y verifica que la tabla volvió igual.

require __DIR__ . '/../config/conexion.php';

class ConexionDePrueba extends PDO
{
    private int $nivel = 0;

    public function iniciarPrueba(): void { parent::beginTransaction(); }
    public function beginTransaction(): bool { $this->exec('SAVEPOINT sp' . (++$this->nivel)); return true; }
    public function commit(): bool { $this->exec('RELEASE SAVEPOINT sp' . $this->nivel--); return true; }
    public function rollBack(): bool
    {
        if ($this->nivel > 0) {
            $this->exec('ROLLBACK TO SAVEPOINT sp' . $this->nivel--);
            return true;
        }
        return parent::rollBack();
    }
    public function inTransaction(): bool { return $this->nivel > 0; }
    public function deshacerTodo(): void { $this->nivel = 0; parent::rollBack(); }
}

$huella = fn() => md5(json_encode($conexion->query('SELECT * FROM contratos ORDER BY id_contrato')->fetchAll()));
$antes = $huella();

$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = json_decode(file_get_contents($argv[1]), true) + ['formato' => 'json'];

$escritura = !empty($_POST['__escritura_de_prueba']);
unset($_POST['__escritura_de_prueba']);
if ($escritura) {
    $conexion = new ConexionDePrueba($dsn, $username, $password, $opciones);
    $conexion->iniciarPrueba();
}

// Escenario opcional ('__preparar_sql', ej. dejar un trabajador inactivo): se aplica en
// una transacción que se deshace al final. Si guardar.php intentara escribir, fallaría
// al abrir su propia transacción, así que nunca quedan cambios.
$preparar = $_POST['__preparar_sql'] ?? null;
unset($_POST['__preparar_sql']);
if ($preparar) {
    $conexion->beginTransaction();
    $conexion->exec($preparar);
}

ob_start();
register_shutdown_function(function () use ($huella, $antes, $escritura) {
    global $conexion;
    $salida = ob_get_clean();
    $fila = null;
    if ($escritura) {
        $id = (int)($_POST['contrato_id'] ?? 0);
        $st = $conexion->prepare('SELECT * FROM contratos WHERE id_contrato = ?');
        $st->execute([$id]);
        $fila = $st->fetch(PDO::FETCH_ASSOC) ?: null;
        $conexion->deshacerTodo();
    } elseif ($conexion->inTransaction()) {
        $conexion->rollBack();
    }
    $pos = strpos($salida, '{');
    $respuesta = $pos !== false ? json_decode(substr($salida, $pos), true) : null;
    echo "\n@@RESULTADO@@" . json_encode([
        'respuesta' => $respuesta,
        'contratos_sin_cambios' => $huella() === $antes,
        'fila_contrato' => $fila,
    ], JSON_UNESCAPED_UNICODE);
});

chdir(__DIR__ . '/../views/contratacion');
require __DIR__ . '/../views/contratacion/guardar.php';
