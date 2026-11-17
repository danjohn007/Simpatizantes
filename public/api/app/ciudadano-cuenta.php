<?php
/**
 * POST /public/api/app/ciudadano-cuenta.php
 * Cuerpo JSON: { accion: "sumarme" | "eliminar" }
 *  - sumarme:  la persona pide sumarse como simpatizante; el equipo la ve en su panel.
 *  - eliminar: borra la cuenta y sus datos personales (los reportes enviados quedan sin su nombre).
 */

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../../app/models/Ciudadano.php';

app_metodo('POST');
[$c] = app_autenticar_ciudadano();
$in = app_cuerpo();
$modelo = new Ciudadano();

switch ($in['accion'] ?? '') {
    case 'sumarme':
        $modelo->solicitarSumarse($c['id']);
        app_json(200, ['ok' => true]);
        break;
    case 'eliminar':
        if (!$modelo->eliminar($c['id'])) {
            app_json(503, ['ok' => false, 'error' => 'No se pudo eliminar la cuenta. Intente de nuevo.']);
        }
        app_json(200, ['ok' => true]);
        break;
    default:
        app_json(422, ['ok' => false, 'error' => 'Acción no válida.']);
}
