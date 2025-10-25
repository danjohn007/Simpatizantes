<?php
/**
 * Crear Simpatizante
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
        'validado' => isset($_POST['validado']) ? 1 : 0
    ];
    
    // Procesar archivos
    if (isset($_FILES['ine_frontal']) && $_FILES['ine_frontal']['size'] > 0) {
        $result = $controller->procesarArchivo($_FILES['ine_frontal'], 'ine_frontal');
        if (isset($result['success'])) {
            $datos['ine_frontal'] = $result['archivo'];
        }
    }
    
    if (isset($_FILES['ine_posterior']) && $_FILES['ine_posterior']['size'] > 0) {
        $result = $controller->procesarArchivo($_FILES['ine_posterior'], 'ine_posterior');
        if (isset($result['success'])) {
            $datos['ine_posterior'] = $result['archivo'];
        }
    }
    
    // Procesar firma digital desde canvas (base64)
    if (!empty($_POST['firma_digital'])) {
        $result = $controller->procesarFirmaBase64($_POST['firma_digital']);
        if (isset($result['success'])) {
            $datos['firma_digital'] = $result['archivo'];
        }
    }
    
    $result = $controller->crear($datos);
    
    if (isset($result['success'])) {
        header('Location: index.php?success=1');
        exit;
    } else {
        $error = $result['error'] ?? 'Error al crear simpatizante';
        $errores = $result['errores'] ?? [];
    }
}

$campanas = $campanaModel->obtenerTodas(1);

$pageTitle = 'Nuevo Simpatizante';
include __DIR__ . '/../../app/views/layouts/header.php';
?>

<div class="container-fluid">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-md-6">
            <h2><i class="bi bi-person-plus-fill me-2"></i>Nuevo Simpatizante</h2>
            <p class="text-muted">Registro de nuevo simpatizante</p>
        </div>
        <div class="col-md-6 text-end">
            <a href="index.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left me-2"></i>Volver
            </a>
        </div>
    </div>
    
    <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>
    
    <!-- OCR INE -->
    <div class="card border-0 shadow-sm mb-4 bg-light">
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h5 class="mb-2"><i class="bi bi-camera-fill me-2"></i>Lectura OCR de INE</h5>
                    <p class="text-muted mb-0 small">
                        Sube una foto clara del INE frontal para extraer automáticamente los datos
                    </p>
                </div>
                <div class="col-md-4">
                    <input type="file" class="form-control" id="ine_ocr" accept="image/*" style="display: none;">
                    <button type="button" class="btn btn-info w-100" onclick="document.getElementById('ine_ocr').click()">
                        <i class="bi bi-upload me-2"></i>Subir INE para OCR
                    </button>
                </div>
            </div>
            <div id="ocr-loading" class="mt-3 text-center" style="display: none;">
                <div class="spinner-border text-info" role="status">
                    <span class="visually-hidden">Procesando...</span>
                </div>
                <p class="mt-2 text-muted">Procesando imagen con OCR...</p>
            </div>
            <div id="ocr-result" class="mt-3" style="display: none;"></div>
        </div>
    </div>
    
    <!-- Formulario -->
    <form method="POST" action="" enctype="multipart/form-data" id="formSimpatizante">
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
                                   value="<?php echo htmlspecialchars($_POST['nombre_completo'] ?? ''); ?>">
                            <?php if (isset($errores['nombre_completo'])): ?>
                                <div class="invalid-feedback"><?php echo $errores['nombre_completo']; ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Domicilio Completo <span class="text-danger">*</span></label>
                            <textarea class="form-control <?php echo isset($errores['domicilio_completo']) ? 'is-invalid' : ''; ?>" 
                                      name="domicilio_completo" rows="2" required><?php echo htmlspecialchars($_POST['domicilio_completo'] ?? ''); ?></textarea>
                            <?php if (isset($errores['domicilio_completo'])): ?>
                                <div class="invalid-feedback"><?php echo $errores['domicilio_completo']; ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Sexo</label>
                                <select class="form-select" name="sexo">
                                    <option value="">Seleccionar</option>
                                    <option value="M" <?php echo (isset($_POST['sexo']) && $_POST['sexo'] === 'M') ? 'selected' : ''; ?>>Masculino</option>
                                    <option value="F" <?php echo (isset($_POST['sexo']) && $_POST['sexo'] === 'F') ? 'selected' : ''; ?>>Femenino</option>
                                    <option value="O" <?php echo (isset($_POST['sexo']) && $_POST['sexo'] === 'O') ? 'selected' : ''; ?>>Otro</option>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Fecha de Nacimiento</label>
                                <input type="date" class="form-control" name="fecha_nacimiento"
                                       value="<?php echo htmlspecialchars($_POST['fecha_nacimiento'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Ciudad</label>
                            <input type="text" class="form-control" name="ciudad"
                                   value="<?php echo htmlspecialchars($_POST['ciudad'] ?? ''); ?>">
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
                                   value="<?php echo htmlspecialchars($_POST['clave_elector'] ?? ''); ?>">
                            <?php if (isset($errores['clave_elector'])): ?>
                                <div class="invalid-feedback"><?php echo $errores['clave_elector']; ?></div>
                            <?php endif; ?>
                            <small class="text-muted">Formato: 18 caracteres</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">CURP</label>
                            <input type="text" class="form-control <?php echo isset($errores['curp']) ? 'is-invalid' : ''; ?>" 
                                   name="curp" maxlength="18"
                                   value="<?php echo htmlspecialchars($_POST['curp'] ?? ''); ?>">
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
                                       value="<?php echo htmlspecialchars($_POST['seccion_electoral'] ?? ''); ?>">
                                <?php if (isset($errores['seccion_electoral'])): ?>
                                    <div class="invalid-feedback"><?php echo $errores['seccion_electoral']; ?></div>
                                <?php endif; ?>
                                <small class="text-muted">4 dígitos</small>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Año de Registro</label>
                                <input type="number" class="form-control" name="ano_registro" min="1900" max="2099"
                                       value="<?php echo htmlspecialchars($_POST['ano_registro'] ?? ''); ?>">
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Vigencia</label>
                                <input type="number" class="form-control" name="vigencia" min="2020" max="2099"
                                       value="<?php echo htmlspecialchars($_POST['vigencia'] ?? ''); ?>">
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
                            <label class="form-label">WhatsApp</label>
                            <input type="tel" class="form-control <?php echo isset($errores['whatsapp']) ? 'is-invalid' : ''; ?>" 
                                   name="whatsapp" pattern="[0-9]{10}" maxlength="10"
                                   value="<?php echo htmlspecialchars($_POST['whatsapp'] ?? ''); ?>">
                            <?php if (isset($errores['whatsapp'])): ?>
                                <div class="invalid-feedback"><?php echo $errores['whatsapp']; ?></div>
                            <?php endif; ?>
                            <small class="text-muted">10 dígitos sin espacios ni guiones</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control <?php echo isset($errores['email']) ? 'is-invalid' : ''; ?>" 
                                   name="email"
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
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
                                   value="<?php echo htmlspecialchars($_POST['twitter'] ?? ''); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label"><i class="bi bi-instagram text-danger"></i> Instagram</label>
                            <input type="text" class="form-control" name="instagram"
                                   value="<?php echo htmlspecialchars($_POST['instagram'] ?? ''); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label"><i class="bi bi-facebook text-primary"></i> Facebook</label>
                            <input type="text" class="form-control" name="facebook"
                                   value="<?php echo htmlspecialchars($_POST['facebook'] ?? ''); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label"><i class="bi bi-youtube text-danger"></i> YouTube</label>
                            <input type="text" class="form-control" name="youtube"
                                   value="<?php echo htmlspecialchars($_POST['youtube'] ?? ''); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label"><i class="bi bi-tiktok"></i> TikTok</label>
                            <input type="text" class="form-control" name="tiktok"
                                   value="<?php echo htmlspecialchars($_POST['tiktok'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
                
                <!-- Ubicación y Archivos -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="bi bi-geo-alt-fill me-2"></i>Ubicación y Documentos</h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Latitud <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="latitud" id="latitud" required
                                       value="<?php echo htmlspecialchars($_POST['latitud'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Longitud <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="longitud" id="longitud" required
                                       value="<?php echo htmlspecialchars($_POST['longitud'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <button type="button" class="btn btn-sm btn-info mb-3" onclick="obtenerUbicacion()">
                            <i class="bi bi-geo-fill me-1"></i>Detectar Ubicación Actual (Obligatorio)
                        </button>
                        <div id="ubicacion-alert" class="alert alert-warning d-none">
                            <i class="bi bi-exclamation-triangle me-2"></i>Debe detectar la ubicación antes de continuar
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">INE Frontal</label>
                            <input type="file" class="form-control" name="ine_frontal" accept="image/*">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">INE Posterior</label>
                            <input type="file" class="form-control" name="ine_posterior" accept="image/*">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Firma Digital</label>
                            <div class="border rounded p-2 bg-white">
                                <canvas id="signatureCanvas" width="400" height="200" class="border" style="cursor: crosshair; touch-action: none;"></canvas>
                            </div>
                            <input type="hidden" name="firma_digital" id="firma_digital_data">
                            <div class="mt-2">
                                <button type="button" class="btn btn-sm btn-secondary" onclick="clearSignature()">
                                    <i class="bi bi-eraser-fill me-1"></i>Limpiar Firma
                                </button>
                                <small class="text-muted ms-2">Firme con el mouse o dedo</small>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Campaña <span class="text-danger">*</span></label>
                            <select class="form-select" name="campana_id" required>
                                <option value="">Seleccionar</option>
                                <?php foreach ($campanas as $campana): ?>
                                    <option value="<?php echo $campana['id']; ?>">
                                        <?php echo htmlspecialchars($campana['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="validado" id="validado">
                            <label class="form-check-label" for="validado">
                                Marcar como validado
                            </label>
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
                        <button type="submit" class="btn btn-gradient btn-lg px-5">
                            <i class="bi bi-save-fill me-2"></i>Guardar Simpatizante
                        </button>
                        <a href="index.php" class="btn btn-secondary btn-lg px-5 ms-2">
                            <i class="bi bi-x-circle me-2"></i>Cancelar
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
function obtenerUbicacion() {
    const alertDiv = document.getElementById('ubicacion-alert');
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(function(position) {
            document.getElementById('latitud').value = position.coords.latitude.toFixed(8);
            document.getElementById('longitud').value = position.coords.longitude.toFixed(8);
            alertDiv.classList.add('d-none');
            mostrarMensaje('Ubicación detectada correctamente', 'success');
        }, function() {
            alertDiv.classList.remove('d-none');
            mostrarMensaje('No se pudo obtener la ubicación. Por favor, permita el acceso a su ubicación.', 'danger');
        });
    } else {
        alertDiv.classList.remove('d-none');
        mostrarMensaje('Geolocalización no soportada por este navegador', 'danger');
    }
}

// Validar ubicación antes de enviar el formulario
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    form.addEventListener('submit', function(e) {
        const latitud = document.getElementById('latitud').value;
        const longitud = document.getElementById('longitud').value;
        if (!latitud || !longitud) {
            e.preventDefault();
            document.getElementById('ubicacion-alert').classList.remove('d-none');
            alert('Debe detectar la ubicación antes de guardar el registro');
            return false;
        }
        
        // Guardar firma digital como base64
        if (signaturePad && !signaturePad.isEmpty()) {
            document.getElementById('firma_digital_data').value = canvas.toDataURL('image/png');
        }
    });
});

// Canvas de Firma Digital
const canvas = document.getElementById('signatureCanvas');
const ctx = canvas.getContext('2d');
let isDrawing = false;
let lastX = 0;
let lastY = 0;

// Configurar canvas
ctx.strokeStyle = '#000';
ctx.lineWidth = 2;
ctx.lineCap = 'round';
ctx.lineJoin = 'round';

// Funciones de dibujo para mouse
canvas.addEventListener('mousedown', (e) => {
    isDrawing = true;
    const rect = canvas.getBoundingClientRect();
    lastX = e.clientX - rect.left;
    lastY = e.clientY - rect.top;
});

canvas.addEventListener('mousemove', (e) => {
    if (!isDrawing) return;
    const rect = canvas.getBoundingClientRect();
    const x = e.clientX - rect.left;
    const y = e.clientY - rect.top;
    
    ctx.beginPath();
    ctx.moveTo(lastX, lastY);
    ctx.lineTo(x, y);
    ctx.stroke();
    
    lastX = x;
    lastY = y;
});

canvas.addEventListener('mouseup', () => {
    isDrawing = false;
});

canvas.addEventListener('mouseout', () => {
    isDrawing = false;
});

// Funciones de dibujo para touch (móvil)
canvas.addEventListener('touchstart', (e) => {
    e.preventDefault();
    isDrawing = true;
    const rect = canvas.getBoundingClientRect();
    const touch = e.touches[0];
    lastX = touch.clientX - rect.left;
    lastY = touch.clientY - rect.top;
});

canvas.addEventListener('touchmove', (e) => {
    e.preventDefault();
    if (!isDrawing) return;
    const rect = canvas.getBoundingClientRect();
    const touch = e.touches[0];
    const x = touch.clientX - rect.left;
    const y = touch.clientY - rect.top;
    
    ctx.beginPath();
    ctx.moveTo(lastX, lastY);
    ctx.lineTo(x, y);
    ctx.stroke();
    
    lastX = x;
    lastY = y;
});

canvas.addEventListener('touchend', () => {
    isDrawing = false;
});

// Función para limpiar firma
function clearSignature() {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    document.getElementById('firma_digital_data').value = '';
}

// Objeto signaturePad simple para compatibilidad
const signaturePad = {
    isEmpty: function() {
        const pixelBuffer = new Uint32Array(
            ctx.getImageData(0, 0, canvas.width, canvas.height).data.buffer
        );
        return !pixelBuffer.some(color => color !== 0);
    }
};

// OCR de INE
document.getElementById('ine_ocr').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (!file) return;
    
    // Mostrar loading
    document.getElementById('ocr-loading').style.display = 'block';
    document.getElementById('ocr-result').style.display = 'none';
    
    // Crear FormData
    const formData = new FormData();
    formData.append('ine_imagen', file);
    
    // Enviar a API
    fetch('<?php echo BASE_URL; ?>/public/api/procesar-ocr.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        document.getElementById('ocr-loading').style.display = 'none';
        
        if (data.error) {
            document.getElementById('ocr-result').innerHTML = 
                '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i>' + 
                data.error + '</div>';
            document.getElementById('ocr-result').style.display = 'block';
            return;
        }
        
        // Llenar formulario con datos extraídos
        if (data.datos) {
            if (data.datos.nombre_completo) {
                document.querySelector('[name="nombre_completo"]').value = data.datos.nombre_completo;
            }
            if (data.datos.curp) {
                document.querySelector('[name="curp"]').value = data.datos.curp;
            }
            if (data.datos.clave_elector) {
                document.querySelector('[name="clave_elector"]').value = data.datos.clave_elector;
            }
            if (data.datos.domicilio_completo) {
                document.querySelector('[name="domicilio_completo"]').value = data.datos.domicilio_completo;
            }
            if (data.datos.seccion_electoral) {
                document.querySelector('[name="seccion_electoral"]').value = data.datos.seccion_electoral;
            }
            if (data.datos.vigencia) {
                document.querySelector('[name="vigencia"]').value = data.datos.vigencia;
            }
            if (data.datos.sexo) {
                document.querySelector('[name="sexo"]').value = data.datos.sexo;
            }
            
            document.getElementById('ocr-result').innerHTML = 
                '<div class="alert alert-success"><i class="bi bi-check-circle-fill me-2"></i>' +
                'Datos extraídos correctamente. Revise y complete la información faltante.</div>';
            document.getElementById('ocr-result').style.display = 'block';
        }
    })
    .catch(error => {
        document.getElementById('ocr-loading').style.display = 'none';
        document.getElementById('ocr-result').innerHTML = 
            '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i>' +
            'Error al procesar la imagen: ' + error.message + '</div>';
        document.getElementById('ocr-result').style.display = 'block';
    });
});
</script>

<?php include __DIR__ . '/../../app/views/layouts/footer.php'; ?>
