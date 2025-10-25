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
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

if (!empty($_GET['rol'])) {
    $filtros['rol'] = $_GET['rol'];
}

// Obtener datos
$resultado = $controller->listar($filtros, $page);

$pageTitle = 'Usuarios';
include __DIR__ . '/../../app/views/layouts/header.php';
?>

<div class="container-fluid">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-md-6">
            <h2><i class="bi bi-person-badge-fill me-2"></i>Usuarios</h2>
            <p class="text-muted">Gestión de usuarios del sistema</p>
        </div>
        <div class="col-md-6 text-end">
            <a href="crear.php" class="btn btn-gradient">
                <i class="bi bi-person-plus-fill me-2"></i>Nuevo Usuario
            </a>
        </div>
    </div>
    
    <!-- Filtros -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label small">Filtrar por Rol</label>
                    <select class="form-select" name="rol" onchange="this.form.submit()">
                        <option value="">Todos los roles</option>
                        <option value="super_admin" <?php echo (isset($_GET['rol']) && $_GET['rol'] === 'super_admin') ? 'selected' : ''; ?>>
                            Super Admin
                        </option>
                        <option value="admin" <?php echo (isset($_GET['rol']) && $_GET['rol'] === 'admin') ? 'selected' : ''; ?>>
                            Administrador
                        </option>
                        <option value="candidato" <?php echo (isset($_GET['rol']) && $_GET['rol'] === 'candidato') ? 'selected' : ''; ?>>
                            Candidato
                        </option>
                        <option value="coordinador" <?php echo (isset($_GET['rol']) && $_GET['rol'] === 'coordinador') ? 'selected' : ''; ?>>
                            Coordinador
                        </option>
                        <option value="capturista" <?php echo (isset($_GET['rol']) && $_GET['rol'] === 'capturista') ? 'selected' : ''; ?>>
                            Capturista
                        </option>
                    </select>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Resultados -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    Total: <?php echo number_format($resultado['total']); ?> usuarios
                </h5>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Usuario</th>
                            <th>Nombre Completo</th>
                            <th>Email</th>
                            <th>Rol</th>
                            <th>Estado</th>
                            <th>Fecha Registro</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($resultado['usuarios'])): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">
                                    <i class="bi bi-inbox" style="font-size: 3rem;"></i>
                                    <p class="mt-2">No se encontraron usuarios</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($resultado['usuarios'] as $usuario): ?>
                                <tr>
                                    <td><?php echo $usuario['id']; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($usuario['username']); ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($usuario['nombre_completo']); ?></td>
                                    <td><small><?php echo htmlspecialchars($usuario['email']); ?></small></td>
                                    <td>
                                        <?php
                                        $rolClasses = [
                                            'super_admin' => 'danger',
                                            'admin' => 'warning',
                                            'candidato' => 'info',
                                            'coordinador' => 'primary',
                                            'capturista' => 'success'
                                        ];
                                        $rolClass = $rolClasses[$usuario['rol']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?php echo $rolClass; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $usuario['rol'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($usuario['activo']): ?>
                                            <span class="badge bg-success">Activo</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inactivo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small><?php echo date('d/m/Y', strtotime($usuario['created_at'])); ?></small>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="editar.php?id=<?php echo $usuario['id']; ?>" 
                                               class="btn btn-warning btn-sm" title="Editar">
                                                <i class="bi bi-pencil-fill"></i>
                                            </a>
                                            <a href="suspender.php?id=<?php echo $usuario['id']; ?>" 
                                               class="btn btn-<?php echo $usuario['activo'] ? 'secondary' : 'success'; ?> btn-sm" 
                                               title="<?php echo $usuario['activo'] ? 'Suspender' : 'Activar'; ?>">
                                                <i class="bi bi-<?php echo $usuario['activo'] ? 'pause' : 'play'; ?>-circle-fill"></i>
                                            </a>
                                            <?php if ($auth->obtenerRol() === 'super_admin'): ?>
                                                <a href="eliminar.php?id=<?php echo $usuario['id']; ?>" 
                                                   class="btn btn-danger btn-sm" title="Eliminar"
                                                   onclick="return confirmarEliminar()">
                                                    <i class="bi bi-trash-fill"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Paginación -->
            <?php if ($resultado['total_paginas'] > 1): ?>
                <nav class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php for ($i = 1; $i <= $resultado['total_paginas']; $i++): ?>
                            <li class="page-item <?php echo ($i == $resultado['pagina_actual']) ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&<?php echo http_build_query($filtros); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../app/views/layouts/footer.php'; ?>
