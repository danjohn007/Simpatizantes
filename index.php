<?php
/**
 * Página de Login
 * Sistema de Validación de Simpatizantes
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/app/controllers/AuthController.php';

$auth = new AuthController();

// Redirigir si ya está autenticado
if ($auth->estaAutenticado()) {
    header('Location: ' . BASE_URL . '/public/dashboard.php');
    exit;
}

// Cargar colores personalizados desde configuración
$db_colors = Database::getInstance();
$sql_colors = "SELECT clave, valor FROM configuracion WHERE tipo = 'color'";
$colores = $db_colors->query($sql_colors);
$color_primario = '#667eea';
$color_secundario = '#764ba2';
foreach ($colores as $color) {
    if ($color['clave'] === 'color_primario') $color_primario = $color['valor'];
    if ($color['clave'] === 'color_secundario') $color_secundario = $color['valor'];
}

$error = '';
$success = '';

// Procesar login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = $auth->login();
    
    if (isset($result['success'])) {
        header('Location: ' . BASE_URL . '/public/dashboard.php');
        exit;
    } else {
        $error = $result['error'] ?? 'Error al iniciar sesión';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, <?php echo $color_primario; ?> 0%, <?php echo $color_secundario; ?> 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        .login-header {
            background: linear-gradient(135deg, <?php echo $color_primario; ?> 0%, <?php echo $color_secundario; ?> 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .login-body {
            padding: 40px;
        }
        .btn-login {
            background: linear-gradient(135deg, <?php echo $color_primario; ?> 0%, <?php echo $color_secundario; ?> 100%);
            border: none;
            color: white;
            padding: 12px;
            font-weight: 500;
            transition: transform 0.2s;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="login-card">
                    <div class="login-header">
                        <i class="bi bi-person-check-fill" style="font-size: 3rem;"></i>
                        <h3 class="mt-3 mb-1"><?php echo APP_NAME; ?></h3>
                        <p class="mb-0">Sistema de Validación de Simpatizantes</p>
                    </div>
                    <div class="login-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo htmlspecialchars($error); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="username" class="form-label">
                                    <i class="bi bi-person-fill"></i> Usuario
                                </label>
                                <input type="text" class="form-control" id="username" name="username" 
                                       placeholder="Ingrese su usuario" required autofocus>
                            </div>
                            
                            <div class="mb-4">
                                <label for="password" class="form-label">
                                    <i class="bi bi-lock-fill"></i> Contraseña
                                </label>
                                <input type="password" class="form-control" id="password" name="password" 
                                       placeholder="Ingrese su contraseña" required>
                            </div>
                            
                            <button type="submit" class="btn btn-login w-100">
                                <i class="bi bi-box-arrow-in-right me-2"></i>Iniciar Sesión
                            </button>
                        </form>
                        
                        <hr class="my-4">
                        
                        <div class="text-center">
                            <p class="mb-2">
                                <a href="<?php echo BASE_URL; ?>/public/recuperar-password.php" class="text-decoration-none">
                                    <i class="bi bi-key-fill me-1"></i>¿Olvidaste tu contraseña?
                                </a>
                            </p>
                            <p class="mb-0">
                                <a href="<?php echo BASE_URL; ?>/public/registro-publico.php" class="text-decoration-none fw-bold">
                                    <i class="bi bi-person-plus-fill me-1"></i>Registrarse como Simpatizante
                                </a>
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="text-center mt-4 text-white">
                    <small>
                        <i class="bi bi-shield-check"></i> Sistema Seguro con Encriptación
                    </small>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
