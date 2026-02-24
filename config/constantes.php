<?php
// config/constantes.php

// Límites de archivos
define('LIMITE_ARCHIVO_NORMAL', 5 * 1024 * 1024); // 5MB - archivos menores se procesan directo
define('TAMANO_CHUNK', 1000); // 1000 filas por chunk

// Rutas de directorios (ajusta según tu estructura)
define('RUTA_TEMP', dirname(__DIR__) . '/temp/');
define('RUTA_UPLOADS', dirname(__DIR__) . '/uploads/');
define('RUTA_CHUNKS', dirname(__DIR__) . '/chunks/');
define('RUTA_CACHE', dirname(__DIR__) . '/cache/');

// Límites de PHP (estos se aplican en cada script)
ini_set('max_execution_time', 3600);
ini_set('memory_limit', '1024M');
ini_set('upload_max_filesize', '100M');
ini_set('post_max_size', '100M');
?>