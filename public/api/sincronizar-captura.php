<?php
/**
 * Recibe una captura hecha desde el celular (versión web instalable) y la registra.
 * POST JSON + cabecera X-CSRF-Token. Idempotente por `uuid`. La lógica vive en CapturaService,
 * compartida con la API de la app nativa (public/api/app/sincronizar.php).
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../app/helpers/security.php';
require_once __DIR__ . '/../../app/controllers/AuthController.php';
require_once __DIR__ . '/../../app/services/CapturaService.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function sync_responder($codigo, $cuerpo) {
    http_response_code($codigo);
    echo json_encode($cuerpo, JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    sync_responder(405, ['ok' => false, 'permanente' => false, 'error' => 'Método no permitido']);
}

$auth = new AuthController();
if (!$auth->estaAutenticado()) {
    sync_responder(401, ['ok' => false, 'permanente' => false, 'error' => 'Su sesión expiró. Inicie sesión para sincronizar.']);
}
csrf_proteger();

$in = json_decode((string)file_get_contents('php://input', false, null, 0, 65536), true);
if (!is_array($in)) {
    sync_responder(422, ['ok' => false, 'permanente' => true, 'error' => 'Datos no válidos.']);
}

[$codigo, $cuerpo] = CapturaService::procesar($auth, $in, 'app');
sync_responder($codigo, $cuerpo);
