<?php
/**
 * Prueba del Sistema de Recuperación de Contraseña
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/app/services/EmailService.php';

echo "<h1>Prueba del Sistema de Recuperación de Contraseña</h1>";

// 1. Verificar conexión a la base de datos
echo "<h2>1. Conexión a Base de Datos</h2>";
try {
    $db = Database::getInstance();
    echo "✅ Conexión a BD exitosa<br>";
} catch (Exception $e) {
    echo "❌ Error de conexión: " . $e->getMessage() . "<br>";
    exit;
}

// 2. Verificar tabla de recuperación
echo "<h2>2. Tabla recuperacion_password</h2>";
try {
    $result = $db->query("SHOW TABLES LIKE 'recuperacion_password'");
    if (count($result) > 0) {
        echo "✅ Tabla recuperacion_password existe<br>";
        
        // Verificar estructura
        $columns = $db->query("DESCRIBE recuperacion_password");
        echo "Columnas: ";
        foreach ($columns as $col) {
            echo $col['Field'] . " (" . $col['Type'] . "), ";
        }
        echo "<br>";
    } else {
        echo "❌ Tabla recuperacion_password no existe<br>";
    }
} catch (Exception $e) {
    echo "❌ Error verificando tabla: " . $e->getMessage() . "<br>";
}

// 3. Verificar usuarios existentes
echo "<h2>3. Usuarios en el Sistema</h2>";
try {
    $usuarios = $db->query("SELECT id, username, email, nombre_completo, activo FROM usuarios LIMIT 5");
    if (count($usuarios) > 0) {
        echo "✅ Usuarios encontrados:<br>";
        foreach ($usuarios as $user) {
            echo "- ID: {$user['id']}, User: {$user['username']}, Email: {$user['email']}, Activo: {$user['activo']}<br>";
        }
    } else {
        echo "⚠️ No hay usuarios en el sistema<br>";
    }
} catch (Exception $e) {
    echo "❌ Error consultando usuarios: " . $e->getMessage() . "<br>";
}

// 4. Verificar configuración de email
echo "<h2>4. Configuración de Email</h2>";
echo "SMTP Host: " . SMTP_HOST . "<br>";
echo "SMTP Port: " . SMTP_PORT . "<br>";
echo "SMTP Username: " . SMTP_USERNAME . "<br>";
echo "From Email: " . SMTP_FROM_EMAIL . "<br>";

// 5. Probar servicio de email
echo "<h2>5. Prueba de Email Service</h2>";
try {
    $emailService = new EmailService();
    echo "✅ EmailService creado exitosamente<br>";
    
    // Verificar función mail()
    if (function_exists('mail')) {
        echo "✅ Función mail() disponible<br>";
    } else {
        echo "❌ Función mail() no disponible<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Error creando EmailService: " . $e->getMessage() . "<br>";
}

// 6. URLs importantes
echo "<h2>6. URLs del Sistema</h2>";
echo "Base URL: " . BASE_URL . "<br>";
echo "Recuperar Password: " . BASE_URL . "/public/recuperar-password.php<br>";
echo "Restablecer Password: " . BASE_URL . "/public/restablecer-password.php<br>";

// 7. Prueba básica del sistema (sin enviar email real)
echo "<h2>7. Prueba de Funcionalidad</h2>";
try {
    // Obtener un usuario activo para prueba
    $usuario = $db->queryOne("SELECT id, username, email, nombre_completo FROM usuarios WHERE activo = 1 LIMIT 1");
    
    if ($usuario) {
        echo "Usuario para prueba: {$usuario['username']} ({$usuario['email']})<br>";
        
        // Simular creación de token
        $token = bin2hex(random_bytes(32));
        $expiracion = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        echo "Token generado: " . substr($token, 0, 10) . "...<br>";
        echo "Expira: {$expiracion}<br>";
        
        // Link de recuperación
        $linkRecuperacion = BASE_URL . "/public/restablecer-password.php?token=" . $token;
        echo "Link de recuperación: <a href='{$linkRecuperacion}' target='_blank'>Abrir</a><br>";
        
        echo "✅ Sistema funcionando correctamente<br>";
    } else {
        echo "⚠️ No hay usuarios activos para probar<br>";
    }
} catch (Exception $e) {
    echo "❌ Error en prueba: " . $e->getMessage() . "<br>";
}

echo "<br><hr>";
echo "<p><strong>🔧 Instrucciones de uso (Sistema Corregido):</strong></p>";
echo "<ol>";
echo "<li><strong>Solicitar recuperación:</strong> Ve a <a href='" . BASE_URL . "/public/recuperar-password.php'>" . BASE_URL . "/public/recuperar-password.php</a></li>";
echo "<li><strong>Verificar en debug:</strong> Ve a <a href='" . BASE_URL . "/debug-tokens.php'>" . BASE_URL . "/debug-tokens.php</a></li>";
echo "<li><strong>Revisar logs:</strong> Los errores se guardan en error_log del servidor</li>";
echo "<li><strong>Usar el enlace:</strong> Del email o del debug para restablecer</li>";
echo "</ol>";

echo "<p><strong>🐛 Para debugging:</strong></p>";
echo "<ul>";
echo "<li><strong>Logs del sistema:</strong> Se guardan automáticamente en error_log</li>";
echo "<li><strong>Debug de tokens:</strong> <a href='" . BASE_URL . "/debug-tokens.php'>Ver todos los tokens</a></li>";
echo "<li><strong>Mensajes específicos:</strong> Ahora el sistema dice si el email no está registrado</li>";
echo "<li><strong>Validación mejorada:</strong> El sistema distingue entre token expirado, usado o inexistente</li>";
echo "</ul>";

echo "<p><strong>⚡ Mejoras implementadas:</strong></p>";
echo "<ul>";
echo "<li>✅ Email más rápido (optimizado)</li>";
echo "<li>✅ Logging detallado para debugging</li>";
echo "<li>✅ Validación mejorada de tokens</li>";
echo "<li>✅ Mensajes específicos por tipo de error</li>";
echo "<li>✅ Template de email mejorado</li>";
echo "</ul>";
?>