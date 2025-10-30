<?php
/**
 * Procesador de Cola de Emails
 * Este script se ejecuta en background para procesar emails
 */

// Solo permitir ejecución desde línea de comandos o localhost
if (php_sapi_name() !== 'cli' && 
    !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1', 'localhost'])) {
    http_response_code(403);
    exit('Acceso denegado');
}

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/app/services/EmailService.php';
require_once __DIR__ . '/app/services/EmailQueue.php';

// Configurar tiempo límite más alto para procesamiento
set_time_limit(60);

try {
    $emailQueue = new EmailQueue();
    
    // Procesar hasta 20 emails en esta ejecución
    $processed = $emailQueue->processQueue(20);
    
    if (php_sapi_name() === 'cli') {
        echo "Emails procesados: {$processed}\n";
    } else {
        echo json_encode(['processed' => $processed, 'status' => 'success']);
    }
    
    // Limpiar emails antiguos ocasionalmente (10% de probabilidad)
    if (rand(1, 10) === 1) {
        $cleaned = $emailQueue->cleanOldEmails(7);
        if (php_sapi_name() === 'cli') {
            echo "Emails antiguos limpiados: {$cleaned}\n";
        }
    }
    
} catch (Exception $e) {
    error_log("Error procesando cola de emails: " . $e->getMessage());
    
    if (php_sapi_name() === 'cli') {
        echo "Error: " . $e->getMessage() . "\n";
    } else {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage(), 'status' => 'error']);
    }
}
?>