<?php
/**
 * Logs de Auditoría
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../app/controllers/AuthController.php';
require_once __DIR__ . '/../../app/models/LogAuditoria.php';

$auth = new AuthController();
$auth->requiereRol(['super_admin', 'admin']);

$logModel = new LogAuditoria();

// Procesar filtros
$filtros = [];
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

if (!empty($_GET['usuario_id'])) {
    $filtros['usuario_id'] = $_GET['usuario_id'];
}

if (!empty($_GET['accion'])) {
    $filtros['accion'] = $_GET['accion'];
}

if (!empty($_GET['fecha_inicio'])) {
    $filtros['fecha_inicio'] = $_GET['fecha_inicio'];
} else {
    $filtros['fecha_inicio'] = date('Y-m-d', strtotime('-7 days'));
}

if (!empty($_GET['fecha_fin'])) {
    $filtros['fecha_fin'] = $_GET['fecha_fin'];
} else {
    $filtros['fecha_fin'] = date('Y-m-d');
}

// Obtener logs
$logs = $logModel->obtenerLogs($filtros, $page) ?: [];
$total = $logModel->contarTotal($filtros);
$totalPaginas = ceil($total / RECORDS_PER_PAGE);

$pageTitle = 'Auditoría';
include __DIR__ . '/../../app/views/layouts/header.php';
?>

<?php
// ---------- Utilidades de presentación ----------
$etiquetasAccion = [
    'login' => 'Inicio de sesión', 'logout' => 'Cierre de sesión',
    'crear_simpatizante' => 'Alta de simpatizante', 'actualizar_simpatizante' => 'Edición de simpatizante', 'eliminar_simpatizante' => 'Baja de simpatizante',
    'crear_usuario' => 'Alta de usuario', 'actualizar_usuario' => 'Edición de usuario', 'eliminar_usuario' => 'Eliminación de usuario',
    'suspender_usuario' => 'Suspensión de usuario', 'activar_usuario' => 'Activación de usuario', 'cambiar_password' => 'Cambio de contraseña',
    'actualizar_configuracion' => 'Cambio de configuración',
];
$nombreAccion = function ($a) use ($etiquetasAccion) {
    return $etiquetasAccion[$a] ?? ucfirst(str_replace('_', ' ', $a));
};
$tipoAccion = function ($a) {
    if (strpos($a, 'crear') === 0) return 'create';
    if (strpos($a, 'eliminar') === 0 || strpos($a, 'suspender') === 0) return 'delete';
    if (strpos($a, 'actualizar') === 0 || strpos($a, 'cambiar') === 0 || strpos($a, 'activar') === 0) return 'update';
    if ($a === 'login' || $a === 'logout') return 'auth';
    return 'other';
};
$decodificar = function ($json) {
    if (!$json) return null;
    $d = json_decode($json, true);
    return is_array($d) ? $d : null;
};
$valorSeguro = function ($clave, $valor) {
    if (preg_match('/pass|token|hash|secret/i', (string)$clave)) return '••••••';
    if (is_array($valor)) return json_encode($valor, JSON_UNESCAPED_UNICODE);
    if ($valor === null || $valor === '') return '—';
    return (string)$valor;
};
// Devuelve [campo, antes, después] solo de lo que cambió
$diferencias = function ($antes, $despues) use ($valorSeguro) {
    $filas = [];
    if ($antes !== null && $despues !== null) {
        foreach ($despues as $k => $nuevo) {
            $viejo = array_key_exists($k, $antes) ? $antes[$k] : null;
            if ((string)(is_array($viejo) ? json_encode($viejo) : $viejo) !== (string)(is_array($nuevo) ? json_encode($nuevo) : $nuevo)) {
                $filas[] = [$k, $valorSeguro($k, $viejo), $valorSeguro($k, $nuevo)];
            }
        }
    } elseif ($despues !== null) {
        foreach ($despues as $k => $nuevo) { $filas[] = [$k, '—', $valorSeguro($k, $nuevo)]; }
    } elseif ($antes !== null) {
        foreach ($antes as $k => $viejo) { $filas[] = [$k, $valorSeguro($k, $viejo), '—']; }
    }
    return $filas;
};

// Listas para los filtros
$dbAud = Database::getInstance();
$accionesDisponibles = array_column($dbAud->query("SELECT DISTINCT accion FROM logs_auditoria ORDER BY accion") ?: [], 'accion');
$usuariosDisponibles = $dbAud->query("SELECT id, nombre_completo FROM usuarios ORDER BY nombre_completo") ?: [];

// Paginación
$page         = max(1, $page);
$totalPaginas = max(1, (int)$totalPaginas);
$desde        = $total > 0 ? (($page - 1) * RECORDS_PER_PAGE) + 1 : 0;
$hasta        = min($total, $page * RECORDS_PER_PAGE);
$urlPagina    = function ($n) use ($filtros) { return '?' . http_build_query(array_merge($filtros, ['page' => $n])); };
$ventana = [];
for ($i = 1; $i <= $totalPaginas; $i++) {
    if ($i === 1 || $i === $totalPaginas || abs($i - $page) <= 2) { $ventana[] = $i; }
}
?>
<div class="container-fluid p-0">
    <div class="page-head">
        <div>
            <div class="eyebrow">Seguridad</div>
            <h1>Auditoría</h1>
            <div class="meta"><span><i class="bi bi-journal-text"></i>Registro de actividades del sistema</span>
                <span><i class="bi bi-calendar3"></i><?php echo date('d/m/Y', strtotime($filtros['fecha_inicio'])); ?> – <?php echo date('d/m/Y', strtotime($filtros['fecha_fin'])); ?></span></div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="" class="row g-3 align-items-end">
                <div class="col-lg-3 col-md-6">
                    <label class="form-label small text-muted mb-1">Acción</label>
                    <select class="form-select" name="accion">
                        <option value="">Todas las acciones</option>
                        <?php foreach ($accionesDisponibles as $a): ?>
                            <option value="<?php echo htmlspecialchars($a); ?>" <?php echo (($_GET['accion'] ?? '') === $a) ? 'selected' : ''; ?>><?php echo htmlspecialchars($nombreAccion($a)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-3 col-md-6">
                    <label class="form-label small text-muted mb-1">Usuario</label>
                    <select class="form-select" name="usuario_id">
                        <option value="">Todos los usuarios</option>
                        <?php foreach ($usuariosDisponibles as $u): ?>
                            <option value="<?php echo (int)$u['id']; ?>" <?php echo (($_GET['usuario_id'] ?? '') == $u['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($u['nombre_completo']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-2 col-md-4">
                    <label class="form-label small text-muted mb-1">Desde</label>
                    <input type="date" class="form-control" name="fecha_inicio" value="<?php echo htmlspecialchars($filtros['fecha_inicio']); ?>">
                </div>
                <div class="col-lg-2 col-md-4">
                    <label class="form-label small text-muted mb-1">Hasta</label>
                    <input type="date" class="form-control" name="fecha_fin" value="<?php echo htmlspecialchars($filtros['fecha_fin']); ?>">
                </div>
                <div class="col-lg-2 col-md-4">
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-fill"><i class="bi bi-funnel me-2"></i>Filtrar</button>
                        <a href="index.php" class="btn btn-soft" title="Restablecer (últimos 7 días)"><i class="bi bi-arrow-counterclockwise"></i></a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Registros -->
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 simp-table">
                <thead>
                    <tr>
                        <th>Fecha y hora</th>
                        <th>Usuario</th>
                        <th>Acción</th>
                        <th class="d-none d-lg-table-cell">Registro afectado</th>
                        <th class="d-none d-xl-table-cell">IP</th>
                        <th class="text-end">Detalle</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($logs)): ?>
                    <tr><td colspan="6">
                        <div class="empty-state"><i class="bi bi-journal"></i><p class="mt-3 mb-0">No hay actividad en el periodo y filtros seleccionados.</p></div>
                    </td></tr>
                <?php else: ?>
                    <?php foreach ($logs as $log):
                        $antes = $decodificar($log['datos_anteriores'] ?? null);
                        $despues = $decodificar($log['datos_nuevos'] ?? null);
                        $filas = ($antes !== null || $despues !== null) ? $diferencias($antes, $despues) : null;
                    ?>
                        <tr>
                            <td class="small text-nowrap"><?php echo date('d/m/Y', strtotime($log['created_at'])); ?><br><span class="text-muted"><?php echo date('H:i:s', strtotime($log['created_at'])); ?></span></td>
                            <td class="small fw-semibold"><?php echo htmlspecialchars($log['usuario_nombre'] ?? 'Sistema'); ?></td>
                            <td><span class="act-pill act-<?php echo $tipoAccion($log['accion']); ?>"><?php echo htmlspecialchars($nombreAccion($log['accion'])); ?></span></td>
                            <td class="d-none d-lg-table-cell small">
                                <?php if (!empty($log['tabla_afectada'])): ?>
                                    <?php echo htmlspecialchars($log['tabla_afectada']); ?><?php echo !empty($log['registro_id']) ? ' <span class="text-muted">#' . (int)$log['registro_id'] . '</span>' : ''; ?>
                                <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                            </td>
                            <td class="d-none d-xl-table-cell small mono"><?php echo htmlspecialchars($log['ip_address'] ?? '—'); ?></td>
                            <td class="text-end">
                                <?php if ($filas !== null): ?>
                                    <button class="icon-btn" data-bs-toggle="modal" data-bs-target="#modalLog<?php echo (int)$log['id']; ?>" title="Ver cambios"><i class="bi bi-card-list"></i></button>
                                    <div class="modal fade" id="modalLog<?php echo (int)$log['id']; ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-lg modal-dialog-scrollable">
                                            <div class="modal-content text-start">
                                                <div class="modal-header">
                                                    <div>
                                                        <h5 class="modal-title mb-0"><?php echo htmlspecialchars($nombreAccion($log['accion'])); ?></h5>
                                                        <div class="small text-muted">Evento #<?php echo (int)$log['id']; ?> · <?php echo date('d/m/Y H:i:s', strtotime($log['created_at'])); ?> · <?php echo htmlspecialchars($log['usuario_nombre'] ?? 'Sistema'); ?></div>
                                                    </div>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <?php if (empty($filas)): ?>
                                                        <p class="text-muted mb-0">No hay diferencias en los campos registrados.</p>
                                                    <?php else: ?>
                                                        <table class="table table-sm diff-table mb-0">
                                                            <thead><tr><th>Campo</th><th>Antes</th><th>Después</th></tr></thead>
                                                            <tbody>
                                                            <?php foreach ($filas as $f): ?>
                                                                <tr>
                                                                    <td class="fw-semibold"><?php echo htmlspecialchars($f[0]); ?></td>
                                                                    <td class="diff-old"><?php echo htmlspecialchars($f[1]); ?></td>
                                                                    <td class="diff-new"><?php echo htmlspecialchars($f[2]); ?></td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                            </tbody>
                                                        </table>
                                                    <?php endif; ?>
                                                    <?php if (!empty($log['user_agent'])): ?>
                                                        <div class="small text-muted mt-3"><strong>Navegador:</strong> <?php echo htmlspecialchars($log['user_agent']); ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total > 0): ?>
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 px-3 py-3 border-top">
            <div class="small text-muted">Mostrando <?php echo number_format($desde); ?>–<?php echo number_format($hasta); ?> de <?php echo number_format($total); ?> eventos</div>
            <?php if ($totalPaginas > 1): ?>
            <nav aria-label="Paginación">
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>"><a class="page-link" href="<?php echo $urlPagina($page - 1); ?>" aria-label="Anterior"><i class="bi bi-chevron-left"></i></a></li>
                    <?php $previa = 0; foreach ($ventana as $n): ?>
                        <?php if ($previa && $n - $previa > 1): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
                        <li class="page-item <?php echo $n === $page ? 'active' : ''; ?>"><a class="page-link" href="<?php echo $urlPagina($n); ?>"><?php echo $n; ?></a></li>
                    <?php $previa = $n; endforeach; ?>
                    <li class="page-item <?php echo $page >= $totalPaginas ? 'disabled' : ''; ?>"><a class="page-link" href="<?php echo $urlPagina($page + 1); ?>" aria-label="Siguiente"><i class="bi bi-chevron-right"></i></a></li>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../../app/views/layouts/footer.php'; ?>
