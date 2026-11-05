<?php
/**
 * Restablecer Contraseña
 * Sistema de Validación de Simpatizantes
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/Database.php';

require_once __DIR__ . '/../app/helpers/security.php';
seguridad_iniciar_sesion();
csrf_proteger();
$db = Database::getInstance();

$error = '';        // error al validar la nueva contraseña (el formulario sigue visible)
$tokenError = '';   // enlace no válido, usado o vencido (no hay formulario)
$success = '';
$token = $_GET['token'] ?? '';
$step = 1; // 1: enlace no válido, 2: formulario, 3: contraseña actualizada
$recuperacion = null;

// Verificar el enlace
if (!is_string($token) || $token === '') {
    $tokenError = 'El enlace de recuperación no es válido.';
} else {
    $recuperacion = $db->queryOne(
        "SELECT r.id, r.usuario_id, r.expiracion, r.usado, u.username, u.email, u.nombre_completo
         FROM recuperacion_password r
         INNER JOIN usuarios u ON r.usuario_id = u.id
         WHERE r.token = ?",
        [$token]
    );

    if (!$recuperacion) {
        $tokenError = 'El enlace de recuperación no es válido.';
    } elseif ($recuperacion['usado'] == 1) {
        $tokenError = 'Este enlace ya fue utilizado. Solicite uno nuevo.';
    } elseif (strtotime($recuperacion['expiracion']) <= time()) {
        $tokenError = 'Este enlace ha expirado. Solicite uno nuevo.';
    } else {
        $step = 2;
    }
}

// Procesar la nueva contraseña
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 2) {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($password === '') {
        $error = 'La contraseña es obligatoria';
    } elseif (strlen($password) < PASSWORD_MIN_LENGTH) {
        $error = 'La contraseña debe tener al menos ' . PASSWORD_MIN_LENGTH . ' caracteres';
    } elseif ($password !== $confirm_password) {
        $error = 'Las contraseñas no coinciden';
    } else {
        try {
            $db->beginTransaction();

            $db->execute(
                "UPDATE usuarios SET password = ?, updated_at = NOW() WHERE id = ?",
                [password_hash($password, PASSWORD_DEFAULT), $recuperacion['usuario_id']]
            );
            // Invalida este enlace y cualquier otro pendiente del usuario
            $db->execute(
                "UPDATE recuperacion_password SET usado = 1 WHERE usuario_id = ?",
                [$recuperacion['usuario_id']]
            );

            $db->commit();

            $success = 'Su contraseña ha sido restablecida correctamente.';
            $step = 3;
        } catch (Exception $e) {
            $db->rollback();
            error_log('Error al restablecer contraseña: ' . $e->getMessage());
            $error = 'No se pudo actualizar la contraseña. Inténtelo nuevamente.';
        }
    }
}

$headClass = $step === 3 ? 'ok' : ($step === 1 ? 'bad' : '');
$headIcon  = $step === 3 ? 'check2-circle' : ($step === 1 ? 'x-circle' : 'shield-lock');
$headTitle = $step === 3 ? 'Contraseña actualizada' : ($step === 1 ? 'Enlace no disponible' : 'Nueva contraseña');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restablecer contraseña - <?php echo APP_NAME; ?></title>
    <link href="<?php echo BASE_URL; ?>/public/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/vendor/icons/bootstrap-icons.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/css/theme.css?v=<?php echo @filemtime(BASE_PATH . '/public/css/theme.css') ?: 1; ?>">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/css/public.css?v=<?php echo @filemtime(BASE_PATH . '/public/css/public.css') ?: 1; ?>">
</head>
<body class="public-page">
<div class="auth-wrap">
    <div>
        <div class="auth-card">
            <div class="auth-head <?php echo $headClass; ?>">
                <span class="badge-ic"><i class="bi bi-<?php echo $headIcon; ?>"></i></span>
                <div>
                    <h1><?php echo $headTitle; ?></h1>
                    <p><?php echo htmlspecialchars(APP_NAME); ?></p>
                </div>
            </div>
            <div class="auth-body">
                <?php if ($step === 1): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo htmlspecialchars($tokenError); ?>
                    </div>
                    <a href="<?php echo BASE_URL; ?>/public/recuperar-password.php" class="btn btn-primary w-100 py-2">
                        <i class="bi bi-arrow-repeat me-2"></i>Solicitar un enlace nuevo
                    </a>

                <?php elseif ($step === 3): ?>
                    <div class="alert alert-success" role="alert">
                        <i class="bi bi-check-circle-fill me-2"></i><?php echo htmlspecialchars($success); ?>
                    </div>
                    <p class="lead-text mb-4">Ya puede iniciar sesión con su nueva contraseña.</p>
                    <a href="<?php echo BASE_URL; ?>" class="btn btn-primary w-100 py-2">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Iniciar sesión
                    </a>

                <?php else: ?>
                    <p class="lead-text">
                        Cuenta de <strong><?php echo htmlspecialchars($recuperacion['nombre_completo']); ?></strong>
                        (<?php echo htmlspecialchars($recuperacion['email']); ?>).
                    </p>

                    <?php if ($error): ?>
                        <div class="alert alert-danger" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="" id="resetForm" novalidate>
<?php echo csrf_field(); ?>
                        <div class="mb-3">
                            <label for="password" class="form-label">Nueva contraseña</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="password" name="password"
                                       placeholder="Escriba su nueva contraseña" required autocomplete="new-password"
                                       minlength="<?php echo PASSWORD_MIN_LENGTH; ?>" autofocus>
                                <button class="btn btn-soft" type="button" data-toggle-pass="password" aria-label="Mostrar u ocultar">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <div class="password-strength" id="passwordStrength"></div>
                            <div class="form-text">Mínimo <?php echo PASSWORD_MIN_LENGTH; ?> caracteres. Combine mayúsculas, minúsculas, números y símbolos.</div>
                        </div>

                        <div class="mb-4">
                            <label for="confirm_password" class="form-label">Confirmar contraseña</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password"
                                       placeholder="Repita la contraseña" required autocomplete="new-password">
                                <button class="btn btn-soft" type="button" data-toggle-pass="confirm_password" aria-label="Mostrar u ocultar">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <div id="passwordMatch" class="form-text"></div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-2 mb-3" id="submitBtn" disabled>
                            <i class="bi bi-check-lg me-2"></i>Restablecer contraseña
                        </button>
                        <div class="text-center">
                            <a href="<?php echo BASE_URL; ?>" class="auth-link"><i class="bi bi-arrow-left me-1"></i>Volver al inicio de sesión</a>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        <div class="auth-foot"><i class="bi bi-shield-check"></i>Sistema seguro con encriptación</div>
    </div>
</div>

<script src="<?php echo BASE_URL; ?>/public/vendor/bootstrap/bootstrap.bundle.min.js"></script>
<?php if ($step === 2): ?>
<script>
(function () {
    var MIN = <?php echo (int)PASSWORD_MIN_LENGTH; ?>;
    var pass = document.getElementById('password');
    var conf = document.getElementById('confirm_password');
    var bar = document.getElementById('passwordStrength');
    var match = document.getElementById('passwordMatch');
    var btn = document.getElementById('submitBtn');

    document.querySelectorAll('[data-toggle-pass]').forEach(function (b) {
        b.addEventListener('click', function () {
            var f = document.getElementById(b.getAttribute('data-toggle-pass'));
            var show = f.type === 'password';
            f.type = show ? 'text' : 'password';
            b.firstElementChild.className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
        });
    });

    function evaluar() {
        var v = pass.value, n = 0;
        if (v.length >= MIN) n++;
        if (/[a-z]/.test(v)) n++;
        if (/[A-Z]/.test(v)) n++;
        if (/[0-9]/.test(v)) n++;
        if (/[^a-zA-Z0-9]/.test(v)) n++;
        bar.style.width = (n * 20) + '%';
        bar.className = 'password-strength ' + (n < 3 ? 'strength-weak' : (n < 4 ? 'strength-medium' : 'strength-strong'));

        var ok = false;
        if (conf.value.length === 0) {
            match.innerHTML = '';
        } else if (v === conf.value) {
            match.innerHTML = '<span class="text-success"><i class="bi bi-check-circle-fill"></i> Las contraseñas coinciden</span>';
            ok = v.length >= MIN;
        } else {
            match.innerHTML = '<span class="text-danger"><i class="bi bi-x-circle-fill"></i> Las contraseñas no coinciden</span>';
        }
        btn.disabled = !ok;
    }
    pass.addEventListener('input', evaluar);
    conf.addEventListener('input', evaluar);
    evaluar();
})();
</script>
<?php endif; ?>
</body>
</html>
