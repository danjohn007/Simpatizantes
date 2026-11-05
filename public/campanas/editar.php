<?php
/**
 * Editar Campaña
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../app/controllers/AuthController.php';
require_once __DIR__ . '/../../app/models/Campana.php';
require_once __DIR__ . '/../../app/models/Usuario.php';

$auth = new AuthController();
$auth->requiereRol(['super_admin', 'admin']);

$campanaModel = new Campana();
$usuarioModel = new Usuario();

// Validar ID y existencia
$id = isset($_GET['id']) && ctype_digit((string)$_GET['id']) ? (int)$_GET['id'] : 0;
$campana = $id ? $campanaModel->obtenerPorId($id) : null;

if (!$campana) {
    $_SESSION['error'] = 'Campaña no encontrada';
    header('Location: index.php');
    exit;
}

$error = '';
$errores = [];

// Valores del formulario: los enviados o los guardados
$form = [
    'nombre'       => $campana['nombre'],
    'descripcion'  => $campana['descripcion'] ?? '',
    'fecha_inicio' => $campana['fecha_inicio'],
    'fecha_fin'    => $campana['fecha_fin'],
    'candidato_id' => $campana['candidato_id'],
    'activa'       => (int)$campana['activa'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = [
        'nombre'       => trim($_POST['nombre'] ?? ''),
        'descripcion'  => trim($_POST['descripcion'] ?? ''),
        'fecha_inicio' => $_POST['fecha_inicio'] ?? '',
        'fecha_fin'    => $_POST['fecha_fin'] ?? '',
        'candidato_id' => !empty($_POST['candidato_id']) ? (int)$_POST['candidato_id'] : null,
        'activa'       => isset($_POST['activa']) ? 1 : 0,
    ];

    if ($form['nombre'] === '') {
        $errores[] = 'El nombre de la campaña es obligatorio';
    } elseif (mb_strlen($form['nombre']) > 150) {
        $errores[] = 'El nombre no puede exceder 150 caracteres';
    }
    if ($form['fecha_inicio'] === '') {
        $errores[] = 'La fecha de inicio es obligatoria';
    }
    if ($form['fecha_fin'] === '') {
        $errores[] = 'La fecha de fin es obligatoria';
    }
    if ($form['fecha_inicio'] !== '' && $form['fecha_fin'] !== ''
        && strtotime($form['fecha_fin']) < strtotime($form['fecha_inicio'])) {
        $errores[] = 'La fecha de fin debe ser posterior a la fecha de inicio';
    }

    if (empty($errores)) {
        if ($campanaModel->actualizar($id, $form)) {
            $_SESSION['success'] = 'Campaña actualizada correctamente';
            header('Location: index.php');
            exit;
        }
        $error = 'No se pudo actualizar la campaña. Intente de nuevo.';
    }
}

$candidatos = $usuarioModel->obtenerTodos(1, 100, 'candidato');

$pageTitle = 'Editar Campaña';
include __DIR__ . '/../../app/views/layouts/header.php';
?>

<div class="container-fluid p-0">
    <div class="page-head">
        <div>
            <div class="eyebrow">Campañas</div>
            <h1>Editar campaña</h1>
            <div class="meta"><span><i class="bi bi-megaphone"></i><?php echo htmlspecialchars($campana['nombre']); ?></span></div>
        </div>
        <a href="index.php" class="btn btn-soft"><i class="bi bi-arrow-left me-2"></i>Volver</a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if (!empty($errores)): ?>
        <div class="alert alert-danger">
            <strong>Corrija lo siguiente:</strong>
            <ul class="mb-0 mt-2">
                <?php foreach ($errores as $err): ?><li><?php echo htmlspecialchars($err); ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header"><h5 class="mb-0"><i class="bi bi-pencil-square me-2"></i>Información de la campaña</h5></div>
                <div class="card-body">
                    <form method="POST" action="?id=<?php echo $id; ?>">
<?php echo csrf_field(); ?>
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Nombre <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="nombre" required maxlength="150"
                                       value="<?php echo htmlspecialchars($form['nombre']); ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Descripción</label>
                                <textarea class="form-control" name="descripcion" rows="3"><?php echo htmlspecialchars($form['descripcion']); ?></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Fecha de inicio <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="fecha_inicio" required
                                       value="<?php echo htmlspecialchars($form['fecha_inicio']); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Fecha de fin <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="fecha_fin" required
                                       value="<?php echo htmlspecialchars($form['fecha_fin']); ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Candidato</label>
                                <select class="form-select" name="candidato_id">
                                    <option value="">Sin candidato asignado</option>
                                    <?php foreach ($candidatos as $c): ?>
                                        <option value="<?php echo (int)$c['id']; ?>" <?php echo ((int)$form['candidato_id'] === (int)$c['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($c['nombre_completo']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" name="activa" id="activa" value="1" <?php echo $form['activa'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="activa">Campaña activa</label>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex justify-content-end gap-2 mt-4">
                            <a href="index.php" class="btn btn-soft">Cancelar</a>
                            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-2"></i>Guardar cambios</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card">
                <div class="card-body">
                    <h6 class="mb-3"><i class="bi bi-info-circle me-2 text-muted"></i>Ten en cuenta</h6>
                    <p class="small text-muted mb-2">Al desactivar una campaña deja de aparecer como opción al capturar nuevos simpatizantes. Los registros existentes se conservan.</p>
                    <p class="small text-muted mb-0">La fecha de fin debe ser igual o posterior a la fecha de inicio.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../app/views/layouts/footer.php'; ?>
