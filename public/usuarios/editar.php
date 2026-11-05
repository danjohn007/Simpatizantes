<?php
/**
 * Editar Usuario
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../app/controllers/AuthController.php';
require_once __DIR__ . '/../../app/controllers/UsuarioController.php';
require_once __DIR__ . '/../../app/models/Campana.php';

$auth = new AuthController();
$auth->requiereRol(['super_admin', 'admin']);

// Validar ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: ' . BASE_URL . '/public/usuarios/index.php');
    exit;
}

$id = (int)$_GET['id'];
$controller = new UsuarioController();
$campanaModel = new Campana();

// Obtener usuario
$usuario = $controller->obtener($id);

if (!$usuario) {
    $_SESSION['error'] = 'Usuario no encontrado';
    header('Location: ' . BASE_URL . '/public/usuarios/index.php');
    exit;
}

$error = '';
$success = '';
$errores = [];

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $datos = [
        'username' => trim($_POST['username'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'nombre_completo' => trim($_POST['nombre_completo'] ?? ''),
        'rol' => $_POST['rol'] ?? 'capturista',
        'campana_id' => !empty($_POST['campana_id']) ? (int)$_POST['campana_id'] : null,
        'whatsapp' => trim($_POST['whatsapp'] ?? ''),
        'twitter' => trim($_POST['twitter'] ?? ''),
        'instagram' => trim($_POST['instagram'] ?? ''),
        'facebook' => trim($_POST['facebook'] ?? ''),
        'youtube' => trim($_POST['youtube'] ?? ''),
        'tiktok' => trim($_POST['tiktok'] ?? ''),
        'activo' => isset($_POST['activo']) ? 1 : 0
    ];
    
    // Solo actualizar contraseña si se proporciona
    if (!empty($_POST['password'])) {
        if ($_POST['password'] !== $_POST['password_confirm']) {
            $errores['password'] = 'Las contraseñas no coinciden';
        } elseif (strlen($_POST['password']) < PASSWORD_MIN_LENGTH) {
            $errores['password'] = 'La contraseña debe tener al menos ' . PASSWORD_MIN_LENGTH . ' caracteres';
        } else {
            $datos['password'] = $_POST['password'];
        }
    }
    
    if (empty($errores)) {
        $result = $controller->actualizar($id, $datos);
        
        if (isset($result['success'])) {
            $_SESSION['success'] = 'Usuario actualizado correctamente';
            header('Location: ' . BASE_URL . '/public/usuarios/index.php');
            exit;
        } else {
            $error = $result['error'] ?? 'Error al actualizar usuario';
            $errores = $result['errores'] ?? [];
        }
    }
}

$campanas = $campanaModel->obtenerTodas(1);

$pageTitle = 'Editar Usuario';
include __DIR__ . '/../../app/views/layouts/header.php';
?>

<div class="container-fluid p-0">
    <div class="page-head">
        <div>
            <div class="eyebrow">Usuarios</div>
            <h1>Editar Usuario</h1>
            <div class="meta"><span>Actualizar información del usuario</span></div>
        </div>
        <a href="<?php echo BASE_URL; ?>/public/usuarios/index.php" class="btn btn-soft"><i class="bi bi-arrow-left me-2"></i>Cancelar</a>
    </div>
    
    <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>
    
    <!-- Formulario -->
    <form method="POST" action="">
<?php echo csrf_field(); ?>
        <div class="row">
            <div class="col-lg-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-person-fill me-2"></i>Información del Usuario</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Nombre de Usuario <span class="text-danger">*</span></label>
                            <input type="text" class="form-control <?php echo isset($errores['username']) ? 'is-invalid' : ''; ?>" 
                                   name="username" required minlength="4"
                                   value="<?php echo htmlspecialchars($usuario['username'] ?? ''); ?>">
                            <?php if (isset($errores['username'])): ?>
                                <div class="invalid-feedback"><?php echo $errores['username']; ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Nombre Completo <span class="text-danger">*</span></label>
                            <input type="text" class="form-control <?php echo isset($errores['nombre_completo']) ? 'is-invalid' : ''; ?>" 
                                   name="nombre_completo" required
                                   value="<?php echo htmlspecialchars($usuario['nombre_completo'] ?? ''); ?>">
                            <?php if (isset($errores['nombre_completo'])): ?>
                                <div class="invalid-feedback"><?php echo $errores['nombre_completo']; ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control <?php echo isset($errores['email']) ? 'is-invalid' : ''; ?>" 
                                   name="email" required
                                   value="<?php echo htmlspecialchars($usuario['email'] ?? ''); ?>">
                            <?php if (isset($errores['email'])): ?>
                                <div class="invalid-feedback"><?php echo $errores['email']; ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Rol <span class="text-danger">*</span></label>
                            <select class="form-select" name="rol" required>
                                <?php if ($auth->obtenerRol() === 'super_admin'): ?>
                                <option value="super_admin" <?php echo ($usuario['rol'] === 'super_admin') ? 'selected' : ''; ?>>Super Admin</option>
                                <?php endif; ?>
                                <option value="admin" <?php echo ($usuario['rol'] === 'admin') ? 'selected' : ''; ?>>Administrador</option>
                                <option value="candidato" <?php echo ($usuario['rol'] === 'candidato') ? 'selected' : ''; ?>>Candidato</option>
                                <option value="coordinador" <?php echo ($usuario['rol'] === 'coordinador') ? 'selected' : ''; ?>>Coordinador</option>
                                <option value="capturista" <?php echo ($usuario['rol'] === 'capturista') ? 'selected' : ''; ?>>Capturista</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Campaña</label>
                            <select class="form-select" name="campana_id">
                                <option value="">Seleccionar campaña</option>
                                <?php foreach ($campanas as $campana): ?>
                                    <option value="<?php echo $campana['id']; ?>" 
                                            <?php echo (isset($usuario['campana_id']) && $usuario['campana_id'] == $campana['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($campana['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Requerido para roles: Candidato, Coordinador y Capturista</small>
                        </div>
                        
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="activo" id="activo" 
                                   <?php echo (!empty($usuario['activo'])) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="activo">
                                Usuario Activo
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-key-fill me-2"></i>Cambiar Contraseña</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>Dejar en blanco para mantener la contraseña actual
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Nueva Contraseña</label>
                            <input type="password" class="form-control <?php echo isset($errores['password']) ? 'is-invalid' : ''; ?>" 
                                   name="password" minlength="<?php echo PASSWORD_MIN_LENGTH; ?>">
                            <?php if (isset($errores['password'])): ?>
                                <div class="invalid-feedback"><?php echo $errores['password']; ?></div>
                            <?php endif; ?>
                            <small class="text-muted">Mínimo <?php echo PASSWORD_MIN_LENGTH; ?> caracteres</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Confirmar Contraseña</label>
                            <input type="password" class="form-control" name="password_confirm">
                        </div>
                    </div>
                </div>
                
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-share-fill me-2"></i>Contacto y Redes Sociales</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label"><i class="bi bi-whatsapp text-muted"></i> WhatsApp</label>
                            <input type="tel" class="form-control <?php echo isset($errores['whatsapp']) ? 'is-invalid' : ''; ?>" 
                                   name="whatsapp" pattern="[0-9]{10}" maxlength="10"
                                   value="<?php echo htmlspecialchars($usuario['whatsapp'] ?? ''); ?>">
                            <?php if (isset($errores['whatsapp'])): ?>
                                <div class="invalid-feedback"><?php echo $errores['whatsapp']; ?></div>
                            <?php endif; ?>
                            <small class="text-muted">10 dígitos</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label"><i class="bi bi-twitter text-muted"></i> Twitter/X</label>
                            <input type="text" class="form-control" name="twitter"
                                   value="<?php echo htmlspecialchars($usuario['twitter'] ?? ''); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label"><i class="bi bi-instagram text-muted"></i> Instagram</label>
                            <input type="text" class="form-control" name="instagram"
                                   value="<?php echo htmlspecialchars($usuario['instagram'] ?? ''); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label"><i class="bi bi-facebook text-muted"></i> Facebook</label>
                            <input type="text" class="form-control" name="facebook"
                                   value="<?php echo htmlspecialchars($usuario['facebook'] ?? ''); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label"><i class="bi bi-youtube text-muted"></i> YouTube</label>
                            <input type="text" class="form-control" name="youtube"
                                   value="<?php echo htmlspecialchars($usuario['youtube'] ?? ''); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label"><i class="bi bi-tiktok text-muted"></i> TikTok</label>
                            <input type="text" class="form-control" name="tiktok"
                                   value="<?php echo htmlspecialchars($usuario['tiktok'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Botones -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body text-center">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-save me-2"></i>Guardar Cambios
                        </button>
                        <a href="<?php echo BASE_URL; ?>/public/usuarios/index.php" class="btn btn-soft btn-lg">
                            <i class="bi bi-x-circle me-2"></i>Cancelar
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<?php include __DIR__ . '/../../app/views/layouts/footer.php'; ?>
