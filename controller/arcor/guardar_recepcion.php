<?php
/**
 * Guarda datos de recepción - VERSIÓN CON LOTE OPTIMIZADO (190 registros)
 * CORREGIDO: Incluye UPDATE cuando la factura ya existe
 */

session_start();
ini_set('max_execution_time', 600);
ini_set('memory_limit', '1024M');

require_once '../../conexion/config.php';
require_once '../progress_tracker.php';

header('Content-Type: application/json');

class GuardadoRecepcionRapido {
    private $conn;
    private $tracker;
    
    public function ejecutar($input) {
        $inicio = microtime(true);
        
        try {
            $this->validarInput($input);
            $this->tracker = new ProgressTracker($_SESSION['user_id']);
            $total = count($input['datos']);
            $this->tracker->iniciarProceso($total);
            
            // Procesar factura (CREA o ACTUALIZA)
            $factura_id = $this->procesarFactura($input);
            $this->tracker->actualizarProgreso(10, 'Factura procesada');
            
            // Insertar en lotes de 190
            $insertados = $this->insertarPorLotes($factura_id, $input['datos']);
            
            $tiempo = round(microtime(true) - $inicio, 2);
            $this->tracker->finalizarProceso("✅ Completado: $insertados registros en {$tiempo}s");
            
            return [
                'success' => true,
                'factura_id' => $factura_id,
                'registros_guardados' => $insertados,
                'tiempo_segundos' => $tiempo,
                'mensaje' => "✅ Se guardaron $insertados registros en {$tiempo} segundos"
            ];
            
        } catch (Exception $e) {
            error_log("❌ Error: " . $e->getMessage());
            if ($this->tracker) {
                $this->tracker->registrarError($e->getMessage());
            }
            throw $e;
        }
    }
    
    private function validarInput($input) {
        if (!isset($_SESSION['user_id'])) {
            throw new Exception('Sesión no iniciada');
        }
        if (empty($input['datos'])) {
            throw new Exception('No hay datos para guardar');
        }
    }
    
    private function procesarFactura($input) {
    $this->conn = getDBConnection();
    
    if (!$input['factura_id']) {
        // ============================================
        // CASO 1: NUEVA FACTURA (INSERT)
        // ============================================
        $sql = "INSERT INTO " . TABLA_FACTURAS . " 
                (cliente_id, cliente_codigo, usuario_id, usuario_nombre, 
                 recepcion_archivo, recepcion_completado, fecha_creacion) 
                OUTPUT INSERTED.id
                VALUES (?, ?, ?, ?, ?, 1, GETDATE())";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            $input['cliente_id'],
            $input['cliente_codigo'],
            $_SESSION['user_id'],
            $_SESSION['user_name'] ?? 'Usuario',
            $input['archivo_nombre']
        ]);
        
        $factura_id = $stmt->fetchColumn();
        error_log("✅ NUEVA factura recepción: ID $factura_id");
        
    } else {
        // ============================================
        // CASO 2: FACTURA EXISTENTE (UPDATE)
        // ============================================
        $sql_delete = "DELETE FROM " . TABLA_RECEPCION . " WHERE factura_id = ?";
        $stmt_delete = $this->conn->prepare($sql_delete);
        $stmt_delete->execute([$input['factura_id']]);
        
        $sql_update = "UPDATE " . TABLA_FACTURAS . " 
                       SET recepcion_archivo = ?, 
                           recepcion_completado = 1
                       WHERE id = ?";
        $stmt_update = $this->conn->prepare($sql_update);
        $stmt_update->execute([$input['archivo_nombre'], $input['factura_id']]);
        
        $factura_id = $input['factura_id'];
        error_log("✅ Factura $factura_id ACTUALIZADA, registros anteriores eliminados");
    }
    
    return $factura_id;
}
    
    private function insertarPorLotes($factura_id, $datos) {
        // 11 campos por registro, límite 2100 parámetros
        $lote_tamano = 190;
        $lotes = array_chunk($datos, $lote_tamano);
        $total_insertados = 0;
        $num_lotes = count($lotes);
        
        error_log("📊 Procesando " . count($datos) . " registros en $num_lotes lotes de $lote_tamano");
        
        foreach ($lotes as $indice => $lote) {
            $inicio_lote = microtime(true);
            
            try {
                $values = [];
                $params = [];
                
                foreach ($lote as $row) {
                    $fecha = $this->procesarFecha($row['DATERECEIVED'] ?? '');
                    
                    $values[] = "(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    
                    $params[] = $factura_id;
                    $params[] = $row['RECEIPTKEY'] ?? '';
                    $params[] = $row['SKU'] ?? '';
                    $params[] = $row['STORERKEY'] ?? '';
                    $params[] = intval($row['UNIDADES'] ?? 0);
                    $params[] = intval($row['CAJAS'] ?? 0);
                    $params[] = intval($row['PALLETS'] ?? 0);
                    $params[] = $row['STATUS'] ?? '';
                    $params[] = $fecha;
                    $params[] = $row['EXTERNRECEIPTKEY'] ?? '';
                    $params[] = $row['TYPE'] ?? '';
                }
                
                // Verificar límite de parámetros
                $num_params = count($params);
                if ($num_params > 2100) {
                    throw new Exception("Demasiados parámetros: $num_params (límite 2100)");
                }
                
                $sql = "INSERT INTO " . TABLA_RECEPCION . " 
                        (factura_id, receiptkey, sku, storerkey, unidades, cajas, pallets, 
                         status, fecha_recepcion, external_receiptkey, type) 
                        VALUES " . implode(',', $values);
                
                $stmt = $this->conn->prepare($sql);
                $stmt->execute($params);
                
                $total_insertados += count($lote);
                $tiempo_lote = round(microtime(true) - $inicio_lote, 3);
                
                error_log("✅ Lote " . ($indice + 1) . "/$num_lotes: " . count($lote) . " registros en {$tiempo_lote}s");
                
                $porcentaje = 10 + round(($total_insertados / count($datos)) * 85);
                $this->tracker->actualizarProgreso(
                    $porcentaje, 
                    "Lote " . ($indice + 1) . "/$num_lotes"
                );
                
            } catch (Exception $e) {
                error_log("❌ Error en lote " . ($indice + 1) . ": " . $e->getMessage());
                throw $e;
            }
        }
        
        return $total_insertados;
    }
    
    private function procesarFecha($fecha_str) {
        if (empty($fecha_str)) return null;
        
        $fecha_str = trim($fecha_str);
        if (strpos($fecha_str, '/') !== false) {
            $partes = explode('/', $fecha_str);
            if (count($partes) === 3) {
                $ano = $partes[2];
                if (strlen($ano) === 2) $ano = '20' . $ano;
                return $ano . '-' . 
                       str_pad($partes[1], 2, '0', STR_PAD_LEFT) . '-' . 
                       str_pad($partes[0], 2, '0', STR_PAD_LEFT);
            }
        }
        return null;
    }
}

// Ejecutar
try {
    $input = json_decode(file_get_contents('php://input'), true);
    $guardado = new GuardadoRecepcionRapido();
    $resultado = $guardado->ejecutar($input);
    echo json_encode($resultado);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>