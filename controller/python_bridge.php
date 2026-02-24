<?php
/**
 * Bridge PHP-Python para procesamiento de Excel
 * 
 * Este archivo actúa como intermediario entre el frontend PHP
 * y la API Python, manejando la comunicación y errores
 */

class PythonBridge {
    private $api_url = 'http://127.0.0.1:5000';
    private $timeout = 600; // 10 minutos
    private $debug = true;
    private $log_file;
    
    public function __construct() {
        $this->log_file = __DIR__ . '/python_bridge.log';
        $this->log("=== Bridge PHP-Python inicializado ===");
    }
    
    /**
     * Verifica si la API Python está disponible
     */
    public function checkHealth() {
        try {
            $response = $this->makeRequest('GET', '/health');
            return isset($response['status']) && $response['status'] === 'ok';
        } catch (Exception $e) {
            $this->log("Health check falló: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Procesa un archivo usando la API Python
     * 
     * @param string $tipo Tipo de módulo (recepcion, despacho, etc.)
     * @param array $file Datos del archivo $_FILES['archivo']
     * @param array $filtros Filtros adicionales (fecha_desde, fecha_hasta)
     * @return array Respuesta decodificada
     */
    public function procesarArchivo($tipo, $file, $filtros = []) {
        $this->log("Procesando archivo tipo: $tipo");
        $this->log("Archivo: " . $file['name'] . " (" . round($file['size']/1024/1024, 2) . "MB)");
        
        try {
            // Validar que la API esté disponible
            if (!$this->checkHealth()) {
                // Intentar iniciar la API automáticamente
                $this->iniciarApiPython();
                
                // Esperar un momento
                sleep(2);
                
                // Verificar de nuevo
                if (!$this->checkHealth()) {
                    throw new Exception("La API Python no está disponible. Asegúrate de ejecutar 'python api_python/app.py'");
                }
            }
            
            // Construir URL
            $url = $this->api_url . '/procesar/' . $tipo;
            
            // Preparar datos multipart
            $postFields = [];
            
            // Agregar archivo
            if (class_exists('CURLFile')) {
                $postFields['archivo'] = new CURLFile(
                    $file['tmp_name'],
                    $file['type'],
                    $file['name']
                );
            } else {
                $postFields['archivo'] = '@' . $file['tmp_name'];
            }
            
            // Agregar filtros
            if (!empty($filtros['fecha_desde'])) {
                $postFields['fecha_desde'] = $filtros['fecha_desde'];
            }
            if (!empty($filtros['fecha_hasta'])) {
                $postFields['fecha_hasta'] = $filtros['fecha_hasta'];
            }
            
            // Hacer petición
            $start = microtime(true);
            $response = $this->makeRequest('POST', '/procesar/' . $tipo, $postFields, true);
            $elapsed = round(microtime(true) - $start, 2);
            
            $this->log("Respuesta recibida en {$elapsed}s");
            
            // Verificar respuesta
            if (isset($response['success']) && $response['success'] === false) {
                throw new Exception($response['error'] ?? 'Error desconocido');
            }
            
            // Agregar tiempo de procesamiento
            $response['tiempo_total_php'] = $elapsed;
            
            return $response;
            
        } catch (Exception $e) {
            $this->log("ERROR: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Realiza una petición HTTP a la API Python
     */
    private function makeRequest($method, $endpoint, $data = null, $multipart = false) {
        $url = $this->api_url . $endpoint;
        
        $ch = curl_init();
        
        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_VERBOSE => $this->debug
        ];
        
        if ($method === 'POST') {
            $options[CURLOPT_POST] = true;
            
            if ($multipart) {
                // Para archivos, usar multipart/form-data
                $options[CURLOPT_POSTFIELDS] = $data;
                $options[CURLOPT_HTTPHEADER] = [
                    'Expect:',
                    'Accept: application/json'
                ];
            } else {
                // Para JSON
                $options[CURLOPT_POSTFIELDS] = json_encode($data);
                $options[CURLOPT_HTTPHEADER] = [
                    'Content-Type: application/json',
                    'Accept: application/json'
                ];
            }
        }
        
        curl_setopt_array($ch, $options);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        if ($error) {
            throw new Exception("Error CURL: $error");
        }
        
        if ($httpCode !== 200) {
            throw new Exception("HTTP Error $httpCode: $response");
        }
        
        $decoded = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("JSON inválido: " . json_last_error_msg() . "\nRespuesta: " . substr($response, 0, 500));
        }
        
        return $decoded;
    }
    
    /**
     * Intenta iniciar la API Python automáticamente
     */
    private function iniciarApiPython() {
        $this->log("Intentando iniciar API Python...");
        
        // Detectar sistema operativo
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // Windows
            $command = 'start /B python ' . __DIR__ . '/../api_python/app.py > NUL 2>&1';
        } else {
            // Linux/Mac
            $command = 'python3 ' . __DIR__ . '/../api_python/app.py > /dev/null 2>&1 &';
        }
        
        // Ejecutar en segundo plano
        if (function_exists('proc_open')) {
            $descriptorspec = [
                0 => ["pipe", "r"],
                1 => ["pipe", "w"],
                2 => ["pipe", "w"]
            ];
            
            $process = proc_open($command, $descriptorspec, $pipes);
            if (is_resource($process)) {
                proc_close($process);
                $this->log("Comando ejecutado: $command");
            }
        } else {
            exec($command);
            $this->log("Exec ejecutado: $command");
        }
        
        return true;
    }
    
    /**
     * Escribe en el log
     */
    private function log($mensaje) {
        if ($this->debug) {
            $fecha = date('Y-m-d H:i:s');
            file_put_contents(
                $this->log_file,
                "[$fecha] $mensaje\n",
                FILE_APPEND
            );
        }
    }
    
    /**
     * Obtiene información de la API
     */
    public function getInfo() {
        try {
            return $this->makeRequest('GET', '/info');
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
}

// Función helper para uso fácil
function procesarConPython($tipo, $archivo, $filtros = []) {
    $bridge = new PythonBridge();
    return $bridge->procesarArchivo($tipo, $archivo, $filtros);
}
?>