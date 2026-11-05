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

// Estadísticas por día (últimos 30 días) para gráfica de actividad
$db = Database::getInstance();
$fechaInicio = date('Y-m-d', strtotime('-30 days'));
$fechaFin = date('Y-m-d');

$whereClauses = [];
$params = [];

if ($auth->obtenerRol() === 'capturista') {
    $whereClauses[] = "capturista_id = ?";
    $params[] = $auth->obtenerUsuarioId();
}

if (!empty($whereClauses)) {
    $whereClause = 'WHERE ' . implode(' AND ', $whereClauses) . ' AND DATE(created_at) BETWEEN ? AND ?';
} else {
    $whereClause = 'WHERE DATE(created_at) BETWEEN ? AND ?';
}

$sql = "SELECT DATE(created_at) as fecha, COUNT(*) as total FROM simpatizantes $whereClause GROUP BY DATE(created_at) ORDER BY fecha ASC";
$params[] = $fechaInicio;
$params[] = $fechaFin;

$estatsPorDia = $db->query($sql, $params);

$pageTitle = 'Dashboard';
include __DIR__ . '/../app/views/layouts/header.php';
?>

<?php
$meses = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
$dias  = ['domingo','lunes','martes','miércoles','jueves','viernes','sábado'];
$fechaHoy = ucfirst($dias[(int)date('w')]) . ' ' . date('j') . ' de ' . $meses[(int)date('n') - 1] . ' de ' . date('Y');
$pctMes = $totalSimpatizantes > 0 ? round(($totalMes / $totalSimpatizantes) * 100, 1) : 0;
$topSecciones = array_slice($estatsPorSeccion, 0, 8);
$maxSeccion = 0;
foreach ($topSecciones as $s) { $maxSeccion = max($maxSeccion, (int)$s['total']); }
$promSeccion = count($estatsPorSeccion) > 0 ? round($totalSimpatizantes / count($estatsPorSeccion), 1) : 0;
?>
<div class="container-fluid p-0">
    <div class="page-head">
        <div>
            <div class="eyebrow">Panel de control</div>
            <h1>Bienvenido, <?php echo htmlspecialchars($_SESSION['nombre_completo']); ?></h1>
            <div class="meta">
                <span><i class="bi bi-calendar3"></i><?php echo $fechaHoy; ?></span>
                <span><i class="bi bi-shield-check"></i>Rol: <?php echo htmlspecialchars(ucfirst($_SESSION['rol'])); ?></span>
            </div>
        </div>
        <a href="<?php echo BASE_URL; ?>/public/simpatizantes/crear.php" class="btn btn-primary">
            <i class="bi bi-person-plus-fill me-2"></i>Registrar simpatizante
        </a>
    </div>

    <!-- Indicadores -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card kpi">
                <i class="bi bi-people ico"></i>
                <div class="label">Total de simpatizantes</div>
                <div class="value" data-count="<?php echo (int)$totalSimpatizantes; ?>"><?php echo number_format($totalSimpatizantes); ?></div>
                <div class="hint">Registros en el sistema</div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card kpi">
                <i class="bi bi-graph-up-arrow ico"></i>
                <div class="label">Últimos 30 días</div>
                <div class="value" data-count="<?php echo (int)$totalMes; ?>"><?php echo number_format($totalMes); ?></div>
                <div class="hint"><?php echo $pctMes; ?>% del total acumulado</div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card kpi">
                <i class="bi bi-megaphone ico"></i>
                <div class="label">Campañas activas</div>
                <div class="value" data-count="<?php echo count($campanas); ?>"><?php echo count($campanas); ?></div>
                <div class="hint">En operación actualmente</div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card kpi">
                <i class="bi bi-geo-alt ico"></i>
                <div class="label">Secciones cubiertas</div>
                <div class="value" data-count="<?php echo count($estatsPorSeccion); ?>"><?php echo count($estatsPorSeccion); ?></div>
                <div class="hint">Promedio de <?php echo $promSeccion; ?> por sección</div>
            </div>
        </div>
    </div>

    <!-- Actividad -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-activity me-2"></i>Actividad de registros</h5>
            <span class="text-muted small">Últimos 30 días</span>
        </div>
        <div class="card-body">
            <div class="chart-box sm"><canvas id="chartActividad"></canvas></div>
        </div>
    </div>

    <!-- Secciones -->
    <div class="row g-3 mb-4">
        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-bar-chart me-2"></i>Simpatizantes por sección electoral</h5>
                </div>
                <div class="card-body">
                    <div class="chart-box"><canvas id="chartSecciones"></canvas></div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header"><h5 class="mb-0"><i class="bi bi-trophy me-2"></i>Secciones con mayor presencia</h5></div>
                <div class="card-body">
                    <?php if (empty($topSecciones)): ?>
                        <p class="text-muted mb-0">Aún no hay registros por sección.</p>
                    <?php endif; ?>
                    <?php foreach ($topSecciones as $i => $seccion): ?>
                        <div class="rank-row">
                            <span class="pos"><?php echo $i + 1; ?></span>
                            <div>
                                <div class="name">Sección <?php echo htmlspecialchars($seccion['seccion_electoral']); ?></div>
                                <div class="bar"><span style="width: <?php echo $maxSeccion > 0 ? round(($seccion['total'] / $maxSeccion) * 100) : 0; ?>%"></span></div>
                            </div>
                            <span class="num"><?php echo number_format($seccion['total']); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Accesos -->
    <div class="card">
        <div class="card-header"><h5 class="mb-0"><i class="bi bi-grid me-2"></i>Accesos directos</h5></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-xl-3 col-md-6">
                    <a class="quick" href="<?php echo BASE_URL; ?>/public/simpatizantes/crear.php">
                        <span class="qi"><i class="bi bi-person-plus"></i></span>
                        <span><b>Registrar simpatizante</b><small>Captura con INE y firma</small></span>
                        <i class="bi bi-chevron-right go"></i>
                    </a>
                </div>
                <div class="col-xl-3 col-md-6">
                    <a class="quick" href="<?php echo BASE_URL; ?>/public/simpatizantes/">
                        <span class="qi"><i class="bi bi-list-ul"></i></span>
                        <span><b>Padrón de simpatizantes</b><small>Consultar y filtrar</small></span>
                        <i class="bi bi-chevron-right go"></i>
                    </a>
                </div>
                <div class="col-xl-3 col-md-6">
                    <a class="quick" href="<?php echo BASE_URL; ?>/public/reportes/">
                        <span class="qi"><i class="bi bi-file-earmark-bar-graph"></i></span>
                        <span><b>Reportes</b><small>Análisis y exportación</small></span>
                        <i class="bi bi-chevron-right go"></i>
                    </a>
                </div>
                <div class="col-xl-3 col-md-6">
                    <a class="quick" href="<?php echo BASE_URL; ?>/public/mapa-calor.php">
                        <span class="qi"><i class="bi bi-map"></i></span>
                        <span><b>Mapa de calor</b><small>Distribución territorial</small></span>
                        <i class="bi bi-chevron-right go"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo BASE_URL; ?>/public/vendor/chartjs/chart.umd.js"></script>
