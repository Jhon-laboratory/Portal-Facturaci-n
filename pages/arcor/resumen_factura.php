<?php
/**
 * Resumen de factura con cálculos de tarifas
 */

session_start();
require_once '../../conexion/config.php';

// Verificar sesión
if (!isset($_SESSION['user_id'])) {
    die('Acceso no autorizado');
}

$factura_id = $_GET['factura_id'] ?? 0;

if (!$factura_id) {
    die('ID de factura no válido');
}

try {
    $conn = getDBConnection();
    
    // Obtener información de la factura y cliente
    $sql_factura = "SELECT fc.*, c.nombre_comercial, c.nit, c.codigo_cliente
                    FROM " . TABLA_FACTURAS . " fc
                    INNER JOIN [FacBol].[clientes] c ON fc.cliente_codigo = c.codigo_cliente
                    WHERE fc.id = ?";
    $stmt = $conn->prepare($sql_factura);
    $stmt->execute([$factura_id]);
    $factura = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$factura) {
        die('Factura no encontrada');
    }
    
    // Obtener datos de recepción
    $sql_recepcion = "SELECT 
                        'Recepción IN' as servicio,
                        CASE 
                            WHEN PALLETS > 0 THEN 'Pallet'
                            WHEN CAJAS > 0 THEN 'Caja/Bulto'
                            ELSE 'Unidades'
                        END as sub_servicio,
                        SUM(UNIDADES) as total_unidades,
                        SUM(CAJAS) as total_cajas,
                        SUM(PALLETS) as total_pallets
                      FROM " . TABLA_RECEPCION . " 
                      WHERE factura_id = ?
                      GROUP BY 
                        CASE 
                            WHEN PALLETS > 0 THEN 'Pallet'
                            WHEN CAJAS > 0 THEN 'Caja/Bulto'
                            ELSE 'Unidades'
                        END";
    $stmt = $conn->prepare($sql_recepcion);
    $stmt->execute([$factura_id]);
    $recepcion = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener datos de despacho
    $sql_despacho = "SELECT 
                        'Despacho OUT' as servicio,
                        CASE 
                            WHEN PALLETS > 0 THEN 'Pallet'
                            WHEN CAJAS > 0 THEN 'Caja/Bulto'
                            ELSE 'Unidades'
                        END as sub_servicio,
                        SUM(UNIDADES) as total_unidades,
                        SUM(CAJAS) as total_cajas,
                        SUM(PALLETS) as total_pallets
                      FROM " . TABLA_DESPACHO . " 
                      WHERE factura_id = ?
                      GROUP BY 
                        CASE 
                            WHEN PALLETS > 0 THEN 'Pallet'
                            WHEN CAJAS > 0 THEN 'Caja/Bulto'
                            ELSE 'Unidades'
                        END";
    $stmt = $conn->prepare($sql_despacho);
    $stmt->execute([$factura_id]);
    $despacho = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener datos de ocupabilidad
    $sql_ocupabilidad = "SELECT 
                            'Almacenamiento' as servicio,
                            tipo_ubicacion as sub_servicio,
                            cantidad
                          FROM FacBol.ocupabilidad_ubicaciones 
                          WHERE factura_id = ?";
    $stmt = $conn->prepare($sql_ocupabilidad);
    $stmt->execute([$factura_id]);
    $ocupabilidad = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener otros servicios
    $sql_otros = "SELECT 
                    'Otros servicios' as servicio,
                    servicio_nombre as sub_servicio,
                    cantidad,
                    tarifa,
                    total
                  FROM [FacBol].[facturas_otros_servicios] 
                  WHERE factura_id = ?";
    $stmt = $conn->prepare($sql_otros);
    $stmt->execute([$factura_id]);
    $otros_servicios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener tarifas del cliente
    $sql_tarifas = "SELECT * FROM FacBol.maestro_tarifas 
                    WHERE cliente_codigo = ? AND activo = 1";
    $stmt = $conn->prepare($sql_tarifas);
    $stmt->execute([$factura['cliente_codigo']]);
    $tarifas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Crear mapa de tarifas
    $mapa_tarifas = [];
    foreach ($tarifas as $t) {
        $key = $t['servicio'] . '|' . $t['sub_servicio'];
        $mapa_tarifas[$key] = [
            'tarifa_usd' => $t['tarifa_usd'],
            'tarifa_bs' => $t['tarifa_bs'],
            'udm' => $t['udm']
        ];
    }
    
    // Verificar si ya existe un resumen guardado
    $sql_resumen = "SELECT * FROM FacBol.facturas_resumen WHERE factura_id = ?";
    $stmt = $conn->prepare($sql_resumen);
    $stmt->execute([$factura_id]);
    $resumen_guardado = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $datos_resumen = [];
    
    if (!empty($resumen_guardado)) {
        // Usar datos guardados
        $datos_resumen = $resumen_guardado;
    } else {
        // Construir resumen desde cero
        
        // Recepción
        foreach ($recepcion as $r) {
            $key = $r['servicio'] . '|' . $r['sub_servicio'];
            $tarifa = $mapa_tarifas[$key] ?? ['tarifa_usd' => 0, 'tarifa_bs' => 0, 'udm' => ''];
            $cantidad = $r['sub_servicio'] == 'Pallet' ? $r['total_pallets'] : 
                       ($r['sub_servicio'] == 'Caja/Bulto' ? $r['total_cajas'] : $r['total_unidades']);
            
            if ($cantidad > 0) {
                $total_usd = $cantidad * $tarifa['tarifa_usd'];
                $datos_resumen[] = [
                    'servicio' => $r['servicio'],
                    'sub_servicio' => $r['sub_servicio'],
                    'udm' => $tarifa['udm'] ?: $r['sub_servicio'],
                    'cantidad' => $cantidad,
                    'tarifa_usd' => $tarifa['tarifa_usd'],
                    'total_usd' => $total_usd,
                    'total_usd_con_iva' => $total_usd * 1.16,
                    'total_bs' => $cantidad * $tarifa['tarifa_bs'],
                    'total_bs_con_iva' => $cantidad * $tarifa['tarifa_bs'] * 1.16,
                    'tipo_servicio' => 'Recepcion',
                    'editable' => 0
                ];
            }
        }
        
        // Despacho
        foreach ($despacho as $d) {
            $key = $d['servicio'] . '|' . $d['sub_servicio'];
            $tarifa = $mapa_tarifas[$key] ?? ['tarifa_usd' => 0, 'tarifa_bs' => 0, 'udm' => ''];
            $cantidad = $d['sub_servicio'] == 'Pallet' ? $d['total_pallets'] : 
                       ($d['sub_servicio'] == 'Caja/Bulto' ? $d['total_cajas'] : $d['total_unidades']);
            
            if ($cantidad > 0) {
                $total_usd = $cantidad * $tarifa['tarifa_usd'];
                $datos_resumen[] = [
                    'servicio' => $d['servicio'],
                    'sub_servicio' => $d['sub_servicio'],
                    'udm' => $tarifa['udm'] ?: $d['sub_servicio'],
                    'cantidad' => $cantidad,
                    'tarifa_usd' => $tarifa['tarifa_usd'],
                    'total_usd' => $total_usd,
                    'total_usd_con_iva' => $total_usd * 1.16,
                    'total_bs' => $cantidad * $tarifa['tarifa_bs'],
                    'total_bs_con_iva' => $cantidad * $tarifa['tarifa_bs'] * 1.16,
                    'tipo_servicio' => 'Despacho',
                    'editable' => 0
                ];
            }
        }
        
        // Ocupabilidad (Almacenamiento)
        foreach ($ocupabilidad as $o) {
            $key = 'Almacenamiento|' . $o['sub_servicio'];
            $tarifa = $mapa_tarifas[$key] ?? ['tarifa_usd' => 0, 'tarifa_bs' => 0, 'udm' => 'Posiciones rack'];
            
            if ($o['cantidad'] > 0) {
                $total_usd = $o['cantidad'] * $tarifa['tarifa_usd'];
                $datos_resumen[] = [
                    'servicio' => 'Almacenamiento',
                    'sub_servicio' => $o['sub_servicio'],
                    'udm' => $tarifa['udm'],
                    'cantidad' => $o['cantidad'],
                    'tarifa_usd' => $tarifa['tarifa_usd'],
                    'total_usd' => $total_usd,
                    'total_usd_con_iva' => $total_usd * 1.16,
                    'total_bs' => $o['cantidad'] * $tarifa['tarifa_bs'],
                    'total_bs_con_iva' => $o['cantidad'] * $tarifa['tarifa_bs'] * 1.16,
                    'tipo_servicio' => 'Almacenamiento',
                    'editable' => 0
                ];
            }
        }
        
        // Descarga y Carga (están vacíos por ahora, editables)
        $servicios_editables = [
            ['servicio' => 'Descarga', 'sub_servicio' => 'Pallet', 'udm' => 'Pallet'],
            ['servicio' => 'Descarga', 'sub_servicio' => 'Caja/Bulto', 'udm' => 'Caja/Bulto'],
            ['servicio' => 'Carga', 'sub_servicio' => 'Pallet', 'udm' => 'Pallet'],
            ['servicio' => 'Carga', 'sub_servicio' => 'Caja/Bulto', 'udm' => 'Caja/Bulto']
        ];
        
        foreach ($servicios_editables as $se) {
            $key = $se['servicio'] . '|' . $se['sub_servicio'];
            $tarifa = $mapa_tarifas[$key] ?? ['tarifa_usd' => 0, 'tarifa_bs' => 0, 'udm' => $se['udm']];
            
            $datos_resumen[] = [
                'servicio' => $se['servicio'],
                'sub_servicio' => $se['sub_servicio'],
                'udm' => $tarifa['udm'],
                'cantidad' => 0,
                'tarifa_usd' => $tarifa['tarifa_usd'],
                'total_usd' => 0,
                'total_usd_con_iva' => 0,
                'total_bs' => 0,
                'total_bs_con_iva' => 0,
                'tipo_servicio' => 'Carga',
                'editable' => 1
            ];
        }
        
        // Otros servicios
        foreach ($otros_servicios as $os) {
            $datos_resumen[] = [
                'servicio' => 'Otros servicios',
                'sub_servicio' => $os['sub_servicio'],
                'udm' => 'Kg',
                'cantidad' => $os['cantidad'],
                'tarifa_usd' => $os['tarifa'] / 6.96, // Aproximación USD a BS
                'total_usd' => $os['total'] / 6.96,
                'total_usd_con_iva' => ($os['total'] / 6.96) * 1.16,
                'total_bs' => $os['total'],
                'total_bs_con_iva' => $os['total'] * 1.16,
                'tipo_servicio' => 'Otros',
                'editable' => 0
            ];
        }
    }
    
    // Calcular totales
    $total_usd = 0;
    $total_usd_con_iva = 0;
    $total_bs = 0;
    $total_bs_con_iva = 0;
    
    foreach ($datos_resumen as $item) {
        $total_usd += $item['total_usd'];
        $total_usd_con_iva += $item['total_usd_con_iva'];
        $total_bs += $item['total_bs'];
        $total_bs_con_iva += $item['total_bs_con_iva'];
    }
    
} catch (Exception $e) {
    die('Error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resumen de Factura <?php echo $factura_id; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e9ecef 100%);
            min-height: 100vh;
            padding: 30px 20px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        /* Header */
        .header-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border-radius: 20px 20px 0 0;
            padding: 25px 30px;
            box-shadow: 0 10px 30px rgba(0,154,63,0.15);
            display: flex;
            align-items: center;
            gap: 30px;
            border-left: 8px solid #009a3f;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }
        
        .info-container {
            flex: 1;
        }
        
        .info-container h1 {
            margin: 0 0 10px 0;
            color: #009a3f;
            font-size: 28px;
            font-weight: 600;
            border-bottom: 2px solid #e0e0e0;
            padding-bottom: 10px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .info-item {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #555;
        }
        
        .info-item i {
            color: #009a3f;
            width: 20px;
        }
        
        /* Botones de acción */
        .action-bar {
            background: white;
            border-radius: 15px;
            padding: 15px 25px;
            margin-bottom: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            flex-wrap: wrap;
            position: sticky;
            top: 20px;
            z-index: 100;
        }
        
        .btn {
            padding: 12px 25px;
            border-radius: 50px;
            font-size: 14px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #009a3f, #007a32);
            color: white;
            box-shadow: 0 4px 15px rgba(0,154,63,0.3);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,154,63,0.4);
        }
        
        .btn-secondary {
            background: white;
            color: #666;
            border: 1px solid #ddd;
        }
        
        .btn-secondary:hover {
            background: #f8f9fa;
            color: #333;
            border-color: #009a3f;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background: #218838;
        }
        
        /* Tabla de resumen */
        .table-container {
            background: white;
            border-radius: 20px;
            padding: 5px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow-x: auto;
            margin-bottom: 30px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        
        th {
            background: linear-gradient(135deg, #009a3f, #007a32);
            color: white;
            padding: 15px 8px;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.5px;
            white-space: nowrap;
            text-align: center;
        }
        
        td {
            padding: 10px 8px;
            border-bottom: 1px solid #e9ecef;
            color: #444;
        }
        
        tbody tr:hover {
            background: #f8f9fa;
        }
        
        tbody tr:nth-child(even) {
            background: #fafafa;
        }
        
        .text-right {
            text-align: right;
        }
        
        .text-center {
            text-align: center;
        }
        
        .servicio-group {
            background: #e8f5e9 !important;
            font-weight: 600;
        }
        
        .total-row {
            background: linear-gradient(135deg, #e8f5e9, #c8e6c9) !important;
            font-weight: 600;
            font-size: 14px;
        }
        
        .total-row td {
            border-top: 2px solid #009a3f;
        }
        
        input[type="number"] {
            width: 80px;
            padding: 5px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            text-align: right;
        }
        
        input[type="number"]:focus {
            border-color: #009a3f;
            outline: none;
            box-shadow: 0 0 0 3px rgba(0,154,63,0.1);
        }
        
        .editable-cell {
            background: #fff3cd;
        }
        
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 500;
        }
        
        .badge-info {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .footer {
            background: white;
            border-radius: 15px;
            padding: 20px 25px;
            margin-top: 30px;
            box-shadow: 0 -5px 20px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            border-top: 3px solid #009a3f;
        }
        
        @media print {
            .action-bar {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header-card">
            <div class="info-container">
                <h1><i class="fa fa-calculator"></i> RESUMEN DE FACTURA</h1>
                <div class="info-grid">
                    <div class="info-item">
                        <i class="fa fa-barcode"></i>
                        <span><strong>Factura:</strong> FAC-<?php echo str_pad($factura_id, 6, '0', STR_PAD_LEFT); ?></span>
                    </div>
                    <div class="info-item">
                        <i class="fa fa-building"></i>
                        <span><strong>Cliente:</strong> <?php echo htmlspecialchars($factura['nombre_comercial']); ?></span>
                    </div>
                    <div class="info-item">
                        <i class="fa fa-id-card"></i>
                        <span><strong>NIT:</strong> <?php echo htmlspecialchars($factura['nit']); ?></span>
                    </div>
                    <div class="info-item">
                        <i class="fa fa-calendar"></i>
                        <span><strong>Fecha:</strong> <?php echo date('d/m/Y H:i', strtotime($factura['fecha_creacion'])); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Botones de acción -->
        <div class="action-bar">
            <button onclick="guardarResumen()" class="btn btn-success">
                <i class="fa fa-save"></i> Guardar Resumen
            </button>
            <button onclick="generarPDF()" class="btn btn-primary">
                <i class="fa fa-file-pdf-o"></i> Exportar PDF
            </button>
            <button onclick="window.close()" class="btn btn-secondary">
                <i class="fa fa-times"></i> Cerrar
            </button>
        </div>

        <!-- Tabla de resumen -->
        <div class="table-container">
            <table id="tablaResumen">
                <thead>
                    <tr>
                        <th>DESCRIPCIÓN</th>
                        <th>UDM</th>
                        <th>TARIFA USD</th>
                        <th>CANTIDAD</th>
                        <th>TOTAL USD</th>
                        <th>IVA + IT (16%) USD</th>
                        <th>TOTAL BS</th>
                        <th>IVA + IT (16%) BS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $current_servicio = '';
                    foreach ($datos_resumen as $item): 
                        if ($current_servicio != $item['servicio']): 
                            $current_servicio = $item['servicio'];
                    ?>
                        <tr class="servicio-group">
                            <td colspan="8"><strong><?php echo $item['servicio']; ?></strong></td>
                        </tr>
                    <?php endif; ?>
                    <tr>
                        <td style="padding-left: 30px;"><?php echo htmlspecialchars($item['sub_servicio']); ?></td>
                        <td class="text-center"><?php echo htmlspecialchars($item['udm']); ?></td>
                        <td class="text-right">$<?php echo number_format($item['tarifa_usd'], 2, ',', '.'); ?></td>
                        <td class="text-right <?php echo $item['editable'] ? 'editable-cell' : ''; ?>">
                            <?php if ($item['editable']): ?>
                                <input type="number" 
                                       class="cantidad-input" 
                                       data-servicio="<?php echo $item['servicio']; ?>"
                                       data-sub="<?php echo $item['sub_servicio']; ?>"
                                       value="<?php echo $item['cantidad']; ?>" 
                                       min="0" 
                                       step="0.01"
                                       onchange="actualizarFila(this)">
                            <?php else: ?>
                                <?php echo number_format($item['cantidad'], 2, ',', '.'); ?>
                            <?php endif; ?>
                        </td>
                        <td class="text-right total-usd">$<?php echo number_format($item['total_usd'], 2, ',', '.'); ?></td>
                        <td class="text-right">$<?php echo number_format($item['total_usd_con_iva'], 2, ',', '.'); ?></td>
                        <td class="text-right total-bs">Bs<?php echo number_format($item['total_bs'], 2, ',', '.'); ?></td>
                        <td class="text-right">Bs<?php echo number_format($item['total_bs_con_iva'], 2, ',', '.'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <!-- Fila de totales -->
                    <tr class="total-row">
                        <td colspan="4" class="text-right"><strong>TOTALES</strong></td>
                        <td class="text-right"><strong>$<?php echo number_format($total_usd, 2, ',', '.'); ?></strong></td>
                        <td class="text-right"><strong>$<?php echo number_format($total_usd_con_iva, 2, ',', '.'); ?></strong></td>
                        <td class="text-right"><strong>Bs<?php echo number_format($total_bs, 2, ',', '.'); ?></strong></td>
                        <td class="text-right"><strong>Bs<?php echo number_format($total_bs_con_iva, 2, ',', '.'); ?></strong></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Footer -->
        <div class="footer">
            <div>
                <span><i class="fa fa-info-circle"></i> Los campos en amarillo son editables</span>
            </div>
            <div style="color: #009a3f;">
                <i class="fa fa-check-circle"></i> Ransa Archivo - Bolivia
            </div>
        </div>
    </div>

    <script>
        // Variables globales
        const factura_id = <?php echo $factura_id; ?>;
        let datosResumen = <?php echo json_encode($datos_resumen); ?>;

        // Actualizar fila cuando cambia cantidad
        function actualizarFila(input) {
            const fila = input.closest('tr');
            const servicio = input.dataset.servicio;
            const subServicio = input.dataset.sub;
            const nuevaCantidad = parseFloat(input.value) || 0;
            
            // Buscar el item en datosResumen
            const item = datosResumen.find(i => i.servicio === servicio && i.sub_servicio === subServicio);
            
            if (item) {
                item.cantidad = nuevaCantidad;
                item.total_usd = nuevaCantidad * item.tarifa_usd;
                item.total_usd_con_iva = item.total_usd * 1.16;
                item.total_bs = nuevaCantidad * (item.tarifa_usd * 6.96); // Aproximación
                item.total_bs_con_iva = item.total_bs * 1.16;
                
                // Actualizar celdas
                fila.querySelector('.total-usd').textContent = '$' + formatNumber(item.total_usd);
                fila.querySelector('.total-bs').textContent = 'Bs' + formatNumber(item.total_bs);
            }
            
            actualizarTotales();
        }

        // Formatear número
        function formatNumber(num) {
            return num.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        }

        // Actualizar totales generales
        function actualizarTotales() {
            let totalUSD = 0;
            let totalUSDIVA = 0;
            let totalBS = 0;
            let totalBSIVA = 0;
            
            datosResumen.forEach(item => {
                totalUSD += item.total_usd;
                totalUSDIVA += item.total_usd_con_iva;
                totalBS += item.total_bs;
                totalBSIVA += item.total_bs_con_iva;
            });
            
            const totalRow = document.querySelector('.total-row');
            if (totalRow) {
                const celdas = totalRow.querySelectorAll('td');
                celdas[4].innerHTML = '<strong>$' + formatNumber(totalUSD) + '</strong>';
                celdas[5].innerHTML = '<strong>$' + formatNumber(totalUSDIVA) + '</strong>';
                celdas[6].innerHTML = '<strong>Bs' + formatNumber(totalBS) + '</strong>';
                celdas[7].innerHTML = '<strong>Bs' + formatNumber(totalBSIVA) + '</strong>';
            }
        }

        // Guardar resumen
        function guardarResumen() {
            fetch('../../controller/arcor/guardar_resumen.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    factura_id: factura_id,
                    datos: datosResumen
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Resumen guardado correctamente');
                } else {
                    alert('Error: ' + data.error);
                }
            })
            .catch(error => {
                alert('Error al guardar: ' + error.message);
            });
        }

        // Generar PDF
        function generarPDF() {
            window.print();
        }
    </script>
</body>
</html>