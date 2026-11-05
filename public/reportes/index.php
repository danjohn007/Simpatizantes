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

// Validar fechas (formato AAAA-MM-DD) y su orden
$esFecha = function ($f) {
    $d = DateTime::createFromFormat('Y-m-d', (string)$f);
    return $d && $d->format('Y-m-d') === $f;
};
if (!$esFecha($filtros['fecha_inicio'])) { $filtros['fecha_inicio'] = date('Y-m-d', strtotime('-30 days')); }
if (!$esFecha($filtros['fecha_fin'])) { $filtros['fecha_fin'] = date('Y-m-d'); }
if ($filtros['fecha_inicio'] > $filtros['fecha_fin']) {
    list($filtros['fecha_inicio'], $filtros['fecha_fin']) = [$filtros['fecha_fin'], $filtros['fecha_inicio']];
}
if (!empty($filtros['campana_id'])) { $filtros['campana_id'] = (int)$filtros['campana_id']; }

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

<?php
// ---------- Indicadores del periodo (calculados con los datos reales) ----------
$totalPeriodo = 0;
$mejorDia = null;
foreach ($estatsPorDia as $r) {
    $t = (int)$r['total'];
    $totalPeriodo += $t;
    if ($mejorDia === null || $t > $mejorDia['total']) { $mejorDia = ['fecha' => $r['fecha'], 'total' => $t]; }
}
$diasPeriodo   = max(1, (int)((strtotime($filtros['fecha_fin']) - strtotime($filtros['fecha_inicio'])) / 86400) + 1);
$promedioDia   = round($totalPeriodo / $diasPeriodo, 1);
$maxSeccion    = 0; foreach (array_slice($estatsPorSeccion, 0, 10) as $s) { $maxSeccion = max($maxSeccion, (int)$s['total']); }
$maxCapturista = 0; foreach (array_slice($estatsPorCapturista, 0, 10) as $c) { $maxCapturista = max($maxCapturista, (int)$c['total']); }

$nombreCampana = '';
if (!empty($filtros['campana_id'])) {
    foreach ($campanas as $c) { if ($c['id'] == $filtros['campana_id']) { $nombreCampana = $c['nombre']; break; } }
}
$rango = function ($dias) use ($filtros) {
    $p = ['fecha_inicio' => date('Y-m-d', strtotime("-{$dias} days")), 'fecha_fin' => date('Y-m-d')];
    if (!empty($filtros['campana_id'])) { $p['campana_id'] = $filtros['campana_id']; }
    return '?' . http_build_query($p);
};
$etiquetaPeriodo = date('d/m/Y', strtotime($filtros['fecha_inicio'])) . ' – ' . date('d/m/Y', strtotime($filtros['fecha_fin']));
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
        <button class="btn btn-primary" onclick="exportarPDF()"><i class="bi bi-file-pdf-fill me-2"></i>Exportar en PDF</button>
    </div>

    <!-- Filtros -->
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="" class="row g-3 align-items-end">
                <?php if ($auth->puedeVerTodasLasCampanas()): ?>
                <div class="col-lg-4">
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
                <div class="<?php echo $auth->puedeVerTodasLasCampanas() ? 'col-lg-3 col-md-6' : 'col-md-5'; ?>">
                    <label class="form-label small text-muted mb-1">Desde</label>
                    <input type="date" class="form-control" name="fecha_inicio" value="<?php echo htmlspecialchars($filtros['fecha_inicio']); ?>">
                </div>
                <div class="<?php echo $auth->puedeVerTodasLasCampanas() ? 'col-lg-3 col-md-6' : 'col-md-5'; ?>">
                    <label class="form-label small text-muted mb-1">Hasta</label>
                    <input type="date" class="form-control" name="fecha_fin" value="<?php echo htmlspecialchars($filtros['fecha_fin']); ?>">
                </div>
                <div class="<?php echo $auth->puedeVerTodasLasCampanas() ? 'col-lg-2' : 'col-md-2'; ?>">
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel me-2"></i>Filtrar</button>
                </div>
            </form>
            <div class="quick-ranges mt-3">
                <span class="text-muted small me-2">Rango rápido:</span>
                <a href="<?php echo $rango(7); ?>">7 días</a>
                <a href="<?php echo $rango(30); ?>">30 días</a>
                <a href="<?php echo $rango(90); ?>">90 días</a>
                <a href="<?php echo $rango(365); ?>">12 meses</a>
            </div>
        </div>
    </div>

    <!-- Indicadores -->
    <div class="row g-3 mb-3">
        <div class="col-xl-3 col-md-6">
            <div class="card kpi"><i class="bi bi-person-plus ico"></i>
                <div class="label">Registros en el periodo</div>
                <div class="value"><?php echo number_format($totalPeriodo); ?></div>
                <div class="hint"><?php echo $diasPeriodo; ?> días analizados</div></div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card kpi"><i class="bi bi-speedometer2 ico"></i>
                <div class="label">Promedio diario</div>
                <div class="value"><?php echo number_format($promedioDia, 1); ?></div>
                <div class="hint">Registros por día</div></div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card kpi"><i class="bi bi-trophy ico"></i>
                <div class="label">Mejor día</div>
                <div class="value"><?php echo $mejorDia ? number_format($mejorDia['total']) : '—'; ?></div>
                <div class="hint"><?php echo $mejorDia ? date('d/m/Y', strtotime($mejorDia['fecha'])) : 'Sin registros'; ?></div></div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card kpi"><i class="bi bi-geo-alt ico"></i>
                <div class="label">Secciones con registros</div>
                <div class="value"><?php echo number_format(count($estatsPorSeccion)); ?></div>
                <div class="hint"><?php echo number_format(count($estatsPorCapturista)); ?> capturistas activos</div></div>
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
                            <span class="num"><?php echo number_format($capturista['total']); ?></span>
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
