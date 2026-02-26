<?php
/**
 * Endpoint para obtener el progreso en tiempo real
 */

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/progress_tracker.php';

$progreso = ProgressTracker::getProgreso();

if ($progreso) {
    // Calcular tiempo estimado restante
    if ($progreso['porcentaje'] > 0 && $progreso['porcentaje'] < 100) {
        $tiempo_transcurrido = time() - $progreso['inicio'];
        $tiempo_estimado_total = round($tiempo_transcurrido / ($progreso['porcentaje'] / 100));
        $tiempo_restante = max(0, $tiempo_estimado_total - $tiempo_transcurrido);
        $progreso['tiempo_restante'] = $tiempo_restante;
    }
    
    echo json_encode(['success' => true, 'data' => $progreso]);
} else {
    echo json_encode(['success' => false, 'message' => 'No hay proceso activo']);
}
?>