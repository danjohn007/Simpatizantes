<?php
/**
 * Exportar Simpatizantes a CSV/Excel
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../app/controllers/AuthController.php';
require_once __DIR__ . '/../../app/models/Simpatizante.php';

$auth = new AuthController();
$auth->requiereAutenticacion();

$simpatizanteModel = new Simpatizante();

// Procesar filtros (los mismos que en index.php)
$filtros = [];

if (!empty($_GET['campana_id'])) {
    $filtros['campana_id'] = $_GET['campana_id'];
}

if (!empty($_GET['seccion'])) {
    $filtros['seccion_electoral'] = $_GET['seccion'];
}

if (!empty($_GET['busqueda'])) {
    $filtros['busqueda'] = $_GET['busqueda'];
}

if (!empty($_GET['fecha_inicio'])) {
    $filtros['fecha_inicio'] = $_GET['fecha_inicio'];
}

if (!empty($_GET['fecha_fin'])) {
    $filtros['fecha_fin'] = $_GET['fecha_fin'];
}

// Filtrar por capturista si es el rol actual
if ($auth->obtenerRol() === 'capturista') {
    $filtros['capturista_id'] = $auth->obtenerUsuarioId();
}

// Obtener todos los simpatizantes (sin paginación)
$db = Database::getInstance();
$where = [];
$params = [];

// Construir filtros
if (!empty($filtros['campana_id'])) {
    $where[] = "s.campana_id = ?";
    $params[] = $filtros['campana_id'];
}

if (!empty($filtros['capturista_id'])) {
    $where[] = "s.capturista_id = ?";
    $params[] = $filtros['capturista_id'];
}

if (!empty($filtros['seccion_electoral'])) {
    $where[] = "s.seccion_electoral = ?";
    $params[] = $filtros['seccion_electoral'];
}

if (!empty($filtros['fecha_inicio'])) {
    $where[] = "DATE(s.created_at) >= ?";
    $params[] = $filtros['fecha_inicio'];
}

if (!empty($filtros['fecha_fin'])) {
    $where[] = "DATE(s.created_at) <= ?";
    $params[] = $filtros['fecha_fin'];
}

if (!empty($filtros['busqueda'])) {
    $where[] = "(s.nombre_completo LIKE ? OR s.clave_elector LIKE ? OR s.curp LIKE ?)";
    $busqueda = '%' . $filtros['busqueda'] . '%';
    $params[] = $busqueda;
    $params[] = $busqueda;
    $params[] = $busqueda;
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "SELECT s.*, c.nombre as campana_nombre, u.nombre_completo as capturista_nombre 
        FROM simpatizantes s
        LEFT JOIN campanas c ON s.campana_id = c.id
        LEFT JOIN usuarios u ON s.capturista_id = u.id
        $whereClause
        ORDER BY s.created_at DESC";

$simpatizantes = $db->query($sql, $params);

// Configurar headers para descarga
$filename = 'simpatizantes_' . date('Y-m-d_His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Crear archivo CSV
$output = fopen('php://output', 'w');

// BOM para UTF-8
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Encabezados
$headers = [
    'ID',
    'Nombre Completo',
    'Domicilio',
    'Sexo',
    'Ciudad',
    'Clave Elector',
    'CURP',
    'Fecha Nacimiento',
    'Año Registro',
    'Vigencia',
    'Sección Electoral',
    'WhatsApp',
    'Email',
    'Twitter',
    'Instagram',
    'Facebook',
    'YouTube',
    'TikTok',
    'Latitud',
    'Longitud',
    'Campaña',
    'Capturista',
    'Método Captura',
    'Validado',
    'Fecha Registro'
];

fputcsv($output, $headers);

// Datos
foreach ($simpatizantes as $simp) {
    $row = [
        $simp['id'],
        $simp['nombre_completo'],
        $simp['domicilio_completo'],
        $simp['sexo'] ?? '',
        $simp['ciudad'] ?? '',
        $simp['clave_elector'] ?? '',
        $simp['curp'] ?? '',
        $simp['fecha_nacimiento'] ?? '',
        $simp['ano_registro'] ?? '',
        $simp['vigencia'] ?? '',
        $simp['seccion_electoral'],
        $simp['whatsapp'] ?? '',
        $simp['email'] ?? '',
        $simp['twitter'] ?? '',
        $simp['instagram'] ?? '',
        $simp['facebook'] ?? '',
        $simp['youtube'] ?? '',
        $simp['tiktok'] ?? '',
        $simp['latitud'] ?? '',
        $simp['longitud'] ?? '',
        $simp['campana_nombre'] ?? '',
        $simp['capturista_nombre'] ?? '',
        $simp['metodo_captura'],
        $simp['validado'] ? 'Sí' : 'No',
        date('d/m/Y H:i:s', strtotime($simp['created_at']))
    ];
    
    fputcsv($output, $row);
}

fclose($output);
exit;
