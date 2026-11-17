<?php
/**
 * Comunicación: arma listas de simpatizantes (por sección, municipio, estado, consentimiento,
 * "sin contacto en X días"…), escribe por WhatsApp con un clic y envía correos a quien aceptó recibirlos.
 *
 * Alcance: admin y super admin, todo; coordinador y candidato, su campaña; capturista, solo lo suyo.
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../app/helpers/security.php';
require_once __DIR__ . '/../../app/controllers/AuthController.php';
require_once __DIR__ . '/../../app/models/Campana.php';
require_once __DIR__ . '/../../app/models/LogAuditoria.php';
require_once __DIR__ . '/../../app/services/AnaliticaService.php';
require_once __DIR__ . '/../../app/services/ComunicacionService.php';
require_once __DIR__ . '/../../app/services/HojaCalculoService.php';

$auth = new AuthController();
$auth->requiereAutenticacion();

$contactoModel = new Contacto();
$disponible = $contactoModel->disponible();
$puedeMasivo = $auth->tieneRol(['super_admin', 'admin', 'coordinador']);
$puedeVerTodas = $auth->puedeVerTodasLasCampanas();
$base = BASE_URL . '/public/comunicacion/index.php';

/** Filtros de la lista desde GET o POST, validados y limitados al alcance del usuario. */
function com_filtros(array $src, $auth) {
    $f = [];
    if (!empty($src['campana_id']) && ctype_digit((string)$src['campana_id'])) { $f['campana_id'] = (int)$src['campana_id']; }
    if (!empty($src['seccion']) && is_string($src['seccion']) && preg_match('/^\d{1,4}$/', $src['seccion'])) { $f['seccion_electoral'] = str_pad($src['seccion'], 4, '0', STR_PAD_LEFT); }
    if (!empty($src['ciudad']) && is_string($src['ciudad'])) { $f['ciudad'] = mb_substr($src['ciudad'], 0, 100); }
    if (!empty($src['estatus']) && in_array($src['estatus'], ['pendiente', 'validado', 'rechazado'], true)) { $f['estatus'] = $src['estatus']; }
    if (!empty($src['consentimiento']) && in_array($src['consentimiento'], ['si', 'sin_registrar', 'baja'], true)) { $f['consentimiento'] = $src['consentimiento']; }
    if (!empty($src['sin_contacto_dias']) && ctype_digit((string)$src['sin_contacto_dias']) && (int)$src['sin_contacto_dias'] <= 3650) { $f['sin_contacto_dias'] = (int)$src['sin_contacto_dias']; }
    if (!empty($src['con_whatsapp'])) { $f['con_whatsapp'] = 1; }
    if (!empty($src['con_correo'])) { $f['con_correo'] = 1; }
    return AnaliticaService::alcance($auth, $f);
}

$servicio = $disponible ? new ComunicacionService() : null;

// ---------------------------------------------------------------- envío de correo (POST)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_proteger();
    if (!$disponible) {
        $_SESSION['error'] = 'Falta ejecutar database/sprint5_comunicacion.sql en la base de datos.';
    } elseif (!$puedeMasivo) {
        $_SESSION['error'] = 'No tiene permisos para enviar correos.';
    } else {
        $filtros = com_filtros($_POST, $auth);
        $res = $servicio->enviarCorreoMasivo($filtros, (string)($_POST['plantilla'] ?? ''), (int)$auth->obtenerUsuarioId(), $_SESSION['nombre_completo'] ?? '');
        if (!empty($res['error'])) {
            $_SESSION['error'] = $res['error'];
        } else {
            (new LogAuditoria())->registrar($auth->obtenerUsuarioId(), 'enviar_correo_masivo', 'simpatizantes', null, null,
                ['plantilla' => $_POST['plantilla'] ?? '', 'encolados' => $res['encolados'], 'omitidos' => $res['omitidos'], 'filtros' => $filtros]);
            $msg = $res['encolados'] . ' correo(s) en la cola de envío.';
            if ($res['omitidos']) { $msg .= ' ' . $res['omitidos'] . ' omitido(s) por correo inválido.'; }
            if ($res['pendientes_por_limite'] > 0) { $msg .= ' Quedan ' . $res['pendientes_por_limite'] . ' personas más en la lista: se envían por tandas de ' . ComunicacionService::MAX_CORREO_POR_ENVIO . '; repita el envío más tarde.'; }
            $_SESSION['success'] = $msg;
        }
    }
    header('Location: ' . $base . (!empty($_POST['volver']) && is_string($_POST['volver']) && $_POST['volver'][0] === '?' ? $_POST['volver'] : ''));
    exit;
}

