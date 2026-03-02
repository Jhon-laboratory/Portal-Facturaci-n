<?php
session_start();
$_SESSION['user_id'] = 1; // Simular sesión

require_once '../../conexion/config.php';

// Obtener facturas para el selector
$conn = getDBConnection();
$sql_facturas = "SELECT id, 'FAC-' + RIGHT('00000' + CAST(id AS VARCHAR), 6) as nro_factura 
                 FROM " . TABLA_FACTURAS . " 
                 ORDER BY id DESC";
$facturas = $conn->query($sql_facturas)->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Modal con Datos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        .table-sm { font-size: 13px; }
        .bg-verde { background: #009a3f; color: white; }
        .bg-verde-claro { background: #e8f5e9; }
        .cantidad-input { width: 70px; text-align: right; }
        .editable { background: #fff3cd; }
    </style>
</head>
<body>
    <div class="container mt-5">
        <div class="card">
            <div class="card-header bg-verde">
                <h4>🔍 TEST - Cargar Resumen de Factura</h4>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <label>Seleccionar Factura:</label>
                        <select id="selectFactura" class="form-control">
                            <option value="">-- Seleccione --</option>
                            <?php foreach ($facturas as $f): ?>
                                <option value="<?php echo $f['id']; ?>"><?php echo $f['nro_factura']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 d-flex align-items-end">
                        <button class="btn btn-success" onclick="cargarResumen()">
                            <i class="fa fa-search"></i> Cargar Resumen
                        </button>
                        <button class="btn btn-primary ml-2" onclick="abrirModalVacio()">
                            <i class="fa fa-window-maximize"></i> Abrir Modal Vacío
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Resultado directo -->
        <div class="card mt-3">
            <div class="card-header bg-info text-white">
                <h5>📋 Resultado Directo</h5>
            </div>
            <div class="card-body" id="resultadoDirecto">
                <p class="text-muted">Seleccione una factura y haga clic en "Cargar Resumen"</p>
            </div>
        </div>
    </div>

    <!-- Modal -->
    <div class="modal fade" id="modalResumen" tabindex="-1" data-backdrop="static">
        <div class="modal-dialog modal-xl">
            <div class="modal-content" id="modalContent">
                <!-- Contenido cargado vía AJAX -->
            </div>
        </div>
    </div>

    <script>
    function abrirModalVacio() {
        $('#modalContent').html(`
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">Modal Vacío</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <p>Este modal no tiene datos</p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
            </div>
        `);
        $('#modalResumen').modal('show');
    }

    function cargarResumen() {
        const factura_id = $('#selectFactura').val();
        
        if (!factura_id) {
            alert('Seleccione una factura');
            return;
        }

        // Mostrar carga en resultado directo
        $('#resultadoDirecto').html(`
            <div class="text-center p-4">
                <i class="fa fa-spinner fa-spin fa-3x text-success"></i>
                <p class="mt-2">Cargando factura ${factura_id}...</p>
            </div>
        `);

        // 1. PRIMERO: Cargar datos directamente (sin modal)
        $.ajax({
            url: 'get_resumen_data.php',
            method: 'GET',
            data: { factura_id: factura_id },
            dataType: 'html',
            success: function(response) {
                console.log('✅ Datos recibidos, longitud:', response.length);
                $('#resultadoDirecto').html(`
                    <div class="alert alert-success">
                        <i class="fa fa-check-circle"></i> Datos cargados correctamente
                        (${response.length} caracteres)
                    </div>
                    <div class="border p-3" style="max-height: 400px; overflow: auto;">
                        ${response}
                    </div>
                `);
                
                // 2. SEGUNDO: Cargar en el modal
                $('#modalContent').html(response);
                $('#modalResumen').modal('show');
            },
            error: function(xhr, status, error) {
                console.error('❌ Error:', status, error);
                console.error('Respuesta:', xhr.responseText);
                
                $('#resultadoDirecto').html(`
                    <div class="alert alert-danger">
                        <h5><i class="fa fa-exclamation-triangle"></i> Error</h5>
                        <p><strong>Status:</strong> ${xhr.status} - ${status}</p>
                        <p><strong>Error:</strong> ${error}</p>
                        <p><strong>URL:</strong> get_resumen_data.php?factura_id=${factura_id}</p>
                        <hr>
                        <pre class="text-white bg-dark p-2">${xhr.responseText || 'Sin respuesta'}</pre>
                    </div>
                `);
            }
        });
    }

    // Probar carga directa de una factura específica
    function testFactura(id) {
        $('#selectFactura').val(id);
        cargarResumen();
    }
    </script>

    <!-- Botones de prueba rápida -->
    <div class="container mt-2">
        <div class="btn-group">
            <button class="btn btn-outline-success btn-sm" onclick="testFactura(1)">Factura 1</button>
            <button class="btn btn-outline-success btn-sm" onclick="testFactura(2)">Factura 2</button>
            <button class="btn btn-outline-success btn-sm" onclick="testFactura(3)">Factura 3</button>
        </div>
    </div>
</body>
</html>