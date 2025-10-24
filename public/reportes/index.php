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
if (!empty($_GET['campana_id'])) {
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

$whereClause = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

$sql = "SELECT DATE(created_at) as fecha, COUNT(*) as total 
        FROM simpatizantes 
        $whereClause
        AND DATE(created_at) BETWEEN ? AND ?
        GROUP BY DATE(created_at)
        ORDER BY fecha ASC";

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
                
                <div class="col-md-3">
                    <label class="form-label">Fecha Inicio</label>
                    <input type="date" class="form-control" name="fecha_inicio" 
                           value="<?php echo htmlspecialchars($filtros['fecha_inicio']); ?>">
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">Fecha Fin</label>
                    <input type="date" class="form-control" name="fecha_fin" 
                           value="<?php echo htmlspecialchars($filtros['fecha_fin']); ?>">
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-search"></i> Filtrar
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Gráfica de Avance en el Tiempo -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="bi bi-graph-up-arrow me-2"></i>Avance en el Tiempo
                    </h5>
                </div>
                <div class="card-body">
                    <canvas id="chartTiempo" height="80"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Gráficas de Comparación -->
    <div class="row mb-4">
        <!-- Por Sección -->
        <div class="col-lg-6 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="bi bi-geo-alt-fill me-2"></i>Por Sección Electoral
                    </h5>
                </div>
                <div class="card-body">
                    <canvas id="chartSecciones" height="150"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Por Capturista -->
        <div class="col-lg-6 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="bi bi-people-fill me-2"></i>Por Capturista
                    </h5>
                </div>
                <div class="card-body">
                    <canvas id="chartCapturistas" height="150"></canvas>
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

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Datos para las gráficas
const dataTiempo = <?php echo json_encode($estatsPorDia); ?>;
const dataSecciones = <?php echo json_encode(array_slice($estatsPorSeccion, 0, 10)); ?>;
const dataCapturistas = <?php echo json_encode(array_slice($estatsPorCapturista, 0, 10)); ?>;

console.log('Data loaded:', { dataTiempo, dataSecciones, dataCapturistas });

// Gráfica de Tiempo
const ctxTiempo = document.getElementById('chartTiempo');
if (ctxTiempo && dataTiempo && dataTiempo.length > 0) {
    new Chart(ctxTiempo, {
        type: 'line',
        data: {
            labels: dataTiempo.map(item => new Date(item.fecha).toLocaleDateString('es-MX')),
            datasets: [{
                label: 'Simpatizantes por Día',
                data: dataTiempo.map(item => parseInt(item.total)),
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
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return 'Simpatizantes: ' + context.parsed.y;
                        }
                    }
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
} else {
    console.error('No se pudo crear gráfica de tiempo');
}

// Gráfica de Secciones
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
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return 'Simpatizantes: ' + context.parsed.y;
                        }
                    }
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
} else {
    console.error('No se pudo crear gráfica de secciones');
}

// Gráfica de Capturistas
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
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.parsed || 0;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = ((value / total) * 100).toFixed(1);
                            return label + ': ' + value + ' (' + percentage + '%)';
                        }
                    }
                }
            }
        }
    });
} else {
    console.error('No se pudo crear gráfica de capturistas');
}

// Función para exportar a PDF
function exportarPDF() {
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
        
        script1.onload = function() {
            html2canvasLoaded = true;
            if (jspdfLoaded) generarPDF();
        };
        
        script2.onload = function() {
            jspdfLoaded = true;
            if (html2canvasLoaded) generarPDF();
        };
    } else {
        generarPDF();
    }
}

