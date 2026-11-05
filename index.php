<?php
/**
 * Página de Login
 * Sistema de Validación de Simpatizantes
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/app/controllers/AuthController.php';

$auth = new AuthController();

// Redirigir si ya está autenticado
if ($auth->estaAutenticado()) {
    header('Location: ' . BASE_URL . '/public/dashboard.php');
    exit;
}

// ¿Está habilitado el registro público de simpatizantes?
$db_login = Database::getInstance();
$cfgRegistro = $db_login->queryOne("SELECT valor FROM configuracion WHERE clave = 'registro_publico_habilitado'");
$registroPublicoActivo = $cfgRegistro && $cfgRegistro['valor'] === 'true';

$error = '';
$success = '';

// Procesar login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = $auth->login();

    if (isset($result['success'])) {
        header('Location: ' . BASE_URL . '/public/dashboard.php');
        exit;
    } else {
        $error = $result['error'] ?? 'Error al iniciar sesión';
    }
}
$imgLogin = BASE_URL . '/public/img/login-mc.jpg?v=' . (@filemtime(BASE_PATH . '/public/img/login-mc.jpg') ?: 1);
$imgLoginSm = BASE_URL . '/public/img/login-mc-sm.jpg?v=' . (@filemtime(BASE_PATH . '/public/img/login-mc-sm.jpg') ?: 1);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo APP_NAME; ?></title>
    <link href="<?php echo BASE_URL; ?>/public/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/vendor/icons/bootstrap-icons.css">
    <style>
        :root {
            --brand: #ff8000;
            --brand-hover: #e67300;
            --brand-text: #b85400;   /* naranja oscuro: texto sobre fondo claro con buen contraste */
            --ink: #111111;
            --muted: #6e655e;
            --line: #e9e4de;
            --paper: #faf8f5;
        }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; color: var(--ink); background: var(--paper); overflow-x: hidden; }

        .login-shell {
            position: relative; display: grid; min-height: 100vh; overflow: hidden; background: var(--ink);
            /* el panel del cartel mide alto × 0.8 (proporción 4:5 del cartel) para mostrarlo completo */
            grid-template-columns: minmax(340px, min(calc(100vh * 0.8), calc(100vw - 540px))) 1fr;
        }

        /* ---------- Panel del cartel ---------- */
        .hero {
            position: relative; overflow: hidden; isolation: isolate; background: #ff9100;
            display: flex; flex-direction: column; justify-content: flex-end; padding: 40px 48px;
        }
        .hero::before {
            content: ""; position: absolute; inset: 0; z-index: -2;
            background: url("<?php echo $imgLogin; ?>") 50% 50% / cover no-repeat;
            animation: settle 2s cubic-bezier(.22,1,.36,1) both;
        }
        .hero::after {
            content: ""; position: absolute; inset: 0; z-index: -1;
            background: linear-gradient(180deg, rgba(17,17,17,0) 55%, rgba(17,17,17,.82) 100%);
        }
        @keyframes settle { from { transform: scale(1.08); } to { transform: scale(1); } }

        .brand { display: flex; align-items: center; gap: 14px; color: #fff; font-weight: 700; font-size: 1.05rem; letter-spacing: .01em; }
        .brand-logo {
            flex: none; width: 48px; height: 48px; border-radius: 12px; display: grid; place-items: center; font-size: 1.5rem;
            background: var(--ink); color: var(--brand); border: 2px solid var(--brand);
            animation: pulseRing 3s ease-out infinite;
        }
        @keyframes pulseRing { 0% { box-shadow: 0 0 0 0 rgba(255,128,0,.55); } 100% { box-shadow: 0 0 0 16px rgba(255,128,0,0); } }
        .brand small { display: block; font-weight: 500; opacity: .8; font-size: .8rem; }

        /* ---------- Panel del formulario ---------- */
        .form-side { display: flex; align-items: center; justify-content: center; padding: 40px 28px; position: relative; background: var(--paper); }
        .form-wrap { width: 100%; max-width: 420px; }
        @keyframes rise { from { opacity: 0; transform: translateY(26px); } to { opacity: 1; transform: none; } }
        .eyebrow { font-size: .78rem; font-weight: 700; letter-spacing: .18em; color: var(--brand-text); text-transform: uppercase; }
        .eyebrow::before { content: ""; display: inline-block; width: 28px; height: 3px; background: var(--brand); border-radius: 2px; vertical-align: middle; margin-right: 10px; }
        .form-wrap h2 { font-size: 2.2rem; font-weight: 800; margin: 8px 0 6px; color: var(--ink); letter-spacing: -.02em; }
        .form-wrap .lead { color: var(--muted); font-size: 1rem; margin-bottom: 28px; }

        .field { position: relative; margin-bottom: 18px; }
        .field label { display: block; font-weight: 600; font-size: .9rem; margin-bottom: 6px; color: var(--ink); }
        .input-ico { position: relative; }
        .input-ico > i.lead-ico { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--muted); transition: color .2s, transform .3s; }
        .input-ico input {
            width: 100%; height: 52px; padding: 0 46px 0 44px; border-radius: 10px; border: 2px solid var(--line); background: #fff; font-size: 1rem; color: var(--ink);
            transition: border-color .2s, box-shadow .2s;
        }
        .input-ico input::placeholder { color: #9a9088; }
        .input-ico input:focus { outline: none; border-color: var(--brand); box-shadow: 0 0 0 4px rgba(255,128,0,.22); }
        .input-ico:focus-within > i.lead-ico { color: var(--brand-text); transform: translateY(-50%) scale(1.2) rotate(-8deg); }
        .toggle-pass { position: absolute; right: 8px; top: 50%; transform: translateY(-50%); border: 0; background: none; color: var(--muted); width: 38px; height: 38px; border-radius: 8px; }
        .toggle-pass:hover { color: var(--ink); background: rgba(0,0,0,.05); }
        .toggle-pass:focus-visible, .btn-login:focus-visible, .link-card:focus-visible { outline: 3px solid var(--ink); outline-offset: 2px; }

        .btn-login {
            position: relative; overflow: hidden; width: 100%; height: 54px; border: 0; border-radius: 10px;
            background: var(--brand); color: var(--ink); font-weight: 800; font-size: 1.05rem; letter-spacing: .01em;
            box-shadow: 0 10px 24px rgba(255,128,0,.35);
            transition: transform .2s, box-shadow .2s, background .2s;
        }
        .btn-login:hover { transform: translateY(-2px); background: var(--brand-hover); box-shadow: 0 14px 30px rgba(255,128,0,.45); }
        .btn-login:active { transform: translateY(0); }
        .btn-login::after { content: ""; position: absolute; top: 0; left: -80%; width: 50%; height: 100%; background: linear-gradient(100deg, transparent, rgba(255,255,255,.55), transparent); transform: skewX(-20deg); animation: shine 3.5s ease-in-out infinite; }
        @keyframes shine { 0%, 60% { left: -80%; } 100% { left: 140%; } }
        .btn-login i.arrow { display: inline-block; transition: transform .25s; }
        .btn-login:hover i.arrow { transform: translateX(5px); }
        .btn-login.loading { pointer-events: none; opacity: .85; }

        .links { margin-top: 22px; display: grid; gap: 12px; }
        .link-card {
            display: flex; align-items: center; gap: 14px; padding: 14px 16px; border-radius: 12px; background: #fff; border: 2px solid var(--line);
            text-decoration: none; color: var(--ink); transition: transform .25s, box-shadow .25s, border-color .25s;
        }
        .link-card:hover { transform: translateY(-3px); box-shadow: 0 12px 26px rgba(17,17,17,.1); border-color: var(--brand); color: var(--ink); }
        .link-card .ic { width: 42px; height: 42px; border-radius: 10px; display: grid; place-items: center; background: var(--ink); color: var(--brand); font-size: 1.2rem; transition: transform .35s; }
        .link-card:hover .ic { transform: rotate(-10deg) scale(1.1); }
        .link-card small { color: var(--muted); display: block; }
        .link-card b { display: block; }
        .secure-note { margin-top: 22px; text-align: center; color: var(--muted); font-size: .85rem; }

        .alert-shake { animation: shake .5s; }
        @keyframes shake { 20%,60% { transform: translateX(-8px); } 40%,80% { transform: translateX(8px); } }

        /* ---------- Entrada: las dos mitades llegan y se unen ---------- */
        .hero, .form-side { will-change: transform, opacity; }
        .hero { animation: joinLeft 1.3s cubic-bezier(.22,1,.36,1) both; }
        .form-side { animation: joinRight 1.3s cubic-bezier(.22,1,.36,1) .12s both; }
        @keyframes joinLeft  { from { transform: translateX(-16vw) scale(.94); opacity: 0; border-radius: 32px; } 60% { opacity: 1; } to { transform: none; opacity: 1; border-radius: 0; } }
        @keyframes joinRight { from { transform: translateX(16vw) scale(.94); opacity: 0; border-radius: 32px; } 60% { opacity: 1; } to { transform: none; opacity: 1; border-radius: 0; } }

        .seam { position: absolute; top: 0; bottom: 0; right: 0; width: 6px; z-index: 5; pointer-events: none; opacity: 0;
            background: linear-gradient(180deg, transparent, var(--brand), transparent); box-shadow: 0 0 28px 8px rgba(255,128,0,.55);
            animation: seam 1.1s ease-out 1.05s both; }
        @keyframes seam { 0% { opacity: 0; transform: scaleY(.1); } 40% { opacity: 1; transform: scaleY(1); } 100% { opacity: 0; transform: scaleY(1); } }

        .brand, .form-wrap, .field, .btn-login, .links, .secure-note { animation: rise .8s cubic-bezier(.22,1,.36,1) both; }
        .brand { animation-delay: 1.3s; }
        .form-wrap { animation-delay: 1.0s; }
        .field:nth-of-type(1) { animation-delay: 1.15s; }
        .field:nth-of-type(2) { animation-delay: 1.25s; }
        .btn-login { animation-delay: 1.35s; }
        .links { animation-delay: 1.45s; }
        .secure-note { animation-delay: 1.55s; }

        /* ---------- Móvil ---------- */
        @media (max-width: 991px) {
            .login-shell { grid-template-columns: 1fr; }
            .hero { min-height: 340px; padding: 24px; }
            .hero::before { background-image: url("<?php echo $imgLoginSm; ?>"); background-position: 50% 8%; }
            .seam { display: none; }
            @keyframes joinLeft  { from { transform: translateY(-12vh) scale(.94); opacity: 0; border-radius: 28px; } 60% { opacity: 1; } to { transform: none; opacity: 1; border-radius: 0; } }
            @keyframes joinRight { from { transform: translateY(12vh) scale(.94); opacity: 0; border-radius: 28px; } 60% { opacity: 1; } to { transform: none; opacity: 1; border-radius: 0; } }
            .form-side { padding: 28px 20px 40px; }
        }
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation: none !important; transition-duration: .01ms !important; }
        }
    </style>