$filtros = com_filtros($_GET, $auth);
$campanaModel = new Campana();
$campanas = $campanaModel->obtenerTodas(1) ?: [];

// ---------------------------------------------------------------- exportar (GET)
if ($disponible && ($_GET['export'] ?? '') === 'xlsx') {
    $filas = [['Nombre', 'Sección', 'Municipio', 'Teléfono', 'WhatsApp', 'Correo', 'Consentimiento', 'Último contacto', 'Campaña']];
    foreach ($servicio->segmentoCompleto($filtros) as $r) {
        $cons = !empty($r['baja_comunicacion']) ? 'Pidió baja' : ($r['consentimiento'] === null ? 'Sin registrar' : ((int)$r['consentimiento'] === 1 ? 'Sí' : 'No'));
        $filas[] = [$r['nombre_completo'], $r['seccion_electoral'], $r['ciudad'] ?? '', $r['telefono'] ?? '', $r['whatsapp'] ?? '', $r['email'] ?? '', $cons,
                    $r['ultimo_contacto'] ? date('d/m/Y H:i', strtotime($r['ultimo_contacto'])) : 'Nunca', $r['campana_nombre'] ?? ''];
    }
    (new LogAuditoria())->registrar($auth->obtenerUsuarioId(), 'exportar_lista_comunicacion', 'simpatizantes', null, null, ['registros' => count($filas) - 1, 'filtros' => $filtros]);
    HojaCalculoService::enviarXlsx($filas, 'lista_contacto_' . date('Y-m-d_His') . '.xlsx', 'Lista');
    exit;
}

