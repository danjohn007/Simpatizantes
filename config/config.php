<?php
/**
 * Archivo de Configuración Principal
 * Sistema de Validación de Simpatizantes
 */

// Configuración de zona horaria
date_default_timezone_set('America/Mexico_City');

// Configuración de errores (cambiar a 0 en producción)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Configuración de Base de Datos
define('DB_HOST', 'localhost');
define('DB_NAME', 'simpatizantes_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Configuración de la Aplicación
define('APP_NAME', 'Sistema de Validación de Simpatizantes');
define('APP_VERSION', '1.0.0');

// Configuración de URL Base (se detecta automáticamente)
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$baseDir = str_replace('\\', '/', dirname($scriptName));
$baseDir = ($baseDir === '/' || $baseDir === '//') ? '' : $baseDir;

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

// Activar compresión de salida
if (extension_loaded('zlib')) {
    ob_start('ob_gzhandler');
}
