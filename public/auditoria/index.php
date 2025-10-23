<?php
/**
 * Logs de Auditoría
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../app/controllers/AuthController.php';
require_once __DIR__ . '/../../app/models/LogAuditoria.php';

$auth = new AuthController();
$auth->requiereRol(['super_admin', 'admin']);

$logModel = new LogAuditoria();

// Procesar filtros
$filtros = [];
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

if (!empty($_GET['usuario_id'])) {
    $filtros['usuario_id'] = $_GET['usuario_id'];
}

if (!empty($_GET['accion'])) {
    $filtros['accion'] = $_GET['accion'];
}

if (!empty($_GET['fecha_inicio'])) {
    $filtros['fecha_inicio'] = $_GET['fecha_inicio'];
} else {
    $filtros['fecha_inicio'] = date('Y-m-d', strtotime('-7 days'));
}

if (!empty($_GET['fecha_fin'])) {
    $filtros['fecha_fin'] = $_GET['fecha_fin'];
} else {
    $filtros['fecha_fin'] = date('Y-m-d');
}

// Obtener logs
$logs = $logModel->obtenerLogs($filtros, $page);
$total = $logModel->contarTotal($filtros);
$totalPaginas = ceil($total / RECORDS_PER_PAGE);

$pageTitle = 'Auditoría';
include __DIR__ . '/../../app/views/layouts/header.php';
?>

<div class="container-fluid">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-md-6">
            <h2><i class="bi bi-journal-text me-2"></i>Logs de Auditoría</h2>
            <p class="text-muted">Registro de actividades del sistema</p>
        </div>
    </div>
    
    <!-- Filtros -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label small">Acción</label>
                    <select class="form-select" name="accion">
                        <option value="">Todas las acciones</option>
                        <option value="login" <?php echo (isset($_GET['accion']) && $_GET['accion'] === 'login') ? 'selected' : ''; ?>>
                            Login
                        </option>
                        <option value="logout" <?php echo (isset($_GET['accion']) && $_GET['accion'] === 'logout') ? 'selected' : ''; ?>>
                            Logout
                        </option>
                        <option value="crear_simpatizante" <?php echo (isset($_GET['accion']) && $_GET['accion'] === 'crear_simpatizante') ? 'selected' : ''; ?>>
                            Crear Simpatizante
                        </option>
                        <option value="actualizar_simpatizante" <?php echo (isset($_GET['accion']) && $_GET['accion'] === 'actualizar_simpatizante') ? 'selected' : ''; ?>>
                            Actualizar Simpatizante
                        </option>
                        <option value="eliminar_simpatizante" <?php echo (isset($_GET['accion']) && $_GET['accion'] === 'eliminar_simpatizante') ? 'selected' : ''; ?>>
                            Eliminar Simpatizante
                        </option>
                        <option value="crear_usuario" <?php echo (isset($_GET['accion']) && $_GET['accion'] === 'crear_usuario') ? 'selected' : ''; ?>>
                            Crear Usuario
                        </option>
                        <option value="actualizar_usuario" <?php echo (isset($_GET['accion']) && $_GET['accion'] === 'actualizar_usuario') ? 'selected' : ''; ?>>
                            Actualizar Usuario
                        </option>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label small">Fecha Inicio</label>
                    <input type="date" class="form-control" name="fecha_inicio" 
                           value="<?php echo htmlspecialchars($filtros['fecha_inicio']); ?>">
                </div>
                
                <div class="col-md-3">
                    <label class="form-label small">Fecha Fin</label>
                    <input type="date" class="form-control" name="fecha_fin" 
                           value="<?php echo htmlspecialchars($filtros['fecha_fin']); ?>">
                </div>
                
                <div class="col-md-3">
                    <label class="form-label small">&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-search"></i> Filtrar
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
                    Total: <?php echo number_format($total); ?> registros
                </h5>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Fecha/Hora</th>
                            <th>Usuario</th>
                            <th>Acción</th>
                            <th>Tabla</th>
                            <th>Registro ID</th>
                            <th>IP</th>
                            <th>Detalles</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">
                                    <i class="bi bi-inbox" style="font-size: 3rem;"></i>
                                    <p class="mt-2">No se encontraron registros</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td><?php echo $log['id']; ?></td>
                                    <td>
                                        <small>
                                            <?php echo date('d/m/Y H:i:s', strtotime($log['created_at'])); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <small><?php echo htmlspecialchars($log['usuario_nombre'] ?? 'Sistema'); ?></small>
                                    </td>
                                    <td>
                                        <?php
                                        $accionClasses = [
                                            'login' => 'success',
                                            'logout' => 'secondary',
                                            'crear_simpatizante' => 'primary',
                                            'crear_usuario' => 'primary',
                                            'actualizar_simpatizante' => 'warning',
                                            'actualizar_usuario' => 'warning',
                                            'eliminar_simpatizante' => 'danger',
                                            'eliminar_usuario' => 'danger',
                                        ];
                                        $accionClass = $accionClasses[$log['accion']] ?? 'info';
                                        ?>
                                        <span class="badge bg-<?php echo $accionClass; ?>">
                                            <?php echo htmlspecialchars(str_replace('_', ' ', $log['accion'])); ?>
                                        </span>
                                    </td>
                                    <td><small><?php echo htmlspecialchars($log['tabla_afectada'] ?? '-'); ?></small></td>
                                    <td><small><?php echo $log['registro_id'] ?? '-'; ?></small></td>
                                    <td><small><?php echo htmlspecialchars($log['ip_address'] ?? '-'); ?></small></td>
                                    <td>
                                        <?php if ($log['datos_nuevos'] || $log['datos_anteriores']): ?>
                                            <button class="btn btn-sm btn-link" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#modalLog<?php echo $log['id']; ?>">
                                                <i class="bi bi-info-circle"></i>
                                            </button>
                                            
                                            <!-- Modal -->
                                            <div class="modal fade" id="modalLog<?php echo $log['id']; ?>" tabindex="-1">
                                                <div class="modal-dialog modal-lg">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Detalles del Log #<?php echo $log['id']; ?></h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <?php if ($log['datos_anteriores']): ?>
                                                                <h6>Datos Anteriores:</h6>
                                                                <pre class="bg-light p-3 rounded"><?php echo htmlspecialchars(json_encode(json_decode($log['datos_anteriores']), JSON_PRETTY_PRINT)); ?></pre>
                                                            <?php endif; ?>
                                                            
                                                            <?php if ($log['datos_nuevos']): ?>
                                                                <h6>Datos Nuevos:</h6>
                                                                <pre class="bg-light p-3 rounded"><?php echo htmlspecialchars(json_encode(json_decode($log['datos_nuevos']), JSON_PRETTY_PRINT)); ?></pre>
                                                            <?php endif; ?>
                                                            
                                                            <h6>Información Adicional:</h6>
                                                            <ul>
                                                                <li><strong>User Agent:</strong> <?php echo htmlspecialchars($log['user_agent'] ?? 'N/A'); ?></li>
                                                            </ul>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Paginación -->
            <?php if ($totalPaginas > 1): ?>
                <nav class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                            <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
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

<?php include __DIR__ . '/../../app/views/layouts/footer.php'; ?>
