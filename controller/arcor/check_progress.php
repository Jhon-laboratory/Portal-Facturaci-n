<?php
/**
 * Guarda los datos procesados con progreso en tiempo real
 */

session_start();
require_once '../../conexion/config.php';
require_once '../progress_tracker.php';

header('Content-Type: application/json');

try {
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Sesión no iniciada');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        throw new Exception('No se recibieron datos');
    }

    $tracker = new ProgressTracker($_SESSION['user_id']);
    $tracker->iniciarProceso(count($input['datos']));

    $conn = getDBConnection();
    $conn->beginTransaction();

    // Guardar en tabla temporal primero
    $sql_temp = "INSERT INTO [FacBol].[procesos_temporales] 
                 (session_id, tipo_modulo, datos_json, total_registros) 
                 VALUES (?, ?, ?, ?)";
    
    $stmt_temp = $conn->prepare($sql_temp);
    $stmt_temp->execute([
        session_id(),
        $input['tipo_modulo'],
        json_encode($input['datos']),
        count($input['datos'])
    ]);
    
    $temp_id = $conn->lastInsertId();
    $tracker->actualizarProgreso(10, 'Datos almacenados temporalmente');

    // Procesar factura
    if (!$input['factura_id']) {
        $sql_factura = "INSERT INTO " . TABLA_FACTURAS . " 
                        (cliente_id, cliente_codigo, usuario_id, usuario_nombre, 
                         {$input['tipo_modulo']}_archivo, {$input['tipo_modulo']}_completado, fecha_creacion) 
                        OUTPUT INSERTED.id
                        VALUES (?, ?, ?, ?, ?, 1, GETDATE())";
        
        $stmt_factura = $conn->prepare($sql_factura);
        $stmt_factura->execute([
            $input['cliente_id'],
            $input['cliente_codigo'],
            $_SESSION['user_id'],
            $_SESSION['user_name'],
            $input['archivo_nombre']
        ]);
        
        $factura_id = $stmt_factura->fetchColumn();
        $tracker->actualizarProgreso(20, 'Factura creada');
    } else {
        $factura_id = $input['factura_id'];
        
        // Eliminar registros anteriores si existen
        $tabla_detalle = $input['tipo_modulo'] == 'recepcion' ? TABLA_RECEPCION : TABLA_DESPACHO;
        $sql_delete = "DELETE FROM $tabla_detalle WHERE factura_id = ?";
        $stmt_delete = $conn->prepare($sql_delete);
        $stmt_delete->execute([$factura_id]);
        
        $tracker->actualizarProgreso(20, 'Registros anteriores eliminados');
    }

    // Insertar datos en bloques para mejor rendimiento
    $tabla_detalle = $input['tipo_modulo'] == 'recepcion' ? TABLA_RECEPCION : TABLA_DESPACHO;
    $insert_count = 0;
    $total = count($input['datos']);
    
    // Preparar inserción por lotes de 100 registros
    $batch_size = 100;
    for ($i = 0; $i < $total; $i += $batch_size) {
        $batch = array_slice($input['datos'], $i, $batch_size);
        
        foreach ($batch as $index => $row) {
            // Lógica de inserción específica según el módulo
            if ($input['tipo_modulo'] == 'recepcion') {
                $sql_insert = "INSERT INTO $tabla_detalle 
                               (factura_id, receiptkey, sku, storerkey, unidades, cajas, pallets, 
                                status, fecha_recepcion, external_receiptkey, type) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                $stmt = $conn->prepare($sql_insert);
                $stmt->execute([
                    $factura_id,
                    $row['RECEIPTKEY'] ?? '',
                    $row['SKU'] ?? '',
                    $row['STORERKEY'] ?? '',
                    intval($row['UNIDADES'] ?? 0),
                    intval($row['CAJAS'] ?? 0),
                    intval($row['PALLETS'] ?? 0),
                    $row['STATUS'] ?? '',
                    $row['DATERECEIVED'] ?? null,
                    $row['EXTERNRECEIPTKEY'] ?? '',
                    $row['TYPE'] ?? ''
                ]);
            } else {
                $sql_insert = "INSERT INTO $tabla_detalle 
                               (factura_id, orderkey, sku, storerkey, unidades, cajas, pallets, 
                                status, fecha_despacho, externorderkey, type) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                $stmt = $conn->prepare($sql_insert);
                $stmt->execute([
                    $factura_id,
                    $row['ORDERKEY'] ?? '',
                    $row['SKU'] ?? '',
                    $row['STORERKEY'] ?? '',
                    intval($row['UNIDADES'] ?? 0),
                    intval($row['CAJAS'] ?? 0),
                    intval($row['PALLETS'] ?? 0),
                    $row['STATUS'] ?? '',
                    $row['ADDDATE'] ?? null,
                    $row['EXTERNORDERKEY'] ?? '',
                    $row['TYPE'] ?? ''
                ]);
            }
            
            $insert_count++;
        }
        
        // Actualizar progreso
        $porcentaje = 20 + round(($insert_count / $total) * 70);
        $tracker->actualizarProgreso($porcentaje, "Procesando registros... ($insert_count de $total)");
    }

    // Marcar proceso como completado
    $sql_update_temp = "UPDATE [FacBol].[procesos_temporales] 
                        SET estado = 'completado' WHERE id = ?";
    $stmt_update = $conn->prepare($sql_update_temp);
    $stmt_update->execute([$temp_id]);

    $conn->commit();
    $tracker->finalizarProceso("✅ Proceso completado: $insert_count registros");

    echo json_encode([
        'success' => true,
        'factura_id' => $factura_id,
        'registros_guardados' => $insert_count,
        'mensaje' => "✅ Se guardaron $insert_count registros correctamente"
    ]);

} catch (Exception $e) {
    if (isset($conn)) $conn->rollBack();
    $tracker->registrarError($e->getMessage());
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>