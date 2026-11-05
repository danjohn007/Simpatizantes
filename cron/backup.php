<?php
/**
 * Respaldo automático (tarea programada).
 *
 * Uso desde cPanel > Tareas Cron (por ejemplo, todos los días a las 2:30 a.m.):
 *     30 2 * * *  php /ruta/al/proyecto/cron/backup.php
 *
 * Respeta el interruptor "Respaldos automáticos" de Configuración.
 * Con el argumento --force se ejecuta aunque el interruptor esté apagado.
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Solo se puede ejecutar desde la línea de comandos.');
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../app/services/BackupService.php';

// config.php activa compresión de salida para la web; en consola no se necesita
while (ob_get_level() > 0) { ob_end_flush(); }

$forzar = in_array('--force', $argv ?? [], true);

$cfg = Database::getInstance()->queryOne("SELECT valor FROM configuracion WHERE clave = 'backup_automatico'");
if (!$forzar && (!$cfg || $cfg['valor'] !== 'true')) {
    echo "Respaldos automáticos desactivados en Configuración. No se hizo nada.\n";
    exit(0);
}

try {
    $r = BackupService::crear();
    echo 'Base de datos: ' . $r['base_datos']['archivo'] . ' (' . BackupService::formatoTamano($r['base_datos']['tam']) . ', ' . $r['base_datos']['tablas'] . " tablas)\n";
    if ($r['archivos']) {
        echo 'Archivos: ' . $r['archivos']['archivo'] . ' (' . BackupService::formatoTamano($r['archivos']['tam']) . ', ' . $r['archivos']['archivos'] . " archivos)\n";
    }
    foreach ($r['avisos'] as $a) { echo "Aviso: $a\n"; }
    exit(0);
} catch (Throwable $e) {
    error_log('Fallo en el respaldo automático: ' . $e->getMessage());
    fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
    exit(1);
}
