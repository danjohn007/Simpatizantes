<?php
/**
 * Ver Detalles de Simpatizante
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../app/controllers/AuthController.php';
require_once __DIR__ . '/../../app/controllers/SimpatizanteController.php';

$auth = new AuthController();
$auth->requiereAutenticacion();

// Validar ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: ' . BASE_URL . '/public/simpatizantes/index.php');
    exit;
}

$id = (int)$_GET['id'];
$controller = new SimpatizanteController();
$simpatizante = $controller->obtener($id);

// Verificar si hubo error
if (isset($simpatizante['error'])) {
    $_SESSION['error'] = $simpatizante['error'];
    header('Location: ' . BASE_URL . '/public/simpatizantes/index.php');
    exit;
}

if (!$simpatizante) {
    $_SESSION['error'] = 'Simpatizante no encontrado';
    header('Location: ' . BASE_URL . '/public/simpatizantes/index.php');
    exit;
}

$pageTitle = 'Detalles del Simpatizante';
include __DIR__ . '/../../app/views/layouts/header.php';
?>

<?php
// Utilidades de presentación
$e = function ($k) use ($simpatizante) {
    $v = $simpatizante[$k] ?? '';
    return ($v === null || $v === '') ? null : htmlspecialchars((string)$v);
};
$dd = function ($valor) {
    return $valor === null ? '<dd class="empty">—</dd>' : '<dd>' . $valor . '</dd>';
};
$fecha = function ($k, $conHora = false) use ($simpatizante) {
    if (empty($simpatizante[$k])) { return null; }
    $t = strtotime($simpatizante[$k]);
    return $t ? date($conHora ? 'd/m/Y H:i' : 'd/m/Y', $t) : null;
};
$sexos   = ['M' => 'Masculino', 'F' => 'Femenino', 'O' => 'Otro'];
$metodos = ['manual' => 'Manual', 'app' => 'App móvil', 'web' => 'Web', 'ocr' => 'OCR'];

$nombre  = trim($simpatizante['nombre_completo'] ?? '');
$partes  = preg_split('/\s+/', $nombre);
$iniciales = mb_strtoupper(mb_substr($partes[0] ?? '', 0, 1) . mb_substr($partes[1] ?? '', 0, 1));
$estatus = $simpatizante['estatus_validacion'] ?? (!empty($simpatizante['validado']) ? 'validado' : 'pendiente');
$estatusEtiqueta = ['pendiente' => 'Pendiente de validación', 'validado' => 'Validado', 'rechazado' => 'Rechazado'];
$estatusClase = ['pendiente' => 'pend', 'validado' => 'on', 'rechazado' => 'bad'];
$puedeValidar = $auth->tieneRol(['super_admin', 'admin', 'coordinador']) && array_key_exists('estatus_validacion', $simpatizante);
$historial = $controller->historialValidacion((int)$simpatizante['id']);
$calidad = $controller->revisarCalidad($simpatizante, (int)$simpatizante['id']);

$puedeEditar = in_array($auth->obtenerRol(), ['super_admin', 'admin', 'coordinador'])
    || ($auth->obtenerRol() === 'capturista' && $simpatizante['capturista_id'] == $auth->obtenerUsuarioId());

$wa = preg_replace('/\D/', '', (string)($simpatizante['whatsapp'] ?? ''));
$tieneUbicacion = is_numeric($simpatizante['latitud'] ?? null) && is_numeric($simpatizante['longitud'] ?? null);

$archivos = [];
foreach ([['ine_frontal', 'INE frontal', false], ['ine_posterior', 'INE reverso', false], ['firma_digital', 'Firma digital', true]] as $a) {
    if (!empty($simpatizante[$a[0]])) { $archivos[] = $a; }
}
?>
<div class="container-fluid p-0">
    <div class="page-head">
        <div class="profile-head">
            <div class="avatar-lg"><?php echo htmlspecialchars($iniciales ?: '?'); ?></div>
            <div>
                <div class="eyebrow">Simpatizante #<?php echo (int)$simpatizante['id']; ?></div>
                <h1><?php echo htmlspecialchars($nombre); ?></h1>
                <div class="meta">
                    <span class="status-pill <?php echo $estatusClase[$estatus] ?? 'off'; ?>"><?php echo $estatusEtiqueta[$estatus] ?? 'Pendiente de validación'; ?></span>
                    <span><i class="bi bi-diagram-3"></i>Sección <?php echo $e('seccion_electoral') ?? '—'; ?></span>
                    <?php if ($e('campana_nombre')): ?><span><i class="bi bi-megaphone"></i><?php echo $e('campana_nombre'); ?></span><?php endif; ?>
                </div>
            </div>
        </div>
        <div class="d-flex gap-2">
            <a href="<?php echo BASE_URL; ?>/public/simpatizantes/index.php" class="btn btn-soft"><i class="bi bi-arrow-left me-2"></i>Volver</a>
            <?php if ($puedeEditar): ?>
                <a href="<?php echo BASE_URL; ?>/public/simpatizantes/editar.php?id=<?php echo (int)$simpatizante['id']; ?>" class="btn btn-primary"><i class="bi bi-pencil me-2"></i>Editar</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="row g-3">
        <!-- Datos personales -->
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header"><h5 class="mb-0"><i class="bi bi-person me-2"></i>Datos personales</h5></div>
                <div class="card-body">
                    <dl class="dl-grid">
                        <div class="full"><dt>Nombre completo</dt><?php echo $dd($e('nombre_completo')); ?></div>
                        <div><dt>Sexo</dt><?php echo $dd(isset($sexos[$simpatizante['sexo'] ?? '']) ? $sexos[$simpatizante['sexo']] : $e('sexo')); ?></div>
                        <div><dt>Fecha de nacimiento</dt><?php echo $dd($fecha('fecha_nacimiento')); ?></div>
                        <div class="full"><dt>Domicilio</dt><?php echo $dd($e('domicilio_completo')); ?></div>
                        <div><dt>Ciudad</dt><?php echo $dd($e('ciudad')); ?></div>
                        <div><dt>Ubicación</dt>
                            <?php if ($tieneUbicacion):
                                $lat = (float)$simpatizante['latitud']; $lng = (float)$simpatizante['longitud']; ?>
                                <dd><a href="https://www.openstreetmap.org/?mlat=<?php echo $lat; ?>&amp;mlon=<?php echo $lng; ?>#map=16/<?php echo $lat; ?>/<?php echo $lng; ?>" target="_blank" rel="noopener"><i class="bi bi-geo-alt me-1"></i>Ver en el mapa</a></dd>
                            <?php else: ?><dd class="empty">—</dd><?php endif; ?>
                        </div>
                    </dl>
                </div>
            </div>
        </div>

        <!-- Datos electorales -->
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header"><h5 class="mb-0"><i class="bi bi-card-checklist me-2"></i>Datos electorales</h5></div>
                <div class="card-body">
                    <dl class="dl-grid">
                        <div><dt>Sección electoral</dt><dd><span class="sec-pill"><?php echo $e('seccion_electoral') ?? '—'; ?></span></dd></div>
                        <div><dt>Año de registro</dt><?php echo $dd($e('ano_registro')); ?></div>
                        <div class="full"><dt>Clave de elector</dt><?php echo !empty($simpatizante['clave_elector']) ? '<dd class="mono">' . $e('clave_elector') . '</dd>' : '<dd class="empty">—</dd>'; ?></div>
                        <div class="full"><dt>CURP</dt><?php echo !empty($simpatizante['curp']) ? '<dd class="mono">' . $e('curp') . '</dd>' : '<dd class="empty">—</dd>'; ?></div>
                        <div><dt>Vigencia</dt><?php echo $dd($e('vigencia')); ?></div>
                    </dl>
                </div>
            </div>
        </div>

        <!-- Contacto -->
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header"><h5 class="mb-0"><i class="bi bi-telephone me-2"></i>Contacto y redes</h5></div>
                <div class="card-body">
                    <dl class="dl-grid">
                        <div><dt>Teléfono</dt><?php echo $dd($e('telefono')); ?></div>
                        <div><dt>WhatsApp</dt>
                            <?php if (strlen($wa) === 10): ?>
                                <dd><a href="https://wa.me/52<?php echo $wa; ?>" target="_blank" rel="noopener"><?php echo htmlspecialchars($simpatizante['whatsapp']); ?></a></dd>
                            <?php else: echo $dd($e('whatsapp')); endif; ?>
                        </div>
                        <div class="full"><dt>Correo electrónico</dt>
                            <?php if (!empty($simpatizante['email'])): ?><dd><a href="mailto:<?php echo $e('email'); ?>"><?php echo $e('email'); ?></a></dd><?php else: ?><dd class="empty">—</dd><?php endif; ?>
                        </div>
                        <div><dt>Facebook</dt><?php echo $dd($e('facebook')); ?></div>
                        <div><dt>Instagram</dt><?php echo $dd($e('instagram')); ?></div>
                        <div><dt>X / Twitter</dt><?php echo $dd($e('twitter')); ?></div>
                        <div><dt>TikTok</dt><?php echo $dd($e('tiktok')); ?></div>
                        <div><dt>YouTube</dt><?php echo $dd($e('youtube')); ?></div>
                    </dl>
                </div>
            </div>
        </div>

        <!-- Registro -->
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header"><h5 class="mb-0"><i class="bi bi-clipboard-data me-2"></i>Registro</h5></div>
                <div class="card-body">
                    <dl class="dl-grid">
                        <div class="full"><dt>Campaña</dt><?php echo $dd($e('campana_nombre')); ?></div>
                        <div><dt>Capturista</dt><?php echo $dd($e('capturista_nombre')); ?></div>
                        <div><dt>Método de captura</dt><?php echo $dd(isset($metodos[$simpatizante['metodo_captura'] ?? '']) ? $metodos[$simpatizante['metodo_captura']] : $e('metodo_captura')); ?></div>
                        <div><dt>Fecha de registro</dt><?php echo $dd($fecha('created_at', true)); ?></div>
                        <div><dt>Última actualización</dt><?php echo $dd($fecha('updated_at', true)); ?></div>
                        <div class="full"><dt>Observaciones</dt>
                            <?php echo !empty($simpatizante['observaciones']) ? '<dd>' . nl2br(htmlspecialchars($simpatizante['observaciones'])) . '</dd>' : '<dd class="empty">Sin observaciones</dd>'; ?>
                        </div>
                    </dl>
                </div>
            </div>
        </div>

        <!-- Validación -->
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header"><h5 class="mb-0"><i class="bi bi-patch-check me-2"></i>Validación</h5></div>
                <div class="card-body">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="status-pill <?php echo $estatusClase[$estatus] ?? 'off'; ?>"><?php echo $estatusEtiqueta[$estatus] ?? 'Pendiente de validación'; ?></span>
                    </div>
                    <?php if ($estatus === 'rechazado' && !empty($simpatizante['motivo_rechazo'])): ?>
                        <p class="small mb-3"><strong>Motivo:</strong> <?php echo htmlspecialchars($simpatizante['motivo_rechazo']); ?></p>
                    <?php endif; ?>

                    <?php if ($puedeValidar): ?>
                    <form method="POST" action="<?php echo BASE_URL; ?>/public/simpatizantes/cambiar-estatus.php" class="mb-3">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="id" value="<?php echo (int)$simpatizante['id']; ?>">
                        <div class="d-flex flex-wrap gap-2 mb-2">
                            <?php if ($estatus !== 'validado'): ?>
                                <button type="submit" name="estatus" value="validado" class="btn btn-primary btn-sm"><i class="bi bi-check-lg me-1"></i>Validar</button>
                            <?php endif; ?>
                            <?php if ($estatus !== 'pendiente'): ?>
                                <button type="submit" name="estatus" value="pendiente" class="btn btn-soft btn-sm"><i class="bi bi-arrow-counterclockwise me-1"></i>Regresar a pendiente</button>
                            <?php endif; ?>
                        </div>
                        <label class="form-label small text-muted mb-1" for="motivoEstatus">Motivo (obligatorio para rechazar)</label>
                        <div class="input-group input-group-sm">
                            <input type="text" id="motivoEstatus" name="motivo" maxlength="255" class="form-control" placeholder="Ej. INE ilegible, datos incompletos…">
                            <button type="submit" name="estatus" value="rechazado" class="btn btn-danger"><i class="bi bi-x-lg me-1"></i>Rechazar</button>
                        </div>
                    </form>
                    <?php endif; ?>

                    <h6 class="small text-uppercase text-muted mb-2" style="letter-spacing:.08em">Historial</h6>
                    <?php if (empty($historial)): ?>
                        <p class="small text-muted mb-0">Sin cambios de estado registrados.</p>
                    <?php else: ?>
                        <ul class="hist-list">
                        <?php foreach ($historial as $h): ?>
                            <li>
                                <div><strong><?php echo htmlspecialchars($estatusEtiqueta[$h['estatus_nuevo']] ?? $h['estatus_nuevo']); ?></strong>
                                    <?php if (!empty($h['estatus_anterior'])): ?><span class="small text-muted">(antes: <?php echo htmlspecialchars($estatusEtiqueta[$h['estatus_anterior']] ?? $h['estatus_anterior']); ?>)</span><?php endif; ?>
                                </div>
                                <div class="quien"><?php echo htmlspecialchars($h['usuario_nombre'] ?? 'Sistema'); ?> · <?php echo date('d/m/Y H:i', strtotime($h['created_at'])); ?></div>
                                <?php if (!empty($h['motivo'])): ?><div class="motivo"><?php echo htmlspecialchars($h['motivo']); ?></div><?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Calidad de los datos -->
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header"><h5 class="mb-0"><i class="bi bi-clipboard2-check me-2"></i>Calidad de los datos</h5></div>
                <div class="card-body">
                    <?php if (empty($calidad['errores']) && empty($calidad['avisos']) && empty($calidad['duplicados'])): ?>
                        <p class="mb-0 text-success"><i class="bi bi-check-circle-fill me-2"></i>Sin duplicados ni inconsistencias detectadas.</p>
                    <?php else: ?>
                        <?php foreach ($calidad['errores'] as $t): ?>
                            <div class="alert alert-danger py-2 px-3 mb-2"><i class="bi bi-x-octagon-fill me-2"></i><?php echo htmlspecialchars($t); ?></div>
                        <?php endforeach; ?>
                        <?php foreach ($calidad['duplicados'] as $d): ?>
                            <div class="alert alert-<?php echo $d['tipo'] === 'duro' ? 'danger' : 'warning'; ?> py-2 px-3 mb-2">
                                <i class="bi bi-people-fill me-2"></i><?php echo htmlspecialchars($d['texto']); ?>:
                                <?php echo htmlspecialchars($d['nombre']); ?> (sección <?php echo htmlspecialchars($d['seccion']); ?>)
                                <?php if (!empty($d['id'])): ?><a href="ver.php?id=<?php echo (int)$d['id']; ?>">Ver registro #<?php echo (int)$d['id']; ?></a><?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        <?php foreach ($calidad['avisos'] as $a): ?>
                            <div class="alert alert-warning py-2 px-3 mb-2"><i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo htmlspecialchars($a['texto']); ?></div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Documentos -->
        <?php if (!empty($archivos)): ?>
        <div class="col-12">
            <div class="card">
                <div class="card-header"><h5 class="mb-0"><i class="bi bi-images me-2"></i>Documentos</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <?php foreach ($archivos as $a):
                            $src = BASE_URL . '/public/archivo.php?f=' . urlencode($simpatizante[$a[0]]); ?>
                            <div class="col-md-4">
                                <a class="doc-tile <?php echo $a[2] ? 'firma' : ''; ?>" href="<?php echo $src; ?>" target="_blank" rel="noopener">
                                    <img src="<?php echo $src; ?>" alt="<?php echo htmlspecialchars($a[1]); ?>" loading="lazy">
                                    <div class="cap"><span><?php echo htmlspecialchars($a[1]); ?></span><i class="bi bi-box-arrow-up-right"></i></div>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../../app/views/layouts/footer.php'; ?>
