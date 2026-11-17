<?php
/**
 * Eventos y avisos para la ciudadanía, y solicitudes de quienes piden sumarse como simpatizantes.
 * Permisos: super admin, admin y coordinador.
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../app/helpers/security.php';
require_once __DIR__ . '/../../app/controllers/AuthController.php';
require_once __DIR__ . '/../../app/services/CiudadanoAuthService.php';
require_once __DIR__ . '/../../app/models/Evento.php';
require_once __DIR__ . '/../../app/models/Ciudadano.php';
require_once __DIR__ . '/../../app/models/LogAuditoria.php';

$auth = new AuthController();
$auth->requiereRol(['super_admin', 'admin', 'coordinador']);

$disponible = (new CiudadanoAuthService())->disponible();
$modelo = new Evento();
$log = new LogAuditoria();
$yo = (int)$auth->obtenerUsuarioId();

if ($disponible && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_proteger();
    $id = isset($_POST['id']) && ctype_digit((string)$_POST['id']) ? (int)$_POST['id'] : 0;
    switch ($_POST['accion'] ?? '') {
        case 'crear_evento':
            $r = $modelo->crear($_POST, $yo);
            if (!empty($r['success'])) { $log->registrar($yo, 'crear_evento', 'eventos', $r['id'], null, ['titulo' => $_POST['titulo'] ?? '']); $_SESSION['success'] = 'Evento publicado: ya lo ve la ciudadanía en la app.'; }
            else { $_SESSION['error'] = $r['error']; }
            break;
        case 'publicar_evento':
            $modelo->publicar($id, !empty($_POST['publicar']));
            $_SESSION['success'] = !empty($_POST['publicar']) ? 'Evento publicado.' : 'Evento ocultado.';
            break;
        case 'eliminar_evento':
            $modelo->eliminar($id);
            $log->registrar($yo, 'eliminar_evento', 'eventos', $id);
            $_SESSION['success'] = 'Evento eliminado.';
            break;
        case 'crear_aviso':
            $r = $modelo->crearAviso($_POST, $yo);
            if (!empty($r['success'])) { $log->registrar($yo, 'crear_aviso', 'avisos', null, null, ['titulo' => $_POST['titulo'] ?? '']); $_SESSION['success'] = 'Aviso publicado.'; }
            else { $_SESSION['error'] = $r['error']; }
            break;
        case 'eliminar_aviso':
            $modelo->eliminarAviso($id);
            $_SESSION['success'] = 'Aviso eliminado.';
            break;
    }
    header('Location: eventos.php');
    exit;
}

$eventos = $disponible ? $modelo->todos() : [];
$avisos = $disponible ? $modelo->avisos() : [];
$solicitudes = $disponible ? (new Ciudadano())->quierenSumarse() : [];
$ahora = date('Y-m-d H:i:s');

$pageTitle = 'Eventos y avisos';
include __DIR__ . '/../../app/views/layouts/header.php';
?>
<div class="container-fluid p-0">
    <div class="page-head">
        <div>
            <div class="eyebrow">Atención ciudadana</div>
            <h1>Eventos y avisos</h1>
            <div class="meta"><span><i class="bi bi-phone"></i>Lo que publiques aquí aparece en la app de la ciudadanía</span></div>
        </div>
        <a href="index.php" class="btn btn-soft"><i class="bi bi-arrow-left me-2"></i>Volver a reportes</a>
    </div>

<?php if (!$disponible): ?>
    <div class="alert alert-warning">Falta ejecutar <code>database/atencion_ciudadana.sql</code> para activar esta sección.</div>
<?php else: ?>
    <div class="row g-3">
        <div class="col-xl-7">
            <div class="card mb-3">
                <div class="card-header"><h5 class="mb-0"><i class="bi bi-calendar-event me-2"></i>Eventos</h5></div>
                <?php if (empty($eventos)): ?>
                    <div class="card-body"><div class="empty-state"><i class="bi bi-calendar-event"></i><p class="mt-3 mb-0">Aún no hay eventos. Publica el primero con el formulario de abajo.</p></div></div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead><tr><th>Evento</th><th>Cuándo</th><th>Zona</th><th>Asistirán</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($eventos as $e): $pasado = $e['fecha_inicio'] < $ahora; ?>
                            <tr style="<?php echo $pasado || !$e['publicado'] ? 'opacity:.6' : ''; ?>">
                                <td><div class="fw-semibold"><?php echo htmlspecialchars($e['titulo']); ?></div><div class="small text-muted"><?php echo htmlspecialchars($e['lugar']); ?></div>
                                    <?php if (!$e['publicado']): ?><span class="status-pill off">Oculto</span><?php elseif ($pasado): ?><span class="status-pill off">Ya pasó</span><?php endif; ?></td>
                                <td class="small text-nowrap"><?php echo date('d/m/Y H:i', strtotime($e['fecha_inicio'])); ?></td>
                                <td class="small"><?php echo htmlspecialchars($e['colonia'] ?: ($e['municipio'] ?: 'Todos')); ?></td>
                                <td><?php echo (int)$e['asistentes']; ?></td>
                                <td class="text-end text-nowrap">
                                    <form method="POST" action="" class="d-inline"><?php echo csrf_field(); ?><input type="hidden" name="accion" value="publicar_evento"><input type="hidden" name="id" value="<?php echo (int)$e['id']; ?>"><input type="hidden" name="publicar" value="<?php echo $e['publicado'] ? '' : '1'; ?>">
                                        <button class="icon-btn" title="<?php echo $e['publicado'] ? 'Ocultar de la app' : 'Publicar'; ?>"><i class="bi bi-<?php echo $e['publicado'] ? 'eye-slash' : 'eye'; ?>"></i></button></form>
                                    <form method="POST" action="" class="d-inline" onsubmit="return confirm('¿Eliminar este evento?');"><?php echo csrf_field(); ?><input type="hidden" name="accion" value="eliminar_evento"><input type="hidden" name="id" value="<?php echo (int)$e['id']; ?>"><button class="icon-btn danger" title="Eliminar"><i class="bi bi-trash"></i></button></form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>

            <div class="card">
                <div class="card-header"><h5 class="mb-0"><i class="bi bi-plus-circle me-2"></i>Nuevo evento</h5></div>
                <div class="card-body">
                    <form method="POST" action="" class="row g-3">
                        <?php echo csrf_field(); ?><input type="hidden" name="accion" value="crear_evento">
                        <div class="col-md-8"><label class="form-label small text-muted mb-1" for="evTitulo">Título</label><input class="form-control" name="titulo" id="evTitulo" maxlength="150" required></div>
                        <div class="col-md-4"><label class="form-label small text-muted mb-1" for="evFecha">Fecha y hora</label><input type="datetime-local" class="form-control" name="fecha_inicio" id="evFecha" required></div>
                        <div class="col-12"><label class="form-label small text-muted mb-1" for="evLugar">Lugar</label><input class="form-control" name="lugar" id="evLugar" maxlength="200" required placeholder="Ej. Parque Villas del Sol"></div>
                        <div class="col-12"><label class="form-label small text-muted mb-1" for="evDesc">Descripción (opcional)</label><textarea class="form-control" name="descripcion" id="evDesc" rows="2" maxlength="2000"></textarea></div>
                        <div class="col-md-6"><label class="form-label small text-muted mb-1" for="evMuni">Municipio (vacío = para todos)</label><input class="form-control" name="municipio" id="evMuni" maxlength="100"></div>
                        <div class="col-md-6"><label class="form-label small text-muted mb-1" for="evCol">Colonia (vacío = todo el municipio)</label><input class="form-control" name="colonia" id="evCol" maxlength="100"></div>
                        <div class="col-12"><button class="btn btn-primary"><i class="bi bi-megaphone me-2"></i>Publicar evento</button></div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-xl-5">
            <div class="card mb-3">
                <div class="card-header"><h5 class="mb-0"><i class="bi bi-bell me-2"></i>Avisos</h5></div>
                <div class="card-body">
                    <?php foreach ($avisos as $a): ?>
                        <div class="d-flex justify-content-between gap-2 mb-3">
                            <div><div class="fw-semibold"><?php echo htmlspecialchars($a['titulo']); ?></div><div class="small"><?php echo htmlspecialchars($a['texto']); ?></div>
                                <div class="small text-muted"><?php echo htmlspecialchars($a['colonia'] ?: ($a['municipio'] ?: 'Todos')); ?><?php echo $a['vence_at'] ? ' · vence ' . date('d/m/Y', strtotime($a['vence_at'])) : ''; ?></div></div>
                            <form method="POST" action="" onsubmit="return confirm('¿Eliminar este aviso?');"><?php echo csrf_field(); ?><input type="hidden" name="accion" value="eliminar_aviso"><input type="hidden" name="id" value="<?php echo (int)$a['id']; ?>"><button class="icon-btn danger" title="Eliminar"><i class="bi bi-trash"></i></button></form>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($avisos)): ?><p class="text-muted small">Sin avisos publicados.</p><?php endif; ?>
                    <hr>
                    <form method="POST" action="" class="row g-2">
                        <?php echo csrf_field(); ?><input type="hidden" name="accion" value="crear_aviso">
                        <div class="col-12"><input class="form-control" name="titulo" maxlength="150" placeholder="Título del aviso" required aria-label="Título del aviso"></div>
                        <div class="col-12"><textarea class="form-control" name="texto" rows="2" maxlength="500" placeholder="Texto (máximo 500 caracteres)" required aria-label="Texto del aviso"></textarea></div>
                        <div class="col-md-4"><input class="form-control" name="municipio" maxlength="100" placeholder="Municipio" aria-label="Municipio"></div>
                        <div class="col-md-4"><input class="form-control" name="colonia" maxlength="100" placeholder="Colonia" aria-label="Colonia"></div>
                        <div class="col-md-4"><input type="date" class="form-control" name="vence" aria-label="Fecha en que vence"></div>
                        <div class="col-12"><button class="btn btn-primary btn-sm"><i class="bi bi-send me-1"></i>Publicar aviso</button></div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h5 class="mb-0"><i class="bi bi-person-plus me-2"></i>Quieren sumarse</h5></div>
                <div class="card-body">
                    <?php if (empty($solicitudes)): ?><p class="text-muted small mb-0">Nadie ha pedido sumarse todavía.</p><?php endif; ?>
                    <?php foreach ($solicitudes as $s): ?>
                        <div class="mb-2"><div class="fw-semibold"><?php echo htmlspecialchars($s['nombre']); ?></div>
                            <div class="small text-muted"><?php echo htmlspecialchars($s['correo']); ?> · <?php echo htmlspecialchars($s['colonia'] ?: ($s['municipio'] ?: 'Sin zona')); ?> · <?php echo date('d/m/Y', strtotime($s['sumarse_at'])); ?></div></div>
                    <?php endforeach; ?>
                    <?php if ($solicitudes): ?><p class="small text-muted mt-3 mb-0">Contáctalas y, si aceptan, regístralas como simpatizantes desde Simpatizantes &gt; Nuevo.</p><?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
</div>
<?php include __DIR__ . '/../../app/views/layouts/footer.php'; ?>
