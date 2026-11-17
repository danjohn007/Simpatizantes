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
    http_response_code(403);
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro no disponible - <?php echo APP_NAME; ?></title>
    <link href="<?php echo BASE_URL; ?>/public/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/vendor/icons/bootstrap-icons.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/css/theme.css?v=<?php echo @filemtime(BASE_PATH . '/public/css/theme.css') ?: 1; ?>">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/css/public.css?v=<?php echo @filemtime(BASE_PATH . '/public/css/public.css') ?: 1; ?>">
</head>
<body class="public-page">
<div class="auth-wrap">
    <div>
        <div class="auth-card">
            <div class="auth-head bad">
                <span class="badge-ic"><i class="bi bi-person-lock"></i></span>
                <div><h1>Registro no disponible</h1><p><?php echo htmlspecialchars(APP_NAME); ?></p></div>
            </div>
            <div class="auth-body">
                <p class="lead-text">El registro público de simpatizantes no está habilitado por el momento. Si desea registrarse, comuníquese con el equipo de la campaña.</p>
                <a href="<?php echo BASE_URL; ?>" class="btn btn-primary w-100 py-2"><i class="bi bi-arrow-left me-2"></i>Volver al inicio</a>
            </div>
        </div>
    </div>
</div>
</body>
</html>
<?php
    exit;
}

$controller = new SimpatizanteController();
$campanaModel = new Campana();

// Obtener campañas activas
$campanas = $campanaModel->obtenerTodas(1);
$idsCampanas = array_map('intval', array_column($campanas, 'id'));

$error = '';
$success = '';
$errores = [];

