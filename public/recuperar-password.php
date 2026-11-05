<?php
/**
 * Recuperar Contraseña
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../app/services/EmailService.php';
require_once __DIR__ . '/../app/services/EmailQueue.php';

require_once __DIR__ . '/../app/helpers/security.php';
seguridad_iniciar_sesion();
csrf_proteger();
$db = Database::getInstance();
$emailService = new EmailService();

$error = '';
$step = 1; // 1: solicitar email, 2: confirmación

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if ($email === '') {
        $error = 'El correo electrónico es obligatorio';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'El formato del correo electrónico no es válido';
    } else {
        $usuario = $db->queryOne(
            "SELECT id, username, email, nombre_completo FROM usuarios WHERE email = ? AND activo = 1",
            [$email]
        );

        if ($usuario) {
            // Token de un solo uso con vigencia de una hora
            $token = bin2hex(random_bytes(32));
            $expiracion = date('Y-m-d H:i:s', strtotime('+1 hour'));
            $guardado = $db->execute(
                "INSERT INTO recuperacion_password (usuario_id, token, expiracion) VALUES (?, ?, ?)",
                [$usuario['id'], $token, $expiracion]
            );

            if ($guardado) {
                $emailService->sendPasswordRecoveryEmail($usuario['email'], $usuario['nombre_completo'], $token);
                error_log('Recuperación de contraseña solicitada para el usuario #' . $usuario['id']);
            } else {
                // Fallo del sistema (por ejemplo, falta la tabla): no se puede fingir que se envió
                error_log('No se pudo guardar el token de recuperación del usuario #' . $usuario['id']);
                $error = 'No pudimos procesar su solicitud en este momento. Inténtelo más tarde o contacte al administrador.';
            }
        } else {
            error_log('Recuperación solicitada para un correo no registrado');
        }

        // Misma respuesta exista o no la cuenta, para no revelar qué correos están registrados
        if ($error === '') {
            $step = 2;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar contraseña - <?php echo APP_NAME; ?></title>
    <link href="<?php echo BASE_URL; ?>/public/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/vendor/icons/bootstrap-icons.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/css/theme.css?v=<?php echo @filemtime(BASE_PATH . '/public/css/theme.css') ?: 1; ?>">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/css/public.css?v=<?php echo @filemtime(BASE_PATH . '/public/css/public.css') ?: 1; ?>">
</head>
<body class="public-page">
<div class="auth-wrap">
    <div>
        <div class="auth-card">
            <div class="auth-head <?php echo $step === 2 ? 'ok' : ''; ?>">
                <span class="badge-ic"><i class="bi bi-<?php echo $step === 2 ? 'envelope-check' : 'key'; ?>"></i></span>
                <div>
                    <h1><?php echo $step === 2 ? 'Revise su correo' : 'Recuperar contraseña'; ?></h1>
                    <p><?php echo htmlspecialchars(APP_NAME); ?></p>
                </div>
            </div>
            <div class="auth-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <?php if ($step === 1): ?>
                    <p class="lead-text">Escriba el correo asociado a su cuenta. Le enviaremos un enlace para crear una nueva contraseña.</p>
                    <form method="POST" action="">
<?php echo csrf_field(); ?>
                        <div class="mb-4">
                            <label for="email" class="form-label">Correo electrónico</label>
                            <div class="input-ico">
                                <i class="bi bi-envelope lead-ico"></i>
                                <input type="email" class="form-control" id="email" name="email" placeholder="usuario@correo.com"
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required autofocus>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 py-2 mb-3">
                            <i class="bi bi-send me-2"></i>Enviar instrucciones
                        </button>
                        <div class="text-center">
                            <a href="<?php echo BASE_URL; ?>" class="auth-link"><i class="bi bi-arrow-left me-1"></i>Volver al inicio de sesión</a>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="alert alert-success" role="alert">
                        <i class="bi bi-check-circle-fill me-2"></i>Si el correo está registrado, en unos momentos recibirá las instrucciones para restablecer su contraseña.
                    </div>
                    <p class="lead-text mb-4">El enlace es válido durante una hora y solo puede usarse una vez. Si no lo ve, revise la carpeta de correo no deseado.</p>
                    <a href="<?php echo BASE_URL; ?>" class="btn btn-primary w-100 py-2"><i class="bi bi-box-arrow-in-right me-2"></i>Volver al inicio de sesión</a>
                <?php endif; ?>
            </div>
        </div>
        <div class="auth-foot"><i class="bi bi-shield-check"></i>Sistema seguro con encriptación</div>
    </div>
</div>
<script src="<?php echo BASE_URL; ?>/public/vendor/bootstrap/bootstrap.bundle.min.js"></script>
</body>
</html>
