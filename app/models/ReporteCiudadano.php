<?php
/**
 * Modelo ReporteCiudadano: reportes de problemas y denuncias que la ciudadanía envía desde la app.
 *
 * Cada reporte tiene un folio y un historial de estados con el mensaje que ve la persona.
 * Las denuncias pueden ser anónimas: no se guarda quién las envió; quien las hizo conserva el folio y un
 * código (solo se guarda su hash) con el que puede consultar el avance sin tener cuenta.
 */

class ReporteCiudadano {
    const CATEGORIAS = [
        'baches' => 'Baches', 'alumbrado' => 'Alumbrado', 'agua' => 'Agua y drenaje', 'basura' => 'Basura',
        'seguridad' => 'Seguridad', 'otro' => 'Otro', 'denuncia' => 'Denuncia',
    ];
    const ESTADOS = ['recibido' => 'Recibido', 'revision' => 'En revisión', 'atendido' => 'Atendido', 'cerrado' => 'Cerrado'];
    const MAX_FOTOS = 3;
    const MAX_POR_DIA_CIUDADANO = 20;
    const MAX_POR_DIA_IP = 10;

    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    // ------------------------------------------------------------------ alta

    /**
     * @param int|null $ciudadanoId  quién lo envía (null si es anónimo)
     * @return array ['success'=>true,'id'=>n,'folio'=>texto,'codigo'=>texto|null] o ['error'=>texto]
     */
    public function crear($ciudadanoId, array $d, $ip = null) {
        $categoria = (string)($d['categoria'] ?? '');
        if (!isset(self::CATEGORIAS[$categoria])) { return ['error' => 'Elija qué desea reportar.']; }
        $tipo = $categoria === 'denuncia' ? 'denuncia' : 'reporte';
        $descripcion = trim((string)($d['descripcion'] ?? ''));
        if (mb_strlen($descripcion) < 6) { return ['error' => 'Cuéntenos un poco más de lo que pasa.']; }
        if (mb_strlen($descripcion) > 2000) { return ['error' => 'La descripción es demasiado larga.']; }

        // Solo las denuncias pueden ser anónimas
        $anonimo = $tipo === 'denuncia' && !empty($d['anonimo']);
        if (!$anonimo && !$ciudadanoId) { return ['error' => 'Inicie sesión para enviar el reporte.']; }

        $lat = $lng = null;
        if (isset($d['latitud'], $d['longitud']) && is_numeric($d['latitud']) && is_numeric($d['longitud'])
            && abs((float)$d['latitud']) <= 90 && abs((float)$d['longitud']) <= 180 && !$anonimo) {
            $lat = round((float)$d['latitud'], 7);
            $lng = round((float)$d['longitud'], 7);
        }

        // Límites contra abuso
        if ($ciudadanoId) {
            $r = $this->db->queryOne("SELECT COUNT(*) AS n FROM reportes_ciudadanos WHERE ciudadano_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 DAY)", [(int)$ciudadanoId]);
            if ((int)($r['n'] ?? 0) >= self::MAX_POR_DIA_CIUDADANO) { return ['error' => 'Ya envió muchos reportes hoy. Intente mañana.']; }
        } elseif ($ip) {
            $r = $this->db->queryOne("SELECT COUNT(*) AS n FROM reportes_ciudadanos WHERE ip_address = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 DAY)", [$ip]);
            if ((int)($r['n'] ?? 0) >= self::MAX_POR_DIA_IP) { return ['error' => 'Se enviaron demasiados reportes desde esta conexión. Intente mañana.']; }
        }

        $codigo = $anonimo ? strtoupper(bin2hex(random_bytes(4))) : null;
        $texto = function ($v, $max) { $v = trim((string)$v); return $v === '' ? null : mb_substr($v, 0, $max); };

        $this->db->beginTransaction();
        $ok = $this->db->execute(
            "INSERT INTO reportes_ciudadanos (ciudadano_id, tipo, categoria, descripcion, anonimo, codigo_hash, latitud, longitud, municipio, colonia, ip_address)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [$anonimo ? null : (int)$ciudadanoId, $tipo, $categoria, $descripcion, $anonimo ? 1 : 0, $codigo ? hash('sha256', $codigo) : null,
             $lat, $lng, $anonimo ? null : $texto($d['municipio'] ?? '', 100), $anonimo ? null : $texto($d['colonia'] ?? '', 100), $anonimo ? null : $ip]
        );
        if (!$ok) { $this->db->rollback(); return ['error' => 'No se pudo guardar el reporte. Intente de nuevo.']; }
        $id = (int)$this->db->lastInsertId();
        $folio = 'CC-' . str_pad((string)$id, 5, '0', STR_PAD_LEFT);
        $ok = $this->db->execute("UPDATE reportes_ciudadanos SET folio = ? WHERE id = ?", [$folio, $id])
           && $this->db->execute("INSERT INTO reporte_historial (reporte_id, estado_anterior, estado_nuevo, mensaje) VALUES (?, NULL, 'recibido', ?)", [$id, 'Tu reporte llegó al equipo.']);
        if (!$ok) { $this->db->rollback(); return ['error' => 'No se pudo guardar el reporte. Intente de nuevo.']; }
        $this->db->commit();

