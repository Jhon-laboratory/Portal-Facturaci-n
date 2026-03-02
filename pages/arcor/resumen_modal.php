<?php
session_start();
require_once '../../conexion/config.php';

header('Content-Type: application/json');

try {
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Sesión no iniciada');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['factura_id']) || !isset($input['datos'])) {
        throw new Exception('Datos incompletos');
    }

    $factura_id = intval($input['factura_id']);
    $datos = $input['datos'];

    $conn = getDBConnection();
    
    // Obtener configuración para cálculos
    $sql_config = "SELECT concepto, valor FROM FacBol.configuracion_facturacion";
    $stmt = $conn->query($sql_config);
    $config = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $config[$row['concepto']] = $row['valor'];
    }
    
    $factor_sin_iva = $config['factor_sin_iva'] ?? 0.84;
    $tipo_cambio = $config['factor_usd_a_bs'] ?? 6.96;
    
    $conn->beginTransaction();

    // Eliminar resumen anterior
    $sql_delete = "DELETE FROM FacBol.facturas_resumen WHERE factura_id = ?";
    $stmt_delete = $conn->prepare($sql_delete);
    $stmt_delete->execute([$factura_id]);

    // Insertar nuevo resumen
    $sql_insert = "INSERT INTO FacBol.facturas_resumen 
                   (factura_id, servicio, sub_servicio, udm, cantidad, tarifa_usd, 
                    total_usd, total_usd_con_iva, total_bs, total_bs_con_iva, 
                    tipo_servicio, editable, usuario_creacion) 
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt_insert = $conn->prepare($sql_insert);
    $insertados = 0;

    foreach ($datos as $item) {
        if ($item['monto_fijo'] > 0) {
            // Para servicios con monto fijo (Otros servicios)
            $total_usd_iva = $item['monto_fijo'];
            $total_usd = $total_usd_iva * $factor_sin_iva;
            $total_bs_iva = $total_usd_iva * $tipo_cambio;
            $total_bs = $total_bs_iva * $factor_sin_iva;
        } else {
            // Para servicios normales
            $total_usd_iva = $item['cantidad'] * $item['tarifa_usd'];
            $total_usd = $total_usd_iva * $factor_sin_iva;
            $total_bs_iva = $total_usd_iva * $tipo_cambio;
            $total_bs = $total_bs_iva * $factor_sin_iva;
        }
        
        $stmt_insert->execute([
            $factura_id,
            $item['servicio'],
            $item['sub_servicio'],
            $item['udm'],
            $item['cantidad'],
            $item['tarifa_usd'],
            $total_usd,
            $total_usd_iva,
            $total_bs,
            $total_bs_iva,
            $item['servicio'],
            0, // editable
            $_SESSION['user_id']
        ]);
        $insertados++;
    }

    $conn->commit();

    echo json_encode([
        'success' => true,
        'insertados' => $insertados,
        'mensaje' => "Resumen guardado correctamente"
    ]);

} catch (Exception $e) {
    if (isset($conn)) $conn->rollBack();
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>