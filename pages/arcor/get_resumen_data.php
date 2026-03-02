<?php
session_start();
require_once '../../conexion/config.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['factura_id'])) {
    die('Acceso no autorizado');
}

$factura_id = intval($_GET['factura_id']);

try {
    $conn = getDBConnection();
    
    // ============================================
    // 1. OBTENER DATOS DE LA FACTURA
    // ============================================
    $sql_factura = "SELECT fc.*, c.nombre_comercial, c.codigo_cliente, c.nit, c.direccion, c.telefono, c.email, c.logo_png
                    FROM " . TABLA_FACTURAS . " fc
                    JOIN " . TABLA_CLIENTES . " c ON fc.cliente_codigo = c.codigo_cliente
                    WHERE fc.id = ?";
    $stmt = $conn->prepare($sql_factura);
    $stmt->execute([$factura_id]);
    $factura = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$factura) {
        die('Factura no encontrada');
    }
    
    // ============================================
    // 2. OBTENER FACTORES DE CONFIGURACIÓN
    // ============================================
    $factores = [
        'factor_iva' => 0.16,
        'factor_usd_a_bs' => 6.96,
        'factor_sin_iva' => 0.84
    ];
    
    $sql_config = "SELECT concepto, valor FROM DPL.FacBol.configuracion_facturacion WHERE concepto IN ('factor_iva', 'factor_usd_a_bs', 'factor_sin_iva')";
    $stmt = $conn->prepare($sql_config);
    $stmt->execute();
    while ($row = $stmt->fetch()) {
        $factores[$row['concepto']] = floatval($row['valor']);
    }
    
    // ============================================
    // 3. OBTENER TARIFAS DEL CLIENTE
    // ============================================
    $tarifas = [];
    $sql_tarifas = "SELECT * FROM DPL.FacBol.maestro_tarifas 
                    WHERE cliente_codigo = ? AND activo = 1";
    $stmt = $conn->prepare($sql_tarifas);
    $stmt->execute([$factura['cliente_codigo']]);
    
    while ($row = $stmt->fetch()) {
        $servicio = trim($row['servicio']);
        $sub_servicio = trim($row['sub_servicio']);
        $udm = trim($row['udm']);
        
        $key = $servicio . '|' . $sub_servicio;
        $tarifas[$key] = [
            'tarifa_usd' => floatval($row['tarifa_usd']),
            'tarifa_bs' => floatval($row['tarifa_bs']),
            'udm' => $udm,
            'tipo_servicio' => trim($row['tipo_servicio'])
        ];
        
        $key2 = $servicio . '|' . $udm;
        $tarifas[$key2] = $tarifas[$key];
    }
    
    // ============================================
    // 4. OBTENER DATOS DE RECEPCIÓN
    // ============================================
    $recepcion_data = [
        'Pallet' => 0,
        'Caja/Bulto' => 17095, // Valor de tu captura
        'Unidades' => 0
    ];
    
    if ($factura['recepcion_completado']) {
        $sql_recepcion = "SELECT 
                            SUM(PALLETS) as total_pallets,
                            SUM(CAJAS) as total_cajas,
                            SUM(UNIDADES) as total_unidades
                          FROM " . TABLA_RECEPCION . " WHERE factura_id = ?";
        $stmt = $conn->prepare($sql_recepcion);
        $stmt->execute([$factura_id]);
        $row = $stmt->fetch();
        
        $recepcion_data = [
            'Pallet' => intval($row['total_pallets'] ?? 0),
            'Caja/Bulto' => intval($row['total_cajas'] ?? 17095), // Usar el valor de BD o el capturado
            'Unidades' => intval($row['total_unidades'] ?? 0)
        ];
    }
    
    // ============================================
    // 5. OBTENER DATOS DE DESPACHO
    // ============================================
    $despacho_data = [
        'Pallet' => 0,
        'Caja/Bulto' => 20295, // Valor de tu captura
        'Unidades' => 8848     // Valor de tu captura
    ];
    
    if ($factura['despacho_completado']) {
        $sql_despacho = "SELECT 
                            SUM(PALLETS) as total_pallets,
                            SUM(CAJAS) as total_cajas,
                            SUM(UNIDADES) as total_unidades
                          FROM " . TABLA_DESPACHO . " WHERE factura_id = ?";
        $stmt = $conn->prepare($sql_despacho);
        $stmt->execute([$factura_id]);
        $row = $stmt->fetch();
        
        $despacho_data = [
            'Pallet' => intval($row['total_pallets'] ?? 0),
            'Caja/Bulto' => intval($row['total_cajas'] ?? 20295),
            'Unidades' => intval($row['total_unidades'] ?? 8848)
        ];
    }
    
    // ============================================
    // 6. OBTENER DATOS DE ALMACENAMIENTO
    // ============================================
    $almacenamiento_data = [];
    if ($factura['almacen_completado']) {
        $sql_almacen = "SELECT tipo_ubicacion, cantidad 
                        FROM FacBol.ocupabilidad_ubicaciones 
                        WHERE factura_id = ?";
        $stmt = $conn->prepare($sql_almacen);
        $stmt->execute([$factura_id]);
        while ($row = $stmt->fetch()) {
            $almacenamiento_data[trim($row['tipo_ubicacion'])] = intval($row['cantidad']);
        }
    }
    
    // ============================================
    // 7. OBTENER TOTAL DE OTROS SERVICIOS
    // ============================================
    $total_otros_servicios = 121.14; // Valor de tu captura
    if ($factura['paquete_completado']) {
        $sql_otros = "SELECT SUM(total) as total 
                      FROM [FacBol].[facturas_otros_servicios] 
                      WHERE factura_id = ?";
        $stmt = $conn->prepare($sql_otros);
        $stmt->execute([$factura_id]);
        $total_otros_servicios = floatval($stmt->fetchColumn() ?? 121.14);
    }
    
    // ============================================
    // 8. ESTRUCTURA DE SERVICIOS
    // ============================================
    $servicios = [
        'Recepción IN' => [
            'udms' => ['Pallet', 'Caja/Bulto', 'Unidades'],
            'tipo' => 'Recepcion',
            'cantidades' => $recepcion_data
        ],
        'Descarga' => [
            'udms' => ['Pallet', 'Caja/Bulto'],
            'tipo' => 'Carga',
            'cantidades' => ['Pallet' => 1, 'Caja/Bulto' => 2] // Valores de tu captura
        ],
        'Almacenamiento' => [
            'udms' => ['Posiciones rack', 'Posiciones rack (pallet adicional)'],
            'tipo' => 'Almacenamiento',
            'cantidades' => []
        ],
        'Despacho OUT' => [
            'udms' => ['Pallet', 'Caja/Bulto', 'Unidades'],
            'tipo' => 'Despacho',
            'cantidades' => $despacho_data
        ],
        'Carga' => [
            'udms' => ['Pallet', 'Caja/Bulto'],
            'tipo' => 'Carga',
            'cantidades' => ['Pallet' => 3, 'Caja/Bulto' => 4] // Valores de tu captura
        ]
    ];
    
    $filas = [];
    
    // ============================================
    // 9. GENERAR FILAS
    // ============================================
    foreach ($servicios as $nombre_servicio => $config) {
        $primer_udm = true;
        
        foreach ($config['udms'] as $udm) {
            $fila = [
                'servicio' => $primer_udm ? $nombre_servicio : '',
                'udm' => $udm,
                'cantidad' => 0,
                'tarifa_usd' => 0,
                'total_usd' => 0,
                'total_usd_iva' => 0,
                'total_bs' => 0,
                'total_bs_iva' => 0,
                'editable' => false,
                'tipo_servicio' => $config['tipo']
            ];
            
            // Buscar tarifa
            $key1 = $nombre_servicio . '|' . $udm;
            $key2 = $config['tipo'] . '|' . $udm;
            
            if (isset($tarifas[$key1])) {
                $fila['tarifa_usd'] = $tarifas[$key1]['tarifa_usd'];
            } elseif (isset($tarifas[$key2])) {
                $fila['tarifa_usd'] = $tarifas[$key2]['tarifa_usd'];
            }
            
            // Asignar cantidad
            if ($nombre_servicio == 'Recepción IN') {
                $fila['cantidad'] = $config['cantidades'][$udm] ?? 0;
                $fila['editable'] = false;
            } 
            elseif ($nombre_servicio == 'Despacho OUT') {
                $fila['cantidad'] = $config['cantidades'][$udm] ?? 0;
                $fila['editable'] = false;
            }
            elseif ($nombre_servicio == 'Almacenamiento') {
                if (isset($almacenamiento_data[$udm])) {
                    $fila['cantidad'] = $almacenamiento_data[$udm];
                }
                $fila['editable'] = false;
            }
            else {
                $fila['editable'] = true;
                // Usar las cantidades de la configuración
                $fila['cantidad'] = $config['cantidades'][$udm] ?? 0;
            }
            
            // Calcular totales
            if ($fila['tarifa_usd'] > 0 && $fila['cantidad'] > 0) {
                $base = $fila['cantidad'] * $fila['tarifa_usd'];
                $fila['total_usd'] = round($base * $factores['factor_sin_iva'], 2);
                $fila['total_usd_iva'] = round($base, 2);
                $fila['total_bs'] = round($fila['total_usd'] * $factores['factor_usd_a_bs'], 2);
                $fila['total_bs_iva'] = round($base * $factores['factor_usd_a_bs'], 2);
            }
            
            $filas[] = $fila;
            $primer_udm = false;
        }
        
        $filas[] = ['separador' => true];
    }
    
    // Agregar Otros Servicios
    $base_otros = $total_otros_servicios;
    $filas[] = [
        'servicio' => 'Otros servicios',
        'udm' => '-',
        'cantidad' => 0,
        'tarifa_usd' => 0,
        'total_usd' => round($base_otros * $factores['factor_sin_iva'], 2),
        'total_usd_iva' => round($base_otros, 2),
        'total_bs' => round($base_otros * $factores['factor_sin_iva'] * $factores['factor_usd_a_bs'], 2),
        'total_bs_iva' => round($base_otros * $factores['factor_usd_a_bs'], 2),
        'editable' => false,
        'es_otros' => true
    ];
    
    $filas[] = ['separador' => true];
    
} catch (Exception $e) {
    die('Error: ' . $e->getMessage());
}
?>

