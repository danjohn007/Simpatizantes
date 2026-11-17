<?php
/**
 * Reportes y denuncias de la ciudadanía.
 *
 * GET  reportes.php               → lista de mis reportes
 * GET  reportes.php?folio=CC-...  → detalle de uno mío: estado, historial con respuestas del equipo y fotos
 * POST reportes.php               → crea un reporte. JSON: { categoria, descripcion, latitud?, longitud?, municipio?, colonia?, anonimo? }
 *       - Con sesión: queda ligado a la cuenta.
 *       - Denuncia con "anonimo": true: no se guarda quién la envió; la respuesta trae un `codigo` que, junto con el folio,
 *         permite consultar su avance en seguimiento.php. Ese código solo se muestra esta vez.
 */

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../../app/models/ReporteCiudadano.php';

$metodo = $_SERVER['REQUEST_METHOD'] ?? '';
$modelo = new ReporteCiudadano();

function reporte_json(array $r, ReporteCiudadano $m, $conDetalle = false) {
    $o = [
        'id' => (int)$r['id'], 'folio' => $r['folio'], 'tipo' => $r['tipo'], 'categoria' => $r['categoria'],
        'categoria_nombre' => ReporteCiudadano::CATEGORIAS[$r['categoria']] ?? $r['categoria'],
        'descripcion' => $r['descripcion'], 'estado' => $r['estado'], 'anonimo' => (bool)$r['anonimo'],
        'fecha' => $r['created_at'], 'actualizado' => $r['updated_at'] ?? null,
    ];
    if ($conDetalle) {
        $o['historial'] = $m->historialPublico($r['id']);
        $o['fotos'] = array_map(function ($a) { return (int)$a['id']; }, $m->archivos($r['id']));
        $o['latitud'] = $r['latitud'] !== null ? (float)$r['latitud'] : null;
        $o['longitud'] = $r['longitud'] !== null ? (float)$r['longitud'] : null;
    } else {
        $o['fotos'] = isset($r['fotos']) ? (int)$r['fotos'] : 0;
    }
    return $o;
}

if ($metodo === 'GET') {
    [$c] = app_autenticar_ciudadano();
    if (!empty($_GET['folio'])) {
        $r = $modelo->obtenerPorFolio((string)$_GET['folio']);
        if (!$r || (int)$r['ciudadano_id'] !== (int)$c['id']) {
            app_json(404, ['ok' => false, 'error' => 'No encontramos ese reporte.']);
        }
        app_json(200, ['ok' => true, 'reporte' => reporte_json($r, $modelo, true)]);
    }
    app_json(200, ['ok' => true, 'reportes' => array_map(function ($r) use ($modelo) { return reporte_json($r, $modelo); }, $modelo->listarDe($c['id']))]);
}

app_metodo('POST');
$sesion = app_autenticar_ciudadano(false);
$in = app_cuerpo();
if (empty($in)) {
    app_json(422, ['ok' => false, 'error' => 'Datos no válidos.']);
}
// Se completa la zona con la de la cuenta si la app no la mandó
if ($sesion) {
    $in['municipio'] = $in['municipio'] ?? $sesion[0]['municipio'];
    $in['colonia'] = $in['colonia'] ?? $sesion[0]['colonia'];
}
$res = $modelo->crear($sesion ? (int)$sesion[0]['id'] : null, $in, app_ip());
if (empty($res['success'])) {
    app_json(422, ['ok' => false, 'error' => $res['error']]);
}
app_json(200, ['ok' => true, 'id' => $res['id'], 'folio' => $res['folio'], 'codigo' => $res['codigo']]);
