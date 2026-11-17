<?php
/**
 * Baja de comunicaciones. El enlace personal viene en cada correo (/public/baja.php?t=TOKEN).
 * No requiere iniciar sesión. Al abrir el enlace solo se pide confirmar: la baja ocurre al
 * presionar el botón, para que los antivirus o previsualizadores de correo no den de baja a nadie.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../app/models/Contacto.php';

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');

$token = isset($_REQUEST['t']) && is_string($_REQUEST['t']) ? $_REQUEST['t'] : '';
$valido = preg_match('/^[a-f0-9]{32}$/', $token) === 1;
$estado = 'confirmar';   // confirmar | listo | invalido

$contacto = new Contacto();
if (!$valido || !$contacto->disponible()) {
    $estado = 'invalido';
} elseif (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $estado = $contacto->darDeBajaPorToken($token) ? 'listo' : 'invalido';
} else {
    $existe = Database::getInstance()->queryOne("SELECT baja_comunicacion FROM simpatizantes WHERE baja_token = ?", [$token]);
    if (!$existe) { $estado = 'invalido'; }
    elseif ((int)$existe['baja_comunicacion'] === 1) { $estado = 'listo'; }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Baja de mensajes - <?php echo htmlspecialchars(APP_NAME); ?></title>
    <link href="<?php echo BASE_URL; ?>/public/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/vendor/icons/bootstrap-icons.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/css/theme.css?v=<?php echo @filemtime(BASE_PATH . '/public/css/theme.css') ?: 1; ?>">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/css/public.css?v=<?php echo @filemtime(BASE_PATH . '/public/css/public.css') ?: 1; ?>">
</head>
<body class="public-page">
<div class="auth-wrap">
    <div>
        <div class="auth-card">
            <div class="auth-head <?php echo $estado === 'listo' ? 'ok' : ($estado === 'invalido' ? 'bad' : ''); ?>">
                <span class="badge-ic"><i class="bi bi-<?php echo $estado === 'listo' ? 'check2-circle' : ($estado === 'invalido' ? 'x-circle' : 'envelope-slash'); ?>"></i></span>
                <div>
                    <h1><?php echo $estado === 'listo' ? 'Listo, ya no recibirá mensajes' : ($estado === 'invalido' ? 'Enlace no válido' : '¿Dejar de recibir mensajes?'); ?></h1>
                    <p><?php echo htmlspecialchars(APP_NAME); ?></p>
                </div>
            </div>
            <div class="auth-body">
                <?php if ($estado === 'confirmar'): ?>
                    <p class="lead-text">Si confirma, dejaremos de enviarle correos y mensajes de este equipo de campaña. Puede cambiar de opinión pidiéndolo directamente a quien lo registró.</p>
                    <form method="POST" action="">
                        <input type="hidden" name="t" value="<?php echo htmlspecialchars($token); ?>">
                        <button type="submit" class="btn btn-primary w-100 py-2"><i class="bi bi-envelope-slash me-2"></i>Sí, darme de baja</button>
                    </form>
                <?php elseif ($estado === 'listo'): ?>
                    <p class="lead-text mb-0">Su baja quedó registrada. Lamentamos las molestias y agradecemos su tiempo.</p>
                <?php else: ?>
                    <p class="lead-text mb-0">El enlace no es válido o ya no está disponible. Si desea dejar de recibir mensajes, responda al correo que recibió y lo atenderemos.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
</body>
</html>
