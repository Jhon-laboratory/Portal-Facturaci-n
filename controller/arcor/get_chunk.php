<?php
// controller/arcor/get_chunk.php
session_start();
header('Content-Type: application/json');

// Verificar sesión
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Sesión no iniciada']);
    exit;
}

// Definir rutas si no están definidas
if (!defined('RUTA_CHUNKS')) {
    define('RUTA_CHUNKS', '../../chunks/');
}
if (!defined('RUTA_CACHE')) {
    define('RUTA_CACHE', '../../cache/');
}

$token = isset($_GET['token']) ? $_GET['token'] : '';
$chunk = isset($_GET['chunk']) ? intval($_GET['chunk']) : 0;

if (empty($token)) {
    echo json_encode(['error' => 'Token no proporcionado']);
    exit;
}

// Verificar que el token existe en metadata Error al rpocesar el archivo : unexpexted token '<',"<br/><b>... is not valid JSON"
$metadata_file = RUTA_CACHE . $token . '_metadata.json';
if (!file_exists($metadata_file)) {
    echo json_encode(['error' => 'Token inválido o expirado']);
    exit;
}

// Cargar chunk
$chunk_file = RUTA_CHUNKS . $token . '_chunk_' . $chunk . '.json';
if (!file_exists($chunk_file)) {
    echo json_encode(['error' => 'Chunk no encontrado', 'data' => []]);
    exit;
}

$contenido = file_get_contents($chunk_file);
if ($contenido === false) {
    echo json_encode(['error' => 'Error al leer chunk']);
    exit;
}

echo $contenido;
?>