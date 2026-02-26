<?php
/**
 * Clase para manejar el progreso en tiempo real
 */

class ProgressTracker {
    private $user_id;
    private $process_id;
    private $start_time;
    
    public function __construct($user_id) {
        $this->user_id = $user_id;
        $this->process_id = uniqid('proc_', true);
        $this->start_time = time();
    }
    
    public function iniciarProceso($total_registros) {
        $_SESSION['progress'] = [
            'id' => $this->process_id,
            'total' => $total_registros,
            'actual' => 0,
            'porcentaje' => 0,
            'mensaje' => 'Iniciando proceso...',
            'estado' => 'procesando',
            'inicio' => $this->start_time
        ];
    }
    
    public function actualizarProgreso($porcentaje, $mensaje) {
        if (isset($_SESSION['progress'])) {
            $_SESSION['progress']['porcentaje'] = $porcentaje;
            $_SESSION['progress']['mensaje'] = $mensaje;
            $_SESSION['progress']['actual'] = round(($porcentaje / 100) * $_SESSION['progress']['total']);
            $_SESSION['progress']['ultima_actualizacion'] = time();
        }
    }
    
    public function finalizarProceso($mensaje) {
        if (isset($_SESSION['progress'])) {
            $_SESSION['progress']['porcentaje'] = 100;
            $_SESSION['progress']['mensaje'] = $mensaje;
            $_SESSION['progress']['estado'] = 'completado';
            $_SESSION['progress']['fin'] = time();
            $_SESSION['progress']['tiempo_total'] = time() - $this->start_time;
        }
    }
    
    public function registrarError($error) {
        if (isset($_SESSION['progress'])) {
            $_SESSION['progress']['estado'] = 'error';
            $_SESSION['progress']['error'] = $error;
        }
    }
    
    public static function getProgreso() {
        return $_SESSION['progress'] ?? null;
    }
    
    public static function limpiarProgreso() {
        unset($_SESSION['progress']);
    }
}
?>