<?php
// controller/arcor/limpiar_temp.php
require_once '../../config/constantes.php';

// Limpiar archivos temporales con más de 1 hora
$archivos = glob(RUTA_CHUNKS . '*');
$ahora = time();

foreach ($archivos as $archivo) {
    if (is_file($archivo) && ($ahora - filemtime($archivo)) > TIEMPO_EXPIRACION_CACHE) {
        unlink($archivo);
    }
}

$archivos = glob(RUTA_CACHE . '*');
foreach ($archivos as $archivo) {
    if (is_file($archivo) && ($ahora - filemtime($archivo)) > TIEMPO_EXPIRACION_CACHE) {
        unlink($archivo);
    }
}

$archivos = glob(RUTA_UPLOADS . '*');
foreach ($archivos as $archivo) {
    if (is_file($archivo) && ($ahora - filemtime($archivo)) > TIEMPO_EXPIRACION_CACHE) {
        unlink($archivo);
    }
}

echo "Limpieza completada";