<?php
session_start();

// Incluir configuración - RUTA CORREGIDA
require_once '../../conexion/config.php';

// Verificar si el usuario está logueado - ACTIVADO
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
    
    // Obtener datos del cliente - CORREGIDO (sin campo rfc)
    $query_cliente = "SELECT id, codigo_cliente, nombre_comercial, logo_png, direccion, telefono, email, nit 
                      FROM [FacBol].[clientes] 
                      WHERE codigo_cliente = :codigo";
    $stmt = $conn->prepare($query_cliente);
    $stmt->execute([':codigo' => $codigo_cliente]);
    $cliente_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$cliente_info) {
        // Debug
        error_log("Cliente no encontrado con código: " . $codigo_cliente);
        header("Location: ../../dashboard.php?msg=" . urlencode("Cliente no encontrado"));
        exit;
    }
    
    // Obtener estadísticas - CORREGIDO (sin referencias a columnas que no existen)
    $query_stats = "SELECT 
                        COUNT(*) as total_facturas,
                        SUM(CASE WHEN MONTH(fecha_emision) = MONTH(GETDATE()) THEN 1 ELSE 0 END) as facturas_mes,
                        SUM(monto_total) as monto_total
                    FROM [FacBol].[facturas] f
                    WHERE f.cliente_id = :cliente_id";
    $stmt = $conn->prepare($query_stats);
    $stmt->execute([':cliente_id' => $cliente_info['id']]);
    $stats_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($stats_data) {
        $stats = array_merge($stats, $stats_data);
    }
    
    // Obtener facturas del cliente - CORREGIDO (sin campos que no existen)
    $query_facturas = "SELECT 
                        f.id,
                        f.nro_factura,
                        c.nombre_comercial as cliente,
                        f.fecha_emision,
                        f.sede,
                        f.estado,
                        f.monto_total,
                        f.moneda,
                        f.created_at
                       FROM [FacBol].[facturas] f
                       INNER JOIN [FacBol].[clientes] c ON f.cliente_id = c.id
                       WHERE c.codigo_cliente = :codigo
                       ORDER BY f.fecha_emision DESC";
    
    $stmt = $conn->prepare($query_facturas);
    $stmt->execute([':codigo' => $codigo_cliente]);
    $facturas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error_db = "Error de conexión: " . $e->getMessage();
    error_log("Error en pages/arcor/index.php: " . $e->getMessage());
}

// Título de la página
$titulo_pagina = "Facturas - " . ($cliente_info['nombre_comercial'] ?? 'Cliente');

