<?php
/**
 * Utilidades de seguridad: errores, sesión, CSRF y validación de imágenes.
 */

// Nunca mostrar errores al usuario; se registran en el log del servidor
@ini_set('display_errors', '0');
@ini_set('log_errors', '1');

/**
 * Inicia la sesión con cookies endurecidas.
 */
function seguridad_iniciar_sesion() {
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    if (defined('SESSION_NAME')) {
        session_name(SESSION_NAME);
    }
    session_start();
}

/**
 * Token CSRF de la sesión actual.
 */
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Campo oculto para formularios POST.
 */
function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES) . '">';
}

/**
 * Verifica el token en toda petición POST (campo del formulario o cabecera X-CSRF-Token).
 */
function csrf_proteger() {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        return;
    }
    $enviado = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    $valido  = !empty($_SESSION['csrf_token']) && is_string($enviado)
        && hash_equals($_SESSION['csrf_token'], $enviado);
    if (!$valido) {
        http_response_code(403);
        $json = stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false
            || strpos($_SERVER['SCRIPT_NAME'] ?? '', '/api/') !== false;
        if ($json) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Sesión expirada o petición no válida. Recargue la página.']);
        } else {
            echo '<!DOCTYPE html><meta charset="utf-8"><title>Solicitud no válida</title>'
               . '<p style="font-family:sans-serif;padding:2rem">La sesión expiró o la solicitud no es válida. '
               . '<a href="javascript:history.back()">Volver</a> y recargar la página.</p>';
        }
        exit;
    }
}

/**
 * Detecta el tipo real de una imagen. Devuelve la extensión segura o null.
 */
function seguridad_extension_imagen($rutaTemporal) {
    if (!is_file($rutaTemporal) || @getimagesize($rutaTemporal) === false) {
        return null;
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($rutaTemporal);
    $mapa  = ['image/jpeg' => 'jpg', 'image/png' => 'png'];
    return $mapa[$mime] ?? null;
}
