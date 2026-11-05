<?php
/**
 * Revisión en vivo de un simpatizante mientras se captura: formato de CURP y clave,
 * duplicados y diferencias entre datos. Solo lectura; devuelve JSON.
 *
 * POST: campos del formulario (+ id si se está editando, + csrf_token).
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../app/helpers/security.php';
require_once __DIR__ . '/../../app/controllers/AuthController.php';
require_once __DIR__ . '/../../app/controllers/SimpatizanteController.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

$auth = new AuthController();
if (!$auth->estaAutenticado()) {
    http_response_code(401);
    echo json_encode(['error' => 'Su sesión expiró. Inicie sesión de nuevo.']);
    exit;
}
csrf_proteger();

$campos = ['nombre_completo', 'sexo', 'fecha_nacimiento', 'clave_elector', 'curp', 'seccion_electoral', 'telefono', 'whatsapp', 'email'];
$datos = [];
foreach ($campos as $c) {
    $datos[$c] = isset($_POST[$c]) && is_string($_POST[$c]) ? mb_substr($_POST[$c], 0, 200) : '';
}
$excluir = isset($_POST['id']) && ctype_digit((string)$_POST['id']) ? (int)$_POST['id'] : null;

$controller = new SimpatizanteController();
echo json_encode($controller->revisarCalidad($datos, $excluir), JSON_UNESCAPED_UNICODE);
