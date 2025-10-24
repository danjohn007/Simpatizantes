<?php
/**
 * Listado de Campañas
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../app/controllers/AuthController.php';
require_once __DIR__ . '/../../app/models/Campana.php';

$auth = new AuthController();
$auth->requiereRol(['super_admin', 'admin', 'coordinador']);

$campanaModel = new Campana();
$campanas = $campanaModel->obtenerTodas();

$pageTitle = 'Campañas';
include __DIR__ . '/../../app/views/layouts/header.php';
?>

<div class="container-fluid">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-md-6">
            <h2><i class="bi bi-megaphone-fill me-2"></i>Campañas</h2>
            <p class="text-muted">Gestión de campañas electorales</p>
        </div>
        <div class="col-md-6 text-end">
            <?php if (in_array($auth->obtenerRol(), ['super_admin', 'admin'])): ?>
                <a href="crear.php" class="btn btn-gradient">
                    <i class="bi bi-plus-circle-fill me-2"></i>Nueva Campaña
                </a>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Lista de Campañas -->
    <div class="row">
        <?php if (empty($campanas)): ?>
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-inbox" style="font-size: 4rem; color: #ddd;"></i>
                        <p class="mt-3 text-muted">No hay campañas registradas</p>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($campanas as $campana): 
                $stats = $campanaModel->obtenerEstadisticas($campana['id']);
            ?>
                <div class="col-lg-6 mb-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <?php echo htmlspecialchars($campana['nombre']); ?>
                                    <?php if ($campana['activa']): ?>
                                        <span class="badge bg-success ms-2">Activa</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary ms-2">Inactiva</span>
                                    <?php endif; ?>
                                </h5>
                            </div>
                        </div>
                        <div class="card-body">
                            <p class="text-muted">
                                <?php echo htmlspecialchars($campana['descripcion'] ?? 'Sin descripción'); ?>
                            </p>
                            
                            <div class="row mb-3">
                                <div class="col-6">
                                    <small class="text-muted">Fecha Inicio:</small><br>
                                    <strong><?php echo date('d/m/Y', strtotime($campana['fecha_inicio'])); ?></strong>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted">Fecha Fin:</small><br>
                                    <strong><?php echo date('d/m/Y', strtotime($campana['fecha_fin'])); ?></strong>
                                </div>
                            </div>
                            
                            <?php if ($campana['candidato_nombre']): ?>
                                <div class="mb-3">
                                    <small class="text-muted">Candidato:</small><br>
                                    <strong><?php echo htmlspecialchars($campana['candidato_nombre']); ?></strong>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Estadísticas -->
                            <div class="row text-center mt-4">
                                <div class="col-3">
                                    <h4 class="text-primary mb-0"><?php echo number_format($stats['total_simpatizantes']); ?></h4>
                                    <small class="text-muted">Simpatizantes</small>
                                </div>
                                <div class="col-3">
                                    <h4 class="text-success mb-0"><?php echo number_format($stats['total_validados']); ?></h4>
                                    <small class="text-muted">Validados</small>
                                </div>
                                <div class="col-3">
                                    <h4 class="text-info mb-0"><?php echo number_format($stats['total_secciones']); ?></h4>
                                    <small class="text-muted">Secciones</small>
                                </div>
                                <div class="col-3">
                                    <h4 class="text-warning mb-0"><?php echo number_format($stats['total_capturistas']); ?></h4>
                                    <small class="text-muted">Capturistas</small>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer bg-white">
                            <div class="d-flex justify-content-between">
                                <a href="ver.php?id=<?php echo $campana['id']; ?>" class="btn btn-sm btn-info">
                                    <i class="bi bi-eye-fill me-1"></i>Ver Detalles
                                </a>
                                <?php if (in_array($auth->obtenerRol(), ['super_admin', 'admin'])): ?>
                                    <div>
                                        <a href="editar.php?id=<?php echo $campana['id']; ?>" class="btn btn-sm btn-warning">
                                            <i class="bi bi-pencil-fill"></i>
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../../app/views/layouts/footer.php'; ?>
