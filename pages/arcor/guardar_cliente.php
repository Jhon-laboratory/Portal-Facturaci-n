<?php
session_start();
require_once '../../conexion/config.php';

header('Content-Type: application/json');

try {
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Sesión no iniciada');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['id'])) {
        throw new Exception('Datos incompletos');
    }

    $cliente_id = intval($input['id']);
    
    // Solo actualizar campos permitidos (excluir logo_png)
    $nombre_comercial = $input['nombre_comercial'] ?? '';
    $razon_social = $input['razon_social'] ?? '';
    $nit = $input['nit'] ?? '';
    $telefono = $input['telefono'] ?? '';
    $email = $input['email'] ?? '';
    $direccion = $input['direccion'] ?? '';
    
    if (empty($nombre_comercial)) {
        throw new Exception('El nombre comercial es requerido');
    }

    $conn = getDBConnection();

    $sql_update = "UPDATE [FacBol].[clientes] 
                   SET nombre_comercial = ?,
                       razon_social = ?,
                       nit = ?,
                       telefono = ?,
                       email = ?,
                       direccion = ?,
                       fecha_actualizacion = GETDATE(),
                       usuario_actualizacion = ?
                   WHERE id = ?";
    
    $stmt = $conn->prepare($sql_update);
    $stmt->execute([
        $nombre_comercial,
        $razon_social,
        $nit,
        $telefono,
        $email,
        $direccion,
        $_SESSION['user_id'],
        $cliente_id
    ]);

    echo json_encode([
        'success' => true,
        'mensaje' => 'Cliente actualizado correctamente'
    ]);

} catch (Exception $e) {
    error_log("Error en guardar_cliente: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>