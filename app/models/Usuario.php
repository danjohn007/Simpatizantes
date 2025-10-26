<?php
/**
 * Modelo Usuario
 * Maneja operaciones relacionadas con usuarios
 */

class Usuario {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Autentica un usuario
     */
    public function autenticar($username, $password) {
        $sql = "SELECT * FROM usuarios WHERE username = ? AND activo = 1";
        $user = $this->db->queryOne($sql, [$username]);
        
        if (!$user) {
            return false;
        }
        
        // Verificar si el usuario está bloqueado
        if ($user['bloqueado_hasta']) {
            $bloqueadoHasta = strtotime($user['bloqueado_hasta']);
            if (time() < $bloqueadoHasta) {
                return ['error' => 'Usuario bloqueado temporalmente'];
            } else {
                // Desbloquear usuario
                $this->desbloquearUsuario($user['id']);
            }
        }
        
        // Verificar contraseña
        if (password_verify($password, $user['password'])) {
            // Login exitoso - resetear intentos fallidos
            $this->resetearIntentosFallidos($user['id']);
            return $user;
        } else {
            // Login fallido - incrementar intentos
            $this->incrementarIntentosFallidos($user['id']);
            return false;
        }
    }
    
    /**
     * Incrementa intentos fallidos de login
     */
    private function incrementarIntentosFallidos($userId) {
        $sql = "UPDATE usuarios SET intentos_fallidos = intentos_fallidos + 1 WHERE id = ?";
        $this->db->execute($sql, [$userId]);
        
        // Verificar si debe bloquearse
        $user = $this->obtenerPorId($userId);
        if ($user['intentos_fallidos'] >= MAX_LOGIN_ATTEMPTS) {
            $this->bloquearUsuario($userId);
        }
    }
    
    /**
     * Resetea intentos fallidos de login
     */
    private function resetearIntentosFallidos($userId) {
        $sql = "UPDATE usuarios SET intentos_fallidos = 0, bloqueado_hasta = NULL WHERE id = ?";
        $this->db->execute($sql, [$userId]);
    }
    
    /**
     * Bloquea un usuario temporalmente
     */
    private function bloquearUsuario($userId) {
        $bloqueadoHasta = date('Y-m-d H:i:s', strtotime('+' . LOCKOUT_TIME . ' minutes'));
        $sql = "UPDATE usuarios SET bloqueado_hasta = ? WHERE id = ?";
        $this->db->execute($sql, [$bloqueadoHasta, $userId]);
    }
    
    /**
     * Desbloquea un usuario
     */
    private function desbloquearUsuario($userId) {
        $sql = "UPDATE usuarios SET intentos_fallidos = 0, bloqueado_hasta = NULL WHERE id = ?";
        $this->db->execute($sql, [$userId]);
    }
    
    /**
     * Obtiene usuario por ID
     */
    public function obtenerPorId($id) {
        $sql = "SELECT * FROM usuarios WHERE id = ?";
        return $this->db->queryOne($sql, [$id]);
    }
    
    /**
     * Obtiene todos los usuarios con paginación
     */
    public function obtenerTodos($page = 1, $perPage = RECORDS_PER_PAGE, $rol = null) {
        $offset = ($page - 1) * $perPage;
        
        if ($rol) {
            $sql = "SELECT id, username, email, nombre_completo, rol, activo, created_at 
                    FROM usuarios WHERE rol = ? 
                    ORDER BY created_at DESC LIMIT ? OFFSET ?";
            return $this->db->query($sql, [$rol, $perPage, $offset]);
        } else {
            $sql = "SELECT id, username, email, nombre_completo, rol, activo, created_at 
                    FROM usuarios 
                    ORDER BY created_at DESC LIMIT ? OFFSET ?";
            return $this->db->query($sql, [$perPage, $offset]);
        }
    }
    
    /**
     * Cuenta total de usuarios
     */
    public function contarTotal($rol = null) {
        if ($rol) {
            $sql = "SELECT COUNT(*) as total FROM usuarios WHERE rol = ?";
            $result = $this->db->queryOne($sql, [$rol]);
        } else {
            $sql = "SELECT COUNT(*) as total FROM usuarios";
            $result = $this->db->queryOne($sql);
        }
        return $result['total'] ?? 0;
    }
    
