<?php
/**
 * Sistema de Cola de Emails
 * Procesa emails en background para respuesta más rápida
 */

class EmailQueue {
    private $db;
    private $queueFile;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->queueFile = BASE_PATH . '/tmp/email_queue.json';
        
        // Crear directorio tmp si no existe
        $tmpDir = BASE_PATH . '/tmp';
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }
        
        // Crear tabla de cola de emails si no existe
        $this->createEmailQueueTable();
    }
    
    /**
     * Crear tabla para cola de emails
     */
    private function createEmailQueueTable() {
        $sql = "CREATE TABLE IF NOT EXISTS email_queue (
            id INT AUTO_INCREMENT PRIMARY KEY,
            to_email VARCHAR(255) NOT NULL,
            to_name VARCHAR(255),
            subject VARCHAR(500) NOT NULL,
            body TEXT NOT NULL,
            status ENUM('pending', 'processing', 'sent', 'failed') DEFAULT 'pending',
            attempts INT DEFAULT 0,
            max_attempts INT DEFAULT 3,
            priority INT DEFAULT 5,
            scheduled_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            processed_at DATETIME NULL,
            error_message TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_status (status),
            INDEX idx_scheduled (scheduled_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        try {
            $this->db->execute($sql);
        } catch (Exception $e) {
            error_log("Error creando tabla email_queue: " . $e->getMessage());
        }
    }
    
    /**
     * Agregar email a la cola
     */
    public function queueEmail($to, $subject, $body, $toName = '', $priority = 5) {
        $sql = "INSERT INTO email_queue (to_email, to_name, subject, body, priority) VALUES (?, ?, ?, ?, ?)";
        
        try {
            $this->db->execute($sql, [$to, $toName, $subject, $body, $priority]);
            
            // Procesar inmediatamente en background si es posible
            $this->processQueueAsync();
            
            return true;
        } catch (Exception $e) {
            error_log("Error agregando email a la cola: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Procesar cola en background (no bloquea la respuesta)
     */
    public function processQueueAsync() {
        // Intentar procesar en background usando diferentes métodos
        
        // Método 1: exec() en background (Linux/Unix)
        if (function_exists('exec') && !$this->isWindows()) {
            $phpPath = $this->findPhpPath();
            $scriptPath = __DIR__ . '/../process_email_queue.php';
            $command = "{$phpPath} {$scriptPath} > /dev/null 2>&1 &";
            exec($command);
            return;
        }
        
        // Método 2: popen() en background (Windows compatible)
        if (function_exists('popen')) {
            $phpPath = $this->findPhpPath();
            $scriptPath = __DIR__ . '/../process_email_queue.php';
            $command = $this->isWindows() ? 
                "start /B {$phpPath} {$scriptPath}" : 
                "{$phpPath} {$scriptPath} > /dev/null 2>&1 &";
            
            $handle = popen($command, 'r');
            if ($handle) {
                pclose($handle);
                return;
            }
        }
        
        // Método 3: cURL hacia un endpoint interno (fallback)
        $this->triggerProcessingViaHttp();
    }
    
    /**
     * Procesar cola de emails inmediatamente
     */
    public function processQueue($maxEmails = 10) {
        $sql = "SELECT * FROM email_queue 
                WHERE status = 'pending' AND scheduled_at <= NOW() 
                ORDER BY priority ASC, created_at ASC 
                LIMIT ?";
        
        $emails = $this->db->query($sql, [$maxEmails]);
        $processed = 0;
        
        foreach ($emails as $email) {
            if ($this->processEmail($email)) {
                $processed++;
            }
        }
        
        return $processed;
    }
    
    /**
     * Procesar un email individual
     */
    private function processEmail($email) {
        // Marcar como procesando
        $this->updateEmailStatus($email['id'], 'processing');
        
        try {
            $emailService = new EmailService();
            $sent = $emailService->sendEmail(
                $email['to_email'],
                $email['subject'],
                $email['body'],
                $email['to_name']
            );
            
            if ($sent) {
                $this->updateEmailStatus($email['id'], 'sent', null, date('Y-m-d H:i:s'));
                error_log("Email enviado desde cola: " . $email['to_email']);
                return true;
            } else {
                throw new Exception("Error enviando email");
            }
            
        } catch (Exception $e) {
            $attempts = $email['attempts'] + 1;
            $status = ($attempts >= $email['max_attempts']) ? 'failed' : 'pending';
            
            $this->updateEmailStatus($email['id'], $status, $e->getMessage());
            $this->incrementAttempts($email['id']);
            
            error_log("Error procesando email ID {$email['id']}: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Actualizar estado del email
     */
    private function updateEmailStatus($id, $status, $errorMessage = null, $processedAt = null) {
        $sql = "UPDATE email_queue SET status = ?, error_message = ?";
        $params = [$status, $errorMessage];
        
        if ($processedAt) {
            $sql .= ", processed_at = ?";
            $params[] = $processedAt;
        }
        
        $sql .= " WHERE id = ?";
        $params[] = $id;
        
        $this->db->execute($sql, $params);
    }
    
    /**
     * Incrementar intentos
     */
    private function incrementAttempts($id) {
        $sql = "UPDATE email_queue SET attempts = attempts + 1 WHERE id = ?";
        $this->db->execute($sql, [$id]);
    }
    
    /**
     * Obtener estadísticas de la cola
     */
    public function getQueueStats() {
        $sql = "SELECT 
                    status,
                    COUNT(*) as count
                FROM email_queue 
                GROUP BY status";
        
        $results = $this->db->query($sql);
        $stats = [];
        
        foreach ($results as $row) {
            $stats[$row['status']] = $row['count'];
        }
        
        return $stats;
    }
    
    /**
     * Limpiar emails antiguos
     */
    public function cleanOldEmails($daysOld = 7) {
        $sql = "DELETE FROM email_queue 
                WHERE status IN ('sent', 'failed') 
                AND created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
        
        return $this->db->execute($sql, [$daysOld]);
    }
    
    /**
     * Detectar si es Windows
     */
    private function isWindows() {
        return strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    }
    
    /**
     * Encontrar path de PHP
     */
    private function findPhpPath() {
        if ($this->isWindows()) {
            return 'php'; // En Windows, usualmente está en PATH
        } else {
            // En Linux/Unix, intentar encontrar PHP
            $paths = ['/usr/bin/php', '/usr/local/bin/php', 'php'];
            foreach ($paths as $path) {
                if (file_exists($path)) {
                    return $path;
                }
            }
            return 'php'; // Fallback
        }
    }
    
    /**
     * Trigger processing via HTTP (fallback method)
     */
    private function triggerProcessingViaHttp() {
        $url = BASE_URL . '/process_email_queue.php';
        
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 1, // Timeout muy bajo para no bloquear
                'ignore_errors' => true
            ]
        ]);
        
        // Ejecutar en modo "fire and forget"
        file_get_contents($url, false, $context);
    }
}