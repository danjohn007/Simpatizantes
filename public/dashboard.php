<?php
/**
 * Dashboard Principal
 * Sistema de Validación de Simpatizantes
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../app/controllers/AuthController.php';
require_once __DIR__ . '/../app/models/Campana.php';
require_once __DIR__ . '/../app/services/AnaliticaService.php';

$auth = new AuthController();
$auth->requiereAutenticacion();

$campanaModel = new Campana();
$analitica = new AnaliticaService();

// Todo se limita al alcance del usuario: su campaña (o solo lo suyo si es capturista)
$fechaInicio = date('Y-m-d', strtotime('-29 days'));
$fechaFin = date('Y-m-d');
$fTotal = AnaliticaService::alcance($auth, []);
$f30 = AnaliticaService::alcance($auth, ['fecha_inicio' => $fechaInicio, 'fecha_fin' => $fechaFin]);

$resumenTotal = $analitica->resumen($fTotal);
$comp = $analitica->comparativo($f30);            // últimos 30 días frente a los 30 anteriores
$totalSimpatizantes = $resumenTotal['total'];
$totalMes = $comp['actual']['total'];
$estatsPorSeccion = $analitica->porSeccion($fTotal, 25);
$estatsPorDia = $analitica->porDia($f30);
$estatus = $analitica->estatus($fTotal);

$campanas = $campanaModel->obtenerTodas(1) ?: [];
if (!$auth->puedeVerTodasLasCampanas()) {
    $mia = (int)$auth->obtenerCampanaId();
    $campanas = array_values(array_filter($campanas, function ($c) use ($mia) { return (int)$c['id'] === $mia; }));
}

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
$seccionesCubiertas = $resumenTotal['secciones'];
$promSeccion = $seccionesCubiertas > 0 ? round($totalSimpatizantes / $seccionesCubiertas, 1) : 0;
$varMes = $comp['var_total'];
$pctValidados = $resumenTotal['pct_validados'];
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
                <div class="hint"><?php if ($varMes === null): ?><?php echo $pctMes; ?>% del total acumulado<?php else: ?><span style="color:<?php echo $varMes >= 0 ? '#2f6b4a' : '#8a2f25'; ?>;font-weight:600"><i class="bi bi-arrow-<?php echo $varMes >= 0 ? 'up' : 'down'; ?>-right"></i> <?php echo ($varMes >= 0 ? '+' : '') . number_format($varMes, 1); ?>%</span> vs los 30 días anteriores<?php endif; ?></div>
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
                <div class="value" data-count="<?php echo (int)$seccionesCubiertas; ?>"><?php echo number_format($seccionesCubiertas); ?></div>
                <div class="hint">Promedio de <?php echo $promSeccion; ?> por sección</div>
            </div>
        </div>
    </div>

    <!-- Validación -->
    <?php $totEst = max(1, array_sum($estatus)); ?>
    <div class="card mb-4">
        <div class="card-body">
            <div class="d-flex justify-content-between flex-wrap gap-2 mb-2">
                <strong><i class="bi bi-patch-check me-2"></i>Estado de validación</strong>
                <a href="<?php echo BASE_URL; ?>/public/simpatizantes/?estatus=pendiente" class="small">Ver pendientes</a>
            </div>
            <div class="d-flex rounded overflow-hidden mb-2" style="height:14px;background:#ece6df" role="img" aria-label="Distribución por estado de validación">
                <?php foreach ([['validado', '#2f6b4a'], ['pendiente', '#ff8000'], ['rechazado', '#8a2f25']] as $e): ?>
                    <div style="width:<?php echo round(100 * ($estatus[$e[0]] ?? 0) / $totEst, 2); ?>%;background:<?php echo $e[1]; ?>"></div>
                <?php endforeach; ?>
            </div>
            <div class="d-flex flex-wrap gap-3 small">
                <span><i class="bi bi-circle-fill" style="color:#2f6b4a"></i> Validados <strong><?php echo number_format($estatus['validado'] ?? 0); ?></strong> (<?php echo $pctValidados; ?>%)</span>
                <span><i class="bi bi-circle-fill" style="color:#ff8000"></i> Pendientes <strong><?php echo number_format($estatus['pendiente'] ?? 0); ?></strong></span>
                <span><i class="bi bi-circle-fill" style="color:#8a2f25"></i> Rechazados <strong><?php echo number_format($estatus['rechazado'] ?? 0); ?></strong></span>
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
                    <h5 class="mb-0"><i class="bi bi-bar-chart me-2"></i>Simpatizantes por sección electoral <span class="text-muted small fw-normal">· las 25 con más registros</span></h5>
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
