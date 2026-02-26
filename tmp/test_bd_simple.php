<?php
require_once '/var/www/html/Portal-Facturaci-n/conexion/config.php';

echo "=== TEST CONEXIÓN BD ===\n";

try {
    $conn = getDBConnection();
    echo "✅ Conexión exitosa\n";
    
    // Probar inserción mínima
    $sql = "INSERT INTO [FacBol].[facturas_despacho_detalle] 
            (factura_id, receiptkey, sku) 
            VALUES (1, 'TEST001', 'SKU001')";
    
    $conn->exec($sql);
    echo "✅ Inserción exitosa\n";
    
    // Eliminar el registro de prueba
    $conn->exec("DELETE FROM [FacBol].[facturas_despacho_detalle] WHERE receiptkey = 'TEST001'");
    echo "✅ Limpieza exitosa\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>