<?php
/**
 * Servicio de Email SMTP
 * Sistema de Validación de Simpatizantes
 */

class EmailService {
    private $smtp_host;
    private $smtp_port;
    private $smtp_secure;
    private $smtp_username;
    private $smtp_password;
    private $from_email;
    private $from_name;
    
    public function __construct() {
        $this->smtp_host = SMTP_HOST;
        $this->smtp_port = SMTP_PORT;
        $this->smtp_secure = SMTP_SECURE;
        $this->smtp_username = SMTP_USERNAME;
        $this->smtp_password = SMTP_PASSWORD;
        $this->from_email = SMTP_FROM_EMAIL;
        $this->from_name = SMTP_FROM_NAME;
    }
    
    /**
     * Envía un email usando SMTP
     * 
     * @param string $to Email destinatario
     * @param string $subject Asunto del email
     * @param string $body Cuerpo del email (HTML)
     * @param string $toName Nombre del destinatario (opcional)
     * @return bool True si se envió correctamente, false en caso de error
     */
    public function sendEmail($to, $subject, $body, $toName = '') {
        // Usar función nativa de PHP con headers SMTP
        return $this->sendEmailSMTP($to, $subject, $body, $toName);
    }
    
    /**
     * Envía email usando SMTP nativo de PHP (optimizado para velocidad)
     */
    private function sendEmailSMTP($to, $subject, $body, $toName = '') {
        // Configurar timeouts más agresivos para velocidad
        $originalSocketTimeout = ini_get('default_socket_timeout');
        ini_set('default_socket_timeout', 10); // 10 segundos máximo
        
        // Headers optimizados para velocidad
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $headers .= "From: {$this->from_name} <{$this->from_email}>\r\n";
        $headers .= "Reply-To: {$this->from_email}\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
        
        // Configurar SMTP solo si es necesario
        $needsConfig = (ini_get('SMTP') !== $this->smtp_host || 
                       ini_get('smtp_port') != $this->smtp_port);
        
        if ($needsConfig) {
            ini_set('SMTP', $this->smtp_host);
            ini_set('smtp_port', $this->smtp_port);
            ini_set('sendmail_from', $this->from_email);
        }
        
        // Configuraciones adicionales para velocidad
        ini_set('smtp_timeout', 15); // Timeout SMTP específico
        
        $result = mail($to, $subject, $body, $headers);
        
        // Restaurar timeout original
        ini_set('default_socket_timeout', $originalSocketTimeout);
        
        return $result;
    }
    
    /**
     * Envía email de forma optimizada con timeout más bajo
     */
    public function sendEmailFast($to, $subject, $body, $toName = '') {
        // Configurar timeouts muy bajos para respuesta rápida
        $originalTimeout = ini_get('default_socket_timeout');
        ini_set('default_socket_timeout', 5);
        
        $result = $this->sendEmailSMTP($to, $subject, $body, $toName);
        
        // Restaurar timeout
        ini_set('default_socket_timeout', $originalTimeout);
        
        return $result;
    }
    
    /**
     * Envía email de recuperación de contraseña (OPTIMIZADO)
     * Usa cola de emails para respuesta instantánea
     * 
     * @param string $email Email del usuario
     * @param string $nombre Nombre del usuario
     * @param string $token Token de recuperación
     * @return bool
     */
    public function sendPasswordRecoveryEmail($email, $nombre, $token) {
        $resetLink = BASE_URL . "/public/restablecer-password.php?token=" . $token;
        
        $subject = "Recuperación de contraseña - " . APP_NAME;
        $body = $this->getPasswordRecoveryTemplate($nombre, $resetLink);
        
        // Intentar envío rápido primero (5 segundos máximo)
        $quickSent = $this->sendEmailFast($email, $subject, $body, $nombre);
        
        if ($quickSent) {
            return true;
        }
        
        // Si falla el envío rápido, usar cola para procesamiento en background
        if (class_exists('EmailQueue')) {
            try {
                require_once __DIR__ . '/EmailQueue.php';
                $queue = new EmailQueue();
                return $queue->queueEmail($email, $subject, $body, $nombre, 1); // Prioridad alta
            } catch (Exception $e) {
                error_log("Error usando cola de email: " . $e->getMessage());
            }
        }
        
        // Fallback: envío normal (puede ser lento)
        return $this->sendEmail($email, $subject, $body, $nombre);
    }
    
    /**
     * Template HTML para email de recuperación de contraseña
     */
    private function getPasswordRecoveryTemplate($nombre, $resetLink) {
        // Asegurar que el link no tenga espacios o caracteres extraños
        $resetLink = trim($resetLink);
        
        return "
        <!DOCTYPE html>
        <html lang='es'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Recuperación de Contraseña</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #111111; border-bottom: 4px solid #ff8000; color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                .button { display: inline-block; background: #ff8000; color: #111111 !important; font-weight: bold; padding: 15px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; font-weight: bold; font-size: 16px; }
                .button:hover { opacity: 0.9; }
                .footer { text-align: center; margin-top: 30px; color: #666; font-size: 12px; }
                .warning { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 20px 0; }
                .link-backup { word-break: break-all; background: #e9ecef; padding: 15px; border-radius: 5px; font-family: monospace; border: 1px solid #dee2e6; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🔑 Recuperación de Contraseña</h1>
                    <p>" . APP_NAME . "</p>
                </div>
                <div class='content'>
                    <h2>Hola " . htmlspecialchars($nombre) . ",</h2>
                    <p>Hemos recibido una solicitud para restablecer la contraseña de tu cuenta.</p>
                    <p>Si solicitaste este cambio, haz clic en el siguiente botón para crear una nueva contraseña:</p>
                    
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='" . htmlspecialchars($resetLink) . "' class='button' style='color: white !important;'>🔓 Restablecer mi contraseña</a>
                    </div>
                    
                    <div class='warning'>
                        <strong>⚠️ Importante:</strong>
                        <ul>
                            <li>Este enlace es válido por <strong>1 hora</strong></li>
                            <li>Solo puede utilizarse una vez</li>
                            <li>Si no solicitaste este cambio, puedes ignorar este email</li>
                        </ul>
                    </div>
                    
                    <p><strong>Si el botón no funciona, copia y pega este enlace en tu navegador:</strong></p>
                    <div class='link-backup'>" . htmlspecialchars($resetLink) . "</div>
                    
                    <p>Si tienes problemas o no solicitaste este cambio, contacta con el administrador del sistema.</p>
                    
                    <p>Saludos,<br>
                    <strong>Equipo de " . APP_NAME . "</strong></p>
                </div>
                <div class='footer'>
                    <p>Este es un email automático, por favor no respondas a este mensaje.</p>
                    <p>&copy; " . date('Y') . " " . APP_NAME . ". Todos los derechos reservados.</p>
                </div>
            </div>
        </body>
        </html>";
    }
}