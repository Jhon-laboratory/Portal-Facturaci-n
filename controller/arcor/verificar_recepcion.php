<?php
/**
 * Verifica si una factura ya tiene datos de recepción
 */

session_start();
require_once '../../conexion/config.php';

header('Content-Type: application/json');

try {
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Sesión no iniciada');
    }

    $factura_id = $_GET['factura_id'] ?? 0;
    
    if (!$factura_id) {
        echo json_encode(['existe' => false]);
        exit;
    }

    $conn = getDBConnection();
    
    $sql = "SELECT COUNT(*) as total FROM " . TABLA_RECEPCION . " WHERE factura_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$factura_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'existe' => ($row['total'] > 0),
        'total' => $row['total']
    ]);

} catch (Exception $e) {
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}
?>