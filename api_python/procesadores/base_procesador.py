#!/usr/bin/env python
# -*- coding: utf-8 -*-

"""
Procesador base para todos los módulos
Define la interfaz común y funcionalidades compartidas
"""

import pandas as pd
import numpy as np
import logging
from abc import ABC, abstractmethod
from datetime import datetime
import os
import sys

# Añadir directorio padre al path
sys.path.append(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
from utils.excel_utils import ExcelUtils

logger = logging.getLogger(__name__)

class ProcesadorBase(ABC):
    """Clase base abstracta para procesadores"""
    
    def __init__(self):
        self.nombre_modulo = self.__class__.__name__
        self.utils = ExcelUtils()
        
    @abstractmethod
    def procesar(self, archivo_path, fecha_desde=None, fecha_hasta=None, request_id=None):
        """
        Método abstracto que deben implementar las subclases
        
        Args:
            archivo_path: Ruta al archivo Excel
            fecha_desde: Filtro fecha desde (YYYY-MM-DD)
            fecha_hasta: Filtro fecha hasta (YYYY-MM-DD)
            request_id: ID de la petición para logging
        
        Returns:
            Dict con resultados
        """
        pass
    
    def aplicar_filtros_fecha(self, df, columna_fecha, fecha_desde, fecha_hasta):
        """
        Aplica filtros de fecha al DataFrame
        
        Args:
            df: DataFrame
            columna_fecha: Nombre de la columna de fecha
            fecha_desde: Fecha desde
            fecha_hasta: Fecha hasta
        
        Returns:
            DataFrame filtrado y estadísticas
        """
        stats = {
            'filas_filtradas_fecha': 0,
            'fecha_min': None,
            'fecha_max': None
        }
        
        if columna_fecha not in df.columns:
            return df, stats
        
        # Crear columna con fecha ISO para filtrado
        df['_FECHA_ISO'] = None
        
        for idx, valor in df[columna_fecha].items():
            fecha_formateada, fecha_iso = self.utils.convertir_fecha_excel(valor)
            df.at[idx, columna_fecha] = fecha_formateada if fecha_formateada else valor
            df.at[idx, '_FECHA_ISO'] = fecha_iso
            
            # Actualizar rango de fechas
            if fecha_iso:
                if stats['fecha_min'] is None or fecha_iso < stats['fecha_min']:
                    stats['fecha_min'] = fecha_iso
                if stats['fecha_max'] is None or fecha_iso > stats['fecha_max']:
                    stats['fecha_max'] = fecha_iso
        
        # Aplicar filtros
        if fecha_desde or fecha_hasta:
            mask = pd.Series(True, index=df.index)
            
            if fecha_desde and '_FECHA_ISO' in df.columns:
                mask &= (df['_FECHA_ISO'] >= fecha_desde)
            
            if fecha_hasta and '_FECHA_ISO' in df.columns:
                mask &= (df['_FECHA_ISO'] <= fecha_hasta)
            
            # Contar filas filtradas
            stats['filas_filtradas_fecha'] = (~mask).sum()
            df = df[mask].copy()
        
        return df, stats
    
    def generar_respuesta(self, datos, headers, stats, total_registros, mensajes=None):
        """
        Genera la respuesta JSON estandarizada
        
        Args:
            datos: Lista de datos para vista previa
            headers: Lista de headers
            stats: Diccionario con estadísticas
            total_registros: Total de registros después de filtros
            mensajes: Mensajes adicionales
        
        Returns:
            Dict para JSON response
        """
        respuesta = {
            'success': True,
            'total_registros': total_registros,
            'headers': headers,
            'data': datos,
            'stats': stats
        }
        
        if mensajes:
            respuesta.update(mensajes)
        
        return respuesta