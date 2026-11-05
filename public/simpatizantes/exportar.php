<?php
/**
 * Exportar simpatizantes a Excel (.xlsx) o CSV, con los mismos filtros del listado.
 *
 * Permisos: admin y super admin exportan todo; coordinador y candidato, solo su campaña;
 * capturista, solo lo que él capturó. Cada exportación queda en la auditoría.
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../app/controllers/AuthController.php';
require_once __DIR__ . '/../../app/models/Simpatizante.php';
require_once __DIR__ . '/../../app/models/LogAuditoria.php';
require_once __DIR__ . '/../../app/services/HojaCalculoService.php';

$auth = new AuthController();
$auth->requiereAutenticacion();

$modelo = new Simpatizante();

// Filtros pedidos por el usuario (mismos nombres que en el listado)
$filtros = [];
foreach (['seccion' => 'seccion_electoral', 'busqueda' => 'busqueda', 'fecha_inicio' => 'fecha_inicio', 'fecha_fin' => 'fecha_fin'] as $get => $campo) {
    if (isset($_GET[$get]) && is_string($_GET[$get]) && $_GET[$get] !== '') {
        $filtros[$campo] = $_GET[$get];
    }
}
if (isset($_GET['estatus']) && in_array($_GET['estatus'], ['pendiente', 'validado', 'rechazado'], true)) {
    $filtros['estatus'] = $_GET['estatus'];
}

// Alcance según el rol (no depende de lo que mande el navegador)
if ($auth->puedeVerTodasLasCampanas()) {
    if (!empty($_GET['campana_id']) && ctype_digit((string)$_GET['campana_id'])) {
        $filtros['campana_id'] = (int)$_GET['campana_id'];
    }
} else {
    $campana = $auth->obtenerCampanaId();
    if (!$campana && $auth->obtenerRol() !== 'capturista') {
        http_response_code(403);
        exit('Su usuario no tiene una campaña asignada.');
    }
    if ($campana) {
        $filtros['campana_id'] = (int)$campana;
    }
}
if ($auth->obtenerRol() === 'capturista') {
    $filtros['capturista_id'] = (int)$auth->obtenerUsuarioId();
}

@set_time_limit(180);
$simpatizantes = $modelo->obtenerParaExportar($filtros);
if ($simpatizantes === false) {
    http_response_code(500);
    exit('No se pudo generar la exportación.');
}

$formato = (($_GET['formato'] ?? 'xlsx') === 'csv') ? 'csv' : 'xlsx';
$etiquetas = ['pendiente' => 'Pendiente', 'validado' => 'Validado', 'rechazado' => 'Rechazado'];

$filas = [[
    'ID', 'Nombre completo', 'Domicilio', 'Sexo', 'Ciudad', 'Clave de elector', 'CURP', 'Fecha de nacimiento',
    'Año de registro', 'Vigencia', 'Sección electoral', 'Teléfono', 'WhatsApp', 'Correo', 'Facebook', 'Instagram',
    'X / Twitter', 'TikTok', 'YouTube', 'Latitud', 'Longitud', 'Campaña', 'Capturista', 'Método de captura',
    'Estado de validación', 'Motivo de rechazo', 'Observaciones', 'Fecha de registro'
]];

foreach ($simpatizantes as $s) {
    $estatus = $s['estatus_validacion'] ?? (!empty($s['validado']) ? 'validado' : 'pendiente');
    $filas[] = [
        $s['id'],
        $s['nombre_completo'],
        $s['domicilio_completo'],
        $s['sexo'] ?? '',
        $s['ciudad'] ?? '',
        $s['clave_elector'] ?? '',
        $s['curp'] ?? '',
        !empty($s['fecha_nacimiento']) ? date('d/m/Y', strtotime($s['fecha_nacimiento'])) : '',
        $s['ano_registro'] ?? '',
        $s['vigencia'] ?? '',
        $s['seccion_electoral'],
        $s['telefono'] ?? '',
        $s['whatsapp'] ?? '',
        $s['email'] ?? '',
        $s['facebook'] ?? '',
        $s['instagram'] ?? '',
        $s['twitter'] ?? '',
        $s['tiktok'] ?? '',
        $s['youtube'] ?? '',
        $s['latitud'] ?? '',
        $s['longitud'] ?? '',
        $s['campana_nombre'] ?? '',
        $s['capturista_nombre'] ?? '',
        $s['metodo_captura'] ?? '',
        $etiquetas[$estatus] ?? 'Pendiente',
        $s['motivo_rechazo'] ?? '',
        $s['observaciones'] ?? '',
        !empty($s['created_at']) ? date('d/m/Y H:i', strtotime($s['created_at'])) : '',
    ];
}

(new LogAuditoria())->registrar(
    $auth->obtenerUsuarioId(), 'exportar_simpatizantes', 'simpatizantes', null, null,
    ['formato' => $formato, 'registros' => count($simpatizantes), 'filtros' => $filtros]
);

$nombre = 'simpatizantes_' . date('Y-m-d_His') . '.' . $formato;
try {
    if ($formato === 'csv') {
        HojaCalculoService::escribirCsv($filas, $nombre);
    } else {
        HojaCalculoService::enviarXlsx($filas, $nombre, 'Simpatizantes');
    }
} catch (RuntimeException $e) {
    http_response_code(500);
    exit('No se pudo generar el archivo: ' . $e->getMessage() . ' Pruebe con el formato CSV.');
}
exit;
