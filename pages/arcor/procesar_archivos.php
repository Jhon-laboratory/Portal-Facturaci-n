<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Verificar si el usuario está logueado
/*if (!isset($_SESSION['usuario']) || !isset($_SESSION['usuario_id'])) {
    header("Location: ../../login.php");
    exit;
}*/

// Verificar si se envió el formulario
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

// Obtener datos del cliente
$cliente_id = $_POST['cliente_id'] ?? '';
$codigo_cliente = $_POST['codigo_cliente'] ?? '';

if (empty($cliente_id) || empty($codigo_cliente)) {
    header("Location: index.php?error=" . urlencode("Datos del cliente no válidos"));
    exit;
}

// Crear carpeta para el cliente si no existe
$carpeta_base = "archivos/" . $codigo_cliente . "/";
$carpeta_despacho = $carpeta_base . "despacho/";
$carpeta_recepcion = $carpeta_base . "recepcion/";
$carpeta_paquete = $carpeta_base . "paquete/";
$carpeta_almacen = $carpeta_base . "almacen/";

// Crear las carpetas necesarias
foreach ([$carpeta_base, $carpeta_despacho, $carpeta_recepcion, $carpeta_paquete, $carpeta_almacen] as $carpeta) {
    if (!file_exists($carpeta)) {
        mkdir($carpeta, 0777, true);
    }
}

// Configuración de tipos de archivo permitidos
$tipos_permitidos = [
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'text/csv',
    'application/vnd.oasis.opendocument.spreadsheet'
];

$extensiones_permitidas = ['xls', 'xlsx', 'csv'];

// Función para procesar cada archivo
function procesarArchivo($file, $carpeta_destino, $tipo) {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => "Error al subir archivo de $tipo"];
    }
    
    // Validar tipo de archivo
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $GLOBALS['extensiones_permitidas'])) {
        return ['success' => false, 'error' => "El archivo de $tipo no es un Excel válido"];
    }
    
    // Generar nombre único para el archivo
    $nombre_archivo = date('Y-m-d_H-i-s') . "_" . $tipo . "." . $extension;
    $ruta_completa = $carpeta_destino . $nombre_archivo;
    
    // Mover el archivo
    if (move_uploaded_file($file['tmp_name'], $ruta_completa)) {
        return [
            'success' => true,
            'nombre' => $nombre_archivo,
            'ruta' => $ruta_completa,
            'tipo' => $tipo
        ];
    } else {
        return ['success' => false, 'error' => "No se pudo guardar el archivo de $tipo"];
    }
}

// Procesar los archivos subidos
$resultados = [];
$errores = [];

// Archivo de Despacho
if (isset($_FILES['archivo_despacho']) && $_FILES['archivo_despacho']['error'] !== UPLOAD_ERR_NO_FILE) {
    $resultado = procesarArchivo($_FILES['archivo_despacho'], $carpeta_despacho, 'despacho');
    if ($resultado['success']) {
        $resultados[] = $resultado;
    } else {
        $errores[] = $resultado['error'];
    }
}

// Archivo de Recepción
if (isset($_FILES['archivo_recepcion']) && $_FILES['archivo_recepcion']['error'] !== UPLOAD_ERR_NO_FILE) {
    $resultado = procesarArchivo($_FILES['archivo_recepcion'], $carpeta_recepcion, 'recepcion');
    if ($resultado['success']) {
        $resultados[] = $resultado;
    } else {
        $errores[] = $resultado['error'];
    }
}

// Archivo de Paquete
if (isset($_FILES['archivo_paquete']) && $_FILES['archivo_paquete']['error'] !== UPLOAD_ERR_NO_FILE) {
    $resultado = procesarArchivo($_FILES['archivo_paquete'], $carpeta_paquete, 'paquete');
    if ($resultado['success']) {
        $resultados[] = $resultado;
    } else {
        $errores[] = $resultado['error'];
    }
}

// Archivo de Almacenamiento
if (isset($_FILES['archivo_almacen']) && $_FILES['archivo_almacen']['error'] !== UPLOAD_ERR_NO_FILE) {
    $resultado = procesarArchivo($_FILES['archivo_almacen'], $carpeta_almacen, 'almacen');
    if ($resultado['success']) {
        $resultados[] = $resultado;
    } else {
        $errores[] = $resultado['error'];
    }
}

// Preparar mensaje para redirección
if (empty($resultados)) {
    $mensaje = "No se subió ningún archivo";
    $tipo_mensaje = "warning";
} elseif (!empty($errores)) {
    $mensaje = "Algunos archivos no se pudieron subir: " . implode(", ", $errores);
    $tipo_mensaje = "warning";
} else {
    $mensaje = count($resultados) . " archivo(s) subido(s) correctamente";
    $tipo_mensaje = "success";
}

// Redirigir de vuelta a la página del cliente
header("Location: index.php?cliente=" . urlencode($codigo_cliente) . "&msg=" . urlencode($mensaje) . "&type=" . $tipo_mensaje);
exit;
?>