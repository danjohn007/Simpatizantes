<?php
/**
 * Reporte semanal por correo (tarea programada).
 *
 * Uso desde cPanel > Tareas Cron (por ejemplo, los lunes a las 7:00 a.m.):
 *     0 7 * * 1  php /ruta/al/proyecto/cron/reporte_semanal.php
 *
 * Manda en PDF la semana anterior (lunes a domingo): cada coordinador y candidato recibe el de su
 * campaña; los administradores, uno consolidado. Respeta el interruptor "Reporte semanal por correo"
 * de Configuración y no repite el envío si ya salió en los últimos 5 días.
 *
 * Opciones: --force (ignora el interruptor y la fecha del último envío), --dry (solo muestra a quién se enviaría).
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Solo se puede ejecutar desde la línea de comandos.');
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../app/models/LogAuditoria.php';
require_once __DIR__ . '/../app/services/ReporteSemanalService.php';

while (ob_get_level() > 0) { ob_end_flush(); }

$forzar = in_array('--force', $argv ?? [], true);
$simular = in_array('--dry', $argv ?? [], true);
$db = Database::getInstance();

$valor = function ($clave) use ($db) {
    $r = $db->queryOne("SELECT valor FROM configuracion WHERE clave = ?", [$clave]);
    return $r ? $r['valor'] : null;
};

if (!$forzar && $valor('reporte_semanal_activo') !== 'true') {
    echo "El reporte semanal está desactivado en Configuración. No se hizo nada.\n";
    exit(0);
}
$ultimo = $valor('reporte_semanal_ultimo');
if (!$forzar && $ultimo && strtotime($ultimo) > strtotime('-5 days')) {
    echo "Ya se envió el $ultimo. No se repite.\n";
    exit(0);
}

$hasta = date('Y-m-d', strtotime('yesterday'));
$grupos = ReporteSemanalService::grupos();
if (!$grupos) {
    echo "No hay destinatarios con correo válido (coordinadores, candidatos o administradores activos).\n";
    exit(0);
}

$totalEnviados = 0;
$totalFallidos = 0;
foreach ($grupos as $g) {
    echo '· ' . $g['nombre'] . ' → ' . count($g['usuarios']) . " destinatario(s)\n";
    if ($simular) {
        foreach ($g['usuarios'] as $u) { echo '    ' . $u['email'] . "\n"; }
        continue;
    }
    try {
        $r = ReporteSemanalService::enviarGrupo($g, $hasta);
        $totalEnviados += $r['enviados'];
        $totalFallidos += count($r['fallidos']);
        echo "    enviados: {$r['enviados']}" . ($r['fallidos'] ? ', fallidos: ' . implode(', ', $r['fallidos']) : '') . "\n";
    } catch (Throwable $e) {
        error_log('Reporte semanal (' . $g['nombre'] . '): ' . $e->getMessage());
        $totalFallidos += count($g['usuarios']);
        echo '    ERROR: ' . $e->getMessage() . "\n";
    }
}

if (!$simular) {
    if ($totalEnviados > 0) {
        $existe = $db->queryOne("SELECT id FROM configuracion WHERE clave = 'reporte_semanal_ultimo'");
        if ($existe) {
            $db->execute("UPDATE configuracion SET valor = ? WHERE clave = 'reporte_semanal_ultimo'", [date('Y-m-d')]);
        } else {
            $db->execute("INSERT INTO configuracion (clave, valor, descripcion, tipo) VALUES ('reporte_semanal_ultimo', ?, 'Fecha del último reporte semanal enviado', 'texto')", [date('Y-m-d')]);
        }
    }
    (new LogAuditoria())->registrar(null, 'reporte_semanal_enviado', 'configuracion', null, null, ['periodo_hasta' => $hasta, 'enviados' => $totalEnviados, 'fallidos' => $totalFallidos]);
    echo "Listo: $totalEnviados enviados, $totalFallidos fallidos.\n";
}
exit($totalFallidos > 0 && $totalEnviados === 0 ? 1 : 0);
