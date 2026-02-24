<?php
// conexion.php
$serverName = "Jorgeserver.database.windows.net";
$database = "DPL";
$username = "Jmmc";
$password = "ChaosSoldier01";

try {
    // Conexión con PDO (recomendado)
    $conn = new PDO(
        "sqlsrv:server=$serverName;Database=$database",
        $username,
        $password
    );
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Opcional: probar conexión
    // echo "✅ Conexión exitosa a SQL Server";
    
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}
?>