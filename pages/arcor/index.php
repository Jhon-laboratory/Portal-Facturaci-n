<?php
session_start();

// Incluir configuración
require_once '../../conexion/config.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit;
}

// Obtener el cliente de la URL
$codigo_cliente = isset($_GET['cliente']) ? $_GET['cliente'] : '';

if (empty($codigo_cliente)) {
    header("Location: ../../dashboard.php");
    exit;
}

// Obtener información del cliente
$cliente_info = [];
$facturas = [];
$stats = [
    'total_facturas' => 0,
    'facturas_mes' => 0,
    'monto_total' => 0,
    'archivos_pendientes' => 0
];

try {
    $conn = getDBConnection();
    
    // Obtener datos del cliente
    $query_cliente = "SELECT id, codigo_cliente, nombre_comercial, logo_png, direccion, telefono, email, nit 
                      FROM [FacBol].[clientes] 
                      WHERE codigo_cliente = :codigo";
    $stmt = $conn->prepare($query_cliente);
    $stmt->execute([':codigo' => $codigo_cliente]);
    $cliente_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$cliente_info) {
        error_log("Cliente no encontrado con código: " . $codigo_cliente);
        header("Location: ../../dashboard.php?msg=" . urlencode("Cliente no encontrado"));
        exit;
    }
    
    // Obtener facturas del cliente con sus estados
    $query_facturas = "SELECT 
                        fc.id,
                        fc.id as factura_id,
                        'FAC-' + RIGHT('00000' + CAST(fc.id AS VARCHAR), 6) as nro_factura,
                        fc.fecha_creacion as fecha_emision,
                        ISNULL(fc.recepcion_completado, 0) as recepcion_completado,
                        ISNULL(fc.despacho_completado, 0) as despacho_completado,
                        ISNULL(fc.paquete_completado, 0) as paquete_completado,
                        ISNULL(fc.almacen_completado, 0) as almacen_completado,
                        fc.estado,
                        fc.recepcion_archivo,
                        fc.despacho_archivo,
                        fc.paquete_archivo,
                        fc.almacen_archivo,
                        (SELECT COUNT(*) FROM " . TABLA_RECEPCION . " WHERE factura_id = fc.id) as total_recepcion,
                        (SELECT COUNT(*) FROM " . TABLA_DESPACHO . " WHERE factura_id = fc.id) as total_despacho,
                        (SELECT COUNT(*) FROM " . TABLA_PAQUETE . " WHERE factura_id = fc.id) as total_paquete,
                        (SELECT COUNT(*) FROM " . TABLA_ALMACEN . " WHERE factura_id = fc.id) as total_almacen
                       FROM " . TABLA_FACTURAS . " fc
                       WHERE fc.cliente_codigo = :codigo
                       ORDER BY fc.fecha_creacion DESC";
    
    $stmt = $conn->prepare($query_facturas);
    $stmt->execute([':codigo' => $codigo_cliente]);
    $facturas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Actualizar estadísticas
    $stats['total_facturas'] = count($facturas);
    $stats['facturas_mes'] = count(array_filter($facturas, function($f) {
        return date('Y-m', strtotime($f['fecha_emision'])) == date('Y-m');
    }));
    
} catch (Exception $e) {
    $error_db = "Error de conexión: " . $e->getMessage();
    error_log("Error en pages/arcor/index.php: " . $e->getMessage());
}

// Título de la página
$titulo_pagina = "Facturas - " . ($cliente_info['nombre_comercial'] ?? 'Cliente');

// Función para determinar el estado general de la factura
function getEstadoFactura($factura) {
    $completados = 0;
    $total_modulos = 4;
    
    if ($factura['recepcion_completado']) $completados++;
    if ($factura['despacho_completado']) $completados++;
    if ($factura['paquete_completado']) $completados++;
    if ($factura['almacen_completado']) $completados++;
    
    if ($completados == 0) return ['Pendiente', 'badge-pendiente'];
    if ($completados == $total_modulos) return ['Completa', 'badge-completo'];
    return ['Parcial', 'badge-warning'];
}

