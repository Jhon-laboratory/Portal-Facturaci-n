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

try {
    $conn = getDBConnection();
    
    // Obtener datos del cliente
    $query_cliente = "SELECT id, codigo_cliente, nombre_comercial, logo_png 
                      FROM [FacBol].[clientes] 
                      WHERE codigo_cliente = :codigo";
    $stmt = $conn->prepare($query_cliente);
    $stmt->execute([':codigo' => $codigo_cliente]);
    $cliente_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$cliente_info) {
        header("Location: ../../dashboard.php?msg=" . urlencode("Cliente no encontrado"));
        exit;
    }
    
} catch (Exception $e) {
    $error_db = "Error de conexión: " . $e->getMessage();
    error_log("Error en nueva_factura.php: " . $e->getMessage());
}

// Título de la página
$titulo_pagina = "Nueva Factura - " . ($cliente_info['nombre_comercial'] ?? 'Cliente');
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
    <link href="../../build/css/custom.min.css" rel="stylesheet">

    <style>
        :root {
            --primary-color: #009a3f;
            --primary-dark: #007a32;
            --primary-light: #e8f5e9;
        }

        body.nav-md {
            background: linear-gradient(135deg, #f5f7fa 0%, #e9ecef 100%);
            min-height: 100vh;
        }

        /* Header de cliente */
        .cliente-header {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 20px;
            border-left: 5px solid var(--primary-color);
        }

        .cliente-logo {
            width: 70px;
            height: 70px;
            border-radius: 15px;
            object-fit: contain;
            border: 2px solid var(--primary-color);
            padding: 5px;
            background: white;
        }

        .cliente-info h2 {
            margin: 0;
            color: #333;
            font-size: 24px;
        }

        .cliente-info p {
            margin: 5px 0 0;
            color: #666;
        }

        /* Título de sección */
        .section-title {
            margin: 30px 0 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--primary-color);
            color: #333;
            font-weight: 600;
        }

        .section-title i {
            color: var(--primary-color);
            margin-right: 10px;
        }

        /* Grid de módulos */
        .modulos-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 25px;
            margin-bottom: 30px;
        }

        /* Tarjetas de módulos */
        .modulo-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: 1px solid #e0e0e0;
            display: flex;
            flex-direction: column;
        }

        .modulo-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,154,63,0.15);
            border-color: var(--primary-color);
        }

        .modulo-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }

        .modulo-icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            background: var(--primary-light);
            color: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
        }

        .modulo-titulo h3 {
            margin: 0;
            color: #333;
            font-size: 18px;
            font-weight: 600;
        }

        .modulo-titulo p {
            margin: 5px 0 0;
            color: #666;
            font-size: 13px;
        }

        /* Upload area */
        .upload-area {
            border: 2px dashed #ddd;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            background: #fafafa;
            transition: all 0.3s ease;
            cursor: pointer;
            margin-bottom: 15px;
        }

        .upload-area:hover {
            border-color: var(--primary-color);
            background: white;
        }

        .upload-area i {
            font-size: 40px;
            color: var(--primary-color);
            margin-bottom: 10px;
        }

        .upload-area p {
            margin: 0;
            color: #666;
        }

        .upload-area small {
            color: #999;
            font-size: 11px;
        }

        /* File info */
        .file-info {
            background: #f0f9f0;
            border: 1px solid var(--primary-color);
            border-radius: 10px;
            padding: 12px;
            margin-top: 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .file-info .file-details {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .file-info i {
            color: var(--primary-color);
            font-size: 20px;
        }

        .file-info .file-name {
            font-weight: 500;
            color: #333;
        }

        .file-info .file-size {
            color: #666;
            font-size: 12px;
        }

        .btn-remove {
            background: none;
            border: none;
            color: #dc3545;
            cursor: pointer;
            padding: 5px;
        }

        .btn-remove:hover {
            color: #c82333;
        }

        /* Campos de datos extraídos */
        .data-extracted {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-top: 15px;
            flex: 1;
        }

        .data-extracted h4 {
            font-size: 14px;
            color: #333;
            margin-bottom: 10px;
            font-weight: 600;
        }

        .data-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
        }

        .data-item {
            background: white;
            padding: 8px 12px;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
            font-size: 13px;
        }

        .data-item .label {
            color: #666;
            display: block;
            font-size: 11px;
        }

        .data-item .value {
            color: #333;
            font-weight: 500;
        }

        /* Botón de procesar por módulo */
        .btn-procesar-modulo {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 15px;
            width: 100%;
            justify-content: center;
            transition: all 0.3s ease;
            opacity: 0.7;
            cursor: not-allowed;
        }

        .btn-procesar-modulo.active {
            opacity: 1;
            cursor: pointer;
        }

        .btn-procesar-modulo.active:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,154,63,0.3);
        }

        .btn-procesar-modulo i {
            font-size: 16px;
        }

        /* Botones de acción generales */
        .actions-bar {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-top: 30px;
            box-shadow: 0 -5px 20px rgba(0,0,0,0.05);
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            position: sticky;
            bottom: 20px;
        }

        .btn-generar-factura {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            border: none;
            padding: 15px 40px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 5px 15px rgba(0,154,63,0.3);
            transition: all 0.3s ease;
        }

        .btn-generar-factura:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,154,63,0.4);
            color: white;
        }

        .btn-cancelar {
            background: white;
            color: #666;
            border: 1px solid #ddd;
            padding: 15px 30px;
            border-radius: 50px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-cancelar:hover {
            background: #f8f9fa;
            color: #333;
        }

        /* Progress bar general */
        .progress-general {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }

        .progress-stats {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            color: #666;
            font-size: 14px;
        }

        .progress-bar-custom {
            height: 10px;
            background: #e0e0e0;
            border-radius: 5px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary-color), #00c851);
            border-radius: 5px;
            transition: width 0.3s ease;
        }

        /* Modal de vista previa */
        .modal-preview {
            max-width: 90%;
        }

        .filter-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border: 1px solid #dee2e6;
        }

        .filter-section h5 {
            margin-bottom: 15px;
            color: var(--primary-color);
            font-weight: 600;
        }

        .filter-section .filter-controls {
            display: flex;
            gap: 15px;
            align-items: flex-end;
            flex-wrap: wrap;
        }

        .filter-section .filter-item {
            flex: 1;
            min-width: 200px;
        }

        .filter-section label {
            font-size: 12px;
            color: #666;
            margin-bottom: 5px;
            display: block;
        }

        .filter-section .form-control {
            height: 38px;
            border-radius: 8px;
            border: 1px solid #ced4da;
        }

        .filter-section .btn-filter {
            height: 38px;
            padding: 0 20px;
            border-radius: 8px;
            font-weight: 500;
        }

        .filter-section .btn-filter-success {
            background: var(--primary-color);
            color: white;
            border: none;
        }

        .filter-section .btn-filter-success:hover {
            background: var(--primary-dark);
        }

        .filter-section .btn-filter-secondary {
            background: #6c757d;
            color: white;
            border: none;
        }

        .filter-section .btn-filter-secondary:hover {
            background: #5a6268;
        }

        .filter-info {
            margin-top: 10px;
            font-size: 12px;
            color: #666;
            padding: 8px;
            background: #e9ecef;
            border-radius: 6px;
        }

        .filter-info i {
            color: var(--primary-color);
            margin-right: 5px;
        }

        .preview-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        .preview-table th {
            background: var(--primary-color);
            color: white;
            padding: 12px;
            font-size: 14px;
            font-weight: 600;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .preview-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #eee;
            font-size: 13px;
        }

        .preview-table tr:hover {
            background: #f5f5f5;
        }

        .preview-table .text-right {
            text-align: right;
        }

        .preview-summary {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin-top: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .preview-summary .total-label {
            font-weight: 600;
            color: #333;
        }

        .preview-summary .total-value {
            font-size: 20px;
            font-weight: 600;
            color: var(--primary-color);
        }

        .filter-stats {
            display: flex;
            gap: 15px;
            margin-top: 10px;
            flex-wrap: wrap;
        }

        .filter-stat-badge {
            background: #e3f2fd;
            color: #0d47a1;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .filter-stat-badge i {
            font-size: 12px;
        }

        /* Barra de progreso para archivos grandes */
        .progress-chunked {
            margin-top: 15px;
            padding: 10px;
            background: #e3f2fd;
            border-radius: 8px;
            border-left: 4px solid #2196f3;
        }

        .progress-chunked .progress {
            height: 20px;
            margin-bottom: 5px;
        }

        .progress-chunked .progress-bar {
            background: linear-gradient(90deg, #2196f3, #64b5f6);
            line-height: 20px;
            font-size: 12px;
        }

        .loading-text {
            font-size: 14px;
            color: #555;
        }

        .small-note {
            font-size: 11px;
            color: #777;
            margin-top: 5px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .modulos-grid {
                grid-template-columns: 1fr;
            }
            
            .data-grid {
                grid-template-columns: 1fr;
            }
            
            .actions-bar {
                flex-direction: column;
            }
            
            .btn-generar-factura, .btn-cancelar {
                width: 100%;
                justify-content: center;
            }
            
            .filter-section .filter-controls {
                flex-direction: column;
            }
            
            .filter-section .filter-item {
                width: 100%;
            }
            
            .filter-section .btn-filter {
                width: 100%;
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
                            <span style="font-size: 12px;">Nueva Factura</span>
                        </a>
                    </div>
                    <div class="clearfix"></div>

                    <div class="profile clearfix">
                        <div class="profile_info">
                            <span>Bienvenido,</span>
                            <h2><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Usuario'); ?></h2>
                        </div>
                    </div>

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
                        </span>
                    </div>
                </div>
            </div>

            <!-- CONTENIDO PRINCIPAL -->
            <div class="right_col" role="main">
                <!-- Header del cliente -->
                <div class="cliente-header">
                    <?php
                    $logo = !empty($cliente_info['logo_png']) ? $cliente_info['logo_png'] : 'arcor.png';
                    $logo_path = "../../img/" . $logo;
                    if (!file_exists($logo_path)) {
                        $logo_path = "../../img/arcor.png";
                    }
                    ?>
                    <img src="<?php echo $logo_path; ?>" alt="Logo" class="cliente-logo">
                    <div class="cliente-info">
                        <h2><?php echo htmlspecialchars($cliente_info['nombre_comercial'] ?? 'Cliente'); ?></h2>
                        <p><i class="fa fa-barcode"></i> Código: <?php echo htmlspecialchars($codigo_cliente); ?></p>
                    </div>
                </div>

                <!-- Progress bar general -->
                <div class="progress-general">
                    <div class="progress-stats">
                        <span><i class="fa fa-file-excel-o"></i> Archivos subidos: <span id="archivosSubidos">0</span>/4</span>
                        <span><i class="fa fa-check-circle"></i> Procesados: <span id="archivosProcesados">0</span>/4</span>
                    </div>
                    <div class="progress-bar-custom">
                        <div class="progress-fill" id="progressFill" style="width: 0%;"></div>
                    </div>
                </div>

                <!-- Título -->
                <h2 class="section-title">
                    <i class="fa fa-cubes"></i> 
                    Módulos de Carga de Archivos
                </h2>

                <!-- Grid de 4 módulos -->
                <div class="modulos-grid">
                    <!-- MÓDULO 1: DESPACHO -->
                    <div class="modulo-card" id="modulo-despacho">
                        <div class="modulo-header">
                            <div class="modulo-icon">
                                <i class="fa fa-truck"></i>
                            </div>
                            <div class="modulo-titulo">
                                <h3>Módulo de Despacho</h3>
                                <p>Archivo Excel con información de despachos</p>
                            </div>
                        </div>
                        
                        <div class="upload-area" onclick="document.getElementById('file-despacho').click()">
                            <i class="fa fa-cloud-upload"></i>
                            <p>Haz clic para seleccionar archivo</p>
                            <small>Formatos: .xls, .xlsx, .csv (Max: 100MB)</small>
                            <input type="file" id="file-despacho" name="archivo_despacho" style="display: none;" accept=".xls,.xlsx,.csv" onchange="habilitarBotonProcesar('despacho')">
                        </div>

                        <div class="file-info" id="info-despacho" style="display: none;">
                            <div class="file-details">
                                <i class="fa fa-file-excel-o"></i>
                                <div>
                                    <div class="file-name" id="nombre-despacho"></div>
                                    <div class="file-size" id="size-despacho"></div>
                                </div>
                            </div>
                            <button class="btn-remove" onclick="eliminarArchivo('despacho')">
                                <i class="fa fa-times"></i>
                            </button>
                        </div>

                        <div class="data-extracted" id="data-despacho" style="display: none;">
                            <h4><i class="fa fa-database"></i> Datos Extraídos</h4>
                            <div class="data-grid">
                                <div class="data-item">
                                    <span class="label">Total Guías</span>
                                    <span class="value" id="despacho-total">-</span>
                                </div>
                                <div class="data-item">
                                    <span class="label">Peso Total</span>
                                    <span class="value" id="despacho-peso">-</span>
                                </div>
                                <div class="data-item">
                                    <span class="label">Fecha Inicio</span>
                                    <span class="value" id="despacho-fecha-ini">-</span>
                                </div>
                                <div class="data-item">
                                    <span class="label">Fecha Fin</span>
                                    <span class="value" id="despacho-fecha-fin">-</span>
                                </div>
                            </div>
                        </div>

                        <button class="btn-procesar-modulo" id="btn-procesar-despacho" onclick="mostrarVistaPrevia('despacho')" disabled>
                            <i class="fa fa-cogs"></i> Procesar Archivo
                        </button>
                    </div>

                    <!-- MÓDULO 2: RECEPCIÓN -->
                    <div class="modulo-card" id="modulo-recepcion">
                        <div class="modulo-header">
                            <div class="modulo-icon">
                                <i class="fa fa-check-circle"></i>
                            </div>
                            <div class="modulo-titulo">
                                <h3>Módulo de Recepción</h3>
                                <p>Archivo Excel con información de recepciones</p>
                            </div>
                        </div>
                        
                        <div class="upload-area" onclick="document.getElementById('file-recepcion').click()">
                            <i class="fa fa-cloud-upload"></i>
                            <p>Haz clic para seleccionar archivo</p>
                            <small>Formatos: .xls, .xlsx, .csv (Max: 100MB)</small>
                            <input type="file" id="file-recepcion" name="archivo_recepcion" style="display: none;" accept=".xls,.xlsx,.csv" onchange="habilitarBotonProcesar('recepcion')">
                        </div>

                        <div class="file-info" id="info-recepcion" style="display: none;">
                            <div class="file-details">
                                <i class="fa fa-file-excel-o"></i>
                                <div>
                                    <div class="file-name" id="nombre-recepcion"></div>
                                    <div class="file-size" id="size-recepcion"></div>
                                </div>
                            </div>
                            <button class="btn-remove" onclick="eliminarArchivo('recepcion')">
                                <i class="fa fa-times"></i>
                            </button>
                        </div>

                        <div class="data-extracted" id="data-recepcion" style="display: none;">
                            <h4><i class="fa fa-database"></i> Datos Extraídos</h4>
                            <div class="data-grid">
                                <div class="data-item">
                                    <span class="label">Total Recepciones</span>
                                    <span class="value" id="recepcion-total">-</span>
                                </div>
                                <div class="data-item">
                                    <span class="label">Bultos Recibidos</span>
                                    <span class="value" id="recepcion-bultos">-</span>
                                </div>
                                <div class="data-item">
                                    <span class="label">Proveedores</span>
                                    <span class="value" id="recepcion-proveedores">-</span>
                                </div>
                                <div class="data-item">
                                    <span class="label">Fecha Recepción</span>
                                    <span class="value" id="recepcion-fecha">-</span>
                                </div>
                            </div>
                        </div>

                        <button class="btn-procesar-modulo" id="btn-procesar-recepcion" onclick="mostrarVistaPrevia('recepcion')" disabled>
                            <i class="fa fa-cogs"></i> Procesar Archivo
                        </button>
                    </div>

                    <!-- MÓDULO 3: PAQUETE -->
                    <div class="modulo-card" id="modulo-paquete">
                        <div class="modulo-header">
                            <div class="modulo-icon">
                                <i class="fa fa-cube"></i>
                            </div>
                            <div class="modulo-titulo">
                                <h3>Módulo de Paquete</h3>
                                <p>Archivo Excel con información de paquetes</p>
                            </div>
                        </div>
                        
                        <div class="upload-area" onclick="document.getElementById('file-paquete').click()">
                            <i class="fa fa-cloud-upload"></i>
                            <p>Haz clic para seleccionar archivo</p>
                            <small>Formatos: .xls, .xlsx, .csv (Max: 100MB)</small>
                            <input type="file" id="file-paquete" name="archivo_paquete" style="display: none;" accept=".xls,.xlsx,.csv" onchange="habilitarBotonProcesar('paquete')">
                        </div>

                        <div class="file-info" id="info-paquete" style="display: none;">
                            <div class="file-details">
                                <i class="fa fa-file-excel-o"></i>
                                <div>
                                    <div class="file-name" id="nombre-paquete"></div>
                                    <div class="file-size" id="size-paquete"></div>
                                </div>
                            </div>
                            <button class="btn-remove" onclick="eliminarArchivo('paquete')">
                                <i class="fa fa-times"></i>
                            </button>
                        </div>

                        <div class="data-extracted" id="data-paquete" style="display: none;">
                            <h4><i class="fa fa-database"></i> Datos Extraídos</h4>
                            <div class="data-grid">
                                <div class="data-item">
                                    <span class="label">Total Paquetes</span>
                                    <span class="value" id="paquete-total">-</span>
                                </div>
                                <div class="data-item">
                                    <span class="label">Volumen Total</span>
                                    <span class="value" id="paquete-volumen">-</span>
                                </div>
                                <div class="data-item">
                                    <span class="label">Tipo</span>
                                    <span class="value" id="paquete-tipo">-</span>
                                </div>
                                <div class="data-item">
                                    <span class="label">Peso Unitario</span>
                                    <span class="value" id="paquete-peso">-</span>
                                </div>
                            </div>
                        </div>

                        <button class="btn-procesar-modulo" id="btn-procesar-paquete" onclick="mostrarVistaPrevia('paquete')" disabled>
                            <i class="fa fa-cogs"></i> Procesar Archivo
                        </button>
                    </div>

                    <!-- MÓDULO 4: ALMACENAMIENTO -->
                    <div class="modulo-card" id="modulo-almacen">
                        <div class="modulo-header">
                            <div class="modulo-icon">
                                <i class="fa fa-archive"></i>
                            </div>
                            <div class="modulo-titulo">
                                <h3>Módulo de Almacenamiento</h3>
                                <p>Archivo Excel con información de almacenes</p>
                            </div>
                        </div>
                        
                        <div class="upload-area" onclick="document.getElementById('file-almacen').click()">
                            <i class="fa fa-cloud-upload"></i>
                            <p>Haz clic para seleccionar archivo</p>
                            <small>Formatos: .xls, .xlsx, .csv (Max: 100MB)</small>
                            <input type="file" id="file-almacen" name="archivo_almacen" style="display: none;" accept=".xls,.xlsx,.csv" onchange="habilitarBotonProcesar('almacen')">
                        </div>

                        <div class="file-info" id="info-almacen" style="display: none;">
                            <div class="file-details">
                                <i class="fa fa-file-excel-o"></i>
                                <div>
                                    <div class="file-name" id="nombre-almacen"></div>
                                    <div class="file-size" id="size-almacen"></div>
                                </div>
                            </div>
                            <button class="btn-remove" onclick="eliminarArchivo('almacen')">
                                <i class="fa fa-times"></i>
                            </button>
                        </div>

                        <div class="data-extracted" id="data-almacen" style="display: none;">
                            <h4><i class="fa fa-database"></i> Datos Extraídos</h4>
                            <div class="data-grid">
                                <div class="data-item">
                                    <span class="label">Total Productos</span>
                                    <span class="value" id="almacen-total">-</span>
                                </div>
                                <div class="data-item">
                                    <span class="label">Ubicaciones</span>
                                    <span class="value" id="almacen-ubicaciones">-</span>
                                </div>
                                <div class="data-item">
                                    <span class="label">Stock Total</span>
                                    <span class="value" id="almacen-stock">-</span>
                                </div>
                                <div class="data-item">
                                    <span class="label">Valor Inventario</span>
                                    <span class="value" id="almacen-valor">-</span>
                                </div>
                            </div>
                        </div>

                        <button class="btn-procesar-modulo" id="btn-procesar-almacen" onclick="mostrarVistaPrevia('almacen')" disabled>
                            <i class="fa fa-cogs"></i> Procesar Archivo
                        </button>
                    </div>
                </div>

                <!-- Barra de acciones -->
                <div class="actions-bar">
                    <a href="index.php?cliente=<?php echo urlencode($codigo_cliente); ?>" class="btn-cancelar">
                        <i class="fa fa-times"></i> Cancelar
                    </a>
                    <button class="btn-generar-factura" onclick="generarFactura()">
                        <i class="fa fa-file-text"></i> Generar Factura Completa
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL DE VISTA PREVIA -->
    <div class="modal fade" id="modalVistaPrevia" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-xl" role="document">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: white;">
                    <h4 class="modal-title">
                        <i class="fa fa-file-excel-o"></i> 
                        Vista Previa - <span id="modal-titulo-modulo"></span>
                    </h4>
                    <button type="button" class="close" data-dismiss="modal" style="color: white;">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <!-- SECCIÓN DE FILTROS DE FECHA -->
                    <div class="filter-section" id="filterSection" style="display: none;">
                        <h5><i class="fa fa-calendar"></i> Filtrar por fecha de recepción</h5>
                        <div class="filter-controls">
                            <div class="filter-item">
                                <label>Fecha desde</label>
                                <input type="date" id="filtro-fecha-desde" class="form-control">
                            </div>
                            <div class="filter-item">
                                <label>Fecha hasta</label>
                                <input type="date" id="filtro-fecha-hasta" class="form-control">
                            </div>
                            <div>
                                <button class="btn-filter btn-filter-success" onclick="aplicarFiltroFecha()">
                                    <i class="fa fa-filter"></i> Aplicar
                                </button>
                                <button class="btn-filter btn-filter-secondary" onclick="limpiarFiltroFecha()">
                                    <i class="fa fa-eraser"></i> Limpiar
                                </button>
                            </div>
                        </div>
                        <div class="filter-info" id="infoRangoFechas">
                            <i class="fa fa-info-circle"></i>
                            Rango disponible: <span id="rango-min">-</span> - <span id="rango-max">-</span>
                        </div>
                        <div class="filter-stats" id="filterStats"></div>
                    </div>

                    <div id="chunkedProgress" style="display: none;" class="progress-chunked">
                        <div class="loading-text">
                            <i class="fa fa-cog fa-spin"></i> Procesando archivo grande...
                        </div>
                        <div class="progress">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" 
                                 id="chunkProgressBar" 
                                 role="progressbar" 
                                 style="width: 0%;" 
                                 aria-valuenow="0" 
                                 aria-valuemin="0" 
                                 aria-valuemax="100">
                                0%
                            </div>
                        </div>
                        <div class="small-note">
                            <span id="chunkStatus">Iniciando procesamiento...</span>
                        </div>
                    </div>

                    <div class="table-responsive" style="max-height: 400px; overflow-y: auto;" id="tableContainer">
                        <table class="preview-table" id="tabla-preview">
                            <thead>
                                <tr id="preview-header">
                                    <!-- Se llena dinámicamente -->
                                </tr>
                            </thead>
                            <tbody id="preview-body">
                                <!-- Se llena dinámicamente -->
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="preview-summary" id="previewSummary">
                        <span class="total-label">Total de registros:</span>
                        <span class="total-value" id="total-registros">0</span>
                    </div>

                    <div id="chunkedInfo" style="display: none;" class="alert alert-info">
                        <i class="fa fa-info-circle"></i> 
                        <span id="chunkedMessage"></span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <i class="fa fa-times"></i> Cerrar
                    </button>
                    <button type="button" class="btn btn-success" onclick="confirmarProcesamiento()" id="btn-confirmar" disabled>
                        <i class="fa fa-check"></i> Confirmar y Guardar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- SCRIPTS -->
    <script src="../../vendors/jquery/dist/jquery.min.js"></script>
    <script src="../../vendors/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../build/js/custom.min.js"></script>

    <script>
        // Variables globales
        let archivosSubidos = 0;
        let archivosProcesados = 0;
        const totalModulos = 4;
        let moduloActual = '';
        let datosProcesados = {};
        let filtrosActuales = {
            fecha_desde: '',
            fecha_hasta: ''
        };

        // Función para habilitar botón de procesar
        function habilitarBotonProcesar(tipo) {
            const fileInput = document.getElementById(`file-${tipo}`);
            const btnProcesar = document.getElementById(`btn-procesar-${tipo}`);
            
            if (fileInput.files.length > 0) {
                btnProcesar.disabled = false;
                btnProcesar.classList.add('active');
                mostrarInfoArchivo(tipo);
            } else {
                btnProcesar.disabled = true;
                btnProcesar.classList.remove('active');
            }
        }

        // Función para mostrar información del archivo
        function mostrarInfoArchivo(tipo) {
            const fileInput = document.getElementById(`file-${tipo}`);
            const file = fileInput.files[0];
            
            if (!file) return;

            const extension = file.name.split('.').pop().toLowerCase();
            if (!['xls', 'xlsx', 'csv'].includes(extension)) {
                alert('Formato no válido. Solo se permiten archivos .xls, .xlsx o .csv');
                fileInput.value = '';
                habilitarBotonProcesar(tipo);
                return;
            }

            document.getElementById(`nombre-${tipo}`).textContent = file.name.length > 30 ? 
                file.name.substring(0, 30) + '...' : file.name;
            document.getElementById(`size-${tipo}`).textContent = (file.size / 1024).toFixed(2) + ' KB';
            document.getElementById(`info-${tipo}`).style.display = 'flex';

            actualizarContador();
        }

        // Función para eliminar archivo
        function eliminarArchivo(tipo) {
            document.getElementById(`file-${tipo}`).value = '';
            document.getElementById(`info-${tipo}`).style.display = 'none';
            document.getElementById(`data-${tipo}`).style.display = 'none';
            
            const btnProcesar = document.getElementById(`btn-procesar-${tipo}`);
            btnProcesar.disabled = true;
            btnProcesar.classList.remove('active');
            
            actualizarContador();
        }

        // Función para actualizar contadores
        function actualizarContador() {
            archivosSubidos = 0;
            const tipos = ['despacho', 'recepcion', 'paquete', 'almacen'];
            
            tipos.forEach(tipo => {
                if (document.getElementById(`file-${tipo}`).files.length > 0) {
                    archivosSubidos++;
                }
            });

            document.getElementById('archivosSubidos').textContent = archivosSubidos;
            document.getElementById('archivosProcesados').textContent = archivosProcesados;
            
            const porcentaje = ((archivosSubidos + archivosProcesados) / (totalModulos * 2)) * 100;
            document.getElementById('progressFill').style.width = porcentaje + '%';
        }

        // Función para aplicar filtro de fecha
        function aplicarFiltroFecha() {
            const fechaDesde = document.getElementById('filtro-fecha-desde').value;
            const fechaHasta = document.getElementById('filtro-fecha-hasta').value;
            
            if (fechaDesde && fechaHasta && fechaDesde > fechaHasta) {
                alert('La fecha "desde" no puede ser mayor que la fecha "hasta"');
                return;
            }
            
            filtrosActuales.fecha_desde = fechaDesde;
            filtrosActuales.fecha_hasta = fechaHasta;
            
            // Recargar vista previa con filtros
            mostrarVistaPrevia(moduloActual);
        }

        // Función para limpiar filtro de fecha
        function limpiarFiltroFecha() {
            document.getElementById('filtro-fecha-desde').value = '';
            document.getElementById('filtro-fecha-hasta').value = '';
            filtrosActuales.fecha_desde = '';
            filtrosActuales.fecha_hasta = '';
            mostrarVistaPrevia(moduloActual);
        }

        // Función para actualizar información de rango de fechas
        function actualizarInfoRangoFechas(stats) {
            const rangoMin = document.getElementById('rango-min');
            const rangoMax = document.getElementById('rango-max');
            const filterStats = document.getElementById('filterStats');
            
            if (rangoMin && rangoMax) {
                rangoMin.textContent = stats.fecha_min || 'No disponible';
                rangoMax.textContent = stats.fecha_max || 'No disponible';
            }
            
            // Mostrar estadísticas de filtros
            if (filterStats) {
                let statsHtml = '';
                if (stats.filas_filtradas_cantidad > 0) {
                    statsHtml += `<span class="filter-stat-badge"><i class="fa fa-filter"></i> ${stats.filas_filtradas_cantidad} filtrados por cantidad cero</span>`;
                }
                if (stats.filas_filtradas_fecha > 0) {
                    statsHtml += `<span class="filter-stat-badge"><i class="fa fa-calendar-times-o"></i> ${stats.filas_filtradas_fecha} filtrados por fecha</span>`;
                }
                if (stats.filtros_aplicados && (stats.filtros_aplicados.fecha_desde || stats.filtros_aplicados.fecha_hasta)) {
                    statsHtml += `<span class="filter-stat-badge"><i class="fa fa-calendar-check-o"></i> Filtro activo: ${stats.filtros_aplicados.fecha_desde || '?'} - ${stats.filtros_aplicados.fecha_hasta || '?'}</span>`;
                }
                filterStats.innerHTML = statsHtml;
            }
        }

        // Función para mostrar vista previa - VERSIÓN CON FILTROS
        function mostrarVistaPrevia(tipo) {
            moduloActual = tipo;
            
            const fileInput = document.getElementById(`file-${tipo}`);
            if (!fileInput.files[0]) {
                alert('Debe seleccionar un archivo primero');
                return;
            }
            
            const file = fileInput.files[0];
            
            // Títulos para cada módulo
            const titulos = {
                despacho: 'Despachos',
                recepcion: 'Recepciones',
                paquete: 'Paquetes',
                almacen: 'Almacenamiento'
            };
            
            document.getElementById('modal-titulo-modulo').textContent = titulos[tipo];
            
            // Mostrar sección de filtros solo para recepción
            const filterSection = document.getElementById('filterSection');
            if (tipo === 'recepcion') {
                filterSection.style.display = 'block';
            } else {
                filterSection.style.display = 'none';
            }
            
            // Mostrar loading
            document.getElementById('preview-body').innerHTML = 
                '<tr><td colspan="20" class="text-center">' +
                '<i class="fa fa-spinner fa-spin fa-3x"></i><br><br>' +
                'Procesando archivo...' +
                '</td></tr>';
            document.getElementById('preview-header').innerHTML = '';
            document.getElementById('btn-confirmar').disabled = true;
            
            // Mostrar modal
            $('#modalVistaPrevia').modal('show');
            
            // Crear FormData
            const formData = new FormData();
            formData.append('archivo', file);
            
            // Agregar filtros de fecha si existen
            if (filtrosActuales.fecha_desde) {
                formData.append('fecha_desde', filtrosActuales.fecha_desde);
            }
            if (filtrosActuales.fecha_hasta) {
                formData.append('fecha_hasta', filtrosActuales.fecha_hasta);
            }
            
            // URL del controller
            let controllerUrl = '';
            if (tipo === 'recepcion') {
                controllerUrl = '../../controller/arcor/recep.arcor.scz.php';
            } else {
                alert('El módulo de ' + tipo + ' estará disponible próximamente');
                $('#modalVistaPrevia').modal('hide');
                return;
            }
            
            console.log('Enviando archivo:', file.name);
            console.log('URL:', controllerUrl);
            console.log('Filtros:', filtrosActuales);
            
            // Hacer fetch
            fetch(controllerUrl, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('Status:', response.status);
                console.log('Status Text:', response.statusText);
                
                if (!response.ok) {
                    throw new Error('Error HTTP: ' + response.status);
                }
                
                return response.text();
            })
            .then(text => {
                console.log('Respuesta raw:', text.substring(0, 500));
                
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('Error parseando JSON:', e);
                    console.error('Respuesta completa:', text);
                    
                    if (text.trim().startsWith('<')) {
                        throw new Error('El servidor devolvió HTML en lugar de JSON. Posible error PHP.');
                    } else {
                        throw new Error('La respuesta no es JSON válido: ' + e.message);
                    }
                }
            })
            .then(data => {
                console.log('Datos recibidos:', data);
                
                if (data.error) {
                    alert('Error: ' + data.error);
                    $('#modalVistaPrevia').modal('hide');
                    return;
                }
                
                // Guardar datos
                datosProcesados[tipo] = data;
                
                // Actualizar estadísticas
                if (data.stats) {
                    actualizarEstadisticasModulo(tipo, data.stats);
                    actualizarInfoRangoFechas(data.stats);
                }
                
                // Mostrar mensajes de filtrado
                if (data.mensaje) {
                    console.log(data.mensaje);
                }
                if (data.mensaje_fecha) {
                    console.log(data.mensaje_fecha);
                }
                
                // Generar tabla
                generarTablaPreview(tipo, data);
                
                // Habilitar botón
                document.getElementById('btn-confirmar').disabled = false;
            })
            .catch(error => {
                console.error('Error completo:', error);
                alert('Error al procesar: ' + error.message);
                $('#modalVistaPrevia').modal('hide');
            });
        }

        // Función para generar tabla preview
        function generarTablaPreview(tipo, data) {
            const header = document.getElementById('preview-header');
            const body = document.getElementById('preview-body');
            
            header.innerHTML = '';
            body.innerHTML = '';
            
            // Usar headers del archivo
            const headers = data.headers || [];
            
            // Si no hay headers, crear algunos por defecto
            if (headers.length === 0 && data.data && data.data.length > 0) {
                for (let i = 0; i < data.data[0].length; i++) {
                    headers.push('Columna ' + (i + 1));
                }
            }
            
            // Crear header
            headers.forEach(col => {
                const th = document.createElement('th');
                th.textContent = col || 'Columna';
                header.appendChild(th);
            });
            
            // Crear body con datos
            if (data.data && data.data.length > 0) {
                data.data.forEach(row => {
                    const tr = document.createElement('tr');
                    
                    // Asegurar que row sea array
                    const rowData = Array.isArray(row) ? row : [row];
                    
                    rowData.forEach(cell => {
                        const td = document.createElement('td');
                        let valor = cell;
                        
                        // Truncar texto largo
                        if (typeof valor === 'string' && valor.length > 50) {
                            valor = valor.substring(0, 50) + '...';
                        }
                        
                        td.textContent = valor || '-';
                        tr.appendChild(td);
                    });
                    
                    body.appendChild(tr);
                });
            } else {
                const tr = document.createElement('tr');
                const td = document.createElement('td');
                td.colSpan = headers.length || 1;
                td.className = 'text-center';
                td.textContent = 'No hay datos para mostrar';
                tr.appendChild(td);
                body.appendChild(tr);
            }
            
            document.getElementById('total-registros').textContent = data.total_registros || 0;
        }

        // Función para actualizar estadísticas
        function actualizarEstadisticasModulo(tipo, stats) {
            if (tipo === 'recepcion') {
                document.getElementById('recepcion-total').textContent = stats.total_filas || 0;
                document.getElementById('recepcion-fecha').textContent = stats.mostrando || 0;
            }
            
            document.getElementById(`data-${tipo}`).style.display = 'block';
        }

        // Función para confirmar procesamiento
        function confirmarProcesamiento() {
            archivosProcesados++;
            document.getElementById('archivosProcesados').textContent = archivosProcesados;
            
            const btnProcesar = document.getElementById(`btn-procesar-${moduloActual}`);
            btnProcesar.disabled = true;
            btnProcesar.classList.remove('active');
            
            document.getElementById(`file-${moduloActual}`).disabled = true;
            
            $('#modalVistaPrevia').modal('hide');
            
            actualizarContador();
            
            const totalRegistros = datosProcesados[moduloActual]?.total_registros || 0;
            const stats = datosProcesados[moduloActual]?.stats || {};
            
            let mensaje = `Archivo procesado correctamente. Se encontraron ${totalRegistros} registros.`;
            if (stats.filas_filtradas_cantidad > 0) {
                mensaje += `\nSe filtraron ${stats.filas_filtradas_cantidad} registros con cantidad cero.`;
            }
            if (stats.filas_filtradas_fecha > 0) {
                mensaje += `\nSe filtraron ${stats.filas_filtradas_fecha} registros por rango de fechas.`;
            }
            
            alert(mensaje);
            
            // Limpiar filtros
            filtrosActuales = { fecha_desde: '', fecha_hasta: '' };
        }

        // Función para generar factura
        function generarFactura() {
            if (archivosProcesados === 0) {
                alert('Debe procesar al menos un módulo');
                return;
            }
            
            alert('Factura generada correctamente');
        }

        // Toggle del menú
        document.getElementById('menu_toggle').addEventListener('click', function() {
            document.querySelector('.left_col').classList.toggle('menu-open');
        });
    </script>
</body>
</html>