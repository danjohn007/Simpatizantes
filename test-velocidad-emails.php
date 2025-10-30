<?php
/**
 * Prueba de Velocidad del Sistema de Emails
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/app/services/EmailService.php';
require_once __DIR__ . '/app/services/EmailQueue.php';

echo "<h1>🚀 Prueba de Velocidad - Sistema de Emails</h1>";
echo "<style>body{font-family:Arial;} .result{padding:10px;margin:5px 0;border-radius:5px;} .fast{background:#d4edda;} .slow{background:#f8d7da;} .medium{background:#fff3cd;}</style>";

// Función para medir tiempo
function measureTime($callback, $description) {
    echo "<h3>🔍 {$description}</h3>";
    
    $start = microtime(true);
    $result = $callback();
    $end = microtime(true);
    
    $time = round(($end - $start) * 1000, 2); // En milisegundos
    
    $class = 'medium';
    if ($time < 1000) $class = 'fast';
    elseif ($time > 5000) $class = 'slow';
    
    echo "<div class='result {$class}'>";
    echo "<strong>⏱️ Tiempo:</strong> {$time}ms ";
    
    if ($time < 500) echo "⚡ ¡Súper rápido!";
    elseif ($time < 1000) echo "✅ Rápido";
    elseif ($time < 3000) echo "⚠️ Aceptable";
    else echo "🐌 Lento";
    
    echo "<br><strong>Resultado:</strong> " . ($result ? '✅ Éxito' : '❌ Error');
    echo "</div>";
    
    return $time;
}

try {
    $db = Database::getInstance();
    $emailService = new EmailService();
    
    // Obtener un usuario de prueba
    $usuario = $db->queryOne("SELECT email, nombre_completo FROM usuarios WHERE activo = 1 LIMIT 1");
    
    if (!$usuario) {
        echo "<div class='result slow'>❌ No hay usuarios activos para prueba</div>";
        exit;
    }
    
    echo "<p><strong>Usuario de prueba:</strong> {$usuario['email']}</p>";
    
    // Prueba 1: Envío normal (método anterior)
    $tiempo1 = measureTime(function() use ($emailService, $usuario) {
        return $emailService->sendEmail(
            $usuario['email'],
            "Prueba de velocidad - Normal",
            "<h1>Email de prueba normal</h1><p>Este es un email de prueba del método normal.</p>",
            $usuario['nombre_completo']
        );
    }, "Método Normal (sendEmail)");
    
    // Prueba 2: Envío rápido
    $tiempo2 = measureTime(function() use ($emailService, $usuario) {
        return $emailService->sendEmailFast(
            $usuario['email'],
            "Prueba de velocidad - Rápido",
            "<h1>Email de prueba rápido</h1><p>Este es un email de prueba del método rápido.</p>",
            $usuario['nombre_completo']
        );
    }, "Método Rápido (sendEmailFast)");
    
    // Prueba 3: Sistema de cola
    $tiempo3 = measureTime(function() use ($usuario) {
        try {
            $queue = new EmailQueue();
            return $queue->queueEmail(
                $usuario['email'],
                "Prueba de velocidad - Cola",
                "<h1>Email de prueba con cola</h1><p>Este es un email de prueba usando cola.</p>",
                $usuario['nombre_completo'],
                1
            );
        } catch (Exception $e) {
            error_log("Error en cola: " . $e->getMessage());
            return false;
        }
    }, "Sistema de Cola (queueEmail)");
    
    // Prueba 4: Método optimizado de recuperación
    $tiempo4 = measureTime(function() use ($emailService, $usuario) {
        return $emailService->sendPasswordRecoveryEmail(
            $usuario['email'],
            $usuario['nombre_completo'],
            'test_token_' . time()
        );
    }, "Recuperación Optimizada (sendPasswordRecoveryEmail)");
    
    // Resumen
    echo "<h2>📊 Resumen de Velocidad</h2>";
    
    $metodos = [
        'Normal' => $tiempo1,
        'Rápido' => $tiempo2,
        'Cola' => $tiempo3,
        'Recuperación' => $tiempo4
    ];
    
    asort($metodos);
    
    echo "<table style='width:100%;border-collapse:collapse;'>";
    echo "<tr style='background:#f8f9fa;'><th style='padding:10px;border:1px solid #ddd;'>Posición</th><th style='padding:10px;border:1px solid #ddd;'>Método</th><th style='padding:10px;border:1px solid #ddd;'>Tiempo (ms)</th><th style='padding:10px;border:1px solid #ddd;'>Evaluación</th></tr>";
    
    $posicion = 1;
    foreach ($metodos as $metodo => $tiempo) {
        $evaluacion = $tiempo < 500 ? "⚡ Excelente" : 
                     ($tiempo < 1000 ? "✅ Bueno" : 
                     ($tiempo < 3000 ? "⚠️ Regular" : "🐌 Lento"));
                     
        $color = $tiempo < 1000 ? "#d4edda" : ($tiempo < 3000 ? "#fff3cd" : "#f8d7da");
        
        echo "<tr style='background:{$color};'>";
        echo "<td style='padding:10px;border:1px solid #ddd;text-align:center;'><strong>{$posicion}</strong></td>";
        echo "<td style='padding:10px;border:1px solid #ddd;'>{$metodo}</td>";
        echo "<td style='padding:10px;border:1px solid #ddd;text-align:center;'><strong>{$tiempo}ms</strong></td>";
        echo "<td style='padding:10px;border:1px solid #ddd;'>{$evaluacion}</td>";
        echo "</tr>";
        $posicion++;
    }
    echo "</table>";
    
    // Recomendaciones
    echo "<h2>💡 Recomendaciones</h2>";
    
    $metodoMasRapido = array_key_first($metodos);
    $tiempoMasRapido = reset($metodos);
    
    echo "<div class='result fast'>";
    echo "<strong>🏆 Método más rápido:</strong> {$metodoMasRapido} ({$tiempoMasRapido}ms)";
    echo "</div>";
    
    if ($tiempoMasRapido < 1000) {
        echo "<div class='result fast'>✅ ¡Excelente! El sistema está optimizado correctamente.</div>";
    } elseif ($tiempoMasRapido < 3000) {
        echo "<div class='result medium'>⚠️ El sistema funciona bien, pero se puede optimizar más.</div>";
    } else {
        echo "<div class='result slow'>🐌 El sistema necesita optimización. Revisar configuración SMTP.</div>";
    }
    
    // Verificar cola de emails
    echo "<h2>📋 Estado de la Cola</h2>";
    try {
        $queue = new EmailQueue();
        $stats = $queue->getQueueStats();
        
        echo "<div class='result medium'>";
        echo "<strong>Pendientes:</strong> " . ($stats['pending'] ?? 0) . " | ";
        echo "<strong>Procesando:</strong> " . ($stats['processing'] ?? 0) . " | ";
        echo "<strong>Enviados:</strong> " . ($stats['sent'] ?? 0) . " | ";
        echo "<strong>Fallidos:</strong> " . ($stats['failed'] ?? 0);
        echo "</div>";
        
        if (($stats['pending'] ?? 0) > 0) {
            echo "<div class='result medium'>";
            echo "📨 Hay emails pendientes en la cola. ";
            echo "<a href='" . BASE_URL . "/process_email_queue.php'>Procesar ahora</a> | ";
            echo "<a href='" . BASE_URL . "/monitor-email-queue.php'>Ver monitor</a>";
            echo "</div>";
        }
        
    } catch (Exception $e) {
        echo "<div class='result slow'>❌ Error accediendo a la cola: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
    
} catch (Exception $e) {
    echo "<div class='result slow'>❌ Error en la prueba: " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "<hr>";
echo "<p><strong>🔗 Enlaces útiles:</strong></p>";
echo "<ul>";
echo "<li><a href='" . BASE_URL . "/public/recuperar-password.php'>Probar recuperación de contraseña</a></li>";
echo "<li><a href='" . BASE_URL . "/monitor-email-queue.php'>Monitor de cola de emails</a></li>";
echo "<li><a href='" . BASE_URL . "/debug-tokens.php'>Debug de tokens</a></li>";
echo "</ul>";
?>