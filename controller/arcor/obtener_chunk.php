<?php
// controller/arcor/obtener_chunk.php
session_start();
header('Content-Type: application/json');

require_once '../../config/constantes.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Sesión no iniciada']);
    exit;
}

$token = $_GET['token'] ?? '';
$pagina = intval($_GET['pagina'] ?? 1);
$por_pagina = intval($_GET['por_pagina'] ?? 100);

if (empty($token)) {
    echo json_encode(['error' => 'Token no proporcionado']);
    exit;
}

// Verificar metadata
$metadata_file = RUTA_CACHE . $token . '_metadata.json';
if (!file_exists($metadata_file)) {
    echo json_encode(['error' => 'Datos expirados o no encontrados']);
    exit;
}

$metadata = json_decode(file_get_contents($metadata_file), true);

// Calcular qué chunk corresponde a esta página
$chunk_num = floor(($pagina - 1) * $por_pagina / TAMANO_CHUNK);
$offset_en_chunk = (($pagina - 1) * $por_pagina) % TAMANO_CHUNK;

$chunk_file = RUTA_CHUNKS . $token . '_chunk_' . $chunk_num . '.json';

if (!file_exists($chunk_file)) {
    echo json_encode(['error' => 'Chunk no encontrado']);
    exit;
}

$chunk_data = json_decode(file_get_contents($chunk_file), true);
$datos_paginados = array_slice($chunk_data['data'], $offset_en_chunk, $por_pagina);

// Si necesitamos más datos del siguiente chunk
if (count($datos_paginados) < $por_pagina && $chunk_num + 1 < $metadata['total_chunks']) {
    $siguiente_chunk = RUTA_CHUNKS . $token . '_chunk_' . ($chunk_num + 1) . '.json';
    if (file_exists($siguiente_chunk)) {
        $siguiente_data = json_decode(file_get_contents($siguiente_chunk), true);
        $faltantes = $por_pagina - count($datos_paginados);
        $datos_paginados = array_merge(
            $datos_paginados,
            array_slice($siguiente_data['data'], 0, $faltantes)
        );
    }
}

echo json_encode([
    'success' => true,
    'pagina' => $pagina,
    'por_pagina' => $por_pagina,
    'total_registros' => $metadata['total_filas'],
    'total_paginas' => ceil($metadata['total_filas'] / $por_pagina),
    'data' => $datos_paginados,
    'stats' => $metadata['stats']
]);