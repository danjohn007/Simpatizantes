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
        'observaciones' => $_POST['observaciones'] ?? ''
    ];
    
    // Procesar archivos si se subieron nuevos
    $archivoError = '';
    foreach (['ine_frontal' => 'ine_frontal', 'ine_posterior' => 'ine_posterior'] as $campo => $carpeta) {
        if (isset($_FILES[$campo]) && $_FILES[$campo]['size'] > 0) {
            $resArchivo = $controller->procesarArchivo($_FILES[$campo], $carpeta);
            if (isset($resArchivo['success'])) {
                $datos[$campo] = $resArchivo['archivo'];
            } else {
                $archivoError = $resArchivo['error'] ?? 'No se pudo subir el archivo';
            }
        }
    }
    
    if ($archivoError !== '') {
        $error = $archivoError;
        $errores = [];
    } else {
    
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
}

// Si hubo error, mostrar lo que el usuario escribió
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($datos)) {
    $simpatizante = array_merge($simpatizante, $datos);
}

$campanas = $campanaModel->obtenerTodas(1);

$pageTitle = 'Editar Simpatizante';
include __DIR__ . '/../../app/views/layouts/header.php';
?>

<div class="container-fluid p-0">
    <div class="page-head">
        <div>
            <div class="eyebrow">Simpatizantes</div>
            <h1>Editar simpatizante</h1>
            <div class="meta"><span><i class="bi bi-person"></i><?php echo htmlspecialchars($simpatizante['nombre_completo'] ?? ''); ?></span></div>
        </div>
        <a href="<?php echo BASE_URL; ?>/public/simpatizantes/ver.php?id=<?php echo $id; ?>" class="btn btn-soft">
            <i class="bi bi-arrow-left me-2"></i>Cancelar
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo htmlspecialchars($error); ?>
            <?php if (!empty($errores)): ?>
                <ul class="mb-0 mt-2"><?php foreach ($errores as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?></ul>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    
    <!-- Formulario -->
    <form method="POST" action="" enctype="multipart/form-data" id="formSimpatizante"
          data-id="<?php echo (int)$id; ?>"
          data-api="<?php echo BASE_URL; ?>/public/api/verificar-calidad.php"
          data-ver="<?php echo BASE_URL; ?>/public/simpatizantes/ver.php?id=">
<?php echo csrf_field(); ?>
        <div id="calidadPanel" class="mb-3" aria-live="polite"></div>

        <div class="row">
            <!-- Información Personal -->
            <div class="col-lg-6">
                <div class="card mb-3">
                    <div class="card-header">
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
                <div class="card mb-3">
                    <div class="card-header">
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
                                       name="seccion_electoral" pattern="[0-9]{4}" maxlength="4" required
                                       value="<?php echo htmlspecialchars($simpatizante['seccion_electoral'] ?? ''); ?>">
                                <?php if (isset($errores['seccion_electoral'])): ?>
                                    <div class="invalid-feedback"><?php echo $errores['seccion_electoral']; ?></div>
                                <?php endif; ?>
                                <small class="text-muted">4 dígitos</small>
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
                <div class="card mb-3">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-telephone-fill me-2"></i>Información de Contacto</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Teléfono</label>
                            <input type="tel" class="form-control" name="telefono" pattern="[0-9]{10}" maxlength="16" inputmode="numeric"
                                   value="<?php echo htmlspecialchars($simpatizante['telefono'] ?? ''); ?>">
                            <small class="text-muted">10 dígitos sin espacios ni guiones</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">WhatsApp</label>
                            <input type="tel" class="form-control <?php echo isset($errores['whatsapp']) ? 'is-invalid' : ''; ?>" 
                                   name="whatsapp" pattern="[0-9]{10}" maxlength="16" inputmode="numeric"
                                   value="<?php echo htmlspecialchars($simpatizante['whatsapp'] ?? ''); ?>">
                            <?php if (isset($errores['whatsapp'])): ?>
                                <div class="invalid-feedback"><?php echo $errores['whatsapp']; ?></div>
                            <?php endif; ?>
                            <small class="text-muted">10 dígitos sin espacios ni guiones</small>
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
                <div class="card mb-3">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-share-fill me-2"></i>Redes Sociales</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label"><i class="bi bi-twitter text-muted"></i> Twitter/X</label>
                            <input type="text" class="form-control" name="twitter"
                                   value="<?php echo htmlspecialchars($simpatizante['twitter'] ?? ''); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label"><i class="bi bi-instagram text-muted"></i> Instagram</label>
                            <input type="text" class="form-control" name="instagram"
                                   value="<?php echo htmlspecialchars($simpatizante['instagram'] ?? ''); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label"><i class="bi bi-facebook text-muted"></i> Facebook</label>
                            <input type="text" class="form-control" name="facebook"
                                   value="<?php echo htmlspecialchars($simpatizante['facebook'] ?? ''); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label"><i class="bi bi-youtube text-muted"></i> YouTube</label>
                            <input type="text" class="form-control" name="youtube"
                                   value="<?php echo htmlspecialchars($simpatizante['youtube'] ?? ''); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label"><i class="bi bi-tiktok text-muted"></i> TikTok</label>
                            <input type="text" class="form-control" name="tiktok"
                                   value="<?php echo htmlspecialchars($simpatizante['tiktok'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
                
                <!-- Campaña y Estado -->
                <div class="card mb-3">
                    <div class="card-header">
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
                        
                        <?php
                        $estatusActual = $simpatizante['estatus_validacion'] ?? (!empty($simpatizante['validado']) ? 'validado' : 'pendiente');
                        $etiquetasEstatus = ['pendiente' => 'Pendiente', 'validado' => 'Validado', 'rechazado' => 'Rechazado'];
                        ?>
                        <div class="mb-3">
                            <label class="form-label d-block">Estado de validación</label>
                            <span class="status-pill <?php echo $estatusActual === 'validado' ? 'on' : 'off'; ?>"><?php echo $etiquetasEstatus[$estatusActual] ?? 'Pendiente'; ?></span>
                            <small class="text-muted ms-2">Se cambia desde el <a href="ver.php?id=<?php echo (int)$id; ?>">detalle del simpatizante</a>, con motivo e historial.</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Observaciones</label>
                            <textarea class="form-control" name="observaciones" rows="3"><?php echo htmlspecialchars($simpatizante['observaciones'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>
                
                <!-- Ubicación y Archivos -->
                <div class="card mb-3">
                    <div class="card-header">
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
                <div class="card mb-3">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-file-earmark-image me-2"></i>Documentos</h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ([['ine_frontal', 'INE frontal'], ['ine_posterior', 'INE reverso']] as $doc): ?>
                        <div class="mb-3">
                            <label class="form-label"><?php echo $doc[1]; ?></label>
                            <?php if (!empty($simpatizante[$doc[0]])): ?>
                                <div class="mb-2 small">
                                    <span class="text-muted">Archivo actual:</span>
                                    <a href="<?php echo BASE_URL; ?>/public/archivo.php?f=<?php echo urlencode($simpatizante[$doc[0]]); ?>" target="_blank" rel="noopener">Ver archivo</a>
                                </div>
                            <?php endif; ?>
                            <input type="file" class="form-control" name="<?php echo $doc[0]; ?>" accept="image/jpeg,image/png">
                            <div class="form-text">JPG o PNG, máximo 5 MB. Déjelo vacío para conservar el actual.</div>
                        </div>
                        <?php endforeach; ?>
                        <?php if (!empty($simpatizante['firma_digital'])): ?>
                            <div class="small">
                                <span class="text-muted">Firma digital:</span>
                                <a href="<?php echo BASE_URL; ?>/public/archivo.php?f=<?php echo urlencode($simpatizante['firma_digital']); ?>" target="_blank" rel="noopener">Ver firma</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Acciones -->
        <div class="action-bar">
            <a href="<?php echo BASE_URL; ?>/public/simpatizantes/ver.php?id=<?php echo $id; ?>" class="btn btn-soft">Cancelar</a>
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-2"></i>Guardar cambios</button>
        </div>
    </form>
</div>

<script src="<?php echo BASE_URL; ?>/public/js/calidad-datos.js?v=<?php echo @filemtime(__DIR__ . '/../js/calidad-datos.js'); ?>"></script>
<?php include __DIR__ . '/../../app/views/layouts/footer.php'; ?>