<script>
Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
Chart.defaults.color = '#6e655e';
const NAVY = '#e06a00', GRID = '#efeae4';

// Secciones electorales
const dataSecciones = <?php echo json_encode($estatsPorSeccion); ?>;
new Chart(document.getElementById('chartSecciones'), {
    type: 'bar',
    data: {
        labels: dataSecciones.map(i => i.seccion_electoral),
        datasets: [{ label: 'Simpatizantes', data: dataSecciones.map(i => i.total), backgroundColor: NAVY, borderRadius: 3, maxBarThickness: 36 }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false }, tooltip: { callbacks: { title: c => 'Sección ' + c[0].label } } },
        scales: {
            y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: GRID }, border: { display: false } },
            x: { grid: { display: false }, title: { display: true, text: 'Sección electoral' } }
        }
    }
});

// Actividad últimos 30 días
const dataActividad = <?php echo json_encode($estatsPorDia); ?>;
const registros = {};
dataActividad.forEach(i => { registros[i.fecha] = parseInt(i.total); });
const fechas = [];
for (let i = 29; i >= 0; i--) {
    const f = new Date(); f.setDate(f.getDate() - i);
    fechas.push(f.toISOString().split('T')[0]);
}
const valores = fechas.map(f => registros[f] || 0);
const etiquetas = fechas.map(f => { const d = new Date(f + 'T00:00:00'); return d.getDate() + '/' + (d.getMonth() + 1); });
const ctxA = document.getElementById('chartActividad').getContext('2d');
const grad = ctxA.createLinearGradient(0, 0, 0, 260);
grad.addColorStop(0, 'rgba(255,128,0,.30)');
grad.addColorStop(1, 'rgba(224,106,0,0)');
new Chart(ctxA, {
    type: 'line',
    data: { labels: etiquetas, datasets: [{ label: 'Registros por día', data: valores, borderColor: NAVY, backgroundColor: grad, borderWidth: 2, fill: true, tension: .3, pointRadius: 0, pointHoverRadius: 5, pointHoverBackgroundColor: NAVY }] },
    options: {
        responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: { display: false },
            tooltip: { callbacks: {
                title: c => { const d = new Date(fechas[c[0].dataIndex] + 'T00:00:00'); return ['Dom','Lun','Mar','Mié','Jue','Vie','Sáb'][d.getDay()] + ' ' + d.getDate() + '/' + (d.getMonth() + 1) + '/' + d.getFullYear(); },
                label: c => 'Registros: ' + c.parsed.y
            } }
        },
        scales: {
            y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: GRID }, border: { display: false } },
            x: { grid: { display: false }, ticks: { maxTicksLimit: 10, maxRotation: 0 } }
        }
    }
});

// Conteo animado de los indicadores
document.querySelectorAll('[data-count]').forEach(el => {
    const target = parseInt(el.dataset.count, 10);
    if (!target || matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    const t0 = performance.now(), dur = 900;
    (function tick(t) {
        const p = Math.min((t - t0) / dur, 1), e = 1 - Math.pow(1 - p, 3);
        el.textContent = Math.round(target * e).toLocaleString('es-MX');
        if (p < 1) requestAnimationFrame(tick);
    })(t0);
});
</script>

<?php include __DIR__ . '/../app/views/layouts/footer.php'; ?>
