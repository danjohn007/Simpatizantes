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

// Filtrar por campaña del usuario si no es admin
if (!$auth->puedeVerTodasLasCampanas()) {
    $filtros['campana_id'] = $auth->obtenerCampanaId();
} elseif (!empty($_GET['campana_id'])) {
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

// Obtener campañas según permisos
if (!$auth->puedeVerTodasLasCampanas()) {
    $campanaId = $auth->obtenerCampanaId();
    if ($campanaId) {
        $campanas = [$campanaModel->obtenerPorId($campanaId)];
    } else {
        $campanas = [];
    }
} else {
    $campanas = $campanaModel->obtenerTodas(1);
}

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
                           value="<?php echo htmlspecialchars($_GET['fecha_inicio'] ?? ''); ?>">
                </div>
                
                <div class="<?php echo $auth->puedeVerTodasLasCampanas() ? 'col-md-3' : 'col-md-4'; ?>">
                    <label class="form-label">Fecha Fin</label>
                    <input type="date" class="form-control" name="fecha_fin" 
                           value="<?php echo htmlspecialchars($_GET['fecha_fin'] ?? ''); ?>">
                </div>
                
                <div class="<?php echo $auth->puedeVerTodasLasCampanas() ? 'col-md-2' : 'col-md-4'; ?>">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-fill">
                            <i class="bi bi-search"></i> Filtrar
                        </button>
                        <a href="mapa-calor.php" class="btn btn-secondary flex-fill">
                            <i class="bi bi-arrow-clockwise"></i> Limpiar
                        </a>
                    </div>
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
// Agrupar ubicaciones por coordenadas para contar concentración
const locationMap = new Map();
const markers = [];

ubicaciones.forEach(function(ubicacion) {
    const lat = parseFloat(ubicacion.latitud);
    const lng = parseFloat(ubicacion.longitud);
    
    if (!isNaN(lat) && !isNaN(lng)) {
        // Redondear coordenadas para agrupar ubicaciones cercanas
        const latRounded = lat.toFixed(4);
        const lngRounded = lng.toFixed(4);
        const key = `${latRounded},${lngRounded}`;
        
        if (locationMap.has(key)) {
            const existing = locationMap.get(key);
            existing.count++;
            existing.nombres.push(ubicacion.nombre_completo);
        } else {
            locationMap.set(key, {
                lat: lat,
                lng: lng,
                count: 1,
                nombres: [ubicacion.nombre_completo],
                seccion: ubicacion.seccion_electoral
            });
        }
    }
});

// Crear datos del mapa de calor con intensidad basada en concentración
const heatData = [];
locationMap.forEach(function(location) {
    // La intensidad será proporcional al número de simpatizantes en ese punto
    heatData.push([location.lat, location.lng, location.count]);
    
    // Crear marcador con información de la concentración
    const marker = L.circleMarker([location.lat, location.lng], {
        radius: Math.min(5 + location.count, 15),
        fillColor: location.count > 10 ? '#dc3545' : location.count > 5 ? '#ffc107' : '#667eea',
        color: '#fff',
        weight: 1,
        opacity: 1,
        fillOpacity: 0.7
    }).addTo(mapa);
    
    const nombresLista = location.nombres.length > 5 
        ? location.nombres.slice(0, 5).join('<br>') + `<br><em>... y ${location.nombres.length - 5} más</em>`
        : location.nombres.join('<br>');
    
    marker.bindPopup(`
        <div class="p-2">
            <h6 class="mb-2">
                <span class="badge bg-primary">${location.count}</span>
                Simpatizante${location.count > 1 ? 's' : ''}
            </h6>
            <p class="mb-1 small"><strong>Sección:</strong> ${location.seccion}</p>
            <hr class="my-2">
            <div class="small" style="max-height: 150px; overflow-y: auto;">
                ${nombresLista}
            </div>
        </div>
    `);
    markers.push(marker);
});

// Agregar capa de mapa de calor con mejor configuración
if (heatData.length > 0) {
    L.heatLayer(heatData, {
        radius: 30,
        blur: 25,
        maxZoom: 13,
        max: Math.max(...heatData.map(d => d[2])), // Máximo basado en la concentración real
        gradient: {
            0.0: '#667eea',
            0.2: '#00bfff',
            0.4: '#00ff00',
            0.6: '#ffff00',
            0.8: '#ff8c00',
            1.0: '#ff0000'
        }
    }).addTo(mapa);
    
    // Ajustar vista para mostrar todos los marcadores
    const group = L.featureGroup(markers);
    mapa.fitBounds(group.getBounds().pad(0.1));
}

// Agregar leyenda del mapa de calor
const legend = L.control({position: 'bottomright'});
legend.onAdd = function(map) {
    const div = L.DomUtil.create('div', 'info legend');
    div.innerHTML = `
        <div style="background: white; padding: 10px; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.2);">
            <h6 style="margin: 0 0 5px 0; font-size: 0.9rem;"><strong>Concentración</strong></h6>
            <div style="font-size: 0.8rem;">
                <i style="background: #667eea; width: 18px; height: 18px; display: inline-block; margin-right: 5px;"></i> Baja<br>
                <i style="background: #00ff00; width: 18px; height: 18px; display: inline-block; margin-right: 5px;"></i> Media<br>
                <i style="background: #ffff00; width: 18px; height: 18px; display: inline-block; margin-right: 5px;"></i> Alta<br>
                <i style="background: #ff0000; width: 18px; height: 18px; display: inline-block; margin-right: 5px;"></i> Muy Alta
            </div>
        </div>
    `;
    return div;
};
legend.addTo(mapa);
</script>

<?php include __DIR__ . '/../app/views/layouts/footer.php'; ?>
