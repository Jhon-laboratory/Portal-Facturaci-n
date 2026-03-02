<?php
// login.php
session_start();

// Si ya está logueado, redirigir
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

// Incluir conexión
require_once 'conexion/conexion.php';

$error = '';

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($login) || empty($password)) {
        $error = "Por favor ingrese usuario y contraseña";
    } else {
        try {
            // Determinar si es email o nombre de usuario (SIN la columna activo)
            if (strpos($login, '@') !== false) {
                $sql = "SELECT * FROM DPL.IT.usuarios_pt WHERE correo = ?";
            } else {
                $sql = "SELECT * FROM DPL.IT.usuarios_pt WHERE nombre = ?";
            }
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([$login]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                // Verificar contraseña
                if (password_verify($password, $user['contrasena'])) {
                    
                    // ============================================
                    // OBTENER PERMISOS DEL USUARIO
                    // ============================================
                    $sql_permisos = "SELECT pu.*, c.nombre_comercial 
                                     FROM [FacBol].[permisos_usuarios] pu
                                     INNER JOIN [FacBol].[clientes] c ON pu.cliente_codigo = c.codigo_cliente
                                     WHERE pu.usuario_id = ? AND pu.activo = 1";
                    
                    $stmt_permisos = $conn->prepare($sql_permisos);
                    $stmt_permisos->execute([$user['id']]);
                    $permisos = $stmt_permisos->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (empty($permisos)) {
                        $error = "El usuario no tiene permisos asignados para ningún cliente";
                    } else {
                        // Determinar el rol máximo del usuario
                        $tipos_usuario = array_column($permisos, 'tipo_usuario');
                        $rol_maximo = min($tipos_usuario); // El número más bajo es el rol más alto (1=Admin)
                        
                        // Guardar datos básicos del usuario
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_name'] = $user['nombre'];
                        $_SESSION['user_email'] = $user['correo'];
                        $_SESSION['user_rol'] = $rol_maximo;
                        $_SESSION['user_rol_nombre'] = ($rol_maximo == 1) ? 'Administrador' : 
                                                        (($rol_maximo == 2) ? 'Supervisor' : 'Verificador');
                        
                        // Guardar permisos por cliente
                        $_SESSION['permisos_clientes'] = [];
                        foreach ($permisos as $permiso) {
                            $_SESSION['permisos_clientes'][$permiso['cliente_codigo']] = [
                                'tipo_usuario' => $permiso['tipo_usuario'],
                                'nombre_comercial' => $permiso['nombre_comercial']
                            ];
                        }
                        
                        // Guardar lista de clientes a los que tiene acceso
                        $_SESSION['clientes_acceso'] = array_keys($_SESSION['permisos_clientes']);
                        
                        // Redirigir al dashboard
                        header('Location: dashboard.php');
                        exit;
                    }
                    
                } else {
                    $error = "Contraseña incorrecta";
                }
            } else {
                $error = "Usuario no encontrado";
            }
        } catch (PDOException $e) {
            $error = "Error en la base de datos: " . $e->getMessage();
            error_log("Error en login: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistema</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }
        body {
            background: linear-gradient(135deg, #009A3F, #00C851);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 400px;
        }
        h1 {
            color: #009A3F;
            text-align: center;
            margin-bottom: 10px;
            font-size: 24px;
        }
        .subtitle {
            text-align: center;
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            color: #333;
            font-weight: bold;
            font-size: 14px;
        }
        input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            font-size: 14px;
            transition: 0.3s;
        }
        input:focus {
            border-color: #009A3F;
            outline: none;
        }
        button {
            width: 100%;
            padding: 12px;
            background: #009A3F;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.3s;
        }
        button:hover {
            background: #00C851;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 14px;
            text-align: center;
        }
        .info {
            text-align: center;
            margin-top: 20px;
            font-size: 12px;
            color: #999;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h1>LOGIRAN S.A.</h1>
        <div class="subtitle">Sistema de Cotización</div>
        
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="post">
            <div class="form-group">
                <label>Usuario o Correo</label>
                <input type="text" name="login" required 
                       value="<?php echo isset($_POST['login']) ? htmlspecialchars($_POST['login']) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label>Contraseña</label>
                <input type="password" name="password" required>
            </div>
            
            <button type="submit">Ingresar</button>
        </form>
        
        <div class="info">
            Sistema interno - Uso autorizado únicamente por personal de LOGIRAN S.A.
        </div>
    </div>
</body>
</html>