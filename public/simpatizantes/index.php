<?php
/**
 * Listado de Simpatizantes
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../app/controllers/AuthController.php';
require_once __DIR__ . '/../../app/controllers/SimpatizanteController.php';
require_once __DIR__ . '/../../app/models/Campana.php';

$auth = new AuthController();
$auth->requiereAutenticacion();

$controller = new SimpatizanteController();
$campanaModel = new Campana();

// Procesar filtros
$filtros = [];
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

if (!empty($_GET['campana_id'])) {
    $filtros['campana_id'] = $_GET['campana_id'];
}

if (!empty($_GET['seccion'])) {
    $filtros['seccion_electoral'] = $_GET['seccion'];
}

if (!empty($_GET['busqueda'])) {
    $filtros['busqueda'] = $_GET['busqueda'];
}

if (!empty($_GET['fecha_inicio'])) {
    $filtros['fecha_inicio'] = $_GET['fecha_inicio'];
}

if (!empty($_GET['fecha_fin'])) {
    $filtros['fecha_fin'] = $_GET['fecha_fin'];
}

// Obtener datos
$resultado = $controller->listar($filtros, $page);
$campanas = $campanaModel->obtenerTodas(1);

$pageTitle = 'Simpatizantes';
include __DIR__ . '/../../app/views/layouts/header.php';
?>

<div class="container-fluid">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-md-6">
            <h2><i class="bi bi-people-fill me-2"></i>Simpatizantes</h2>
            <p class="text-muted">Gestión de registros de simpatizantes</p>
        </div>
        <div class="col-md-6 text-end">
            <a href="<?php echo BASE_URL; ?>/public/simpatizantes/crear.php" class="btn btn-gradient">
                <i class="bi bi-person-plus-fill me-2"></i>Nuevo Simpatizante
            </a>
        </div>
    </div>
    
    <!-- Filtros -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label small">Búsqueda</label>
                    <input type="text" class="form-control" name="busqueda" 
                           placeholder="Nombre, CURP, Clave..." 
                           value="<?php echo htmlspecialchars($_GET['busqueda'] ?? ''); ?>">
                </div>
                
                <div class="col-md-2">
                    <label class="form-label small">Campaña</label>
                    <select class="form-select" name="campana_id">
                        <option value="">Todas</option>
                        <?php foreach ($campanas as $campana): ?>
                            <option value="<?php echo $campana['id']; ?>" 
                                    <?php echo (isset($_GET['campana_id']) && $_GET['campana_id'] == $campana['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($campana['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label small">Sección</label>
                    <input type="text" class="form-control" name="seccion" 
                           placeholder="Sección electoral"
                           value="<?php echo htmlspecialchars($_GET['seccion'] ?? ''); ?>">
                </div>
                
                <div class="col-md-2">
                    <label class="form-label small">Fecha Inicio</label>
                    <input type="date" class="form-control" name="fecha_inicio" 
                           value="<?php echo htmlspecialchars($_GET['fecha_inicio'] ?? ''); ?>">
                </div>
                
                <div class="col-md-2">
                    <label class="form-label small">Fecha Fin</label>
                    <input type="date" class="form-control" name="fecha_fin" 
                           value="<?php echo htmlspecialchars($_GET['fecha_fin'] ?? ''); ?>">
                </div>
                
                <div class="col-md-1">
                    <label class="form-label small">&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-search"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Resultados -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    Total: <?php echo number_format($resultado['total']); ?> registros
                </h5>
                <a href="<?php echo BASE_URL; ?>/public/simpatizantes/exportar.php?<?php echo http_build_query($filtros); ?>" 
                   class="btn btn-sm btn-success">
                    <i class="bi bi-file-earmark-excel me-1"></i>Exportar
                </a>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nombre Completo</th>
                            <th>Sección</th>
                            <th>Clave Elector</th>
                            <th>CURP</th>
                            <th>Campaña</th>
                            <th>Capturista</th>
                            <th>Fecha</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($resultado['simpatizantes'])): ?>
                            <tr>
                                <td colspan="9" class="text-center text-muted py-4">
                                    <i class="bi bi-inbox" style="font-size: 3rem;"></i>
                                    <p class="mt-2">No se encontraron simpatizantes</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($resultado['simpatizantes'] as $simp): ?>
                                <tr>
                                    <td><?php echo $simp['id']; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($simp['nombre_completo']); ?></strong>
                                        <br>
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars(substr($simp['domicilio_completo'], 0, 40)); ?>...
                                        </small>
                                    </td>
                                    <td>
                                        <span class="badge bg-info">
                                            <?php echo htmlspecialchars($simp['seccion_electoral']); ?>
                                        </span>
                                    </td>
                                    <td><small><?php echo htmlspecialchars($simp['clave_elector'] ?? '-'); ?></small></td>
                                    <td><small><?php echo htmlspecialchars($simp['curp'] ?? '-'); ?></small></td>
                                    <td><small><?php echo htmlspecialchars($simp['campana_nombre'] ?? '-'); ?></small></td>
                                    <td><small><?php echo htmlspecialchars($simp['capturista_nombre'] ?? '-'); ?></small></td>
                                    <td>
                                        <small><?php echo date('d/m/Y', strtotime($simp['created_at'])); ?></small>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="<?php echo BASE_URL; ?>/public/simpatizantes/ver.php?id=<?php echo $simp['id']; ?>" 
                                               class="btn btn-info btn-sm" title="Ver">
                                                <i class="bi bi-eye-fill"></i>
                                            </a>
                                            <a href="<?php echo BASE_URL; ?>/public/simpatizantes/editar.php?id=<?php echo $simp['id']; ?>" 
                                               class="btn btn-warning btn-sm" title="Editar">
                                                <i class="bi bi-pencil-fill"></i>
                                            </a>
                                            <?php if (in_array($auth->obtenerRol(), ['super_admin', 'admin'])): ?>
                                                <a href="<?php echo BASE_URL; ?>/public/simpatizantes/eliminar.php?id=<?php echo $simp['id']; ?>" 
                                                   class="btn btn-danger btn-sm" title="Eliminar"
                                                   onclick="return confirmarEliminar()">
                                                    <i class="bi bi-trash-fill"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Paginación -->
            <?php if ($resultado['total_paginas'] > 1): ?>
                <nav class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php for ($i = 1; $i <= $resultado['total_paginas']; $i++): ?>
                            <li class="page-item <?php echo ($i == $resultado['pagina_actual']) ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&<?php echo http_build_query($filtros); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function confirmarEliminar() {
    return confirm('¿Está seguro de que desea eliminar este simpatizante?\n\nEsta acción no se puede deshacer.');
}
</script>

<?php include __DIR__ . '/../../app/views/layouts/footer.php'; ?>
