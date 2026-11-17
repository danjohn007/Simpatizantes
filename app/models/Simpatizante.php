<?php
/**
 * Modelo Simpatizante
 * Maneja operaciones relacionadas con simpatizantes
 */

require_once __DIR__ . '/../helpers/validacion.php';
require_once __DIR__ . '/../services/CacheService.php';

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
    
    /** Temas que una persona puede pedir mejorar en su colonia (clave => etiqueta). */
    const TEMAS = [
        'agua' => 'Agua', 'drenaje' => 'Drenaje', 'seguridad' => 'Seguridad', 'alumbrado' => 'Alumbrado',
        'baches' => 'Baches y calles', 'basura' => 'Basura', 'transporte' => 'Transporte', 'salud' => 'Salud',
        'educacion' => 'Educación', 'empleo' => 'Empleo', 'parques' => 'Parques y deporte', 'otro' => 'Otro',
    ];

    /** ¿Existe la tabla de temas? (migración database/app_movil.sql) */
    public function tieneTemas() {
        static $ok = null;
        if ($ok === null) {
            $r = $this->db->queryOne("SELECT COUNT(*) AS n FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'simpatizante_temas'");
            $ok = !empty($r['n']);
        }
        return $ok;
    }

    /** Guarda los temas de colonia de un simpatizante (solo claves válidas, sin repetir). */
    public function guardarTemas($id, array $temas) {
        if (!$this->tieneTemas()) { return 0; }
        $validos = array_values(array_unique(array_filter(array_map('strval', $temas), function ($t) { return isset(self::TEMAS[$t]); })));
        foreach ($validos as $t) {
            $this->db->execute("INSERT IGNORE INTO simpatizante_temas (simpatizante_id, tema) VALUES (?, ?)", [(int)$id, $t]);
        }
        return count($validos);
    }

    /** Claves de los temas de un simpatizante. */
    public function temasDe($id) {
        if (!$this->tieneTemas()) { return []; }
        return array_column($this->db->query("SELECT tema FROM simpatizante_temas WHERE simpatizante_id = ? ORDER BY tema", [(int)$id]) ?: [], 'tema');
    }

    /** Estados de validación permitidos. */
    const ESTATUS = ['pendiente', 'validado', 'rechazado'];

    /** ¿Existe la columna en `simpatizantes`? (tolera que falte una migración). Se consulta una vez por columna. */
    public function tieneColumna($columna) {
        static $cache = [];
        if (!array_key_exists($columna, $cache)) {
            $r = $this->db->queryOne(
                "SELECT COUNT(*) AS n FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'simpatizantes' AND COLUMN_NAME = ?",
                [$columna]
            );
            $cache[$columna] = !empty($r['n']);
        }
        return $cache[$columna];
    }

    /** ¿Ya se ejecutó la migración del Sprint 2 (columna estatus_validacion)? */
    public function tieneEstatus() {
        return $this->tieneColumna('estatus_validacion');
    }

    /** Registro creado desde el celular con ese identificador (para no duplicar al reintentar). */
    public function obtenerPorUuid($uuid) {
        if (!$this->tieneColumna('uuid_cliente')) {
            return null;
        }
        return $this->db->queryOne("SELECT id FROM simpatizantes WHERE uuid_cliente = ?", [$uuid]) ?: null;
    }

    /**
     * Condiciones WHERE compartidas por el listado, el conteo y la exportación.
     * Devuelve [cláusula, parámetros]; la tabla siempre se usa con el alias "s".
     */
    public function condiciones($filtros) {
        $where = [];
        $params = [];

        foreach (['campana_id', 'capturista_id', 'seccion_electoral', 'metodo_captura', 'ciudad'] as $campo) {
            if (!empty($filtros[$campo])) {
                $where[] = "s.$campo = ?";
                $params[] = $filtros[$campo];
            }
        }

        if (!empty($filtros['estatus']) && in_array($filtros['estatus'], self::ESTATUS, true)) {
            if ($this->tieneEstatus()) {
                $where[] = "s.estatus_validacion = ?";
                $params[] = $filtros['estatus'];
            } elseif ($filtros['estatus'] === 'validado') {
                $where[] = "s.validado = 1";
            } elseif ($filtros['estatus'] === 'pendiente') {
                $where[] = "(s.validado = 0 OR s.validado IS NULL)";
            } else {
                $where[] = "1 = 0"; // sin la migración no existen rechazados
            }
        }

        if (!empty($filtros['fecha_inicio'])) {
            $where[] = "s.created_at >= ?";
            $params[] = $filtros['fecha_inicio'] . ' 00:00:00';
        }
        if (!empty($filtros['fecha_fin'])) {
            $where[] = "s.created_at < DATE_ADD(?, INTERVAL 1 DAY)";
            $params[] = $filtros['fecha_fin'];
        }

        if (!empty($filtros['busqueda'])) {
            $where[] = "(s.nombre_completo LIKE ? OR s.clave_elector LIKE ? OR s.curp LIKE ? OR s.whatsapp LIKE ? OR s.telefono LIKE ? OR s.email LIKE ?)";
            $busqueda = '%' . addcslashes($filtros['busqueda'], '%_\\') . '%';
            for ($i = 0; $i < 6; $i++) { $params[] = $busqueda; }
        }

        return [!empty($where) ? 'WHERE ' . implode(' AND ', $where) : '', $params];
    }

    /**
     * Obtiene todos los simpatizantes con filtros y paginación
     */
    public function obtenerTodos($filtros = [], $page = 1, $perPage = RECORDS_PER_PAGE) {
        $offset = ($page - 1) * $perPage;
        [$whereClause, $params] = $this->condiciones($filtros);

        $sql = "SELECT s.*, c.nombre as campana_nombre, u.nombre_completo as capturista_nombre
                FROM simpatizantes s
                LEFT JOIN campanas c ON s.campana_id = c.id
                LEFT JOIN usuarios u ON s.capturista_id = u.id
                $whereClause
                ORDER BY s.created_at DESC, s.id DESC
                LIMIT " . (int)$perPage . " OFFSET " . (int)$offset;

        return $this->db->query($sql, $params);
    }

    /**
     * Todos los simpatizantes que cumplen los filtros (sin paginar), para exportar.
     */
    public function obtenerParaExportar($filtros = []) {
        [$whereClause, $params] = $this->condiciones($filtros);

        $sql = "SELECT s.*, c.nombre as campana_nombre, u.nombre_completo as capturista_nombre
                FROM simpatizantes s
                LEFT JOIN campanas c ON s.campana_id = c.id
                LEFT JOIN usuarios u ON s.capturista_id = u.id
                $whereClause
                ORDER BY s.created_at DESC, s.id DESC";

        return $this->db->query($sql, $params);
    }

    /**
     * Cuenta total de simpatizantes
     */
    public function contarTotal($filtros = []) {
        [$whereClause, $params] = $this->condiciones($filtros);
        $result = $this->db->queryOne("SELECT COUNT(*) as total FROM simpatizantes s $whereClause", $params);
        return $result['total'] ?? 0;
    }

    /**
     * Crea un nuevo simpatizante
     */
    public function crear($datos) {
        // Duplicados "duros": misma clave de elector, CURP, WhatsApp o correo
        foreach ($this->buscarDuplicados($datos) as $d) {
            if ($d['tipo'] === 'duro') {
                return ['error' => 'Ya existe un simpatizante con ' . self::describirCampos($d['campos'])];
            }
        }

        $conEstatus = $this->tieneEstatus();
        $estatus = (!empty($datos['validado'])) ? 'validado' : 'pendiente';

        $columnas = [
            'nombre_completo', 'domicilio_completo', 'sexo', 'ciudad', 'clave_elector', 'curp',
            'fecha_nacimiento', 'ano_registro', 'vigencia', 'seccion_electoral', 'telefono', 'whatsapp', 'email',
            'twitter', 'instagram', 'facebook', 'youtube', 'tiktok', 'latitud', 'longitud',
            'ine_frontal', 'ine_posterior', 'firma_digital', 'campana_id', 'capturista_id',
            'metodo_captura', 'observaciones', 'validado'
        ];
        $params = [];
        foreach ($columnas as $c) {
            if ($c === 'metodo_captura') {
                $params[] = $datos[$c] ?? 'manual';
            } elseif ($c === 'validado') {
                $params[] = (int)($datos[$c] ?? 0);
            } else {
                $v = $datos[$c] ?? null;
                $params[] = ($v === '') ? null : $v;
            }
        }
        if ($conEstatus) {
            $columnas[] = 'estatus_validacion';
            $params[] = $estatus;
        }
        foreach (['colonia', 'comentario_colonia'] as $extra) {
            if (!empty($datos[$extra]) && $this->tieneColumna($extra)) {
                $columnas[] = $extra;
                $params[] = trim((string)$datos[$extra]);
            }
        }
        if (!empty($datos['consentimiento']) && $this->tieneColumna('consentimiento')) {
            $columnas[] = 'consentimiento';
            $params[] = 1;
            $columnas[] = 'consentimiento_fecha';
            $params[] = date('Y-m-d H:i:s');
        }
        if (!empty($datos['uuid_cliente']) && $this->tieneColumna('uuid_cliente')) {
            $columnas[] = 'uuid_cliente';
            $params[] = $datos['uuid_cliente'];
        }

        $sql = "INSERT INTO simpatizantes (" . implode(', ', $columnas) . ") VALUES ("
             . implode(', ', array_fill(0, count($columnas), '?')) . ")";

        if ($this->db->execute($sql, $params)) {
            $id = $this->db->lastInsertId();
            CacheService::invalidar();
            if ($conEstatus && $estatus === 'validado') {
                $this->registrarHistorial($id, null, 'validado', 'Validado al capturar', $datos['capturista_id'] ?? null);
            }
            return ['success' => true, 'id' => $id];
        }

        return ['error' => $this->db->describirError('crear el simpatizante')];
    }

    /**
     * Actualiza un simpatizante. El estado de validación NO se cambia aquí:
     * solo con cambiarEstatus(), que deja historial y motivo.
     */
    public function actualizar($id, $datos) {
        $sql = "UPDATE simpatizantes SET
                nombre_completo = ?, domicilio_completo = ?, sexo = ?, ciudad = ?,
                clave_elector = ?, curp = ?, fecha_nacimiento = ?, ano_registro = ?,
                vigencia = ?, seccion_electoral = ?, telefono = ?, whatsapp = ?, email = ?,
                twitter = ?, instagram = ?, facebook = ?, youtube = ?, tiktok = ?,
                latitud = ?, longitud = ?, campana_id = ?, metodo_captura = ?,
                observaciones = ?,
                ine_frontal = COALESCE(?, ine_frontal),
                ine_posterior = COALESCE(?, ine_posterior),
                firma_digital = COALESCE(?, firma_digital)
                WHERE id = ?";

        $n = function ($v) { return ($v === '' || $v === null) ? null : $v; };
        $params = [
            $datos['nombre_completo'],
            $datos['domicilio_completo'],
            $n($datos['sexo'] ?? null),
            $n($datos['ciudad'] ?? null),
            $n($datos['clave_elector'] ?? null),
            $n($datos['curp'] ?? null),
            $n($datos['fecha_nacimiento'] ?? null),
            $n($datos['ano_registro'] ?? null),
            $n($datos['vigencia'] ?? null),
            $datos['seccion_electoral'],
            $n($datos['telefono'] ?? null),
            $n($datos['whatsapp'] ?? null),
            $n($datos['email'] ?? null),
            $n($datos['twitter'] ?? null),
            $n($datos['instagram'] ?? null),
            $n($datos['facebook'] ?? null),
            $n($datos['youtube'] ?? null),
            $n($datos['tiktok'] ?? null),
            $n($datos['latitud'] ?? null),
            $n($datos['longitud'] ?? null),
            $n($datos['campana_id'] ?? null),
            $datos['metodo_captura'] ?? 'manual',
            $n($datos['observaciones'] ?? null),
            $datos['ine_frontal'] ?? null,
            $datos['ine_posterior'] ?? null,
            $datos['firma_digital'] ?? null,
            $id
        ];

        $ok = $this->db->execute($sql, $params);
        if ($ok) { CacheService::invalidar(); }
        return $ok;
    }

    /**
     * Elimina un simpatizante (y su historial de validación)
     */
    public function eliminar($id) {
        $ok = $this->db->execute("DELETE FROM simpatizantes WHERE id = ?", [$id]);
        if ($ok) { CacheService::invalidar(); }
        if ($ok && $this->tieneEstatus()) {
            $this->db->execute("DELETE FROM simpatizantes_validaciones WHERE simpatizante_id = ?", [$id]);
        }
        return $ok;
    }

    // ------------------------------------------------------------------ duplicados

    /** Campos que, si coinciden, impiden guardar (duplicado "duro"). */
    const CAMPOS_DUROS = ['clave_elector', 'curp', 'whatsapp', 'email'];

    /**
     * Busca registros que se parezcan al que se va a guardar.
     * Cada resultado trae 'campos' (qué coincide) y 'tipo':
     *   'duro'    → clave de elector, CURP, WhatsApp o correo repetidos (no se debe guardar)
     *   'posible' → mismo teléfono, o mismo nombre en la misma sección (se avisa, pero se puede guardar)
     */
    public function buscarDuplicados($datos, $excludeId = null) {
        $cond = [];
        $params = [];
        foreach (['clave_elector', 'curp', 'whatsapp', 'email', 'telefono'] as $campo) {
            $v = trim((string)($datos[$campo] ?? ''));
            if ($v !== '') {
                $cond[] = "$campo = ?";
                $params[] = $v;
            }
        }
        $nombre = trim((string)($datos['nombre_completo'] ?? ''));
        $seccion = trim((string)($datos['seccion_electoral'] ?? ''));
        if ($nombre !== '' && $seccion !== '') {
            $cond[] = "(nombre_completo = ? AND seccion_electoral = ?)";
            $params[] = $nombre;
            $params[] = $seccion;
        }
        if (empty($cond)) {
            return [];
        }

        $sql = "SELECT id, nombre_completo, seccion_electoral, clave_elector, curp, whatsapp, telefono, email,
                       capturista_id, campana_id, created_at
                FROM simpatizantes WHERE (" . implode(' OR ', $cond) . ")";
        if ($excludeId) {
            $sql .= " AND id <> ?";
            $params[] = (int)$excludeId;
        }
        $sql .= " ORDER BY id LIMIT 10";

        $filas = $this->db->query($sql, $params) ?: [];
        $resultado = [];
        foreach ($filas as $f) {
            $campos = [];
            foreach (['clave_elector', 'curp', 'whatsapp', 'email', 'telefono'] as $campo) {
                $v = trim((string)($datos[$campo] ?? ''));
                if ($v !== '' && strcasecmp($v, (string)$f[$campo]) === 0) {
                    $campos[] = $campo;
                }
            }
            if ($nombre !== '' && $seccion !== '' && $seccion === (string)$f['seccion_electoral']
                && vd_sin_acentos($nombre) === vd_sin_acentos($f['nombre_completo'])) {
                $campos[] = 'nombre';
            }
            if (empty($campos)) {
                continue;
            }
            $f['campos'] = $campos;
            $f['tipo'] = count(array_intersect($campos, self::CAMPOS_DUROS)) > 0 ? 'duro' : 'posible';
            $resultado[] = $f;
        }
        return $resultado;
    }

    /** Texto legible de los campos coincidentes: "la misma clave de elector y la misma CURP". */
    public static function describirCampos(array $campos) {
        $nombres = [
            'clave_elector' => 'la misma clave de elector', 'curp' => 'la misma CURP',
            'whatsapp' => 'el mismo WhatsApp', 'email' => 'el mismo correo',
            'telefono' => 'el mismo teléfono', 'nombre' => 'el mismo nombre en la misma sección'
        ];
        $lista = array_map(function ($c) use ($nombres) { return $nombres[$c] ?? $c; }, $campos);
        $ultimo = array_pop($lista);
        return $lista ? implode(', ', $lista) . ' y ' . $ultimo : $ultimo;
    }

    /**
     * Para importaciones: de una lista de valores, cuáles ya existen en la base.
     * @return array valor en mayúsculas => id del registro existente
     */
    public function valoresExistentes($campo, array $valores) {
        if (!in_array($campo, ['clave_elector', 'curp', 'whatsapp', 'email', 'telefono'], true)) {
            return [];
        }
        $valores = array_values(array_unique(array_filter(array_map('trim', $valores), 'strlen')));
        $encontrados = [];
        foreach (array_chunk($valores, 500) as $lote) {
            $sql = "SELECT id, $campo AS valor FROM simpatizantes WHERE $campo IN ("
                 . implode(',', array_fill(0, count($lote), '?')) . ")";
            foreach ($this->db->query($sql, $lote) ?: [] as $r) {
                $encontrados[strtoupper($r['valor'])] = (int)$r['id'];
            }
        }
        return $encontrados;
    }

    /**
     * Para importaciones: nombres ya registrados en las secciones dadas.
     * @return array "SECCION|NOMBRE SIN ACENTOS" => id
     */
    public function nombresPorSeccion(array $secciones) {
        $secciones = array_values(array_unique(array_filter($secciones, 'strlen')));
        $mapa = [];
        foreach (array_chunk($secciones, 500) as $lote) {
            $sql = "SELECT id, seccion_electoral, nombre_completo FROM simpatizantes WHERE seccion_electoral IN ("
                 . implode(',', array_fill(0, count($lote), '?')) . ")";
            foreach ($this->db->query($sql, $lote) ?: [] as $r) {
                $mapa[$r['seccion_electoral'] . '|' . vd_sin_acentos($r['nombre_completo'])] = (int)$r['id'];
            }
        }
        return $mapa;
    }

    // ------------------------------------------------------------ estado de validación

    /**
     * Cambia el estado de validación y deja constancia (quién, cuándo y por qué).
     * El rechazo exige un motivo.
     */
    public function cambiarEstatus($id, $nuevo, $motivo, $usuarioId) {
        if (!$this->tieneEstatus()) {
            return ['error' => 'Falta ejecutar la migración del Sprint 2 (database/sprint2_calidad_datos.sql).'];
        }
        if (!in_array($nuevo, self::ESTATUS, true)) {
            return ['error' => 'Estado de validación no válido'];
        }
        $motivo = trim((string)$motivo);
        if ($nuevo === 'rechazado' && $motivo === '') {
            return ['error' => 'Indique el motivo del rechazo'];
        }
        if (mb_strlen($motivo) > 255) {
            return ['error' => 'El motivo no puede pasar de 255 caracteres'];
        }

        $actual = $this->db->queryOne("SELECT estatus_validacion FROM simpatizantes WHERE id = ?", [$id]);
        if (!$actual) {
            return ['error' => 'Simpatizante no encontrado'];
        }
        if ($actual['estatus_validacion'] === $nuevo && $nuevo !== 'rechazado') {
            return ['error' => 'El simpatizante ya está en ese estado'];
        }

        $this->db->beginTransaction();
        $ok = $this->db->execute(
            "UPDATE simpatizantes SET estatus_validacion = ?, motivo_rechazo = ?, validado = ? WHERE id = ?",
            [$nuevo, $nuevo === 'rechazado' ? $motivo : null, $nuevo === 'validado' ? 1 : 0, $id]
        );
        $ok = $ok && $this->registrarHistorial($id, $actual['estatus_validacion'], $nuevo, $motivo !== '' ? $motivo : null, $usuarioId);
        if (!$ok) {
            $this->db->rollback();
            return ['error' => $this->db->describirError('cambiar el estado de validación')];
        }
        $this->db->commit();
        CacheService::invalidar();
        return ['success' => true, 'anterior' => $actual['estatus_validacion']];
    }

    private function registrarHistorial($id, $anterior, $nuevo, $motivo, $usuarioId) {
        return $this->db->execute(
            "INSERT INTO simpatizantes_validaciones (simpatizante_id, estatus_anterior, estatus_nuevo, motivo, usuario_id)
             VALUES (?, ?, ?, ?, ?)",
            [$id, $anterior, $nuevo, $motivo, $usuarioId]
        );
    }

    /** Historial de cambios de estado, del más reciente al más antiguo. */
    public function historialValidacion($id) {
        $filas = $this->db->query(
            "SELECT h.*, u.nombre_completo AS usuario_nombre
             FROM simpatizantes_validaciones h
             LEFT JOIN usuarios u ON h.usuario_id = u.id
             WHERE h.simpatizante_id = ?
             ORDER BY h.created_at DESC, h.id DESC LIMIT 50",
            [$id]
        );
        return $filas ?: [];
    }

    /** Conteo por estado de validación con los mismos filtros del listado (sin el filtro de estado). */
    public function contarPorEstatus($filtros = []) {
        unset($filtros['estatus']);
        [$whereClause, $params] = $this->condiciones($filtros);
        $col = $this->tieneEstatus() ? 's.estatus_validacion' : "(CASE WHEN s.validado = 1 THEN 'validado' ELSE 'pendiente' END)";
        $filas = $this->db->query("SELECT $col AS estatus, COUNT(*) AS n FROM simpatizantes s $whereClause GROUP BY estatus", $params) ?: [];
        $r = ['pendiente' => 0, 'validado' => 0, 'rechazado' => 0];
        foreach ($filas as $f) { $r[$f['estatus']] = (int)$f['n']; }
        return $r;
    }

    /**
     * Obtiene datos para mapa de calor (hasta 20,000 puntos, los más recientes).
     * Acepta los mismos filtros que el listado (campaña, fechas, estado, sección, ciudad…).
     */
    public function obtenerParaMapaCalor($filtros = []) {
        [$whereClause, $params] = $this->condiciones($filtros);
        $whereClause .= ($whereClause === '' ? 'WHERE ' : ' AND ') . 's.latitud IS NOT NULL AND s.longitud IS NOT NULL';

        $sql = "SELECT s.latitud, s.longitud, s.nombre_completo, s.seccion_electoral, s.created_at
                FROM simpatizantes s
                $whereClause
                ORDER BY s.id DESC
                LIMIT 20000";

        return $this->db->query($sql, $params);
    }

    /**
     * Obtiene estadísticas por sección electoral
     */
    public function obtenerEstadisticasPorSeccion($filtros = []) {
        [$whereClause, $params] = $this->condiciones($filtros);
        $sql = "SELECT s.seccion_electoral, COUNT(*) as total
                FROM simpatizantes s
                $whereClause
                GROUP BY s.seccion_electoral
                ORDER BY total DESC";
        return $this->db->query($sql, $params);
    }

    /**
     * Obtiene estadísticas por capturista
     */
    public function obtenerEstadisticasPorCapturista($filtros = []) {
        [$whereClause, $params] = $this->condiciones($filtros);
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
