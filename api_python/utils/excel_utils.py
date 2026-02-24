#!/usr/bin/env python
# -*- coding: utf-8 -*-

"""
Utilidades para procesamiento de Excel
Versión mejorada con manejo de formatos latinos
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
        
        Ejemplos:
            'A' -> 0
            'C' -> 2
            'AH' -> 33
            'ZZ' -> 701
        """
        if not col_letter:
            return 0
        
        col_letter = col_letter.upper().strip()
        index = 0
        
        for char in col_letter:
            index = index * 26 + (ord(char) - ord('A') + 1)
        
        return index - 1  # Convertir a 0-based
    
    @staticmethod
    def convertir_fecha_excel(valor):
        """
        Convierte valor de Excel a fecha
        
        Returns:
            tuple: (fecha_formateada, fecha_iso)
        """
        try:
            if pd.isna(valor) or valor is None:
                return None, None
            
            # Si es número de Excel
            if isinstance(valor, (int, float, np.integer, np.floating)):
                # Si es un número grande, probablemente es fecha Excel
                if valor > 40000:  # Rango de fechas Excel
                    fecha = pd.Timestamp('1899-12-30') + pd.Timedelta(days=float(valor))
                    return fecha.strftime('%d/%m/%Y %H:%M'), fecha.strftime('%Y-%m-%d')
                else:
                    return str(valor), None
            
            # Si es string
            if isinstance(valor, str):
                # Intentar varios formatos
                for fmt in ['%Y-%m-%d %H:%M:%S', '%Y-%m-%d %H:%M', '%Y-%m-%d', 
                           '%d/%m/%Y %H:%M:%S', '%d/%m/%Y %H:%M', '%d/%m/%Y',
                           '%m/%d/%Y %H:%M:%S', '%m/%d/%Y %H:%M', '%m/%d/%Y']:
                    try:
                        fecha = datetime.strptime(valor, fmt)
                        return fecha.strftime('%d/%m/%Y %H:%M'), fecha.strftime('%Y-%m-%d')
                    except:
                        continue
                
                # Si no se pudo convertir, devolver el original
                return valor, None
            
            # Si ya es datetime
            if isinstance(valor, (datetime, pd.Timestamp)):
                return valor.strftime('%d/%m/%Y %H:%M'), valor.strftime('%Y-%m-%d')
            
            return str(valor), None
            
        except Exception as e:
            logger.warning(f"Error convirtiendo fecha {valor}: {e}")
            return str(valor), None
    
    @staticmethod
    def formatear_numero(valor, decimales=5):
        """
        Formatea un número para visualización en formato latino
        
        Args:
            valor: Número a formatear
            decimales: Número de decimales
        
        Returns:
            String formateado (ej: 11.404.852,00000)
        """
        try:
            if pd.isna(valor) or valor is None:
                return '0'
            
            # Convertir a float
            num = float(valor)
            
            if num == 0:
                return '0'
            
            # Formatear con separadores latinos
            # Primero formatear con separadores de miles y decimales
            # Luego intercambiar comas y puntos
            
            # Parte entera y decimal
            formato = f"{{:,.{decimales}f}}"
            resultado = formato.format(num)
            
            # Intercambiar: primero coma por punto, luego punto por coma
            # Esto convierte "1,234,567.89012" a "1.234.567,89012"
            resultado = resultado.replace(',', 'X')  # Marcar comas temporales
            resultado = resultado.replace('.', ',')  # Puntos a comas (decimales)
            resultado = resultado.replace('X', '.')  # Comas temporales a puntos (miles)
            
            return resultado
            
        except Exception as e:
            logger.warning(f"Error formateando número {valor}: {e}")
            return str(valor)