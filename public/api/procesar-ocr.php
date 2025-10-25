<?php
/**
 * Endpoint para procesar OCR de INE
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../app/controllers/AuthController.php';
require_once __DIR__ . '/../../app/controllers/SimpatizanteController.php';
require_once __DIR__ . '/../../app/services/OCRService.php';

header('Content-Type: application/json');

$auth = new AuthController();
$auth->requiereAutenticacion();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

// Verificar que se subió un archivo
if (!isset($_FILES['ine_imagen']) || $_FILES['ine_imagen']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['error' => 'No se recibió imagen o hubo un error al subirla']);
    exit;
}

$simpatizanteController = new SimpatizanteController();
$ocrService = new OCRService();

// Verificar si OCR está disponible
if (!$ocrService->estaDisponible()) {
    echo json_encode([
        'error' => 'Servicio OCR no disponible',
        'mensaje' => 'El servicio OCR no está configurado. Contacte al administrador.'
    ]);
    exit;
}

// Procesar archivo temporalmente
$resultado = $simpatizanteController->procesarArchivo($_FILES['ine_imagen'], 'temp_ocr');

if (isset($resultado['error'])) {
    echo json_encode(['error' => $resultado['error']]);
    exit;
}

// Obtener ruta completa del archivo
$rutaArchivo = UPLOAD_PATH . '/' . str_replace('uploads/', '', $resultado['archivo']);

// Procesar con OCR
$resultadoOCR = $ocrService->procesarINE($rutaArchivo);

// Eliminar archivo temporal
if (file_exists($rutaArchivo)) {
    unlink($rutaArchivo);
}

if (isset($resultadoOCR['error'])) {
    echo json_encode(['error' => $resultadoOCR['error']]);
    exit;
}

// Retornar datos extraídos
echo json_encode([
    'success' => true,
    'datos' => $resultadoOCR['datos']
]);
