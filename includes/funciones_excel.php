<?php
// includes/funciones_excel.php
require_once __DIR__ . '/../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

function convertirExcelACSV($archivo_excel, $archivo_csv_destino) {
    try {
        // Cargar solo para obtener el número de hojas y nombres
        $reader = IOFactory::createReaderForFile($archivo_excel);
        $reader->setReadDataOnly(true);
        
        // Configurar para leer solo la hoja 'Detail'
        $reader->setLoadSheetsOnly(['Detail', 'DETALLE']);
        
        // Cargar el spreadsheet
        $spreadsheet = $reader->load($archivo_excel);
        
        // Obtener la hoja Detail
        $hoja = $spreadsheet->getSheetByName('Detail');
        if (!$hoja) {
            $hoja = $spreadsheet->getSheetByName('DETALLE');
        }
        if (!$hoja) {
            throw new Exception('No se encontró la hoja Detail');
        }
        
        // Abrir archivo CSV para escritura
        $csv_file = fopen($archivo_csv_destino, 'w');
        
        // Obtener encabezados (fila 1)
        $encabezados = [];
        $columna = 'A';
        while ($hoja->getCell($columna . '1')->getValue() !== null) {
            $encabezados[] = $hoja->getCell($columna . '1')->getValue();
            $columna++;
        }
        fputcsv($csv_file, $encabezados, ';');
        
        // Obtener todas las filas en modo streaming
        $highestRow = $hoja->getHighestRow();
        $highestColumn = $hoja->getHighestColumn();
        
        // Escribir datos en chunks de 1000 filas
        for ($row = 2; $row <= $highestRow; $row++) {
            $fila_data = [];
            for ($col = 'A'; $col <= $highestColumn; $col++) {
                $fila_data[] = $hoja->getCell($col . $row)->getValue();
            }
            fputcsv($csv_file, $fila_data, ';');
            
            // Liberar memoria cada 1000 filas
            if ($row % 1000 == 0) {
                $hoja->garbageCollect();
            }
        }
        
        fclose($csv_file);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
        
        return true;
        
    } catch (Exception $e) {
        throw new Exception('Error convirtiendo a CSV: ' . $e->getMessage());
    }
}

function normalizarUOM($uom_raw) {
    $uom_raw = trim(strtoupper($uom_raw ?? ''));
    
    if (in_array($uom_raw, ['UN', 'UNIDAD', 'UNIDADES', 'PIEZA', 'PIEZAS', 'EA', 'EACH'])) {
        return 'UN';
    } elseif (in_array($uom_raw, ['CJ', 'CAJA', 'CAJAS', 'BOX', 'BOXES', 'CTN', 'CARTON'])) {
        return 'CJ';
    } elseif (in_array($uom_raw, ['PL', 'PALET', 'PALETA', 'PALETS', 'PALLET', 'PALLETS', 'PAL'])) {
        return 'PL';
    }
    
    return 'UN'; // Por defecto
}

function normalizarNumero($valor) {
    if (empty($valor)) return 0;
    
    if (is_numeric($valor)) {
        return floatval($valor);
    }
    
    $valor = trim(strval($valor));
    
    // Formato europeo: 2.413,00000
    if (strpos($valor, ',') !== false) {
        // Quitar puntos de miles y cambiar coma por punto
        $valor = str_replace('.', '', $valor);
        $valor = str_replace(',', '.', $valor);
    } else {
        // Limpiar espacios
        $valor = str_replace(' ', '', $valor);
    }
    
    return floatval($valor);
}

function traducirEstado($estado) {
    $mapa = [
        '9' => 'Recibido',
        '15' => 'Verificado y cerrado',
        '0' => 'Nuevo',
        '5' => 'En recepción',
        '11' => 'Cerrado',
        'RECEIVED' => 'Recibido',
        'CLOSED' => 'Cerrado',
        'NEW' => 'Nuevo'
    ];
    
    return $mapa[$estado] ?? $estado;
}

function traducirTipo($tipo) {
    $mapa = [
        '100' => 'IMPORTACION',
        '101' => 'REPLENISHMENT',
        'IMPORT' => 'IMPORTACION',
        'REPLEN' => 'REPLENISHMENT'
    ];
    
    return $mapa[$tipo] ?? $tipo;
}

function fusionarStats($stats1, $stats2) {
    return [
        'total_recepciones' => ($stats1['total_recepciones'] ?? 0) + ($stats2['total_recepciones'] ?? 0),
        'total_articulos' => ($stats1['total_articulos'] ?? 0) + ($stats2['total_articulos'] ?? 0),
        'total_un' => ($stats1['total_un'] ?? 0) + ($stats2['total_un'] ?? 0),
        'total_cj' => ($stats1['total_cj'] ?? 0) + ($stats2['total_cj'] ?? 0),
        'total_pl' => ($stats1['total_pl'] ?? 0) + ($stats2['total_pl'] ?? 0),
        'proveedores' => ($stats1['proveedores'] ?? 0) + ($stats2['proveedores'] ?? 0)
    ];
}