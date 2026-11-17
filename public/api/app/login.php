<?php
/**
 * POST /public/api/app/login.php
 * Cuerpo JSON: { usuario, password, dispositivo?, plataforma?, app_version? }
 * Respuesta:   { ok, token, expira, usuario: { id, nombre, rol, campana_id, campana } }
 *
 * Mismas cuentas y mismo bloqueo por intentos fallidos que el inicio de sesión web.
 */

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../../app/models/Usuario.php';
require_once __DIR__ . '/../../../app/models/Campana.php';
require_once __DIR__ . '/../../../app/models/LogAuditoria.php';
require_once __DIR__ . '/../../../app/models/Ciudadano.php';

app_metodo('POST');
app_exigir_version();

$servicio = new AppAuthService();
if (!$servicio->disponible()) {
    app_json(503, ['ok' => false, 'error' => 'Falta ejecutar database/app_movil.sql en el servidor.']);
}

$in = app_cuerpo();
$usuario = isset($in['usuario']) && is_string($in['usuario']) ? trim($in['usuario']) : '';
$password = isset($in['password']) && is_string($in['password']) ? $in['password'] : '';
if ($usuario === '' || $password === '' || mb_strlen($usuario) > 100 || strlen($password) > 200) {
    app_json(422, ['ok' => false, 'error' => 'Escriba su usuario y su contraseña.']);
}

$res = (new Usuario())->autenticar($usuario, $password);
if (is_array($res) && isset($res['error'])) {
    app_json(423, ['ok' => false, 'error' => $res['error'] . '. Intente más tarde.']);
}
if (!$res) {
    // No es del equipo: se intenta como cuenta de ciudadano (se entra con el correo)
    $servicioCiudadano = new CiudadanoAuthService();
    if (strpos($usuario, '@') !== false && $servicioCiudadano->disponible()) {
        $c = (new Ciudadano())->autenticar($usuario, $password);
        if (is_array($c) && isset($c['error'])) {
            app_json(423, ['ok' => false, 'error' => $c['error'] . '. Intente más tarde.']);
        }
        if (is_array($c)) {
            $token = $servicioCiudadano->emitir($c['id'], $in['dispositivo'] ?? '', $in['plataforma'] ?? '', $_SERVER['HTTP_X_APP_VERSION'] ?? '');
            if (!$token) { app_json(503, ['ok' => false, 'error' => 'No se pudo iniciar la sesión. Intente de nuevo.']); }
            app_json(200, ['ok' => true, 'tipo' => 'ciudadano', 'token' => $token, 'ciudadano' => [
                'id' => (int)$c['id'], 'nombre' => $c['nombre'], 'correo' => $c['correo'], 'municipio' => $c['municipio'], 'colonia' => $c['colonia'],
            ]]);
        }
    }
    // Mismo mensaje exista o no la cuenta, para no revelar quién está registrado
    usleep(300000);
    app_json(401, ['ok' => false, 'error' => 'Usuario o contraseña incorrectos.']);
}

$token = $servicio->emitir($res['id'], $in['dispositivo'] ?? '', $in['plataforma'] ?? '', $_SERVER['HTTP_X_APP_VERSION'] ?? ($in['app_version'] ?? ''));
if (!$token) {
    app_json(503, ['ok' => false, 'error' => 'No se pudo iniciar la sesión. Intente de nuevo.']);
}
(new LogAuditoria())->registrar($res['id'], 'login_app', 'usuarios', $res['id'], null, ['plataforma' => $in['plataforma'] ?? null, 'dispositivo' => $in['dispositivo'] ?? null]);

$campana = !empty($res['campana_id']) ? (new Campana())->obtenerPorId($res['campana_id']) : null;
app_json(200, [
    'ok' => true,
    'tipo' => 'equipo',
    'token' => $token,
    'expira' => date('c', strtotime('+' . AppAuthService::VIDA_DIAS . ' days')),
    'usuario' => [
        'id' => (int)$res['id'], 'nombre' => $res['nombre_completo'], 'rol' => $res['rol'],
        'campana_id' => $res['campana_id'] ? (int)$res['campana_id'] : null, 'campana' => $campana['nombre'] ?? null,
    ],
]);
