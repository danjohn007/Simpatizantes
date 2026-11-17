<?php
/**
 * POST /public/api/app/ciudadano-registro.php
 * Cuerpo JSON: { nombre, correo, password, municipio?, colonia?, acepto: true, dispositivo?, plataforma? }
 * Crea la cuenta de ciudadano e inicia sesión. Respuesta: { ok, tipo, token, ciudadano }.
 */

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../../app/models/Ciudadano.php';

app_metodo('POST');
app_exigir_version();

$auth = new CiudadanoAuthService();
if (!$auth->disponible()) {
    app_json(503, ['ok' => false, 'error' => 'Falta ejecutar database/atencion_ciudadana.sql en el servidor.']);
}

$in = app_cuerpo();
$modelo = new Ciudadano();
$res = $modelo->registrar($in, app_ip());
if (empty($res['success'])) {
    app_json(422, ['ok' => false, 'error' => $res['error']]);
}
$token = $auth->emitir($res['id'], $in['dispositivo'] ?? '', $in['plataforma'] ?? '', $_SERVER['HTTP_X_APP_VERSION'] ?? '');
if (!$token) {
    app_json(503, ['ok' => false, 'error' => 'La cuenta se creó, pero no se pudo iniciar la sesión. Inicie sesión con su correo.']);
}
$c = $modelo->obtener($res['id']);
app_json(200, ['ok' => true, 'tipo' => 'ciudadano', 'token' => $token, 'ciudadano' => [
    'id' => (int)$c['id'], 'nombre' => $c['nombre'], 'correo' => $c['correo'], 'municipio' => $c['municipio'], 'colonia' => $c['colonia'],
]]);
