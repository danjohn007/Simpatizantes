<?php
/**
 * Listado de Campañas
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../app/controllers/AuthController.php';
require_once __DIR__ . '/../../app/models/Campana.php';

$auth = new AuthController();
$auth->requiereRol(['super_admin', 'admin', 'coordinador']);

$campanaModel = new Campana();

// Filtrar por campaña del usuario si no es admin
if (!$auth->puedeVerTodasLasCampanas()) {
    $campanaId = $auth->obtenerCampanaId();
    if ($campanaId) {
        $campanas = [$campanaModel->obtenerPorId($campanaId)];
    } else {
        $campanas = [];
    }
} else {
    $campanas = $campanaModel->obtenerTodas();
}

$pageTitle = 'Campañas';
include __DIR__ . '/../../app/views/layouts/header.php';
?>

<?php $puedeEditar = in_array($auth->obtenerRol(), ['super_admin', 'admin']); ?>
<div class="container-fluid p-0">
    <div class="page-head">
        <div>
            <div class="eyebrow">Gestión</div>
            <h1>Campañas</h1>
            <div class="meta"><span><i class="bi bi-megaphone"></i>Campañas electorales y su avance de captación</span></div>
        </div>
        <?php if ($puedeEditar): ?>
            <a href="crear.php" class="btn btn-primary">
                <i class="bi bi-plus-lg me-2"></i>Nueva campaña
            </a>
        <?php endif; ?>
    </div>

    <div class="row g-3">
        <?php if (empty($campanas) || empty($campanas[0])): ?>
            <div class="col-12">
                <div class="card">
                    <div class="empty-state">
                        <i class="bi bi-inbox"></i>
                        <p class="mt-3 mb-0">No hay campañas registradas</p>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($campanas as $campana):
                $stats = $campanaModel->obtenerEstadisticas($campana['id']) ?: [];
                $totalSimp = (int)($stats['total_simpatizantes'] ?? 0);
                $totalVal  = (int)($stats['total_validados'] ?? 0);
                $totalSec  = (int)($stats['total_secciones'] ?? 0);
                $totalCap  = (int)($stats['total_capturistas'] ?? 0);
                $pctVal    = $totalSimp > 0 ? round(($totalVal / $totalSimp) * 100) : 0;
                $inicio = !empty($campana['fecha_inicio']) ? date('d/m/Y', strtotime($campana['fecha_inicio'])) : '—';
                $fin    = !empty($campana['fecha_fin']) ? date('d/m/Y', strtotime($campana['fecha_fin'])) : '—';
            ?>
                <div class="col-xl-6">
                    <div class="card camp-card">
                        <div class="camp-top">
                            <div>
                                <h3 class="camp-name"><?php echo htmlspecialchars($campana['nombre']); ?></h3>
                                <p class="camp-desc"><?php echo htmlspecialchars($campana['descripcion'] ?: 'Sin descripción'); ?></p>
                            </div>
                            <?php if ($campana['activa']): ?>
                                <span class="status-pill on">Activa</span>
                            <?php else: ?>
                                <span class="status-pill off">Inactiva</span>
                            <?php endif; ?>
                        </div>

                        <div class="camp-info">
                            <div><div class="k">Inicio</div><div class="v"><?php echo $inicio; ?></div></div>
                            <div><div class="k">Fin</div><div class="v"><?php echo $fin; ?></div></div>
                            <div><div class="k">Candidato</div><div class="v"><?php echo !empty($campana['candidato_nombre']) ? htmlspecialchars($campana['candidato_nombre']) : '—'; ?></div></div>
                        </div>

                        <div class="camp-stats">
                            <div><div class="n"><?php echo number_format($totalSimp); ?></div><div class="t">Simpatizantes</div></div>
                            <div><div class="n"><?php echo number_format($totalVal); ?></div><div class="t">Validados</div></div>
                            <div><div class="n"><?php echo number_format($totalSec); ?></div><div class="t">Secciones</div></div>
                            <div><div class="n"><?php echo number_format($totalCap); ?></div><div class="t">Capturistas</div></div>
                        </div>

                        <div class="camp-prog">
                            <div class="row-p"><span>Avance de validación</span><strong><?php echo $pctVal; ?>%</strong></div>
                            <div class="track"><span style="width: <?php echo $pctVal; ?>%"></span></div>
                        </div>

                        <div class="camp-actions">
                            <a href="ver.php?id=<?php echo (int)$campana['id']; ?>" class="btn btn-sm btn-primary">
                                <i class="bi bi-eye me-1"></i>Ver detalles
                            </a>
                            <?php if ($puedeEditar): ?>
                                <a href="editar.php?id=<?php echo (int)$campana['id']; ?>" class="btn btn-sm btn-soft">
                                    <i class="bi bi-pencil me-1"></i>Editar
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../../app/views/layouts/footer.php'; ?>
