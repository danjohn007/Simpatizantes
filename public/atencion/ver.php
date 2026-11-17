<?php
/**
 * Atención de un reporte o denuncia: ver los datos, cambiar el estado con un mensaje para la persona,
 * asignarlo a alguien del equipo y consultar el historial.
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../app/helpers/security.php';
require_once __DIR__ . '/../../app/controllers/AuthController.php';
require_once __DIR__ . '/../../app/services/CiudadanoAuthService.php';
require_once __DIR__ . '/../../app/models/ReporteCiudadano.php';
require_once __DIR__ . '/../../app/models/LogAuditoria.php';

$auth = new AuthController();
$auth->requiereRol(['super_admin', 'admin', 'coordinador']);

$modelo = new ReporteCiudadano();
$id = isset($_GET['id']) && ctype_digit((string)$_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) && ctype_digit((string)$_POST['id']) ? (int)$_POST['id'] : 0);
$reporte = (new CiudadanoAuthService())->disponible() && $id ? $modelo->detalle($id) : null;
if (!$reporte) {
    $_SESSION['error'] = 'Reporte no encontrado.';
    header('Location: index.php');
    exit;
}
$yo = (int)$auth->obtenerUsuarioId();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_proteger();
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'estado') {
        $res = $modelo->cambiarEstado($id, (string)($_POST['estado'] ?? ''), (string)($_POST['mensaje'] ?? ''), $yo);
        if (!empty($res['success'])) {
            // Quien cambia el estado se queda con el caso si no tenía responsable
            if (empty($reporte['asignado_a'])) { $modelo->asignar($id, $yo); }
            (new LogAuditoria())->registrar($yo, 'atender_reporte_ciudadano', 'reportes_ciudadanos', $id, ['estado' => $res['anterior']], ['estado' => $_POST['estado'], 'mensaje' => $_POST['mensaje'] ?? null]);
            $_SESSION['success'] = 'Reporte actualizado. La persona verá tu mensaje en la app.';
        } else {
            $_SESSION['error'] = $res['error'];
        }
    } elseif ($accion === 'tomar') {
        $modelo->asignar($id, $yo);
        $_SESSION['success'] = 'Ahora tú atiendes este reporte.';
    }
    header('Location: ver.php?id=' . $id);
    exit;
}

$historial = $modelo->historialCompleto($id);
$fotos = $modelo->archivos($id);
$clasePill = ['recibido' => 'pend', 'revision' => 'pend', 'atendido' => 'on', 'cerrado' => 'off'];
$tieneUbicacion = $reporte['latitud'] !== null && $reporte['longitud'] !== null;

$pageTitle = 'Reporte ' . $reporte['folio'];
include __DIR__ . '/../../app/views/layouts/header.php';
?>
<div class="container-fluid p-0">
    <div class="page-head">
        <div>
            <div class="eyebrow">Atención ciudadana</div>
            <h1><?php echo htmlspecialchars($reporte['folio']); ?> · <?php echo htmlspecialchars(ReporteCiudadano::CATEGORIAS[$reporte['categoria']] ?? $reporte['categoria']); ?></h1>
            <div class="meta">
                <span class="status-pill <?php echo $clasePill[$reporte['estado']]; ?>"><?php echo ReporteCiudadano::ESTADOS[$reporte['estado']]; ?></span>
                <?php if ($reporte['tipo'] === 'denuncia'): ?><span class="status-pill bad"><?php echo $reporte['anonimo'] ? 'Denuncia anónima' : 'Denuncia'; ?></span><?php endif; ?>
                <span><i class="bi bi-calendar3"></i><?php echo date('d/m/Y H:i', strtotime($reporte['created_at'])); ?></span>
            </div>
        </div>
        <a href="index.php" class="btn btn-soft"><i class="bi bi-arrow-left me-2"></i>Volver</a>
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card mb-3">
                <div class="card-header"><h5 class="mb-0"><i class="bi bi-card-text me-2"></i>Lo que reportó</h5></div>
                <div class="card-body">
                    <p style="white-space:pre-wrap"><?php echo htmlspecialchars($reporte['descripcion']); ?></p>
                    <dl class="dl-grid">
                        <div><dt>Quién</dt><dd><?php echo $reporte['anonimo'] ? 'Anónimo (no se guardó quién fue)' : htmlspecialchars($reporte['ciudadano_nombre'] ?? 'Cuenta eliminada'); ?></dd></div>
                        <div><dt>Contacto</dt><dd><?php echo !empty($reporte['ciudadano_correo']) ? htmlspecialchars($reporte['ciudadano_correo']) : '—'; ?></dd></div>
                        <div><dt>Municipio</dt><dd><?php echo htmlspecialchars($reporte['municipio'] ?? '—'); ?></dd></div>
                        <div><dt>Colonia</dt><dd><?php echo htmlspecialchars($reporte['colonia'] ?? '—'); ?></dd></div>
                        <div class="full"><dt>Ubicación</dt>
                            <?php if ($tieneUbicacion): $lat = (float)$reporte['latitud']; $lng = (float)$reporte['longitud']; ?>
                                <dd><a href="https://www.openstreetmap.org/?mlat=<?php echo $lat; ?>&amp;mlon=<?php echo $lng; ?>#map=17/<?php echo $lat; ?>/<?php echo $lng; ?>" target="_blank" rel="noopener"><i class="bi bi-geo-alt me-1"></i>Ver en el mapa</a></dd>
                            <?php else: ?><dd class="empty">—</dd><?php endif; ?>
                        </div>
                    </dl>
                    <?php if ($fotos): ?>
                        <div class="row g-2 mt-1">
                            <?php foreach ($fotos as $a): $src = BASE_URL . '/public/archivo.php?f=' . urlencode($a['ruta']); ?>
                                <div class="col-4"><a class="doc-tile" href="<?php echo $src; ?>" target="_blank" rel="noopener"><img src="<?php echo $src; ?>" alt="Foto del reporte" loading="lazy"><div class="cap"><span>Foto</span><i class="bi bi-box-arrow-up-right"></i></div></a></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card mb-3">
                <div class="card-header"><h5 class="mb-0"><i class="bi bi-reply me-2"></i>Atender</h5></div>
                <div class="card-body">
                    <form method="POST" action="">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="id" value="<?php echo $id; ?>">
                        <input type="hidden" name="accion" value="estado">
                        <div class="mb-3">
                            <label class="form-label small text-muted mb-1" for="estado">Estado</label>
                            <select class="form-select" name="estado" id="estado">
                                <?php foreach (ReporteCiudadano::ESTADOS as $k => $t): ?><option value="<?php echo $k; ?>" <?php echo $reporte['estado'] === $k ? 'selected' : ''; ?>><?php echo $t; ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small text-muted mb-1" for="mensaje">Mensaje para la persona (lo verá en la app)</label>
                            <textarea class="form-control" name="mensaje" id="mensaje" rows="3" maxlength="500" placeholder="Ej. Ya se programó la reparación para esta semana."></textarea>
                        </div>
                        <button class="btn btn-primary"><i class="bi bi-check-lg me-2"></i>Guardar</button>
                    </form>
                    <hr>
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="small"><span class="text-muted">Atiende:</span> <strong><?php echo htmlspecialchars($reporte['asignado_nombre'] ?? 'Nadie todavía'); ?></strong></div>
                        <?php if ((int)($reporte['asignado_a'] ?? 0) !== $yo): ?>
                            <form method="POST" action=""><?php echo csrf_field(); ?><input type="hidden" name="id" value="<?php echo $id; ?>"><input type="hidden" name="accion" value="tomar"><button class="btn btn-soft btn-sm">Atenderlo yo</button></form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Historial</h5></div>
                <div class="card-body">
                    <ul class="hist-list">
                    <?php foreach ($historial as $h): ?>
                        <li>
                            <div><strong><?php echo ReporteCiudadano::ESTADOS[$h['estado_nuevo']] ?? $h['estado_nuevo']; ?></strong>
                                <?php if (!empty($h['estado_anterior'])): ?><span class="small text-muted">(antes: <?php echo ReporteCiudadano::ESTADOS[$h['estado_anterior']] ?? $h['estado_anterior']; ?>)</span><?php endif; ?></div>
                            <div class="quien"><?php echo htmlspecialchars($h['usuario_nombre'] ?? 'Sistema'); ?> · <?php echo date('d/m/Y H:i', strtotime($h['created_at'])); ?></div>
                            <?php if (!empty($h['mensaje'])): ?><div class="motivo"><?php echo htmlspecialchars($h['mensaje']); ?></div><?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../../app/views/layouts/footer.php'; ?>