// Función para formatear moneda
function formatMoney($amount, $currency = 'BOB') {
    if ($amount === null) $amount = 0;
    return number_format($amount, 2, ',', '.') . ' ' . $currency;
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $titulo_pagina; ?></title>

    <!-- CSS del template -->
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
        }

        body.nav-md {
            background: linear-gradient(135deg, #f5f7fa 0%, #e9ecef 100%);
            min-height: 100vh;
        }

        /* Header de cliente mejorado */
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

        /* Botón de nueva factura mejorado */
        .btn-nueva-factura {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 5px 15px rgba(0,154,63,0.3);
            transition: all 0.3s ease;
        }

        .btn-nueva-factura:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,154,63,0.4);
            color: white;
            background: linear-gradient(135deg, var(--primary-dark), var(--primary-color));
        }

        .btn-nueva-factura i {
            font-size: 20px;
        }

        .btn-volver {
            background: white;
            color: #333;
            border: 1px solid #ddd;
            padding: 12px 20px;
            border-radius: 50px;
            font-weight: 500;
            margin-left: 10px;
        }

        .btn-volver:hover {
            background: #f8f9fa;
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

        .stat-info .stat-number small {
            font-size: 14px;
            color: #999;
            font-weight: 400;
        }

        /* Tabla mejorada */
        .table-container {
            background: white;
            border-radius: 20px;
            padding: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        }

        .table thead th {
            background: #f8f9fa;
            color: #333;
            font-weight: 600;
            border-bottom: 2px solid var(--primary-color);
        }

        .badge-estado {
            padding: 6px 12px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
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
            
            .btn-nueva-factura {
                width: 100%;
                justify-content: center;
            }
            
            .stats-grid {
                grid-template-columns: 1fr 1fr;
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

                <!-- HEADER DE CLIENTE MEJORADO -->
                <div class="cliente-header-card">
                    <div class="cliente-info-wrapper">
                        <div class="cliente-logo-container">
                            <?php
                            // Verificar si existe cliente_info
                            if (!empty($cliente_info) && is_array($cliente_info)) {
                                $logo = !empty($cliente_info['logo_png']) ? $cliente_info['logo_png'] : 'arcor.png';
                                $nombre_cliente = !empty($cliente_info['nombre_comercial']) ? $cliente_info['nombre_comercial'] : 'Cliente';
                                $codigo_cliente_display = !empty($cliente_info['codigo_cliente']) ? $cliente_info['codigo_cliente'] : '';
                                $nit_cliente = !empty($cliente_info['nit']) ? $cliente_info['nit'] : '';
                                $telefono_cliente = !empty($cliente_info['telefono']) ? $cliente_info['telefono'] : '';
                                $email_cliente = !empty($cliente_info['email']) ? $cliente_info['email'] : '';
                            } else {
                                $logo = 'arcor.png';
                                $nombre_cliente = 'Cliente no encontrado';
                                $codigo_cliente_display = '';
                                $nit_cliente = '';
                                $telefono_cliente = '';
                                $email_cliente = '';
                            }
                            
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
                            <h1><?php echo htmlspecialchars($nombre_cliente); ?></h1>
                            <div class="cliente-meta">
                                <?php if (!empty($codigo_cliente_display)): ?>
                                    <span><i class="fa fa-barcode"></i> Código: <?php echo htmlspecialchars($codigo_cliente_display); ?></span>
                                <?php endif; ?>
                                
                                <?php if (!empty($nit_cliente)): ?>
                                    <span><i class="fa fa-id-card"></i> NIT: <?php echo htmlspecialchars($nit_cliente); ?></span>
                                <?php endif; ?>
                                
                                <?php if (!empty($telefono_cliente)): ?>
                                    <span><i class="fa fa-phone"></i> <?php echo htmlspecialchars($telefono_cliente); ?></span>
                                <?php endif; ?>
                                
                                <?php if (!empty($email_cliente)): ?>
                                    <span><i class="fa fa-envelope"></i> <?php echo htmlspecialchars($email_cliente); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="cliente-actions">
                        <a href="nueva_factura.php?cliente=<?php echo urlencode($codigo_cliente); ?>" class="btn-nueva-factura">
                            <i class="fa fa-plus-circle"></i>
                            <span>Generar Nueva Factura</span>
                            <i class="fa fa-chevron-right"></i>
                        </a>
                            <i class="fa fa-plus-circle"></i>
                            <span>Generar Nueva Factura</span>
                            <i class="fa fa-chevron-right"></i>
                        </button>
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
                                <?php echo isset($stats['total_facturas']) ? number_format($stats['total_facturas']) : '0'; ?>
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
                                <?php echo isset($stats['facturas_mes']) ? number_format($stats['facturas_mes']) : '0'; ?>
                            </div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fa fa-money"></i>
                        </div>
                        <div class="stat-info">
                            <h3>Monto Total</h3>
                            <div class="stat-number">
                                <?php 
                                $monto_total = isset($stats['monto_total']) ? $stats['monto_total'] : 0;
                                echo formatMoney($monto_total); 
                                ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabla de facturas -->
                <div class="table-container">
                    <table class="table table-hover" id="tablaFacturas">
                        <thead>
                            <tr>
                                <th>N° Factura</th>
                                <th>Fecha</th>
                                <th>Sede</th>
                                <th>Estado</th>
                                <th>Monto</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($facturas)): ?>
                                <tr>
                                    <td colspan="5" class="text-center">
                                        <i class="fa fa-info-circle fa-2x text-muted"></i>
                                        <p class="mt-2">No hay facturas para este cliente</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($facturas as $factura): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($factura['nro_factura'] ?? 'N/A'); ?></strong>
                                    </td>
                                    <td>
                                        <?php 
                                        if (!empty($factura['fecha_emision'])) {
                                            echo date('d/m/Y', strtotime($factura['fecha_emision']));
                                        } else {
                                            echo 'N/A';
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($factura['sede'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php
                                        $estado = $factura['estado'] ?? 'pendiente';
                                        $badge_class = 'badge-pendiente';
                                        if ($estado == 'emitida') $badge_class = 'badge-completo';
                                        if ($estado == 'anulada') $badge_class = 'badge-incompleto';
                                        ?>
                                        <span class="badge-estado <?php echo $badge_class; ?>">
                                            <?php echo ucfirst($estado); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong><?php echo formatMoney($factura['monto_total'] ?? 0, $factura['moneda'] ?? 'BOB'); ?></strong>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- MODAL PARA NUEVA FACTURA (simplificado por ahora) -->
            <div class="modal fade" id="modalNuevaFactura" tabindex="-1" role="dialog">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h4 class="modal-title">
                                <i class="fa fa-file-excel-o"></i> Generar Nueva Factura
                            </h4>
                            <button type="button" class="close" data-dismiss="modal">
                                <span>&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <p class="text-center">
                                <i class="fa fa-info-circle fa-3x text-info"></i>
                            </p>
                            <p class="text-center">
                                Funcionalidad en desarrollo.<br>
                                Próximamente podrás generar facturas desde aquí.
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

            <!-- FOOTER -->
            <footer style="margin-top: 20px; padding: 15px; background: rgba(0, 154, 63, 0.05); border-radius: 8px;">
                <div class="pull-right">
                    <i class="fa fa-clock-o"></i> Sistema Ransa Archivo - Bolivia 
                    <span class="text-muted">v2.0</span>
                </div>
            </footer>
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

        // Toggle del menú
        document.getElementById('menu_toggle').addEventListener('click', function() {
            document.querySelector('.left_col').classList.toggle('menu-open');
        });
    </script>
</body>
</html>