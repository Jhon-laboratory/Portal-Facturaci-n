#!/usr/bin/env python
# -*- coding: utf-8 -*-

"""
Procesador para el módulo de Despacho
"""

import logging
import pandas as pd
import os
import sys

sys.path.append(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

logger = logging.getLogger(__name__)

class DespachoProcesador:
    """Procesador para archivos de despacho"""
    
    def __init__(self):
        self.nombre_modulo = 'DespachoProcesador'
    
    def procesar(self, archivo_path, fecha_desde=None, fecha_hasta=None, request_id=None):
        """
        Procesa archivo de despacho
        """
        logger.info(f"[{request_id}] Procesando despacho: {archivo_path}")
        
        try:
            # Leer el archivo
            df = pd.read_excel(archivo_path, engine='openpyxl')
            
            headers = ['GUIA', 'FECHA_DESPACHO', 'CLIENTE', 'DESTINO', 
                      'PESO', 'VOLUMEN', 'ESTADO']
            
            datos_preview = []
            for i in range(min(10, len(df))):
                fila = [str(df.iloc[i, j]) if j < len(df.columns) else '-' for j in range(7)]
                datos_preview.append(fila)
            
            return {
                'success': True,
                'total_registros': len(df),
                'headers': headers,
                'data': datos_preview,
                'stats': {
                    'total_filas': len(df),
                    'mostrando': len(datos_preview)
                }
            }
            
        except Exception as e:
            logger.error(f"[{request_id}] Error: {str(e)}")
            raise