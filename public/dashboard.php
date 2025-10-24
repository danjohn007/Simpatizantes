<?php
/**
 * Dashboard Principal
 * Sistema de Validación de Simpatizantes
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../app/controllers/AuthController.php';
require_once __DIR__ . '/../app/models/Simpatizante.php';
require_once __DIR__ . '/../app/models/Campana.php';

$auth = new AuthController();
$auth->requiereAutenticacion();

$simpatizanteModel = new Simpatizante();
$campanaModel = new Campana();

// Obtener estadísticas
$filtros = [];
if ($auth->obtenerRol() === 'capturista') {
    $filtros['capturista_id'] = $auth->obtenerUsuarioId();
}

$totalSimpatizantes = $simpatizanteModel->contarTotal($filtros);
$campanas = $campanaModel->obtenerTodas(1);

// Estadísticas recientes (últimos 30 días)
$filtros['fecha_inicio'] = date('Y-m-d', strtotime('-30 days'));
$totalMes = $simpatizanteModel->contarTotal($filtros);

// Estadísticas por sección
$estatsPorSeccion = $simpatizanteModel->obtenerEstadisticasPorSeccion($filtros);

$pageTitle = 'Dashboard';
include __DIR__ . '/../app/views/layouts/header.php';
?>

<div class="container-fluid py-4">
    <!-- Bienvenida -->
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="mb-1">Bienvenido, <?php echo htmlspecialchars($_SESSION['nombre_completo']); ?></h2>
            <p class="text-muted">
                <i class="bi bi-shield-check"></i> Rol: <strong><?php echo ucfirst($_SESSION['rol']); ?></strong>
            </p>
        </div>
    </div>
    
    <!-- Tarjetas de estadísticas -->
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="text-muted mb-1 small">Total Simpatizantes</p>
                            <h3 class="mb-0"><?php echo number_format($totalSimpatizantes); ?></h3>
                        </div>
                        <div class="bg-primary bg-opacity-10 p-3 rounded">
                            <i class="bi bi-people-fill text-primary" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="text-muted mb-1 small">Últimos 30 días</p>
                            <h3 class="mb-0"><?php echo number_format($totalMes); ?></h3>
                        </div>
                        <div class="bg-success bg-opacity-10 p-3 rounded">
                            <i class="bi bi-graph-up-arrow text-success" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="text-muted mb-1 small">Campañas Activas</p>
                            <h3 class="mb-0"><?php echo count($campanas); ?></h3>
                        </div>
                        <div class="bg-warning bg-opacity-10 p-3 rounded">
                            <i class="bi bi-megaphone-fill text-warning" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="text-muted mb-1 small">Secciones Cubiertas</p>
                            <h3 class="mb-0"><?php echo count($estatsPorSeccion); ?></h3>
                        </div>
                        <div class="bg-info bg-opacity-10 p-3 rounded">
                            <i class="bi bi-geo-alt-fill text-info" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Gráficas -->
    <div class="row">
        <div class="col-lg-8 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="bi bi-bar-chart-fill me-2"></i>Simpatizantes por Sección Electoral
                    </h5>
                </div>
                <div class="card-body">
                    <canvas id="chartSecciones" height="100"></canvas>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="bi bi-list-ul me-2"></i>Top Secciones
                    </h5>
                </div>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        <?php 
                        $topSecciones = array_slice($estatsPorSeccion, 0, 5);
                        foreach ($topSecciones as $seccion): 
                        ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                                <span>Sección <?php echo htmlspecialchars($seccion['seccion_electoral']); ?></span>
                                <span class="badge bg-primary rounded-pill">
                                    <?php echo number_format($seccion['total']); ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Acciones rápidas -->
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="bi bi-lightning-fill me-2"></i>Acciones Rápidas
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <a href="<?php echo BASE_URL; ?>/public/simpatizantes/crear.php" 
                               class="btn btn-primary w-100 py-3">
                                <i class="bi bi-person-plus-fill d-block mb-2" style="font-size: 2rem;"></i>
                                Registrar Simpatizante
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="<?php echo BASE_URL; ?>/public/simpatizantes/" 
                               class="btn btn-info w-100 py-3 text-white">
                                <i class="bi bi-list-ul d-block mb-2" style="font-size: 2rem;"></i>
                                Ver Simpatizantes
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="<?php echo BASE_URL; ?>/public/reportes/" 
                               class="btn btn-success w-100 py-3">
                                <i class="bi bi-graph-up d-block mb-2" style="font-size: 2rem;"></i>
                                Reportes
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="<?php echo BASE_URL; ?>/public/mapa-calor.php" 
                               class="btn btn-warning w-100 py-3">
                                <i class="bi bi-map-fill d-block mb-2" style="font-size: 2rem;"></i>
                                Mapa de Calor
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Gráfica de secciones electorales
const ctxSecciones = document.getElementById('chartSecciones').getContext('2d');
const dataSecciones = <?php echo json_encode($estatsPorSeccion); ?>;

new Chart(ctxSecciones, {
    type: 'bar',
    data: {
        labels: dataSecciones.map(item => 'Sección ' + item.seccion_electoral),
        datasets: [{
            label: 'Simpatizantes',
            data: dataSecciones.map(item => item.total),
            backgroundColor: 'rgba(102, 126, 234, 0.8)',
            borderColor: 'rgba(102, 126, 234, 1)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1
                }
            }
        }
    }
});
</script>

<?php include __DIR__ . '/../app/views/layouts/footer.php'; ?>