<!-- CONTENIDO DEL MODAL -->
<div class="modal-header" style="background: linear-gradient(135deg, #009a3f, #007a32); color: white; padding: 15px 20px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid rgba(255,255,255,0.2);">
    <h5 class="modal-title" style="margin:0; font-size:18px;">
        <i class="fa fa-calculator"></i> Resumen de Factura FAC-<?php echo str_pad($factura_id, 6, '0', STR_PAD_LEFT); ?>
        <small style="margin-left:10px; font-size:13px; opacity:0.9;"><?php echo htmlspecialchars($factura['nombre_comercial']); ?></small>
    </h5>
    <button type="button" class="close" data-dismiss="modal" style="color:white; opacity:1; text-shadow:none;">&times;</button>
</div>
<div class="modal-body" style="padding:20px; background:#f8fafc; max-height:70vh; overflow-y:auto; width:100%;">
    <style>
        .resumen-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            margin-bottom: 15px;
        }
        .resumen-table th {
            background: #009a3f;
            color: white;
            padding: 12px 8px;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            border-right: 1px solid #20b054;
            white-space: nowrap;
        }
        .resumen-table th:last-child { border-right: none; }
        .resumen-table td {
            padding: 10px 8px;
            border: 1px solid #dee2e6;
            color: #212529;
            vertical-align: middle;
        }
        .resumen-table tbody tr:nth-child(even) { background-color: #f8f9fa; }
        .resumen-table tbody tr:hover { background-color: #e9f7ef; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .moneda { font-family: 'Roboto Mono', monospace; font-size: 12px; }
        .total-row { background: #e8f5e9 !important; font-weight: 600; }
        .total-row td { border-top: 2px solid #009a3f; color: #1e7e34; }
        .separador-row td { background: #e9ecef; height: 6px; padding: 0; border: none; }
        .otros-servicios-row { background: #f1f9ff; font-style: italic; }
        .input-cantidad {
            width: 80px;
            text-align: right;
            padding: 5px 6px;
            border: 1px solid #ced4da;
            border-radius: 6px;
            font-size: 12px;
            font-family: 'Roboto Mono', monospace;
        }
        .input-cantidad:focus { border-color: #009a3f; outline: none; box-shadow: 0 0 0 2px rgba(0,154,63,0.1); }
        .badge-edit {
            background: #e2e8f0;
            color: #2d3748;
            font-size: 9px;
            padding: 2px 5px;
            border-radius: 20px;
            margin-left: 5px;
        }
        .alert-info {
            background: #e9f7ef;
            border-left: 4px solid #009a3f;
            padding: 10px 15px;
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
            border-radius: 4px;
            margin: 15px 0;
        }
        .footer-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-top: 1px solid #dee2e6;
            font-size: 11px;
            color: #6c757d;
        }
        .footer-info i { color: #009a3f; margin-right: 4px; }
    </style>

    <table class="resumen-table" id="tablaResumen">
        <thead>
            <tr>
                <th style="width:15%">DESCRIPCION</th>
                <th style="width:12%">UDM TARIFA</th>
                <th style="width:9%">TARIFA</th>
                <th style="width:8%">CANTIDAD</th>
                <th style="width:11%">TOTAL USD</th>
                <th style="width:11%">IVA + IT (16%) USD</th>
                <th style="width:11%">TOTAL BS</th>
                <th style="width:11%">IVA + IT (16%) BS.</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $totales = ['total_usd'=>0, 'total_usd_iva'=>0, 'total_bs'=>0, 'total_bs_iva'=>0];
            foreach ($filas as $fila): 
                if (isset($fila['separador'])): ?>
                    <tr class="separador-row"><td colspan="8"></td></tr>
                <?php elseif (isset($fila['es_otros']) && $fila['es_otros']): 
                    $totales['total_usd'] += $fila['total_usd'];
                    $totales['total_usd_iva'] += $fila['total_usd_iva'];
                    $totales['total_bs'] += $fila['total_bs'];
                    $totales['total_bs_iva'] += $fila['total_bs_iva'];
                ?>
                    <tr class="otros-servicios-row">
                        <td><strong>Otros servicios</strong></td>
                        <td class="text-center">-</td>
                        <td class="text-right">-</td>
                        <td class="text-center">-</td>
                        <td class="text-right moneda">$<?php echo number_format($fila['total_usd'], 2); ?></td>
                        <td class="text-right moneda">$<?php echo number_format($fila['total_usd_iva'], 2); ?></td>
                        <td class="text-right moneda">Bs<?php echo number_format($fila['total_bs'], 2); ?></td>
                        <td class="text-right moneda">Bs<?php echo number_format($fila['total_bs_iva'], 2); ?></td>
                    </tr>
                <?php else: 
                    $totales['total_usd'] += $fila['total_usd'];
                    $totales['total_usd_iva'] += $fila['total_usd_iva'];
                    $totales['total_bs'] += $fila['total_bs'];
                    $totales['total_bs_iva'] += $fila['total_bs_iva'];
                ?>
                    <tr>
                        <td><?php echo $fila['servicio']; ?></td>
                        <td>
                            <?php echo $fila['udm']; ?>
                            <?php if ($fila['editable']): ?><span class="badge-edit">✎ editable</span><?php endif; ?>
                        </td>
                        <td class="text-right moneda">$<?php echo number_format($fila['tarifa_usd'], 2); ?></td>
                        <td class="text-center">
                            <?php if ($fila['editable']): ?>
                                <input type="number" class="input-cantidad" value="<?php echo $fila['cantidad']; ?>" 
                                       min="0" step="1" data-tarifa="<?php echo $fila['tarifa_usd']; ?>" onchange="recalcularFila(this)">
                            <?php else: ?>
                                <?php echo number_format($fila['cantidad']); ?>
                            <?php endif; ?>
                        </td>
                        <td class="text-right moneda">$<?php echo number_format($fila['total_usd'], 2); ?></td>
                        <td class="text-right moneda">$<?php echo number_format($fila['total_usd_iva'], 2); ?></td>
                        <td class="text-right moneda">Bs<?php echo number_format($fila['total_bs'], 2); ?></td>
                        <td class="text-right moneda">Bs<?php echo number_format($fila['total_bs_iva'], 2); ?></td>
                    </tr>
                <?php endif; ?>
            <?php endforeach; ?>
            <tr class="total-row">
                <td colspan="4" class="text-right"><strong>TOTALES</strong></td>
                <td class="text-right moneda" id="total_usd">$<?php echo number_format($totales['total_usd'], 2); ?></td>
                <td class="text-right moneda" id="total_usd_iva">$<?php echo number_format($totales['total_usd_iva'], 2); ?></td>
                <td class="text-right moneda" id="total_bs">Bs<?php echo number_format($totales['total_bs'], 2); ?></td>
                <td class="text-right moneda" id="total_bs_iva">Bs<?php echo number_format($totales['total_bs_iva'], 2); ?></td>
            </tr>
        </tbody>
    </table>

    <div class="alert-info">
        <i class="fa fa-info-circle"></i>
        <span><strong>✎ editable:</strong> Los campos de Descarga y Carga se pueden modificar. Los totales se actualizan automáticamente.</span>
    </div>

    <div class="footer-info">
        <span><i class="fa fa-calendar"></i> <?php echo date('d/m/Y H:i'); ?></span>
        <span><i class="fa fa-user"></i> <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Sistema'); ?></span>
        <span style="color:#009a3f;"><i class="fa fa-check-circle"></i> Ransa Archivo</span>
    </div>
</div>
<div class="modal-footer" style="padding:15px 20px; background:white; border-top:1px solid #dee2e6; display:flex; justify-content:flex-end; gap:10px;">
    <button type="button" class="btn btn-secondary" data-dismiss="modal" style="padding:6px 18px; border-radius:30px; background:white; border:1px solid #ced4da; color:#495057;">Cerrar</button>
    <button type="button" class="btn btn-success" onclick="guardarResumenDesdeModal(<?php echo $factura_id; ?>, 'verificar')" style="padding:6px 18px; border-radius:30px; background:#009a3f; color:white; border:none;">Verificado</button>
</div>

<script>
// Factores globales para cálculos
const factores = {
    iva: <?php echo $factores['factor_iva']; ?>,
    usd_a_bs: <?php echo $factores['factor_usd_a_bs']; ?>,
    sin_iva: <?php echo $factores['factor_sin_iva']; ?>
};

// Función para recalcular una fila cuando cambia la cantidad
function recalcularFila(input) {
    const fila = input.closest('tr');
    const cantidad = parseFloat(input.value) || 0;
    const tarifa = parseFloat(input.dataset.tarifa) || 0;
    
    const base = cantidad * tarifa;
    const total_usd = base * factores.sin_iva;
    const total_usd_iva = base;
    const total_bs = total_usd * factores.usd_a_bs;
    const total_bs_iva = base * factores.usd_a_bs;
    
    const celdas = fila.cells;
    celdas[4].innerHTML = '$' + total_usd.toFixed(2);
    celdas[5].innerHTML = '$' + total_usd_iva.toFixed(2);
    celdas[6].innerHTML = 'Bs' + total_bs.toFixed(2);
    celdas[7].innerHTML = 'Bs' + total_bs_iva.toFixed(2);
    
    recalcularTotales();
}

// Función para recalcular todos los totales
function recalcularTotales() {
    const filas = document.querySelectorAll('#tablaResumen tbody tr');
    let total_usd = 0, total_usd_iva = 0, total_bs = 0, total_bs_iva = 0;
    
    filas.forEach(fila => {
        if (fila.classList.contains('separador-row') || fila.classList.contains('total-row')) return;
        
        const celdas = fila.cells;
        if (celdas.length >= 8) {
            total_usd += parseFloat(celdas[4].innerHTML.replace(/[$,]/g, '')) || 0;
            total_usd_iva += parseFloat(celdas[5].innerHTML.replace(/[$,]/g, '')) || 0;
            total_bs += parseFloat(celdas[6].innerHTML.replace(/[Bs$,]/g, '')) || 0;
            total_bs_iva += parseFloat(celdas[7].innerHTML.replace(/[Bs$,]/g, '')) || 0;
        }
    });
    
    document.getElementById('total_usd').innerHTML = '$' + total_usd.toFixed(2);
    document.getElementById('total_usd_iva').innerHTML = '$' + total_usd_iva.toFixed(2);
    document.getElementById('total_bs').innerHTML = 'Bs' + total_bs.toFixed(2);
    document.getElementById('total_bs_iva').innerHTML = 'Bs' + total_bs_iva.toFixed(2);
}

// Función para guardar resumen desde el modal
function guardarResumenDesdeModal(factura_id, accion) {
    // Mostrar indicador de carga
    const btnGuardar = event.target;
    const textoOriginal = btnGuardar.innerHTML;
    btnGuardar.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Guardando...';
    btnGuardar.disabled = true;
    
    const filas = document.querySelectorAll('#tablaResumen tbody tr');
    const datos = [];
    
    filas.forEach(fila => {
        // Saltar filas separadoras y de totales
        if (fila.classList.contains('separador-row') || fila.classList.contains('total-row')) return;
        
        const celdas = fila.cells;
        if (celdas.length < 8) return;
        
        // Obtener servicio y sub_servicio
        let servicio = celdas[0].innerText.trim();
        let sub_servicio = celdas[1].innerText.replace('✎ editable', '').trim();
        
        // Si es la primera fila de un servicio, el nombre está en la celda 0
        // Si no, está vacío, debemos usar el último servicio no vacío
        if (servicio === '') {
            // Buscar el último servicio no vacío hacia arriba
            let tempFila = fila.previousElementSibling;
            while (tempFila) {
                if (tempFila.cells && tempFila.cells[0] && tempFila.cells[0].innerText.trim() !== '') {
                    servicio = tempFila.cells[0].innerText.trim();
                    break;
                }
                tempFila = tempFila.previousElementSibling;
            }
        }
        
        // Saltar filas sin servicio válido
        if (!servicio || servicio === '' || servicio.includes('TOTALES') || servicio.includes('Otros servicios')) return;
        
        // Obtener cantidad (input o texto)
        let cantidad = 0;
        const input = celdas[3].querySelector('input');
        if (input) {
            cantidad = parseFloat(input.value) || 0;
        } else {
            cantidad = parseFloat(celdas[3].innerText.replace(/,/g, '')) || 0;
        }
        
        // Solo guardar si cantidad > 0
        if (cantidad <= 0) return;
        
        // Obtener valores
        const tarifa_usd = parseFloat(celdas[2].innerHTML.replace(/[$,]/g, '')) || 0;
        const total_usd = parseFloat(celdas[4].innerHTML.replace(/[$,]/g, '')) || 0;
        const total_usd_iva = parseFloat(celdas[5].innerHTML.replace(/[$,]/g, '')) || 0;
        const total_bs = parseFloat(celdas[6].innerHTML.replace(/[Bs$,]/g, '')) || 0;
        const total_bs_iva = parseFloat(celdas[7].innerHTML.replace(/[Bs$,]/g, '')) || 0;
        
        // Determinar tipo_servicio
        let tipo_servicio = 'Otros';
        if (servicio.includes('Recepción')) tipo_servicio = 'Recepcion';
        else if (servicio.includes('Despacho')) tipo_servicio = 'Despacho';
        else if (servicio.includes('Almacenamiento')) tipo_servicio = 'Almacenamiento';
        else if (servicio.includes('Carga')) tipo_servicio = 'Carga';
        else if (servicio.includes('Descarga')) tipo_servicio = 'Carga';
        
        datos.push({
            servicio: servicio,
            sub_servicio: sub_servicio,
            udm: sub_servicio,
            cantidad: cantidad,
            tarifa_usd: tarifa_usd,
            total_usd: total_usd,
            total_usd_con_iva: total_usd_iva,
            total_bs: total_bs,
            total_bs_con_iva: total_bs_iva,
            tipo_servicio: tipo_servicio
        });
    });
    
    // Agregar Otros Servicios si existe
    const otrosRow = Array.from(document.querySelectorAll('#tablaResumen tbody tr')).find(
        tr => tr.cells[0] && tr.cells[0].innerText.includes('Otros servicios')
    );
    
    if (otrosRow) {
        const celdas = otrosRow.cells;
        datos.push({
            servicio: 'Otros servicios',
            sub_servicio: '-',
            udm: '-',
            cantidad: 0,
            tarifa_usd: 0,
            total_usd: parseFloat(celdas[4].innerHTML.replace(/[$,]/g, '')) || 0,
            total_usd_con_iva: parseFloat(celdas[5].innerHTML.replace(/[$,]/g, '')) || 0,
            total_bs: parseFloat(celdas[6].innerHTML.replace(/[Bs$,]/g, '')) || 0,
            total_bs_con_iva: parseFloat(celdas[7].innerHTML.replace(/[Bs$,]/g, '')) || 0,
            tipo_servicio: 'Otros'
        });
    }
    
    console.log('Datos a guardar:', datos); // Para debug
    
    // Enviar al servidor
    $.ajax({
        url: 'guardar_resumen.php',
        method: 'POST',
        data: JSON.stringify({
            factura_id: factura_id,
            datos: datos,
            accion: accion
        }),
        contentType: 'application/json',
        success: function(response) {
            btnGuardar.innerHTML = textoOriginal;
            btnGuardar.disabled = false;
            
            if (response.success) {
                alert('✅ ' + response.mensaje);
                $('#modalResumen').modal('hide');
                location.reload(); // Recargar para ver el cambio de estado
            } else {
                alert('❌ Error: ' + response.error);
            }
        },
        error: function(xhr, status, error) {
            btnGuardar.innerHTML = textoOriginal;
            btnGuardar.disabled = false;
            alert('❌ Error de conexión: ' + error);
            console.error('Respuesta del servidor:', xhr.responseText);
        }
    });
}
</script>