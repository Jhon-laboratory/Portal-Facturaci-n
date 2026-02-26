<?php
/**
 * Genera PDF con los detalles de un módulo específico
 * VERSIÓN COMPLETA - Incluye Otros Servicios
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
    $sql_factura = "SELECT fc.*, c.nombre_comercial, c.nit, c.logo_png 
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
            break;
        case 'despacho':
            $sql_datos = "SELECT * FROM " . TABLA_DESPACHO . " WHERE factura_id = ? ORDER BY ORDERKEY, SKU";
            break;
        case 'paquete':
        case 'otros':
        case 'otros_servicios':
            $sql_datos = "SELECT * FROM [FacBol].[facturas_otros_servicios] WHERE factura_id = ? ORDER BY id";
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
    }
    
} catch (Exception $e) {
    die('Error: ' . $e->getMessage());
}

// Determinar el título según el módulo
$titulo_modulo = '';
switch ($modulo) {
    case 'recepcion': $titulo_modulo = 'RECEPCIONES'; break;
    case 'despacho': $titulo_modulo = 'DESPACHOS'; break;
    case 'paquete':
    case 'otros':
    case 'otros_servicios': $titulo_modulo = 'OTROS SERVICIOS'; break;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?php echo ucfirst($modulo); ?> - Factura <?php echo $factura['id']; ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }
        .header {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 30px;
            border-bottom: 2px solid #009a3f;
            padding-bottom: 20px;
        }
        .logo {
            width: 100px;
            height: 100px;
            object-fit: contain;
        }
        .info {
            flex: 1;
        }
        .info h1 {
            margin: 0;
            color: #009a3f;
        }
        .info p {
            margin: 5px 0;
            color: #666;
        }
        .resumen {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            gap: 20px;
        }
        .resumen-item {
            flex: 1;
            text-align: center;
        }
        .resumen-item .label {
            font-size: 12px;
            color: #666;
        }
        .resumen-item .value {
            font-size: 20px;
            font-weight: bold;
            color: #009a3f;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th {
            background: #009a3f;
            color: white;
            padding: 10px;
            font-size: 12px;
        }
        td {
            padding: 8px;
            border-bottom: 1px solid #ddd;
            font-size: 11px;
        }
        .text-right {
            text-align: right;
        }
        .total-row {
            background: #e8f5e9;
            font-weight: bold;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 10px;
            color: #999;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }
        @media print {
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <?php 
        $logo = !empty($factura['logo_png']) ? '../../img/' . $factura['logo_png'] : '../../img/arcor.png';
        ?>
        <img src="<?php echo $logo; ?>" class="logo">
        <div class="info">
            <h1><?php echo $titulo_modulo; ?></h1>
            <p><strong>Factura N°:</strong> FAC-<?php echo str_pad($factura_id, 6, '0', STR_PAD_LEFT); ?></p>
            <p><strong>Cliente:</strong> <?php echo $factura['nombre_comercial']; ?> (NIT: <?php echo $factura['nit']; ?>)</p>
            <p><strong>Fecha:</strong> <?php echo date('d/m/Y H:i', strtotime($factura['fecha_creacion'])); ?></p>
        </div>
    </div>

    <?php if ($modulo == 'recepcion' || $modulo == 'despacho'): ?>
    <div class="resumen">
        <div class="resumen-item">
            <div class="label">Total Pallets</div>
            <div class="value"><?php echo number_format($total_pallets, 0, ',', '.'); ?></div>
        </div>
        <div class="resumen-item">
            <div class="label">Total Cajas</div>
            <div class="value"><?php echo number_format($total_cajas, 0, ',', '.'); ?></div>
        </div>
        <div class="resumen-item">
            <div class="label">Total Unidades</div>
            <div class="value"><?php echo number_format($total_unidades, 0, ',', '.'); ?></div>
        </div>
    </div>
    <?php endif; ?>

    <table>
        <thead>
            <tr>
                <?php if ($modulo == 'recepcion'): ?>
                    <th>ASN/Recepción</th>
                    <th>Propietario</th>
                    <th>Recepción externa</th>
                    <th>Estatus</th>
                    <th>Tipo</th>
                    <th>Fecha recepción</th>
                    <th>Artículo</th>
                    <th class="text-right">Cant. UN</th>
                    <th class="text-right">Cant. CJ</th>
                    <th class="text-right">Cant. PL</th>
                
                <?php elseif ($modulo == 'despacho'): ?>
                    <th>N° Orden</th>
                    <th>Propietario</th>
                    <th>Orden Externa</th>
                    <th>Estatus</th>
                    <th>Tipo</th>
                    <th>Fecha Despacho</th>
                    <th>Artículo</th>
                    <th class="text-right">Cant. UN</th>
                    <th class="text-right">Cant. CJ</th>
                    <th class="text-right">Cant. PL</th>
                
                <?php elseif ($modulo == 'paquete' || $modulo == 'otros' || $modulo == 'otros_servicios'): ?>
                    <th>Servicio</th>
                    <th class="text-right">Tarifa</th>
                    <th class="text-right">Cantidad</th>
                    <th class="text-right">Total</th>
                    <th>Tipo</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($datos)): ?>
                <tr>
                    <td colspan="10" class="text-center">No hay datos disponibles</td>
                </tr>
            <?php else: ?>
                <?php foreach ($datos as $row): ?>
                <tr>
                    <?php if ($modulo == 'recepcion'): ?>
                        <td><?php echo htmlspecialchars($row['receiptkey'] ?? $row['RECEIPTKEY'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($row['storerkey'] ?? $row['STORERKEY'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($row['external_receiptkey'] ?? $row['EXTERNRECEIPTKEY'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($row['status'] ?? $row['STATUS'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($row['type'] ?? $row['TYPE'] ?? ''); ?></td>
                        <td>
                            <?php 
                            $fecha = $row['fecha_recepcion'] ?? $row['FECHA_RECEPCION'] ?? null;
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
                        <td><?php echo htmlspecialchars($row['STATUS'] ?? $row['status'] ?? ''); ?></td>
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
                        <td class="text-right"><?php echo number_format($row['tarifa'] ?? 0, 2, ',', '.'); ?></td>
                        <td class="text-right"><?php echo number_format($row['cantidad'] ?? 0, 2, ',', '.'); ?></td>
                        <td class="text-right"><?php echo number_format($row['total'] ?? 0, 2, ',', '.'); ?></td>
                        <td><?php echo $row['es_personalizado'] ? 'Personalizado' : 'Predefinido'; ?></td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
                
                <?php if ($modulo == 'recepcion' || $modulo == 'despacho'): ?>
                <tr class="total-row">
                    <td colspan="7" class="text-right"><strong>TOTAL</strong></td>
                    <td class="text-right"><strong><?php echo number_format($total_unidades, 0, ',', '.'); ?></strong></td>
                    <td class="text-right"><strong><?php echo number_format($total_cajas, 0, ',', '.'); ?></strong></td>
                    <td class="text-right"><strong><?php echo number_format($total_pallets, 0, ',', '.'); ?></strong></td>
                </tr>
                <?php elseif ($modulo == 'paquete' || $modulo == 'otros' || $modulo == 'otros_servicios'): ?>
                <tr class="total-row">
                    <td colspan="3" class="text-right"><strong>TOTAL</strong></td>
                    <td class="text-right"><strong><?php echo number_format($total_servicios, 2, ',', '.'); ?></strong></td>
                    <td></td>
                </tr>
                <?php endif; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="footer">
        <p>Documento generado por Sistema Ransa Archivo - <?php echo date('d/m/Y H:i'); ?></p>
    </div>

    <div class="no-print" style="text-align: center; margin-top: 20px;">
        <button onclick="window.print()" style="background: #009a3f; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer;">
            <i class="fa fa-print"></i> Imprimir / Guardar PDF
        </button>
        <button onclick="window.close()" style="background: #6c757d; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; margin-left: 10px;">
            <i class="fa fa-times"></i> Cerrar
        </button>
    </div>
</body>
</html>