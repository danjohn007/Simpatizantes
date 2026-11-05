<?php
/**
 * Archivo de Configuración Principal
 * Sistema de Validación de Simpatizantes
 */

// Configuración de zona horaria
date_default_timezone_set('America/Mexico_City');

// Credenciales y modo de ejecución: se leen de config/config.local.php (no versionado)
// o de variables de entorno. Ver config/config.local.php.example
if (is_file(__DIR__ . '/config.local.php')) {
    require __DIR__ . '/config.local.php';
}

// Modo debug: desactivado por defecto (solo se activa en config.local.php de desarrollo)
if (!defined('APP_DEBUG')) {
    define('APP_DEBUG', false);
}

// Los errores nunca se muestran al usuario en producción; se registran en el log
error_reporting(E_ALL);
ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('log_errors', '1');

// Configuración de Base de Datos
defined('DB_HOST') || define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
defined('DB_NAME') || define('DB_NAME', getenv('DB_NAME') ?: '');
defined('DB_USER') || define('DB_USER', getenv('DB_USER') ?: '');
defined('DB_PASS') || define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_CHARSET', 'utf8mb4');

// Configuración de la Aplicación
define('APP_NAME', 'Sistema de Validación de Simpatizantes');
define('APP_VERSION', '1.0.0');

// Configuración de URL Base (se detecta automáticamente)
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

// Determinar el directorio base del proyecto de forma consistente
// Usando la ruta del archivo config.php como referencia
$configPath = str_replace('\\', '/', __DIR__);
$documentRoot = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? '');

// Calcular el path relativo desde document root hasta la raíz del proyecto
if (!empty($documentRoot) && strpos($configPath, $documentRoot) === 0) {
    // El directorio del proyecto es el padre del directorio config
    $projectPath = dirname($configPath);
    $baseDir = str_replace($documentRoot, '', $projectPath);
    $baseDir = ($baseDir === '/' || $baseDir === '' || $baseDir === '//') ? '' : $baseDir;
} else {
    // Fallback si no se puede determinar
    $baseDir = '';
}

define('BASE_URL', $protocol . '://' . $host . $baseDir);
define('BASE_PATH', dirname(__DIR__));

// Rutas de directorios
define('APP_PATH', BASE_PATH . '/app');
define('PUBLIC_PATH', BASE_PATH . '/public');
define('UPLOAD_PATH', PUBLIC_PATH . '/uploads');
define('VIEWS_PATH', APP_PATH . '/views');
define('MODELS_PATH', APP_PATH . '/models');
define('CONTROLLERS_PATH', APP_PATH . '/controllers');

// Configuración de sesión
define('SESSION_LIFETIME', 7200); // 2 horas en segundos
define('SESSION_NAME', 'SIMPATIZANTES_SESSION');

// Configuración de seguridad
define('PASSWORD_MIN_LENGTH', 8);
define('MAX_LOGIN_ATTEMPTS', 3);
define('LOCKOUT_TIME', 30); // minutos

// Configuración de archivos
define('MAX_FILE_SIZE', 5242880); // 5MB en bytes
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/jpg']);

// Configuración de paginación
define('RECORDS_PER_PAGE', 25);

// Configuración de Email SMTP
define('SMTP_HOST', 'recaudabot.digital');
define('SMTP_PORT', 465);
define('SMTP_SECURE', 'ssl'); // ssl para puerto 465, tls para puerto 587
define('SMTP_USERNAME', 'resetpassword@recaudabot.digital');
defined('SMTP_PASSWORD') || define('SMTP_PASSWORD', getenv('SMTP_PASSWORD') ?: '');
define('SMTP_FROM_EMAIL', 'resetpassword@recaudabot.digital');
define('SMTP_FROM_NAME', 'Sistema de Simpatizantes');

// Activar compresión de salida
if (extension_loaded('zlib')) {
    ob_start('ob_gzhandler');
}
