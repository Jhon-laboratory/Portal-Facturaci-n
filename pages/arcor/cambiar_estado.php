<?php
session_start();
require_once '../../conexion/config.php';

header('Content-Type: application/json');

try {
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Sesión no iniciada');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['factura_id']) || !isset($input['estado'])) {
        throw new Exception('Datos incompletos');
    }

    $factura_id = intval($input['factura_id']);
    $nuevo_estado = $input['estado'];
    $observacion = $input['observacion'] ?? '';
    $tipo_observacion = $input['tipo_observacion'] ?? 'APROBADOR';

    // Estados válidos
    $estados_validos = ['REGISTRADO', 'VERIFICADO', 'APROBADO', 'OBSERVADO', 'FACTURADO', 'PAGADO'];
    
    if (!in_array($nuevo_estado, $estados_validos)) {
        throw new Exception('Estado no válido');
    }

    $conn = getDBConnection();
    $conn->beginTransaction();

    // Obtener información actual
    $sql_factura = "SELECT estado, almacen_archivo FROM " . TABLA_FACTURAS . " WHERE id = ?";
    $stmt = $conn->prepare($sql_factura);
    $stmt->execute([$factura_id]);
    $factura = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$factura) {
        throw new Exception('Factura no encontrada');
    }

    // Preparar la actualización
    if ($nuevo_estado == 'APROBADO') {
        // Si se aprueba, guardar el nombre del aprobador
        $sql_update = "UPDATE " . TABLA_FACTURAS . " 
                       SET estado = ?, almacen_archivo = ?
                       WHERE id = ?";
        $stmt_update = $conn->prepare($sql_update);
        $stmt_update->execute([$nuevo_estado, $_SESSION['user_name'], $factura_id]);
    } else {
        $sql_update = "UPDATE " . TABLA_FACTURAS . " 
                       SET estado = ?
                       WHERE id = ?";
        $stmt_update = $conn->prepare($sql_update);
        $stmt_update->execute([$nuevo_estado, $factura_id]);
    }

    // Si hay observación y el estado es OBSERVADO, guardar en la tabla de observaciones
    if ($nuevo_estado == 'OBSERVADO' && !empty($observacion)) {
        $sql_obs = "INSERT INTO [FacBol].[facturas_observaciones] 
                    (factura_id, usuario_id, tipo_observacion, observacion) 
                    VALUES (?, ?, ?, ?)";
        $stmt_obs = $conn->prepare($sql_obs);
        $stmt_obs->execute([$factura_id, $_SESSION['user_id'], $tipo_observacion, $observacion]);
    }

    $conn->commit();

    echo json_encode([
        'success' => true,
        'factura_id' => $factura_id,
        'estado' => $nuevo_estado,
        'mensaje' => "Factura actualizada a estado: $nuevo_estado"
    ]);

} catch (Exception $e) {
    if (isset($conn)) $conn->rollBack();
    error_log("Error en cambiar_estado: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>