<?php
// test_resumen.php - Para depurar el problema
session_start();
$_SESSION['user_id'] = 1; // Simular sesión
require_once '../../conexion/config.php';

$factura_id = isset($_GET['id']) ? intval($_GET['id']) : 3;

echo "<h2>🔍 DEPURACIÓN DE RESUMEN - Factura ID: $factura_id</h2>";

try {
    $conn = getDBConnection();
    echo "✅ Conexión a BD exitosa<br><br>";
    
    // 1. Verificar tablas
    echo "<h3>1. Verificando tablas:</h3>";
    $tablas = [
        'TABLA_FACTURAS' => TABLA_FACTURAS,
        'TABLA_RECEPCION' => TABLA_RECEPCION,
        'TABLA_DESPACHO' => TABLA_DESPACHO,
        'TABLA_OCUPABILIDAD' => TABLA_OCUPABILIDAD,
        'TABLA_OTROS_SERVICIOS' => TABLA_OTROS_SERVICIOS,
        'TABLA_TARIFAS' => TABLA_TARIFAS,
        'TABLA_CONFIG' => TABLA_CONFIG
    ];
    
    foreach ($tablas as $nombre => $tabla) {
        try {
            $result = $conn->query("SELECT TOP 1 * FROM $tabla");
            echo "✅ $nombre: $tabla - OK<br>";
        } catch (Exception $e) {
            echo "❌ $nombre: $tabla - ERROR: " . $e->getMessage() . "<br>";
        }
    }
    
    // 2. Verificar factura
    echo "<h3>2. Verificando factura $factura_id:</h3>";
    $stmt = $conn->prepare("SELECT * FROM " . TABLA_FACTURAS . " WHERE id = ?");
    $stmt->execute([$factura_id]);
    $factura = $stmt->fetch();
    
    if ($factura) {
        echo "✅ Factura encontrada: " . json_encode($factura) . "<br>";
    } else {
        echo "❌ Factura NO encontrada<br>";
    }
    
    // 3. Verificar datos de recepción
    echo "<h3>3. Datos de recepción:</h3>";
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM " . TABLA_RECEPCION . " WHERE factura_id = ?");
    $stmt->execute([$factura_id]);
    $count = $stmt->fetch();
    echo "Registros en recepción: " . $count['total'] . "<br>";
    
    // 4. Verificar datos de despacho
    echo "<h3>4. Datos de despacho:</h3>";
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM " . TABLA_DESPACHO . " WHERE factura_id = ?");
    $stmt->execute([$factura_id]);
    $count = $stmt->fetch();
    echo "Registros en despacho: " . $count['total'] . "<br>";
    
    // 5. Verificar datos de ocupabilidad
    echo "<h3>5. Datos de ocupabilidad:</h3>";
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM " . TABLA_OCUPABILIDAD . " WHERE factura_id = ?");
    $stmt->execute([$factura_id]);
    $count = $stmt->fetch();
    echo "Registros en ocupabilidad: " . $count['total'] . "<br>";
    
    // 6. Verificar otros servicios
    echo "<h3>6. Otros servicios:</h3>";
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM " . TABLA_OTROS_SERVICIOS . " WHERE factura_id = ?");
    $stmt->execute([$factura_id]);
    $count = $stmt->fetch();
    echo "Registros en otros servicios: " . $count['total'] . "<br>";
    
    // 7. Verificar tarifas del cliente
    if ($factura) {
        echo "<h3>7. Tarifas para cliente: " . $factura['cliente_codigo'] . "</h3>";
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM " . TABLA_TARIFAS . " WHERE cliente_codigo = ?");
        $stmt->execute([$factura['cliente_codigo']]);
        $count = $stmt->fetch();
        echo "Tarifas encontradas: " . $count['total'] . "<br>";
    }
    
    // 8. Verificar configuración
    echo "<h3>8. Configuración global:</h3>";
    $stmt = $conn->query("SELECT * FROM " . TABLA_CONFIG);
    $config = $stmt->fetchAll();
    echo "<pre>" . print_r($config, true) . "</pre>";
    
} catch (Exception $e) {
    echo "❌ ERROR GENERAL: " . $e->getMessage();
}
?>