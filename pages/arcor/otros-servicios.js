// Variables globales
let serviciosData = [];
let tarifasDisponibles = [];

// Función de formato (DEFINIDA AL PRINCIPIO)
function formatearMoneda(valor) {
    return valor.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

// Cargar tarifas al iniciar
function cargarTarifas() {
    fetch('../../controller/arcor/get_tarifas_servicios.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                tarifasDisponibles = data.data.map(t => ({
                    servicio: t.servicio,
                    tarifa1: parseFloat(t.tarifa1) || 0,
                    tarifa2: parseFloat(t.tarifa2) || 0,
                    tarifa3: parseFloat(t.tarifa3) || 0,
                    tarifa4: parseFloat(t.tarifa4) || 0,
                    tarifa5: parseFloat(t.tarifa5) || 0
                }));
                cargarServiciosIniciales();
            }
        });
}

// Cargar servicios iniciales
function cargarServiciosIniciales() {
    if (tarifasDisponibles.length === 0) {
        setTimeout(cargarServiciosIniciales, 100);
        return;
    }
    serviciosData = tarifasDisponibles.map(t => ({
        servicio: t.servicio,
        tarifa1: t.tarifa1, tarifa2: t.tarifa2, tarifa3: t.tarifa3,
        tarifa4: t.tarifa4, tarifa5: t.tarifa5,
        tarifa: t.tarifa1, cantidad: 0, personalizado: false
    }));
    renderizarTabla();
}

// Abrir modal
function abrirModalOtrosServicios() {
    moduloActual = 'paquete';
    if (datosProcesados['paquete']?.servicios) {
        serviciosData = JSON.parse(JSON.stringify(datosProcesados['paquete'].servicios));
    } else cargarServiciosIniciales();
    renderizarTabla();
    $('#modalOtrosServicios').modal('show');
}

// Renderizar tabla compacta
function renderizarTabla() {
    const tbody = document.getElementById('servicios-body');
    tbody.innerHTML = '';
    let totalGeneral = 0;
    
    serviciosData.forEach((item, i) => {
        const total = item.tarifa * item.cantidad;
        totalGeneral += total;
        const fila = document.createElement('tr');
        
        if (!item.personalizado) {
            let opts = '';
            if (item.tarifa1 > 0) opts += `<option value="${item.tarifa1}" ${item.tarifa==item.tarifa1?'selected':''}>Bs ${item.tarifa1.toFixed(2).replace('.',',')}</option>`;
            if (item.tarifa2 > 0) opts += `<option value="${item.tarifa2}" ${item.tarifa==item.tarifa2?'selected':''}>Bs ${item.tarifa2.toFixed(2).replace('.',',')}</option>`;
            if (item.tarifa3 > 0) opts += `<option value="${item.tarifa3}" ${item.tarifa==item.tarifa3?'selected':''}>Bs ${item.tarifa3.toFixed(2).replace('.',',')}</option>`;
            if (item.tarifa4 > 0) opts += `<option value="${item.tarifa4}" ${item.tarifa==item.tarifa4?'selected':''}>Bs ${item.tarifa4.toFixed(2).replace('.',',')}</option>`;
            if (item.tarifa5 > 0) opts += `<option value="${item.tarifa5}" ${item.tarifa==item.tarifa5?'selected':''}>Bs ${item.tarifa5.toFixed(2).replace('.',',')}</option>`;
            
            fila.innerHTML = `
                <td style="padding:4px">${item.servicio}</td>
                <td style="padding:4px"><select class="form-control form-control-sm" onchange="cambiarTarifa(this,${i})" style="width:90px; font-size:12px; padding:2px">${opts}</select></td>
                <td style="padding:4px"><input type="number" class="form-control form-control-sm" value="${item.cantidad}" step="0.01" min="0" oninput="cambiarCantidad(this,${i})" style="width:70px; font-size:12px; padding:2px"></td>
                <td class="text-right" style="padding:4px; vertical-align:middle">Bs ${formatearMoneda(total)}</td>
                <td style="padding:4px"></td>
            `;
        } else {
            fila.innerHTML = `
                <td style="padding:4px"><input type="text" class="form-control form-control-sm" value="${item.servicio}" placeholder="Servicio" oninput="cambiarServicio(this,${i})" style="font-size:12px; padding:2px"></td>
                <td style="padding:4px"><input type="number" class="form-control form-control-sm" value="${item.tarifa}" step="0.01" min="0" oninput="cambiarTarifaPersonal(this,${i})" style="width:90px; font-size:12px; padding:2px"></td>
                <td style="padding:4px"><input type="number" class="form-control form-control-sm" value="${item.cantidad}" step="0.01" min="0" oninput="cambiarCantidad(this,${i})" style="width:70px; font-size:12px; padding:2px"></td>
                <td class="text-right" style="padding:4px; vertical-align:middle">Bs ${formatearMoneda(total)}</td>
                <td style="padding:4px"><button class="btn btn-sm btn-link text-danger" onclick="eliminarServicio(${i})" style="padding:0"><i class="fa fa-trash"></i></button></td>
            `;
        }
        tbody.appendChild(fila);
    });
    document.getElementById('total-servicios').innerHTML = `Bs ${formatearMoneda(totalGeneral)}`;
}

