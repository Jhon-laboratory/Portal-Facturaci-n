#!/usr/bin/env python
# -*- coding: utf-8 -*-

"""
Procesador base para todos los módulos
Versión unificada con mejor manejo de datos
"""

import pandas as pd
import numpy as np
import logging
from datetime import datetime
import os
import sys

sys.path.append(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
from utils.excel_utils import ExcelUtils

logger = logging.getLogger(__name__)

class ProcesadorBase:
    """Clase base para todos los procesadores"""
    
    def __init__(self):
        self.utils = ExcelUtils()
        
    def _convertir_a_serializable(self, obj):
        """Convierte objetos numpy a tipos nativos"""
        if obj is None or pd.isna(obj):
            return ''
        if isinstance(obj, (np.int64, np.int32, np.int16, np.int8)):
            return int(obj)
        if isinstance(obj, (np.float64, np.float32, np.float16)):
            return int(obj) if float(obj).is_integer() else float(obj)
        if isinstance(obj, (np.datetime64, pd.Timestamp, datetime)):
            return obj.strftime('%d/%m/%Y')
        if isinstance(obj, (datetime)):
            return obj.strftime('%d/%m/%Y')
        return str(obj)
    
    def _convertir_dataframe_a_lista(self, df):
        """Convierte DataFrame a lista serializable"""
        return [[self._convertir_a_serializable(val) for val in row] for _, row in df.iterrows()]
    
    def _extraer_parte_entera(self, valor):
        """Extrae parte entera de número con coma"""
        try:
            if pd.isna(valor) or valor is None:
                return 0
            if isinstance(valor, (int, float, np.integer, np.floating)):
                return int(float(valor))
            valor_str = str(valor).strip()
            if ',' in valor_str:
                return int(float(valor_str.split(',')[0].replace('.', '')))
            if valor_str:
                return int(float(valor_str.replace(',', '').replace('.', '')))
            return 0
        except Exception as e:
            logger.warning(f"Error extrayendo parte entera: {e}")
            return 0
    
    def _procesar_fecha(self, valor):
        """Procesa fecha unificada"""
        try:
            if pd.isna(valor) or valor is None:
                return None
            fecha_str, fecha_iso = self.utils.convertir_fecha_excel(valor)
            if fecha_iso:
                anio, mes, dia = map(int, fecha_iso.split('-'))
                return datetime(anio, mes, dia)
            return None
        except Exception as e:
            logger.warning(f"Error procesando fecha: {e}")
            return None
    
    def buscar_hoja_detail(self, excel_file):
        """Busca hoja Detail en el Excel"""
        hojas = excel_file.sheet_names
        for hoja in hojas:
            if hoja.lower() == 'detail':
                return hoja
        for hoja in hojas:
            if 'detail' in hoja.lower():
                return hoja
        return hojas[1] if len(hojas) > 1 else hojas[0]
    
    def aplicar_filtros_fecha(self, df, columna_fecha, fecha_desde, fecha_hasta):
        """Aplica filtros de fecha de manera unificada"""
        stats = {'filas_filtradas_fecha': 0, 'fecha_min': None, 'fecha_max': None}
        
        if columna_fecha in df.columns:
            df['_fecha_iso'] = None
            for idx, val in df[columna_fecha].items():
                fecha_obj = self._procesar_fecha(val)
                if fecha_obj:
                    fecha_iso = fecha_obj.strftime('%Y-%m-%d')
                    df.at[idx, '_fecha_iso'] = fecha_iso
                    df.at[idx, columna_fecha] = fecha_obj.strftime('%d/%m/%Y')
                    
                    if stats['fecha_min'] is None or fecha_iso < stats['fecha_min']:
                        stats['fecha_min'] = fecha_iso
                    if stats['fecha_max'] is None or fecha_iso > stats['fecha_max']:
                        stats['fecha_max'] = fecha_iso
            
            if fecha_desde or fecha_hasta:
                mask = pd.Series(True, index=df.index)
                if fecha_desde:
                    fecha_limpia = fecha_desde.split(' ')[0] if ' ' in fecha_desde else fecha_desde
                    mask &= (df['_fecha_iso'] >= fecha_limpia)
                if fecha_hasta:
                    fecha_limpia = fecha_hasta.split(' ')[0] if ' ' in fecha_hasta else fecha_hasta
                    mask &= (df['_fecha_iso'] <= fecha_limpia)
                
                stats['filas_filtradas_fecha'] = int((~mask).sum())
                df = df[mask].copy()
        
        return df, stats