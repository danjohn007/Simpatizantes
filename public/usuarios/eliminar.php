<?php
/**
 * Eliminar Usuario
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../app/controllers/AuthController.php';
require_once __DIR__ . '/../../app/controllers/UsuarioController.php';

$auth = new AuthController();
$auth->requiereRol(['super_admin']);

// Validar ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: ' . BASE_URL . '/public/usuarios/index.php');
    exit;
}

$id = (int)$_GET['id'];

// No permitir eliminar al propio usuario
if ($id === $auth->obtenerUsuarioId()) {
    $_SESSION['error'] = 'No puede eliminar su propio usuario';
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

$error = '';

// Procesar eliminación
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['confirmar']) && $_POST['confirmar'] === 'si') {
        $result = $controller->eliminar($id);
        
        if (isset($result['success'])) {
            $_SESSION['success'] = 'Usuario eliminado correctamente';
            header('Location: ' . BASE_URL . '/public/usuarios/index.php');
            exit;
        } else {
            $error = $result['error'] ?? 'Error al eliminar usuario';
        }
    } else {
        header('Location: ' . BASE_URL . '/public/usuarios/index.php');
        exit;
    }
}

$pageTitle = 'Eliminar Usuario';
include __DIR__ . '/../../app/views/layouts/header.php';
?>

<div class="container-fluid p-0">
    <div class="page-head">
        <div>
            <div class="eyebrow">Usuarios</div>
            <h1>Eliminar Usuario</h1>
            <div class="meta"><span>Confirmación de eliminación</span></div>
        </div>
        <a href="index.php" class="btn btn-soft"><i class="bi bi-arrow-left me-2"></i>Volver</a>
    </div>
    
    <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>
    
    <!-- Confirmación -->
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-exclamation-triangle-fill me-2"></i>Advertencia</h5>
                </div>
                <div class="card-body">
                    <p class="lead">¿Está seguro de que desea eliminar este usuario?</p>
                    
                    <div class="alert alert-warning">
                        <i class="bi bi-info-circle me-2"></i>
                        Esta acción no se puede deshacer. Se eliminará permanentemente:
                        <ul class="mb-0 mt-2">
                            <li>El usuario y sus datos</li>
                            <li>Sus relaciones con otros registros</li>
                            <li>Su historial de actividades</li>
                        </ul>
                    </div>
                    
                    <div class="bg-light p-3 rounded mb-4">
                        <h6 class="mb-3">Información del Usuario:</h6>
                        <p class="mb-1"><strong>Usuario:</strong> <?php echo htmlspecialchars($usuario['username']); ?></p>
                        <p class="mb-1"><strong>Nombre:</strong> <?php echo htmlspecialchars($usuario['nombre_completo']); ?></p>
                        <p class="mb-1"><strong>Email:</strong> <?php echo htmlspecialchars($usuario['email']); ?></p>
                        <p class="mb-0"><strong>Rol:</strong> <?php echo ucfirst(str_replace('_', ' ', $usuario['rol'])); ?></p>
                    </div>
                    
                    <form method="POST" action="">
<?php echo csrf_field(); ?>
                        <input type="hidden" name="confirmar" value="si">
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-danger btn-lg">
                                <i class="bi bi-trash-fill me-2"></i>Sí, Eliminar Usuario
                            </button>
                            <a href="<?php echo BASE_URL; ?>/public/usuarios/index.php" class="btn btn-soft btn-lg">
                                <i class="bi bi-x-circle me-2"></i>No, Cancelar
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function confirmarEliminar() {
    return confirm('¿Está seguro de que desea eliminar este usuario? Esta acción no se puede deshacer.');
}
</script>

<?php include __DIR__ . '/../../app/views/layouts/footer.php'; ?>
