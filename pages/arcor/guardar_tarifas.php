<?php
session_start();
require_once '../../conexion/config.php';

header('Content-Type: application/json');

try {
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Sesión no iniciada');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['cliente']) || !isset($input['tarifas'])) {
        throw new Exception('Datos incompletos');
    }

    $cliente_codigo = $input['cliente'];
    $tarifas = $input['tarifas'];
    
    if (empty($tarifas)) {
        throw new Exception('No hay tarifas para guardar');
    }

    $conn = getDBConnection();
    $conn->beginTransaction();

    // CORREGIDO: Eliminadas las columnas que no existen en la tabla
    $sql_update = "UPDATE [FacBol].[maestro_tarifas] 
                   SET tarifa_usd = ?
                   WHERE id = ? AND cliente_codigo = ?";
    
    $stmt = $conn->prepare($sql_update);
    $actualizados = 0;

    foreach ($tarifas as $tarifa) {
        if (!isset($tarifa['id']) || !isset($tarifa['tarifa_usd'])) {
            continue;
        }
        
        $stmt->execute([
            floatval($tarifa['tarifa_usd']),
            $tarifa['id'],
            $cliente_codigo
        ]);
        
        if ($stmt->rowCount() > 0) {
            $actualizados++;
        }
    }

    $conn->commit();

    echo json_encode([
        'success' => true,
        'actualizados' => $actualizados,
        'mensaje' => "Se actualizaron $actualizados tarifas correctamente"
    ]);

} catch (Exception $e) {
    if (isset($conn)) $conn->rollBack();
    error_log("Error en guardar_tarifas: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>