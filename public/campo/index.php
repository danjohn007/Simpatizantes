<?php
/**
 * Metas y zonas: objetivos de captura (por campaña, capturista o sección) y asignación
 * de secciones electorales a capturistas.
 *
 * Permisos: super admin, admin y coordinador (el coordinador solo ve y administra su campaña).
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../app/helpers/security.php';
require_once __DIR__ . '/../../app/helpers/validacion.php';
require_once __DIR__ . '/../../app/controllers/AuthController.php';
require_once __DIR__ . '/../../app/models/Campana.php';
require_once __DIR__ . '/../../app/models/Campo.php';
require_once __DIR__ . '/../../app/models/LogAuditoria.php';

$auth = new AuthController();
$auth->requiereRol(['super_admin', 'admin', 'coordinador']);

$campo = new Campo();
$log = new LogAuditoria();
$campanaModel = new Campana();
$yo = (int)$auth->obtenerUsuarioId();
$base = BASE_URL . '/public/campo/index.php';

// Campañas que puede administrar
$todas = $campanaModel->obtenerTodas(1) ?: [];
if (!$auth->puedeVerTodasLasCampanas()) {
    $mia = (int)$auth->obtenerCampanaId();
    $todas = array_values(array_filter($todas, function ($c) use ($mia) { return (int)$c['id'] === $mia; }));
}
$idsCampana = array_map(function ($c) { return (int)$c['id']; }, $todas);
$filtroCampana = $auth->puedeVerTodasLasCampanas() ? null : (int)$auth->obtenerCampanaId();

function campo_volver($url) { header('Location: ' . $url); exit; }
/** Fecha Y-m-d válida (a diferencia de una fecha de nacimiento, puede ser futura). */
function campo_fecha($v) {
    $d = DateTime::createFromFormat('!Y-m-d', (string)$v);
    return ($d && $d->format('Y-m-d') === $v) ? $v : null;
}

