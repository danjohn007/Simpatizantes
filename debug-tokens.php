<?php
/**
 * Debug de Tokens de Recuperación
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/Database.php';

echo "<h1>Debug - Tokens de Recuperación</h1>";
echo "<style>body{font-family:Arial;} table{border-collapse:collapse;width:100%;} th,td{border:1px solid #ddd;padding:8px;text-align:left;} th{background:#f2f2f2;} .expired{background:#ffebee;} .used{background:#e8f5e8;} .valid{background:#fff3e0;}</style>";

try {
    $db = Database::getInstance();
    
    // Obtener todos los tokens
    echo "<h2>Tokens en la Base de Datos</h2>";
    $sql = "SELECT r.id, r.usuario_id, r.token, r.expiracion, r.usado, r.created_at, 
                   u.username, u.email, u.nombre_completo
            FROM recuperacion_password r 
            INNER JOIN usuarios u ON r.usuario_id = u.id 
            ORDER BY r.created_at DESC 
            LIMIT 20";
    
    $tokens = $db->query($sql);
    
    if (count($tokens) > 0) {
        echo "<table>";
        echo "<tr><th>ID</th><th>Usuario</th><th>Email</th><th>Token (10 chars)</th><th>Creado</th><th>Expira</th><th>Estado</th><th>Usado</th></tr>";
        
        foreach ($tokens as $token) {
            $now = time();
            $expiracion = strtotime($token['expiracion']);
            $estado = '';
            $clase = '';
            
            if ($token['usado'] == 1) {
                $estado = 'USADO';
                $clase = 'used';
            } elseif ($expiracion <= $now) {
                $estado = 'EXPIRADO';
                $clase = 'expired';
            } else {
                $estado = 'VÁLIDO';
                $clase = 'valid';
            }
            
            echo "<tr class='{$clase}'>";
            echo "<td>{$token['id']}</td>";
            echo "<td>{$token['username']}</td>";
            echo "<td>{$token['email']}</td>";
            echo "<td>" . substr($token['token'], 0, 10) . "...</td>";
            echo "<td>{$token['created_at']}</td>";
            echo "<td>{$token['expiracion']}</td>";
            echo "<td><strong>{$estado}</strong></td>";
            echo "<td>" . ($token['usado'] ? 'Sí' : 'No') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Estadísticas
        echo "<h3>Estadísticas</h3>";
        $stats = $db->query("SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN usado = 1 THEN 1 ELSE 0 END) as usados,
            SUM(CASE WHEN expiracion <= NOW() AND usado = 0 THEN 1 ELSE 0 END) as expirados,
            SUM(CASE WHEN expiracion > NOW() AND usado = 0 THEN 1 ELSE 0 END) as validos
            FROM recuperacion_password")[0];
        
        echo "<p><strong>Total tokens:</strong> {$stats['total']}</p>";
        echo "<p><strong>Tokens usados:</strong> {$stats['usados']}</p>";
        echo "<p><strong>Tokens expirados:</strong> {$stats['expirados']}</p>";
        echo "<p><strong>Tokens válidos:</strong> {$stats['validos']}</p>";
        
    } else {
        echo "<p>No hay tokens en la base de datos.</p>";
    }
    
    // Probar token específico si se proporciona
    if (isset($_GET['token'])) {
        $testToken = $_GET['token'];
        echo "<h2>Verificar Token Específico</h2>";
        echo "<p><strong>Token a verificar:</strong> " . htmlspecialchars($testToken) . "</p>";
        
        $sql = "SELECT r.id, r.usuario_id, r.expiracion, r.usado, r.created_at, 
                       u.username, u.email, u.nombre_completo 
                FROM recuperacion_password r 
                INNER JOIN usuarios u ON r.usuario_id = u.id 
                WHERE r.token = ?";
        
        $resultado = $db->queryOne($sql, [$testToken]);
        
        if (!$resultado) {
            echo "<p style='color:red;'><strong>❌ Token NO encontrado en la base de datos</strong></p>";
        } else {
            echo "<table>";
            echo "<tr><th>Campo</th><th>Valor</th></tr>";
            echo "<tr><td>ID</td><td>{$resultado['id']}</td></tr>";
            echo "<tr><td>Usuario</td><td>{$resultado['username']}</td></tr>";
            echo "<tr><td>Email</td><td>{$resultado['email']}</td></tr>";
            echo "<tr><td>Creado</td><td>{$resultado['created_at']}</td></tr>";
            echo "<tr><td>Expira</td><td>{$resultado['expiracion']}</td></tr>";
            echo "<tr><td>Usado</td><td>" . ($resultado['usado'] ? 'Sí' : 'No') . "</td></tr>";
            echo "</table>";
            
            // Verificar validez
            if ($resultado['usado'] == 1) {
                echo "<p style='color:orange;'><strong>⚠️ Token ya fue USADO</strong></p>";
            } elseif (strtotime($resultado['expiracion']) <= time()) {
                echo "<p style='color:red;'><strong>⏰ Token EXPIRADO</strong></p>";
            } else {
                echo "<p style='color:green;'><strong>✅ Token VÁLIDO</strong></p>";
                $resetLink = BASE_URL . "/public/restablecer-password.php?token=" . $testToken;
                echo "<p><a href='{$resetLink}' target='_blank'>Probar Link de Restablecimiento</a></p>";
            }
        }
    }
    
    // Formulario para probar token
    echo "<h2>Probar Token</h2>";
    echo "<form method='GET'>";
    echo "<input type='text' name='token' placeholder='Pegar token aquí' style='width:400px;padding:5px;'>";
    echo "<button type='submit' style='padding:5px 10px;'>Verificar Token</button>";
    echo "</form>";
    
    // Limpiar tokens expirados
    echo "<h2>Mantenimiento</h2>";
    if (isset($_GET['limpiar']) && $_GET['limpiar'] === 'si') {
        $deleted = $db->execute("DELETE FROM recuperacion_password WHERE expiracion <= NOW() AND usado = 0");
        echo "<p style='color:green;'>✅ Tokens expirados eliminados</p>";
    } else {
        echo "<p><a href='?limpiar=si' onclick='return confirm(\"¿Eliminar todos los tokens expirados?\")'>🗑️ Limpiar tokens expirados</a></p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color:red;'>Error: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><small>Para probar el sistema completo:</small></p>";
echo "<ol>";
echo "<li><a href='" . BASE_URL . "/public/recuperar-password.php'>Solicitar recuperación</a></li>";
echo "<li>Revisar el token generado aquí</li>";
echo "<li>Usar el link de restablecimiento</li>";
echo "</ol>";
?>