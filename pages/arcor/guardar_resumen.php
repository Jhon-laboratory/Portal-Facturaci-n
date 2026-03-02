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
    $accion = $input['accion'] ?? 'verificar'; // 'registrar' o 'verificar'
    
    if (empty($datos)) {
        throw new Exception('No hay datos para guardar');
    }

    $conn = getDBConnection();
    $conn->beginTransaction();

    // Obtener estado actual de la factura
    $sql_estado = "SELECT estado FROM " . TABLA_FACTURAS . " WHERE id = ?";
    $stmt_estado = $conn->prepare($sql_estado);
    $stmt_estado->execute([$factura_id]);
    $estado_actual = $stmt_estado->fetchColumn();

    // Eliminar resumen anterior
    $sql_delete = "DELETE FROM DPL.FacBol.facturas_resumen WHERE factura_id = ?";
    $stmt_delete = $conn->prepare($sql_delete);
    $stmt_delete->execute([$factura_id]);

    // Insertar nuevos registros
    $sql_insert = "INSERT INTO DPL.FacBol.facturas_resumen 
                   (factura_id, servicio, sub_servicio, udm, cantidad, tarifa_usd, 
                    total_usd, total_usd_con_iva, total_bs, total_bs_con_iva, 
                    tipo_servicio, editable, usuario_creacion) 
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt_insert = $conn->prepare($sql_insert);
    $insertados = 0;

    foreach ($datos as $row) {
        // Validar que los campos requeridos existan
        if (!isset($row['servicio']) || !isset($row['sub_servicio'])) {
            continue;
        }
        
        $stmt_insert->execute([
            $factura_id,
            $row['servicio'],
            $row['sub_servicio'],
            $row['udm'] ?? $row['sub_servicio'],
            $row['cantidad'] ?? 0,
            $row['tarifa_usd'] ?? 0,
            $row['total_usd'] ?? 0,
            $row['total_usd_con_iva'] ?? 0,
            $row['total_bs'] ?? 0,
            $row['total_bs_con_iva'] ?? 0,
            $row['tipo_servicio'] ?? 'Otros',
            1, // editable
            $_SESSION['user_id']
        ]);
        $insertados++;
    }

    // Determinar nuevo estado según la acción
    $nuevo_estado = $estado_actual;
    $aprobador = null;
    
    if ($accion == 'registrar') {
        // Si es la primera vez que se guarda el resumen
        $nuevo_estado = 'REGISTRADO';
    } elseif ($accion == 'verificar') {
        $nuevo_estado = 'VERIFICADO';
    } elseif ($accion == 'aprobar') {
        $nuevo_estado = 'APROBADO';
        $aprobador = $_SESSION['user_name'] ?? 'Usuario';
    }

    // Actualizar estado de la factura
    if ($aprobador) {
        // Guardar nombre del aprobador en almacen_archivo
        $sql_update = "UPDATE " . TABLA_FACTURAS . " 
                       SET estado = ?, almacen_archivo = ?
                       WHERE id = ?";
        $stmt_update = $conn->prepare($sql_update);
        $stmt_update->execute([$nuevo_estado, $aprobador, $factura_id]);
    } else {
        $sql_update = "UPDATE " . TABLA_FACTURAS . " 
                       SET estado = ?
                       WHERE id = ?";
        $stmt_update = $conn->prepare($sql_update);
        $stmt_update->execute([$nuevo_estado, $factura_id]);
    }

    $conn->commit();

    $mensajes = [
        'registrar' => 'Resumen guardado correctamente. Factura en estado REGISTRADO',
        'verificar' => 'Resumen verificado correctamente. Factura en estado VERIFICADO',
        'aprobar' => 'Resumen aprobado correctamente. Factura en estado APROBADO'
    ];

    echo json_encode([
        'success' => true,
        'insertados' => $insertados,
        'estado' => $nuevo_estado,
        'mensaje' => $mensajes[$accion] ?? 'Resumen guardado correctamente'
    ]);

} catch (Exception $e) {
    if (isset($conn)) $conn->rollBack();
    error_log("Error en guardar_resumen: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>