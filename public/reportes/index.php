<?php
/**
 * Reportes y Analytics
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../app/controllers/AuthController.php';
require_once __DIR__ . '/../../app/models/Campana.php';
require_once __DIR__ . '/../../app/helpers/security.php';
require_once __DIR__ . '/../../app/services/AnaliticaService.php';

$auth = new AuthController();
$auth->requiereAutenticacion();

$campanaModel = new Campana();
$puedeVerTodas = $auth->puedeVerTodasLasCampanas();
$esCapturista = $auth->obtenerRol() === 'capturista';

// Filtros validados y limitados al alcance del usuario (campaña propia, o solo lo suyo si es capturista)
$filtros = AnaliticaService::filtrosDeGet($_GET, 30);
$filtros = AnaliticaService::alcance($auth, $filtros);

$analitica = new AnaliticaService();
$comp = $analitica->comparativo($filtros);
$resumen = $comp['actual'];
$estatsPorDia = $analitica->porDia($filtros);
$estatsPorSeccion = $analitica->porSeccion($filtros);
$estatsPorCapturista = $analitica->porCapturista($filtros, 10);
$estatsPorCiudad = $analitica->porCiudad($filtros, 10);
$estatus = $analitica->estatus($filtros);
$temas = $analitica->porTema($filtros);
$ciudadesDisp = $analitica->ciudades($filtros);
$campanas = $campanaModel->obtenerTodas(1);

$pageTitle = 'Reportes';
include __DIR__ . '/../../app/views/layouts/header.php';
?>

<?php
// ---------- Indicadores del periodo (datos reales, comparados con el periodo anterior) ----------
$totalPeriodo = $resumen['total'];
$mejorDia = null;
foreach ($estatsPorDia as $r) {
    $t = (int)$r['total'];
    if ($mejorDia === null || $t > $mejorDia['total']) { $mejorDia = ['fecha' => $r['fecha'], 'total' => $t]; }
}
$diasPeriodo   = $comp['dias'];
$promedioDia   = round($totalPeriodo / max(1, $diasPeriodo), 1);
$maxSeccion    = 0; foreach (array_slice($estatsPorSeccion, 0, 10) as $s) { $maxSeccion = max($maxSeccion, (int)$s['total']); }
$maxCapturista = 0; foreach (array_slice($estatsPorCapturista, 0, 10) as $c) { $maxCapturista = max($maxCapturista, (int)$c['total']); }
$maxCiudad     = 0; foreach ($estatsPorCiudad as $c) { $maxCiudad = max($maxCiudad, (int)$c['total']); }

$nombreCampana = '';
if (!empty($filtros['campana_id'])) {
    foreach ($campanas as $c) { if ($c['id'] == $filtros['campana_id']) { $nombreCampana = $c['nombre']; break; } }
}
// Enlaces de rango rápido: conservan campaña, municipio, sección y estado
$conservar = [];
foreach (['campana_id' => 'campana_id', 'ciudad' => 'ciudad', 'seccion_electoral' => 'seccion', 'estatus' => 'estatus'] as $k => $get) {
    if (!empty($filtros[$k]) && !($k === 'campana_id' && !$puedeVerTodas)) { $conservar[$get] = $filtros[$k]; }
}
$rango = function ($dias) use ($conservar) {
    return '?' . http_build_query($conservar + ['fecha_inicio' => date('Y-m-d', strtotime('-' . ($dias - 1) . ' days')), 'fecha_fin' => date('Y-m-d')]);
};
$etiquetaPeriodo = date('d/m/Y', strtotime($filtros['fecha_inicio'])) . ' – ' . date('d/m/Y', strtotime($filtros['fecha_fin']));
$delta = function ($v) {
    if ($v === null) { return '<span class="text-muted">sin periodo anterior para comparar</span>'; }
    $sube = $v >= 0;
    return '<span style="color:' . ($sube ? '#2f6b4a' : '#8a2f25') . ';font-weight:600"><i class="bi bi-arrow-' . ($sube ? 'up' : 'down') . '-right"></i> '
         . ($sube ? '+' : '') . number_format($v, 1) . '%</span> <span class="text-muted">vs periodo anterior</span>';
};
$totEstatus = max(1, array_sum($estatus));
?>
<div class="container-fluid p-0">
    <div class="page-head">
        <div>
            <div class="eyebrow">Análisis</div>
            <h1>Reportes</h1>
            <div class="meta">
                <span><i class="bi bi-calendar3"></i><?php echo $etiquetaPeriodo; ?></span>
                <span><i class="bi bi-megaphone"></i><?php echo $nombreCampana !== '' ? htmlspecialchars($nombreCampana) : 'Todas las campañas'; ?></span>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <?php if (!$esCapturista): ?>
            <a class="btn btn-soft" target="_blank" rel="noopener" href="semanal.php<?php echo !empty($filtros['campana_id']) && $puedeVerTodas ? '?campana_id=' . (int)$filtros['campana_id'] : ''; ?>"
               title="PDF de los últimos 7 días comparado con los 7 anteriores"><i class="bi bi-calendar-week me-2"></i>Reporte semanal</a>
            <form method="POST" action="semanal.php" class="d-inline">
                <?php echo csrf_field(); ?>
                <?php if (!empty($filtros['campana_id']) && $puedeVerTodas): ?><input type="hidden" name="campana_id" value="<?php echo (int)$filtros['campana_id']; ?>"><?php endif; ?>
                <button class="btn btn-soft" title="Recibir el reporte semanal en su correo"><i class="bi bi-envelope me-2"></i>Enviármelo</button>
            </form>
            <?php endif; ?>
            <button class="btn btn-primary" onclick="exportarPDF()"><i class="bi bi-file-pdf-fill me-2"></i>Exportar esta vista</button>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="" class="row g-3 align-items-end">
                <?php if ($puedeVerTodas): ?>
                <div class="col-lg-3 col-md-6">
                    <label class="form-label small text-muted mb-1">Campaña</label>
                    <select class="form-select" name="campana_id">
                        <option value="">Todas las campañas</option>
                        <?php foreach ($campanas as $campana): ?>
                            <option value="<?php echo (int)$campana['id']; ?>" <?php echo (!empty($filtros['campana_id']) && $filtros['campana_id'] == $campana['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($campana['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="col-lg-2 col-md-6">
                    <label class="form-label small text-muted mb-1">Desde</label>
                    <input type="date" class="form-control" name="fecha_inicio" value="<?php echo htmlspecialchars($filtros['fecha_inicio']); ?>">
                </div>
                <div class="col-lg-2 col-md-6">
                    <label class="form-label small text-muted mb-1">Hasta</label>
                    <input type="date" class="form-control" name="fecha_fin" value="<?php echo htmlspecialchars($filtros['fecha_fin']); ?>">
                </div>
                <div class="col-lg-2 col-md-6">
                    <label class="form-label small text-muted mb-1">Municipio</label>
                    <select class="form-select" name="ciudad">
                        <option value="">Todos</option>
                        <?php foreach ($ciudadesDisp as $ciudad): ?>
                            <option value="<?php echo htmlspecialchars($ciudad); ?>" <?php echo (($filtros['ciudad'] ?? '') === $ciudad) ? 'selected' : ''; ?>><?php echo htmlspecialchars($ciudad); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-1 col-md-6">
                    <label class="form-label small text-muted mb-1">Sección</label>
                    <input type="text" class="form-control" name="seccion" inputmode="numeric" maxlength="4" placeholder="0123" value="<?php echo htmlspecialchars($filtros['seccion_electoral'] ?? ''); ?>">
                </div>
                <div class="col-lg-2 col-md-6">
                    <label class="form-label small text-muted mb-1">Estado</label>
                    <select class="form-select" name="estatus">
                        <option value="">Todos</option>
                        <?php foreach (['pendiente' => 'Pendiente', 'validado' => 'Validado', 'rechazado' => 'Rechazado'] as $k => $t): ?>
                            <option value="<?php echo $k; ?>" <?php echo (($filtros['estatus'] ?? '') === $k) ? 'selected' : ''; ?>><?php echo $t; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 d-flex flex-wrap align-items-center gap-3">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-funnel me-2"></i>Filtrar</button>
                    <a href="index.php" class="btn btn-soft">Limpiar</a>
                    <div class="quick-ranges ms-lg-2">
                        <span class="text-muted small me-2">Rango rápido:</span>
                        <a href="<?php echo $rango(7); ?>">7 días</a>
                        <a href="<?php echo $rango(30); ?>">30 días</a>
                        <a href="<?php echo $rango(90); ?>">90 días</a>
                        <a href="<?php echo $rango(365); ?>">12 meses</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Indicadores -->
    <div class="row g-3 mb-3">
        <div class="col-xl-3 col-md-6">
            <div class="card kpi"><i class="bi bi-person-plus ico"></i>
                <div class="label">Registros en el periodo</div>
                <div class="value"><?php echo number_format($totalPeriodo); ?></div>
                <div class="hint"><?php echo $delta($comp['var_total']); ?></div></div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card kpi"><i class="bi bi-patch-check ico"></i>
                <div class="label">Validados</div>
                <div class="value"><?php echo number_format($resumen['validados']); ?></div>
                <div class="hint"><?php echo $resumen['pct_validados']; ?>% del periodo · <?php echo $delta($comp['var_validados']); ?></div></div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card kpi"><i class="bi bi-speedometer2 ico"></i>
                <div class="label">Promedio diario</div>
                <div class="value"><?php echo number_format($promedioDia, 1); ?></div>
                <div class="hint">Mejor día: <?php echo $mejorDia ? number_format($mejorDia['total']) . ' (' . date('d/m', strtotime($mejorDia['fecha'])) . ')' : '—'; ?></div></div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card kpi"><i class="bi bi-geo-alt ico"></i>
                <div class="label">Secciones con registros</div>
                <div class="value"><?php echo number_format($resumen['secciones']); ?></div>
                <div class="hint"><?php echo number_format($resumen['capturistas']); ?> capturistas activos</div></div>
        </div>
    </div>

    <!-- Actividad -->
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0"><i class="bi bi-activity me-2"></i>Actividad de registros</h5>
            <span class="text-muted small">Registros por día y promedio móvil de 7 días</span>
        </div>
        <div class="card-body"><div id="chartTiempo" class="chart-box"></div></div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header"><h5 class="mb-0"><i class="bi bi-geo-alt me-2"></i>Por sección electoral</h5></div>
                <div class="card-body"><div id="chartSecciones" class="chart-box"></div></div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header"><h5 class="mb-0"><i class="bi bi-people me-2"></i>Por capturista</h5></div>
                <div class="card-body"><div id="chartCapturistas" class="chart-box"></div></div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header"><h5 class="mb-0"><i class="bi bi-patch-check me-2"></i>Estado de validación</h5></div>
                <div class="card-body">
                    <div class="d-flex rounded overflow-hidden mb-3" style="height:18px;background:#ece6df" role="img" aria-label="Distribución por estado de validación">
                        <?php foreach ([['validado', '#2f6b4a'], ['pendiente', '#ff8000'], ['rechazado', '#8a2f25']] as $e): ?>
                            <div style="width:<?php echo round(100 * ($estatus[$e[0]] ?? 0) / $totEstatus, 2); ?>%;background:<?php echo $e[1]; ?>"></div>
                        <?php endforeach; ?>
                    </div>
                    <div class="row text-center g-2">
                        <?php foreach ([['validado', 'Validados', '#2f6b4a'], ['pendiente', 'Pendientes', '#b85400'], ['rechazado', 'Rechazados', '#8a2f25']] as $e): ?>
                            <div class="col-4"><div class="fs-4 fw-bold" style="color:<?php echo $e[2]; ?>"><?php echo number_format($estatus[$e[0]] ?? 0); ?></div>
                                <div class="small text-muted"><?php echo $e[1]; ?> · <?php echo round(100 * ($estatus[$e[0]] ?? 0) / $totEstatus); ?>%</div></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header"><h5 class="mb-0"><i class="bi bi-buildings me-2"></i>Municipios con más registros</h5></div>
                <div class="card-body">
                    <?php if (empty($estatsPorCiudad)): ?><p class="text-muted mb-0">Sin registros en el periodo.</p><?php endif; ?>
                    <?php foreach (array_slice($estatsPorCiudad, 0, 6) as $i => $c): ?>
                        <div class="rank-row">
                            <span class="pos"><?php echo $i + 1; ?></span>
                            <div>
                                <div class="name"><?php echo htmlspecialchars($c['ciudad']); ?></div>
                                <div class="bar"><span style="width: <?php echo $maxCiudad > 0 ? round(($c['total'] / $maxCiudad) * 100) : 0; ?>%"></span></div>
                            </div>
                            <span class="num"><?php echo number_format($c['total']); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($temas)): ?>
    <div class="card mb-3">
        <div class="card-header"><h5 class="mb-0"><i class="bi bi-chat-heart me-2"></i>Lo que piden los vecinos</h5></div>
        <div class="card-body">
            <?php foreach (array_slice($temas, 0, 8) as $i => $t): ?>
                <div class="rank-row">
                    <span class="pos"><?php echo $i + 1; ?></span>
                    <div>
                        <div class="name"><?php echo htmlspecialchars($t['etiqueta']); ?></div>
                        <div class="bar"><span style="width: <?php echo (int)$t['pct']; ?>%"></span></div>
                    </div>
                    <span class="num"><?php echo number_format($t['total']); ?><small class="d-block text-muted fw-normal" style="font-size:.7rem"><?php echo (int)$t['pct']; ?>% de quienes respondieron</small></span>
                </div>
            <?php endforeach; ?>
            <p class="text-muted small mt-2 mb-0">Sale de la pregunta "¿qué le gustaría mejorar en su colonia?" que se hace al registrar a cada persona desde la app.</p>
        </div>
    </div>
    <?php endif; ?>

    <!-- Rankings -->
    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card h-100" id="tabla-secciones">
                <div class="card-header"><h5 class="mb-0"><i class="bi bi-award me-2"></i>Top 10 secciones</h5></div>
                <div class="card-body">
                    <?php $topSecciones = array_slice($estatsPorSeccion, 0, 10); ?>
                    <?php if (empty($topSecciones)): ?><p class="text-muted mb-0">Sin registros en el periodo.</p><?php endif; ?>
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
        <div class="col-lg-6">
            <div class="card h-100" id="tabla-capturistas">
                <div class="card-header"><h5 class="mb-0"><i class="bi bi-person-check me-2"></i>Top 10 capturistas</h5></div>
                <div class="card-body">
                    <?php $topCapturistas = array_slice($estatsPorCapturista, 0, 10); ?>
                    <?php if (empty($topCapturistas)): ?><p class="text-muted mb-0">Sin registros en el periodo.</p><?php endif; ?>
                    <?php foreach ($topCapturistas as $i => $capturista): ?>
                        <div class="rank-row">
                            <span class="pos"><?php echo $i + 1; ?></span>
                            <div>
                                <div class="name"><?php echo htmlspecialchars($capturista['nombre_completo']); ?></div>
                                <div class="bar"><span style="width: <?php echo $maxCapturista > 0 ? round(($capturista['total'] / $maxCapturista) * 100) : 0; ?>%"></span></div>
                            </div>
                            <span class="num"><?php echo number_format($capturista['total']); ?><small class="d-block text-muted fw-normal" style="font-size:.7rem"><?php echo $capturista['pct']; ?>% valid.</small></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .chart-box { width: 100%; height: 320px; }
    .quick-ranges a { display: inline-block; padding: 3px 12px; margin-right: 6px; border: 1px solid var(--slate-200); border-radius: 999px; font-size: .82rem; color: var(--navy-700); text-decoration: none; background: #fff; }
    .quick-ranges a:hover { border-color: var(--navy-700); background: #fffaf4; }
    .chart-empty { height: 100%; display: grid; place-items: center; color: var(--slate-500); text-align: center; }
    .chart-empty i { font-size: 2rem; color: var(--slate-300); display: block; margin-bottom: 6px; }
</style>

<script src="<?php echo BASE_URL; ?>/public/vendor/highcharts/highcharts.js"></script>
<script src="<?php echo BASE_URL; ?>/public/vendor/highcharts/modules/accessibility.js"></script>
<script>
// Etiquetas del periodo para el PDF
const PERIODO = {
    inicio: <?php echo json_encode($filtros['fecha_inicio']); ?>,
    fin: <?php echo json_encode($filtros['fecha_fin']); ?>,
    etiqueta: <?php echo json_encode($etiquetaPeriodo); ?>,
    campana: <?php echo json_encode($nombreCampana); ?>
};

// Datos reales de la base de datos (sin valores simulados)
const dataTiempo = <?php echo json_encode($estatsPorDia); ?>;
const dataSecciones = <?php echo json_encode(array_slice($estatsPorSeccion, 0, 10)); ?>;
const dataCapturistas = <?php echo json_encode(array_slice($estatsPorCapturista, 0, 10)); ?>;

(function () {
    const NAVY = '#e06a00', STEEL = '#3a3633', GOLD = '#111111', GRID = '#efeae4', TXT = '#6e655e';

    function vacio(id, texto) {
        document.getElementById(id).innerHTML = '<div class="chart-empty"><div><i class="bi bi-bar-chart"></i>' + texto + '</div></div>';
    }
    if (typeof Highcharts === 'undefined') {
        ['chartTiempo', 'chartSecciones', 'chartCapturistas'].forEach(function (id) { vacio(id, 'No se pudo cargar la librería de gráficas.'); });
        return;
    }

    Highcharts.setOptions({
        lang: {
            thousandsSep: ',',
            months: ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'],
            shortMonths: ['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'],
            weekdays: ['domingo','lunes','martes','miércoles','jueves','viernes','sábado']
        },
        chart: { style: { fontFamily: 'inherit' }, backgroundColor: 'transparent', spacingTop: 10 },
        credits: { enabled: false },
        exporting: { enabled: false },
        title: { text: null }
    });

    // ---- Actividad por día (todos los días del rango, los vacíos en 0) ----
    const porFecha = {};
    dataTiempo.forEach(function (r) { porFecha[r.fecha] = parseInt(r.total, 10); });
    const pad = function (n) { return (n < 10 ? '0' : '') + n; };
    const valores = [];
    const fin = new Date(PERIODO.fin + 'T00:00:00');
    for (let d = new Date(PERIODO.inicio + 'T00:00:00'); d <= fin && valores.length < 400; d.setDate(d.getDate() + 1)) {
        valores.push(porFecha[d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate())] || 0);
    }
    if (!dataTiempo.length) {
        vacio('chartTiempo', 'No hay registros en el periodo seleccionado.');
    } else {
        const media7 = valores.map(function (_, i) {
            const ini = Math.max(0, i - 6), tramo = valores.slice(ini, i + 1);
            return Math.round((tramo.reduce(function (a, b) { return a + b; }, 0) / tramo.length) * 10) / 10;
        });
        const p = PERIODO.inicio.split('-').map(Number);
        const inicioUTC = Date.UTC(p[0], p[1] - 1, p[2]);
        Highcharts.chart('chartTiempo', {
            xAxis: { type: 'datetime', tickLength: 0, lineColor: GRID, labels: { format: '{value:%e %b}', style: { color: TXT } } },
            yAxis: { title: { text: null }, min: 0, allowDecimals: false, gridLineColor: GRID, labels: { style: { color: TXT } } },
            legend: { align: 'right', verticalAlign: 'top', itemStyle: { color: '#3a3633', fontWeight: '500' } },
            tooltip: { shared: true, xDateFormat: '%A %e de %B de %Y', valueDecimals: 0, borderColor: '#d9d2cb' },
            plotOptions: { series: { pointStart: inicioUTC, pointInterval: 86400000, animation: { duration: 600 } } },
            series: [
                { type: 'area', name: 'Registros por día', data: valores, color: NAVY, lineWidth: 2, marker: { enabled: false },
                  fillColor: { linearGradient: { x1: 0, y1: 0, x2: 0, y2: 1 }, stops: [[0, 'rgba(224,106,0,.22)'], [1, 'rgba(224,106,0,0)']] } },
                { type: 'line', name: 'Promedio móvil (7 días)', data: media7, color: GOLD, lineWidth: 2, dashStyle: 'ShortDash', marker: { enabled: false },
                  tooltip: { valueDecimals: 1 } }
            ]
        });
    }

    // ---- Barras horizontales (secciones y capturistas) ----
    function barras(id, etiquetas, datos, nombreSerie, color) {
        if (!datos.length) { vacio(id, 'Sin registros en el periodo.'); return; }
        Highcharts.chart(id, {
            chart: { type: 'bar' },
            xAxis: { categories: etiquetas, tickLength: 0, lineColor: GRID, labels: { style: { color: '#3a3633' } } },
            yAxis: { title: { text: null }, min: 0, allowDecimals: false, gridLineColor: GRID, labels: { style: { color: TXT } } },
            legend: { enabled: false },
            tooltip: { headerFormat: '<b>{point.key}</b><br>', pointFormat: nombreSerie + ': <b>{point.y}</b>', borderColor: '#d9d2cb' },
            plotOptions: { bar: { color: color, borderRadius: 3, pointPadding: .12, groupPadding: .08, dataLabels: { enabled: true, style: { color: '#3a3633', fontWeight: '600', textOutline: 'none' } } } },
            series: [{ name: nombreSerie, data: datos }]
        });
    }
    barras('chartSecciones',
        dataSecciones.map(function (r) { return 'Sección ' + r.seccion_electoral; }),
        dataSecciones.map(function (r) { return parseInt(r.total, 10); }), 'Registros', NAVY);
    barras('chartCapturistas',
        dataCapturistas.map(function (r) { return r.nombre_completo; }),
        dataCapturistas.map(function (r) { return parseInt(r.total, 10); }), 'Registros', STEEL);
})();

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
        script1.src = '<?php echo BASE_URL; ?>/public/vendor/pdf/html2canvas.min.js';
        document.head.appendChild(script1);
        
        const script2 = document.createElement('script');
        script2.src = '<?php echo BASE_URL; ?>/public/vendor/pdf/jspdf.umd.min.js';
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
            pdf.setFillColor(22, 38, 63);
            pdf.rect(0, 0, pageWidth, 29, 'F');
            pdf.setFillColor(166, 138, 75);
            pdf.rect(0, 29, pageWidth, 1.2, 'F');
            pdf.setTextColor(255, 255, 255);
            pdf.setFontSize(18);
            pdf.setFont(undefined, 'bold');
            pdf.text('Reporte de simpatizantes', pageWidth / 2, 11, { align: 'center' });
            pdf.setFontSize(10);
            pdf.setFont(undefined, 'normal');
            pdf.text('Sistema de Validación de Simpatizantes', pageWidth / 2, 17, { align: 'center' });
            pdf.setFontSize(9);
            pdf.text('Periodo: ' + PERIODO.etiqueta + '   |   Campaña: ' + (PERIODO.campana || 'Todas'), pageWidth / 2, 23, { align: 'center' });
            pdf.setTextColor(100, 100, 100);
            pdf.setFontSize(8);
            pdf.text(`Página ${pageNum}`, pageWidth - margin, pageHeight - 5, { align: 'right' });
            pdf.text(`Generado: ${new Date().toLocaleDateString('es-MX')}`, margin, pageHeight - 5);
            return 36; // Posición Y después del encabezado
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
            pdf.setTextColor(31, 58, 95);
            pdf.setFont(undefined, 'bold');
            pdf.text('Actividad de Registros', margin, yPosition);
            yPosition += 4;
            pdf.addImage(imgTiempo, 'JPEG', margin, yPosition, chartWidth, chartHeight);
            yPosition += chartHeight + 3;
        }
        
        // Gráfica 2
        if (imgSecciones) {
            pdf.setFontSize(11);
            pdf.setTextColor(31, 58, 95);
            pdf.setFont(undefined, 'bold');
            pdf.text('Por Sección Electoral', margin, yPosition);
            yPosition += 4;
            pdf.addImage(imgSecciones, 'JPEG', margin, yPosition, chartWidth, chartHeight);
            yPosition += chartHeight + 3;
        }
        
        // Gráfica 3
        if (imgCapturistas) {
            pdf.setFontSize(11);
            pdf.setTextColor(31, 58, 95);
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
            pdf.setTextColor(31, 58, 95);
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
            pdf.setTextColor(31, 58, 95);
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
