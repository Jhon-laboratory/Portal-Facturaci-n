#!/usr/bin/env python
# -*- coding: utf-8 -*-

"""
API Python para procesamiento eficiente de archivos Excel
Sistema de Facturación - Módulo Python
"""

import os
import sys
import json
import logging
import tempfile
import time
import traceback
from datetime import datetime
from flask import Flask, request, jsonify
from flask_cors import CORS
import pandas as pd

# Configurar logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s',
    handlers=[
        logging.FileHandler(os.path.join(os.path.dirname(__file__), 'logs', 'api_python.log')),
        logging.StreamHandler()
    ]
)
logger = logging.getLogger(__name__)

# Crear directorio de logs si no existe
os.makedirs(os.path.join(os.path.dirname(__file__), 'logs'), exist_ok=True)

# Inicializar Flask
app = Flask(__name__)
CORS(app)  # Habilitar CORS para todas las rutas

# Configuración
app.config['MAX_CONTENT_LENGTH'] = 500 * 1024 * 1024  # 500MB max
app.config['UPLOAD_FOLDER'] = tempfile.gettempdir()

# Importar procesadores
try:
    from procesadores.recepcion_procesador import RecepcionProcesador
    from procesadores.despacho_procesador import DespachoProcesador
    from procesadores.paquete_procesador import PaqueteProcesador
    from procesadores.almacen_procesador import AlmacenProcesador
    logger.info("Procesadores importados correctamente")
except Exception as e:
    logger.error(f"Error importando procesadores: {e}")
    raise

# Diccionario de procesadores
PROCESADORES = {
    'recepcion': RecepcionProcesador(),
    'despacho': DespachoProcesador(),
    'paquete': PaqueteProcesador(),
    'almacen': AlmacenProcesador()
}

@app.route('/health', methods=['GET'])
def health_check():
    """Endpoint para verificar que la API está funcionando"""
    return jsonify({
        'status': 'ok',
        'timestamp': datetime.now().isoformat(),
        'python_version': sys.version
    })

@app.route('/procesar/<tipo>', methods=['POST'])
def procesar_archivo(tipo):
    """
    Endpoint principal para procesar archivos Excel
    """
    start_time = datetime.now()
    request_id = datetime.now().strftime('%Y%m%d_%H%M%S_%f')
    
    logger.info(f"[{request_id}] ===== INICIO PROCESAMIENTO {tipo.upper()} =====")
    
    try:
        # Validar tipo
        if tipo not in PROCESADORES:
            logger.error(f"[{request_id}] Tipo no válido: {tipo}")
            return jsonify({
                'success': False,
                'error': f'Tipo de módulo no válido: {tipo}'
            }), 400
        
        # Validar archivo
        if 'archivo' not in request.files:
            logger.error(f"[{request_id}] No se recibió archivo")
            return jsonify({
                'success': False,
                'error': 'No se recibió ningún archivo'
            }), 400
        
        archivo = request.files['archivo']
        if archivo.filename == '':
            logger.error(f"[{request_id}] Nombre de archivo vacío")
            return jsonify({
                'success': False,
                'error': 'Nombre de archivo vacío'
            }), 400
        
        # Validar extensión
        extension = archivo.filename.split('.')[-1].lower()
        if extension not in ['xls', 'xlsx', 'csv']:
            logger.error(f"[{request_id}] Extensión no válida: {extension}")
            return jsonify({
                'success': False,
                'error': f'Formato no válido: {extension}. Solo .xls, .xlsx, .csv'
            }), 400
        
        # Guardar archivo temporal
        temp_path = os.path.join(app.config['UPLOAD_FOLDER'], f"{request_id}_{archivo.filename}")
        archivo.save(temp_path)
        file_size = os.path.getsize(temp_path)
        logger.info(f"[{request_id}] Archivo guardado: {temp_path} ({file_size} bytes)")
        
        # Obtener filtros
        fecha_desde = request.form.get('fecha_desde', '')
        fecha_hasta = request.form.get('fecha_hasta', '')
        
        logger.info(f"[{request_id}] Filtros: desde={fecha_desde}, hasta={fecha_hasta}")
        
        # Procesar con el procesador correspondiente
        procesador = PROCESADORES[tipo]
        resultado = procesador.procesar(
            archivo_path=temp_path,
            fecha_desde=fecha_desde if fecha_desde else None,
            fecha_hasta=fecha_hasta if fecha_hasta else None,
            request_id=request_id
        )
        
        # Calcular tiempo de procesamiento
        elapsed = (datetime.now() - start_time).total_seconds()
        logger.info(f"[{request_id}] Procesamiento completado en {elapsed:.2f} segundos")
        
        # Agregar metadatos
        resultado['metadata'] = {
            'request_id': request_id,
            'tiempo_procesamiento': round(elapsed, 2),
            'archivo_original': archivo.filename,
            'tamano_bytes': file_size
        }
        
        # Limpiar archivo temporal con reintentos
        for intento in range(3):
            try:
                if os.path.exists(temp_path):
                    # Forzar cierre de archivos
                    import gc
                    gc.collect()
                    time.sleep(0.5)
                    os.remove(temp_path)
                    logger.info(f"[{request_id}] Archivo temporal eliminado")
                break
            except Exception as e:
                if intento < 2:
                    logger.warning(f"[{request_id}] Reintentando eliminar archivo... ({intento+1}/3)")
                    time.sleep(1)
                else:
                    logger.warning(f"[{request_id}] No se pudo eliminar archivo temporal: {e}")
        
        logger.info(f"[{request_id}] Enviando respuesta JSON exitosamente")
        return jsonify(resultado)
    
    except Exception as e:
        elapsed = (datetime.now() - start_time).total_seconds()
        error_msg = str(e)
        error_trace = traceback.format_exc()
        
        logger.error(f"[{request_id}] ERROR: {error_msg}\n{error_trace}")
        
        return jsonify({
            'success': False,
            'error': error_msg,
            'tipo_error': type(e).__name__,
            'tiempo_procesamiento': round(elapsed, 2),
            'request_id': request_id
        }), 500

@app.route('/info', methods=['GET'])
def info():
    """Información de la API"""
    return jsonify({
        'nombre': 'API Python Procesador Excel',
        'version': '1.0.0',
        'modulos_soportados': list(PROCESADORES.keys()),
        'librerias': {
            'pandas': pd.__version__,
            'openpyxl': '3.1.2'
        }
    })

if __name__ == '__main__':
    # Ejecutar en modo desarrollo
    logger.info("Iniciando API Python en http://127.0.0.1:5000")
    app.run(host='127.0.0.1', port=5000, debug=True, threaded=True)