<?php
/**
 * Plantillas de mensajes (correo y WhatsApp) editables por administradores y coordinadores,
 * con vista previa usando datos de ejemplo.
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../app/helpers/security.php';
require_once __DIR__ . '/../../app/controllers/AuthController.php';
require_once __DIR__ . '/../../app/models/LogAuditoria.php';
require_once __DIR__ . '/../../app/services/ComunicacionService.php';

$auth = new AuthController();
$auth->requiereRol(['super_admin', 'admin', 'coordinador']);

$contacto = new Contacto();
$base = BASE_URL . '/public/comunicacion/plantillas.php';
$servicio = $contacto->disponible() ? new ComunicacionService() : null;

if ($servicio && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_proteger();
    $clave = (string)($_POST['clave'] ?? '');
    $antes = $servicio->plantilla($clave);
    $res = $servicio->guardarPlantilla($clave, $_POST['asunto'] ?? '', $_POST['cuerpo'] ?? '', (int)$auth->obtenerUsuarioId());
    if (!empty($res['success'])) {
        (new LogAuditoria())->registrar($auth->obtenerUsuarioId(), 'editar_plantilla_mensaje', 'plantillas_mensaje', null,
            ['clave' => $clave, 'asunto' => $antes['asunto'] ?? null, 'cuerpo' => $antes['cuerpo'] ?? null], ['clave' => $clave, 'asunto' => $_POST['asunto'] ?? null, 'cuerpo' => $_POST['cuerpo'] ?? null]);
        $_SESSION['success'] = 'Plantilla guardada.';
    } else {
        $_SESSION['error'] = $res['error'];
    }
    header('Location: ' . $base . '#' . rawurlencode($clave));
    exit;
}

$plantillas = $servicio ? $servicio->plantillas() : [];
$ejemplo = ['nombre_completo' => 'MARÍA FERNANDA LÓPEZ RAMÍREZ', 'seccion_electoral' => '0123', 'ciudad' => 'Querétaro', 'campana_nombre' => 'Campaña de ejemplo'];
$extra = ['capturista' => $_SESSION['nombre_completo'] ?? 'Tu capturista', 'enlace_baja' => BASE_URL . '/public/baja.php?t=ejemplo'];

$pageTitle = 'Plantillas de mensajes';
include __DIR__ . '/../../app/views/layouts/header.php';
?>
<div class="container-fluid p-0">
    <div class="page-head">
        <div>
            <div class="eyebrow">Comunicación</div>
            <h1>Plantillas de mensajes</h1>
            <div class="meta"><span><i class="bi bi-card-text"></i>Textos de correo y WhatsApp que usa el equipo</span></div>
        </div>
        <a href="index.php" class="btn btn-soft"><i class="bi bi-arrow-left me-2"></i>Volver</a>
    </div>

<?php if (!$servicio): ?>
    <div class="alert alert-warning">Falta ejecutar <code>database/sprint5_comunicacion.sql</code> para activar las plantillas.</div>
<?php else: ?>
    <div class="card mb-3">
        <div class="card-body small">
            <strong>Variables disponibles</strong> (se reemplazan con los datos de cada persona):
            <div class="d-flex flex-wrap gap-2 mt-2">
                <?php foreach (ComunicacionService::variables() as $v => $desc): ?>
                    <span class="sec-pill" title="<?php echo htmlspecialchars($desc); ?>"><?php echo htmlspecialchars($v); ?></span>
                <?php endforeach; ?>
            </div>
            <div class="mt-2 text-muted">Los correos deben incluir <code>{{enlace_baja}}</code>: es el enlace con el que la persona se da de baja.</div>
        </div>
    </div>

    <div class="row g-3">
    <?php foreach ($plantillas as $p):
        $esCorreo = $p['canal'] === 'correo';
        $vista = ComunicacionService::render($p['cuerpo'], $ejemplo, $extra);
    ?>
        <div class="col-xl-6" id="<?php echo htmlspecialchars($p['clave']); ?>">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-<?php echo $esCorreo ? 'envelope' : 'whatsapp'; ?> me-2"></i><?php echo htmlspecialchars($p['nombre']); ?></h5>
                    <span class="status-pill <?php echo $esCorreo ? 'pend' : 'on'; ?>"><?php echo $esCorreo ? 'Correo' : 'WhatsApp'; ?></span>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="clave" value="<?php echo htmlspecialchars($p['clave']); ?>">
                        <?php if ($esCorreo): ?>
                        <div class="mb-3">
                            <label class="form-label small text-muted mb-1">Asunto</label>
                            <input type="text" class="form-control" name="asunto" maxlength="150" required value="<?php echo htmlspecialchars($p['asunto'] ?? ''); ?>">
                        </div>
                        <?php endif; ?>
                        <div class="mb-3">
                            <label class="form-label small text-muted mb-1">Mensaje</label>
                            <textarea class="form-control" name="cuerpo" rows="<?php echo $esCorreo ? 9 : 5; ?>" maxlength="4000" required><?php echo htmlspecialchars($p['cuerpo']); ?></textarea>
                        </div>
                        <button class="btn btn-primary btn-sm"><i class="bi bi-check-lg me-1"></i>Guardar</button>
                    </form>
                    <hr>
                    <div class="small text-muted mb-1">Vista previa con datos de ejemplo:</div>
                    <?php if ($esCorreo): ?><div class="small mb-1"><strong>Asunto:</strong> <?php echo htmlspecialchars(ComunicacionService::render($p['asunto'], $ejemplo, $extra)); ?></div><?php endif; ?>
                    <div class="p-3 rounded small" style="background:#f5f2ee;white-space:pre-wrap"><?php echo htmlspecialchars($vista); ?></div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    </div>
<?php endif; ?>
</div>
<?php include __DIR__ . '/../../app/views/layouts/footer.php'; ?>
