<?php
require_once __DIR__ . '/../../config/auth.php';
requerirAcceso();
// verificacion_cambios.php
require_once '../../config/conexion.php';

echo "<h2>🔍 Verificación de Cambios en Base de Datos</h2>";
echo "<hr>";

// 1. Verificar tabla familiares_trabajador
echo "<h3>1. Tabla: familiares_trabajador</h3>";
try {
    $stmt = $conexion->query("SHOW TABLES LIKE 'familiares_trabajador'");
    if ($stmt->rowCount() > 0) {
        echo "✅ <strong>La tabla familiares_trabajador EXISTE</strong><br><br>";
        
        // Mostrar estructura
        echo "<strong>Estructura:</strong><br>";
        $stmt = $conexion->query("DESCRIBE familiares_trabajador");
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "<tr>";
            echo "<td>{$row['Field']}</td>";
            echo "<td>{$row['Type']}</td>";
            echo "<td>{$row['Null']}</td>";
            echo "<td>{$row['Key']}</td>";
            echo "<td>{$row['Default']}</td>";
            echo "</tr>";
        }
        echo "</table><br>";
        
        // Contar registros
        $stmt = $conexion->query("SELECT COUNT(*) as total FROM familiares_trabajador");
        $count = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "Registros: <strong>{$count['total']}</strong><br>";
    } else {
        echo "❌ <strong>La tabla familiares_trabajador NO existe</strong><br>";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}

echo "<hr>";

// 2. Verificar columnas en trabajadores
echo "<h3>2. Tabla: trabajadores (Nuevas columnas)</h3>";
$columnasEsperadas = [
    'tiene_hijos',
    'numero_hijos',
    'tiene_personas_cargo',
    'observaciones_familiares'
];

try {
    $stmt = $conexion->query("DESCRIBE trabajadores");
    $columnasExistentes = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $columnasExistentes[] = $row['Field'];
    }
    
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr><th>Columna</th><th>Estado</th><th>Tipo</th></tr>";
    
    foreach ($columnasEsperadas as $col) {
        if (in_array($col, $columnasExistentes)) {
            // Obtener tipo de columna
            $stmt = $conexion->prepare("SHOW COLUMNS FROM trabajadores WHERE Field = ?");
            $stmt->execute([$col]);
            $info = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo "<tr>";
            echo "<td><strong>$col</strong></td>";
            echo "<td style='color: green;'>✅ EXISTE</td>";
            echo "<td>{$info['Type']}</td>";
            echo "</tr>";
        } else {
            echo "<tr>";
            echo "<td><strong>$col</strong></td>";
            echo "<td style='color: red;'>❌ NO EXISTE</td>";
            echo "<td>-</td>";
            echo "</tr>";
        }
    }
    echo "</table><br>";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}

echo "<hr>";

// 3. Mostrar todas las columnas de trabajadores
echo "<h3>3. Todas las columnas de trabajadores:</h3>";
try {
    $stmt = $conexion->query("DESCRIBE trabajadores");
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr><th>#</th><th>Field</th><th>Type</th><th>Null</th><th>Key</th></tr>";
    $i = 1;
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $highlight = in_array($row['Field'], $columnasEsperadas) ? "style='background-color: #d4edda;'" : "";
        echo "<tr $highlight>";
        echo "<td>$i</td>";
        echo "<td>{$row['Field']}</td>";
        echo "<td>{$row['Type']}</td>";
        echo "<td>{$row['Null']}</td>";
        echo "<td>{$row['Key']}</td>";
        echo "</tr>";
        $i++;
    }
    echo "</table>";
    echo "<br><em>Las columnas resaltadas en verde son las nuevas agregadas.</em>";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}

echo "<hr>";
echo "<h3>✅ Verificación completada</h3>";
?>