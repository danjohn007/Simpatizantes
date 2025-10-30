<?php
/**
 * Recuperar Contraseña
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../app/services/EmailService.php';
require_once __DIR__ . '/../app/services/EmailQueue.php';

session_start();
$db = Database::getInstance();
$emailService = new EmailService();

$error = '';
$success = '';
$step = 1; // 1: solicitar email, 2: mostrar mensaje de éxito

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        $error = 'El email es obligatorio';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'El formato del email no es válido';
    } else {
        // Verificar si el email existe
        $sql = "SELECT id, username, email, nombre_completo FROM usuarios WHERE email = ? AND activo = 1";
        $usuario = $db->queryOne($sql, [$email]);
        
        if ($usuario) {
            // Log para debugging
            error_log("Recuperación solicitada para usuario: " . $usuario['username'] . " (" . $usuario['email'] . ")");
            
            // Generar token de recuperación
            $token = bin2hex(random_bytes(32));
            $expiracion = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Guardar token en la base de datos
            $sql = "INSERT INTO recuperacion_password (usuario_id, token, expiracion) VALUES (?, ?, ?)";
            $db->execute($sql, [$usuario['id'], $token, $expiracion]);
            
            error_log("Token generado para recuperación: " . substr($token, 0, 10) . "... - Expira: " . $expiracion);
            
            // RESPUESTA INMEDIATA: Dar feedback al usuario sin esperar el email
            $success = 'Se están enviando las instrucciones de recuperación a su email. Esto puede tomar unos momentos.';
            $step = 2;
            
            // Procesar envío de email en background
            $emailEnviado = $emailService->sendPasswordRecoveryEmail(
                $usuario['email'],
                $usuario['nombre_completo'],
                $token
            );
            
            // Solo mostrar link de fallback si hay error y estamos en desarrollo
            if (!$emailEnviado && defined('APP_DEBUG') && APP_DEBUG) {
                $linkRecuperacion = BASE_URL . "/public/restablecer-password.php?token=" . $token;
                $success .= '<br><small class="text-muted">Link directo (desarrollo): <a href="' . $linkRecuperacion . '" target="_blank">Restablecer contraseña</a></small>';
            }
            
            if ($emailEnviado) {
                error_log("Email de recuperación enviado/encolado para: " . $usuario['email']);
            } else {
                error_log("Email de recuperación encolado en background para: " . $usuario['email']);
            }
        } else {
            // Mensaje específico para email no encontrado
            error_log("Intento de recuperación con email no registrado: " . $email);
            $error = 'El email ingresado no está registrado en el sistema o la cuenta está inactiva.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Contraseña - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .recover-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            overflow: hidden;
            max-width: 500px;
            width: 100%;
        }
        .recover-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .recover-body {
            padding: 40px;
        }
        .btn-recover {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            padding: 12px;
            font-weight: 500;
            transition: transform 0.2s;
        }
        .btn-recover:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-12">
                <div class="recover-card">
                    <div class="recover-header">
                        <i class="bi bi-key-fill" style="font-size: 3rem;"></i>
                        <h3 class="mt-3 mb-1">Recuperar Contraseña</h3>
                        <p class="mb-0">Sistema de Validación de Simpatizantes</p>
                    </div>
                    <div class="recover-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo htmlspecialchars($error); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success" role="alert">
                                <i class="bi bi-check-circle-fill me-2"></i><?php echo $success; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($step === 1): ?>
                            <form method="POST" action="">
                                <div class="mb-3">
                                    <label for="email" class="form-label">
                                        <i class="bi bi-envelope-fill"></i> Email
                                    </label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           placeholder="Ingrese su email" required autofocus>
                                    <small class="text-muted">
                                        Ingrese el email asociado a su cuenta. Le enviaremos las instrucciones para restablecer su contraseña.
                                    </small>
                                </div>
                                
                                <button type="submit" class="btn btn-recover w-100 mb-3">
                                    <i class="bi bi-send-fill me-2"></i>Enviar Instrucciones
                                </button>
                                
                                <div class="text-center">
                                    <a href="<?php echo BASE_URL; ?>" class="text-decoration-none">
                                        <i class="bi bi-arrow-left me-1"></i>Volver al Login
                                    </a>
                                </div>
                            </form>
                        <?php else: ?>
                            <div class="text-center">
                                <p class="mb-3">Revise su correo electrónico para continuar con el proceso de recuperación.</p>
                                <a href="<?php echo BASE_URL; ?>" class="btn btn-recover">
                                    <i class="bi bi-house-fill me-2"></i>Volver al Inicio
                                </a>
                            </div>
                        <?php endif; ?>
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