// Funciones de cambio
function cambiarTarifa(select, i) { serviciosData[i].tarifa = parseFloat(select.value) || 0; actualizarTotales(); }
function cambiarTarifaPersonal(input, i) { serviciosData[i].tarifa = parseFloat(input.value) || 0; actualizarTotales(); }
function cambiarCantidad(input, i) { serviciosData[i].cantidad = parseFloat(input.value) || 0; actualizarTotales(); }
function cambiarServicio(input, i) { serviciosData[i].servicio = input.value; }

// Actualizar totales
function actualizarTotales() {
    let totalGral = 0;
    const filas = document.querySelectorAll('#servicios-body tr');
    serviciosData.forEach((item, i) => {
        const total = item.tarifa * item.cantidad;
        totalGral += total;
        if (filas[i]) filas[i].querySelector('td:nth-child(4)').innerHTML = `Bs ${formatearMoneda(total)}`;
    });
    document.getElementById('total-servicios').innerHTML = `Bs ${formatearMoneda(totalGral)}`;
}

// Agregar/eliminar servicios
function agregarServicio() { serviciosData.push({ servicio: 'Nuevo servicio', tarifa: 0, cantidad: 0, personalizado: true }); renderizarTabla(); }
function eliminarServicio(i) { serviciosData.splice(i, 1); renderizarTabla(); }

// Guardar servicios (VERSIÓN CORREGIDA)
function guardarServicios() {
    const serviciosGuardar = serviciosData.filter(s => s.cantidad > 0);
    
    if (serviciosGuardar.length === 0) {
        alert('Debe ingresar al menos un servicio con cantidad > 0');
        return;
    }
    
    // Mostrar indicador de carga
    const btn = event.target;
    const textoOriginal = btn.innerHTML;
    btn.innerHTML = 'Guardando...';
    btn.disabled = true;
    
    // Preparar datos para enviar
    const datosAGuardar = {
        factura_id: datosProcesados.factura_id,
        servicios: serviciosData.map(s => ({
            servicio_id: null,
            servicio: s.servicio,
            tarifa: s.tarifa,
            cantidad: s.cantidad,
            personalizado: s.personalizado || false
        }))
    };
    
    fetch('../../controller/arcor/guardar_otros_servicios.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(datosAGuardar)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const totalMonto = serviciosGuardar.reduce((sum, s) => sum + (s.tarifa * s.cantidad), 0);
            
            datosProcesados['paquete'] = {
                servicios: serviciosData,
                total_servicios: serviciosGuardar.length,
                total_monto: totalMonto
            };
            
            document.getElementById('paquete-volumen').textContent = serviciosGuardar.length;
            document.getElementById('paquete-total').innerHTML = `Bs ${formatearMoneda(totalMonto)}`;
            document.getElementById('data-paquete').style.display = 'block';
            
            $('#modalOtrosServicios').modal('hide');
            alert('Servicios guardados correctamente');
        } else {
            alert('Error: ' + data.error);
        }
    })
    .catch(error => {
        alert('Error al guardar: ' + error.message);
    })
    .finally(() => {
        btn.innerHTML = textoOriginal;
        btn.disabled = false;
    });
}

// Inicializar
document.addEventListener('DOMContentLoaded', cargarTarifas);