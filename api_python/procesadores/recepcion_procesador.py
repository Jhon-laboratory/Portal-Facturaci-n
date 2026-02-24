#!/usr/bin/env python
# -*- coding: utf-8 -*-

"""
Procesador específico para el módulo de Recepción
VERSIÓN CORREGIDA - Manejo de fechas SIN HORA para filtros correctos
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

class RecepcionProcesador:
    """Procesador para archivos de recepción - Agrupación por RECEIPTKEY+SKU"""
    
    # MAPEO DE COLUMNAS
    COLUMNAS = {
        'RECEIPTKEY': {'columna': 'C', 'tipo': 'string', 'nombre': 'ASN/Recepción'},
        'SKU': {'columna': 'D', 'tipo': 'string', 'nombre': 'Artículo'},
        'STORERKEY': {'columna': 'E', 'tipo': 'string', 'nombre': 'Propietario'},
        'QTYRECEIVED': {'columna': 'H', 'tipo': 'float', 'nombre': 'Ctd. recibida'},
        'UOM': {'columna': 'I', 'tipo': 'string', 'nombre': 'UDM'},
        'STATUS': {'columna': 'O', 'tipo': 'string', 'nombre': 'Estatus'},
        'DATERECEIVED': {'columna': 'AH', 'tipo': 'date', 'nombre': 'Fecha de recepción'},
        'EXTERNRECEIPTKEY': {'columna': 'AN', 'tipo': 'string', 'nombre': 'N.º de ASN externo'}
    }
    
    # Headers para mostrar (con UOM desglosado)
    HEADERS_MOSTRAR = [
        'RECEIPTKEY',
        'SKU',
        'STORERKEY',
        'UNIDADES',      # Para UOM = UN
        'CAJAS',         # Para UOM = CJ
        'PALLETS',       # Para UOM = PL
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
        """
        Convierte objetos numpy a tipos nativos de Python para JSON
        """
        # Si es None o NaN
        if obj is None or pd.isna(obj):
            return ''
        
        # Manejar todos los tipos de numpy enteros
        if isinstance(obj, (np.int8, np.int16, np.int32, np.int64)):
            return int(obj)
        
        # Manejar todos los tipos de numpy flotantes
        if isinstance(obj, (np.float16, np.float32, np.float64)):
            # Si es un número entero, devolver como entero
            if float(obj).is_integer():
                return int(obj)
            return float(obj)
        
        # Manejar numpy boolean
        if isinstance(obj, np.bool_):
            return bool(obj)
        
        # Manejar numpy datetime
        if isinstance(obj, np.datetime64):
            return pd.Timestamp(obj).strftime('%d/%m/%Y')
        
        # Manejar pandas Timestamp
        if isinstance(obj, pd.Timestamp):
            return obj.strftime('%d/%m/%Y')
        
        # Manejar pandas Series
        if isinstance(obj, pd.Series):
            return obj.tolist()
        
        # Manejar numpy arrays
        if isinstance(obj, np.ndarray):
            return obj.tolist()
        
        # Manejar datetime
        if isinstance(obj, datetime):
            return obj.strftime('%d/%m/%Y')
        
        # Si es un número pero no numpy, asegurar conversión
        if isinstance(obj, (int, float)):
            return obj
        
        # Para strings y otros tipos
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
            
            # Si ya es número
            if isinstance(valor, (int, float, np.integer, np.floating)):
                return int(float(valor))
            
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
        SOLO LA FECHA (sin hora) - EXTREMADAMENTE IMPORTANTE PARA FILTROS
        """
        try:
            if pd.isna(valor) or valor is None:
                return None
            
            # Usar la función de utils que ya maneja fechas sin hora
            fecha_formateada, fecha_iso = self.utils.convertir_fecha_excel(valor)
            
            if fecha_iso:
                # Crear objeto datetime desde la fecha ISO (YYYY-MM-DD)
                anio, mes, dia = map(int, fecha_iso.split('-'))
                return datetime(anio, mes, dia)
            
            return None
            
        except Exception as e:
            logger.warning(f"Error procesando fecha {valor}: {e}")
            return None
    
    def aplicar_filtros_fecha(self, df, columna_fecha, fecha_desde, fecha_hasta):
        """
        Aplica filtros de fecha al DataFrame - COMPARANDO SOLO FECHAS SIN HORA
        """
        stats = {
            'filas_filtradas_fecha': 0,
            'fecha_min': None,
            'fecha_max': None
        }
        
        if columna_fecha not in df.columns:
            return df, stats
        
        # Crear columna con fecha ISO para filtrado (SOLO FECHA)
        df['_FECHA_OBJ'] = None
        df['_FECHA_ISO'] = None
        
        # LOG PARA DEPURACIÓN
        logger.info(f"Procesando columna de fechas: {columna_fecha}")
        muestras = []
        
        for idx, valor in df[columna_fecha].items():
            fecha_obj = self._procesar_fecha(valor)
            if fecha_obj:
                df.at[idx, '_FECHA_OBJ'] = fecha_obj
                fecha_iso = fecha_obj.strftime('%Y-%m-%d')
                df.at[idx, '_FECHA_ISO'] = fecha_iso
                
                # Guardar muestra para log
                if len(muestras) < 5:
                    muestras.append(f"{valor} -> {fecha_iso}")
                
                # Actualizar rango de fechas
                if stats['fecha_min'] is None or fecha_iso < stats['fecha_min']:
                    stats['fecha_min'] = fecha_iso
                if stats['fecha_max'] is None or fecha_iso > stats['fecha_max']:
                    stats['fecha_max'] = fecha_iso
            
            # Formatear para visualización (SOLO FECHA)
            if fecha_obj:
                df.at[idx, columna_fecha] = fecha_obj.strftime('%d/%m/%Y')
        
        # LOG DE MUESTRAS
        logger.info(f"Muestras de conversión de fechas: {muestras}")
        logger.info(f"Rango de fechas en datos: {stats['fecha_min']} - {stats['fecha_max']}")
        
        # Aplicar filtros - EXTREMADAMENTE IMPORTANTE: Comparar SOLO la fecha
        if fecha_desde or fecha_hasta:
            mask = pd.Series(True, index=df.index)
            
            # LOG DE FILTROS RECIBIDOS
            logger.info(f"Filtros recibidos - desde: '{fecha_desde}', hasta: '{fecha_hasta}'")
            
            if fecha_desde and '_FECHA_ISO' in df.columns:
                # Extraer SOLO la fecha de fecha_desde (ignorar hora)
                fecha_desde_sin_hora = fecha_desde.split(' ')[0] if ' ' in fecha_desde else fecha_desde
                # Asegurar formato YYYY-MM-DD
                if len(fecha_desde_sin_hora) == 10 and fecha_desde_sin_hora[4] == '-' and fecha_desde_sin_hora[7] == '-':
                    mask &= (df['_FECHA_ISO'] >= fecha_desde_sin_hora)
                    logger.info(f"Filtrando desde fecha: {fecha_desde_sin_hora}")
                else:
                    logger.warning(f"Formato de fecha_desde no reconocido: {fecha_desde_sin_hora}")
            
            if fecha_hasta and '_FECHA_ISO' in df.columns:
                # Extraer SOLO la fecha de fecha_hasta (ignorar hora)
                fecha_hasta_sin_hora = fecha_hasta.split(' ')[0] if ' ' in fecha_hasta else fecha_hasta
                # Asegurar formato YYYY-MM-DD
                if len(fecha_hasta_sin_hora) == 10 and fecha_hasta_sin_hora[4] == '-' and fecha_hasta_sin_hora[7] == '-':
                    mask &= (df['_FECHA_ISO'] <= fecha_hasta_sin_hora)
                    logger.info(f"Filtrando hasta fecha: {fecha_hasta_sin_hora}")
                else:
                    logger.warning(f"Formato de fecha_hasta no reconocido: {fecha_hasta_sin_hora}")
            
            stats['filas_filtradas_fecha'] = int((~mask).sum())
            df = df[mask].copy()
            logger.info(f"Filas después de filtro fecha: {len(df)}")
        
        return df, stats
    
    def procesar(self, archivo_path, fecha_desde=None, fecha_hasta=None, request_id=None):
        """
        Procesa archivo de recepción - Agrupa por RECEIPTKEY+SKU, suma cantidades, solo STATUS 11/15
        """
        logger.info(f"[{request_id}] ===== INICIANDO PROCESAMIENTO RECEPCIÓN =====")
        logger.info(f"[{request_id}] Archivo: {archivo_path}")
        logger.info(f"[{request_id}] Filtros recibidos: fecha_desde='{fecha_desde}', fecha_hasta='{fecha_hasta}'")
        
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
                    # La primera fila (fila 0) contiene los headers, los datos empiezan en fila 1
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
            filas_filtradas_status = 0
            if 'STATUS' in df_resultado.columns and len(df_resultado) > 0:
                # Convertir STATUS a string para comparar
                df_resultado['STATUS_STR'] = df_resultado['STATUS'].astype(str).str.strip()
                
                # Filtrar solo STATUS 11 o 15
                mask_status = df_resultado['STATUS_STR'].isin(['11', '15'])
                filas_filtradas_status = int((~mask_status).sum())
                df_resultado = df_resultado[mask_status].copy()
                
                logger.info(f"[{request_id}] Filas filtradas por STATUS (no 11/15): {filas_filtradas_status}")
                logger.info(f"[{request_id}] Filas después de filtro STATUS: {len(df_resultado)}")
            
            # PASO 7: Procesar cantidades (extraer parte entera)
            if 'QTYRECEIVED' in df_resultado.columns and len(df_resultado) > 0:
                logger.info(f"[{request_id}] Procesando cantidades - extrayendo parte entera...")
                
                # Aplicar la función de extracción de parte entera
                df_resultado['QTY_ENTERO'] = df_resultado['QTYRECEIVED'].apply(self._extraer_parte_entera)
                
                # Mostrar estadísticas de cantidades
                if len(df_resultado) > 0:
                    logger.info(f"[{request_id}] Cantidades enteras extraídas - min: {df_resultado['QTY_ENTERO'].min()}, max: {df_resultado['QTY_ENTERO'].max()}")
            
            # PASO 8: Procesar fechas para ordenamiento y filtros
            if 'DATERECEIVED' in df_resultado.columns and len(df_resultado) > 0:
                logger.info(f"[{request_id}] Procesando fechas...")
                # Aplicar filtros de fecha (esto también formatea las fechas)
                df_resultado, stats_fecha_temp = self.aplicar_filtros_fecha(
                    df_resultado, 
                    'DATERECEIVED', 
                    fecha_desde, 
                    fecha_hasta
                )
                # Guardar FECHA_OBJ para uso posterior
                if '_FECHA_OBJ' in df_resultado.columns:
                    df_resultado['FECHA_OBJ'] = df_resultado['_FECHA_OBJ']
            else:
                stats_fecha_temp = {'filas_filtradas_fecha': 0, 'fecha_min': None, 'fecha_max': None}
            
            # PASO 9: AGRUPAR POR RECEIPTKEY + SKU
            stats_agrupacion = {
                'filas_originales': len(df_resultado) if len(df_resultado) > 0 else 0,
                'filas_agrupadas': 0,
                'reduccion': 0
            }
            
            if len(df_resultado) > 0 and 'RECEIPTKEY' in df_resultado.columns and 'SKU' in df_resultado.columns:
                logger.info(f"[{request_id}] Agrupando por RECEIPTKEY + SKU...")
                
                # Función para agregar cada grupo
                def agregar_grupo(grupo):
                    if len(grupo) == 0:
                        return None
                    
                    # Tomar el primer registro para campos que se mantienen iguales
                    primer_registro = grupo.iloc[0]
                    
                    # Sumar las cantidades (QTY_ENTERO)
                    cantidad_total = int(grupo['QTY_ENTERO'].sum()) if 'QTY_ENTERO' in grupo.columns else 0
                    
                    # Determinar UOM del grupo (tomar el del primer registro)
                    uom = primer_registro['UOM'] if 'UOM' in grupo.columns else ''
                    
                    # Encontrar la fecha más reciente (usando FECHA_OBJ si existe)
                    fecha_str = ''
                    if 'FECHA_OBJ' in grupo.columns:
                        # Eliminar fechas nulas
                        fechas_validas = grupo['FECHA_OBJ'].dropna()
                        if len(fechas_validas) > 0:
                            # Ordenar por fecha y tomar la más reciente
                            fecha_reciente = fechas_validas.sort_values(ascending=False).iloc[0]
                            fecha_str = fecha_reciente.strftime('%d/%m/%Y')  # SIN HORA
                        else:
                            fecha_str = grupo['DATERECEIVED'].iloc[0] if 'DATERECEIVED' in grupo.columns else ''
                    
                    # Crear registro agregado (con UOM desglosado)
                    resultado = {
                        'RECEIPTKEY': primer_registro['RECEIPTKEY'],
                        'SKU': primer_registro['SKU'],
                        'STORERKEY': primer_registro['STORERKEY'] if 'STORERKEY' in grupo.columns else '',
                        'UNIDADES': 0,
                        'CAJAS': 0,
                        'PALLETS': 0,
                        'STATUS': primer_registro['STATUS'] if 'STATUS' in grupo.columns else '',
                        'DATERECEIVED': fecha_str,
                        'EXTERNRECEIPTKEY': primer_registro['EXTERNRECEIPTKEY'] if 'EXTERNRECEIPTKEY' in grupo.columns else '',
                        'CANTIDAD_REGISTROS_ORIGINALES': len(grupo)
                    }
                    
                    # Asignar la cantidad según el tipo de UOM
                    uom_str = str(uom).strip().upper() if pd.notna(uom) else ''
                    if uom_str == 'UN':
                        resultado['UNIDADES'] = cantidad_total
                    elif uom_str == 'CJ':
                        resultado['CAJAS'] = cantidad_total
                    elif uom_str == 'PL':
                        resultado['PALLETS'] = cantidad_total
                    else:
                        # Si no es UN, CJ o PL, poner en UNIDADES por defecto
                        resultado['UNIDADES'] = cantidad_total
                    
                    return pd.Series(resultado)
                
                # Crear columna combinada para agrupar
                df_resultado['GRUPO_KEY'] = df_resultado['RECEIPTKEY'].astype(str) + '_' + df_resultado['SKU'].astype(str)
                
                # Contar grupos antes de agrupar
                grupos_unicos = df_resultado['GRUPO_KEY'].nunique()
                logger.info(f"[{request_id}] Grupos únicos encontrados: {grupos_unicos}")
                
                # Aplicar agrupación
                df_agrupado = df_resultado.groupby('GRUPO_KEY').apply(agregar_grupo).reset_index(drop=True)
                
                logger.info(f"[{request_id}] Después de agrupar: {len(df_agrupado)} filas")
                
                stats_agrupacion = {
                    'filas_originales': len(df_resultado),
                    'filas_agrupadas': len(df_agrupado),
                    'reduccion': len(df_resultado) - len(df_agrupado)
                }
                logger.info(f"[{request_id}] Reducción por agrupación: {stats_agrupacion['reduccion']} filas")
            else:
                df_agrupado = df_resultado if len(df_resultado) > 0 else pd.DataFrame()
                stats_agrupacion = {
                    'filas_originales': len(df_resultado) if len(df_resultado) > 0 else 0,
                    'filas_agrupadas': len(df_agrupado) if len(df_agrupado) > 0 else 0,
                    'reduccion': 0
                }
            
            # PASO 10: Calcular estadísticas finales
            total_registros_final = int(len(df_agrupado)) if len(df_agrupado) > 0 else 0
            
            # Calcular totales por tipo de UOM
            total_unidades = 0
            total_cajas = 0
            total_pallets = 0
            
            if len(df_agrupado) > 0:
                total_unidades = int(df_agrupado['UNIDADES'].sum()) if 'UNIDADES' in df_agrupado.columns else 0
                total_cajas = int(df_agrupado['CAJAS'].sum()) if 'CAJAS' in df_agrupado.columns else 0
                total_pallets = int(df_agrupado['PALLETS'].sum()) if 'PALLETS' in df_agrupado.columns else 0
            
            stats = {
                'total_filas': int(total_registros_final),
                'mostrando': int(min(100, total_registros_final)),
                'filas_originales': int(stats_agrupacion['filas_originales']),
                'filas_agrupadas': int(stats_agrupacion['filas_agrupadas']),
                'reduccion_agrupacion': int(stats_agrupacion['reduccion']),
                'filas_filtradas_status': int(filas_filtradas_status),
                'filas_filtradas_fecha': int(stats_fecha_temp['filas_filtradas_fecha']),
                'fecha_min': self._convertir_a_serializable(stats_fecha_temp['fecha_min']),
                'fecha_max': self._convertir_a_serializable(stats_fecha_temp['fecha_max']),
                'filtros_aplicados': {
                    'fecha_desde': self._convertir_a_serializable(fecha_desde.split(' ')[0] if fecha_desde and ' ' in fecha_desde else fecha_desde),
                    'fecha_hasta': self._convertir_a_serializable(fecha_hasta.split(' ')[0] if fecha_hasta and ' ' in fecha_hasta else fecha_hasta)
                },
                'hoja_procesada': str(hoja_detail),
                'total_unidades': self._convertir_a_serializable(total_unidades),
                'total_cajas': self._convertir_a_serializable(total_cajas),
                'total_pallets': self._convertir_a_serializable(total_pallets)
            }
            
            # Calcular estadísticas adicionales
            if total_registros_final > 0:
                if 'RECEIPTKEY' in df_agrupado.columns:
                    stats['receiptkeys_unicos'] = int(df_agrupado['RECEIPTKEY'].nunique())
                else:
                    stats['receiptkeys_unicos'] = 0
            
            logger.info(f"[{request_id}] ESTADÍSTICAS FINALES:")
            logger.info(f"[{request_id}]   - Filas originales: {stats['filas_originales']}")
            logger.info(f"[{request_id}]   - Filas después de agrupar: {stats['filas_agrupadas']}")
            logger.info(f"[{request_id}]   - Filas filtradas por STATUS: {stats['filas_filtradas_status']}")
            logger.info(f"[{request_id}]   - Filas filtradas por fecha: {stats['filas_filtradas_fecha']}")
            logger.info(f"[{request_id}]   - Registros finales: {stats['total_filas']}")
            logger.info(f"[{request_id}]   - Total UNIDADES: {stats['total_unidades']}")
            logger.info(f"[{request_id}]   - Total CAJAS: {stats['total_cajas']}")
            logger.info(f"[{request_id}]   - Total PALLETS: {stats['total_pallets']}")
            
            # PASO 11: Preparar datos para vista previa
            if len(df_agrupado) > 0:
                columnas_existentes = [col for col in self.HEADERS_MOSTRAR if col in df_agrupado.columns]
                
                if len(columnas_existentes) < len(self.HEADERS_MOSTRAR):
                    for col in self.HEADERS_MOSTRAR:
                        if col not in df_agrupado.columns:
                            df_agrupado[col] = 0 if col in ['UNIDADES', 'CAJAS', 'PALLETS'] else ''
                    columnas_existentes = self.HEADERS_MOSTRAR
                
                df_preview = df_agrupado[columnas_existentes].head(100).copy()
                datos_preview = self._convertir_dataframe_a_lista(df_preview)
            else:
                datos_preview = []
                columnas_existentes = self.HEADERS_MOSTRAR
            
            # PASO 12: Generar mensajes
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
                'total_registros': int(total_registros_final),
                'headers': columnas_existentes,
                'data': datos_preview,
                'stats': stats,
                **mensajes
            }
            
        except Exception as e:
            logger.error(f"[{request_id}] Error en procesamiento: {str(e)}", exc_info=True)
            raise