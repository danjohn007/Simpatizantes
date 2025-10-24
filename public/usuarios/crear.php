<?php
/**
 * Crear Usuario
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../app/controllers/AuthController.php';
require_once __DIR__ . '/../../app/models/Usuario.php';

$auth = new AuthController();
$auth->requiereRol(['super_admin', 'admin']);

$usuarioModel = new Usuario();

$error = '';
$success = '';
$errores = [];

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $datos = [
        'username' => trim($_POST['username'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'password' => $_POST['password'] ?? '',
        'nombre_completo' => trim($_POST['nombre_completo'] ?? ''),
        'rol' => $_POST['rol'] ?? 'capturista',
        'whatsapp' => trim($_POST['whatsapp'] ?? ''),
        'twitter' => trim($_POST['twitter'] ?? ''),
        'instagram' => trim($_POST['instagram'] ?? ''),
        'facebook' => trim($_POST['facebook'] ?? ''),
        'youtube' => trim($_POST['youtube'] ?? ''),
        'tiktok' => trim($_POST['tiktok'] ?? ''),
        'activo' => isset($_POST['activo']) ? 1 : 0
    ];
    
    // Validaciones
    if (empty($datos['username'])) {
        $errores[] = 'El nombre de usuario es obligatorio';
    } elseif (strlen($datos['username']) < 4) {
        $errores[] = 'El nombre de usuario debe tener al menos 4 caracteres';
    }
    
    if (empty($datos['email'])) {
        $errores[] = 'El email es obligatorio';
    } elseif (!filter_var($datos['email'], FILTER_VALIDATE_EMAIL)) {
        $errores[] = 'El email no es válido';
    }
    
    if (empty($datos['password'])) {
        $errores[] = 'La contraseña es obligatoria';
    } elseif (strlen($datos['password']) < PASSWORD_MIN_LENGTH) {
        $errores[] = 'La contraseña debe tener al menos ' . PASSWORD_MIN_LENGTH . ' caracteres';
    }
    
    if (!empty($_POST['password_confirm']) && $_POST['password'] !== $_POST['password_confirm']) {
        $errores[] = 'Las contraseñas no coinciden';
    }
    
    if (empty($datos['nombre_completo'])) {
        $errores[] = 'El nombre completo es obligatorio';
    }
    
    if (empty($errores)) {
        $result = $usuarioModel->crear($datos);
        
        if (isset($result['success'])) {
            header('Location: index.php?success=1');
            exit;
        } else {
            $error = $result['error'] ?? 'Error al crear el usuario';
        }
    }
}

$pageTitle = 'Nuevo Usuario';
include __DIR__ . '/../../app/views/layouts/header.php';
?>

<div class="container-fluid">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-md-6">
            <h2><i class="bi bi-person-plus-fill me-2"></i>Nuevo Usuario</h2>
            <p class="text-muted">Crear nuevo usuario del sistema</p>
        </div>
        <div class="col-md-6 text-end">
            <a href="index.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left me-2"></i>Volver
            </a>
        </div>
    </div>
    
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($errores)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <strong>Por favor corrija los siguientes errores:</strong>
            <ul class="mb-0 mt-2">
                <?php foreach ($errores as $err): ?>
                    <li><?php echo htmlspecialchars($err); ?></li>
                <?php endforeach; ?>
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="bi bi-person-fill-add me-2"></i>Información del Usuario
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="row">
                            <!-- Username -->
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nombre de Usuario <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="username" 
                                       value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" 
                                       required maxlength="50" pattern="[a-zA-Z0-9_]+"
                                       title="Solo letras, números y guión bajo">
                                <small class="text-muted">Solo letras, números y guión bajo</small>
                            </div>
                            
                            <!-- Email -->
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" name="email" 
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                                       required maxlength="100">
                            </div>
                            
                            <!-- Nombre Completo -->
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Nombre Completo <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="nombre_completo" 
                                       value="<?php echo htmlspecialchars($_POST['nombre_completo'] ?? ''); ?>" 
                                       required maxlength="150">
                            </div>
                            
                            <!-- Contraseña -->
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Contraseña <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" name="password" 
                                       required minlength="<?php echo PASSWORD_MIN_LENGTH; ?>">
                                <small class="text-muted">Mínimo <?php echo PASSWORD_MIN_LENGTH; ?> caracteres</small>
                            </div>
                            
                            <!-- Confirmar Contraseña -->
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Confirmar Contraseña <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" name="password_confirm" 
                                       required minlength="<?php echo PASSWORD_MIN_LENGTH; ?>">
                            </div>
                            
                            <!-- Rol -->
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Rol <span class="text-danger">*</span></label>
                                <select class="form-select" name="rol" required>
                                    <?php if ($auth->obtenerRol() === 'super_admin'): ?>
                                        <option value="super_admin" <?php echo (isset($_POST['rol']) && $_POST['rol'] === 'super_admin') ? 'selected' : ''; ?>>
                                            Super Administrador
                                        </option>
                                    <?php endif; ?>
                                    <option value="admin" <?php echo (isset($_POST['rol']) && $_POST['rol'] === 'admin') ? 'selected' : ''; ?>>
                                        Administrador
                                    </option>
                                    <option value="candidato" <?php echo (isset($_POST['rol']) && $_POST['rol'] === 'candidato') ? 'selected' : ''; ?>>
                                        Candidato
                                    </option>
                                    <option value="coordinador" <?php echo (isset($_POST['rol']) && $_POST['rol'] === 'coordinador') ? 'selected' : ''; ?>>
                                        Coordinador
                                    </option>
                                    <option value="capturista" <?php echo (!isset($_POST['rol']) || $_POST['rol'] === 'capturista') ? 'selected' : ''; ?>>
                                        Capturista
                                    </option>
                                </select>
                            </div>
                        </div>
                        
                        <hr class="my-4">
                        
                        <h6 class="mb-3"><i class="bi bi-share me-2"></i>Redes Sociales (Opcional)</h6>
                        <div class="row">
                            <!-- WhatsApp -->
                            <div class="col-md-6 mb-3">
                                <label class="form-label">WhatsApp</label>
                                <input type="text" class="form-control" name="whatsapp" 
                                       value="<?php echo htmlspecialchars($_POST['whatsapp'] ?? ''); ?>" 
                                       maxlength="20" placeholder="5551234567">
                            </div>
                            
                            <!-- Twitter -->
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Twitter/X</label>
                                <input type="text" class="form-control" name="twitter" 
                                       value="<?php echo htmlspecialchars($_POST['twitter'] ?? ''); ?>" 
                                       maxlength="100" placeholder="@usuario">
                            </div>
                            
                            <!-- Instagram -->
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Instagram</label>
                                <input type="text" class="form-control" name="instagram" 
                                       value="<?php echo htmlspecialchars($_POST['instagram'] ?? ''); ?>" 
                                       maxlength="100" placeholder="@usuario">
                            </div>
                            
                            <!-- Facebook -->
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Facebook</label>
                                <input type="text" class="form-control" name="facebook" 
                                       value="<?php echo htmlspecialchars($_POST['facebook'] ?? ''); ?>" 
                                       maxlength="100" placeholder="usuario">
                            </div>
                            
                            <!-- YouTube -->
                            <div class="col-md-6 mb-3">
                                <label class="form-label">YouTube</label>
                                <input type="text" class="form-control" name="youtube" 
                                       value="<?php echo htmlspecialchars($_POST['youtube'] ?? ''); ?>" 
                                       maxlength="100" placeholder="@usuario">
                            </div>
                            
                            <!-- TikTok -->
                            <div class="col-md-6 mb-3">
                                <label class="form-label">TikTok</label>
                                <input type="text" class="form-control" name="tiktok" 
                                       value="<?php echo htmlspecialchars($_POST['tiktok'] ?? ''); ?>" 
                                       maxlength="100" placeholder="@usuario">
                            </div>
                        </div>
                        
                        <hr class="my-4">
                        
                        <!-- Activo -->
                        <div class="col-md-12 mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="activo" 
                                       id="activo" value="1" 
                                       <?php echo (isset($_POST['activo']) || !isset($_POST['username'])) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="activo">
                                    Usuario activo
                                </label>
                            </div>
                        </div>
                        
                        <div class="text-end mt-4">
                            <a href="index.php" class="btn btn-secondary me-2">
                                <i class="bi bi-x-circle me-2"></i>Cancelar
                            </a>
                            <button type="submit" class="btn btn-gradient">
                                <i class="bi bi-save-fill me-2"></i>Crear Usuario
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm bg-info bg-opacity-10">
                <div class="card-body">
                    <h6><i class="bi bi-info-circle-fill text-info me-2"></i>Información</h6>
                    <p class="small mb-2">
                        <strong>Campos obligatorios:</strong> Los campos marcados con 
                        <span class="text-danger">*</span> son obligatorios.
                    </p>
                    <p class="small mb-2">
                        <strong>Contraseña:</strong> Debe tener al menos <?php echo PASSWORD_MIN_LENGTH; ?> caracteres.
                    </p>
                    <p class="small mb-0">
                        <strong>Roles:</strong><br>
                        - <strong>Super Admin:</strong> Control total del sistema<br>
                        - <strong>Admin:</strong> Gestión completa excepto configuración<br>
                        - <strong>Candidato:</strong> Vista de su campaña<br>
                        - <strong>Coordinador:</strong> Gestión de capturistas<br>
                        - <strong>Capturista:</strong> Registro de simpatizantes
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../app/views/layouts/footer.php'; ?>
