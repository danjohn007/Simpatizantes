<?php
/**
 * Acciones sobre la comunicación de UN simpatizante, desde su ficha:
 *   registrar    → anota una llamada, visita, mensaje, etc.
 *   consentimiento → registra que la persona aceptó (si) o no (no) recibir mensajes
 *   revertir_baja  → solo administradores: vuelve a permitir mensajes a quien se había dado de baja
 * Solo POST con token CSRF; el acceso al registro se valida igual que para verlo.
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../app/helpers/security.php';
require_once __DIR__ . '/../../app/controllers/AuthController.php';
require_once __DIR__ . '/../../app/controllers/SimpatizanteController.php';
require_once __DIR__ . '/../../app/models/Contacto.php';
require_once __DIR__ . '/../../app/models/LogAuditoria.php';

$auth = new AuthController();
$auth->requiereAutenticacion();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: ' . BASE_URL . '/public/simpatizantes/index.php');
    exit;
}
csrf_proteger();

$id = isset($_POST['id']) && ctype_digit((string)$_POST['id']) ? (int)$_POST['id'] : 0;
$volver = BASE_URL . '/public/simpatizantes/ver.php?id=' . $id . '#contactos';

$contacto = new Contacto();
$simp = $id ? (new SimpatizanteController())->obtener($id) : null;

if (!$contacto->disponible()) {
    $_SESSION['error'] = 'Falta ejecutar database/sprint5_comunicacion.sql en la base de datos.';
} elseif (!$simp || isset($simp['error'])) {
    $_SESSION['error'] = 'Simpatizante no encontrado o sin acceso.';
    $volver = BASE_URL . '/public/simpatizantes/index.php';
} else {
    $uid = (int)$auth->obtenerUsuarioId();
    $log = new LogAuditoria();
    switch ($_POST['accion'] ?? '') {
        case 'registrar':
            $res = $contacto->registrar($id, $uid, (string)($_POST['canal'] ?? ''), (string)($_POST['resultado'] ?? ''), (string)($_POST['nota'] ?? ''));
            if (!empty($res['success'])) { $_SESSION['success'] = 'Contacto registrado.'; }
            else { $_SESSION['error'] = $res['error']; }
            break;

        case 'consentimiento':
            $valor = $_POST['valor'] ?? '';
            if (!in_array($valor, ['si', 'no'], true)) { $_SESSION['error'] = 'Valor no válido.'; break; }
            if (!empty($simp['baja_comunicacion'])) { $_SESSION['error'] = 'La persona se dio de baja; solo un administrador puede revertirlo.'; break; }
            $contacto->fijarConsentimiento($id, $valor);
            $contacto->registrar($id, $uid, 'otro', 'otro', $valor === 'si' ? 'Aceptó recibir mensajes' : 'No acepta recibir mensajes');
            $log->registrar($uid, 'consentimiento_simpatizante', 'simpatizantes', $id, ['consentimiento' => $simp['consentimiento'] ?? null], ['consentimiento' => $valor === 'si' ? 1 : 0]);
            $_SESSION['success'] = $valor === 'si' ? 'Consentimiento registrado.' : 'Se registró que no desea recibir mensajes.';
            break;

        case 'revertir_baja':
            if (!$auth->tieneRol(['super_admin', 'admin'])) { $_SESSION['error'] = 'Solo un administrador puede revertir una baja.'; break; }
            $contacto->revertirBaja($id);
            $contacto->fijarConsentimiento($id, 'sin_registrar');
            $contacto->registrar($id, $uid, 'otro', 'otro', 'Baja revertida por un administrador; consentimiento sin registrar');
            $log->registrar($uid, 'revertir_baja_comunicacion', 'simpatizantes', $id, ['baja_comunicacion' => 1], ['baja_comunicacion' => 0]);
            $_SESSION['success'] = 'Baja revertida. El consentimiento quedó sin registrar.';
            break;

        default:
            $_SESSION['error'] = 'Acción no válida.';
    }
}

header('Location: ' . $volver);
exit;
