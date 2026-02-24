#!/usr/bin/env python
# -*- coding: utf-8 -*-

"""
Procesador para el módulo de Paquete
"""

import logging
import pandas as pd
import os
import sys

sys.path.append(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

logger = logging.getLogger(__name__)

class PaqueteProcesador:
    """Procesador para archivos de paquete"""
    
    def __init__(self):
        self.nombre_modulo = 'PaqueteProcesador'
    
    def procesar(self, archivo_path, fecha_desde=None, fecha_hasta=None, request_id=None):
        """
        Procesa archivo de paquete
        """
        logger.info(f"[{request_id}] Procesando paquete: {archivo_path}")
        
        try:
            df = pd.read_excel(archivo_path, engine='openpyxl')
            
            headers = ['ID_PAQUETE', 'TIPO', 'PESO', 'VOLUMEN', 
                      'DIMENSIONES', 'ESTADO']
            
            datos_preview = []
            for i in range(min(10, len(df))):
                fila = [str(df.iloc[i, j]) if j < len(df.columns) else '-' for j in range(6)]
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