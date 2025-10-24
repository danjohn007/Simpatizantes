<?php
/**
 * Crear Campaña
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../app/controllers/AuthController.php';
require_once __DIR__ . '/../../app/models/Campana.php';
require_once __DIR__ . '/../../app/models/Usuario.php';

$auth = new AuthController();
$auth->requiereRol(['super_admin', 'admin']);

$campanaModel = new Campana();
$usuarioModel = new Usuario();

$error = '';
$success = '';
$errores = [];

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $datos = [
        'nombre' => trim($_POST['nombre'] ?? ''),
        'descripcion' => trim($_POST['descripcion'] ?? ''),
        'fecha_inicio' => $_POST['fecha_inicio'] ?? '',
        'fecha_fin' => $_POST['fecha_fin'] ?? '',
        'candidato_id' => !empty($_POST['candidato_id']) ? $_POST['candidato_id'] : null,
        'activa' => isset($_POST['activa']) ? 1 : 0
    ];
    
    // Validaciones
    if (empty($datos['nombre'])) {
        $errores[] = 'El nombre de la campaña es obligatorio';
    }
    
    if (empty($datos['fecha_inicio'])) {
        $errores[] = 'La fecha de inicio es obligatoria';
    }
    
    if (empty($datos['fecha_fin'])) {
        $errores[] = 'La fecha de fin es obligatoria';
    }
    
    if (!empty($datos['fecha_inicio']) && !empty($datos['fecha_fin'])) {
        if (strtotime($datos['fecha_fin']) < strtotime($datos['fecha_inicio'])) {
            $errores[] = 'La fecha de fin debe ser posterior a la fecha de inicio';
        }
    }
    
    if (empty($errores)) {
        $result = $campanaModel->crear($datos);
        
        if (isset($result['success'])) {
            header('Location: index.php?success=1');
            exit;
        } else {
            $error = $result['error'] ?? 'Error al crear la campaña';
        }
    }
}

// Obtener candidatos para el select
$candidatos = $usuarioModel->obtenerTodos(1, 100, 'candidato');

$pageTitle = 'Nueva Campaña';
include __DIR__ . '/../../app/views/layouts/header.php';
?>

<div class="container-fluid">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-md-6">
            <h2><i class="bi bi-megaphone-fill me-2"></i>Nueva Campaña</h2>
            <p class="text-muted">Crear nueva campaña electoral</p>
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
                        <i class="bi bi-plus-circle-fill me-2"></i>Información de la Campaña
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="row">
                            <!-- Nombre -->
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Nombre de la Campaña <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="nombre" 
                                       value="<?php echo htmlspecialchars($_POST['nombre'] ?? ''); ?>" 
                                       required maxlength="150">
                            </div>
                            
                            <!-- Descripción -->
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Descripción</label>
                                <textarea class="form-control" name="descripcion" rows="3"><?php echo htmlspecialchars($_POST['descripcion'] ?? ''); ?></textarea>
                            </div>
                            
                            <!-- Fecha Inicio -->
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Fecha de Inicio <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="fecha_inicio" 
                                       value="<?php echo htmlspecialchars($_POST['fecha_inicio'] ?? ''); ?>" 
                                       required>
                            </div>
                            
                            <!-- Fecha Fin -->
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Fecha de Fin <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="fecha_fin" 
                                       value="<?php echo htmlspecialchars($_POST['fecha_fin'] ?? ''); ?>" 
                                       required>
                            </div>
                            
                            <!-- Candidato -->
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Candidato</label>
                                <select class="form-select" name="candidato_id">
                                    <option value="">Seleccione un candidato (opcional)</option>
                                    <?php foreach ($candidatos as $candidato): ?>
                                        <option value="<?php echo $candidato['id']; ?>"
                                                <?php echo (isset($_POST['candidato_id']) && $_POST['candidato_id'] == $candidato['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($candidato['nombre_completo']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- Activa -->
                            <div class="col-md-12 mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="activa" 
                                           id="activa" value="1" 
                                           <?php echo (isset($_POST['activa']) || !isset($_POST['nombre'])) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="activa">
                                        Campaña activa
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="text-end mt-4">
                            <a href="index.php" class="btn btn-secondary me-2">
                                <i class="bi bi-x-circle me-2"></i>Cancelar
                            </a>
                            <button type="submit" class="btn btn-gradient">
                                <i class="bi bi-save-fill me-2"></i>Crear Campaña
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
                        <strong>Fechas:</strong> La fecha de fin debe ser posterior a la fecha de inicio.
                    </p>
                    <p class="small mb-0">
                        <strong>Candidato:</strong> Puedes asociar un candidato a la campaña o dejarlo sin asignar.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../app/views/layouts/footer.php'; ?>
