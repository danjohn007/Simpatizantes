<?php
/**
 * GET /public/api/app/me.php
 * Datos que la app guarda para trabajar sin conexión: usuario, campaña, metas vigentes,
 * secciones asignadas, catálogo de temas de colonia y (si el rol lo permite) campañas disponibles.
 */

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../../app/models/Campana.php';
require_once __DIR__ . '/../../../app/models/Campo.php';
require_once __DIR__ . '/../../../app/models/Simpatizante.php';

app_metodo('GET');
[$auth, $u] = app_autenticar();

$campo = new Campo();
$campanaModel = new Campana();
$campana = !empty($u['campana_id']) ? $campanaModel->obtenerPorId($u['campana_id']) : null;

$metas = [];
foreach ($campo->metas(['usuario_id' => (int)$u['id'], 'vigentes' => 1]) as $m) {
    $metas[] = [
        'id' => (int)$m['id'], 'objetivo' => (int)$m['objetivo'], 'registrados' => (int)$m['registrados'], 'validados' => (int)$m['validados'],
        'desde' => $m['fecha_inicio'], 'hasta' => $m['fecha_fin'], 'seccion' => $m['seccion_electoral'],
    ];
}

$campanas = [];
if ($auth->puedeVerTodasLasCampanas()) {
    foreach ($campanaModel->obtenerTodas(1) ?: [] as $c) { $campanas[] = ['id' => (int)$c['id'], 'nombre' => $c['nombre']]; }
}

$temas = [];
foreach (Simpatizante::TEMAS as $clave => $etiqueta) { $temas[] = ['clave' => $clave, 'etiqueta' => $etiqueta]; }

app_json(200, [
    'ok' => true,
    'usuario' => [
        'id' => (int)$u['id'], 'nombre' => $u['nombre_completo'], 'rol' => $u['rol'],
        'campana_id' => $u['campana_id'] ? (int)$u['campana_id'] : null, 'campana' => $campana['nombre'] ?? null,
    ],
    'metas' => $metas,
    'zonas' => $campo->seccionesDe((int)$u['id']),
    'temas' => $temas,
    'campanas' => $campanas,
    'version_minima' => app_version_minima(),
    'hora_servidor' => date('c'),
]);
