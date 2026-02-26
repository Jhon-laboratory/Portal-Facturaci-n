<?php
/**
 * TEST: Insertar un registro directamente en facturas_despacho_detalle
 */

// Mostrar errores
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'conexion/config.php';

echo "<h2>🔍 TEST DE INSERCIÓN EN DESPACHO</h2>";

try {
    // 1. Verificar conexión
    echo "<h3>1. Verificando conexión...</h3>";
    $conn = getDBConnection();
    echo "✅ Conexión OK<br>";

    // 2. Verificar constantes
    echo "<h3>2. Verificando constantes...</h3>";
    echo "TABLA_FACTURAS: " . (defined('TABLA_FACTURAS') ? TABLA_FACTURAS : 'NO DEFINIDA') . "<br>";
    echo "TABLA_DESPACHO: " . (defined('TABLA_DESPACHO') ? TABLA_DESPACHO : 'NO DEFINIDA') . "<br>";
    
    if (!defined('TABLA_DESPACHO')) {
        throw new Exception("La constante TABLA_DESPACHO no está definida en config.php");
    }

    // 3. Verificar si la tabla existe
    echo "<h3>3. Verificando tabla...</h3>";
    $sql = "SELECT TOP 1 * FROM " . TABLA_DESPACHO;
    $stmt = $conn->query($sql);
    echo "✅ Tabla accesible<br>";

    // 4. Obtener o crear una factura de prueba
    echo "<h3>4. Buscando/Creando factura de prueba...</h3>";
    
    // Buscar una factura existente para ARCOR
    $sql = "SELECT TOP 1 id FROM " . TABLA_FACTURAS . " WHERE cliente_codigo = 'ARCOR' ORDER BY id DESC";
    $stmt = $conn->query($sql);
    $factura = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($factura) {
        $factura_id = $factura['id'];
        echo "✅ Usando factura existente ID: $factura_id<br>";
    } else {
        // Crear una factura de prueba
        echo "Creando factura de prueba...<br>";
        $sql = "INSERT INTO " . TABLA_FACTURAS . " 
                (cliente_id, cliente_codigo, usuario_id, usuario_nombre, despacho_archivo, despacho_completado, fecha_creacion) 
                VALUES (1, 'ARCOR', 1, 'TEST', 'test.xlsx', 0, GETDATE())";
        
        $conn->exec($sql);
        $factura_id = $conn->lastInsertId();
        echo "✅ Factura de prueba creada ID: $factura_id<br>";
    }

    // 5. Insertar registro de prueba en despacho
    echo "<h3>5. Insertando registro de prueba...</h3>";
    
    $sql_insert = "INSERT INTO " . TABLA_DESPACHO . " 
                   (factura_id, receiptkey, sku, storerkey, unidades, cajas, pallets, status, fecha_despacho, oc, type) 
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql_insert);
    $params = [
        $factura_id,
        'TEST001',              // receiptkey
        'SKU001',               // sku
        'ARCOR',                // storerkey
        10,                     // unidades
        5,                      // cajas
        1,                      // pallets
        '55',                   // status
        '2026-02-26',           // fecha_despacho
        'EXT001',               // oc
        '1'                     // type
    ];
    
    $stmt->execute($params);
    $insert_id = $conn->lastInsertId();
    echo "✅ Registro insertado con ID: $insert_id<br>";

    // 6. Verificar que se insertó
    echo "<h3>6. Verificando inserción...</h3>";
    $sql = "SELECT * FROM " . TABLA_DESPACHO . " WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$insert_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($row) {
        echo "✅ Registro encontrado:<br>";
        echo "<pre>";
        print_r($row);
        echo "</pre>";
    } else {
        echo "❌ No se pudo verificar el registro<br>";
    }

    // 7. Opción para limpiar
    echo "<h3>7. ¿Eliminar registro de prueba?</h3>";
    echo "<form method='POST'>";
    echo "<input type='hidden' name='eliminar_id' value='$insert_id'>";
    echo "<button type='submit' name='accion' value='eliminar' style='background:#dc3545; color:white; padding:10px; border:none; border-radius:5px; cursor:pointer;'>🗑️ Eliminar registro de prueba</button>";
    echo "</form>";

    // 8. Procesar eliminación si se solicita
    if ($_POST['accion'] == 'eliminar' && isset($_POST['eliminar_id'])) {
        $eliminar_id = $_POST['eliminar_id'];
        $sql_delete = "DELETE FROM " . TABLA_DESPACHO . " WHERE id = ?";
        $stmt = $conn->prepare($sql_delete);
        $stmt->execute([$eliminar_id]);
        echo "<p style='color:green; font-weight:bold;'>✅ Registro $eliminar_id eliminado</p>";
    }

    // 9. Mostrar últimos registros
    echo "<h3>📋 Últimos 5 registros en despacho:</h3>";
    $sql = "SELECT TOP 5 * FROM " . TABLA_DESPACHO . " ORDER BY id DESC";
    $stmt = $conn->query($sql);
    $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($registros)) {
        echo "<p>No hay registros</p>";
    } else {
        echo "<table border='1' cellpadding='5' style='border-collapse:collapse;'>";
        echo "<tr>";
        foreach (array_keys($registros[0]) as $col) {
            echo "<th>" . htmlspecialchars($col) . "</th>";
        }
        echo "</tr>";
        
        foreach ($registros as $row) {
            echo "<tr>";
            foreach ($row as $val) {
                echo "<td>" . htmlspecialchars($val ?? 'NULL') . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    }

} catch (PDOException $e) {
    echo "<p style='color:red; font-weight:bold;'>❌ Error PDO: " . $e->getMessage() . "</p>";
    echo "<p>Archivo: " . $e->getFile() . " línea: " . $e->getLine() . "</p>";
} catch (Exception $e) {
    echo "<p style='color:red; font-weight:bold;'>❌ Error: " . $e->getMessage() . "</p>";
    echo "<p>Archivo: " . $e->getFile() . " línea: " . $e->getLine() . "</p>";
}
?>