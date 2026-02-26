<?php
// conexion/config.php

// Configuración de conexión SQL Server
define('DB_HOST', 'Jorgeserver.database.windows.net');
define('DB_NAME', 'DPL');
define('DB_USER', 'Jmmc');
define('DB_PASS', 'ChaosSoldier01');

// Configuración de entorno
define('ENVIRONMENT', 'development'); // 'development' o 'production'

// Definiciones de tablas (NUEVO)
define('TABLA_FACTURAS', '[FacBol].[facturas_cabecera]');
define('TABLA_RECEPCION', '[FacBol].[facturas_recepcion_detalle]');
define('TABLA_DESPACHO', '[FacBol].[facturas_despacho_detalle]');
define('TABLA_PAQUETE', '[FacBol].[facturas_paquete_detalle]');
define('TABLA_ALMACEN', '[FacBol].[facturas_almacen_detalle]');
define('TABLA_CLIENTES', '[FacBol].[clientes]'); // Por si la necesitas

if (ENVIRONMENT === 'development') {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}

// Función de ayuda para obtener conexión
function getDBConnection() {
    try {
        $conn = new PDO(
            "sqlsrv:server=" . DB_HOST . ";Database=" . DB_NAME,
            DB_USER,
            DB_PASS
        );
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $conn;
    } catch (PDOException $e) {
        error_log("Error de conexión: " . $e->getMessage());
        throw new Exception("Error al conectar con la base de datos");
    }
}

// Función helper para logging (opcional pero útil)
function logError($mensaje, $datos = null) {
    $log = date('Y-m-d H:i:s') . " - " . $mensaje;
    if ($datos) {
        $log .= " - " . print_r($datos, true);
    }
    error_log($log);
}
?>