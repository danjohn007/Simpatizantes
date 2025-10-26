<?php
/**
 * Modelo Simpatizante
 * Maneja operaciones relacionadas con simpatizantes
 */

class Simpatizante {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Obtiene simpatizante por ID
     */
    public function obtenerPorId($id) {
        $sql = "SELECT s.*, c.nombre as campana_nombre, u.nombre_completo as capturista_nombre 
                FROM simpatizantes s
                LEFT JOIN campanas c ON s.campana_id = c.id
                LEFT JOIN usuarios u ON s.capturista_id = u.id
                WHERE s.id = ?";
        return $this->db->queryOne($sql, [$id]);
    }
    
    /**
     * Obtiene todos los simpatizantes con filtros y paginación
     */
    public function obtenerTodos($filtros = [], $page = 1, $perPage = RECORDS_PER_PAGE) {
        $offset = ($page - 1) * $perPage;
        $where = [];
        $params = [];
        
        // Construir filtros
        if (!empty($filtros['campana_id'])) {
            $where[] = "s.campana_id = ?";
            $params[] = $filtros['campana_id'];
        }
        
        if (!empty($filtros['capturista_id'])) {
            $where[] = "s.capturista_id = ?";
            $params[] = $filtros['capturista_id'];
        }
        
        if (!empty($filtros['seccion_electoral'])) {
            $where[] = "s.seccion_electoral = ?";
            $params[] = $filtros['seccion_electoral'];
        }
        
        if (!empty($filtros['metodo_captura'])) {
            $where[] = "s.metodo_captura = ?";
            $params[] = $filtros['metodo_captura'];
        }
        
        if (!empty($filtros['validado'])) {
            $where[] = "s.validado = ?";
            $params[] = $filtros['validado'];
        }
        
        if (!empty($filtros['fecha_inicio'])) {
            $where[] = "DATE(s.created_at) >= ?";
            $params[] = $filtros['fecha_inicio'];
        }
        
        if (!empty($filtros['fecha_fin'])) {
            $where[] = "DATE(s.created_at) <= ?";
            $params[] = $filtros['fecha_fin'];
        }
        
        if (!empty($filtros['busqueda'])) {
            $where[] = "(s.nombre_completo LIKE ? OR s.clave_elector LIKE ? OR s.curp LIKE ?)";
            $busqueda = '%' . $filtros['busqueda'] . '%';
            $params[] = $busqueda;
            $params[] = $busqueda;
            $params[] = $busqueda;
        }
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $sql = "SELECT s.*, c.nombre as campana_nombre, u.nombre_completo as capturista_nombre 
                FROM simpatizantes s
                LEFT JOIN campanas c ON s.campana_id = c.id
                LEFT JOIN usuarios u ON s.capturista_id = u.id
                $whereClause
                ORDER BY s.created_at DESC 
                LIMIT ? OFFSET ?";
        
        $params[] = $perPage;
        $params[] = $offset;
        
        return $this->db->query($sql, $params);
    }
    
    /**
     * Cuenta total de simpatizantes
     */
    public function contarTotal($filtros = []) {
        $where = [];
        $params = [];
        
        if (!empty($filtros['campana_id'])) {
            $where[] = "campana_id = ?";
            $params[] = $filtros['campana_id'];
        }
        
        if (!empty($filtros['capturista_id'])) {
            $where[] = "capturista_id = ?";
            $params[] = $filtros['capturista_id'];
        }
        
        if (!empty($filtros['seccion_electoral'])) {
            $where[] = "seccion_electoral = ?";
            $params[] = $filtros['seccion_electoral'];
        }
        
        if (!empty($filtros['fecha_inicio'])) {
            $where[] = "DATE(created_at) >= ?";
            $params[] = $filtros['fecha_inicio'];
        }
        
        if (!empty($filtros['fecha_fin'])) {
            $where[] = "DATE(created_at) <= ?";
            $params[] = $filtros['fecha_fin'];
        }
        
        if (!empty($filtros['busqueda'])) {
            $where[] = "(nombre_completo LIKE ? OR clave_elector LIKE ? OR curp LIKE ?)";
            $busqueda = '%' . $filtros['busqueda'] . '%';
            $params[] = $busqueda;
            $params[] = $busqueda;
            $params[] = $busqueda;
        }
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $sql = "SELECT COUNT(*) as total FROM simpatizantes $whereClause";
        $result = $this->db->queryOne($sql, $params);
        return $result['total'] ?? 0;
    }
    