function generarPDF() {
    const { jsPDF } = window.jspdf;
    
    // Crear un elemento temporal para el contenido a exportar
    const elementoTemporal = document.createElement('div');
    elementoTemporal.style.position = 'absolute';
    elementoTemporal.style.left = '-9999px';
    elementoTemporal.style.width = '1200px';
    elementoTemporal.style.backgroundColor = 'white';
    elementoTemporal.style.padding = '20px';
    
    // Crear el contenido del PDF
    const contenidoPDF = `
        <div style="font-family: Arial, sans-serif;">
            <h1 style="text-align: center; color: #667eea;">Reportes y Analytics</h1>
            <p style="text-align: center; color: #666;">Sistema de Validación de Simpatizantes</p>
            <hr style="margin: 20px 0;">
            
            <div id="pdf-graficas" style="margin: 20px 0;">
                <h2 style="color: #667eea;">Gráficas</h2>
                <div id="chart-tiempo-container" style="margin-bottom: 30px;"></div>
                <div style="display: flex; gap: 20px; margin-bottom: 30px;">
                    <div id="chart-secciones-container" style="flex: 1;"></div>
                    <div id="chart-capturistas-container" style="flex: 1;"></div>
                </div>
            </div>
            
            <div id="pdf-tablas" style="margin: 20px 0;">
                <h2 style="color: #667eea;">Tablas de Datos</h2>
                <div style="display: flex; gap: 20px;">
                    <div id="tabla-secciones-container" style="flex: 1;"></div>
                    <div id="tabla-capturistas-container" style="flex: 1;"></div>
                </div>
            </div>
        </div>
    `;
    
    elementoTemporal.innerHTML = contenidoPDF;
    document.body.appendChild(elementoTemporal);
    
    // Copiar las gráficas
    const chartTiempo = document.getElementById('chartTiempo');
    if (chartTiempo) {
        const canvasCopia1 = chartTiempo.cloneNode(true);
        document.getElementById('chart-tiempo-container').appendChild(canvasCopia1);
    }
    
    const chartSecciones = document.getElementById('chartSecciones');
    if (chartSecciones) {
        const canvasCopia2 = chartSecciones.cloneNode(true);
        canvasCopia2.style.maxHeight = '300px';
        document.getElementById('chart-secciones-container').appendChild(canvasCopia2);
    }
    
    const chartCapturistas = document.getElementById('chartCapturistas');
    if (chartCapturistas) {
        const canvasCopia3 = chartCapturistas.cloneNode(true);
        canvasCopia3.style.maxHeight = '300px';
        document.getElementById('chart-capturistas-container').appendChild(canvasCopia3);
    }
    
    // Copiar las tablas usando los IDs específicos
    const tablaSecciones = document.getElementById('tabla-secciones');
    if (tablaSecciones) {
        const tablaClonada1 = tablaSecciones.cloneNode(true);
        document.getElementById('tabla-secciones-container').appendChild(tablaClonada1);
    }
    
    const tablaCapturistas = document.getElementById('tabla-capturistas');
    if (tablaCapturistas) {
        const tablaClonada2 = tablaCapturistas.cloneNode(true);
        document.getElementById('tabla-capturistas-container').appendChild(tablaClonada2);
    }
    
    // Generar PDF
    html2canvas(elementoTemporal, {
        scale: 2,
        logging: false,
        useCORS: true,
        allowTaint: true
    }).then(canvas => {
        const imgData = canvas.toDataURL('image/png');
        const pdf = new jsPDF('p', 'mm', 'a4');
        
        const imgWidth = 210; // A4 width in mm
        const pageHeight = 297; // A4 height in mm
        const imgHeight = (canvas.height * imgWidth) / canvas.width;
        let heightLeft = imgHeight;
        let position = 0;
        
        pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
        heightLeft -= pageHeight;
        
        while (heightLeft >= 0) {
            position = heightLeft - imgHeight;
            pdf.addPage();
            pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
            heightLeft -= pageHeight;
        }
        
        // Generar nombre del archivo con fecha
        const fecha = new Date().toISOString().split('T')[0];
        pdf.save(`reporte_simpatizantes_${fecha}.pdf`);
        
        // Limpiar
        document.body.removeChild(elementoTemporal);
    }).catch(error => {
        console.error('Error al generar PDF:', error);
        alert('Hubo un error al generar el PDF. Por favor, intente nuevamente.');
        document.body.removeChild(elementoTemporal);
    });
}
</script>

<?php include __DIR__ . '/../../app/views/layouts/footer.php'; ?>
