<?php
/**
 * Atención ciudadana: bandeja de reportes y denuncias que llegan desde la app.
 * Permisos: super admin, admin y coordinador.
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../app/helpers/security.php';
require_once __DIR__ . '/../../app/controllers/AuthController.php';
require_once __DIR__ . '/../../app/services/CiudadanoAuthService.php';
require_once __DIR__ . '/../../app/models/ReporteCiudadano.php';

$auth = new AuthController();
$auth->requiereRol(['super_admin', 'admin', 'coordinador']);

$disponible = (new CiudadanoAuthService())->disponible();
$modelo = new ReporteCiudadano();

$f = [];
foreach (['estado' => ReporteCiudadano::ESTADOS, 'categoria' => ReporteCiudadano::CATEGORIAS] as $k => $lista) {
    if (!empty($_GET[$k]) && is_string($_GET[$k]) && isset($lista[$_GET[$k]])) { $f[$k] = $_GET[$k]; }
}
if (!empty($_GET['tipo']) && in_array($_GET['tipo'], ['reporte', 'denuncia'], true)) { $f['tipo'] = $_GET['tipo']; }
if (!empty($_GET['busqueda']) && is_string($_GET['busqueda'])) { $f['busqueda'] = mb_substr(trim($_GET['busqueda']), 0, 80); }
if (!empty($_GET['abiertos'])) { $f['abiertos'] = 1; }

$pagina = isset($_GET['page']) && ctype_digit((string)$_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$porPagina = 25;
$resumen = ['recibido' => 0, 'revision' => 0, 'atendido' => 0, 'cerrado' => 0, 'vencidos' => 0];
$filas = [];
$total = 0;
if ($disponible) {
    $resumen = $modelo->resumen();
    $total = $modelo->contar($f);
    $filas = $modelo->listar($f, $pagina, $porPagina);
}
$totalPaginas = max(1, (int)ceil($total / $porPagina));
$qs = $_GET; unset($qs['page']);
$url = function ($n) use ($qs) { return '?' . http_build_query($qs + ['page' => $n]); };
$sel = function ($k, $v) { return (($_GET[$k] ?? '') === (string)$v) ? 'selected' : ''; };
$clasePill = ['recibido' => 'pend', 'revision' => 'pend', 'atendido' => 'on', 'cerrado' => 'off'];
$ahora = time();
$antig = function ($t) use ($ahora) {
    $d = (int)floor(($ahora - strtotime($t)) / 86400);
    return $d <= 0 ? 'Hoy' : ($d === 1 ? 'Ayer' : 'Hace ' . $d . ' días');
};

$pageTitle = 'Atención ciudadana';
include __DIR__ . '/../../app/views/layouts/header.php';
?>
<div class="container-fluid p-0">
    <div class="page-head">
        <div>
            <div class="eyebrow">Comunidad</div>
            <h1>Atención ciudadana</h1>
            <div class="meta"><span><i class="bi bi-megaphone"></i>Reportes y denuncias que llegan desde la app</span></div>
        </div>
        <a href="eventos.php" class="btn btn-soft"><i class="bi bi-calendar-event me-2"></i>Eventos y avisos</a>
    </div>

<?php if (!$disponible): ?>
    <div class="alert alert-warning"><i class="bi bi-exclamation-triangle-fill me-2"></i>Falta ejecutar <code>database/atencion_ciudadana.sql</code> (phpMyAdmin &gt; SQL) para activar la atención ciudadana.</div>
<?php else: ?>
    <div class="row g-3 mb-3">
        <div class="col-md-3 col-6"><div class="card kpi"><i class="bi bi-inbox ico"></i><div class="label">Recibidos</div><div class="value"><?php echo number_format($resumen['recibido']); ?></div><div class="hint">sin revisar</div></div></div>
        <div class="col-md-3 col-6"><div class="card kpi"><i class="bi bi-hourglass-split ico"></i><div class="label">En revisión</div><div class="value"><?php echo number_format($resumen['revision']); ?></div><div class="hint">en atención</div></div></div>
        <div class="col-md-3 col-6"><div class="card kpi"><i class="bi bi-check2-circle ico"></i><div class="label">Atendidos</div><div class="value"><?php echo number_format($resumen['atendido'] + $resumen['cerrado']); ?></div><div class="hint">resueltos</div></div></div>
        <div class="col-md-3 col-6"><a class="text-decoration-none" href="?abiertos=1"><div class="card kpi"><i class="bi bi-alarm ico"></i><div class="label">Con más de una semana</div><div class="value" style="<?php echo $resumen['vencidos'] ? 'color:#8a2f25' : ''; ?>"><?php echo number_format($resumen['vencidos']); ?></div><div class="hint">abiertos sin resolver</div></div></a></div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="" class="row g-3 align-items-end">
                <div class="col-lg-3 col-md-6">
                    <label class="form-label small text-muted mb-1">Buscar</label>
                    <input type="text" class="form-control" name="busqueda" placeholder="Folio, texto o colonia" value="<?php echo htmlspecialchars($_GET['busqueda'] ?? ''); ?>">
                </div>
                <div class="col-lg-2 col-md-6">
                    <label class="form-label small text-muted mb-1">Estado</label>
                    <select class="form-select" name="estado"><option value="">Todos</option>
                        <?php foreach (ReporteCiudadano::ESTADOS as $k => $t): ?><option value="<?php echo $k; ?>" <?php echo $sel('estado', $k); ?>><?php echo $t; ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-2 col-md-6">
                    <label class="form-label small text-muted mb-1">Tema</label>
                    <select class="form-select" name="categoria"><option value="">Todos</option>
                        <?php foreach (ReporteCiudadano::CATEGORIAS as $k => $t): ?><option value="<?php echo $k; ?>" <?php echo $sel('categoria', $k); ?>><?php echo $t; ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-2 col-md-6">
                    <label class="form-label small text-muted mb-1">Tipo</label>
                    <select class="form-select" name="tipo"><option value="">Todos</option>
                        <option value="reporte" <?php echo $sel('tipo', 'reporte'); ?>>Reportes</option>
                        <option value="denuncia" <?php echo $sel('tipo', 'denuncia'); ?>>Denuncias</option>
                    </select>
                </div>
                <div class="col-lg-auto d-flex gap-2">
                    <button class="btn btn-primary"><i class="bi bi-funnel me-2"></i>Filtrar</button>
                    <a href="index.php" class="btn btn-soft">Limpiar</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 simp-table">
                <thead><tr><th>Folio</th><th>Tema</th><th>Descripción</th><th class="d-none d-lg-table-cell">Colonia</th><th>Estado</th><th>Recibido</th><th class="d-none d-xl-table-cell">Atiende</th><th></th></tr></thead>
                <tbody>
                <?php if (empty($filas)): ?>
                    <tr><td colspan="8"><div class="empty-state"><i class="bi bi-inbox"></i><p class="mt-3 mb-0">No hay reportes con estos filtros.</p></div></td></tr>
                <?php endif; ?>
                <?php foreach ($filas as $r): $vencido = in_array($r['estado'], ['recibido', 'revision'], true) && strtotime($r['created_at']) < $ahora - 7 * 86400; ?>
                    <tr>
                        <td class="fw-semibold text-nowrap"><a class="text-decoration-none" href="ver.php?id=<?php echo (int)$r['id']; ?>"><?php echo htmlspecialchars($r['folio']); ?></a></td>
                        <td class="text-nowrap">
                            <?php echo htmlspecialchars(ReporteCiudadano::CATEGORIAS[$r['categoria']] ?? $r['categoria']); ?>
                            <?php if ($r['tipo'] === 'denuncia'): ?><span class="status-pill bad ms-1"><?php echo $r['anonimo'] ? 'Anónima' : 'Denuncia'; ?></span><?php endif; ?>
                        </td>
                        <td class="small"><?php echo htmlspecialchars(mb_strimwidth($r['descripcion'], 0, 90, '…')); ?><?php if ((int)$r['fotos'] > 0): ?> <i class="bi bi-image text-muted" title="Tiene fotos"></i><?php endif; ?></td>
                        <td class="d-none d-lg-table-cell small"><?php echo htmlspecialchars($r['colonia'] ?? '—'); ?></td>
                        <td><span class="status-pill <?php echo $clasePill[$r['estado']]; ?>"><?php echo ReporteCiudadano::ESTADOS[$r['estado']]; ?></span></td>
                        <td class="small text-nowrap" <?php echo $vencido ? 'style="color:#8a2f25;font-weight:600"' : ''; ?>><?php echo $antig($r['created_at']); ?></td>
                        <td class="d-none d-xl-table-cell small"><?php echo htmlspecialchars($r['asignado_nombre'] ?? '—'); ?></td>
                        <td class="text-end"><a class="icon-btn" href="ver.php?id=<?php echo (int)$r['id']; ?>" title="Atender"><i class="bi bi-eye"></i></a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($total > 0): ?>
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 px-3 py-3 border-top">
            <div class="small text-muted"><?php echo number_format($total); ?> en total. Los abiertos aparecen primero.</div>
            <?php if ($totalPaginas > 1): ?>
            <ul class="pagination pagination-sm mb-0">
                <li class="page-item <?php echo $pagina <= 1 ? 'disabled' : ''; ?>"><a class="page-link" href="<?php echo $url($pagina - 1); ?>"><i class="bi bi-chevron-left"></i></a></li>
                <li class="page-item disabled"><span class="page-link"><?php echo $pagina; ?> / <?php echo $totalPaginas; ?></span></li>
                <li class="page-item <?php echo $pagina >= $totalPaginas ? 'disabled' : ''; ?>"><a class="page-link" href="<?php echo $url($pagina + 1); ?>"><i class="bi bi-chevron-right"></i></a></li>
            </ul>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
<?php endif; ?>
</div>
<?php include __DIR__ . '/../../app/views/layouts/footer.php'; ?>
