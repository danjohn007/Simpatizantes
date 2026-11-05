<?php
/**
 * Listado de Usuarios
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../app/controllers/AuthController.php';
require_once __DIR__ . '/../../app/controllers/UsuarioController.php';

$auth = new AuthController();
$auth->requiereRol(['super_admin', 'admin']);

$controller = new UsuarioController();

// Procesar filtros
$filtros = [];
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

if (!empty($_GET['rol'])) {
    $filtros['rol'] = $_GET['rol'];
}

if (!empty($_GET['buscar'])) {
    $filtros['buscar'] = $_GET['buscar'];
}

// Obtener datos
$resultado = $controller->listar($filtros, $page);

$pageTitle = 'Usuarios';
include __DIR__ . '/../../app/views/layouts/header.php';
?>

<?php
$esSuper       = $auth->obtenerRol() === 'super_admin';
$miId          = $auth->obtenerUsuarioId();
$paginaActual  = max(1, (int)$resultado['pagina_actual']);
$totalPaginas  = max(1, (int)$resultado['total_paginas']);
$total         = (int)$resultado['total'];
$porPagina     = defined('RECORDS_PER_PAGE') ? RECORDS_PER_PAGE : 25;
$desde         = $total > 0 ? (($paginaActual - 1) * $porPagina) + 1 : 0;
$hasta         = min($total, $paginaActual * $porPagina);

$filtrosGet = [];
foreach (['buscar', 'rol'] as $k) {
    if (isset($_GET[$k]) && $_GET[$k] !== '') { $filtrosGet[$k] = $_GET[$k]; }
}
$hayFiltros = !empty($filtrosGet);
$urlPagina  = function ($n) use ($filtrosGet) { return '?' . http_build_query(array_merge($filtrosGet, ['page' => $n])); };
$ventana = [];
for ($i = 1; $i <= $totalPaginas; $i++) {
    if ($i === 1 || $i === $totalPaginas || abs($i - $paginaActual) <= 2) { $ventana[] = $i; }
}
$roles = ['super_admin' => 'Super admin', 'admin' => 'Administrador', 'candidato' => 'Candidato', 'coordinador' => 'Coordinador', 'capturista' => 'Capturista'];
?>
<div class="container-fluid p-0">
    <div class="page-head">
        <div>
            <div class="eyebrow">Administración</div>
            <h1>Usuarios</h1>
            <div class="meta"><span><i class="bi bi-people"></i><?php echo number_format($total); ?> usuarios<?php echo $hayFiltros ? ' con los filtros aplicados' : ''; ?></span></div>
        </div>
        <a href="crear.php" class="btn btn-primary"><i class="bi bi-person-plus-fill me-2"></i>Nuevo usuario</a>
    </div>

    <!-- Filtros -->
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="" class="row g-3 align-items-end">
                <div class="col-lg-6">
                    <label class="form-label small text-muted mb-1">Buscar por nombre, correo o teléfono</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" class="form-control border-start-0" name="buscar" placeholder="Escriba para buscar"
                               value="<?php echo htmlspecialchars($_GET['buscar'] ?? ''); ?>">
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <label class="form-label small text-muted mb-1">Rol</label>
                    <select class="form-select" name="rol">
                        <option value="">Todos los roles</option>
                        <?php foreach ($roles as $clave => $nombreRol): ?>
                            <option value="<?php echo $clave; ?>" <?php echo (($_GET['rol'] ?? '') === $clave) ? 'selected' : ''; ?>><?php echo $nombreRol; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-fill"><i class="bi bi-funnel me-2"></i>Filtrar</button>
                        <?php if ($hayFiltros): ?>
                            <a href="index.php" class="btn btn-soft" title="Limpiar filtros"><i class="bi bi-x-lg"></i></a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Resultados -->
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 simp-table">
                <thead>
                    <tr>
                        <th>Usuario</th>
                        <th>Rol</th>
                        <th>Estado</th>
                        <th class="d-none d-lg-table-cell">Alta</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($resultado['usuarios'])): ?>
                    <tr><td colspan="5">
                        <div class="empty-state">
                            <i class="bi bi-people"></i>
                            <p class="mt-3 mb-1"><?php echo $hayFiltros ? 'Ningún usuario coincide con los filtros.' : 'No hay usuarios registrados.'; ?></p>
                            <?php if ($hayFiltros): ?><a href="index.php" class="btn btn-sm btn-soft mt-2">Limpiar filtros</a><?php endif; ?>
                        </div>
                    </td></tr>
                <?php else: ?>
                    <?php foreach ($resultado['usuarios'] as $usuario):
                        $partes = preg_split('/\s+/', trim($usuario['nombre_completo']));
                        $iniciales = mb_strtoupper(mb_substr($partes[0] ?? '', 0, 1) . mb_substr($partes[1] ?? '', 0, 1));
                        $esMio = ((int)$usuario['id'] === (int)$miId);
                        $objetivoSuper = ($usuario['rol'] === 'super_admin');
                        $puedeGestionar = $esSuper || !$objetivoSuper;
                    ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <span class="avatar"><?php echo htmlspecialchars($iniciales ?: '?'); ?></span>
                                    <div class="min-w-0">
                                        <div class="fw-semibold">
                                            <?php echo htmlspecialchars($usuario['nombre_completo']); ?>
                                            <?php if ($esMio): ?><span class="status-pill off ms-1">Usted</span><?php endif; ?>
                                        </div>
                                        <div class="small text-muted">@<?php echo htmlspecialchars($usuario['username']); ?> · <?php echo htmlspecialchars($usuario['email']); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td><span class="rol-pill rol-<?php echo htmlspecialchars($usuario['rol']); ?>"><?php echo htmlspecialchars($roles[$usuario['rol']] ?? ucfirst(str_replace('_', ' ', $usuario['rol']))); ?></span></td>
                            <td><span class="status-pill <?php echo $usuario['activo'] ? 'on' : 'off'; ?>"><?php echo $usuario['activo'] ? 'Activo' : 'Inactivo'; ?></span></td>
                            <td class="d-none d-lg-table-cell small text-nowrap"><?php echo date('d/m/Y', strtotime($usuario['created_at'])); ?></td>
                            <td class="text-end text-nowrap">
                                <?php if ($puedeGestionar): ?>
                                    <a href="editar.php?id=<?php echo (int)$usuario['id']; ?>" class="icon-btn" title="Editar"><i class="bi bi-pencil"></i></a>
                                    <?php if (!$esMio): ?>
                                        <a href="suspender.php?id=<?php echo (int)$usuario['id']; ?>" class="icon-btn" title="<?php echo $usuario['activo'] ? 'Suspender' : 'Activar'; ?>">
                                            <i class="bi bi-<?php echo $usuario['activo'] ? 'pause-circle' : 'play-circle'; ?>"></i>
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($esSuper && !$esMio): ?>
                                        <a href="eliminar.php?id=<?php echo (int)$usuario['id']; ?>" class="icon-btn danger" title="Eliminar"><i class="bi bi-trash"></i></a>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted small" title="Solo un super administrador puede gestionar esta cuenta"><i class="bi bi-lock"></i></span>
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
            <div class="small text-muted">Mostrando <?php echo number_format($desde); ?>–<?php echo number_format($hasta); ?> de <?php echo number_format($total); ?></div>
            <?php if ($totalPaginas > 1): ?>
            <nav aria-label="Paginación">
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?php echo $paginaActual <= 1 ? 'disabled' : ''; ?>"><a class="page-link" href="<?php echo $urlPagina($paginaActual - 1); ?>" aria-label="Anterior"><i class="bi bi-chevron-left"></i></a></li>
                    <?php $previa = 0; foreach ($ventana as $n): ?>
                        <?php if ($previa && $n - $previa > 1): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
                        <li class="page-item <?php echo $n === $paginaActual ? 'active' : ''; ?>"><a class="page-link" href="<?php echo $urlPagina($n); ?>"><?php echo $n; ?></a></li>
                    <?php $previa = $n; endforeach; ?>
                    <li class="page-item <?php echo $paginaActual >= $totalPaginas ? 'disabled' : ''; ?>"><a class="page-link" href="<?php echo $urlPagina($paginaActual + 1); ?>" aria-label="Siguiente"><i class="bi bi-chevron-right"></i></a></li>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../../app/views/layouts/footer.php'; ?>