    /**
     * Crea un nuevo simpatizante
     */
    public function crear($datos) {
        // Verificar duplicados
        if ($this->verificarDuplicado($datos)) {
            return ['error' => 'Ya existe un simpatizante con la misma Clave de Elector o CURP'];
        }
        
        $sql = "INSERT INTO simpatizantes (
                    nombre_completo, domicilio_completo, sexo, ciudad, clave_elector, curp,
                    fecha_nacimiento, ano_registro, vigencia, seccion_electoral, whatsapp, email,
                    twitter, instagram, facebook, youtube, tiktok, latitud, longitud,
                    ine_frontal, ine_posterior, firma_digital, campana_id, capturista_id,
                    metodo_captura, validado
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $datos['nombre_completo'],
            $datos['domicilio_completo'],
            $datos['sexo'] ?? null,
            $datos['ciudad'] ?? null,
            $datos['clave_elector'] ?? null,
            $datos['curp'] ?? null,
            $datos['fecha_nacimiento'] ?? null,
            $datos['ano_registro'] ?? null,
            $datos['vigencia'] ?? null,
            $datos['seccion_electoral'],
            $datos['whatsapp'] ?? null,
            $datos['email'] ?? null,
            $datos['twitter'] ?? null,
            $datos['instagram'] ?? null,
            $datos['facebook'] ?? null,
            $datos['youtube'] ?? null,
            $datos['tiktok'] ?? null,
            $datos['latitud'] ?? null,
            $datos['longitud'] ?? null,
            $datos['ine_frontal'] ?? null,
            $datos['ine_posterior'] ?? null,
            $datos['firma_digital'] ?? null,
            $datos['campana_id'] ?? null,
            $datos['capturista_id'] ?? null,
            $datos['metodo_captura'] ?? 'manual',
            $datos['validado'] ?? 0
        ];
        
        if ($this->db->execute($sql, $params)) {
            return ['success' => true, 'id' => $this->db->lastInsertId()];
        }
        
