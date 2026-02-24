<?php
/**
 * Controlador para procesar recepciones usando Python
 * 
 * Recibe el archivo y lo envía a la API Python para procesamiento eficiente
 */

session_start();

require_once __DIR__ . '/../python_bridge.php';

// Configurar headers
header('Content-Type: application/json');

try {
    // Verificar sesión
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Sesión no iniciada');
    }
    
    // Verificar archivo
    if (!isset($_FILES['archivo'])) {
        throw new Exception('No se recibió ningún archivo');
    }
    
    if ($_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
        $errores = [
            UPLOAD_ERR_INI_SIZE => 'El archivo excede el tamaño máximo',
            UPLOAD_ERR_FORM_SIZE => 'El archivo excede el tamaño máximo del formulario',
            UPLOAD_ERR_PARTIAL => 'El archivo se subió parcialmente',
            UPLOAD_ERR_NO_FILE => 'No se subió ningún archivo',
            UPLOAD_ERR_NO_TMP_DIR => 'Falta la carpeta temporal',
            UPLOAD_ERR_CANT_WRITE => 'No se pudo escribir el archivo',
            UPLOAD_ERR_EXTENSION => 'Una extensión detuvo la subida'
        ];
        $error_msg = $errores[$_FILES['archivo']['error']] ?? 'Error desconocido';
        throw new Exception('Error al subir: ' . $error_msg);
    }
    
    // Verificar extensión
    $extension = strtolower(pathinfo($_FILES['archivo']['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, ['xls', 'xlsx', 'csv'])) {
        throw new Exception('Formato no válido. Solo .xls, .xlsx o .csv');
    }
    
    // Obtener filtros
    $filtros = [];
    if (isset($_POST['fecha_desde']) && !empty($_POST['fecha_desde'])) {
        $filtros['fecha_desde'] = $_POST['fecha_desde'];
    }
    if (isset($_POST['fecha_hasta']) && !empty($_POST['fecha_hasta'])) {
        $filtros['fecha_hasta'] = $_POST['fecha_hasta'];
    }
    
    // Procesar con Python
    $bridge = new PythonBridge();
    $resultado = $bridge->procesarArchivo('recepcion', $_FILES['archivo'], $filtros);
    
    // Devolver resultado
    echo json_encode($resultado);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>