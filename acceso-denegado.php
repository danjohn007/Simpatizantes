<?php
/**
 * Acceso Denegado
 */

require_once __DIR__ . '/config/config.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso Denegado - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .error-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            padding: 40px;
            text-align: center;
            max-width: 500px;
        }
        .error-icon {
            font-size: 5rem;
            color: #dc3545;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="error-card">
                    <i class="bi bi-shield-x error-icon"></i>
                    <h1 class="mb-3">Acceso Denegado</h1>
                    <p class="text-muted mb-4">
                        No tienes permisos suficientes para acceder a esta página.
                    </p>
                    <div class="d-grid gap-2">
                        <a href="<?php echo BASE_URL; ?>/public/dashboard.php" class="btn btn-primary">
                            <i class="bi bi-house-fill me-2"></i>Ir al Dashboard
                        </a>
                        <a href="<?php echo BASE_URL; ?>/public/logout.php" class="btn btn-outline-secondary">
                            <i class="bi bi-box-arrow-right me-2"></i>Cerrar Sesión
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
