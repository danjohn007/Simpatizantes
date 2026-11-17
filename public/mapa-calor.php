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

if (!empty($_GET['estatus']) && in_array($_GET['estatus'], ['pendiente', 'validado', 'rechazado'], true)) {
    $filtros['estatus'] = $_GET['estatus'];
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

$descripcionFiltros = 'Campaña: ';
$nombreCampanaMapa = 'todas';
if (!empty($filtros['campana_id'])) {
    foreach ($campanas as $c) { if ($c && $c['id'] == $filtros['campana_id']) { $nombreCampanaMapa = $c['nombre']; } }
}
$descripcionFiltros .= $nombreCampanaMapa;
if (!empty($filtros['estatus'])) { $descripcionFiltros .= ' · Estado: ' . $filtros['estatus']; }
if (!empty($filtros['fecha_inicio']) || !empty($filtros['fecha_fin'])) {
    $descripcionFiltros .= ' · ' . ($filtros['fecha_inicio'] ?? '…') . ' a ' . ($filtros['fecha_fin'] ?? '…');
}

$pageTitle = 'Mapa de Calor';
include __DIR__ . '/../app/views/layouts/header.php';
?>

<?php
$puntosValidos = 0;
$porSeccion = [];
foreach ($datosMapaCalor as $d) {
    if (is_numeric($d['latitud']) && is_numeric($d['longitud'])) { $puntosValidos++; }
    $sec = $d['seccion_electoral'] ?? '';
    if ($sec !== '') { $porSeccion[$sec] = ($porSeccion[$sec] ?? 0) + 1; }
}
arsort($porSeccion);
$topSeccion = $porSeccion ? array_key_first($porSeccion) : null;
$topSeccionTotal = $topSeccion !== null ? $porSeccion[$topSeccion] : 0;
?>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/vendor/leaflet/leaflet.css" />
<style>
    #mapa { height: 640px; border-radius: 8px; background: #dfe5ec; }
    /* Mapa base atenuado para que el calor sea protagonista */
    #mapa .leaflet-tile-pane { filter: grayscale(.9) brightness(1.06) contrast(.88); }
    .map-panel { background: rgba(255,255,255,.96); border: 1px solid #e9e4de; border-radius: 8px; box-shadow: 0 4px 16px rgba(17,17,17,.12); padding: 12px 14px; font-size: .82rem; color: #1f1d1b; }
    .map-panel h6 { font-size: .72rem; text-transform: uppercase; letter-spacing: .08em; color: #6e655e; margin: 0 0 8px; font-weight: 700; }
    .legend-bar { height: 10px; border-radius: 5px; width: 190px; background: linear-gradient(90deg, #2f6fd0, #27b5a0, #f2d230, #f08a24, #d62d20); }
    .legend-scale { display: flex; justify-content: space-between; margin-top: 4px; color: #6e655e; font-size: .72rem; }
    .map-panel input[type=range] { width: 190px; accent-color: #b85400; }
    .seg { display: flex; border: 2px solid #d9d2cb; border-radius: 8px; overflow: hidden; }
    .seg button { flex: 1; border: 0; background: #fff; padding: 6px 8px; font-size: .78rem; font-weight: 600; color: #111; cursor: pointer; }
    .seg button + button { border-left: 2px solid #d9d2cb; }
    .seg button.on { background: #ff8000; color: #111; }
    .seg button:focus-visible { outline: 3px solid #111; outline-offset: -3px; }
    .map-hint { margin-top: 6px; font-size: .72rem; color: #6e655e; max-width: 190px; }
    .map-panel label.chk { display: flex; align-items: center; gap: 8px; margin-top: 8px; cursor: pointer; }
    .map-empty { position: absolute; inset: 0; z-index: 500; display: grid; place-items: center; pointer-events: none; }
    .map-empty > div { background: rgba(255,255,255,.95); border: 1px solid #e9e4de; border-radius: 10px; padding: 18px 24px; text-align: center; color: #6e655e; box-shadow: 0 4px 16px rgba(17,17,17,.12); }
    .map-empty i { font-size: 1.8rem; color: #d9d2cb; display: block; margin-bottom: 6px; }
    .leaflet-popup-content-wrapper { border-radius: 8px; }
    .pop-title { font-weight: 700; color: #111111; }
    .pop-num { display: inline-block; min-width: 24px; text-align: center; padding: 1px 8px; border-radius: 999px; background: #b85400; color: #fff; font-weight: 700; margin-right: 6px; }
</style>

<div class="container-fluid p-0">
    <div class="page-head">
        <div>
            <div class="eyebrow">Análisis territorial</div>
            <h1>Mapa de calor</h1>
            <div class="meta"><span><i class="bi bi-geo-alt"></i>Concentración geográfica de simpatizantes</span></div>
        </div>
    </div>

    <!-- Indicadores -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card kpi"><i class="bi bi-pin-map ico"></i>
                <div class="label">Ubicaciones registradas</div>
                <div class="value"><?php echo number_format($puntosValidos); ?></div>
                <div class="hint">Con coordenadas válidas</div></div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card kpi"><i class="bi bi-diagram-3 ico"></i>
                <div class="label">Secciones cubiertas</div>
                <div class="value"><?php echo number_format(count($porSeccion)); ?></div>
                <div class="hint">Secciones electorales distintas</div></div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card kpi"><i class="bi bi-fire ico"></i>
                <div class="label">Mayor concentración</div>
                <div class="value"><?php echo $topSeccion !== null ? 'Sec. ' . htmlspecialchars($topSeccion) : '—'; ?></div>
                <div class="hint"><?php echo $topSeccion !== null ? number_format($topSeccionTotal) . ' simpatizantes' : 'Sin datos'; ?></div></div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card kpi"><i class="bi bi-megaphone ico"></i>
                <div class="label">Campañas activas</div>
                <div class="value"><?php echo count($campanas); ?></div>
                <div class="hint">Disponibles para filtrar</div></div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="" class="row g-3 align-items-end">
                <?php if ($auth->puedeVerTodasLasCampanas()): ?>
                <div class="col-lg-3 col-md-6">
                    <label class="form-label small text-muted mb-1">Campaña</label>
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
                <div class="col-lg-2 col-md-6">
                    <label class="form-label small text-muted mb-1">Estado</label>
                    <select class="form-select" name="estatus">
                        <option value="">Todos</option>
                        <?php foreach (['validado' => 'Validados', 'pendiente' => 'Pendientes', 'rechazado' => 'Rechazados'] as $k => $t): ?>
                            <option value="<?php echo $k; ?>" <?php echo (($filtros['estatus'] ?? '') === $k) ? 'selected' : ''; ?>><?php echo $t; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-2 col-md-6">
                    <label class="form-label small text-muted mb-1">Fecha inicio</label>
                    <input type="date" class="form-control" name="fecha_inicio" value="<?php echo htmlspecialchars($_GET['fecha_inicio'] ?? ''); ?>">
                </div>
                <div class="col-lg-2 col-md-6">
                    <label class="form-label small text-muted mb-1">Fecha fin</label>
                    <input type="date" class="form-control" name="fecha_fin" value="<?php echo htmlspecialchars($_GET['fecha_fin'] ?? ''); ?>">
                </div>
                <div class="col-lg-3 col-md-12">
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-fill"><i class="bi bi-funnel me-1"></i>Filtrar</button>
                        <a href="mapa-calor.php" class="btn btn-soft" title="Limpiar filtros"><i class="bi bi-arrow-counterclockwise"></i></a>
                        <button type="button" class="btn btn-soft" id="btnImagen" title="Descargar la vista actual del mapa como imagen"><i class="bi bi-image me-1"></i>Imagen</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Mapa -->
    <div class="card mb-4">
        <div class="card-body p-2 position-relative">
            <div id="mapa"></div>
            <?php if ($puntosValidos === 0): ?>
                <div class="map-empty"><div><i class="bi bi-geo"></i>No hay ubicaciones para mostrar con estos filtros.</div></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="<?php echo BASE_URL; ?>/public/vendor/leaflet/leaflet.js"></script>
<script src="<?php echo BASE_URL; ?>/public/vendor/leaflet/leaflet-heat.js"></script>
<script>
(function () {
    const ubicaciones = <?php echo json_encode($datosMapaCalor); ?>;

    const mapa = L.map('mapa', { zoomControl: true, scrollWheelZoom: true }).setView([23.6, -102.5], 5);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        maxZoom: 18,
        crossOrigin: true
    }).addTo(mapa);

    // Agrupar por coordenadas cercanas (~11 m)
    const grupos = new Map();
    ubicaciones.forEach(u => {
        const lat = parseFloat(u.latitud), lng = parseFloat(u.longitud);
        if (isNaN(lat) || isNaN(lng)) return;
        const key = lat.toFixed(4) + ',' + lng.toFixed(4);
        if (grupos.has(key)) { const g = grupos.get(key); g.count++; g.nombres.push(u.nombre_completo); }
        else grupos.set(key, { lat, lng, count: 1, nombres: [u.nombre_completo], seccion: u.seccion_electoral });
    });
    const puntos = Array.from(grupos.values());
    if (!puntos.length) { return; }

    let radioKm = 20;
    let modo = 'auto';      // 'auto' | 'calor' | 'puntos'

    // --- Escala del calor según la densidad real de los datos ---------------------------------
    // Cuenta, para cada punto, cuántos registros hay a menos de radioKm. El 90 % de los puntos
    // queda por debajo del "rojo": así los datos densos no saturan todo el mapa.
    const latMedia = puntos.reduce((acc, p) => acc + p.lat, 0) / puntos.length;
    function calcularMax() {
        const kmPorGrado = 111.32, cosLat = Math.cos(latMedia * Math.PI / 180);
        const celdaLat = radioKm / kmPorGrado, celdaLng = radioKm / (kmPorGrado * cosLat);
        const rejilla = new Map();
        puntos.forEach(p => {
            const k = Math.floor(p.lat / celdaLat) + ',' + Math.floor(p.lng / celdaLng);
            if (!rejilla.has(k)) rejilla.set(k, []);
            rejilla.get(k).push(p);
        });
        const vecinos = puntos.map(p => {
            const cy = Math.floor(p.lat / celdaLat), cx = Math.floor(p.lng / celdaLng);
            let suma = 0;
            for (let dy = -1; dy <= 1; dy++) for (let dx = -1; dx <= 1; dx++) {
                const celda = rejilla.get((cy + dy) + ',' + (cx + dx));
                if (!celda) continue;
                celda.forEach(q => {
                    const ky = (q.lat - p.lat) * kmPorGrado, kx = (q.lng - p.lng) * kmPorGrado * cosLat;
                    if (Math.sqrt(kx * kx + ky * ky) <= radioKm) suma += q.count;
                });
            }
            return suma;
        }).sort((x, y) => x - y);
        const p90 = vecinos[Math.floor(vecinos.length * 0.9)] || 1;
        return Math.max(3, p90 * 0.45);
    }

    const heat = L.heatLayer(puntos.map(p => [p.lat, p.lng, p.count]), {
        radius: 40, blur: 24,
        max: calcularMax(),
        maxZoom: 1,            // la intensidad no depende del nivel de acercamiento
        minOpacity: 0.3,
        gradient: { 0.2: '#2f6fd0', 0.4: '#27b5a0', 0.6: '#f2d230', 0.8: '#f08a24', 1.0: '#d62d20' }
    }).addTo(mapa);

    // Radio de influencia en km -> píxeles según el zoom. Se limita el tamaño: manchas muy grandes
    // se recortan en bloques rectangulares y tapan el mapa.
    function aplicarRadio() {
        if (!mapa.hasLayer(heat)) return;
        const mpp = 156543.03392 * Math.cos(mapa.getCenter().lat * Math.PI / 180) / Math.pow(2, mapa.getZoom());
        const px = Math.max(12, Math.min(52, (radioKm * 1000) / mpp));
        heat.setOptions({ radius: px, blur: px * 0.6 });
    }

    // --- Puntos -----------------------------------------------------------------------------
    const puntosVisibles = L.layerGroup();
    puntos.forEach(p => {
        const info = p.nombres.length > 5
            ? p.nombres.slice(0, 5).join('<br>') + '<br><em>... y ' + (p.nombres.length - 5) + ' más</em>'
            : p.nombres.join('<br>');
        const html = '<div class="pop-title"><span class="pop-num">' + p.count + '</span>Simpatizante' + (p.count > 1 ? 's' : '') + '</div>' +
            '<div class="small text-muted mt-1">Sección ' + (p.seccion || '—') + '</div><hr class="my-2">' +
            '<div class="small" style="max-height:140px;overflow:auto">' + info + '</div>';
        // zona de clic (invisible) y punto visible
        L.circleMarker([p.lat, p.lng], { radius: 14, stroke: false, fillOpacity: 0 }).bindPopup(html).addTo(mapa);
        L.circleMarker([p.lat, p.lng], { radius: 4, color: '#111111', weight: 1.2, fillColor: '#ff8000', fillOpacity: .9, interactive: false, cnt: p.count }).addTo(puntosVisibles);
    });
    function radioPunto(z, cnt) {
        const base = z <= 9 ? 2.5 : z <= 10 ? 3 : z <= 12 ? 3.8 : z <= 14 ? 5 : 6.5;
        return base + Math.min(cnt - 1, 3) * 0.6;
    }

    // --- Qué capa se ve según el zoom y el modo --------------------------------------------------
    // Automático: el calor se desvanece al acercar (zoom 9 a 11.5) y quedan solo los puntos.
    function actualizarCapas() {
        const z = mapa.getZoom();
        let opCalor, verPuntos;
        if (modo === 'calor')       { opCalor = 1; verPuntos = false; }
        else if (modo === 'puntos') { opCalor = 0; verPuntos = true; }
        else { opCalor = Math.max(0, Math.min(1, (11.5 - z) / 2.5)); verPuntos = z >= 9.5; }

        if (opCalor <= 0.03) {
            if (mapa.hasLayer(heat)) mapa.removeLayer(heat);
        } else {
            if (!mapa.hasLayer(heat)) heat.addTo(mapa);
            aplicarRadio();
            if (heat._canvas) heat._canvas.style.opacity = opCalor;
        }
        if (verPuntos) {
            puntosVisibles.addTo(mapa);
            puntosVisibles.eachLayer(l => l.setRadius(radioPunto(z, l.options.cnt || 1)));
        } else {
            mapa.removeLayer(puntosVisibles);
        }
    }
    mapa.on('zoomend moveend', actualizarCapas);

    // Ajustar la vista a los datos sin acercarse de más
    mapa.fitBounds(L.latLngBounds(puntos.map(p => [p.lat, p.lng])).pad(0.25), { maxZoom: 11 });

    // --- Panel de control ---------------------------------------------------------------------
    const control = L.control({ position: 'topright' });
    control.onAdd = function () {
        const div = L.DomUtil.create('div', 'map-panel');
        div.innerHTML = '<h6>Vista</h6>' +
            '<div class="seg" id="segModo">' +
            '<button type="button" data-modo="auto" class="on">Automática</button>' +
            '<button type="button" data-modo="calor">Calor</button>' +
            '<button type="button" data-modo="puntos">Puntos</button></div>' +
            '<div class="map-hint">Al acercar, el calor se desvanece y quedan solo los puntos.</div>' +
            '<h6 style="margin-top:12px">Radio de influencia</h6>' +
            '<input type="range" id="rngRadio" min="3" max="60" step="1" value="' + radioKm + '">' +
            '<div class="legend-scale"><span>3 km</span><strong id="lblRadio">' + radioKm + ' km</strong><span>60 km</span></div>';
        L.DomEvent.disableClickPropagation(div);
        L.DomEvent.disableScrollPropagation(div);
        return div;
    };
    control.addTo(mapa);

    document.getElementById('segModo').addEventListener('click', function (e) {
        const b = e.target.closest('button[data-modo]');
        if (!b) return;
        modo = b.getAttribute('data-modo');
        this.querySelectorAll('button').forEach(x => x.classList.toggle('on', x === b));
        actualizarCapas();
    });
    let temporizador = null;
    document.getElementById('rngRadio').addEventListener('input', function () {
        radioKm = parseInt(this.value, 10);
        document.getElementById('lblRadio').textContent = radioKm + ' km';
        clearTimeout(temporizador);
        temporizador = setTimeout(function () {
            heat.setOptions({ max: calcularMax() });
            actualizarCapas();
        }, 120);
    });

    // Leyenda
    const legend = L.control({ position: 'bottomright' });
    legend.onAdd = function () {
        const div = L.DomUtil.create('div', 'map-panel');
        div.innerHTML = '<h6>Concentración</h6><div class="legend-bar"></div>' +
            '<div class="legend-scale"><span>Baja</span><span>Media</span><span>Alta</span></div>';
        return div;
    };
    legend.addTo(mapa);

    actualizarCapas();

    // ---- Descargar la vista actual como imagen (con el título, los filtros y la fecha)
    (function () {
        const btn = document.getElementById('btnImagen');
        if (!btn) return;
        function cargarLib() {
            return new Promise(function (ok, fallo) {
                if (window.html2canvas) { ok(); return; }
                const sc = document.createElement('script');
                sc.src = '<?php echo BASE_URL; ?>/public/vendor/pdf/html2canvas.min.js';
                sc.onload = ok; sc.onerror = function () { fallo(new Error('No se pudo cargar la librería de imágenes')); };
                document.head.appendChild(sc);
            });
        }
        btn.addEventListener('click', function () {
            const etiqueta = btn.innerHTML;
            btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
            cargarLib().then(function () {
                return html2canvas(document.getElementById('mapa'), { useCORS: true, backgroundColor: '#ffffff', scale: 2, logging: false });
            }).then(function (lienzo) {
                // Franja con el título y los filtros sobre la imagen del mapa
                const franja = 64 * 2;
                const salida = document.createElement('canvas');
                salida.width = lienzo.width; salida.height = lienzo.height + franja;
                const c = salida.getContext('2d');
                c.fillStyle = '#111111'; c.fillRect(0, 0, salida.width, franja);
                c.fillStyle = '#ff8000'; c.fillRect(0, franja - 6, salida.width, 6);
                c.fillStyle = '#ffffff'; c.font = 'bold 30px sans-serif'; c.fillText('Mapa de calor de simpatizantes', 28, 44);
                c.fillStyle = '#f0e6da'; c.font = '20px sans-serif';
                c.fillText(<?php echo json_encode($descripcionFiltros . ' · ' . date('d/m/Y H:i')); ?> + ' · ' + <?php echo json_encode(number_format($puntosValidos) . ' ubicaciones'); ?>, 28, 88);
                c.drawImage(lienzo, 0, franja);
                salida.toBlob(function (blob) {
                    const a = document.createElement('a');
                    a.href = URL.createObjectURL(blob); a.download = 'mapa_calor_' + new Date().toISOString().slice(0, 10) + '.png';
                    document.body.appendChild(a); a.click(); a.remove();
                    setTimeout(function () { URL.revokeObjectURL(a.href); }, 2000);
                }, 'image/png');
            }).catch(function (e) {
                alert('No se pudo generar la imagen: ' + e.message);
            }).then(function () { btn.disabled = false; btn.innerHTML = etiqueta; });
        });
    })();
})();
</script>

<?php include __DIR__ . '/../app/views/layouts/footer.php'; ?>