// Función para obtener los módulos completados
function getModulosCompletados($factura) {
    $modulos = [];
    if ($factura['recepcion_completado']) $modulos[] = 'Recepción';
    if ($factura['despacho_completado']) $modulos[] = 'Despacho';
    if ($factura['paquete_completado']) $modulos[] = 'Otros Servicios';
    if ($factura['almacen_completado']) $modulos[] = 'Ocupabilidad';
    return implode(', ', $modulos);
}

// Función para obtener el nombre amigable del módulo
function getNombreModulo($modulo) {
    $nombres = [
        'recepcion' => 'Recepción',
        'despacho' => 'Despacho',
        'paquete' => 'Otros Servicios',
        'almacen' => 'Ocupabilidad'
    ];
    return $nombres[$modulo] ?? $modulo;
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $titulo_pagina; ?></title>

    <!-- CSS -->
    <link href="../../vendors/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../vendors/font-awesome/css/font-awesome.min.css" rel="stylesheet">
    <link href="../../vendors/nprogress/nprogress.css" rel="stylesheet">
    <link href="../../vendors/datatables.net-bs/css/dataTables.bootstrap.min.css" rel="stylesheet">
    <link href="../../vendors/animate.css/animate.min.css" rel="stylesheet">
    <link href="../../build/css/custom.min.css" rel="stylesheet">

    <style>
        :root {
            --primary-color: #009a3f;
            --primary-dark: #007a32;
            --primary-light: #e8f5e9;
            --secondary-color: #ff6b00;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --info-color: #17a2b8;
        }

        body.nav-md {
            background: linear-gradient(135deg, #f5f7fa 0%, #e9ecef 100%);
            min-height: 100vh;
        }

        /* Header de cliente */
        .cliente-header-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 20px;
            border-left: 5px solid var(--primary-color);
        }

        .cliente-info-wrapper {
            display: flex;
            align-items: center;
            gap: 25px;
            flex-wrap: wrap;
        }

        .cliente-logo-container {
            position: relative;
        }

        .cliente-logo {
            width: 100px;
            height: 100px;
            border-radius: 20px;
            object-fit: contain;
            border: 3px solid var(--primary-color);
            padding: 8px;
            background: white;
            box-shadow: 0 5px 15px rgba(0,154,63,0.2);
        }

        .cliente-badge {
            position: absolute;
            bottom: -5px;
            right: -5px;
            background: var(--primary-color);
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            border: 2px solid white;
        }

        .cliente-details h1 {
            margin: 0 0 5px 0;
            color: #333;
            font-size: 28px;
            font-weight: 600;
        }

        .cliente-meta {
            display: flex;
            gap: 20px;
            color: #666;
            font-size: 14px;
            flex-wrap: wrap;
        }

        .cliente-meta span {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .cliente-meta i {
            color: var(--primary-color);
            width: 20px;
        }

        /* Botones */
        .btn-nueva-factura {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 16px;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 5px 15px rgba(0,154,63,0.3);
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .btn-nueva-factura:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,154,63,0.4);
            color: white;
            background: linear-gradient(135deg, var(--primary-dark), var(--primary-color));
        }

        .btn-volver {
            background: white;
            color: #333;
            border: 1px solid #ddd;
            padding: 12px 20px;
            border-radius: 50px;
            font-weight: 500;
            margin-left: 10px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-volver:hover {
            background: #f8f9fa;
            color: #333;
        }

        .btn-pdf {
            background: #dc3545;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
            margin: 2px;
        }

        .btn-pdf:hover {
            background: #c82333;
            transform: translateY(-2px);
        }

        .btn-pdf i {
            font-size: 14px;
        }

        .btn-completar {
            background: var(--info-color);
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            margin: 2px;
        }

        .btn-completar:hover {
            background: #138496;
            transform: translateY(-2px);
            color: white;
            text-decoration: none;
        }

        .btn-actualizar {
            background: #ffc107;
            color: #333;
            border: none;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            margin: 2px;
        }

        .btn-actualizar:hover {
            background: #e0a800;
            transform: translateY(-2px);
            color: #333;
            text-decoration: none;
        }

        /* Stats cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            gap: 15px;
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-3px);
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            background: var(--primary-light);
            color: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        .stat-info h3 {
            margin: 0;
            font-size: 14px;
            color: #666;
            font-weight: 400;
        }

        .stat-info .stat-number {
            font-size: 24px;
            font-weight: 600;
            color: #333;
            margin: 5px 0 0;
        }

        /* Tabla mejorada */
        .table-container {
            background: white;
            border-radius: 20px;
            padding: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            overflow-x: auto;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table thead th {
            background: #f8f9fa;
            color: #333;
            font-weight: 600;
            border-bottom: 2px solid var(--primary-color);
            padding: 12px;
            white-space: nowrap;
        }

        .table tbody td {
            padding: 12px;
            vertical-align: middle;
            border-bottom: 1px solid #dee2e6;
        }

        .table tbody tr:hover {
            background: #f5f5f5;
        }

        /* Badges */
        .badge-estado {
            padding: 6px 12px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
            white-space: nowrap;
        }

        .badge-completo {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .badge-pendiente {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }

        .badge-incompleto {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .badge-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }

        .badge-recepcion { background: #cce5ff; color: #004085; }
        .badge-despacho { background: #d4edda; color: #155724; }
        .badge-otrosservicios { background: #fff3cd; color: #856404; }
        .badge-ocupabilidad { background: #e8f5e9; color: #1e7e34; }

        /* Tooltip */
        .tooltip-modulos {
            cursor: help;
            border-bottom: 1px dashed #999;
        }

        /* Acciones compactas */
        .acciones-container {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .cliente-header-card {
                flex-direction: column;
                align-items: stretch;
            }
            
            .cliente-info-wrapper {
                flex-direction: column;
                text-align: center;
            }
            
            .cliente-meta {
                justify-content: center;
            }
            
            .btn-nueva-factura, .btn-volver {
                width: 100%;
                justify-content: center;
                margin: 5px 0;
            }
            
            .stats-grid {
                grid-template-columns: 1fr 1fr;
            }
            
            .acciones-container {
                flex-direction: row;
                flex-wrap: wrap;
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body class="nav-md">
    <div class="container body">
        <div class="main_container">
            <!-- SIDEBAR -->
            <div class="col-md-3 left_col">
                <div class="left_col scroll-view">
                    <div class="navbar nav_title" style="border: 0;">
                        <a href="../../dashboard.php" class="site_title">
                            <img src="../../img/logo.png" alt="RANSA Logo" style="height: 32px;">
                            <span style="font-size: 12px;">Dashboard</span>
                        </a>
                    </div>
                    <div class="clearfix"></div>

                    <div class="profile clearfix">
                        <div class="profile_info">
                            <span>Bienvenido,</span>
                            <h2><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Usuario'); ?></h2>
                        </div>
                    </div>

                    <br />

                    <div id="sidebar-menu" class="main_menu_side hidden-print main_menu">
                        <div class="menu_section">
                            <h3>Navegación</h3>
                            <ul class="nav side-menu">
                                <li>
                                    <a href="../../dashboard.php"><i class="fa fa-dashboard"></i> Dashboard</a>
                                </li>
                                <li>
                                    <a href="../../ingreso.php"><i class="fa fa-sign-in"></i> Ingreso</a>
                                </li>
                                <li>
                                    <a href="../../translado.php"><i class="fa fa-exchange"></i> Traslado</a>
                                </li>
                                <li>
                                    <a href="../../reportes.php"><i class="fa fa-file-text"></i> Reportes</a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- NAVBAR SUPERIOR -->
            <div class="top_nav">
                <div class="nav_menu">
                    <div class="nav toggle">
                        <a id="menu_toggle"><i class="fa fa-bars"></i></a>
                    </div>
                    <div class="nav navbar-nav navbar-right">
                        <span style="color: white; padding: 15px;">
                            <i class="fa fa-user-circle"></i> 
                            <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Usuario'); ?>
                            <small style="margin-left: 10px;">
                                <i class="fa fa-map-marker"></i> 
                                <?php echo htmlspecialchars($_SESSION['user_ciudad'] ?? 'N/A'); ?>
                            </small>
                        </span>
                    </div>
                </div>
            </div>

            <!-- CONTENIDO PRINCIPAL -->
            <div class="right_col" role="main">
                <div class="page-title">
                    <div class="title_left">
                        <h3><i class="fa fa-file-text"></i> Gestión de Facturas</h3>
                    </div>
                </div>
                
                <div class="clearfix"></div>

                <?php if (isset($error_db)): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fa fa-exclamation-triangle"></i> <?php echo $error_db; ?>
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                    </div>
                <?php endif; ?>

                <!-- HEADER DE CLIENTE -->
                <div class="cliente-header-card">
                    <div class="cliente-info-wrapper">
                        <div class="cliente-logo-container">
                            <?php
                            $logo = !empty($cliente_info['logo_png']) ? $cliente_info['logo_png'] : 'arcor.png';
                            $logo_path = "../../img/" . $logo;
                            if (!file_exists($logo_path)) {
                                $logo_path = "../../img/arcor.png";
                            }
                            ?>
                            <img src="<?php echo $logo_path; ?>" alt="Logo" class="cliente-logo">
                            <span class="cliente-badge">
                                <i class="fa fa-check"></i>
                            </span>
                        </div>
                        <div class="cliente-details">
                            <h1><?php echo htmlspecialchars($cliente_info['nombre_comercial'] ?? 'Cliente'); ?></h1>
                            <div class="cliente-meta">
                                <span><i class="fa fa-barcode"></i> Código: <?php echo htmlspecialchars($cliente_info['codigo_cliente']); ?></span>
                                <?php if (!empty($cliente_info['nit'])): ?>
                                    <span><i class="fa fa-id-card"></i> NIT: <?php echo htmlspecialchars($cliente_info['nit']); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($cliente_info['telefono'])): ?>
                                    <span><i class="fa fa-phone"></i> <?php echo htmlspecialchars($cliente_info['telefono']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="cliente-actions">
                        <a href="nueva_factura.php?cliente=<?php echo urlencode($codigo_cliente); ?>" class="btn-nueva-factura">
                            <i class="fa fa-plus-circle"></i>
                            <span>Nueva Factura</span>
                        </a>
                        <a href="../../dashboard.php" class="btn-volver">
                            <i class="fa fa-arrow-left"></i> Volver
                        </a>
                    </div>
                </div>

                <!-- STATS CARDS -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fa fa-file-text"></i>
                        </div>
                        <div class="stat-info">
                            <h3>Total Facturas</h3>
                            <div class="stat-number">
                                <?php echo number_format($stats['total_facturas']); ?>
                            </div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fa fa-calendar"></i>
                        </div>
                        <div class="stat-info">
                            <h3>Este Mes</h3>
                            <div class="stat-number">
                                <?php echo number_format($stats['facturas_mes']); ?>
                            </div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fa fa-cubes"></i>
                        </div>
                        <div class="stat-info">
                            <h3>Módulos</h3>
                            <div class="stat-number">
                                <small>Recepción, Despacho, Otros, Ocupabilidad</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TABLA DE FACTURAS -->
                <div class="table-container">
                    <table class="table" id="tablaFacturas">
                        <thead>
                            <tr>
                                <th>N° Factura</th>
                                <th>Fecha</th>
                                <th>Estado</th>
                                <th>Recepción</th>
                                <th>Despacho</th>
                                <th>Otros Servicios</th>
                                <th>Ocupabilidad</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($facturas)): ?>
                                <tr>
                                    <td colspan="8" class="text-center">
                                        <i class="fa fa-info-circle fa-2x text-muted"></i>
                                        <p class="mt-2">No hay facturas para este cliente</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($facturas as $factura): 
                                    list($estado_texto, $estado_clase) = getEstadoFactura($factura);
                                ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($factura['nro_factura']); ?></strong>
                                    </td>
                                    <td>
                                        <?php echo date('d/m/Y', strtotime($factura['fecha_emision'])); ?>
                                    </td>
                                    <td>
                                        <span class="badge-estado <?php echo $estado_clase; ?>">
                                            <?php echo $estado_texto; ?>
                                        </span>
                                        <br>
                                        <small class="text-muted tooltip-modulos" title="<?php echo getModulosCompletados($factura); ?>">
                                            <?php 
                                            $completados = 0;
                                            if ($factura['recepcion_completado']) $completados++;
                                            if ($factura['despacho_completado']) $completados++;
                                            if ($factura['paquete_completado']) $completados++;
                                            if ($factura['almacen_completado']) $completados++;
                                            echo $completados . '/4 completados';
                                            ?>
                                        </small>
                                    </td>
                                    
                                    <!-- RECEPCIÓN -->
                                    <td class="text-center">
                                        <?php if ($factura['recepcion_completado']): ?>
                                            <span class="badge badge-success">
                                                <i class="fa fa-check"></i> <?php echo $factura['total_recepcion']; ?>
                                            </span>
                                            <div class="acciones-container">
                                                <button class="btn-pdf" onclick="generarPDF(<?php echo $factura['factura_id']; ?>, 'recepcion')">
                                                    <i class="fa fa-file-pdf-o"></i> PDF
                                                </button>
                                                <a href="nueva_factura.php?cliente=<?php echo urlencode($codigo_cliente); ?>&factura_id=<?php echo $factura['factura_id']; ?>&modulo=recepcion" class="btn-actualizar">
                                                    <i class="fa fa-refresh"></i> Actualizar
                                                </a>
                                            </div>
                                        <?php else: ?>
                                            <span class="badge badge-secondary">Pendiente</span>
                                            <a href="nueva_factura.php?cliente=<?php echo urlencode($codigo_cliente); ?>&factura_id=<?php echo $factura['factura_id']; ?>&modulo=recepcion" class="btn-completar">
                                                <i class="fa fa-plus"></i> Completar
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <!-- DESPACHO -->
                                    <td class="text-center">
                                        <?php if ($factura['despacho_completado']): ?>
                                            <span class="badge badge-success">
                                                <i class="fa fa-check"></i> <?php echo $factura['total_despacho']; ?>
                                            </span>
                                            <div class="acciones-container">
                                                <button class="btn-pdf" onclick="generarPDF(<?php echo $factura['factura_id']; ?>, 'despacho')">
                                                    <i class="fa fa-file-pdf-o"></i> PDF
                                                </button>
                                                <a href="nueva_factura.php?cliente=<?php echo urlencode($codigo_cliente); ?>&factura_id=<?php echo $factura['factura_id']; ?>&modulo=despacho" class="btn-actualizar">
                                                    <i class="fa fa-refresh"></i> Actualizar
                                                </a>
                                            </div>
                                        <?php else: ?>
                                            <span class="badge badge-secondary">Pendiente</span>
                                            <a href="nueva_factura.php?cliente=<?php echo urlencode($codigo_cliente); ?>&factura_id=<?php echo $factura['factura_id']; ?>&modulo=despacho" class="btn-completar">
                                                <i class="fa fa-plus"></i> Completar
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <!-- OTROS SERVICIOS (antes Paquete) -->
                                    <td class="text-center">
                                        <?php if ($factura['paquete_completado']): ?>
                                            <span class="badge badge-success">
                                                <i class="fa fa-check"></i> <?php echo $factura['total_paquete']; ?>
                                            </span>
                                            <div class="acciones-container">
                                                <button class="btn-pdf" onclick="generarPDF(<?php echo $factura['factura_id']; ?>, 'paquete')">
                                                    <i class="fa fa-file-pdf-o"></i> PDF
                                                </button>
                                                <a href="nueva_factura.php?cliente=<?php echo urlencode($codigo_cliente); ?>&factura_id=<?php echo $factura['factura_id']; ?>&modulo=paquete" class="btn-actualizar">
                                                    <i class="fa fa-refresh"></i> Actualizar
                                                </a>
                                            </div>
                                        <?php else: ?>
                                            <span class="badge badge-secondary">Pendiente</span>
                                            <a href="nueva_factura.php?cliente=<?php echo urlencode($codigo_cliente); ?>&factura_id=<?php echo $factura['factura_id']; ?>&modulo=paquete" class="btn-completar">
                                                <i class="fa fa-plus"></i> Completar
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <!-- OCUPABILIDAD (antes Almacén) -->
                                    <td class="text-center">
                                        <?php if ($factura['almacen_completado']): ?>
                                            <span class="badge badge-success">
                                                <i class="fa fa-check"></i> <?php echo $factura['total_almacen']; ?>
                                            </span>
                                            <div class="acciones-container">
                                                <button class="btn-pdf" onclick="generarPDF(<?php echo $factura['factura_id']; ?>, 'almacen')">
                                                    <i class="fa fa-file-pdf-o"></i> PDF
                                                </button>
                                                <a href="nueva_factura.php?cliente=<?php echo urlencode($codigo_cliente); ?>&factura_id=<?php echo $factura['factura_id']; ?>&modulo=almacen" class="btn-actualizar">
                                                    <i class="fa fa-refresh"></i> Actualizar
                                                </a>
                                            </div>
                                        <?php else: ?>
                                            <span class="badge badge-secondary">Pendiente</span>
                                            <a href="nueva_factura.php?cliente=<?php echo urlencode($codigo_cliente); ?>&factura_id=<?php echo $factura['factura_id']; ?>&modulo=almacen" class="btn-completar">
                                                <i class="fa fa-plus"></i> Completar
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <!-- ACCIONES GENERALES -->
                                    <td>
                                        <button class="btn btn-sm btn-info" onclick="verResumen(<?php echo $factura['factura_id']; ?>)">
                                            <i class="fa fa-eye"></i> Resumen
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- FOOTER -->
            <footer style="margin-top: 20px; padding: 15px; background: rgba(0, 154, 63, 0.05); border-radius: 8px;">
                <div class="pull-right">
                    <i class="fa fa-clock-o"></i> Sistema Ransa Archivo - Bolivia 
                    <span class="text-muted">v2.0</span>
                </div>
            </footer>
        </div>
    </div>

    <!-- MODAL DE RESUMEN -->
    <div class="modal fade" id="modalResumen" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header" style="background: var(--primary-color); color: white;">
                    <h4 class="modal-title">
                        <i class="fa fa-file-text"></i> Resumen de Factura
                    </h4>
                    <button type="button" class="close" data-dismiss="modal" style="color: white;">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body" id="resumenContent">
                    <p class="text-center">
                        <i class="fa fa-spinner fa-spin fa-3x"></i><br>
                        Cargando resumen...
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <i class="fa fa-times"></i> Cerrar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- SCRIPTS -->
    <script src="../../vendors/jquery/dist/jquery.min.js"></script>
    <script src="../../vendors/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../vendors/datatables.net/js/jquery.dataTables.min.js"></script>
    <script src="../../vendors/datatables.net-bs/js/dataTables.bootstrap.min.js"></script>
    <script src="../../build/js/custom.min.js"></script>

    <script>
        // Inicializar DataTable
        $(document).ready(function() {
            $('#tablaFacturas').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.10.16/i18n/Spanish.json'
                },
                order: [[1, 'desc']],
                pageLength: 25,
                responsive: true
            });
        });

        // Función para generar PDF
        function generarPDF(factura_id, modulo) {
            let moduloNombre = '';
            switch(modulo) {
                case 'recepcion': moduloNombre = 'Recepción'; break;
                case 'despacho': moduloNombre = 'Despacho'; break;
                case 'paquete': moduloNombre = 'Otros Servicios'; break;
                case 'almacen': moduloNombre = 'Ocupabilidad'; break;
            }
            window.open('generar_pdf.php?factura_id=' + factura_id + '&modulo=' + modulo, '_blank');
        }

        // Función para ver resumen
        function verResumen(factura_id) {
            $('#modalResumen').modal('show');
            
            $.ajax({
                url: 'resumen_factura.php',
                method: 'GET',
                data: { factura_id: factura_id },
                success: function(response) {
                    $('#resumenContent').html(response);
                },
                error: function() {
                    $('#resumenContent').html('<p class="text-center text-danger">Error al cargar el resumen</p>');
                }
            });
        }

        // Toggle del menú
        document.getElementById('menu_toggle').addEventListener('click', function() {
            document.querySelector('.left_col').classList.toggle('menu-open');
        });
    </script>
</body>
</html>