<?php
/**
 * Ver Detalles de Campaña
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../app/controllers/AuthController.php';
require_once __DIR__ . '/../../app/models/Campana.php';
require_once __DIR__ . '/../../app/models/Simpatizante.php';

$auth = new AuthController();
$auth->requiereRol(['super_admin', 'admin', 'coordinador']);

// Validar ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$campanaId = (int)$_GET['id'];
$campanaModel = new Campana();
$simpatizanteModel = new Simpatizante();

// Obtener campaña
$campana = $campanaModel->obtenerPorId($campanaId);

if (!$campana) {
    header('Location: index.php');
    exit;
}

// Obtener estadísticas
$stats = $campanaModel->obtenerEstadisticas($campanaId);

// Obtener simpatizantes recientes de esta campaña
$filtros = ['campana_id' => $campanaId];
$simpatizantesRecientes = $simpatizanteModel->obtenerTodos($filtros, 1, 10);

// Obtener estadísticas por sección para esta campaña
$estatsPorSeccion = $simpatizanteModel->obtenerEstadisticasPorSeccion($filtros);

// Obtener estadísticas por capturista para esta campaña
$estatsPorCapturista = $simpatizanteModel->obtenerEstadisticasPorCapturista($filtros);

// Obtener actividad diaria (últimos 30 días)
$db = Database::getInstance();
$sql = "SELECT DATE(created_at) as fecha, COUNT(*) as total 
        FROM simpatizantes 
        WHERE campana_id = ?
        AND DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY DATE(created_at)
        ORDER BY fecha ASC";
$actividadDiaria = $db->query($sql, [$campanaId]);

$pageTitle = 'Detalles de Campaña';
include __DIR__ . '/../../app/views/layouts/header.php';
?>

<div class="container-fluid">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-md-8">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Campañas</a></li>
                    <li class="breadcrumb-item active"><?php echo htmlspecialchars($campana['nombre']); ?></li>
                </ol>
            </nav>
            <h2><i class="bi bi-megaphone-fill me-2"></i><?php echo htmlspecialchars($campana['nombre']); ?></h2>
            <?php if ($campana['activa']): ?>
                <span class="badge bg-success">Activa</span>
            <?php else: ?>
                <span class="badge bg-secondary">Inactiva</span>
            <?php endif; ?>
        </div>
        <div class="col-md-4 text-end">
            <a href="index.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left me-2"></i>Volver
            </a>
            <?php if (in_array($auth->obtenerRol(), ['super_admin', 'admin'])): ?>
                <a href="editar.php?id=<?php echo $campana['id']; ?>" class="btn btn-warning">
                    <i class="bi bi-pencil-fill me-2"></i>Editar
                </a>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Información General -->
    <div class="row mb-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>Información General</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="text-muted small">Descripción:</label>
                            <p><?php echo htmlspecialchars($campana['descripcion'] ?? 'Sin descripción'); ?></p>
                        </div>
                        <div class="col-md-6">
                            <?php if ($campana['candidato_nombre']): ?>
                                <label class="text-muted small">Candidato:</label>
                                <p><strong><?php echo htmlspecialchars($campana['candidato_nombre']); ?></strong></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-3">
                            <label class="text-muted small">Fecha Inicio:</label>
                            <p><strong><?php echo date('d/m/Y', strtotime($campana['fecha_inicio'])); ?></strong></p>
                        </div>
                        <div class="col-md-3">
                            <label class="text-muted small">Fecha Fin:</label>
                            <p><strong><?php echo date('d/m/Y', strtotime($campana['fecha_fin'])); ?></strong></p>
                        </div>
                        <div class="col-md-3">
                            <label class="text-muted small">Fecha Creación:</label>
                            <p><?php echo date('d/m/Y H:i', strtotime($campana['created_at'])); ?></p>
                        </div>
                        <div class="col-md-3">
                            <label class="text-muted small">Última Actualización:</label>
                            <p><?php echo date('d/m/Y H:i', strtotime($campana['updated_at'])); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Estadísticas Principales -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="bi bi-bar-chart me-2"></i>Estadísticas</h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-6 mb-3">
                            <h3 class="text-primary mb-0"><?php echo number_format($stats['total_simpatizantes']); ?></h3>
                            <small class="text-muted">Simpatizantes</small>
                        </div>
                        <div class="col-6 mb-3">
                            <h3 class="text-success mb-0"><?php echo number_format($stats['total_validados']); ?></h3>
                            <small class="text-muted">Validados</small>
                        </div>
                        <div class="col-6">
                            <h3 class="text-info mb-0"><?php echo number_format($stats['total_secciones']); ?></h3>
                            <small class="text-muted">Secciones</small>
                        </div>
                        <div class="col-6">
                            <h3 class="text-warning mb-0"><?php echo number_format($stats['total_capturistas']); ?></h3>
                            <small class="text-muted">Capturistas</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Gráfica de Actividad -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="bi bi-graph-up me-2"></i>Actividad Reciente (Últimos 30 días)</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($actividadDiaria)): ?>
                        <canvas id="chartActividad" height="80"></canvas>
                    <?php else: ?>
                        <p class="text-muted text-center py-4">No hay datos de actividad disponibles</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Gráficas de Distribución -->
    <div class="row mb-4">
        <!-- Por Sección -->
        <div class="col-lg-6 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="bi bi-geo-alt-fill me-2"></i>Top 10 Secciones</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($estatsPorSeccion)): ?>
                        <canvas id="chartSecciones" height="150"></canvas>
                    <?php else: ?>
                        <p class="text-muted text-center py-4">No hay datos disponibles</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Por Capturista -->
        <div class="col-lg-6 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="bi bi-people-fill me-2"></i>Top 10 Capturistas</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($estatsPorCapturista)): ?>
                        <canvas id="chartCapturistas" height="150"></canvas>
                    <?php else: ?>
                        <p class="text-muted text-center py-4">No hay datos disponibles</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Simpatizantes Recientes -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-people me-2"></i>Simpatizantes Recientes</h5>
                    <a href="../simpatizantes/index.php?campana_id=<?php echo $campana['id']; ?>" class="btn btn-sm btn-primary">
                        Ver Todos
                    </a>
                </div>
                <div class="card-body">
                    <?php if (!empty($simpatizantesRecientes)): ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nombre</th>
                                        <th>Sección</th>
                                        <th>Sexo</th>
                                        <th>Capturista</th>
                                        <th>Estado</th>
                                        <th>Fecha Registro</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($simpatizantesRecientes as $simp): ?>
                                        <tr>
                                            <td><?php echo $simp['id']; ?></td>
                                            <td><?php echo htmlspecialchars($simp['nombre_completo']); ?></td>
                                            <td><?php echo htmlspecialchars($simp['seccion_electoral']); ?></td>
                                            <td><?php echo htmlspecialchars($simp['sexo'] ?? '-'); ?></td>
                                            <td><small><?php echo htmlspecialchars($simp['capturista_nombre'] ?? 'N/A'); ?></small></td>
                                            <td>
                                                <?php if ($simp['validado']): ?>
                                                    <span class="badge bg-success">Validado</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning">Pendiente</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><small><?php echo date('d/m/Y H:i', strtotime($simp['created_at'])); ?></small></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="bi bi-inbox" style="font-size: 3rem; color: #ddd;"></i>
                            <p class="mt-3 text-muted">No hay simpatizantes registrados en esta campaña</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($actividadDiaria) || !empty($estatsPorSeccion) || !empty($estatsPorCapturista)): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Datos para las gráficas
const dataActividad = <?php echo json_encode($actividadDiaria); ?>;
const dataSecciones = <?php echo json_encode(array_slice($estatsPorSeccion, 0, 10)); ?>;
const dataCapturistas = <?php echo json_encode(array_slice($estatsPorCapturista, 0, 10)); ?>;

// Gráfica de Actividad
<?php if (!empty($actividadDiaria)): ?>
const ctxActividad = document.getElementById('chartActividad');
if (ctxActividad && dataActividad && dataActividad.length > 0) {
    new Chart(ctxActividad, {
        type: 'line',
        data: {
            labels: dataActividad.map(item => new Date(item.fecha).toLocaleDateString('es-MX')),
            datasets: [{
                label: 'Simpatizantes Registrados',
                data: dataActividad.map(item => parseInt(item.total)),
                borderColor: 'rgb(102, 126, 234)',
                backgroundColor: 'rgba(102, 126, 234, 0.1)',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    display: true
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            }
        }
    });
}
<?php endif; ?>

// Gráfica de Secciones
<?php if (!empty($estatsPorSeccion)): ?>
const ctxSecciones = document.getElementById('chartSecciones');
if (ctxSecciones && dataSecciones && dataSecciones.length > 0) {
    new Chart(ctxSecciones, {
        type: 'bar',
        data: {
            labels: dataSecciones.map(item => 'Sección ' + item.seccion_electoral),
            datasets: [{
                label: 'Simpatizantes',
                data: dataSecciones.map(item => parseInt(item.total)),
                backgroundColor: 'rgba(102, 126, 234, 0.8)',
                borderColor: 'rgba(102, 126, 234, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            }
        }
    });
}
<?php endif; ?>

// Gráfica de Capturistas
<?php if (!empty($estatsPorCapturista)): ?>
const ctxCapturistas = document.getElementById('chartCapturistas');
if (ctxCapturistas && dataCapturistas && dataCapturistas.length > 0) {
    new Chart(ctxCapturistas, {
        type: 'pie',
        data: {
            labels: dataCapturistas.map(item => item.nombre_completo),
            datasets: [{
                data: dataCapturistas.map(item => parseInt(item.total)),
                backgroundColor: [
                    'rgba(255, 99, 132, 0.8)',
                    'rgba(54, 162, 235, 0.8)',
                    'rgba(255, 206, 86, 0.8)',
                    'rgba(75, 192, 192, 0.8)',
                    'rgba(153, 102, 255, 0.8)',
                    'rgba(255, 159, 64, 0.8)',
                    'rgba(199, 199, 199, 0.8)',
                    'rgba(83, 102, 255, 0.8)',
                    'rgba(255, 99, 255, 0.8)',
                    'rgba(99, 255, 132, 0.8)'
                ],
                borderColor: '#fff',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
}
<?php endif; ?>
</script>
<?php endif; ?>

<?php include __DIR__ . '/../../app/views/layouts/footer.php'; ?>