        return ['success' => true, 'id' => $id, 'folio' => $folio, 'codigo' => $codigo];
    }

    // ------------------------------------------------------------------ consulta (ciudadanía)

    public function obtenerPorFolio($folio) {
        $r = $this->db->queryOne("SELECT * FROM reportes_ciudadanos WHERE folio = ?", [(string)$folio]);
        return $r ?: null;
    }

    public function obtener($id) {
        $r = $this->db->queryOne("SELECT * FROM reportes_ciudadanos WHERE id = ?", [(int)$id]);
        return $r ?: null;
    }

    /** ¿El código corresponde al de este reporte anónimo? */
    public function codigoValido(array $reporte, $codigo) {
        return !empty($reporte['codigo_hash']) && is_string($codigo) && hash_equals($reporte['codigo_hash'], hash('sha256', strtoupper(trim($codigo))));
    }

    public function listarDe($ciudadanoId) {
        return $this->db->query(
            "SELECT r.id, r.folio, r.tipo, r.categoria, r.descripcion, r.estado, r.anonimo, r.created_at, r.updated_at,
                    (SELECT COUNT(*) FROM reporte_archivos a WHERE a.reporte_id = r.id) AS fotos
             FROM reportes_ciudadanos r WHERE r.ciudadano_id = ? ORDER BY r.created_at DESC, r.id DESC LIMIT 100",
            [(int)$ciudadanoId]
        ) ?: [];
    }

    /** Historial visible para la ciudadanía (sin nombres del equipo). */
    public function historialPublico($reporteId) {
        return $this->db->query(
            "SELECT estado_nuevo AS estado, mensaje, created_at FROM reporte_historial WHERE reporte_id = ? ORDER BY created_at ASC, id ASC",
            [(int)$reporteId]
        ) ?: [];
    }

    public function archivos($reporteId) {
        return $this->db->query("SELECT id, ruta, mime, tamano FROM reporte_archivos WHERE reporte_id = ? ORDER BY id", [(int)$reporteId]) ?: [];
    }

    public function archivo($id) {
        return $this->db->queryOne("SELECT a.*, r.ciudadano_id, r.codigo_hash FROM reporte_archivos a INNER JOIN reportes_ciudadanos r ON r.id = a.reporte_id WHERE a.id = ?", [(int)$id]) ?: null;
    }

    public function contarFotos($reporteId) {
        $r = $this->db->queryOne("SELECT COUNT(*) AS n FROM reporte_archivos WHERE reporte_id = ?", [(int)$reporteId]);
        return (int)($r['n'] ?? 0);
    }

    public function agregarFoto($reporteId, $ruta, $mime, $tamano) {
        $ok = $this->db->execute("INSERT INTO reporte_archivos (reporte_id, ruta, mime, tamano) VALUES (?, ?, ?, ?)", [(int)$reporteId, $ruta, $mime, (int)$tamano]);
        return $ok ? (int)$this->db->lastInsertId() : 0;
    }

    // ------------------------------------------------------------------ atención (equipo)

    /**
     * Cambia el estado y deja el mensaje que verá la ciudadanía.
     * @return array ['success'=>true] o ['error'=>texto]
     */
    public function cambiarEstado($id, $estado, $mensaje, $usuarioId) {
        if (!isset(self::ESTADOS[$estado])) { return ['error' => 'Estado no válido']; }
        $mensaje = trim((string)$mensaje);
        if (mb_strlen($mensaje) > 500) { return ['error' => 'El mensaje no puede pasar de 500 caracteres']; }
        $actual = $this->obtener($id);
        if (!$actual) { return ['error' => 'Reporte no encontrado']; }
        if ($actual['estado'] === $estado && $mensaje === '') { return ['error' => 'El reporte ya está en ese estado']; }

        $this->db->beginTransaction();
        $ok = $this->db->execute(
            "UPDATE reportes_ciudadanos SET estado = ?, atendido_at = ? WHERE id = ?",
            [$estado, in_array($estado, ['atendido', 'cerrado'], true) ? date('Y-m-d H:i:s') : null, (int)$id]
        ) && $this->db->execute(
            "INSERT INTO reporte_historial (reporte_id, estado_anterior, estado_nuevo, mensaje, usuario_id) VALUES (?, ?, ?, ?, ?)",
            [(int)$id, $actual['estado'], $estado, $mensaje !== '' ? $mensaje : null, $usuarioId ?: null]
        );
        if (!$ok) { $this->db->rollback(); return ['error' => 'No se pudo actualizar el reporte']; }
        $this->db->commit();
        return ['success' => true, 'anterior' => $actual['estado']];
    }

    public function asignar($id, $usuarioId) {
        return $this->db->execute("UPDATE reportes_ciudadanos SET asignado_a = ? WHERE id = ?", [$usuarioId ?: null, (int)$id]);
    }

    private function condiciones(array $f) {
        $w = [];
        $p = [];
        foreach (['estado', 'categoria', 'tipo'] as $c) {
            if (!empty($f[$c])) { $w[] = "r.$c = ?"; $p[] = $f[$c]; }
        }
        if (!empty($f['busqueda'])) {
            $w[] = "(r.folio LIKE ? OR r.descripcion LIKE ? OR r.colonia LIKE ?)";
            $b = '%' . addcslashes($f['busqueda'], '%_\\') . '%';
            array_push($p, $b, $b, $b);
        }
        if (!empty($f['abiertos'])) { $w[] = "r.estado IN ('recibido', 'revision')"; }
        return [$w ? 'WHERE ' . implode(' AND ', $w) : '', $p];
    }

    public function listar(array $f, $pagina = 1, $porPagina = 25) {
        [$w, $p] = $this->condiciones($f);
        return $this->db->query(
            "SELECT r.*, c.nombre AS ciudadano_nombre, u.nombre_completo AS asignado_nombre,
                    (SELECT COUNT(*) FROM reporte_archivos a WHERE a.reporte_id = r.id) AS fotos
             FROM reportes_ciudadanos r
             LEFT JOIN ciudadanos c ON c.id = r.ciudadano_id
             LEFT JOIN usuarios u ON u.id = r.asignado_a
             $w ORDER BY FIELD(r.estado, 'recibido', 'revision', 'atendido', 'cerrado'), r.created_at DESC
             LIMIT " . (int)$porPagina . " OFFSET " . (int)(max(1, $pagina) - 1) * (int)$porPagina, $p
        ) ?: [];
    }

    public function contar(array $f) {
        [$w, $p] = $this->condiciones($f);
        $r = $this->db->queryOne("SELECT COUNT(*) AS n FROM reportes_ciudadanos r $w", $p);
        return (int)($r['n'] ?? 0);
    }

    /** Conteo por estado y antigüedad de los abiertos, para el resumen de la bandeja. */
    public function resumen() {
        $r = ['recibido' => 0, 'revision' => 0, 'atendido' => 0, 'cerrado' => 0];
        foreach ($this->db->query("SELECT estado, COUNT(*) AS n FROM reportes_ciudadanos GROUP BY estado") ?: [] as $f) { $r[$f['estado']] = (int)$f['n']; }
        $v = $this->db->queryOne("SELECT COUNT(*) AS n FROM reportes_ciudadanos WHERE estado IN ('recibido', 'revision') AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $r['vencidos'] = (int)($v['n'] ?? 0);
        return $r;
    }

    public function detalle($id) {
        $r = $this->db->queryOne(
            "SELECT r.*, c.nombre AS ciudadano_nombre, c.correo AS ciudadano_correo, u.nombre_completo AS asignado_nombre
             FROM reportes_ciudadanos r LEFT JOIN ciudadanos c ON c.id = r.ciudadano_id LEFT JOIN usuarios u ON u.id = r.asignado_a
             WHERE r.id = ?", [(int)$id]
        );
        return $r ?: null;
    }

    public function historialCompleto($id) {
        return $this->db->query(
            "SELECT h.*, u.nombre_completo AS usuario_nombre FROM reporte_historial h LEFT JOIN usuarios u ON u.id = h.usuario_id
             WHERE h.reporte_id = ? ORDER BY h.created_at DESC, h.id DESC", [(int)$id]
        ) ?: [];
    }
}
