<?php
/**
 * Registro Público de Simpatizantes
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../app/controllers/SimpatizanteController.php';
require_once __DIR__ . '/../app/models/Campana.php';

// Verificar si el registro público está habilitado
$db = Database::getInstance();
$config = $db->queryOne("SELECT valor FROM configuracion WHERE clave = 'registro_publico_habilitado'");
$registroHabilitado = $config && $config['valor'] === 'true';

if (!$registroHabilitado) {
    header('Location: ' . BASE_URL);
    exit;
}

// Cargar colores personalizados desde configuración
$sql_colors = "SELECT clave, valor FROM configuracion WHERE tipo = 'color'";
$colores = $db->query($sql_colors);
$color_primario = '#667eea';
$color_secundario = '#764ba2';
foreach ($colores as $color) {
    if ($color['clave'] === 'color_primario') $color_primario = $color['valor'];
    if ($color['clave'] === 'color_secundario') $color_secundario = $color['valor'];
}

$controller = new SimpatizanteController();
$campanaModel = new Campana();

// Obtener campañas activas
$campanas = $campanaModel->obtenerTodas(1);

$error = '';
$success = '';
$errores = [];

// Generar números para captcha (la sesión ya está iniciada en AuthController)
if (!isset($_SESSION['captcha_num1']) || !isset($_SESSION['captcha_num2'])) {
    $_SESSION['captcha_num1'] = rand(1, 10);
    $_SESSION['captcha_num2'] = rand(1, 10);
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar CAPTCHA
    $captchaRespuesta = $_POST['captcha'] ?? '';
    $captchaCorrecta = $_SESSION['captcha_num1'] + $_SESSION['captcha_num2'];
    
    if ($captchaRespuesta != $captchaCorrecta) {
        $error = 'Respuesta incorrecta en la verificación matemática';
        // Regenerar números
        $_SESSION['captcha_num1'] = rand(1, 10);
        $_SESSION['captcha_num2'] = rand(1, 10);
    } elseif (!isset($_POST['terminos']) || $_POST['terminos'] !== 'aceptado') {
        $error = 'Debe aceptar los términos y condiciones';
    } else {
        $datos = [
            'nombre_completo' => $_POST['nombre_completo'] ?? '',
            'domicilio_completo' => $_POST['domicilio_completo'] ?? '',
            'sexo' => $_POST['sexo'] ?? '',
            'ciudad' => $_POST['ciudad'] ?? '',
            'clave_elector' => $_POST['clave_elector'] ?? '',
            'curp' => $_POST['curp'] ?? '',
            'fecha_nacimiento' => $_POST['fecha_nacimiento'] ?? '',
            'seccion_electoral' => $_POST['seccion_electoral'] ?? '',
            'whatsapp' => $_POST['whatsapp'] ?? '',
            'email' => $_POST['email'] ?? '',
            'campana_id' => !empty($_POST['campana_id']) ? (int)$_POST['campana_id'] : null,
            'latitud' => $_POST['latitud'] ?? '',
            'longitud' => $_POST['longitud'] ?? '',
            'metodo_captura' => 'web',
            'validado' => 0
        ];
        
        // Validaciones adicionales
        if (empty($datos['nombre_completo'])) {
            $errores['nombre_completo'] = 'El nombre completo es obligatorio';
        }
        if (empty($datos['domicilio_completo'])) {
            $errores['domicilio_completo'] = 'El domicilio es obligatorio';
        }
        if (empty($datos['sexo'])) {
            $errores['sexo'] = 'El sexo es obligatorio';
        }
        if (empty($datos['campana_id'])) {
            $errores['campana_id'] = 'La campaña es obligatoria';
        }
        if (empty($datos['seccion_electoral'])) {
            $errores['seccion_electoral'] = 'La sección electoral es obligatoria';
        }
        if (empty($datos['whatsapp'])) {
            $errores['whatsapp'] = 'El WhatsApp es obligatorio';
        } elseif (!preg_match('/^[0-9]{10}$/', $datos['whatsapp'])) {
            $errores['whatsapp'] = 'El WhatsApp debe tener 10 dígitos';
        }
        if (empty($datos['latitud']) || empty($datos['longitud'])) {
            $errores['ubicacion'] = 'Debe detectar su ubicación';
        }
        
        if (empty($errores)) {
            $result = $controller->crear($datos, true); // true indica que es registro público
            
            if (isset($result['success'])) {
                $success = '¡Registro exitoso! Su información ha sido enviada para validación.';
                // Limpiar formulario
                $_POST = [];
            } else {
                $error = $result['error'] ?? 'Error al registrar';
                $errores = $result['errores'] ?? [];
            }
        }
        
        // Regenerar captcha
        $_SESSION['captcha_num1'] = rand(1, 10);
        $_SESSION['captcha_num2'] = rand(1, 10);
    }
}

// Obtener términos y condiciones
$terminosConfig = $db->queryOne("SELECT valor FROM configuracion WHERE clave = 'terminos_condiciones'");
$terminos = $terminosConfig ? $terminosConfig['valor'] : 'No se han configurado los términos y condiciones.';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro de Simpatizante - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, <?php echo $color_primario; ?> 0%, <?php echo $color_secundario; ?> 100%);
            min-height: 100vh;
            padding: 30px 0;
        }
        .registro-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            padding: 30px;
        }
        .header-gradient {
            background: linear-gradient(135deg, <?php echo $color_primario; ?> 0%, <?php echo $color_secundario; ?> 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        .btn-gradient {
            background: linear-gradient(135deg, <?php echo $color_primario; ?> 0%, <?php echo $color_secundario; ?> 100%);
            border: none;
            color: white;
        }
        .btn-gradient:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="registro-container">
                    <div class="header-gradient text-center">
                        <h2><i class="bi bi-person-plus-fill me-2"></i>Registro de Simpatizante</h2>
                        <p class="mb-0">Complete el formulario para registrarse</p>
                    </div>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <i class="bi bi-check-circle-fill me-2"></i><?php echo htmlspecialchars($success); ?>
                            <div class="mt-3">
                                <a href="<?php echo BASE_URL; ?>" class="btn btn-success">
                                    <i class="bi bi-house-fill me-2"></i>Volver al Inicio
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Nombre Completo <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control <?php echo isset($errores['nombre_completo']) ? 'is-invalid' : ''; ?>" 
                                               name="nombre_completo" required value="<?php echo htmlspecialchars($_POST['nombre_completo'] ?? ''); ?>">
                                        <?php if (isset($errores['nombre_completo'])): ?>
                                            <div class="invalid-feedback"><?php echo $errores['nombre_completo']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Sexo <span class="text-danger">*</span></label>
                                        <select class="form-select <?php echo isset($errores['sexo']) ? 'is-invalid' : ''; ?>" name="sexo" required>
                                            <option value="">Seleccionar</option>
                                            <option value="M" <?php echo (isset($_POST['sexo']) && $_POST['sexo'] === 'M') ? 'selected' : ''; ?>>Masculino</option>
                                            <option value="F" <?php echo (isset($_POST['sexo']) && $_POST['sexo'] === 'F') ? 'selected' : ''; ?>>Femenino</option>
                                            <option value="O" <?php echo (isset($_POST['sexo']) && $_POST['sexo'] === 'O') ? 'selected' : ''; ?>>Otro</option>
                                        </select>
                                        <?php if (isset($errores['sexo'])): ?>
                                            <div class="invalid-feedback"><?php echo $errores['sexo']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Campaña <span class="text-danger">*</span></label>
                                <select class="form-select <?php echo isset($errores['campana_id']) ? 'is-invalid' : ''; ?>" name="campana_id" required>
                                    <option value="">Seleccionar campaña</option>
                                    <?php foreach ($campanas as $campana): ?>
                                        <option value="<?php echo $campana['id']; ?>" 
                                                <?php echo (isset($_POST['campana_id']) && $_POST['campana_id'] == $campana['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($campana['nombre']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (isset($errores['campana_id'])): ?>
                                    <div class="invalid-feedback"><?php echo $errores['campana_id']; ?></div>
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
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Ciudad</label>
                                        <input type="text" class="form-control" name="ciudad" value="<?php echo htmlspecialchars($_POST['ciudad'] ?? ''); ?>">
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Fecha de Nacimiento</label>
                                        <input type="date" class="form-control" name="fecha_nacimiento" value="<?php echo htmlspecialchars($_POST['fecha_nacimiento'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Clave de Elector</label>
                                        <input type="text" class="form-control" name="clave_elector" maxlength="18" value="<?php echo htmlspecialchars($_POST['clave_elector'] ?? ''); ?>">
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">CURP</label>
                                        <input type="text" class="form-control" name="curp" maxlength="18" value="<?php echo htmlspecialchars($_POST['curp'] ?? ''); ?>">
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Sección Electoral <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control <?php echo isset($errores['seccion_electoral']) ? 'is-invalid' : ''; ?>" 
                                               name="seccion_electoral" pattern="[0-9]{4}" maxlength="4" required 
                                               value="<?php echo htmlspecialchars($_POST['seccion_electoral'] ?? ''); ?>">
                                        <?php if (isset($errores['seccion_electoral'])): ?>
                                            <div class="invalid-feedback"><?php echo $errores['seccion_electoral']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">WhatsApp <span class="text-danger">*</span></label>
                                        <input type="tel" class="form-control <?php echo isset($errores['whatsapp']) ? 'is-invalid' : ''; ?>" 
                                               name="whatsapp" pattern="[0-9]{10}" maxlength="10" required 
                                               value="<?php echo htmlspecialchars($_POST['whatsapp'] ?? ''); ?>">
                                        <?php if (isset($errores['whatsapp'])): ?>
                                            <div class="invalid-feedback"><?php echo $errores['whatsapp']; ?></div>
                                        <?php endif; ?>
                                        <small class="text-muted">10 dígitos</small>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Email</label>
                                        <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Ubicación Actual <span class="text-danger">*</span></label>
                                <div class="row">
                                    <div class="col-md-6">
                                        <input type="text" class="form-control <?php echo isset($errores['ubicacion']) ? 'is-invalid' : ''; ?>" 
                                               name="latitud" id="latitud" placeholder="Latitud" readonly required 
                                               value="<?php echo htmlspecialchars($_POST['latitud'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <input type="text" class="form-control <?php echo isset($errores['ubicacion']) ? 'is-invalid' : ''; ?>" 
                                               name="longitud" id="longitud" placeholder="Longitud" readonly required 
                                               value="<?php echo htmlspecialchars($_POST['longitud'] ?? ''); ?>">
                                    </div>
                                </div>
                                <button type="button" class="btn btn-info btn-sm mt-2" onclick="obtenerUbicacion()">
                                    <i class="bi bi-geo-fill me-1"></i>Detectar Mi Ubicación
                                </button>
                                <?php if (isset($errores['ubicacion'])): ?>
                                    <div class="text-danger small mt-1"><?php echo $errores['ubicacion']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Verificación <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <?php echo $_SESSION['captcha_num1']; ?> + <?php echo $_SESSION['captcha_num2']; ?> =
                                    </span>
                                    <input type="number" class="form-control" name="captcha" required placeholder="Respuesta">
                                </div>
                                <small class="text-muted">Resuelva la suma para continuar</small>
                            </div>
                            
                            <div class="mb-3">
                                <div class="card">
                                    <div class="card-header">
                                        <strong>Términos y Condiciones</strong>
                                    </div>
                                    <div class="card-body" style="max-height: 200px; overflow-y: auto;">
                                        <?php echo nl2br(htmlspecialchars($terminos)); ?>
                                    </div>
                                </div>
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" name="terminos" value="aceptado" id="terminos" required>
                                    <label class="form-check-label" for="terminos">
                                        <strong>Acepto los términos y condiciones <span class="text-danger">*</span></strong>
                                    </label>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-gradient btn-lg">
                                    <i class="bi bi-check-circle-fill me-2"></i>Registrarme
                                </button>
                                <a href="<?php echo BASE_URL; ?>" class="btn btn-secondary btn-lg">
                                    <i class="bi bi-arrow-left me-2"></i>Volver al Inicio
                                </a>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function obtenerUbicacion() {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(function(position) {
                document.getElementById('latitud').value = position.coords.latitude.toFixed(8);
                document.getElementById('longitud').value = position.coords.longitude.toFixed(8);
                alert('Ubicación detectada correctamente');
            }, function() {
                alert('No se pudo obtener la ubicación. Por favor, permita el acceso a su ubicación.');
            });
        } else {
            alert('Geolocalización no soportada por este navegador');
        }
    }
    
    // Detectar ubicación automáticamente al cargar la página
    window.addEventListener('load', function() {
        if (!document.getElementById('latitud').value) {
            obtenerUbicacion();
        }
    });
    </script>
</body>
</html>
