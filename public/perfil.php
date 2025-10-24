<?php
/**
 * Perfil de Usuario
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../app/controllers/AuthController.php';
require_once __DIR__ . '/../app/models/Usuario.php';

$auth = new AuthController();
$auth->requiereAutenticacion();

$usuarioModel = new Usuario();
$usuario = $usuarioModel->obtenerPorId($auth->obtenerUsuarioId());

$success = '';
$error = '';

// Actualizar perfil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'actualizar_perfil') {
    $datos = [
        'email' => $_POST['email'] ?? '',
        'nombre_completo' => $_POST['nombre_completo'] ?? '',
        'rol' => $usuario['rol'], // No cambiar rol desde perfil
        'whatsapp' => $_POST['whatsapp'] ?? '',
        'twitter' => $_POST['twitter'] ?? '',
        'instagram' => $_POST['instagram'] ?? '',
        'facebook' => $_POST['facebook'] ?? '',
        'youtube' => $_POST['youtube'] ?? '',
        'tiktok' => $_POST['tiktok'] ?? '',
        'activo' => $usuario['activo']
    ];
    
    if ($usuarioModel->actualizar($auth->obtenerUsuarioId(), $datos)) {
        $success = 'Perfil actualizado correctamente';
        $usuario = $usuarioModel->obtenerPorId($auth->obtenerUsuarioId());
        $_SESSION['nombre_completo'] = $usuario['nombre_completo'];
        $_SESSION['email'] = $usuario['email'];
    } else {
        $error = 'Error al actualizar el perfil';
    }
}

// Cambiar contraseña
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cambiar_password') {
    $passwordActual = $_POST['password_actual'] ?? '';
    $passwordNuevo = $_POST['password_nuevo'] ?? '';
    $passwordConfirmar = $_POST['password_confirmar'] ?? '';
    
    if (empty($passwordActual) || empty($passwordNuevo) || empty($passwordConfirmar)) {
        $error = 'Todos los campos de contraseña son obligatorios';
    } elseif ($passwordNuevo !== $passwordConfirmar) {
        $error = 'Las contraseñas nuevas no coinciden';
    } elseif (strlen($passwordNuevo) < PASSWORD_MIN_LENGTH) {
        $error = 'La contraseña debe tener al menos ' . PASSWORD_MIN_LENGTH . ' caracteres';
    } else {
        // Verificar contraseña actual
        if (password_verify($passwordActual, $usuario['password'])) {
            if ($usuarioModel->cambiarPassword($auth->obtenerUsuarioId(), $passwordNuevo)) {
                $success = 'Contraseña cambiada correctamente';
            } else {
                $error = 'Error al cambiar la contraseña';
            }
        } else {
            $error = 'La contraseña actual es incorrecta';
        }
    }
}

$pageTitle = 'Mi Perfil';
include __DIR__ . '/../app/views/layouts/header.php';
?>

<div class="container-fluid">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-md-6">
            <h2><i class="bi bi-person-fill me-2"></i>Mi Perfil</h2>
            <p class="text-muted">Administra tu información personal</p>
        </div>
    </div>
    
    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle-fill me-2"></i><?php echo htmlspecialchars($success); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <div class="row">
        <!-- Información del Perfil -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="bi bi-person-badge-fill me-2"></i>Información Personal</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="actualizar_perfil">
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Usuario</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($usuario['username']); ?>" disabled>
                                <small class="text-muted">El usuario no se puede cambiar</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Rol</label>
                                <input type="text" class="form-control" value="<?php echo ucfirst(str_replace('_', ' ', $usuario['rol'])); ?>" disabled>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Nombre Completo</label>
                            <input type="text" class="form-control" name="nombre_completo" 
                                   value="<?php echo htmlspecialchars($usuario['nombre_completo']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" 
                                   value="<?php echo htmlspecialchars($usuario['email']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">WhatsApp</label>
                            <input type="tel" class="form-control" name="whatsapp" 
                                   value="<?php echo htmlspecialchars($usuario['whatsapp'] ?? ''); ?>">
                        </div>
                        
                        <h6 class="mt-4 mb-3">Redes Sociales</h6>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label"><i class="bi bi-twitter text-info"></i> Twitter/X</label>
                                <input type="text" class="form-control" name="twitter" 
                                       value="<?php echo htmlspecialchars($usuario['twitter'] ?? ''); ?>">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label"><i class="bi bi-instagram text-danger"></i> Instagram</label>
                                <input type="text" class="form-control" name="instagram" 
                                       value="<?php echo htmlspecialchars($usuario['instagram'] ?? ''); ?>">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label"><i class="bi bi-facebook text-primary"></i> Facebook</label>
                                <input type="text" class="form-control" name="facebook" 
                                       value="<?php echo htmlspecialchars($usuario['facebook'] ?? ''); ?>">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label"><i class="bi bi-youtube text-danger"></i> YouTube</label>
                                <input type="text" class="form-control" name="youtube" 
                                       value="<?php echo htmlspecialchars($usuario['youtube'] ?? ''); ?>">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label"><i class="bi bi-tiktok"></i> TikTok</label>
                                <input type="text" class="form-control" name="tiktok" 
                                       value="<?php echo htmlspecialchars($usuario['tiktok'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <div class="text-end">
                            <button type="submit" class="btn btn-gradient">
                                <i class="bi bi-save-fill me-2"></i>Guardar Cambios
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Cambiar Contraseña -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="bi bi-lock-fill me-2"></i>Cambiar Contraseña</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="cambiar_password">
                        
                        <div class="mb-3">
                            <label class="form-label">Contraseña Actual</label>
                            <input type="password" class="form-control" name="password_actual" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Nueva Contraseña</label>
                            <input type="password" class="form-control" name="password_nuevo" required 
                                   minlength="<?php echo PASSWORD_MIN_LENGTH; ?>">
                            <small class="text-muted">Mínimo <?php echo PASSWORD_MIN_LENGTH; ?> caracteres</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Confirmar Nueva Contraseña</label>
                            <input type="password" class="form-control" name="password_confirmar" required>
                        </div>
                        
                        <div class="text-end">
                            <button type="submit" class="btn btn-warning">
                                <i class="bi bi-key-fill me-2"></i>Cambiar Contraseña
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Información Adicional -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="bi bi-info-circle-fill me-2"></i>Información de Cuenta</h5>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled">
                        <li class="mb-3">
                            <strong>Estado de Cuenta:</strong><br>
                            <?php if ($usuario['activo']): ?>
                                <span class="badge bg-success">Activa</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Inactiva</span>
                            <?php endif; ?>
                        </li>
                        
                        <li class="mb-3">
                            <strong>Fecha de Registro:</strong><br>
                            <?php echo date('d/m/Y H:i', strtotime($usuario['created_at'])); ?>
                        </li>
                        
                        <li class="mb-3">
                            <strong>Última Actualización:</strong><br>
                            <?php echo date('d/m/Y H:i', strtotime($usuario['updated_at'])); ?>
                        </li>
                        
                        <li class="mb-3">
                            <strong>Intentos Fallidos:</strong><br>
                            <?php echo $usuario['intentos_fallidos']; ?>
                        </li>
                    </ul>
                </div>
            </div>
            
            <div class="card border-0 shadow-sm bg-light">
                <div class="card-body">
                    <h6><i class="bi bi-shield-check me-2"></i>Seguridad</h6>
                    <p class="small text-muted mb-0">
                        Mantén tu contraseña segura y no la compartas con nadie. 
                        Cambia tu contraseña regularmente para mayor seguridad.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../app/views/layouts/footer.php'; ?>
