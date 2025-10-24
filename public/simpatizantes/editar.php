<?php
/**
 * Editar Simpatizante
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../app/controllers/AuthController.php';
require_once __DIR__ . '/../../app/controllers/SimpatizanteController.php';
require_once __DIR__ . '/../../app/models/Campana.php';

$auth = new AuthController();
$auth->requiereAutenticacion();

// Validar ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: ' . BASE_URL . '/public/simpatizantes/index.php');
    exit;
}

$id = (int)$_GET['id'];
$controller = new SimpatizanteController();
$campanaModel = new Campana();

// Obtener simpatizante
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

$error = '';
$success = '';
$errores = [];

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $datos = [
        'nombre_completo' => $_POST['nombre_completo'] ?? '',
        'domicilio_completo' => $_POST['domicilio_completo'] ?? '',
        'sexo' => $_POST['sexo'] ?? '',
        'ciudad' => $_POST['ciudad'] ?? '',
        'clave_elector' => $_POST['clave_elector'] ?? '',
        'curp' => $_POST['curp'] ?? '',
        'fecha_nacimiento' => $_POST['fecha_nacimiento'] ?? '',
        'ano_registro' => $_POST['ano_registro'] ?? '',
        'vigencia' => $_POST['vigencia'] ?? '',
        'seccion_electoral' => $_POST['seccion_electoral'] ?? '',
        'telefono' => $_POST['telefono'] ?? '',
        'whatsapp' => $_POST['whatsapp'] ?? '',
        'email' => $_POST['email'] ?? '',
        'twitter' => $_POST['twitter'] ?? '',
        'instagram' => $_POST['instagram'] ?? '',
        'facebook' => $_POST['facebook'] ?? '',
        'youtube' => $_POST['youtube'] ?? '',
        'tiktok' => $_POST['tiktok'] ?? '',
        'latitud' => $_POST['latitud'] ?? '',
        'longitud' => $_POST['longitud'] ?? '',
        'campana_id' => $_POST['campana_id'] ?? '',
        'metodo_captura' => $_POST['metodo_captura'] ?? 'manual',
        'validado' => isset($_POST['validado']) ? 1 : 0,
        'observaciones' => $_POST['observaciones'] ?? ''
    ];
    
    // Procesar archivos si se subieron nuevos
    if (isset($_FILES['foto_ine_frontal']) && $_FILES['foto_ine_frontal']['size'] > 0) {
        $result = $controller->procesarArchivo($_FILES['foto_ine_frontal'], 'ine_frontal');
        if (isset($result['success'])) {
            $datos['foto_ine_frontal'] = $result['archivo'];
        }
    }
    
    if (isset($_FILES['foto_ine_reverso']) && $_FILES['foto_ine_reverso']['size'] > 0) {
        $result = $controller->procesarArchivo($_FILES['foto_ine_reverso'], 'ine_reverso');
        if (isset($result['success'])) {
            $datos['foto_ine_reverso'] = $result['archivo'];
        }
    }
    
    if (isset($_FILES['foto_comprobante']) && $_FILES['foto_comprobante']['size'] > 0) {
        $result = $controller->procesarArchivo($_FILES['foto_comprobante'], 'comprobantes');
        if (isset($result['success'])) {
            $datos['foto_comprobante'] = $result['archivo'];
        }
    }
    
    $result = $controller->actualizar($id, $datos);
    
    if (isset($result['success'])) {
        $_SESSION['success'] = 'Simpatizante actualizado correctamente';
        header('Location: ' . BASE_URL . '/public/simpatizantes/ver.php?id=' . $id);
        exit;
    } else {
        $error = $result['error'] ?? 'Error al actualizar simpatizante';
        $errores = $result['errores'] ?? [];
    }
}

$campanas = $campanaModel->obtenerTodas(1);

$pageTitle = 'Editar Simpatizante';
include __DIR__ . '/../../app/views/layouts/header.php';
?>

<div class="container-fluid">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-md-6">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/public/simpatizantes/index.php">Simpatizantes</a></li>
                    <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/public/simpatizantes/ver.php?id=<?php echo $id; ?>">Detalles</a></li>
                    <li class="breadcrumb-item active">Editar</li>
                </ol>
            </nav>
            <h2><i class="bi bi-pencil-fill me-2"></i>Editar Simpatizante</h2>
            <p class="text-muted">Actualizar información del simpatizante</p>
        </div>
        <div class="col-md-6 text-end">
            <a href="<?php echo BASE_URL; ?>/public/simpatizantes/ver.php?id=<?php echo $id; ?>" class="btn btn-secondary">
                <i class="bi bi-arrow-left me-2"></i>Cancelar
            </a>
        </div>
    </div>
    
    <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>
    
    <!-- Formulario -->
    <form method="POST" action="" enctype="multipart/form-data">
        <div class="row">
            <!-- Información Personal -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="bi bi-person-fill me-2"></i>Información Personal</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Nombre Completo <span class="text-danger">*</span></label>
                            <input type="text" class="form-control <?php echo isset($errores['nombre_completo']) ? 'is-invalid' : ''; ?>" 
                                   name="nombre_completo" required
                                   value="<?php echo htmlspecialchars($simpatizante['nombre_completo'] ?? ''); ?>">
                            <?php if (isset($errores['nombre_completo'])): ?>
                                <div class="invalid-feedback"><?php echo $errores['nombre_completo']; ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Domicilio Completo <span class="text-danger">*</span></label>
                            <textarea class="form-control <?php echo isset($errores['domicilio_completo']) ? 'is-invalid' : ''; ?>" 
                                      name="domicilio_completo" rows="2" required><?php echo htmlspecialchars($simpatizante['domicilio_completo'] ?? ''); ?></textarea>
                            <?php if (isset($errores['domicilio_completo'])): ?>
                                <div class="invalid-feedback"><?php echo $errores['domicilio_completo']; ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Sexo</label>
                                <select class="form-select" name="sexo">
                                    <option value="">Seleccionar</option>
                                    <option value="M" <?php echo (isset($simpatizante['sexo']) && $simpatizante['sexo'] === 'M') ? 'selected' : ''; ?>>Masculino</option>
                                    <option value="F" <?php echo (isset($simpatizante['sexo']) && $simpatizante['sexo'] === 'F') ? 'selected' : ''; ?>>Femenino</option>
                                    <option value="O" <?php echo (isset($simpatizante['sexo']) && $simpatizante['sexo'] === 'O') ? 'selected' : ''; ?>>Otro</option>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Fecha de Nacimiento</label>
                                <input type="date" class="form-control" name="fecha_nacimiento"
                                       value="<?php echo htmlspecialchars($simpatizante['fecha_nacimiento'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Ciudad</label>
                            <input type="text" class="form-control" name="ciudad"
                                   value="<?php echo htmlspecialchars($simpatizante['ciudad'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
                
                <!-- Información Electoral -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="bi bi-card-checklist me-2"></i>Información Electoral</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Clave de Elector</label>
                            <input type="text" class="form-control <?php echo isset($errores['clave_elector']) ? 'is-invalid' : ''; ?>" 
                                   name="clave_elector" maxlength="18"
                                   value="<?php echo htmlspecialchars($simpatizante['clave_elector'] ?? ''); ?>">
                            <?php if (isset($errores['clave_elector'])): ?>
                                <div class="invalid-feedback"><?php echo $errores['clave_elector']; ?></div>
                            <?php endif; ?>
                            <small class="text-muted">Formato: 18 caracteres</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">CURP</label>
                            <input type="text" class="form-control <?php echo isset($errores['curp']) ? 'is-invalid' : ''; ?>" 
                                   name="curp" maxlength="18"
                                   value="<?php echo htmlspecialchars($simpatizante['curp'] ?? ''); ?>">
                            <?php if (isset($errores['curp'])): ?>
                                <div class="invalid-feedback"><?php echo $errores['curp']; ?></div>
                            <?php endif; ?>
                            <small class="text-muted">Formato: 18 caracteres</small>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Sección Electoral <span class="text-danger">*</span></label>
                                <input type="text" class="form-control <?php echo isset($errores['seccion_electoral']) ? 'is-invalid' : ''; ?>" 
                                       name="seccion_electoral" required
                                       value="<?php echo htmlspecialchars($simpatizante['seccion_electoral'] ?? ''); ?>">
                                <?php if (isset($errores['seccion_electoral'])): ?>
                                    <div class="invalid-feedback"><?php echo $errores['seccion_electoral']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Año de Registro</label>
                                <input type="number" class="form-control" name="ano_registro" min="1900" max="2099"
                                       value="<?php echo htmlspecialchars($simpatizante['ano_registro'] ?? ''); ?>">
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Vigencia</label>
                                <input type="number" class="form-control" name="vigencia" min="2020" max="2099"
                                       value="<?php echo htmlspecialchars($simpatizante['vigencia'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Contacto y Redes Sociales -->
            <div class="col-lg-6">
                <!-- Contacto -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="bi bi-telephone-fill me-2"></i>Información de Contacto</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Teléfono</label>
                            <input type="tel" class="form-control" name="telefono"
                                   value="<?php echo htmlspecialchars($simpatizante['telefono'] ?? ''); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">WhatsApp</label>
                            <input type="tel" class="form-control" name="whatsapp"
                                   value="<?php echo htmlspecialchars($simpatizante['whatsapp'] ?? ''); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control <?php echo isset($errores['email']) ? 'is-invalid' : ''; ?>" 
                                   name="email"
                                   value="<?php echo htmlspecialchars($simpatizante['email'] ?? ''); ?>">
                            <?php if (isset($errores['email'])): ?>
                                <div class="invalid-feedback"><?php echo $errores['email']; ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Redes Sociales -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="bi bi-share-fill me-2"></i>Redes Sociales</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label"><i class="bi bi-twitter text-info"></i> Twitter/X</label>
                            <input type="text" class="form-control" name="twitter"
                                   value="<?php echo htmlspecialchars($simpatizante['twitter'] ?? ''); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label"><i class="bi bi-instagram text-danger"></i> Instagram</label>
                            <input type="text" class="form-control" name="instagram"
                                   value="<?php echo htmlspecialchars($simpatizante['instagram'] ?? ''); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label"><i class="bi bi-facebook text-primary"></i> Facebook</label>
                            <input type="text" class="form-control" name="facebook"
                                   value="<?php echo htmlspecialchars($simpatizante['facebook'] ?? ''); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label"><i class="bi bi-youtube text-danger"></i> YouTube</label>
                            <input type="text" class="form-control" name="youtube"
                                   value="<?php echo htmlspecialchars($simpatizante['youtube'] ?? ''); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label"><i class="bi bi-tiktok"></i> TikTok</label>
                            <input type="text" class="form-control" name="tiktok"
                                   value="<?php echo htmlspecialchars($simpatizante['tiktok'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
                
                <!-- Campaña y Estado -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="bi bi-megaphone-fill me-2"></i>Campaña y Estado</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Campaña</label>
                            <select class="form-select" name="campana_id">
                                <option value="">Seleccionar campaña</option>
                                <?php foreach ($campanas as $campana): ?>
                                    <option value="<?php echo $campana['id']; ?>" 
                                            <?php echo (isset($simpatizante['campana_id']) && $simpatizante['campana_id'] == $campana['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($campana['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Método de Captura</label>
                            <select class="form-select" name="metodo_captura">
                                <option value="manual" <?php echo (isset($simpatizante['metodo_captura']) && $simpatizante['metodo_captura'] === 'manual') ? 'selected' : ''; ?>>Manual</option>
                                <option value="app" <?php echo (isset($simpatizante['metodo_captura']) && $simpatizante['metodo_captura'] === 'app') ? 'selected' : ''; ?>>App Móvil</option>
                                <option value="web" <?php echo (isset($simpatizante['metodo_captura']) && $simpatizante['metodo_captura'] === 'web') ? 'selected' : ''; ?>>Web</option>
                            </select>
                        </div>
                        
                        <?php if (in_array($auth->obtenerRol(), ['super_admin', 'admin', 'coordinador'])): ?>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="validado" id="validado" 
                                       <?php echo (!empty($simpatizante['validado'])) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="validado">
                                    Validado
                                </label>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label class="form-label">Observaciones</label>
                            <textarea class="form-control" name="observaciones" rows="3"><?php echo htmlspecialchars($simpatizante['observaciones'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>
                
                <!-- Ubicación y Archivos -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="bi bi-geo-alt-fill me-2"></i>Ubicación</h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Latitud</label>
                                <input type="text" class="form-control" name="latitud"
                                       value="<?php echo htmlspecialchars($simpatizante['latitud'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Longitud</label>
                                <input type="text" class="form-control" name="longitud"
                                       value="<?php echo htmlspecialchars($simpatizante['longitud'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Documentos -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="bi bi-file-earmark-image me-2"></i>Documentos</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">INE Frontal</label>
                            <?php if (!empty($simpatizante['foto_ine_frontal'])): ?>
                                <div class="mb-2">
                                    <small class="text-muted">Archivo actual: </small>
                                    <a href="<?php echo BASE_URL; ?>/public/<?php echo htmlspecialchars($simpatizante['foto_ine_frontal']); ?>" target="_blank">Ver archivo</a>
                                </div>
                            <?php endif; ?>
                            <input type="file" class="form-control" name="foto_ine_frontal" accept="image/*">
                            <small class="text-muted">Dejar vacío para mantener el archivo actual</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">INE Reverso</label>
                            <?php if (!empty($simpatizante['foto_ine_reverso'])): ?>
                                <div class="mb-2">
                                    <small class="text-muted">Archivo actual: </small>
                                    <a href="<?php echo BASE_URL; ?>/public/<?php echo htmlspecialchars($simpatizante['foto_ine_reverso']); ?>" target="_blank">Ver archivo</a>
                                </div>
                            <?php endif; ?>
                            <input type="file" class="form-control" name="foto_ine_reverso" accept="image/*">
                            <small class="text-muted">Dejar vacío para mantener el archivo actual</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Comprobante de Domicilio</label>
                            <?php if (!empty($simpatizante['foto_comprobante'])): ?>
                                <div class="mb-2">
                                    <small class="text-muted">Archivo actual: </small>
                                    <a href="<?php echo BASE_URL; ?>/public/<?php echo htmlspecialchars($simpatizante['foto_comprobante']); ?>" target="_blank">Ver archivo</a>
                                </div>
                            <?php endif; ?>
                            <input type="file" class="form-control" name="foto_comprobante" accept="image/*">
                            <small class="text-muted">Dejar vacío para mantener el archivo actual</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Botones -->
        <div class="row">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <button type="submit" class="btn btn-gradient btn-lg">
                            <i class="bi bi-save me-2"></i>Guardar Cambios
                        </button>
                        <a href="<?php echo BASE_URL; ?>/public/simpatizantes/ver.php?id=<?php echo $id; ?>" class="btn btn-secondary btn-lg">
                            <i class="bi bi-x-circle me-2"></i>Cancelar
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<?php include __DIR__ . '/../../app/views/layouts/footer.php'; ?>
