// ocupabilidad.js

// Variables globales para ocupabilidad
let ubicacionesData = [];

// Función para abrir el modal de ocupabilidad
function abrirModalOcupabilidad() {
    // Cargar datos existentes si los hay
    cargarUbicacionesExistentes();
    $('#modalOcupabilidad').modal('show');
}

// Función para cargar ubicaciones existentes
function cargarUbicacionesExistentes() {
    const factura_id = typeof facturaIdGlobal !== 'undefined' ? facturaIdGlobal : null;
    
    if (!factura_id) {
        // Si no hay factura_id, inicializar con los dos tipos predeterminados
        ubicacionesData = [
            { tipo: 'Posiciones rack', cantidad: 0 },
            { tipo: 'Posiciones rack (pallet adicional)', cantidad: 0 }
        ];
        renderizarTablaOcupabilidad();
        return;
    }
    
    // Mostrar indicador de carga
    $('#ocupabilidad-body').html(`
        <tr>
            <td colspan="4" class="text-center">
                <i class="fa fa-spinner fa-spin"></i> Cargando ubicaciones...
            </td>
        </tr>
    `);
    
    // Cargar desde la base de datos
    $.ajax({
        url: '../../controller/arcor/get_ocupabilidad.php',
        method: 'GET',
        data: { factura_id: factura_id },
        dataType: 'json',
        success: function(response) {
            if (response.success && response.data && response.data.length > 0) {
                ubicacionesData = response.data;
            } else {
                // Si no hay datos, inicializar con los dos tipos predeterminados
                ubicacionesData = [
                    { tipo: 'Posiciones rack', cantidad: 0 },
                    { tipo: 'Posiciones rack (pallet adicional)', cantidad: 0 }
                ];
            }
            renderizarTablaOcupabilidad();
        },
        error: function(xhr, status, error) {
            console.error('Error al cargar ubicaciones:', error);
            if (typeof mostrarNotificacion === 'function') {
                mostrarNotificacion('Error al cargar ubicaciones', 'error');
            }
            ubicacionesData = [
                { tipo: 'Posiciones rack', cantidad: 0 },
                { tipo: 'Posiciones rack (pallet adicional)', cantidad: 0 }
            ];
            renderizarTablaOcupabilidad();
        }
    });
}

// Función para renderizar la tabla de ocupabilidad
function renderizarTablaOcupabilidad() {
    const tbody = document.getElementById('ocupabilidad-body');
    if (!tbody) return;
    
    tbody.innerHTML = '';
    
    if (ubicacionesData.length === 0) {
        // Si no hay datos, mostrar los dos tipos predeterminados
        ubicacionesData = [
            { tipo: 'Posiciones rack', cantidad: 0 },
            { tipo: 'Posiciones rack (pallet adicional)', cantidad: 0 }
        ];
    }
    
    ubicacionesData.forEach((item, index) => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>
                <input type="text" class="form-control form-control-sm" 
                       value="${escapeHtml(item.tipo)}" 
                       placeholder="Tipo de ubicación"
                       onchange="actualizarTipoUbicacion(${index}, this.value)">
            </td>
            <td>
                <input type="number" class="form-control form-control-sm" 
                       value="${item.cantidad || 0}" 
                       min="0" 
                       step="1"
                       onchange="actualizarCantidadUbicacion(${index}, this.value)"
                       onkeyup="actualizarCantidadUbicacion(${index}, this.value)">
            </td>
            <td class="text-center align-middle">
                <strong>${item.cantidad || 0}</strong>
            </td>
            <td class="text-center">
                ${ubicacionesData.length > 1 ? `
                    <button class="btn-remove-ubicacion" onclick="eliminarUbicacion(${index})" title="Eliminar">
                        <i class="fa fa-trash"></i>
                    </button>
                ` : ''}
            </td>
        `;
        tbody.appendChild(tr);
    });
    
    actualizarTotalOcupabilidad();
}

// Función para escapar HTML
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Función para agregar una nueva ubicación
function agregarUbicacion() {
    ubicacionesData.push({ tipo: 'Nueva ubicación', cantidad: 0 });
    renderizarTablaOcupabilidad();
}

// Función para actualizar el tipo de ubicación
function actualizarTipoUbicacion(index, valor) {
    if (ubicacionesData[index]) {
        ubicacionesData[index].tipo = valor;
    }
}

// Función para actualizar la cantidad
function actualizarCantidadUbicacion(index, valor) {
    const cantidad = parseInt(valor) || 0;
    if (ubicacionesData[index]) {
        ubicacionesData[index].cantidad = cantidad;
        // Actualizar la celda del total
        const row = document.querySelector(`#ocupabilidad-body tr:nth-child(${index + 1})`);
        if (row) {
            const totalCell = row.querySelector('td:nth-child(3) strong');
            if (totalCell) {
                totalCell.textContent = cantidad;
            }
        }
        actualizarTotalOcupabilidad();
    }
}

// Función para eliminar una ubicación
function eliminarUbicacion(index) {
    if (ubicacionesData.length > 1) {
        ubicacionesData.splice(index, 1);
        renderizarTablaOcupabilidad();
    } else {
        if (typeof mostrarNotificacion === 'function') {
            mostrarNotificacion('Debe haber al menos una ubicación', 'warning');
        } else {
            alert('Debe haber al menos una ubicación');
        }
    }
}

