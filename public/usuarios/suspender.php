<?php
/**
 * Suspender/Activar Usuario
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../app/controllers/AuthController.php';
require_once __DIR__ . '/../../app/controllers/UsuarioController.php';

$auth = new AuthController();
$auth->requiereRol(['super_admin', 'admin']);

// Validar ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: ' . BASE_URL . '/public/usuarios/index.php');
    exit;
}

$id = (int)$_GET['id'];

// No permitir suspender al propio usuario
if ($id === $auth->obtenerUsuarioId()) {
    $_SESSION['error'] = 'No puede suspender su propio usuario';
    header('Location: ' . BASE_URL . '/public/usuarios/index.php');
    exit;
}

$controller = new UsuarioController();

// Obtener usuario
$usuario = $controller->obtener($id);

if (!$usuario) {
    $_SESSION['error'] = 'Usuario no encontrado';
    header('Location: ' . BASE_URL . '/public/usuarios/index.php');
    exit;
}

// Procesar cambio de estado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nuevoEstado = $usuario['activo'] ? 0 : 1;
    $result = $controller->cambiarEstado($id, $nuevoEstado);
    
    if (isset($result['success'])) {
        $mensaje = $nuevoEstado ? 'activado' : 'suspendido';
        $_SESSION['success'] = "Usuario $mensaje correctamente";
        header('Location: ' . BASE_URL . '/public/usuarios/index.php');
        exit;
    } else {
        $_SESSION['error'] = $result['error'] ?? 'Error al cambiar el estado del usuario';
        header('Location: ' . BASE_URL . '/public/usuarios/index.php');
        exit;
    }
}

$pageTitle = $usuario['activo'] ? 'Suspender Usuario' : 'Activar Usuario';
include __DIR__ . '/../../app/views/layouts/header.php';
?>

<div class="container-fluid">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-md-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/public/usuarios/index.php">Usuarios</a></li>
                    <li class="breadcrumb-item active"><?php echo $usuario['activo'] ? 'Suspender' : 'Activar'; ?></li>
                </ol>
            </nav>
            <h2>
                <i class="bi bi-<?php echo $usuario['activo'] ? 'pause-circle' : 'play-circle'; ?>-fill me-2"></i>
                <?php echo $usuario['activo'] ? 'Suspender' : 'Activar'; ?> Usuario
            </h2>
            <p class="text-muted">Confirmación de cambio de estado</p>
        </div>
    </div>
    
    <!-- Confirmación -->
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="card border-<?php echo $usuario['activo'] ? 'warning' : 'success'; ?> shadow-sm">
                <div class="card-header bg-<?php echo $usuario['activo'] ? 'warning' : 'success'; ?> text-white">
                    <h5 class="mb-0"><i class="bi bi-info-circle-fill me-2"></i>Confirmación</h5>
                </div>
                <div class="card-body">
                    <p class="lead">
                        ¿Está seguro de que desea <?php echo $usuario['activo'] ? 'suspender' : 'activar'; ?> este usuario?
                    </p>
                    
                    <div class="alert alert-<?php echo $usuario['activo'] ? 'warning' : 'info'; ?>">
                        <i class="bi bi-info-circle me-2"></i>
                        <?php if ($usuario['activo']): ?>
                            Al suspender el usuario:
                            <ul class="mb-0 mt-2">
                                <li>No podrá iniciar sesión en el sistema</li>
                                <li>Sus registros existentes se mantendrán</li>
                                <li>Puede reactivarse posteriormente</li>
                            </ul>
                        <?php else: ?>
                            Al activar el usuario:
                            <ul class="mb-0 mt-2">
                                <li>Podrá iniciar sesión nuevamente</li>
                                <li>Recuperará todos sus permisos</li>
                                <li>Tendrá acceso a sus registros</li>
                            </ul>
                        <?php endif; ?>
                    </div>
                    
                    <div class="bg-light p-3 rounded mb-4">
                        <h6 class="mb-3">Información del Usuario:</h6>
                        <p class="mb-1"><strong>Usuario:</strong> <?php echo htmlspecialchars($usuario['username']); ?></p>
                        <p class="mb-1"><strong>Nombre:</strong> <?php echo htmlspecialchars($usuario['nombre_completo']); ?></p>
                        <p class="mb-1"><strong>Email:</strong> <?php echo htmlspecialchars($usuario['email']); ?></p>
                        <p class="mb-1"><strong>Rol:</strong> <?php echo ucfirst(str_replace('_', ' ', $usuario['rol'])); ?></p>
                        <p class="mb-0">
                            <strong>Estado Actual:</strong> 
                            <span class="badge bg-<?php echo $usuario['activo'] ? 'success' : 'secondary'; ?>">
                                <?php echo $usuario['activo'] ? 'Activo' : 'Suspendido'; ?>
                            </span>
                        </p>
                    </div>
                    
                    <form method="POST" action="">
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-<?php echo $usuario['activo'] ? 'warning' : 'success'; ?> btn-lg">
                                <i class="bi bi-<?php echo $usuario['activo'] ? 'pause' : 'play'; ?>-circle-fill me-2"></i>
                                Sí, <?php echo $usuario['activo'] ? 'Suspender' : 'Activar'; ?> Usuario
                            </button>
                            <a href="<?php echo BASE_URL; ?>/public/usuarios/index.php" class="btn btn-secondary btn-lg">
                                <i class="bi bi-x-circle me-2"></i>No, Cancelar
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../app/views/layouts/footer.php'; ?>
