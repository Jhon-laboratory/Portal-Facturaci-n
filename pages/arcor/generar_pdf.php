<?php
/**
 * Genera PDF con los detalles de un módulo específico
 * VERSIÓN COMPLETA - Incluye Ocupabilidad y diseño mejorado
 */

session_start();
require_once '../../conexion/config.php';

// Verificar sesión
if (!isset($_SESSION['user_id'])) {
    die('Acceso no autorizado');
}

$factura_id = $_GET['factura_id'] ?? 0;
$modulo = $_GET['modulo'] ?? '';

if (!$factura_id || !$modulo) {
    die('Parámetros incompletos');
}

try {
    $conn = getDBConnection();
    
    // Obtener información de la factura
    $sql_factura = "SELECT fc.*, c.nombre_comercial, c.nit, c.logo_png, c.direccion, c.telefono, c.email
                    FROM " . TABLA_FACTURAS . " fc
                    INNER JOIN [FacBol].[clientes] c ON fc.cliente_codigo = c.codigo_cliente
                    WHERE fc.id = ?";
    $stmt = $conn->prepare($sql_factura);
    $stmt->execute([$factura_id]);
    $factura = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$factura) {
        die('Factura no encontrada');
    }
    
    // Obtener datos según el módulo
    switch ($modulo) {
        case 'recepcion':
            $sql_datos = "SELECT * FROM " . TABLA_RECEPCION . " WHERE factura_id = ? ORDER BY receiptkey, sku";
            $titulo_modulo = 'RECEPCIONES';
            break;
        case 'despacho':
            $sql_datos = "SELECT * FROM " . TABLA_DESPACHO . " WHERE factura_id = ? ORDER BY ORDERKEY, SKU";
            $titulo_modulo = 'DESPACHOS';
            break;
        case 'paquete':
        case 'otros':
        case 'otros_servicios':
            $sql_datos = "SELECT * FROM [FacBol].[facturas_otros_servicios] WHERE factura_id = ? ORDER BY id";
            $titulo_modulo = 'OTROS SERVICIOS';
            break;
        case 'almacen':
        case 'ocupabilidad':
            $sql_datos = "SELECT * FROM FacBol.ocupabilidad_ubicaciones WHERE factura_id = ? ORDER BY id";
            $titulo_modulo = 'OCUPABILIDAD - POSICIONES DE RACK';
            break;
        default:
            die('Módulo no válido');
    }
    
    $stmt = $conn->prepare($sql_datos);
    $stmt->execute([$factura_id]);
    $datos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calcular totales según el módulo
    $total_unidades = 0;
    $total_cajas = 0;
    $total_pallets = 0;
    $total_servicios = 0;
    $total_ubicaciones = 0;
    
    if ($modulo == 'recepcion' || $modulo == 'despacho') {
        foreach ($datos as $row) {
            $total_unidades += $row['UNIDADES'] ?? $row['unidades'] ?? 0;
            $total_cajas += $row['CAJAS'] ?? $row['cajas'] ?? 0;
            $total_pallets += $row['PALLETS'] ?? $row['pallets'] ?? 0;
        }
    } elseif ($modulo == 'paquete' || $modulo == 'otros' || $modulo == 'otros_servicios') {
        foreach ($datos as $row) {
            $total_servicios += $row['total'] ?? 0;
        }
    } elseif ($modulo == 'almacen' || $modulo == 'ocupabilidad') {
        foreach ($datos as $row) {
            $total_ubicaciones += $row['cantidad'] ?? 0;
        }
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
    <title><?php echo ucfirst($modulo); ?> - Factura <?php echo $factura['id']; ?></title>
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
        
        /* Header con gradiente */
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
            width: 100px;
            height: 100px;
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
            letter-spacing: 1px;
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
            font-size: 16px;
        }
        
        .info-item strong {
            color: #333;
            margin-right: 5px;
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
        
        /* Tarjetas de resumen */
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .summary-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            gap: 15px;
            transition: transform 0.3s ease;
            border: 1px solid #e0e0e0;
        }
        
        .summary-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0,154,63,0.15);
        }
        
        .summary-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            background: linear-gradient(135deg, #e8f5e9, #c8e6c9);
            color: #009a3f;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        
        .summary-content {
            flex: 1;
        }
        
        .summary-content .label {
            font-size: 13px;
            color: #888;
            margin-bottom: 5px;
            display: block;
        }
        
        .summary-content .value {
            font-size: 24px;
            font-weight: 600;
            color: #333;
            line-height: 1.2;
        }
        
        .summary-content .sub-value {
            font-size: 12px;
            color: #666;
            margin-top: 3px;
        }
        
        /* Tabla mejorada */
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
            padding: 15px 12px;
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
            padding: 12px 10px;
            border-bottom: 1px solid #e9ecef;
            color: #444;
            white-space: nowrap;
        }
        
        td:first-child {
            padding-left: 15px;
        }
        
        td:last-child {
            padding-right: 15px;
        }
        
        tbody tr:hover {
            background: #f8f9fa;
        }
        
        tbody tr:nth-child(even) {
            background: #fafafa;
        }
        
        tbody tr:nth-child(even):hover {
            background: #f0f9f0;
        }
        
        .text-right {
            text-align: right;
        }
        
        .text-center {
            text-align: center;
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
        
        /* Badges */
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 500;
        }
        
        .badge-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .badge-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        
        /* Footer */
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
        
        /* Versión impresión */
        @media print {
            body {
                background: white;
                padding: 0.5cm;
            }
            
            .action-bar {
                display: none;
            }
            
            .header-card {
                box-shadow: none;
                border: 1px solid #ddd;
                padding: 15px;
            }
            
            .summary-card {
                box-shadow: none;
                border: 1px solid #ddd;
            }
            
            .table-container {
                box-shadow: none;
                border: 1px solid #ddd;
            }
            
            th {
                background: #333 !important;
                color: white !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            .total-row {
                background: #f0f0f0 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            .footer {
                box-shadow: none;
                border: 1px solid #ddd;
            }
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .header-card {
                flex-direction: column;
                text-align: center;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .action-bar {
                justify-content: center;
            }
            
            .summary-cards {
                grid-template-columns: 1fr;
            }
            
            table {
                font-size: 11px;
            }
            
            td, th {
                padding: 8px 5px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- HEADER MEJORADO -->
        <div class="header-card">
            <div class="logo-container">
                <?php 
                $logo = !empty($factura['logo_png']) ? '../../img/' . $factura['logo_png'] : '../../img/arcor.png';
                ?>
                <img src="<?php echo $logo; ?>" class="logo">
            </div>
            <div class="info-container">
                <h1><i class="fa fa-file-text"></i> <?php echo $titulo_modulo; ?></h1>
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
                    <?php if (!empty($factura['direccion'])): ?>
                    <div class="info-item">
                        <i class="fa fa-map-marker"></i>
                        <span><?php echo htmlspecialchars($factura['direccion']); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- BARRA DE ACCIONES (AL INICIO) -->
        <div class="action-bar">
            <button onclick="window.print()" class="btn btn-primary">
                <i class="fa fa-print"></i> Imprimir / Guardar PDF
            </button>
            <button onclick="window.close()" class="btn btn-secondary">
                <i class="fa fa-times"></i> Cerrar
            </button>
        </div>

        <!-- TARJETAS DE RESUMEN -->
        <div class="summary-cards">
            <?php if ($modulo == 'recepcion' || $modulo == 'despacho'): ?>
                <div class="summary-card">
                    <div class="summary-icon"><i class="fa fa-cubes"></i></div>
                    <div class="summary-content">
                        <span class="label">Total Pallets</span>
                        <span class="value"><?php echo number_format($total_pallets, 0, ',', '.'); ?></span>
                    </div>
                </div>
                <div class="summary-card">
                    <div class="summary-icon"><i class="fa fa-cube"></i></div>
                    <div class="summary-content">
                        <span class="label">Total Cajas</span>
                        <span class="value"><?php echo number_format($total_cajas, 0, ',', '.'); ?></span>
                    </div>
                </div>
                <div class="summary-card">
                    <div class="summary-icon"><i class="fa fa-archive"></i></div>
                    <div class="summary-content">
                        <span class="label">Total Unidades</span>
                        <span class="value"><?php echo number_format($total_unidades, 0, ',', '.'); ?></span>
                    </div>
                </div>
                <div class="summary-card">
                    <div class="summary-icon"><i class="fa fa-list"></i></div>
                    <div class="summary-content">
                        <span class="label">Registros</span>
                        <span class="value"><?php echo number_format(count($datos), 0, ',', '.'); ?></span>
                    </div>
                </div>

            <?php elseif ($modulo == 'paquete' || $modulo == 'otros' || $modulo == 'otros_servicios'): ?>
                <div class="summary-card">
                    <div class="summary-icon"><i class="fa fa-calculator"></i></div>
                    <div class="summary-content">
                        <span class="label">Total Servicios</span>
                        <span class="value"><?php echo number_format(count($datos), 0, ',', '.'); ?></span>
                    </div>
                </div>
                <div class="summary-card">
                    <div class="summary-icon"><i class="fa fa-money"></i></div>
                    <div class="summary-content">
                        <span class="label">Monto Total</span>
                        <span class="value">Bs <?php echo number_format($total_servicios, 2, ',', '.'); ?></span>
                    </div>
                </div>

            <?php elseif ($modulo == 'almacen' || $modulo == 'ocupabilidad'): ?>
                <div class="summary-card">
                    <div class="summary-icon"><i class="fa fa-archive"></i></div>
                    <div class="summary-content">
                        <span class="label">Tipos de Ubicación</span>
                        <span class="value"><?php echo number_format(count($datos), 0, ',', '.'); ?></span>
                    </div>
                </div>
                <div class="summary-card">
                    <div class="summary-icon"><i class="fa fa-cubes"></i></div>
                    <div class="summary-content">
                        <span class="label">Total Posiciones</span>
                        <span class="value"><?php echo number_format($total_ubicaciones, 0, ',', '.'); ?></span>
                        <span class="sub-value">ubicaciones ocupadas</span>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- TABLA DE DATOS -->
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <?php if ($modulo == 'recepcion'): ?>
                            <th>ASN/Recepción</th>
                            <th>Propietario</th>
                            <th>Recepción Ext.</th>
                            <th>Estatus</th>
                            <th>Tipo</th>
                            <th>Fecha Recepción</th>
                            <th>Artículo</th>
                            <th class="text-right">Unidades</th>
                            <th class="text-right">Cajas</th>
                            <th class="text-right">Pallets</th>
                        
                        <?php elseif ($modulo == 'despacho'): ?>
                            <th>N° Orden</th>
                            <th>Propietario</th>
                            <th>Orden Externa</th>
                            <th>Estatus</th>
                            <th>Tipo</th>
                            <th>Fecha Despacho</th>
                            <th>Artículo</th>
                            <th class="text-right">Unidades</th>
                            <th class="text-right">Cajas</th>
                            <th class="text-right">Pallets</th>
                        
                        <?php elseif ($modulo == 'paquete' || $modulo == 'otros' || $modulo == 'otros_servicios'): ?>
                            <th>Servicio</th>
                            <th class="text-right">Tarifa</th>
                            <th class="text-right">Cantidad</th>
                            <th class="text-right">Total</th>
                            <th class="text-center">Tipo</th>
                        
                        <?php elseif ($modulo == 'almacen' || $modulo == 'ocupabilidad'): ?>
                            <th style="width: 40%;">Tipo de Ubicación</th>
                            <th class="text-right" style="width: 20%;">Cantidad</th>
                            <th class="text-right" style="width: 20%;">Total Posiciones</th>
                            <th class="text-center" style="width: 20%;">Fecha Registro</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($datos)): ?>
                        <tr>
                            <td colspan="10" class="text-center" style="padding: 50px;">
                                <i class="fa fa-info-circle" style="font-size: 40px; color: #ccc;"></i>
                                <p style="margin-top: 15px; color: #666;">No hay datos disponibles para este módulo</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($datos as $row): ?>
                        <tr>
                            <?php if ($modulo == 'recepcion'): ?>
                                <td><?php echo htmlspecialchars($row['receiptkey'] ?? $row['RECEIPTKEY'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($row['storerkey'] ?? $row['STORERKEY'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($row['external_receiptkey'] ?? $row['EXTERNRECEIPTKEY'] ?? ''); ?></td>
                                <td>
                                    <?php 
                                    $status = $row['status'] ?? $row['STATUS'] ?? '';
                                    $badgeClass = ($status == 'Completado' || $status == 'COMPLETADO') ? 'badge-success' : 'badge-info';
                                    echo '<span class="badge ' . $badgeClass . '">' . htmlspecialchars($status) . '</span>';
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars($row['type'] ?? $row['TYPE'] ?? ''); ?></td>
                                <td>
                                    <?php 
                                    $fecha = $row['fecha_recepcion'] ?? $row['FECHA_RECEPCION'] ?? $row['DATERECEIVED'] ?? null;
                                    echo $fecha ? date('d/m/Y', strtotime($fecha)) : '';
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars($row['sku'] ?? $row['SKU'] ?? ''); ?></td>
                                <td class="text-right"><?php echo number_format($row['unidades'] ?? $row['UNIDADES'] ?? 0, 0, ',', '.'); ?></td>
                                <td class="text-right"><?php echo number_format($row['cajas'] ?? $row['CAJAS'] ?? 0, 0, ',', '.'); ?></td>
                                <td class="text-right"><?php echo number_format($row['pallets'] ?? $row['PALLETS'] ?? 0, 0, ',', '.'); ?></td>
                            
                            <?php elseif ($modulo == 'despacho'): ?>
                                <td><?php echo htmlspecialchars($row['ORDERKEY'] ?? $row['orderkey'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($row['STORERKEY'] ?? $row['storerkey'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($row['EXTERNORDERKEY'] ?? $row['externorderkey'] ?? ''); ?></td>
                                <td>
                                    <?php 
                                    $status = $row['STATUS'] ?? $row['status'] ?? '';
                                    $badgeClass = ($status == 'Completado' || $status == 'COMPLETADO') ? 'badge-success' : 'badge-info';
                                    echo '<span class="badge ' . $badgeClass . '">' . htmlspecialchars($status) . '</span>';
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars($row['TYPE'] ?? $row['type'] ?? ''); ?></td>
                                <td>
                                    <?php 
                                    $fecha = $row['ADDDATE'] ?? $row['adddate'] ?? $row['fecha_despacho'] ?? null;
                                    echo $fecha ? date('d/m/Y', strtotime($fecha)) : '';
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars($row['SKU'] ?? $row['sku'] ?? ''); ?></td>
                                <td class="text-right"><?php echo number_format($row['UNIDADES'] ?? $row['unidades'] ?? 0, 0, ',', '.'); ?></td>
                                <td class="text-right"><?php echo number_format($row['CAJAS'] ?? $row['cajas'] ?? 0, 0, ',', '.'); ?></td>
                                <td class="text-right"><?php echo number_format($row['PALLETS'] ?? $row['pallets'] ?? 0, 0, ',', '.'); ?></td>
                            
                            <?php elseif ($modulo == 'paquete' || $modulo == 'otros' || $modulo == 'otros_servicios'): ?>
                                <td><?php echo htmlspecialchars($row['servicio_nombre'] ?? $row['servicio'] ?? ''); ?></td>
                                <td class="text-right">Bs <?php echo number_format($row['tarifa'] ?? 0, 2, ',', '.'); ?></td>
                                <td class="text-right"><?php echo number_format($row['cantidad'] ?? 0, 2, ',', '.'); ?></td>
                                <td class="text-right">Bs <?php echo number_format($row['total'] ?? 0, 2, ',', '.'); ?></td>
                                <td class="text-center">
                                    <?php if ($row['es_personalizado'] ?? false): ?>
                                        <span class="badge badge-info">Personalizado</span>
                                    <?php else: ?>
                                        <span class="badge badge-success">Predefinido</span>
                                    <?php endif; ?>
                                </td>
                            
                            <?php elseif ($modulo == 'almacen' || $modulo == 'ocupabilidad'): ?>
                                <td><strong><?php echo htmlspecialchars($row['tipo_ubicacion'] ?? ''); ?></strong></td>
                                <td class="text-right"><?php echo number_format($row['cantidad'] ?? 0, 0, ',', '.'); ?></td>
                                <td class="text-right"><?php echo number_format($row['total_ubicaciones'] ?? $row['cantidad'] ?? 0, 0, ',', '.'); ?></td>
                                <td class="text-center"><?php echo date('d/m/Y H:i', strtotime($row['fecha_creacion'] ?? 'now')); ?></td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                        
                        <!-- FILA DE TOTALES -->
                        <?php if ($modulo == 'recepcion' || $modulo == 'despacho'): ?>
                        <tr class="total-row">
                            <td colspan="7" class="text-right"><strong>TOTAL GENERAL</strong></td>
                            <td class="text-right"><strong><?php echo number_format($total_unidades, 0, ',', '.'); ?></strong></td>
                            <td class="text-right"><strong><?php echo number_format($total_cajas, 0, ',', '.'); ?></strong></td>
                            <td class="text-right"><strong><?php echo number_format($total_pallets, 0, ',', '.'); ?></strong></td>
                        </tr>
                        <?php elseif ($modulo == 'paquete' || $modulo == 'otros' || $modulo == 'otros_servicios'): ?>
                        <tr class="total-row">
                            <td colspan="3" class="text-right"><strong>TOTAL GENERAL</strong></td>
                            <td class="text-right"><strong>Bs <?php echo number_format($total_servicios, 2, ',', '.'); ?></strong></td>
                            <td></td>
                        </tr>
                        <?php elseif ($modulo == 'almacen' || $modulo == 'ocupabilidad'): ?>
                        <tr class="total-row">
                            <td class="text-right"><strong>TOTAL GENERAL</strong></td>
                            <td class="text-right"><strong><?php echo number_format($total_ubicaciones, 0, ',', '.'); ?></strong></td>
                            <td class="text-right"><strong><?php echo number_format($total_ubicaciones, 0, ',', '.'); ?></strong></td>
                            <td></td>
                        </tr>
                        <?php endif; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- FOOTER MEJORADO -->
        <div class="footer">
            <div class="footer-info">
                <span><i class="fa fa-calendar"></i> Fecha: <?php echo date('d/m/Y H:i'); ?></span>
                <span><i class="fa fa-user"></i> Usuario: <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Sistema'); ?></span>
            </div>
            <div style="color: #009a3f; font-weight: 500;">
                <i class="fa fa-check-circle"></i> Ransa Archivo - Bolivia v2.0
            </div>
        </div>
    </div>

    <script>
        // Función para imprimir (ya incluida en los botones)
        function printPDF() {
            window.print();
        }
    </script>
</body>
</html>