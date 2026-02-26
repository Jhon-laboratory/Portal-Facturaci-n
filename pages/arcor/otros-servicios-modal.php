<!-- MODAL DE OTROS SERVICIOS -->
<div class="modal fade" id="modalOtrosServicios" tabindex="-1" role="dialog" data-backdrop="static">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header" style="border-bottom: 2px solid #009a3f;">
                <h4 class="modal-title">Otros servicios</h4>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-bordered" id="tablaServicios">
                        <thead style="background-color: #f5f5f5;">
                            <tr>
                                <th>SERVICIO</th>
                                <th>TARIFA</th>
                                <th>CANTIDAD</th>
                                <th>TOTAL</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="servicios-body">
                            <!-- Se llena dinámicamente -->
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="5" class="text-center">
                                    <button class="btn btn-link" onclick="agregarServicio()" style="color: #009a3f;">
                                        <i class="fa fa-plus"></i> + nuevo servicio
                                    </button>
                                </td>
                            </tr>
                            <tr style="background-color: #f9f9f9; font-weight: bold;">
                                <td colspan="3" class="text-right">TOTAL</td>
                                <td id="total-servicios">Bs 0,00</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <div class="modal-footer" style="border-top: 1px solid #dee2e6;">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">cancelar</button>
                <button type="button" class="btn" style="background: #009a3f; color: white;" onclick="guardarServicios()">registrar</button>
            </div>
        </div>
    </div>
</div>