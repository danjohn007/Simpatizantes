<?php
/**
 * Modelo Campaña
 * Maneja operaciones relacionadas con campañas
 */

class Campana {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Obtiene campaña por ID
     */
    public function obtenerPorId($id) {
        $sql = "SELECT c.*, u.nombre_completo as candidato_nombre 
                FROM campanas c
                LEFT JOIN usuarios u ON c.candidato_id = u.id
                WHERE c.id = ?";
        return $this->db->queryOne($sql, [$id]);
    }
    
    /**
     * Obtiene todas las campañas
     */
    public function obtenerTodas($activa = null) {
        if ($activa !== null) {
            $sql = "SELECT c.*, u.nombre_completo as candidato_nombre 
                    FROM campanas c
                    LEFT JOIN usuarios u ON c.candidato_id = u.id
                    WHERE c.activa = ?
                    ORDER BY c.fecha_inicio DESC";
            return $this->db->query($sql, [$activa]);
        } else {
            $sql = "SELECT c.*, u.nombre_completo as candidato_nombre 
                    FROM campanas c
                    LEFT JOIN usuarios u ON c.candidato_id = u.id
                    ORDER BY c.fecha_inicio DESC";
            return $this->db->query($sql);
        }
    }
    
    /**
     * Crea una nueva campaña
     */
    public function crear($datos) {
        $sql = "INSERT INTO campanas (nombre, descripcion, fecha_inicio, fecha_fin, candidato_id, activa) 
                VALUES (?, ?, ?, ?, ?, ?)";
        
        $params = [
            $datos['nombre'],
            $datos['descripcion'] ?? null,
            $datos['fecha_inicio'],
            $datos['fecha_fin'],
            $datos['candidato_id'] ?? null,
            $datos['activa'] ?? 1
        ];
        
        if ($this->db->execute($sql, $params)) {
            return ['success' => true, 'id' => $this->db->lastInsertId()];
        }
        
        return ['error' => 'Error al crear la campaña'];
    }
    
    /**
     * Actualiza una campaña
     */
    public function actualizar($id, $datos) {
        $sql = "UPDATE campanas SET 
                nombre = ?, descripcion = ?, fecha_inicio = ?, fecha_fin = ?, 
                candidato_id = ?, activa = ?
                WHERE id = ?";
        
        $params = [
            $datos['nombre'],
            $datos['descripcion'] ?? null,
            $datos['fecha_inicio'],
            $datos['fecha_fin'],
            $datos['candidato_id'] ?? null,
            $datos['activa'] ?? 1,
            $id
        ];
        
        return $this->db->execute($sql, $params);
    }
    
    /**
     * Elimina una campaña
     */
    public function eliminar($id) {
        $sql = "DELETE FROM campanas WHERE id = ?";
        return $this->db->execute($sql, [$id]);
    }
    
    /**
     * Obtiene estadísticas de una campaña
     */
    public function obtenerEstadisticas($campanaId) {
        $sql = "SELECT 
                    COUNT(DISTINCT s.id) as total_simpatizantes,
                    COUNT(DISTINCT s.capturista_id) as total_capturistas,
                    COUNT(DISTINCT s.seccion_electoral) as total_secciones,
                    SUM(CASE WHEN s.validado = 1 THEN 1 ELSE 0 END) as total_validados
                FROM simpatizantes s
                WHERE s.campana_id = ?";
        
        return $this->db->queryOne($sql, [$campanaId]);
    }
}