        return ['error' => 'Error al crear el simpatizante'];
    }
    
    /**
     * Actualiza un simpatizante
     */
    public function actualizar($id, $datos) {
        $sql = "UPDATE simpatizantes SET 
                nombre_completo = ?, domicilio_completo = ?, sexo = ?, ciudad = ?,
                clave_elector = ?, curp = ?, fecha_nacimiento = ?, ano_registro = ?,
                vigencia = ?, seccion_electoral = ?, telefono = ?, whatsapp = ?, email = ?,
                twitter = ?, instagram = ?, facebook = ?, youtube = ?, tiktok = ?,
                latitud = ?, longitud = ?, campana_id = ?, metodo_captura = ?, 
                observaciones = ?, validado = ?
                WHERE id = ?";
        
        $params = [
            $datos['nombre_completo'],
            $datos['domicilio_completo'],
            $datos['sexo'] ?? null,
            $datos['ciudad'] ?? null,
            $datos['clave_elector'] ?? null,
            $datos['curp'] ?? null,
            $datos['fecha_nacimiento'] ?? null,
            $datos['ano_registro'] ?? null,
            $datos['vigencia'] ?? null,
            $datos['seccion_electoral'],
            $datos['telefono'] ?? null,
            $datos['whatsapp'] ?? null,
            $datos['email'] ?? null,
            $datos['twitter'] ?? null,
            $datos['instagram'] ?? null,
            $datos['facebook'] ?? null,
            $datos['youtube'] ?? null,
            $datos['tiktok'] ?? null,
            $datos['latitud'] ?? null,
            $datos['longitud'] ?? null,
            $datos['campana_id'] ?? null,
            $datos['metodo_captura'] ?? 'manual',
            $datos['observaciones'] ?? null,
            $datos['validado'] ?? 0,
            $id
        ];
        
        return $this->db->execute($sql, $params);
    }
    
    /**
     * Elimina un simpatizante
     */
    public function eliminar($id) {
        $sql = "DELETE FROM simpatizantes WHERE id = ?";
        return $this->db->execute($sql, [$id]);
    }
    
    /**
     * Verifica si existe un duplicado
     */
    public function verificarDuplicado($datos, $excludeId = null) {
        $where = [];
        $params = [];
        
        if (!empty($datos['clave_elector'])) {
            $where[] = "clave_elector = ?";
            $params[] = $datos['clave_elector'];
        }
        
        if (!empty($datos['curp'])) {
            if (!empty($where)) {
                $where[] = "OR curp = ?";
            } else {
                $where[] = "curp = ?";
            }
            $params[] = $datos['curp'];
        }
        
        if (empty($where)) {
            return false;
        }
        
        $whereClause = implode(' ', $where);
        
        if ($excludeId) {
            $sql = "SELECT COUNT(*) as count FROM simpatizantes WHERE ($whereClause) AND id != ?";
            $params[] = $excludeId;
        } else {
            $sql = "SELECT COUNT(*) as count FROM simpatizantes WHERE $whereClause";
        }
        
        $result = $this->db->queryOne($sql, $params);
        return $result['count'] > 0;
    }
    
    /**
     * Obtiene datos para mapa de calor
     */
    public function obtenerParaMapaCalor($filtros = []) {
        $where = [];
        $params = [];
        
        if (!empty($filtros['campana_id'])) {
            $where[] = "campana_id = ?";
            $params[] = $filtros['campana_id'];
        }
        
        if (!empty($filtros['fecha_inicio'])) {
            $where[] = "DATE(created_at) >= ?";
            $params[] = $filtros['fecha_inicio'];
        }
        
        if (!empty($filtros['fecha_fin'])) {
            $where[] = "DATE(created_at) <= ?";
            $params[] = $filtros['fecha_fin'];
        }
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $whereClause .= !empty($whereClause) ? ' AND ' : 'WHERE ';
        $whereClause .= 'latitud IS NOT NULL AND longitud IS NOT NULL';
        
        $sql = "SELECT latitud, longitud, nombre_completo, seccion_electoral, created_at 
                FROM simpatizantes 
                $whereClause";
        
        return $this->db->query($sql, $params);
    }
    
    /**
     * Obtiene estadísticas por sección electoral
     */
    public function obtenerEstadisticasPorSeccion($filtros = []) {
        $where = [];
        $params = [];
        
        if (!empty($filtros['campana_id'])) {
            $where[] = "campana_id = ?";
            $params[] = $filtros['campana_id'];
        }
        
        if (!empty($filtros['fecha_inicio'])) {
            $where[] = "DATE(created_at) >= ?";
            $params[] = $filtros['fecha_inicio'];
        }
        
        if (!empty($filtros['fecha_fin'])) {
            $where[] = "DATE(created_at) <= ?";
            $params[] = $filtros['fecha_fin'];
        }
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $sql = "SELECT seccion_electoral, COUNT(*) as total 
                FROM simpatizantes 
                $whereClause
                GROUP BY seccion_electoral
                ORDER BY total DESC";
        
        return $this->db->query($sql, $params);
    }
    
    /**
     * Obtiene estadísticas por capturista
     */
    public function obtenerEstadisticasPorCapturista($filtros = []) {
        $where = [];
        $params = [];
        
        if (!empty($filtros['campana_id'])) {
            $where[] = "s.campana_id = ?";
            $params[] = $filtros['campana_id'];
        }
        
        if (!empty($filtros['fecha_inicio'])) {
            $where[] = "DATE(s.created_at) >= ?";
            $params[] = $filtros['fecha_inicio'];
        }
        
        if (!empty($filtros['fecha_fin'])) {
            $where[] = "DATE(s.created_at) <= ?";
            $params[] = $filtros['fecha_fin'];
        }
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $sql = "SELECT u.nombre_completo, COUNT(s.id) as total 
                FROM simpatizantes s
                INNER JOIN usuarios u ON s.capturista_id = u.id
                $whereClause
                GROUP BY s.capturista_id, u.nombre_completo
                ORDER BY total DESC";
        
        return $this->db->query($sql, $params);
    }
    
    /**
     * Verifica si existe un WhatsApp registrado
     */
    public function existeWhatsApp($whatsapp, $excludeId = null) {
        if ($excludeId) {
            $sql = "SELECT COUNT(*) as count FROM simpatizantes WHERE whatsapp = ? AND id != ?";
            $result = $this->db->queryOne($sql, [$whatsapp, $excludeId]);
        } else {
            $sql = "SELECT COUNT(*) as count FROM simpatizantes WHERE whatsapp = ?";
            $result = $this->db->queryOne($sql, [$whatsapp]);
        }
        
        return $result['count'] > 0;
    }
    
    /**
     * Verifica si existe un email registrado
     */
    public function existeEmail($email, $excludeId = null) {
        if ($excludeId) {
            $sql = "SELECT COUNT(*) as count FROM simpatizantes WHERE email = ? AND id != ?";
            $result = $this->db->queryOne($sql, [$email, $excludeId]);
        } else {
            $sql = "SELECT COUNT(*) as count FROM simpatizantes WHERE email = ?";
            $result = $this->db->queryOne($sql, [$email]);
        }
        
        return $result['count'] > 0;
    }
}
