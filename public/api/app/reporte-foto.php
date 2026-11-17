<?php
/**
 * Fotos de un reporte.
 *
 * POST (multipart) reporte-foto.php   campos: folio, codigo? (solo anónimos), archivo: foto
 *      Se aceptan JPEG y PNG (hasta 6 MB, máximo tres por reporte). Se eliminan los metadatos (GPS, fecha, modelo del teléfono).
 * GET  reporte-foto.php?id=N[&codigo=XXXX]  → la imagen, solo para quien envió el reporte (o con el código si es anónimo)
 */

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../../app/helpers/imagenes.php';
require_once __DIR__ . '/../../../app/models/ReporteCiudadano.php';

const FOTO_MAX_BYTES = 6291456;

$modelo = new ReporteCiudadano();
$sesion = app_autenticar_ciudadano(false);

/** ¿Esta persona puede tocar este reporte? (dueño con sesión, o dueño anónimo con su código) */
function foto_autorizado(array $reporte, $sesion, ReporteCiudadano $m, $codigo) {
    if ($sesion && !empty($reporte['ciudadano_id']) && (int)$reporte['ciudadano_id'] === (int)$sesion[0]['id']) { return true; }
    return !empty($reporte['anonimo']) && $m->codigoValido($reporte, (string)$codigo);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
    $a = $modelo->archivo((int)($_GET['id'] ?? 0));
    $reporte = $a ? $modelo->obtener($a['reporte_id']) : null;
    if (!$a || !$reporte || !foto_autorizado($reporte, $sesion, $modelo, $_GET['codigo'] ?? '')) {
        http_response_code(404);
        exit;
    }
    $ruta = realpath(PUBLIC_PATH . '/' . $a['ruta']);
    $base = realpath(UPLOAD_PATH . '/reportes');
    if ($ruta === false || $base === false || strpos($ruta, $base . DIRECTORY_SEPARATOR) !== 0 || !is_file($ruta)) {
        http_response_code(404);
        exit;
    }
    header_remove('Content-Type');
    header('Content-Type: ' . $a['mime']);
    header('Content-Length: ' . filesize($ruta));
    header('Cache-Control: private, max-age=86400');
    readfile($ruta);
    exit;
}

app_metodo('POST');
if (!(new CiudadanoAuthService())->disponible()) {
    app_json(503, ['ok' => false, 'error' => 'Falta ejecutar database/atencion_ciudadana.sql en el servidor.']);
}
app_exigir_version();

$reporte = $modelo->obtenerPorFolio((string)($_POST['folio'] ?? ''));
if (!$reporte || !foto_autorizado($reporte, $sesion, $modelo, $_POST['codigo'] ?? '')) {
    app_json(404, ['ok' => false, 'error' => 'No encontramos ese reporte.']);
}
if ($modelo->contarFotos($reporte['id']) >= ReporteCiudadano::MAX_FOTOS) {
    app_json(422, ['ok' => false, 'error' => 'Un reporte admite hasta ' . ReporteCiudadano::MAX_FOTOS . ' fotos.']);
}
$f = $_FILES['foto'] ?? null;
if (!$f || $f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
    app_json(422, ['ok' => false, 'error' => 'No se recibió la foto.']);
}
if ($f['size'] > FOTO_MAX_BYTES) {
    app_json(422, ['ok' => false, 'error' => 'La foto pesa demasiado (máximo 6 MB).']);
}
$img = imagen_sanear($f['tmp_name']);
if (!$img) {
    app_json(422, ['ok' => false, 'error' => 'Solo se aceptan fotos JPG o PNG.']);
}

$dir = UPLOAD_PATH . '/reportes';
if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
    app_json(503, ['ok' => false, 'error' => 'No se pudo guardar la foto en el servidor.']);
}
$nombre = bin2hex(random_bytes(16)) . '.' . $img['extension'];
if (@file_put_contents($dir . '/' . $nombre, $img['contenido']) === false) {
    app_json(503, ['ok' => false, 'error' => 'No se pudo guardar la foto en el servidor.']);
}
$id = $modelo->agregarFoto($reporte['id'], 'uploads/reportes/' . $nombre, $img['mime'], strlen($img['contenido']));
app_json(200, ['ok' => true, 'id' => $id]);
