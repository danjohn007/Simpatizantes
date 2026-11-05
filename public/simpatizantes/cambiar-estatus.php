<?php
/**
 * Cambia el estado de validación de un simpatizante (pendiente / validado / rechazado).
 * Solo POST con token CSRF. Admin, super admin y coordinador.
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../app/helpers/security.php';
require_once __DIR__ . '/../../app/controllers/AuthController.php';
require_once __DIR__ . '/../../app/controllers/SimpatizanteController.php';

$auth = new AuthController();
$auth->requiereAutenticacion();

$volver = BASE_URL . '/public/simpatizantes/index.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: ' . $volver);
    exit;
}
csrf_proteger();

$id = isset($_POST['id']) && ctype_digit((string)$_POST['id']) ? (int)$_POST['id'] : 0;
if ($id > 0) {
    $volver = BASE_URL . '/public/simpatizantes/ver.php?id=' . $id;
}

$controller = new SimpatizanteController();
$res = $id > 0
    ? $controller->cambiarEstatus($id, (string)($_POST['estatus'] ?? ''), (string)($_POST['motivo'] ?? ''))
    : ['error' => 'Simpatizante no válido'];

if (!empty($res['success'])) {
    $etiquetas = ['pendiente' => 'pendiente de validación', 'validado' => 'validado', 'rechazado' => 'rechazado'];
    $_SESSION['success'] = 'El simpatizante quedó ' . ($etiquetas[$_POST['estatus']] ?? 'actualizado') . '.';
} else {
    $_SESSION['error'] = $res['error'] ?? 'No se pudo cambiar el estado.';
}

header('Location: ' . $volver);
exit;
