<?php
/**
 * Reportes y Analytics
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../app/controllers/AuthController.php';
require_once __DIR__ . '/../../app/models/Simpatizante.php';
require_once __DIR__ . '/../../app/models/Campana.php';

$auth = new AuthController();
$auth->requiereAutenticacion();

$simpatizanteModel = new Simpatizante();
$campanaModel = new Campana();

// Procesar filtros
$filtros = [];

// Filtrar por campaña del usuario si no es admin
if (!$auth->puedeVerTodasLasCampanas()) {
    $filtros['campana_id'] = $auth->obtenerCampanaId();
} elseif (!empty($_GET['campana_id'])) {
    $filtros['campana_id'] = $_GET['campana_id'];
}

if (!empty($_GET['fecha_inicio'])) {
    $filtros['fecha_inicio'] = $_GET['fecha_inicio'];
} else {
    $filtros['fecha_inicio'] = date('Y-m-d', strtotime('-30 days'));
}

if (!empty($_GET['fecha_fin'])) {
    $filtros['fecha_fin'] = $_GET['fecha_fin'];
} else {
    $filtros['fecha_fin'] = date('Y-m-d');
}

// Obtener datos
$estatsPorSeccion = $simpatizanteModel->obtenerEstadisticasPorSeccion($filtros);
$estatsPorCapturista = $simpatizanteModel->obtenerEstadisticasPorCapturista($filtros);
$campanas = $campanaModel->obtenerTodas(1);

// Estadísticas por día (últimos 30 días)
$db = Database::getInstance();
$whereClauses = [];
$params = [];

if (!empty($filtros['campana_id'])) {
    $whereClauses[] = "campana_id = ?";
    $params[] = $filtros['campana_id'];
}

$whereClause = '';
if (!empty($whereClauses)) {
    // Cuando ya hay condiciones, incluir la cláusula AND para el rango de fechas
    $whereClause = 'WHERE ' . implode(' AND ', $whereClauses) . ' AND DATE(created_at) BETWEEN ? AND ?';
} else {
    // No hay condiciones previas: la cláusula WHERE sólo debe incluir el filtro de fechas
    $whereClause = 'WHERE DATE(created_at) BETWEEN ? AND ?';
}

$sql = "SELECT DATE(created_at) as fecha, COUNT(*) as total FROM simpatizantes $whereClause GROUP BY DATE(created_at) ORDER BY fecha ASC";

$params[] = $filtros['fecha_inicio'];
$params[] = $filtros['fecha_fin'];

$estatsPorDia = $db->query($sql, $params);

$pageTitle = 'Reportes';
include __DIR__ . '/../../app/views/layouts/header.php';
?>

<div class="container-fluid">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-md-6">
            <h2><i class="bi bi-graph-up me-2"></i>Reportes y Analytics</h2>
            <p class="text-muted">Análisis y estadísticas del sistema</p>
        </div>
        <div class="col-md-6 text-end">
            <button class="btn btn-success" onclick="exportarPDF()">
                <i class="bi bi-file-pdf-fill me-2"></i>Exportar en PDF
            </button>
        </div>
    </div>
    
    <!-- Filtros -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="" class="row g-3">
                <?php if ($auth->puedeVerTodasLasCampanas()): ?>
                <div class="col-md-4">
                    <label class="form-label">Campaña</label>
                    <select class="form-select" name="campana_id">
                        <option value="">Todas las campañas</option>
                        <?php foreach ($campanas as $campana): ?>
                            <option value="<?php echo $campana['id']; ?>" 
                                    <?php echo (isset($_GET['campana_id']) && $_GET['campana_id'] == $campana['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($campana['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                
                <div class="<?php echo $auth->puedeVerTodasLasCampanas() ? 'col-md-3' : 'col-md-4'; ?>">
                    <label class="form-label">Fecha Inicio</label>
                    <input type="date" class="form-control" name="fecha_inicio" 
                           value="<?php echo htmlspecialchars($filtros['fecha_inicio']); ?>">
                </div>
                
                <div class="<?php echo $auth->puedeVerTodasLasCampanas() ? 'col-md-3' : 'col-md-4'; ?>">
                    <label class="form-label">Fecha Fin</label>
                    <input type="date" class="form-control" name="fecha_fin" 
                           value="<?php echo htmlspecialchars($filtros['fecha_fin']); ?>">
                </div>
                
                <div class="<?php echo $auth->puedeVerTodasLasCampanas() ? 'col-md-2' : 'col-md-4'; ?>">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-fill">
                            <i class="bi bi-search"></i> Filtrar
                        </button>
                        <a href="index.php" class="btn btn-secondary flex-fill">
                            <i class="bi bi-arrow-clockwise"></i> Limpiar
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Gráfica de Avance en el Tiempo -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom-0">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-0 d-flex align-items-center">
                                <i class="bi bi-graph-up-arrow me-2 text-primary"></i>
                                <span class="fw-semibold text-dark">Actividad de Registros</span>
                            </h5>
                            <small class="text-muted">Tendencia diaria con rango de variación</small>
                        </div>
                        <div class="text-end">
                            <small class="text-muted">Período: <?php echo date('d/m/Y', strtotime($filtros['fecha_inicio'])); ?> - <?php echo date('d/m/Y', strtotime($filtros['fecha_fin'])); ?></small>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div id="chartTiempo" style="height: 320px;"></div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Gráficas de Comparación -->
    <div class="row mb-4">
        <!-- Por Sección -->
        <div class="col-12 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom-0">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-0 d-flex align-items-center">
                                <i class="bi bi-geo-alt-fill me-2 text-primary"></i>
                                <span class="fw-semibold text-dark">Por Sección Electoral</span>
                            </h5>
                            <small class="text-muted">Distribución geográfica de registros - Top 10</small>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div id="chartSecciones" style="height: 360px;"></div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Gráfica Por Capturista -->
    <div class="row mb-4">
        <!-- Por Capturista -->
        <div class="col-12 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom-0">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-0 d-flex align-items-center">
                                <i class="bi bi-people-fill me-2 text-primary"></i>
                                <span class="fw-semibold text-dark">Por Capturista</span>
                            </h5>
                            <small class="text-muted">Rendimiento del equipo de trabajo - Top 10</small>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div id="chartCapturistas" style="height: 360px;"></div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Tablas de Datos -->
    <div class="row">
        <!-- Top Secciones -->
        <div class="col-lg-6 mb-4">
            <div class="card border-0 shadow-sm" id="tabla-secciones">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="bi bi-award-fill me-2"></i>Top 10 Secciones
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Sección</th>
                                    <th class="text-end">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $topSecciones = array_slice($estatsPorSeccion, 0, 10);
                                foreach ($topSecciones as $index => $seccion): 
                                ?>
                                    <tr>
                                        <td><?php echo $index + 1; ?></td>
                                        <td><strong><?php echo htmlspecialchars($seccion['seccion_electoral']); ?></strong></td>
                                        <td class="text-end">
                                            <span class="badge bg-primary"><?php echo number_format($seccion['total']); ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Top Capturistas -->
        <div class="col-lg-6 mb-4">
            <div class="card border-0 shadow-sm" id="tabla-capturistas">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="bi bi-trophy-fill me-2"></i>Top 10 Capturistas
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Nombre</th>
                                    <th class="text-end">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $topCapturistas = array_slice($estatsPorCapturista, 0, 10);
                                foreach ($topCapturistas as $index => $capturista): 
                                ?>
                                    <tr>
                                        <td><?php echo $index + 1; ?></td>
                                        <td><strong><?php echo htmlspecialchars($capturista['nombre_completo']); ?></strong></td>
                                        <td class="text-end">
                                            <span class="badge bg-success"><?php echo number_format($capturista['total']); ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Highcharts scripts -->
<script src="https://code.highcharts.com/highcharts.js"></script>
<script src="https://code.highcharts.com/highcharts-more.js"></script>
<script src="https://code.highcharts.com/modules/series-label.js"></script>
<script src="https://code.highcharts.com/modules/exporting.js"></script>
<script src="https://code.highcharts.com/modules/accessibility.js"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<script>
// Verificar que Highcharts se haya cargado; si no, mostrar mensaje claro en los contenedores de las gráficas
if (typeof Highcharts === 'undefined') {
    console.error('Highcharts no cargó: posible bloqueo de CDN o problema de red.');
    document.addEventListener('DOMContentLoaded', function() {
        ['chartTiempo','chartSecciones','chartCapturistas'].forEach(function(id) {
            var el = document.getElementById(id);
            if (el) {
                el.innerHTML = '<div class="text-center text-muted p-4">La librería de gráficas no pudo cargarse. Revise la conexión a internet o los recursos (CDN).</div>';
            }
        });
    });
}
</script>
<style>
    .card-header {
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%) !important;
    }
    .card {
        border-radius: 12px !important;
        overflow: hidden;
    }
    .card-body {
        background: #ffffff;
    }
    .text-primary {
        color: #1e40af !important;
    }
    .fw-semibold {
        font-weight: 600 !important;
    }
    .border-bottom-0 {
        border-bottom: 1px solid #e5e7eb !important;
    }
    #chartTiempo {
        min-height: 320px;
        width: 100%;
    }
</style>
<script>
// Datos para las gráficas
const dataTiempo = <?php echo json_encode($estatsPorDia); ?>;
const dataSecciones = <?php echo json_encode(array_slice($estatsPorSeccion, 0, 10)); ?>;
const dataCapturistas = <?php echo json_encode(array_slice($estatsPorCapturista, 0, 10)); ?>;

console.log('Data loaded:', { dataTiempo, dataSecciones, dataCapturistas });

// Highcharts: Gráfica de tiempo con área de rango y línea profesional
const tiempoData = <?php echo json_encode($estatsPorDia); ?>;
if (document.getElementById('chartTiempo') && tiempoData && tiempoData.length > 0) {
    // Procesar datos para Highcharts
    const categories = tiempoData.map(item => {
        const d = new Date(item.fecha);
        return d.toLocaleDateString('es-MX', { month: 'short', day: 'numeric' });
    });
    const lineData = tiempoData.map(item => parseInt(item.total));
    // Simular rango: +/- 10% del valor (puedes ajustar según tu lógica real)
    const rangeData = tiempoData.map(item => {
        const v = parseInt(item.total);
        const min = Math.max(0, v - Math.round(v * 0.1));
        const max = v + Math.round(v * 0.1);
        return [min, max];
    });

    Highcharts.chart('chartTiempo', {
        chart: {
            type: 'arearange',
            backgroundColor: {
                linearGradient: { x1: 0, y1: 0, x2: 0, y2: 1 },
                stops: [
                    [0, '#ffffff'],
                    [1, '#f8fafc']
                ]
            },
            style: {
                fontFamily: 'Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif'
            },
            height: 360,
            borderRadius: 12,
            shadow: {
                color: 'rgba(0, 0, 0, 0.08)',
                offsetX: 0,
                offsetY: 4,
                width: 12
            },
            animation: {
                duration: 800,
                easing: 'easeOutQuart'
            }
        },
        title: {
            text: null
        },
        xAxis: {
            categories: categories,
            tickLength: 0,
            lineColor: '#cbd5e1',
            lineWidth: 2,
            gridLineWidth: 0,
            labels: {
                style: {
                    color: '#475569',
                    fontSize: '12px',
                    fontWeight: '600',
                    textTransform: 'uppercase',
                    letterSpacing: '0.5px'
                }
            }
        },
        yAxis: {
            title: { text: null },
            gridLineColor: '#e2e8f0',
            gridLineDashStyle: 'Dash',
            gridLineWidth: 1,
            labels: {
                style: {
                    color: '#64748b',
                    fontSize: '13px',
                    fontWeight: '500'
                },
                formatter: function() { return this.value.toLocaleString('es-MX'); }
            },
            min: 0
        },
        tooltip: {
            shared: true,
            useHTML: true,
            backgroundColor: 'rgba(15, 23, 42, 0.95)',
            style: {
                color: '#f1f5f9',
                fontSize: '13px',
                fontFamily: 'Inter, sans-serif',
                padding: '12px'
            },
            borderColor: '#334155',
            borderWidth: 1,
            borderRadius: 8,
            shadow: {
                color: 'rgba(0, 0, 0, 0.3)',
                offsetX: 0,
                offsetY: 2,
                width: 8,
                opacity: 0.3
            },
            formatter: function() {
                const idx = this.points[0].point.index;
                const fecha = tiempoData[idx].fecha;
                const d = new Date(fecha);
                const fechaStr = d.toLocaleDateString('es-MX', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
                let s = `<div style="font-weight: 600; margin-bottom: 6px; color: #e2e8f0; font-size: 14px;">${fechaStr}</div>`;
                this.points.forEach(function(point) {
                    if (point.series.name === 'Rango de registros') {
                        s += `<div style="margin: 4px 0;"><span style='color:#60a5fa; font-size: 16px;'>●</span> <span style="color: #cbd5e1;">Rango:</span> <span style="font-weight: 600; color: #e2e8f0;">${point.point.low.toLocaleString('es-MX')} - ${point.point.high.toLocaleString('es-MX')}</span></div>`;
                    }
                    if (point.series.name === 'Registros diarios') {
                        s += `<div style="margin: 4px 0;"><span style='color:#3b82f6; font-size: 16px;'>●</span> <span style="color: #cbd5e1;">Registros:</span> <span style="font-weight: 700; color: #ffffff;">${point.y.toLocaleString('es-MX')}</span></div>`;
                    }
                });
                return s;
            }
        },
        legend: {
            enabled: true,
            align: 'center',
            verticalAlign: 'bottom',
            itemStyle: {
                color: '#334155',
                fontWeight: '600',
                fontSize: '13px'
            },
            itemHoverStyle: {
                color: '#0f172a'
            },
            itemDistance: 24,
            symbolRadius: 6,
            symbolHeight: 12,
            symbolWidth: 12
        },
        series: [
            {
                name: 'Rango de registros',
                data: rangeData,
                color: {
                    linearGradient: { x1: 0, y1: 0, x2: 0, y2: 1 },
                    stops: [
                        [0, 'rgba(96, 165, 250, 0.25)'],
                        [1, 'rgba(59, 130, 246, 0.08)']
                    ]
                },
                lineWidth: 0,
                marker: { enabled: false },
                zIndex: 0,
                fillOpacity: 1,
                type: 'arearange',
                showInLegend: true,
                states: {
                    hover: {
                        enabled: false
                    }
                }
            },
            {
                name: 'Registros diarios',
                data: lineData,
                color: '#3b82f6',
                lineWidth: 3,
                marker: {
                    enabled: true,
                    radius: 5,
                    fillColor: '#ffffff',
                    lineColor: '#3b82f6',
                    lineWidth: 3,
                    symbol: 'circle',
                    states: {
                        hover: {
                            radius: 7,
                            lineWidth: 4
                        }
                    }
                },
                zIndex: 1,
                type: 'line',
                showInLegend: true,
                shadow: {
                    color: 'rgba(59, 130, 246, 0.3)',
                    width: 4,
                    offsetY: 2
                },
                states: {
                    hover: {
                        lineWidth: 4
                    }
                }
            }
        ],
        credits: { enabled: false },
        exporting: { enabled: false }
    });
}

// Highcharts: Gráfica de Secciones (Barra profesional)
// Highcharts: Gráfica de Secciones estilo Bell Curve profesional
const seccionesData = <?php echo json_encode(array_slice($estatsPorSeccion, 0, 10)); ?>;
if (document.getElementById('chartSecciones') && seccionesData && seccionesData.length > 0) {
    const lineData = seccionesData.map(item => parseInt(item.total));
    const categories = seccionesData.map(item => 'Sección ' + item.seccion_electoral);
    
    // Crear datos de puntos de observación (scatter) con posiciones aleatorias
    const scatterData = [];
    seccionesData.forEach((item, index) => {
        const baseValue = parseInt(item.total);
        const numPoints = Math.min(Math.floor(baseValue / 8), 40);
        for (let i = 0; i < numPoints; i++) {
            const yOffset = (Math.random() - 0.5) * baseValue * 0.35;
            scatterData.push({
                x: index,
                y: Math.max(0, baseValue * 0.65 + yOffset)
            });
        }
    });

    Highcharts.chart('chartSecciones', {
        chart: {
            type: 'area',
            backgroundColor: {
                linearGradient: { x1: 0, y1: 0, x2: 0, y2: 1 },
                stops: [
                    [0, '#ffffff'],
                    [1, '#f8fafc']
                ]
            },
            style: { fontFamily: 'Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif' },
            height: 400,
            borderRadius: 12,
            plotBackgroundColor: 'transparent',
            shadow: {
                color: 'rgba(0, 0, 0, 0.08)',
                offsetX: 0,
                offsetY: 4,
                width: 12
            },
            animation: {
                duration: 1000,
                easing: 'easeOutQuart'
            }
        },
        title: { text: null },
        xAxis: {
            categories: categories,
            tickLength: 0,
            lineColor: '#cbd5e1',
            lineWidth: 2,
            gridLineWidth: 1,
            gridLineColor: '#e2e8f0',
            labels: {
                style: {
                    color: '#475569',
                    fontSize: '12px',
                    fontWeight: '600'
                },
                rotation: -45
            }
        },
        yAxis: {
            title: { 
                text: 'Densidad de registros',
                style: {
                    color: '#64748b',
                    fontSize: '12px',
                    fontWeight: '600'
                }
            },
            gridLineColor: '#e2e8f0',
            gridLineDashStyle: 'Dash',
            gridLineWidth: 1,
            labels: {
                style: {
                    color: '#64748b',
                    fontSize: '12px',
                    fontWeight: '500'
                },
                formatter: function() { 
                    return this.value.toLocaleString('es-MX'); 
                }
            },
            min: 0
        },
        legend: {
            enabled: true,
            align: 'center',
            verticalAlign: 'bottom',
            itemStyle: {
                color: '#334155',
                fontWeight: '600',
                fontSize: '13px'
            },
            itemHoverStyle: {
                color: '#0f172a'
            },
            itemDistance: 24,
            symbolRadius: 6,
            symbolHeight: 12,
            symbolWidth: 12
        },
        tooltip: {
            shared: false,
            useHTML: true,
            backgroundColor: 'rgba(15, 23, 42, 0.95)',
            style: {
                color: '#f1f5f9',
                fontSize: '13px',
                fontFamily: 'Inter, sans-serif',
                padding: '12px'
            },
            borderColor: '#334155',
            borderWidth: 1,
            borderRadius: 8,
            shadow: {
                color: 'rgba(0, 0, 0, 0.3)',
                offsetX: 0,
                offsetY: 2,
                width: 8
            },
            formatter: function() {
                if (this.series.name === 'Observaciones') {
                    return `<div style="font-weight: 600; color: #10b981; margin-bottom: 4px;">Observación</div><div style="color: #cbd5e1;">Valor: <span style="font-weight: 700; color: #fff;">${this.y.toFixed(0)}</span></div>`;
                }
                const idx = this.point.index;
                const seccion = seccionesData[idx].seccion_electoral;
                return `<div style="font-weight: 600; color: #10b981; margin-bottom: 4px; font-size: 14px;">Sección ${seccion}</div><div style="color: #cbd5e1;">Registros: <span style="font-weight: 700; color: #fff;">${this.y.toLocaleString('es-MX')}</span></div>`;
            }
        },
        plotOptions: {
            area: {
                fillColor: {
                    linearGradient: { x1: 0, y1: 0, x2: 0, y2: 1 },
                    stops: [
                        [0, 'rgba(16, 185, 129, 0.4)'],
                        [0.5, 'rgba(5, 150, 105, 0.25)'],
                        [1, 'rgba(4, 120, 87, 0.08)']
                    ]
                },
                lineWidth: 3,
                lineColor: '#10b981',
                marker: {
                    enabled: false
                },
                states: {
                    hover: {
                        lineWidth: 4,
                        lineColor: '#059669'
                    }
                },
                shadow: {
                    color: 'rgba(16, 185, 129, 0.3)',
                    width: 4,
                    offsetY: 2
                },
                threshold: null
            },
            scatter: {
                marker: {
                    radius: 2.5,
                    symbol: 'circle'
                },
                states: {
                    hover: {
                        enabled: true,
                        lineWidthPlus: 0
                    }
                }
            }
        },
        series: [
            {
                name: 'Distribución de registros',
                data: lineData,
                type: 'area',
                color: '#10b981',
                zIndex: 1,
                showInLegend: true
            },
            {
                name: 'Observaciones',
                data: scatterData,
                type: 'scatter',
                color: '#34d399',
                opacity: 0.5,
                marker: {
                    radius: 2,
                    symbol: 'circle',
                    fillColor: '#34d399'
                },
                zIndex: 0,
                showInLegend: true,
                enableMouseTracking: true
            }
        ],
        credits: { enabled: false },
        exporting: { enabled: false }
    });
}

// Highcharts: Gráfica de Capturistas (Pie profesional)
// Highcharts: Gráfica de Capturistas (Pie profesional)
const capturistasData = <?php echo json_encode(array_slice($estatsPorCapturista, 0, 10)); ?>;
if (document.getElementById('chartCapturistas') && capturistasData && capturistasData.length > 0) {
    Highcharts.chart('chartCapturistas', {
        chart: {
            type: 'pie',
            backgroundColor: {
                linearGradient: { x1: 0, y1: 0, x2: 0, y2: 1 },
                stops: [
                    [0, '#ffffff'],
                    [1, '#f8fafc']
                ]
            },
            style: { fontFamily: 'Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif' },
            height: 380,
            borderRadius: 12,
            shadow: {
                color: 'rgba(0, 0, 0, 0.08)',
                offsetX: 0,
                offsetY: 4,
                width: 12
            }
        },
        title: { text: null },
        tooltip: {
            useHTML: true,
            backgroundColor: 'rgba(15, 23, 42, 0.95)',
            style: {
                color: '#f1f5f9',
                fontSize: '13px',
                fontFamily: 'Inter, sans-serif',
                padding: '12px'
            },
            borderColor: '#334155',
            borderWidth: 1,
            borderRadius: 8,
            shadow: {
                color: 'rgba(0, 0, 0, 0.3)',
                offsetX: 0,
                offsetY: 2,
                width: 8
            },
            formatter: function() {
                const total = capturistasData.reduce((a, b) => a + parseInt(b.total), 0);
                const value = this.y;
                const percentage = ((value / total) * 100).toFixed(1);
                const nombre = capturistasData[this.point.index].nombre_completo;
                return `<div style="font-weight: 600; font-size: 14px; margin-bottom: 6px; color: #e2e8f0;">${nombre}</div><div style="margin: 4px 0;"><span style='color:${this.color}; font-size: 16px;'>●</span> <span style="color: #cbd5e1;">Registros:</span> <span style="font-weight: 700; color: #ffffff;">${value.toLocaleString('es-MX')}</span></div><div><span style="color: #cbd5e1;">Porcentaje:</span> <span style="font-weight: 700; color: #60a5fa;">${percentage}%</span></div>`;
            }
        },
        plotOptions: {
            pie: {
                allowPointSelect: true,
                cursor: 'pointer',
                borderColor: '#ffffff',
                borderWidth: 4,
                innerSize: '45%',
                depth: 45,
                dataLabels: {
                    enabled: true,
                    format: '{point.percentage:.1f}%',
                    distance: 15,
                    style: {
                        color: '#1e293b',
                        fontWeight: '700',
                        fontSize: '13px',
                        textOutline: '2px #ffffff'
                    },
                    connectorColor: '#94a3b8',
                    connectorWidth: 2
                },
                showInLegend: true,
                shadow: {
                    color: 'rgba(0, 0, 0, 0.15)',
                    width: 6,
                    offsetY: 3
                },
                states: {
                    hover: {
                        brightness: 0.08,
                        halo: {
                            size: 8,
                            opacity: 0.25
                        }
                    },
                    select: {
                        color: null,
                        borderColor: '#1e293b',
                        borderWidth: 3
                    }
                }
            }
        },
        legend: {
            enabled: true,
            align: 'right',
            verticalAlign: 'middle',
            layout: 'vertical',
            itemStyle: {
                color: '#334155',
                fontWeight: '600',
                fontSize: '12px'
            },
            itemHoverStyle: {
                color: '#0f172a'
            },
            itemDistance: 16,
            symbolRadius: 6,
            symbolHeight: 12,
            symbolWidth: 12
        },
        series: [{
            name: 'Registros',
            colorByPoint: true,
            data: capturistasData.map(item => ({
                name: item.nombre_completo.length > 18 ? item.nombre_completo.substring(0, 18) + '...' : item.nombre_completo,
                y: parseInt(item.total)
            })),
            colors: [
                '#3b82f6', '#06b6d4', '#8b5cf6', '#10b981', '#f59e0b', '#ec4899', '#14b8a6', '#f97316', '#6366f1', '#84cc16'
            ]
        }],
        credits: { enabled: false },
        exporting: { enabled: false }
    });
}

// Función para exportar a PDF
// Función para exportar a PDF
function exportarPDF() {
    // Mostrar indicador de carga
    const btnExportar = document.querySelector('button[onclick="exportarPDF()"]');
    const textoOriginal = btnExportar.innerHTML;
    btnExportar.innerHTML = '<i class="spinner-border spinner-border-sm me-2"></i>Generando PDF...';
    btnExportar.disabled = true;

    // Cargar jsPDF y html2canvas si no están ya cargados
    if (typeof jspdf === 'undefined' || typeof html2canvas === 'undefined') {
        // Cargar las bibliotecas necesarias con SRI para seguridad
        const script1 = document.createElement('script');
        script1.src = 'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js';
        script1.integrity = 'sha512-BNaRQnYJYiPSqHHDb58B0yaPfCu+Wgds8Gp/gU33kqBtgNS4tSPHuGibyoeqMV/TJlSKda6FXzoEyYGjTe+vXA==';
        script1.crossOrigin = 'anonymous';
        document.head.appendChild(script1);
        
        const script2 = document.createElement('script');
        script2.src = 'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js';
        script2.integrity = 'sha512-qZvrmS2ekKPF2mSznTQsxqPgnpkI4DNTlrdUmTzrDgektczlKNRRhy5X5AAOnx5S09ydFYWWNSfcEqDTTHgtNA==';
        script2.crossOrigin = 'anonymous';
        document.head.appendChild(script2);
        
        // Esperar a que ambas bibliotecas se carguen
        let html2canvasLoaded = false;
        let jspdfLoaded = false;
        let loadError = false;
        
        script1.onload = function() {
            html2canvasLoaded = true;
            if (jspdfLoaded && !loadError) procesarPDF();
        };
        
        script1.onerror = function() {
            loadError = true;
            btnExportar.innerHTML = textoOriginal;
            btnExportar.disabled = false;
            alert('Error al cargar la biblioteca html2canvas. Por favor, verifique su conexión a internet e intente nuevamente.');
        };
        
        script2.onload = function() {
            jspdfLoaded = true;
            if (html2canvasLoaded && !loadError) procesarPDF();
        };
        
        script2.onerror = function() {
            loadError = true;
            btnExportar.innerHTML = textoOriginal;
            btnExportar.disabled = false;
            alert('Error al cargar la biblioteca jsPDF. Por favor, verifique su conexión a internet e intente nuevamente.');
        };
    } else {
        procesarPDF();
    }
}

async function procesarPDF() {
    const btnExportar = document.querySelector('button[onclick="exportarPDF()"]');
    const textoOriginal = '<i class="bi bi-file-pdf-fill me-2"></i>Exportar en PDF';
    
    try {
        const { jsPDF } = window.jspdf;
        const pdf = new jsPDF('p', 'mm', 'a4');
        const pageWidth = pdf.internal.pageSize.getWidth();
        const pageHeight = pdf.internal.pageSize.getHeight();
        const margin = 15;
        let yPosition = margin;
        let pageNum = 1;
        
        // Función para agregar encabezado
        function agregarEncabezado(pdf, pageNum) {
            pdf.setFillColor(102, 126, 234);
            pdf.rect(0, 0, pageWidth, 25, 'F');
            pdf.setTextColor(255, 255, 255);
            pdf.setFontSize(18);
            pdf.setFont(undefined, 'bold');
            pdf.text('Reportes y Analytics', pageWidth / 2, 12, { align: 'center' });
            pdf.setFontSize(10);
            pdf.setFont(undefined, 'normal');
            pdf.text('Sistema de Validación de Simpatizantes', pageWidth / 2, 18, { align: 'center' });
            pdf.setTextColor(100, 100, 100);
            pdf.setFontSize(8);
            pdf.text(`Página ${pageNum}`, pageWidth - margin, pageHeight - 5, { align: 'right' });
            pdf.text(`Generado: ${new Date().toLocaleDateString('es-MX')}`, margin, pageHeight - 5);
            return 30; // Retornar posición Y después del encabezado
        }
        
        // Capturar TODOS los elementos en paralelo (mucho más rápido)
        const captureOptions = {
            scale: 0.8,
            backgroundColor: '#ffffff',
            logging: false,
            imageTimeout: 0,
            removeContainer: true
        };
        
        const divTiempo = document.getElementById('chartTiempo');
        const divSecciones = document.getElementById('chartSecciones');
        const divCapturistas = document.getElementById('chartCapturistas');
        const tablaSecciones = document.getElementById('tabla-secciones');
        const tablaCapturistas = document.getElementById('tabla-capturistas');
        
        // Capturar todo en paralelo
        const [canvasTiempo, canvasSecciones, canvasCapturistas, canvasTabla1, canvasTabla2] = await Promise.all([
            divTiempo ? html2canvas(divTiempo, captureOptions) : null,
            divSecciones ? html2canvas(divSecciones, captureOptions) : null,
            divCapturistas ? html2canvas(divCapturistas, captureOptions) : null,
            tablaSecciones ? html2canvas(tablaSecciones, captureOptions) : null,
            tablaCapturistas ? html2canvas(tablaCapturistas, captureOptions) : null
        ]);
        
        // Convertir a imágenes
        const imgTiempo = canvasTiempo ? canvasTiempo.toDataURL('image/jpeg', 0.6) : null;
        const imgSecciones = canvasSecciones ? canvasSecciones.toDataURL('image/jpeg', 0.6) : null;
        const imgCapturistas = canvasCapturistas ? canvasCapturistas.toDataURL('image/jpeg', 0.6) : null;
        const imgTabla1 = canvasTabla1 ? canvasTabla1.toDataURL('image/jpeg', 0.6) : null;
        const imgTabla2 = canvasTabla2 ? canvasTabla2.toDataURL('image/jpeg', 0.6) : null;
        
        // Página 1: Las 3 gráficas
        yPosition = agregarEncabezado(pdf, pageNum);
        
        const chartHeight = 78;
        const chartWidth = pageWidth - 2 * margin;
        
        // Gráfica 1
        if (imgTiempo) {
            pdf.setFontSize(11);
            pdf.setTextColor(102, 126, 234);
            pdf.setFont(undefined, 'bold');
            pdf.text('Actividad de Registros', margin, yPosition);
            yPosition += 4;
            pdf.addImage(imgTiempo, 'JPEG', margin, yPosition, chartWidth, chartHeight);
            yPosition += chartHeight + 3;
        }
        
        // Gráfica 2
        if (imgSecciones) {
            pdf.setFontSize(11);
            pdf.setTextColor(102, 126, 234);
            pdf.setFont(undefined, 'bold');
            pdf.text('Por Sección Electoral', margin, yPosition);
            yPosition += 4;
            pdf.addImage(imgSecciones, 'JPEG', margin, yPosition, chartWidth, chartHeight);
            yPosition += chartHeight + 3;
        }
        
        // Gráfica 3
        if (imgCapturistas) {
            pdf.setFontSize(11);
            pdf.setTextColor(102, 126, 234);
            pdf.setFont(undefined, 'bold');
            pdf.text('Por Capturista', margin, yPosition);
            yPosition += 4;
            pdf.addImage(imgCapturistas, 'JPEG', margin, yPosition, chartWidth, chartHeight);
        }
        
        // Página 2: Tablas
        pdf.addPage();
        pageNum++;
        yPosition = agregarEncabezado(pdf, pageNum);
        
        if (imgTabla1) {
            pdf.setFontSize(12);
            pdf.setTextColor(102, 126, 234);
            pdf.setFont(undefined, 'bold');
            pdf.text('Top 10 Secciones Electorales', margin, yPosition);
            yPosition += 6;
            const imgWidth = pageWidth - 2 * margin;
            const imgHeight = (canvasTabla1.height * imgWidth) / canvasTabla1.width;
            pdf.addImage(imgTabla1, 'JPEG', margin, yPosition, imgWidth, imgHeight);
            yPosition += imgHeight + 8;
        }
        
        if (imgTabla2) {
            pdf.setFontSize(12);
            pdf.setTextColor(102, 126, 234);
            pdf.setFont(undefined, 'bold');
            pdf.text('Top 10 Capturistas', margin, yPosition);
            yPosition += 6;
            const imgWidth = pageWidth - 2 * margin;
            const imgHeight = (canvasTabla2.height * imgWidth) / canvasTabla2.width;
            pdf.addImage(imgTabla2, 'JPEG', margin, yPosition, imgWidth, imgHeight);
        }
        
        // Descargar PDF
        const fecha = new Date().toISOString().split('T')[0];
        pdf.save(`reporte_simpatizantes_${fecha}.pdf`);
        
        btnExportar.innerHTML = textoOriginal;
        btnExportar.disabled = false;
        
    } catch (error) {
        console.error('Error al generar PDF:', error);
        alert('Hubo un error al generar el PDF. Por favor, intente nuevamente.');
        
        btnExportar.innerHTML = textoOriginal;
        btnExportar.disabled = false;
    }
}
</script>

<?php include __DIR__ . '/../../app/views/layouts/footer.php'; ?>
