<?php
/**
 * Recibe una captura hecha desde el celular (en línea o guardada sin conexión) y la registra.
 *
 * POST JSON + cabecera X-CSRF-Token. Es idempotente: el celular manda un `uuid` por captura;
 * si llega repetido (reintento tras perder señal) responde éxito sin crear otro registro.
 *
 * Respuestas:
 *   200 {ok:true,  id, repetido?, avisos[]}                 → guardada
 *   422 {ok:false, permanente:true, error, errores{}}       → datos inválidos o duplicado: no reintentar
 *   401/403/5xx                                             → sesión vencida o falla temporal: reintentar después
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../app/helpers/security.php';
require_once __DIR__ . '/../../app/controllers/AuthController.php';
require_once __DIR__ . '/../../app/controllers/SimpatizanteController.php';
require_once __DIR__ . '/../../app/models/Campana.php';

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

$crudo = file_get_contents('php://input', false, null, 0, 65536);
$in = json_decode((string)$crudo, true);
if (!is_array($in)) {
    sync_responder(422, ['ok' => false, 'permanente' => true, 'error' => 'Datos no válidos.']);
}

$uuid = isset($in['uuid']) && is_string($in['uuid']) && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $in['uuid'])
    ? strtolower($in['uuid']) : null;
if (!$uuid) {
    sync_responder(422, ['ok' => false, 'permanente' => true, 'error' => 'Falta el identificador de la captura.']);
}

$controller = new SimpatizanteController();
$modelo = new Simpatizante();

// Reintento de algo que ya se guardó
if ($previo = $modelo->obtenerPorUuid($uuid)) {
    sync_responder(200, ['ok' => true, 'id' => (int)$previo['id'], 'repetido' => true, 'avisos' => []]);
}

// Solo estos campos se aceptan del celular
$datos = [];
foreach (['nombre_completo', 'domicilio_completo', 'seccion_electoral', 'telefono', 'whatsapp', 'email', 'clave_elector',
          'curp', 'sexo', 'fecha_nacimiento', 'ciudad', 'latitud', 'longitud', 'observaciones'] as $c) {
    $v = $in[$c] ?? '';
    $datos[$c] = is_scalar($v) ? mb_substr((string)$v, 0, 500) : '';
}

// Campaña: quien está ligado a una campaña solo captura en ella
if ($auth->puedeVerTodasLasCampanas()) {
    $campana = (int)($in['campana_id'] ?? 0);
    $validas = array_map(function ($c) { return (int)$c['id']; }, (new Campana())->obtenerTodas(1) ?: []);
    if (!in_array($campana, $validas, true)) {
        sync_responder(422, ['ok' => false, 'permanente' => true, 'error' => 'Seleccione una campaña válida.']);
    }
} else {
    $campana = (int)$auth->obtenerCampanaId();
    if (!$campana) {
        sync_responder(422, ['ok' => false, 'permanente' => true, 'error' => 'Su usuario no tiene una campaña asignada. Avise a su coordinador.']);
    }
}
$datos['campana_id'] = $campana;
$datos['metodo_captura'] = 'app';
$datos['uuid_cliente'] = $uuid;

// Si se capturó sin señal, se deja constancia de cuándo
if (!empty($in['capturado_en']) && is_string($in['capturado_en']) && ($t = strtotime($in['capturado_en'])) && $t < time() - 600 && $t > time() - 86400 * 60) {
    $nota = 'Capturado sin conexión el ' . date('d/m/Y H:i', $t);
    $datos['observaciones'] = trim($datos['observaciones'] . ($datos['observaciones'] !== '' ? "\n" : '') . $nota);
}

try {
    $res = $controller->crear($datos);
} catch (Throwable $e) {
    error_log('sincronizar-captura: ' . $e->getMessage());
    sync_responder(500, ['ok' => false, 'permanente' => false, 'error' => 'Falla temporal del servidor.']);
}

if (!empty($res['success'])) {
    $avisos = [];
    if ($zona = $controller->avisoZona($datos['seccion_electoral'])) { $avisos[] = $zona; }
    sync_responder(200, ['ok' => true, 'id' => (int)$res['id'], 'avisos' => $avisos]);
}

// Carrera: otro reintento guardó el mismo uuid entre la consulta y el alta
if ($previo = $modelo->obtenerPorUuid($uuid)) {
    sync_responder(200, ['ok' => true, 'id' => (int)$previo['id'], 'repetido' => true, 'avisos' => []]);
}

$errores = $res['errores'] ?? [];
$mensaje = $errores ? implode(' · ', array_values($errores)) : ($res['error'] ?? 'No se pudo guardar.');
// Un error de base de datos puede ser temporal; una validación o un duplicado no
$temporal = !$errores && stripos($mensaje, 'Ya existe') === false;
sync_responder($temporal ? 503 : 422, ['ok' => false, 'permanente' => !$temporal, 'error' => $mensaje, 'errores' => $errores]);
