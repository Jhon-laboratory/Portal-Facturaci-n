<?php
/**
 * Guarda los servicios adicionales en la base de datos
 * VERSIÓN MODIFICADA - Crea factura si no existe
 */

session_start();
require_once '../../conexion/config.php';

header('Content-Type: application/json');

try {
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Sesión no iniciada');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['servicios'])) {
        throw new Exception('Datos incompletos');
    }

    $servicios = $input['servicios'];
    
    if (empty($servicios)) {
        throw new Exception('No hay servicios para guardar');
    }

    $conn = getDBConnection();
    $conn->beginTransaction();

    // ===== VERIFICAR/CREAR FACTURA =====
    $factura_id = isset($input['factura_id']) ? intval($input['factura_id']) : 0;
    
    if (!$factura_id) {
        // Crear nueva factura
        $sql_factura = "INSERT INTO " . TABLA_FACTURAS . " 
                        (cliente_id, cliente_codigo, usuario_id, usuario_nombre, 
                         paquete_archivo, paquete_completado, fecha_creacion) 
                        OUTPUT INSERTED.id
                        VALUES (?, ?, ?, ?, ?, 1, GETDATE())";
        
        $stmt_factura = $conn->prepare($sql_factura);
        $stmt_factura->execute([
            $input['cliente_id'] ?? 0,
            $input['cliente_codigo'] ?? '',
            $_SESSION['user_id'],
            $_SESSION['user_name'] ?? 'Usuario',
            $input['archivo_nombre'] ?? 'servicios_manual.xlsx'
        ]);
        
        $factura_id = $stmt_factura->fetchColumn();
        error_log("✅ NUEVA factura otros servicios: ID $factura_id");
    } else {
        // Factura existente - eliminar servicios anteriores
        $sql_delete = "DELETE FROM [FacBol].[facturas_otros_servicios] WHERE factura_id = ?";
        $stmt_delete = $conn->prepare($sql_delete);
        $stmt_delete->execute([$factura_id]);
        error_log("✅ Registros anteriores eliminados para factura $factura_id");
    }

    // ===== INSERTAR NUEVOS SERVICIOS =====
    $sql_insert = "INSERT INTO [FacBol].[facturas_otros_servicios] 
                   (factura_id, servicio_id, servicio_nombre, tarifa, cantidad, total, es_personalizado, usuario_creacion) 
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt_insert = $conn->prepare($sql_insert);
    $insertados = 0;

    foreach ($servicios as $s) {
        // Solo guardar si cantidad > 0
        if ($s['cantidad'] <= 0) continue;
        
        $total = $s['tarifa'] * $s['cantidad'];
        $servicio_id = isset($s['servicio_id']) ? intval($s['servicio_id']) : null;
        $es_personalizado = isset($s['personalizado']) && $s['personalizado'] ? 1 : 0;
        
        $stmt_insert->execute([
            $factura_id,
            $servicio_id,
            $s['servicio'],
            $s['tarifa'],
            $s['cantidad'],
            $total,
            $es_personalizado,
            $_SESSION['user_id']
        ]);
        
        $insertados++;
    }

    // ===== ACTUALIZAR CABECERA DE FACTURA =====
    $sql_update = "UPDATE " . TABLA_FACTURAS . " 
                   SET paquete_completado = 1,
                       paquete_archivo = ?
                   WHERE id = ?";
    $stmt_update = $conn->prepare($sql_update);
    $stmt_update->execute([$input['archivo_nombre'] ?? 'servicios_manual.xlsx', $factura_id]);

    $conn->commit();

    echo json_encode([
        'success' => true,
        'factura_id' => $factura_id,
        'insertados' => $insertados,
        'mensaje' => "Se guardaron $insertados servicios correctamente"
    ]);

} catch (Exception $e) {
    if (isset($conn)) $conn->rollBack();
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>