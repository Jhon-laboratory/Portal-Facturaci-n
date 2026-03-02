<?php
// ocupabilidad-modal.php
?>
<!-- MODAL DE OCUPABILIDAD -->
<div class="modal fade" id="modalOcupabilidad" tabindex="-1" role="dialog" data-backdrop="static">
    <div class="modal-dialog" role="document" style="max-width: 600px;">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: white; border-radius: 10px 10px 0 0;">
                <h5 class="modal-title">
                    <i class="fa fa-archive"></i> Ocupabilidad - Posiciones de Rack
                </h5>
                <button type="button" class="close" data-dismiss="modal" style="color: white; opacity: 1;">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body" style="padding: 25px;">
                
                
                <div class="table-responsive">
                    <table class="table table-bordered" id="tablaOcupabilidad">
                        <thead style="background-color: #f5f5f5;">
                            <tr>
                                <th style="width: 40%;">TIPO DE UBICACIÓN</th>
                                <th style="width: 25%;">CANTIDAD</th>
                                <th style="width: 25%;">TOTAL</th>
                                <th style="width: 10%;"></th>
                            </tr>
                        </thead>
                        <tbody id="ocupabilidad-body">
                            <!-- Se llena dinámicamente -->
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="4" class="text-center">
                                    <button class="btn btn-link" onclick="agregarUbicacion()" style="color: #009a3f; text-decoration: none;">
                                        <i class="fa fa-plus-circle"></i> + Agregar otro tipo de ubicación
                                    </button>
                                </td>
                            </tr>
                            <tr style="background-color: #f9f9f9; font-weight: bold;">
                                <td colspan="2" class="text-right">TOTAL POSICIONES</td>
                                <td id="total-ocupabilidad">0</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

            </div>
            <div class="modal-footer" style="border-top: 2px solid #e9ecef; padding: 15px 25px;">
                <button type="button" class="btn btn-secondary" data-dismiss="modal" style="border-radius: 50px; padding: 8px 25px;">
                    <i class="fa fa-times"></i> Cancelar
                </button>
                <button type="button" class="btn" onclick="guardarOcupabilidad()" 
                        style="background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: white; border-radius: 50px; padding: 8px 30px;">
                    <i class="fa fa-save"></i> Guardar Ocupabilidad
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    #tablaOcupabilidad tbody tr {
        transition: background-color 0.3s ease;
    }
    #tablaOcupabilidad tbody tr:hover {
        background-color: #f0f9f0;
    }
    #tablaOcupabilidad input[type="text"],
    #tablaOcupabilidad input[type="number"] {
        border: 1px solid #ced4da;
        border-radius: 6px;
        padding: 8px 12px;
        width: 100%;
        transition: border-color 0.3s ease;
    }
    #tablaOcupabilidad input[type="text"]:focus,
    #tablaOcupabilidad input[type="number"]:focus {
        border-color: var(--primary-color);
        outline: none;
        box-shadow: 0 0 0 3px rgba(0,154,63,0.1);
    }
    #tablaOcupabilidad .btn-remove-ubicacion {
        color: #dc3545;
        background: none;
        border: none;
        font-size: 16px;
        cursor: pointer;
        padding: 5px 10px;
        border-radius: 50%;
        transition: all 0.3s ease;
    }
    #tablaOcupabilidad .btn-remove-ubicacion:hover {
        background-color: #dc3545;
        color: white;
    }
    #total-ocupabilidad {
        font-size: 18px;
        font-weight: 600;
        color: var(--primary-color);
    }
</style>