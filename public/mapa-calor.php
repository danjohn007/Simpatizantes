<?php
/**
 * Mapa de Calor - Visualización Geográfica de Simpatizantes
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

// Procesar filtros
$filtros = [];
if (!empty($_GET['campana_id'])) {
    $filtros['campana_id'] = $_GET['campana_id'];
}

if (!empty($_GET['fecha_inicio'])) {
    $filtros['fecha_inicio'] = $_GET['fecha_inicio'];
}

if (!empty($_GET['fecha_fin'])) {
    $filtros['fecha_fin'] = $_GET['fecha_fin'];
}

// Obtener datos para el mapa
$datosMapaCalor = $simpatizanteModel->obtenerParaMapaCalor($filtros);
$campanas = $campanaModel->obtenerTodas(1);

$pageTitle = 'Mapa de Calor';
include __DIR__ . '/../app/views/layouts/header.php';
?>

<style>
    #mapa {
        height: 600px;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
</style>

<div class="container-fluid">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-md-6">
            <h2><i class="bi bi-map-fill me-2"></i>Mapa de Calor</h2>
            <p class="text-muted">Visualización geográfica de simpatizantes</p>
        </div>
        <div class="col-md-6 text-end">
            <span class="badge bg-primary fs-6">
                <i class="bi bi-people-fill me-1"></i>
                <?php echo count($datosMapaCalor); ?> ubicaciones
            </span>
        </div>
    </div>
    
    <!-- Filtros -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Campaña</label>
                    <select class="form-select" name="campana_id" onchange="this.form.submit()">
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
                           value="<?php echo htmlspecialchars($_GET['fecha_inicio'] ?? ''); ?>"
                           onchange="this.form.submit()">
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">Fecha Fin</label>
                    <input type="date" class="form-control" name="fecha_fin" 
                           value="<?php echo htmlspecialchars($_GET['fecha_fin'] ?? ''); ?>"
                           onchange="this.form.submit()">
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <a href="mapa-calor.php" class="btn btn-secondary w-100">
                        <i class="bi bi-arrow-clockwise"></i> Limpiar
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Mapa -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div id="mapa"></div>
        </div>
    </div>
    
    <!-- Estadísticas -->
    <div class="row">
        <div class="col-md-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="bi bi-bar-chart-fill me-2"></i>Estadísticas de Ubicación</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 text-center">
                            <h3 class="text-primary"><?php echo count($datosMapaCalor); ?></h3>
                            <p class="text-muted">Ubicaciones Registradas</p>
                        </div>
                        <div class="col-md-3 text-center">
                            <h3 class="text-success">
                                <?php 
                                $secciones = array_unique(array_column($datosMapaCalor, 'seccion_electoral'));
                                echo count($secciones); 
                                ?>
                            </h3>
                            <p class="text-muted">Secciones Cubiertas</p>
                        </div>
                        <div class="col-md-3 text-center">
                            <h3 class="text-warning">
                                <?php echo count($campanas); ?>
                            </h3>
                            <p class="text-muted">Campañas Activas</p>
                        </div>
                        <div class="col-md-3 text-center">
                            <h3 class="text-info">100%</h3>
                            <p class="text-muted">Cobertura</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Leaflet CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<!-- Leaflet.heat plugin -->
<script src="https://unpkg.com/leaflet.heat@0.2.0/dist/leaflet-heat.js"></script>

<script>
// Inicializar el mapa
const mapa = L.map('mapa').setView([19.4326, -99.1332], 6);

// Agregar capa de OpenStreetMap
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    maxZoom: 19
}).addTo(mapa);

// Datos de ubicaciones
const ubicaciones = <?php echo json_encode($datosMapaCalor); ?>;

// Preparar datos para el mapa de calor
const heatData = [];
const markers = [];

ubicaciones.forEach(function(ubicacion) {
    const lat = parseFloat(ubicacion.latitud);
    const lng = parseFloat(ubicacion.longitud);
    
    if (!isNaN(lat) && !isNaN(lng)) {
        // Agregar a mapa de calor
        heatData.push([lat, lng, 1]);
        
        // Crear marcador
        const marker = L.marker([lat, lng]).addTo(mapa);
        marker.bindPopup(`
            <div class="p-2">
                <h6 class="mb-1">${ubicacion.nombre_completo}</h6>
                <p class="mb-1 small"><strong>Sección:</strong> ${ubicacion.seccion_electoral}</p>
                <p class="mb-0 small text-muted">${new Date(ubicacion.created_at).toLocaleDateString()}</p>
            </div>
        `);
        markers.push(marker);
    }
});

// Agregar capa de mapa de calor
if (heatData.length > 0) {
    L.heatLayer(heatData, {
        radius: 25,
        blur: 35,
        maxZoom: 17,
        gradient: {
            0.0: 'blue',
            0.5: 'lime',
            0.7: 'yellow',
            0.9: 'orange',
            1.0: 'red'
        }
    }).addTo(mapa);
    
    // Ajustar vista para mostrar todos los marcadores
    const group = L.featureGroup(markers);
    mapa.fitBounds(group.getBounds().pad(0.1));
}
</script>

<?php include __DIR__ . '/../app/views/layouts/footer.php'; ?>
