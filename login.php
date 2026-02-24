<?php
// login.php
session_start();

// Si ya está logueado, redirigir
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

// Incluir conexión
//require_once 'conexion.php';
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
            // Determinar si es email o nombre de usuario
            if (strpos($login, '@') !== false) {
                $sql = "SELECT * FROM IT.usuarios_pt WHERE correo = ?";
            } else {
                $sql = "SELECT * FROM IT.usuarios_pt WHERE nombre = ?";
            }
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([$login]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Verificar contraseña (asumiendo que está hasheada con password_hash)
                if (password_verify($password, $user['contrasena'])) {
                    // Guardar datos en sesión
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['nombre'];
                    $_SESSION['user_email'] = $user['correo'];
                    $_SESSION['user_type'] = $user['tipo_usuario'];
                    
                    // Redirigir
                    header('Location: dashboard.php');
                    exit;
                } else {
                    $error = "Contraseña incorrecta";
                }
            } else {
                $error = "Usuario no encontrado";
            }
        } catch (PDOException $e) {
            $error = "Error en la base de datos: " . $e->getMessage();
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