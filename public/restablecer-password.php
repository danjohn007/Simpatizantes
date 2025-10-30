<?php
/**
 * Restablecer Contraseña
 * Sistema de Validación de Simpatizantes
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/Database.php';

session_start();
$db = Database::getInstance();

$error = '';
$success = '';
$token = $_GET['token'] ?? '';
$step = 1; // 1: verificar token, 2: mostrar formulario, 3: contraseña actualizada

// Verificar token
if (empty($token)) {
    $error = 'Token de recuperación no válido o expirado.';
    error_log("Token vacío en restablecer-password.php");
} else {
    // Log para debugging
    error_log("Verificando token: " . substr($token, 0, 10) . "... (longitud: " . strlen($token) . ")");
    
    // Verificar token en la base de datos con más detalles
    $sql = "SELECT r.id, r.usuario_id, r.expiracion, r.usado, r.created_at, u.username, u.email, u.nombre_completo 
            FROM recuperacion_password r 
            INNER JOIN usuarios u ON r.usuario_id = u.id 
            WHERE r.token = ?";
    
    $recuperacion = $db->queryOne($sql, [$token]);
    
    if (!$recuperacion) {
        $error = 'Token de recuperación no válido.';
        error_log("Token no encontrado en BD: " . $token);
    } elseif ($recuperacion['usado'] == 1) {
        $error = 'Este enlace de recuperación ya fue utilizado.';
        error_log("Token ya usado: " . $token);
    } elseif (strtotime($recuperacion['expiracion']) <= time()) {
        $error = 'Este enlace de recuperación ha expirado. Solicite uno nuevo.';
        error_log("Token expirado: " . $token . " - Expiró: " . $recuperacion['expiracion']);
    } else {
        $step = 2; // Mostrar formulario de nueva contraseña
        error_log("Token válido para usuario: " . $recuperacion['username']);
    }
}

// Procesar formulario de nueva contraseña
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 2) {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($password)) {
        $error = 'La contraseña es obligatoria';
    } elseif (strlen($password) < PASSWORD_MIN_LENGTH) {
        $error = 'La contraseña debe tener al menos ' . PASSWORD_MIN_LENGTH . ' caracteres';
    } elseif ($password !== $confirm_password) {
        $error = 'Las contraseñas no coinciden';
    } else {
        try {
            $db->beginTransaction();
            
            // Actualizar contraseña del usuario
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $sqlUpdate = "UPDATE usuarios SET password = ?, updated_at = NOW() WHERE id = ?";
            $db->execute($sqlUpdate, [$hashedPassword, $recuperacion['usuario_id']]);
            
            // Marcar token como usado
            $sqlToken = "UPDATE recuperacion_password SET usado = 1 WHERE id = ?";
            $db->execute($sqlToken, [$recuperacion['id']]);
            
            // Invalidar todos los demás tokens del usuario
            $sqlInvalidate = "UPDATE recuperacion_password SET usado = 1 WHERE usuario_id = ? AND id != ?";
            $db->execute($sqlInvalidate, [$recuperacion['usuario_id'], $recuperacion['id']]);
            
            $db->commit();
            
            $success = 'Su contraseña ha sido restablecida exitosamente.';
            $step = 3;
            
        } catch (Exception $e) {
            $db->rollback();
            $error = 'Error al actualizar la contraseña. Inténtelo nuevamente.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restablecer Contraseña - <?php echo APP_NAME; ?></title>
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
        .reset-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            overflow: hidden;
            max-width: 500px;
            width: 100%;
        }
        .reset-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .reset-body {
            padding: 40px;
        }
        .btn-reset {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            padding: 12px;
            font-weight: 500;
            transition: transform 0.2s;
        }
        .btn-reset:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
            color: white;
        }
        .password-strength {
            height: 5px;
            margin-top: 5px;
            border-radius: 3px;
            transition: all 0.3s;
        }
        .strength-weak { background-color: #dc3545; }
        .strength-medium { background-color: #ffc107; }
        .strength-strong { background-color: #28a745; }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-12">
                <div class="reset-card">
                    <div class="reset-header">
                        <?php if ($step === 3): ?>
                            <i class="bi bi-check-circle-fill" style="font-size: 3rem;"></i>
                            <h3 class="mt-3 mb-1">¡Contraseña Restablecida!</h3>
                        <?php elseif ($error): ?>
                            <i class="bi bi-x-circle-fill" style="font-size: 3rem;"></i>
                            <h3 class="mt-3 mb-1">Error</h3>
                        <?php else: ?>
                            <i class="bi bi-shield-lock-fill" style="font-size: 3rem;"></i>
                            <h3 class="mt-3 mb-1">Nueva Contraseña</h3>
                        <?php endif; ?>
                        <p class="mb-0">Sistema de Validación de Simpatizantes</p>
                    </div>
                    <div class="reset-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo htmlspecialchars($error); ?>
                            </div>
                            <div class="text-center">
                                <a href="<?php echo BASE_URL; ?>/public/recuperar-password.php" class="btn btn-reset">
                                    <i class="bi bi-arrow-left me-2"></i>Solicitar Nuevo Token
                                </a>
                            </div>
                        <?php elseif ($success): ?>
                            <div class="alert alert-success" role="alert">
                                <i class="bi bi-check-circle-fill me-2"></i><?php echo htmlspecialchars($success); ?>
                            </div>
                            <div class="text-center">
                                <p class="mb-3">Ya puede iniciar sesión con su nueva contraseña.</p>
                                <a href="<?php echo BASE_URL; ?>" class="btn btn-reset">
                                    <i class="bi bi-box-arrow-in-right me-2"></i>Iniciar Sesión
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="mb-4">
                                <div class="alert alert-info" role="alert">
                                    <i class="bi bi-info-circle-fill me-2"></i>
                                    <strong>Usuario:</strong> <?php echo htmlspecialchars($recuperacion['nombre_completo']); ?><br>
                                    <strong>Email:</strong> <?php echo htmlspecialchars($recuperacion['email']); ?>
                                </div>
                            </div>
                            
                            <form method="POST" action="" id="resetForm">
                                <div class="mb-3">
                                    <label for="password" class="form-label">
                                        <i class="bi bi-lock-fill"></i> Nueva Contraseña
                                    </label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="password" name="password" 
                                               placeholder="Ingrese su nueva contraseña" required 
                                               minlength="<?php echo PASSWORD_MIN_LENGTH; ?>"
                                               onkeyup="checkPasswordStrength()">
                                        <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('password')">
                                            <i class="bi bi-eye" id="password-eye"></i>
                                        </button>
                                    </div>
                                    <div class="password-strength" id="passwordStrength"></div>
                                    <small class="text-muted">
                                        Mínimo <?php echo PASSWORD_MIN_LENGTH; ?> caracteres. Use mayúsculas, minúsculas, números y símbolos.
                                    </small>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">
                                        <i class="bi bi-lock-fill"></i> Confirmar Nueva Contraseña
                                    </label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                               placeholder="Confirme su nueva contraseña" required
                                               onkeyup="checkPasswordMatch()">
                                        <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('confirm_password')">
                                            <i class="bi bi-eye" id="confirm_password-eye"></i>
                                        </button>
                                    </div>
                                    <div id="passwordMatch"></div>
                                </div>
                                
                                <button type="submit" class="btn btn-reset w-100 mb-3" id="submitBtn" disabled>
                                    <i class="bi bi-check-circle-fill me-2"></i>Restablecer Contraseña
                                </button>
                                
                                <div class="text-center">
                                    <a href="<?php echo BASE_URL; ?>" class="text-decoration-none">
                                        <i class="bi bi-arrow-left me-1"></i>Volver al Login
                                    </a>
                                </div>
                            </form>
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
    <script>
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const eye = document.getElementById(fieldId + '-eye');
            
            if (field.type === 'password') {
                field.type = 'text';
                eye.className = 'bi bi-eye-slash';
            } else {
                field.type = 'password';
                eye.className = 'bi bi-eye';
            }
        }
        
        function checkPasswordStrength() {
            const password = document.getElementById('password').value;
            const strengthBar = document.getElementById('passwordStrength');
            
            let strength = 0;
            if (password.length >= <?php echo PASSWORD_MIN_LENGTH; ?>) strength++;
            if (password.match(/[a-z]/)) strength++;
            if (password.match(/[A-Z]/)) strength++;
            if (password.match(/[0-9]/)) strength++;
            if (password.match(/[^a-zA-Z0-9]/)) strength++;
            
            strengthBar.style.width = (strength * 20) + '%';
            
            if (strength < 3) {
                strengthBar.className = 'password-strength strength-weak';
            } else if (strength < 4) {
                strengthBar.className = 'password-strength strength-medium';
            } else {
                strengthBar.className = 'password-strength strength-strong';
            }
            
            checkPasswordMatch();
        }
        
        function checkPasswordMatch() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const matchDiv = document.getElementById('passwordMatch');
            const submitBtn = document.getElementById('submitBtn');
            
            if (confirmPassword.length > 0) {
                if (password === confirmPassword) {
                    matchDiv.innerHTML = '<small class="text-success"><i class="bi bi-check-circle-fill"></i> Las contraseñas coinciden</small>';
                    if (password.length >= <?php echo PASSWORD_MIN_LENGTH; ?>) {
                        submitBtn.disabled = false;
                    }
                } else {
                    matchDiv.innerHTML = '<small class="text-danger"><i class="bi bi-x-circle-fill"></i> Las contraseñas no coinciden</small>';
                    submitBtn.disabled = true;
                }
            } else {
                matchDiv.innerHTML = '';
                submitBtn.disabled = true;
            }
        }
    </script>
</body>
</html>