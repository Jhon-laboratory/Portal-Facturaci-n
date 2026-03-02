<?php
session_start();
require_once '../../conexion/config.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['cliente'])) {
    die('Acceso no autorizado');
}

$cliente_codigo = $_GET['cliente'];

try {
    $conn = getDBConnection();
    
    $sql = "SELECT id, servicio, sub_servicio, udm, tarifa_usd, tipo_servicio 
            FROM [FacBol].[maestro_tarifas] 
            WHERE cliente_codigo = ? AND activo = 1
            ORDER BY 
                CASE tipo_servicio 
                    WHEN 'Recepcion' THEN 1
                    WHEN 'Carga' THEN 2
                    WHEN 'Almacenamiento' THEN 3
                    WHEN 'Despacho' THEN 4
                    WHEN 'Otros' THEN 5
                END,
                id";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$cliente_codigo]);
    $tarifas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($tarifas)) {
        echo '<div class="alert alert-warning">No hay tarifas configuradas para este cliente</div>';
        exit;
    }
    
    ?>
    <div style="max-height: 500px; overflow-y: auto;">
        <table class="table table-bordered table-hover" style="font-size: 12px;">
            <thead style="position: sticky; top: 0; background: #f8f9fa; z-index: 10;">
                <tr>
                    <th>Servicio</th>
                    <th>Sub Servicio</th>
                    <th>UDM</th>
                    <th>Tipo</th>
                    <th style="width: 120px;">Tarifa USD</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tarifas as $tarifa): ?>
                <tr class="tarifa-row" data-id="<?php echo $tarifa['id']; ?>">
                    <td><?php echo htmlspecialchars($tarifa['servicio']); ?></td>
                    <td><?php echo htmlspecialchars($tarifa['sub_servicio']); ?></td>
                    <td><?php echo htmlspecialchars($tarifa['udm']); ?></td>
                    <td><?php echo htmlspecialchars($tarifa['tipo_servicio']); ?></td>
                    <td>
                        <input type="number" step="0.01" min="0" 
                               class="form-control form-control-sm tarifa-usd" 
                               value="<?php echo number_format($tarifa['tarifa_usd'], 2, '.', ''); ?>">
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
} catch (Exception $e) {
    echo '<div class="alert alert-danger">Error al cargar tarifas: ' . htmlspecialchars($e->getMessage()) . '</div>';
    error_log("Error en get_tarifas: " . $e->getMessage());
}
?>