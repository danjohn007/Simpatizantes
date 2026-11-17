<?php
/**
 * POST /public/api/app/seguimiento.php   { folio, codigo }
 * Consulta el avance de una denuncia anónima con el folio y el código que se entregaron al enviarla. No requiere cuenta.
 */

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../../app/models/ReporteCiudadano.php';

app_metodo('POST');
app_exigir_version();
if (!(new CiudadanoAuthService())->disponible()) {
    app_json(503, ['ok' => false, 'error' => 'Falta ejecutar database/atencion_ciudadana.sql en el servidor.']);
}
$in = app_cuerpo();
$modelo = new ReporteCiudadano();
$r = $modelo->obtenerPorFolio((string)($in['folio'] ?? ''));
if (!$r || !$modelo->codigoValido($r, (string)($in['codigo'] ?? ''))) {
    usleep(300000);
    app_json(404, ['ok' => false, 'error' => 'El folio o el código no coinciden.']);
}
app_json(200, ['ok' => true, 'reporte' => [
    'folio' => $r['folio'], 'categoria_nombre' => ReporteCiudadano::CATEGORIAS[$r['categoria']] ?? $r['categoria'],
    'estado' => $r['estado'], 'fecha' => $r['created_at'], 'historial' => $modelo->historialPublico($r['id']),
]]);