// Generar números para captcha (la sesión ya está iniciada en AuthController)
if (!isset($_SESSION['captcha_num1']) || !isset($_SESSION['captcha_num2'])) {
    $_SESSION['captcha_num1'] = random_int(1, 10);
    $_SESSION['captcha_num2'] = random_int(1, 10);
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar CAPTCHA
    $captchaRespuesta = $_POST['captcha'] ?? '';
    $captchaCorrecta = $_SESSION['captcha_num1'] + $_SESSION['captcha_num2'];
    
    if (!empty($_POST['sitio_web'])) {
        // Un bot rellenó el campo oculto: se responde como si todo estuviera bien, sin guardar nada
        $success = '¡Registro exitoso! Su información ha sido enviada para validación.';
    } elseif ($captchaRespuesta != $captchaCorrecta) {
        $error = 'Respuesta incorrecta en la verificación matemática';
        // Regenerar números
        $_SESSION['captcha_num1'] = random_int(1, 10);
        $_SESSION['captcha_num2'] = random_int(1, 10);
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
            'consentimiento' => isset($_POST['consentimiento']) ? 1 : 0,
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
        // Validate campaign_id - must be a positive integer
        if (empty($datos['campana_id']) || !filter_var($datos['campana_id'], FILTER_VALIDATE_INT) || $datos['campana_id'] <= 0
            || !in_array((int)$datos['campana_id'], $idsCampanas, true)) {
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
        $_SESSION['captcha_num1'] = random_int(1, 10);
        $_SESSION['captcha_num2'] = random_int(1, 10);
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
    <title>Registro de simpatizante - <?php echo APP_NAME; ?></title>
    <link href="<?php echo BASE_URL; ?>/public/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/vendor/icons/bootstrap-icons.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/css/theme.css?v=<?php echo @filemtime(BASE_PATH . '/public/css/theme.css') ?: 1; ?>">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/css/public.css?v=<?php echo @filemtime(BASE_PATH . '/public/css/public.css') ?: 1; ?>">
</head>
<body class="public-page">
<div class="auth-wrap top">
    <div class="w-100" style="max-width: 940px;">
        <div class="auth-card wide">
            <div class="auth-head <?php echo $success ? 'ok' : ''; ?>">
                <span class="badge-ic"><i class="bi bi-<?php echo $success ? 'check2-circle' : 'person-plus'; ?>"></i></span>
                <div>
                    <h1><?php echo $success ? 'Registro enviado' : 'Registro de simpatizante'; ?></h1>
                    <p><?php echo $success ? htmlspecialchars(APP_NAME) : 'Complete el formulario. Los campos con * son obligatorios.'; ?></p>
                </div>
            </div>

            <div class="auth-body">
            <?php if ($success): ?>
                <div class="alert alert-success mb-4">
                    <i class="bi bi-check-circle-fill me-2"></i><?php echo htmlspecialchars($success); ?>
                </div>
                <a href="<?php echo BASE_URL; ?>" class="btn btn-primary"><i class="bi bi-house me-2"></i>Volver al inicio</a>
            <?php else: ?>
                <?php if ($error || !empty($errores)): ?>
                    <div class="alert alert-danger" id="avisoErrores">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <?php echo htmlspecialchars($error ?: 'Revise la información: hay datos que corregir.'); ?>
                        <?php if (!empty($errores)): ?>
                            <ul class="mb-0 mt-2">
                                <?php foreach ($errores as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                    <script>document.addEventListener('DOMContentLoaded', function () { var a = document.getElementById('avisoErrores'); if (a) a.scrollIntoView({ block: 'center' }); });</script>
                <?php endif; ?>

                <form method="POST" action="" novalidate>
<?php echo csrf_field(); ?>
                    <!-- Campo trampa para bots: las personas no lo ven -->
                    <div class="hp-field" aria-hidden="true">
                        <label>No llenar este campo<input type="text" name="sitio_web" tabindex="-1" autocomplete="off"></label>
                    </div>

                    <div class="section-title">Datos personales</div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-8">
                            <label class="form-label">Nombre completo <span class="text-danger">*</span></label>
                            <input type="text" class="form-control <?php echo isset($errores['nombre_completo']) ? 'is-invalid' : ''; ?>"
                                   name="nombre_completo" required value="<?php echo htmlspecialchars($_POST['nombre_completo'] ?? ''); ?>">
                            <?php if (isset($errores['nombre_completo'])): ?><div class="invalid-feedback"><?php echo htmlspecialchars($errores['nombre_completo']); ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Sexo <span class="text-danger">*</span></label>
                            <select class="form-select <?php echo isset($errores['sexo']) ? 'is-invalid' : ''; ?>" name="sexo" required>
                                <option value="">Seleccionar</option>
                                <option value="M" <?php echo (($_POST['sexo'] ?? '') === 'M') ? 'selected' : ''; ?>>Masculino</option>
                                <option value="F" <?php echo (($_POST['sexo'] ?? '') === 'F') ? 'selected' : ''; ?>>Femenino</option>
                                <option value="O" <?php echo (($_POST['sexo'] ?? '') === 'O') ? 'selected' : ''; ?>>Otro</option>
                            </select>
                            <?php if (isset($errores['sexo'])): ?><div class="invalid-feedback"><?php echo htmlspecialchars($errores['sexo']); ?></div><?php endif; ?>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Domicilio completo <span class="text-danger">*</span></label>
                            <textarea class="form-control <?php echo isset($errores['domicilio_completo']) ? 'is-invalid' : ''; ?>"
                                      name="domicilio_completo" rows="2" required><?php echo htmlspecialchars($_POST['domicilio_completo'] ?? ''); ?></textarea>
                            <?php if (isset($errores['domicilio_completo'])): ?><div class="invalid-feedback"><?php echo htmlspecialchars($errores['domicilio_completo']); ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Ciudad</label>
                            <input type="text" class="form-control" name="ciudad" value="<?php echo htmlspecialchars($_POST['ciudad'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Fecha de nacimiento</label>
                            <input type="date" class="form-control" name="fecha_nacimiento" value="<?php echo htmlspecialchars($_POST['fecha_nacimiento'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="section-title">Datos electorales</div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Clave de elector</label>
                            <input type="text" class="form-control" name="clave_elector" maxlength="18" value="<?php echo htmlspecialchars($_POST['clave_elector'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">CURP</label>
                            <input type="text" class="form-control" name="curp" maxlength="18" value="<?php echo htmlspecialchars($_POST['curp'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Sección electoral <span class="text-danger">*</span></label>
                            <input type="text" class="form-control <?php echo isset($errores['seccion_electoral']) ? 'is-invalid' : ''; ?>"
                                   name="seccion_electoral" pattern="[0-9]{4}" maxlength="4" inputmode="numeric" required
                                   value="<?php echo htmlspecialchars($_POST['seccion_electoral'] ?? ''); ?>">
                            <?php if (isset($errores['seccion_electoral'])): ?><div class="invalid-feedback"><?php echo htmlspecialchars($errores['seccion_electoral']); ?></div><?php endif; ?>
                            <div class="form-text">4 dígitos, aparece en su INE.</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Campaña <span class="text-danger">*</span></label>
                            <select class="form-select <?php echo isset($errores['campana_id']) ? 'is-invalid' : ''; ?>" name="campana_id" required>
                                <option value="">Seleccionar campaña</option>
                                <?php foreach ($campanas as $campana): ?>
                                    <option value="<?php echo (int)$campana['id']; ?>" <?php echo (isset($_POST['campana_id']) && $_POST['campana_id'] == $campana['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($campana['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errores['campana_id'])): ?><div class="invalid-feedback"><?php echo htmlspecialchars($errores['campana_id']); ?></div><?php endif; ?>
                        </div>
                    </div>

                    <div class="section-title">Contacto</div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">WhatsApp <span class="text-danger">*</span></label>
                            <input type="tel" class="form-control <?php echo isset($errores['whatsapp']) ? 'is-invalid' : ''; ?>"
                                   name="whatsapp" pattern="[0-9]{10}" maxlength="10" inputmode="numeric" required
                                   value="<?php echo htmlspecialchars($_POST['whatsapp'] ?? ''); ?>">
                            <?php if (isset($errores['whatsapp'])): ?><div class="invalid-feedback"><?php echo htmlspecialchars($errores['whatsapp']); ?></div><?php endif; ?>
                            <div class="form-text">10 dígitos, sin espacios ni guiones.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Correo electrónico</label>
                            <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="section-title">Ubicación</div>
                    <div class="mb-3">
                        <div class="row g-3">
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
                        <div class="d-flex flex-wrap align-items-center gap-3 mt-3">
                            <button type="button" class="btn btn-sm btn-soft" onclick="obtenerUbicacion()">
                                <i class="bi bi-crosshair me-1"></i>Detectar mi ubicación
                            </button>
                            <span class="loc-status" id="locStatus"></span>
                        </div>
                        <?php if (isset($errores['ubicacion'])): ?>
                            <div class="text-danger small mt-2"><?php echo htmlspecialchars($errores['ubicacion']); ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="section-title">Verificación y términos</div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Verificación <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text fw-semibold"><?php echo (int)$_SESSION['captcha_num1']; ?> + <?php echo (int)$_SESSION['captcha_num2']; ?> =</span>
                                <input type="number" class="form-control" name="captcha" required placeholder="Respuesta" inputmode="numeric">
                            </div>
                            <div class="form-text">Resuelva la suma para continuar.</div>
                        </div>
                    </div>
                    <div class="mb-4">
                        <div class="terms-box"><?php echo nl2br(htmlspecialchars($terminos)); ?></div>
                        <div class="form-check mt-3">
                            <input class="form-check-input" type="checkbox" name="terminos" value="aceptado" id="terminos" required>
                            <label class="form-check-label" for="terminos">Acepto los términos y condiciones <span class="text-danger">*</span></label>
                        </div>
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" name="consentimiento" value="1" id="consentimiento" <?php echo isset($_POST['consentimiento']) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="consentimiento">Acepto recibir mensajes por WhatsApp, correo o llamada con información de la campaña (opcional; puedo darme de baja en cualquier momento)</label>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap justify-content-end gap-2">
                        <a href="<?php echo BASE_URL; ?>" class="btn btn-soft px-4">Cancelar</a>
                        <button type="submit" class="btn btn-primary px-4"><i class="bi bi-check-lg me-2"></i>Registrarme</button>
                    </div>
                </form>
            <?php endif; ?>
            </div>
        </div>
        <div class="auth-foot"><i class="bi bi-shield-check"></i>Sus datos se envían de forma segura</div>
    </div>
</div>

<script src="<?php echo BASE_URL; ?>/public/vendor/bootstrap/bootstrap.bundle.min.js"></script>
<script>
function estadoUbicacion(texto, clase) {
    var el = document.getElementById('locStatus');
    if (!el) return;
    el.className = 'loc-status ' + (clase || '');
    el.textContent = texto;
}
function obtenerUbicacion() {
    if (!navigator.geolocation) {
        estadoUbicacion('Su navegador no permite obtener la ubicación.', 'bad');
        return;
    }
    estadoUbicacion('Obteniendo ubicación...', '');
    navigator.geolocation.getCurrentPosition(function (position) {
        document.getElementById('latitud').value = position.coords.latitude.toFixed(8);
        document.getElementById('longitud').value = position.coords.longitude.toFixed(8);
        estadoUbicacion('Ubicación detectada correctamente', 'ok');
    }, function () {
        estadoUbicacion('No se pudo obtener la ubicación. Permita el acceso en su navegador e inténtelo de nuevo.', 'bad');
    });
}
window.addEventListener('load', function () {
    var lat = document.getElementById('latitud');
    if (!lat) return;
    if (!lat.value) { obtenerUbicacion(); } else { estadoUbicacion('Ubicación detectada correctamente', 'ok'); }
});
</script>
</body>
</html>
