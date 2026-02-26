#!/usr/bin/env python
# -*- coding: utf-8 -*-

"""
Procesador optimizado para Despacho
"""

import pandas as pd
import logging
import os
import sys

sys.path.append(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
from procesadores.procesador_base import ProcesadorBase

logger = logging.getLogger(__name__)

class DespachoProcesador(ProcesadorBase):
    
    COLUMNAS = {
        'ORDERKEY': {'col': 'C', 'tipo': 'string'},
        'SKU': {'col': 'D', 'tipo': 'string'},
        'STORERKEY': {'col': 'E', 'tipo': 'string'},
        'EXTERNORDERKEY': {'col': 'F', 'tipo': 'string'},
        'SHIPPEDQTY': {'col': 'O', 'tipo': 'float'},
        'UOM': {'col': 'I', 'tipo': 'string'},
        'STATUS': {'col': 'P', 'tipo': 'string'},
        'ADDDATE': {'col': 'CN', 'tipo': 'date'},
        'TYPE': {'col': '??', 'tipo': 'string'}  # Ajusta la columna si es necesario
    }
    
    HEADERS = ['ORDERKEY', 'SKU', 'STORERKEY', 'EXTERNORDERKEY', 'UNIDADES', 
               'CAJAS', 'PALLETS', 'STATUS', 'ADDDATE', 'TYPE']
    
    STATUS_VALIDOS = ['55', '92', '95']
    
    def procesar(self, archivo_path, fecha_desde=None, fecha_hasta=None, request_id=None):
        logger.info(f"[{request_id}] Procesando despacho")
        
        try:
            # Leer Excel
            excel_file = pd.ExcelFile(archivo_path, engine='openpyxl')
            hoja = self.buscar_hoja_detail(excel_file)
            df = pd.read_excel(archivo_path, sheet_name=hoja, engine='openpyxl', header=None)
            logger.info(f"[{request_id}] Hoja: {hoja}, Filas: {len(df)}")
            
            # Extraer columnas
            df_resultado = pd.DataFrame()
            for nombre, info in self.COLUMNAS.items():
                idx = self.utils.excel_col_to_index(info['col'])
                if idx < len(df.columns) and len(df) > 1:
                    df_resultado[nombre] = df.iloc[1:, idx].reset_index(drop=True)
            
            # Limpiar y filtrar
            df_resultado = df_resultado.dropna(subset=['ORDERKEY', 'SKU'], how='all')
            logger.info(f"[{request_id}] Después de limpiar: {len(df_resultado)} filas")
            
            # Filtrar STATUS
            if 'STATUS' in df_resultado.columns and len(df_resultado) > 0:
                df_resultado['STATUS_STR'] = df_resultado['STATUS'].astype(str).str.strip()
                df_resultado = df_resultado[df_resultado['STATUS_STR'].isin(self.STATUS_VALIDOS)].copy()
                logger.info(f"[{request_id}] Después de filtrar STATUS: {len(df_resultado)} filas")
            
            # Procesar cantidades
            if 'SHIPPEDQTY' in df_resultado.columns and len(df_resultado) > 0:
                df_resultado['QTY_ENTERO'] = df_resultado['SHIPPEDQTY'].apply(self._extraer_parte_entera)
            
            # Aplicar filtros de fecha
            df_resultado, stats_fecha = self.aplicar_filtros_fecha(df_resultado, 'ADDDATE', 
                                                                   fecha_desde, fecha_hasta)
            
            # Agrupar por ORDERKEY + SKU
            if len(df_resultado) > 0:
                df_resultado['GRUPO'] = df_resultado['ORDERKEY'].astype(str) + '_' + df_resultado['SKU'].astype(str)
                
                def agregar_grupo(g):
                    if len(g) == 0:
                        return None
                    primero = g.iloc[0]
                    uom = str(primero['UOM']).strip().upper() if pd.notna(primero['UOM']) else ''
                    cantidad = int(g['QTY_ENTERO'].sum()) if 'QTY_ENTERO' in g.columns else 0
                    
                    return pd.Series({
                        'ORDERKEY': primero['ORDERKEY'],
                        'SKU': primero['SKU'],
                        'STORERKEY': primero['STORERKEY'] if 'STORERKEY' in g.columns else '',
                        'EXTERNORDERKEY': primero['EXTERNORDERKEY'] if 'EXTERNORDERKEY' in g.columns else '',
                        'UNIDADES': cantidad if uom == 'UN' else 0,
                        'CAJAS': cantidad if uom == 'CJ' else 0,
                        'PALLETS': cantidad if uom == 'PL' else 0,
                        'STATUS': primero['STATUS'] if 'STATUS' in g.columns else '',
                        'ADDDATE': g['ADDDATE'].iloc[0] if 'ADDDATE' in g.columns else '',
                        'TYPE': primero['TYPE'] if 'TYPE' in g.columns else ''
                    })
                
                df_agrupado = df_resultado.groupby('GRUPO').apply(agregar_grupo).reset_index(drop=True)
                logger.info(f"[{request_id}] Después de agrupar: {len(df_agrupado)} filas")
            else:
                df_agrupado = pd.DataFrame(columns=self.HEADERS)
            
            # Preparar respuesta
            stats = {
                'total_filas': len(df_agrupado),
                'filas_filtradas_fecha': stats_fecha['filas_filtradas_fecha'],
                'fecha_min': stats_fecha['fecha_min'],
                'fecha_max': stats_fecha['fecha_max'],
                'hoja_procesada': hoja
            }
            
            return {
                'success': True,
                'total_registros': len(df_agrupado),
                'headers': self.HEADERS,
                'data': self._convertir_dataframe_a_lista(df_agrupado.head(100)),
                'data_completa': self._convertir_dataframe_a_lista(df_agrupado),
                'stats': stats
            }
            
        except Exception as e:
            logger.error(f"[{request_id}] Error: {str(e)}", exc_info=True)
            raise