<?php
/**
 * Obtiene las ubicaciones de ocupabilidad de una factura
 * Tabla: FacBol.ocupabilidad_ubicaciones
 */

session_start();
require_once '../../conexion/config.php';

header('Content-Type: application/json');

try {
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Sesión no iniciada');
    }

    $factura_id = isset($_GET['factura_id']) ? intval($_GET['factura_id']) : 0;
    
    if (!$factura_id) {
        throw new Exception('ID de factura no válido');
    }

    $conn = getDBConnection();
    
    // Tabla: FacBol.ocupabilidad_ubicaciones (esquema FacBol)
    $sql = "SELECT id, factura_id, tipo_ubicacion, cantidad 
            FROM FacBol.ocupabilidad_ubicaciones 
            WHERE factura_id = :factura_id 
            ORDER BY id";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([':factura_id' => $factura_id]);
    $ubicaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formatear datos
    $data = [];
    foreach ($ubicaciones as $u) {
        $data[] = [
            'id' => $u['id'],
            'tipo' => $u['tipo_ubicacion'],
            'cantidad' => intval($u['cantidad'])
        ];
    }
    
    $total = array_sum(array_column($data, 'cantidad'));
    
    echo json_encode([
        'success' => true,
        'data' => $data,
        'total' => $total
    ]);

} catch (Exception $e) {
    error_log("❌ Error en get_ocupabilidad.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>