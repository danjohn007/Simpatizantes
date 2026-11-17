<?php
/**
 * Visor protegido de archivos subidos (INE, firmas y fotos de reportes ciudadanos).
 *
 * Reglas de acceso:
 *  - super_admin y admin: cualquier archivo.
 *  - capturista: solo los de simpatizantes que él mismo capturó.
 *  - coordinador y candidato: solo los de simpatizantes de su campaña.
 *  - Un archivo que no pertenece a ningún simpatizante solo lo ven super_admin y admin.
 *  - Fotos de reportes ciudadanos (uploads/reportes/): super_admin, admin y coordinador.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../app/controllers/AuthController.php';

$auth = new AuthController();
$auth->requiereAutenticacion();

function archivo_denegar($codigo = 404) {
    http_response_code($codigo);
    exit;
}

$f = $_GET['f'] ?? '';
// Solo rutas del tipo uploads/<carpeta>/<archivo>
if (!is_string($f) || !preg_match('#^uploads/[A-Za-z0-9_]+/[A-Za-z0-9_.-]+$#', $f)) {
    archivo_denegar();
}

$base = realpath(UPLOAD_PATH);
$ruta = realpath(PUBLIC_PATH . '/' . $f);
if ($base === false || $ruta === false || strpos($ruta, $base . DIRECTORY_SEPARATOR) !== 0 || !is_file($ruta)) {
    archivo_denegar();
}

// ---- Fotos de reportes ciudadanos: las ve el equipo de atención (super admin, admin y coordinador) ----
$rol = $auth->obtenerRol();
if (strpos($f, 'uploads/reportes/') === 0) {
    if (!in_array($rol, ['super_admin', 'admin', 'coordinador'], true)) { archivo_denegar(403); }
    if (!Database::getInstance()->queryOne("SELECT id FROM reporte_archivos WHERE ruta = ? LIMIT 1", [$f])) { archivo_denegar(404); }
} elseif (!in_array($rol, ['super_admin', 'admin'], true)) {
    // ---- Autorización según el simpatizante al que pertenece el archivo ----
    $dueno = Database::getInstance()->queryOne(
        "SELECT capturista_id, campana_id FROM simpatizantes
         WHERE ine_frontal = ? OR ine_posterior = ? OR firma_digital = ? LIMIT 1",
        [$f, $f, $f]
    );

    if (!$dueno) {
        archivo_denegar(403);
    }
    if ($rol === 'capturista') {
        if ((int)$dueno['capturista_id'] !== (int)$auth->obtenerUsuarioId()) { archivo_denegar(403); }
    } else {
        $miCampana = $auth->obtenerCampanaId();
        if (empty($miCampana) || (int)$dueno['campana_id'] !== (int)$miCampana) { archivo_denegar(403); }
    }
}

$mime = (new finfo(FILEINFO_MIME_TYPE))->file($ruta);
if (!in_array($mime, ['image/jpeg', 'image/png'], true)) {
    archivo_denegar();
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($ruta));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=3600');
readfile($ruta);
