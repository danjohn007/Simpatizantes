<?php
/**
 * Anota un contacto (por ejemplo, al abrir WhatsApp desde la lista de comunicación).
 * POST JSON + cabecera X-CSRF-Token. Solo sobre simpatizantes a los que el usuario tiene acceso.
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../app/helpers/security.php';
require_once __DIR__ . '/../../app/controllers/AuthController.php';
require_once __DIR__ . '/../../app/controllers/SimpatizanteController.php';
require_once __DIR__ . '/../../app/models/Contacto.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function contacto_responder($codigo, $cuerpo) {
    http_response_code($codigo);
    echo json_encode($cuerpo, JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    contacto_responder(405, ['ok' => false, 'error' => 'Método no permitido']);
}
$auth = new AuthController();
if (!$auth->estaAutenticado()) {
    contacto_responder(401, ['ok' => false, 'error' => 'Su sesión expiró.']);
}
csrf_proteger();

$in = json_decode((string)file_get_contents('php://input', false, null, 0, 8192), true);
if (!is_array($in) || !isset($in['simpatizante_id']) || !ctype_digit((string)$in['simpatizante_id'])) {
    contacto_responder(422, ['ok' => false, 'error' => 'Datos no válidos']);
}

$contacto = new Contacto();
if (!$contacto->disponible()) {
    contacto_responder(503, ['ok' => false, 'error' => 'Falta ejecutar la migración del Sprint 5.']);
}

// Control de acceso: el mismo que para ver al simpatizante (capturista: solo los suyos; coordinador: su campaña)
$simp = (new SimpatizanteController())->obtener((int)$in['simpatizante_id']);
if (!$simp || isset($simp['error'])) {
    contacto_responder(403, ['ok' => false, 'error' => 'No tiene acceso a este simpatizante']);
}
// A quien pidió no recibir mensajes no se le escribe
if (!empty($simp['baja_comunicacion']) && ($in['canal'] ?? '') === 'whatsapp') {
    contacto_responder(409, ['ok' => false, 'error' => 'Esta persona pidió no recibir mensajes']);
}

$res = $contacto->registrar(
    (int)$in['simpatizante_id'], (int)$auth->obtenerUsuarioId(),
    (string)($in['canal'] ?? ''), (string)($in['resultado'] ?? ''),
    isset($in['nota']) && is_string($in['nota']) ? $in['nota'] : '',
    isset($in['plantilla']) && is_string($in['plantilla']) && preg_match('/^[a-z0-9_]{1,50}$/', $in['plantilla']) ? $in['plantilla'] : null
);
if (empty($res['success'])) {
    contacto_responder(422, ['ok' => false, 'error' => $res['error'] ?? 'No se pudo registrar']);
}
contacto_responder(200, ['ok' => true, 'id' => (int)$res['id']]);
