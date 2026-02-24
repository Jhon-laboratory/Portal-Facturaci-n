#!/usr/bin/env python
# -*- coding: utf-8 -*-

"""
Procesador específico para el módulo de Recepción
VERSIÓN FINAL - Con agrupación por RECEIPTKEY+SKU, STATUS 11/15 y suma de cantidades
"""

import pandas as pd
import numpy as np
import logging
from datetime import datetime
import os
import sys
import time

sys.path.append(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
from utils.excel_utils import ExcelUtils

logger = logging.getLogger(__name__)

class RecepcionProcesador:
    """Procesador para archivos de recepción - Agrupación por RECEIPTKEY+SKU"""
    
    # MAPEO DE COLUMNAS
    COLUMNAS = {
        'RECEIPTKEY': {'columna': 'C', 'tipo': 'string', 'nombre': 'ASN/Recepción'},
        'SKU': {'columna': 'D', 'tipo': 'string', 'nombre': 'Artículo'},
        'STORERKEY': {'columna': 'E', 'tipo': 'string', 'nombre': 'Propietario'},
        # Columna D parece estar vacía o no usada, la saltamos
        'QTYRECEIVED': {'columna': 'H', 'tipo': 'float', 'nombre': 'Ctd. recibida'},
        'UOM': {'columna': 'I', 'tipo': 'string', 'nombre': 'UDM'},
        'STATUS': {'columna': 'O', 'tipo': 'string', 'nombre': 'Estatus'},
        'DATERECEIVED': {'columna': 'AH', 'tipo': 'date', 'nombre': 'Fecha de recepción'},
        'EXTERNRECEIPTKEY': {'columna': 'AN', 'tipo': 'string', 'nombre': 'N.º de ASN externo'}
    }
    
    # Headers para mostrar
    HEADERS_MOSTRAR = [
        'RECEIPTKEY',
        'SKU',
        'STORERKEY',
        'QTYRECEIVED',
        'UOM',
        'STATUS',
        'DATERECEIVED',
        'EXTERNRECEIPTKEY'
    ]
    
    # STATUS permitidos (solo 11 y 15)
    STATUS_PERMITIDOS = ['11', '15', 11, 15]
    
    def __init__(self):
        self.utils = ExcelUtils()
        self.nombre_modulo = 'RecepcionProcesador'
    
    def _convertir_a_serializable(self, obj):
        """Convierte objetos numpy a tipos nativos de Python para JSON"""
        if isinstance(obj, (np.int64, np.int32, np.int16, np.int8)):
            return int(obj)
        elif isinstance(obj, (np.float64, np.float32, np.float16)):
            return float(obj)
        elif isinstance(obj, np.bool_):
            return bool(obj)
        elif isinstance(obj, np.datetime64):
            return pd.Timestamp(obj).strftime('%d/%m/%Y %H:%M')
        elif isinstance(obj, pd.Timestamp):
            return obj.strftime('%d/%m/%Y %H:%M')
        elif isinstance(obj, pd.Series):
            return obj.tolist()
        elif isinstance(obj, np.ndarray):
            return obj.tolist()
        elif pd.isna(obj):
            return ''
        elif isinstance(obj, (datetime, pd.Timestamp)):
            return obj.strftime('%d/%m/%Y %H:%M')
        else:
            return str(obj)
    
    def _convertir_dataframe_a_lista(self, df):
        """Convierte un DataFrame a lista de listas con tipos serializables"""
        datos = []
        for _, row in df.iterrows():
            fila = []
            for valor in row:
                fila.append(self._convertir_a_serializable(valor))
            datos.append(fila)
        return datos
    
    def _extraer_parte_entera(self, valor):
        """
        Extrae solo la parte entera de un número (antes de la coma)
        
        Ejemplos:
            "144,00000" -> 144
            "65,00000" -> 65
            "28,00000" -> 28
            "0,00000" -> 0
        """
        try:
            if pd.isna(valor) or valor is None:
                return 0
            
            # Convertir a string
            valor_str = str(valor).strip()
            
            # Si contiene coma, tomar la parte antes de la coma
            if ',' in valor_str:
                parte_entera = valor_str.split(',')[0]
                # Limpiar espacios y puntos de miles
                parte_entera = parte_entera.replace('.', '').replace(' ', '')
                if parte_entera:
                    return int(float(parte_entera))
            
            # Si no tiene coma, intentar convertir directamente
            if valor_str:
                # Limpiar y convertir
                valor_limpio = valor_str.replace(',', '').replace('.', '').replace(' ', '')
                if valor_limpio:
                    return int(float(valor_limpio))
            
            return 0
            
        except Exception as e:
            logger.warning(f"Error extrayendo parte entera de '{valor}': {e}")
            return 0
    
    def _procesar_fecha(self, valor):
        """
        Procesa fecha y devuelve objeto datetime para comparación
        """
        try:
            if pd.isna(valor) or valor is None:
                return None
            
            # Si es número de Excel
            if isinstance(valor, (int, float, np.integer, np.floating)) and valor > 40000:
                return pd.Timestamp('1899-12-30') + pd.Timedelta(days=float(valor))
            
            # Si es string
            if isinstance(valor, str):
                # Intentar varios formatos
                for fmt in ['%Y-%m-%d %H:%M:%S', '%Y-%m-%d %H:%M', '%Y-%m-%d', 
                           '%d/%m/%Y %H:%M:%S', '%d/%m/%Y %H:%M', '%d/%m/%Y']:
                    try:
                        return datetime.strptime(valor, fmt)
                    except:
                        continue
            
            # Si ya es datetime
            if isinstance(valor, (datetime, pd.Timestamp)):
                return valor
            
            return None
            
        except Exception as e:
            logger.warning(f"Error procesando fecha {valor}: {e}")
            return None
    
    def aplicar_filtros_fecha(self, df, columna_fecha, fecha_desde, fecha_hasta):
        """
        Aplica filtros de fecha al DataFrame
        """
        stats = {
            'filas_filtradas_fecha': 0,
            'fecha_min': None,
            'fecha_max': None
        }
        
        if columna_fecha not in df.columns:
            return df, stats
        
        # Crear columna con fecha ISO para filtrado
        df['_FECHA_OBJ'] = None
        df['_FECHA_ISO'] = None
        
        for idx, valor in df[columna_fecha].items():
            fecha_obj = self._procesar_fecha(valor)
            if fecha_obj:
                df.at[idx, '_FECHA_OBJ'] = fecha_obj
                df.at[idx, '_FECHA_ISO'] = fecha_obj.strftime('%Y-%m-%d')
                
                # Actualizar rango de fechas
                fecha_iso = fecha_obj.strftime('%Y-%m-%d')
                if stats['fecha_min'] is None or fecha_iso < stats['fecha_min']:
                    stats['fecha_min'] = fecha_iso
                if stats['fecha_max'] is None or fecha_iso > stats['fecha_max']:
                    stats['fecha_max'] = fecha_iso
            
            # Formatear para visualización
            if fecha_obj:
                df.at[idx, columna_fecha] = fecha_obj.strftime('%d/%m/%Y %H:%M')
        
        # Aplicar filtros
        if fecha_desde or fecha_hasta:
            mask = pd.Series(True, index=df.index)
            
            if fecha_desde and '_FECHA_ISO' in df.columns:
                mask &= (df['_FECHA_ISO'] >= fecha_desde)
            
            if fecha_hasta and '_FECHA_ISO' in df.columns:
                mask &= (df['_FECHA_ISO'] <= fecha_hasta)
            
            # Contar filas filtradas
            stats['filas_filtradas_fecha'] = int((~mask).sum())
            df = df[mask].copy()
        
        return df, stats
    
    def procesar(self, archivo_path, fecha_desde=None, fecha_hasta=None, request_id=None):
        """
        Procesa archivo de recepción - Agrupa por RECEIPTKEY+SKU, suma cantidades, solo STATUS 11/15
        """
        logger.info(f"[{request_id}] ===== INICIANDO PROCESAMIENTO RECEPCIÓN =====")
        logger.info(f"[{request_id}] Archivo: {archivo_path}")
        logger.info(f"[{request_id}] Filtros: fecha_desde={fecha_desde}, fecha_hasta={fecha_hasta}")
        
        try:
            # PASO 1: Leer el archivo Excel para obtener todas las hojas
            logger.info(f"[{request_id}] Leyendo archivo Excel...")
            excel_file = pd.ExcelFile(archivo_path, engine='openpyxl')
            hojas_disponibles = excel_file.sheet_names
            logger.info(f"[{request_id}] Hojas disponibles: {hojas_disponibles}")
            
            # PASO 2: BUSCAR LA HOJA "Detail"
            hoja_detail = None
            for hoja in hojas_disponibles:
                if hoja.lower() == 'detail':
                    hoja_detail = hoja
                    logger.info(f"[{request_id}] ✓ Encontrada hoja exacta: '{hoja}'")
                    break
            
            if not hoja_detail:
                for hoja in hojas_disponibles:
                    if 'detail' in hoja.lower():
                        hoja_detail = hoja
                        logger.info(f"[{request_id}] ✓ Encontrada hoja que contiene 'detail': '{hoja}'")
                        break
            
            if not hoja_detail and len(hojas_disponibles) > 1:
                hoja_detail = hojas_disponibles[1]
                logger.info(f"[{request_id}] ⚠️ Usando segunda hoja: '{hoja_detail}'")
            elif not hoja_detail:
                hoja_detail = hojas_disponibles[0]
                logger.info(f"[{request_id}] ⚠️ Usando primera hoja: '{hoja_detail}'")
            
            logger.info(f"[{request_id}] Hoja seleccionada: '{hoja_detail}'")
            
            # PASO 3: Leer la hoja específica
            logger.info(f"[{request_id}] Leyendo hoja '{hoja_detail}'...")
            df = pd.read_excel(archivo_path, sheet_name=hoja_detail, engine='openpyxl', header=None)
            logger.info(f"[{request_id}] Hoja leída: {len(df)} filas, {len(df.columns)} columnas")
            
            # PASO 4: Identificar columnas por posición
            df_resultado = pd.DataFrame()
            
            for nombre, info in self.COLUMNAS.items():
                letra = info['columna']
                indice = self.utils.excel_col_to_index(letra)
                
                if indice < len(df.columns):
                    # La primera fila (fila 1) contiene los headers, los datos empiezan en fila 2
                    # Por eso tomamos desde la fila 1 en adelante (iloc[1:] para saltar headers)
                    if len(df) > 1:
                        df_resultado[nombre] = df.iloc[1:, indice].reset_index(drop=True)
                    else:
                        df_resultado[nombre] = []
                    logger.info(f"[{request_id}] ✓ {nombre} en columna {letra} (índice {indice})")
                else:
                    logger.warning(f"[{request_id}] ⚠️ {nombre} columna {letra} fuera de rango")
                    df_resultado[nombre] = []
            
            logger.info(f"[{request_id}] DataFrame inicial: {len(df_resultado)} filas")
            
            # PASO 5: Limpiar datos - eliminar filas donde RECEIPTKEY o SKU estén vacíos
            df_resultado = df_resultado.dropna(subset=['RECEIPTKEY', 'SKU'], how='all')
            logger.info(f"[{request_id}] Después de limpiar vacíos: {len(df_resultado)} filas")
            
            # PASO 6: Filtrar por STATUS (solo 11 y 15)
            if 'STATUS' in df_resultado.columns:
                # Convertir STATUS a string para comparar
                df_resultado['STATUS_STR'] = df_resultado['STATUS'].astype(str).str.strip()
                
                # Filtrar solo STATUS 11 o 15
                mask_status = df_resultado['STATUS_STR'].isin(['11', '15'])
                filas_filtradas_status = (~mask_status).sum()
                df_resultado = df_resultado[mask_status].copy()
                
                logger.info(f"[{request_id}] Filas filtradas por STATUS (no 11/15): {filas_filtradas_status}")
                logger.info(f"[{request_id}] Filas después de filtro STATUS: {len(df_resultado)}")
            
            # PASO 7: Procesar cantidades (extraer parte entera)
            if 'QTYRECEIVED' in df_resultado.columns:
                logger.info(f"[{request_id}] Procesando cantidades - extrayendo parte entera...")
                
                # Aplicar la función de extracción de parte entera
                df_resultado['QTY_ENTERO'] = df_resultado['QTYRECEIVED'].apply(self._extraer_parte_entera)
                
                # Mostrar estadísticas de cantidades
                logger.info(f"[{request_id}] Cantidades enteras extraídas - min: {df_resultado['QTY_ENTERO'].min()}, max: {df_resultado['QTY_ENTERO'].max()}")
            
            # PASO 8: Procesar fechas para ordenamiento
            if 'DATERECEIVED' in df_resultado.columns:
                logger.info(f"[{request_id}] Procesando fechas...")
                df_resultado['FECHA_OBJ'] = df_resultado['DATERECEIVED'].apply(self._procesar_fecha)
            
            # PASO 9: AGRUPAR POR RECEIPTKEY + SKU
            logger.info(f"[{request_id}] Agrupando por RECEIPTKEY + SKU...")
            
            # Función para agregar cada grupo
            def agregar_grupo(grupo):
                if len(grupo) == 0:
                    return None
                
                # Tomar el primer registro para campos que se mantienen iguales
                primer_registro = grupo.iloc[0]
                
                # Sumar las cantidades (QTY_ENTERO)
                cantidad_total = grupo['QTY_ENTERO'].sum() if 'QTY_ENTERO' in grupo.columns else 0
                
                # Encontrar la fecha más reciente
                if 'FECHA_OBJ' in grupo.columns:
                    # Eliminar fechas nulas
                    fechas_validas = grupo['FECHA_OBJ'].dropna()
                    if len(fechas_validas) > 0:
                        # Ordenar por fecha y tomar la más reciente
                        fecha_reciente = fechas_validas.sort_values(ascending=False).iloc[0]
                        fecha_str = fecha_reciente.strftime('%d/%m/%Y %H:%M')
                    else:
                        fecha_str = grupo['DATERECEIVED'].iloc[0] if 'DATERECEIVED' in grupo.columns else ''
                else:
                    fecha_str = grupo['DATERECEIVED'].iloc[0] if 'DATERECEIVED' in grupo.columns else ''
                
                # Crear registro agregado
                return pd.Series({
                    'RECEIPTKEY': primer_registro['RECEIPTKEY'],
                    'SKU': primer_registro['SKU'],
                    'STORERKEY': primer_registro['STORERKEY'] if 'STORERKEY' in grupo.columns else '',
                    'QTYRECEIVED': cantidad_total,
                    'UOM': primer_registro['UOM'] if 'UOM' in grupo.columns else '',
                    'STATUS': primer_registro['STATUS'] if 'STATUS' in grupo.columns else '',
                    'DATERECEIVED': fecha_str,
                    'EXTERNRECEIPTKEY': primer_registro['EXTERNRECEIPTKEY'] if 'EXTERNRECEIPTKEY' in grupo.columns else '',
                    'CANTIDAD_REGISTROS_ORIGINALES': len(grupo)  # Para estadísticas
                })
            
            # Aplicar agrupación
            if len(df_resultado) > 0 and 'RECEIPTKEY' in df_resultado.columns and 'SKU' in df_resultado.columns:
                # Crear columna combinada para agrupar
                df_resultado['GRUPO_KEY'] = df_resultado['RECEIPTKEY'].astype(str) + '_' + df_resultado['SKU'].astype(str)
                
                # Contar grupos antes de agrupar
                grupos_unicos = df_resultado['GRUPO_KEY'].nunique()
                logger.info(f"[{request_id}] Grupos únicos encontrados: {grupos_unicos}")
                
                # Aplicar agrupación
                df_agrupado = df_resultado.groupby('GRUPO_KEY').apply(agregar_grupo).reset_index(drop=True)
                
                logger.info(f"[{request_id}] Después de agrupar: {len(df_agrupado)} filas")
                
                # Calcular estadísticas de agrupación
                stats_agrupacion = {
                    'filas_originales': len(df_resultado),
                    'filas_agrupadas': len(df_agrupado),
                    'reduccion': len(df_resultado) - len(df_agrupado)
                }
                logger.info(f"[{request_id}] Reducción por agrupación: {stats_agrupacion['reduccion']} filas")
            else:
                df_agrupado = df_resultado
                stats_agrupacion = {
                    'filas_originales': len(df_resultado),
                    'filas_agrupadas': len(df_resultado),
                    'reduccion': 0
                }
            
            # PASO 10: Aplicar filtros de fecha (después de agrupar)
            stats_fecha = {'filas_filtradas_fecha': 0, 'fecha_min': None, 'fecha_max': None}
            
            # Reconstruir columna de fecha para filtrado si es necesario
            if fecha_desde or fecha_hasta:
                # Necesitamos fechas en formato ISO para filtrar
                df_agrupado['_FECHA_ISO_FILTRO'] = None
                for idx, row in df_agrupado.iterrows():
                    fecha_val = row['DATERECEIVED']
                    if fecha_val:
                        # Intentar convertir la fecha ya formateada de vuelta a objeto
                        try:
                            fecha_obj = datetime.strptime(str(fecha_val), '%d/%m/%Y %H:%M')
                            df_agrupado.at[idx, '_FECHA_ISO_FILTRO'] = fecha_obj.strftime('%Y-%m-%d')
                        except:
                            pass
                
                # Aplicar filtros
                mask_fecha = pd.Series(True, index=df_agrupado.index)
                if fecha_desde:
                    mask_fecha &= (df_agrupado['_FECHA_ISO_FILTRO'] >= fecha_desde)
                if fecha_hasta:
                    mask_fecha &= (df_agrupado['_FECHA_ISO_FILTRO'] <= fecha_hasta)
                
                stats_fecha['filas_filtradas_fecha'] = int((~mask_fecha).sum())
                df_agrupado = df_agrupado[mask_fecha].copy()
            
            # PASO 11: Calcular estadísticas finales
            total_registros_final = int(len(df_agrupado))
            
            # Formatear QTYRECEIVED para visualización
            if 'QTYRECEIVED' in df_agrupado.columns:
                df_agrupado['QTYRECEIVED'] = df_agrupado['QTYRECEIVED'].apply(
                    lambda x: self.utils.formatear_numero(x, 0)  # 0 decimales porque ya son enteros
                )
            
            stats = {
                'total_filas': total_registros_final,
                'mostrando': min(100, total_registros_final),
                'filas_originales': stats_agrupacion['filas_originales'],
                'filas_agrupadas': stats_agrupacion['filas_agrupadas'],
                'reduccion_agrupacion': stats_agrupacion['reduccion'],
                'filas_filtradas_status': filas_filtradas_status if 'filas_filtradas_status' in locals() else 0,
                'filas_filtradas_fecha': int(stats_fecha['filas_filtradas_fecha']),
                'fecha_min': stats_fecha['fecha_min'],
                'fecha_max': stats_fecha['fecha_max'],
                'filtros_aplicados': {
                    'fecha_desde': fecha_desde,
                    'fecha_hasta': fecha_hasta
                },
                'hoja_procesada': hoja_detail
            }
            
            # Calcular estadísticas adicionales
            if total_registros_final > 0:
                if 'RECEIPTKEY' in df_agrupado.columns:
                    stats['receiptkeys_unicos'] = int(df_agrupado['RECEIPTKEY'].nunique())
                else:
                    stats['receiptkeys_unicos'] = 0
                
                if 'QTYRECEIVED' in df_agrupado.columns:
                    # Extraer valores numéricos para suma
                    total_qty = 0
                    for val in df_agrupado['QTYRECEIVED']:
                        try:
                            # Limpiar formato (ej: "525,00000" -> 525)
                            if isinstance(val, str) and ',' in val:
                                num = int(val.split(',')[0].replace('.', ''))
                            else:
                                num = int(float(str(val).replace(',', '').replace('.', '')))
                            total_qty += num
                        except:
                            pass
                    
                    stats['total_unidades'] = self.utils.formatear_numero(total_qty, 0)
                else:
                    stats['total_unidades'] = '0'
            
            logger.info(f"[{request_id}] ESTADÍSTICAS FINALES:")
            logger.info(f"[{request_id}]   - Filas originales: {stats['filas_originales']}")
            logger.info(f"[{request_id}]   - Filas después de agrupar: {stats['filas_agrupadas']}")
            logger.info(f"[{request_id}]   - Filas filtradas por STATUS: {stats['filas_filtradas_status']}")
            logger.info(f"[{request_id}]   - Filas filtradas por fecha: {stats['filas_filtradas_fecha']}")
            logger.info(f"[{request_id}]   - Registros finales: {stats['total_filas']}")
            
            # PASO 12: Preparar datos para vista previa
            columnas_existentes = [col for col in self.HEADERS_MOSTRAR if col in df_agrupado.columns]
            
            if len(columnas_existentes) < len(self.HEADERS_MOSTRAR):
                for col in self.HEADERS_MOSTRAR:
                    if col not in df_agrupado.columns:
                        df_agrupado[col] = ''
                columnas_existentes = self.HEADERS_MOSTRAR
            
            df_preview = df_agrupado[columnas_existentes].head(100).copy()
            datos_preview = self._convertir_dataframe_a_lista(df_preview)
            
            # PASO 13: Generar mensajes
            mensajes = {
                'mensaje_hoja': f"Datos extraídos de la hoja: '{hoja_detail}'",
                'mensaje_agrupacion': f"Se agruparon {stats['filas_originales']} registros en {stats['filas_agrupadas']} grupos (reducción de {stats['reduccion_agrupacion']} filas)"
            }
            
            if stats['filas_filtradas_status'] > 0:
                mensajes['mensaje_status'] = f"Se filtraron {stats['filas_filtradas_status']} registros con STATUS diferente de 11/15"
            
            if stats['filas_filtradas_fecha'] > 0:
                mensajes['mensaje_fecha'] = f"Se filtraron {stats['filas_filtradas_fecha']} registros por rango de fechas"
            
            logger.info(f"[{request_id}] Procesamiento completado exitosamente")
            
            return {
                'success': True,
                'total_registros': total_registros_final,
                'headers': columnas_existentes,
                'data': datos_preview,
                'stats': stats,
                **mensajes
            }
            
        except Exception as e:
            logger.error(f"[{request_id}] Error en procesamiento: {str(e)}", exc_info=True)
            raise