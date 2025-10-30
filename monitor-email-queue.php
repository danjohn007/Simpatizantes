<?php
/**
 * Monitor de Cola de Emails
 * Panel para monitorear y gestionar la cola de emails
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/app/services/EmailQueue.php';

try {
    $emailQueue = new EmailQueue();
    
    // Procesar acciones
    if (isset($_GET['action'])) {
        switch ($_GET['action']) {
            case 'process':
                $processed = $emailQueue->processQueue(10);
                $message = "✅ Procesados {$processed} emails";
                break;
                
            case 'clean':
                $cleaned = $emailQueue->cleanOldEmails(7);
                $message = "🗑️ Limpiados {$cleaned} emails antiguos";
                break;
                
            case 'stats':
                // Solo mostrar stats, se hace abajo
                break;
        }
    }
    
    $stats = $emailQueue->getQueueStats();
    
} catch (Exception $e) {
    $error = "Error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitor de Cola de Emails - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .stat-card { transition: transform 0.2s; }
        .stat-card:hover { transform: translateY(-2px); }
        .pending { background: #fff3cd; border-left: 4px solid #ffc107; }
        .processing { background: #d1ecf1; border-left: 4px solid #17a2b8; }
        .sent { background: #d4edda; border-left: 4px solid #28a745; }
        .failed { background: #f8d7da; border-left: 4px solid #dc3545; }
    </style>
</head>
<body class="bg-light">
    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1><i class="bi bi-envelope-gear"></i> Monitor de Cola de Emails</h1>
                    <div>
                        <a href="<?php echo BASE_URL; ?>/public/recuperar-password.php" class="btn btn-outline-primary">
                            <i class="bi bi-arrow-left"></i> Volver
                        </a>
                    </div>
                </div>
                
                <?php if (isset($message)): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <!-- Estadísticas -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card stat-card pending">
                            <div class="card-body text-center">
                                <h2 class="card-title"><?php echo $stats['pending'] ?? 0; ?></h2>
                                <p class="card-text"><i class="bi bi-clock"></i> Pendientes</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card processing">
                            <div class="card-body text-center">
                                <h2 class="card-title"><?php echo $stats['processing'] ?? 0; ?></h2>
                                <p class="card-text"><i class="bi bi-gear"></i> Procesando</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card sent">
                            <div class="card-body text-center">
                                <h2 class="card-title"><?php echo $stats['sent'] ?? 0; ?></h2>
                                <p class="card-text"><i class="bi bi-check-circle"></i> Enviados</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card failed">
                            <div class="card-body text-center">
                                <h2 class="card-title"><?php echo $stats['failed'] ?? 0; ?></h2>
                                <p class="card-text"><i class="bi bi-x-circle"></i> Fallidos</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Acciones -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5><i class="bi bi-tools"></i> Acciones</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <a href="?action=process" class="btn btn-primary w-100 mb-2">
                                    <i class="bi bi-play-circle"></i> Procesar Cola
                                </a>
                                <small class="text-muted">Procesa hasta 10 emails pendientes</small>
                            </div>
                            <div class="col-md-4">
                                <a href="?action=clean" class="btn btn-warning w-100 mb-2" 
                                   onclick="return confirm('¿Limpiar emails antiguos?')">
                                    <i class="bi bi-trash"></i> Limpiar Antiguos
                                </a>
                                <small class="text-muted">Elimina emails de más de 7 días</small>
                            </div>
                            <div class="col-md-4">
                                <a href="?" class="btn btn-info w-100 mb-2">
                                    <i class="bi bi-arrow-clockwise"></i> Actualizar Stats
                                </a>
                                <small class="text-muted">Actualiza las estadísticas</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Lista de emails recientes -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="bi bi-list"></i> Emails Recientes</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        try {
                            $db = Database::getInstance();
                            $recentEmails = $db->query(
                                "SELECT * FROM email_queue 
                                 ORDER BY created_at DESC 
                                 LIMIT 20"
                            );
                            
                            if (count($recentEmails) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Email</th>
                                                <th>Asunto</th>
                                                <th>Estado</th>
                                                <th>Intentos</th>
                                                <th>Creado</th>
                                                <th>Procesado</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recentEmails as $email): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($email['to_email']); ?></td>
                                                    <td><?php echo htmlspecialchars(substr($email['subject'], 0, 30)) . '...'; ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php 
                                                            echo $email['status'] === 'sent' ? 'success' : 
                                                                ($email['status'] === 'failed' ? 'danger' : 
                                                                ($email['status'] === 'processing' ? 'info' : 'warning')); 
                                                        ?>">
                                                            <?php echo ucfirst($email['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo $email['attempts']; ?>/<?php echo $email['max_attempts']; ?></td>
                                                    <td><?php echo date('H:i:s', strtotime($email['created_at'])); ?></td>
                                                    <td>
                                                        <?php echo $email['processed_at'] ? 
                                                            date('H:i:s', strtotime($email['processed_at'])) : 
                                                            '-'; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-muted">No hay emails en la cola.</p>
                            <?php endif; ?>
                        <?php
                        } catch (Exception $e) {
                            echo '<p class="text-danger">Error cargando emails: ' . htmlspecialchars($e->getMessage()) . '</p>';
                        }
                        ?>
                    </div>
                </div>
                
                <!-- Auto-refresh -->
                <div class="text-center mt-4">
                    <small class="text-muted">
                        <i class="bi bi-arrow-clockwise"></i> 
                        Esta página se actualiza automáticamente cada 30 segundos
                    </small>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-refresh cada 30 segundos
        setTimeout(() => {
            window.location.href = '?action=stats';
        }, 30000);
    </script>
</body>
</html>