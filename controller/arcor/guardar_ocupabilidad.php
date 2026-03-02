<?php
/**
 * Guarda las ubicaciones de ocupabilidad en la base de datos
 * VERSIÓN CORREGIDA - IGUAL A otros_servicios
 */

session_start();
require_once '../../conexion/config.php';

header('Content-Type: application/json');

try {
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Sesión no iniciada');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['ubicaciones'])) {
        throw new Exception('Datos incompletos');
    }

    $ubicaciones = $input['ubicaciones'];
    
    if (empty($ubicaciones)) {
        throw new Exception('No hay ubicaciones para guardar');
    }

    $conn = getDBConnection();
    $conn->beginTransaction();

    // ===== VERIFICAR FACTURA =====
    $factura_id = isset($input['factura_id']) ? intval($input['factura_id']) : 0;
    
    if (!$factura_id) {
        throw new Exception('ID de factura no válido');
    }

    // Eliminar ubicaciones anteriores
    $sql_delete = "DELETE FROM FacBol.ocupabilidad_ubicaciones WHERE factura_id = ?";
    $stmt_delete = $conn->prepare($sql_delete);
    $stmt_delete->execute([$factura_id]);

    // Insertar nuevas ubicaciones
    $sql_insert = "INSERT INTO FacBol.ocupabilidad_ubicaciones 
                   (factura_id, tipo_ubicacion, cantidad, total_ubicaciones, usuario_creacion) 
                   VALUES (?, ?, ?, ?, ?)";
    
    $stmt_insert = $conn->prepare($sql_insert);
    $insertados = 0;

    foreach ($ubicaciones as $u) {
        $tipo = trim($u['tipo'] ?? '');
        $cantidad = intval($u['cantidad'] ?? 0);
        
        if (empty($tipo) || $cantidad <= 0) continue;
        
        $stmt_insert->execute([
            $factura_id,
            $tipo,
            $cantidad,
            $cantidad,
            $_SESSION['user_id']
        ]);
        
        $insertados++;
    }

    if ($insertados == 0) {
        throw new Exception('No se pudo guardar ninguna ubicación');
    }

    // Actualizar factura
    $sql_update = "UPDATE " . TABLA_FACTURAS . " 
                   SET almacen_completado = 1
                   WHERE id = ?";
    $stmt_update = $conn->prepare($sql_update);
    $stmt_update->execute([$factura_id]);

    $conn->commit();

    echo json_encode([
        'success' => true,
        'factura_id' => $factura_id,
        'insertados' => $insertados,
        'mensaje' => "Se guardaron $insertados ubicaciones correctamente"
    ]);

} catch (Exception $e) {
    if (isset($conn)) $conn->rollBack();
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>