<?php
/**
 * Modelo Campo: metas de captura y zonas (secciones) asignadas a capturistas.
 */

class Campo {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /** ¿Se ejecutó la migración del Sprint 3 (tablas de metas y zonas)? */
    public function disponible() {
        static $ok = null;
        if ($ok === null) {
            $r = $this->db->queryOne(
                "SELECT COUNT(*) AS n FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('metas_captura', 'asignaciones_seccion')"
            );
            $ok = isset($r['n']) && (int)$r['n'] === 2;
        }
        return $ok;
    }

    // ------------------------------------------------------------------ metas

    /** Subconsulta que cuenta lo capturado dentro de la meta (periodo, capturista y sección). */
    private function conteo($soloValidados) {
        return "(SELECT COUNT(*) FROM simpatizantes s
                  WHERE s.campana_id = m.campana_id
                    AND s.created_at >= m.fecha_inicio
                    AND s.created_at < DATE_ADD(m.fecha_fin, INTERVAL 1 DAY)
                    AND (m.usuario_id IS NULL OR s.capturista_id = m.usuario_id)
                    AND (m.seccion_electoral IS NULL OR s.seccion_electoral = m.seccion_electoral)"
             . ($soloValidados ? " AND s.validado = 1" : "") . ")";
    }

    /**
     * Metas con su avance.
     * Filtros: campana_id, usuario_id, vigentes (solo las que no han terminado).
     */
    public function metas($filtros = []) {
        if (!$this->disponible()) { return []; }
        $where = [];
        $params = [];
        if (!empty($filtros['campana_id'])) { $where[] = "m.campana_id = ?"; $params[] = (int)$filtros['campana_id']; }
        if (!empty($filtros['usuario_id'])) { $where[] = "m.usuario_id = ?"; $params[] = (int)$filtros['usuario_id']; }
        if (!empty($filtros['vigentes']))   { $where[] = "m.fecha_fin >= CURDATE()"; }

        $sql = "SELECT m.*, c.nombre AS campana_nombre, u.nombre_completo AS capturista_nombre,
                       " . $this->conteo(false) . " AS registrados,
                       " . $this->conteo(true) . " AS validados
                FROM metas_captura m
                LEFT JOIN campanas c ON c.id = m.campana_id
                LEFT JOIN usuarios u ON u.id = m.usuario_id
                " . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . "
                ORDER BY m.fecha_fin DESC, m.id DESC";
        return $this->db->query($sql, $params) ?: [];
    }

    public function obtenerMeta($id) {
        $r = $this->db->queryOne("SELECT * FROM metas_captura WHERE id = ?", [(int)$id]);
        return $r ?: null;
    }

    /** @return array ['success'=>true,'id'=>n] o ['error'=>texto] */
    public function crearMeta($datos) {
        $ok = $this->db->execute(
            "INSERT INTO metas_captura (campana_id, usuario_id, seccion_electoral, objetivo, fecha_inicio, fecha_fin, creada_por)
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            [$datos['campana_id'], $datos['usuario_id'] ?: null, $datos['seccion_electoral'] ?: null,
             $datos['objetivo'], $datos['fecha_inicio'], $datos['fecha_fin'], $datos['creada_por'] ?? null]
        );
        return $ok ? ['success' => true, 'id' => $this->db->lastInsertId()] : ['error' => $this->db->describirError('crear la meta')];
    }

    public function eliminarMeta($id) {
        return $this->db->execute("DELETE FROM metas_captura WHERE id = ?", [(int)$id]);
    }

    // ------------------------------------------------------------------ zonas

    /** Capturistas activos (de una campaña si se indica). */
    public function capturistas($campanaId = null) {
        $sql = "SELECT id, nombre_completo, campana_id FROM usuarios WHERE rol = 'capturista' AND (activo = 1 OR activo IS NULL)";
        $params = [];
        if ($campanaId) { $sql .= " AND campana_id = ?"; $params[] = (int)$campanaId; }
        return $this->db->query($sql . " ORDER BY nombre_completo", $params) ?: [];
    }

    /** Secciones asignadas a un usuario. */
    public function seccionesDe($usuarioId) {
        if (!$this->disponible()) { return []; }
        $filas = $this->db->query(
            "SELECT seccion_electoral FROM asignaciones_seccion WHERE usuario_id = ? ORDER BY seccion_electoral",
            [(int)$usuarioId]
        ) ?: [];
        return array_column($filas, 'seccion_electoral');
    }

    /** Todas las asignaciones (de una campaña si se indica), con el nombre del capturista. */
    public function asignaciones($campanaId = null) {
        if (!$this->disponible()) { return []; }
        $sql = "SELECT a.id, a.usuario_id, a.seccion_electoral, u.nombre_completo
                FROM asignaciones_seccion a
                INNER JOIN usuarios u ON u.id = a.usuario_id";
        $params = [];
        if ($campanaId) { $sql .= " WHERE u.campana_id = ?"; $params[] = (int)$campanaId; }
        return $this->db->query($sql . " ORDER BY u.nombre_completo, a.seccion_electoral", $params) ?: [];
    }

    /** Asigna varias secciones (las que ya estaban se ignoran). Devuelve cuántas se agregaron. */
    public function asignar($usuarioId, array $secciones, $asignadaPor) {
        $agregadas = 0;
        foreach (array_unique($secciones) as $sec) {
            if ($this->db->execute(
                "INSERT IGNORE INTO asignaciones_seccion (usuario_id, seccion_electoral, asignada_por) VALUES (?, ?, ?)",
                [(int)$usuarioId, $sec, $asignadaPor]
            )) { $agregadas++; }
        }
        return $agregadas;
    }

    public function obtenerAsignacion($id) {
        $r = $this->db->queryOne(
            "SELECT a.*, u.campana_id FROM asignaciones_seccion a INNER JOIN usuarios u ON u.id = a.usuario_id WHERE a.id = ?",
            [(int)$id]
        );
        return $r ?: null;
    }

    public function quitarAsignacion($id) {
        return $this->db->execute("DELETE FROM asignaciones_seccion WHERE id = ?", [(int)$id]);
    }

    /** Usuario (para validar a qué campaña pertenece antes de asignarle zonas o metas). */
    public function usuario($id) {
        $r = $this->db->queryOne("SELECT id, nombre_completo, rol, campana_id FROM usuarios WHERE id = ?", [(int)$id]);
        return $r ?: null;
    }
}
