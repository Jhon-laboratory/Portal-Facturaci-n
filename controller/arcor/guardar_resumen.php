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
        $stmt_insert->execute([
            $factura_id,
            $item['servicio'],
            $item['sub_servicio'],
            $item['udm'],
            $item['cantidad'],
            $item['tarifa_usd'],
            $item['total_usd'],
            $item['total_usd_con_iva'],
            $item['total_bs'],
            $item['total_bs_con_iva'],
            $item['tipo_servicio'],
            $item['editable'] ? 1 : 0,
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