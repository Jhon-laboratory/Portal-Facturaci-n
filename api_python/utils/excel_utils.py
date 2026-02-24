#!/usr/bin/env python
# -*- coding: utf-8 -*-

"""
Utilidades para procesamiento de Excel
Versión CORREGIDA - Manejo de fechas SIN HORA
"""

import pandas as pd
import numpy as np
import logging
from datetime import datetime

logger = logging.getLogger(__name__)

class ExcelUtils:
    """Utilidades para manejo de Excel"""
    
    @staticmethod
    def excel_col_to_index(col_letter):
        """
        Convierte letras de columna Excel a índice numérico (0-based)
        """
        if not col_letter:
            return 0
        
        col_letter = col_letter.upper().strip()
        index = 0
        
        for char in col_letter:
            index = index * 26 + (ord(char) - ord('A') + 1)
        
        return index - 1
    
    @staticmethod
    def convertir_fecha_excel(valor):
        """
        Convierte valor de Excel a fecha (SOLO FECHA, ignora hora)
        
        Returns:
            tuple: (fecha_formateada, fecha_iso)
        """
        try:
            if pd.isna(valor) or valor is None:
                return None, None
            
            # Si es número de Excel
            if isinstance(valor, (int, float, np.integer, np.floating)):
                if valor > 40000:  # Es fecha Excel
                    fecha = pd.Timestamp('1899-12-30') + pd.Timedelta(days=float(valor))
                    # Devolver SOLO la fecha (sin hora)
                    return fecha.strftime('%d/%m/%Y'), fecha.strftime('%Y-%m-%d')
                else:
                    return str(valor), None
            
            # Si es string
            if isinstance(valor, str):
                # Limpiar el string
                valor = valor.strip()
                
                # Si tiene hora, separar (tomar solo la fecha)
                if ' ' in valor:
                    fecha_str = valor.split(' ')[0]
                else:
                    fecha_str = valor
                
                # Intentar varios formatos de fecha
                for fmt in ['%d/%m/%y', '%d/%m/%Y', '%Y-%m-%d', '%m/%d/%y', '%m/%d/%Y']:
                    try:
                        fecha = datetime.strptime(fecha_str, fmt)
                        # Devolver SOLO la fecha
                        return fecha.strftime('%d/%m/%Y'), fecha.strftime('%Y-%m-%d')
                    except:
                        continue
                
                return valor, None
            
            # Si ya es datetime
            if isinstance(valor, (datetime, pd.Timestamp)):
                # Devolver SOLO la fecha
                return valor.strftime('%d/%m/%Y'), valor.strftime('%Y-%m-%d')
            
            return str(valor), None
            
        except Exception as e:
            logger.warning(f"Error convirtiendo fecha {valor}: {e}")
            return str(valor), None
    
    @staticmethod
    def formatear_numero(valor, decimales=5):
        """
        Formatea un número para visualización en formato latino
        """
        try:
            if pd.isna(valor) or valor is None:
                return '0'
            
            num = float(valor)
            
            if num == 0:
                return '0'
            
            formato = f"{{:,.{decimales}f}}"
            resultado = formato.format(num)
            
            resultado = resultado.replace(',', 'X')
            resultado = resultado.replace('.', ',')
            resultado = resultado.replace('X', '.')
            
            return resultado
            
        except Exception as e:
            logger.warning(f"Error formateando número {valor}: {e}")
            return str(valor)