<?php
/**
 * Test de Conexión y Configuración
 * Verifica que la base de datos y configuración estén correctas
 */

require_once __DIR__ . '/config/config.php';

$tests = [];
$allPassed = true;

// Test 1: Configuración básica
$tests[] = [
    'name' => 'Configuración de constantes',
    'status' => defined('BASE_URL') && defined('DB_HOST') && defined('DB_NAME'),
    'message' => 'BASE_URL: ' . BASE_URL
];

// Test 2: Conexión a Base de Datos
try {
    require_once __DIR__ . '/config/Database.php';
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    $tests[] = [
        'name' => 'Conexión a MySQL',
        'status' => $conn !== null,
        'message' => 'Conectado a ' . DB_NAME . ' en ' . DB_HOST
    ];
    
    // Test 3: Verificar tablas
    $tables = ['usuarios', 'simpatizantes', 'campanas', 'logs_auditoria'];
    $existingTables = [];
    
    foreach ($tables as $table) {
        $result = $db->query("SHOW TABLES LIKE '$table'");
        if ($result && count($result) > 0) {
            $existingTables[] = $table;
        }
    }
    
    $tests[] = [
        'name' => 'Tablas de base de datos',
        'status' => count($existingTables) === count($tables),
        'message' => 'Encontradas: ' . implode(', ', $existingTables)
    ];
    
    // Test 4: Verificar usuario de prueba
    $user = $db->queryOne("SELECT * FROM usuarios WHERE username = 'superadmin'");
    $tests[] = [
        'name' => 'Usuario de prueba',
        'status' => $user !== false,
        'message' => $user ? 'Usuario superadmin encontrado' : 'Usuario no encontrado'
    ];
    
} catch (Exception $e) {
    $tests[] = [
        'name' => 'Conexión a Base de Datos',
        'status' => false,
        'message' => 'Error: ' . $e->getMessage()
    ];
    $allPassed = false;
}

// Test 5: Directorio de uploads
$uploadDirs = ['uploads', 'uploads/ine_frontal', 'uploads/ine_posterior', 'uploads/firmas'];
$uploadTest = true;
$uploadMessage = [];

foreach ($uploadDirs as $dir) {
    $path = PUBLIC_PATH . '/' . $dir;
    if (!is_dir($path)) {
        @mkdir($path, 0755, true);
    }
    if (is_writable($path)) {
        $uploadMessage[] = $dir . ' ✓';
    } else {
        $uploadMessage[] = $dir . ' ✗';
        $uploadTest = false;
    }
}

$tests[] = [
    'name' => 'Directorios de carga',
    'status' => $uploadTest,
    'message' => implode(', ', $uploadMessage)
];

// Test 6: Extensiones PHP
$requiredExtensions = ['pdo', 'pdo_mysql', 'mbstring', 'json'];
$loadedExtensions = [];
$missingExtensions = [];

foreach ($requiredExtensions as $ext) {
    if (extension_loaded($ext)) {
        $loadedExtensions[] = $ext;
    } else {
        $missingExtensions[] = $ext;
    }
}

$tests[] = [
    'name' => 'Extensiones PHP',
    'status' => empty($missingExtensions),
    'message' => empty($missingExtensions) ? 
        'Todas las extensiones cargadas' : 
        'Faltantes: ' . implode(', ', $missingExtensions)
];

foreach ($tests as $test) {
    if (!$test['status']) {
        $allPassed = false;
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test de Conexión - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background: #f8f9fa;
            padding: 40px 0;
        }
        .test-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 30px;
            margin-bottom: 30px;
        }
        .test-item {
            padding: 15px;
            border-left: 4px solid #dee2e6;
            margin-bottom: 15px;
            background: #f8f9fa;
            border-radius: 5px;
        }
        .test-item.success {
            border-left-color: #28a745;
            background: #d4edda;
        }
        .test-item.failed {
            border-left-color: #dc3545;
            background: #f8d7da;
        }
        .header-success {
            color: #28a745;
        }
        .header-failed {
            color: #dc3545;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="test-card">
                    <div class="text-center mb-4">
                        <i class="bi bi-gear-fill <?php echo $allPassed ? 'header-success' : 'header-failed'; ?>" 
                           style="font-size: 4rem;"></i>
                        <h2 class="mt-3">Test de Conexión y Configuración</h2>
                        <p class="text-muted"><?php echo APP_NAME; ?></p>
                    </div>
                    
                    <?php if ($allPassed): ?>
                        <div class="alert alert-success">
                            <i class="bi bi-check-circle-fill me-2"></i>
                            <strong>¡Éxito!</strong> Todos los tests pasaron correctamente.
                        </div>
                    <?php else: ?>
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <strong>Atención!</strong> Algunos tests fallaron. Revise la configuración.
                        </div>
                    <?php endif; ?>
                    
                    <h5 class="mb-3">Resultados de los Tests:</h5>
                    
                    <?php foreach ($tests as $test): ?>
                        <div class="test-item <?php echo $test['status'] ? 'success' : 'failed'; ?>">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong>
                                        <?php if ($test['status']): ?>
                                            <i class="bi bi-check-circle-fill text-success me-2"></i>
                                        <?php else: ?>
                                            <i class="bi bi-x-circle-fill text-danger me-2"></i>
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($test['name']); ?>
                                    </strong>
                                    <div class="small text-muted mt-1">
                                        <?php echo htmlspecialchars($test['message']); ?>
                                    </div>
                                </div>
                                <span class="badge <?php echo $test['status'] ? 'bg-success' : 'bg-danger'; ?>">
                                    <?php echo $test['status'] ? 'OK' : 'ERROR'; ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <hr class="my-4">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Información del Sistema:</h6>
                            <ul class="list-unstyled small">
                                <li><strong>PHP Version:</strong> <?php echo PHP_VERSION; ?></li>
                                <li><strong>Servidor:</strong> <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'N/A'; ?></li>
                                <li><strong>Base URL:</strong> <?php echo BASE_URL; ?></li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6>Base de Datos:</h6>
                            <ul class="list-unstyled small">
                                <li><strong>Host:</strong> <?php echo DB_HOST; ?></li>
                                <li><strong>Database:</strong> <?php echo DB_NAME; ?></li>
                                <li><strong>Charset:</strong> <?php echo DB_CHARSET; ?></li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="text-center mt-4">
                        <a href="<?php echo BASE_URL; ?>" class="btn btn-primary">
                            <i class="bi bi-house-fill me-2"></i>Ir al Sistema
                        </a>
                    </div>
                </div>
                
                <?php if ($allPassed): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle-fill me-2"></i>
                        <strong>Siguiente paso:</strong> Acceda al sistema con las credenciales de prueba:
                        <code>superadmin / admin123</code>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