</head>
<body>
<div class="login-shell">
    <section class="hero" aria-label="Movimiento Ciudadano Querétaro">
        <span class="seam"></span>
        <div class="brand">
            <div class="brand-logo"><i class="bi bi-person-check-fill"></i></div>
            <div><?php echo htmlspecialchars(APP_NAME); ?><small>Acceso seguro para el equipo de campaña</small></div>
        </div>
    </section>

    <main class="form-side">
        <div class="form-wrap">
            <div class="eyebrow">Bienvenido</div>
            <h2>Inicia sesión</h2>
            <p class="lead">Entra a tu panel para gestionar simpatizantes y campañas.</p>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-shake alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="loginForm">
<?php echo csrf_field(); ?>
                <div class="field">
                    <label for="username">Usuario</label>
                    <div class="input-ico">
                        <i class="bi bi-person-fill lead-ico"></i>
                        <input type="text" id="username" name="username" placeholder="Ingrese su usuario" autocomplete="username" required autofocus>
                    </div>
                </div>
                <div class="field">
                    <label for="password">Contraseña</label>
                    <div class="input-ico">
                        <i class="bi bi-lock-fill lead-ico"></i>
                        <input type="password" id="password" name="password" placeholder="Ingrese su contraseña" autocomplete="current-password" required>
                        <button type="button" class="toggle-pass" id="togglePass" aria-label="Mostrar u ocultar contraseña"><i class="bi bi-eye"></i></button>
                    </div>
                </div>
                <button type="submit" class="btn-login" id="btnLogin">
                    <span class="label">Iniciar Sesión <i class="bi bi-arrow-right arrow ms-1"></i></span>
                </button>
            </form>

            <div class="links">
                <a class="link-card" href="<?php echo BASE_URL; ?>/public/recuperar-password.php">
                    <span class="ic"><i class="bi bi-key-fill"></i></span>
                    <span><b>¿Olvidaste tu contraseña?</b><small>Te enviamos un enlace para restablecerla</small></span>
                </a>
                <?php if ($registroPublicoActivo): ?>
                <a class="link-card" href="<?php echo BASE_URL; ?>/public/registro-publico.php">
                    <span class="ic"><i class="bi bi-person-plus-fill"></i></span>
                    <span><b>Registrarse como Simpatizante</b><small>Crea tu registro en unos minutos</small></span>
                </a>
                <?php endif; ?>
            </div>
            <div class="secure-note"><i class="bi bi-shield-check"></i> Sistema seguro con encriptación</div>
        </div>
    </main>
</div>

<script src="<?php echo BASE_URL; ?>/public/vendor/bootstrap/bootstrap.bundle.min.js"></script>
<script>
(function () {
    var pass = document.getElementById('password'), tg = document.getElementById('togglePass');
    tg.addEventListener('click', function () {
        var show = pass.type === 'password';
        pass.type = show ? 'text' : 'password';
        tg.firstElementChild.className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
    });

    document.getElementById('loginForm').addEventListener('submit', function () {
        var b = document.getElementById('btnLogin');
        b.classList.add('loading');
        b.querySelector('.label').innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Entrando...';
    });
})();
</script>
</body>
</html>
