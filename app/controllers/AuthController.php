<?php
/**
 * Controlador de Autenticación
 * Maneja login, logout y gestión de sesiones
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../models/Usuario.php';
require_once __DIR__ . '/../models/LogAuditoria.php';

class AuthController {
    private $usuarioModel;
    private $logModel;
    
    public function __construct() {
        $this->usuarioModel = new Usuario();
        $this->logModel = new LogAuditoria();
        $this->iniciarSesion();
    }
    
    /**
     * Inicia la sesión si no está iniciada
     */
    private function iniciarSesion() {
        if (session_status() === PHP_SESSION_NONE) {
            session_name(SESSION_NAME);
            session_start();
        }
    }
    
    /**
     * Procesa el login
     */
    public function login() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            
            if (empty($username) || empty($password)) {
                return ['error' => 'Usuario y contraseña son requeridos'];
            }
            
            $result = $this->usuarioModel->autenticar($username, $password);
            
            if (is_array($result) && isset($result['error'])) {
                return $result;
            } elseif ($result) {
                // Login exitoso
                $_SESSION['usuario_id'] = $result['id'];
                $_SESSION['username'] = $result['username'];
                $_SESSION['nombre_completo'] = $result['nombre_completo'];
                $_SESSION['rol'] = $result['rol'];
                $_SESSION['email'] = $result['email'];
                $_SESSION['campana_id'] = $result['campana_id'];
                $_SESSION['last_activity'] = time();
                
                // Registrar en log
                $this->logModel->registrar($result['id'], 'login', 'usuarios', $result['id']);
                
                return ['success' => true, 'rol' => $result['rol']];
            } else {
                return ['error' => 'Credenciales inválidas'];
            }
        }
        
        return ['error' => 'Método no permitido'];
    }
    
    /**
     * Cierra la sesión
     */
    public function logout() {
        if (isset($_SESSION['usuario_id'])) {
            $this->logModel->registrar($_SESSION['usuario_id'], 'logout', 'usuarios', $_SESSION['usuario_id']);
        }
        
        session_unset();
        session_destroy();
        
        return ['success' => true];
    }
    
    /**
     * Verifica si el usuario está autenticado
     */
    public function estaAutenticado() {
        if (!isset($_SESSION['usuario_id'])) {
            return false;
        }
        
        // Verificar tiempo de inactividad
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_LIFETIME)) {
            $this->logout();
            return false;
        }
        
        $_SESSION['last_activity'] = time();
        return true;
    }
    
    /**
     * Verifica si el usuario tiene un rol específico
     */
    public function tieneRol($roles) {
        if (!$this->estaAutenticado()) {
            return false;
        }
        
        if (!is_array($roles)) {
            $roles = [$roles];
        }
        
        return in_array($_SESSION['rol'], $roles);
    }
    
    /**
     * Obtiene el ID del usuario actual
     */
    public function obtenerUsuarioId() {
        return $_SESSION['usuario_id'] ?? null;
    }
    
    /**
     * Obtiene el rol del usuario actual
     */
    public function obtenerRol() {
        return $_SESSION['rol'] ?? null;
    }
    
    /**
     * Obtiene el ID de la campaña del usuario actual
     */
    public function obtenerCampanaId() {
        return $_SESSION['campana_id'] ?? null;
    }
    
    /**
     * Verifica si el usuario puede ver todas las campañas
     */
    public function puedeVerTodasLasCampanas() {
        if (!$this->estaAutenticado()) {
            return false;
        }
        
        $rol = $this->obtenerRol();
        return in_array($rol, ['super_admin', 'admin']);
    }
    
    /**
     * Redirige si no está autenticado
     */
    public function requiereAutenticacion() {
        if (!$this->estaAutenticado()) {
            header('Location: ' . BASE_URL . '/login.php');
            exit;
        }
    }
    
    /**
     * Redirige si no tiene el rol requerido
     */
    public function requiereRol($roles) {
        $this->requiereAutenticacion();
        
        if (!$this->tieneRol($roles)) {
            header('Location: ' . BASE_URL . '/acceso-denegado.php');
            exit;
        }
    }
}