// ---------------------------------------------------------------- acciones
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_proteger();
    if (!$campo->disponible()) {
        $_SESSION['error'] = 'Falta ejecutar database/sprint3_campo.sql en la base de datos.';
        campo_volver($base);
    }
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'crear_meta') {
        $campana = (int)($_POST['campana_id'] ?? 0);
        $objetivo = (int)($_POST['objetivo'] ?? 0);
        $ini = campo_fecha($_POST['fecha_inicio'] ?? '');
        $fin = campo_fecha($_POST['fecha_fin'] ?? '');
        $tipo = $_POST['tipo'] ?? 'campana';
        $usuario = $tipo === 'capturista' ? (int)($_POST['usuario_id'] ?? 0) : 0;
        $seccion = $tipo === 'seccion' ? vd_normalizar_seccion($_POST['seccion_electoral'] ?? '') : '';

        $error = null;
        if (!in_array($campana, $idsCampana, true)) { $error = 'Seleccione una campaña válida.'; }
        elseif ($objetivo < 1 || $objetivo > 1000000) { $error = 'El objetivo debe ser un número mayor que cero.'; }
        elseif (!$ini || !$fin || $fin < $ini) { $error = 'Indique un periodo válido (la fecha final no puede ser anterior a la inicial).'; }
        elseif ($tipo === 'capturista') {
            $u = $campo->usuario($usuario);
            if (!$u || $u['rol'] !== 'capturista' || (int)$u['campana_id'] !== $campana) { $error = 'Seleccione un capturista de esa campaña.'; }
        } elseif ($tipo === 'seccion' && !preg_match('/^\d{4}$/', $seccion)) { $error = 'La sección debe tener 4 dígitos.'; }

        if ($error) {
            $_SESSION['error'] = $error;
        } else {
            $res = $campo->crearMeta(['campana_id' => $campana, 'usuario_id' => $usuario, 'seccion_electoral' => $seccion,
                'objetivo' => $objetivo, 'fecha_inicio' => $ini, 'fecha_fin' => $fin, 'creada_por' => $yo]);
            if (!empty($res['success'])) {
                $log->registrar($yo, 'crear_meta', 'metas_captura', $res['id'], null, ['campana_id' => $campana, 'usuario_id' => $usuario ?: null, 'seccion' => $seccion ?: null, 'objetivo' => $objetivo, 'inicio' => $ini, 'fin' => $fin]);
                $_SESSION['success'] = 'Meta creada.';
            } else {
                $_SESSION['error'] = $res['error'];
            }
        }
        campo_volver($base);
    }

    if ($accion === 'eliminar_meta') {
        $m = $campo->obtenerMeta((int)($_POST['id'] ?? 0));
        if ($m && in_array((int)$m['campana_id'], $idsCampana, true)) {
            $campo->eliminarMeta($m['id']);
            $log->registrar($yo, 'eliminar_meta', 'metas_captura', $m['id'], $m, null);
            $_SESSION['success'] = 'Meta eliminada.';
        } else {
            $_SESSION['error'] = 'Meta no encontrada.';
        }
        campo_volver($base);
    }

    if ($accion === 'asignar_zonas') {
        $u = $campo->usuario((int)($_POST['usuario_id'] ?? 0));
        $permitido = $u && $u['rol'] === 'capturista' && in_array((int)$u['campana_id'], $idsCampana, true);
        // Secciones separadas por coma, espacio o salto de línea; "0100-0105" agrega el rango
        $secciones = [];
        foreach (preg_split('/[\s,;]+/', (string)($_POST['secciones'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $tramo) {
            if (preg_match('/^(\d{1,4})-(\d{1,4})$/', $tramo, $m) && (int)$m[2] >= (int)$m[1] && (int)$m[2] - (int)$m[1] <= 200) {
                for ($n = (int)$m[1]; $n <= (int)$m[2]; $n++) { $secciones[] = vd_normalizar_seccion((string)$n); }
            } elseif (preg_match('/^\d{1,4}$/', $tramo)) {
                $secciones[] = vd_normalizar_seccion($tramo);
            } else {
                $secciones = null; break;
            }
        }
        if (!$permitido) {
            $_SESSION['error'] = 'Seleccione un capturista de su campaña.';
        } elseif (!$secciones) {
            $_SESSION['error'] = 'Escriba secciones de 4 dígitos separadas por coma o espacio (también rangos como 0100-0105).';
        } else {
            $n = $campo->asignar($u['id'], $secciones, $yo);
            $log->registrar($yo, 'asignar_zonas', 'asignaciones_seccion', null, null, ['usuario_id' => $u['id'], 'secciones' => array_values(array_unique($secciones))]);
            $_SESSION['success'] = 'Zonas asignadas a ' . $u['nombre_completo'] . '.';
        }
        campo_volver($base);
    }

    if ($accion === 'quitar_zona') {
        $a = $campo->obtenerAsignacion((int)($_POST['id'] ?? 0));
        if ($a && in_array((int)$a['campana_id'], $idsCampana, true)) {
            $campo->quitarAsignacion($a['id']);
            $log->registrar($yo, 'quitar_zona', 'asignaciones_seccion', $a['id'], $a, null);
            $_SESSION['success'] = 'Sección ' . $a['seccion_electoral'] . ' quitada.';
        }
        campo_volver($base);
    }
    campo_volver($base);
}

// ---------------------------------------------------------------- datos de la vista
$disponible = $campo->disponible();
$metas = $campo->metas(['campana_id' => $filtroCampana]);
$capturistas = $campo->capturistas($filtroCampana);
$asignaciones = $campo->asignaciones($filtroCampana);
$porUsuario = [];
foreach ($asignaciones as $a) {
    $porUsuario[$a['usuario_id']]['nombre'] = $a['nombre_completo'];
    $porUsuario[$a['usuario_id']]['zonas'][] = $a;
}
$hoy = date('Y-m-d');

$pageTitle = 'Metas y zonas';
include __DIR__ . '/../../app/views/layouts/header.php';
?>
<div class="container-fluid p-0">
    <div class="page-head">
        <div>
            <div class="eyebrow">Operación en campo</div>
            <h1>Metas y zonas</h1>
            <div class="meta"><span><i class="bi bi-bullseye"></i>Objetivos de captura y secciones asignadas a cada capturista</span></div>
        </div>
    </div>

<?php if (!$disponible): ?>
    <div class="alert alert-warning"><i class="bi bi-exclamation-triangle-fill me-2"></i>Falta ejecutar <code>database/sprint3_campo.sql</code> (phpMyAdmin &gt; SQL) para activar metas y zonas.</div>
<?php else: ?>
    <div class="row g-3">
        <!-- Metas -->
        <div class="col-xl-7">
            <div class="card mb-3">
                <div class="card-header"><h5 class="mb-0"><i class="bi bi-bullseye me-2"></i>Metas de captura</h5></div>
                <?php if (empty($metas)): ?>
                    <div class="card-body"><div class="empty-state"><i class="bi bi-bullseye"></i><p class="mt-3 mb-0">Aún no hay metas. Cree la primera con el formulario de abajo.</p></div></div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead><tr><th>Meta</th><th style="min-width:200px">Avance</th><th>Periodo</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($metas as $m):
                            $obj = max(1, (int)$m['objetivo']);
                            $reg = (int)$m['registrados'];
                            $pct = min(100, (int)round($reg * 100 / $obj));
                            $vencida = $m['fecha_fin'] < $hoy;
                            $alcance = $m['usuario_id'] ? 'Capturista: ' . $m['capturista_nombre']
                                     : ($m['seccion_electoral'] ? 'Sección ' . $m['seccion_electoral'] : 'Toda la campaña');
                        ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?php echo htmlspecialchars($alcance); ?></div>
                                    <div class="small text-muted"><?php echo htmlspecialchars($m['campana_nombre'] ?? ''); ?></div>
                                </td>
                                <td>
                                    <div class="d-flex justify-content-between small mb-1">
                                        <span><strong><?php echo number_format($reg); ?></strong> de <?php echo number_format($obj); ?></span>
                                        <span class="text-muted"><?php echo $pct; ?>%</span>
                                    </div>
                                    <div class="progress" style="height:8px" role="progressbar" aria-valuenow="<?php echo $pct; ?>" aria-valuemin="0" aria-valuemax="100">
                                        <div class="progress-bar <?php echo $pct >= 100 ? 'bg-success' : ''; ?>" style="width:<?php echo $pct; ?>%;<?php echo $pct < 100 ? 'background:#ff8000' : ''; ?>"></div>
                                    </div>
                                    <div class="small text-muted mt-1"><?php echo number_format((int)$m['validados']); ?> validados</div>
                                </td>
                                <td class="small text-nowrap">
                                    <?php echo date('d/m/Y', strtotime($m['fecha_inicio'])); ?> – <?php echo date('d/m/Y', strtotime($m['fecha_fin'])); ?>
                                    <?php if ($vencida): ?><div><span class="status-pill off">Terminada</span></div><?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <form method="POST" action="" onsubmit="return confirm('¿Eliminar esta meta?');">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="accion" value="eliminar_meta">
                                        <input type="hidden" name="id" value="<?php echo (int)$m['id']; ?>">
                                        <button class="icon-btn danger" title="Eliminar meta"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>

            <div class="card">
                <div class="card-header"><h5 class="mb-0"><i class="bi bi-plus-circle me-2"></i>Nueva meta</h5></div>
                <div class="card-body">
                    <form method="POST" action="" class="row g-3" id="formMeta">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="accion" value="crear_meta">
                        <div class="col-md-6">
                            <label class="form-label" for="mCampana">Campaña</label>
                            <select class="form-select" name="campana_id" id="mCampana" required>
                                <?php foreach ($todas as $c): ?><option value="<?php echo (int)$c['id']; ?>"><?php echo htmlspecialchars($c['nombre']); ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="mTipo">La meta es de…</label>
                            <select class="form-select" name="tipo" id="mTipo">
                                <option value="campana">Toda la campaña</option>
                                <option value="capturista">Un capturista</option>
                                <option value="seccion">Una sección</option>
                            </select>
                        </div>
                        <div class="col-md-6" id="mCapturistaBox" hidden>
                            <label class="form-label" for="mUsuario">Capturista</label>
                            <select class="form-select" name="usuario_id" id="mUsuario">
                                <?php foreach ($capturistas as $u): ?><option value="<?php echo (int)$u['id']; ?>" data-campana="<?php echo (int)$u['campana_id']; ?>"><?php echo htmlspecialchars($u['nombre_completo']); ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6" id="mSeccionBox" hidden>
                            <label class="form-label" for="mSeccion">Sección electoral</label>
                            <input type="text" class="form-control" name="seccion_electoral" id="mSeccion" inputmode="numeric" maxlength="4" placeholder="0123">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="mObjetivo">Objetivo (registros)</label>
                            <input type="number" class="form-control" name="objetivo" id="mObjetivo" min="1" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="mIni">Desde</label>
                            <input type="date" class="form-control" name="fecha_inicio" id="mIni" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="mFin">Hasta</label>
                            <input type="date" class="form-control" name="fecha_fin" id="mFin" value="<?php echo date('Y-m-d', strtotime('+14 days')); ?>" required>
                        </div>
                        <div class="col-12"><button class="btn btn-primary"><i class="bi bi-check-lg me-2"></i>Crear meta</button></div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Zonas -->
        <div class="col-xl-5">
            <div class="card mb-3">
                <div class="card-header"><h5 class="mb-0"><i class="bi bi-diagram-3 me-2"></i>Zonas por capturista</h5></div>
                <div class="card-body">
                    <?php if (empty($porUsuario)): ?>
                        <p class="text-muted small mb-0">Ningún capturista tiene secciones asignadas. Si no tiene zona, puede capturar en cualquier sección.</p>
                    <?php endif; ?>
                    <?php foreach ($porUsuario as $uid => $info): ?>
                        <div class="mb-3">
                            <div class="fw-semibold mb-1"><?php echo htmlspecialchars($info['nombre']); ?> <span class="small text-muted">· <?php echo count($info['zonas']); ?> secciones</span></div>
                            <div class="d-flex flex-wrap gap-1">
                            <?php foreach ($info['zonas'] as $z): ?>
                                <form method="POST" action="" class="d-inline">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="accion" value="quitar_zona">
                                    <input type="hidden" name="id" value="<?php echo (int)$z['id']; ?>">
                                    <button class="sec-pill border-0" title="Quitar la sección <?php echo htmlspecialchars($z['seccion_electoral']); ?>"><?php echo htmlspecialchars($z['seccion_electoral']); ?> <i class="bi bi-x"></i></button>
                                </form>
                            <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h5 class="mb-0"><i class="bi bi-geo-alt me-2"></i>Asignar secciones</h5></div>
                <div class="card-body">
                    <?php if (empty($capturistas)): ?>
                        <p class="text-muted small mb-0">No hay capturistas activos en la campaña.</p>
                    <?php else: ?>
                    <form method="POST" action="">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="accion" value="asignar_zonas">
                        <div class="mb-3">
                            <label class="form-label" for="zUsuario">Capturista</label>
                            <select class="form-select" name="usuario_id" id="zUsuario" required>
                                <?php foreach ($capturistas as $u): ?><option value="<?php echo (int)$u['id']; ?>"><?php echo htmlspecialchars($u['nombre_completo']); ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="zSecciones">Secciones</label>
                            <textarea class="form-control" name="secciones" id="zSecciones" rows="2" placeholder="0123, 0124, 0130-0135" required></textarea>
                            <div class="form-text">Separadas por coma o espacio. Un rango como 0130-0135 agrega todas las intermedias.</div>
                        </div>
                        <button class="btn btn-primary"><i class="bi bi-plus-lg me-2"></i>Asignar</button>
                    </form>
                    <p class="small text-muted mt-3 mb-0"><i class="bi bi-info-circle me-1"></i>Si un capturista registra a alguien fuera de sus secciones, el sistema le avisa; no se bloquea el registro.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
</div>
<script>
(function () {
    var tipo = document.getElementById('mTipo');
    if (!tipo) { return; }
    var campana = document.getElementById('mCampana'), usuario = document.getElementById('mUsuario');
    function ajustar() {
        document.getElementById('mCapturistaBox').hidden = tipo.value !== 'capturista';
        document.getElementById('mSeccionBox').hidden = tipo.value !== 'seccion';
        // Solo se ofrecen capturistas de la campaña elegida
        if (usuario) {
            Array.prototype.forEach.call(usuario.options, function (o) { o.hidden = o.dataset.campana !== campana.value; o.disabled = o.hidden; });
            if (usuario.selectedOptions[0] && usuario.selectedOptions[0].disabled) {
                var primero = Array.prototype.find.call(usuario.options, function (o) { return !o.disabled; });
                if (primero) { usuario.value = primero.value; }
            }
        }
    }
    tipo.addEventListener('change', ajustar); campana.addEventListener('change', ajustar); ajustar();
})();
</script>
<?php include __DIR__ . '/../../app/views/layouts/footer.php'; ?>
