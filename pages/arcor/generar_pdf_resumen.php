<?php
/**
 * Genera PDF con el resumen completo de la factura
 * VERSIÓN MEJORADA:
 * - Botón de descargar PDF (descarga directa)
 * - Botón "Aprobar y Enviar"
 * - Botón cerrar
 * - Los botones NO aparecen en el PDF
 */

session_start();
require_once '../../conexion/config.php';

// Verificar sesión
if (!isset($_SESSION['user_id'])) {
    die('Acceso no autorizado');
}

$factura_id = $_GET['factura_id'] ?? 0;

if (!$factura_id) {
    die('ID de factura no proporcionado');
}

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
    }
    
    // ============================================
    // 4. OBTENER DATOS DE RESUMEN (para Descarga y Carga)
    // ============================================
    $resumen_data = [];
    $sql_resumen = "SELECT * FROM DPL.FacBol.facturas_resumen 
                    WHERE factura_id = ? AND servicio IN ('Descarga', 'Carga')";
    $stmt = $conn->prepare($sql_resumen);
    $stmt->execute([$factura_id]);
    while ($row = $stmt->fetch()) {
        $key = $row['servicio'] . '|' . $row['sub_servicio'];
        $resumen_data[$key] = [
            'cantidad' => floatval($row['cantidad']),
            'total_usd' => floatval($row['total_usd']),
            'total_usd_iva' => floatval($row['total_usd_con_iva']),
            'total_bs' => floatval($row['total_bs']),
            'total_bs_iva' => floatval($row['total_bs_con_iva'])
        ];
    }
    
    // ============================================
    // 5. OBTENER DATOS DE RECEPCIÓN
    // ============================================
    $recepcion_data = [
        'Pallet' => 0,
        'Caja/Bulto' => 0,
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
            'Caja/Bulto' => intval($row['total_cajas'] ?? 0),
            'Unidades' => intval($row['total_unidades'] ?? 0)
        ];
    }
    
    // ============================================
    // 6. OBTENER DATOS DE DESPACHO
    // ============================================
    $despacho_data = [
        'Pallet' => 0,
        'Caja/Bulto' => 0,
        'Unidades' => 0
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
            'Caja/Bulto' => intval($row['total_cajas'] ?? 0),
            'Unidades' => intval($row['total_unidades'] ?? 0)
        ];
    }
    
    // ============================================
    // 7. OBTENER DATOS DE ALMACENAMIENTO
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
    // 8. OBTENER TOTAL DE OTROS SERVICIOS
    // ============================================
    $total_otros_servicios = 0;
    if ($factura['paquete_completado']) {
        $sql_otros = "SELECT SUM(total) as total 
                      FROM [FacBol].[facturas_otros_servicios] 
                      WHERE factura_id = ?";
        $stmt = $conn->prepare($sql_otros);
        $stmt->execute([$factura_id]);
        $total_otros_servicios = floatval($stmt->fetchColumn() ?? 0);
    }
    
    // ============================================
    // 9. ESTRUCTURA DE SERVICIOS
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
            'usar_resumen' => true
        ],
        'Almacenamiento' => [
            'udms' => ['Posiciones rack', 'Posiciones rack (pallet adicional)'],
            'tipo' => 'Almacenamiento',
            'cantidades' => $almacenamiento_data
        ],
        'Despacho OUT' => [
            'udms' => ['Pallet', 'Caja/Bulto', 'Unidades'],
            'tipo' => 'Despacho',
            'cantidades' => $despacho_data
        ],
        'Carga' => [
            'udms' => ['Pallet', 'Caja/Bulto'],
            'tipo' => 'Carga',
            'usar_resumen' => true
        ]
    ];
    
    $filas = [];
    
    // ============================================
    // 10. GENERAR FILAS
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
                'total_bs_iva' => 0
            ];
            
            // Buscar tarifa
            $key_tarifa = $nombre_servicio . '|' . $udm;
            if (isset($tarifas[$key_tarifa])) {
                $fila['tarifa_usd'] = $tarifas[$key_tarifa]['tarifa_usd'];
            }
            
            // Si es un servicio que usa resumen (Descarga o Carga)
            if (isset($config['usar_resumen']) && $config['usar_resumen']) {
                $key_resumen = $nombre_servicio . '|' . $udm;
                if (isset($resumen_data[$key_resumen])) {
                    $fila['cantidad'] = $resumen_data[$key_resumen]['cantidad'];
                    $fila['total_usd'] = $resumen_data[$key_resumen]['total_usd'];
                    $fila['total_usd_iva'] = $resumen_data[$key_resumen]['total_usd_iva'];
                    $fila['total_bs'] = $resumen_data[$key_resumen]['total_bs'];
                    $fila['total_bs_iva'] = $resumen_data[$key_resumen]['total_bs_iva'];
                }
            } else {
                // Para servicios que usan datos originales
                if (isset($config['cantidades'][$udm])) {
                    $fila['cantidad'] = $config['cantidades'][$udm];
                    
                    // Calcular totales si hay tarifa y cantidad
                    if ($fila['tarifa_usd'] > 0 && $fila['cantidad'] > 0) {
                        $base = $fila['cantidad'] * $fila['tarifa_usd'];
                        $fila['total_usd'] = round($base * $factores['factor_sin_iva'], 2);
                        $fila['total_usd_iva'] = round($base, 2);
                        $fila['total_bs'] = round($fila['total_usd'] * $factores['factor_usd_a_bs'], 2);
                        $fila['total_bs_iva'] = round($base * $factores['factor_usd_a_bs'], 2);
                    }
                }
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
        'es_otros' => true
    ];
    
} catch (Exception $e) {
    die('Error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resumen Factura FAC-<?php echo str_pad($factura_id, 6, '0', STR_PAD_LEFT); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <!-- Librerías para generar PDF -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
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
        
        .logo-container {
            background: white;
            border-radius: 15px;
            padding: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .logo {
            width: 80px;
            height: 80px;
            object-fit: contain;
            border-radius: 10px;
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
        
        .info-item strong {
            color: #333;
            margin-right: 5px;
        }
        
        /* Barra de acciones - SEPARADA del contenido del PDF */
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
            border: 1px solid rgba(0,154,63,0.2);
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
            text-decoration: none;
        }
        
        .btn-download {
            background: linear-gradient(135deg, #009a3f, #007a32);
            color: white;
            box-shadow: 0 4px 15px rgba(0,154,63,0.3);
        }
        
        .btn-download:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,154,63,0.4);
        }
        
        .btn-approve {
            background: linear-gradient(135deg, #ffc107, #e0a800);
            color: #856404;
            box-shadow: 0 4px 15px rgba(255,193,7,0.3);
        }
        
        .btn-approve:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(255,193,7,0.4);
        }
        
        .btn-secondary {
            background: white;
            color: #666;
            border: 1px solid #ddd;
        }
        
        .btn-secondary:hover {
            background: #f8f9fa;
            border-color: #009a3f;
        }
        
        /* Contenido del PDF - SIN BOTONES */
        #contenido-pdf {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        /* Tabla */
        .table-container {
            padding: 5px;
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            table-layout: fixed;
        }
        
        th {
            background: linear-gradient(135deg, #009a3f, #007a32);
            color: white;
            padding: 15px 8px;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }
        
        th:first-child {
            border-radius: 15px 0 0 0;
        }
        
        th:last-child {
            border-radius: 0 15px 0 0;
        }
        
        td {
            padding: 12px 8px;
            border-bottom: 1px solid #e9ecef;
            color: #444;
        }
        
        tbody tr:nth-child(even) {
            background: #fafafa;
        }
        
        tbody tr:hover {
            background: #f0f9f0;
        }
        
        .text-right {
            text-align: right;
        }
        
        .text-center {
            text-align: center;
        }
        
        .moneda {
            font-family: 'Roboto Mono', monospace;
        }
        
        .total-row {
            background: linear-gradient(135deg, #e8f5e9, #c8e6c9) !important;
            font-weight: 600;
        }
        
        .total-row td {
            border-top: 2px solid #009a3f;
            border-bottom: none;
            color: #1e7e34;
        }
        
        .separador-row td {
            background: #f1f5f9;
            height: 8px;
            padding: 0;
            border-bottom: none;
        }
        
        .otros-servicios-row {
            background: #f1f9ff;
            font-style: italic;
        }
        
        /* Footer */
        .footer {
            background: white;
            padding: 20px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            border-top: 3px solid #009a3f;
        }
        
        .footer-info {
            display: flex;
            gap: 20px;
            color: #666;
            font-size: 12px;
        }
        
        .footer-info i {
            color: #009a3f;
            margin-right: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- BARRA DE ACCIONES (FUERA DEL PDF) -->
        <div class="action-bar">
            <button onclick="descargarPDF()" class="btn btn-download">
                <i class="fa fa-download"></i> Descargar PDF
            </button>
            <button onclick="aprobarYEnviar(<?php echo $factura_id; ?>)" class="btn btn-approve">
                <i class="fa fa-check-circle"></i> Aprobar y Enviar
            </button>
            <button onclick="window.close()" class="btn btn-secondary">
                <i class="fa fa-times"></i> Cerrar
            </button>
        </div>

        <!-- CONTENIDO DEL PDF (SIN BOTONES) -->
        <div id="contenido-pdf">
            <!-- HEADER -->
            <div class="header-card">
                <div class="logo-container">
                    <?php 
                    $logo = !empty($factura['logo_png']) ? '../../img/' . $factura['logo_png'] : '../../img/arcor.png';
                    ?>
                    <img src="<?php echo $logo; ?>" class="logo" alt="Logo">
                </div>
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
                            <span><strong>NIT:</strong> <?php echo htmlspecialchars($factura['nit'] ?? ''); ?></span>
                        </div>
                        <div class="info-item">
                            <i class="fa fa-calendar"></i>
                            <span><strong>Fecha emisión:</strong> <?php echo date('d/m/Y H:i', strtotime($factura['fecha_creacion'])); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TABLA DE RESUMEN -->
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 15%;">SERVICIO</th>
                            <th style="width: 12%;">UDM</th>
                            <th style="width: 9%;">TARIFA USD</th>
                            <th style="width: 8%;">CANTIDAD</th>
                            <th style="width: 11%;">TOTAL USD</th>
                            <th style="width: 11%;">USD +16%</th>
                            <th style="width: 11%;">TOTAL BS</th>
                            <th style="width: 11%;">BS +16%</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $totales = [
                            'total_usd' => 0,
                            'total_usd_iva' => 0,
                            'total_bs' => 0,
                            'total_bs_iva' => 0
                        ];
                        
                        foreach ($filas as $fila): 
                            if (isset($fila['separador'])): ?>
                                <tr class="separador-row">
                                    <td colspan="8"></td>
                                </tr>
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
                                    <td class="text-right moneda">$ <?php echo number_format($fila['total_usd'], 2); ?></td>
                                    <td class="text-right moneda">$ <?php echo number_format($fila['total_usd_iva'], 2); ?></td>
                                    <td class="text-right moneda">Bs <?php echo number_format($fila['total_bs'], 2); ?></td>
                                    <td class="text-right moneda">Bs <?php echo number_format($fila['total_bs_iva'], 2); ?></td>
                                </tr>
                            <?php else: 
                                $totales['total_usd'] += $fila['total_usd'];
                                $totales['total_usd_iva'] += $fila['total_usd_iva'];
                                $totales['total_bs'] += $fila['total_bs'];
                                $totales['total_bs_iva'] += $fila['total_bs_iva'];
                            ?>
                                <tr>
                                    <td><?php echo $fila['servicio']; ?></td>
                                    <td><?php echo $fila['udm']; ?></td>
                                    <td class="text-right moneda">$ <?php echo number_format($fila['tarifa_usd'], 2); ?></td>
                                    <td class="text-right"><?php echo number_format($fila['cantidad']); ?></td>
                                    <td class="text-right moneda">$ <?php echo number_format($fila['total_usd'], 2); ?></td>
                                    <td class="text-right moneda">$ <?php echo number_format($fila['total_usd_iva'], 2); ?></td>
                                    <td class="text-right moneda">Bs <?php echo number_format($fila['total_bs'], 2); ?></td>
                                    <td class="text-right moneda">Bs <?php echo number_format($fila['total_bs_iva'], 2); ?></td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        
                        <!-- FILA DE TOTALES -->
                        <tr class="total-row">
                            <td colspan="4" class="text-right"><strong>TOTALES</strong></td>
                            <td class="text-right moneda"><strong>$ <?php echo number_format($totales['total_usd'], 2); ?></strong></td>
                            <td class="text-right moneda"><strong>$ <?php echo number_format($totales['total_usd_iva'], 2); ?></strong></td>
                            <td class="text-right moneda"><strong>Bs <?php echo number_format($totales['total_bs'], 2); ?></strong></td>
                            <td class="text-right moneda"><strong>Bs <?php echo number_format($totales['total_bs_iva'], 2); ?></strong></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- FOOTER -->
            <div class="footer">
                <div class="footer-info">
                    <span><i class="fa fa-calendar"></i> Fecha impresión: <?php echo date('d/m/Y H:i'); ?></span>
                    <span><i class="fa fa-user"></i> Usuario: <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Sistema'); ?></span>
                </div>
                <div style="color: #009a3f; font-weight: 500;">
                    <i class="fa fa-check-circle"></i> Ransa Archivo - Bolivia v2.0
                </div>
            </div>
        </div>
    </div>

    <script>
        // Función para descargar PDF directamente (sin diálogo de impresión)
        function descargarPDF() {
            const elemento = document.getElementById('contenido-pdf');
            
            // Opciones para el PDF
            const opciones = {
                margin:        [0.5, 0.5, 0.5, 0.5], // superior, izquierdo, inferior, derecho (en cm)
                filename:      'Resumen_Factura_FAC-<?php echo str_pad($factura_id, 6, '0', STR_PAD_LEFT); ?>.pdf',
                image:         { type: 'jpeg', quality: 0.98 },
                html2canvas:   { scale: 2, letterRendering: true, useCORS: true, logging: false },
                jsPDF:         { unit: 'cm', format: 'a4', orientation: 'landscape' } // Orientación horizontal
            };
            
            // Generar y descargar PDF
            html2pdf().set(opciones).from(elemento).save();
        }
        
        // Función para aprobar y enviar
        function aprobarYEnviar(factura_id) {
            if (confirm('¿Está seguro de aprobar y enviar esta factura?')) {
                // Cambiar el estado a "APROBADO"
                fetch('cambiar_estado.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        factura_id: factura_id,
                        estado: 'APROBADO',
                        observacion: 'Aprobado desde vista de PDF'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('✅ Factura aprobada y enviada correctamente');
                        window.close();
                    } else {
                        alert('❌ Error: ' + data.error);
                    }
                })
                .catch(error => {
                    alert('❌ Error al aprobar la factura');
                    console.error('Error:', error);
                });
            }
        }
    </script>
</body>
</html>