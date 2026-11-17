<?php
/**
 * Base común de la API de la app móvil (public/api/app/*.php).
 *
 * Autenticación: token opaco en la cabecera "Authorization: Bearer <token>". Como algunos hostings
 * (Apache en cPanel) quitan esa cabecera, la app envía además "X-App-Token" con el mismo valor.
 * No usa cookies ni sesión del navegador, por lo que no necesita token CSRF.
 */

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/Database.php';
require_once __DIR__ . '/../../../app/helpers/security.php';
require_once __DIR__ . '/../../../app/services/AppAuthService.php';
require_once __DIR__ . '/../../../app/services/CiudadanoAuthService.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function app_json($codigo, array $cuerpo) {
    http_response_code($codigo);
    echo json_encode($cuerpo, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Exige un método HTTP. */
function app_metodo($metodo) {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== $metodo) {
        app_json(405, ['ok' => false, 'error' => 'Método no permitido']);
    }
}

/** Cuerpo JSON de la petición (máximo 128 KB). */
function app_cuerpo() {
    $in = json_decode((string)file_get_contents('php://input', false, null, 0, 131072), true);
    return is_array($in) ? $in : [];
}

/** Token enviado por la app, o null. */
function app_token() {
    $h = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    if (stripos($h, 'Bearer ') === 0) { return trim(substr($h, 7)); }
    $x = $_SERVER['HTTP_X_APP_TOKEN'] ?? '';
    return $x !== '' ? trim($x) : null;
}

/** Compara versiones "1.2.3". true si $a >= $b. */
function app_version_ok($a, $b) {
    return version_compare((string)$a ?: '0.0.0', (string)$b ?: '0.0.0', '>=');
}

/** Versión mínima que puede sincronizar (configuración `app_version_minima`). */
function app_version_minima() {
    $r = Database::getInstance()->queryOne("SELECT valor FROM configuracion WHERE clave = 'app_version_minima'");
    return ($r && $r['valor'] !== '') ? $r['valor'] : '1.0.0';
}

/** Responde 426 si la app es más vieja que la mínima permitida. */
function app_exigir_version() {
    $v = $_SERVER['HTTP_X_APP_VERSION'] ?? '';
    $min = app_version_minima();
    if ($v !== '' && !app_version_ok($v, $min)) {
        app_json(426, ['ok' => false, 'actualizar' => true, 'error' => 'Hay una versión nueva de la app. Actualícela para seguir sincronizando.', 'version_minima' => $min]);
    }
}

/**
 * Valida el token y prepara el contexto del usuario para reutilizar los controladores existentes
 * (alcance por campaña, capturista, auditoría…), que leen al usuario de $_SESSION.
 *
 * @return array [AuthController $auth, array $usuario, string $token]
 */
function app_autenticar() {
    $servicio = new AppAuthService();
    if (!$servicio->disponible()) {
        app_json(503, ['ok' => false, 'error' => 'Falta ejecutar database/app_movil.sql en el servidor.']);
    }
    $token = app_token();
    $usuario = $token ? $servicio->validar($token) : null;
    if (!$usuario) {
        app_json(401, ['ok' => false, 'sesion_vencida' => true, 'error' => 'Su sesión venció. Inicie sesión de nuevo.']);
    }
    app_exigir_version();

    define('API_APP_TOKEN_OK', true);
    require_once __DIR__ . '/../../../app/controllers/AuthController.php';
    $auth = new AuthController();               // inicia una sesión temporal solo para esta petición
    header_remove('Set-Cookie');
    $_SESSION['usuario_id'] = (int)$usuario['id'];
    $_SESSION['username'] = $usuario['username'];
    $_SESSION['nombre_completo'] = $usuario['nombre_completo'];
    $_SESSION['rol'] = $usuario['rol'];
    $_SESSION['email'] = $usuario['email'];
    $_SESSION['campana_id'] = $usuario['campana_id'] ?? null;
    $_SESSION['last_activity'] = time();
    register_shutdown_function(function () {
        if (session_status() === PHP_SESSION_ACTIVE) { $_SESSION = []; @session_destroy(); }
    });
    return [$auth, $usuario, $token];
}

/**
 * Autentica a una persona de la ciudadanía con su token. No usa sesión ni controladores del equipo.
 * Con $obligatorio = false devuelve null si no hay token válido (para rutas que también aceptan denuncias anónimas).
 *
 * @return array|null [array $ciudadano, string $token]
 */
function app_autenticar_ciudadano($obligatorio = true) {
    $servicio = new CiudadanoAuthService();
    if (!$servicio->disponible()) {
        app_json(503, ['ok' => false, 'error' => 'Falta ejecutar database/atencion_ciudadana.sql en el servidor.']);
    }
    $token = app_token();
    $ciudadano = $token ? $servicio->validar($token) : null;
    if (!$ciudadano) {
        if ($obligatorio) {
            app_json(401, ['ok' => false, 'sesion_vencida' => true, 'error' => 'Su sesión venció. Inicie sesión de nuevo.']);
        }
        return null;
    }
    app_exigir_version();
    return [$ciudadano, $token];
}

/** IP del dispositivo (para límites contra abuso). */
function app_ip() {
    return $_SERVER['REMOTE_ADDR'] ?? null;
}
