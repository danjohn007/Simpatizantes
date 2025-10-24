<?php
/**
 * Ver Detalles de Simpatizante
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../app/controllers/AuthController.php';
require_once __DIR__ . '/../../app/controllers/SimpatizanteController.php';

$auth = new AuthController();
$auth->requiereAutenticacion();

// Validar ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: ' . BASE_URL . '/public/simpatizantes/index.php');
    exit;
}

$id = (int)$_GET['id'];
$controller = new SimpatizanteController();
$simpatizante = $controller->obtener($id);

// Verificar si hubo error
if (isset($simpatizante['error'])) {
    $_SESSION['error'] = $simpatizante['error'];
    header('Location: ' . BASE_URL . '/public/simpatizantes/index.php');
    exit;
}

if (!$simpatizante) {
    $_SESSION['error'] = 'Simpatizante no encontrado';
    header('Location: ' . BASE_URL . '/public/simpatizantes/index.php');
    exit;
}

$pageTitle = 'Detalles del Simpatizante';
include __DIR__ . '/../../app/views/layouts/header.php';
?>

<div class="container-fluid">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-md-8">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/public/simpatizantes/index.php">Simpatizantes</a></li>
                    <li class="breadcrumb-item active">Detalles</li>
                </ol>
            </nav>
            <h2><i class="bi bi-person-badge me-2"></i><?php echo htmlspecialchars($simpatizante['nombre_completo']); ?></h2>
        </div>
        <div class="col-md-4 text-end">
            <a href="<?php echo BASE_URL; ?>/public/simpatizantes/index.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left me-2"></i>Volver
            </a>
            <?php if (in_array($auth->obtenerRol(), ['super_admin', 'admin', 'coordinador']) || 
                      ($auth->obtenerRol() === 'capturista' && $simpatizante['capturista_id'] == $auth->obtenerUsuarioId())): ?>
                <a href="<?php echo BASE_URL; ?>/public/simpatizantes/editar.php?id=<?php echo $simpatizante['id']; ?>" class="btn btn-warning">
                    <i class="bi bi-pencil-fill me-2"></i>Editar
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Información Personal -->
    <div class="row mb-4">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="bi bi-person-fill me-2"></i>Información Personal</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label class="text-muted small">Nombre Completo:</label>
                            <p class="fw-bold"><?php echo htmlspecialchars($simpatizante['nombre_completo']); ?></p>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="text-muted small">Sexo:</label>
                            <p><?php echo htmlspecialchars($simpatizante['sexo'] ?? 'No especificado'); ?></p>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small">Fecha de Nacimiento:</label>
                            <p><?php echo !empty($simpatizante['fecha_nacimiento']) ? date('d/m/Y', strtotime($simpatizante['fecha_nacimiento'])) : 'No especificada'; ?></p>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="text-muted small">Teléfono:</label>
                            <p><?php echo htmlspecialchars($simpatizante['telefono'] ?? 'No proporcionado'); ?></p>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small">Email:</label>
                            <p><?php echo htmlspecialchars($simpatizante['email'] ?? 'No proporcionado'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Información Electoral -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="bi bi-card-checklist me-2"></i>Información Electoral</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="text-muted small">Sección Electoral:</label>
                            <p><span class="badge bg-info"><?php echo htmlspecialchars($simpatizante['seccion_electoral']); ?></span></p>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small">Clave de Elector:</label>
                            <p><?php echo htmlspecialchars($simpatizante['clave_elector'] ?? 'No proporcionada'); ?></p>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label class="text-muted small">CURP:</label>
                            <p><?php echo htmlspecialchars($simpatizante['curp'] ?? 'No proporcionado'); ?></p>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label class="text-muted small">Domicilio:</label>
                            <p><?php echo htmlspecialchars($simpatizante['domicilio_completo']); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Información de Campaña -->
    <div class="row mb-4">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="bi bi-megaphone-fill me-2"></i>Información de Campaña</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label class="text-muted small">Campaña:</label>
                            <p class="fw-bold"><?php echo htmlspecialchars($simpatizante['campana_nombre'] ?? 'No asignada'); ?></p>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="text-muted small">Capturista:</label>
                            <p><?php echo htmlspecialchars($simpatizante['capturista_nombre'] ?? 'No asignado'); ?></p>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small">Método de Captura:</label>
                            <p><?php echo htmlspecialchars($simpatizante['metodo_captura'] ?? 'No especificado'); ?></p>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="text-muted small">Estado:</label>
                            <p>
                                <?php if (!empty($simpatizante['validado'])): ?>
                                    <span class="badge bg-success">Validado</span>
                                <?php else: ?>
                                    <span class="badge bg-warning">Pendiente</span>
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small">Tipo de Registro:</label>
                            <p><?php echo htmlspecialchars($simpatizante['tipo_registro'] ?? 'No especificado'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Fechas y Observaciones -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="bi bi-calendar-event me-2"></i>Fechas y Observaciones</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="text-muted small">Fecha de Registro:</label>
                            <p><?php echo date('d/m/Y H:i', strtotime($simpatizante['created_at'])); ?></p>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small">Última Actualización:</label>
                            <p><?php echo date('d/m/Y H:i', strtotime($simpatizante['updated_at'])); ?></p>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label class="text-muted small">Observaciones:</label>
                            <p><?php echo !empty($simpatizante['observaciones']) ? nl2br(htmlspecialchars($simpatizante['observaciones'])) : 'Sin observaciones'; ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Imágenes -->
    <?php if (!empty($simpatizante['foto_ine_frontal']) || !empty($simpatizante['foto_ine_reverso']) || !empty($simpatizante['foto_comprobante'])): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="bi bi-images me-2"></i>Archivos Adjuntos</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php if (!empty($simpatizante['foto_ine_frontal'])): ?>
                        <div class="col-md-4 mb-3">
                            <label class="text-muted small d-block mb-2">INE Frontal:</label>
                            <a href="<?php echo BASE_URL; ?>/public/<?php echo htmlspecialchars($simpatizante['foto_ine_frontal']); ?>" target="_blank">
                                <img src="<?php echo BASE_URL; ?>/public/<?php echo htmlspecialchars($simpatizante['foto_ine_frontal']); ?>" 
                                     alt="INE Frontal" class="img-fluid rounded border">
                            </a>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($simpatizante['foto_ine_reverso'])): ?>
                        <div class="col-md-4 mb-3">
                            <label class="text-muted small d-block mb-2">INE Reverso:</label>
                            <a href="<?php echo BASE_URL; ?>/public/<?php echo htmlspecialchars($simpatizante['foto_ine_reverso']); ?>" target="_blank">
                                <img src="<?php echo BASE_URL; ?>/public/<?php echo htmlspecialchars($simpatizante['foto_ine_reverso']); ?>" 
                                     alt="INE Reverso" class="img-fluid rounded border">
                            </a>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($simpatizante['foto_comprobante'])): ?>
                        <div class="col-md-4 mb-3">
                            <label class="text-muted small d-block mb-2">Comprobante:</label>
                            <a href="<?php echo BASE_URL; ?>/public/<?php echo htmlspecialchars($simpatizante['foto_comprobante']); ?>" target="_blank">
                                <img src="<?php echo BASE_URL; ?>/public/<?php echo htmlspecialchars($simpatizante['foto_comprobante']); ?>" 
                                     alt="Comprobante" class="img-fluid rounded border">
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../app/views/layouts/footer.php'; ?>