    /**
     * Crea un nuevo usuario
     */
    public function crear($datos) {
        // Validar que no exista el username o email
        if ($this->existeUsername($datos['username'])) {
            return ['error' => 'El nombre de usuario ya existe'];
        }
        
        if ($this->existeEmail($datos['email'])) {
            return ['error' => 'El email ya está registrado'];
        }
        
        // Hash de la contraseña
        $passwordHash = password_hash($datos['password'], PASSWORD_DEFAULT);
        
        $sql = "INSERT INTO usuarios (username, email, password, nombre_completo, rol, whatsapp, 
                twitter, instagram, facebook, youtube, tiktok, activo, campana_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $datos['username'],
            $datos['email'],
            $passwordHash,
            $datos['nombre_completo'],
            $datos['rol'] ?? 'capturista',
            $datos['whatsapp'] ?? null,
            $datos['twitter'] ?? null,
            $datos['instagram'] ?? null,
            $datos['facebook'] ?? null,
            $datos['youtube'] ?? null,
            $datos['tiktok'] ?? null,
            $datos['activo'] ?? 1,
            $datos['campana_id'] ?? null
        ];
        
        if ($this->db->execute($sql, $params)) {
            return ['success' => true, 'id' => $this->db->lastInsertId()];
        }
        
        return ['error' => 'Error al crear el usuario'];
    }
    
    /**
     * Actualiza un usuario
     */
    public function actualizar($id, $datos) {
        $sql = "UPDATE usuarios SET 
                email = ?, nombre_completo = ?, rol = ?, campana_id = ?, whatsapp = ?, 
                twitter = ?, instagram = ?, facebook = ?, youtube = ?, tiktok = ?, activo = ?
                WHERE id = ?";
        
        $params = [
            $datos['email'],
            $datos['nombre_completo'],
            $datos['rol'],
            $datos['campana_id'] ?? null,
            $datos['whatsapp'] ?? null,
            $datos['twitter'] ?? null,
            $datos['instagram'] ?? null,
            $datos['facebook'] ?? null,
            $datos['youtube'] ?? null,
            $datos['tiktok'] ?? null,
            $datos['activo'] ?? 1,
            $id
        ];
        
        return $this->db->execute($sql, $params);
    }
    
    /**
     * Cambia la contraseña de un usuario
     */
    public function cambiarPassword($id, $newPassword) {
        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $sql = "UPDATE usuarios SET password = ? WHERE id = ?";
        return $this->db->execute($sql, [$passwordHash, $id]);
    }
    
    /**
     * Elimina un usuario permanentemente (hard delete)
     */
    public function eliminar($id) {
        $sql = "DELETE FROM usuarios WHERE id = ?";
        return $this->db->execute($sql, [$id]);
    }
    
    /**
     * Cambia el estado activo/suspendido de un usuario
     */
    public function cambiarEstado($id, $nuevoEstado) {
        $sql = "UPDATE usuarios SET activo = ? WHERE id = ?";
        return $this->db->execute($sql, [$nuevoEstado, $id]);
    }
    
    /**
     * Verifica si existe un username
     */
    public function existeUsername($username, $excludeId = null) {
        if ($excludeId) {
            $sql = "SELECT COUNT(*) as count FROM usuarios WHERE username = ? AND id != ?";
            $result = $this->db->queryOne($sql, [$username, $excludeId]);
        } else {
            $sql = "SELECT COUNT(*) as count FROM usuarios WHERE username = ?";
            $result = $this->db->queryOne($sql, [$username]);
        }
        return $result['count'] > 0;
    }
    
    /**
     * Verifica si existe un email
     */
    public function existeEmail($email, $excludeId = null) {
        if ($excludeId) {
            $sql = "SELECT COUNT(*) as count FROM usuarios WHERE email = ? AND id != ?";
            $result = $this->db->queryOne($sql, [$email, $excludeId]);
        } else {
            $sql = "SELECT COUNT(*) as count FROM usuarios WHERE email = ?";
            $result = $this->db->queryOne($sql, [$email]);
        }
        return $result['count'] > 0;
    }
    
    /**
     * Verifica si existe un WhatsApp
     */
    public function existeWhatsApp($whatsapp, $excludeId = null) {
        if ($excludeId) {
            $sql = "SELECT COUNT(*) as count FROM usuarios WHERE whatsapp = ? AND id != ?";
            $result = $this->db->queryOne($sql, [$whatsapp, $excludeId]);
        } else {
            $sql = "SELECT COUNT(*) as count FROM usuarios WHERE whatsapp = ?";
            $result = $this->db->queryOne($sql, [$whatsapp]);
        }
        return $result['count'] > 0;
    }
    
    /**
     * Obtiene subordinados de un usuario
     */
    public function obtenerSubordinados($userId) {
        $sql = "SELECT u.* FROM usuarios u
                INNER JOIN jerarquia_usuarios j ON u.id = j.subordinado_id
                WHERE j.superior_id = ?";
        return $this->db->query($sql, [$userId]);
    }
    
    /**
     * Asigna un subordinado a un superior
     */
    public function asignarSubordinado($superiorId, $subordinadoId) {
        $sql = "INSERT INTO jerarquia_usuarios (superior_id, subordinado_id) VALUES (?, ?)";
        return $this->db->execute($sql, [$superiorId, $subordinadoId]);
    }
}
