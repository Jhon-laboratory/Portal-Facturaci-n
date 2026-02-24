<?php
// controller/arcor/recep.arcor.scz.php - VERSIÓN CON FILTRO POR RANGO DE FECHAS

// Activar todos los errores
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Log de errores en archivo
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error_log.txt');

// Aumentar límites
ini_set('max_execution_time', 600);
ini_set('memory_limit', '1024M');
ini_set('upload_max_filesize', '500M');
ini_set('post_max_size', '500M');

// Iniciar buffer
ob_start();

session_start();

// Función para debug
function debug_log($mensaje) {
    $log = date('Y-m-d H:i:s') . " - " . $mensaje . PHP_EOL;
    file_put_contents(__DIR__ . '/debug_log.txt', $log, FILE_APPEND);
}

debug_log("=== INICIO DE PETICIÓN ===");
debug_log("POST: " . print_r($_POST, true));
debug_log("FILES: " . print_r($_FILES, true));
debug_log("SESSION: " . print_r($_SESSION, true));

try {
    // Verificar sesión
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Sesión no iniciada');
    }
    debug_log("Sesión OK: " . $_SESSION['user_id']);

    // Verificar archivo
    if (!isset($_FILES['archivo'])) {
        throw new Exception('No se recibió ningún archivo');
    }
    debug_log("Archivo recibido");

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
    debug_log("Error upload: OK");

    // Verificar tamaño
    $tamano = $_FILES['archivo']['size'];
    debug_log("Tamaño archivo: " . round($tamano/1024/1024, 2) . "MB");

    // Verificar extensión
    $extension = strtolower(pathinfo($_FILES['archivo']['name'], PATHINFO_EXTENSION));
    debug_log("Extensión: " . $extension);
    
    if (!in_array($extension, ['xls', 'xlsx', 'csv'])) {
        throw new Exception('Formato no válido. Solo .xls, .xlsx o .csv');
    }

    // Ruta del archivo temporal
    $archivo_tmp = $_FILES['archivo']['tmp_name'];
    debug_log("Archivo temporal: " . $archivo_tmp);
    debug_log("Archivo existe: " . (file_exists($archivo_tmp) ? 'SI' : 'NO'));

    // Cargar autoload
    $rutas_autoload = [
        __DIR__ . '/../../vendor/autoload.php',
        __DIR__ . '/../vendor/autoload.php',
        __DIR__ . '/vendor/autoload.php',
        'C:/xampp/htdocs/Portal-Facturacion/vendor/autoload.php'
    ];
    
    $autoload_cargado = false;
    foreach ($rutas_autoload as $ruta) {
        debug_log("Buscando autoload en: " . $ruta);
        if (file_exists($ruta)) {
            require_once $ruta;
            $autoload_cargado = true;
            debug_log("✓ Autoload encontrado en: " . $ruta);
            break;
        }
    }
    
    if (!$autoload_cargado) {
        throw new Exception('No se encontró autoload.php');
    }

    // Verificar PhpSpreadsheet
    if (!class_exists('PhpOffice\PhpSpreadsheet\IOFactory')) {
        throw new Exception('PhpSpreadsheet no está instalado');
    }
    debug_log("✓ PhpSpreadsheet cargado");

    // Cargar el archivo
    debug_log("Cargando archivo Excel...");
    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($archivo_tmp);
    debug_log("✓ Archivo cargado");

    // Buscar la hoja 'Detail' (exactamente como la pides)
    $hoja = $spreadsheet->getSheetByName('Detail');
    if (!$hoja) {
        // Si no encuentra 'Detail', intentar con mayúsculas/minúsculas
        $hoja = $spreadsheet->getSheetByName('DETAIL');
    }
    if (!$hoja) {
        // Si no encuentra ninguna, buscar por nombre que contenga 'Detail'
        foreach ($spreadsheet->getSheetNames() as $sheetName) {
            if (stripos($sheetName, 'detail') !== false) {
                $hoja = $spreadsheet->getSheetByName($sheetName);
                break;
            }
        }
    }
    if (!$hoja) {
        // Último recurso: usar la hoja activa
        $hoja = $spreadsheet->getActiveSheet();
        debug_log("⚠️ No se encontró hoja 'Detail', usando: " . $hoja->getTitle());
    } else {
        debug_log("✓ Hoja 'Detail' encontrada: " . $hoja->getTitle());
    }

    // Obtener dimensiones
    $highestRow = $hoja->getHighestRow();
    $highestColumn = $hoja->getHighestColumn();
    $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);
    
    debug_log("Filas: $highestRow, Columnas: $highestColumn (índice: $highestColumnIndex)");

    // Definir las columnas que nos interesan (basado en tu ejemplo)
    $columnas_deseadas = [
        'RECEIPTKEY' => 'C',        // Columna C - ASN/Recepción
        'SKU' => 'D',               // Columna D - Artículo
        'STORERKEY' => 'E',         // Columna E - Propietario
        'RECEIPTLINENUMBER' => 'F',  // Columna F - N.º de línea
        'QTYRECEIVED' => 'H',        // Columna H - Ctd. recibida
        'UOM' => 'I',                // Columna I - UDM
        'STATUS' => 'O',              // Columna O - Estatus
        'DATERECEIVED' => 'AH'        // Columna AH - Fecha de recepción
    ];

    // Obtener filtros de fecha si vienen en POST
    $fecha_desde = isset($_POST['fecha_desde']) ? $_POST['fecha_desde'] : null;
    $fecha_hasta = isset($_POST['fecha_hasta']) ? $_POST['fecha_hasta'] : null;
    
    debug_log("Filtros de fecha: desde=$fecha_desde, hasta=$fecha_hasta");

    // Primero, leer la fila 1 para obtener los headers reales
    $headers_reales = [];
    foreach ($columnas_deseadas as $nombre => $columna) {
        $valor = $hoja->getCell($columna . '1')->getValue();
        $headers_reales[$columna] = $valor ? trim($valor) : $nombre;
    }
    debug_log("Headers encontrados: " . print_r($headers_reales, true));

    // Leer los datos (desde fila 2 hasta el final)
    $datos = [];
    $todos_los_datos = []; // Guardar todos los datos válidos para aplicar filtros
    $total_registros_sin_filtro = 0;
    $total_registros_filtrados_cantidad = 0; // Filtrados por cantidad cero
    $total_registros_filtrados_fecha = 0;    // Filtrados por rango de fechas
    $max_filas_vista = 100; // Mostrar hasta 100 filas en la vista previa
    
    // Arrays para almacenar rangos de fechas
    $fecha_min = null;
    $fecha_max = null;

    for ($fila = 2; $fila <= $highestRow; $fila++) {
        $fila_datos = [];
        $fila_valida = false;
        $fecha_formateada = '';
        
        foreach ($columnas_deseadas as $nombre => $columna) {
            // Obtener el valor de la celda
            $celda = $hoja->getCell($columna . $fila);
            $valor = $celda->getValue();
            
            // Formatear según el tipo de dato
            if ($celda->getDataType() == \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC) {
                // Si es numérico, mantener formato
                if (is_float($valor)) {
                    // Para cantidades, formatear con decimales
                    if ($nombre == 'QTYRECEIVED') {
                        $valor = number_format($valor, 5, ',', '');
                    } else {
                        $valor = (string)$valor;
                    }
                }
            }
            
            // Limpiar el valor
            $valor = trim($valor ?? '');
            
            // Si es STATUS, convertir número a texto si es necesario
            if ($nombre == 'STATUS' && $valor === '0') {
                $valor = '0 - Pendiente';
            } elseif ($nombre == 'STATUS' && $valor === '9') {
                $valor = '9 - Completado';
            }
            
            // Si es DATERECEIVED, formatear para mostrar solo la fecha
            if ($nombre == 'DATERECEIVED' && !empty($valor)) {
                // Intentar convertir a fecha
                try {
                    // Si es un número de Excel (timestamp)
                    if (is_numeric($valor) && $valor > 40000) { // Rango aproximado de fechas Excel
                        $fecha_excel = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($valor);
                        $fecha_formateada = $fecha_excel->format('d/m/Y'); // Formato día/mes/año
                        $fecha_para_comparar = $fecha_excel->format('Y-m-d'); // Para comparar (formato ISO)
                        
                        // Actualizar rangos de fechas
                        if ($fecha_min === null || $fecha_para_comparar < $fecha_min) {
                            $fecha_min = $fecha_para_comparar;
                        }
                        if ($fecha_max === null || $fecha_para_comparar > $fecha_max) {
                            $fecha_max = $fecha_para_comparar;
                        }
                        
                        $valor = $fecha_formateada;
                    } 
                    // Si es string con formato de fecha
                    else {
                        // Intentar diferentes formatos de fecha
                        $timestamp = strtotime($valor);
                        if ($timestamp !== false) {
                            $fecha_formateada = date('d/m/Y', $timestamp);
                            $fecha_para_comparar = date('Y-m-d', $timestamp);
                            
                            // Actualizar rangos de fechas
                            if ($fecha_min === null || $fecha_para_comparar < $fecha_min) {
                                $fecha_min = $fecha_para_comparar;
                            }
                            if ($fecha_max === null || $fecha_para_comparar > $fecha_max) {
                                $fecha_max = $fecha_para_comparar;
                            }
                            
                            $valor = $fecha_formateada;
                        } else {
                            // Si no se puede convertir, tomar solo la fecha si tiene formato con hora
                            if (strpos($valor, ' ') !== false) {
                                $partes = explode(' ', $valor);
                                $fecha_sin_hora = $partes[0];
                                $timestamp = strtotime($fecha_sin_hora);
                                if ($timestamp !== false) {
                                    $fecha_para_comparar = date('Y-m-d', $timestamp);
                                    $valor = date('d/m/Y', $timestamp);
                                } else {
                                    $fecha_para_comparar = null;
                                    $valor = $fecha_sin_hora;
                                }
                            } else {
                                $fecha_para_comparar = null;
                            }
                        }
                    }
                    
                    // Guardar la fecha para comparar en el filtro
                    $fila_datos['_FECHA_COMPARAR'] = $fecha_para_comparar;
                    
                } catch (Exception $e) {
                    debug_log("Error formateando fecha en fila $fila: " . $e->getMessage());
                    // Si hay error, mantener el valor original pero intentar quitar la hora
                    if (strpos($valor, ' ') !== false) {
                        $partes = explode(' ', $valor);
                        $valor = $partes[0];
                    }
                    $fila_datos['_FECHA_COMPARAR'] = null;
                }
            }
            
            $fila_datos[$nombre] = $valor;
            
            // Verificar si la fila tiene datos válidos
            if (!empty($valor) && $valor !== '0,00000' && $valor !== '0') {
                $fila_valida = true;
            }
        }
        
        // SOLO procesar si la fila tiene datos válidos
        if ($fila_valida) {
            // Verificar específicamente si QTYRECEIVED es cero o está vacío
            $cantidad = isset($fila_datos['QTYRECEIVED']) ? $fila_datos['QTYRECEIVED'] : '';
            
            // Función para verificar si la cantidad es cero (considerando diferentes formatos)
            $es_cero = false;
            if (empty($cantidad) || $cantidad === '0' || $cantidad === '0,00000' || $cantidad === '0.00000' || floatval(str_replace(',', '.', $cantidad)) == 0) {
                $es_cero = true;
                $total_registros_filtrados_cantidad++;
                debug_log("Fila $fila filtrada por cantidad cero: '$cantidad'");
            }
            
            // Si NO es cero, considerar para filtros de fecha
            if (!$es_cero) {
                $total_registros_sin_filtro++;
                
                // Aplicar filtro de fecha si existe
                $pasa_filtro_fecha = true;
                
                if ($fecha_desde || $fecha_hasta) {
                    $fecha_comparar = isset($fila_datos['_FECHA_COMPARAR']) ? $fila_datos['_FECHA_COMPARAR'] : null;
                    
                    if ($fecha_comparar) {
                        if ($fecha_desde && $fecha_comparar < $fecha_desde) {
                            $pasa_filtro_fecha = false;
                        }
                        if ($fecha_hasta && $fecha_comparar > $fecha_hasta) {
                            $pasa_filtro_fecha = false;
                        }
                    } else {
                        // Si no hay fecha para comparar, pasar el filtro? 
                        // Decidí NO pasar para mantener consistencia
                        $pasa_filtro_fecha = false;
                    }
                }
                
                // Guardar en todos_los_datos para estadísticas
                $todos_los_datos[] = [
                    'datos' => $fila_datos,
                    'fecha_comparar' => isset($fila_datos['_FECHA_COMPARAR']) ? $fila_datos['_FECHA_COMPARAR'] : null,
                    'pasa_filtro' => $pasa_filtro_fecha
                ];
                
                // Si pasa el filtro de fecha, agregar a la vista previa
                if ($pasa_filtro_fecha) {
                    if (count($datos) < $max_filas_vista) {
                        // Remover campo interno antes de agregar a datos
                        unset($fila_datos['_FECHA_COMPARAR']);
                        $datos[] = array_values($fila_datos);
                    }
                } else {
                    if ($fecha_desde || $fecha_hasta) {
                        $total_registros_filtrados_fecha++;
                    }
                }
            }
        }
    }

    // Calcular total después de filtros
    $total_registros_final = 0;
    foreach ($todos_los_datos as $item) {
        if ($item['pasa_filtro']) {
            $total_registros_final++;
        }
    }

    debug_log("Total registros sin filtrar: $total_registros_sin_filtro");
    debug_log("Registros filtrados por cantidad cero: $total_registros_filtrados_cantidad");
    debug_log("Registros filtrados por fecha: $total_registros_filtrados_fecha");
    debug_log("Registros después de filtros: $total_registros_final");
    debug_log("Registros para vista previa: " . count($datos));

    // Calcular estadísticas
    $stats = [
        'total_filas' => $total_registros_final,
        'mostrando' => count($datos),
        'receiptkeys_unicos' => 0,
        'total_unidades' => 0,
        'filas_filtradas_cantidad' => $total_registros_filtrados_cantidad,
        'filas_filtradas_fecha' => $total_registros_filtrados_fecha,
        'fecha_min' => $fecha_min ? date('d/m/Y', strtotime($fecha_min)) : null,
        'fecha_max' => $fecha_max ? date('d/m/Y', strtotime($fecha_max)) : null,
        'filtros_aplicados' => [
            'fecha_desde' => $fecha_desde ? date('d/m/Y', strtotime($fecha_desde)) : null,
            'fecha_hasta' => $fecha_hasta ? date('d/m/Y', strtotime($fecha_hasta)) : null
        ]
    ];

    // Calcular estadísticas adicionales con los datos filtrados
    if ($total_registros_final > 0) {
        $receiptkeys = [];
        $total_qty = 0;
        
        foreach ($todos_los_datos as $item) {
            if ($item['pasa_filtro']) {
                $receiptkey = isset($item['datos']['RECEIPTKEY']) ? $item['datos']['RECEIPTKEY'] : '';
                $cantidad_str = isset($item['datos']['QTYRECEIVED']) ? $item['datos']['QTYRECEIVED'] : '0';
                $cantidad = floatval(str_replace(',', '.', str_replace('.', '', $cantidad_str)));
                
                if (!empty($receiptkey)) {
                    $receiptkeys[$receiptkey] = true;
                }
                $total_qty += $cantidad;
            }
        }
        
        $stats['receiptkeys_unicos'] = count($receiptkeys);
        $stats['total_unidades'] = number_format($total_qty, 2, ',', '.');
    }

    // Liberar memoria
    $spreadsheet->disconnectWorksheets();
    unset($spreadsheet);

    // Headers para mostrar (los nombres de columna que quieres ver)
    $headers_mostrar = [
        'RECEIPTKEY',
        'SKU',
        'STORERKEY',
        'RECEIPTLINENUMBER',
        'QTYRECEIVED',
        'UOM',
        'STATUS',
        'DATERECEIVED'
    ];

    // Preparar respuesta
    $respuesta = [
        'success' => true,
        'total_registros' => $total_registros_final,
        'headers' => $headers_mostrar,
        'data' => $datos,
        'stats' => $stats,
        'hoja_usada' => $hoja->getTitle(),
        'mensaje' => $total_registros_filtrados_cantidad > 0 ? "Se filtraron $total_registros_filtrados_cantidad registros con cantidad cero" : '',
        'mensaje_fecha' => $total_registros_filtrados_fecha > 0 ? "Se filtraron $total_registros_filtrados_fecha registros por rango de fechas" : ''
    ];

    debug_log("Enviando respuesta JSON con " . count($datos) . " filas de vista previa");
    debug_log("Rango de fechas: " . ($stats['fecha_min'] ?? 'N/A') . " - " . ($stats['fecha_max'] ?? 'N/A'));
    
    // Limpiar buffer y enviar JSON
    ob_clean();
    echo json_encode($respuesta, JSON_PRETTY_PRINT);
    debug_log("JSON enviado correctamente");
    
} catch (Exception $e) {
    $error_msg = $e->getMessage();
    $error_line = $e->getLine();
    $error_file = $e->getFile();
    
    debug_log("ERROR: $error_msg en $error_file:$error_line");
    
    $respuesta = [
        'success' => false,
        'error' => $error_msg,
        'linea' => $error_line,
        'archivo' => basename($error_file),
        'debug' => 'Revisa debug_log.txt en ' . __DIR__
    ];
    
    ob_clean();
    echo json_encode($respuesta);
}
?>