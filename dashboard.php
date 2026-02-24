<?php
// dashboard.php
session_start();
require_once 'conexion/config.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Variables de sesión con valores por defecto
$usuario_nombre = $_SESSION['user_name'] ?? 'Usuario';
$usuario_correo = $_SESSION['user_email'] ?? '';
$usuario_tipo = $_SESSION['user_type'] ?? 'usuario';
$usuario_ciudad = $_SESSION['user_ciudad'] ?? 'N/A';
$usuario_pais = $_SESSION['user_pais'] ?? 'N/A';
$usuario_color = $_SESSION['user_color'] ?? '#009a3f';
$id_perfil = $_SESSION['id_perfil'] ?? 0;
$user_area = $_SESSION['user_area'] ?? '';
$user_subarea = $_SESSION['user_subarea'] ?? '';

// Obtener datos adicionales del usuario (firma, cédula)
$firma = '';
$cedula = '';

// Mostrar mensaje si viene por parámetro
$mensaje = isset($_GET['msg']) ? urldecode($_GET['msg']) : '';

// Obtener clientes de la base de datos
$clientes = [];
$error_db = null;

try {
    $conn = getDBConnection();
    
    // Obtener datos adicionales del usuario
    $sql = "SELECT firma, cedula FROM IT.usuarios_pt WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$_SESSION['user_id']]);
    $user_data = $stmt->fetch();
    
    if ($user_data) {
        $firma = $user_data['firma'] ?? '';
        $cedula = $user_data['cedula'] ?? '';
    }
    
    // Obtener clientes - CORREGIDO: eliminada referencia a columna 'estado' que no existe
    $query = "SELECT id, codigo_cliente, nombre_comercial, logo_png 
              FROM [FacBol].[clientes] 
              ORDER BY nombre_comercial";
    
    $stmt = $conn->query($query);
    $clientes = $stmt->fetchAll();
    
} catch (Exception $e) {
    $error_db = "Error de conexión: " . $e->getMessage();
    error_log("Error en dashboard: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard - Clientes RANSA</title>

    <!-- CSS del template -->
    <link href="vendors/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="vendors/font-awesome/css/font-awesome.min.css" rel="stylesheet">
    <link href="vendors/nprogress/nprogress.css" rel="stylesheet">
    <link href="vendors/iCheck/skins/flat/green.css" rel="stylesheet">
    <link href="vendors/select2/dist/css/select2.min.css" rel="stylesheet">
    <link href="vendors/bootstrap-progressbar/css/bootstrap-progressbar-3.3.4.min.css" rel="stylesheet">
    <link href="vendors/datatables.net-bs/css/dataTables.bootstrap.min.css" rel="stylesheet">
    <link href="build/css/custom.min.css" rel="stylesheet">

    <style>
        /* Fondo para la página */
        body.nav-md {
            background: linear-gradient(rgba(245, 247, 250, 0.97), rgba(245, 247, 250, 0.97)), 
                        url('img/imglogin.jpg') center/cover no-repeat fixed;
            min-height: 100vh;
        }
        
        /* Estilos para las tarjetas de clientes */
        .client-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            margin-bottom: 25px;
            background: white;
            overflow: hidden;
            height: 220px;
            display: flex;
            flex-direction: column;
            cursor: pointer;
        }
        
        .client-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,154,63,0.2);
        }
        
        .client-header {
            background: linear-gradient(135deg, <?php echo $usuario_color; ?> 0%, <?php echo $usuario_color; ?> 100%);
            padding: 25px 15px 15px 15px;
            text-align: center;
            color: white;
            height: 140px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .client-logo {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            background: white;
            padding: 8px;
            object-fit: contain;
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
            border: 3px solid white;
        }
        
        .client-body {
            padding: 15px;
            text-align: center;
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .client-title {
            font-size: 18px;
            font-weight: bold;
            color: #333;
            margin: 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            width: 100%;
        }
        
        /* Buscador */
        .search-box {
            margin-bottom: 20px;
        }
        
        #buscarCliente {
            border-radius: 20px 0 0 20px;
            border: 2px solid #eee;
            border-right: none;
            height: 40px;
        }
        
        #buscarCliente:focus {
            border-color: <?php echo $usuario_color; ?>;
            box-shadow: none;
        }
        
        .input-group-btn .btn {
            border-radius: 0 20px 20px 0;
            border: 2px solid #eee;
            border-left: none;
            background: white;
            color: <?php echo $usuario_color; ?>;
            height: 40px;
        }
        
        .input-group-btn .btn:hover {
            background: <?php echo $usuario_color; ?>;
            color: white;
            border-color: <?php echo $usuario_color; ?>;
        }
        
        /* Perfil de usuario mejorado */
        .user-profile-card {
            background: linear-gradient(135deg, <?php echo $usuario_color; ?> 0%, <?php echo $usuario_color; ?> 100%);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            color: white;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .user-profile-card i {
            margin-right: 10px;
            opacity: 0.9;
        }
        
        .user-profile-card .user-name {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 5px;
            word-break: break-word;
        }
        
        .user-profile-card .user-detail {
            font-size: 14px;
            opacity: 0.9;
            margin-bottom: 3px;
            word-break: break-word;
        }
        
        .user-badge {
            background: rgba(255,255,255,0.2);
            border-radius: 20px;
            padding: 5px 15px;
            font-size: 12px;
            display: inline-block;
            margin-top: 10px;
        }
        
        /* Responsividad */
        @media (max-width: 768px) {
            .left_col {
                display: block !important;
                position: fixed;
                z-index: 1000;
                height: 100%;
                overflow-y: auto;
            }
            
            .right_col {
                margin-left: 0 !important;
                width: 100% !important;
                padding: 15px !important;
            }
            
            .client-card {
                height: 200px;
            }
            
            .client-header {
                height: 120px;
                padding: 15px;
            }
            
            .client-logo {
                width: 70px;
                height: 70px;
            }
            
            .client-title {
                font-size: 16px;
            }
            
            .user-profile-card {
                padding: 15px;
            }
            
            .user-profile-card .user-name {
                font-size: 18px;
            }
        }
        
        @media (max-width: 480px) {
            .client-title {
                font-size: 14px;
            }
            
            .user-profile-card .user-name {
                font-size: 16px;
            }
            
            .user-profile-card .user-detail {
                font-size: 12px;
            }
        }

        /* Estilo para el footer */
        footer {
            margin-top: 20px;
            padding: 15px;
            background: rgba(0, 154, 63, 0.05);
            border-radius: 8px;
            font-size: 11px;
            border-top: 1px solid #e0e0e0;
        }
    </style>
</head>

<body class="nav-md">
    <div class="container body">
        <div class="main_container">
            <!-- SIDEBAR MEJORADO -->
            <div class="col-md-3 left_col">
                <div class="left_col scroll-view">
                    <div class="navbar nav_title" style="border: 0;">
                        <a href="dashboard.php" class="site_title">
                            <img src="img/logo.png" alt="RANSA Logo" style="height: 32px;" onerror="this.src='img/default-logo.png'">
                        </a>
                    </div>
                    <div class="clearfix"></div>

                    <!-- Información del usuario mejorada -->
                    <div class="user-profile-card">
                        <div class="user-name">
                            <i class="fa fa-user-circle"></i> <?php echo htmlspecialchars($usuario_nombre); ?>
                        </div>
                        
                    </div>

                    <!-- MENU -->
                    <div id="sidebar-menu" class="main_menu_side hidden-print main_menu">
                        <div class="menu_section">
                            <h3>Navegación</h3>
                            <ul class="nav side-menu">
                                <li class="active">
                                    <a href="dashboard.php"><i class="fa fa-dashboard"></i> Dashboard</a>
                                </li>
                                <li>
                                    <a href="ingreso.php"><i class="fa fa-sign-in"></i> Ingreso</a>
                                </li>
                                <li>
                                    <a href="translado.php"><i class="fa fa-exchange"></i> Traslado</a>
                                </li>
                                <li>
                                    <a href="reportes.php"><i class="fa fa-file-text"></i> Reportes</a>
                                </li>
                            </ul>
                        </div>
                    </div>
                    
                    <!-- FOOTER DEL SIDEBAR -->
                    <div class="sidebar-footer hidden-small">
                        <a title="Actualizar" data-toggle="tooltip" data-placement="top" onclick="location.reload()" style="cursor: pointer;">
                            <span class="glyphicon glyphicon-refresh"></span>
                        </a>
                        <a title="Salir" data-toggle="tooltip" data-placement="top" href="logout.php">
                            <span class="glyphicon glyphicon-off"></span>
                        </a>
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
                        <span style="color: white; padding: 15px; font-weight: 600;">
                            <i class="fa fa-user-circle"></i> 
                            <?php echo htmlspecialchars($usuario_nombre); ?>
                            <small style="opacity: 0.8; margin-left: 10px;">
                                <i class="fa fa-map-marker"></i> 
                                <?php echo htmlspecialchars($usuario_ciudad); ?>
                            </small>
                        </span>
                    </div>
                </div>
            </div>

            <!-- CONTENIDO PRINCIPAL -->
            <div class="right_col" role="main">
                <div class="page-title">
                    <div class="title_left">
                        <h3><i class="fa fa-building"></i> Clientes</h3>
                    </div>
                    <div class="title_right">
                        <div class="col-md-5 col-sm-5 col-xs-12 form-group pull-right top_search">
                            <div class="input-group">
                                <input type="text" class="form-control" placeholder="Buscar cliente..." id="buscarCliente">
                                <span class="input-group-btn">
                                    <button class="btn btn-default" type="button" id="btnBuscar">
                                        <i class="fa fa-search"></i>
                                    </button>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="clearfix"></div>

                <?php if (isset($error_db) && $error_db): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fa fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error_db); ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                <?php endif; ?>

                <?php if ($mensaje): ?>
                    <div class="alert alert-info alert-dismissible fade show" role="alert">
                        <i class="fa fa-info-circle"></i> <?php echo htmlspecialchars($mensaje); ?>
                        <button type="button" class="close" data-dismiss="alert">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                <?php endif; ?>

                <!-- Tarjetas de clientes -->
                <div class="row" id="clientesContainer">
                    <?php if (empty($clientes)): ?>
                        <div class="col-md-12">
                            <div class="alert alert-info text-center">
                                <i class="fa fa-info-circle fa-2x"></i>
                                <p>No hay clientes activos registrados</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($clientes as $cliente): ?>
                            <div class="col-lg-3 col-md-4 col-sm-6 col-xs-12 cliente-item" 
                                 data-nombre="<?php echo strtolower(htmlspecialchars($cliente['nombre_comercial'])); ?>"
                                 data-codigo="<?php echo strtolower(htmlspecialchars($cliente['codigo_cliente'])); ?>">
                                <div class="client-card" onclick="verCliente('<?php echo $cliente['codigo_cliente']; ?>')">
                                    <div class="client-header">
                                        <?php
                                        $logo = !empty($cliente['logo_png']) ? $cliente['logo_png'] : 'default.png';
                                        $logo_path = "img/" . $logo;
                                        ?>
                                        <img src="<?php echo $logo_path; ?>" alt="Logo" class="client-logo" 
                                             onerror="this.src='img/default.png'; this.onerror=null;">
                                    </div>
                                    <div class="client-body">
                                        <div class="client-title" title="<?php echo htmlspecialchars($cliente['nombre_comercial']); ?>">
                                            <?php echo htmlspecialchars($cliente['nombre_comercial']); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Mensaje de resultados de búsqueda -->
                <div class="row" id="noResultados" style="display: none;">
                    <div class="col-md-12">
                        <div class="alert alert-warning text-center">
                            <i class="fa fa-exclamation-circle fa-2x"></i>
                            <p>No se encontraron clientes que coincidan con la búsqueda</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- FOOTER -->
            <footer>
                <div class="pull-right">
                    <i class="fa fa-clock-o"></i>
                    Sistema Ransa Archivo - Bolivia | Usuario: <?php echo htmlspecialchars($usuario_nombre); ?> | 
                    Fecha: <?php echo date('d/m/Y H:i:s'); ?>
                </div>
                <div class="clearfix"></div>
            </footer>
        </div>
    </div>

    <!-- SCRIPTS -->
    <script src="vendors/jquery/dist/jquery.min.js"></script>
    <script src="vendors/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
    <script src="vendors/fastclick/lib/fastclick.js"></script>
    <script src="vendors/nprogress/nprogress.js"></script>
    <script src="build/js/custom.min.js"></script>

    <script>
        // Función para ver detalle del cliente - CORREGIDO: apunta a la ruta correcta
        function verCliente(codigoCliente) {
            if (codigoCliente) {
                window.location.href = 'pages/arcor/index.php?cliente=' + encodeURIComponent(codigoCliente);
            }
        }

        // Toggle del menú para móviles
        document.getElementById('menu_toggle').addEventListener('click', function() {
            const leftCol = document.querySelector('.left_col');
            if (leftCol) {
                leftCol.classList.toggle('menu-open');
            }
        });

        // Buscador de clientes en tiempo real
        document.getElementById('buscarCliente').addEventListener('keyup', function() {
            filtrarClientes(this.value);
        });

        document.getElementById('btnBuscar').addEventListener('click', function() {
            filtrarClientes(document.getElementById('buscarCliente').value);
        });

        function filtrarClientes(busqueda) {
            busqueda = busqueda.toLowerCase().trim();
            const clientes = document.querySelectorAll('.cliente-item');
            let visibleCount = 0;
            
            clientes.forEach(cliente => {
                const nombre = cliente.getAttribute('data-nombre') || '';
                const codigo = cliente.getAttribute('data-codigo') || '';
                
                if (busqueda === '' || nombre.includes(busqueda) || codigo.includes(busqueda)) {
                    cliente.style.display = '';
                    visibleCount++;
                } else {
                    cliente.style.display = 'none';
                }
            });
            
            const noResultados = document.getElementById('noResultados');
            if (noResultados) {
                if (visibleCount === 0 && busqueda !== '') {
                    noResultados.style.display = 'block';
                } else {
                    noResultados.style.display = 'none';
                }
            }
        }

        // Animación de entrada
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-focus en el buscador
            const buscador = document.getElementById('buscarCliente');
            if (buscador) {
                setTimeout(() => buscador.focus(), 500);
            }
            
            // Animación para las tarjetas
            const cards = document.querySelectorAll('.client-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, 100 + (index * 50));
            });
        });

        // Prevenir comportamiento por defecto en los botones del sidebar
        document.querySelectorAll('.sidebar-footer a').forEach(link => {
            link.addEventListener('click', function(e) {
                if (this.getAttribute('onclick') === 'location.reload()') {
                    e.preventDefault();
                    location.reload();
                }
            });
        });
    </script>
</body>
</html>