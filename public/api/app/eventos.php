<?php
/**
 * GET  eventos.php  → próximos eventos y avisos para la zona de la persona
 * POST eventos.php  { evento_id } → confirma o cancela la asistencia (devuelve `asisto`)
 */

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../../app/models/Evento.php';

[$c] = app_autenticar_ciudadano();
$modelo = new Evento();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $in = app_cuerpo();
    $asiste = $modelo->alternarAsistencia((int)($in['evento_id'] ?? 0), (int)$c['id']);
    if ($asiste === null) {
        app_json(404, ['ok' => false, 'error' => 'Ese evento ya no está disponible.']);
    }
    app_json(200, ['ok' => true, 'asisto' => $asiste]);
}

app_metodo('GET');
$eventos = array_map(function ($e) {
    return [
        'id' => (int)$e['id'], 'titulo' => $e['titulo'], 'descripcion' => $e['descripcion'], 'lugar' => $e['lugar'],
        'inicio' => $e['fecha_inicio'], 'fin' => $e['fecha_fin'], 'municipio' => $e['municipio'], 'colonia' => $e['colonia'],
        'asistentes' => (int)$e['asistentes'], 'asisto' => (bool)$e['asisto'],
    ];
}, $modelo->proximos($c['municipio'], $c['colonia'], (int)$c['id']));
$avisos = array_map(function ($a) {
    return ['id' => (int)$a['id'], 'titulo' => $a['titulo'], 'texto' => $a['texto'], 'fecha' => $a['created_at']];
}, $modelo->avisosVigentes($c['municipio'], $c['colonia']));
app_json(200, ['ok' => true, 'eventos' => $eventos, 'avisos' => $avisos]);
