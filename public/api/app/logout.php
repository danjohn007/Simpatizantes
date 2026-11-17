<?php
/** POST /public/api/app/logout.php — revoca el token de este dispositivo. */

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../../app/models/LogAuditoria.php';

app_metodo('POST');
$servicio = new AppAuthService();
$token = app_token();
$usuario = $token ? $servicio->validar($token) : null;
if ($usuario) {
    $servicio->revocar($token);
    (new LogAuditoria())->registrar($usuario['id'], 'logout_app', 'usuarios', $usuario['id']);
}
if ($token) { (new CiudadanoAuthService())->disponible() && (new CiudadanoAuthService())->revocar($token); }
// Siempre éxito: si el token ya no valía, la sesión ya estaba cerrada
app_json(200, ['ok' => true]);
