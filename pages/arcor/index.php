<?php
session_start();

// Incluir configuración
require_once '../../conexion/config.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit;
}

// Obtener el rol del usuario
$user_rol = $_SESSION['user_rol'] ?? 3; // 1=Admin, 2=Supervisor, 3=Verificador
$user_id = $_SESSION['user_id'];

// Obtener el cliente de la URL
$codigo_cliente = isset($_GET['cliente']) ? $_GET['cliente'] : '';

if (empty($codigo_cliente)) {
    header("Location: ../../dashboard.php");
    exit;
}

// Verificar si el usuario tiene permiso para este cliente
$tiene_permiso = false;
if (isset($_SESSION['permisos_clientes'][$codigo_cliente])) {
    $tiene_permiso = true;
    $permiso_cliente = $_SESSION['permisos_clientes'][$codigo_cliente];
    $tipo_acceso = $permiso_cliente['tipo_usuario'];
} else {
    // Si no tiene permiso, redirigir
    header("Location: ../../dashboard.php?msg=" . urlencode("No tiene acceso a este cliente"));
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
    $query_cliente = "SELECT id, codigo_cliente, nombre_comercial, razon_social, nit, telefono, email, direccion, logo_png, contador_facturas 
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
                        fc.observaciones,
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
    
    // Obtener observaciones de facturas
    $query_observaciones = "SELECT fo.*, u.nombre as usuario_nombre 
                           FROM [FacBol].[facturas_observaciones] fo
                           INNER JOIN [IT].[usuarios_pt] u ON fo.usuario_id = u.id
                           WHERE fo.factura_id IN (SELECT id FROM " . TABLA_FACTURAS . " WHERE cliente_codigo = :codigo)
                           ORDER BY fo.fecha_registro DESC";
    $stmt = $conn->prepare($query_observaciones);
    $stmt->execute([':codigo' => $codigo_cliente]);
    $observaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Indexar observaciones por factura_id
    $observaciones_por_factura = [];
    foreach ($observaciones as $obs) {
        $observaciones_por_factura[$obs['factura_id']][] = $obs;
    }
    
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
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

        /* Estilos para iconos pequeños */
        .acciones-iconos {
            display: flex;
            gap: 5px;
            justify-content: center;
            margin-top: 5px;
        }

        .btn-icono {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            transition: all 0.2s ease;
            text-decoration: none;
            border: 1px solid #e0e0e0;
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .btn-icono:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .btn-pdf-icono {
            color: #dc3545;
        }

        .btn-pdf-icono:hover {
            background: #dc3545;
            color: white;
            border-color: #dc3545;
        }

        .btn-actualizar-icono {
            color: #28a745;
        }

        .btn-actualizar-icono:hover {
            background: #28a745;
            color: white;
            border-color: #28a745;
        }

        /* Estilos para botón completar */
        .btn-completar-modulo {
            background: white;
            color: #17a2b8;
            border: 1px solid #17a2b8;
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
            font-weight: 500;
        }

        .btn-completar-modulo:hover {
            background: #17a2b8;
            color: white;
            transform: translateY(-2px);
            text-decoration: none;
            box-shadow: 0 4px 8px rgba(23, 162, 184, 0.3);
        }

        .btn-completar-modulo i {
            font-size: 14px;
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

        /* Badges originales */
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

        .badge-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }

        /* NUEVOS ESTILOS PARA BOTONES DE ESTADO */
        .btn-estado {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            width: 100%;
            text-align: center;
            transition: all 0.2s ease;
        }

        .btn-estado:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .btn-estado-registrado {
            background: #cce5ff;
            color: #004085;
            border: 1px solid #b8daff;
        }

        .btn-estado-verificado {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .btn-estado-aprobado {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        .btn-estado-observado {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }

        .btn-estado-facturado {
            background: #e0d4f7;
            color: #563d7c;
            border: 1px solid #d3c5f0;
        }

        .btn-estado-pagado {
            background: #d6d8d9;
            color: #1e7e34;
            border: 1px solid #c6c8ca;
            font-weight: 700;
        }

        /* Acciones de resumen */
        .acciones-resumen {
            display: flex;
            gap: 5px;
            justify-content: center;
        }

        .btn-resumen {
            padding: 6px 10px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
            white-space: nowrap;
        }

        .btn-resumen-editar {
            background: #009a3f;
            color: white;
        }

        .btn-resumen-editar:hover {
            background: #007a32;
            transform: translateY(-2px);
        }

        .btn-resumen-pdf {
            background: #dc3545;
            color: white;
        }

        .btn-resumen-pdf:hover {
            background: #c82333;
            transform: translateY(-2px);
        }

        .tooltip-modulos {
            cursor: help;
            border-bottom: 1px dashed #999;
        }

        /* Estilos para observaciones */
        .badge-obs {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
            transition: all 0.2s;
            cursor: pointer;
        }
        
        .badge-obs-aprobador {
            background: #ffc107;
            color: #856404;
        }
        
        .badge-obs-aprobador:hover {
            background: #e0a800;
            transform: translateY(-1px);
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .badge-obs-cliente {
            background: #17a2b8;
            color: white;
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
            
            .acciones-resumen {
                flex-direction: column;
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
                            <small><i class="fa fa-tag"></i> <?php echo $_SESSION['user_rol_nombre'] ?? ''; ?></small>
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
                                    <a href="index.php?cliente=<?php echo urlencode($codigo_cliente); ?>">
                                        <i class="fa fa-arrow-left"></i> Volver al Cliente
                                    </a>
                                </li>
                                <?php if ($user_rol <= 2): // Admin o Supervisor ?>
                                <li>
                                    <a href="#" onclick="abrirModalTarifas()">
                                        <i class="fa fa-usd"></i> Modificar Tarifas
                                    </a>
                                </li>
                                <li>
                                    <a href="#" onclick="abrirModalCliente()">
                                        <i class="fa fa-building"></i> Editar Cliente
                                    </a>
                                </li>
                                <?php endif; ?>
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
                                <i class="fa fa-tag"></i> 
                                <?php echo $_SESSION['user_rol_nombre'] ?? ''; ?>
                            </small>
                        </span>
                    </div>
                </div>
            </div>

            <!-- CONTENIDO PRINCIPAL -->
            <div class="right_col" role="main">
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
                                <th>Resumen</th>
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
                                    $estado = $factura['estado'] ?? 'REGISTRADO';
                                    
                                    // Determinar clase de color para el botón de estado
                                    $color_class = '';
                                    switch($estado) {
                                        case 'REGISTRADO':
                                            $color_class = 'btn-estado-registrado';
                                            break;
                                        case 'VERIFICADO':
                                            $color_class = 'btn-estado-verificado';
                                            break;
                                        case 'APROBADO':
                                            $color_class = 'btn-estado-aprobado';
                                            break;
                                        case 'OBSERVADO':
                                            $color_class = 'btn-estado-observado';
                                            break;
                                        case 'FACTURADO':
                                            $color_class = 'btn-estado-facturado';
                                            break;
                                        case 'PAGADO':
                                            $color_class = 'btn-estado-pagado';
                                            break;
                                        default:
                                            $color_class = 'btn-estado-registrado';
                                    }
                                ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($factura['nro_factura']); ?></strong>
                                    </td>
                                    <td>
                                        <?php echo date('d/m/Y', strtotime($factura['fecha_emision'])); ?>
                                    </td>
                                    
                                    <!-- COLUMNA ESTADO CON BOTÓN Y COLORES -->
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 5px; flex-wrap: wrap;">
                                            <?php if ($user_rol <= 2): // Admin o Supervisor pueden cambiar estado ?>
                                                <button class="btn-estado <?php echo $color_class; ?>" 
                                                        onclick="cambiarEstadoModal(<?php echo $factura['factura_id']; ?>, '<?php echo $estado; ?>')"
                                                        title="Click para cambiar estado"
                                                        style="flex: 1;">
                                                    <?php echo $estado; ?>
                                                </button>
                                            <?php else: // Verificador solo ve el estado ?>
                                                <span class="btn-estado <?php echo $color_class; ?>" style="cursor:default; flex: 1;">
                                                    <?php echo $estado; ?>
                                                </span>
                                            <?php endif; ?>
                                            
                                            <!-- MOSTRAR OBSERVACIONES CUANDO EL ESTADO ES OBSERVADO -->
                                            <?php if ($estado == 'OBSERVADO' && isset($observaciones_por_factura[$factura['factura_id']])): ?>
                                                <?php foreach ($observaciones_por_factura[$factura['factura_id']] as $obs): ?>
                                                    <span class="badge-obs badge-obs-aprobador" 
                                                          style="display:inline-flex; align-items:center; gap:3px; white-space:nowrap;"
                                                          onclick="verObservacion('<?php echo htmlspecialchars(addslashes($obs['observacion'])); ?>', '<?php echo htmlspecialchars($obs['usuario_nombre']); ?>', '<?php echo $obs['fecha_registro']; ?>')"
                                                          title="Click para ver detalle">
                                                        <i class="fa fa-eye"></i> Ver
                                                    </span>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
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
                                        <?php if ($estado == 'APROBADO' && !empty($factura['almacen_archivo'])): ?>
                                            <br><small class="text-success"><i class="fa fa-check-circle"></i> <?php echo htmlspecialchars($factura['almacen_archivo']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <!-- RECEPCIÓN -->
                                    <td class="text-center">
                                        <?php if ($factura['recepcion_completado']): ?>
                                            <span class="badge badge-success">
                                                <i class="fa fa-check"></i> <?php echo $factura['total_recepcion']; ?>
                                            </span>
                                            <div class="acciones-iconos">
                                                <button class="btn-icono btn-pdf-icono" onclick="generarPDF(<?php echo $factura['factura_id']; ?>, 'recepcion')" title="Generar PDF">
                                                    <i class="fa fa-file-pdf-o"></i>
                                                </button>
                                                <a href="nueva_factura.php?cliente=<?php echo urlencode($codigo_cliente); ?>&factura_id=<?php echo $factura['factura_id']; ?>&modulo=recepcion" class="btn-icono btn-actualizar-icono" title="Actualizar">
                                                    <i class="fa fa-refresh"></i>
                                                </a>
                                            </div>
                                        <?php else: ?>
                                            <a href="nueva_factura.php?cliente=<?php echo urlencode($codigo_cliente); ?>&factura_id=<?php echo $factura['factura_id']; ?>&modulo=recepcion" class="btn-completar-modulo">
                                                <i class="fa fa-plus-circle"></i> Completar
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <!-- DESPACHO -->
                                    <td class="text-center">
                                        <?php if ($factura['despacho_completado']): ?>
                                            <span class="badge badge-success">
                                                <i class="fa fa-check"></i> <?php echo $factura['total_despacho']; ?>
                                            </span>
                                            <div class="acciones-iconos">
                                                <button class="btn-icono btn-pdf-icono" onclick="generarPDF(<?php echo $factura['factura_id']; ?>, 'despacho')" title="Generar PDF">
                                                    <i class="fa fa-file-pdf-o"></i>
                                                </button>
                                                <a href="nueva_factura.php?cliente=<?php echo urlencode($codigo_cliente); ?>&factura_id=<?php echo $factura['factura_id']; ?>&modulo=despacho" class="btn-icono btn-actualizar-icono" title="Actualizar">
                                                    <i class="fa fa-refresh"></i>
                                                </a>
                                            </div>
                                        <?php else: ?>
                                            <a href="nueva_factura.php?cliente=<?php echo urlencode($codigo_cliente); ?>&factura_id=<?php echo $factura['factura_id']; ?>&modulo=despacho" class="btn-completar-modulo">
                                                <i class="fa fa-plus-circle"></i> Completar
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <!-- OTROS SERVICIOS (antes Paquete) -->
                                    <td class="text-center">
                                        <?php if ($factura['paquete_completado']): ?>
                                            <span class="badge badge-success">
                                                <i class="fa fa-check"></i> <?php echo $factura['total_paquete']; ?>
                                            </span>
                                            <div class="acciones-iconos">
                                                <button class="btn-icono btn-pdf-icono" onclick="generarPDF(<?php echo $factura['factura_id']; ?>, 'paquete')" title="Generar PDF">
                                                    <i class="fa fa-file-pdf-o"></i>
                                                </button>
                                                <a href="nueva_factura.php?cliente=<?php echo urlencode($codigo_cliente); ?>&factura_id=<?php echo $factura['factura_id']; ?>&modulo=paquete" class="btn-icono btn-actualizar-icono" title="Actualizar">
                                                    <i class="fa fa-refresh"></i>
                                                </a>
                                            </div>
                                        <?php else: ?>
                                            <a href="nueva_factura.php?cliente=<?php echo urlencode($codigo_cliente); ?>&factura_id=<?php echo $factura['factura_id']; ?>&modulo=paquete" class="btn-completar-modulo">
                                                <i class="fa fa-plus-circle"></i> Completar
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <!-- OCUPABILIDAD (antes Almacén) -->
                                    <td class="text-center">
                                        <?php if ($factura['almacen_completado']): ?>
                                            <span class="badge badge-success">
                                                <i class="fa fa-check"></i> <?php echo $factura['total_almacen']; ?>
                                            </span>
                                            <div class="acciones-iconos">
                                                <button class="btn-icono btn-pdf-icono" onclick="generarPDF(<?php echo $factura['factura_id']; ?>, 'almacen')" title="Generar PDF">
                                                    <i class="fa fa-file-pdf-o"></i>
                                                </button>
                                                <a href="nueva_factura.php?cliente=<?php echo urlencode($codigo_cliente); ?>&factura_id=<?php echo $factura['factura_id']; ?>&modulo=almacen" class="btn-icono btn-actualizar-icono" title="Actualizar">
                                                    <i class="fa fa-refresh"></i>
                                                </a>
                                            </div>
                                        <?php else: ?>
                                            <a href="nueva_factura.php?cliente=<?php echo urlencode($codigo_cliente); ?>&factura_id=<?php echo $factura['factura_id']; ?>&modulo=almacen" class="btn-completar-modulo">
                                                <i class="fa fa-plus-circle"></i> Completar
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <!-- ACCIONES DE RESUMEN (2 BOTONES) -->
                                    <td>
                                        <div class="acciones-resumen">
                                            <button class="btn-resumen btn-resumen-editar" onclick="abrirResumen(<?php echo $factura['factura_id']; ?>)">
                                                <i class="fa fa-pencil"></i> Editar
                                            </button>
                                            <button class="btn-resumen btn-resumen-pdf" onclick="generarPDFResumen(<?php echo $factura['factura_id']; ?>)">
                                                <i class="fa fa-file-pdf-o"></i> PDF
                                            </button>
                                        </div>
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

    <!-- MODAL DE TARIFAS -->
    <div class="modal fade" id="modalTarifas" tabindex="-1" role="dialog" aria-labelledby="modalTarifasLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl" style="max-width: 1200px;" role="document">
            <div class="modal-content">
                <div class="modal-header" style="background: #009a3f; color: white;">
                    <h5 class="modal-title">
                        <i class="fa fa-usd"></i> Modificar Tarifas - <?php echo htmlspecialchars($cliente_info['nombre_comercial']); ?>
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" style="color: white;">&times;</button>
                </div>
                <div class="modal-body" style="padding: 20px; max-height: 70vh; overflow-y: auto;">
                    <div id="tarifas-content">
                        <p class="text-center">
                            <i class="fa fa-spinner fa-spin fa-3x text-success"></i>
                            <br>Cargando tarifas...
                        </p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
                    <button type="button" class="btn btn-success" onclick="guardarTarifas()">Guardar Cambios</button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL EDITAR CLIENTE -->
    <div class="modal fade" id="modalCliente" tabindex="-1" role="dialog" aria-labelledby="modalClienteLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header" style="background: #009a3f; color: white;">
                    <h5 class="modal-title">
                        <i class="fa fa-building"></i> Editar Cliente
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" style="color: white;">&times;</button>
                </div>
                <div class="modal-body" style="padding: 20px;">
                    <form id="formCliente">
                        <input type="hidden" id="cliente_id" value="<?php echo $cliente_info['id']; ?>">
                        <input type="hidden" id="cliente_codigo" value="<?php echo $cliente_info['codigo_cliente']; ?>">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Nombre Comercial <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="nombre_comercial" value="<?php echo htmlspecialchars($cliente_info['nombre_comercial']); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Razón Social</label>
                                    <input type="text" class="form-control" id="razon_social" value="<?php echo htmlspecialchars($cliente_info['razon_social'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>NIT</label>
                                    <input type="text" class="form-control" id="nit" value="<?php echo htmlspecialchars($cliente_info['nit'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Teléfono</label>
                                    <input type="text" class="form-control" id="telefono" value="<?php echo htmlspecialchars($cliente_info['telefono'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" class="form-control" id="email" value="<?php echo htmlspecialchars($cliente_info['email'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>Dirección</label>
                            <textarea class="form-control" id="direccion" rows="2"><?php echo htmlspecialchars($cliente_info['direccion'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fa fa-info-circle"></i> El logo se gestiona por separado. Contacte al administrador para cambiarlo.
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-success" onclick="guardarCliente()">Guardar Cambios</button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL DE RESUMEN -->
    <div class="modal fade" id="modalResumen" tabindex="-1" role="dialog" aria-labelledby="modalResumenLabel" aria-hidden="true">
        <div class="modal-dialog" style="max-width: 1400px; width: 95%;" role="document">
            <div class="modal-content" id="modalResumenContent">
                <!-- El contenido se carga aquí dinámicamente -->
            </div>
        </div>
    </div>

    <!-- MODAL DE CAMBIO DE ESTADO -->
    <div class="modal fade" id="modalCambioEstado" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document" style="max-width: 500px;">
            <div class="modal-content">
                <!-- El contenido se carga dinámicamente -->
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
        // Variables globales
        var userRol = <?php echo $user_rol; ?>;
        var codigoCliente = '<?php echo $codigo_cliente; ?>';
        var clienteId = <?php echo $cliente_info['id']; ?>;

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

        // Función para abrir modal de tarifas
        function abrirModalTarifas() {
            $('#modalTarifas').modal('show');
            $('#tarifas-content').html(`
                <p class="text-center">
                    <i class="fa fa-spinner fa-spin fa-3x text-success"></i>
                    <br>Cargando tarifas...
                </p>
            `);
            
            $.ajax({
                url: 'get_tarifas.php',
                method: 'GET',
                data: { cliente: codigoCliente },
                dataType: 'html',
                success: function(response) {
                    $('#tarifas-content').html(response);
                },
                error: function(xhr, status, error) {
                    $('#tarifas-content').html(`
                        <div class="alert alert-danger">
                            <i class="fa fa-exclamation-triangle"></i> Error al cargar tarifas: ${error}
                        </div>
                    `);
                }
            });
        }

        // Función para guardar tarifas
        function guardarTarifas() {
            var tarifas = [];
            $('.tarifa-row').each(function() {
                var id = $(this).data('id');
                var tarifa_usd = $(this).find('.tarifa-usd').val();
                if (id && tarifa_usd) {
                    tarifas.push({
                        id: id,
                        tarifa_usd: tarifa_usd
                    });
                }
            });
            
            $.ajax({
                url: 'guardar_tarifas.php',
                method: 'POST',
                data: JSON.stringify({
                    cliente: codigoCliente,
                    tarifas: tarifas
                }),
                contentType: 'application/json',
                success: function(response) {
                    if (response.success) {
                        alert('✅ Tarifas guardadas correctamente');
                        $('#modalTarifas').modal('hide');
                    } else {
                        alert('❌ Error: ' + response.error);
                    }
                },
                error: function(xhr, status, error) {
                    alert('❌ Error al guardar tarifas: ' + error);
                }
            });
        }

        // Función para abrir modal de editar cliente
        function abrirModalCliente() {
            $('#modalCliente').modal('show');
        }

        // Función para guardar cliente
        function guardarCliente() {
            var data = {
                id: $('#cliente_id').val(),
                codigo_cliente: $('#cliente_codigo').val(),
                nombre_comercial: $('#nombre_comercial').val(),
                razon_social: $('#razon_social').val(),
                nit: $('#nit').val(),
                telefono: $('#telefono').val(),
                email: $('#email').val(),
                direccion: $('#direccion').val()
            };
            
            $.ajax({
                url: 'guardar_cliente.php',
                method: 'POST',
                data: JSON.stringify(data),
                contentType: 'application/json',
                success: function(response) {
                    if (response.success) {
                        alert('✅ Cliente actualizado correctamente');
                        $('#modalCliente').modal('hide');
                        location.reload();
                    } else {
                        alert('❌ Error: ' + response.error);
                    }
                },
                error: function(xhr, status, error) {
                    alert('❌ Error al guardar cliente: ' + error);
                }
            });
        }

        // Función para generar PDF de módulos específicos
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

        // Función para generar PDF del resumen completo
        function generarPDFResumen(factura_id) {
            window.open('generar_pdf_resumen.php?factura_id=' + factura_id, '_blank');
        }

        // Función para ver observación
        function verObservacion(observacion, usuario, fecha) {
            // Formatear fecha
            if (fecha) {
                var date = new Date(fecha);
                fecha = date.toLocaleString('es-ES', {
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });
            }
            
            // Crear modal temporal
            var modalHtml = `
                <div class="modal fade" id="modalVerObservacion" tabindex="-1" role="dialog">
                    <div class="modal-dialog" role="document">
                        <div class="modal-content">
                            <div class="modal-header" style="background: #ffc107; color: #856404;">
                                <h5 class="modal-title">
                                    <i class="fa fa-eye"></i> Detalle de Observación
                                </h5>
                                <button type="button" class="close" data-dismiss="modal">&times;</button>
                            </div>
                            <div class="modal-body">
                                <p><strong>Observación:</strong></p>
                                <div class="alert alert-warning" style="white-space: pre-wrap;">
                                    ${observacion}
                                </div>
                                <hr>
                                <p><strong>Registrado por:</strong> ${usuario}</p>
                                <p><strong>Fecha:</strong> ${fecha}</p>
                            </div>
                            <div class="modal-footer">
                                <button class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Eliminar modal anterior si existe
            $('#modalVerObservacion').remove();
            
            // Agregar y mostrar el nuevo modal
            $('body').append(modalHtml);
            $('#modalVerObservacion').modal('show');
            
            // Eliminar del DOM cuando se cierre
            $('#modalVerObservacion').on('hidden.bs.modal', function() {
                $(this).remove();
            });
        }

        // Función para abrir modal de cambio de estado
        function cambiarEstadoModal(factura_id, estado_actual) {
            var opciones = '';
            if (userRol == 1) { // Admin puede todos
                opciones = `
                    <option value="REGISTRADO">REGISTRADO</option>
                    <option value="VERIFICADO">VERIFICADO</option>
                    <option value="APROBADO">APROBADO</option>
                    <option value="OBSERVADO">OBSERVADO</option>
                    <option value="FACTURADO">FACTURADO</option>
                    <option value="PAGADO">PAGADO</option>
                `;
            } else if (userRol == 2) { // Supervisor solo puede poner Aprobado u Observado
                opciones = `
                    <option value="APROBADO">APROBADO</option>
                    <option value="OBSERVADO">OBSERVADO</option>
                `;
            } else {
                return; // Verificador no puede cambiar estado
            }
            
            $('#modalCambioEstado .modal-content').html(`
                <div class="modal-header" style="background:#009a3f; color:white;">
                    <h5 class="modal-title">Cambiar Estado de Factura</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body p-4">
                    <p><strong>Factura:</strong> FAC-${String(factura_id).padStart(6, '0')}</p>
                    <p><strong>Estado actual:</strong> <span class="badge badge-info">${estado_actual}</span></p>
                    
                    <div class="form-group mt-3">
                        <label>Nuevo estado:</label>
                        <select class="form-control" id="nuevoEstado">
                            ${opciones}
                        </select>
                    </div>
                    
                    <div class="form-group mt-3" id="observacionGroup" style="display:none;">
                        <label>Observaciones:</label>
                        <textarea class="form-control" id="observacion" rows="3" placeholder="Ingrese las observaciones..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button class="btn btn-success" onclick="cambiarEstado(${factura_id})">Cambiar Estado</button>
                </div>
            `);
            
            // Mostrar campo de observación solo si selecciona OBSERVADO
            $('#nuevoEstado').change(function() {
                if ($(this).val() === 'OBSERVADO') {
                    $('#observacionGroup').show();
                } else {
                    $('#observacionGroup').hide();
                }
            });
            
            $('#modalCambioEstado').modal('show');
        }

        // Función para cambiar estado (ajax)
        function cambiarEstado(factura_id) {
            const nuevoEstado = $('#nuevoEstado').val();
            const observacion = $('#observacion').val() || '';
            
            $.ajax({
                url: 'cambiar_estado.php',
                method: 'POST',
                data: JSON.stringify({
                    factura_id: factura_id,
                    estado: nuevoEstado,
                    observacion: observacion,
                    tipo_observacion: 'APROBADOR'
                }),
                contentType: 'application/json',
                success: function(response) {
                    if (response.success) {
                        alert('✅ ' + response.mensaje);
                        $('#modalCambioEstado').modal('hide');
                        location.reload(); // Recargar para ver el cambio
                    } else {
                        alert('❌ Error: ' + response.error);
                    }
                },
                error: function(xhr, status, error) {
                    alert('❌ Error al cambiar estado: ' + error);
                }
            });
        }

        // Función para abrir el resumen
        function abrirResumen(factura_id) {
            $('#modalResumen .modal-content').html(`
                <div class="modal-header" style="background:#009a3f; color:white;">
                    <h5 class="modal-title">Cargando...</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body text-center p-5">
                    <i class="fa fa-spinner fa-spin fa-3x text-success"></i>
                    <p class="mt-3">Cargando datos de la factura...</p>
                </div>
            `);
            $('#modalResumen').modal('show');

            $.ajax({
                url: 'get_resumen_data.php',
                method: 'GET',
                data: { factura_id: factura_id },
                dataType: 'html',
                success: function(response) {
                    $('#modalResumen .modal-content').html(response);
                },
                error: function(xhr, status, error) {
                    $('#modalResumen .modal-content').html(`
                        <div class="modal-header" style="background:#dc3545; color:white;">
                            <h5 class="modal-title">Error</h5>
                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                        </div>
                        <div class="modal-body text-center p-4">
                            <i class="fa fa-exclamation-triangle fa-3x text-danger"></i>
                            <p class="mt-3">Error al cargar: ${error}</p>
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
                        </div>
                    `);
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