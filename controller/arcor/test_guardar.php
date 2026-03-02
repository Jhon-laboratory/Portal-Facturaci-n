<?php
// test_guardar.php - Archivo de prueba simple

require_once '../../conexion/config.php';

echo "<h2>Prueba de guardado</h2>";

try {
    $conn = getDBConnection();
    echo "✅ Conexión a BD exitosa<br>";
    
    // Datos de prueba
    $factura_id = 2;
    $tipo = "Posiciones rack (prueba)";
    $cantidad = 99;
    $usuario = 1;
    
    // Insertar directamente
    $sql = "INSERT INTO FacBol.ocupabilidad_ubicaciones 
            (factura_id, tipo_ubicacion, cantidad, total_ubicaciones, usuario_creacion) 
            VALUES (?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $resultado = $stmt->execute([
        $factura_id,
        $tipo,
        $cantidad,
        $cantidad,
        $usuario
    ]);
    
    if ($resultado) {
        echo "✅ DATOS GUARDADOS CORRECTAMENTE<br>";
        echo "ID insertado: " . $conn->lastInsertId() . "<br>";
    } else {
        echo "❌ Error al guardar<br>";
    }
    
    // Mostrar los datos guardados
    $sql_verificar = "SELECT * FROM FacBol.ocupabilidad_ubicaciones WHERE factura_id = ? ORDER BY id DESC";
    $stmt_verificar = $conn->prepare($sql_verificar);
    $stmt_verificar->execute([$factura_id]);
    $datos = $stmt_verificar->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>Datos en la tabla:</h3>";
    echo "<pre>";
    print_r($datos);
    echo "</pre>";
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "<br>";
}
?>