// Función para actualizar el total
function actualizarTotalOcupabilidad() {
    const total = ubicacionesData.reduce((sum, item) => sum + (parseInt(item.cantidad) || 0), 0);
    const totalElement = document.getElementById('total-ocupabilidad');
    if (totalElement) {
        totalElement.textContent = total;
    }
}

// Función para guardar ocupabilidad
// Función para guardar ocupabilidad
// Función para guardar ocupabilidad (VERSIÓN CORREGIDA - IDÉNTICA A guardarServicios)
function guardarOcupabilidad() {
    // Filtrar solo ubicaciones con cantidad > 0
    const ubicacionesGuardar = ubicacionesData.filter(u => u.cantidad > 0);
    
    if (ubicacionesGuardar.length === 0) {
        alert('Debe ingresar al menos una ubicación con cantidad > 0');
        return;
    }
    
    // Mostrar indicador de carga
    const btn = event.target;
    const textoOriginal = btn.innerHTML;
    btn.innerHTML = 'Guardando...';
    btn.disabled = true;
    
    // Preparar datos para enviar (IGUAL QUE en otros servicios)
    const datosAGuardar = {
        factura_id: facturaIdGlobal, // USAR LA VARIABLE GLOBAL, NO datosProcesados
        ubicaciones: ubicacionesData.map(u => ({
            tipo: u.tipo,
            cantidad: u.cantidad
        }))
    };
    
    console.log('Enviando datos:', datosAGuardar);
    
    fetch('../../controller/arcor/guardar_ocupabilidad.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(datosAGuardar)
    })
    .then(response => response.json())
    .then(data => {
        console.log('Respuesta:', data);
        
        if (data.success) {
            const totalPosiciones = ubicacionesGuardar.reduce((sum, u) => sum + u.cantidad, 0);
            
            // Actualizar UI
            document.getElementById('almacen-total').textContent = totalPosiciones;
            
            // Contar posiciones rack
            const rackCount = ubicacionesGuardar
                .filter(u => u.tipo.toLowerCase().includes('rack'))
                .reduce((sum, u) => sum + u.cantidad, 0);
            document.getElementById('almacen-ubicaciones').textContent = rackCount;
            
            document.getElementById('data-almacen').style.display = 'block';
            
            // Marcar como completado
            archivosProcesados++;
            document.getElementById('archivosProcesados').textContent = archivosProcesados;
            
            // Actualizar barra de progreso
            const porcentaje = ((archivosSubidos + archivosProcesados) / (totalModulos * 2)) * 100;
            document.getElementById('progressFill').style.width = porcentaje + '%';
            
            // Marcar módulo como completado
            const moduloCard = document.getElementById('modulo-almacen');
            if (moduloCard) {
                moduloCard.classList.add('completado');
                const header = moduloCard.querySelector('.modulo-header');
                if (header && !header.querySelector('.badge-completado')) {
                    const badge = document.createElement('span');
                    badge.className = 'badge-completado';
                    badge.innerHTML = '<i class="fa fa-check"></i> Completado';
                    header.appendChild(badge);
                }
            }
            
            $('#modalOcupabilidad').modal('hide');
            alert('Ocupabilidad guardada correctamente');
        } else {
            alert('Error: ' + (data.error || 'Error desconocido'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error al guardar: ' + error.message);
    })
    .finally(() => {
        btn.innerHTML = textoOriginal;
        btn.disabled = false;
    });
}

// Función para actualizar el módulo en la UI
function actualizarModuloCompletado(modulo, total) {
    // Actualizar contador de archivos procesados
    const archivosProcesados = document.getElementById('archivosProcesados');
    let actual = 0;
    
    if (archivosProcesados) {
        actual = parseInt(archivosProcesados.textContent) || 0;
        archivosProcesados.textContent = actual + 1;
    }
    
    // Actualizar barra de progreso
    const totalModulos = typeof totalModulosGlobal !== 'undefined' ? totalModulosGlobal : 4;
    const archivosSubidos = parseInt(document.getElementById('archivosSubidos')?.textContent) || 0;
    const progressFill = document.getElementById('progressFill');
    
    if (progressFill) {
        const porcentaje = ((archivosSubidos + (actual + 1)) / (totalModulos * 2)) * 100;
        progressFill.style.width = porcentaje + '%';
    }
    
    // Marcar el módulo como completado visualmente
    const moduloCard = document.getElementById(`modulo-${modulo}`);
    if (moduloCard) {
        moduloCard.classList.add('completado');
        
        // Agregar badge de completado
        const header = moduloCard.querySelector('.modulo-header');
        if (header && !header.querySelector('.badge-completado')) {
            const badge = document.createElement('span');
            badge.className = 'badge-completado';
            badge.innerHTML = '<i class="fa fa-check"></i> Completado';
            header.appendChild(badge);
        }
        
        // Deshabilitar área de carga
        const uploadArea = moduloCard.querySelector('.upload-area');
        if (uploadArea) {
            uploadArea.style.opacity = '0.5';
            uploadArea.style.cursor = 'not-allowed';
            uploadArea.onclick = null;
        }
        
        // Deshabilitar botón
        const btn = moduloCard.querySelector('.btn-procesar-modulo');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fa fa-check"></i> Completado';
        }
    }
}

// Función para mostrar notificaciones (respaldo por si no existe la global)
if (typeof mostrarNotificacion !== 'function') {
    window.mostrarNotificacion = function(mensaje, tipo) {
        console.log(`[${tipo}] ${mensaje}`);
        alert(mensaje);
    };
}