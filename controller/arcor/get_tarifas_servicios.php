<?php
/**
 * Obtiene la lista de tarifas de servicios con múltiples tarifas
 */

session_start();
require_once '../../conexion/config.php';

header('Content-Type: application/json');

try {
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Sesión no iniciada');
    }
    
    $conn = getDBConnection();
    
    $sql = "SELECT id, servicio, tarifa1, tarifa2, tarifa3, tarifa4, tarifa5, unidad_medida 
            FROM [FacBol].[tarifas_servicios] 
            WHERE activo = 1 
            ORDER BY orden";
    
    $stmt = $conn->query($sql);
    $tarifas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $tarifas
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>