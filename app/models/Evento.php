<?php
/**
 * Modelo Evento: eventos y avisos por zona que ve la ciudadanía en la app.
 * Un evento sin municipio ni colonia es para todos; con municipio o colonia, solo para esa zona.
 */

class Evento {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    // ------------------------------------------------------------------ ciudadanía

    /** Próximos eventos publicados para la zona de la persona, con cuántos asistirán y si ella ya confirmó. */
    public function proximos($municipio, $colonia, $ciudadanoId) {
        return $this->db->query(
            "SELECT e.id, e.titulo, e.descripcion, e.lugar, e.fecha_inicio, e.fecha_fin, e.municipio, e.colonia,
                    (SELECT COUNT(*) FROM evento_asistencia a WHERE a.evento_id = e.id) AS asistentes,
                    EXISTS (SELECT 1 FROM evento_asistencia a WHERE a.evento_id = e.id AND a.ciudadano_id = ?) AS asisto
             FROM eventos e
             WHERE e.publicado = 1 AND e.fecha_inicio >= DATE_SUB(NOW(), INTERVAL 3 HOUR)
               AND (e.municipio IS NULL OR e.municipio = ?) AND (e.colonia IS NULL OR e.colonia = ?)
             ORDER BY e.fecha_inicio ASC LIMIT 50",
            [(int)$ciudadanoId, (string)$municipio, (string)$colonia]
        ) ?: [];
    }

    /** Confirma o cancela la asistencia. Devuelve true si ahora asiste. */
    public function alternarAsistencia($eventoId, $ciudadanoId) {
        $e = $this->db->queryOne("SELECT id FROM eventos WHERE id = ? AND publicado = 1", [(int)$eventoId]);
        if (!$e) { return null; }
        $ya = $this->db->queryOne("SELECT id FROM evento_asistencia WHERE evento_id = ? AND ciudadano_id = ?", [(int)$eventoId, (int)$ciudadanoId]);
        if ($ya) {
            $this->db->execute("DELETE FROM evento_asistencia WHERE id = ?", [(int)$ya['id']]);
            return false;
        }
        $this->db->execute("INSERT IGNORE INTO evento_asistencia (evento_id, ciudadano_id) VALUES (?, ?)", [(int)$eventoId, (int)$ciudadanoId]);
        return true;
    }

    public function avisosVigentes($municipio, $colonia) {
        return $this->db->query(
            "SELECT id, titulo, texto, created_at FROM avisos
             WHERE publicado = 1 AND (vence_at IS NULL OR vence_at > NOW())
               AND (municipio IS NULL OR municipio = ?) AND (colonia IS NULL OR colonia = ?)
             ORDER BY created_at DESC LIMIT 10",
            [(string)$municipio, (string)$colonia]
        ) ?: [];
    }

    // ------------------------------------------------------------------ equipo

    public function todos() {
        return $this->db->query(
            "SELECT e.*, (SELECT COUNT(*) FROM evento_asistencia a WHERE a.evento_id = e.id) AS asistentes
             FROM eventos e ORDER BY e.fecha_inicio DESC LIMIT 100"
        ) ?: [];
    }

    /** @return array ['success'=>true,'id'=>n] o ['error'=>texto] */
    public function crear(array $d, $usuarioId) {
        $titulo = trim((string)($d['titulo'] ?? ''));
        $lugar = trim((string)($d['lugar'] ?? ''));
        if ($titulo === '' || mb_strlen($titulo) > 150) { return ['error' => 'Escriba el título del evento (máximo 150 caracteres).']; }
        if ($lugar === '' || mb_strlen($lugar) > 200) { return ['error' => 'Escriba el lugar del evento.']; }
        $inicio = DateTime::createFromFormat('Y-m-d\TH:i', (string)($d['fecha_inicio'] ?? ''));
        if (!$inicio) { return ['error' => 'Indique la fecha y la hora de inicio.']; }
        $texto = function ($v, $max) { $v = trim((string)$v); return $v === '' ? null : mb_substr($v, 0, $max); };
        $ok = $this->db->execute(
            "INSERT INTO eventos (titulo, descripcion, lugar, fecha_inicio, municipio, colonia, publicado, creado_por) VALUES (?, ?, ?, ?, ?, ?, 1, ?)",
            [$titulo, $texto($d['descripcion'] ?? '', 2000), $lugar, $inicio->format('Y-m-d H:i:00'), $texto($d['municipio'] ?? '', 100), $texto($d['colonia'] ?? '', 100), $usuarioId]
        );
        return $ok ? ['success' => true, 'id' => (int)$this->db->lastInsertId()] : ['error' => 'No se pudo guardar el evento.'];
    }

    public function publicar($id, $publicado) {
        return $this->db->execute("UPDATE eventos SET publicado = ? WHERE id = ?", [$publicado ? 1 : 0, (int)$id]);
    }

    public function eliminar($id) {
        return $this->db->execute("DELETE FROM evento_asistencia WHERE evento_id = ?", [(int)$id])
            && $this->db->execute("DELETE FROM eventos WHERE id = ?", [(int)$id]);
    }

    public function avisos() {
        return $this->db->query("SELECT * FROM avisos ORDER BY created_at DESC LIMIT 50") ?: [];
    }

    public function crearAviso(array $d, $usuarioId) {
        $titulo = trim((string)($d['titulo'] ?? ''));
        $texto = trim((string)($d['texto'] ?? ''));
        if ($titulo === '' || mb_strlen($titulo) > 150) { return ['error' => 'Escriba el título del aviso (máximo 150 caracteres).']; }
        if ($texto === '' || mb_strlen($texto) > 500) { return ['error' => 'Escriba el texto del aviso (máximo 500 caracteres).']; }
        $t = function ($v, $max) { $v = trim((string)$v); return $v === '' ? null : mb_substr($v, 0, $max); };
        $vence = DateTime::createFromFormat('Y-m-d', (string)($d['vence'] ?? ''));
        $ok = $this->db->execute(
            "INSERT INTO avisos (titulo, texto, municipio, colonia, vence_at, creado_por) VALUES (?, ?, ?, ?, ?, ?)",
            [$titulo, $texto, $t($d['municipio'] ?? '', 100), $t($d['colonia'] ?? '', 100), $vence ? $vence->format('Y-m-d 23:59:59') : null, $usuarioId]
        );
        return $ok ? ['success' => true] : ['error' => 'No se pudo guardar el aviso.'];
    }

    public function eliminarAviso($id) {
        return $this->db->execute("DELETE FROM avisos WHERE id = ?", [(int)$id]);
    }
}
