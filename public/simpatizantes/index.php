<?php
/**
 * Listado de Simpatizantes
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../app/controllers/AuthController.php';
require_once __DIR__ . '/../../app/controllers/SimpatizanteController.php';
require_once __DIR__ . '/../../app/models/Campana.php';

$auth = new AuthController();
$auth->requiereAutenticacion();

$controller = new SimpatizanteController();
$campanaModel = new Campana();

// Procesar filtros
$filtros = [];
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

// Filtrar por campaña del usuario si no es admin
if (!$auth->puedeVerTodasLasCampanas()) {
    $filtros['campana_id'] = $auth->obtenerCampanaId();
} elseif (!empty($_GET['campana_id'])) {
    $filtros['campana_id'] = $_GET['campana_id'];
}

if (!empty($_GET['seccion'])) {
    $filtros['seccion_electoral'] = $_GET['seccion'];
}

if (!empty($_GET['busqueda'])) {
    $filtros['busqueda'] = $_GET['busqueda'];
}

if (!empty($_GET['estatus']) && in_array($_GET['estatus'], ['pendiente', 'validado', 'rechazado'], true)) {
    $filtros['estatus'] = $_GET['estatus'];
}

if (!empty($_GET['fecha_inicio'])) {
    $filtros['fecha_inicio'] = $_GET['fecha_inicio'];
}

if (!empty($_GET['fecha_fin'])) {
    $filtros['fecha_fin'] = $_GET['fecha_fin'];
}

// Obtener datos
$resultado = $controller->listar($filtros, $page);
$campanas = $campanaModel->obtenerTodas(1);

$pageTitle = 'Simpatizantes';
include __DIR__ . '/../../app/views/layouts/header.php';
?>

<?php
$puedeEliminar = in_array($auth->obtenerRol(), ['super_admin', 'admin']);
$puedeImportar = in_array($auth->obtenerRol(), ['super_admin', 'admin', 'coordinador']);
$porEstatus    = $resultado['por_estatus'] ?? ['pendiente' => 0, 'validado' => 0, 'rechazado' => 0];
$estatusSel    = isset($_GET["estatus"]) && is_string($_GET["estatus"]) ? $_GET["estatus"] : "";
$urlEstatus    = function ($e) {
    $q = $_GET; unset($q['page']);
    if ($e === '') { unset($q['estatus']); } else { $q['estatus'] = $e; }
    return '?' . http_build_query($q);
};
$paginaActual  = max(1, (int)$resultado['pagina_actual']);
$totalPaginas  = max(1, (int)$resultado['total_paginas']);
$total         = (int)$resultado['total'];
$porPagina     = defined('RECORDS_PER_PAGE') ? RECORDS_PER_PAGE : 25;
$desde         = $total > 0 ? (($paginaActual - 1) * $porPagina) + 1 : 0;
$hasta         = min($total, $paginaActual * $porPagina);

// Filtros activos (para el aviso y para conservarlos al paginar)
$filtrosGet = [];
foreach (['busqueda', 'campana_id', 'seccion', 'estatus', 'fecha_inicio', 'fecha_fin'] as $k) {
    if (isset($_GET[$k]) && $_GET[$k] !== '') { $filtrosGet[$k] = $_GET[$k]; }
}
$hayFiltros = !empty($filtrosGet);
$urlPagina  = function ($n) use ($filtrosGet) { return '?' . http_build_query(array_merge($filtrosGet, ['page' => $n])); };

// Ventana de paginación: 1 … 4 5 [6] 7 8 … N
$ventana = [];
for ($i = 1; $i <= $totalPaginas; $i++) {
    if ($i === 1 || $i === $totalPaginas || abs($i - $paginaActual) <= 2) { $ventana[] = $i; }
}
?>
<div class="container-fluid p-0">
    <div class="page-head">
        <div>
            <div class="eyebrow">Padrón</div>
            <h1>Simpatizantes</h1>
            <div class="meta"><span><i class="bi bi-people"></i><?php echo number_format($total); ?> registros<?php echo $hayFiltros ? ' con los filtros aplicados' : ''; ?></span></div>
        </div>
        <div class="d-flex gap-2">
            <?php if ($puedeImportar): ?>
            <a href="<?php echo BASE_URL; ?>/public/simpatizantes/importar.php" class="btn btn-soft">
                <i class="bi bi-upload me-2"></i>Importar
            </a>
            <?php endif; ?>
            <div class="btn-group" role="group" aria-label="Exportar">
                <a href="<?php echo BASE_URL; ?>/public/simpatizantes/exportar.php?<?php echo http_build_query($filtrosGet + ['formato' => 'xlsx']); ?>" class="btn btn-soft" title="Descargar en Excel con los filtros aplicados">
                    <i class="bi bi-file-earmark-excel me-1"></i>Excel
                </a>
                <a href="<?php echo BASE_URL; ?>/public/simpatizantes/exportar.php?<?php echo http_build_query($filtrosGet + ['formato' => 'csv']); ?>" class="btn btn-soft" title="Descargar en CSV con los filtros aplicados">
                    <i class="bi bi-filetype-csv me-1"></i>CSV
                </a>
            </div>
            <a href="<?php echo BASE_URL; ?>/public/simpatizantes/crear.php" class="btn btn-primary">
                <i class="bi bi-person-plus-fill me-2"></i>Nuevo simpatizante
            </a>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="" class="row g-3 align-items-end">
                <div class="col-lg-3 col-md-6">
                    <label class="form-label small text-muted mb-1">Búsqueda</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" class="form-control border-start-0" name="busqueda" placeholder="Nombre, CURP o clave"
                               value="<?php echo htmlspecialchars($_GET['busqueda'] ?? ''); ?>">
                    </div>
                </div>
                <?php if ($auth->puedeVerTodasLasCampanas()): ?>
                <div class="col-lg-2 col-md-6">
                    <label class="form-label small text-muted mb-1">Campaña</label>
                    <select class="form-select" name="campana_id">
                        <option value="">Todas</option>
                        <?php foreach ($campanas as $campana): ?>
                            <option value="<?php echo (int)$campana['id']; ?>" <?php echo (isset($_GET['campana_id']) && $_GET['campana_id'] == $campana['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($campana['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="col-lg-2 col-md-4">
                    <label class="form-label small text-muted mb-1">Estado</label>
                    <select class="form-select" name="estatus">
                        <option value="">Todos</option>
                        <option value="pendiente" <?php echo $estatusSel === 'pendiente' ? 'selected' : ''; ?>>Pendiente</option>
                        <option value="validado" <?php echo $estatusSel === 'validado' ? 'selected' : ''; ?>>Validado</option>
                        <option value="rechazado" <?php echo $estatusSel === 'rechazado' ? 'selected' : ''; ?>>Rechazado</option>
                    </select>
                </div>
                <div class="col-lg-2 col-md-4">
                    <label class="form-label small text-muted mb-1">Sección</label>
                    <input type="text" class="form-control" name="seccion" placeholder="Ej. 0123" value="<?php echo htmlspecialchars($_GET['seccion'] ?? ''); ?>">
                </div>
                <div class="col-lg-2 col-md-4">
                    <label class="form-label small text-muted mb-1">Desde</label>
                    <input type="date" class="form-control" name="fecha_inicio" value="<?php echo htmlspecialchars($_GET['fecha_inicio'] ?? ''); ?>">
                </div>
                <div class="col-lg-2 col-md-4">
                    <label class="form-label small text-muted mb-1">Hasta</label>
                    <input type="date" class="form-control" name="fecha_fin" value="<?php echo htmlspecialchars($_GET['fecha_fin'] ?? ''); ?>">
                </div>
                <div class="col-lg-auto col-12">
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-fill" title="Filtrar"><i class="bi bi-funnel"></i></button>
                        <?php if ($hayFiltros): ?>
                            <a href="<?php echo BASE_URL; ?>/public/simpatizantes/index.php" class="btn btn-soft" title="Limpiar filtros"><i class="bi bi-x-lg"></i></a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Resumen por estado -->
    <div class="d-flex flex-wrap gap-2 mb-3">
        <?php foreach ([
            ['', 'Todos', array_sum($porEstatus)],
            ['pendiente', 'Pendientes', $porEstatus['pendiente']],
            ['validado', 'Validados', $porEstatus['validado']],
            ['rechazado', 'Rechazados', $porEstatus['rechazado']],
        ] as $chip): ?>
            <a href="<?php echo $urlEstatus($chip[0]); ?>" class="btn btn-sm <?php echo $estatusSel === $chip[0] ? 'btn-primary' : 'btn-soft'; ?>">
                <?php echo $chip[1]; ?> <span class="ms-1 opacity-75"><?php echo number_format($chip[2]); ?></span>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Resultados -->
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 simp-table">
                <thead>
                    <tr>
                        <th>Simpatizante</th>
                        <th>Sección</th>
                        <th class="d-none d-xl-table-cell">Clave de elector / CURP</th>
                        <th class="d-none d-lg-table-cell">Campaña</th>
                        <th class="d-none d-xl-table-cell">Capturista</th>
                        <th>Registro</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($resultado['simpatizantes'])): ?>
                    <tr>
                        <td colspan="7">
                            <div class="empty-state">
                                <i class="bi bi-people"></i>
                                <p class="mt-3 mb-1"><?php echo $hayFiltros ? 'Ningún simpatizante coincide con los filtros.' : 'Aún no hay simpatizantes registrados.'; ?></p>
                                <?php if ($hayFiltros): ?>
                                    <a href="<?php echo BASE_URL; ?>/public/simpatizantes/index.php" class="btn btn-sm btn-soft mt-2">Limpiar filtros</a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($resultado['simpatizantes'] as $simp):
                        $nombre = trim($simp['nombre_completo'] ?? '');
                        $partes = preg_split('/\s+/', $nombre);
                        $iniciales = mb_strtoupper(mb_substr($partes[0] ?? '', 0, 1) . mb_substr($partes[1] ?? '', 0, 1));
                        $domicilio = mb_strimwidth((string)($simp['domicilio_completo'] ?? ''), 0, 48, '…');
                        $estatus = $simp['estatus_validacion'] ?? (!empty($simp['validado']) ? 'validado' : 'pendiente');
                        $pillClase = ['pendiente' => 'pend', 'validado' => 'on', 'rechazado' => 'bad'][$estatus] ?? 'off';
                        $pillTexto = ['pendiente' => 'Pendiente', 'validado' => 'Validado', 'rechazado' => 'Rechazado'][$estatus] ?? 'Pendiente';
                    ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <span class="avatar"><?php echo htmlspecialchars($iniciales ?: '?'); ?></span>
                                    <div class="min-w-0">
                                        <a class="fw-semibold text-decoration-none" href="<?php echo BASE_URL; ?>/public/simpatizantes/ver.php?id=<?php echo (int)$simp['id']; ?>">
                                            <?php echo htmlspecialchars($nombre); ?>
                                        </a>
                                        <span class="status-pill <?php echo $pillClase; ?> ms-1"><?php echo $pillTexto; ?></span>
                                        <div class="small text-muted"><?php echo htmlspecialchars($domicilio); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td><span class="sec-pill"><?php echo htmlspecialchars($simp['seccion_electoral'] ?? '—'); ?></span></td>
                            <td class="d-none d-xl-table-cell">
                                <div class="mono small"><?php echo htmlspecialchars($simp['clave_elector'] ?: '—'); ?></div>
                                <div class="mono small text-muted"><?php echo htmlspecialchars($simp['curp'] ?: '—'); ?></div>
                            </td>
                            <td class="d-none d-lg-table-cell small"><?php echo htmlspecialchars($simp['campana_nombre'] ?? '—'); ?></td>
                            <td class="d-none d-xl-table-cell small"><?php echo htmlspecialchars($simp['capturista_nombre'] ?? '—'); ?></td>
                            <td class="small text-nowrap"><?php echo date('d/m/Y', strtotime($simp['created_at'])); ?></td>
                            <td class="text-end text-nowrap">
                                <a href="<?php echo BASE_URL; ?>/public/simpatizantes/ver.php?id=<?php echo (int)$simp['id']; ?>" class="icon-btn" title="Ver detalle"><i class="bi bi-eye"></i></a>
                                <a href="<?php echo BASE_URL; ?>/public/simpatizantes/editar.php?id=<?php echo (int)$simp['id']; ?>" class="icon-btn" title="Editar"><i class="bi bi-pencil"></i></a>
                                <?php if ($puedeEliminar): ?>
                                    <a href="<?php echo BASE_URL; ?>/public/simpatizantes/eliminar.php?id=<?php echo (int)$simp['id']; ?>" class="icon-btn danger" title="Eliminar"><i class="bi bi-trash"></i></a>
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
                    <li class="page-item <?php echo $paginaActual <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="<?php echo $urlPagina($paginaActual - 1); ?>" aria-label="Anterior"><i class="bi bi-chevron-left"></i></a>
                    </li>
                    <?php $previa = 0; foreach ($ventana as $n): ?>
                        <?php if ($previa && $n - $previa > 1): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
                        <li class="page-item <?php echo $n === $paginaActual ? 'active' : ''; ?>">
                            <a class="page-link" href="<?php echo $urlPagina($n); ?>"><?php echo $n; ?></a>
                        </li>
                    <?php $previa = $n; endforeach; ?>
                    <li class="page-item <?php echo $paginaActual >= $totalPaginas ? 'disabled' : ''; ?>">
                        <a class="page-link" href="<?php echo $urlPagina($paginaActual + 1); ?>" aria-label="Siguiente"><i class="bi bi-chevron-right"></i></a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../../app/views/layouts/footer.php'; ?>
