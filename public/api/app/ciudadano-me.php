<?php
/**
 * GET  /public/api/app/ciudadano-me.php   → datos de la cuenta
 * POST /public/api/app/ciudadano-me.php   → { municipio?, colonia? } actualiza la zona de la persona (eventos y avisos)
 */

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../../app/models/Ciudadano.php';

[$c] = app_autenticar_ciudadano();
$modelo = new Ciudadano();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $in = app_cuerpo();
    $modelo->actualizarZona($c['id'], $in['municipio'] ?? '', $in['colonia'] ?? '');
} else {
    app_metodo('GET');
}
$d = $modelo->obtener($c['id']);
app_json(200, ['ok' => true, 'ciudadano' => [
    'id' => (int)$d['id'], 'nombre' => $d['nombre'], 'correo' => $d['correo'], 'municipio' => $d['municipio'], 'colonia' => $d['colonia'],
    'quiere_sumarse' => (bool)$d['quiere_sumarse'],
]]);
