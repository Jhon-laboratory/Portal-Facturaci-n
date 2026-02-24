#!/usr/bin/env python
# -*- coding: utf-8 -*-

"""
Procesador para el módulo de Almacenamiento
"""

import logging
import pandas as pd
import os
import sys

sys.path.append(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

logger = logging.getLogger(__name__)

class AlmacenProcesador:
    """Procesador para archivos de almacenamiento"""
    
    def __init__(self):
        self.nombre_modulo = 'AlmacenProcesador'
    
    def procesar(self, archivo_path, fecha_desde=None, fecha_hasta=None, request_id=None):
        """
        Procesa archivo de almacenamiento
        """
        logger.info(f"[{request_id}] Procesando almacen: {archivo_path}")
        
        try:
            df = pd.read_excel(archivo_path, engine='openpyxl')
            
            headers = ['CODIGO', 'PRODUCTO', 'UBICACION', 'STOCK', 
                      'STOCK_MIN', 'STOCK_MAX', 'VALOR']
            
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