<?php
/**
 * Reporte semanal en PDF: descarga directa o envío de una copia al correo del usuario.
 *
 * GET  ?campana_id=&hasta=Y-m-d  → muestra el PDF (los 7 días que terminan en "hasta"; por defecto hoy)
 * POST accion=enviar             → manda el mismo PDF por correo al usuario que lo pide
 *
 * Alcance: admin y super admin eligen cualquier campaña (o todas); coordinador y candidato, solo la suya.
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../app/helpers/security.php';
require_once __DIR__ . '/../../app/controllers/AuthController.php';
require_once __DIR__ . '/../../app/models/Campana.php';
require_once __DIR__ . '/../../app/models/LogAuditoria.php';
require_once __DIR__ . '/../../app/services/ReporteSemanalService.php';

$auth = new AuthController();
$auth->requiereRol(['super_admin', 'admin', 'coordinador', 'candidato']);

$src = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;

// Campaña permitida
if ($auth->puedeVerTodasLasCampanas()) {
    $campanaId = (!empty($src['campana_id']) && ctype_digit((string)$src['campana_id'])) ? (int)$src['campana_id'] : null;
} else {
    $campanaId = (int)$auth->obtenerCampanaId();
    if (!$campanaId) {
        http_response_code(403);
        exit('Su usuario no tiene una campaña asignada.');
    }
}
$nombre = 'Todas las campañas';
if ($campanaId) {
    $c = (new Campana())->obtenerPorId($campanaId);
    if (!$c) { http_response_code(404); exit('Campaña no encontrada.'); }
    $nombre = $c['nombre'];
}

$hasta = $src['hasta'] ?? date('Y-m-d');
$d = DateTime::createFromFormat('!Y-m-d', (string)$hasta);
if (!$d || $d->format('Y-m-d') !== $hasta || $hasta > date('Y-m-d')) { $hasta = date('Y-m-d'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_proteger();
    $volver = BASE_URL . '/public/reportes/index.php' . ($campanaId && $auth->puedeVerTodasLasCampanas() ? '?campana_id=' . $campanaId : '');
    $u = Database::getInstance()->queryOne("SELECT nombre_completo, email FROM usuarios WHERE id = ?", [(int)$auth->obtenerUsuarioId()]);
    if (!$u || !filter_var($u['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = 'Su usuario no tiene un correo válido registrado.';
    } else {
        try {
            $res = ReporteSemanalService::enviarGrupo(
                ['campana_id' => $campanaId, 'nombre' => $nombre, 'usuarios' => [['email' => $u['email'], 'nombre' => $u['nombre_completo']]]], $hasta
            );
            if ($res['enviados'] > 0) {
                (new LogAuditoria())->registrar($auth->obtenerUsuarioId(), 'enviar_reporte_semanal', 'campanas', $campanaId, null, ['hasta' => $hasta, 'a' => $u['email']]);
                $_SESSION['success'] = 'Reporte enviado a ' . $u['email'] . '. Si no llega en unos minutos, revise la carpeta de spam.';
            } else {
                $_SESSION['error'] = 'El servidor no pudo enviar el correo. Revise la configuración SMTP.';
            }
        } catch (Throwable $e) {
            error_log('Reporte semanal manual: ' . $e->getMessage());
            $_SESSION['error'] = 'No se pudo generar el reporte.';
        }
    }
    header('Location: ' . $volver);
    exit;
}

try {
    $r = ReporteSemanalService::generar($campanaId, $nombre, $hasta);
} catch (Throwable $e) {
    error_log('Reporte semanal PDF: ' . $e->getMessage());
    http_response_code(500);
    exit('No se pudo generar el reporte.');
}

(new LogAuditoria())->registrar($auth->obtenerUsuarioId(), 'descargar_reporte_semanal', 'campanas', $campanaId, null, ['hasta' => $hasta]);

$archivo = 'reporte_semanal_' . $hasta . '.pdf';
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $archivo . '"');
header('Content-Length: ' . strlen($r['pdf']));
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
echo $r['pdf'];
