<?php
/**
 * Configuración del Sistema
 * Solo para Super Admin
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../app/controllers/AuthController.php';

$auth = new AuthController();
$auth->requiereRol(['super_admin']);

$db = Database::getInstance();
$success = '';
$error = '';

// Obtener configuración actual
$sql = "SELECT * FROM configuracion ORDER BY clave";
$configuraciones = $db->query($sql);

// Actualizar configuración
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($_POST as $clave => $valor) {
        if (strpos($clave, 'config_') === 0) {
            $claveConfig = str_replace('config_', '', $clave);
            $sql = "UPDATE configuracion SET valor = ? WHERE clave = ?";
            $db->execute($sql, [$valor, $claveConfig]);
        }
    }
    $success = 'Configuración actualizada correctamente';
    $configuraciones = $db->query("SELECT * FROM configuracion ORDER BY clave");
}

$pageTitle = 'Configuración';
include __DIR__ . '/../../app/views/layouts/header.php';
?>

<div class="container-fluid">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-md-6">
            <h2><i class="bi bi-gear-fill me-2"></i>Configuración del Sistema</h2>
            <p class="text-muted">Parámetros y ajustes del sistema</p>
        </div>
    </div>
    
    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle-fill me-2"></i><?php echo htmlspecialchars($success); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="bi bi-sliders me-2"></i>Parámetros del Sistema
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <?php foreach ($configuraciones as $config): ?>
                            <div class="mb-3">
                                <label class="form-label">
                                    <strong><?php echo htmlspecialchars($config['clave']); ?></strong>
                                </label>
                                
                                <?php if ($config['tipo'] === 'boolean'): ?>
                                    <select class="form-select" name="config_<?php echo $config['clave']; ?>">
                                        <option value="true" <?php echo ($config['valor'] === 'true') ? 'selected' : ''; ?>>
                                            Sí
                                        </option>
                                        <option value="false" <?php echo ($config['valor'] === 'false') ? 'selected' : ''; ?>>
                                            No
                                        </option>
                                    </select>
                                <?php elseif ($config['tipo'] === 'numero'): ?>
                                    <input type="number" class="form-control" 
                                           name="config_<?php echo $config['clave']; ?>" 
                                           value="<?php echo htmlspecialchars($config['valor']); ?>">
                                <?php else: ?>
                                    <input type="text" class="form-control" 
                                           name="config_<?php echo $config['clave']; ?>" 
                                           value="<?php echo htmlspecialchars($config['valor']); ?>">
                                <?php endif; ?>
                                
                                <?php if ($config['descripcion']): ?>
                                    <small class="text-muted">
                                        <?php echo htmlspecialchars($config['descripcion']); ?>
                                    </small>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        
                        <div class="text-end mt-4">
                            <button type="submit" class="btn btn-gradient">
                                <i class="bi bi-save-fill me-2"></i>Guardar Configuración
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="bi bi-info-circle-fill me-2"></i>Información
                    </h5>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <strong>Versión:</strong><br>
                            <?php echo APP_VERSION; ?>
                        </li>
                        <li class="mb-2">
                            <strong>PHP Version:</strong><br>
                            <?php echo PHP_VERSION; ?>
                        </li>
                        <li class="mb-2">
                            <strong>Base de Datos:</strong><br>
                            <?php echo DB_NAME; ?>
                        </li>
                        <li class="mb-2">
                            <strong>Servidor:</strong><br>
                            <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'N/A'; ?>
                        </li>
                    </ul>
                </div>
            </div>
            
            <div class="card border-0 shadow-sm bg-warning bg-opacity-10">
                <div class="card-body">
                    <h6><i class="bi bi-exclamation-triangle-fill text-warning me-2"></i>Advertencia</h6>
                    <p class="small mb-0">
                        Modifica estos valores con precaución. Los cambios pueden afectar 
                        el funcionamiento del sistema.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../app/views/layouts/footer.php'; ?>
