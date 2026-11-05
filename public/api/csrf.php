<?php
/**
 * Token CSRF vigente para la sesión actual. Lo usa la captura móvil al sincronizar,
 * porque la página pudo abrirse hace horas desde la memoria del teléfono.
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../app/helpers/security.php';
require_once __DIR__ . '/../../app/controllers/AuthController.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$auth = new AuthController();
if (!$auth->estaAutenticado()) {
    http_response_code(401);
    echo json_encode(['error' => 'Su sesión expiró. Inicie sesión para sincronizar.']);
    exit;
}
echo json_encode(['token' => csrf_token()]);
