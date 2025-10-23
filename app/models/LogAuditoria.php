<?php
/**
 * Modelo LogAuditoria
 * Maneja el registro de auditoría del sistema
 */

class LogAuditoria {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Registra una acción en el log de auditoría
     */
    public function registrar($usuarioId, $accion, $tablaAfectada = null, $registroId = null, 
                             $datosAnteriores = null, $datosNuevos = null) {
        $sql = "INSERT INTO logs_auditoria 
                (usuario_id, accion, tabla_afectada, registro_id, datos_anteriores, datos_nuevos, 
                 ip_address, user_agent) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $usuarioId,
            $accion,
            $tablaAfectada,
            $registroId,
            $datosAnteriores ? json_encode($datosAnteriores) : null,
            $datosNuevos ? json_encode($datosNuevos) : null,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ];
        
        return $this->db->execute($sql, $params);
    }
    
    /**
     * Obtiene logs de auditoría con filtros
     */
    public function obtenerLogs($filtros = [], $page = 1, $perPage = RECORDS_PER_PAGE) {
        $offset = ($page - 1) * $perPage;
        $where = [];
        $params = [];
        
        if (!empty($filtros['usuario_id'])) {
            $where[] = "l.usuario_id = ?";
            $params[] = $filtros['usuario_id'];
        }
        
        if (!empty($filtros['accion'])) {
            $where[] = "l.accion = ?";
            $params[] = $filtros['accion'];
        }
        
        if (!empty($filtros['tabla_afectada'])) {
            $where[] = "l.tabla_afectada = ?";
            $params[] = $filtros['tabla_afectada'];
        }
        
        if (!empty($filtros['fecha_inicio'])) {
            $where[] = "DATE(l.created_at) >= ?";
            $params[] = $filtros['fecha_inicio'];
        }
        
        if (!empty($filtros['fecha_fin'])) {
            $where[] = "DATE(l.created_at) <= ?";
            $params[] = $filtros['fecha_fin'];
        }
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $sql = "SELECT l.*, u.nombre_completo as usuario_nombre 
                FROM logs_auditoria l
                LEFT JOIN usuarios u ON l.usuario_id = u.id
                $whereClause
                ORDER BY l.created_at DESC 
                LIMIT ? OFFSET ?";
        
        $params[] = $perPage;
        $params[] = $offset;
        
        return $this->db->query($sql, $params);
    }
    
    /**
     * Cuenta total de logs
     */
    public function contarTotal($filtros = []) {
        $where = [];
        $params = [];
        
        if (!empty($filtros['usuario_id'])) {
            $where[] = "usuario_id = ?";
            $params[] = $filtros['usuario_id'];
        }
        
        if (!empty($filtros['accion'])) {
            $where[] = "accion = ?";
            $params[] = $filtros['accion'];
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
        
        $sql = "SELECT COUNT(*) as total FROM logs_auditoria $whereClause";
        $result = $this->db->queryOne($sql, $params);
        return $result['total'] ?? 0;
    }
}
