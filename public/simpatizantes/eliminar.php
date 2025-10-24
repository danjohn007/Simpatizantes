<?php
/**
 * Eliminar Simpatizante
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../app/controllers/AuthController.php';
require_once __DIR__ . '/../../app/controllers/SimpatizanteController.php';

$auth = new AuthController();
$auth->requiereRol(['super_admin', 'admin']);

// Validar ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: ' . BASE_URL . '/public/simpatizantes/index.php');
    exit;
}

$id = (int)$_GET['id'];
$controller = new SimpatizanteController();

// Obtener simpatizante para mostrar información
$simpatizante = $controller->obtener($id);

if (!$simpatizante || isset($simpatizante['error'])) {
    $_SESSION['error'] = 'Simpatizante no encontrado';
    header('Location: ' . BASE_URL . '/public/simpatizantes/index.php');
    exit;
}

$error = '';

// Procesar eliminación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmar_eliminar'])) {
    $result = $controller->eliminar($id);
    
    if (isset($result['success'])) {
        $_SESSION['success'] = 'Simpatizante eliminado correctamente';
        header('Location: ' . BASE_URL . '/public/simpatizantes/index.php');
        exit;
    } else {
        $error = $result['error'] ?? 'Error al eliminar simpatizante';
    }
}

$pageTitle = 'Eliminar Simpatizante';
include __DIR__ . '/../../app/views/layouts/header.php';
?>

<div class="container-fluid">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-md-8">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/public/simpatizantes/index.php">Simpatizantes</a></li>
                    <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/public/simpatizantes/ver.php?id=<?php echo $id; ?>">Detalles</a></li>
                    <li class="breadcrumb-item active">Eliminar</li>
                </ol>
            </nav>
            <h2><i class="bi bi-trash-fill me-2 text-danger"></i>Eliminar Simpatizante</h2>
        </div>
        <div class="col-md-4 text-end">
            <a href="<?php echo BASE_URL; ?>/public/simpatizantes/ver.php?id=<?php echo $id; ?>" class="btn btn-secondary">
                <i class="bi bi-arrow-left me-2"></i>Cancelar
            </a>
        </div>
    </div>
    
    <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>
    
    <!-- Confirmación de Eliminación -->
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card border-danger shadow-sm">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0"><i class="bi bi-exclamation-triangle-fill me-2"></i>Confirmación de Eliminación</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <strong>¡Advertencia!</strong> Esta acción no se puede deshacer. Toda la información del simpatizante será eliminada permanentemente.
                    </div>
                    
                    <h5 class="mb-3">Datos del simpatizante a eliminar:</h5>
                    
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <tbody>
                                <tr>
                                    <td class="fw-bold" style="width: 30%;">ID:</td>
                                    <td><?php echo htmlspecialchars($simpatizante['id']); ?></td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">Nombre Completo:</td>
                                    <td><?php echo htmlspecialchars($simpatizante['nombre_completo']); ?></td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">Sección Electoral:</td>
                                    <td><?php echo htmlspecialchars($simpatizante['seccion_electoral']); ?></td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">CURP:</td>
                                    <td><?php echo htmlspecialchars($simpatizante['curp'] ?? 'No proporcionado'); ?></td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">Clave de Elector:</td>
                                    <td><?php echo htmlspecialchars($simpatizante['clave_elector'] ?? 'No proporcionada'); ?></td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">Domicilio:</td>
                                    <td><?php echo htmlspecialchars($simpatizante['domicilio_completo']); ?></td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">Campaña:</td>
                                    <td><?php echo htmlspecialchars($simpatizante['campana_nombre'] ?? 'No asignada'); ?></td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">Capturista:</td>
                                    <td><?php echo htmlspecialchars($simpatizante['capturista_nombre'] ?? 'No asignado'); ?></td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">Fecha de Registro:</td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($simpatizante['created_at'])); ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <form method="POST" action="" class="mt-4">
                        <div class="d-grid gap-2 d-md-flex justify-content-md-center">
                            <button type="submit" name="confirmar_eliminar" class="btn btn-danger btn-lg" onclick="return confirm('¿Está completamente seguro de que desea eliminar este simpatizante? Esta acción es irreversible.');">
                                <i class="bi bi-trash-fill me-2"></i>Confirmar Eliminación
                            </button>
                            <a href="<?php echo BASE_URL; ?>/public/simpatizantes/ver.php?id=<?php echo $id; ?>" class="btn btn-secondary btn-lg">
                                <i class="bi bi-x-circle me-2"></i>Cancelar
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
    return confirm('¿Está seguro de que desea eliminar este simpatizante?\n\nEsta acción no se puede deshacer.');
}
</script>

<?php include __DIR__ . '/../../app/views/layouts/footer.php'; ?>