// ---------------------------------------------------------------- datos de la vista
$pagina = isset($_GET['page']) && ctype_digit((string)$_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$porPagina = 25;
$total = $consentimiento = $conCorreo = 0;
$filas = [];
$plantillasWa = $plantillasCorreo = [];
$plantillaWa = null;
$ciudades = [];
if ($disponible) {
    $total = $servicio->contar($filtros);
    $consentimiento = $servicio->contar(array_merge($filtros, ['consentimiento' => 'si']));
    $conCorreo = $servicio->contar(array_merge($filtros, ['consentimiento' => 'si', 'con_correo' => 1]));
    $filas = $servicio->segmento($filtros, $pagina, $porPagina);
    $plantillasWa = $servicio->plantillas('whatsapp');
    $plantillasCorreo = $servicio->plantillas('correo');
    $claveWa = $_GET['plantilla'] ?? ($plantillasWa[0]['clave'] ?? '');
    $plantillaWa = $servicio->plantilla($claveWa);
    if (!$plantillaWa || $plantillaWa['canal'] !== 'whatsapp') { $plantillaWa = $plantillasWa[0] ?? null; }
    $ciudades = (new AnaliticaService())->ciudades(array_intersect_key($filtros, array_flip(['campana_id', 'capturista_id'])));
}
$totalPaginas = max(1, (int)ceil($total / $porPagina));

$qs = $_GET; unset($qs['page'], $qs['export']);
$urlPagina = function ($n) use ($qs) { return '?' . http_build_query($qs + ['page' => $n]); };
$volver = '?' . http_build_query($qs);
$sel = function ($k, $v) { return (($_GET[$k] ?? '') === (string)$v) ? 'selected' : ''; };
$ahora = time();
$relativo = function ($t) use ($ahora) {
    if (!$t) { return '<span class="text-muted">Nunca</span>'; }
    $d = (int)floor(($ahora - strtotime($t)) / 86400);
    return $d <= 0 ? 'Hoy' : ($d === 1 ? 'Ayer' : 'Hace ' . $d . ' días');
};

$pageTitle = 'Comunicación';
include __DIR__ . '/../../app/views/layouts/header.php';
?>
<div class="container-fluid p-0">
    <div class="page-head">
        <div>
            <div class="eyebrow">Seguimiento</div>
            <h1>Comunicación</h1>
            <div class="meta"><span><i class="bi bi-chat-dots"></i>Listas de contacto, WhatsApp y correo a simpatizantes</span></div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <?php if ($disponible): ?>
            <a class="btn btn-soft" href="?<?php echo htmlspecialchars(http_build_query($qs + ['export' => 'xlsx'])); ?>"><i class="bi bi-file-earmark-excel me-2"></i>Exportar lista</a>
            <?php endif; ?>
            <?php if ($puedeMasivo): ?><a class="btn btn-soft" href="plantillas.php"><i class="bi bi-card-text me-2"></i>Plantillas</a><?php endif; ?>
        </div>
    </div>

<?php if (!$disponible): ?>
    <div class="alert alert-warning"><i class="bi bi-exclamation-triangle-fill me-2"></i>Falta ejecutar <code>database/sprint5_comunicacion.sql</code> (phpMyAdmin &gt; SQL) para activar la comunicación.</div>
<?php else: ?>
    <!-- Filtros -->
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="" class="row g-3 align-items-end">
                <?php if ($puedeVerTodas): ?>
                <div class="col-lg-3 col-md-6">
                    <label class="form-label small text-muted mb-1">Campaña</label>
                    <select class="form-select" name="campana_id">
                        <option value="">Todas</option>
                        <?php foreach ($campanas as $c): ?><option value="<?php echo (int)$c['id']; ?>" <?php echo $sel('campana_id', $c['id']); ?>><?php echo htmlspecialchars($c['nombre']); ?></option><?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="col-lg-2 col-md-6">
                    <label class="form-label small text-muted mb-1">Municipio</label>
                    <select class="form-select" name="ciudad">
                        <option value="">Todos</option>
                        <?php foreach ($ciudades as $c): ?><option value="<?php echo htmlspecialchars($c); ?>" <?php echo $sel('ciudad', $c); ?>><?php echo htmlspecialchars($c); ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-1 col-md-4">
                    <label class="form-label small text-muted mb-1">Sección</label>
                    <input type="text" class="form-control" name="seccion" inputmode="numeric" maxlength="4" placeholder="0123" value="<?php echo htmlspecialchars($_GET['seccion'] ?? ''); ?>">
                </div>
                <div class="col-lg-2 col-md-4">
                    <label class="form-label small text-muted mb-1">Estado</label>
                    <select class="form-select" name="estatus">
                        <option value="">Todos</option>
                        <option value="validado" <?php echo $sel('estatus', 'validado'); ?>>Validados</option>
                        <option value="pendiente" <?php echo $sel('estatus', 'pendiente'); ?>>Pendientes</option>
                        <option value="rechazado" <?php echo $sel('estatus', 'rechazado'); ?>>Rechazados</option>
                    </select>
                </div>
                <div class="col-lg-2 col-md-4">
                    <label class="form-label small text-muted mb-1">Consentimiento</label>
                    <select class="form-select" name="consentimiento">
                        <option value="">Todos</option>
                        <option value="si" <?php echo $sel('consentimiento', 'si'); ?>>Aceptó recibir mensajes</option>
                        <option value="sin_registrar" <?php echo $sel('consentimiento', 'sin_registrar'); ?>>Sin registrar</option>
                        <option value="baja" <?php echo $sel('consentimiento', 'baja'); ?>>No acepta / baja</option>
                    </select>
                </div>
                <div class="col-lg-2 col-md-6">
                    <label class="form-label small text-muted mb-1">Sin contacto desde hace</label>
                    <select class="form-select" name="sin_contacto_dias">
                        <option value="">Cualquiera</option>
                        <?php foreach ([7, 15, 30, 60, 90] as $d): ?><option value="<?php echo $d; ?>" <?php echo $sel('sin_contacto_dias', $d); ?>><?php echo $d; ?> días o más</option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-auto col-md-6 d-flex gap-3">
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="con_whatsapp" value="1" id="fWa" <?php echo !empty($_GET['con_whatsapp']) ? 'checked' : ''; ?>><label class="form-check-label" for="fWa">Con WhatsApp</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="con_correo" value="1" id="fMail" <?php echo !empty($_GET['con_correo']) ? 'checked' : ''; ?>><label class="form-check-label" for="fMail">Con correo</label></div>
                </div>
                <div class="col-12 d-flex flex-wrap align-items-center gap-3">
                    <button class="btn btn-primary"><i class="bi bi-funnel me-2"></i>Filtrar</button>
                    <a href="index.php" class="btn btn-soft">Limpiar</a>
                    <?php if ($plantillasWa): ?>
                    <div class="d-flex align-items-center gap-2 ms-lg-3">
                        <label class="small text-muted mb-0" for="selPlantilla"><i class="bi bi-whatsapp me-1"></i>Mensaje de WhatsApp:</label>
                        <select class="form-select form-select-sm" name="plantilla" id="selPlantilla" style="width:auto">
                            <?php foreach ($plantillasWa as $p): ?><option value="<?php echo htmlspecialchars($p['clave']); ?>" <?php echo ($plantillaWa && $plantillaWa['clave'] === $p['clave']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($p['nombre']); ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Resumen y correo masivo -->
    <div class="row g-3 mb-3">
        <div class="col-md-4"><div class="card kpi"><i class="bi bi-people ico"></i><div class="label">En la lista</div><div class="value"><?php echo number_format($total); ?></div><div class="hint">personas con estos filtros</div></div></div>
        <div class="col-md-4"><div class="card kpi"><i class="bi bi-patch-check ico"></i><div class="label">Aceptaron mensajes</div><div class="value"><?php echo number_format($consentimiento); ?></div><div class="hint"><?php echo $total > 0 ? round($consentimiento * 100 / $total) : 0; ?>% de la lista</div></div></div>
        <div class="col-md-4"><div class="card kpi"><i class="bi bi-envelope-check ico"></i><div class="label">Pueden recibir correo</div><div class="value"><?php echo number_format($conCorreo); ?></div><div class="hint">con consentimiento y correo</div></div></div>
    </div>

    <?php if ($puedeMasivo && $plantillasCorreo): ?>
    <div class="card mb-3">
        <div class="card-body">
            <form method="POST" action="" class="row g-3 align-items-end" onsubmit="return confirm('Se pondrán en la cola de envío hasta <?php echo min($conCorreo, ComunicacionService::MAX_CORREO_POR_ENVIO); ?> correos. ¿Continuar?');">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="volver" value="<?php echo htmlspecialchars($volver); ?>">
                <?php foreach ($filtros as $k => $v): if (in_array($k, ['capturista_id'], true) || $v === '' ) { continue; } ?>
                    <input type="hidden" name="<?php echo $k === 'seccion_electoral' ? 'seccion' : htmlspecialchars($k); ?>" value="<?php echo htmlspecialchars((string)$v); ?>">
                <?php endforeach; ?>
                <div class="col-lg-5">
                    <label class="form-label small text-muted mb-1"><i class="bi bi-envelope me-1"></i>Enviar un correo a esta lista</label>
                    <select class="form-select" name="plantilla" required>
                        <?php foreach ($plantillasCorreo as $p): ?><option value="<?php echo htmlspecialchars($p['clave']); ?>"><?php echo htmlspecialchars($p['nombre']); ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-auto"><button class="btn btn-primary" <?php echo $conCorreo === 0 ? 'disabled' : ''; ?>><i class="bi bi-send me-2"></i>Enviar a <?php echo number_format(min($conCorreo, ComunicacionService::MAX_CORREO_POR_ENVIO)); ?> personas</button></div>
                <div class="col-12 small text-muted">Solo reciben correo quienes aceptaron recibir mensajes y no se dieron de baja. Cada correo lleva su enlace para darse de baja. Los envíos salen por tandas de <?php echo ComunicacionService::MAX_CORREO_POR_ENVIO; ?>.</div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Lista -->
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 simp-table">
                <thead><tr><th>Simpatizante</th><th>Sección</th><th class="d-none d-lg-table-cell">Contacto</th><th>Último contacto</th><th>Consentimiento</th><th class="text-end">Acciones</th></tr></thead>
                <tbody>
                <?php if (empty($filas)): ?>
                    <tr><td colspan="6"><div class="empty-state"><i class="bi bi-chat-dots"></i><p class="mt-3 mb-0">Nadie coincide con estos filtros.</p></div></td></tr>
                <?php endif; ?>
                <?php foreach ($filas as $r):
                    $estadoWa = ComunicacionService::estadoWhatsapp($r);
                    $mensaje = $plantillaWa ? ComunicacionService::render($plantillaWa['cuerpo'], $r, ['capturista' => $_SESSION['nombre_completo'] ?? '']) : '';
                    $urlWa = $plantillaWa ? ComunicacionService::enlaceWhatsapp($r, $mensaje) : null;
                    if (!empty($r['baja_comunicacion'])) { $consTxt = ['Baja', 'bad']; }
                    elseif ($r['consentimiento'] === null) { $consTxt = ['Sin registrar', 'pend']; }
                    elseif ((int)$r['consentimiento'] === 1) { $consTxt = ['Aceptó', 'on']; }
                    else { $consTxt = ['No acepta', 'bad']; }
                ?>
                    <tr data-id="<?php echo (int)$r['id']; ?>">
                        <td>
                            <a class="fw-semibold text-decoration-none" href="../simpatizantes/ver.php?id=<?php echo (int)$r['id']; ?>"><?php echo htmlspecialchars($r['nombre_completo']); ?></a>
                            <div class="small text-muted"><?php echo htmlspecialchars($r['ciudad'] ?? ''); ?></div>
                        </td>
                        <td><span class="sec-pill"><?php echo htmlspecialchars($r['seccion_electoral']); ?></span></td>
                        <td class="d-none d-lg-table-cell small"><?php echo htmlspecialchars($r['whatsapp'] ?: ($r['telefono'] ?: '—')); ?><div class="text-muted"><?php echo htmlspecialchars($r['email'] ?: ''); ?></div></td>
                        <td class="small ultimo"><?php echo $relativo($r['ultimo_contacto']); ?></td>
                        <td><span class="status-pill <?php echo $consTxt[1]; ?>"><?php echo $consTxt[0]; ?></span></td>
                        <td class="text-end text-nowrap">
                            <?php if ($urlWa && $estadoWa['ok']): ?>
                                <a href="<?php echo htmlspecialchars($urlWa); ?>" target="_blank" rel="noopener" class="btn btn-sm btn-soft btn-wa" data-id="<?php echo (int)$r['id']; ?>"
                                   title="<?php echo htmlspecialchars($estadoWa['aviso'] ?? 'Abrir WhatsApp con el mensaje ya escrito'); ?>">
                                    <i class="bi bi-whatsapp me-1" style="color:#1f8f4e"></i>WhatsApp<?php echo $estadoWa['aviso'] ? ' <i class="bi bi-exclamation-triangle" style="color:#b85400"></i>' : ''; ?>
                                </a>
                            <?php else: ?>
                                <button class="btn btn-sm btn-soft" style="opacity:.45" disabled title="<?php echo htmlspecialchars(!$urlWa ? 'Sin número de 10 dígitos' : $estadoWa['aviso']); ?>"><i class="bi bi-whatsapp me-1"></i>WhatsApp</button>
                            <?php endif; ?>
                            <a class="icon-btn" href="../simpatizantes/ver.php?id=<?php echo (int)$r['id']; ?>#contactos" title="Registrar llamada o visita"><i class="bi bi-telephone"></i></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($total > 0): ?>
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 px-3 py-3 border-top">
            <div class="small text-muted">Mostrando <?php echo number_format(($pagina - 1) * $porPagina + 1); ?>–<?php echo number_format(min($total, $pagina * $porPagina)); ?> de <?php echo number_format($total); ?>. Primero aparecen quienes nunca han sido contactados.</div>
            <?php if ($totalPaginas > 1): ?>
            <ul class="pagination pagination-sm mb-0">
                <li class="page-item <?php echo $pagina <= 1 ? 'disabled' : ''; ?>"><a class="page-link" href="<?php echo $urlPagina($pagina - 1); ?>"><i class="bi bi-chevron-left"></i></a></li>
                <li class="page-item disabled"><span class="page-link"><?php echo $pagina; ?> / <?php echo $totalPaginas; ?></span></li>
                <li class="page-item <?php echo $pagina >= $totalPaginas ? 'disabled' : ''; ?>"><a class="page-link" href="<?php echo $urlPagina($pagina + 1); ?>"><i class="bi bi-chevron-right"></i></a></li>
            </ul>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
<?php endif; ?>
</div>

<?php if ($disponible && $plantillaWa): ?>
<script>
// Al tocar WhatsApp se abre la conversación y, en segundo plano, se anota el contacto.
(function () {
    var token = <?php echo json_encode(csrf_token()); ?>;
    var api = <?php echo json_encode(BASE_URL . '/public/api/registrar-contacto.php'); ?>;
    var plantilla = <?php echo json_encode($plantillaWa['clave']); ?>;
    var sel = document.getElementById('selPlantilla');
    if (sel) { sel.addEventListener('change', function () { this.form.submit(); }); }
    document.querySelectorAll('.btn-wa').forEach(function (a) {
        a.addEventListener('click', function () {
            var fila = a.closest('tr');
            fetch(api, { method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': token, 'Accept': 'application/json' },
                body: JSON.stringify({ simpatizante_id: a.dataset.id, canal: 'whatsapp', resultado: 'enviado', plantilla: plantilla })
            }).then(function (r) { return r.json(); }).then(function (j) {
                if (j && j.ok && fila) { fila.querySelector('.ultimo').textContent = 'Hoy'; }
            }).catch(function () {});
        });
    });
})();
</script>
<?php endif; ?>
<?php include __DIR__ . '/../../app/views/layouts/footer.php'; ?>
