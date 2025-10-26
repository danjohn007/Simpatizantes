<?php
/**
 * Test para verificar que la columna observaciones existe
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/Database.php';

try {
    $db = Database::getInstance();
    
    // Verificar si la columna observaciones existe
    $result = $db->query("SHOW COLUMNS FROM simpatizantes LIKE 'observaciones'");
    
    if (!empty($result)) {
        echo "✓ ÉXITO: La columna 'observaciones' existe en la tabla simpatizantes\n";
        echo "Detalles de la columna:\n";
        print_r($result[0]);
        exit(0);
    } else {
        echo "✗ ERROR: La columna 'observaciones' NO existe en la tabla simpatizantes\n";
        echo "Ejecuta el archivo de migración: database/add_observaciones_column.sql\n";
        exit(1);
    }
    
} catch (Exception $e) {
    echo "✗ ERROR al verificar: " . $e->getMessage() . "\n";
    exit(1);
}
